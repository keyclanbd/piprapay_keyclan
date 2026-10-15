<?php
    if (!defined('pp_allowed_access')) {
        die('Direct access not allowed');
    }

    // Register the hook on plugin load
    add_action('pp_transaction_ipn', 'send_webhook_ipn');
    
    function send_webhook_ipn($transaction_id) {
        global $conn;
    
        $transaction_details = pp_get_transation($transaction_id);
        $setting = pp_get_settings();
        $meta = json_decode($transaction_details['response'][0]['transaction_metadata'], true) ?? [];
    
        $webhook_url = $transaction_details['response'][0]['transaction_webhook_url'];
    
        $payload = [
            'pp_id' => $transaction_details['response'][0]['pp_id'],
            'customer_name' => $transaction_details['response'][0]['c_name'],
            'customer_email_mobile' => $transaction_details['response'][0]['c_email_mobile'],
            'payment_method' => $transaction_details['response'][0]['payment_method'],
            'amount' => $transaction_details['response'][0]['transaction_amount'],
            'fee' => $transaction_details['response'][0]['transaction_fee'],
            'refund_amount' => $transaction_details['response'][0]['transaction_refund_amount'],
            'total' => $transaction_details['response'][0]['transaction_amount'] + $transaction_details['response'][0]['transaction_fee'] - $transaction_details['response'][0]['transaction_refund_amount'],
            'currency' => $transaction_details['response'][0]['transaction_currency'],
            'metadata' => $meta,
            'sender_number' => $transaction_details['response'][0]['payment_sender_number'],
            'transaction_id' => $transaction_details['response'][0]['payment_verify_id'],
            'status' => $transaction_details['response'][0]['transaction_status'],
            'date' => $transaction_details['response'][0]['created_at']
        ];
    
        $headerApi = $setting['response'][0]['api_key'];
        $extra_headers = [];
    
        if ($headerApi) {
            $extra_headers[] = 'mh-piprapay-api-key: ' . $headerApi;
        }
        
        if($webhook_url !== "--"){
            register_shutdown_function(function() use ($webhook_url, $payload, $extra_headers) {
                ignore_user_abort(true);
                webhook_plugin_send_request($webhook_url, $payload, $extra_headers);
            });
        }
    }
    
    
    function webhook_plugin_send_request($url, $data, $extra_headers = []) {
        $ch = curl_init();
    
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

        $headers = array_merge(['Content-Type: application/json'], $extra_headers);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
    }

?>