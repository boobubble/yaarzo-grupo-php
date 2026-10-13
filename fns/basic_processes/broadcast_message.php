<?php
$broadcast_message_id = null;
$redirect = null;
$return_home = false;

if (role(['permissions' => ['private_conversations' => 'broadcast_message']])) {
    $page_content = [
        'title' => Registry::load('strings')->sending_message,
        'loading_text' => Registry::load('strings')->sending_message,
        'subtitle' => Registry::load('strings')->please_wait,
    ];

    if (isset($data["broadcast_message_id"]) && !empty($data["broadcast_message_id"])) {
        $broadcast_message_id = (int)$data["broadcast_message_id"];
    }

    if (!empty($broadcast_message_id)) {
        $page_content['redirect'] = Registry::load('config')->site_url.'basic_process?process=broadcast_message&broadcast_message_id='.$broadcast_message_id;

        $columns = $where = $join = null;
        $columns = [
            'message_content', 'status', 'user_id', 'attachment_type', 'attachments',
            'last_sent_to', 'site_role_ids'
        ];

        $where = [
            "broadcast_message_id" => $broadcast_message_id,
            "LIMIT" => 1,
        ];

        $message = DB::connect()->select('broadcast_messages', $columns, $where);

        if (isset($message[0]) && empty($message[0]['status'])) {
            $message = $message[0];

            $site_role_ids = json_decode($message['site_role_ids'], true);

            if (!empty($site_role_ids)) {

                $where = ['site_role_id' => $site_role_ids, 'LIMIT' => 50, 'ORDER' => ['user_id' => 'ASC']];
                $where['user_id[!]'] = $message['user_id'];

                if (!empty($message['last_sent_to'])) {
                    $where['user_id[>]'] = $message['last_sent_to'];
                }

                $site_users = DB::connect()->select('site_users', ['user_id'], $where);

                $insert_data = array();

                if (!empty($site_users)) {
                    foreach ($site_users as $site_user) {
                        $last_sent_to = $site_user['user_id'];

                        $where = [];

                        $where["OR"]["AND #first_query"] = [
                            "private_conversations.initiator_user_id" => $site_user['user_id'],
                            "private_conversations.recipient_user_id" => $message['user_id'],
                        ];
                        $where["OR"]["AND #second_query"] = [
                            "private_conversations.initiator_user_id" => $message['user_id'],
                            "private_conversations.recipient_user_id" => $site_user['user_id'],
                        ];

                        $where["LIMIT"] = 1;

                        $pc = DB::connect()->select('private_conversations', ['private_conversation_id'], $where);

                        if (isset($pc[0])) {
                            $private_conversation_id = $pc[0]['private_conversation_id'];
                        } else {
                            DB::connect()->insert("private_conversations", [
                                "initiator_user_id" => $message['user_id'],
                                "recipient_user_id" => $site_user["user_id"],
                                "created_on" => Registry::load('current_user')->time_stamp,
                                "updated_on" => Registry::load('current_user')->time_stamp,
                            ]);
                            $private_conversation_id = DB::connect()->id();
                        }

                        $insert_data[] = [
                            "original_message" => $message['message_content'],
                            "filtered_message" => $message['message_content'],
                            "private_conversation_id" => $private_conversation_id,
                            "user_id" => $message['user_id'],
                            "parent_message_id" => null,
                            "attachment_type" => $message['attachment_type'],
                            "attachments" => $message['attachments'],
                            "link_preview" => null,
                            "created_on" => Registry::load('current_user')->time_stamp,
                            "updated_on" => Registry::load('current_user')->time_stamp,
                        ];
                    }

                    if (!empty($insert_data)) {
                        DB::connect()->insert("private_chat_messages", $insert_data);

                        if (Registry::load('settings')->realtime_mode === 'websocket') {
                            foreach ($site_users as $site_user) {
                                ws_push(['update' => 'new_private_chat_message', 'receiver_id' => $site_user['user_id'], 'sender_id' => $message['user_id']]);
                            }
                        }

                        DB::connect()->update('broadcast_messages', ['last_sent_to' => $last_sent_to], ["broadcast_message_id" => $broadcast_message_id]);
                    }
                } else {
                    DB::connect()->update('broadcast_messages', ['status' => 1], ["broadcast_message_id" => $broadcast_message_id]);
                    $return_home = true;
                }
            } else {
                $return_home = true;
            }

        } else {
            $return_home = true;
        }


    } else {
        $return_home = true;
    }
    if ($return_home) {
        $page_content['redirect'] = Registry::load('config')->site_url;
    }
} else {
    $page_content = [
        'title' => 'Error',
        'page_content' => 'Error, Something Went Wrong',
        'heading' => 'Oops',
        'page_status' => 'error',
        'button_text' => 'Go Home',
        'button_link' => Registry::load('config')->site_url
    ];
}

?>