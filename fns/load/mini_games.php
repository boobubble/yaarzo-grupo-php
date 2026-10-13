<?php

if (role(['permissions' => ['mini_games' => ['play_games', 'manage']], 'condition' => 'OR'])) {


    $columns = [
        'mini_game_id', 'game_name', 'game_genre_ids'
    ];

    if (!empty($data["offset"])) {
        $data["offset"] = array_map('intval', explode(',', $data["offset"]));
        $where["mini_game_id[!]"] = $data["offset"];
    }

    if (!empty($data["search"])) {
        $where["AND #search_query"] = ["game_name[~]" => $data["search"]];
    }

    if (isset($data["game_genre_id"])) {
        $game_genre_id = filter_var($data["game_genre_id"], FILTER_SANITIZE_NUMBER_INT);

        if (!empty($game_genre_id)) {
            $where["AND #filter_genre"] = ["game_genre_ids[~]" => $game_genre_id];
        }
    }

    $where["LIMIT"] = Registry::load('settings')->records_per_call;
    $where["ORDER"] = [
        "mini_game_id" => "DESC",
    ];

    $mini_games = DB::connect()->select('mini_games', $columns, $where);

    $i = 1;
    $output = array();
    $output['loaded'] = new stdClass();
    $output['loaded']->title = Registry::load('strings')->mini_games;
    $output['loaded']->loaded = 'mini_games';
    $output['loaded']->offset = array();

    if (!empty($data["offset"])) {
        $output['loaded']->offset = $data["offset"];
    }

    $game_genres = DB::connect()->select('game_genres', ['game_genre_id', 'genre_name']);

    $filter_option = 1;

    $output['filters'][$filter_option] = new stdClass();
    $output['filters'][$filter_option]->filter = Registry::load('strings')->all;
    $output['filters'][$filter_option]->class = 'load_aside';
    $output['filters'][$filter_option]->attributes['load'] = 'mini_games';
    $filter_option++;

    foreach ($game_genres as $game_genre) {
        $output['filters'][$filter_option] = new stdClass();
        $output['filters'][$filter_option]->filter = $game_genre['genre_name'];
        $output['filters'][$filter_option]->class = 'load_aside';
        $output['filters'][$filter_option]->attributes['load'] = 'mini_games';
        $output['filters'][$filter_option]->attributes['data-game_genre_id'] = $game_genre['game_genre_id'];
        $filter_option++;
    }

    $output['multiple_select'] = new stdClass();
    $output['multiple_select']->title = Registry::load('strings')->delete;
    $output['multiple_select']->attributes['class'] = 'ask_confirmation';
    $output['multiple_select']->attributes['data-remove'] = 'mini_games';
    $output['multiple_select']->attributes['multi_select'] = 'mini_game_id';
    $output['multiple_select']->attributes['submit_button'] = Registry::load('strings')->yes;
    $output['multiple_select']->attributes['cancel_button'] = Registry::load('strings')->no;
    $output['multiple_select']->attributes['confirmation'] = Registry::load('strings')->confirm_action;


    if (role(['permissions' => ['mini_games' => 'manage']])) {
        $output['todo'] = new stdClass();
        $output['todo']->class = 'load_form';
        $output['todo']->title = Registry::load('strings')->add;
        $output['todo']->attributes['form'] = 'mini_games';
    }


    foreach ($mini_games as $mini_game) {
        $output['loaded']->offset[] = $mini_game['mini_game_id'];

        $output['content'][$i] = new stdClass();
        $output['content'][$i]->image = get_image(['from' => 'mini_games', 'search' => $mini_game['mini_game_id']]);
        $output['content'][$i]->title = $mini_game['game_name'];
        $output['content'][$i]->identifier = $mini_game['mini_game_id'];
        $output['content'][$i]->class = "game_name square";
        $output['content'][$i]->icon = 0;
        $output['content'][$i]->unread = 0;


        $output['content'][$i]->subtitle = Registry::load('strings')->mini_games;

        $output['options'][$i][1] = new stdClass();
        $output['options'][$i][1]->option = Registry::load('strings')->play_game;
        $output['options'][$i][1]->class = 'open_mini_game';
        $output['options'][$i][1]->attributes['mini_game_id'] = $mini_game['mini_game_id'];


        if (role(['permissions' => ['mini_games' => 'manage']])) {
            $output['options'][$i][2] = new stdClass();
            $output['options'][$i][2]->option = Registry::load('strings')->edit;
            $output['options'][$i][2]->class = 'load_form';
            $output['options'][$i][2]->attributes['form'] = 'mini_games';
            $output['options'][$i][2]->attributes['data-mini_game_id'] = $mini_game['mini_game_id'];


            $output['options'][$i][3] = new stdClass();
            $output['options'][$i][3]->option = Registry::load('strings')->delete;
            $output['options'][$i][3]->class = 'ask_confirmation';
            $output['options'][$i][3]->attributes['data-info_box'] = true;
            $output['options'][$i][3]->attributes['data-remove'] = 'mini_games';
            $output['options'][$i][3]->attributes['data-mini_game_id'] = $mini_game['mini_game_id'];
            $output['options'][$i][3]->attributes['confirmation'] = Registry::load('strings')->confirm_action;
            $output['options'][$i][3]->attributes['submit_button'] = Registry::load('strings')->yes;
            $output['options'][$i][3]->attributes['cancel_button'] = Registry::load('strings')->no;
        }


        $i++;
    }
}
?>