<?php
use Medoo\Medoo;

$unread_input = filter_var($data["unread_group_messages"] ?? 0, FILTER_SANITIZE_NUMBER_INT);
$current_user_id = Registry::load('current_user')->id;
$current_site_role = Registry::load('current_user')->site_role;
$banned_role_ids = Registry::load('group_role_attributes')->banned_users;

$super_privileges = role(['permissions' => ['groups' => 'super_privileges']]);

$columns = [
    'groups.group_id',
    'unread_messages' => Medoo::raw('COUNT(DISTINCT <group_messages.group_message_id>)')
];

$join = [
    '[>]group_members' => [
        'groups.group_id' => 'group_id',
        'AND' => ['group_members.user_id' => $current_user_id]
    ],
    '[>]group_messages' => [
        'groups.group_id' => 'group_id',
        'AND' => [
            'group_messages.group_message_id[>]' => Medoo::raw('<group_members.last_read_message_id>'),
            'group_messages.user_id[!]' => $current_user_id
        ]
    ],
    '[>]site_users_blacklist(blacklist)' => [
        'group_messages.user_id' => 'blacklisted_user_id',
        'AND' => ['blacklist.user_id' => $current_user_id]
    ],
    '[>]site_users_blacklist(blocked)' => [
        'group_messages.user_id' => 'user_id',
        'AND' => ['blocked.blacklisted_user_id' => $current_user_id]
    ]
];

if (!$super_privileges) {
    $join['[>]site_users_blacklist(blocked)'] = [
        'group_messages.user_id' => 'user_id',
        'AND' => ['blocked.blacklisted_user_id' => $current_user_id]
    ];
}

$where = [
    'group_members.user_id' => $current_user_id,
    'group_members.last_read_message_id[!]' => null,
    "group_members.group_role_id[!]" => null,
    "group_members.group_role_id[!] #banned_check" => $banned_role_ids,

    "AND #my_blacklist" => [
        "OR #block_status" => [
            "blacklist.block" => 0,
            "blacklist.block #is_null" => null 
        ],
        "OR #ignore_status" => [
            "blacklist.ignore" => 0,
            "blacklist.ignore #is_null" => null
        ]
    ]
];

if (!$super_privileges) {
    $join["[>]group_categories"] = ["groups.group_category_id" => "group_category_id"];

    $join["[>]group_categories_roles"] = [
        "group_categories.group_category_id" => "group_category_id",
        "AND" => ["group_categories_roles.site_role_id" => $current_site_role]
    ];

    $where["AND #cat_access"] = [
        "OR #cat_options" => [
            "group_categories.access_restricted" => 0,
            "group_categories_roles.site_role_id[!]" => null
        ]
    ];

    $where["AND #whisper_logic"] = [
        "OR #whisper_options" => [
            "group_messages.whisper" => null,
            "group_messages.whisper #me" => $current_user_id,
            "group_messages.user_id #is_me" => $current_user_id
        ]
    ];

    $where['AND #their_blacklist'] = [
        "OR #they_blocked_me" => [
            'blocked.block' => 0,
            'blocked.block #is_null' => null
        ]
    ];
}

$where['GROUP'] = 'groups.group_id';

$groups = DB::connect()->select('groups', $join, $columns, $where);

$unread_group_messages = 0;
if ($groups) {
    foreach ($groups as $group) {
        $unread_group_messages += (int)($group['unread_messages'] ?? 0);
    }
}

if ((int)$unread_group_messages !== (int)$unread_input) {
    $result['unread_group_messages'] = $unread_group_messages;

    $settings = Registry::load('settings');
    if (isset($settings->play_notification_sound->on_group_unread_count_change)) {
        if ($unread_group_messages > $unread_input) {
            $result['play_sound_notification'] = true;
        }
    }
    $escape = true;
}