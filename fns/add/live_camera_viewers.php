<?php

$result = array();
$result['status'] = 'rejected';
$result['alert_message'] = Registry::load('strings')->went_wrong;

if (Registry::load('current_user')->logged_in) {
    if (Registry::load('settings')->video_chat !== 'disable' && Registry::load('settings')->broadcast_camera !== 'disable') {

        $group_id = isset($data['group_id'])
        ? filter_var($data['group_id'], FILTER_SANITIZE_NUMBER_INT)
        : null;

        $user_id = isset($data['user_id'])
        ? filter_var($data['user_id'], FILTER_SANITIZE_NUMBER_INT)
        : null;


        if (empty($group_id) || empty($user_id)) {
            return;
        }

        $currently_viewing = false;

        $columns = $join = $where = null;
        $columns = [
            'group_members.group_member_id', 'group_members.live_camera',
            'live_cam_visitors.current_status', 'live_cam_visitors.lc_viewer_id',
            'site_users_blacklist.block'
        ];
        $join["[>]site_users_blacklist"] = ["group_members.user_id" => "user_id", "AND" => ["site_users_blacklist.blacklisted_user_id" => Registry::load('current_user')->id]];
        $join["[>]live_cam_visitors"] = ["group_members.group_member_id" => "group_member_id", "AND" => ["live_cam_visitors.user_id" => Registry::load('current_user')->id]];
        $where = [
            'group_members.group_id' => $group_id,
            'group_members.user_id' => $user_id,
            'LIMIT' => 1
        ];

        $info = DB::connect()->select('group_members', $join, $columns, $where);

        if (isset($info[0])) {
            $info = $info[0];
            if ((int)$info['block'] === 1) {
                $result = array();
                $result['status'] = 'rejected';
                $result['alert_message'] = Registry::load('strings')->camera_view_rejected;
                return;
            }

        } else {
            return;
        }

        if ((int)$info['live_camera'] === 0) {
            $result = array();
            $result['group_id'] = $group_id;
            $result['status'] = 'not_found';
            return;
        }


        if (isset($info['lc_viewer_id']) && !empty($info['lc_viewer_id'])) {

            if ((int)$info['current_status'] === 2) {
                $result = array();
                $result['status'] = 'rejected';
                $result['alert_message'] = Registry::load('strings')->camera_view_rejected;
            } else if ((int)$info['current_status'] === 1) {
                $result = array();
                $result['status'] = 'approved';

                $where = [
                    'group_member_id' => $info['group_member_id'],
                    'user_id' => Registry::load('current_user')->id,
                ];
                DB::connect()->update('live_cam_visitors', ['currently_viewing' => 1], $where);
                $currently_viewing = true;
            } else {
                $result = array();
                $result['status'] = 'pending';
                $result['alert_message'] = Registry::load('strings')->camera_view_pending;
            }

        } else {
            $result['alert_message'] = $info['group_member_id'];

            $insert_data = [
                'group_member_id' => $info['group_member_id'],
                'user_id' => Registry::load('current_user')->id
            ];

            if ((int)$info['live_camera'] === 1) {
                $insert_data['current_status'] = 0;
            } else {
                $insert_data['currently_viewing'] = 1;
                $currently_viewing = true;
            }

            DB::connect()->insert('live_cam_visitors', $insert_data);

            if ((int)$info['live_camera'] === 1) {
                $result = array();
                $result['status'] = 'pending';
                $result['alert_message'] = Registry::load('strings')->camera_view_pending;
            } else {
                $result = array();
                $result['status'] = 'approved';
            }
        }
        if ($currently_viewing) {
            ws_push(['update' => 'new_live_cam_viewer', 'group_id' => $group_id, 'lc_user_id' => $user_id]);
        }
    }
}


?>