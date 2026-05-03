<?php

namespace App\Controllers\Webhooks;

use App\Controllers\BaseController;
use App\Libraries\Flutterwave;

/**
 * Flutterwave webhook controller.
 *
 * Handles incoming webhook events from Flutterwave:
 *   - charge.completed (successful)  → order payment / subscription / additional charges
 *   - charge.completed (failed)      → mark order/transaction as failed
 *
 * Follows the same pattern as StripeWebhook, PaystackWebhook, etc.
 * Separated from Webhooks.php for clarity and maintainability.
 *
 * IMPORTANT: Webhook signature verification is preserved exactly as in the
 * original Webhooks.php to ensure the webhook continues to work correctly.
 */
class FlutterwaveWebhook extends BaseController
{
    /** @var string Default language for notifications (no request context in webhooks) */
    protected $defaultLanguage;

    /** @var array General settings (timezone, currency, etc.) */
    protected $settings;

    public function __construct()
    {
        helper('api');
        helper('function');
        $this->settings        = get_settings('general_settings', true);
        date_default_timezone_set($this->settings['system_timezone'] ?? 'UTC');
        $this->defaultLanguage = get_default_language();
    }

    // =========================================================
    // Main entry point
    // =========================================================

    /**
     * Flutterwave POSTs a JSON body to this endpoint.
     *
     * Flow:
     *   1. Read raw body and decode payload.
     *   2. Verify the request signature against our stored secret hash.
     *      (Verification is kept exactly as in the original implementation.)
     *   3. Verify the transaction with Flutterwave's API.
     *   4. Parse common fields once (txn_id, order context, amount, currency).
     *   5. Dispatch to the correct private handler based on event + status.
     */
    public function index()
    {
        log_message('error', '[FLUTTERWAVE WEBHOOK] ===== Incoming request =====');

        // --- Step 1: Read & decode payload ---
        $request_body = file_get_contents('php://input');
        $event        = json_decode($request_body, false); // stdClass to match original behaviour

        log_message('error', '[FLUTTERWAVE WEBHOOK] Payload --> ' . var_export($event, true));

        // --- Step 2: Signature verification ---
        // NOTE: This logic is preserved exactly from the original Webhooks::flutterwave().
        // Flutterwave sends its secret hash in the server variables.
        // We compare it against the secret_hash stored in our Flutterwave credentials.
        $flutterwave        = new Flutterwave();
        $credentials        = $flutterwave->get_credentials();
        $local_secret_hash  = $credentials['secret_hash'];

        // Check both $_SERVER and env() for the incoming hash header
        $signature = (isset($_SERVER['FLUTTERWAVE_SECRET_KEY'])) ? $_SERVER['FLUTTERWAVE_SECRET_KEY'] : '';

        log_message('error', '[FLUTTERWAVE WEBHOOK] Header signature --> ' . var_export($signature, true));

        if (empty($signature) || $signature !== $local_secret_hash) {
            // Invalid or missing signature — reject the request silently
            log_message('error', '[FLUTTERWAVE WEBHOOK] Invalid signature. Payload --> ' . var_export($event, true));
            return false;
        }

        // --- Step 3: Verify transaction with Flutterwave API ---
        $verify_raw = $flutterwave->verify_transaction($event->data->id);
        $response   = json_decode($verify_raw);

        log_message('error', '[FLUTTERWAVE WEBHOOK] Verified response --> ' . var_export($response, true));

        $status = $response->status ?? '';

        // --- Step 4: Parse common fields ---
        $txn_id     = '';
        $order_id   = 0;
        $user_id    = 0;
        $partner_id = 0;
        $amount     = 0;
        $currency   = '';
        $transaction = [];

        if (!empty($event->data)) {
            $txn_id   = $event->data->id ?? '';
            $amount   = $event->data->amount ?? 0;
            $currency = $event->data->currency ?? '';

            if (!empty($txn_id)) {
                // Try to find an existing transaction by the Flutterwave transaction ID
                $transaction = fetch_details('transactions', ['txn_id' => $txn_id]);
            }

            if (!empty($transaction)) {
                // Found an existing transaction — derive order context from it
                $order_id = (int) $transaction[0]['order_id'];
                $user_id  = (int) $transaction[0]['user_id'];
            } else {
                // No existing transaction — try to resolve order from metadata
                if (!empty($response->data->meta->order_id)) {
                    $order_id   = (int) $response->data->meta->order_id;
                    $order_data = fetch_details('orders', ['id' => $order_id]);
                    if (!empty($order_data)) {
                        $user_id    = (int) $order_data[0]['user_id'];
                        $partner_id = (int) $order_data[0]['partner_id'];
                    }
                }
            }
        } else {
            // Empty data — set safe defaults
            $order_id = 0;
            $amount   = 0;
            $currency = $event->data->currency ?? '';
        }

        // --- Step 5: Dispatch based on event type and charge status ---
        $event_type    = $event->event ?? '';
        $charge_status = $event->data->status ?? '';

        if ($event_type === 'charge.completed' && $charge_status === 'successful') {
            // Payment was successful — determine which type of payment this is
            return $this->handleChargeSuccess($event, $response, $txn_id, $amount, $currency, $order_id, $user_id, $partner_id, $transaction);
        } else {
            // Payment failed or is in an unexpected state
            return $this->handleChargeFailed($event, $response, $txn_id, $order_id, $transaction);
        }
    }

    // =========================================================
    // Successful charge handler — three branches
    // =========================================================

    /**
     * Handle a successful charge.completed event.
     *
     * Branches:
     *   1. Subscription payment   — metadata has `transaction_id`
     *   2. Additional charges     — metadata has `additional_charges_transaction_id`
     *   3. Regular order booking  — everything else
     *
     * @param object $event      Raw webhook event (stdClass)
     * @param object $response   Verified transaction response from Flutterwave API
     * @param string $txn_id     Flutterwave transaction ID
     * @param float  $amount     Charge amount
     * @param string $currency   Charge currency
     * @param int    $order_id   Resolved order ID (may be 0)
     * @param int    $user_id    Resolved user ID
     * @param int    $partner_id Resolved partner/provider ID
     * @param array  $transaction Existing transaction record (may be empty)
     */
    private function handleChargeSuccess(
        object $event,
        object $response,
        string $txn_id,
        float  $amount,
        string $currency,
        int    $order_id,
        int    $user_id,
        int    $partner_id,
        array  $transaction
    ) {
        // --- Branch 1: Subscription payment ---
        // Flutterwave passes our internal transaction_id in meta when it is a subscription payment.
        if (!empty($response->data->meta->transaction_id)) {
            log_message('error', '[FLUTTERWAVE WEBHOOK] Branch: subscription payment. transaction_id: ' . $response->data->meta->transaction_id);
            $this->handleSubscriptionPayment($event, $response, $txn_id);
            return true;
        }

        // --- Branch 2: Additional charges payment ---
        if (!empty($response->data->meta->additional_charges_transaction_id)) {
            log_message('error', '[FLUTTERWAVE WEBHOOK] Branch: additional charges. additional_charges_transaction_id: ' . $response->data->meta->additional_charges_transaction_id);
            $this->handleAdditionalChargesSuccess($event, $response, $txn_id, $order_id);
            return true;
        }

        // --- Branch 3: Regular order booking ---
        log_message('error', '[FLUTTERWAVE WEBHOOK] Branch: order booking. order_id: ' . $order_id);
        return $this->handleOrderPayment($event, $txn_id, $amount, $currency, $order_id, $user_id, $partner_id, $transaction);
    }

    // =========================================================
    // Branch 1: Subscription payment
    // =========================================================

    /**
     * Handle a subscription payment.
     * Marks the transaction as successful, activates the partner subscription,
     * and notifies the admin.
     */
    private function handleSubscriptionPayment(object $event, object $response, string $txn_id): void
    {
        $transaction_id = (int) $response->data->meta->transaction_id;

        log_message('error', '[FLUTTERWAVE WEBHOOK] [subscription] Fetching transaction ID: ' . $transaction_id);

        $transaction_details = fetch_details('transactions', ['id' => $transaction_id]);
        if (empty($transaction_details)) {
            log_message('error', '[FLUTTERWAVE WEBHOOK] [subscription] Transaction not found. Aborting.');
            return;
        }

        $subscription_id = (int) $transaction_details[0]['subscription_id'];
        $partner_id      = (int) $transaction_details[0]['user_id'];

        $subscription_details = fetch_details('subscriptions', ['id' => $subscription_id]);
        if (empty($subscription_details)) {
            log_message('error', '[FLUTTERWAVE WEBHOOK] [subscription] Subscription ' . $subscription_id . ' not found. Aborting.');
            return;
        }

        // Mark transaction as successful and store the reference
        update_details(
            ['status' => 'success', 'txn_id' => $txn_id, 'reference' => $event->data->tx_ref ?? ''],
            ['id' => $transaction_id],
            'transactions'
        );

        log_message('error', '[FLUTTERWAVE WEBHOOK] [subscription] Transaction ' . $transaction_id . ' marked success.');

        // Notify the provider that their subscription payment was received
        send_subscription_payment_status_notification($transaction_id, 'success');

        // Calculate subscription activation dates
        $purchaseDate         = date('Y-m-d');
        $subscriptionDuration = $subscription_details[0]['duration'];
        $expiryDate           = date('Y-m-d', strtotime($purchaseDate . ' + ' . $subscriptionDuration . ' days'));

        if ($subscriptionDuration === 'unlimited') {
            $subscriptionDuration = 0;
        }

        log_message('error', '[FLUTTERWAVE WEBHOOK] [subscription] Activating. purchase_date: ' . $purchaseDate . ' | expiry_date: ' . $expiryDate);

        $update_result = update_details(
            [
                'status'        => 'active',
                'is_payment'    => '1',
                'purchase_date' => $purchaseDate,
                'expiry_date'   => $expiryDate,
                'updated_at'    => date('Y-m-d H:i:s'),
            ],
            [
                'subscription_id' => $subscription_id,
                'partner_id'      => $partner_id,
                'status !='       => 'active',
                'transaction_id'  => $transaction_id,
            ],
            'partner_subscriptions'
        );

        log_message('error', '[FLUTTERWAVE WEBHOOK] [subscription] partner_subscriptions update: ' . ($update_result ? 'success' : 'failed/no-change'));

        // Notify admin when subscription is purchased
        if ($update_result) {
            try {
                $provider_name = get_translated_partner_field($partner_id, 'company_name');
                if (empty($provider_name)) {
                    $partner_data  = fetch_details('partner_details', ['partner_id' => $partner_id], ['company_name']);
                    $provider_name = !empty($partner_data) ? $partner_data[0]['company_name'] : 'Provider';
                }

                $subscription_name = $subscription_details[0]['name'] ?? 'Subscription';
                $transaction_data  = fetch_details('transactions', ['id' => $transaction_id], ['amount']);
                $txn_amount        = !empty($transaction_data) ? $transaction_data[0]['amount'] : '0.00';
                $currency          = get_settings('general_settings', true)['currency'] ?? 'USD';

                queue_notification_service(
                    eventType: 'subscription_purchased',
                    recipients: [],
                    context: [
                        'provider_id'       => $partner_id,
                        'provider_name'     => $provider_name,
                        'subscription_id'   => $subscription_id,
                        'subscription_name' => $subscription_name,
                        'purchase_date'     => date('d-m-Y', strtotime($purchaseDate)),
                        'expiry_date'       => date('d-m-Y', strtotime($expiryDate)),
                        'duration'          => $subscriptionDuration,
                        'amount'            => number_format($txn_amount, 2),
                        'currency'          => $currency,
                        'transaction_id'    => (string) $transaction_id,
                    ],
                    options: [
                        'channels'    => ['fcm', 'email', 'sms'],
                        'user_groups' => [1], // Admin user group
                        'platforms'   => ['admin_panel'],
                    ]
                );

                log_message('error', '[FLUTTERWAVE WEBHOOK] [subscription] Admin notification queued.');
            } catch (\Throwable $e) {
                // Notification failure must not block subscription activation
                log_message('error', '[FLUTTERWAVE WEBHOOK] [subscription] Notification error: ' . $e->getMessage());
            }
        }

        log_message('error', '[FLUTTERWAVE WEBHOOK] [subscription] Handler complete for transaction ' . $transaction_id);
    }

    // =========================================================
    // Branch 2: Additional charges payment (success)
    // =========================================================

    /**
     * Handle a successful additional charges payment.
     * Updates the additional charge transaction and marks it as paid on the order.
     */
    private function handleAdditionalChargesSuccess(object $event, object $response, string $txn_id, int $order_id): void
    {
        $additional_charges_txn_id = (int) $response->data->meta->additional_charges_transaction_id;

        log_message('error', '[FLUTTERWAVE WEBHOOK] [additional_charges] Marking transaction ' . $additional_charges_txn_id . ' as success.');

        update_details(
            ['status' => 'success', 'txn_id' => $txn_id],
            ['id' => $additional_charges_txn_id],
            'transactions'
        );

        // Mark the order's additional charge as paid
        update_details(['payment_status_of_additional_charge' => '1'], ['id' => $order_id], 'orders');

        log_message('error', '[FLUTTERWAVE WEBHOOK] [additional_charges] Order ' . $order_id . ' additional charge status set to paid.');
    }

    // =========================================================
    // Branch 3: Regular order payment (success)
    // =========================================================

    /**
     * Handle a regular order payment.
     *
     * If an existing transaction record is found: just update its status.
     * Otherwise: create a new transaction record and finalise the order.
     */
    private function handleOrderPayment(
        object $event,
        string $txn_id,
        float  $amount,
        string $currency,
        int    $order_id,
        int    $user_id,
        int    $partner_id,
        array  $transaction
    ) {
        $order = fetch_details('orders', ['id' => $order_id]);
        log_message('error', '[FLUTTERWAVE WEBHOOK] [order] Order --> ' . var_export($order, true));

        if (empty($order_id)) {
            // No order ID found — tell Flutterwave to retry later (non-200)
            log_message('error', '[FLUTTERWAVE WEBHOOK] [order] order_id is empty — cannot finalise order.');
            return false;
        }

        if (!empty($transaction)) {
            // Existing transaction — just update status to success
            $transaction_id = (int) $transaction[0]['id'];
            update_details(['status' => 'success'], ['id' => $transaction_id], 'transactions');
            log_message('error', '[FLUTTERWAVE WEBHOOK] [order] Existing transaction ' . $transaction_id . ' marked success.');
            return true;
        }

        // No prior transaction — create one and finalise the order.
        // Note: original code divided amount by 100; keeping that for backward compatibility.
        $insert_id = add_transaction([
            'transaction_type' => 'transaction',
            'user_id'          => $user_id,
            'partner_id'       => $partner_id,
            'order_id'         => $order_id,
            'type'             => 'flutterwave',
            'txn_id'           => $txn_id,
            'amount'           => ($amount / 100),
            'status'           => 'success',
            'currency_code'    => $currency,
            'message'          => 'Order placed successfully',
            'reference'        => $event->data->tx_ref ?? '',
        ]);

        if ($insert_id) {
            log_message('error', '[FLUTTERWAVE WEBHOOK] [order] Transaction inserted (ID: ' . $insert_id . '). Finalising order ' . $order_id . '.');
            update_details(['payment_status' => 1], ['id' => $order_id], 'orders');
            update_custom_job_status($order_id, 'booked');
            $this->send_booking_notifications($order_id, $user_id, $partner_id);

            return $this->response->setJSON([
                'error'              => false,
                'transaction_status' => 'flutterwave',
                'message'            => 'Transaction successfully done',
            ]);
        }

        log_message('error', '[FLUTTERWAVE WEBHOOK] [order] Failed to insert transaction for order ' . $order_id);
        return $this->response->setJSON(['error' => true, 'message' => 'Something went wrong']);
    }

    // =========================================================
    // Failed charge handler
    // =========================================================

    /**
     * Handle a failed/non-successful charge.
     *
     * Branches:
     *   1. Additional charges failure — metadata has `additional_charges_transaction_id`
     *   2. Regular order failure      — cancel order and update transaction
     */
    private function handleChargeFailed(
        object $event,
        object $response,
        string $txn_id,
        int    $order_id,
        array  $transaction
    ) {
        // --- Branch 1: Additional charges failure ---
        if (!empty($response->data->meta->additional_charges_transaction_id)) {
            $additional_charges_txn_id = (int) $response->data->meta->additional_charges_transaction_id;

            log_message('error', '[FLUTTERWAVE WEBHOOK] [failed] Additional charges failed. Transaction: ' . $additional_charges_txn_id);

            update_details(['status' => 'failed', 'txn_id' => $txn_id], ['id' => $additional_charges_txn_id], 'transactions');
            update_details(['payment_status_of_additional_charge' => '2'], ['id' => $order_id], 'orders');

            return true;
        }

        // --- Branch 2: Regular order failure ---
        log_message('error', '[FLUTTERWAVE WEBHOOK] [failed] Order payment failed. order_id: ' . $order_id);

        if (!empty($order_id) && is_numeric($order_id)) {
            // Cancel the order
            update_details(['status' => 'cancelled'], ['id' => $order_id], 'orders');
        }

        // Update job status (works even when order_id is empty)
        update_custom_job_status($order_id, 'cancelled');

        // Update existing transaction record if one exists
        if (!empty($transaction)) {
            $transaction_id = (int) $transaction[0]['id'];
            update_details(['status' => 'failed'], ['id' => $transaction_id], 'transactions');
            update_details(['payment_status' => 2], ['id' => $order_id], 'orders');
        }

        $failed_response = [
            'error'              => true,
            'transaction_status' => $event->event ?? '',
            'message'            => 'Transaction could not be detected.',
        ];
        echo json_encode($failed_response);
        return false;
    }

    // =========================================================
    // Notification helpers
    // =========================================================

    /**
     * Send booking notifications to provider and customer.
     * Called after a new order is successfully paid via Flutterwave.
     *
     * @param int $order_id   The order ID
     * @param int $user_id    The customer user ID
     * @param int $partner_id The provider/partner user ID
     */
    private function send_booking_notifications(int $order_id, int $user_id, int $partner_id): void
    {
        try {
            $order_data = fetch_details('orders', ['id' => $order_id]);
            if (empty($order_data)) {
                log_message('error', '[FLUTTERWAVE WEBHOOK] [notifications] Order ' . $order_id . ' not found.');
                return;
            }

            $final_total = $order_data[0]['total'] ?? 0;
            $language    = $this->defaultLanguage;

            $notificationContext = [
                'provider_id' => $partner_id,
                'user_id'     => $user_id,
                'booking_id'  => $order_id,
                'amount'      => $final_total,
            ];

            // Notify provider about new booking
            queue_notification_service(
                eventType: 'new_booking_received_for_provider',
                recipients: ['user_id' => $partner_id],
                context: $notificationContext,
                options: [
                    'channels'  => ['fcm', 'email', 'sms'],
                    'language'  => $language,
                    'platforms' => ['android', 'ios', 'web', 'provider_panel'],
                ]
            );

            // Notify customer about booking confirmation
            queue_notification_service(
                eventType: 'new_booking_confirmation_to_customer',
                recipients: ['user_id' => $user_id],
                context: $notificationContext,
                options: [
                    'channels'  => ['fcm', 'email', 'sms'],
                    'language'  => $language,
                    'platforms' => ['android', 'ios', 'web'],
                ]
            );

            log_message('error', '[FLUTTERWAVE WEBHOOK] [notifications] Booking notifications queued for order ' . $order_id);
        } catch (\Throwable $e) {
            // Notification failure must not block webhook processing
            log_message('error', '[FLUTTERWAVE WEBHOOK] [notifications] Exception: ' . $e->getMessage());
        }
    }
}
