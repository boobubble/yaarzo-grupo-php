<?php

include 'fns/filters/load.php';

$result = array();
$result['success'] = false;
$result['error_message'] = Registry::load('strings')->something_went_wrong;
$result['error_key'] = 'something_went_wrong';
$ban_till = null;
$super_privileges = false;

if ($force_request || role(['permissions' => ['groups' => 'super_privileges']])) {
    $super_privileges = true;
}


$approve_user = 0;

if (isset($data['group_id'])) {
    $data["group_id"] = filter_var($data["group_id"], FILTER_SANITIZE_NUMBER_INT);

    if (!empty($data['group_id'])) {
        $columns = $where = $join = null;
        $columns = [
            'groups.group_id', 'group_members.group_role_id',
            'group_roles.group_role_attribute', 'group_roles.role_hierarchy'
        ];

        $join["[>]group_members"] = ["groups.group_id" => "group_id", "AND" => ["user_id" => Registry::load('current_user')->id]];
        $join["[>]group_roles"] = ["group_members.group_role_id" => "group_role_id"];

        $where['groups.group_id'] = $data['group_id'];

        $group_info = DB::connect()->select('groups', $join, $columns, $where);

        if (isset($group_info[0])) {
            $group_info = $group_info[0];
        } else {
            return false;
        }


        if ($super_privileges || isset($group_info['group_role_id']) && !empty($group_info['group_role_id'])) {

            if (!$super_privileges && !role(['permissions' => ['group_members' => 'approve_group_members'], 'group_role_id' => $group_info['group_role_id']])) {
                return false;
            }

            if (isset($data['approve_user_id'])) {
                $data["user_id"] = filter_var($data["approve_user_id"], FILTER_SANITIZE_NUMBER_INT);


                if (!empty($data['user_id'])) {
                    $approve_user = 1;
                }
            } elseif (isset($data['disapprove_user_id'])) {
                $data["user_id"] = filter_var($data["disapprove_user_id"], FILTER_SANITIZE_NUMBER_INT);
                $data["group_role_id"] = null;

                if (!empty($data['user_id'])) {
                    $approve_user = 0;
                }
            }




            if (isset($data['user_id'])) {
                $data["user_id"] = filter_var($data["user_id"], FILTER_SANITIZE_NUMBER_INT);

                if (!empty($data['user_id'])) {
                    $columns = $where = $join = null;

                    $columns = ['group_members.group_member_id', 'group_members.group_role_id', 'group_roles.group_role_attribute', 'group_roles.role_hierarchy'];
                    $where['AND'] = ['group_members.group_id' => $data['group_id'], 'group_members.user_id' => $data['user_id']];
                    $join["[>]group_roles"] = ["group_members.group_role_id" => "group_role_id"];

                    $group_member = DB::connect()->select('group_members', $join, $columns, $where);


                    if (isset($group_member[0])) {
                        $group_member = $group_member[0];

                        if (!$force_request && !$super_privileges) {

                            if (isset($group_member['group_role_attribute']) && $group_member['group_role_attribute'] === 'administrators') {
                                $result['error_message'] = Registry::load('strings')->permission_denied;
                                $result['error_key'] = 'permission_denied';
                                return false;
                            }

                            if ($group_info['group_role_attribute'] !== 'administrators') {
                                if ((int)$group_info['role_hierarchy'] <= (int)$group_member['role_hierarchy']) {
                                    $result['error_message'] = Registry::load('strings')->permission_denied;
                                    $result['error_key'] = 'permission_denied';
                                    return false;
                                }
                            }
                        }


                        DB::connect()->update('group_members', ['approved' => $approve_user], ['group_id' => $data["group_id"], 'user_id' => $data["user_id"]]);

                        if (!empty($approve_user)) {
                            if (isset(Registry::load('settings')->system_messages_groups->on_join_group_chat)) {
                                $system_message = [
                                    'message' => 'joined_group',
                                    'user_id' => $data["user_id"]
                                ];
                                $system_message = json_encode($system_message);

                                DB::connect()->insert("group_messages", [
                                    "system_message" => 1,
                                    "original_message" => 'system_message',
                                    "filtered_message" => $system_message,
                                    "group_id" => $data['group_id'],
                                    "user_id" => $data["user_id"],
                                    "created_on" => Registry::load('current_user')->time_stamp,
                                    "updated_on" => Registry::load('current_user')->time_stamp,
                                ]);

                                DB::connect()->update("groups", ["updated_on" => Registry::load('current_user')->time_stamp], ['group_id' => $data['group_id']]);

                                ws_push(['update' => 'new_group_message', 'group_id' => $data['group_id']]);
                            }
                        }

                        $result = array();
                        $result['success'] = true;
                        $result['todo'] = 'reload';
                        $result['reload'] = 'pending_group_members';
                        $result['info_box']['group_id'] = $data['group_id'];
                    }
                }
            }
        }
    }
}