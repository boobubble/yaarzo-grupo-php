<?php

if (Registry::load('current_user')->logged_in) {
    if (Registry::load('settings')->video_chat !== 'disable') {

        if (isset($data["group_id"]) && !empty($data["group_id"])) {
            $group_id = filter_var($data["group_id"], FILTER_SANITIZE_NUMBER_INT);
            $current_user_id = Registry::load('current_user')->id;

            $user_id = isset($data['user_id'])
            ? filter_var($data['user_id'], FILTER_SANITIZE_NUMBER_INT)
            : null;

            if (is_numeric($group_id)) {

                if (!empty($user_id)) {

                    include('fns/video_chat/load.php');

                    $status = video_chat_module([
                        'check_user_status' => [
                            'channel_name' => 'live_camera_' . $group_id,
                            'user_id' => $user_id,
                        ]
                    ]);

                    if (!$status) {
                        $current_user_id = $user_id;
                    } else {
                        return;
                    }
                }

                $group_member_id = DB::connect()->select('group_members', ['group_member_id'], ['group_id' => $group_id, 'user_id' => $current_user_id]);

                if (isset($group_member_id[0])) {
                    $group_member_id = $group_member_id[0]['group_member_id'];
                    DB::connect()->delete('live_cam_visitors', ['group_member_id' => $group_member_id]);
                }

                DB::connect()->update('group_members', ['live_camera' => 0], ['group_id' => $group_id, 'user_id' => $current_user_id]);
                ws_push(['update' => 'new_online_group_member', 'group_id' => $group_id]);
            }
        }
    }

}