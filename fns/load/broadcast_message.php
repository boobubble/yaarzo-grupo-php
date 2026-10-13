<?php

if (role(['permissions' => ['private_conversations' => 'broadcast_message']])) {

    $columns = $where = $join = null;
    $columns = [
        'broadcast_messages.broadcast_message_id', 'broadcast_messages.status',
        'broadcast_messages.user_id', 'site_users.display_name'
    ];


    $join["[>]site_users"] = ["broadcast_messages.user_id" => "user_id"];

    if (!empty($data["offset"])) {
        $data["offset"] = array_map('intval', explode(',', $data["offset"]));
        $where["broadcast_messages.broadcast_message_id[!]"] = $data["offset"];
    }

    if (!empty($data["search"])) {
        $where["AND #search_query"]["OR"] = ["broadcast_messages.message_content[~]" => $data["search"], "site_users.display_name[~]" => $data["search"]];
    }

    $where["broadcast_messages.user_id"] = Registry::load('current_user')->id;
    $where["LIMIT"] = Registry::load('settings')->records_per_call;
    $where["ORDER"] = [
        "broadcast_messages.broadcast_message_id" => "DESC"
    ];

    $messages = DB::connect()->select('broadcast_messages', $join, $columns, $where);

    $i = 1;
    $output = array();
    $output['loaded'] = new stdClass();
    $output['loaded']->title = Registry::load('strings')->messages;
    $output['loaded']->loaded = 'broadcast_message';
    $output['loaded']->offset = array();

    if (!empty($data["offset"])) {
        $output['loaded']->offset = $data["offset"];
    }


    $output['multiple_select'] = new stdClass();
    $output['multiple_select']->title = Registry::load('strings')->delete;
    $output['multiple_select']->attributes['class'] = 'ask_confirmation';
    $output['multiple_select']->attributes['data-remove'] = 'broadcast_message';
    $output['multiple_select']->attributes['multi_select'] = 'broadcast_message_id';
    $output['multiple_select']->attributes['submit_button'] = Registry::load('strings')->yes;
    $output['multiple_select']->attributes['cancel_button'] = Registry::load('strings')->no;
    $output['multiple_select']->attributes['confirmation'] = Registry::load('strings')->confirm_action;

    $output['todo'] = new stdClass();
    $output['todo']->class = 'load_form';
    $output['todo']->title = Registry::load('strings')->broadcast_message;
    $output['todo']->attributes['form'] = 'broadcast_message';
    $output['todo']->attributes['enlarge'] = true;


    foreach ($messages as $message) {
        $output['loaded']->offset[] = $message['broadcast_message_id'];

        $output['content'][$i] = new stdClass();
        $output['content'][$i]->image = Registry::load('config')->site_url."assets/files/defaults/scheduled_messages.png";
        $output['content'][$i]->title = 'MSG#'.$message['broadcast_message_id'].' ['.$message['display_name'].']';
        $output['content'][$i]->identifier = $message['broadcast_message_id'];
        $output['content'][$i]->class = "device_log square";
        $output['content'][$i]->icon = 0;
        $output['content'][$i]->unread = 0;

        if ((int)$message['status'] === 1) {
            $message['status'] = Registry::load('strings')->success;
        } else {
            $message['status'] = Registry::load('strings')->pending;
        }

        $output['content'][$i]->subtitle = $message['status'];

        $output['options'][$i][1] = new stdClass();
        $output['options'][$i][1]->option = Registry::load('strings')->execute;
        $output['options'][$i][1]->class = 'open_link';
        $output['options'][$i][1]->attributes['target'] = '_blank';
        $output['options'][$i][1]->attributes['link'] = Registry::load('config')->site_url.'basic_process/?process=broadcast_message&broadcast_message_id='.$message['broadcast_message_id'];

        $output['options'][$i][2] = new stdClass();
        $output['options'][$i][2]->option = Registry::load('strings')->view;
        $output['options'][$i][2]->class = 'load_form';
        $output['options'][$i][2]->attributes['form'] = 'broadcast_message';
        $output['options'][$i][2]->attributes['enlarge'] = true;
        $output['options'][$i][2]->attributes['data-broadcast_message_id'] = $message['broadcast_message_id'];


        $output['options'][$i][3] = new stdClass();
        $output['options'][$i][3]->option = Registry::load('strings')->delete;
        $output['options'][$i][3]->class = 'ask_confirmation';
        $output['options'][$i][3]->attributes['data-remove'] = 'broadcast_message';
        $output['options'][$i][3]->attributes['data-broadcast_message_id'] = $message['broadcast_message_id'];
        $output['options'][$i][3]->attributes['confirmation'] = Registry::load('strings')->confirm_action;
        $output['options'][$i][3]->attributes['submit_button'] = Registry::load('strings')->yes;
        $output['options'][$i][3]->attributes['cancel_button'] = Registry::load('strings')->no;


        $i++;
    }
}
?>