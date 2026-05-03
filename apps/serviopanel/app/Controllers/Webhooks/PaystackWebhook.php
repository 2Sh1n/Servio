<?php

namespace App\Controllers\Webhooks;

use App\Controllers\BaseController;
/**
 * Paystack webhook controller.
 * Handles payment events from Paystack:
 *   - charge.success         (orders, subscriptions, additional charges)
 *   - charge.dispute.create  (disputes — sets order to awaiting)
 *   - refund.processed       (refund records)
 *   - all other events       (marks additional-charge transactions as failed if present)
 *
 * Same pattern as StripeWebhook and RazorpayWebhook:
 *   index() parses + dispatches → private handlers do the work.
 */
class PaystackWebhook extends BaseController
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
     * Paystack POSTs a JSON body to this endpoint.
     *
     * Flow:
     *   1. Read raw body.
     *   2. Guard against empty / invalid payload.
     *   3. Parse common fields once (txn_id, amount, currency, order context).
     *   4. Dispatch to the correct private handler.
     */
    public function index()
    {
        $raw_body = file_get_contents('php://input');
        $event    = json_decode($raw_body, true);

        log_message('info', '[PAYSTACK WEBHOOK] ===== Incoming request =====');

        // --- Guard: reject empty or malformed payloads immediately ---
        if (empty($event) || !is_array($event)) {
            log_message('error', '[PAYSTACK WEBHOOK] Rejected: empty or invalid JSON payload');
            return $this->response->setStatusCode(400)->setJSON(['error' => true, 'message' => 'Invalid payload']);
        }

        $event_type = $event['event'] ?? 'unknown';
        log_message('info', '[PAYSTACK WEBHOOK] Event type: ' . $event_type);

        // --- Parse common fields once ---
        // txn_id is the Paystack charge/transaction ID (data.id), stored as our txn_id.
        $txn_id   = (string) ($event['data']['id']       ?? '');
        $amount   = (float)  ($event['data']['amount']   ?? 0);
        $currency = (string) ($event['data']['currency'] ?? '');

        log_message('info', '[PAYSTACK WEBHOOK] txn_id: ' . ($txn_id ?: 'n/a') . ' | amount: ' . $amount . ' | currency: ' . $currency);

        // --- Resolve order context (order_id, user_id, partner_id) ---
        // Strategy: look for an existing transaction record first, then fall back to metadata.
        $transaction = [];
        $order_id    = 0;
        $user_id     = 0;
        $partner_id  = 0;

        if (!empty($txn_id)) {
            $transaction = fetch_details('transactions', ['txn_id' => $txn_id]);
        }

        if (!empty($transaction)) {
            // Existing transaction found — derive order from it.
            // Also fetch order to get partner_id (transaction row may not store it reliably).
            $order_id   = (int) $transaction[0]['order_id'];
            $user_id    = (int) $transaction[0]['user_id'];
            $order_data = fetch_details('orders', ['id' => $order_id]);
            $partner_id = !empty($order_data) ? (int) $order_data[0]['partner_id'] : 0;
            log_message('info', '[PAYSTACK WEBHOOK] Context from existing transaction — order_id: ' . $order_id . ' | user_id: ' . $user_id . ' | partner_id: ' . $partner_id);

        } elseif (!empty($event['data']['metadata']['order_id'])) {
            // No existing transaction — derive from Paystack metadata.
            $order_id   = (int) $event['data']['metadata']['order_id'];
            $order_data = fetch_details('orders', ['id' => $order_id]);
            $user_id    = !empty($order_data) ? (int) $order_data[0]['user_id']    : 0;
            $partner_id = !empty($order_data) ? (int) $order_data[0]['partner_id'] : 0;
            log_message('info', '[PAYSTACK WEBHOOK] Context from metadata — order_id: ' . $order_id . ' | user_id: ' . $user_id . ' | partner_id: ' . $partner_id);
        }

        // --- Dispatch ---
        switch ($event_type) {
            case 'charge.success':
                return $this->handleChargeSuccess($event, $txn_id, $amount, $currency, $order_id, $user_id, $partner_id, $transaction);

            case 'charge.dispute.create':
                return $this->handleChargeDisputeCreate($order_id, $transaction);

            case 'refund.processed':
                return $this->handleRefundProcessed($txn_id, $amount, $currency, $order_id, $user_id, $partner_id);

            default:
                // For any unrecognised event, handle potential additional-charge failure then return 200.
                return $this->handleDefaultEvent($event, $txn_id, $order_id);
        }
    }

    // =========================================================
    // Event: charge.success
    // Three branches: subscription / additional charge / regular order
    // =========================================================

    /**
     * Handle charge.success.
     * Branches on metadata to determine which type of payment succeeded.
     */
    private function handleChargeSuccess(
        array  $event,
        string $txn_id,
        float  $amount,
        string $currency,
        int    $order_id,
        int    $user_id,
        int    $partner_id,
        array  $transaction
    ) {
        log_message('info', '[PAYSTACK WEBHOOK] [charge.success] Processing.');

        // --- Branch 1: Subscription payment ---
        // Paystack passes our internal transaction_id in metadata when it is a subscription payment.
        $subscription_transaction_id = (int) ($event['data']['metadata']['transaction_id'] ?? 0);
        if (!empty($subscription_transaction_id)) {
            log_message('info', '[PAYSTACK WEBHOOK] [charge.success] Branch: subscription. transaction_id: ' . $subscription_transaction_id);
            $this->handleSubscriptionPayment($txn_id, $subscription_transaction_id);
            return $this->response->setJSON(['error' => false, 'message' => 'Subscription processed']);
        }

        // --- Branch 2: Additional charge payment ---
        $additional_charges_txn_id = (int) ($event['data']['metadata']['additional_charges_transaction_id'] ?? 0);
        if (!empty($additional_charges_txn_id)) {
            log_message('info', '[PAYSTACK WEBHOOK] [charge.success] Branch: additional charge. additional_charges_txn_id: ' . $additional_charges_txn_id);

            update_details(
                ['status' => 'success', 'txn_id' => $txn_id, 'reference' => $event['data']['reference'] ?? ''],
                ['id' => $additional_charges_txn_id],
                'transactions'
            );
            update_details(['payment_status_of_additional_charge' => '1'], ['id' => $order_id], 'orders');

            log_message('info', '[PAYSTACK WEBHOOK] [charge.success] Additional charge transaction ' . $additional_charges_txn_id . ' marked success. Order ' . $order_id . ' updated.');
            return $this->response->setJSON(['error' => false, 'message' => 'Additional charge processed']);
        }

        // --- Branch 3: Regular order booking ---
        log_message('info', '[PAYSTACK WEBHOOK] [charge.success] Branch: order booking. order_id: ' . $order_id);

        if (empty($order_id)) {
            // No order ID found — tell Paystack to retry later (non-200 response).
            log_message('error', '[PAYSTACK WEBHOOK] [charge.success] order_id is empty — cannot finalise order.');
            return $this->response->setStatusCode(304)->setJSON(['error' => true, 'message' => 'Order/transaction id not found']);
        }

        if (!empty($transaction)) {
            // Existing transaction record — just update its status to success.
            $transaction_id = (int) $transaction[0]['id'];
            update_details(['status' => 'success'], ['id' => $transaction_id], 'transactions');
            log_message('info', '[PAYSTACK WEBHOOK] [charge.success] Existing transaction ' . $transaction_id . ' marked success.');
            return $this->response->setJSON(['error' => false, 'message' => 'Transaction updated']);
        }

        // No prior transaction — create one and finalise the order.
        $insert_id = add_transaction([
            'transaction_type' => 'transaction',
            'user_id'          => $user_id,
            'partner_id'       => $partner_id,
            'order_id'         => $order_id,
            'type'             => 'paystack',
            'txn_id'           => $txn_id,
            'amount'           => $amount,
            'status'           => 'success',
            'currency_code'    => $currency,
            'message'          => 'Order placed successfully',
            'reference'        => $event['data']['reference'] ?? '',
        ]);

        if ($insert_id) {
            log_message('info', '[PAYSTACK WEBHOOK] [charge.success] Transaction inserted (ID: ' . $insert_id . '). Finalising order ' . $order_id . '.');
            update_details(['payment_status' => 1], ['id' => $order_id], 'orders');
            update_custom_job_status($order_id, 'booked');
            $this->send_booking_notifications($order_id, $user_id, $partner_id);
            return $this->response->setJSON([
                'error'              => false,
                'transaction_status' => 'paystack',
                'message'            => 'Transaction successfully done',
            ]);
        }

        log_message('error', '[PAYSTACK WEBHOOK] [charge.success] Failed to insert transaction for order ' . $order_id);
        return $this->response->setJSON(['error' => true, 'message' => 'Something went wrong']);
    }

    // =========================================================
    // Subscription payment (called from charge.success branch 1)
    // =========================================================

    /**
     * Handle a subscription payment.
     * Marks the transaction as success, activates the partner subscription, notifies admin.
     */
    private function handleSubscriptionPayment(string $txn_id, int $transaction_id): void
    {
        log_message('info', '[PAYSTACK WEBHOOK] [subscription] Looking up transaction ID: ' . $transaction_id);

        $transaction_details = fetch_details('transactions', ['id' => $transaction_id]);
        if (empty($transaction_details)) {
            log_message('error', '[PAYSTACK WEBHOOK] [subscription] Transaction ID ' . $transaction_id . ' not found. Aborting.');
            return;
        }

        $subscription_id = (int) $transaction_details[0]['subscription_id'];
        $partner_id      = (int) $transaction_details[0]['user_id'];
        log_message('info', '[PAYSTACK WEBHOOK] [subscription] subscription_id: ' . $subscription_id . ' | partner_id: ' . $partner_id);

        $subscription = fetch_details('subscriptions', ['id' => $subscription_id]);
        if (empty($subscription)) {
            log_message('error', '[PAYSTACK WEBHOOK] [subscription] Subscription ' . $subscription_id . ' not found. Aborting.');
            return;
        }

        // Mark transaction as successful
        update_details(['status' => 'success', 'txn_id' => $txn_id], ['id' => $transaction_id], 'transactions');
        log_message('info', '[PAYSTACK WEBHOOK] [subscription] Transaction ' . $transaction_id . ' marked success.');

        send_subscription_payment_status_notification($transaction_id, 'success');

        // Calculate subscription dates
        $purchaseDate         = date('Y-m-d');
        $subscriptionDuration = $subscription[0]['duration'];
        $expiryDate           = date('Y-m-d', strtotime($purchaseDate . ' + ' . $subscriptionDuration . ' days'));

        if ($subscriptionDuration === 'unlimited') {
            $subscriptionDuration = 0;
        }

        log_message('info', '[PAYSTACK WEBHOOK] [subscription] Activating subscription. purchase_date: ' . $purchaseDate . ' | expiry_date: ' . $expiryDate);

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

        log_message('info', '[PAYSTACK WEBHOOK] [subscription] partner_subscriptions update result: ' . ($update_result ? 'success' : 'failed/no-change'));

        // Notify admin when a subscription is purchased
        if ($update_result) {
            try {
                $provider_name = get_translated_partner_field($partner_id, 'company_name');
                if (empty($provider_name)) {
                    $partner_data  = fetch_details('partner_details', ['partner_id' => $partner_id], ['company_name']);
                    $provider_name = !empty($partner_data) ? $partner_data[0]['company_name'] : 'Provider';
                }

                $subscription_name = $subscription[0]['name'] ?? 'Subscription';
                $transaction_data  = fetch_details('transactions', ['id' => $transaction_id], ['amount']);
                $amount            = !empty($transaction_data) ? $transaction_data[0]['amount'] : '0.00';
                $currency          = get_settings('general_settings', true)['currency'] ?? 'USD';

                log_message('info', '[PAYSTACK WEBHOOK] [subscription] Queuing admin notification. Provider: ' . $provider_name . ' | Plan: ' . $subscription_name);

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
                        'amount'            => number_format($amount, 2),
                        'currency'          => $currency,
                        'transaction_id'    => (string) $transaction_id,
                    ],
                    options: [
                        'channels'    => ['fcm', 'email', 'sms'],
                        'user_groups' => [1], // Admin user group
                        'platforms'   => ['admin_panel'],
                    ]
                );
            } catch (\Throwable $e) {
                // Notification failure must not block subscription activation
                log_message('error', '[PAYSTACK WEBHOOK] [subscription] Notification error: ' . $e->getMessage());
            }
        }

        log_message('info', '[PAYSTACK WEBHOOK] [subscription] Handler complete for transaction ' . $transaction_id);
    }

    // =========================================================
    // Event: charge.dispute.create
    // =========================================================

    /**
     * Handle charge.dispute.create.
     * Sets the order to "awaiting" and the transaction to "pending".
     */
    private function handleChargeDisputeCreate(int $order_id, array $transaction)
    {
        log_message('info', '[PAYSTACK WEBHOOK] [charge.dispute.create] Processing. order_id: ' . $order_id);

        if (!empty($order_id)) {
            $order         = fetch_details('orders', ['id' => $order_id]);
            $active_status = $order[0]['active_status'] ?? '';

            // Only roll back orders that were already received or processed
            if (in_array($active_status, ['received', 'processed'])) {
                update_details(['status' => 'awaiting'], ['id' => $order_id], 'orders');
                update_custom_job_status($order_id, 'pending');
                log_message('info', '[PAYSTACK WEBHOOK] [charge.dispute.create] Order ' . $order_id . ' set to awaiting (was: ' . $active_status . ').');
            }

            if (!empty($transaction)) {
                $transaction_id = (int) $transaction[0]['id'];
                update_details(['status' => 'pending'], ['id' => $transaction_id], 'transactions');
                log_message('info', '[PAYSTACK WEBHOOK] [charge.dispute.create] Transaction ' . $transaction_id . ' set to pending.');
            }
        }

        return $this->response->setJSON(['error' => false, 'message' => 'Processed']);
    }

    // =========================================================
    // Event: refund.processed
    // =========================================================

    /**
     * Handle refund.processed.
     * Updates an existing refund record to "processed", or creates a new one.
     */
    private function handleRefundProcessed(
        string $txn_id,
        float  $amount,
        string $currency,
        int    $order_id,
        int    $user_id,
        int    $partner_id
    ) {
        log_message('info', '[PAYSTACK WEBHOOK] [refund.processed] Processing. txn_id: ' . $txn_id . ' | order_id: ' . $order_id);

        // A refund only makes sense if a matching successful transaction exists
        $success_transaction = fetch_details('transactions', [
            'transaction_type' => 'transaction',
            'type'             => 'paystack',
            'status'           => 'success',
            'txn_id'           => $txn_id,
        ]);

        if (empty($success_transaction)) {
            log_message('warning', '[PAYSTACK WEBHOOK] [refund.processed] No matching success transaction for txn_id: ' . $txn_id . '. Skipping.');
            return $this->response->setStatusCode(200)->setBody('');
        }

        log_message('info', '[PAYSTACK WEBHOOK] [refund.processed] Found success transaction ID: ' . $success_transaction[0]['id']);

        // Check for an existing refund record (idempotency — Paystack may retry)
        $existing_refund = fetch_details('transactions', [
            'transaction_type' => 'refund',
            'type'             => 'paystack',
            'message'          => 'paystack_refund',
            'txn_id'           => $txn_id,
        ]);

        if (!empty($existing_refund)) {
            update_details(['status' => 'processed'], ['id' => $existing_refund[0]['id']], 'transactions');
            log_message('info', '[PAYSTACK WEBHOOK] [refund.processed] Existing refund transaction ' . $existing_refund[0]['id'] . ' updated to processed.');
        } else {
            $insert_id = add_transaction([
                'transaction_type' => 'refund',
                'user_id'          => $user_id,
                'partner_id'       => $partner_id,
                'order_id'         => $order_id,
                'type'             => 'paystack',
                'txn_id'           => $txn_id,
                'amount'           => $amount,
                'status'           => 'processed',
                'currency_code'    => $currency,
                'message'          => 'paystack_refund',
            ]);
            log_message('info', '[PAYSTACK WEBHOOK] [refund.processed] Refund transaction inserted (ID: ' . ($insert_id ?: 'failed') . ').');
            update_custom_job_status($order_id, 'refunded');
            log_message('info', '[PAYSTACK WEBHOOK] [refund.processed] Order ' . $order_id . ' custom job status set to refunded.');
        }

        return $this->response->setStatusCode(200)->setBody('');
    }

    // =========================================================
    // Default event handler
    // =========================================================

    /**
     * Handle any unrecognised event type.
     * If the event carries additional_charges_transaction_id metadata, mark it as failed.
     * Always returns 200 so Paystack stops retrying.
     */
    private function handleDefaultEvent(array $event, string $txn_id, int $order_id)
    {
        $event_type                = $event['event'] ?? 'unknown';
        $additional_charges_txn_id = (int) ($event['data']['metadata']['additional_charges_transaction_id'] ?? 0);

        if (!empty($additional_charges_txn_id)) {
            log_message('info', '[PAYSTACK WEBHOOK] [' . $event_type . '] Marking additional charge transaction ' . $additional_charges_txn_id . ' as failed.');
            update_details(
                ['status' => 'failed', 'txn_id' => $txn_id, 'reference' => $event['data']['reference'] ?? ''],
                ['id' => $additional_charges_txn_id],
                'transactions'
            );
            update_details(['payment_status_of_additional_charge' => '2'], ['id' => $order_id], 'orders');
        } else {
            log_message('info', '[PAYSTACK WEBHOOK] Unhandled event type: ' . $event_type . '. Returning 200.');
        }

        return $this->response->setStatusCode(200)->setBody('');
    }

    // =========================================================
    // Notification helpers
    // =========================================================

    /**
     * Send booking notifications to provider and customer.
     * Called after a new order is successfully paid via Paystack.
     *
     * @param array $order_data Pre-fetched order record. If empty, fetched internally.
     */
    private function send_booking_notifications(int $order_id, int $user_id, int $partner_id, array $order_data = []): void
    {
        try {
            // Fall back to fetching order if caller did not supply it
            if (empty($order_data)) {
                $order_data = fetch_details('orders', ['id' => $order_id]);
            }
            if (empty($order_data)) {
                log_message('error', '[PAYSTACK WEBHOOK] [notifications] Order ' . $order_id . ' not found. Cannot send booking notifications.');
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

            log_message('info', '[PAYSTACK WEBHOOK] [notifications] Queuing new_booking_received_for_provider for partner ' . $partner_id . ' | order ' . $order_id);
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

            log_message('info', '[PAYSTACK WEBHOOK] [notifications] Queuing new_booking_confirmation_to_customer for user ' . $user_id . ' | order ' . $order_id);
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

            log_message('info', '[PAYSTACK WEBHOOK] [notifications] Booking notifications queued for order ' . $order_id);
        } catch (\Throwable $e) {
            // Notification failure must not block webhook processing
            log_message('error', '[PAYSTACK WEBHOOK] [notifications] Exception: ' . $e->getMessage());
        }
    }
}
