<?php

namespace App\Models;

use CodeIgniter\Model;

class Cash_collection_model extends Model
{
    protected $DBGroup = 'default';
    protected $table = 'cities';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType     = 'array';
    protected $useSoftDeletes = true;
    protected $allowedFields = [
        'user_id',
        'message',
        'status',
        'total_amount',
        'commison',
        'status',
        'date'
    ];
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $deletedField  = 'deleted_at';
    public $base, $admin_id, $db;
    public function list($from_app = false, $search = '', $limit = 10, $offset = 0, $sort = 'id', $order = 'ASC', $where = [], $user_details = [])
    {
        $db      = \Config\Database::connect();
        $builder = $db->table('cash_collection c');
        $sortable_fields = ['id' => 'id'];
        $condition  = [];

        // Get current language for translation support
        // This allows searching in translated company names and usernames
        $currentLang = get_current_language();
        $defaultLang = get_default_language();

        // Trim search term to remove leading/trailing whitespace that might cause issues
        // This fixes the issue where pasting full names with spaces doesn't work
        if (isset($search) and $search != '') {
            $search = trim($search);
        }

        // Build search conditions including message, company names, and usernames
        // This ensures users can search by the provider name they see in the table
        // Escape search term for safe LIKE queries to prevent SQL injection
        $escapedSearch = '';
        if (isset($search) and $search != '') {
            $escapedSearch = $db->escapeLikeString($search);
        }

        // Handle pagination and sorting parameters
        if (isset($_GET['offset']))
            $offset = $_GET['offset'];
        if (isset($_GET['limit'])) {
            $limit = $_GET['limit'];
        }
        if (isset($_GET['sort'])) {
            if ($_GET['sort'] == 'id') {
                $sort = "c.id";
            } else {
                $sort = $_GET['sort'];
            }
        }
        if (isset($_GET['order'])) {
            $order = $_GET['order'];
        }
        if (isset($_POST['order'])) {
            $order = $_POST['order'];
        }
        if (isset($_GET['id']) && $_GET['id'] != '') {
            $builder->where($condition);
        }

        // Count query - build separately to ensure accurate count
        // Add translated fields join for count query when searching
        // This allows searching by translated partner names displayed in the table
        $countBuilder = $db->table('cash_collection c');
        $countBuilder->select('COUNT(c.id) as `total`')
            ->join('partner_details pd', 'c.partner_id = pd.partner_id', 'left')
            ->join('users u', 'c.partner_id = u.id', 'left');

        // Apply search conditions - search across all languages, not just current language
        // This allows users to search for provider names in any language translation
        if (isset($search) and $search != '') {
            $countBuilder->groupStart();

            // Search in base fields using LIKE with proper escaping
            $countBuilder->like('c.id', $escapedSearch);
            $countBuilder->orLike('c.message', $escapedSearch);
            $countBuilder->orLike('pd.company_name', $escapedSearch);
            $countBuilder->orLike('u.username', $escapedSearch);

            // Search in translated fields across ALL languages using EXISTS subquery
            // This allows searching for provider names in any language translation
            $translationSearchCondition = "EXISTS (
                SELECT 1 FROM translated_partner_details tpd_search 
                WHERE tpd_search.partner_id = c.partner_id 
                AND (
                    tpd_search.company_name LIKE '%{$escapedSearch}%' 
                    OR tpd_search.username LIKE '%{$escapedSearch}%'
                )
            )";
            $countBuilder->orWhere($translationSearchCondition, null, false);

            $countBuilder->groupEnd();
        }

        // Apply additional where conditions to count query
        if (isset($where) && !empty($where)) {
            $countBuilder->where($where);
        }

        // Apply cash collection filter to count query
        if (isset($_GET['cash_collection_filter']) && $_GET['cash_collection_filter'] != '') {
            $countBuilder->where('c.status', $_GET['cash_collection_filter']);
        }

        $total_count = $countBuilder->get()->getResultArray();
        $total = $total_count[0]['total'];

        // Data query - build with necessary joins
        // Add users table join to enable username search
        $builder->select('c.*,pd.company_name, u.username, u.email as partner_email, u.phone as partner_phone, u.image as partner_image, u.username as partner_name')
            ->join('partner_details pd', 'c.partner_id = pd.partner_id', 'left')
            ->join('users u', 'c.partner_id = u.id', 'left');

        // Apply search conditions - search across all languages, not just current language
        // This allows users to search for provider names in any language translation
        if (isset($search) and $search != '') {
            $builder->groupStart();

            // Search in base fields using LIKE with proper escaping
            $builder->like('c.id', $escapedSearch);
            $builder->orLike('c.message', $escapedSearch);
            $builder->orLike('pd.company_name', $escapedSearch);
            $builder->orLike('u.username', $escapedSearch);

            // Search in translated fields across ALL languages using EXISTS subquery
            // This allows searching for provider names in any language translation
            $translationSearchCondition = "EXISTS (
                SELECT 1 FROM translated_partner_details tpd_search 
                WHERE tpd_search.partner_id = c.partner_id 
                AND (
                    tpd_search.company_name LIKE '%{$escapedSearch}%' 
                    OR tpd_search.username LIKE '%{$escapedSearch}%'
                )
            )";
            $builder->orWhere($translationSearchCondition, null, false);

            $builder->groupEnd();
        }

        // Apply additional where conditions to data query
        if (isset($where) && !empty($where)) {
            $builder->where($where);
        }

        // Apply cash collection filter to data query
        if (isset($_GET['cash_collection_filter']) && $_GET['cash_collection_filter'] != '') {
            $builder->where('c.status', $_GET['cash_collection_filter']);
        }

        $cash_collection_record = $builder->orderBy($sort, $order)->limit($limit, $offset)->get()->getResultArray();

        // Batch fetch all translations for all partners in a single query
        // This improves performance by avoiding N+1 queries
        $allTranslations = [];
        if (!empty($cash_collection_record)) {
            $partnerIds = array_column($cash_collection_record, 'partner_id');
            $translatedPartnerDetailsModel = new \App\Models\TranslatedPartnerDetails_model();
            // Get all translations for all partners (not just current language)
            $allTranslations = $translatedPartnerDetailsModel->getAllTranslationsForPartners($partnerIds);
        }

        $bulkData = array();
        $bulkData['total'] = $total;
        $rows = array();
        $tempRow = array();
        foreach ($cash_collection_record as $row) {
            $partner_details = fetch_details('partner_details', ['partner_id' => $row['partner_id']]);

            // Get translations for this specific partner
            $partnerTranslations = $allTranslations[$row['partner_id']] ?? [];

            // Re-index translations by language_code for easier access
            $translationsByLang = [];
            if (!empty($partnerTranslations)) {
                foreach ($partnerTranslations as $langCode => $translation) {
                    $translationsByLang[$langCode] = $translation;
                }
            }

            // Get translated company name with fallback logic
            // Priority: current language → default language → base table
            $companyName = $partner_details[0]['company_name'] ?? ''; // fallback to base table
            $partner_name = $row['partner_name'] ?? ''; // fallback to base table
            if (!empty($translationsByLang)) {
                // Try current language first
                if (isset($translationsByLang[$currentLang]) && !empty($translationsByLang[$currentLang]['company_name'])) {
                    $companyName = $translationsByLang[$currentLang]['company_name'];
                }
                // Fallback to default language
                elseif (isset($translationsByLang[$defaultLang]) && !empty($translationsByLang[$defaultLang]['company_name'])) {
                    $companyName = $translationsByLang[$defaultLang]['company_name'];
                }

                if (isset($translationsByLang[$currentLang]) && !empty($translationsByLang[$currentLang]['username'])) {
                    $partner_name = $translationsByLang[$currentLang]['username'];
                } elseif (isset($translationsByLang[$defaultLang]) && !empty($translationsByLang[$defaultLang]['username'])) {
                    $partner_name = $translationsByLang[$defaultLang]['username'];
                }
            }
            $disk = fetch_current_file_manager();
            // Handle profile image with consistent fallback logic
            $imageSrc = get_file_url($disk, $row['partner_image'], 'public/backend/assets/default.png', 'profile');

            $profile = '<div class="o-media o-media--middle">
                        <a href="' . $imageSrc . '" data-lightbox="image-1">
                            <img class="o-media__img images_in_card" src="' . $imageSrc . '" alt="' . $partner_name . '">
                        </a>';
            $profile .= '<a href="' . base_url('/admin/partners/general_outlook/' . $row['partner_id']) . '"><div class="o-media__body">
                <div class="provider_name_table" >' . $partner_name .'</span></div>
                <div class="provider_email_table">' . $companyName . '</div>
                <div class="provider_email_table">' . $row['partner_email'] . '(' . $row['partner_phone'] . ')</div>
                </div>
                </div></a>';

            $operations = '<button class="btn btn-success btn-sm edit_cash_collection" data-id="' . $row['id'] . '" data-toggle="modal" data-target="#update_modal"><i class="fa fa-pen" aria-hidden="true"></i> </button>';
            $tempRow = [
                'id' => $row['id'],
                'user_id' => $row['user_id'],
                'message' => $row['message'],
                'commison' => $row['commison'],
                'status' => labels($row['status'], $row['status']),
                'partner_name' => $profile, // Use translated company name with fallback
                'admin_commision_percentage' => $partner_details[0]['admin_commission'] . '%',
                'date' => $row['date'],
                'order_id' => $row['order_id']
            ];
            if (!$from_app) {
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
