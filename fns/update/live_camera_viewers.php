<?php

if (Registry::load('current_user')->logged_in) {
    if (Registry::load('settings')->video_chat !== 'disable' && Registry::load('settings')->broadcast_camera !== 'disable') {

        $current_user_id = Registry::load('current_user')->id;
        $group_id = isset($data['group_id'])
        ? filter_var($data['group_id'], FILTER_SANITIZE_NUMBER_INT)
        : null;

        $user_id = isset($data['user_id'])
        ? filter_var($data['user_id'], FILTER_SANITIZE_NUMBER_INT)
        : null;


        if (empty($group_id) || empty($user_id)) {
            return;
        }


        $group_member_id = DB::connect()->select('group_members', ['group_member_id'], ['group_id' => $group_id, 'user_id' => $current_user_id]);

        if (isset($group_member_id[0])) {
            $group_member_id = $group_member_id[0]['group_member_id'];

            $status = $post_status = isset($data['status']) ? $data['status'] : 'reject';

            if ($status === 'approve') {
                $status = 1;
            } else {
                $status = 2;
            }

            DB::connect()->update('live_cam_visitors', ['current_status' => $status], ['group_member_id' => $group_member_id, 'user_id' => $user_id]);

            if ($post_status === 'block') {
                $realtime_log_data = array();
                $realtime_log_data["log_type"] = 'blocked_live_cam_view';
                $realtime_log_data["related_parameters"] = [
                    "group_id" => $group_id,
                    "user_id" => $current_user_id,
                ];
                $realtime_log_data["related_parameters"] = json_encode($realtime_log_data["related_parameters"]);
                $realtime_log_data["user_id"] = $user_id;
                $realtime_log_data["created_on"] = Registry::load('current_user')->time_stamp;
                DB::connect()->insert("realtime_logs", $realtime_log_data);

                ws_push(['update' => 'new_realtime_log']);
            }

            $result = array();
            $result['success'] = true;
            $result['todo'] = 'reload';
            $result['reload'] = ['live_cam_viewers'];
        }
    }

}