<?php

namespace App\Jobs;

use CodeIgniter\Queue\BaseJob;
use CodeIgniter\Queue\Interfaces\JobInterface;
use Exception;

/**
 * NotificationJob
 * 
 * This job handles the processing of general notifications in the background.
 * It supports multiple notification types:
 * - Push notifications (FCM) for mobile apps
 * - Web notifications for web interface
 * - Database notifications storage
 * 
 * The job receives notification data and processes it using the existing
 * notification logic, but in a queued manner for better performance.
 * This replaces the direct send_notification() function calls.
 */
class NotificationJob extends BaseJob implements JobInterface
{
    /**
     * Process the notification job
     * 
     * This method handles the actual sending of notifications
     * based on the notification type specified in the job data.
     */
    public function process()
    {
        log_message('info', 'Notification job started for type: ' . $this->data['notification_type']);

        try {
            // Validate required data
            if (empty($this->data['notification_type'])) {
                throw new Exception('Notification type is required');
            }

            // Process notification based on type
            switch ($this->data['notification_type']) {
                case 'general_notification':
                    $this->processGeneralNotification();
                    break;

                case 'web_notification':
                    $this->processWebNotification();
                    break;

                default:
                    throw new Exception('Unknown notification type: ' . $this->data['notification_type']);
            }

            log_message('info', 'Notification job completed successfully for type: ' . $this->data['notification_type']);
            return true;
        } catch (\Throwable $th) {
            log_message('error', 'Error processing notification job: ' . $th->getMessage());
            throw $th;
        }
    }

    /**
     * Process general notification (mobile push notifications)
     * 
     * Handles FCM push notifications for mobile apps
     * This replaces the direct send_notification() function call
     */
    private function processGeneralNotification()
    {
        // Extract data from job payload
        $fcmMsg = $this->data['fcmMsg'];
        $registrationIDs_chunks = $this->data['registrationIDs_chunks'];

        // Validate required data
        if (empty($fcmMsg) || empty($registrationIDs_chunks)) {
            throw new Exception('FCM message and registration IDs are required for general notification');
        }

        // Get Firebase access token and settings
        $access_token = getAccessToken();
        $settings = fetch_details('settings', ['variable' => 'firebase_settings'])[0];
        $settings = $settings['value'];
        $settings = json_decode($settings, true);

        if (empty($settings) || empty($settings['projectId'])) {
            throw new Exception('Firebase settings not configured properly');
        }

        $url = 'https://fcm.googleapis.com/v1/projects/' . $settings['projectId'] . '/messages:send';

        // Process each chunk of registration IDs
        foreach ($registrationIDs_chunks[0] as $registrationIDs) {
            $message1 = [
                "message" => [
                    "token" => $registrationIDs['fcm_id'],
                    "data" => $fcmMsg,
                    // Android notification
                    "android" => [
                        "notification" => [
                            "title" => $fcmMsg["title"],
                            "body" => $fcmMsg["body"],
                            "sound" => $fcmMsg["type"] == "order" || $fcmMsg["type"] == "new_order" ? "order_sound" : "default",
                            "image" => isset($fcmMsg["image"]) ? $fcmMsg["image"] : ""
                        ]
                    ],
                    // iOS notification
                    "apns" => [
                        "payload" => [
                            "aps" => [
                                "alert" => [
                                    "title" => $fcmMsg["title"],
                                    "body" => $fcmMsg["body"]
                                ],
                                "sound" => $fcmMsg["type"] == "order" || $fcmMsg["type"] == "new_order" ? "order_sound.aiff" : "default",
                                "mutable-content" => 1
                            ]
                        ],
                        "fcm_options" => [
                            "image" => isset($fcmMsg["image"]) ? $fcmMsg["image"] : ""
                        ]
                    ],
                    "notification" => [
                        "title" => $fcmMsg["title"],
                        "body" => $fcmMsg["body"],
                        "image" => isset($fcmMsg["image"]) ? $fcmMsg["image"] : ""
                    ]
                ]
            ];

            $data1 = json_encode($message1);
            sendNotificationToFCM($url, $access_token, $data1);
        }

        log_message('info', 'General notification sent successfully to ' . count($registrationIDs_chunks[0]) . ' devices');
    }

    /**
     * Process web notification
     * 
     * Handles web notifications for web interface
     * This replaces the direct send_customer_web_notification() function call
     */
    private function processWebNotification()
    {
        // Extract data from job payload
        $fcmMsg = $this->data['fcmMsg'];
        $web_registrationIDs = $this->data['web_registrationIDs'];

        // Validate required data
        if (empty($fcmMsg) || empty($web_registrationIDs)) {
            throw new Exception('FCM message and web registration IDs are required for web notification');
        }

        // Get Firebase access token and settings
        $access_token = getAccessToken();
        $settings = fetch_details('settings', ['variable' => 'firebase_settings'])[0];
        $settings = $settings['value'];
        $settings = json_decode($settings, true);

        if (empty($settings) || empty($settings['projectId'])) {
            throw new Exception('Firebase settings not configured properly');
        }

        $url = 'https://fcm.googleapis.com/v1/projects/' . $settings['projectId'] . '/messages:send';

        // Process each web registration ID
        foreach ($web_registrationIDs as $registrationIDs) {
            $message1 = [
                "message" => [
                    "token" => $registrationIDs['web_fcm_id'],
                    "data" => $fcmMsg
                ]
            ];

            $data1 = json_encode($message1);
            sendNotificationToFCM($url, $access_token, $data1);
        }

        log_message('info', 'Web notification sent successfully to ' . count($web_registrationIDs) . ' web devices');
    }
}
