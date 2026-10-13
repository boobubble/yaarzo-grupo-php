<?php
use Medoo\Medoo;


$last_total = (int)($data["total_online_users"] ?? 0);
$last_recent_id = (int)($data["recent_online_user_id"] ?? 0);
$last_recent_status = (int)($data["recent_online_user_online_status"] ?? 0);

if (Registry::load('settings')->realtime_mode !== 'websocket') {
    update_online_statuses();
}


$where = [
    "site_users.online_status[!]" => 0,
    "ORDER" => ["site_users.last_login_session" => "DESC"]
];

$total_online_users = DB::connect()->count('site_users', $where);

$where["LIMIT"] = 1;
$recent_user = DB::connect()->get('site_users', ['user_id', 'online_status'], $where);

$current_recent_id = (int)($recent_user['user_id'] ?? 0);
$current_recent_status = (int)($recent_user['online_status'] ?? 0);

$has_changed = false;

if ($total_online_users !== $last_total) {
    $has_changed = true;
} elseif ($current_recent_id !== $last_recent_id) {
    $has_changed = true;
} elseif ($current_recent_status !== $last_recent_status) {
    $has_changed = true;
}

if ($has_changed) {
    $result['total_online_users'] = $total_online_users;
    $result['recent_online_user_id'] = $current_recent_id;
    $result['recent_online_user_online_status'] = $current_recent_status;
    $escape = true;
}