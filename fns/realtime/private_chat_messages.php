<?php

$load_conversation_id = 0;
$user_id_raw = $data["user_id"] ?? '';

if (strpos($user_id_raw, 'all[') === 0) {
    $parts = explode('[', $user_id_raw);
    $load_conversation_id = filter_var($parts[1] ?? 0, FILTER_SANITIZE_NUMBER_INT);
}

if ($user_id_raw !== 'all' && empty($load_conversation_id)) {
    $data["user_id"] = filter_var($user_id_raw, FILTER_SANITIZE_NUMBER_INT);
}

if (!empty($data["user_id"])) {

    if (Registry::load('settings')->realtime_mode !== 'websocket') {
        include_once('fns/load/load.php');
    }

    $load_params = [
        "load" => 'private_chat_messages',
        "user_id" => $data["user_id"],
        "return" => true,
        "message_id_greater_than" => filter_var($data["message_id_greater_than"] ?? 0, FILTER_SANITIZE_NUMBER_INT)
    ];

    $messages_data = load($load_params);

    if (!empty($messages_data['messages'])) {
        $result['private_chat_messages'] = $messages_data;

        if ($data["user_id"] !== 'all' && isset(Registry::load('settings')->play_notification_sound->on_new_private_message)) {
            $latest_msg = $messages_data['messages'][0];

            if (!isset($latest_msg->own_message) || $latest_msg->own_message === false) {
                $result['play_sound_notification'] = true;
            }
        }

        $escape = true;
    }
}