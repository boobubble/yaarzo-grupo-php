<?php

$result = array();
$result['success'] = false;
$result['error_message'] = Registry::load('strings')->went_wrong;
$result['error_key'] = 'something_went_wrong';
$result['error_variables'] = [];

$no_error = true;

if (role(['permissions' => ['mini_games' => 'manage']])) {

    if (!isset($data['name']) || empty($data['name'])) {
        $result['error_message'] = Registry::load('strings')->invalid_value;
        $result['error_key'] = 'invalid_value';
        $result['error_variables'][] = 'name';
        $no_error = false;
    }

    if (!isset($data['embed_code']) || empty($data['embed_code'])) {
        $result['error_message'] = Registry::load('strings')->invalid_value;
        $result['error_key'] = 'invalid_value';
        $result['error_variables'][] = 'embed_code';
        $no_error = false;
    }

    if (!isset($data['genre_ids']) || empty($data['genre_ids'])) {
        $result['error_message'] = Registry::load('strings')->invalid_value;
        $result['error_key'] = 'invalid_value';
        $result['error_variables'][] = 'genre_ids';
        $no_error = false;
    }

    if (isset($data["mini_game_id"]) && !empty($data['mini_game_id'])) {

        $mini_game_id = filter_var($data["mini_game_id"], FILTER_SANITIZE_NUMBER_INT);

        if (empty($mini_game_id)) {
            $no_error = false;
        }
    }


    if ($no_error) {
        $data['name'] = htmlspecialchars($data['name'], ENT_QUOTES, 'UTF-8');
        $data['genre_ids'] = array_filter($data['genre_ids'], 'is_numeric');
        $data["genre_ids"] = json_encode($data['genre_ids']);

        if (filter_var($data["embed_code"], FILTER_VALIDATE_URL)) {
            $data["embed_code"] = '<iframe src="'.$data["embed_code"].'" seamless="seamless" allowtransparency="true" allowfullscreen="true" frameborder="0" style="width: 100%;height: 100%;border: 0px;"></iframe>';
        }

        DB::connect()->update("mini_games", [
            "game_name" => $data['name'],
            "game_genre_ids" => $data["genre_ids"],
            "embed_code" => $data["embed_code"],
            "updated_on" => Registry::load('current_user')->time_stamp,
        ], ['mini_game_id' => $mini_game_id]);

        if (isset($_FILES['image']['name']) && !empty($_FILES['image']['name'])) {

            include 'fns/filters/load.php';
            include 'fns/files/load.php';

            if (isImage($_FILES['image']['tmp_name'])) {

                foreach (glob("assets/files/mini_games/".$mini_game_id.Registry::load('config')->file_seperator."*.*") as $old_image) {
                    unlink($old_image);
                }

                $extension = pathinfo($_FILES['image']['name'])['extension'];
                $filename = $mini_game_id.Registry::load('config')->file_seperator.random_string(['length' => 6]).'.'.$extension;

                if (files('upload', ['upload' => 'image', 'folder' => 'mini_games', 'saveas' => $filename])['result']) {
                    files('resize_img', ['resize' => 'mini_games/'.$filename, 'width' => 150, 'height' => 150, 'crop' => true]);
                }
            }
        }

        $result = array();
        $result['success'] = true;
        $result['todo'] = 'reload';
        $result['reload'] = 'mini_games';
    }
}