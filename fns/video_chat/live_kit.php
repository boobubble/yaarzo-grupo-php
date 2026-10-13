<?php

error_reporting(E_ALL & ~E_DEPRECATED);

include ('fns/live_kit_sdk/autoload.php');

use Agence104\LiveKit\AccessTokenOptions;
use Agence104\LiveKit\VideoGrant;
use Agence104\LiveKit\AccessToken;
use Agence104\LiveKit\RoomServiceClient;

$live_kit_api_key = Registry::load('settings')->live_kit_api_key;
$live_kit_secret_key = Registry::load('settings')->live_kit_secret_key;
$live_kit_url = Registry::load('settings')->live_kit_url;

if (isset($data['generate_token']) && !empty($data['generate_token'])) {

    if (isset($data['generate_token']['channel_name']) && !empty($data['generate_token']['channel_name'])) {


        $channelName = $data['generate_token']['channel_name'];

        $participantName = Registry::load('current_user')->id;

        $tokenOptions = (new AccessTokenOptions())->setIdentity($participantName);


        if (isset($data['generate_token']['viewer_mode']) && $data['generate_token']['viewer_mode']) {
            $videoGrant = (new VideoGrant())->setRoomJoin()->setRoomName($channelName)->setCanPublish(FALSE);
        } else {
            $videoGrant = (new VideoGrant())->setRoomJoin()->setRoomName($channelName);
        }

        $token = new AccessToken($live_kit_api_key, $live_kit_secret_key);
        $token = $token->init($tokenOptions)->setGrant($videoGrant)->toJwt();


        $result = array();
        $result['token'] = $token;
        $result['channel'] = $channelName;
        $result['live_kit_url'] = $live_kit_url;

        if (isset($data['generate_token']['viewer_mode']) && $data['generate_token']['viewer_mode']) {
            $result['subscriber_only'] = true;
        }
    }
} else if (isset($data['check_user_status'])) {
    $channelName = $data['check_user_status']['channel_name'] ?? '';
    $target_user_id = (string)($data['check_user_status']['user_id'] ?? '');

    $live_kit_url = str_replace(
        ['wss://', 'ws://'],
        ['https://', 'http://'],
        Registry::load('settings')->live_kit_url
    );

    try {
        $roomService = new RoomServiceClient($live_kit_url, $live_kit_api_key, $live_kit_secret_key);

        $response = $roomService->listParticipants($channelName);

        $participants = $response->getParticipants();

        $found = false;
        foreach ($participants as $participant) {
            if ($participant->getIdentity() === $target_user_id) {
                $found = true;
                break;
            }
        }
        $result = $found;

    } catch (Exception $e) {
        $result = false;
    }
}

?>