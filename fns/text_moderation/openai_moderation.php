<?php
$settings = Registry::load('settings');

if (!empty($settings->openai_moderation_api_key)) {

    $skip_text = false;
    $reason_for_removal = '';

    $apiKey = $settings->openai_moderation_api_key;

    $requestData = [
        "model" => "omni-moderation-latest",
        "input" => $text
    ];


    $jsonData = json_encode($requestData);

    $ch = curl_init("https://api.openai.com/v1/moderations");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey
    ]);

    $response = curl_exec($ch);

    $response = json_decode($response, true);


    if (!empty($response['results'][0]['categories'])) {

        $categories = $response['results'][0]['categories'];

        if (!empty($settings->openai_text_removal_criteria)) {

            foreach ($settings->openai_text_removal_criteria as $value) {

                $openai_key = str_replace('self_harm', 'self-harm', $value);
                $openai_key = str_replace('_', '/', $openai_key);

                if (!empty($categories[$openai_key])) {
                    $skip_text = true;
                    $reason_for_removal = strtoupper(str_replace('_', ' ', $value));
                    break;
                }
            }
        }
    }

    if ($skip_text) {
        $result = [
            'success' => false,
            'reason' => $reason_for_removal
        ];
    }
}