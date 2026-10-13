<?php

if (role(['permissions' => ['mini_games' => 'manage']])) {

    $form = array();
    $form['loaded'] = new stdClass();
    $form['loaded']->title = Registry::load('strings')->mini_games;
    $form['loaded']->button = Registry::load('strings')->add;

    $form['fields'] = new stdClass();

    $mini_game_id = null;

    if (isset($load["mini_game_id"]) && !empty($load['mini_game_id'])) {

        $mini_game_id = filter_var($load["mini_game_id"], FILTER_SANITIZE_NUMBER_INT);

        $form['fields']->mini_game_id = [
            "tag" => 'input', "type" => 'hidden', "class" => 'd-none', "value" => $mini_game_id
        ];
        $form['fields']->update = [
            "tag" => 'input', "type" => 'hidden', "class" => 'd-none', "value" => "mini_games"
        ];
        $form['loaded']->button = Registry::load('strings')->update;
    } else {
        $form['fields']->add = [
            "tag" => 'input', "type" => 'hidden', "class" => 'd-none', "value" => "mini_games"
        ];
    }

    $form['fields']->name = [
        "title" => Registry::load('strings')->name, "tag" => 'input', "type" => "text", "class" => 'field',
        "placeholder" => Registry::load('strings')->name,
    ];

    $form['fields']->embed_code = [
        "title" => Registry::load('strings')->embed_code, "tag" => 'textarea', "class" => 'field',
        "placeholder" => Registry::load('strings')->embed_code, "attributes" => ["rows" => 10]
    ];
    
    
    $form['fields']->image = [
        "title" => Registry::load('strings')->image, "tag" => 'input', "type" => 'file', "class" => 'field filebrowse',
        "accept" => 'image/png,image/x-png,image/gif,image/jpeg'
    ];


    $form['fields']->genre_ids = [
        "title" => Registry::load('strings')->game_genres, "tag" => 'checkbox', "class" => 'field'
    ];

    $game_genres = DB::connect()->select('game_genres', ['game_genre_id', 'genre_name']);

    foreach ($game_genres as $game_genre) {
        $game_genre_id = $game_genre['game_genre_id'];
        $form['fields']->genre_ids['options'][$game_genre_id] = $game_genre['genre_name'];
    }

    if (!empty($mini_game_id)) {
        $mini_game = DB::connect()->select("mini_games", ["game_name", "game_genre_ids", "embed_code"], ["mini_game_id" => $mini_game_id]);

        if (isset($mini_game[0])) {
            $mini_game = $mini_game[0];
            $form['fields']->name['value'] = $mini_game['game_name'];
            $form['fields']->genre_ids['value'] = $mini_game['game_genre_ids'];
            $form['fields']->embed_code['value'] = $mini_game['embed_code'];
        }
    }

}

?>