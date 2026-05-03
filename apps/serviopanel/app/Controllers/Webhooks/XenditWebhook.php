<?php

namespace App\Controllers\Webhooks;

use App\Controllers\BaseController;
use App\Libraries\Xendit;
use Config\ApiResponseAndNotificationStrings;

class XenditWebhook extends BaseController
{
    protected $output;
    protected $settings;
    protected $trans;
    protected $defaultLanguage;

    public function __construct()
    {
        helper('api');
        helper('function');
        $this->settings        = get_settings('general_settings', true);
        date_default_timezone_set($this->settings['system_timezone']);
        $this->trans           = new ApiResponseAndNotificationStrings();
        $this->defaultLanguage = get_default_language();
    }
    public function index()
    {
        try {
            // Log the webhook call (consistent with other gateways)
            log_message('error', 'Xendit Webhook called');

            $request_body = file_get_contents('php://input');
            $event = json_decode($request_body, true);

            // Log the full event payload for debugging
            log_message('error', 'Xendit Webhook event: ' . var_export($event, true));

            // Verify webhook signature
            $signature = $_SERVER['HTTP_X_CALLBACK_TOKEN'] ?? '';
            $xendit = new Xendit();

            if (!$xendit->verify_webhook($signature)) {
                log_message('error', 'Xendit Webhook - Invalid signature');
                http_response_code(401);
                return false;
            }

            // Handle event-based and status-based webhooks
            $event_type = $event['event'] ?? null;

            // Handle event-based webhooks first
            if ($event_type) {
                log_message('error', 'Xendit Webhook - Event type: ' . $event_type);
                switch ($event_type) {
                    case 'invoice.paid':
                        $this->handle_xendit_invoice_paid($event);
                        break;
                    case 'invoice.expired':
                        $this->handle_xendit_invoice_expired($event);
                        break;
                    case 'invoice.payment_failed':
                        $this->handle_xendit_invoice_failed($event);
                        break;
                    case 'refund.succeeded':
                        // New standardized refund webhook format
                        $this->handle_xendit_refund_processed($event, 'new_format');
                        break;
                    case 'refund.failed':
                        // New standardized refund webhook format
                        $this->handle_xendit_refund_failed($event, 'new_format');
                        break;
                    case 'ewallet.refund':
                        // E-wallet specific refund format with nested data structure
                        $this->handle_xendit_refund_processed($event, 'ewallet_format');
                        break;
                    case 'refund.completed':
                        // Legacy format - maintain backward compatibility
                        $this->handle_xendit_refund_processed($event, 'legacy_format');
                        break;
                    default:
                        log_message('error', 'Xendit Webhook - Unhandled event type: ' . $event_type);
                        break;
                }
            }
            // Fallback: If no event, use status (for compatibility with Xendit webhooks that only send 'status')
            else if (isset($event['status'])) {
                switch (strtoupper($event['status'])) {
                    case 'PAID':
                        $this->handle_xendit_invoice_paid($event);
                        $event_type = 'invoice.paid';
                        break;
                    case 'EXPIRED':
                        $this->handle_xendit_invoice_expired($event);
                        $event_type = 'invoice.expired';
                        break;
                    case 'FAILED':
                        $this->handle_xendit_invoice_failed($event);
                        $event_type = 'invoice.payment_failed';
                        break;
                    case 'REFUNDED':
                    case 'REFUND_SUCCEEDED':
                        // Status-based fallback - use legacy format for backward compatibility
                        $this->handle_xendit_refund_processed($event, 'legacy_format');
                        $event_type = 'refund.succeeded';
                        break;
                    case 'REFUND_FAILED':
                        // Status-based fallback - use legacy format for backward compatibility
                        $this->handle_xendit_refund_failed($event, 'legacy_format');
                        $event_type = 'refund.failed';
                        break;
                    default:
                        log_message('error', 'Xendit Webhook - Unhandled status: ' . $event['status']);
                        break;
                }
            }



            $response = [
                'error' => false,
                'message' => 'Webhook processed successfully'
            ];
            echo json_encode($response);
        } catch (\Exception $th) {
            log_message('error', 'Xendit Webhook Error: ' . $th->getMessage());
            $response = [
                'error' => true,
                'message' => 'Webhook processing failed'
            ];
            echo json_encode($response);
        }
    }

    private function handle_xendit_invoice_paid($event)
    {
        try {
            $external_id = $event['external_id'] ?? '';
            $amount = $event['amount'] ?? 0;
            $paid_amount = $event['paid_amount'] ?? 0;
            $payment_id = $event['payment_id'] ?? '';
            $payment_method = $event['payment_method'] ?? '';
            $invoice_id = $event['id'] ?? '';

            if (empty($external_id)) {
                log_message('error', 'Xendit Webhook - No external_id provided');
                return false;
            }

            $invoice_type = explode('_', $external_id);
            if ($invoice_type[0] == 'subscription') {
                $subscription_id = $invoice_type[1];
                $partner_id = $invoice_type[2];

                // Handle subscription payment
                $this->handle_xendit_subscription_payment($event, $external_id, $subscription_id, $partner_id, $payment_id, $paid_amount);
                return true;
            }

            // Check if this is an additional charges payment
            if ($invoice_type[0] == 'additionalCharges') {
                $additional_charges_transaction_id = $invoice_type[1];
                $this->handle_xendit_additional_charges_payment($event, $additional_charges_transaction_id, $payment_id, $paid_amount);
                return true;
            }

            // Handle regular order payment
            if ($invoice_type[0] == 'order') {
                $order_id = $invoice_type[1];
                $this->handle_xendit_order_payment($event, $external_id, $order_id, $payment_id, $paid_amount);
                return true;
            }

            log_message('error', 'Xendit Webhook - Invalid external_id format: ' . $external_id);
            return false;
        } catch (\Exception $th) {
            log_message('error', 'Xendit Webhook - Error processing paid invoice: ' . $th->getMessage());
            return false;
        }
    }

    private function handle_xendit_subscription_payment($event, $external_id, $subscription_id, $partner_id, $payment_id, $paid_amount)
    {
        try {
            // Find the transaction using external_id first
            $transaction = fetch_details('transactions', ['txn_id' => $external_id, 'user_id' => $partner_id]);

            // If not found by external_id, try to find by metadata in the invoice
            if (empty($transaction)) {
                $metadata = $event['metadata'] ?? [];
                $transaction_id_from_metadata = $metadata['transaction_id'] ?? null;

                if ($transaction_id_from_metadata) {
                    $transaction = fetch_details('transactions', ['id' => $transaction_id_from_metadata, 'user_id' => $partner_id]);
                    log_message('error', 'Xendit Webhook - Found transaction by metadata transaction_id: ' . $transaction_id_from_metadata);
                }
            }

            // If still not found, try to find the most recent pending transaction for this partner and subscription
            if (empty($transaction)) {
                $transaction = fetch_details('transactions', [
                    'user_id' => $partner_id,
                    'subscription_id' => $subscription_id,
                    'type' => 'xendit',
                    'status' => 'pending'
                ], '*', 1, 'id', 'DESC');

                log_message('error', 'Xendit Webhook - Found transaction by subscription_id and status: ' . (!empty($transaction) ? $transaction[0]['id'] : 'None'));
            }

            // If still not found, try to find any recent xendit transaction for this partner
            if (empty($transaction)) {
                $transaction = fetch_details('transactions', [
                    'user_id' => $partner_id,
                    'type' => 'xendit'
                ], '*', 1, 'id', 'DESC');

                log_message('error', 'Xendit Webhook - Found recent xendit transaction: ' . (!empty($transaction) ? $transaction[0]['id'] : 'None'));
            }

            if (!empty($transaction)) {
                $transaction_id = $transaction[0]['id'];

                // Check if transaction is already processed to prevent duplicate processing
                if ($transaction[0]['status'] === 'success') {
                    log_message('error', 'Xendit Webhook - Transaction already processed: ' . $transaction_id);
                    return true;
                }

                // Store additional transaction data in reference column as JSON
                $payment_reference_data = [
                    'payment_id' => $payment_id,
                    'external_id' => $external_id,
                    'invoice_id' => $event['id'] ?? '',
                    'payment_method' => $event['payment_method'] ?? '',
                    'webhook_type' => 'subscription_payment',
                    'paid_at' => $event['paid_at'] ?? date('Y-m-d H:i:s'),
                    'raw_event' => $event
                ];

                // Update transaction status and external_id
                update_details([
                    'status' => 'success',
                    'amount' => $paid_amount,
                    'txn_id' => $external_id, // Ensure external_id is set
                    'currency_code' => $event['currency'] ?? 'IDR',
                    'reference' => json_encode($payment_reference_data),
                ], ['id' => $transaction_id], 'transactions');
                // Send payment successful notification to provider
                send_subscription_payment_status_notification($transaction_id, 'success');

                // Get subscription details
                $subscription_details = fetch_details('subscriptions', ['id' => $subscription_id]);
                if (!empty($subscription_details)) {
                    $purchaseDate = date('Y-m-d');
                    $subscriptionDuration = $subscription_details[0]['duration'];
                    $expiryDate = date('Y-m-d', strtotime($purchaseDate . ' + ' . $subscriptionDuration . ' days'));

                    if ($subscriptionDuration == "unlimited") {
                        $subscriptionDuration = 0;
                    }

                    // First, try to update existing subscription
                    $update_result = update_details([
                        'status' => 'active',
                        'is_payment' => '1',
                        'purchase_date' => $purchaseDate,
                        'expiry_date' => $expiryDate,
                        'updated_at' => date('Y-m-d H:i:s'),
                        'transaction_id' => $transaction_id
                    ], [
                        'subscription_id' => $subscription_id,
                        'partner_id' => $partner_id,
                        'status !=' => 'active'
                    ], 'partner_subscriptions');

                    // Send notification to admin when subscription is purchased
                    if ($update_result) {
                        try {
                            // Get provider name with translation support
                            $provider_name = get_translated_partner_field($partner_id, 'company_name');
                            if (empty($provider_name)) {
                                $partner_data = fetch_details('partner_details', ['partner_id' => $partner_id], ['company_name']);
                                $provider_name = !empty($partner_data) && !empty($partner_data[0]['company_name']) ? $partner_data[0]['company_name'] : 'Provider';
                            }

                            // Get subscription name
                            $subscription_name = $subscription_details[0]['name'] ?? 'Subscription';

                            // Get transaction amount
                            $amount = $paid_amount;

                            // Get currency from event or settings
                            $currency = $event['currency'] ?? get_settings('general_settings', true)['currency'] ?? 'USD';

                            // Format dates for display
                            $purchase_date_formatted = date('d-m-Y', strtotime($purchaseDate));
                            $expiry_date_formatted = date('d-m-Y', strtotime($expiryDate));

                            // Prepare context data for notification templates
                            $context = [
                                'provider_id' => $partner_id,
                                'provider_name' => $provider_name,
                                'subscription_id' => $subscription_id,
                                'subscription_name' => $subscription_name,
                                'purchase_date' => $purchase_date_formatted,
                                'expiry_date' => $expiry_date_formatted,
                                'duration' => $subscriptionDuration,
                                'amount' => number_format($amount, 2),
                                'currency' => $currency,
                                'transaction_id' => (string)$transaction_id
                            ];

                            // Queue notification to admin users (group_id = 1)
                            queue_notification_service(
                                eventType: 'subscription_purchased',
                                recipients: [],
                                context: $context,
                                options: [
                                    'channels' => ['fcm', 'email', 'sms'],
                                    'user_groups' => [1], // Admin user group
                                    'platforms' => ['admin_panel']
                                ]
                            );
                            // log_message('info', '[SUBSCRIPTION_PURCHASED] Notification queued for admin - Provider: ' . $provider_name . ', Subscription: ' . $subscription_name);
                        } catch (\Throwable $notificationError) {
                            // Log error but don't fail the subscription activation
                            log_message('error', '[SUBSCRIPTION_PURCHASED] Notification error: ' . $notificationError->getMessage());
                        }
                    }

                    // If no existing subscription was updated, create a new one
                    if (!$update_result) {
                        // Check if there's already an active subscription for this partner
                        $existing_subscription = fetch_details('partner_subscriptions', [
                            'partner_id' => $partner_id,
                            'status' => 'active'
                        ]);

                        if (!empty($existing_subscription)) {
                            // Deactivate existing subscription
                            update_details(['status' => 'expired'], ['id' => $existing_subscription[0]['id']], 'partner_subscriptions');
                        }

                        // Add new subscription
                        add_subscription($subscription_id, $partner_id, $transaction_id);
                        log_message('error', 'Xendit Webhook - New partner subscription created');
                    } else {
                        log_message('error', 'Xendit Webhook - Partner subscription updated successfully');
                    }
                }

                log_message('error', 'Xendit Webhook - Subscription payment processed successfully for partner: ' . $partner_id . ', transaction_id: ' . $transaction_id . ', subscription_id: ' . $subscription_id);
            } else {
                log_message('error', 'Xendit Webhook - No transaction found for external_id: ' . $external_id . ', partner_id: ' . $partner_id . ', subscription_id: ' . $subscription_id);
            }
        } catch (\Exception $th) {
            log_message('error', 'Xendit Webhook - Error processing subscription payment: ' . $th->getMessage());
        }
    }

    private function handle_xendit_additional_charges_payment($event, $additional_charges_transaction_id, $payment_id, $paid_amount)
    {
        try {
            // Store additional charges data in reference column as JSON
            $additional_charges_reference_data = [
                'payment_id' => $payment_id,
                'invoice_id' => $event['id'] ?? '',
                'payment_method' => $event['payment_method'] ?? '',
                'webhook_type' => 'additional_charges_payment',
                'paid_at' => $event['paid_at'] ?? date('Y-m-d H:i:s'),
                'raw_event' => $event
            ];

            // Update additional charges transaction
            update_details([
                'status' => 'success',
                'amount' => $paid_amount,
                'txn_id' => $payment_id,
                'currency_code' => $event['currency'] ?? 'IDR',
                'reference' => json_encode($additional_charges_reference_data),
            ], ['id' => $additional_charges_transaction_id], 'transactions');

            // Update order additional charges payment status
            $transaction = fetch_details('transactions', ['id' => $additional_charges_transaction_id]);

            if (!empty($transaction)) {
                $order_id = $transaction[0]['order_id'];
                update_details(['payment_status_of_additional_charge' => '1'], ['id' => $order_id], 'orders');

                // Get order data to get customer ID
                $order_data = fetch_details('orders', ['id' => $order_id]);
                if (!empty($order_data)) {
                    $user_id = $order_data[0]['user_id'];

                    // Send online payment success notification to customer for additional charges
                    // This notification is specifically for payment status and redirects to booking details screen
                    $payment_data = [
                        'amount' => $paid_amount,
                        'currency' => $event['currency'] ?? 'IDR',
                        'transaction_id' => $payment_id,
                        'payment_method' => $event['payment_method'] ?? 'Online Payment',
                        'paid_at' => $event['paid_at'] ?? date('d-m-Y H:i:s')
                    ];
                    $this->send_online_payment_status_notification($order_id, $user_id, 'success', $payment_data);
                }
            }

            log_message('error', 'Xendit Webhook - Additional charges payment processed successfully');
        } catch (\Exception $th) {
            log_message('error', 'Xendit Webhook - Error processing additional charges payment: ' . $th->getMessage());
        }
    }

    private function handle_xendit_order_payment($event, $external_id, $order_id, $payment_id, $paid_amount)
    {
        try {
            $order_data = fetch_details('orders', ['id' => $order_id]);
            if (empty($order_data)) {
                log_message('error', 'Xendit Webhook - Order not found: ' . $order_id);
                return false;
            }

            $user_id = $order_data[0]['user_id'];
            $partner_id = $order_data[0]['partner_id'];

            $transaction = fetch_details('transactions', ['order_id' => $order_id, 'txn_id' => $external_id]);
            if (!empty($transaction)) {
                $transaction_id = $transaction[0]['id'];

                // Store order payment data in reference column as JSON
                $order_payment_reference_data = [
                    'payment_id' => $payment_id,
                    'external_id' => $external_id,
                    'invoice_id' => $event['id'] ?? '',
                    'payment_method' => $event['payment_method'] ?? '',
                    'webhook_type' => 'order_payment',
                    'paid_at' => $event['paid_at'] ?? date('Y-m-d H:i:s'),
                    'raw_event' => $event
                ];

                update_details([
                    'status' => 'success',
                    'amount' => $paid_amount,
                    'txn_id' => $payment_id,
                    'currency_code' => $event['currency'] ?? 'IDR',
                    'message' => 'Order placed successfully',
                    'reference' => json_encode($order_payment_reference_data),
                ], ['id' => $transaction_id], 'transactions');

                update_details(['payment_status' => 1], ['id' => $order_id], 'orders');
                update_custom_job_status($order_id, 'booked');

                // Clear cart
                $cart_data = fetch_cart(true, $user_id);
                if (!empty($cart_data)) {
                    foreach ($cart_data['data'] as $row) {
                        delete_details(['id' => $row['id']], 'cart');
                    }
                }

                // Send booking notifications (provider and customer)
                $this->send_xendit_order_notifications($order_id, $user_id, $partner_id);

                // Send online payment success notification to customer
                // This notification is specifically for payment status and redirects to booking details screen
                $payment_data = [
                    'amount' => $paid_amount,
                    'currency' => $event['currency'] ?? 'IDR',
                    'transaction_id' => $payment_id,
                    'payment_method' => $event['payment_method'] ?? 'Online Payment',
                    'paid_at' => $event['paid_at'] ?? date('d-m-Y H:i:s')
                ];
                $this->send_online_payment_status_notification($order_id, $user_id, 'success', $payment_data);

                log_message('error', 'Xendit Webhook - Payment processed successfully for order: ' . $order_id);
                return true;
            }

            return false;
        } catch (\Exception $th) {
            log_message('error', 'Xendit Webhook - Error processing order payment: ' . $th->getMessage());
            return false;
        }
    }

    private function handle_xendit_invoice_expired($event)
    {
        try {
            $external_id = $event['external_id'] ?? '';

            $invoice_type = explode('_', $external_id);
            if ($invoice_type[0] == 'subscription') {
                $subscription_id = $invoice_type[1];
                $partner_id = $invoice_type[2];
                // Update subscription status
                update_details(['status' => 'expired'], [
                    'subscription_id' => $subscription_id,
                    'partner_id' => $partner_id,
                    'status' => 'active'
                ], 'partner_subscriptions');

                log_message('error', 'Xendit Webhook - Subscription expired for partner: ' . $partner_id);

                // Send notifications to provider when subscription expires via webhook
                // NotificationService handles FCM, Email, and SMS notifications using templates
                // Single generalized template works for provider notifications
                try {
                    // Get subscription and provider details for notification context
                    $subscription_details = fetch_details('partner_subscriptions', [
                        'subscription_id' => $subscription_id,
                        'partner_id' => $partner_id
                    ]);

                    if (!empty($subscription_details)) {
                        $subscription = $subscription_details[0];
                        $provider_id = $partner_id;
                        $provider_details = fetch_details('users', ['id' => $provider_id], ['username', 'email']);

                        $provider_name = !empty($provider_details) && !empty($provider_details[0]['username']) ? $provider_details[0]['username'] : 'Provider';

                        // Get subscription name
                        $subscription_name = $subscription['name'] ?? '';
                        if (empty($subscription_name) && !empty($subscription_id)) {
                            $subscription_info = fetch_details('subscriptions', ['id' => $subscription_id], ['name']);
                            $subscription_name = !empty($subscription_info) && !empty($subscription_info[0]['name']) ? $subscription_info[0]['name'] : 'Subscription';
                        }

                        // Format dates for display
                        $expiry_date = !empty($subscription['expiry_date']) ? date('d-m-Y', strtotime($subscription['expiry_date'])) : date('d-m-Y');
                        $purchase_date = !empty($subscription['purchase_date']) ? date('d-m-Y', strtotime($subscription['purchase_date'])) : '';
                        $duration = $subscription['duration'] ?? '';

                        // Prepare context data for notification templates
                        $notificationContext = [
                            'subscription_id' => (string)$subscription_id,
                            'subscription_name' => $subscription_name,
                            'provider_id' => $provider_id,
                            'provider_name' => $provider_name,
                            'expiry_date' => $expiry_date,
                            'purchase_date' => $purchase_date,
                            'duration' => $duration
                        ];

                        // Queue all notifications (FCM, Email, SMS) to provider using NotificationService
                        queue_notification_service(
                            eventType: 'subscription_expired',
                            recipients: ['user_id' => $provider_id],
                            context: $notificationContext,
                            options: [
                                'channels' => ['fcm', 'email', 'sms'],
                                'language' => $this->defaultLanguage,
                                'platforms' => ['android', 'ios', 'provider_panel'],
                                'type' => 'subscription',
                                'data' => [
                                    'subscription_id' => (string)$subscription_id,
                                    'provider_id' => (string)$provider_id,
                                    'click_action' => 'FLUTTER_NOTIFICATION_CLICK'
                                ]
                            ]
                        );

                        // log_message('info', '[XENDIT_SUBSCRIPTION_EXPIRED_NOTIFICATION] Notification queued for provider: ' . $provider_id . ', Subscription: ' . $subscription_name . ', Result: ' . json_encode($result));
                    }
                } catch (\Throwable $notificationError) {
                    // Log error but don't fail the webhook processing
                    log_message('error', '[XENDIT_SUBSCRIPTION_EXPIRED_NOTIFICATION] Notification error trace: ' . $notificationError->getTraceAsString());
                }

                return;
            }

            // Handle additional charges expiration
            if (isset($event['metadata']['additional_charges_transaction_id'])) {
                $additional_charges_transaction_id = $event['metadata']['additional_charges_transaction_id'];
                update_details(['status' => 'failed'], ['id' => $additional_charges_transaction_id], 'transactions');

                $transaction = fetch_details('transactions', ['id' => $additional_charges_transaction_id]);
                if (!empty($transaction)) {
                    $order_id = $transaction[0]['order_id'];
                    update_details(['payment_status_of_additional_charge' => '2'], ['id' => $order_id], 'orders');
                }

                log_message('error', 'Xendit Webhook - Additional charges expired');
                return;
            }

            $invoice_type = explode('_', $external_id);
            if ($invoice_type[0] == 'order') {
                $order_id = $invoice_type[1];

                // Update order status to cancelled
                update_details(['payment_status' => 2], ['id' => $order_id], 'orders');
                update_details(['status' => 'cancelled'], ['id' => $order_id], 'orders');

                $order_data = fetch_details('orders', ['id' => $order_id]);
                if (!empty($order_data)) {
                    $user_id = $order_data[0]['user_id'];
                    $partner_id = $order_data[0]['partner_id'];

                    $transaction_data = [
                        'transaction_type' => 'transaction',
                        'user_id' => $user_id,
                        'partner_id' => $partner_id,
                        'order_id' => $order_id,
                        'type' => 'xendit',
                        'txn_id' => '',
                        'amount' => $event['amount'] ?? 0,
                        'status' => 'failed',
                        'currency_code' => $event['currency'] ?? 'IDR',
                        'message' => 'Payment expired - Booking cancelled',
                        'external_id' => $external_id
                    ];

                    add_transaction($transaction_data);
                }

                log_message('error', 'Xendit Webhook - Invoice expired for order: ' . $order_id);
            }
        } catch (\Exception $th) {
            log_message('error', 'Xendit Webhook - Error processing expired invoice: ' . $th->getMessage());
        }
    }

    private function handle_xendit_invoice_failed($event)
    {
        try {
            $external_id = $event['external_id'] ?? '';

            // Handle subscription payment failure
            $invoice_type = explode('_', $external_id);
            if ($invoice_type[0] == 'subscription') {
                $subscription_id = $invoice_type[1];
                $partner_id = $invoice_type[2];

                // Update subscription status
                update_details(['status' => 'failed'], [
                    'subscription_id' => $subscription_id,
                    'partner_id' => $partner_id,
                    'status' => 'pending'
                ], 'partner_subscriptions');

                $transaction = fetch_details('transactions', ['subscription_id' => $subscription_id, 'txn_id' => $event['external_id']], ['id']);
                if (!empty($transaction)) {
                    $transaction_id = $transaction[0]['id'];
                    update_details([
                        'status' => 'failed',
                        'txn_id' => $event['external_id'],
                        'amount' => $event['amount'] ?? 0,
                        'currency_code' => $event['currency'] ?? 'IDR',
                    ], ['id' => $transaction_id], 'transactions');

                    // Send payment failed notification to provider
                    $failure_reason = $event['failure_reason'] ?? 'Payment could not be processed.';
                    send_subscription_payment_status_notification($transaction_id, 'failed', $failure_reason);
                }

                log_message('error', 'Xendit Webhook - Subscription payment failed for partner: ' . $partner_id);
                return;
            }

            // Handle additional charges payment failure
            if (isset($event['metadata']['additional_charges_transaction_id'])) {
                $additional_charges_transaction_id = $event['metadata']['additional_charges_transaction_id'];
                update_details(['status' => 'failed'], ['id' => $additional_charges_transaction_id], 'transactions');

                $transaction = fetch_details('transactions', ['id' => $additional_charges_transaction_id]);
                if (!empty($transaction)) {
                    $order_id = $transaction[0]['order_id'];
                    update_details(['payment_status_of_additional_charge' => '2'], ['id' => $order_id], 'orders');
                    update_details(['status' => 'failed'], ['id' => $additional_charges_transaction_id], 'transactions');

                    // Get order data to get customer ID
                    $order_data = fetch_details('orders', ['id' => $order_id]);
                    if (!empty($order_data)) {
                        $user_id = $order_data[0]['user_id'];

                        // Send online payment failed notification to customer for additional charges
                        // This notification is specifically for payment status and redirects to booking details screen
                        $failure_reason = $event['failure_reason'] ?? 'Payment could not be processed.';
                        $payment_data = [
                            'amount' => $event['amount'] ?? 0,
                            'currency' => $event['currency'] ?? 'IDR',
                            'transaction_id' => $event['external_id'] ?? '',
                            'payment_method' => $event['payment_method'] ?? 'Online Payment',
                            'failure_reason' => $failure_reason
                        ];
                        $this->send_online_payment_status_notification($order_id, $user_id, 'failed', $payment_data);
                    }
                }

                log_message('error', 'Xendit Webhook - Additional charges payment failed');
                return;
            }

            // Handle order payment failure
            if ($invoice_type[0] == 'order') {
                $order_id = $invoice_type[1];

                // Update order status to failed
                update_details(['payment_status' => 2, 'status' => 'cancelled'], ['id' => $order_id], 'orders');

                $transaction_result = fetch_details('transactions', ['order_id' => $order_id, 'txn_id' => $event['external_id']], ['id']);
                if (!empty($transaction_result)) {
                    $transaction_id = $transaction_result[0]['id'];
                    update_details([
                        'status' => 'failed',
                        'txn_id' => $event['external_id'],
                        'amount' => $event['amount'] ?? 0,
                        'currency_code' => $event['currency'] ?? 'IDR',
                    ], ['id' => $transaction_id], 'transactions');
                }

                // Get order data to get customer ID
                $order_data = fetch_details('orders', ['id' => $order_id]);
                if (!empty($order_data)) {
                    $user_id = $order_data[0]['user_id'];

                    // Send online payment failed notification to customer
                    // This notification is specifically for payment status and redirects to booking details screen
                    $failure_reason = $event['failure_reason'] ?? 'Payment could not be processed.';
                    $payment_data = [
                        'amount' => $event['amount'] ?? 0,
                        'currency' => $event['currency'] ?? 'IDR',
                        'transaction_id' => $event['external_id'] ?? '',
                        'payment_method' => $event['payment_method'] ?? 'Online Payment',
                        'failure_reason' => $failure_reason
                    ];
                    $this->send_online_payment_status_notification($order_id, $user_id, 'failed', $payment_data);
                }

                log_message('error', 'Xendit Webhook - Payment failed for order: ' . $order_id);
            }
        } catch (\Exception $th) {
            log_message('error', 'Xendit Webhook - Error processing failed payment: ' . $th->getMessage());
        }
    }

    /**
     * Unified method to send booking notifications for online payment webhooks
     * Uses NotificationService to send notifications via all channels (FCM, Email, SMS)
     * This matches the approach used for COD payments in V1.php
     * 
     * @param int $order_id The order ID
     * @param int $user_id The customer user ID
     * @param int $partner_id The provider/partner user ID
     * @return void
     */
    private function send_booking_notifications($order_id, $user_id, $partner_id)
    {
        try {
            // Get order data to extract total amount
            $order_data = fetch_details('orders', ['id' => $order_id]);
            if (empty($order_data)) {
                log_message('error', '[WEBHOOK_NOTIFICATION] Order not found: ' . $order_id);
                return;
            }

            // Get the order total amount for notification context
            $final_total = $order_data[0]['total'] ?? 0;

            // Use default language for webhooks (no request context available)
            $language = $this->defaultLanguage;

            // Prepare context data for notification templates
            // This context will be used by NotificationService to extract variables
            $notificationContext = [
                'provider_id' => $partner_id,
                'user_id' => $user_id,
                'booking_id' => $order_id,
                'amount' => $final_total,
            ];

            // Queue notifications using NotificationService for all channels (FCM, Email, SMS)
            // This unified approach handles all notification channels consistently
            // Notifications are queued for background processing to improve performance

            // Queue notifications to provider (FCM, Email, SMS)
            // NotificationService automatically checks notification settings and unsubscribe status
            queue_notification_service(
                eventType: 'new_booking_received_for_provider',
                recipients: ['user_id' => $partner_id],
                context: $notificationContext,
                options: [
                    'channels' => ['fcm', 'email', 'sms'], // All channels
                    'language' => $language,
                    'platforms' => ['android', 'ios', 'web', 'provider_panel'] // Provider platforms
                ]
            );
            // log_message('info', '[WEBHOOK_NOTIFICATION] Provider notification queued, job ID: ' . ($result ?: 'N/A'));

            // Queue notifications to admin users (group_id = 1) (FCM, Email, SMS)
            // Admin users should also be notified about new bookings
            // queue_notification_service(
            //     eventType: 'new_booking_received_for_provider',
            //     recipients: [],
            //     context: $notificationContext,
            //     options: [
            //         'user_groups' => [1], // Admin user group
            //         'channels' => ['fcm', 'email', 'sms'], // All channels
            //         'language' => $language,
            //         'platforms' => ['admin_panel'] // Admin panel platform
            //     ]
            // );
            // log_message('info', '[WEBHOOK_NOTIFICATION] Admin notification queued, job ID: ' . ($result ?: 'N/A'));

            // Queue notifications to customer (FCM, Email, SMS)
            // NotificationService automatically checks notification settings and unsubscribe status
            queue_notification_service(
                eventType: 'new_booking_confirmation_to_customer',
                recipients: ['user_id' => $user_id],
                context: $notificationContext,
                options: [
                    'channels' => ['fcm', 'email', 'sms'], // All channels
                    'language' => $language,
                    'platforms' => ['android', 'ios', 'web'] // Customer platforms
                ]
            );
            // log_message('info', '[WEBHOOK_NOTIFICATION] Customer notification queued, job ID: ' . ($result ?: 'N/A'));
        } catch (\Throwable $notificationError) {
            // Log error but don't fail the webhook processing
            log_message('error', '[WEBHOOK_NOTIFICATION] Notification error trace: ' . $notificationError->getTraceAsString());
        }
    }

    /**
     * Legacy method for Xendit - kept for backward compatibility
     * Now uses the unified send_booking_notifications method
     * 
     * @param int $order_id The order ID
     * @param int $user_id The customer user ID
     * @param int $partner_id The provider/partner user ID
     * @return void
     */
    private function send_xendit_order_notifications($order_id, $user_id, $partner_id)
    {
        // Use the unified notification method
        $this->send_booking_notifications($order_id, $user_id, $partner_id);
    }

    /**
     * Send online payment status notification to customer
     * Handles success, failed, and pending payment statuses
     * Sends notifications via FCM, Email, and SMS channels
     * 
     * @param int $order_id The order/booking ID
     * @param int $user_id The customer user ID
     * @param string $status Payment status: 'success', 'failed', or 'pending'
     * @param array $payment_data Additional payment data (amount, currency, transaction_id, payment_method, failure_reason, etc.)
     * @return void
     */
    private function send_online_payment_status_notification($order_id, $user_id, $status, $payment_data = [])
    {
        try {
            // Validate status
            $valid_statuses = ['success', 'failed', 'pending'];
            if (!in_array($status, $valid_statuses)) {
                log_message('error', '[ONLINE_PAYMENT_NOTIFICATION] Invalid status: ' . $status);
                return;
            }

            // Map status to event type
            $event_type_map = [
                'success' => 'online_payment_success',
                'failed' => 'online_payment_failed',
                'pending' => 'online_payment_pending'
            ];
            $event_type = $event_type_map[$status];

            // Get order data to extract total amount if not provided
            $order_data = fetch_details('orders', ['id' => $order_id]);
            if (empty($order_data)) {
                log_message('error', '[ONLINE_PAYMENT_NOTIFICATION] Order not found: ' . $order_id);
                return;
            }

            // Get customer details for notification context
            $customer_details = fetch_details('users', ['id' => $user_id], ['username', 'email']);
            $customer_name = !empty($customer_details) && !empty($customer_details[0]['username']) ? $customer_details[0]['username'] : 'Customer';
            $customer_email = !empty($customer_details) && !empty($customer_details[0]['email']) ? $customer_details[0]['email'] : '';

            // Extract payment data with defaults
            $amount = $payment_data['amount'] ?? $order_data[0]['total'] ?? 0;
            $currency = $payment_data['currency'] ?? $order_data[0]['currency_code'] ?? 'IDR';
            $transaction_id = $payment_data['transaction_id'] ?? $payment_data['txn_id'] ?? '';
            $payment_method = $payment_data['payment_method'] ?? 'Online Payment';
            $failure_reason = $payment_data['failure_reason'] ?? 'Payment could not be processed.';
            $paid_at = $payment_data['paid_at'] ?? date('d-m-Y H:i:s');

            // Format amount for display
            $formatted_amount = number_format($amount, 2);

            // Prepare context data for notification templates
            // This context will be used to populate template variables like [[booking_id]], [[amount]], [[currency]], etc.
            $notificationContext = [
                'booking_id' => (string)$order_id,
                'order_id' => (string)$order_id,
                'amount' => $formatted_amount,
                'currency' => $currency,
                'transaction_id' => (string)$transaction_id,
                'customer_id' => (string)$user_id,
                'customer_name' => $customer_name,
                'customer_email' => $customer_email,
                'payment_method' => $payment_method,
            ];

            // Add status-specific context
            if ($status === 'success') {
                $notificationContext['paid_at'] = $paid_at;
            } elseif ($status === 'failed') {
                $notificationContext['failure_reason'] = $failure_reason;
            }

            // Queue all notifications (FCM, Email, SMS) to customer using NotificationService
            // NotificationService automatically handles:
            // - Translation of templates based on user language
            // - Variable replacement in templates
            // - Notification settings checking for each channel
            // - Fetching user email/phone/FCM tokens
            // - Unsubscribe status checking for email
            queue_notification_service(
                eventType: $event_type,
                recipients: ['user_id' => $user_id],
                context: $notificationContext,
                options: [
                    'channels' => ['fcm', 'email', 'sms'], // All channels
                    'language' => $this->defaultLanguage,
                    'platforms' => ['android', 'ios', 'web'], // Customer platforms
                    'type' => 'payment', // Notification type for app routing
                    'data' => [
                        'order_id' => (string)$order_id,
                        'booking_id' => (string)$order_id,
                        'transaction_id' => (string)$transaction_id,
                        'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                        'redirect_to' => 'booking_details_screen' // Redirect to booking details screen
                    ]
                ]
            );

            // log_message('info', '[ONLINE_PAYMENT_NOTIFICATION] Notification queued for customer: ' . $user_id . ', Order: ' . $order_id . ', Status: ' . $status . ', Result: ' . json_encode($result));
        } catch (\Throwable $notificationError) {
            // Log error but don't fail the webhook processing
            log_message('error', '[ONLINE_PAYMENT_NOTIFICATION] Notification error trace: ' . $notificationError->getTraceAsString());
        }
    }

    /**
     * Handle successful Xendit refund webhook
     * Supports both new standardized format and legacy ewallet format
     * 
     * @param array $event Webhook event data
     * @param string $format Format type: 'new_format' or 'legacy_format'
     * @return void
     */
    private function handle_xendit_refund_processed($event, $format = 'legacy_format')
    {
        try {
            log_message('error', 'Xendit Webhook | REFUND PROCESSED (Format: ' . $format . ')');

            // Extract refund information based on format
            if ($format === 'new_format') {
                // New standardized refund webhook format
                // Data is nested in the 'data' object according to Xendit docs
                $refund_data = $event['data'] ?? [];
                $business_id = $event['business_id'] ?? '';
                $webhook_created = $event['created'] ?? '';

                $refund_id = $refund_data['id'] ?? '';
                $external_id = $refund_data['reference_id'] ?? $refund_data['invoice_id'] ?? '';
                $refund_amount = $refund_data['amount'] ?? 0;
                $currency = $refund_data['currency'] ?? 'IDR';
                $refund_reason = $refund_data['reason'] ?? 'Refund processed';
                $payment_id = $refund_data['payment_id'] ?? '';
                $payment_method_type = $refund_data['payment_method_type'] ?? '';
                $channel_code = $refund_data['channel_code'] ?? '';
                $status = $refund_data['status'] ?? 'SUCCEEDED';

                log_message('error', 'New Format - Business ID: ' . $business_id . ', Webhook Created: ' . $webhook_created);
            } elseif ($format === 'ewallet_format') {
                // E-wallet specific refund format with nested data structure
                $refund_data = $event['data'] ?? [];
                $business_id = $event['business_id'] ?? '';
                $webhook_created = $event['created'] ?? '';

                $refund_id = $refund_data['id'] ?? '';
                $external_id = $refund_data['reference_id'] ?? '';
                $refund_amount = $refund_data['refund_amount'] ?? 0; // Note: ewallet uses 'refund_amount' not 'amount'
                $currency = $refund_data['currency'] ?? 'IDR';
                $refund_reason = $refund_data['reason'] ?? 'Refund processed';
                $payment_id = $refund_data['charge_id'] ?? ''; // Note: ewallet uses 'charge_id' not 'payment_id'
                $payment_method_type = 'EWALLET'; // E-wallet format
                $channel_code = $refund_data['channel_code'] ?? '';
                $status = $refund_data['status'] ?? 'SUCCEEDED';
                $capture_amount = $refund_data['capture_amount'] ?? 0;

                log_message('error', 'E-wallet Format - Business ID: ' . $business_id . ', Charge ID: ' . $payment_id . ', Channel: ' . $channel_code);
            } else {
                // Legacy format (older formats)
                $refund_id = $event['id'] ?? '';
                $external_id = $event['external_id'] ?? $event['invoice_id'] ?? '';
                $refund_amount = $event['amount'] ?? 0;
                $currency = $event['currency'] ?? 'IDR';
                $refund_reason = $event['reason'] ?? 'Refund processed';
                $payment_id = $event['payment_id'] ?? '';
                $payment_method_type = $event['payment_method_type'] ?? '';
                $channel_code = $event['channel_code'] ?? '';
                $status = 'SUCCEEDED'; // Legacy assumes success
            }

            // Store additional refund data in reference column as JSON
            $reference_data = [
                'refund_id' => $refund_id,
                'external_id' => $external_id,
                'payment_id' => $payment_id,
                'refund_reason' => $refund_reason,
                'payment_method_type' => $payment_method_type,
                'channel_code' => $channel_code,
                'status' => $status,
                'refund_fee' => $format === 'new_format' ? ($refund_data['refund_fee_amount'] ?? 0) : ($event['fee'] ?? 0),
                'webhook_type' => 'refund_processed',
                'webhook_format' => $format,
                'business_id' => ($format === 'new_format' || $format === 'ewallet_format') ? $business_id : '',
                'webhook_created' => ($format === 'new_format' || $format === 'ewallet_format') ? $webhook_created : '',
                'processed_at' => date('Y-m-d H:i:s'),
                'raw_event' => $event
            ];

            // Add ewallet-specific fields if this is an ewallet refund
            if ($format === 'ewallet_format') {
                $reference_data['capture_amount'] = $capture_amount ?? 0;
                $reference_data['charge_id'] = $payment_id; // Store the original charge_id
                $reference_data['refund_amount_to_payer'] = $refund_data['refund_amount_to_payer'] ?? null;
                $reference_data['payer_captured_amount'] = $refund_data['payer_captured_amount'] ?? null;
                $reference_data['payer_captured_currency'] = $refund_data['payer_captured_currency'] ?? null;
            }

            log_message('error', 'Xendit Refund ID: ' . $refund_id);
            log_message('error', 'Xendit External ID: ' . $external_id);

            // Find the original successful transaction
            $success_transaction = [];

            // Try to find by payment_id first (most accurate)
            if (!empty($payment_id)) {
                $success_transaction = fetch_details('transactions', [
                    'transaction_type' => 'transaction',
                    'type' => 'xendit',
                    'status' => 'success',
                    'txn_id' => $payment_id
                ]);

                // For ewallet format, also log the charge_id being searched
                if ($format === 'ewallet_format') {
                    log_message('error', 'Searching for ewallet transaction with Charge ID: ' . $payment_id);
                }
            }

            // Fallback: try to find by external_id
            if (empty($success_transaction) && !empty($external_id)) {
                $success_transaction = fetch_details('transactions', [
                    'transaction_type' => 'transaction',
                    'type' => 'xendit',
                    'status' => 'success',
                    'txn_id' => $external_id
                ]);
            }

            if (!empty($success_transaction)) {
                $original_transaction = $success_transaction[0];
                $order_id = $original_transaction['order_id'];
                $user_id = $original_transaction['user_id'];
                $partner_id = $original_transaction['partner_id'];
                $original_txn_id = $original_transaction['txn_id'];

                log_message('error', 'Xendit Refund - Found original transaction for Order ID: ' . $order_id);

                // Check if refund transaction already exists to avoid duplicates
                $existing_refund = fetch_details('transactions', [
                    'transaction_type' => 'refund',
                    'type' => 'xendit',
                    'message' => 'xendit_refund',
                    'order_id' => $order_id
                ]);

                if (!empty($existing_refund)) {
                    // Update existing refund transaction
                    log_message('error', 'Xendit Refund - Updating existing refund transaction');
                    $refund_update_data = [
                        'status' => 'succeeded',
                        'amount' => $refund_amount,
                        'currency_code' => $currency,
                        'reference' => json_encode($reference_data),
                        'updated_at' => date('Y-m-d H:i:s')
                    ];
                    update_details($refund_update_data, ['id' => $existing_refund[0]['id']], 'transactions');
                } else {
                    // Create new refund transaction
                    log_message('error', 'Xendit Refund - Creating new refund transaction');
                    $refund_transaction_data = [
                        'transaction_type' => 'refund',
                        'user_id' => $user_id,
                        'partner_id' => $partner_id,
                        'order_id' => $order_id,
                        'type' => 'xendit',
                        'txn_id' => $original_txn_id,
                        'amount' => $refund_amount,
                        'status' => 'succeeded',
                        'currency_code' => $currency,
                        'message' => 'xendit_refund',
                        'reference' => json_encode($reference_data)
                    ];

                    $refund_insert_id = add_transaction($refund_transaction_data);

                    if ($refund_insert_id) {
                        log_message('error', 'Xendit Refund - Transaction created successfully with ID: ' . $refund_insert_id);
                    }
                }

                // Update order status to refunded
                update_custom_job_status($order_id, 'refunded');

                log_message('error', 'Xendit Webhook | Refund processed successfully for Order ID: ' . $order_id);

                // Send notifications to user and admin when refund is successfully processed
                // NotificationService handles FCM, Email, and SMS notifications using templates
                // Single generalized template works for both user and admin
                try {
                    // Get user and order details for notification context
                    $user_details = fetch_details('users', ['id' => $user_id], ['username', 'email']);
                    $order_details = fetch_details('orders', ['id' => $order_id], ['total']);

                    $customer_name = !empty($user_details) && !empty($user_details[0]['username']) ? $user_details[0]['username'] : 'Customer';
                    $customer_email = !empty($user_details) && !empty($user_details[0]['email']) ? $user_details[0]['email'] : '';

                    // Get refund transaction ID (use the refund_id from Xendit or the transaction ID)
                    $refund_transaction_id = $refund_id ?? $original_txn_id;

                    // Prepare context data for notification templates (generalized for both user and admin)
                    // This context will be used to populate template variables like [[order_id]], [[amount]], [[currency]], etc.
                    $notificationContext = [
                        'order_id' => $order_id,
                        'booking_id' => $order_id, // Add booking_id for template variables
                        'amount' => number_format($refund_amount, 2),
                        'currency' => $currency,
                        'refund_id' => $refund_id ?? '',
                        'transaction_id' => $refund_transaction_id,
                        'customer_name' => $customer_name,
                        'customer_email' => $customer_email,
                        'customer_id' => $user_id,
                        'processed_date' => date('d-m-Y H:i:s')
                    ];

                    // Queue all notifications (FCM, Email, SMS) to user using NotificationService
                    // Send payment_refund_executed notification to customer (redirects to booking details)
                    // NotificationService automatically handles:
                    // - Translation of templates based on user language
                    // - Variable replacement in templates
                    // - Notification settings checking for each channel
                    // - Fetching user email/phone/FCM tokens
                    // - Unsubscribe status checking for email
                    queue_notification_service(
                        eventType: 'payment_refund_executed',
                        recipients: [],
                        context: $notificationContext,
                        options: [
                            'channels' => ['fcm', 'email', 'sms'], // All channels handled by NotificationService
                            'user_ids' => [$user_id], // Send only to this specific customer
                            'language' => $this->defaultLanguage,
                            'platforms' => ['android', 'ios', 'web'], // User platforms for FCM
                            'type' => 'refund', // Notification type for app routing
                            'data' => [
                                'order_id' => (string)$order_id,
                                'booking_id' => (string)$order_id,
                                'refund_id' => (string)$refund_id,
                                'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                                'redirect_to' => 'booking_details_screen'
                            ]
                        ]
                    );

                    // log_message('info', '[REFUND_SUCCESSFUL_USER_NOTIFICATION] Notification queued for user: ' . $user_id . ', Result: ' . json_encode($userResult));

                    // Queue all notifications (FCM, Email, SMS) to admin using NotificationService
                    // Send to admin panel users (user group 1 is typically admin)
                    // Uses the same generalized template and context
                    queue_notification_service(
                        eventType: 'payment_refund_successful',
                        recipients: [],
                        context: $notificationContext,
                        options: [
                            'channels' => ['fcm', 'email', 'sms'], // All channels handled by NotificationService
                            'language' => $this->defaultLanguage,
                            'user_groups' => [1], // Admin user group
                            'platforms' => ['admin_panel'], // Admin platform for FCM
                            'type' => 'refund', // Notification type for app routing
                            'data' => [
                                'order_id' => (string)$order_id,
                                'refund_id' => (string)$refund_id,
                                'click_action' => 'FLUTTER_NOTIFICATION_CLICK'
                            ]
                        ]
                    );

                    // log_message('info', '[REFUND_SUCCESSFUL_ADMIN_NOTIFICATION] Notification queued for admin, Result: ' . json_encode($adminResult));
                } catch (\Throwable $notificationError) {
                    // Log error but don't fail the refund processing
                    log_message('error', '[REFUND_SUCCESSFUL_NOTIFICATION] Notification error trace: ' . $notificationError->getTraceAsString());
                }
            } else {
                log_message('error', 'Xendit Webhook | Original transaction not found for refund. Payment ID: ' . $payment_id . ', External ID: ' . $external_id);
            }
        } catch (\Exception $th) {
            log_message('error', 'Xendit Webhook - Error processing refund: ' . $th->getMessage());
        }
    }

    /**
     * Handle failed Xendit refund webhook
     * Supports both new standardized format and legacy ewallet format
     * 
     * @param array $event Webhook event data
     * @param string $format Format type: 'new_format' or 'legacy_format'
     * @return void
     */
    private function handle_xendit_refund_failed($event, $format = 'legacy_format')
    {
        try {
            log_message('error', 'Xendit Webhook | REFUND FAILED (Format: ' . $format . ')');

            // Extract refund information based on format
            if ($format === 'new_format') {
                // New standardized refund webhook format
                // Data is nested in the 'data' object according to Xendit docs
                $refund_data = $event['data'] ?? [];
                $business_id = $event['business_id'] ?? '';
                $webhook_created = $event['created'] ?? '';

                $refund_id = $refund_data['id'] ?? '';
                $external_id = $refund_data['reference_id'] ?? $refund_data['invoice_id'] ?? '';
                $refund_amount = $refund_data['amount'] ?? 0;
                $currency = $refund_data['currency'] ?? 'IDR';
                $failure_reason = $refund_data['reason'] ?? 'Refund failed';
                $payment_id = $refund_data['payment_id'] ?? '';
                $failure_code = $refund_data['failure_code'] ?? '';
                $payment_method_type = $refund_data['payment_method_type'] ?? '';
                $channel_code = $refund_data['channel_code'] ?? '';
                $status = $refund_data['status'] ?? 'FAILED';

                log_message('error', 'New Format - Business ID: ' . $business_id . ', Failure Code: ' . $failure_code);
            } elseif ($format === 'ewallet_format') {
                // E-wallet specific refund format with nested data structure
                $refund_data = $event['data'] ?? [];
                $business_id = $event['business_id'] ?? '';
                $webhook_created = $event['created'] ?? '';

                $refund_id = $refund_data['id'] ?? '';
                $external_id = $refund_data['reference_id'] ?? '';
                $refund_amount = $refund_data['refund_amount'] ?? 0; // Note: ewallet uses 'refund_amount' not 'amount'
                $currency = $refund_data['currency'] ?? 'IDR';
                $failure_reason = $refund_data['reason'] ?? 'Refund failed';
                $payment_id = $refund_data['charge_id'] ?? ''; // Note: ewallet uses 'charge_id' not 'payment_id'
                $failure_code = $refund_data['failure_code'] ?? '';
                $payment_method_type = 'EWALLET'; // E-wallet format
                $channel_code = $refund_data['channel_code'] ?? '';
                $status = $refund_data['status'] ?? 'FAILED';

                log_message('error', 'E-wallet Failed Format - Business ID: ' . $business_id . ', Charge ID: ' . $payment_id . ', Channel: ' . $channel_code);
            } else {
                // Legacy format (older formats)
                $refund_id = $event['id'] ?? '';
                $external_id = $event['external_id'] ?? $event['invoice_id'] ?? '';
                $refund_amount = $event['amount'] ?? 0;
                $currency = $event['currency'] ?? 'IDR';
                $failure_reason = $event['failure_reason'] ?? $event['reason'] ?? 'Refund failed';
                $payment_id = $event['payment_id'] ?? '';
                $failure_code = $event['failure_code'] ?? '';
                $payment_method_type = $event['payment_method_type'] ?? '';
                $channel_code = $event['channel_code'] ?? '';
                $status = 'FAILED'; // Legacy assumes failure
            }

            // Store additional refund failure data in reference column as JSON
            $reference_data = [
                'refund_id' => $refund_id,
                'external_id' => $external_id,
                'payment_id' => $payment_id,
                'failure_reason' => $failure_reason,
                'failure_code' => $failure_code,
                'payment_method_type' => $payment_method_type,
                'channel_code' => $channel_code,
                'status' => $status,
                'webhook_type' => 'refund_failed',
                'webhook_format' => $format,
                'business_id' => ($format === 'new_format' || $format === 'ewallet_format') ? $business_id : '',
                'webhook_created' => ($format === 'new_format' || $format === 'ewallet_format') ? $webhook_created : '',
                'failed_at' => date('Y-m-d H:i:s'),
                'raw_event' => $event
            ];

            // Add ewallet-specific fields if this is an ewallet refund failure
            if ($format === 'ewallet_format') {
                $reference_data['charge_id'] = $payment_id; // Store the original charge_id
            }

            log_message('error', 'Xendit Refund Failed ID: ' . $refund_id);
            log_message('error', 'Xendit Refund Failure Reason: ' . $failure_reason);

            // Find the original successful transaction
            $success_transaction = [];

            // Try to find by payment_id first (most accurate)
            if (!empty($payment_id)) {
                $success_transaction = fetch_details('transactions', [
                    'transaction_type' => 'transaction',
                    'type' => 'xendit',
                    'status' => 'success',
                    'txn_id' => $payment_id
                ]);
            }

            // Fallback: try to find by external_id
            if (empty($success_transaction) && !empty($external_id)) {
                $success_transaction = fetch_details('transactions', [
                    'transaction_type' => 'transaction',
                    'type' => 'xendit',
                    'status' => 'success',
                    'txn_id' => $external_id
                ]);
            }

            if (!empty($success_transaction)) {
                $original_transaction = $success_transaction[0];
                $order_id = $original_transaction['order_id'];
                $user_id = $original_transaction['user_id'];
                $partner_id = $original_transaction['partner_id'];

                log_message('error', 'Xendit Refund Failed - Found original transaction for Order ID: ' . $order_id);

                // Check if refund transaction already exists
                $existing_refund = fetch_details('transactions', [
                    'transaction_type' => 'refund',
                    'type' => 'xendit',
                    'message' => 'xendit_refund',
                    'order_id' => $order_id
                ]);

                if (!empty($existing_refund)) {
                    // Update existing refund transaction to failed status
                    log_message('error', 'Xendit Refund Failed - Updating existing refund transaction to failed');
                    $refund_update_data = [
                        'status' => 'failed',
                        'amount' => $refund_amount,
                        'currency_code' => $currency,
                        'reference' => json_encode($reference_data),
                        'updated_at' => date('Y-m-d H:i:s')
                    ];
                    update_details($refund_update_data, ['id' => $existing_refund[0]['id']], 'transactions');
                } else {
                    // Create new failed refund transaction for tracking
                    log_message('error', 'Xendit Refund Failed - Creating new failed refund transaction');
                    $refund_transaction_data = [
                        'transaction_type' => 'refund',
                        'user_id' => $user_id,
                        'partner_id' => $partner_id,
                        'order_id' => $order_id,
                        'type' => 'xendit',
                        'txn_id' => $original_transaction['txn_id'],
                        'amount' => $refund_amount,
                        'status' => 'failed',
                        'currency_code' => $currency,
                        'message' => 'xendit_refund',
                        'reference' => json_encode($reference_data)
                    ];

                    $refund_insert_id = add_transaction($refund_transaction_data);

                    if ($refund_insert_id) {
                        log_message('error', 'Xendit Refund Failed - Transaction created successfully with ID: ' . $refund_insert_id);
                    }
                }

                log_message('error', 'Xendit Webhook | Refund failed for Order ID: ' . $order_id . '. Reason: ' . $failure_reason);
            } else {
                log_message('error', 'Xendit Webhook | Original transaction not found for failed refund. Payment ID: ' . $payment_id . ', External ID: ' . $external_id);
            }
        } catch (\Exception $th) {
            log_message('error', 'Xendit Webhook - Error processing failed refund: ' . $th->getMessage());
        }
    }
}
