<?php

$form = array();
$form['loaded'] = new stdClass();
$form['loaded']->title = Registry::load('strings')->temp_site_ban;
$form['loaded']->button = Registry::load('strings')->ban;


$form['fields'] = new stdClass();

$form['fields']->update = [
    "tag" => 'input', "type" => 'hidden', "class" => 'd-none', "value" => "site_user_role"
];

if (isset($load['user_id'])) {

    $load["user_id"] = filter_var($load["user_id"], FILTER_SANITIZE_NUMBER_INT);

    if (!empty($load['user_id'])) {

        $columns = $where = $join = null;

        $columns = [
            'site_users.display_name',
            'site_users.username',
            'site_roles.string_constant'
        ];

        $join["[>]site_roles"] = ['site_users.site_role_id' => 'site_role_id'];

        $where = ['site_users.user_id' => $load['user_id'], 'LIMIT' => 1];

        $user_info = DB::connect()->select('site_users', $join, $columns, $where);

        if (isset($user_info[0])) {

            $user_info = $user_info[0];

            $form['fields']->ban_user_id = [
                "tag" => 'input', "type" => 'hidden', "class" => 'd-none', "value" => $load['user_id']
            ];

            $form['fields']->full_name = [
                "title" => Registry::load('strings')->full_name, "tag" => 'input', "type" => 'text',
                "attributes" => ['disabled' => 'disabled'], "class" => 'field', "value" => $user_info['display_name'],
            ];

            $form['fields']->username = [
                "title" => Registry::load('strings')->username, "tag" => 'input', "type" => 'text',
                "attributes" => ['disabled' => 'disabled'], "class" => 'field', "value" => $user_info['username'],
            ];

            $current_role = $user_info['string_constant'];
            $current_role = Registry::load('strings')->$current_role;

            $form['fields']->current_role = [
                "title" => Registry::load('strings')->current_role, "tag" => 'input', "type" => 'text',
                "attributes" => ['disabled' => 'disabled'], "class" => 'field', "value" => $current_role,
            ];

            $tomorrow = date("Y-m-d", strtotime("+1 day"));

            $form['fields']->ban_till = [
                "title" => Registry::load('strings')->banned_till, "tag" => 'input', "type" => 'datetime-local',
                "class" => 'field', "attributes" => ['min' => $tomorrow]
            ];
        }
    }
}



?>