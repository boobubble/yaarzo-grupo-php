<?php

if (role(['permissions' => ['complaints' => 'report']])) {

    include 'fns/filters/load.php';
    $result = array();
    $noerror = true;
    $message_id = $user_id = $group_id = null;
    $result['success'] = false;
    $result['error_message'] = Registry::load('strings')->invalid_value;
    $result['error_key'] = 'invalid_value';
    $result['error_variables'] = [];

    $reasons = ['spam', 'abuse', 'inappropriate', 'other'];

    if (!isset($data['group_id']) && !isset($data['user_id'])) {
        $noerror = false;
    }

    if (!isset($data['reason']) || empty(trim($data['reason'])) || !in_array($data['reason'], $reasons)) {
        $result['error_variables'][] = ['reason'];
        $noerror = false;
    }

    if (isset($data['group_id'])) {
        $data['group_id'] = filter_var($data['group_id'], FILTER_SANITIZE_NUMBER_INT);
        if (empty($data['group_id'])) {
            $noerror = false;
        } else {
            $columns = $where = null;
            $columns = ['name'];
            $where['group_id'] = $data['group_id'];
            $group_valid = DB::connect()->select('groups', $columns, $where);

            if (!isset($group_valid[0])) {
                $noerror = false;
            }
        }
    } else if (isset($data['user_id'])) {
        $data['user_id'] = filter_var($data['user_id'], FILTER_SANITIZE_NUMBER_INT);
        if (empty($data['user_id'])) {
            $noerror = false;
        } else {
            $columns = $where = null;
            $columns = ['display_name'];
            $where['user_id'] = $data['user_id'];
            $user_valid = DB::connect()->select('site_users', $columns, $where);

            if (!isset($user_valid[0])) {
                $noerror = false;
            }
        }
    }

    if ($noerror) {

        if (isset($data['comments'])) {
            $data['comments'] = htmlspecialchars($data['comments'], ENT_QUOTES, 'UTF-8');
        } else {
            $data['comments'] = '';
        }

        if (isset($data['group_id'])) {
            $group_id = $data['group_id'];
        } else if (isset($data['user_id'])) {
            $user_id = $data['user_id'];
        }

        if (isset($data['message_id'])) {
            $data['message_id'] = filter_var($data['message_id'], FILTER_SANITIZE_NUMBER_INT);
            if (!empty($data['message_id'])) {
                $message_id = $data['message_id'];
            }
        }

        $insert_data = [
            "reason" => $data['reason'],
            "comments_by_complainant" => $data['comments'],
            "complainant_user_id" => Registry::load('current_user')->id,
            "user_id" => $user_id,
            "group_id" => $group_id,
            "complaint_status" => 0,
            "created_on" => Registry::load('current_user')->time_stamp,
            "updated_on" => Registry::load('current_user')->time_stamp,
        ];

        if (!empty($message_id)) {
            if (!empty($group_id)) {
                $insert_data['group_message_id'] = $message_id;
            } else {
                $insert_data['private_chat_message_id'] = $message_id;
            }
        }

        DB::connect()->insert("complaints", $insert_data);

        ws_push(['update' => 'complaints']);

        if (!DB::connect()->error) {


            if (isset(Registry::load('settings')->send_email_notification->on_new_complaint)) {
                include_once('fns/mailer/load.php');

                if (isset($data['group_id'])) {
                    $complaint_against = Registry::load('strings')->group.' ['.$group_valid[0]['name'].']';
                } else if (isset($data['user_id'])) {
                    $complaint_against = $user_valid[0]['display_name'];
                }

                $whats_wrong = $data['reason'];
                $sql_query = "SELECT <site_users.email_address>
            FROM <site_users>
            LEFT JOIN <site_roles> ON <site_users.site_role_id> = <site_roles.site_role_id>
            WHERE <site_roles.permissions> LIKE :search
            LIMIT 20";

                $site_admins = DB::connect()->query($sql_query, [
                    ":search" => '%"review_complaints"%'
                ])->fetchAll(PDO::FETCH_ASSOC);

                if (count($site_admins) > 0) {
                    $site_admins = array_column($site_admins, 'email_address');


                    $complaint_details = Registry::load('strings')->complaint_against.' : '.$complaint_against.'<br/>';
                    $complaint_details .= Registry::load('strings')->complainant .' : '.Registry::load('current_user')->name.'<br/>';
                    $complaint_details .= Registry::load('strings')->whats_wrong .' : '.Registry::load('strings')->$whats_wrong.'<br/>';

                    if (!empty($message_id) && !empty($group_id)) {

                        $msg_where = ['group_messages.group_id' => $group_id, 'group_messages.group_message_id' => $message_id, 'LIMIT' => 1];
                        $msg_join["[>]site_users"] = ["group_messages.user_id" => "user_id"];
                        $msg = DB::connect()->select('group_messages', $msg_join, ['group_messages.filtered_message', 'site_users.username'], $msg_where);

                        if (isset($msg[0])) {
                            $msg_user = $msg[0]['username'];
                            $msg = $msg[0]['filtered_message'];
                            $msg = strip_tags($msg);
                            $complaint_details .= Registry::load('strings')->complaint_against .' : @'.$msg_user.'<br/>';

                            if (!empty($msg)) {
                                $complaint_details .= Registry::load('strings')->message .' : '.$msg.'<br/>';
                            }
                        }
                    }


                    if (!empty($data['comments'])) {
                        $complaint_details .= Registry::load('strings')->comments_if_any .' : '.$data['comments'];
                    }

                    $mail = array();
                    $mail['email_addresses'] = $site_admins;
                    $mail['category'] = 'new_complaint';
                    $mail['user_id'] = Registry::load('current_user')->id;
                    $mail['parameters'] = ['link' => Registry::load('config')->site_url, 'append_content' => $complaint_details];
                    $mail['send_now'] = true;
                    mailer('compose', $mail);
                }
            }


            $result = array();
            $result['success'] = true;
            $result['todo'] = 'reload';
            $result['reload'] = 'complaints';
        } else {
            $result['error_message'] = Registry::load('strings')->went_wrong;
            $result['error_key'] = 'something_went_wrong';
        }

    }
}
?>