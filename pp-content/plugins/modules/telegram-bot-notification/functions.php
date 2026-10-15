<?php
if (!defined('pp_allowed_access')) {
    die('Direct access not allowed');
}

// Hooks
add_action('pp_transaction_ipn', 'telegram_bot_notification_transaction_admin_ipn');
add_action('pp_invoice_ipn', 'telegram_bot_notification_invoice_admin_ipn');


if (isset($_POST['telegram-bot-notification-action'])) {
    // ✅ Send correct content type for AJAX
    header('Content-Type: application/json');

    // Sanitize input
    $telegram_bot_token = escape_string($_POST['telegram_bot_token']);
    $auth_code = escape_string($_POST['auth_code']);

    // ✅ Validate input
    if ($telegram_bot_token == "" || $auth_code == "") {
        echo json_encode(['status' => false, 'message' => 'Enter all info!']);
        exit();
    }

    // ✅ Validate Telegram token before proceeding
    $checkBot = @file_get_contents("https://api.telegram.org/bot".$telegram_bot_token."/getMe");
    $checkResult = json_decode($checkBot, true);

    if (!$checkResult || !isset($checkResult['ok']) || $checkResult['ok'] !== true) {
        echo json_encode(['status' => false, 'message' => 'Invalid Telegram Bot Token.']);
        exit();
    }

    // ✅ Set webhook
    
    $webhook_url = pp_get_site_url() . "/pp-content/plugins/modules/telegram-bot-notification/ipn?telegram-bot-notification";
    $webhookSet = @file_get_contents("https://api.telegram.org/bot".$telegram_bot_token."/setWebhook?url=".$webhook_url);
    $webhookResponse = json_decode($webhookSet, true);

    if (!$webhookResponse || !isset($webhookResponse['ok']) || $webhookResponse['ok'] !== true) {
        echo json_encode(['status' => false, 'message' => 'Failed to set Telegram webhook.']);
        exit();
    }

    // ✅ Prepare data for local plugin update
    $targetUrl = pp_get_site_url().'/admin/dashboard';
    $data = [
        'action' => 'plugin_update-submit',
        'plugin_slug' => 'telegram-bot-notification',
        'telegram_bot_token' => $telegram_bot_token,
        'auth_code' => $auth_code,
    ];

    // ✅ cURL request
    $ch = curl_init($targetUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    $curl_response = curl_exec($ch);
    curl_close($ch);

    // ✅ Final response
    echo json_encode(['status' => true, 'message' => 'Telegram Bot authorized successfully.']);
    exit();
}

// ✅ Helper: Send OneSignal Notification to Admin
function send_telegram_bot_notification($message) {
    $plugin_slug = 'telegram-bot-notification';
    $settings = pp_get_plugin_setting($plugin_slug);
    
    if(isset($settings['telegram_bot_token']) && $settings['telegram_bot_token'] !== "" || isset($settings['auth_code']) && $settings['auth_code'] !== "" || isset($settings['chat_id']) && $settings['chat_id'] !== "" ){
        
    }else{
        return;
    }

    $text = urlencode($message);
    $url = "https://api.telegram.org/bot".$settings['telegram_bot_token']."/sendMessage?chat_id=".$settings['chat_id']."&text=$text";
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_exec($ch);
    curl_close($ch);
}

// ✅ Transaction Notification to Admin
function telegram_bot_notification_transaction_admin_ipn($transaction_id) {
    // Get transaction data
    $transaction = pp_get_transation($transaction_id);

    $t = $transaction['response'][0];

    // Extract details safely with fallback values
    $status = !empty($t['transaction_status']) ? ucfirst($t['transaction_status']) : 'Unknown';
    $amount = !empty($t['transaction_amount']) ? $t['transaction_amount'] : '0.00';
    $currency = !empty($t['transaction_currency']) ? $t['transaction_currency'] : '';
    $name = !empty($t['c_name']) ? $t['c_name'] : 'Unknown payer';
    $tx_id = $t['transaction_id'] ?? $transaction_id;

    $title = "Transaction $status";
    $message = "$title\nID: $tx_id\nAmount: $amount $currency\nFrom: $name";

    send_telegram_bot_notification($message);
}

// ✅ Invoice Notification to Admin
function telegram_bot_notification_invoice_admin_ipn($invoice_id) {
    $invoice = pp_get_invoice($invoice_id);

    $i = $invoice['response'][0];

    $status = !empty($i['i_status']) ? ucfirst($i['i_status']) : 'Unknown';
    
    $subtotal = 0;
    $totalVat = 0;
    $totalDiscount = 0;
    
    $response_items = pp_get_invoice_items($i['i_id']);
    foreach ($response_items['response'] as $item) {
        $quantity = isset($item['quantity']) ? floatval($item['quantity']) : 0;
        $amount = isset($item['amount']) ? floatval($item['amount']) : 0;
        $discount = isset($item['discount']) ? floatval($item['discount']) : 0;
        $vatPercentage = isset($item['vat']) ? floatval($item['vat']) : 0;
    
        $itemSubtotal = $quantity * $amount;
    
        $itemDiscount = min($discount, $itemSubtotal);
        $totalDiscount += $itemDiscount;

        $itemAmountAfterDiscount = $itemSubtotal - $itemDiscount;
        $itemVat = $itemAmountAfterDiscount * ($vatPercentage / 100);
        $totalVat += $itemVat;

        $subtotal += $itemSubtotal;
    }
    
    $shipping = pp_get_invoice($i['i_id']);
    $shipping = $shipping['response'][0]['i_amount_shipping'];

    $amount = $subtotal - $totalDiscount + $totalVat + $shipping;
    
    $currency = !empty($i['i_currency']) ? $i['i_currency'] : '';
    $invoice_no = !empty($i['i_id']) ? $i['i_id'] : 'N/A';
    $name = !empty($i['c_name']) ? $i['c_name'] : 'Unknown client';

    $message = "Invoice $status\nInvoice #$invoice_no | Amount: $amount $currency from $name";

    send_telegram_bot_notification($message);
}