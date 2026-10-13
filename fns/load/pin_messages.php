<?php

if (isset($data["group_id"])) {
    $data["group_id"] = filter_var($data["group_id"], FILTER_SANITIZE_NUMBER_INT);
}

$output = array();
$output['unpin_messages'] = false;

$storage_public_url = Registry::load('config')->site_url;

if (Registry::load('settings')->cloud_storage !== 'disable') {
    if (!empty(Registry::load('settings')->cloud_storage_public_url)) {
        $storage_public_url = Registry::load('settings')->cloud_storage_public_url;
    }
}

if (isset($data["group_id"]) && !empty($data["group_id"])) {


    $columns = $join = $where = null;
    $columns = [
        'groups.name(group_name)', 'group_roles.group_role_attribute',
        'group_members.group_role_id'
    ];

    $join["[>]group_members"] = ["groups.group_id" => "group_id", "AND" => ["user_id" => Registry::load('current_user')->id]];
    $join["[>]group_roles"] = ["group_members.group_role_id" => "group_role_id"];

    $where["groups.group_id"] = $data["group_id"];
    $where["LIMIT"] = 1;

    $group_info = DB::connect()->select('groups', $join, $columns, $where);

    if (isset($group_info[0])) {
        $group_info = $group_info[0];
    } else {
        return;
    }

    $super_privileges = false;

    if (role(['permissions' => ['groups' => 'super_privileges']])) {
        $super_privileges = true;
    }

    if ($super_privileges || role(['permissions' => ['messages' => 'pin_messages'], 'group_role_id' => $group_info['group_role_id']])) {
        $output['unpin_messages'] = true;
    }

    if ($super_privileges || isset($group_info['group_role_id']) && !empty($group_info['group_role_id'])) {

        if ($super_privileges || isset($group_info['group_role_attribute']) && $group_info['group_role_attribute'] !== 'banned_users') {

            $columns = $join = $where = null;
            $columns = [
                'group_messages.group_message_id', 'group_messages.attachments', 'group_messages.attachment_type',
                'group_messages.filtered_message', 'site_users.display_name'
            ];

            $join["[>]site_users"] = ["group_messages.user_id" => "user_id"];

            $where["group_messages.group_id"] = $data["group_id"];
            $where["group_messages.pin_message"] = 1;


            $where["ORDER"] = ['group_messages.group_message_id' => 'DESC'];
            $where["LIMIT"] = 20;


            $pin_messages = DB::connect()->select('group_messages', $join, $columns, $where);

            $index = 0;


            foreach ($pin_messages as $pin_message) {

                $output['messages'][$index] = new stdClass();
                $output['messages'][$index]->user = $pin_message['display_name'];
                $output['messages'][$index]->content = Registry::load('strings')->attachments;
                $output['messages'][$index]->message_id = $pin_message['group_message_id'];

                if (!empty($pin_message['attachments'])) {
                    $attachments = json_decode($pin_message['attachments']);

                    if ($pin_message['attachment_type'] === 'sticker') {
                        $output['messages'][$index]->image = Registry::load('config')->site_url.$attachments->sticker;
                        $output['messages'][$index]->attributes = [
                            'class' => 'preview_image',
                            'load_image' => Registry::load('config')->site_url.$attachments->sticker
                        ];

                    } else if ($pin_message['attachment_type'] === 'gif') {
                        $output['messages'][$index]->image = $attachments->gif_url;
                        $output['messages'][$index]->attributes = [
                            'class' => 'preview_image',
                            'load_image' => $attachments->gif_url
                        ];

                    } else {
                        foreach ($attachments as $attachment) {
                            if ($pin_message['attachment_type'] === 'image_files' && isset($attachment->thumbnail)) {
                                $output['messages'][$index]->image = Registry::load('config')->site_url.'assets/files/defaults/image_thumb.jpg';

                                if (file_exists($attachment->thumbnail) || Registry::load('settings')->cloud_storage !== 'disable') {
                                    $output['messages'][$index]->image = $storage_public_url.$attachment->thumbnail;
                                }

                                $output['messages'][$index]->attributes = [
                                    'class' => 'preview_image',
                                    'load_image' => $storage_public_url.$attachment->file,
                                ];

                            } else if ($pin_message['attachment_type'] === 'video_files' && isset($attachment->file)) {

                                $output['messages'][$index]->image = Registry::load('config')->site_url.'assets/files/defaults/video_thumb.jpg';

                                if (isset($attachment->thumbnail) && file_exists($attachment->thumbnail) || isset($attachment->thumbnail) && Registry::load('settings')->cloud_storage !== 'disable') {
                                    $output['messages'][$index]->image = $storage_public_url.$attachment->thumbnail;
                                }

                                $output['messages'][$index]->attributes = [
                                    'class' => 'preview_video',
                                    'mime_type' => $attachment->file_type,
                                    'thumbnail' => $output['messages'][$index]->image,
                                    'video_file' => $storage_public_url.$attachment->file,
                                ];


                            } else if ($pin_message['attachment_type'] === 'audio_files') {

                                $output['messages'][$index]->image = Registry::load('config')->site_url.'assets/files/defaults/audio_thumb.jpg';

                                $output['messages'][$index]->attributes = [
                                    'class' => 'preview_video',
                                    'thumbnail' => $output['messages'][$index]->image,
                                    'mime_type' => $attachment->file_type,
                                    'video_file' => $storage_public_url.$attachment->file,
                                ];

                            }
                        }

                    }
                }

                if (!empty($pin_message['filtered_message'])) {
                    $output['messages'][$index]->content = $pin_message['filtered_message'];
                }

                $index++;
            }
        }
    }
}