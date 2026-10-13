<?php

include_once('fns/url_highlight/load.php');

use VStelmakh\UrlHighlight\UrlHighlight;
use VStelmakh\UrlHighlight\Validator\Validator;
use Snipe\BanBuilder\CensorWords;

$openrouter_api_key = Registry::load('settings')->openrouter_api_key;
$openrouter_model_id = Registry::load('settings')->openrouter_model_id;

if (isset($ai_chat_msg['ai_model']) && !empty($ai_chat_msg['ai_model'])) {
    $openrouter_model_id = $ai_chat_msg['ai_model'];
}

$body = [
    "model" => $openrouter_model_id,
    "messages" => $messages
];

$ch = curl_init('https://openrouter.ai/api/v1/chat/completions');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Content-Type: application/json",
    "Authorization: Bearer $openrouter_api_key",
]);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));

$response = curl_exec($ch);


$response = json_decode($response, true);
$error_message = $response['error']['message'] ?? '';
$recieved_message = $response['choices'][0]['message']['content'] ?? '';
$attachment_type = null;
$attachment = '';

if (!empty($error_message)) {
    echo $error_message;
}

if (!empty($recieved_message)) {
    include_once('fns/filters/load.php');
    include_once('fns/HTMLPurifier/load.php');

    $recieved_message = convertMarkdownToHTML($recieved_message);
    $customURLHighlighter = new CustomURLHighlighter();
    $convert_email_addresses = true;
    $url_validator = new Validator(true, [], [], $convert_email_addresses);
    $urlHighlight = new UrlHighlight($url_validator, $customURLHighlighter);

    if (!empty($recieved_message)) {

        $recieved_message = $urlHighlight->highlightUrls($recieved_message);

        $links = $urlHighlight->getUrls($recieved_message);
        if (isset($links[0])) {
            include_once('fns/url_metadata/load.php');
            $url_meta_data = url_metadata($links[0]);
            if ($url_meta_data['success']) {
                unset($url_meta_data['success']);
                $attachment_type = 'url_meta';
                $attachment = json_encode($url_meta_data);
            }
        }

        $allowed_tags = 'p,span[class],';
        $allowed_tags .= 'a[href],br';

        if (Registry::load('settings')->message_text_formatting !== 'disable') {
            $allowed_tags .= ',b,em,i,u,strong,s,ol,ul,li';
        }

        $config = HTMLPurifier_Config::createDefault();
        $config->set('HTML.Allowed', $allowed_tags);
        $config->set('Attr.AllowedClasses', array());
        $config->set('HTML.Nofollow', true);
        $config->set('HTML.TargetBlank', true);
        $config->set('AutoFormat.RemoveEmpty', true);

        $define = $config->getHTMLDefinition(true);
        $define->addAttribute('span', 'class', new CustomClassDef(array('emoji_icon'), array('emoji-')));

        $purifier = new HTMLPurifier($config);

        $recieved_message = $purifier->purify(trim($recieved_message));

        if (!empty($recieved_message) && !empty($ai_chat_msg['private_conversation_id'])) {
            DB::connect()->insert("private_chat_messages", [
                "original_message" => $recieved_message,
                "filtered_message" => $recieved_message,
                "private_conversation_id" => $ai_chat_msg['private_conversation_id'],
                "attachment_type" => $attachment_type,
                "attachments" => $attachment,
                "user_id" => $ai_chat_msg['user_id'],
                "created_on" => Registry::load('current_user')->time_stamp,
                "updated_on" => Registry::load('current_user')->time_stamp,
            ]);

            if (!DB::connect()->error) {

                DB::connect()->update("private_chat_messages", ["read_status" => 1], [
                    'private_conversation_id' => $ai_chat_msg['private_conversation_id'],
                    'user_id[!]' => $ai_chat_msg['user_id']
                ]);

                ws_push(['update' => 'new_private_chat_message', 'receiver_id' => $receiver_id, 'sender_id' => $ai_chat_msg['user_id']]);

            }
        } else if (!empty($recieved_message) && !empty($ai_chat_msg['group_id'])) {
            DB::connect()->insert("group_messages", [
                "original_message" => $recieved_message,
                "filtered_message" => $recieved_message,
                "attachment_type" => $attachment_type,
                "attachments" => $attachment,
                "group_id" => $ai_chat_msg['group_id'],
                "user_id" => $ai_chat_msg['user_id'],
                "created_on" => Registry::load('current_user')->time_stamp,
                "updated_on" => Registry::load('current_user')->time_stamp,
            ]);

            if (!DB::connect()->error) {
                ws_push(['update' => 'new_group_message', 'group_id' => $ai_chat_msg['group_id']]);
            }
        }
    }
}