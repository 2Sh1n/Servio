<?php

namespace App\Controllers\Webhooks;

use App\Controllers\BaseController;
use App\Libraries\Razorpay;

/**
 * Razorpay webhook controller.
 * Handles payment events (authorized, captured, failed, refund) from Razorpay.
 * Separated from Webhooks.php for clarity, same pattern as CashfreeWebhook.
 *
 * Optimization: order data is fetched ONCE at the top and passed to all helpers,
 * avoiding redundant DB queries per event.
 */
class RazorpayWebhook extends BaseController
{
    private Razorpay $razorpay;

    /** @var string Default language for notifications (no request context in webhooks) */
    protected $defaultLanguage;

    /** @var array General settings for timezone etc. */
    protected $settings;

    public function __construct()
    {
        helper('api');
        helper('function');
        $this->razorpay = new Razorpay();
        $this->settings = get_settings('general_settings', true);
        date_default_timezone_set($this->settings['system_timezone'] ?? 'UTC');
        $this->defaultLanguage = get_default_language();
    }

    /**
     * Main webhook entry point. Razorpay sends POST with JSON body and X-Razorpay-Signature header.
     */
    public function index()
    {
        $raw_body = file_get_contents('php://input');
        $request  = json_decode($raw_body, true);
        $event    = $request['event'] ?? 'unknown';

        log_message('info', '[RAZORPAY WEBHOOK] ===== Incoming event: ' . $event . ' =====');
        log_message('info', '[RAZORPAY WEBHOOK] Raw payload: ' . $raw_body);

        if (strtoupper($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            log_message('warning', '[RAZORPAY WEBHOOK] Rejected: method not allowed (' . ($_SERVER['REQUEST_METHOD'] ?? 'none') . ')');
            return $this->response->setStatusCode(405)->setJSON([
                'error'   => true,
                'message' => 'Method not allowed',
            ]);
        }

        if (empty($request) || !is_array($request)) {
            log_message('error', '[RAZORPAY WEBHOOK] Rejected: invalid or empty JSON body');
            return $this->response->setStatusCode(400)->setJSON(['error' => true, 'message' => 'Invalid payload']);
        }

        // Signature verification disabled for now. Can be re-enabled when webhook secret is configured in settings.

        // --- Parse common fields from payload ---
        $txn_id                = (string) ($request['payload']['payment']['entity']['id'] ?? '');
        $transaction           = [];
        $transaction_id_actual = null;
        $order_data            = [];
        $order_id              = 0;
        $user_id               = 0;
        $partner_id            = 0;

        log_message('info', '[RAZORPAY WEBHOOK] Razorpay payment ID (txn_id): ' . ($txn_id ?: 'not present'));

        if (!empty($txn_id)) {
            // Look up existing transaction record by Razorpay payment ID
            $transaction = fetch_details('transactions', ['txn_id' => $txn_id]);
            if (!empty($transaction)) {
                log_message('info', '[RAZORPAY WEBHOOK] Matched local transaction ID: ' . $transaction[0]['id'] . ' | status: ' . $transaction[0]['status']);
            } else {
                log_message('info', '[RAZORPAY WEBHOOK] No existing transaction found for txn_id: ' . $txn_id);
            }
        }

        $amount   = !empty($txn_id) ? (float) ($request['payload']['payment']['entity']['amount'] ?? 0) / 100 : 0;
        $currency = (string) ($request['payload']['payment']['entity']['currency'] ?? '');

        log_message('info', '[RAZORPAY WEBHOOK] Amount: ' . $amount . ' | Currency: ' . $currency);

        $is_for_additional_charge = $request['payload']['payment']['entity']['notes']['additional_charges_transaction_id'] ?? '';
        if (!empty($is_for_additional_charge)) {
            log_message('info', '[RAZORPAY WEBHOOK] This is an additional charge payment. Additional charge transaction ID: ' . $is_for_additional_charge);
        }

        // --- Resolve order_id + order_data ONCE ---
        // This single fetch is reused throughout the entire request lifecycle.
        if (!empty($transaction)) {
            // Found existing transaction — derive order from it
            $order_id   = (int) $transaction[0]['order_id'];
            $user_id    = (int) $transaction[0]['user_id'];
            $order_data = fetch_details('orders', ['id' => $order_id]);
            $user_id    = (int) (!empty($order_data) ? $order_data[0]['user_id']    : $user_id);
            $partner_id = (int) (!empty($order_data) ? $order_data[0]['partner_id'] : 0);

            log_message('info', '[RAZORPAY WEBHOOK] Order resolved from existing transaction. Order ID: ' . $order_id . ' | User ID: ' . $user_id . ' | Partner ID: ' . $partner_id);

            if (!empty($order_data)) {
                log_message('info', '[RAZORPAY WEBHOOK] Order status: payment_status=' . $order_data[0]['payment_status'] . ' | order status=' . ($order_data[0]['status'] ?? 'n/a'));
            } else {
                log_message('warning', '[RAZORPAY WEBHOOK] Order ID ' . $order_id . ' not found in DB (resolved from transaction)');
            }

        } elseif (!empty($request['payload']['payment']['entity']['notes']['transaction_id'])) {
            // Subscription payment — handled separately; no order lookup needed here
            $transaction_id_actual = (int) $request['payload']['payment']['entity']['notes']['transaction_id'];
            log_message('info', '[RAZORPAY WEBHOOK] Identified as subscription payment. Internal transaction ID from notes: ' . $transaction_id_actual);

        } else {
            // No existing transaction — derive order from notes in the payload
            $order_id = (int) ($request['payload']['order']['entity']['notes']['order_id']
                ?? $request['payload']['payment']['entity']['notes']['order_id']
                ?? 0);

            log_message('info', '[RAZORPAY WEBHOOK] No existing transaction. Deriving order ID from payload notes: ' . $order_id);

            $order_data = fetch_details('orders', ['id' => $order_id]);
            $user_id    = !empty($order_data) ? (int) $order_data[0]['user_id']    : 0;
            $partner_id = !empty($order_data) ? (int) $order_data[0]['partner_id'] : 0;

            if (!empty($order_data)) {
                log_message('info', '[RAZORPAY WEBHOOK] Order found. Order ID: ' . $order_id . ' | User ID: ' . $user_id . ' | Partner ID: ' . $partner_id . ' | payment_status: ' . $order_data[0]['payment_status']);
            } else {
                log_message('warning', '[RAZORPAY WEBHOOK] Order ID ' . $order_id . ' not found in DB (from notes)');
            }
        }

        // =========================================================
        // --- Event: payment.authorized ---
        // Capture the payment immediately (Razorpay manual-capture flow)
        // =========================================================
        if ($event === 'payment.authorized') {
            $currency = (string) ($request['payload']['payment']['entity']['currency'] ?? 'INR');
            log_message('info', '[RAZORPAY WEBHOOK] [payment.authorized] Attempting to capture payment. txn_id: ' . $txn_id . ' | amount (paise): ' . ($amount * 100) . ' | currency: ' . $currency);
            $this->razorpay->capture_payment($amount * 100, $txn_id, $currency);
            log_message('info', '[RAZORPAY WEBHOOK] [payment.authorized] capture_payment() called. Razorpay will send payment.captured next.');
            return $this->response->setJSON(['error' => false, 'message' => 'Captured']);
        }

        // =========================================================
        // --- Event: payment.captured / order.paid ---
        // =========================================================
        if ($event === 'payment.captured' || $event === 'order.paid') {
            log_message('info', '[RAZORPAY WEBHOOK] [' . $event . '] Processing payment success flow.');

            // --- Path 1: Subscription payment ---
            if (!empty($transaction_id_actual)) {
                log_message('info', '[RAZORPAY WEBHOOK] [' . $event . '] Routing to subscription payment handler. Transaction ID: ' . $transaction_id_actual);
                $this->handleSubscriptionPayment($request, $txn_id, $transaction_id_actual);

            // --- Path 2: Additional charge payment ---
            } elseif (!empty($is_for_additional_charge)) {
                log_message('info', '[RAZORPAY WEBHOOK] [' . $event . '] Updating additional charge transaction ID ' . $is_for_additional_charge . ' to status=success.');
                $result = update_details(['status' => 'success', 'txn_id' => $txn_id], ['id' => $is_for_additional_charge], 'transactions');
                log_message('info', '[RAZORPAY WEBHOOK] [' . $event . '] Transaction update result: ' . ($result ? 'success' : 'failed/no-change'));

                // Reuse already-fetched $order_data — no need to re-query
                if (!empty($order_data)) {
                    log_message('info', '[RAZORPAY WEBHOOK] [' . $event . '] Marking order ' . $order_id . ' payment_status_of_additional_charge=1 (paid).');
                    $result = update_details(['payment_status_of_additional_charge' => '1'], ['id' => $order_id], 'orders');
                    log_message('info', '[RAZORPAY WEBHOOK] [' . $event . '] Order additional charge status update result: ' . ($result ? 'success' : 'failed/no-change'));

                    // For additional charges, send an \"online payment success\" notification instead of a new booking notification.
                    log_message('info', '[RAZORPAY WEBHOOK] [' . $event . '] Sending online_payment_success notification for additional charge. Order ' . $order_id . ' | User ' . $user_id);
                    $this->send_online_payment_status_notification(
                        $order_id,
                        $user_id,
                        'success',
                        [
                            'amount'         => $amount,
                            'currency'       => $currency,
                            'transaction_id' => $txn_id,
                            'payment_method' => 'Razorpay',
                            'paid_at'        => date('d-m-Y H:i:s'),
                        ],
                        $order_data
                    );
                } else {
                    log_message('warning', '[RAZORPAY WEBHOOK] [' . $event . '] Order ' . $order_id . ' not found — cannot update additional charge status or send success notification.');
                }

                // Additional charge flow is complete here; do not fall through to main order finalisation
                // to avoid sending booking notifications again.
                log_message('info', '[RAZORPAY WEBHOOK] [' . $event . '] Additional charge flow complete. Skipping main order finalisation.');
                return $this->response->setJSON(['error' => false, 'message' => 'Processed additional charge']);
            }

            // For order.paid, Razorpay includes the order_id in the receipt field.
            // Only re-fetch if the receipt gives us a different order_id than what we already have.
            if ($event === 'order.paid') {
                $receipt_order_id = (int) ($request['payload']['order']['entity']['receipt'] ?? $order_id);
                log_message('info', '[RAZORPAY WEBHOOK] [order.paid] Receipt order ID from payload: ' . $receipt_order_id . ' | Current order ID: ' . $order_id);
                if ($receipt_order_id !== $order_id) {
                    log_message('info', '[RAZORPAY WEBHOOK] [order.paid] order_id changed via receipt. Re-fetching order ' . $receipt_order_id);
                    $order_id   = $receipt_order_id;
                    $order_data = fetch_details('orders', ['id' => $order_id]);
                }
                if (!empty($order_data)) {
                    $user_id    = (int) (!empty($order_data) ? $order_data[0]['user_id'] : 0);
                    $partner_id = (int) (!empty($order_data) ? $order_data[0]['partner_id'] : 0);
                    log_message('info', '[RAZORPAY WEBHOOK] [order.paid] Resolved: order=' . $order_id . ' user=' . $user_id . ' partner=' . $partner_id . ' payment_status=' . $order_data[0]['payment_status']);
                } else {
                    log_message('warning', '[RAZORPAY WEBHOOK] [order.paid] Order ' . $order_id . ' not found after receipt lookup.');
                }
            }

            // --- Finalise the order ---
            if (!empty($order_id)) {
                if (!empty($transaction)) {
                    // Existing transaction — mark it successful
                    $transaction_id = (int) $transaction[0]['id'];
                    log_message('info', '[RAZORPAY WEBHOOK] [' . $event . '] Existing transaction found (ID: ' . $transaction_id . '). Updating status to success.');
                    $result = update_details(['status' => 'success'], ['id' => $transaction_id], 'transactions');
                    log_message('info', '[RAZORPAY WEBHOOK] [' . $event . '] Transaction status update result: ' . ($result ? 'success' : 'failed/no-change'));
                } else {
                    // No prior transaction record — create one and finalise the order
                    $currency = (string) ($request['payload']['payment']['entity']['currency'] ?? '');
                    log_message('info', '[RAZORPAY WEBHOOK] [' . $event . '] No existing transaction. Inserting new transaction record for order ' . $order_id . '.');
                    $data = [
                        'transaction_type' => 'transaction',
                        'user_id'          => $user_id,
                        'partner_id'       => $partner_id,
                        'order_id'         => $order_id,
                        'type'             => 'razorpay',
                        'txn_id'           => $txn_id,
                        'amount'           => $amount,
                        'status'           => 'success',
                        'currency_code'    => $currency,
                        'message'          => 'Order placed successfully',
                    ];
                    $insert_id = add_transaction($data);
                    if ($insert_id) {
                        log_message('info', '[RAZORPAY WEBHOOK] [' . $event . '] Transaction inserted with ID: ' . $insert_id . '. Updating order payment_status=1 and job status=booked.');
                        $order_result = update_details(['payment_status' => 1], ['id' => $order_id], 'orders');
                        log_message('info', '[RAZORPAY WEBHOOK] [' . $event . '] Order payment_status update result: ' . ($order_result ? 'success' : 'failed/no-change'));
                        update_custom_job_status($order_id, 'booked');
                        log_message('info', '[RAZORPAY WEBHOOK] [' . $event . '] Custom job status set to booked for order ' . $order_id . '. Sending booking notifications.');
                        // Pass already-fetched order_data to avoid another DB query inside
                        $this->send_booking_notifications($order_id, $user_id, $partner_id, $order_data);
                    } else {
                        log_message('error', '[RAZORPAY WEBHOOK] [' . $event . '] Failed to insert transaction record for order ' . $order_id . '. Order NOT finalised.');
                    }
                }
            } else {
                log_message('error', '[RAZORPAY WEBHOOK] [' . $event . '] order_id is empty — cannot finalise order. Payload: ' . json_encode($request));
            }

            log_message('info', '[RAZORPAY WEBHOOK] [' . $event . '] Processing complete. Returning 200.');
            return $this->response->setJSON(['error' => false, 'message' => 'Processed']);
        }

        // =========================================================
        // --- Event: payment.failed ---
        // =========================================================
        if ($event === 'payment.failed') {
            log_message('info', '[RAZORPAY WEBHOOK] [payment.failed] Processing failure for order ' . $order_id . ' | txn_id: ' . $txn_id);
            $this->handlePaymentFailed($request, $txn_id, $amount, $currency, $order_id, $user_id, $partner_id, $transaction, $is_for_additional_charge, $order_data);
            return $this->response->setJSON(['error' => false, 'message' => 'Processed']);
        }

        // =========================================================
        // --- Event: refund.processed ---
        // =========================================================
        if ($event === 'refund.processed') {
            log_message('info', '[RAZORPAY WEBHOOK] [refund.processed] Processing refund for order ' . $order_id . ' | txn_id: ' . $txn_id . ' | amount: ' . $amount);
            $this->handleRefundProcessed($txn_id, $amount, $currency, $order_id, $user_id, $partner_id);
            return $this->response->setJSON(['error' => false, 'message' => 'Processed']);
        }

        // =========================================================
        // --- Event: refund.failed ---
        // =========================================================
        if ($event === 'refund.failed') {
            // Log the failure details so we can investigate and manually reprocess if needed.
            $refund_id      = $request['payload']['refund']['entity']['id']          ?? 'unknown';
            $failure_reason = $request['payload']['refund']['entity']['description'] ?? 'no reason provided';
            log_message('error', '[RAZORPAY WEBHOOK] [refund.failed] Order: ' . $order_id . ' | Refund ID: ' . $refund_id . ' | Reason: ' . $failure_reason);
            return $this->response->setStatusCode(200)->setBody('');
        }

        // =========================================================
        // --- All other events ---
        // We don't process them. Return 200 immediately so Razorpay stops retrying.
        // No logging — avoids noise from irrelevant events (e.g. payment.pending, subscription.*).
        // =========================================================
        return $this->response->setStatusCode(200)->setBody('');
    }

    /**
     * Handle subscription payment: update transaction, activate subscription, notify admin.
     */
    private function handleSubscriptionPayment(array $request, string $txn_id, int $transaction_id): void
    {
        log_message('info', '[RAZORPAY WEBHOOK] [subscription] Looking up transaction ID: ' . $transaction_id);
        $transaction_details = fetch_details('transactions', ['id' => $transaction_id]);

        if (empty($transaction_details)) {
            log_message('error', '[RAZORPAY WEBHOOK] [subscription] Transaction ID ' . $transaction_id . ' not found in DB. Aborting.');
            return;
        }

        $subscription_id = (int) $transaction_details[0]['subscription_id'];
        $partner_id      = (int) $transaction_details[0]['user_id'];
        log_message('info', '[RAZORPAY WEBHOOK] [subscription] Transaction found. Subscription ID: ' . $subscription_id . ' | Partner ID: ' . $partner_id);

        $details_for_subscription = fetch_details('subscriptions', ['id' => $subscription_id]);
        if (empty($details_for_subscription)) {
            log_message('error', '[RAZORPAY WEBHOOK] [subscription] Subscription ID ' . $subscription_id . ' not found. Aborting subscription payment handling to avoid inconsistent state.');
            return;
        }

        // Mark the transaction as successful
        log_message('info', '[RAZORPAY WEBHOOK] [subscription] Marking transaction ' . $transaction_id . ' as success. txn_id: ' . $txn_id);
        $txn_result = update_details(['status' => 'success', 'txn_id' => $txn_id], ['id' => $transaction_id], 'transactions');
        log_message('info', '[RAZORPAY WEBHOOK] [subscription] Transaction update result: ' . ($txn_result ? 'success' : 'failed/no-change'));

        send_subscription_payment_status_notification($transaction_id, 'success');
        log_message('info', '[RAZORPAY WEBHOOK] [subscription] Payment status notification queued for transaction ' . $transaction_id);

        $purchaseDate         = date('Y-m-d');
        $subscriptionDuration = $details_for_subscription[0]['duration'] ?? 0;
        $expiryDate           = date('Y-m-d', strtotime($purchaseDate . ' + ' . $subscriptionDuration . ' days'));

        if ($subscriptionDuration === 'unlimited') {
            $subscriptionDuration = 0;
        }

        log_message('info', '[RAZORPAY WEBHOOK] [subscription] Activating partner_subscription. partner_id: ' . $partner_id . ' | subscription_id: ' . $subscription_id . ' | purchase_date: ' . $purchaseDate . ' | expiry_date: ' . $expiryDate . ' | duration: ' . $subscriptionDuration);

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

        if ($update_result) {
            log_message('info', '[RAZORPAY WEBHOOK] [subscription] partner_subscriptions row updated to active for partner ' . $partner_id);
        } else {
            log_message('warning', '[RAZORPAY WEBHOOK] [subscription] partner_subscriptions update returned no rows. Possibly already active or wrong transaction_id. partner_id: ' . $partner_id . ' | subscription_id: ' . $subscription_id . ' | transaction_id: ' . $transaction_id);
        }

        if ($update_result) {
            $provider_name = get_translated_partner_field($partner_id, 'company_name');
            if (empty($provider_name)) {
                $partner_data  = fetch_details('partner_details', ['partner_id' => $partner_id], ['company_name']);
                $provider_name = !empty($partner_data) ? $partner_data[0]['company_name'] : 'Provider';
            }

            $subscription_name = $details_for_subscription[0]['name'] ?? 'Subscription';
            $transaction_data  = fetch_details('transactions', ['id' => $transaction_id], ['amount']);
            $amount            = !empty($transaction_data) ? $transaction_data[0]['amount'] : '0.00';
            $currency          = get_settings('general_settings', true)['currency'] ?? 'USD';

            log_message('info', '[RAZORPAY WEBHOOK] [subscription] Queuing subscription_purchased admin notification. Provider: ' . $provider_name . ' | Plan: ' . $subscription_name . ' | Amount: ' . $amount . ' ' . $currency);

            $context = [
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
            ];
            queue_notification_service(
                eventType: 'subscription_purchased',
                recipients: [],
                context: $context,
                options: [
                    'channels'    => ['fcm', 'email', 'sms'],
                    'user_groups' => [1],
                    'platforms'   => ['admin_panel'],
                ]
            );
        }

        log_message('info', '[RAZORPAY WEBHOOK] [subscription] Handler complete for transaction ' . $transaction_id);
    }

    /**
     * Handle payment.failed: update order/transaction and send failed notification.
     *
     * @param array $order_data Pre-fetched order record (avoids an extra DB query).
     */
    private function handlePaymentFailed(
        array $request,
        string $txn_id,
        float $amount,
        string $currency,
        int $order_id,
        int $user_id,
        int $partner_id,
        array $transaction,
        $is_for_additional_charge,
        array $order_data = []
    ): void {
        $failure_reason = $request['payload']['payment']['entity']['error_description'] ?? 'Payment could not be processed.';
        log_message('error', '[RAZORPAY WEBHOOK] [payment.failed] Reason: ' . $failure_reason . ' | Order: ' . $order_id . ' | txn_id: ' . $txn_id);

        // --- Path: Additional charge failed ---
        if (!empty($is_for_additional_charge)) {
            log_message('info', '[RAZORPAY WEBHOOK] [payment.failed] Updating additional charge transaction ' . $is_for_additional_charge . ' to status=failed.');
            $result = update_details(['status' => 'failed', 'txn_id' => $txn_id], ['id' => $is_for_additional_charge], 'transactions');
            log_message('info', '[RAZORPAY WEBHOOK] [payment.failed] Additional charge transaction update result: ' . ($result ? 'success' : 'failed/no-change'));

            log_message('info', '[RAZORPAY WEBHOOK] [payment.failed] Marking order ' . $order_id . ' payment_status_of_additional_charge=2 (failed).');
            $result = update_details(['payment_status_of_additional_charge' => '2'], ['id' => $order_id], 'orders');
            log_message('info', '[RAZORPAY WEBHOOK] [payment.failed] Order additional charge status update result: ' . ($result ? 'success' : 'failed/no-change'));

            // Use passed-in $order_data — no re-fetch needed
            if (!empty($order_data[0])) {
                $this->send_online_payment_status_notification($order_id, $user_id, 'failed', [
                    'amount'         => $amount,
                    'currency'       => $currency,
                    'transaction_id' => $txn_id,
                    'payment_method' => 'Razorpay',
                    'failure_reason' => $failure_reason,
                ], $order_data);
            } else {
                log_message('warning', '[RAZORPAY WEBHOOK] [payment.failed] No order_data for additional charge — skipping failure notification for order ' . $order_id);
            }
            return;
        }

        // --- Path: Regular order payment failed ---
        log_message('info', '[RAZORPAY WEBHOOK] [payment.failed] Updating order ' . $order_id . ' payment_status=2 and status=cancelled.');
        $r1 = update_details(['payment_status' => 2], ['id' => $order_id], 'orders');
        $r2 = update_details(['status' => 'cancelled'], ['id' => $order_id], 'orders');
        log_message('info', '[RAZORPAY WEBHOOK] [payment.failed] Order payment_status update: ' . ($r1 ? 'success' : 'failed/no-change') . ' | order status=cancelled update: ' . ($r2 ? 'success' : 'failed/no-change'));

        if (!empty($transaction[0])) {
            log_message('info', '[RAZORPAY WEBHOOK] [payment.failed] Existing transaction ' . $transaction[0]['id'] . ' found. Updating status to failed.');
            $result = update_details(['status' => 'failed'], ['id' => $transaction[0]['id']], 'transactions');
            log_message('info', '[RAZORPAY WEBHOOK] [payment.failed] Transaction update result: ' . ($result ? 'success' : 'failed/no-change'));
        } else {
            // No prior transaction — insert a failed one for the audit trail
            log_message('info', '[RAZORPAY WEBHOOK] [payment.failed] No existing transaction. Inserting failed transaction record for order ' . $order_id);
            $data = [
                'transaction_type' => 'transaction',
                'user_id'          => $user_id,
                'partner_id'       => $partner_id,
                'order_id'         => $order_id,
                'type'             => 'razorpay',
                'txn_id'           => $txn_id,
                'amount'           => $amount,
                'status'           => 'failed',
                'currency_code'    => $currency,
                'message'          => 'Booking is cancelled',
            ];
            $insert_id = add_transaction($data);
            log_message('info', '[RAZORPAY WEBHOOK] [payment.failed] Failed transaction insert result: ' . ($insert_id ? 'inserted ID ' . $insert_id : 'failed'));
            update_custom_job_status($order_id, 'cancelled');
            log_message('info', '[RAZORPAY WEBHOOK] [payment.failed] Custom job status set to cancelled for order ' . $order_id);
        }

        log_message('info', '[RAZORPAY WEBHOOK] [payment.failed] Sending online_payment_failed notification to user ' . $user_id);
        $this->send_online_payment_status_notification($order_id, $user_id, 'failed', [
            'amount'         => $amount,
            'currency'       => $currency,
            'transaction_id' => $txn_id,
            'payment_method' => 'Razorpay',
            'failure_reason' => $failure_reason,
        ], $order_data);

        log_message('info', '[RAZORPAY WEBHOOK] [payment.failed] Handler complete for order ' . $order_id);
    }

    /**
     * Handle refund.processed: create or update refund transaction and update job status.
     */
    private function handleRefundProcessed(
        string $txn_id,
        float $amount,
        string $currency,
        int $order_id,
        int $user_id,
        int $partner_id
    ): void {
        log_message('info', '[RAZORPAY WEBHOOK] [refund.processed] Looking up successful transaction for txn_id: ' . $txn_id);

        $success_transaction = fetch_details('transactions', [
            'transaction_type' => 'transaction',
            'type'             => 'razorpay',
            'status'           => 'success',
            'txn_id'           => $txn_id,
        ]);

        if (empty($success_transaction)) {
            // No matching success transaction — cannot link refund
            log_message('warning', '[RAZORPAY WEBHOOK] [refund.processed] No successful transaction found for txn_id: ' . $txn_id . '. Skipping refund processing.');
            return;
        }

        log_message('info', '[RAZORPAY WEBHOOK] [refund.processed] Found success transaction ID: ' . $success_transaction[0]['id'] . '. Checking for existing refund record.');

        $already_exist_refund = fetch_details('transactions', [
            'transaction_type' => 'refund',
            'type'             => 'razorpay',
            'message'          => 'razorpay_refund',
            'txn_id'           => $txn_id,
        ]);

        if (!empty($already_exist_refund)) {
            log_message('info', '[RAZORPAY WEBHOOK] [refund.processed] Existing refund transaction found (ID: ' . $already_exist_refund[0]['id'] . '). Updating status to processed.');
            $result = update_details(['status' => 'processed'], ['id' => $already_exist_refund[0]['id']], 'transactions');
            log_message('info', '[RAZORPAY WEBHOOK] [refund.processed] Refund transaction update result: ' . ($result ? 'success' : 'failed/no-change'));
        } else {
            log_message('info', '[RAZORPAY WEBHOOK] [refund.processed] No existing refund record. Inserting new refund transaction for order ' . $order_id . ' | amount: ' . $amount);
            $insert_id = add_transaction([
                'transaction_type' => 'refund',
                'user_id'          => $user_id,
                'partner_id'       => $partner_id,
                'order_id'         => $order_id,
                'type'             => 'razorpay',
                'txn_id'           => $txn_id,
                'amount'           => $amount,
                'status'           => 'processed',
                'currency_code'    => $currency,
                'message'          => 'razorpay_refund',
            ]);
            log_message('info', '[RAZORPAY WEBHOOK] [refund.processed] Refund transaction insert result: ' . ($insert_id ? 'inserted ID ' . $insert_id : 'failed'));
            update_custom_job_status($order_id, 'refunded');
            log_message('info', '[RAZORPAY WEBHOOK] [refund.processed] Custom job status set to refunded for order ' . $order_id);
        }

        log_message('info', '[RAZORPAY WEBHOOK] [refund.processed] Handler complete for order ' . $order_id);
    }

    /**
     * Send booking notifications to provider and customer (same logic as Webhooks controller).
     *
     * @param array $order_data Pre-fetched order record (avoids an extra DB query).
     */
    private function send_booking_notifications(int $order_id, int $user_id, int $partner_id, array $order_data = []): void
    {
        try {
            // If caller did not supply order_data, fall back to fetching it now
            if (empty($order_data)) {
                log_message('info', '[RAZORPAY WEBHOOK] [notifications] order_data not provided; fetching order ' . $order_id);
                $order_data = fetch_details('orders', ['id' => $order_id]);
            }
            if (empty($order_data)) {
                log_message('error', '[RAZORPAY WEBHOOK] [notifications] Order ' . $order_id . ' not found. Cannot send booking notifications.');
                return;
            }
            $final_total = $order_data[0]['total'] ?? 0;
            $language    = $this->defaultLanguage;

            log_message('info', '[RAZORPAY WEBHOOK] [notifications] Queuing new_booking_received_for_provider for partner ' . $partner_id . ' | order ' . $order_id . ' | total: ' . $final_total);
            $notificationContext = [
                'provider_id' => $partner_id,
                'user_id'     => $user_id,
                'booking_id'  => $order_id,
                'amount'      => $final_total,
            ];
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

            log_message('info', '[RAZORPAY WEBHOOK] [notifications] Queuing new_booking_confirmation_to_customer for user ' . $user_id . ' | order ' . $order_id);
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

            log_message('info', '[RAZORPAY WEBHOOK] [notifications] Booking notifications queued successfully for order ' . $order_id);
        } catch (\Throwable $e) {
            log_message('error', '[RAZORPAY WEBHOOK] [notifications] Exception while queuing notifications: ' . $e->getMessage());
        }
    }

    /**
     * Send online payment status notification to customer (success/failed/pending).
     *
     * @param array $order_data Pre-fetched order record (avoids an extra DB query).
     */
    private function send_online_payment_status_notification(int $order_id, int $user_id, string $status, array $payment_data = [], array $order_data = []): void
    {
        try {
            $valid_statuses = ['success', 'failed', 'pending'];
            if (!in_array($status, $valid_statuses)) {
                log_message('error', '[RAZORPAY WEBHOOK] [payment-notification] Invalid status: ' . $status);
                return;
            }
            $event_type_map = [
                'success' => 'online_payment_success',
                'failed'  => 'online_payment_failed',
                'pending' => 'online_payment_pending',
            ];
            $event_type = $event_type_map[$status];

            // If caller did not supply order_data, fall back to fetching it now
            if (empty($order_data)) {
                log_message('info', '[RAZORPAY WEBHOOK] [payment-notification] order_data not provided; fetching order ' . $order_id);
                $order_data = fetch_details('orders', ['id' => $order_id]);
            }
            if (empty($order_data)) {
                log_message('error', '[RAZORPAY WEBHOOK] [payment-notification] Order ' . $order_id . ' not found. Cannot send ' . $status . ' notification.');
                return;
            }

            $customer_details = fetch_details('users', ['id' => $user_id], ['username', 'email']);
            $customer_name    = !empty($customer_details) ? $customer_details[0]['username'] : 'Customer';
            $customer_email   = !empty($customer_details) ? $customer_details[0]['email']    : '';

            log_message('info', '[RAZORPAY WEBHOOK] [payment-notification] Queuing ' . $event_type . ' for user ' . $user_id . ' (' . $customer_name . ') | order ' . $order_id);

            $notificationContext = [
                'booking_id'     => (string) $order_id,
                'order_id'       => (string) $order_id,
                'amount'         => number_format($payment_data['amount'] ?? $order_data[0]['total'] ?? 0, 2),
                'currency'       => $payment_data['currency'] ?? $order_data[0]['currency_code'] ?? 'INR',
                'transaction_id' => (string) ($payment_data['transaction_id'] ?? $payment_data['txn_id'] ?? ''),
                'customer_id'    => (string) $user_id,
                'customer_name'  => $customer_name,
                'customer_email' => $customer_email,
                'payment_method' => $payment_data['payment_method'] ?? 'Razorpay',
            ];
            if ($status === 'success') {
                $notificationContext['paid_at'] = $payment_data['paid_at'] ?? date('d-m-Y H:i:s');
            } elseif ($status === 'failed') {
                $notificationContext['failure_reason'] = $payment_data['failure_reason'] ?? 'Payment could not be processed.';
            }

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
                        'transaction_id' => (string) ($payment_data['transaction_id'] ?? $payment_data['txn_id'] ?? ''),
                        'click_action'   => 'FLUTTER_NOTIFICATION_CLICK',
                        'redirect_to'    => 'booking_details_screen',
                    ],
                ]
            );

            log_message('info', '[RAZORPAY WEBHOOK] [payment-notification] Notification ' . $event_type . ' queued for user ' . $user_id . ' | order ' . $order_id);
        } catch (\Throwable $e) {
            log_message('error', '[RAZORPAY WEBHOOK] [payment-notification] Exception: ' . $e->getMessage());
        }
    }
}
