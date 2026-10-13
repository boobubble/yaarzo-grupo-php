<?php

if (role(['permissions' => ['mini_games' => 'manage']])) {


    $columns = [
        'game_genre_id', 'genre_name'
    ];

    if (!empty($data["offset"])) {
        $data["offset"] = array_map('intval', explode(',', $data["offset"]));
        $where["game_genre_id[!]"] = $data["offset"];
    }

    if (!empty($data["search"])) {
        $where["AND #search_query"] = ["genre_name[~]" => $data["search"]];
    }


    $where["LIMIT"] = Registry::load('settings')->records_per_call;
    $where["ORDER"] = [
        "game_genre_id" => "DESC",
    ];

    $game_genres = DB::connect()->select('game_genres', $columns, $where);

    $i = 1;
    $output = array();
    $output['loaded'] = new stdClass();
    $output['loaded']->title = Registry::load('strings')->game_genres;
    $output['loaded']->loaded = 'game_genres';
    $output['loaded']->offset = array();

    if (!empty($data["offset"])) {
        $output['loaded']->offset = $data["offset"];
    }


    $output['multiple_select'] = new stdClass();
    $output['multiple_select']->title = Registry::load('strings')->delete;
    $output['multiple_select']->attributes['class'] = 'ask_confirmation';
    $output['multiple_select']->attributes['data-remove'] = 'game_genres';
    $output['multiple_select']->attributes['multi_select'] = 'game_genre_id';
    $output['multiple_select']->attributes['submit_button'] = Registry::load('strings')->yes;
    $output['multiple_select']->attributes['cancel_button'] = Registry::load('strings')->no;
    $output['multiple_select']->attributes['confirmation'] = Registry::load('strings')->confirm_action;

    $output['todo'] = new stdClass();
    $output['todo']->class = 'load_form';
    $output['todo']->title = Registry::load('strings')->add;
    $output['todo']->attributes['form'] = 'game_genres';


    foreach ($game_genres as $game_genre) {
        $output['loaded']->offset[] = $game_genre['game_genre_id'];

        $output['content'][$i] = new stdClass();
        $output['content'][$i]->alphaicon = true;
        $output['content'][$i]->title = $game_genre['genre_name'];
        $output['content'][$i]->identifier = $game_genre['game_genre_id'];
        $output['content'][$i]->class = "game_name square";
        $output['content'][$i]->icon = 0;
        $output['content'][$i]->unread = 0;


        $output['content'][$i]->subtitle = Registry::load('strings')->game_genres;

        $output['options'][$i][1] = new stdClass();
        $output['options'][$i][1]->option = Registry::load('strings')->edit;
        $output['options'][$i][1]->class = 'load_form';
        $output['options'][$i][1]->attributes['form'] = 'game_genres';
        $output['options'][$i][1]->attributes['data-game_genre_id'] = $game_genre['game_genre_id'];


        $output['options'][$i][3] = new stdClass();
        $output['options'][$i][3]->option = Registry::load('strings')->delete;
        $output['options'][$i][3]->class = 'ask_confirmation';
        $output['options'][$i][3]->attributes['data-info_box'] = true;
        $output['options'][$i][3]->attributes['data-remove'] = 'game_genres';
        $output['options'][$i][3]->attributes['data-game_genre_id'] = $game_genre['game_genre_id'];
        $output['options'][$i][3]->attributes['confirmation'] = Registry::load('strings')->confirm_action;
        $output['options'][$i][3]->attributes['submit_button'] = Registry::load('strings')->yes;
        $output['options'][$i][3]->attributes['cancel_button'] = Registry::load('strings')->no;


        $i++;
    }
}
?>