<?php

namespace App\Controllers\Webhooks;

use App\Controllers\BaseController;
use App\Libraries\Paypal;

/**
 * Paypal webhook controller.
 *
 * Extracted from the legacy `Webhooks` controller so Paypal
 * follows the same pattern as Stripe / Razorpay / Paystack:
 *   - a dedicated controller per provider
 *   - single public `index()` entry point
 *
 * The core IPN verification logic is kept exactly the same:
 * we still use `$this->paypal_lib->validate_ipn($event)` on
 * the raw POST variables parsed from `php://input`.
 */
class PaypalWebhook extends BaseController
{
    /** @var Paypal */
    private $paypal_lib;

    /** @var array General settings (timezone, etc.) */
    protected $settings;

    /** @var string Default language for notifications (no request context in webhooks) */
    protected $defaultLanguage;

    public function __construct()
    {
        // Helpers and configuration are consistent with other webhooks
        helper('api');
        helper('function');

        $this->paypal_lib = new Paypal();
        $this->settings   = get_settings('general_settings', true);
        date_default_timezone_set($this->settings['system_timezone'] ?? 'UTC');
        $this->defaultLanguage = get_default_language();
    }

    /**
     * Main Paypal webhook entry point.
     *
     * This is a direct refactor of the old `Webhooks::paypal()`
     * method. Behaviour (especially IPN validation) is preserved.
     */
    public function index()
    {
        // Build the validation payload and parse raw body
        $req          = 'cmd=_notify-validate';
        $request_body = file_get_contents('php://input');
        parse_str($request_body, $event);

        log_message('error', 'paypal------' . var_export($event, true));

        $txn_id = isset($event['txn_id']) ? $event['txn_id'] : '';

        if (empty($request_body)) {
            // Nothing to process; keep behaviour simple and silent for Paypal
            return $this->response->setStatusCode(200)->setBody('');
        }

        // IMPORTANT: keep IPN verification exactly as before
        $ipnCheck = $this->paypal_lib->validate_ipn($event);

        if (!$ipnCheck) {
            log_message('error', 'IPN failed');
            return $this->response->setStatusCode(400)->setBody('IPN validation failed');
        }

        if (!empty($event['txn_id'])) {
            if (!empty($txn_id)) {
                $transaction = fetch_details('transactions', ['txn_id' => $txn_id]);
            }

            $amount   = $event['payment_gross'];
            $amount   = ($amount);
            $currency = isset($event['mc_currency']) ? $event['mc_currency'] : '';
        } else {
            $amount   = 0;
            $currency = isset($event['mc_currency']) ? $event['mc_currency'] : '';
        }

        $custom_data              = explode('|', $event['custom']);
        $is_subscripition         = $custom_data[2] ?? null;
        $is_for_additional_charge = $custom_data[2] ?? null;

        if (!empty($transaction)) {
            $order_id   = $transaction[0]['order_id'];
            $order_data = fetch_details('orders', ['id' => $order_id]);
            $user_id    = $order_data[0]['user_id'];
            $partner_id = $order_data[0]['partner_id'];
        } else {
            $order_id = 0;
            $order_id = isset($event['item_number']) ? $event['item_number'] : $event['item_number'];
            $order_data = fetch_details('orders', ['id' => $order_id]);
            if (!empty($order_data)) {
                $user_id    = $order_data[0]['user_id'];
                $partner_id = $order_data[0]['partner_id'];
            }
        }

        if ($event['payment_status'] == 'Completed') {
            if ($is_subscripition == 'subscription') {
                if (isset($event['custom']) && !empty($event['custom'])) {
                    $subsciption_data = explode('|', $event['custom']);
                    $transaction_id   = $subsciption_data[0] ?? null;
                    if (!empty($transaction_id) && $transaction_id != null) {
                        $transaction_details_for_subscription = fetch_details('transactions', ['id' => $transaction_id]);
                        if (!empty($transaction_details_for_subscription)) {
                            $details_for_subscription = fetch_details('subscriptions', ['id' => $transaction_details_for_subscription[0]['subscription_id']]);
                            log_message('error', 'FOR SUBSCRIPTION');
                            update_details(
                                ['status' => 'success', 'txn_id' => $txn_id],
                                ['id' => $transaction_id],
                                'transactions'
                            );
                            // Send payment successful notification to provider
                            send_subscription_payment_status_notification($transaction_id, 'success');

                            $purchaseDate         = date('Y-m-d');
                            $subscriptionDuration = $details_for_subscription[0]['duration'];
                            $expiryDate           = date('Y-m-d', strtotime($purchaseDate . ' + ' . $subscriptionDuration . ' days'));

                            if ($subscriptionDuration == 'unlimited') {
                                $subscriptionDuration = 0;
                            }

                            update_details(
                                [
                                    'status'        => 'active',
                                    'is_payment'    => '1',
                                    'purchase_date' => $purchaseDate,
                                    'expiry_date'   => $expiryDate,
                                    'updated_at'    => date('Y-m-d h:i:s'),
                                ],
                                [
                                    'subscription_id' => $transaction_details_for_subscription[0]['subscription_id'],
                                    'partner_id'      => $transaction_details_for_subscription[0]['user_id'],
                                    'status !='       => 'active',
                                    'transaction_id'  => $transaction_id,
                                ],
                                'partner_subscriptions'
                            );
                        }
                    }
                }
            } elseif (isset($is_for_additional_charge)) {
                log_message('error', 'FOR ADDITIONAL CHARGES');
                update_details(
                    ['status' => 'success', 'txn_id' => $txn_id],
                    ['id' => $custom_data[2]],
                    'transactions'
                );
                $order_data = fetch_details('orders', ['id' => $order_id]);
                update_details(
                    ['payment_status_of_additional_charge' => '1'],
                    ['id' => $order_id],
                    'orders'
                );
            } else {
                if (!empty($order_id)) {
                    // No need to add because the transaction is already added; just update the status
                    if (!empty($transaction)) {
                        $transaction_id = $transaction[0]['id'];
                        update_details(['status' => 'success'], ['id' => $transaction_id], 'transactions');
                    } else {
                        log_message('error', 'add transaction of the payment');
                        // add transaction of the payment
                        $currency = isset($event['mc_currency']) ? $event['mc_currency'] : '';
                        $data     = [
                            'transaction_type' => 'transaction',
                            'user_id'          => $user_id,
                            'partner_id'       => $partner_id,
                            'order_id'         => $order_id,
                            'type'             => 'paypal',
                            'txn_id'           => $txn_id,
                            'amount'           => $amount,
                            'status'           => 'success',
                            'currency_code'    => $currency,
                            'message'          => 'Order placed successfully',
                        ];
                        $insert_id = add_transaction($data);
                    }

                    if ($insert_id) {
                        update_details(['payment_status' => 1], ['id' => $order_id], 'orders');
                        update_custom_job_status($order_id, 'booked');

                        // Send notifications using unified NotificationService approach
                        $this->send_booking_notifications($order_id, $user_id, $partner_id);

                        $response['error']              = false;
                        $response['transaction_status'] = $event['payment_status'];
                        $response['message']            = 'Transaction successfully done';
                        log_message('error', 'Transaction successfully done');
                    } else {
                        $response['error']   = true;
                        $response['message'] = 'something went wrong';
                        log_message('error', 'something went wrong');
                    }

                    $response['error']              = false;
                    $response['transaction_status'] = $event['payment_status'];
                    $response['message']            = 'Transaction successfully done';
                    log_message('error', 'Transaction successfully done ');

                    return $this->response->setJSON($response);
                }
            }
        } elseif ($event['payment_status'] == 'Refunded') {
            log_message('error', 'Paypal Webhook | REFUND ');
            $success_transaction = fetch_details('transactions', [
                'transaction_type' => 'transaction',
                'type'             => 'paypal',
                'status'           => 'success',
                'txn_id'           => $txn_id,
            ]);

            if (!empty($success_transaction)) {
                $already_exist_refund_transaction = fetch_details('transactions', [
                    'transaction_type' => 'refund',
                    'type'             => 'paypal',
                    'message'          => 'paypal_refund',
                    'txn_id'           => $txn_id,
                ]);

                if (!empty($already_exist_refund_transaction)) {
                    $refund_data = [
                        'status' => 'success',
                    ];
                    update_details(
                        $refund_data,
                        ['id' => $already_exist_refund_transaction[0]['id']],
                        'transactions'
                    );
                } else {
                    $data = [
                        'transaction_type' => 'refund',
                        'user_id'          => $user_id,
                        'partner_id'       => $partner_id,
                        'order_id'         => $order_id,
                        'type'             => 'paypal',
                        'txn_id'           => $txn_id,
                        'amount'           => $amount,
                        'status'           => 'success',
                        'currency_code'    => $currency,
                        'message'          => 'paypal_refund',
                    ];
                    $insert_id = add_transaction($data);
                    update_custom_job_status($order_id, 'refunded');
                }
            }
        } else {
            log_message('error', 'Something went wrong1111');
        }

        log_message('error', 'SUCCESS');
        return $this->response->setStatusCode(200)->setBody('');
    }

    /**
     * Unified method to send booking notifications for Paypal webhook.
     * Uses NotificationService to send notifications via all channels (FCM, Email, SMS).
     *
     * This mirrors the implementation used in other webhook controllers.
     */
    private function send_booking_notifications($order_id, $user_id, $partner_id)
    {
        try {
            $order_data = fetch_details('orders', ['id' => $order_id]);
            if (empty($order_data)) {
                log_message('error', '[PAYPAL_WEBHOOK_NOTIFICATION] Order not found: ' . $order_id);
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

            // Provider notification
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

            // Customer notification
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
        } catch (\Throwable $notificationError) {
            log_message('error', '[PAYPAL_WEBHOOK_NOTIFICATION] Notification error trace: ' . $notificationError->getTraceAsString());
        }
    }
}

