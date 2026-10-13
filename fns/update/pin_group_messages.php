<?php
if (isset($data['message_id'])) {

    $data['message_id'] = filter_var($data["message_id"], FILTER_SANITIZE_NUMBER_INT);

    if (!empty($data['message_id'])) {

        $super_privileges = false;

        if (role(['permissions' => ['groups' => 'super_privileges']])) {
            $super_privileges = true;
        }

        $columns = $join = $where = null;
        $columns = [
            'group_members.group_role_id', 'group_messages.pin_message', 'group_messages.group_id'
        ];
        $join["[>]groups"] = ["group_messages.group_id" => "group_id"];

        $join["[>]group_members"] = ["group_messages.group_id" => "group_id", "AND" => ["group_members.user_id" => Registry::load('current_user')->id]];
        $where["group_messages.group_message_id"] = $data["message_id"];
        $where["group_messages.system_message"] = 0;
        $where["LIMIT"] = 1;
        $group_info = DB::connect()->select('group_messages', $join, $columns, $where);

        if (isset($group_info[0])) {
            $group_info = $group_info[0];
        } else {
            return;
        }

        if ($super_privileges || isset($group_info['group_role_id']) && !empty($group_info['group_role_id'])) {

            if ($super_privileges || role(['permissions' => ['messages' => 'pin_messages'], 'group_role_id' => $group_info['group_role_id']])) {

                $pin_status = 1;

                if ((int)$group_info['pin_message'] === 1) {
                    $pin_status = 0;
                }

                DB::connect()->update('group_messages', ['pin_message' => $pin_status], ['group_message_id' => $data['message_id']]);

                $realtime_log_data = array();
                $realtime_log_data["log_type"] = 'pinned_message';
                $realtime_log_data["related_parameters"] = ["group_id" => $group_info['group_id']];
                $realtime_log_data["related_parameters"] = json_encode($realtime_log_data["related_parameters"]);
                $realtime_log_data["created_on"] = Registry::load('current_user')->time_stamp;

                DB::connect()->insert("realtime_logs", $realtime_log_data);
                ws_push(['update' => 'new_realtime_log']);

            }
        }
    }
}
?>