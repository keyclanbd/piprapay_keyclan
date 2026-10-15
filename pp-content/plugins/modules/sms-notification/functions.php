<?php
    if (!defined('pp_allowed_access')) {
        die('Direct access not allowed');
    }

add_action('pp_transaction_ipn', 'send_sms_notification_transaction_ipn');
add_action('pp_invoice_ipn', 'send_sms_notification_invoice_ipn');
        
function send_sms_notification_transaction_ipn($transaction_id) {
    global $conn;

    $transaction_details = pp_get_transation($transaction_id);
    $plugin_slug = 'sms-notification';
    $sms_settings = pp_get_plugin_setting($plugin_slug);

    if (empty($sms_settings)) {
        return; // SMS settings not found
    }

    // Check if mobile number is valid
    $mobile_number = $transaction_details['response'][0]['c_email_mobile'];
    if (!validate_mobile_number($mobile_number)) {
        return; // Mobile number not valid
    }

    // Check if transaction notifications are enabled
    if (!isset($sms_settings['enable_transaction_complete'])) {
        return;
    }

    // Prepare SMS content
    $message = "Dear ".$transaction_details['response'][0]['c_name'].",\n";
    $message .= "Your transaction #".$transaction_id." has been processed.\n";
    $message .= "Amount: ".$transaction_details['response'][0]['transaction_amount']." ".$transaction_details['response'][0]['transaction_currency']."\n";
    $message .= "Status: ".$transaction_details['response'][0]['transaction_status']."\n";
    $message .= "Date: ".$transaction_details['response'][0]['created_at']."\n";
    $message .= "Thank you!";

    // Send SMS based on selected gateway
    if(isset($sms_settings['sms_gateway'])) {
        if($sms_settings['sms_gateway'] == 'bulksmsbd'){
            send_via_bulksmsbd($sms_settings, $mobile_number, $message);
        }else{
            if($sms_settings['sms_gateway'] == 'mimsms'){
                send_via_mimsms($sms_settings, $mobile_number, $message);
            }else{
                if($sms_settings['sms_gateway'] == 'greenweb'){
                    send_via_greenweb($sms_settings, $mobile_number, $message);
                }
            }
        }
    }
}

function send_sms_notification_invoice_ipn($invoice_id) {
    global $conn;

    $invoice_details = pp_get_invoice($invoice_id);
    $invoice_items = pp_get_invoice_items($invoice_id); // Get invoice items
    $plugin_slug = 'sms-notification';
    $sms_settings = pp_get_plugin_setting($plugin_slug);

    if (empty($sms_settings)) {
        return; // SMS settings not found
    }

    // Check if mobile number is valid
    $mobile_number = $invoice_details['response'][0]['c_email_mobile'];
    if (!validate_mobile_number($mobile_number)) {
        return; // Mobile number not valid
    }

    // Calculate invoice total from items
    $total_amount = 0;
    foreach ($invoice_items['response'] as $item) {
        $item_total = $item['amount'] * $item['quantity'];
        $item_discount = min($item['discount'], $item_total);
        $item_amount_after_discount = $item_total - $item_discount;
        $item_vat = $item_amount_after_discount * ($item['vat'] / 100);
        $total_amount += $item_amount_after_discount + $item_vat;
    }

    // Add shipping cost if exists
    if (isset($invoice_details['response'][0]['i_amount_shipping'])) {
        $total_amount += floatval($invoice_details['response'][0]['i_amount_shipping']);
    }

    // Determine which notifications are enabled
    $send_sms = false;
    $message = "";
    
    switch($invoice_details['response'][0]['i_status']) {
        case 'unpaid':
            if (isset($sms_settings['enable_invoice_created'])) {
                $send_sms = true;
                $message = "Dear ".$invoice_details['response'][0]['c_name'].",\n";
                $message .= "Invoice #".$invoice_id." has been created.\n";
                $message .= "Amount: ".number_format($total_amount, 2)." ".$invoice_details['response'][0]['i_currency']."\n";
                $message .= "Due Date: ".$invoice_details['response'][0]['i_due_date']."\n";
                $message .= "Status: Unpaid\n";
                
                // Only include payment link if available
                $payment_link = get_invoice_link($invoice_id);
                if ($payment_link) {
                    $message .= "Pay now: ".$payment_link;
                }
            }
            break;
            
        case 'paid':
            if (isset($sms_settings['enable_invoice_paid'])) {
                $send_sms = true;
                $message = "Dear ".$invoice_details['response'][0]['c_name'].",\n";
                $message .= "Invoice #".$invoice_id." has been paid.\n";
                $message .= "Amount: ".number_format($total_amount, 2)." ".$invoice_details['response'][0]['i_currency']."\n";
                $message .= "Thank you for your payment!";
            }
            break;
    }

    if (!$send_sms) {
        return;
    }

    if(isset($sms_settings['sms_gateway'])) {
        if($sms_settings['sms_gateway'] == 'bulksmsbd'){
            send_via_bulksmsbd($sms_settings, $mobile_number, $message);
        }else{
            if($sms_settings['sms_gateway'] == 'mimsms'){
                send_via_mimsms($sms_settings, $mobile_number, $message);
            }else{
                if($sms_settings['sms_gateway'] == 'greenweb'){
                    send_via_greenweb($sms_settings, $mobile_number, $message);
                }
            }
        }
    }
}
// SMS Gateway Implementations
function send_via_bulksmsbd($settings, $mobile, $message) {
    $api_key = $settings['bulksmsbd_api_key'];
    $sender_id = $settings['bulksmsbd_sender_id'];
    $type = $settings['bulksmsbd_type'];
    
    $url = "http://bulksmsbd.net/api/smsapi";
    $data = [
        "api_key" => $api_key,
        "senderid" => $sender_id,
        "number" => $mobile,
        "message" => $message,
        "type" => $type
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    $response = curl_exec($ch);
    curl_close($ch);
}

function send_via_mimsms($settings, $mobile, $message) {
    $url = "https://api.mimsms.com/api/SmsSending/SMS";
    
    $data = [
        "UserName" => $settings['mimsms_username'],
        "Apikey" => $settings['mimsms_api_key'],
        "MobileNumber" => $mobile,
        "CampaignId" => null,
        "SenderName" => $settings['mimsms_sender_id'],
        "TransactionType" => "T",
        "Message" => $message
    ];
    
    $payload = json_encode($data);
    
    $ch = curl_init($url);
    
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Content-Type: application/json",
        "Accept: application/json"
    ]);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $response = curl_exec($ch);
    
    curl_close($ch);
}

function send_via_greenweb($settings, $mobile, $message) {
    $to = $mobile;
    $token = $settings['greenweb_api_token'];
    $message = $message;
    $url = "https://api.bdbulksms.net/api.php?json";
    
    $data= array(
    'to'=>$to,
    'message'=>$message,
    'token'=>$token
    ); 
    $ch = curl_init(); 
    curl_setopt($ch, CURLOPT_URL,$url);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 1);
    curl_setopt($ch, CURLOPT_ENCODING, '');
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $smsresult = curl_exec($ch);
    curl_close($ch);
}

// Helper function to validate mobile numbers
function validate_mobile_number($number) {
    // Remove any non-digit characters
    $number = preg_replace('/[^0-9]/', '', $number);
    
    // Check if it's a valid Bangladeshi mobile number
    if (preg_match('/^(?:\+88|88)?(01[3-9]\d{8})$/', $number, $matches)) {
        return '880'.$matches[1]; // Return in 8801XXXXXXXXX format
    }
    
    return false;
}