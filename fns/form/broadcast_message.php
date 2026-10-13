<?php
if (role(['permissions' => ['private_conversations' => 'broadcast_message']])) {
    $todo = 'add';
    $broadcast_message_id = 0;
    $language_id = Registry::load('current_user')->language;

    if (isset($load["broadcast_message_id"])) {
        $broadcast_message_id = filter_var($load["broadcast_message_id"], FILTER_SANITIZE_NUMBER_INT);

        if (!empty($broadcast_message_id)) {
            $message = DB::connect()->select("broadcast_messages",
                ['broadcast_messages.message_content', 'broadcast_messages.site_role_ids'],
                ['LIMIT' => 1, 'broadcast_message_id' => $broadcast_message_id]);

            if (isset($message[0])) {
                $todo = 'update';
                $message = $message[0];
            } else {
                $broadcast_message_id = 0;
            }
        }
    }

    $form = array();
    $form['loaded'] = new stdClass();
    $form['loaded']->title = Registry::load('strings')->broadcast_message;

    $form['fields'] = new stdClass();
    $group_list = array();

    $form['fields']->$todo = [
        "tag" => 'input', "type" => 'hidden', "class" => 'd-none', "value" => "broadcast_message"
    ];

    if (!empty($broadcast_message_id)) {
        $form['fields']->broadcast_message_id = [
            "tag" => 'input', "type" => 'hidden', "class" => 'd-none', "value" => $broadcast_message_id
        ];
    } else {
        $form['loaded']->button = Registry::load('strings')->$todo;
    }

    $form['fields']->message = [
        "title" => Registry::load('strings')->message, "tag" => 'textarea',
        "class" => 'field page_content content_editor tiny_toolbar schedule_message_field',
    ];

    $form['fields']->message["attributes"] = ["rows" => 6];

    $join = ["[>]language_strings(string)" => ["site_roles.string_constant" => "string_constant", "AND" => ["language_id" => $language_id]]];
    $columns = ['site_roles.site_role_id', 'string.string_value(name)'];
    $where = ['site_role_attribute[!]' => 'banned_users'];

    $site_roles = DB::connect()->select('site_roles', $join, $columns, $where);

    $site_roles = array_column($site_roles, 'name', 'site_role_id');

    $form['fields']->site_roles = [
        "title" => Registry::load('strings')->choose_site_roles, "tag" => 'checkbox', "class" => 'field choose_roles_pm',
        'select_all' => true
    ];

    $form['fields']->site_roles['options'] = $site_roles;


    if (!empty($broadcast_message_id)) {
        $form['fields']->message['value'] = $message['message_content'];
        $form['fields']->site_roles['value'] = $message['site_role_ids'];
    }
}
?>