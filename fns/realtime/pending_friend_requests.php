<?php

$previous_count = isset($data["pending_friend_requests"]) ? (int)filter_var($data["pending_friend_requests"], FILTER_SANITIZE_NUMBER_INT) : 0;

$columns = $join = $where = null;

$where = [
    "friends.to_user_id" => (int)$current_user_id,
    "friends.relation_status" => 0
];

$pending_friend_requests = (int)DB::connect()->count('friends', $where);

$is_websocket = (isset(Registry::load('settings')->realtime_mode) && Registry::load('settings')->realtime_mode === 'websocket');

if ($is_websocket || $pending_friend_requests !== $previous_count) {
    
    $result['pending_friend_requests'] = $pending_friend_requests;

    if (isset(Registry::load('settings')->play_notification_sound->on_new_friend_request)) {
        if ($pending_friend_requests > $previous_count) {
            $result['play_sound_notification'] = true;
        }
    }

    $escape = true;
}