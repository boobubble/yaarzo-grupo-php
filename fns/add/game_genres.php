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
        $data['name'] = htmlspecialchars($data['name'], ENT_QUOTES, 'UTF-8');

        DB::connect()->insert("game_genres", [
            "genre_name" => $data['name'],
            "updated_on" => Registry::load('current_user')->time_stamp,
        ]);
        
        $result = array();
        $result['success'] = true;
        $result['todo'] = 'reload';
        $result['reload'] = 'game_genres';
    }
}