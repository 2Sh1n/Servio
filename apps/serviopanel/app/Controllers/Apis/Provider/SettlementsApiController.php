<?php

namespace App\Controllers\Apis\Provider;

use App\Controllers\BaseController;
use App\Models\Settlement_CashCollection_history_model;

class SettlementsApiController extends BaseController
{
    protected $request, $db, $data;
    protected $user_details = [];
   
    public function __construct()
    {
        helper('api');
        helper("function");
        helper('ResponceServices');
        $this->request = \Config\Services::request();
        $this->db = \Config\Database::connect();
       
        $token = verify_app_request();
        if (!$token['error'] && isset($token['data']) && !empty($token['data'])) {
            $this->user_details = $token['data'];
        } else {
            header('Content-Type: application/json');
            http_response_code($token['status']);
            print_r(json_encode([
                'error' => true,
                'message' => $token['message'],
                'status' => 401,
            ]));
            die();
        }
    }

    public function get_cash_collection()
    {
        try {
            $limit = $this->request->getPost('limit') ?: 10;
            $offset = $this->request->getPost('offset') ?: 0;
            $sort = $this->request->getPost('sort') ?: 'id';
            $order = $this->request->getPost('order') ?: 'DESC';
            $user_id = $this->user_details['id'];
            if (!exists(['id' => $user_id], 'users')) {
                $response = [
                    'error' => true,
                    'message' => labels(INVALID_USER_ID, 'Invalid User Id.'),
                    'data' => [],
                ];
                return $this->response->setJSON($response);
            }
            $where = ['partner_id' => $user_id];
            // Skip records where commison is zero or negative so partners only see actionable entries.
            $where['commison >'] = "0";
            if (!empty($this->request->getPost('admin_cash_recevied'))) {
                $where['status'] = "admin_cash_recevied";
            }
            if (!empty($this->request->getPost('provider_cash_recevied'))) {
                $where['status'] = "provider_cash_recevied";
            }
            $res = fetch_details('cash_collection', $where, '', $limit, $offset, $sort, $order);
            $payable_commision = fetch_details("users", ["id" => $this->user_details['id']], ['payable_commision']);
            if (!empty($res)) {
                foreach ($res as &$row) {
                    $row['translated_status'] = getTranslatedValue($row['status'], 'panel');
                }
            }
            $total = count($res);
            if (!empty($res)) {
                $response = [
                    'error' => false,
                    'message' => labels(CASH_COLLECTION_HISTORY_RECEIVED_SUCCESSFULLY, 'Cash collection history recieved successfully.'),
                    'total' => strval($total),
                    'payable_commision' => isset($payable_commision[0]['payable_commision']) ? $payable_commision[0]['payable_commision'] : "0",
                    'data' => $res,
                ];
                return $this->response->setJSON($response);
            } else {
                $response = [
                    'error' => true,
                    'message' => labels(NO_DATA_FOUND, 'No data found'),
                    'payable_commision' => isset($payable_commision[0]['payable_commision']) ? $payable_commision[0]['payable_commision'] : "0",
                    'data' => [],
                ];
                return $this->response->setJSON($response);
            }
        } catch (\Exception $th) {
            log_the_responce($this->request->header('Authorization') . '   Params passed :: ' . json_encode($_POST) . " Issue => " . $th, date("Y-m-d H:i:s") . '--> app/Controllers/partner/api/V1.php - get_cash_collection()');
            return $this->response->setJSON([
                'error' => true,
                'message' => labels(SOMETHING_WENT_WRONG, 'Something went wrong'),
                'data' => [],
            ]);
        }
    }

    public function get_settlement_history()
    {
        try {
            $limit = $this->request->getPost('limit') ?: 10;
            $offset = $this->request->getPost('offset') ?: 0;
            $sort = $this->request->getPost('sort') ?: 'id';
            $order = $this->request->getPost('order') ?: 'DESC';
            $status_filter = $this->request->getPost('status_filter') ?? null; // New filter

            $user_id = $this->user_details['id'];
            if (!exists(['id' => $user_id], 'users')) {
                $response = [
                    'error' => true,
                    'message' => labels(INVALID_USER_ID, 'Invalid User Id.'),
                    'data' => [],
                ];
                return $this->response->setJSON($response);
            }

            $filter = ['provider_id' => $user_id];

            if ($status_filter) {
                $status_map = ['credited' => 'credit', 'debited' => 'debit'];
                if (isset($status_map[$status_filter])) {
                    $filter['status'] = $status_map[$status_filter];
                }
            }

            $res = fetch_details('settlement_history', $filter, '', $limit, $offset, $sort, $order);

            $balance = fetch_details("users", ["id" => $user_id], ['balance', 'payable_commision']);
            $total = count($res);
            if (!empty($res)) {

                foreach ($res as &$value) { // Add "&" to modify the original array
                    if ($value['status'] == "credit") {
                        $value['status'] = "credited";
                    } elseif ($value['status'] == "debit") {
                        $value['status'] = "debited";
                    }
                    $value['translated_status'] = getTranslatedValue($value['status'], 'panel');
                }
                unset($value); // Unset reference to avoid unexpected behavior

                $response = [
                    'error' => false,
                    'message' => labels(SETTLEMENT_HISTORY_RECEIVED_SUCCESSFULLY, 'Settlement history recieved successfully.'),
                    'total' => $total,
                    'balance' => $balance[0]['balance'],
                    'data' => $res,
                ];
                return $this->response->setJSON($response);
            } else {
                $response = [
                    'error' => true,
                    'message' => labels(NO_DATA_FOUND, 'No data found'),
                    'data' => [],
                ];
                return $this->response->setJSON($response);
            }
        } catch (\Exception $th) {
            log_the_responce($this->request->header('Authorization') . '   Params passed :: ' . json_encode($_POST) . " Issue => " . $th, date("Y-m-d H:i:s") . '--> app/Controllers/partner/api/V1.php - get_settlement_history()');
            return $this->response->setJSON([
                'error' => true,
                'message' => labels(SOMETHING_WENT_WRONG, 'Something went wrong'),
                'data' => [],
            ]);
        }
    }

    public function get_booking_settle_manegement_history()
    {
        try {
            $limit = $this->request->getPost('limit') ?: 10;
            $offset = $this->request->getPost('offset') ?: 0;
            $sort = $this->request->getPost('sort') ?: 'id';
            $order = $this->request->getPost('order') ?: 'DESC';
            $search = $this->request->getPost('search') ?: '';
            $user_id = $this->user_details['id'];
            if (!exists(['id' => $user_id], 'users')) {
                $response = [
                    'error' => true,
                    'message' => labels(INVALID_USER_ID, 'Invalid User Id.'),
                    'data' => [],
                ];
                return $this->response->setJSON($response);
            }
            $where = ['sc.provider_id' => $user_id];
            $Settlement_CashCollection_history_model = new Settlement_CashCollection_history_model();
            $data = $Settlement_CashCollection_history_model->list($where, 'no', true, $limit, $offset, $sort, $order, $search);
            $for_total = $Settlement_CashCollection_history_model->list($where, 'no', true, 0, 0, $sort, $order, $search);
            if (!empty($data)) {
                $response = [
                    'error' => false,
                    'message' => labels(BOOKING_PAYMENT_HISTORY_RECEIVED_SUCCESSFULLY, 'Booking payment history recieved successfully.'),
                    'total' => count($for_total),
                    'data' => $data,
                ];
                return $this->response->setJSON($response);
            } else {
                $response = [
                    'error' => true,
                    'message' => labels(NO_DATA_FOUND, 'No data found'),
                    'data' => [],
                ];
                return $this->response->setJSON($response);
            }
        } catch (\Exception $th) {
            log_the_responce($this->request->header('Authorization') . '   Params passed :: ' . json_encode($_POST) . " Issue => " . $th, date("Y-m-d H:i:s") . '--> app/Controllers/partner/api/V1.php - get_booking_settle_manegement_history()');
            return $this->response->setJSON([
                'error' => true,
                'message' => labels(SOMETHING_WENT_WRONG, 'Something went wrong'),
                'data' => [],
            ]);
        }
    }
}
