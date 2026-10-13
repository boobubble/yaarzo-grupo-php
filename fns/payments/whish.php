<?php

$whish_channel = $whish_secret = $whish_websiteurl = null;
$whish_url = 'https://whish.money/itel-service/api/';
$whish_test_mode = false;

if (isset($payment_data['credentials']) && !empty($payment_data['credentials'])) {

    $credentials = json_decode($payment_data['credentials']);

    if (!empty($credentials)) {
        if (isset($credentials->whish_channel)) {
            $whish_channel = $credentials->whish_channel;
        }

        if (isset($credentials->whish_secret)) {
            $whish_secret = $credentials->whish_secret;
        }

        if (isset($credentials->whish_websiteurl)) {
            $whish_websiteurl = $credentials->whish_websiteurl;
        }

        if (isset($credentials->whish_test_mode) && $credentials->whish_test_mode === 'yes') {
            $whish_url = 'https://api.sandbox.whish.money/itel-service/api/';
            $whish_test_mode = true;
        }

    }

}

if (empty($whish_channel) || empty($whish_secret)) {
    $result['error_message'] = "Invalid payment method credentials — Contact the webmaster";
    $result['error_key'] = 'invalid_payment_credentials';
    return;
}


if (isset($payment_data['purchase'])) {

    $currency = Registry::load('settings')->default_currency;

    if (!in_array(Registry::load('settings')->default_currency, array('LBP', 'AED', 'USD'))) {

        $currency = 'USD';

        include_once "fns/currency_tools/load.php";
        $payment_data['purchase'] = currency_converter($payment_data['purchase'], Registry::load('settings')->default_currency);

        if (empty($payment_data['purchase'])) {
            $result['error_message'] = "Currency conversion was unsuccessful.";
            $result['error_key'] = 'invalid_payment_credentials';
            return;
        }
    }


    try {

        $purchase_amount = $payment_data['purchase'];

        $payload = [
            "amount" => $purchase_amount,
            "currency" => $currency,
            "invoice" => $payment_data['transaction_name'],
            "externalId" => $payment_data['wallet_transaction_id'],
            "successCallbackUrl" => $payment_data['validation_url'],
            "failureCallbackUrl" => $payment_data['validation_url'],
            "successRedirectUrl" => $payment_data['validation_url'],
            "failureRedirectUrl" => $payment_data['validation_url']
        ];

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => $whish_url.'payment/whish',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_HTTPHEADER => [
                "Content-Type: application/json",
                "channel: ".$whish_channel,
                "secret: ".$whish_secret,
                "websiteurl: ".$whish_websiteurl
            ],
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => json_encode($payload)));


        $response = curl_exec($curl);
        $session = json_decode($response, true);

        if (!empty($session) && isset($session['data']) && !empty($session['data']['collectUrl'])) {
            $result['redirect'] = $session['data']['collectUrl'];
            return;
        } else {
            $result['redirect'] = $payment_data['validation_url'];
            return;
        }
    } catch (Exception $e) {
        $result['redirect'] = $payment_data['validation_url'];
        return;
    }
} else if (isset($payment_data['validate_purchase'])) {

    $transaction_info = array_merge($_GET, $_POST);

    $result = array();
    $result['success'] = false;
    $result['transaction_info'] = $transaction_info;
    $result['error'] = 'something_went_wrong';
    $session_id = null;

    try {

        $currency = Registry::load('settings')->default_currency;

        if (!in_array(Registry::load('settings')->default_currency, array('LBP', 'AED', 'USD'))) {
            $currency = 'USD';
        }

        $payload = [
            "currency" => $currency,
            "externalId" => $payment_data['validate_purchase'],
        ];

        $curl_handle = curl_init();
        curl_setopt($curl_handle, CURLOPT_URL, $whish_url.'payment/collect/status');

        curl_setopt($curl_handle, CURLOPT_VERBOSE, true);
        curl_setopt($curl_handle, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl_handle, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl_handle, CURLOPT_HTTPHEADER, [
            "Content-Type: application/json",
            "channel: ".$whish_channel,
            "secret: ".$whish_secret,
            "websiteurl: ".$whish_websiteurl
        ]);
        curl_setopt($curl_handle, CURLOPT_POSTFIELDS, json_encode($payload));
        $response = curl_exec($curl_handle);


        $payment_intent = json_decode($response, true);

        if (!empty($payment_intent) && isset($payment_intent['data']) && !empty($payment_intent['data']['collectStatus'])) {
            if ($payment_intent['data']['collectStatus'] === "success") {
                $result = array();
                $result['success'] = true;
                $result['transaction_info'] = $payment_intent;
            } else {
                $result['error'] = 'Failed Payment';
            }
        } else {
            $result['error'] = 'Failed Payment';
        }

    } catch (Exception $e) {
        $result['error'] = $e->getMessage();
    }
}