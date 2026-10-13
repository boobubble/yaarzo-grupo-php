<?php
if (Registry::load('current_user')->logged_in) {
    if (Registry::load('settings')->video_chat !== 'disable' && Registry::load('settings')->broadcast_camera !== 'disable') {
if (role(['permissions' => ['groups' => 'broadcast_camera']])) {
    $current_user_id = Registry::load('current_user')->id;

    $group_id = isset($data['group_id'])
    ? filter_var($data['group_id'], FILTER_SANITIZE_NUMBER_INT)
    : null;

    if (empty($group_id)) {
        return;
    }

    $group_member_id = DB::connect()->select('group_members', ['group_member_id'], ['group_id' => $group_id, 'user_id' => $current_user_id]);

    if (isset($group_member_id[0])) {
        $group_member_id = $group_member_id[0]['group_member_id'];
    } else {
        return;
    }

    $columns = $join = $where = null;
    $columns = [
        'live_cam_visitors.user_id', 'site_users.display_name', 'site_users.username'
    ];
    $join["[>]site_users"] = ["live_cam_visitors.user_id" => "user_id"];
    $where = [
        'live_cam_visitors.group_member_id' => $group_member_id,
        'live_cam_visitors.current_status' => 0,
        'LIMIT' => 1
    ];

    $requests = DB::connect()->select('live_cam_visitors', $join, $columns, $where);


    if (isset($requests[0])) {

        $user = $requests[0];
        $output = array();
        $output['has_request'] = true;
        $output['alert_message'] = $user['display_name'].' (@'.$user['username'].') '.Registry::load('strings')->cam_request_permision;
        $output['user_id'] = $user['user_id'];
    }
}
}
}
?>