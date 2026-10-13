<?php
if (role(['permissions' => ['mini_games' => 'play_games']])) {

    if (isset($data['mini_game_id'])) {

        $data["mini_game_id"] = filter_var($data["mini_game_id"], FILTER_SANITIZE_NUMBER_INT);

        if (!empty($data['mini_game_id'])) {
            $columns = [
                'mini_game_id', 'game_name', 'embed_code'
            ];

            $where["mini_game_id"] = $data["mini_game_id"];
            $where["LIMIT"] = 1;

            $mini_game = DB::connect()->select('mini_games', $columns, $where);

            if (isset($mini_game[0])) {
                $output = $mini_game[0];
            }
        }
    }
}
?>