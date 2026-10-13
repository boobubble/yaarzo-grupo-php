<?php
use Medoo\Medoo;

$previous_count = isset($data["unread_private_chat_messages"]) ? (int)filter_var($data["unread_private_chat_messages"], FILTER_SANITIZE_NUMBER_INT) : 0;
$current_user_id = Registry::load('current_user')->id;

$columns = [
    "private_conversations.private_conversation_id",
    'unread_messages' => medoo::raw("COUNT(<private_chat_messages.private_chat_message_id>)")
];

$join = [
    '[>]private_chat_messages' => [
        'private_conversations.private_conversation_id' => 'private_conversation_id',
        'AND' => [
            'private_chat_messages.user_id[!]' => $current_user_id,
            'private_chat_messages.read_status' => 0
        ]
    ],
    "[>]site_users_blacklist(ignore_recipient)" => [
        "private_conversations.recipient_user_id" => "blacklisted_user_id",
        "AND" => ["ignore_recipient.user_id" => $current_user_id]
    ],
    "[>]site_users_blacklist(ignore_initiator)" => [
        "private_conversations.initiator_user_id" => "blacklisted_user_id",
        "AND" => ["ignore_initiator.user_id" => $current_user_id]
    ]
];

$where = [
    "OR #participant_check" => [
        "private_conversations.initiator_user_id" => $current_user_id,
        "private_conversations.recipient_user_id" => $current_user_id,
    ],

    "AND #blacklist_exclusion" => [
        "OR #recipient_filters" => [
            "ignore_recipient.ignore" => 0,
            "ignore_recipient.ignore #null1" => null,
            "ignore_recipient.block" => 0,
            "ignore_recipient.block #null2" => null,
            "ignore_recipient.user_id" => null
        ],
        "OR #initiator_filters" => [
            "ignore_initiator.ignore" => 0,
            "ignore_initiator.ignore #null1" => null,
            "ignore_initiator.block" => 0,
            "ignore_initiator.block #null2" => null,
            "ignore_initiator.user_id" => null
        ]
    ],

    "GROUP" => "private_conversations.private_conversation_id"
];

$conversations = DB::connect()->select('private_conversations', $join, $columns, $where);

$unread_private_chat_messages = 0;
if ($conversations) {
    foreach ($conversations as $conversation) {
        $unread_private_chat_messages += (int)($conversation['unread_messages'] ?? 0);
    }
}

$is_websocket = (isset(Registry::load('settings')->realtime_mode) && Registry::load('settings')->realtime_mode === 'websocket');

if ($is_websocket || (int)$unread_private_chat_messages !== $previous_count) {
    
    $result['unread_private_chat_messages'] = (int)$unread_private_chat_messages;

    $settings = Registry::load('settings');
    if (isset($settings->play_notification_sound->on_private_conversation_unread_count_change)) {
        if ($unread_private_chat_messages > $previous_count) {
            $result['play_sound_notification'] = true;
        }
    }
    
    $escape = true;
}