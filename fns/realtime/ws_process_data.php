<?php


function process_ws_data($ws_instance, $ws_data, $ws_clients, $ws_users, $monitoring_table, $group_users) {

    $ws_solo_requests = ['site_notification', 'new_private_chat_message', 'new_live_cam_viewer', 'last_seen_by_recipient', 'friends_list', 'new_private_video_call', 'reload_page'];
    $ws_update_request = $ws_data['update'] ?? null;
    $target_fds = [];

    if ($ws_update_request === "refresh_user_groups" && isset($ws_data['user_id'])) {
        $user_id = (int)$ws_data['user_id'];

        if ($ws_users->exists($user_id)) {
            $user_row = $ws_users->get($user_id);
            $user_fds = json_decode($user_row['fds'] ?? '[]', true);

            $new_groups = [];
            $groups_query = DB::connect()->select('group_members', ['group_id'], ['user_id' => $user_id]);
            foreach ($groups_query as $group) {
                $new_groups[] = (string)$group['group_id'];
            }

            foreach ($user_fds as $fd) {
                if ($ws_clients->exists($fd)) {
                    $client_data = $ws_clients->get($fd);
                    $u_info = json_decode($client_data['info']);

                    $u_info->joined_groups = $new_groups;

                    $ws_clients->set($fd, [
                        'info' => json_encode($u_info)
                    ]);
                }
            }
        }
        return;
    }

    if ($ws_update_request === 'user_typing' && isset($ws_data['user_id']) && !isset($ws_data['group_id'])) {
        $ws_solo_requests[] = 'user_typing';
    }

    if (!Registry::stored('site_role_attributes')) {
        $site_role_attributes_data = extract_json(['file' => 'assets/cache/site_role_attributes.cache']);
        Registry::add('site_role_attributes', $site_role_attributes_data);
    }

    if (!Registry::stored('group_role_attributes')) {
        $group_role_attributes_data = extract_json(['file' => 'assets/cache/group_role_attributes.cache']);
        Registry::add('group_role_attributes', $group_role_attributes_data);
    }


    if (in_array($ws_update_request, $ws_solo_requests)) {
        $id_map = [
            'site_notification' => 'user_id',
            'new_live_cam_viewer' => 'lc_user_id',
            'friends_list' => 'user_id',
            'new_private_video_call' => 'user_id',
            'reload_page' => 'user_id',
            'new_private_chat_message' => 'receiver_id',
            'last_seen_by_recipient' => 'receiver_id',
            'user_typing' => 'user_id'
        ];

        $check_user_id = $ws_data[$id_map[$ws_update_request] ?? null] ?? null;

        if (!empty($check_user_id) && $ws_users->exists($check_user_id)) {
            $user_row = $ws_users->get($check_user_id);
            $target_fds = json_decode($user_row['fds'] ?? '[]', true);
        }

        if (in_array($ws_update_request, ["new_private_chat_message", "last_seen_by_recipient"])) {
            foreach ($monitoring_table as $m_fd => $m_row) {
                $target_fds[] = (int)$m_fd;
            }
        }
    } elseif (isset($ws_data['group_id'])) {
        $group_id = (string)$ws_data['group_id'];
        if ($group_users->exists($group_id)) {
            $joined_fds = json_decode($group_users->get($group_id)['fds'] ?? '[]', true);

            if ($ws_update_request === "new_online_group_member") {
                foreach ($joined_fds as $fd) {
                    $client = $ws_clients->get((int)$fd);
                    $context = json_decode($client['chat_context'] ?? '[]', true);
                    if (isset($context['online_group_members']) && (int)$context['online_group_members'] === (int)$group_id) {
                        $target_fds[] = (int)$fd;
                    }
                }
            } else {
                $target_fds = $joined_fds;
            }

            foreach ($ws_clients as $fd => $client_row) {
                $context = json_decode($client_row['chat_context'] ?? '[]', true);
                if (isset($context['group_id']) && $context['group_id'] === 'all') {
                    $target_fds[] = (int)$fd;
                }
            }
        }
    } elseif (in_array($ws_update_request, ["online_users", "complaints", "new_realtime_log"])) {

        if ($ws_update_request === "new_realtime_log") {
            foreach ($ws_instance->connections as $fd) {
                $target_fds[] = (int)$fd;
            }
        } else {
            foreach ($ws_clients as $fd => $client_row) {
                $context = json_decode($client_row['chat_context'] ?? '[]', true);
                $fd_int = (int)$fd;

                if ($ws_update_request === "online_users") {
                    if (isset($context['recent_online_user_id'])) {
                        $target_fds[] = $fd_int;
                    }
                } elseif ($ws_update_request === "complaints") {
                    if (isset($context['unresolved_complaints'])) {
                        $target_fds[] = $fd_int;
                    }
                }
            }
        }
    }

    if (empty($target_fds)) {
        return;
    }

    $target_fds = array_unique($target_fds);

    include_once('fns/load/load.php');

    $cached_strings = [];
    $cached_perms = [];
    $last_processed_user_id = null;

    foreach ($target_fds as $fd) {
        if (!$ws_clients->exists($fd)) continue;

        $active_user = $ws_clients->get($fd);
        $u_id = (int)$active_user['user_id'];
        $clientFd = (int)$fd;
        $result = [];
        $data = isset($active_user['chat_context']) ? json_decode($active_user['chat_context'], true) : [];

        if ($u_id !== $last_processed_user_id) {
            $u_info = json_decode($active_user["info"]);
            $u_info->language = $u_info->language ?? 1;

            Registry::add('current_user', $u_info);

            if (!isset($cached_strings[$u_info->language])) {
                $cached_strings[$u_info->language] = getLanguageStrings($u_info->language);
            }
            Registry::add('strings', $cached_strings[$u_info->language]);

            if (!isset($cached_perms[$u_info->site_role])) {
                $cached_perms[$u_info->site_role] = getRolePermissions($u_info->site_role);
            }
            Registry::add('permissions', $cached_perms[$u_info->site_role]);

            $last_processed_user_id = $u_id;
        }

        $current_user_id = $u_id;

        if ($ws_update_request === "site_notification") {
            include('fns/realtime/unread_site_notifications.php');
        } else if ($ws_update_request === "new_live_cam_viewer") {
            if (isset($data["live_cam_viewers"]) && (int)$data["live_cam_viewers"] === (int)$ws_data['group_id']) {
                $result['live_cam_viewers'] = (int)$ws_data['group_id'];
            }
        } elseif ($ws_update_request === "friends_list") {
            include('fns/realtime/pending_friend_requests.php');
        } elseif ($ws_update_request === "reload_page") {
            $result['reload_page'] = true;
        } elseif ($ws_update_request === "new_private_video_call") {
            if (isset($data['user_id'])) $data["user_id"] = filter_var($data["user_id"], FILTER_SANITIZE_NUMBER_INT);
            if (!empty($data['user_id']) && isset($ws_data['call_rejected'])) {
                $result['video_chat_status'] = ['user_id' => $data['user_id'], 'rejected' => true];
            } elseif (isset($ws_data['caller_left'])) {
                $result['new_call_notification'] = [];
            } elseif (isset($data["check_call_logs"], $data["current_call_id"])) {
                include('fns/realtime/check_call_logs.php');
            }
        } elseif ($ws_update_request === "user_typing") {
            $is_group = isset($ws_data['group_id']);
            $perm_type = $is_group ? 'groups' : 'private_conversations';

            $match = $is_group ? ((int)$ws_data['group_id'] === (int)($data['group_id'] ?? 0)) : true;

            if ($match && role(['permissions' => [$perm_type => 'typing_indicator']])) {
                include('fns/realtime/whos_typing.php');
            }
        } elseif ($ws_update_request === "new_online_group_member") {
            if (isset($data["online_group_members"]) && (int)$data["online_group_members"] === (int)$ws_data['group_id']) {
                $result['ogm_group_id'] = (int)$ws_data['group_id'];
            }
        } else if ($ws_update_request === "new_group_video_call") {
            if (isset($data["video_chat_status"]) && isset($data["group_id"])) {
                include('fns/realtime/video_chat_status.php');
            }
        } elseif ($ws_update_request === "new_group_message") {
            $msg_group_id = (int)$ws_data['group_id'];
            $user_viewing_id = (int)($data["group_id"] ?? 0);

            if (($msg_group_id !== 0 && $msg_group_id === $user_viewing_id) || (isset($data["group_id"]) && $data["group_id"] === 'all')) {
                include('fns/realtime/group_messages.php');
            }

            if (isset($data["unread_group_messages"])) {
                include('fns/realtime/unread_group_messages.php');
            }
        } elseif ($ws_update_request === "new_private_chat_message" || $ws_update_request === "last_seen_by_recipient") {
            $is_monitor = (isset($data["user_id"]) && $data["user_id"] === 'all');
            if ($is_monitor || (isset($ws_data['sender_id'], $data["user_id"]) && (int)$ws_data['sender_id'] === (int)$data["user_id"])) {
                if ($ws_update_request === "new_private_chat_message") include('fns/realtime/private_chat_messages.php');
                if (isset($data["last_seen_by_recipient"]) && role(['permissions' => ['private_conversations' => 'check_read_receipts']])) {
                    include('fns/realtime/last_seen_by_recipient.php');
                }
            }
            if (isset($data["unread_private_chat_messages"])) include('fns/realtime/unread_private_chat_messages.php');
        } elseif ($ws_update_request === "complaints" && isset($data["unresolved_complaints"])) {
            include('fns/realtime/unresolved_complaints.php');
        } elseif ($ws_update_request === "online_users" && isset($data["recent_online_user_id"])) {
            $result['recent_online_user_id'] = $clientFd;
        } elseif ($ws_update_request === "new_realtime_log" && isset($data["last_realtime_log_id"])) {
            include('fns/realtime/realtime_logs.php');
        }

        if (!empty($result) && $ws_instance->isEstablished($clientFd)) {
            $ws_instance->push($clientFd, json_encode($result));
        }
    }
}