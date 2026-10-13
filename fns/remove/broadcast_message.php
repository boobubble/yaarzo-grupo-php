<?php
$result = array();
$noerror = true;

$result['success'] = false;
$result['error_message'] = Registry::load('strings')->went_wrong;
$result['error_key'] = 'something_went_wrong';
$broadcast_message_ids = array();

if (role(['permissions' => ['private_conversations' => 'broadcast_message']])) {

    if (isset($data['broadcast_message_id'])) {
        if (!is_array($data['broadcast_message_id'])) {
            $data["broadcast_message_id"] = filter_var($data["broadcast_message_id"], FILTER_SANITIZE_NUMBER_INT);
            $broadcast_message_ids[] = $data["broadcast_message_id"];
        } else {
            $broadcast_message_ids = array_filter($data["broadcast_message_id"], 'ctype_digit');
        }
    }

    if (!empty($broadcast_message_ids)) {

        DB::connect()->delete("broadcast_messages", ["broadcast_message_id" => $broadcast_message_ids, "user_id" => Registry::load('current_user')->id]);

        if (!DB::connect()->error) {
            $result = array();
            $result['success'] = true;
            $result['todo'] = 'reload';
            $result['reload'] = 'broadcast_message';
        } else {
            $result['errormsg'] = Registry::load('strings')->went_wrong;
        }
    }
}
?>