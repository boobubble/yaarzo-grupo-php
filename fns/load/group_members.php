<?php

if (isset($data["group_id"])) {

    $data["group_id"] = filter_var($data["group_id"], FILTER_SANITIZE_NUMBER_INT);

    if (!empty($data["group_id"])) {

        $super_privileges = false;
        $today = new DateTime();
        $view_broadcast_cameras = false;

        if (role(['permissions' => ['groups' => 'super_privileges']])) {
            $super_privileges = true;
        }

        $permission = array();

        if (role(['permissions' => ['site_users' => 'unban_users_from_site']])) {
            $permission['unban_users'] = true;
        }

        if (role(['permissions' => ['site_users' => 'ban_users_from_site']])) {
            $permission['ban_users'] = true;
        }

        $group_id = $data["group_id"];

        $columns = $where = $join = null;
        $columns = [
            'groups.group_id', 'groups.secret_group', 'groups.password', 'group_members.group_role_id',
            'group_roles.group_role_attribute', 'group_roles.role_hierarchy', 'groups.broadcast_camera',
            'group_members.group_member_id', 'group_members.live_camera',
        ];

        $join["[>]group_members"] = ["groups.group_id" => "group_id", "AND" => ["user_id" => Registry::load('current_user')->id]];
        $join["[>]group_roles"] = ["group_members.group_role_id" => "group_role_id"];

        $where['groups.group_id'] = $group_id;

        $group_info = DB::connect()->select('groups', $join, $columns, $where);

        if (isset($group_info[0])) {
            $group_info = $group_info[0];
        } else {
            return false;
        }

        if (Registry::load('settings')->video_chat !== 'disable' && Registry::load('settings')->broadcast_camera !== 'disable') {
            if (!empty($group_info['broadcast_camera']) && role(['permissions' => ['groups' => 'view_camera_broadcasts']])) {
                if (isset($group_info['group_role_id']) && !empty($group_info['group_role_id'])) {
                    if (role(['permissions' => ['group' => 'view_camera_broadcasts'], 'group_role_id' => $group_info['group_role_id']])) {
                        $view_broadcast_cameras = true;
                    }
                }
            }
        }



        if (role(['permissions' => ['groups' => 'mention_users']])) {
            if (isset($group_info['group_role_id']) && !empty($group_info['group_role_id'])) {
                if (role(['permissions' => ['messages' => 'mention_users'], 'group_role_id' => $group_info['group_role_id']])) {
                    $permission['mention_users'] = true;
                }
            }
        }

        if (role(['permissions' => ['groups' => 'whisper']])) {
            if (isset($group_info['group_role_id']) && !empty($group_info['group_role_id'])) {
                if (role(['permissions' => ['messages' => 'whisper'], 'group_role_id' => $group_info['group_role_id']])) {
                    $permission['whisper'] = true;
                }
            }
        }

        $output = array();
        $output['loaded'] = new stdClass();
        $output['loaded']->title = Registry::load('strings')->members;
        $output['loaded']->loaded = 'group_members';
        $output['loaded']->null_search = true;
        $output['loaded']->offset = array();

        if ($super_privileges) {
            $output['multiple_select'] = new stdClass();
            $output['multiple_select']->title = Registry::load('strings')->remove;
            $output['multiple_select']->attributes['class'] = 'ask_confirmation';
            $output['multiple_select']->attributes['data-remove'] = 'group_members';
            $output['multiple_select']->attributes['data-group_id'] = $group_id;
            $output['multiple_select']->attributes['multi_select'] = 'group_member_id';
            $output['multiple_select']->attributes['submit_button'] = Registry::load('strings')->yes;
            $output['multiple_select']->attributes['cancel_button'] = Registry::load('strings')->no;
            $output['multiple_select']->attributes['confirmation'] = Registry::load('strings')->confirm_action;
        }

        $view_group_members = false;
        $approve_users = false;

        if (!isset($group_info['group_role_id']) || empty($group_info['group_role_id'])) {
            if (isset(Registry::load('settings')->hide_group_member_list_from_non_members) && Registry::load('settings')->hide_group_member_list_from_non_members === 'no') {
                $view_group_members = true;
            }
        }

        if ($view_group_members || $super_privileges || isset($group_info['group_role_id']) && !empty($group_info['group_role_id'])) {

            if ($view_group_members || $super_privileges || role(['permissions' => ['group_members' => 'view_group_members'], 'group_role_id' => $group_info['group_role_id']])) {

                $columns = $where = $join = null;
                $columns = [
                    'group_members.user_id', 'site_users.display_name', 'site_users.email_address', 'site_users.online_status',
                    'group_members.group_member_id', 'group_roles.string_constant(group_role)', 'group_members.live_camera',
                    'group_roles.group_role_attribute', 'group_members.banned_till', 'blacklist.ignore', 'blacklist.block',
                    'site_users.profile_picture', 'group_roles.role_hierarchy(group_hierarchy)', 'group_members.pin_group_member', 'group_members.approved',
                    'site_roles.role_hierarchy(site_hierarchy)', 'site_roles.site_role_attribute', 'site_users.username', 'site_users.site_role_id'
                ];

                if (Registry::load('settings')->show_age_members_list === 'yes' && !empty(Registry::load('settings')->birthdate_field)) {
                    $columns[] = 'birthdate.field_value(user_bday)';
                    $join["[>]custom_fields_values(birthdate)"] = ["group_members.user_id" => "user_id", "AND" => ["field_id" => Registry::load('settings')->birthdate_field]];
                }

                if (!empty($group_info['group_member_id'])) {
                    $columns[] = 'live_cam_visitors.current_status';
                    $columns[] = 'live_cam_visitors.currently_viewing';
                    $join["[>]live_cam_visitors"] = ["group_members.user_id" => "user_id", "AND" => ["live_cam_visitors.group_member_id" => $group_info['group_member_id']]];
                }

                $join["[>]site_users"] = ["group_members.user_id" => "user_id"];
                $join["[>]site_roles"] = ["site_users.site_role_id" => "site_role_id"];
                $join["[>]group_roles"] = ["group_members.group_role_id" => "group_role_id"];
                $join["[>]site_users_blacklist(blacklist)"] = ["group_members.user_id" => "blacklisted_user_id", "AND" => ["blacklist.user_id" => Registry::load('current_user')->id]];
                $join["[>]site_users_blacklist(blocked)"] = ["group_members.user_id" => "user_id", "AND" => ["blocked.blacklisted_user_id" => Registry::load('current_user')->id]];

                $where["group_members.group_id"] = $group_id;

                if (!$super_privileges) {

                    $check_user_black_list = true;

                    if (isset($group_info['group_role_attribute']) && $group_info['group_role_attribute'] === 'administrators') {
                        $check_user_black_list = false;
                    }

                    if (isset($group_info['group_role_attribute']) && $group_info['group_role_attribute'] === 'moderators') {
                        $check_user_black_list = false;
                    }

                    if ($check_user_black_list) {
                        $where["AND"]["OR #blocked"] = ["blocked.block" => NULL, "blocked.block(blocked)" => 0];
                    }
                }

                if (!empty($data["offset"])) {
                    $data["offset"] = array_map('intval', explode(',', $data["offset"]));
                    $where["group_members.group_member_id[!]"] = $data["offset"];
                }

                if (!empty($data["search"])) {
                    $where["AND #search_query"] = ["OR" => [
                        "site_users.display_name[~]" => $data["search"],
                        "site_users.username" => $data["search"],
                        "site_users.email_address" => $data["search"],
                    ]];
                }

                if ($super_privileges || role(['permissions' => ['group_members' => 'approve_group_members'], 'group_role_id' => $group_info['group_role_id']])) {
                    $approve_users = true;
                    if ($data["filter"] === 'pending') {
                        $where["group_members.approved"] = 0;
                    }
                }

                if (isset($private_data["live_cam_viewers"])) {
                    $output['loaded']->title = Registry::load('strings')->viewers;
                    $data["filter"] = 'online';

                    $where["site_users.online_status"] = 1;
                    $where["group_members.currently_browsing"] = 1;
                    $where["live_cam_visitors.current_status"] = 1;
                    $where["live_cam_visitors.currently_viewing"] = 1;

                    if (!role(['permissions' => ['site_users' => 'view_invisible_users']])) {

                        $join["[>]site_users_settings"] = ["site_users.user_id" => "user_id"];
                        $where["site_users_settings.offline_mode[!]"] = 1;
                    }

                } else if ($super_privileges || role(['permissions' => ['group_members' => 'view_currently_online'], 'group_role_id' => $group_info['group_role_id']])) {

                    if (Registry::load('settings')->shows_online_group_members_by_default === 'enable') {
                        if (empty($data["filter"])) {
                            $data["filter"] = 'online';
                        }
                    }

                    if ($data["filter"] === 'online') {

                        $where["site_users.online_status"] = 1;
                        $where["group_members.currently_browsing"] = 1;

                        if (!role(['permissions' => ['site_users' => 'view_invisible_users']])) {

                            $join["[>]site_users_settings"] = ["site_users.user_id" => "user_id"];
                            $where["site_users_settings.offline_mode[!]"] = 1;
                        }
                    }
                }

                if (isset($private_data["read_receipts"]) && $private_data["read_receipts"]) {

                    $output['loaded']->title = Registry::load('strings')->read_receipts;

                    if (isset($data["message_id"])) {
                        $data["message_id"] = filter_var($data["message_id"], FILTER_SANITIZE_NUMBER_INT);
                    }

                    if (isset($data["message_id"]) && !empty($data["message_id"])) {
                        if ($super_privileges || isset($group_info['group_role_id']) && !empty($group_info['group_role_id'])) {
                            if ($super_privileges || role(['permissions' => ['messages' => 'check_read_receipts'], 'group_role_id' => $group_info['group_role_id']])) {
                                $where["group_members.last_read_message_id[>=]"] = $data["message_id"];
                            }
                        }
                    } else {
                        $where["group_members.user_id"] = 0;
                    }
                }

                $where["LIMIT"] = Registry::load('settings')->records_per_call;

                if ($data["sortby"] === 'name_asc') {
                    if (Registry::load('settings')->hide_names_members_list === 'yes') {
                        $where["ORDER"] = ["site_users.username" => "ASC"];
                    } else {
                        $where["ORDER"] = ["site_users.display_name" => "ASC"];
                    }
                } else if ($data["sortby"] === 'name_desc') {
                    if (Registry::load('settings')->hide_names_members_list === 'yes') {
                        $where["ORDER"] = ["site_users.username" => "DESC"];
                    } else {
                        $where["ORDER"] = ["site_users.display_name" => "DESC"];
                    }
                } else if ($data["sortby"] === 'age_asc') {
                    $where["birthdate.field_value[!]"] = null;
                    $where["ORDER"] = ["birthdate.field_value" => "DESC"];
                } else if ($data["sortby"] === 'age_desc') {
                    $where["birthdate.field_value[!]"] = null;
                    $where["ORDER"] = ["birthdate.field_value" => "ASC"];
                } else if ($data["sortby"] === 'cam_asc') {
                    $where["ORDER"] = ["group_members.live_camera" => "ASC"];
                } else if ($data["sortby"] === 'cam_desc') {
                    $where["ORDER"] = ["group_members.live_camera" => "DESC"];
                } else {

                    if ($data["filter"] === 'online') {
                        $where["ORDER"] = [
                            "group_members.live_camera" => "DESC",
                            "group_members.group_role_id" => [2, 3, 4, 1],
                            "group_members.group_member_id" => "DESC",
                        ];
                    } else {
                        $where["ORDER"] = [
                            "group_members.pin_group_member" => "DESC",
                            "group_members.group_role_id" => [2, 3, 4, 1],
                            "group_members.group_member_id" => "DESC",
                        ];
                    }
                }

                $group_members = DB::connect()->select('group_members', $join, $columns, $where);

                $i = 1;

                if (!isset($private_data["read_receipts"]) && !isset($private_data["live_cam_viewers"])) {

                    if ($super_privileges || role(['permissions' => ['groups' => 'add_site_members']]) && $group_info['group_role_attribute'] === 'administrators') {
                        $output['todo'] = new stdClass();
                        $output['todo']->class = 'load_aside';
                        $output['todo']->title = Registry::load('strings')->add_members;
                        $output['todo']->attributes['load'] = 'non_group_members';
                        $output['todo']->attributes['data-group_id'] = $group_id;
                    } else if (role(['permissions' => ['groups' => 'add_site_members']]) && empty($group_info['password']) && empty($group_info['secret_group'])) {
                        $output['todo'] = new stdClass();
                        $output['todo']->class = 'load_aside';
                        $output['todo']->title = Registry::load('strings')->add_members;
                        $output['todo']->attributes['load'] = 'non_group_members';
                        $output['todo']->attributes['data-group_id'] = $group_id;
                    } else if (role(['permissions' => ['groups' => 'invite_users']]) && $group_info['group_role_attribute'] === 'administrators') {
                        $output['todo'] = new stdClass();
                        $output['todo']->class = 'load_form';
                        $output['todo']->title = Registry::load('strings')->invite_users;
                        $output['todo']->attributes['form'] = 'invite_group_members';
                        $output['todo']->attributes['data-group_id'] = $group_id;
                    } else if (role(['permissions' => ['groups' => 'invite_users']]) && empty($group_info['password']) && empty($group_info['secret_group'])) {
                        $output['todo'] = new stdClass();
                        $output['todo']->class = 'load_form';
                        $output['todo']->title = Registry::load('strings')->invite_users;
                        $output['todo']->attributes['form'] = 'invite_group_members';
                        $output['todo']->attributes['data-group_id'] = $group_id;
                    }
                }

                if (!empty($data["offset"])) {
                    $output['loaded']->offset = $data["offset"];
                }

                if (!isset($private_data["read_receipts"])) {
                    $output['sortby'][1] = new stdClass();
                    $output['sortby'][1]->sortby = Registry::load('strings')->sort_by_default;
                    $output['sortby'][1]->class = 'load_aside';
                    $output['sortby'][1]->attributes['load'] = 'group_members';
                    $output['sortby'][1]->attributes['data-group_id'] = $group_id;

                    if (isset($private_data["live_cam_viewers"])) {
                        $output['sortby'][1]->attributes['load'] = 'live_cam_viewers';
                    }

                    $output['sortby'][2] = new stdClass();
                    $output['sortby'][2]->sortby = Registry::load('strings')->name;
                    $output['sortby'][2]->class = 'load_aside sort_asc';
                    $output['sortby'][2]->attributes['load'] = 'group_members';
                    $output['sortby'][2]->attributes['sort'] = 'name_asc';
                    $output['sortby'][2]->attributes['data-group_id'] = $group_id;

                    if (isset($private_data["live_cam_viewers"])) {
                        $output['sortby'][2]->attributes['load'] = 'live_cam_viewers';
                    }

                    $output['sortby'][3] = new stdClass();
                    $output['sortby'][3]->sortby = Registry::load('strings')->name;
                    $output['sortby'][3]->class = 'load_aside sort_desc';
                    $output['sortby'][3]->attributes['load'] = 'group_members';
                    $output['sortby'][3]->attributes['sort'] = 'name_desc';
                    $output['sortby'][3]->attributes['data-group_id'] = $group_id;

                    if (isset($private_data["live_cam_viewers"])) {
                        $output['sortby'][3]->attributes['load'] = 'live_cam_viewers';
                    }

                    if (Registry::load('settings')->show_age_members_list === 'yes') {
                        $output['sortby'][4] = new stdClass();
                        $output['sortby'][4]->sortby = Registry::load('strings')->age;
                        $output['sortby'][4]->class = 'load_aside sort_asc';
                        $output['sortby'][4]->attributes['load'] = 'group_members';
                        $output['sortby'][4]->attributes['sort'] = 'age_asc';
                        $output['sortby'][4]->attributes['data-group_id'] = $group_id;

                        if (isset($private_data["live_cam_viewers"])) {
                            $output['sortby'][4]->attributes['load'] = 'live_cam_viewers';
                        }

                        $output['sortby'][5] = new stdClass();
                        $output['sortby'][5]->sortby = Registry::load('strings')->age;
                        $output['sortby'][5]->class = 'load_aside sort_desc';
                        $output['sortby'][5]->attributes['load'] = 'group_members';
                        $output['sortby'][5]->attributes['sort'] = 'age_desc';
                        $output['sortby'][5]->attributes['data-group_id'] = $group_id;

                        if (isset($private_data["live_cam_viewers"])) {
                            $output['sortby'][5]->attributes['load'] = 'live_cam_viewers';
                        }
                    }

                    if ($view_broadcast_cameras) {
                        $output['sortby'][6] = new stdClass();
                        $output['sortby'][6]->sortby = Registry::load('strings')->camera;
                        $output['sortby'][6]->class = 'load_aside sort_asc';
                        $output['sortby'][6]->attributes['load'] = 'group_members';
                        $output['sortby'][6]->attributes['sort'] = 'cam_asc';
                        $output['sortby'][6]->attributes['data-group_id'] = $group_id;

                        if (isset($private_data["live_cam_viewers"])) {
                            $output['sortby'][6]->attributes['load'] = 'live_cam_viewers';
                        }

                        $output['sortby'][7] = new stdClass();
                        $output['sortby'][7]->sortby = Registry::load('strings')->camera;
                        $output['sortby'][7]->class = 'load_aside sort_desc';
                        $output['sortby'][7]->attributes['load'] = 'group_members';
                        $output['sortby'][7]->attributes['sort'] = 'cam_desc';
                        $output['sortby'][7]->attributes['data-group_id'] = $group_id;

                        if (isset($private_data["live_cam_viewers"])) {
                            $output['sortby'][7]->attributes['load'] = 'live_cam_viewers';
                        }
                    }

                    $filter_option = 1;


                    if ($super_privileges || role(['permissions' => ['group_members' => 'view_currently_online'], 'group_role_id' => $group_info['group_role_id']])) {


                        $output['filters'][$filter_option] = new stdClass();
                        $output['filters'][$filter_option]->filter = Registry::load('strings')->all;

                        $output['filters'][$filter_option]->class = 'load_aside';
                        $output['filters'][$filter_option]->attributes['load'] = 'group_members';
                        $output['filters'][$filter_option]->attributes['data-group_id'] = $group_id;

                        if (Registry::load('settings')->shows_online_group_members_by_default === 'enable') {
                            $output['filters'][$filter_option]->attributes['filter'] = 'all';
                        }
                        $filter_option++;

                        $output['filters'][$filter_option] = new stdClass();
                        $output['filters'][$filter_option]->filter = Registry::load('strings')->online;
                        $output['filters'][$filter_option]->class = 'load_aside';
                        $output['filters'][$filter_option]->attributes['load'] = 'group_members';
                        $output['filters'][$filter_option]->attributes['data-group_id'] = $group_id;

                        if (Registry::load('settings')->shows_online_group_members_by_default !== 'enable') {
                            $output['filters'][$filter_option]->attributes['filter'] = 'online';
                        }

                        $filter_option++;

                        if ($view_broadcast_cameras) {
                            if (isset($group_info['live_camera']) && !empty($group_info['live_camera'])) {
                                $output['filters'][$filter_option] = new stdClass();
                                $output['filters'][$filter_option]->filter = Registry::load('strings')->viewers;
                                $output['filters'][$filter_option]->class = 'load_aside';
                                $output['filters'][$filter_option]->attributes['load'] = 'live_cam_viewers';
                                $output['filters'][$filter_option]->attributes['data-group_id'] = $group_id;
                                $filter_option++;
                            }
                        }

                    }
                }

                foreach ($group_members as $group_member) {

                    $higher_equal_role_hierarchy = true;
                    $higher_role_hierarchy = true;

                    if (Registry::load('current_user')->site_role_attribute !== 'administrators') {
                        if ((int)$group_member['site_hierarchy'] >= (int)Registry::load('current_user')->role_hierarchy) {
                            $higher_equal_role_hierarchy = false;
                        }
                    }


                    if ($group_info['group_role_attribute'] !== 'administrators') {
                        if ((int)$group_info['role_hierarchy'] <= (int)$group_member['group_hierarchy']) {
                            $higher_role_hierarchy = false;
                        }
                    }

                    $output['loaded']->offset[] = $group_member['group_member_id'];

                    $output['content'][$i] = new stdClass();
                    $output['content'][$i]->image = get_img_url(['from' => 'site_users/profile_pics', 'image' => $group_member['profile_picture'], 'gravatar' => $group_member['email_address']]);
                    $output['content'][$i]->title = $group_member['display_name'];
                    $output['content'][$i]->identifier = $group_member['group_member_id'];
                    $output['content'][$i]->class = "user";
                    $output['content'][$i]->icon = 0;
                    $output['content'][$i]->unread = 0;

                    if (Registry::load('settings')->hide_names_members_list === 'yes') {
                        $output['content'][$i]->title = $group_member['username'];
                    }

                    if (Registry::load('settings')->show_age_members_list === 'yes' && isset($group_member['user_bday']) && !empty($group_member['user_bday'])) {
                        $age = null;

                        try {
                            if (!empty($group_member['user_bday'])) {
                                $dob = new DateTime($group_member['user_bday']);

                                if ($dob < $today) {
                                    $age = $today->diff($dob)->y;

                                    if ($age <= 0) {
                                        $age = null;
                                    }
                                }
                            }
                        } catch (Exception $e) {
                            $age = null;
                        }

                        if ($age !== null) {
                            $output['content'][$i]->title .= ' [' . $age .' '.Registry::load('strings')->age__yrs.']';
                        }
                    }


                    if ($data["filter"] === 'online') {
                        $output['content'][$i]->online_status = 'online';
                    }

                    if (Registry::load('settings')->show_name_color_members_list === 'yes') {
                        $name_color = role(['find' => 'name_color', 'site_role_id' => $group_member['site_role_id']]);

                        if (!empty($name_color)) {
                            $output['content'][$i]->title_color = $name_color;
                        }
                    }

                    $group_role = $group_member['group_role'];
                    $output['content'][$i]->subtitle = Registry::load('strings')->$group_role;

                    if ($group_member['group_role_attribute'] === 'banned_users' && !empty($group_member['banned_till'])) {
                        $output['content'][$i]->subtitle = Registry::load('strings')->temporarily_banned;
                    }

                    if (!empty($group_member['pin_group_member']) && empty($data['sortby'])) {
                        $output['content'][$i]->icons[] = ['icon' => 'pinned_icon'];
                    }

                    if ($view_broadcast_cameras && (int)$group_member['online_status'] !== 0) {

                        $attributes = [];
                        if ((int)$group_member['user_id'] !== (int)Registry::load('current_user')->id) {

                            $attributes = [
                                'class' => 'live_cam_btn_view prevent_default',
                                'user_id' => $group_member['user_id'],
                                'group_id' => $data['group_id']
                            ];
                        }

                        if ((int)$group_member['live_camera'] === 2) {
                            $output['content'][$i]->icons[] = ['icon' => 'live_cam_icon', 'attributes' => $attributes];
                        } else if ((int)$group_member['live_camera'] === 1) {
                            $output['content'][$i]->icons[] = ['icon' => 'live_cam_private_icon', 'attributes' => $attributes];
                        }
                    }

                    if (isset($private_data["live_cam_viewers"])) {
                        $attributes = [
                            'class' => 'ask_confirmation prevent_default',
                            'data-update' => 'live_camera_viewers',
                            'data-user_id' => $group_member['user_id'],
                            'data-status' => 'block',
                            'data-group_id' => $data['group_id'],
                            'confirmation' => Registry::load('strings')->broadcast_block_user_confirm,
                            'submit_button' => Registry::load('strings')->yes,
                            'cancel_button' => Registry::load('strings')->no
                        ];
                        $output['content'][$i]->icons[] = ['icon' => 'eye_icon', 'attributes' => $attributes];
                    }

                    $option_index = 1;

                    if ((int)$group_member['user_id'] !== (int)Registry::load('current_user')->id) {

                        if ($view_broadcast_cameras && (int)$group_member['live_camera'] !== 0 && (int)$group_member['online_status'] !== 0) {
                            $output['options'][$i][$option_index] = new stdClass();
                            $output['options'][$i][$option_index]->option = Registry::load('strings')->view_camera;
                            $output['options'][$i][$option_index]->class = 'live_cam_btn_view hide_onClick';
                            $output['options'][$i][$option_index]->attributes['user_id'] = $group_member['user_id'];
                            $output['options'][$i][$option_index]->attributes['group_id'] = $data['group_id'];
                            $option_index++;
                        }

                        if (role(['permissions' => ['private_conversations' => 'send_message']])) {
                            $output['options'][$i][$option_index] = new stdClass();
                            $output['options'][$i][$option_index]->option = Registry::load('strings')->message;
                            $output['options'][$i][$option_index]->class = 'load_conversation force_request';
                            $output['options'][$i][$option_index]->attributes['user_id'] = $group_member['user_id'];
                            $option_index++;
                        }

                    }

                    $output['options'][$i][$option_index] = new stdClass();
                    $output['options'][$i][$option_index]->option = Registry::load('strings')->profile;
                    $output['options'][$i][$option_index]->class = 'get_info';
                    $output['options'][$i][$option_index]->attributes['data-group_identifier'] = $data['group_id'];
                    $output['options'][$i][$option_index]->attributes['user_id'] = $group_member['user_id'];
                    $option_index++;

                    if (role(['permissions' => ['complaints' => 'report']])) {
                        $output['options'][$i][$option_index] = new stdClass();
                        $output['options'][$i][$option_index]->option = Registry::load('strings')->report;
                        $output['options'][$i][$option_index]->class = 'load_form';
                        $output['options'][$i][$option_index]->attributes['form'] = 'complaint';
                        $output['options'][$i][$option_index]->attributes['data-user_id'] = $group_member['user_id'];
                        $option_index++;
                    }

                    if ($approve_users) {
                        if (!empty($group_member['approved'])) {
                            $output['options'][$i][$option_index] = new stdClass();
                            $output['options'][$i][$option_index]->option = Registry::load('strings')->disapprove;
                            $output['options'][$i][$option_index]->class = 'ask_confirmation';
                            $output['options'][$i][$option_index]->attributes['data-update'] = 'group_member_status';
                            $output['options'][$i][$option_index]->attributes['data-disapprove_user_id'] = $group_member['user_id'];
                            $output['options'][$i][$option_index]->attributes['data-group_id'] = $group_id;
                            $output['options'][$i][$option_index]->attributes['confirmation'] = Registry::load('strings')->disapprove_member_confirmation;
                            $output['options'][$i][$option_index]->attributes['submit_button'] = Registry::load('strings')->yes;
                            $output['options'][$i][$option_index]->attributes['cancel_button'] = Registry::load('strings')->no;
                            $option_index++;
                        } else {
                            $output['options'][$i][$option_index] = new stdClass();
                            $output['options'][$i][$option_index]->option = Registry::load('strings')->approve;
                            $output['options'][$i][$option_index]->class = 'ask_confirmation';
                            $output['options'][$i][$option_index]->attributes['data-update'] = 'group_member_status';
                            $output['options'][$i][$option_index]->attributes['data-approve_user_id'] = $group_member['user_id'];
                            $output['options'][$i][$option_index]->attributes['data-group_id'] = $group_id;
                            $output['options'][$i][$option_index]->attributes['confirmation'] = Registry::load('strings')->approve_member_confirmation;
                            $output['options'][$i][$option_index]->attributes['submit_button'] = Registry::load('strings')->yes;
                            $output['options'][$i][$option_index]->attributes['cancel_button'] = Registry::load('strings')->no;
                            $option_index++;
                        }
                    }

                    if ($group_member['group_role_attribute'] !== 'banned_users') {
                        if (isset($permission['mention_users'])) {
                            $output['options'][$i][$option_index] = new stdClass();
                            $output['options'][$i][$option_index]->option = Registry::load('strings')->mention;
                            $output['options'][$i][$option_index]->class = 'add_to_editor';
                            $output['options'][$i][$option_index]->attributes['content'] = '@['.$group_member['username'].'] ';
                            $option_index++;
                        }

                        if (isset($permission['whisper'])) {
                            $output['options'][$i][$option_index] = new stdClass();
                            $output['options'][$i][$option_index]->option = Registry::load('strings')->whisper;
                            $output['options'][$i][$option_index]->class = 'add_to_editor';
                            $output['options'][$i][$option_index]->attributes['content'] = '/whisper @['.$group_member['username'].'] ';
                            $option_index++;
                        }
                    }

                    if (role(['permissions' => ['site_users' => 'block_users']])) {
                        if ((int)$group_member['user_id'] !== (int)Registry::load('current_user')->id) {
                            if (!isset($group_member['block']) || empty($group_member['block'])) {
                                $output['options'][$i][$option_index] = new stdClass();
                                $output['options'][$i][$option_index]->option = Registry::load('strings')->block_user;
                                $output['options'][$i][$option_index]->class = 'ask_confirmation';
                                $output['options'][$i][$option_index]->attributes['data-update'] = 'site_user_blacklist';
                                $output['options'][$i][$option_index]->attributes['data-block_user_id'] = $group_member['user_id'];
                                $output['options'][$i][$option_index]->attributes['confirmation'] = Registry::load('strings')->block_user_confirmation;
                                $output['options'][$i][$option_index]->attributes['submit_button'] = Registry::load('strings')->yes;
                                $output['options'][$i][$option_index]->attributes['cancel_button'] = Registry::load('strings')->no;
                                $option_index++;
                            } else {
                                $output['options'][$i][$option_index] = new stdClass();
                                $output['options'][$i][$option_index]->option = Registry::load('strings')->unblock_user;
                                $output['options'][$i][$option_index]->class = 'ask_confirmation';
                                $output['options'][$i][$option_index]->attributes['data-update'] = 'site_user_blacklist';
                                $output['options'][$i][$option_index]->attributes['data-unblock_user_id'] = $group_member['user_id'];
                                $output['options'][$i][$option_index]->attributes['confirmation'] = Registry::load('strings')->unblock_user_confirmation;
                                $output['options'][$i][$option_index]->attributes['submit_button'] = Registry::load('strings')->yes;
                                $output['options'][$i][$option_index]->attributes['cancel_button'] = Registry::load('strings')->no;
                                $option_index++;
                            }
                        }
                    }

                    if (role(['permissions' => ['site_users' => 'ignore_users']])) {
                        if ((int)$group_member['user_id'] !== (int)Registry::load('current_user')->id) {
                            if (!isset($group_member['ignore']) || empty($group_member['ignore'])) {
                                $output['options'][$i][$option_index] = new stdClass();
                                $output['options'][$i][$option_index]->option = Registry::load('strings')->ignore_user;
                                $output['options'][$i][$option_index]->class = 'ask_confirmation';
                                $output['options'][$i][$option_index]->attributes['data-update'] = 'site_user_blacklist';
                                $output['options'][$i][$option_index]->attributes['data-ignore_user_id'] = $group_member['user_id'];
                                $output['options'][$i][$option_index]->attributes['confirmation'] = Registry::load('strings')->ignore_user_confirmation;
                                $output['options'][$i][$option_index]->attributes['submit_button'] = Registry::load('strings')->yes;
                                $output['options'][$i][$option_index]->attributes['cancel_button'] = Registry::load('strings')->no;
                                $option_index++;
                            } else {
                                $output['options'][$i][$option_index] = new stdClass();
                                $output['options'][$i][$option_index]->option = Registry::load('strings')->unignore_user;
                                $output['options'][$i][$option_index]->class = 'ask_confirmation';
                                $output['options'][$i][$option_index]->attributes['data-update'] = 'site_user_blacklist';
                                $output['options'][$i][$option_index]->attributes['data-unignore_user_id'] = $group_member['user_id'];
                                $output['options'][$i][$option_index]->attributes['confirmation'] = Registry::load('strings')->unignore_user_confirmation;
                                $output['options'][$i][$option_index]->attributes['submit_button'] = Registry::load('strings')->yes;
                                $output['options'][$i][$option_index]->attributes['cancel_button'] = Registry::load('strings')->no;
                                $option_index++;
                            }
                        }
                    }

                    if ($super_privileges || $higher_role_hierarchy && role(['permissions' => ['group_members' => 'manage_user_roles'], 'group_role_id' => $group_info['group_role_id']])) {
                        $output['options'][$i][$option_index] = new stdClass();
                        $output['options'][$i][$option_index]->option = Registry::load('strings')->edit_group_role;
                        $output['options'][$i][$option_index]->class = 'load_form';
                        $output['options'][$i][$option_index]->attributes['form'] = 'group_user_role';
                        $output['options'][$i][$option_index]->attributes['data-group_id'] = $data["group_id"];
                        $output['options'][$i][$option_index]->attributes['data-user_id'] = $group_member['user_id'];
                        $option_index++;
                    }


                    if ($super_privileges || role(['permissions' => ['group_members' => 'pin_group_members'], 'group_role_id' => $group_info['group_role_id']])) {
                        $output['options'][$i][$option_index] = new stdClass();
                        $output['options'][$i][$option_index]->option = Registry::load('strings')->pin_user;
                        $output['options'][$i][$option_index]->class = 'load_form';
                        $output['options'][$i][$option_index]->attributes['form'] = 'pin_group_member';
                        $output['options'][$i][$option_index]->attributes['data-group_id'] = $data["group_id"];
                        $output['options'][$i][$option_index]->attributes['data-user_id'] = $group_member['user_id'];
                        $option_index++;
                    }

                    if ((int)Registry::load('current_user')->id !== (int)$group_member['user_id']) {
                        if ($super_privileges || $higher_role_hierarchy && role(['permissions' => ['group_members' => 'ban_users_from_group'], 'group_role_id' => $group_info['group_role_id']])) {
                            if ($group_member['group_role_attribute'] !== 'banned_users') {
                                $output['options'][$i][$option_index] = new stdClass();
                                $output['options'][$i][$option_index]->option = Registry::load('strings')->temporary_ban_from_group;
                                $output['options'][$i][$option_index]->class = 'load_form';
                                $output['options'][$i][$option_index]->attributes['form'] = 'temporary_ban_from_group';
                                $output['options'][$i][$option_index]->attributes['data-group_id'] = $group_id;
                                $output['options'][$i][$option_index]->attributes['data-user_id'] = $group_member['user_id'];
                                $option_index++;

                                $output['options'][$i][$option_index] = new stdClass();
                                $output['options'][$i][$option_index]->class = 'ask_confirmation';
                                $output['options'][$i][$option_index]->option = Registry::load('strings')->ban_from_group;
                                $output['options'][$i][$option_index]->attributes['data-update'] = 'group_user_role';
                                $output['options'][$i][$option_index]->attributes['data-group_id'] = $group_id;
                                $output['options'][$i][$option_index]->attributes['submit_button'] = Registry::load('strings')->yes;
                                $output['options'][$i][$option_index]->attributes['cancel_button'] = Registry::load('strings')->no;
                                $output['options'][$i][$option_index]->attributes['data-ban_user_id'] = $group_member['user_id'];
                                $output['options'][$i][$option_index]->attributes['confirmation'] = Registry::load('strings')->ban_from_group_confirmation;
                                $option_index++;
                            }
                        }
                    }

                    if ($group_member['group_role_attribute'] === 'banned_users') {
                        if ($super_privileges || $higher_role_hierarchy && role(['permissions' => ['group_members' => 'unban_users_from_group'], 'group_role_id' => $group_info['group_role_id']])) {
                            $output['options'][$i][$option_index] = new stdClass();
                            $output['options'][$i][$option_index]->class = 'ask_confirmation';
                            $output['options'][$i][$option_index]->option = Registry::load('strings')->unban_from_group;
                            $output['options'][$i][$option_index]->attributes['data-update'] = 'group_user_role';
                            $output['options'][$i][$option_index]->attributes['data-group_id'] = $group_id;
                            $output['options'][$i][$option_index]->attributes['submit_button'] = Registry::load('strings')->yes;
                            $output['options'][$i][$option_index]->attributes['cancel_button'] = Registry::load('strings')->no;
                            $output['options'][$i][$option_index]->attributes['data-unban_user_id'] = $group_member['user_id'];
                            $output['options'][$i][$option_index]->attributes['confirmation'] = Registry::load('strings')->unban_from_group_confirmation;
                            $option_index++;
                        }
                    }

                    if (isset($permission['unban_users']) && $group_member['site_role_attribute'] === 'banned_users' && $higher_equal_role_hierarchy) {
                        $output['options'][$i][$option_index] = new stdClass();
                        $output['options'][$i][$option_index]->class = 'ask_confirmation';
                        $output['options'][$i][$option_index]->attributes['data-update'] = 'site_user_role';
                        $output['options'][$i][$option_index]->attributes['submit_button'] = Registry::load('strings')->yes;
                        $output['options'][$i][$option_index]->attributes['cancel_button'] = Registry::load('strings')->no;
                        $output['options'][$i][$option_index]->option = Registry::load('strings')->unban_from_site;
                        $output['options'][$i][$option_index]->attributes['data-unban_user_id'] = $group_member['user_id'];
                        $output['options'][$i][$option_index]->attributes['confirmation'] = Registry::load('strings')->unban_from_site_confirmation;
                        $option_index++;
                    }

                    if ((int)$group_member['user_id'] !== (int)Registry::load('current_user')->id) {
                        if (isset($permission['ban_users']) && $group_member['site_role_attribute'] !== 'banned_users' && $higher_equal_role_hierarchy) {
                            $output['options'][$i][$option_index] = new stdClass();
                            $output['options'][$i][$option_index]->class = 'ask_confirmation';
                            $output['options'][$i][$option_index]->attributes['data-update'] = 'site_user_role';
                            $output['options'][$i][$option_index]->attributes['submit_button'] = Registry::load('strings')->yes;
                            $output['options'][$i][$option_index]->attributes['cancel_button'] = Registry::load('strings')->no;
                            $output['options'][$i][$option_index]->option = Registry::load('strings')->ban_from_site;
                            $output['options'][$i][$option_index]->attributes['data-ban_user_id'] = $group_member['user_id'];
                            $output['options'][$i][$option_index]->attributes['confirmation'] = Registry::load('strings')->ban_from_site_confirmation;
                            $option_index++;

                            $output['options'][$i][$option_index] = new stdClass();
                            $output['options'][$i][$option_index]->class = 'load_form';
                            $output['options'][$i][$option_index]->attributes['form'] = 'temporary_ban_from_site';
                            $output['options'][$i][$option_index]->attributes['data-user_id'] = $group_member['user_id'];
                            $output['options'][$i][$option_index]->option = Registry::load('strings')->temp_site_ban;
                            $option_index++;
                        }
                    }


                    if ($super_privileges || $higher_role_hierarchy && role(['permissions' => ['group_members' => 'remove_group_members'], 'group_role_id' => $group_info['group_role_id']])) {
                        $output['options'][$i][$option_index] = new stdClass();
                        $output['options'][$i][$option_index]->option = Registry::load('strings')->remove_from_group;
                        $output['options'][$i][$option_index]->class = 'ask_confirmation';
                        $output['options'][$i][$option_index]->attributes['data-remove'] = 'group_members';
                        $output['options'][$i][$option_index]->attributes['data-group_id'] = $group_id;
                        $output['options'][$i][$option_index]->attributes['data-user_id'] = $group_member['user_id'];
                        $output['options'][$i][$option_index]->attributes['confirmation'] = Registry::load('strings')->remove_from_group_confirmation;
                        $output['options'][$i][$option_index]->attributes['submit_button'] = Registry::load('strings')->yes;
                        $output['options'][$i][$option_index]->attributes['cancel_button'] = Registry::load('strings')->no;
                        $option_index++;
                    }

                    $i++;
                }
            }
        }
    }
}
?>