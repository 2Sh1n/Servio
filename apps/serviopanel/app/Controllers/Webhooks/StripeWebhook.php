<?php

namespace App\Controllers\Webhooks;

use App\Controllers\BaseController;
use App\Libraries\Stripe;
use App\Libraries\StripeMoney;

/**
 * Stripe webhook controller.
 * Handles payment events (charge.succeeded, charge.failed, charge.pending,
 * charge.expired, charge.refunded) from Stripe.
 *
 * Separated from Webhooks.php for clarity — same pattern as RazorpayWebhook and CashfreeWebhook.
 *
 */
class StripeWebhook extends BaseController
{
    private Stripe $stripe;

    /** @var string Default language for notifications (no request context in webhooks) */
    protected $defaultLanguage;

    /** @var array General settings (timezone, currency, etc.) */
    protected $settings;

    public function __construct()
    {
        helper('api');
        helper('function');
        $this->stripe          = new Stripe();
        $this->settings        = get_settings('general_settings', true);
        date_default_timezone_set($this->settings['system_timezone'] ?? 'UTC');
        $this->defaultLanguage = get_default_language();
    }

    /**
     * Main webhook entry point.
     * Stripe sends POST with a JSON body and a `Stripe-Signature` HTTP header.
     *
     * Flow:
     *   1. Read raw body (needed for signature verification — must NOT decode first).
     *   2. Verify signature using construct_event().
     *   3. Decode the event payload and dispatch to the correct handler.
     */
    public function index()
    {
        // Read raw body BEFORE any decoding — Stripe signature validation requires the raw bytes.
        $request_body = file_get_contents('php://input');
        $event        = json_decode($request_body, false); // stdClass; Stripe uses objects

        log_message('info', '[STRIPE WEBHOOK] ===== Incoming request =====');

        // --- Basic payload guard ---
        if (empty($event) || !is_object($event)) {
            log_message('error', '[STRIPE WEBHOOK] Rejected: empty or invalid JSON payload');
            return $this->response->setStatusCode(400)->setJSON(['error' => true, 'message' => 'Invalid payload']);
        }

        // --- Signature verification (MUST remain here) ---
        // Stripe provides the Stripe-Signature header; we compare it against our webhook_key.
        // If verification fails, reject the request immediately.
        $http_stripe_signature = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
        $credentials           = $this->stripe->get_credentials();
        $result                = $this->stripe->construct_event($request_body, $http_stripe_signature, $credentials['webhook_key']);

        if ($result !== 'Matched') {
            log_message('error', '[STRIPE WEBHOOK] Invalid signature — request rejected');
            return $this->response->setStatusCode(401)->setJSON(['error' => true, 'message' => 'Invalid signature']);
        }

        $event_type = $event->type ?? 'unknown';
        log_message('info', '[STRIPE WEBHOOK] Signature verified. Event type: ' . $event_type);

        // --- Extract common fields used across multiple event handlers ---
        // payment_intent is the Stripe transaction identifier stored in our DB as txn_id.
        $txn_id   = (string) ($event->data->object->payment_intent ?? '');
        $currency = (string) ($event->data->object->currency ?? '');

        // Stripe stores amounts in minor units (e.g. cents). Convert to major units (e.g. dollars).
        $raw_amount = $event->data->object->amount_received
            ?? $event->data->object->amount
            ?? 0;
        $amount = $this->formatStripeAmount($raw_amount, $currency);

        // order_id and user/partner IDs come from the PaymentIntent metadata.
        // They may be empty if this is a subscription payment (uses transaction_id in metadata instead).
        $order_id   = 0;
        $user_id    = 0;
        $partner_id = 0;

        if (
            !empty($event->data->object->payment_intent)
            && isset($event->data->object->metadata)
            && !empty($event->data->object->metadata->order_id)
        ) {
            $order_id   = (int) $event->data->object->metadata->order_id;
            $order_data = fetch_details('orders', ['id' => $order_id]);
            $user_id    = !empty($order_data) ? (int) $order_data[0]['user_id']    : 0;
            $partner_id = !empty($order_data) ? (int) $order_data[0]['partner_id'] : 0;

            log_message('info', '[STRIPE WEBHOOK] Context from metadata — order_id: ' . $order_id . ' | user_id: ' . $user_id . ' | partner_id: ' . $partner_id);
        }

        log_message('info', '[STRIPE WEBHOOK] txn_id: ' . ($txn_id ?: 'n/a') . ' | amount: ' . $amount . ' | currency: ' . $currency);

        // --- Dispatch to event-specific handlers ---
        switch ($event_type) {
            case 'charge.succeeded':
                return $this->handleChargeSucceeded($event, $txn_id, $amount, $currency, $order_id, $user_id, $partner_id);

            case 'charge.failed':
                return $this->handleChargeFailed($event, $txn_id, $amount, $currency, $order_id, $user_id, $partner_id);

            case 'charge.pending':
                return $this->handleChargePending($event, $txn_id, $amount, $currency, $order_id, $user_id, $partner_id);

            case 'charge.expired':
                return $this->handleChargeExpired($event, $txn_id, $amount, $currency, $order_id, $user_id, $partner_id);

            case 'charge.refunded':
                return $this->handleChargeRefunded($txn_id, $amount, $currency, $order_id, $user_id, $partner_id);

            default:
                // Unrecognised event — return 200 so Stripe stops retrying.
                log_message('info', '[STRIPE WEBHOOK] Unhandled event type: ' . $event_type . '. Returning 200.');
                return $this->response->setStatusCode(200)->setBody('');
        }
    }

    // =========================================================
    // Event: charge.succeeded
    // Three branches: subscription payment / additional charge / regular order booking
    // =========================================================

    /**
     * Handle charge.succeeded event.
     */
    private function handleChargeSucceeded(
        object $event,
        string $txn_id,
        float  $amount,
        string $currency,
        int    $order_id,
        int    $user_id,
        int    $partner_id
    ) {
        log_message('info', '[STRIPE WEBHOOK] [charge.succeeded] Processing.');

        // --- Branch 1: Subscription payment ---
        // Stripe passes our internal transaction_id in the PaymentIntent metadata.
        $subscription_transaction_id = (int) ($event->data->object->metadata->transaction_id ?? 0);
        if (!empty($subscription_transaction_id)) {
            log_message('info', '[STRIPE WEBHOOK] [charge.succeeded] Branch: subscription. transaction_id: ' . $subscription_transaction_id);
            $this->handleSubscriptionPayment($event, $txn_id, $subscription_transaction_id);
            return $this->response->setJSON(['error' => false, 'message' => 'Subscription processed']);
        }

        // --- Branch 2: Additional charge payment ---
        $additional_charges_txn_id = (int) ($event->data->object->metadata->additional_charges_transaction_id ?? 0);
        if (!empty($additional_charges_txn_id)) {
            log_message('info', '[STRIPE WEBHOOK] [charge.succeeded] Branch: additional charge. additional_charges_txn_id: ' . $additional_charges_txn_id);

            // Use metadata order_id (more reliable than the one parsed at the top for additional charges).
            $order_id_for_additional = (int) ($event->data->object->metadata->order_id ?? 0);

            update_details(['status' => 'success', 'txn_id' => $txn_id], ['id' => $additional_charges_txn_id], 'transactions');
            log_message('info', '[STRIPE WEBHOOK] [charge.succeeded] Additional charge transaction ' . $additional_charges_txn_id . ' marked success.');

            update_details(['payment_status_of_additional_charge' => '1'], ['id' => $order_id_for_additional], 'orders');
            log_message('info', '[STRIPE WEBHOOK] [charge.succeeded] Order ' . $order_id_for_additional . ' payment_status_of_additional_charge=1.');

            return $this->response->setJSON(['error' => false, 'message' => 'Additional charge processed']);
        }

        // --- Branch 3: Regular order booking ---
        log_message('info', '[STRIPE WEBHOOK] [charge.succeeded] Branch: order booking. order_id: ' . $order_id);

        $data = [
            'transaction_type' => 'transaction',
            'user_id'          => $user_id,
            'partner_id'       => $partner_id,
            'order_id'         => $order_id,
            'type'             => 'stripe',
            'txn_id'           => $txn_id,
            'amount'           => $amount,
            'status'           => 'success',
            'currency_code'    => $currency,
            'message'          => 'Order placed successfully',
        ];
        $insert_id = add_transaction($data);

        if ($insert_id) {
            log_message('info', '[STRIPE WEBHOOK] [charge.succeeded] Transaction inserted (ID: ' . $insert_id . '). Updating order and sending notifications.');

            update_details(['payment_status' => 1], ['id' => $order_id], 'orders');
            update_custom_job_status($order_id, 'booked');

            // Notify provider and customer about the new booking
            $this->send_booking_notifications($order_id, $user_id, $partner_id);

            // Notify customer about payment success
            $this->send_online_payment_status_notification($order_id, $user_id, 'success', [
                'amount'         => $amount,
                'currency'       => $currency,
                'transaction_id' => $txn_id,
                'payment_method' => 'Stripe',
                'paid_at'        => date('d-m-Y H:i:s'),
            ]);

            return $this->response->setJSON([
                'error'              => false,
                'transaction_status' => 'charge.succeeded',
                'message'            => 'Transaction successfully done',
            ]);
        }

        log_message('error', '[STRIPE WEBHOOK] [charge.succeeded] Failed to insert transaction for order ' . $order_id);
        return $this->response->setJSON(['error' => true, 'message' => 'Something went wrong']);
    }

    // =========================================================
    // Event: charge.failed
    // =========================================================

    /**
     * Handle charge.failed event.
     */
    private function handleChargeFailed(
        object $event,
        string $txn_id,
        float  $amount,
        string $currency,
        int    $order_id,
        int    $user_id,
        int    $partner_id
    ) {
        log_message('info', '[STRIPE WEBHOOK] [charge.failed] Processing. order_id: ' . $order_id . ' | txn_id: ' . $txn_id);

        $failure_reason = $event->data->object->failure_message ?? 'Payment could not be processed.';

        // --- Branch 1: Additional charge failed ---
        $additional_charges_txn_id = (int) ($event->data->object->metadata->additional_charges_transaction_id ?? 0);
        if (!empty($additional_charges_txn_id)) {
            log_message('info', '[STRIPE WEBHOOK] [charge.failed] Branch: additional charge. additional_charges_txn_id: ' . $additional_charges_txn_id);

            $order_id_for_notification = (int) ($event->data->object->metadata->order_id ?? 0);

            update_details(['status' => 'failed', 'txn_id' => $txn_id], ['id' => $additional_charges_txn_id], 'transactions');
            update_details(['payment_status_of_additional_charge' => '2'], ['id' => $order_id_for_notification], 'orders');
            log_message('info', '[STRIPE WEBHOOK] [charge.failed] Additional charge transaction and order updated for order ' . $order_id_for_notification);

            // Notify customer of failure
            $order_data_for_notification = fetch_details('orders', ['id' => $order_id_for_notification]);
            if (!empty($order_data_for_notification)) {
                $user_id_for_notification = (int) $order_data_for_notification[0]['user_id'];
                $this->send_online_payment_status_notification($order_id_for_notification, $user_id_for_notification, 'failed', [
                    'amount'         => $amount,
                    'currency'       => $currency,
                    'transaction_id' => $txn_id,
                    'payment_method' => 'Stripe',
                    'failure_reason' => $failure_reason,
                ]);
            }

            return $this->response->setJSON(['error' => false, 'message' => 'Processed']);
        }

        // --- Branch 2: Regular order failed ---
        log_message('error', '[STRIPE WEBHOOK] [charge.failed] Branch: order payment failed. order_id: ' . $order_id);

        add_transaction([
            'transaction_type' => 'transaction',
            'user_id'          => $user_id,
            'partner_id'       => $partner_id,
            'order_id'         => $order_id,
            'type'             => 'stripe',
            'txn_id'           => $txn_id,
            'amount'           => $amount,
            'status'           => 'failed',
            'currency_code'    => $currency,
            'message'          => 'Booking is cancelled',
        ]);

        update_details(['payment_status' => 2], ['id' => $order_id], 'orders');
        update_details(['status' => 'cancelled'], ['id' => $order_id], 'orders');
        update_custom_job_status($order_id, 'cancelled');

        log_message('info', '[STRIPE WEBHOOK] [charge.failed] Order ' . $order_id . ' marked payment_status=2 and status=cancelled.');

        // Notify customer of failure
        $this->send_online_payment_status_notification($order_id, $user_id, 'failed', [
            'amount'         => $amount,
            'currency'       => $currency,
            'transaction_id' => $txn_id,
            'payment_method' => 'Stripe',
            'failure_reason' => $failure_reason,
        ]);

        return $this->response->setJSON(['error' => false, 'message' => 'Processed']);
    }

    // =========================================================
    // Event: charge.pending
    // =========================================================

    /**
     * Handle charge.pending event.
     */
    private function handleChargePending(
        object $event,
        string $txn_id,
        float  $amount,
        string $currency,
        int    $order_id,
        int    $user_id,
        int    $partner_id
    ) {
        log_message('info', '[STRIPE WEBHOOK] [charge.pending] Processing. order_id: ' . $order_id . ' | txn_id: ' . $txn_id);

        // --- Branch 1: Additional charge pending ---
        $additional_charges_txn_id = (int) ($event->data->object->metadata->additional_charges_transaction_id ?? 0);
        if (!empty($additional_charges_txn_id)) {
            log_message('info', '[STRIPE WEBHOOK] [charge.pending] Branch: additional charge. additional_charges_txn_id: ' . $additional_charges_txn_id);

            $order_id_for_notification = (int) ($event->data->object->metadata->order_id ?? 0);

            update_details(['status' => 'pending', 'txn_id' => $txn_id], ['id' => $additional_charges_txn_id], 'transactions');
            update_details(['payment_status_of_additional_charge' => '0'], ['id' => $order_id_for_notification], 'orders');
            log_message('info', '[STRIPE WEBHOOK] [charge.pending] Additional charge transaction and order updated for order ' . $order_id_for_notification);

            // Notify customer of pending status
            $order_data_for_notification = fetch_details('orders', ['id' => $order_id_for_notification]);
            if (!empty($order_data_for_notification)) {
                $user_id_for_notification = (int) $order_data_for_notification[0]['user_id'];
                $this->send_online_payment_status_notification($order_id_for_notification, $user_id_for_notification, 'pending', [
                    'amount'         => $amount,
                    'currency'       => $currency,
                    'transaction_id' => $txn_id,
                    'payment_method' => 'Stripe',
                ]);
            }

            return $this->response->setJSON(['error' => false, 'message' => 'Processed']);
        }

        // --- Branch 2: Regular order pending ---
        log_message('info', '[STRIPE WEBHOOK] [charge.pending] Branch: order payment pending. order_id: ' . $order_id);

        add_transaction([
            'transaction_type' => 'transaction',
            'user_id'          => $user_id,
            'partner_id'       => $partner_id,
            'order_id'         => $order_id,
            'type'             => 'stripe',
            'txn_id'           => $txn_id,
            'amount'           => $amount,
            'status'           => 'pending',
            'currency_code'    => $currency,
            'message'          => 'Order placed successfully',
        ]);

        update_details(['payment_status' => 0], ['id' => $order_id], 'orders');
        update_custom_job_status($order_id, 'pending');

        log_message('info', '[STRIPE WEBHOOK] [charge.pending] Order ' . $order_id . ' marked payment_status=0 and job status=pending.');

        // Notify customer of pending status
        $this->send_online_payment_status_notification($order_id, $user_id, 'pending', [
            'amount'         => $amount,
            'currency'       => $currency,
            'transaction_id' => $txn_id,
            'payment_method' => 'Stripe',
        ]);

        return $this->response->setJSON(['error' => false, 'message' => 'Processed']);
    }

    // =========================================================
    // Event: charge.expired
    // =========================================================

    /**
     * Handle charge.expired event.
     * Records a failed transaction and marks the order cancelled.
     */
    private function handleChargeExpired(
        object $event,
        string $txn_id,
        float  $amount,
        string $currency,
        int    $order_id,
        int    $user_id,
        int    $partner_id
    ) {
        log_message('info', '[STRIPE WEBHOOK] [charge.expired] Processing. order_id: ' . $order_id . ' | txn_id: ' . $txn_id);

        add_transaction([
            'transaction_type' => 'transaction',
            'user_id'          => $user_id,
            'partner_id'       => $partner_id,
            'order_id'         => $order_id,
            'type'             => 'stripe',
            'txn_id'           => $txn_id,
            'amount'           => $amount,
            'status'           => 'failed',
            'currency_code'    => $currency,
            'message'          => 'Order placed successfully',
        ]);

        update_custom_job_status($order_id, 'cancelled');
        log_message('info', '[STRIPE WEBHOOK] [charge.expired] Order ' . $order_id . ' custom job status set to cancelled.');

        return $this->response->setStatusCode(200)->setBody('');
    }

    // =========================================================
    // Event: charge.refunded
    // =========================================================

    /**
     * Handle charge.refunded event.
     * Updates an existing refund record or creates a new one.
     */
    private function handleChargeRefunded(
        string $txn_id,
        float  $amount,
        string $currency,
        int    $order_id,
        int    $user_id,
        int    $partner_id
    ) {
        log_message('info', '[STRIPE WEBHOOK] [charge.refunded] Processing. txn_id: ' . $txn_id . ' | order_id: ' . $order_id);

        // A refund only makes sense if there is a matching successful transaction.
        $success_transaction = fetch_details('transactions', [
            'transaction_type' => 'transaction',
            'type'             => 'stripe',
            'status'           => 'success',
            'txn_id'           => $txn_id,
        ]);

        if (empty($success_transaction)) {
            log_message('warning', '[STRIPE WEBHOOK] [charge.refunded] No matching success transaction found for txn_id: ' . $txn_id . '. Skipping.');
            return $this->response->setStatusCode(200)->setBody('');
        }

        log_message('info', '[STRIPE WEBHOOK] [charge.refunded] Found success transaction ID: ' . $success_transaction[0]['id']);

        // Check if a refund transaction record already exists (idempotency).
        $existing_refund = fetch_details('transactions', [
            'transaction_type' => 'refund',
            'type'             => 'stripe',
            'message'          => 'stripe_refund',
            'txn_id'           => $txn_id,
        ]);

        if (!empty($existing_refund)) {
            // Update the existing refund record to succeeded.
            update_details(['status' => 'succeeded'], ['id' => $existing_refund[0]['id']], 'transactions');
            log_message('info', '[STRIPE WEBHOOK] [charge.refunded] Existing refund transaction ' . $existing_refund[0]['id'] . ' updated to succeeded.');
        } else {
            // Create a new refund transaction record.
            $insert_id = add_transaction([
                'transaction_type' => 'refund',
                'user_id'          => $user_id,
                'partner_id'       => $partner_id,
                'order_id'         => $order_id,
                'type'             => 'stripe',
                'txn_id'           => $txn_id,
                'amount'           => $amount,
                'status'           => 'succeeded',
                'currency_code'    => $currency,
                'message'          => 'stripe_refund',
            ]);
            log_message('info', '[STRIPE WEBHOOK] [charge.refunded] Refund transaction inserted (ID: ' . ($insert_id ?: 'failed') . ').');
            update_custom_job_status($order_id, 'refunded');
            log_message('info', '[STRIPE WEBHOOK] [charge.refunded] Order ' . $order_id . ' custom job status set to refunded.');
        }

        return $this->response->setStatusCode(200)->setBody('');
    }

    // =========================================================
    // Subscription payment handler (called from charge.succeeded)
    // =========================================================

    /**
     * Handle subscription payment via Stripe.
     * Marks the transaction as success and activates the partner subscription.
     */
    private function handleSubscriptionPayment(object $event, string $txn_id, int $transaction_id): void
    {
        log_message('info', '[STRIPE WEBHOOK] [subscription] Looking up transaction ID: ' . $transaction_id);

        $transaction_details = fetch_details('transactions', ['id' => $transaction_id]);
        if (empty($transaction_details)) {
            log_message('error', '[STRIPE WEBHOOK] [subscription] Transaction ID ' . $transaction_id . ' not found. Aborting.');
            return;
        }

        $subscription_id = (int) $transaction_details[0]['subscription_id'];
        $partner_id      = (int) $transaction_details[0]['user_id'];
        log_message('info', '[STRIPE WEBHOOK] [subscription] subscription_id: ' . $subscription_id . ' | partner_id: ' . $partner_id);

        $details_for_subscription = fetch_details('subscriptions', ['id' => $subscription_id]);
        if (empty($details_for_subscription)) {
            log_message('error', '[STRIPE WEBHOOK] [subscription] Subscription ' . $subscription_id . ' not found. Aborting.');
            return;
        }

        // Mark transaction as successful
        update_details(
            ['status' => 'success', 'txn_id' => $txn_id],
            ['id' => $transaction_id],
            'transactions'
        );
        log_message('info', '[STRIPE WEBHOOK] [subscription] Transaction ' . $transaction_id . ' marked success.');

        // Notify provider of payment success
        send_subscription_payment_status_notification($transaction_id, 'success');

        // Calculate subscription dates
        $purchaseDate         = date('Y-m-d');
        $subscriptionDuration = $details_for_subscription[0]['duration'];
        $expiryDate           = date('Y-m-d', strtotime($purchaseDate . ' + ' . $subscriptionDuration . ' days'));

        if ($subscriptionDuration === 'unlimited') {
            $subscriptionDuration = 0;
        }

        log_message('info', '[STRIPE WEBHOOK] [subscription] Activating subscription. purchase_date: ' . $purchaseDate . ' | expiry_date: ' . $expiryDate);

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
                'transaction_id'  => $transaction_id,
            ],
            'partner_subscriptions'
        );

        log_message('info', '[STRIPE WEBHOOK] [subscription] partner_subscriptions update result: ' . ($update_result ? 'success' : 'failed/no-change'));

        // Notify admin when a subscription is purchased
        if ($update_result) {
            try {
                $provider_name = get_translated_partner_field($partner_id, 'company_name');
                if (empty($provider_name)) {
                    $partner_data  = fetch_details('partner_details', ['partner_id' => $partner_id], ['company_name']);
                    $provider_name = !empty($partner_data) ? $partner_data[0]['company_name'] : 'Provider';
                }

                $subscription_name = $details_for_subscription[0]['name'] ?? 'Subscription';

                $transaction_data = fetch_details('transactions', ['id' => $transaction_id], ['amount']);
                $amount           = !empty($transaction_data) ? $transaction_data[0]['amount'] : '0.00';

                $currency = get_settings('general_settings', true)['currency'] ?? 'USD';

                log_message('info', '[STRIPE WEBHOOK] [subscription] Queuing admin notification. Provider: ' . $provider_name . ' | Plan: ' . $subscription_name);

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
                // Notification failure must not block subscription activation.
                log_message('error', '[STRIPE WEBHOOK] [subscription] Notification error: ' . $e->getMessage());
            }
        }

        log_message('info', '[STRIPE WEBHOOK] [subscription] Handler complete for transaction ' . $transaction_id);
    }

    // =========================================================
    // Notification helpers
    // =========================================================

    /**
     * Send booking notifications to provider and customer.
     */
    private function send_booking_notifications(int $order_id, int $user_id, int $partner_id): void
    {
        try {
            $order_data = fetch_details('orders', ['id' => $order_id]);
            if (empty($order_data)) {
                log_message('error', '[STRIPE WEBHOOK] [notifications] Order ' . $order_id . ' not found. Cannot send booking notifications.');
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

            log_message('info', '[STRIPE WEBHOOK] [notifications] Queuing new_booking_received_for_provider for partner ' . $partner_id . ' | order ' . $order_id);
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

            log_message('info', '[STRIPE WEBHOOK] [notifications] Queuing new_booking_confirmation_to_customer for user ' . $user_id . ' | order ' . $order_id);
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

            log_message('info', '[STRIPE WEBHOOK] [notifications] Booking notifications queued for order ' . $order_id);
        } catch (\Throwable $e) {
            log_message('error', '[STRIPE WEBHOOK] [notifications] Exception: ' . $e->getMessage());
        }
    }

    /**
     * Send online payment status notification to customer (success / failed / pending).
     */
    private function send_online_payment_status_notification(int $order_id, int $user_id, string $status, array $payment_data = []): void
    {
        try {
            $valid_statuses = ['success', 'failed', 'pending'];
            if (!in_array($status, $valid_statuses)) {
                log_message('error', '[STRIPE WEBHOOK] [payment-notification] Invalid status: ' . $status);
                return;
            }

            $event_type_map = [
                'success' => 'online_payment_success',
                'failed'  => 'online_payment_failed',
                'pending' => 'online_payment_pending',
            ];
            $event_type = $event_type_map[$status];

            $order_data = fetch_details('orders', ['id' => $order_id]);
            if (empty($order_data)) {
                log_message('error', '[STRIPE WEBHOOK] [payment-notification] Order ' . $order_id . ' not found. Skipping.');
                return;
            }

            $customer_details = fetch_details('users', ['id' => $user_id], ['username', 'email']);
            $customer_name    = !empty($customer_details) ? $customer_details[0]['username'] : 'Customer';
            $customer_email   = !empty($customer_details) ? $customer_details[0]['email']    : '';

            $notificationContext = [
                'booking_id'     => (string) $order_id,
                'order_id'       => (string) $order_id,
                'amount'         => number_format($payment_data['amount'] ?? $order_data[0]['total'] ?? 0, 2),
                'currency'       => $payment_data['currency'] ?? $order_data[0]['currency_code'] ?? '',
                'transaction_id' => (string) ($payment_data['transaction_id'] ?? ''),
                'customer_id'    => (string) $user_id,
                'customer_name'  => $customer_name,
                'customer_email' => $customer_email,
                'payment_method' => $payment_data['payment_method'] ?? 'Stripe',
            ];

            if ($status === 'success') {
                $notificationContext['paid_at'] = $payment_data['paid_at'] ?? date('d-m-Y H:i:s');
            } elseif ($status === 'failed') {
                $notificationContext['failure_reason'] = $payment_data['failure_reason'] ?? 'Payment could not be processed.';
            }

            log_message('info', '[STRIPE WEBHOOK] [payment-notification] Queuing ' . $event_type . ' for user ' . $user_id . ' | order ' . $order_id);

            queue_notification_service(
                eventType: $event_type,
                recipients: ['user_id' => $user_id],
                context: $notificationContext,
                options: [
                    'channels'  => ['fcm', 'email', 'sms'],
                    'language'  => $this->defaultLanguage,
                    'platforms' => ['android', 'ios', 'web'],
                    'type'      => 'payment',
                    'data'      => [
                        'order_id'       => (string) $order_id,
                        'booking_id'     => (string) $order_id,
                        'transaction_id' => (string) ($payment_data['transaction_id'] ?? ''),
                        'click_action'   => 'FLUTTER_NOTIFICATION_CLICK',
                        'redirect_to'    => 'booking_details_screen',
                    ],
                ]
            );

            log_message('info', '[STRIPE WEBHOOK] [payment-notification] ' . $event_type . ' queued for user ' . $user_id . ' | order ' . $order_id);
        } catch (\Throwable $e) {
            log_message('error', '[STRIPE WEBHOOK] [payment-notification] Exception: ' . $e->getMessage());
        }
    }

    // =========================================================
    // Utility
    // =========================================================

    /**
     * Convert Stripe minor units (e.g. cents) to major units (e.g. dollars).
     * Delegates to StripeMoney so currency rules stay centralised.
     */
    private function formatStripeAmount($amount, string $currency): float
    {
        return StripeMoney::fromMinorUnits($amount, $currency);
    }
}
