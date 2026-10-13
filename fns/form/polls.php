<?php

if (role(['permissions' => ['groups' => 'create_poll']])) {

    $form = array();

    if (isset($load['group_id'])) {
        $load['group_id'] = filter_var($load['group_id'], FILTER_SANITIZE_NUMBER_INT);
    } else {
        return false;
    }


    if (isset($load["group_id"]) && !empty($load['group_id'])) {


        $columns = $where = null;
        $where = [
            'group_id' => $load['group_id'],
            'user_id' => Registry::load('current_user')->id,
            'LIMIT' => 1
        ];
        $group_member = DB::connect()->select('group_members', ['group_role_id'], $where);

        if (isset($group_member[0])) {
            $group_role_id = $group_member[0]['group_role_id'];

            if (!role(['permissions' => ['groups' => 'super_privileges']])) {
                if (!role(['permissions' => ['messages' => 'create_poll'], 'group_role_id' => $group_role_id])) {
                    return false;
                }
            }

        } else {
            return false;
        }


        $form['loaded'] = new stdClass();
        $form['fields'] = new stdClass();

        $form['fields']->group_id = [
            "tag" => 'input', "type" => 'hidden', "class" => 'd-none', "value" => $load["group_id"]
        ];

        $form['loaded']->title = Registry::load('strings')->create_poll;
        $form['loaded']->button = Registry::load('strings')->create;

        $form['fields']->add = [
            "tag" => 'input', "type" => 'hidden', "class" => 'd-none', "value" => "polls"
        ];

        $form['fields']->question = [
            "title" => Registry::load('strings')->question, "tag" => 'textarea', "class" => 'field',
            "attributes" => ["rows" => 3]
        ];

        $form['fields']->poll_options = [
            "title" => Registry::load('strings')->poll_options, "tag" => 'input', "type" => "text", "class" => 'field',
            "placeholder" => Registry::load('strings')->poll_options, "clone_field_on_input" => true,
        ];


    }
}

?>