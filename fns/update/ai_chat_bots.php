<?php

$result = array();
$result['success'] = false;
$result['error_message'] = Registry::load('strings')->went_wrong;
$result['error_key'] = 'something_went_wrong';

if (role(['permissions' => ['super_privileges' => 'core_settings']])) {

    $noerror = true;
    $ai_chat_bot_id = $user_id = 0;

    $result['success'] = false;
    $result['error_message'] = Registry::load('strings')->invalid_value;
    $result['error_key'] = 'invalid_value';
    $result['error_variables'] = [];

    if (!isset($data['username']) || empty($data['username'])) {
        $result['error_variables'][] = ['username'];
        $noerror = false;
    }
    if (!isset($data['description']) || empty($data['description'])) {
        $result['error_variables'][] = ['description'];
        $noerror = false;
    }

    if (isset($data['ai_chat_bot_id'])) {
        $ai_chat_bot_id = filter_var($data["ai_chat_bot_id"], FILTER_SANITIZE_NUMBER_INT);
    }

    if (isset($data['username']) && !empty($data['username'])) {
        $result['error_message'] = Registry::load('strings')->invalid_value;
        $result['error_key'] = 'invalid_value';
        $result['error_variables'][] = ['username'];
        $noerror = false;

        $site_user = DB::connect()->select('site_users', ['user_id'], ['username' => $data['username'], 'LIMIT' => 1]);

        if (isset($site_user[0])) {
            $noerror = true;
            $user_id = $site_user[0]['user_id'];
        } else {
            $result['error_message'] = Registry::load('strings')->invalid_username;
            $result['error_key'] = 'invalid_username';
        }
    }

    if ($noerror && !empty($ai_chat_bot_id)) {

        $ai_model = null;

        if (isset($data['use_different_model']) && $data['use_different_model'] === 'yes') {
            if (isset($data['ai_model'])) {
                $ai_model = htmlspecialchars(trim($data['ai_model']), ENT_QUOTES, 'UTF-8');
            }
        }

        $data['description'] = htmlspecialchars(trim($data['description']), ENT_QUOTES, 'UTF-8');

        DB::connect()->update("ai_chat_bots", [
            "description" => $data['description'],
            'ai_model' => $ai_model,
            "user_id" => $user_id,
            "updated_on" => Registry::load('current_user')->time_stamp,
        ], ["ai_chat_bot_id" => $ai_chat_bot_id]);

        if (!DB::connect()->error) {

            if (isset($data['validate_bot']) && $data['validate_bot'] === 'yes') {

                $openrouter_api_key = Registry::load('settings')->openrouter_api_key;
                $openrouter_model_id = Registry::load('settings')->openrouter_model_id;

                if (!empty($ai_model)) {
                    $openrouter_model_id = $ai_model;
                }

                $ch = curl_init('https://openrouter.ai/api/v1/chat/completions');
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_HTTPHEADER => [
                        "Content-Type: application/json",
                        "Authorization: Bearer {$openrouter_api_key}"
                    ],
                    CURLOPT_POST => true,
                    CURLOPT_POSTFIELDS => json_encode([
                        "model" => $openrouter_model_id,
                        "messages" => [['role' => 'user', 'content' => 'test message']]
                    ])
                ]);

                $res = json_decode(curl_exec($ch), true);

                $display_message = $res['error']['message'] ?? 'Validated Successfully';

                $result['display_message'] = $display_message;
                $result['error_variables'] = [];
            } else {
                $result = array();
                $result['success'] = true;
                $result['todo'] = 'reload';
                $result['reload'] = 'ai_chat_bots';
            }
        } else {
            $result['error_message'] = Registry::load('strings')->went_wrong;
            $result['error_key'] = 'something_went_wrong';
        }

    }
}

?>