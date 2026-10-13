<?php
$result = array();
$noerror = true;

$result['success'] = false;
$result['error_message'] = Registry::load('strings')->went_wrong;
$result['error_key'] = 'something_went_wrong';
$game_genre_ids = $string_constants = array();

if (role(['permissions' => ['mini_games' => 'manage']])) {

    if (isset($data['game_genre_id'])) {
        if (!is_array($data['game_genre_id'])) {
            $data["game_genre_id"] = filter_var($data["game_genre_id"], FILTER_SANITIZE_NUMBER_INT);
            $game_genre_ids[] = $data["game_genre_id"];
        } else {
            $game_genre_ids = array_filter($data["game_genre_id"], 'ctype_digit');
        }
    }

    if (!empty($game_genre_ids)) {

        DB::connect()->delete("game_genres", ["game_genre_id" => $game_genre_ids]);

        if (!DB::connect()->error) {
            $result = array();
            $result['success'] = true;
            $result['todo'] = 'reload';
            $result['reload'] = 'game_genres';
        } else {
            $result['error_message'] = Registry::load('strings')->went_wrong;
            $result['error_key'] = 'something_went_wrong';
        }
    }
}
?>