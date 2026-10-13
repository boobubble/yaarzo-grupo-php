<?php
use Medoo\Medoo;


$last_gm_count = filter_var($data["last_realtime_gm"] ?? 0, FILTER_SANITIZE_NUMBER_INT);
$last_lc_count = filter_var($data["last_realtime_lc"] ?? 0, FILTER_SANITIZE_NUMBER_INT);


$last_gm_uid = filter_var($data["last_gm_uid"] ?? 0, FILTER_SANITIZE_NUMBER_INT);
$last_lc_uid = filter_var($data["last_lc_uid"] ?? 0, FILTER_SANITIZE_NUMBER_INT);

$group_id = filter_var($data["online_group_members"] ?? 0, FILTER_SANITIZE_NUMBER_INT);

$columns = $where = $join = null;

$columns = [
    "online_users" => Medoo::raw("COUNT(<group_members.group_member_id>)"),
    "last_member_id" => Medoo::raw("MAX(<group_members.group_member_id>)")
];

$settings = Registry::load('settings');
$track_cam = ($settings->video_chat !== 'disable' && $settings->broadcast_camera !== 'disable');

if ($track_cam) {
    $columns["live_camera_users"] = Medoo::raw("SUM(<group_members.live_camera> != 0)");
    $columns["last_camera_member_id"] = Medoo::raw("MAX(CASE WHEN <group_members.live_camera> != 0 THEN <group_members.group_member_id> ELSE 0 END)");
}

$join = [
    "[>]site_users" => ["group_members.user_id" => "user_id"]
];

$where = [
    "group_members.group_id" => $group_id,
    "group_members.currently_browsing" => 1,
    "site_users.online_status" => 1
];

$db_result = DB::connect()->get("group_members", $join, $columns, $where);

$online_users = (int)($db_result["online_users"] ?? 0);
$live_camera_users = (int)($db_result["live_camera_users"] ?? 0);
$last_member_id = (int)($db_result["last_member_id"] ?? 0);
$last_camera_member_id = (int)($db_result["last_camera_member_id"] ?? 0);

$general_changed = ($online_users !== (int)$last_gm_count || $last_member_id !== (int)$last_gm_uid);
$camera_changed = ($track_cam && ($live_camera_users !== (int)$last_lc_count || $last_camera_member_id !== (int)$last_lc_uid));
$is_websocket = (isset(Registry::load('settings')->realtime_mode) && Registry::load('settings')->realtime_mode === 'websocket');

if ($is_websocket || $general_changed || $camera_changed) {
    $result['ogm_group_id'] = $group_id;
    $result['last_realtime_gm'] = $online_users;
    $result['last_gm_uid'] = $last_member_id;
    $result['online_group_members'] = true;

    if ($track_cam) {
        $result['last_realtime_lc'] = $live_camera_users;
        $result['last_lc_uid'] = $last_camera_member_id;
        $result['online_live_cam'] = true;
    }

    $escape = true;
}