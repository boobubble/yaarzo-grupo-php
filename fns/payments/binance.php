<?php

$api_key = null;
$secret_key = null;
$binance_base_url = 'https://bpay.binanceapi.com';

if (isset($payment_data['credentials']) && !empty($payment_data['credentials'])) {

    $credentials = json_decode($payment_data['credentials']);

    if (!empty($credentials)) {
        if (isset($credentials->binance_api_key)) {
            $api_key = $credentials->binance_api_key;
        }

        if (isset($credentials->binance_secret_key)) {
            $secret_key = $credentials->binance_secret_key;
        }

    }

}


if (empty($api_key) || empty($secret_key)) {
    $result['error_message'] = "Invalid payment method credentials — Contact the webmaster";
    $result['error_key'] = 'invalid_payment_credentials';
    return;
}

if (isset($payment_data['purchase'])) {

    $currency = Registry::load('settings')->default_currency;

    if (!in_array(Registry::load('settings')->default_currency, array('USD'))) {

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


        $timestamp = round(microtime(true) * 1000);
        $nonce = bin2hex(random_bytes(16));

        $orderData = [
            "env" => ["terminalType" => "WEB"],
            "merchantTradeNo" => $payment_data['wallet_transaction_id'],
            "fiatAmount" => (float)$payment_data['purchase'],
            "fiatCurrency" => strtoupper($currency),
            "goods" => [
                "goodsType" => "01",
                "goodsCategory" => "Z000",
                "referenceGoodsId" => $payment_data['wallet_transaction_id'],
                "goodsName" => $payment_data['transaction_name'],
                "goodsDetail" => $payment_data['description']
            ],
            "returnUrl" => $payment_data['validation_url'],
            "cancelUrl" => $payment_data['validation_url'],
        ];

        $payload = json_encode($orderData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $payload_body = $timestamp . "\n" . $nonce . "\n" . $payload . "\n";
        $signature = strtoupper(hash_hmac("SHA512", $payload_body, $secret_key));

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $binance_base_url . "/binancepay/openapi/v3/order",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                "Content-Type: application/json",
                "BinancePay-Timestamp: $timestamp",
                "BinancePay-Nonce: $nonce",
                "BinancePay-Certificate-SN: $api_key",
                "BinancePay-Signature: $signature"
            ],
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_TIMEOUT => 30
        ]);

        $response = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($httpcode == 200 && $response) {
            $session = json_decode($response, true);
            if ($session["status"] === "SUCCESS") {

                $payment_session_data = array();
                $payment_session_data["payment_session_data"] = $session["data"]['prepayId'];

                $payment_session_data = json_encode($payment_session_data);
                DB::connect()->update('site_users_wallet', ['transaction_info' => $payment_session_data], ['wallet_transaction_id' => $payment_data['wallet_transaction_id']]);


                $result['redirect'] = $session["data"]["checkoutUrl"];
            } else {
                $result['redirect'] = $payment_data['validation_url'];
                return;
            }
        } else {
            $result['redirect'] = $payment_data['validation_url'];
            return;
        }

    }
    catch (Exception $e) {
        $result['redirect'] = $payment_data['validation_url'];
        return;
    }
} else if (isset($payment_data['validate_purchase'])) {


    $transaction_info = array_merge($_GET, $_POST);

    $result = array();
    $result['success'] = false;
    $result['transaction_info'] = $transaction_info;
    $result['error'] = 'something_went_wrong';
    $payment_session_data = null;


    if (isset($payment_data['payment_session_data'])) {
        $payment_session_data = $payment_data['payment_session_data'];
        $transaction_info['payment_session_data'] = $payment_session_data;
    }

    if (!empty($payment_session_data)) {

        try {

            $request = [
                "prepayId" => $payment_session_data
            ];

            $json = json_encode($request, JSON_UNESCAPED_SLASHES);
            $timestamp = round(microtime(true) * 1000);
            $nonce = bin2hex(random_bytes(16));
            $payload = $timestamp . "\n" . $nonce . "\n" . $json . "\n";

            $signature = strtoupper(hash_hmac("SHA512", $payload, $secret_key));

            $ch = curl_init("https://bpay.binanceapi.com/binancepay/openapi/v2/order/query");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "Content-Type: application/json",
                "BinancePay-Timestamp: $timestamp",
                "BinancePay-Nonce: $nonce",
                "BinancePay-Certificate-SN: $api_key",
                "BinancePay-Signature: $signature"
            ]);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $json);

            $response = curl_exec($ch);
            if (curl_errno($ch)) {
                echo "cURL error: " . curl_error($ch);
            }

            $res = json_decode($response, true);

            if (!empty($response) && $res["status"] === "SUCCESS") {
                $result = array();
                $result['success'] = true;
                $result['transaction_info'] = $transaction_info;
            }

        } catch (Exception $e) {
            $result['error'] = $e->getMessage();
        }

    }
}