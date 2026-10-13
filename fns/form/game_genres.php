<?php

if (role(['permissions' => ['mini_games' => 'manage']])) {

    $form = array();
    $form['loaded'] = new stdClass();
    $form['loaded']->title = Registry::load('strings')->game_genres;
    $form['loaded']->button = Registry::load('strings')->add;

    $form['fields'] = new stdClass();

    $game_genre_id = null;

    if (isset($load["game_genre_id"]) && !empty($load['game_genre_id'])) {

        $game_genre_id = filter_var($load["game_genre_id"], FILTER_SANITIZE_NUMBER_INT);

        $form['fields']->game_genre_id = [
            "tag" => 'input', "type" => 'hidden', "class" => 'd-none', "value" => $game_genre_id
        ];
        $form['fields']->update = [
            "tag" => 'input', "type" => 'hidden', "class" => 'd-none', "value" => "game_genres"
        ];
        $form['loaded']->button = Registry::load('strings')->update;
    } else {
        $form['fields']->add = [
            "tag" => 'input', "type" => 'hidden', "class" => 'd-none', "value" => "game_genres"
        ];
    }

    $form['fields']->name = [
        "title" => Registry::load('strings')->name, "tag" => 'input', "type" => "text", "class" => 'field',
        "placeholder" => Registry::load('strings')->game_genres,
    ];

    if (!empty($game_genre_id)) {
        $game_genre = DB::connect()->select("game_genres", ["genre_name"], ["game_genre_id" => $game_genre_id]);

        if (isset($game_genre[0])) {
            $game_genre = $game_genre[0];
            $form['fields']->name['value'] = $game_genre['genre_name'];
        }
    }

}

?>