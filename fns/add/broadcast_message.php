<?php

include 'fns/filters/load.php';
include('fns/url_highlight/load.php');

use VStelmakh\UrlHighlight\UrlHighlight;
use VStelmakh\UrlHighlight\Validator\Validator;

$customURLHighlighter = new CustomURLHighlighter();
$convert_email_addresses = true;
$url_validator = new Validator(true, [], [], $convert_email_addresses);
$urlHighlight = new UrlHighlight($url_validator, $customURLHighlighter);

$result = array();
$result['success'] = false;
$result['error_message'] = Registry::load('strings')->went_wrong;
$result['error_key'] = 'something_went_wrong';
$noerror = true;
$site_role_ids = $attachment_type = null;
$attachments = '';

if (role(['permissions' => ['private_conversations' => 'broadcast_message']])) {


    $result['error_message'] = Registry::load('strings')->invalid_value;
    $result['error_key'] = 'invalid_value';

    $required_fields = ['message', 'site_roles'];

    foreach ($required_fields as $required_field) {
        if (!isset($data[$required_field]) || empty($data[$required_field])) {
            $result['error_variables'][] = [$required_field];
            $noerror = false;
        }
    }

    if (isset($data['site_roles'])) {
        $data['site_roles'] = array_filter($data['site_roles'], 'is_numeric');
        $site_role_ids = json_encode($data['site_roles']);
    }


    if ($noerror) {

        $data['message'] = $urlHighlight->highlightUrls($data['message']);

        include('fns/HTMLPurifier/load.php');
        $allowed_tags = 'p,span[class],b,em,i,u,strong,s,';
        $allowed_tags .= 'a[href],ol,ul,li,br';

        $config = HTMLPurifier_Config::createDefault();
        $config->set('HTML.Allowed', $allowed_tags);
        $config->set('Attr.AllowedClasses', array());
        $config->set('HTML.Nofollow', true);
        $config->set('HTML.TargetBlank', true);
        $config->set('AutoFormat.RemoveEmpty', true);
        $config->set('AutoFormat.RemoveEmpty.RemoveNbsp', true);

        $define = $config->getHTMLDefinition(true);
        $define->addAttribute('span', 'class', new CustomClassDef(array('emoji_icon'), array('emoji-')));

        $purifier = new HTMLPurifier($config);

        $message = $purifier->purify(trim($data['message']));

        $clean = trim($message);
        $clean = preg_replace('/<br\s*\/?>/i', '', $clean);
        $clean = preg_replace('/<p>\s*<\/p>/i', '', $clean);
        $clean = trim($clean);

        if (!empty($clean) && !empty($site_role_ids)) {

            $links = $urlHighlight->getUrls($message);

            if (isset($links[0])) {
                include('fns/url_metadata/load.php');
                $url_meta_data = url_metadata($links[0]);
                if ($url_meta_data['success']) {
                    unset($url_meta_data['success']);
                    $attachments = json_encode($url_meta_data);

                    if (!empty($attachments)) {
                        $attachment_type = 'url_meta';
                    }
                }
            }

            DB::connect()->insert("broadcast_messages", [
                "message_content" => $message,
                "attachment_type" => $attachment_type,
                "attachments" => $attachments,
                "user_id" => Registry::load('current_user')->id,
                "site_role_ids" => $site_role_ids,
                "created_on" => Registry::load('current_user')->time_stamp,
            ]);

            if (!DB::connect()->error) {
                $broadcast_message_id = DB::connect()->id();

                $result = array();
                $result['success'] = true;
                $result['todo'] = 'redirect';
                $result['redirect'] = Registry::load('config')->site_url.'basic_process/?process=broadcast_message&broadcast_message_id='.$broadcast_message_id;
            }
        } else {
            $result['error_message'] = Registry::load('strings')->invalid_value;
            $result['error_key'] = 'invalid_value';
        }
    }
}

?>