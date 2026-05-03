<?php

namespace App\Models;

use CodeIgniter\Model;

class Settlement_model extends Model
{
    protected $DBGroup = 'default';
    protected $table = 'cities';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType     = 'array';
    protected $useSoftDeletes = true;
    protected $allowedFields = [
        'provider_id',
        'message',
        'status',
        'amount',
        'date'
    ];
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $deletedField  = 'deleted_at';
    public $base, $admin_id, $db;
    public function list($from_app = false, $search = '', $limit = 10, $offset = 0, $sort = 'id', $order = 'ASC', $where = [], $user_details = [])
    {
        $multipleWhere = '';
        $db      = \Config\Database::connect();
        $builder = $db->table('settlement_history');
        $sortable_fields = ['id' => 'id', 'amount' => 'amount'];
        $condition  = [];
        // Get current language for translation support
        $currentLang = get_current_language();
        $defaultLang = get_default_language();

        // Build search conditions - always include both original and translated fields
        // This ensures users can search by the partner name they see in the table (which may be translated)
        // Escape search term for safe LIKE queries to prevent SQL injection
        $escapedSearch = '';
        if (isset($search) and $search != '') {
            $escapedSearch = $db->escapeLikeString($search);
        }
        if (isset($_GET['id']) && $_GET['id'] != '') {
            $builder->where($condition);
        }
        if (isset($_POST['order'])) {
            $order = $_POST['order'];
        }

        // Count query - use orLike to match data query behavior
        // This ensures the total count matches the filtered results when searching
        $countBuilder = $db->table('settlement_history');
        $total_count = $countBuilder->select('COUNT(`settlement_history`.`id`) as `total`,pd.id as p_id')
            ->join('partner_details pd', 'settlement_history.provider_id = pd.partner_id', 'left')
            ->join('users u', 'settlement_history.provider_id = u.id', 'left');

        // Apply search conditions - search across all languages, not just current language
        // This allows users to search for provider names in any language translation
        if (isset($search) and $search != '') {
            $total_count->groupStart();
            
            // Search in base fields using LIKE with proper escaping
            $total_count->like('settlement_history.id', $escapedSearch);
            $total_count->orLike('settlement_history.amount', $escapedSearch);
            $total_count->orLike('settlement_history.message', $escapedSearch);
            $total_count->orLike('pd.company_name', $escapedSearch);
            $total_count->orLike('u.username', $escapedSearch);
            
            // Search in translated fields across ALL languages using EXISTS subquery
            // This allows searching for provider names in any language translation
            $translationSearchCondition = "EXISTS (
                SELECT 1 FROM translated_partner_details tpd_search 
                WHERE tpd_search.partner_id = settlement_history.provider_id 
                AND (
                    tpd_search.company_name LIKE '%{$escapedSearch}%' 
                    OR tpd_search.username LIKE '%{$escapedSearch}%'
                )
            )";
            $total_count->orWhere($translationSearchCondition, null, false);
            
            $total_count->groupEnd();
        }
        if (isset($where) && !empty($where)) {
            $total_count->where($where);
        }
        $total_count = $total_count->get()->getResultArray();
        $total = $total_count[0]['total'];

        // Data query - build with necessary joins
        $builder->select('settlement_history.* ,pd.id as p_id, pd.company_name, u.username as partner_name, u.image as partner_image, u.email as partner_email, u.phone as partner_phone')
            ->join('partner_details pd', 'settlement_history.provider_id = pd.partner_id', 'left')
            ->join('users u', 'settlement_history.provider_id = u.id', 'left');

        // Apply search conditions - search across all languages, not just current language
        // This allows users to search for provider names in any language translation
        if (isset($search) and $search != '') {
            $builder->groupStart();
            
            // Search in base fields using LIKE with proper escaping
            $builder->like('settlement_history.id', $escapedSearch);
            $builder->orLike('settlement_history.amount', $escapedSearch);
            $builder->orLike('settlement_history.message', $escapedSearch);
            $builder->orLike('pd.company_name', $escapedSearch);
            $builder->orLike('u.username', $escapedSearch);
            
            // Search in translated fields across ALL languages using EXISTS subquery
            // This allows searching for provider names in any language translation
            $translationSearchCondition = "EXISTS (
                SELECT 1 FROM translated_partner_details tpd_search 
                WHERE tpd_search.partner_id = settlement_history.provider_id 
                AND (
                    tpd_search.company_name LIKE '%{$escapedSearch}%' 
                    OR tpd_search.username LIKE '%{$escapedSearch}%'
                )
            )";
            $builder->orWhere($translationSearchCondition, null, false);
            
            $builder->groupEnd();
        }
        if (isset($where) && !empty($where)) {
            $builder->where($where);
        }
        $settlement_data = $builder->orderBy($sort, $order)->limit($limit, $offset)->get()->getResultArray();
        $db = \Config\Database::connect();
        $bulkData = array();
        $bulkData['total'] = $total;
        $rows = array();
        $tempRow = array();
        foreach ($settlement_data as $row) {
            $parter_details = (fetch_details('partner_details', ['partner_id' => $row['provider_id']]));
            $translatedModel = new \App\Models\TranslatedPartnerDetails_model();
            $translated_details = $translatedModel->getAllTranslationsForPartner($row['provider_id']);
            // Current language and default language are already set above

            // Re-index translations by language_code for easier access
            $translationsByLang = [];
            if (!empty($translated_details)) {
                foreach ($translated_details as $t) {
                    $translationsByLang[$t['language_code']] = $t;
                }
            }

            // Get translated company name with fallback logic
            $companyName = $parter_details[0]['company_name'] ?? ''; // fallback original
            if (!empty($translationsByLang)) {
                if (isset($translationsByLang[$currentLang]) && !empty($translationsByLang[$currentLang]['company_name'])) {
                    $companyName = $translationsByLang[$currentLang]['company_name'];
                } elseif (isset($translationsByLang[$defaultLang]) && !empty($translationsByLang[$defaultLang]['company_name'])) {
                    $companyName = $translationsByLang[$defaultLang]['company_name'];
                }
            }

            // Get translated partner name (username) with fallback logic
            // Partner name comes from users table (username field), which is stored as 'username' in translations
            $partnerName = $row['partner_name'] ?? ''; // fallback to username from query
            $companyName = $partner_details[0]['company_name'] ?? ''; // fallback to base table
            if (!empty($translationsByLang)) {
                if (isset($translationsByLang[$currentLang]) && !empty($translationsByLang[$currentLang]['username'])) {
                    $partnerName = $translationsByLang[$currentLang]['username'];
                } elseif (isset($translationsByLang[$defaultLang]) && !empty($translationsByLang[$defaultLang]['username'])) {
                    $partnerName = $translationsByLang[$defaultLang]['username'];
                }

                // Try current language first
                if (isset($translationsByLang[$currentLang]) && !empty($translationsByLang[$currentLang]['company_name'])) {
                    $companyName = $translationsByLang[$currentLang]['company_name'];
                }
                // Fallback to default language
                elseif (isset($translationsByLang[$defaultLang]) && !empty($translationsByLang[$defaultLang]['company_name'])) {
                    $companyName = $translationsByLang[$defaultLang]['company_name'];
                }
            }

            $disk = fetch_current_file_manager();
            // Handle profile image with consistent fallback logic
            $imageSrc = get_file_url($disk, $row['partner_image'], 'public/backend/assets/default.png', 'profile');

            $profile = '<div class="o-media o-media--middle">
                        <a href="' . $imageSrc . '" data-lightbox="image-1">
                            <img class="o-media__img images_in_card" src="' . $imageSrc . '" alt="' . $partnerName . '">
                        </a>';
            $profile .= '<a href="' . base_url('/admin/partners/general_outlook/' . $row['provider_id']) . '"><div class="o-media__body">
                <div class="provider_name_table" >' . $partnerName .'</span></div>
                <div class="provider_email_table">' . $companyName . '</div>
                <div class="provider_email_table">' . $row['partner_email'] . '(' . $row['partner_phone'] . ')</div>
                </div>
                </div></a>';

            $operations = '<button class="btn btn-success btn-sm edit_cash_collection" data-id="' . $row['id'] . '" data-toggle="modal" data-target="#update_modal"><i class="fa fa-pen" aria-hidden="true"></i> </button> ';
            $tempRow['id'] = $row['id'];
            $tempRow['provider_id'] = $row['provider_id'];
            $tempRow['message'] = $row['message'];
            $tempRow['amount'] = ($row['amount']);
            $tempRow['status'] = labels(strtolower($row['status']), $row['status']);
            $tempRow['date'] = $row['date'];
            $tempRow['profile'] = $profile; // Use translated partner name
            if ($from_app == false) {
                $tempRow['operations'] = $operations;
            }
            $rows[] = $tempRow;
        }
        if ($from_app) {
            $data['total'] = (empty($total)) ? (string) count($rows) : $total;
            $data['data'] = $rows;
            return $data;
        } else {
            $bulkData['rows'] = $rows;
            return ($bulkData);
        }
    }
}
