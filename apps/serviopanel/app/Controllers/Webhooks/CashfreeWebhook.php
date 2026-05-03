<?php

namespace App\Controllers\Webhooks;

use App\Controllers\BaseController;
use App\Libraries\Cashfree;

class CashfreeWebhook extends BaseController
{
    private Cashfree $cashfree;

    public function __construct()
    {
        helper('api');
        helper('function');
        $this->cashfree = new Cashfree();
    }

    public function index()
    {
        try {
            log_message('info', '[CASHFREE WEBHOOK] Request received. Method: ' . ($_SERVER['REQUEST_METHOD'] ?? 'unknown'));

            if (strtoupper($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
                log_message('info', '[CASHFREE WEBHOOK] Rejected: method not allowed');
                return $this->response->setStatusCode(405)->setJSON([
                    'error' => true,
                    'message' => 'Method not allowed',
                ]);
            }

            $raw_body = file_get_contents('php://input');
            $event = json_decode($raw_body, true);

            // Log full payload for debugging (payload is from Cashfree, no user secrets in order_id/payment_id).
            log_message('info', '[CASHFREE WEBHOOK] Raw payload: ' . $raw_body);

            if (empty($event) || !is_array($event)) {
                log_message('info', '[CASHFREE WEBHOOK] Rejected: invalid or empty JSON payload');
                return $this->response->setStatusCode(400)->setJSON([
                    'error' => true,
                    'message' => 'Invalid payload',
                ]);
            }

            $signature = $_SERVER['HTTP_X_WEBHOOK_SIGNATURE'] ?? '';
            $timestamp = $_SERVER['HTTP_X_WEBHOOK_TIMESTAMP'] ?? '';
            log_message('info', '[CASHFREE WEBHOOK] Verifying signature (timestamp present: ' . ($timestamp !== '' ? 'yes' : 'no') . ')');

            if (!$this->cashfree->verify_webhook_signature($raw_body, (string) $signature, (string) $timestamp)) {
                log_message('error', '[CASHFREE WEBHOOK] Invalid signature – webhook rejected');
                return $this->response->setStatusCode(401)->setJSON([
                    'error' => true,
                    'message' => 'Invalid signature',
                ]);
            }
            log_message('info', '[CASHFREE WEBHOOK] Signature verified OK');

            $gateway_order_id = (string) ($event['data']['order']['order_id'] ?? '');
            $cf_payment_id = (string) ($event['data']['payment']['cf_payment_id'] ?? '');
            $payment_status = strtoupper((string) ($event['data']['payment']['payment_status'] ?? ''));
            $event_type = strtoupper((string) ($event['type'] ?? ''));
            $currency = strtoupper((string) ($event['data']['order']['order_currency'] ?? ''));
            $amount = (float) ($event['data']['payment']['payment_amount'] ?? ($event['data']['order']['order_amount'] ?? 0));
            $order_tags = $event['data']['order']['order_tags'] ?? [];
            if (is_object($order_tags)) {
                $order_tags = json_decode(json_encode($order_tags), true);
            }
            $subscription_transaction_id = (int) ($order_tags['transaction_id'] ?? 0);

            log_message('info', '[CASHFREE WEBHOOK] Parsed event_type=' . $event_type . ' gateway_order_id=' . $gateway_order_id . ' cf_payment_id=' . $cf_payment_id . ' payment_status=' . $payment_status . ' amount=' . $amount . ' currency=' . $currency);

            if (strpos($event_type, 'REFUND') !== false) {
                log_message('info', '[CASHFREE WEBHOOK] Refund event detected. event_type=' . $event_type . ' gateway_order_id=' . $gateway_order_id);
            }

            // Log refund data if present (for future refund handling or debugging).
            $refund_data = $event['data']['refund'] ?? null;
            if ($refund_data !== null) {
                $refund_id = $refund_data['cf_refund_id'] ?? $refund_data['refund_id'] ?? '';
                $refund_status = $refund_data['refund_status'] ?? $refund_data['status'] ?? '';
                $refund_amount = $refund_data['refund_amount'] ?? $refund_data['amount'] ?? '';
                $refund_reason = $refund_data['refund_reason'] ?? $refund_data['reason'] ?? '';
                log_message('info', '[CASHFREE WEBHOOK] Refund data present – refund_id=' . $refund_id . ' status=' . $refund_status . ' amount=' . $refund_amount . ' reason=' . $refund_reason . ' order_id=' . $gateway_order_id);
            }

            if (empty($gateway_order_id)) {
                log_message('info', '[CASHFREE WEBHOOK] Ignored: gateway order id missing in payload');
                return $this->response->setJSON([
                    'error' => false,
                    'message' => 'Ignored: order id missing',
                ]);
            }

            $context = $this->extractOrderContext($gateway_order_id);
            $order_id = (int) ($context['order_id'] ?? 0);
            $additional_txn_id = (int) ($context['additional_charges_transaction_id'] ?? 0);
            $is_additional_charge = (bool) ($context['is_additional_charge'] ?? false);
            if (empty($subscription_transaction_id)) {
                $subscription_transaction_id = (int) ($context['subscription_transaction_id'] ?? 0);
            }
            log_message('info', '[CASHFREE WEBHOOK] Context: order_id=' . $order_id . ' is_additional_charge=' . ($is_additional_charge ? '1' : '0') . ' subscription_transaction_id=' . $subscription_transaction_id);

            // We only mark payments as successful when Cashfree explicitly sets
            // payment_status to SUCCESS. This avoids clearing the cart or updating
            // orders on ambiguous webhook types or intermediate events where the
            // final payment outcome is not a confirmed success.
            $is_success = ($payment_status === 'SUCCESS');
            $is_failed = ($payment_status === 'FAILED');
            $transaction_status = $is_success ? 'success' : ($is_failed ? 'failed' : 'pending');

            log_message('info', '[CASHFREE WEBHOOK] Payment outcome: payment_status=' . $payment_status . ' is_success=' . ($is_success ? '1' : '0') . ' is_failed=' . ($is_failed ? '1' : '0') . ' transaction_status=' . $transaction_status);

            if (!empty($subscription_transaction_id)) {
                log_message('info', '[CASHFREE WEBHOOK] Branch: subscription payment. subscription_transaction_id=' . $subscription_transaction_id);

                $subscription_tx = fetch_details('transactions', ['id' => $subscription_transaction_id], ['id', 'user_id', 'subscription_id']);
                if (!empty($subscription_tx[0])) {
                    $partner_id = (int) $subscription_tx[0]['user_id'];
                    $subscription_id = (int) $subscription_tx[0]['subscription_id'];
                    log_message('info', '[CASHFREE WEBHOOK] Subscription meta: subscription_id=' . $subscription_id . ' partner_id=' . $partner_id . ' transaction_id=' . $subscription_transaction_id);

                    update_details([
                        'status' => $transaction_status,
                        'txn_id' => $cf_payment_id,
                        'currency_code' => $currency,
                        'reference' => $gateway_order_id,
                    ], ['id' => $subscription_transaction_id], 'transactions');
                    log_message('info', '[CASHFREE WEBHOOK] Subscription transaction updated: id=' . $subscription_transaction_id . ' status=' . $transaction_status . ' cf_payment_id=' . $cf_payment_id);

                    send_subscription_payment_status_notification($subscription_transaction_id, $transaction_status);

                    if ($is_success && !empty($subscription_id) && !empty($partner_id)) {
                        $details_for_subscription = fetch_details('subscriptions', ['id' => $subscription_id]);
                        if (!empty($details_for_subscription[0])) {
                            $purchaseDate = date('Y-m-d');
                            $subscriptionDuration = $details_for_subscription[0]['duration'];
                            $expiryDate = date('Y-m-d', strtotime($purchaseDate . ' + ' . $subscriptionDuration . ' days'));
                            if ($subscriptionDuration == "unlimited") {
                                $subscriptionDuration = 0;
                            }

                            update_details(
                                [
                                    'status' => 'active',
                                    'is_payment' => '1',
                                    'purchase_date' => $purchaseDate,
                                    'expiry_date' => $expiryDate,
                                    'updated_at' => date('Y-m-d H:i:s')
                                ],
                                [
                                    'subscription_id' => $subscription_id,
                                    'partner_id' => $partner_id,
                                    'status !=' => 'active',
                                    'transaction_id' => $subscription_transaction_id,
                                ],
                                'partner_subscriptions'
                            );
                            log_message('info', '[CASHFREE WEBHOOK] Subscription activated: subscription_id=' . $subscription_id . ' partner_id=' . $partner_id . ' expiry_date=' . $expiryDate);
                        }
                    } else {
                        log_message('info', '[CASHFREE WEBHOOK] Subscription not activated (is_success=' . ($is_success ? '1' : '0') . ' subscription_id=' . $subscription_id . ' partner_id=' . $partner_id . ')');
                    }
                } else {
                    log_message('info', '[CASHFREE WEBHOOK] Subscription transaction not found for id=' . $subscription_transaction_id);
                }

                log_message('info', '[CASHFREE WEBHOOK] Subscription branch completed. Returning.');
                return $this->response->setJSON([
                    'error' => false,
                    'message' => 'Webhook processed',
                ]);
            }

            if ($is_additional_charge || !empty($additional_txn_id)) {
                log_message('info', '[CASHFREE WEBHOOK] Branch: additional charge. order_id=' . $order_id . ' additional_txn_id=' . $additional_txn_id);

                if (!empty($additional_txn_id)) {
                    update_details([
                        'status' => $transaction_status,
                        'txn_id' => $cf_payment_id,
                        'currency_code' => $currency,
                        'reference' => $gateway_order_id,
                    ], ['id' => $additional_txn_id], 'transactions');
                    log_message('info', '[CASHFREE WEBHOOK] Additional charge transaction updated: id=' . $additional_txn_id . ' status=' . $transaction_status);
                }

                if (!empty($order_id)) {
                    $additional_payment_status = $is_success ? '1' : ($is_failed ? '2' : '0');
                    update_details(['payment_status_of_additional_charge' => $additional_payment_status], ['id' => $order_id], 'orders');
                    log_message('info', '[CASHFREE WEBHOOK] Additional charge order status updated: order_id=' . $order_id . ' payment_status_of_additional_charge=' . $additional_payment_status);
                }

                log_message('info', '[CASHFREE WEBHOOK] Additional charge branch completed. Returning.');
                return $this->response->setJSON([
                    'error' => false,
                    'message' => 'Webhook processed',
                ]);
            }

            log_message('info', '[CASHFREE WEBHOOK] Branch: order payment. Looking up transaction by reference=' . $gateway_order_id);

            // Server-side verification: only mark order paid if Cashfree API confirms order is PAID.
            // Prevents test UPI "back to merchant" or duplicate webhooks from marking unpaid orders as paid.
            $is_success_verified = $is_success;
            if ($is_success && !empty($gateway_order_id)) {
                $is_success_verified = $this->verifyOrderPaidWithCashfree($gateway_order_id);
                log_message('info', '[CASHFREE WEBHOOK] API verification: is_success_verified=' . ($is_success_verified ? '1' : '0') . ' gateway_order_id=' . $gateway_order_id);
            }

            $transaction = fetch_details(
                'transactions',
                ['reference' => $gateway_order_id, 'type' => 'cashfree'],
                ['id', 'order_id', 'user_id', 'partner_id'],
                1,
                0,
                'id',
                'DESC'
            );

            if (!empty($transaction[0])) {
                $order_id = (int) ($transaction[0]['order_id'] ?? $order_id);
                log_message('info', '[CASHFREE WEBHOOK] Order transaction found: txn_id=' . $transaction[0]['id'] . ' order_id=' . $order_id . ' user_id=' . ($transaction[0]['user_id'] ?? '') . ' partner_id=' . ($transaction[0]['partner_id'] ?? ''));
                update_details([
                    'status' => $transaction_status,
                    'txn_id' => $cf_payment_id,
                    'currency_code' => $currency,
                    'reference' => $gateway_order_id,
                ], ['id' => $transaction[0]['id']], 'transactions');
                log_message('info', '[CASHFREE WEBHOOK] Order transaction updated: id=' . $transaction[0]['id'] . ' status=' . $transaction_status);
            } else {
                log_message('info', '[CASHFREE WEBHOOK] No existing transaction for reference=' . $gateway_order_id . ' order_id(from context)=' . $order_id);

                // One transaction per order: if Cashfree sends a different reference per payment attempt
                // (e.g. wrong OTP then correct OTP on the same checkout), find the existing Cashfree
                // transaction for this order and update it instead of creating a new one.
                $order_id_for_lookup = $order_id;
                if ($order_id_for_lookup <= 0 && !empty($order_tags['order_id'])) {
                    $order_id_for_lookup = (int) $order_tags['order_id'];
                }
                if ($order_id_for_lookup > 0) {
                    $existing_by_order = fetch_details(
                        'transactions',
                        ['order_id' => $order_id_for_lookup, 'type' => 'cashfree'],
                        ['id', 'order_id', 'user_id', 'partner_id'],
                        1,
                        0,
                        'id',
                        'DESC'
                    );
                    if (!empty($existing_by_order[0])) {
                        $transaction = $existing_by_order;
                        $order_id = (int) ($transaction[0]['order_id'] ?? $order_id);
                        log_message('info', '[CASHFREE WEBHOOK] Found existing transaction by order_id: id=' . $transaction[0]['id'] . ' — updating instead of creating new.');
                        update_details([
                            'status' => $transaction_status,
                            'txn_id' => $cf_payment_id,
                            'currency_code' => $currency,
                            'reference' => $gateway_order_id,
                        ], ['id' => $transaction[0]['id']], 'transactions');
                        log_message('info', '[CASHFREE WEBHOOK] Order transaction updated: id=' . $transaction[0]['id'] . ' status=' . $transaction_status);
                    }
                }
            }

            // Idempotency: only change order state if it is not already in that state.
            // This prevents spurious SUCCESS webhooks (e.g. after user cancels/retries)
            // from marking an order paid and clearing the cart when no real payment occurred.
            $order_current = !empty($order_id)
                ? fetch_details('orders', ['id' => $order_id], ['id', 'payment_status', 'user_id'])
                : [];
            $order_already_paid = !empty($order_current[0]) && (int) ($order_current[0]['payment_status'] ?? 0) === 1;
            $current_status = !empty($order_current[0]) ? ($order_current[0]['payment_status'] ?? 'n/a') : 'n/a';
            log_message('info', '[CASHFREE WEBHOOK] Idempotency: order_id=' . $order_id . ' current_payment_status=' . $current_status . ' order_already_paid=' . ($order_already_paid ? '1' : '0'));

            if (!empty($order_id)) {
                if ($is_success_verified && !$order_already_paid) {
                    update_details(['payment_status' => 1], ['id' => $order_id], 'orders');
                    update_custom_job_status($order_id, 'booked');
                    log_message('info', '[CASHFREE WEBHOOK] Order status updated: order_id=' . $order_id . ' payment_status=1(paid) custom_job_status=booked');
                } elseif ($is_failed && !$order_already_paid) {
                    // Only mark failed if order was not already paid (avoid overwriting paid with failed).
                    update_details(['payment_status' => 2], ['id' => $order_id], 'orders');
                    update_details(['status' => 'cancelled'], ['id' => $order_id], 'orders');
                    update_custom_job_status($order_id, 'cancelled');
                    log_message('info', '[CASHFREE WEBHOOK] Order status updated: order_id=' . $order_id . ' payment_status=2(failed) status=cancelled custom_job_status=cancelled');
                } else {
                    log_message('info', '[CASHFREE WEBHOOK] Order status not changed: order_id=' . $order_id . ' (already_paid or SUCCESS not verified by API or non-final status)');
                }
            }

            if (empty($transaction[0]) && !empty($order_id) && ($is_success || $is_failed)) {
                $order_data = fetch_details('orders', ['id' => $order_id], ['id', 'user_id', 'partner_id']);
                if (!empty($order_data[0])) {
                    add_transaction([
                        'transaction_type' => 'transaction',
                        'user_id' => $order_data[0]['user_id'],
                        'partner_id' => $order_data[0]['partner_id'],
                        'order_id' => $order_id,
                        'type' => 'cashfree',
                        'txn_id' => $cf_payment_id,
                        'amount' => $amount,
                        'status' => $transaction_status,
                        'currency_code' => $currency,
                        'message' => $is_success_verified ? 'Order placed successfully' : ($is_failed ? 'Booking is cancelled' : 'Payment pending or unverified'),
                        'reference' => $gateway_order_id,
                    ]);
                    log_message('info', '[CASHFREE WEBHOOK] New transaction created for order: order_id=' . $order_id . ' user_id=' . $order_data[0]['user_id'] . ' partner_id=' . $order_data[0]['partner_id'] . ' status=' . $transaction_status);
                }
            }

            // Only clear cart when we actually just marked this order as paid (API-verified).
            // If order was already paid or webhook SUCCESS was not verified by API, do not clear cart.
            if ($is_success_verified && !empty($order_id) && !$order_already_paid) {
                $user_id_for_cart = 0;
                if (!empty($transaction[0]['user_id'])) {
                    $user_id_for_cart = (int) $transaction[0]['user_id'];
                } elseif (!empty($order_current[0]['user_id'])) {
                    $user_id_for_cart = (int) $order_current[0]['user_id'];
                } else {
                    $order_data_for_cart = fetch_details('orders', ['id' => $order_id], ['user_id']);
                    if (!empty($order_data_for_cart[0]['user_id'])) {
                        $user_id_for_cart = (int) $order_data_for_cart[0]['user_id'];
                    }
                }
                log_message('info', '[CASHFREE WEBHOOK] Cart clear: order_id=' . $order_id . ' user_id=' . $user_id_for_cart);
                $this->removeOrderedServicesFromCart($order_id, $user_id_for_cart);
            } else {
                log_message('info', '[CASHFREE WEBHOOK] Cart not cleared: is_success_verified=' . ($is_success_verified ? '1' : '0') . ' order_id=' . $order_id . ' order_already_paid=' . ($order_already_paid ? '1' : '0'));
            }

            log_message('info', '[CASHFREE WEBHOOK] Order branch completed. Returning success.');
            return $this->response->setJSON([
                'error' => false,
                'message' => 'Webhook processed',
            ]);
        } catch (\Throwable $th) {
            log_message('error', '[CASHFREE WEBHOOK] Exception: ' . $th->getMessage() . ' in ' . $th->getFile() . ':' . $th->getLine());
            return $this->response->setStatusCode(500)->setJSON([
                'error' => true,
                'message' => 'Something went wrong',
            ]);
        }
    }

    /**
     * Verify with Cashfree API that the order is actually PAID before we mark it paid.
     * Prevents "back to merchant" / test UPI or duplicate webhooks from marking unpaid orders as paid.
     *
     * @param string $gateway_order_id Cashfree order_id (e.g. order_177_1773210628)
     * @return bool true only if API returns order_status PAID
     */
    private function verifyOrderPaidWithCashfree(string $gateway_order_id): bool
    {
        $response = $this->cashfree->fetch_order($gateway_order_id);
        if (!empty($response['error'])) {
            log_message('info', '[CASHFREE WEBHOOK] API verification failed: fetch_order error. gateway_order_id=' . $gateway_order_id . ' message=' . ($response['message'] ?? ''));
            return false;
        }
        // Cashfree Get Order returns order_status: PAID | ACTIVE | EXPIRED | TERMINATED | etc.
        // Handle both flat and nested response (SDK may return order_status at root or under 'order').
        $order_status = '';
        if (isset($response['order_status'])) {
            $order_status = strtoupper((string) $response['order_status']);
        } elseif (isset($response['order']['order_status'])) {
            $order_status = strtoupper((string) $response['order']['order_status']);
        }
        if ($order_status !== 'PAID') {
            log_message('info', '[CASHFREE WEBHOOK] API verification: order not PAID. gateway_order_id=' . $gateway_order_id . ' order_status=' . ($order_status ?: 'empty'));
            return false;
        }
        return true;
    }

    private function extractOrderContext(string $gateway_order_id): array
    {
        $result = [
            'order_id' => 0,
            'additional_charges_transaction_id' => 0,
            'is_additional_charge' => false,
            'subscription_transaction_id' => 0,
        ];

        if (preg_match('/^additional_charges_(\d+)_\d+$/', $gateway_order_id, $matches)) {
            $result['is_additional_charge'] = true;
            $result['order_id'] = (int) $matches[1];
            return $result;
        }

        if (preg_match('/^order_(\d+)_\d+$/', $gateway_order_id, $matches)) {
            $result['order_id'] = (int) $matches[1];
            return $result;
        }

        if (preg_match('/^subs_(\d+)_(\d+)_\d+$/', $gateway_order_id, $matches)) {
            $result['subscription_transaction_id'] = (int) $matches[1];
            return $result;
        }

        if (is_numeric($gateway_order_id)) {
            $result['order_id'] = (int) $gateway_order_id;
        }

        return $result;
    }

    private function removeOrderedServicesFromCart(int $order_id, int $user_id): void
    {
        if ($order_id <= 0 || $user_id <= 0) {
            return;
        }

        $order_services = fetch_details('order_services', ['order_id' => $order_id], ['service_id']);
        if (empty($order_services)) {
            return;
        }

        $service_ids = [];
        foreach ($order_services as $service_row) {
            $service_id = (int) ($service_row['service_id'] ?? 0);
            if ($service_id > 0) {
                $service_ids[] = $service_id;
            }
        }
        $service_ids = array_unique($service_ids);

        foreach ($service_ids as $service_id) {
            delete_details(['user_id' => $user_id, 'service_id' => $service_id], 'cart');
        }
    }
}
