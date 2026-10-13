<?php

function ai_chat_module() {
    $provider = Registry::load('settings')->ai_chat_bots;
    $function_file = '';
    $result = array();
    $result['success'] = false;
    $result['error_key'] = 'something_went_wrong';
    $receiver_id = 0;

    if ($provider !== 'disable') {

        if (!empty($provider)) {
            $provider = preg_replace("/[^a-zA-Z0-9_]+/", "", $provider);
            $provider = str_replace('libraries', '', $provider);
        }

        if (!empty($provider)) {
            $function_file = 'fns/ai_chat/'.$provider.'.php';
            if (file_exists($function_file)) {


                $columns = $join = $where = $previous_chat_msgs = null;

                $columns = [
                    'ai_chat_messages.ai_msg_id',
                    'ai_chat_messages.private_conversation_id',
                    'ai_chat_messages.group_id',
                    'ai_chat_messages.message_id',
                    'ai_chat_bots.user_id',
                    'ai_chat_bots.description',
                    'ai_chat_bots.ai_model'
                ];

                $join["[>]ai_chat_bots"] = ["ai_chat_messages.ai_chat_bot_id" => "ai_chat_bot_id"];


                $where["LIMIT"] = 5;

                $ai_chat_msgs = DB::connect()->select('ai_chat_messages', $join, $columns, $where);
                foreach ($ai_chat_msgs as $ai_chat_msg) {

                    DB::connect()->delete('ai_chat_messages', [
                        'ai_msg_id' => $ai_chat_msg['ai_msg_id']
                    ]);

                    $previous_chat_msgs = null;

                    if (!empty($ai_chat_msg['private_conversation_id'])) {
                        $previous_chat_msgs = DB::connect()->select('private_chat_messages', ['filtered_message', 'user_id'], [
                            'private_chat_message_id[<=]' => $ai_chat_msg['message_id'],
                            'private_conversation_id' => $ai_chat_msg['private_conversation_id'],
                            'system_message' => 0,
                            'ORDER' => ['private_chat_message_id' => 'DESC'],
                            'LIMIT' => 10
                        ]);
                    } else if (!empty($ai_chat_msg['group_id'])) {
                        $previous_chat_msgs = DB::connect()->select('group_messages', ['filtered_message', 'user_id'], [
                            'group_message_id[<=]' => $ai_chat_msg['message_id'],
                            'group_id' => $ai_chat_msg['group_id'],
                            'system_message' => 0,
                            "whisper" => null,
                            'ORDER' => ['group_message_id' => 'DESC'],
                            'LIMIT' => 10
                        ]);
                    }

                    if (!empty($previous_chat_msgs)) {
                        $messages = array();

                        $messages[] = ['role' => 'system', 'content' => $ai_chat_msg['description']];

                        $previous_chat_msgs = array_reverse($previous_chat_msgs);

                        foreach ($previous_chat_msgs as $previous_chat_msg) {
                            $filtered_message = $previous_chat_msg['filtered_message'];

                            if (!empty($ai_chat_msg['group_id']) && Registry::load('settings')->trigger_ai_only_on_mention == 'yes') {
                                $filtered_message = preg_replace(
                                    '/<span class="get_info mention"[^>]*>@[^<]*<\/span>/iu',
                                    '',
                                    $filtered_message
                                );
                                $filtered_message = trim($filtered_message);
                            }

                            $filtered_message = strip_tags($filtered_message);

                            if ((int)$previous_chat_msg['user_id'] === (int)$ai_chat_msg['user_id']) {
                                $messages[] = ['role' => 'assistant', 'content' => $filtered_message];
                            } else {
                                $messages[] = ['role' => 'user', 'content' => $filtered_message];
                                $receiver_id = $previous_chat_msg['user_id'];
                            }
                        }
                        include($function_file);
                    }
                }
            }
        }
    }

    return $result;

}