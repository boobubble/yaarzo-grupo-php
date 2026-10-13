<?php

$previous_count = isset($data["unread_site_notifications"]) ? (int)filter_var($data["unread_site_notifications"], FILTER_SANITIZE_NUMBER_INT) : 0;

$columns = $join = $where = null;

$where = [
    "site_notifications.user_id" => (int)$current_user_id,
    "site_notifications.read_status" => 0
];

$unread_site_notifications = (int)DB::connect()->count('site_notifications', $where);

$is_websocket = (isset(Registry::load('settings')->realtime_mode) && Registry::load('settings')->realtime_mode === 'websocket');

if ($is_websocket || $unread_site_notifications !== $previous_count) {
    
    $result['unread_site_notifications'] = $unread_site_notifications;

    if (isset(Registry::load('settings')->play_notification_sound->on_new_site_notification)) {
        if ($unread_site_notifications > $previous_count) {
            $result['play_sound_notification'] = true;
        }
    }

    $escape = true;
}