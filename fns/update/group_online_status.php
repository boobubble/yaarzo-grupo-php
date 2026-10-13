<?php

$user_id = Registry::load('current_user')->id;

if (Registry::load('current_user')->logged_in && isset($data['group_id'])) {
    $group_id = filter_var($data["group_id"], FILTER_SANITIZE_NUMBER_INT);

    if (!empty($group_id)) {
        DB::connect()->update("group_members", ["currently_browsing" => 1], ['group_id' => $group_id, 'user_id' => $user_id]);
        ws_push(['update' => 'new_online_group_member', 'group_id' => $group_id]);
    }
}