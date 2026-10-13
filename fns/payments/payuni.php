<?php
include "fns/payments/payuni/payuni.php";

$payuni_merchant_key = $payuni_merchant_iv = $payuni_merchant_id = null;
$payuni_test_mode = false;

if (isset($payment_data['credentials']) && !empty($payment_data['credentials'])) {

    $credentials = json_decode($payment_data['credentials']);

    if (!empty($credentials)) {
        if (isset($credentials->payuni_merchant_key)) {
            $payuni_merchant_key = $credentials->payuni_merchant_key;
        }

        if (isset($credentials->payuni_merchant_iv)) {
            $payuni_merchant_iv = $credentials->payuni_merchant_iv;
        }

        if (isset($credentials->payuni_merchant_id)) {
            $payuni_merchant_id = $credentials->payuni_merchant_id;
        }


        if (isset($credentials->payuni_test_mode) && $credentials->payuni_test_mode === 'yes') {
            $payuni_test_mode = true;
        }

    }

}

if (empty($payuni_merchant_key) || empty($payuni_merchant_iv)) {
    $result['error_message'] = "Invalid payment method credentials — Contact the webmaster";
    $result['error_key'] = 'invalid_payment_credentials';
    return;
}


if (isset($payment_data['purchase'])) {

    $currency = Registry::load('settings')->default_currency;

    if (!in_array(Registry::load('settings')->default_currency, array('USD'))) {

        $currency = 'USD';

        include_once "fns/currency_tools/load.php";
        $payment_data['purchase'] = currency_converter($payment_data['purchase'], Registry::load('settings')->default_currency, 'USD');

        if (empty($payment_data['purchase'])) {
            $result['error_message'] = "Currency conversion was unsuccessful.";
            $result['error_key'] = 'invalid_payment_credentials';
            return;
        }
    }


    try {

        $session_time = time();

        if ($payuni_test_mode) {
            $payuniApi = new \Payuni\Sdk\PayuniApi($payuni_merchant_key, $payuni_merchant_iv, 't');
        } else {
            $payuniApi = new \Payuni\Sdk\PayuniApi($payuni_merchant_key, $payuni_merchant_iv);
        }

        $encryptInfo = [
            'MerID' => $payuni_merchant_id,
            'MerTradeNo' => $payment_data['wallet_transaction_id'],
            'TradeAmt' => $payment_data['purchase'],
            'Timestamp' => $session_time,
            'ReturnURL' => $payment_data['validation_url'],
            'BackURL' => $payment_data['validation_url'],
        ];

        $embed_code = $payuniApi->UniversalTrade($encryptInfo, 'upp');


        if (!empty($embed_code)) {

            $payment_session_data = array();
            $payment_session_data["session_time"] = $session_time;
            $payment_session_data["embed_code"] = $embed_code;

            $payment_session_data = json_encode($payment_session_data);
            DB::connect()->update('site_users_wallet', ['transaction_info' => $payment_session_data], ['wallet_transaction_id' => $payment_data['wallet_transaction_id']]);


            $result['redirect'] = $payment_data['validation_url'].'?embed_code='.$payment_data['wallet_transaction_id'];
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
    $session_time = null;

    if (isset($payment_data['transaction_info'])) {
        $session_time = json_decode($payment_data['transaction_info'], true);
        $session_time = $session_time['session_time'];
    }

    if (!empty($session_time)) {

        try {
            if ($payuni_test_mode) {
                $payuniApi = new \Payuni\Sdk\PayuniApi($payuni_merchant_key, $payuni_merchant_iv, 't');
            } else {
                $payuniApi = new \Payuni\Sdk\PayuniApi($payuni_merchant_key, $payuni_merchant_iv);
            }

            $encryptInfo = [
                'MerID' => $payuni_merchant_id,
                'MerTradeNo' => $payment_data['validate_purchase'],
                'Timestamp' => $session_time,
            ];

            $payment_intent = $payuniApi->UniversalTrade($encryptInfo, 'trade_query');
            
            if (!empty($payment_intent) && isset($payment_intent["message"]["Status"])) {

                if ($payment_intent["message"]["Status"] === "SUCCESS") {
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
}