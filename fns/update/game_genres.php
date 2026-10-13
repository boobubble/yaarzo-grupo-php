<?php

$result = array();
$result['success'] = false;
$result['error_message'] = Registry::load('strings')->went_wrong;
$result['error_key'] = 'something_went_wrong';

if (role(['permissions' => ['mini_games' => 'manage']])) {

    if (!isset($data['name']) || empty($data['name'])) {
        $result['error_message'] = Registry::load('strings')->invalid_value;
        $result['error_key'] = 'invalid_value';
        $result['error_variables'] = ['name'];
    } else {

        if (isset($data["game_genre_id"]) && !empty($data['game_genre_id'])) {

            $game_genre_id = filter_var($data["game_genre_id"], FILTER_SANITIZE_NUMBER_INT);

            if (!empty($game_genre_id)) {
                $data['name'] = htmlspecialchars($data['name'], ENT_QUOTES, 'UTF-8');

                DB::connect()->update("game_genres", [
                    "genre_name" => $data['name'],
                    "updated_on" => Registry::load('current_user')->time_stamp,
                ], ["game_genre_id" => $game_genre_id]);

                $result = array();
                $result['success'] = true;
                $result['todo'] = 'reload';
                $result['reload'] = 'game_genres';
            }
        }
    }
}