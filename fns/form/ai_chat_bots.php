<?php

if (role(['permissions' => ['super_privileges' => 'core_settings']])) {

    $form = array();

    $todo = 'add';
    $location = 0;
    $form['loaded'] = new stdClass();
    $form['fields'] = new stdClass();

    if (isset($load["ai_chat_bot_id"])) {
        $load["ai_chat_bot_id"] = filter_var($load["ai_chat_bot_id"], FILTER_SANITIZE_NUMBER_INT);
    }

    if (isset($load["ai_chat_bot_id"]) && !empty($load["ai_chat_bot_id"])) {

        $columns = $where = $join = null;

        $columns = [
            'site_users.user_id', 'site_users.display_name', 'ai_chat_bots.description',
            'site_users.username', 'ai_chat_bots.ai_chat_bot_id', 'ai_chat_bots.ai_model'
        ];

        $where = [
            "ai_chat_bots.ai_chat_bot_id" => $load["ai_chat_bot_id"],
            "LIMIT" => 1
        ];
        $join["[>]site_users"] = ["ai_chat_bots.user_id" => "user_id"];
        $ai_chat_bot = DB::connect()->select('ai_chat_bots', $join, $columns, $where);

        if (isset($ai_chat_bot[0])) {
            $ai_chat_bot = $ai_chat_bot[0];
        } else {
            return;
        }

        $todo = 'update';
        $form['fields']->ai_chat_bot_id = [
            "tag" => 'input', "type" => 'hidden', "class" => 'd-none', "value" => $load["ai_chat_bot_id"]
        ];

        $form['loaded']->title = Registry::load('strings')->ai_chat_bots;
        $form['loaded']->button = Registry::load('strings')->update;


        $form['fields']->name = [
            "title" => Registry::load('strings')->name, "tag" => 'input', "type" => "text", "class" => 'field',
            "value" => $ai_chat_bot['display_name'], "attributes" => ["disabled" => true]
        ];

    } else {
        $form['loaded']->title = Registry::load('strings')->ai_chat_bots;
        $form['loaded']->button = Registry::load('strings')->create;
    }

    $form['fields']->$todo = [
        "tag" => 'input', "type" => 'hidden', "class" => 'd-none', "value" => "ai_chat_bots"
    ];

    $form['fields']->username = [
        "title" => Registry::load('strings')->username, "tag" => 'input', "type" => "text", "class" => 'field',
        "placeholder" => Registry::load('strings')->username,
    ];

    $form['fields']->use_different_model = [
        "title" => Registry::load('strings')->use_different_model, "tag" => 'select', "class" => 'field showfieldon'
    ];

    $form['fields']->use_different_model["attributes"] = [
        "fieldclass" => "model_fields",
        "checkvalue" => "no",
        "hideclass" => "ai_model_field"
    ];

    $form['fields']->use_different_model['options'] = [
        "yes" => Registry::load('strings')->yes,
        "no" => Registry::load('strings')->no,
    ];


    $form['fields']->ai_model = [
        "title" => Registry::load('strings')->ai_model_id, "tag" => 'input', "type" => "text", "class" => 'field model_fields ai_model_field d-none',
        "placeholder" => Registry::load('strings')->ai_model_id,
    ];


    $form['fields']->description = [
        "title" => Registry::load('strings')->describe_bot, "tag" => 'textarea', "class" => 'field',
        "placeholder" => Registry::load('strings')->describe_bot,
    ];

    $form['fields']->description["attributes"] = ["rows" => 10];

    if (isset($load["ai_chat_bot_id"]) && !empty($load["ai_chat_bot_id"])) {

        $form['fields']->validate_bot = [
            "title" => Registry::load('strings')->validate, "tag" => 'select', "class" => 'field'
        ];
        $form['fields']->validate_bot['options'] = [
            "yes" => Registry::load('strings')->yes,
            "no" => Registry::load('strings')->no,
        ];

        $form['fields']->username['value'] = $ai_chat_bot['username'];
        $form['fields']->description['value'] = $ai_chat_bot['description'];
        $form['fields']->use_different_model['value'] = 'no';

        if (!empty($ai_chat_bot['ai_model'])) {
            $form['fields']->use_different_model['value'] = 'yes';
            $form['fields']->ai_model['value'] = $ai_chat_bot['ai_model'];
            $form['fields']->ai_model['class'] = 'field model_fields ai_model_field';
        }
    }

}
?>