<?php
$result = array();
$result['success'] = false;
$result['error_message'] = Registry::load('strings')->went_wrong;
$result['error_key'] = 'something_went_wrong';
$noerror = true;
$poll_options = array();

if (role(['permissions' => ['groups' => 'create_poll']])) {

    if (isset($data['group_id'])) {
        $data['group_id'] = filter_var($data['group_id'], FILTER_SANITIZE_NUMBER_INT);
    }

    if (!isset($data["group_id"]) || empty($data['group_id'])) {
        $noerror = false;
    }

    if ($noerror) {
        $result['error_message'] = Registry::load('strings')->invalid_value;
        $result['error_key'] = 'invalid_value';

        $required_fields = ['question', 'poll_options'];

        foreach ($required_fields as $required_field) {
            if (!isset($data[$required_field]) || empty($data[$required_field])) {
                $result['error_variables'][] = [$required_field];
                $noerror = false;
            }
        }
        if ($noerror) {
            $poll_options = array_filter($data['poll_options'] ?? [], fn($o) => trim($o) !== '');
            if (count($poll_options) < 2) {
                $result['error_message'] = Registry::load('strings')->two_polls_options_required;
                $noerror = false;
            } else if (count($poll_options) > 10) {
                $result['error_message'] = Registry::load('strings')->max_ten_poll_options;
                $noerror = false;
            }
        }
    }

    if ($noerror) {
        $noerror = false;
        $columns = $where = null;
        $where = [
            'group_id' => $data['group_id'],
            'user_id' => Registry::load('current_user')->id,
            'LIMIT' => 1
        ];
        $group_member = DB::connect()->select('group_members', ['group_role_id'], $where);

        if (isset($group_member[0])) {
            $group_role_id = $group_member[0]['group_role_id'];

            if (role(['permissions' => ['groups' => 'super_privileges']]) || role(['permissions' => ['messages' => 'create_poll'], 'group_role_id' => $group_role_id])) {
                $noerror = true;
            }

        }
    }

    if ($noerror) {

        $data['question'] = htmlspecialchars($data['question'], ENT_QUOTES, 'UTF-8');

        DB::connect()->insert("polls", [
            "question" => $data['question'],
            "group_id" => $data['group_id'],
            "created_by" => Registry::load('current_user')->id,
            "created_on" => Registry::load('current_user')->time_stamp,
        ]);

        if (!DB::connect()->error) {
            $poll_id = DB::connect()->id();

            foreach ($poll_options as $poll_option) {
                $poll_option = htmlspecialchars($poll_option, ENT_QUOTES, 'UTF-8');

                if (!empty($poll_option)) {
                    DB::connect()->insert("poll_options", [
                        "option_text" => $poll_option,
                        "poll_id" => $poll_id,
                    ]);
                }
            }
            DB::connect()->insert("group_messages", [
                "original_message" => '',
                "filtered_message" => '',
                "group_id" => $data['group_id'],
                "user_id" => Registry::load('current_user')->id,
                "attachment_type" => 'poll',
                "attachments" => json_encode(['poll_id' => $poll_id]),
                "created_on" => Registry::load('current_user')->time_stamp,
                "updated_on" => Registry::load('current_user')->time_stamp,
            ]);

            $group_message_id = DB::connect()->id();

            DB::connect()->update("polls", [
                "group_message_id" => $group_message_id
            ], ['poll_id' => $poll_id]);

            ws_push(['update' => 'new_group_message', 'group_id' => $data['group_id']]);

            $result = array();
            $result['success'] = true;
            $result['todo'] = 'reload';
            $result['reload'] = 'group_polls';
        }
    }

}
?>