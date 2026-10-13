<?php
require 'fns/push_notification/firebase_php/autoload.php';

use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;
use Kreait\Firebase\Messaging\WebPushConfig;

if (
    !empty(Registry::load('settings')->firebase_private_key) &&
    !empty($data['device_tokens']) &&
    is_array($data['device_tokens'])
) {

    $firebase_pkey = 'fns/push_notification/firebase_key/' . Registry::load('settings')->firebase_private_key;

    if (file_exists($firebase_pkey)) {
        $factory = (new Factory)->withServiceAccount($firebase_pkey);
        $messaging = $factory->createMessaging();

        $title = $data['title'] ?? 'New Message';
        $body  = $data['message'] ?? 'You have a new notification';
        $link  = Registry::load('config')->site_url;

        $webPushConfig = WebPushConfig::fromArray([
            'headers' => [
                'Urgency' => 'high',
                'TTL' => '60'
            ],
            'notification' => [
                'title' => $title,
                'body' => $body,
                'icon' => Registry::load('config')->site_url . 'assets/files/defaults/favicon.png',
                'requireInteraction' => true
            ],
            'fcm_options' => [
                'link' => $link 
            ]
        ]);

        $message = CloudMessage::new()
            ->withNotification(Notification::create($title, $body))
            ->withWebPushConfig($webPushConfig)
            ->withData(['target_link' => $link]);

        $tokenBatches = array_chunk(array_values(array_unique($data['device_tokens'])), 500);
        $totalSent = 0;
        $totalFailed = 0;

        foreach ($tokenBatches as $tokens) {
            try {
                $report = $messaging->sendMulticast($message, $tokens);
                $totalSent += $report->successes()->count();
                $totalFailed += $report->failures()->count();

                if ($report->hasFailures()) {
                    foreach ($report->failures()->items() as $failure) {
                        if ($failure->error() instanceof \Kreait\Firebase\Exception\Messaging\NotFound || 
                            $failure->error() instanceof \Kreait\Firebase\Exception\Messaging\InvalidMessage) {
                            
                            $staleToken = $failure->target()->value();
                        }
                    }
                }
            } catch (\Throwable $e) {
                error_log("FCM Batch Error: " . $e->getMessage());
            }
        }

        $result = [
            'status' => 'success',
            'sent'   => $totalSent,
            'failed' => $totalFailed,
        ];
    }
}