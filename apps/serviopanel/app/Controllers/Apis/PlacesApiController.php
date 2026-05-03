<?php

namespace App\Controllers\Apis;

use App\Controllers\BaseController;
use Config\Services;

class PlacesApiController extends BaseController
{

    protected $excluded_routes = [
        "api/v1/get_places_for_app",
        "api/v1/get_place_details_for_app",
        "api/v1/get_places_for_web",
        "api/v1/get_place_details_for_web",
        "partner/api/v1/get_places_for_app",
        "partner/api/v1/get_place_details_for_app",
    ];

    public function __construct()
    {
        helper("function");
        helper('ResponceServices');
    }


    /**
     * Unified autocomplete endpoint for both app and web.
     *
     * This method:
     * - Accepts "input" as a GET parameter.
     * - Applies the same length and basic validation currently used.
     * - Uses the configured Google Places API key.
     * - Returns the raw Google response wrapped in our standard JSON structure.
     *
     * It effectively replaces:
     * - Customer V1::get_places_for_app()
     * - Customer V1::get_places_for_web()
     * - Provider V1::get_places_for_app()
     */
    public function get_places()
    {
        try {
            // Use a consistent validation flow similar to existing methods
            $validation = Services::validation();
            $validation->setRules([
                'input' => 'required',
            ]);

            if (!$validation->withRequest($this->request)->run()) {
                return $this->response->setJSON([
                    'error'   => true,
                    'message' => $validation->getErrors(),
                    'data'    => [],
                ]);
            }

            // Use trim and length checks as in the customer app version
            $rawInput = trim((string) $this->request->getGet('input'));

            if ($rawInput === '' || mb_strlen($rawInput) > 150) {
                return $this->response->setJSON([
                    'error'   => true,
                    'message' => labels('invalid_input_provided', 'Invalid input provided'),
                ]);
            }

            // Fetch API key using the same settings structure
            $key = get_settings('api_key_settings', true);

            // Use Places API key if available
            if (isset($key['google_places_api']) && !empty($key['google_places_api'])) {
                $google_api_key = $key['google_places_api'];
            } else {
                // Reuse the more generic, reusable label where possible
                return $this->response->setJSON([
                    'error'   => true,
                    'message' => labels(PLACES_API_KEY_NOT_SET, 'Places API key is not set'),
                ]);
            }

            $baseUrl = "https://maps.googleapis.com/maps/api/place/autocomplete/json";
            $query = http_build_query([
                'key'   => $google_api_key,
                'input' => $rawInput,
            ]);
            $url = $baseUrl . "?" . $query;

            // Secure cURL request to Google Places Autocomplete API
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            $response = curl_exec($ch);
            unset($ch);

            return $this->response->setJSON([
                'error' => false,
                'data'  => json_decode($response, true) ?? [],
            ]);
        } catch (\Throwable $th) {
            // Keep logging consistent with existing pattern
            log_the_responce(
                $this->request->header('Authorization') . ' Params passed: ' . json_encode($_REQUEST) . " Issue => " . $th,
                date("Y-m-d H:i:s") . '--> app/Controllers/Apis/PlacesController.php - get_places()'
            );

            return $this->response->setJSON([
                'error'   => true,
                'message' => labels(SOMETHING_WENT_WRONG, 'Something went wrong'),
            ]);
        }
    }

    /**
     * Unified place details endpoint for both app and web.
     *
     * This method supports:
     * - Details by "placeid" (used by app endpoints).
     * - Details by "place_id" OR by "latitude" + "longitude" (used by web endpoint).
     *
     * Behaviour:
     * - If "placeid" or "place_id" is provided, it will be used as the primary identifier.
     * - If no place id is provided, but "latitude" and "longitude" are provided,
     *   it behaves like the existing web endpoint that accepts coordinates.
     *
     * This mirrors:
     * - Customer V1::get_place_details_for_app()
     * - Customer V1::get_place_details_for_web()
     * - Provider V1::get_place_details_for_app()
     */
    public function get_place_details()
    {
        try {
            // Read all potentially relevant inputs
            $rawLatitude  = trim((string) $this->request->getGet('latitude'));
            $rawLongitude = trim((string) $this->request->getGet('longitude'));
            $rawPlaceId   = trim((string) $this->request->getGet('placeid'));
            $rawPlaceIdWeb = trim((string) $this->request->getGet('place_id'));

            // Prefer explicit placeid / place_id when present
            $effectivePlaceId = $rawPlaceId !== '' ? $rawPlaceId : $rawPlaceIdWeb;

            // If place id is present, follow the "app" style validation and flow
            if ($effectivePlaceId !== '') {
                // Mirror the existing length check
                if (mb_strlen($effectivePlaceId) > 150) {
                    return $this->response->setJSON([
                        'error'   => true,
                        'message' => labels('invalid_input_provided', 'Invalid input provided'),
                    ]);
                }

                $key = get_settings('api_key_settings', true);

                if (isset($key['google_places_api']) && !empty($key['google_places_api'])) {
                    $google_api_key = $key['google_places_api'];
                } else {
                    return $this->response->setJSON([
                        'error'   => true,
                        'message' => labels(PLACES_API_KEY_NOT_SET, 'Places API key is not set'),
                    ]);
                }

                $baseUrl = "https://maps.googleapis.com/maps/api/place/details/json";
                $query = http_build_query([
                    'key'     => $google_api_key,
                    'placeid' => $effectivePlaceId,
                ]);
                $url = $baseUrl . "?" . $query;

                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

                $response = curl_exec($ch);
                unset($ch);

                return $this->response->setJSON([
                    'error' => false,
                    'data'  => json_decode($response, true) ?? [],
                ]);
            }

            // If no place id, fall back to the "web" style latitude/longitude behaviour
            // This mirrors Customer\V1::get_place_details_for_web() which uses the Geocoding API.
            $rawLatitude  = trim((string) $this->request->getGet('latitude'));
            $rawLongitude = trim((string) $this->request->getGet('longitude'));
            $rawPlaceIdWeb = trim((string) $this->request->getGet('place_id'));

            // Validate latitude
            if ($rawLatitude !== '' && !preg_match('/^-?\d{1,3}(\.\d+)?$/', $rawLatitude)) {
                return $this->response->setJSON([
                    'error'   => true,
                    'message' => labels('please_enter_valid_latitude', 'Please enter valid latitude'),
                ]);
            }

            // Validate longitude
            if ($rawLongitude !== '' && !preg_match('/^-?\d{1,3}(\.\d+)?$/', $rawLongitude)) {
                return $this->response->setJSON([
                    'error'   => true,
                    'message' => labels('please_enter_valid_longitude', 'Please enter valid longitude'),
                ]);
            }

            if ($rawPlaceIdWeb !== '' && mb_strlen($rawPlaceIdWeb) > 200) {
                return $this->response->setJSON([
                    'error'   => true,
                    'message' => labels('invalid_input_provided', 'Invalid input provided'),
                ]);
            }

            // Ensure at least one of lat/lng or place_id is provided
            if ($rawPlaceIdWeb === '' && ($rawLatitude === '' || $rawLongitude === '')) {
                return $this->response->setJSON([
                    'error'   => true,
                    'message' => labels('invalid_input_provided', 'Invalid input provided'),
                ]);
            }

            $key = get_settings('api_key_settings', true);

            if (empty($key['google_places_api'])) {
                return $this->response->setJSON([
                    'error'   => true,
                    'message' => labels(PLACES_API_KEY_NOT_SET, 'Places API key is not set'),
                ]);
            }

            $google_api_key = $key['google_places_api'];

            // Use the same Geocoding endpoint and parameters as the original web implementation
            $baseUrl = "https://maps.googleapis.com/maps/api/geocode/json";

            $queryParams = [
                'key' => $google_api_key,
            ];

            if ($rawLatitude !== '' && $rawLongitude !== '') {
                $queryParams['latlng'] = "{$rawLatitude},{$rawLongitude}";
            }

            if ($rawPlaceIdWeb !== '') {
                $queryParams['place_id'] = $rawPlaceIdWeb;
            }

            $finalUrl = $baseUrl . '?' . http_build_query($queryParams);

            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL            => $finalUrl,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_TIMEOUT        => 15,
            ]);

            $response = curl_exec($ch);
            unset($ch);

            return $this->response->setJSON([
                'error' => false,
                'data'  => json_decode($response, true) ?? [],
            ]);
        } catch (\Throwable $th) {
            log_the_responce(
                $this->request->header('Authorization') . ' Params passed: ' . json_encode($_REQUEST) . " Issue => " . $th,
                date("Y-m-d H:i:s") . '--> app/Controllers/Apis/PlacesController.php - get_place_details()'
            );

            return $this->response->setJSON([
                'error'   => true,
                'message' => labels(SOMETHING_WENT_WRONG, 'Something went wrong'),
            ]);
        }
    }
}

