<?php

if (Registry::load('current_user')->logged_in) {
    if (Registry::load('settings')->video_chat !== 'disable' && Registry::load('settings')->broadcast_camera !== 'disable') {

        include('fns/video_chat/load.php');

        if (isset($data["group_id"]) && !empty($data["group_id"])) {
            $group_id = filter_var($data["group_id"], FILTER_SANITIZE_NUMBER_INT);
            $current_user_id = Registry::load('current_user')->id;

            if (is_numeric($group_id)) {

                $columns = $join = $where = null;
                $join["[>]groups"] = ["group_members.group_id" => "group_id"];
                $columns = ['group_members.group_member_id', 'group_members.group_role_id', 'groups.broadcast_camera'];
                $member = DB::connect()->select('group_members', $join, $columns, ['group_members.group_id' => $group_id, 'group_members.user_id' => $current_user_id, 'LIMIT' => 1]);

                if (isset($member[0])) {
                    $member = $member[0];

                    if (empty($member['broadcast_camera'])) {
                        return;
                    }
                } else {
                    return;
                }

                $broadcast_permission = false;
                $view_broadcast = false;

                if (role(['permissions' => ['groups' => 'broadcast_camera']])) {
                    if (role(['permissions' => ['group' => 'broadcast_camera'], 'group_role_id' => $member['group_role_id']])) {
                        $broadcast_permission = true;
                    }
                }

                if (role(['permissions' => ['groups' => 'view_camera_broadcasts']])) {
                    if (role(['permissions' => ['group' => 'view_camera_broadcasts'], 'group_role_id' => $member['group_role_id']])) {
                        $view_broadcast = true;
                    }
                }

                $channel_name = 'live_camera_' . $group_id;

                $intent = isset($data['intent']) ? $data['intent'] : 'view';
                $privacy = isset($data['privacy']) ? $data['privacy'] : 'public';


                if ($intent === 'broadcast') {
                    if ($broadcast_permission) {
                        $live_camera_status = 2;

                        if ($privacy === 'private') {
                            $live_camera_status = 1;
                        }

                        DB::connect()->delete('live_cam_visitors', ['group_member_id' => $member['group_member_id']]);

                        DB::connect()->update('group_members', ['live_camera' => $live_camera_status], ['group_id' => $group_id, 'user_id' => $current_user_id]);
                        ws_push(['update' => 'new_online_group_member', 'group_id' => $group_id]);

                    } else {
                        return;
                    }
                } else if (!$view_broadcast) {
                    return;
                }


                $result = video_chat_module([
                    'generate_token' => [
                        'channel_name' => $channel_name,
                        'channel_admin' => false,
                        'one_to_one' => false,
                        'viewer_mode' => false
                    ]
                ]);
            }
        }
    }

}