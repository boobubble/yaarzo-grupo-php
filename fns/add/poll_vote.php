<?php
use Medoo\Medoo;

$result = array();
$result['success'] = false;
$result['error_message'] = Registry::load('strings')->went_wrong;
$result['error_key'] = 'something_went_wrong';
$poll_id = $option_id = 0;
$user_id = Registry::load('current_user')->id;

if (isset($data['poll_id'])) {
    $poll_id = filter_var($data["poll_id"], FILTER_SANITIZE_NUMBER_INT);
}

if (isset($data['option_id'])) {
    $option_id = filter_var($data["option_id"], FILTER_SANITIZE_NUMBER_INT);
}


if (!empty($poll_id) && !empty($option_id)) {

    $columns = $join = $where = null;

    $join["[>]group_members"] = ["polls.group_id" => "group_id", "AND" => ["group_members.user_id" => $user_id]];
    $join["[>]poll_options"] = ["polls.poll_id" => "poll_id", "AND" => ["poll_options.poll_option_id" => $option_id]];


    $where["polls.poll_id"] = $poll_id;
    $where["LIMIT"] = 1;

    $poll = DB::connect()->select('polls', $join, ["polls.poll_id", "group_members.group_role_id", "poll_options.poll_option_id"], $where);

    if (isset($poll[0])) {
        $poll = $poll[0];

        $cast_vote = false;

        if (role(['permissions' => ['groups' => 'cast_vote']])) {
            if (isset($poll['group_role_id']) && !empty($poll['group_role_id'])) {
                if (role(['permissions' => ['messages' => 'cast_vote'], 'group_role_id' => $poll['group_role_id']])) {
                    $cast_vote = true;
                }
            }
        }

        if ($cast_vote) {
            if (!empty($poll['poll_option_id'])) {
                DB::connect()->delete('poll_votes', ["poll_id" => $poll_id, "user_id" => $user_id]);
                DB::connect()->insert('poll_votes', ["poll_id" => $poll_id, "user_id" => $user_id, "poll_option_id" => $option_id]);


                $columns = $join = $where = null;

                $votes = DB::connect()->select(
                    'poll_options',
                    [
                        "[>]poll_votes" => ["poll_option_id" => "poll_option_id"]
                    ],
                    [
                        "poll_options.poll_option_id",
                        "votes" => Medoo::raw("COUNT(<poll_votes.poll_vote_id>)")
                    ],
                    [
                        "poll_options.poll_id" => $poll_id,
                        "GROUP" => "poll_options.poll_option_id"
                    ]
                );

                foreach ($votes as $vote) {
                    DB::connect()->update('poll_options', ["vote_count" => $vote['votes']], ["poll_option_id" => $vote['poll_option_id']]);
                }

                $result = array();
                $result['success'] = true;

            }
        }
    }

}