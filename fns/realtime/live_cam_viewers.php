<?php
use Medoo\Medoo;


$last_lc_viewer_id = filter_var($data["last_lc_viewer_id"] ?? 0, FILTER_SANITIZE_NUMBER_INT);
$total_lc_viewers = filter_var($data["total_lc_viewers"] ?? 0, FILTER_SANITIZE_NUMBER_INT);

$group_id = filter_var($data["live_cam_viewers"] ?? 0, FILTER_SANITIZE_NUMBER_INT);

if (!empty($group_id)) {

    $group_member_id = DB::connect()->select('group_members', ['group_member_id'], ['group_id' => $group_id, 'user_id' => Registry::load('current_user')->id]);

    if (isset($group_member_id[0])) {
        $group_member_id = $group_member_id[0]['group_member_id'];

        $db_result = DB::connect()->get("live_cam_visitors", [
            "total_lc_viewers" => Medoo::raw("COUNT(<lc_viewer_id>)"),
            "last_lc_viewer_id" => Medoo::raw("MAX(<user_id>)")
        ], [
            "group_member_id" => $group_member_id,
            "currently_viewing" => 1,
            "current_status" => 1,
        ]);

        if ($db_result) {
            $total = (int)$db_result['total_lc_viewers'];
            $last_id = (int)$db_result['last_lc_viewer_id'];

            if ($total !== (int)$total_lc_viewers || $last_id !== (int)$last_lc_viewer_id) {

                $result['total_lc_viewers'] = $total;
                $result['last_lc_viewer_id'] = $last_id;
                $result['live_cam_viewers'] = $group_id;

                $escape = true;
            }
        }

    }
}