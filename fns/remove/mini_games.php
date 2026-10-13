<?php
$result = array();
$noerror = true;

$result['success'] = false;
$result['error_message'] = Registry::load('strings')->went_wrong;
$result['error_key'] = 'something_went_wrong';
$mini_game_ids = $string_constants = array();

if (role(['permissions' => ['mini_games' => 'manage']])) {

    if (isset($data['mini_game_id'])) {
        if (!is_array($data['mini_game_id'])) {
            $data["mini_game_id"] = filter_var($data["mini_game_id"], FILTER_SANITIZE_NUMBER_INT);
            $mini_game_ids[] = $data["mini_game_id"];
        } else {
            $mini_game_ids = array_filter($data["mini_game_id"], 'ctype_digit');
        }
    }

    if (!empty($mini_game_ids)) {

        DB::connect()->delete("mini_games", ["mini_game_id" => $mini_game_ids]);

        if (!DB::connect()->error) {
            $result = array();
            $result['success'] = true;
            $result['todo'] = 'reload';
            $result['reload'] = 'mini_games';
        } else {
            $result['error_message'] = Registry::load('strings')->went_wrong;
            $result['error_key'] = 'something_went_wrong';
        }
    }
}
?>