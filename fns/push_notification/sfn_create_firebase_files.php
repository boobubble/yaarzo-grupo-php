<?php

function create_firebase_files($data) {

    $firebase_pkey = 'firebase_pk_'.random_string(['length' => 10]).'.json';

    $sw_file_content = '';
    $sw_file_content .= 'importScripts("https://www.gstatic.com/firebasejs/10.5.2/firebase-app-compat.js");' . PHP_EOL;
    $sw_file_content .= 'importScripts("https://www.gstatic.com/firebasejs/10.5.2/firebase-messaging-compat.js");' . PHP_EOL;
    $sw_file_content .= PHP_EOL;


    $sw_file_content .= 'firebase.initializeApp({' . PHP_EOL;
    $sw_file_content .= ' apiKey: "' . $data['fb_apikey'] . '",' . PHP_EOL;
    $sw_file_content .= ' authDomain: "' . $data['fb_authdomain'] . '",' . PHP_EOL;
    $sw_file_content .= ' projectId: "' . $data['fb_projectid'] . '",' . PHP_EOL;
    $sw_file_content .= ' messagingSenderId: "' . $data['fb_messagingsenderid'] . '",' . PHP_EOL;
    $sw_file_content .= ' appId: "' . $data['fb_appid'] . '",' . PHP_EOL;
    $sw_file_content .= '});' . PHP_EOL;
    $sw_file_content .= PHP_EOL;


    $sw_file_content .= 'const messaging = firebase.messaging();' . PHP_EOL;
    $sw_file_content .= PHP_EOL;


    $sw_file_content .= 'messaging.onBackgroundMessage((payload) => {' . PHP_EOL;
    $sw_file_content .= ' const title =' . PHP_EOL;
    $sw_file_content .= ' payload?.notification?.title ||' . PHP_EOL;
    $sw_file_content .= ' payload?.data?.title ||' . PHP_EOL;
    $sw_file_content .= ' "New Message";' . PHP_EOL;
    $sw_file_content .= PHP_EOL;
    $sw_file_content .= ' const body =' . PHP_EOL;
    $sw_file_content .= ' payload?.notification?.body ||' . PHP_EOL;
    $sw_file_content .= ' payload?.data?.body ||' . PHP_EOL;
    $sw_file_content .= ' "You have a new notification";' . PHP_EOL;
    $sw_file_content .= PHP_EOL;
    $sw_file_content .= ' const link =' . PHP_EOL;
    $sw_file_content .= ' payload?.fcmOptions?.link ||' . PHP_EOL;
    $sw_file_content .= ' payload?.data?.target_link ||' . PHP_EOL;
    $sw_file_content .= ' "/";' . PHP_EOL;
    $sw_file_content .= PHP_EOL;


    // $sw_file_content .= ' self.registration.showNotification(title, {' . PHP_EOL;
    // $sw_file_content .= ' body: body,' . PHP_EOL;
    // $sw_file_content .= ' data: { target_link: link },' . PHP_EOL;
    // $sw_file_content .= ' requireInteraction: true' . PHP_EOL;
    // $sw_file_content .= ' });' . PHP_EOL;
    
    $sw_file_content .= '});' . PHP_EOL;
    $sw_file_content .= PHP_EOL;


    $sw_file_content .= 'self.addEventListener("notificationclick", (event) => {' . PHP_EOL;
    $sw_file_content .= ' event.notification.close();' . PHP_EOL;
    $sw_file_content .= PHP_EOL;
    $sw_file_content .= ' const target = event.notification?.data?.target_link || "/";' . PHP_EOL;
    $sw_file_content .= PHP_EOL;
    $sw_file_content .= ' event.waitUntil(' . PHP_EOL;
    $sw_file_content .= ' clients.matchAll({ type: "window", includeUncontrolled: true }).then((clientList) => {' . PHP_EOL;
    $sw_file_content .= ' for (const client of clientList) {' . PHP_EOL;
    $sw_file_content .= ' if (client.url === target && "focus" in client) {' . PHP_EOL;
    $sw_file_content .= ' return client.focus();' . PHP_EOL;
    $sw_file_content .= ' }' . PHP_EOL;
    $sw_file_content .= ' }' . PHP_EOL;
    $sw_file_content .= ' if (clients.openWindow) {' . PHP_EOL;
    $sw_file_content .= ' return clients.openWindow(target);' . PHP_EOL;
    $sw_file_content .= ' }' . PHP_EOL;
    $sw_file_content .= ' })' . PHP_EOL;
    $sw_file_content .= ' );' . PHP_EOL;
    $sw_file_content .= '});' . PHP_EOL;
    $sw_file_content .= PHP_EOL;


    $sw_file_content .= 'self.addEventListener("install", () => self.skipWaiting());' . PHP_EOL;
    $sw_file_content .= 'self.addEventListener("activate", (event) => event.waitUntil(self.clients.claim()));' . PHP_EOL;


    $sw_filePath = 'assets/service_worker/firebase-messaging-sw.js';


    if (file_exists($sw_filePath)) {
        unlink($sw_filePath);
    }


    file_put_contents($sw_filePath, $sw_file_content);

    if (isset($_FILES['firebase_private_key']['name']) && !empty($_FILES['firebase_private_key']['name'])) {

        $folderPath = 'fns/push_notification/firebase_key/';

        if (file_exists($folderPath)) {
            $old_pk_files = glob($folderPath . '*');
            array_map('unlink', $old_pk_files);
        } else {
            mkdir($folderPath);
        }

        if (files('upload', ['upload' => 'firebase_private_key', 'folder' => $folderPath, 'saveas' => $firebase_pkey, 'real_path' => true])['result']) {
            DB::connect()->update("settings", ["value" => $firebase_pkey, "updated_on" => Registry::load('current_user')->time_stamp], ["setting" => 'firebase_private_key']);
        }
    }

}

?>