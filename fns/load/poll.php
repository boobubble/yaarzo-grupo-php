<?php

$poll_id = null;
$output = array();

if (isset($data['poll_id'])) {
    $poll_id = filter_var($data['poll_id'], FILTER_SANITIZE_NUMBER_INT);
}

if (!empty($poll_id)) {
    $columns = $where = $join = null;

    $columns = [
        'poll_options.option_text', 'poll_options.vote_count', 'poll_options.poll_option_id',
        'polls.question', 'polls.group_id', 'poll_votes.poll_vote_id', 'poll_votes.poll_option_id(voted_option_id)',
        "group_members.group_role_id"
    ];
    $where["poll_options.poll_id"] = $poll_id;
    $where["ORDER"] = ["poll_options.poll_option_id" => "ASC"];

    $join["[>]polls"] = ["poll_options.poll_id" => "poll_id"];
    $join["[>]group_members"] = ["polls.group_id" => "group_id", "AND" => ["group_members.user_id" => Registry::load('current_user')->id]];
    $join["[>]poll_votes"] = ["poll_options.poll_id" => "poll_id", "AND" => ["poll_votes.user_id" => Registry::load('current_user')->id]];
    $poll_options = DB::connect()->select('poll_options', $join, $columns, $where);

    $total_votes = 0;

    if (isset($poll_options[0])) {

        foreach ($poll_options as $option) {
            $total_votes += (int)$option['vote_count'];
        }

        $output['question'] = $poll_options[0]['question'];
        $output['poll_id'] = $poll_id;
        $cast_vote = false;

        if (role(['permissions' => ['groups' => 'cast_vote']])) {
            if (isset($poll_options[0]['group_role_id']) && !empty($poll_options[0]['group_role_id'])) {
                if (role(['permissions' => ['messages' => 'cast_vote'], 'group_role_id' => $poll_options[0]['group_role_id']])) {
                    $cast_vote = true;
                }
            }
        }

        if ($cast_vote) {
            $output['vote_btn'] = Registry::load('strings')->submit_vote;
            $output['results_btn'] = Registry::load('strings')->view_results;
            $output['view_options_btn'] = Registry::load('strings')->view_options;
        } else {
            $output['load_result'] = true;
        }

        $output['refresh_btn'] = Registry::load('strings')->refresh;

        if (!empty($poll_options[0]['poll_vote_id'])) {
            $output['load_result'] = true;
            $output['voted_option_id'] = $poll_options[0]['voted_option_id'];
        }

        foreach ($poll_options as $poll_option) {
            $poll_option_id = $poll_option['poll_option_id'];
            $vote_count = 0;

            if (!empty($total_votes)) {
                $vote_count = ((int)$poll_option['vote_count'] / $total_votes) * 100;
                $vote_count = (int)$vote_count;
            }

            $output['poll_options'][$poll_option_id]['option_text'] = $poll_option['option_text'];
            $output['poll_options'][$poll_option_id]['vote_count'] = $vote_count.'%';
        }
    }
}