<?php
if (!defined('pp_allowed_access')) {
    die('Direct access not allowed');
}

// Hooks
add_action('pp_transaction_created', 'telegram_bot_notification_transaction_created');
add_action('pp_transaction_pending', 'telegram_bot_notification_transaction_pending');
add_action('pp_transaction_ipn', 'telegram_bot_notification_transaction_admin_ipn');
add_action('pp_invoice_ipn', 'telegram_bot_notification_invoice_admin_ipn');

// Admin settings endpoint (bot token + chat id based connection)
if (isset($_POST['telegram-bot-notification-action']) && checkCookie('pp_admin')) {
    telegram_bot_notification_handle_admin_request();
    exit();
}

/**
 * Handle the admin form submissions of this module.
 */
function telegram_bot_notification_handle_admin_request() {
    header('Content-Type: application/json');

    global $global_user_login;

    if (empty($global_user_login)) {
        echo json_encode(['status' => false, 'message' => 'Unauthorized request.']);
        exit();
    }

    $csrfToken = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!function_exists('validateCsrfToken') || !validateCsrfToken($csrfToken)) {
        echo json_encode(['status' => false, 'message' => 'Invalid security token. Please refresh the page and try again.']);
        exit();
    }

    $action = is_string($_POST['telegram-bot-notification-action']) ? $_POST['telegram-bot-notification-action'] : '';
    $settings = pp_get_plugin_setting('telegram-bot-notification');

    $botToken = isset($_POST['telegram_bot_token']) && is_string($_POST['telegram_bot_token']) ? trim($_POST['telegram_bot_token']) : (string) ($settings['telegram_bot_token'] ?? '');
    $chatId = isset($_POST['chat_id']) && is_string($_POST['chat_id']) ? trim($_POST['chat_id']) : (string) ($settings['chat_id'] ?? '');

    if ($action === 'test_notification') {
        if ($botToken === '' || $chatId === '') {
            echo json_encode(['status' => false, 'message' => 'Save the bot token and chat ID first.']);
            exit();
        }

        $botInfo = telegram_bot_api_request($botToken, 'getMe');
        if (empty($botInfo['ok'])) {
            echo json_encode(['status' => false, 'message' => 'Invalid bot token. ' . ($botInfo['message'] ?? '')]);
            exit();
        }

        $chat = telegram_bot_resolve_chat($botToken, $chatId, $botInfo['response']['result']['id'] ?? null);
        if (empty($chat['ok'])) {
            echo json_encode(['status' => false, 'message' => $chat['message']]);
            exit();
        }

        $message = "Test notification from PipraPay.\nBot: @" . ($chat['response']['result']['username'] ?? 'bot') . "\nChat: " . ($chat['response']['result']['title'] ?? $chatId) . "\nTime: " . date('Y-m-d H:i:s');

        if (telegram_bot_send_message($botToken, $chat['response']['result']['id'], $message)) {
            echo json_encode(['status' => true, 'message' => 'Test notification sent successfully.']);
        } else {
            echo json_encode(['status' => false, 'message' => 'Test notification could not be sent. Check the bot token and chat ID.']);
        }
        exit();
    }

    // Save settings
    if ($botToken === '' || $chatId === '') {
        echo json_encode(['status' => false, 'message' => 'Enter both the bot token and the chat ID.']);
        exit();
    }

    if (!preg_match('/^\d+:[A-Za-z0-9_-]{10,}$/', $botToken)) {
        echo json_encode(['status' => false, 'message' => 'Invalid bot token format. Copy it from @BotFather.']);
        exit();
    }

    $botInfo = telegram_bot_api_request($botToken, 'getMe');
    if (empty($botInfo['ok'])) {
        echo json_encode(['status' => false, 'message' => 'Invalid bot token. ' . ($botInfo['message'] ?? '')]);
        exit();
    }

    $chat = telegram_bot_resolve_chat($botToken, $chatId, $botInfo['response']['result']['id'] ?? null);
    if (empty($chat['ok'])) {
        echo json_encode(['status' => false, 'message' => $chat['message']]);
        exit();
    }

    $resolvedChatId = (string) $chat['response']['result']['id'];
    $status = isset($_POST['status']) && is_string($_POST['status'])
        ? (strtolower(trim($_POST['status'])) === 'enable' ? 'enable' : 'disable')
        : (string) ($settings['status'] ?? 'enable');

    $newSettings = array_merge($settings, [
        'name' => $settings['name'] ?? 'Telegram Bot Notification',
        'telegram_bot_token' => $botToken,
        'chat_id' => $resolvedChatId,
        'status' => $status,
        'notify_created' => telegram_bot_post_flag($settings, 'notify_created', 'disable'),
        'notify_pending' => telegram_bot_post_flag($settings, 'notify_pending'),
        'notify_completed' => telegram_bot_post_flag($settings, 'notify_completed'),
    ]);

    if (function_exists('pp_set_plugin_setting') && pp_set_plugin_setting('telegram-bot-notification', $newSettings)) {
        echo json_encode(['status' => true, 'message' => 'Settings saved successfully!']);
    } else {
        echo json_encode(['status' => false, 'message' => 'Failed to save settings.']);
    }
    exit();
}

/**
 * Resolve a chat id, @username or t.me link into a Telegram chat.
 */
function telegram_bot_resolve_chat($botToken, $chatId, $botUserId = null) {
    $chatId = trim((string) $chatId);

    if (preg_match('#^(?:https?://)?(?:t|telegram)\.me/([A-Za-z0-9_]{4,})#i', $chatId, $matches)) {
        $chatId = '@' . $matches[1];
    } elseif (!preg_match('/^-?\d+$/', $chatId) && !preg_match('/^@[A-Za-z0-9_]{4,}$/', $chatId)) {
        return ['ok' => false, 'message' => 'Invalid chat ID. Use a numeric chat ID, an @username or a t.me link.'];
    }

    $chat = telegram_bot_api_request($botToken, 'getChat', ['chat_id' => $chatId]);

    if (empty($chat['ok'])) {
        return ['ok' => false, 'message' => 'Chat not found for the bot. ' . ($chat['message'] ?? '')];
    }

    $chatType = (string) ($chat['response']['result']['type'] ?? '');
    if (in_array($chatType, ['group', 'supergroup', 'channel'], true)) {
        if ($botUserId === null) {
            $botInfo = telegram_bot_api_request($botToken, 'getMe');
            $botUserId = $botInfo['response']['result']['id'] ?? null;
        }

        $member = telegram_bot_api_request($botToken, 'getChatMember', ['chat_id' => $chatId, 'user_id' => $botUserId]);
        $memberStatus = (string) ($member['response']['result']['status'] ?? '');
        if ($memberStatus !== 'administrator' && $memberStatus !== 'creator') {
            return ['ok' => false, 'message' => 'Add the bot as an administrator of the ' . $chatType . ' to receive messages there.'];
        }
    }

    return $chat;
}

/**
 * Call a Telegram Bot API method.
 */
function telegram_bot_api_request($botToken, $method, $params = []) {
    $url = 'https://api.telegram.org/bot' . $botToken . '/' . $method;

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
    curl_setopt($ch, CURLOPT_TIMEOUT, 6);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

    $response = curl_exec($ch);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($response === false || $response === '') {
        return ['ok' => false, 'message' => 'Could not reach Telegram. ' . $curlError];
    }

    $decoded = json_decode($response, true);
    if (!is_array($decoded)) {
        return ['ok' => false, 'message' => 'Unexpected response from Telegram.'];
    }

    if (empty($decoded['ok'])) {
        return ['ok' => false, 'message' => (string) ($decoded['description'] ?? 'Telegram request failed.')];
    }

    return ['ok' => true, 'response' => $decoded];
}

/**
 * Send a message through the configured bot.
 */
function telegram_bot_send_message($botToken, $chatId, $message) {
    $result = telegram_bot_api_request($botToken, 'sendMessage', [
        'chat_id' => $chatId,
        'text' => $message,
        'disable_web_page_preview' => 'true',
    ]);

    if (empty($result['ok'])) {
        error_log('Telegram bot notification failed: ' . ($result['message'] ?? 'unknown error'));
        return false;
    }

    return true;
}

/**
 * Send a photo with a caption through the configured bot.
 */
function telegram_bot_send_photo($botToken, $chatId, $photoUrl, $caption) {
    $caption = (string) $caption;
    $caption = function_exists('mb_substr') ? mb_substr($caption, 0, 1000) : substr($caption, 0, 1000);

    $result = telegram_bot_api_request($botToken, 'sendPhoto', [
        'chat_id' => $chatId,
        'photo' => $photoUrl,
        'caption' => $caption,
    ]);

    if (empty($result['ok'])) {
        error_log('Telegram bot receipt upload failed: ' . ($result['message'] ?? 'unknown error'));
        return false;
    }

    return true;
}

/**
 * Site url of this installation, safe for use in notification links.
 */
function telegram_bot_site_url() {
    $host = preg_replace('/[^A-Za-z0-9.\-]/', '', (string) ($_SERVER['HTTP_HOST'] ?? ''));

    return $host === '' ? '' : 'https://' . $host;
}

/**
 * Receiving number configured on the payment gateway, if any.
 */
function telegram_bot_gateway_number($paymentMethodId) {
    $paymentMethodId = trim((string) $paymentMethodId);
    if ($paymentMethodId === '' || $paymentMethodId === '--') {
        return '';
    }

    $gatewaySettings = pp_get_plugin_setting($paymentMethodId, false);
    if (!is_array($gatewaySettings)) {
        return '';
    }

    $number = trim((string) ($gatewaySettings['payment_number'] ?? ''));

    return ($number === '' || $number === '--') ? '' : $number;
}

/**
 * Read an enable/disable flag from the plugin settings.
 */
function telegram_bot_flag($settings, $key, $default = 'enable') {
    return strtolower(trim((string) ($settings[$key] ?? $default))) === 'disable' ? 'disable' : 'enable';
}

/**
 * Read an enable/disable flag from the request, keeping the stored value when absent.
 */
function telegram_bot_post_flag($settings, $key, $default = 'enable') {
    if (!isset($_POST[$key]) || !is_string($_POST[$key])) {
        return telegram_bot_flag($settings, $key, $default);
    }

    return strtolower(trim($_POST[$key])) === 'disable' ? 'disable' : 'enable';
}

/**
 * Send a notification to the admin chat using the stored credentials.
 */
function send_telegram_bot_notification($message, $photoUrl = '', $settings = null) {
    if ($settings === null) {
        $settings = pp_get_plugin_setting('telegram-bot-notification');
    }

    $botToken = trim((string) ($settings['telegram_bot_token'] ?? ''));
    $chatId = trim((string) ($settings['chat_id'] ?? ''));
    $status = strtolower((string) ($settings['status'] ?? 'enable'));

    if ($botToken === '' || $chatId === '' || $status === 'disable') {
        return false;
    }

    if ($photoUrl !== '' && telegram_bot_send_photo($botToken, $chatId, $photoUrl, $message)) {
        return true;
    }

    if ($photoUrl !== '') {
        $message .= "\nReceipt: " . $photoUrl;
    }

    return telegram_bot_send_message($botToken, $chatId, $message);
}

// Transaction Notification to Admin
function telegram_bot_notification_transaction_admin_ipn($transaction_id) {
    telegram_bot_notification_transaction_event($transaction_id, 'completed');
}

// Transaction Notification to Admin, customer submitted the payment reference
function telegram_bot_notification_transaction_pending($transaction_id) {
    telegram_bot_notification_transaction_event($transaction_id, 'pending');
}

// Transaction Notification to Admin, new transaction created
function telegram_bot_notification_transaction_created($transaction_id) {
    telegram_bot_notification_transaction_event($transaction_id, 'created');
}

function telegram_bot_notification_transaction_event($transaction_id, $event) {
    $settings = pp_get_plugin_setting('telegram-bot-notification');

    $flagKey = $event === 'created' ? 'notify_created' : ($event === 'pending' ? 'notify_pending' : 'notify_completed');
    $flagDefault = $event === 'created' ? 'disable' : 'enable';
    if (telegram_bot_flag($settings, $flagKey, $flagDefault) === 'disable') {
        return;
    }

    // Get transaction data
    $transaction = pp_get_transation($transaction_id);

    if (empty($transaction['response'][0])) {
        return;
    }

    $t = $transaction['response'][0];

    // Extract details safely with fallback values
    $rawStatus = strtolower((string) ($t['transaction_status'] ?? ''));
    $status = $rawStatus === 'initialize' ? 'Pending' : ucfirst($rawStatus !== '' ? $rawStatus : 'unknown');
    $amount = !empty($t['transaction_amount']) ? $t['transaction_amount'] : '0.00';
    $currency = !empty($t['transaction_currency']) ? $t['transaction_currency'] : '';
    $fee = !empty($t['transaction_fee']) ? $t['transaction_fee'] : '';
    $refund = !empty($t['transaction_refund_amount']) ? $t['transaction_refund_amount'] : '';
    $name = !empty($t['c_name']) ? $t['c_name'] : 'Unknown payer';
    $contact = !empty($t['c_email_mobile']) ? $t['c_email_mobile'] : '';
    $method = !empty($t['payment_method']) ? $t['payment_method'] : '--';
    $senderNumber = !empty($t['payment_sender_number']) ? $t['payment_sender_number'] : '';
    $verifyWay = (string) ($t['payment_verify_way'] ?? '');
    $verifyId = !empty($t['payment_verify_id']) ? (string) $t['payment_verify_id'] : '';
    $ppId = !empty($t['pp_id']) ? $t['pp_id'] : $transaction_id;

    $tx_id = ($verifyWay !== 'slip' && $verifyId !== '' && $verifyId !== '--') ? $verifyId : $ppId;

    if ($event === 'created') {
        $title = "New Transaction";
        $note = "Awaiting payment";
    } elseif ($event === 'pending') {
        $title = "Transaction Pending";
        $note = "Awaiting verification";
    } else {
        $title = "Transaction $status";
        $note = "";
    }

    $lines = [
        $title,
        "ID: $tx_id",
        "Status: $status",
    ];

    if ($note !== '') {
        $lines[] = $note;
    }

    $lines[] = "Amount: $amount $currency";

    if ($fee !== '' && $fee !== '--' && (float) $fee > 0) {
        $lines[] = "Fee: $fee $currency";
    }

    if ($refund !== '' && $refund !== '--' && (float) $refund > 0) {
        $lines[] = "Refund: $refund $currency";
    }

    $lines[] = "Method: $method";

    if ($senderNumber !== '' && $senderNumber !== '--') {
        $lines[] = "Number: $senderNumber";
    }

    $merchantNumber = telegram_bot_gateway_number($t['payment_method_id'] ?? '');
    if ($merchantNumber !== '') {
        $lines[] = "Merchant No: $merchantNumber";
    }

    $lines[] = "Customer: $name" . ($contact !== '' && $contact !== '--' ? " ($contact)" : '');

    if (!empty($t['transaction_product_name']) && $t['transaction_product_name'] !== '--') {
        $lines[] = "Product: " . $t['transaction_product_name'];
    }

    if (!empty($t['created_at']) && $t['created_at'] !== '--') {
        $lines[] = "Time: " . $t['created_at'];
    }

    $siteUrl = telegram_bot_site_url();
    if ($siteUrl !== '' && !empty($t['id'])) {
        $lines[] = "Panel: $siteUrl/admin/view-transaction?ref=" . $t['id'];
    }

    $message = implode("\n", $lines);

    // Receipt uploaded by the customer, sent along with the details.
    $receipt = '';
    if ($verifyWay === 'slip' && $verifyId !== '' && $verifyId !== '--' && preg_match('#^https?://#i', $verifyId)) {
        $receipt = $verifyId;
    }

    send_telegram_bot_notification($message, $receipt, $settings);
}

// Invoice Notification to Admin
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
    
    $shipping = isset($invoice['response'][0]['i_amount_shipping']) ? floatval($invoice['response'][0]['i_amount_shipping']) : 0;

    $amount = $subtotal - $totalDiscount + $totalVat + $shipping;
    
    $currency = !empty($i['i_currency']) ? $i['i_currency'] : '';
    $invoice_no = !empty($i['i_id']) ? $i['i_id'] : 'N/A';
    $name = !empty($i['c_name']) ? $i['c_name'] : 'Unknown client';
    $contact = !empty($i['c_email_mobile']) ? $i['c_email_mobile'] : '';
    $dueDate = !empty($i['i_due_date']) ? $i['i_due_date'] : '';

    $lines = [
        "Invoice $status",
        "Invoice #$invoice_no",
        "Amount: $amount $currency",
        "Customer: $name" . ($contact !== '' && $contact !== '--' ? " ($contact)" : ''),
    ];

    if ($dueDate !== '' && $dueDate !== '--') {
        $lines[] = "Due Date: $dueDate";
    }

    $siteUrl = telegram_bot_site_url();
    if ($siteUrl !== '') {
        $lines[] = "Pay now: $siteUrl/invoice/$invoice_no";
    }

    send_telegram_bot_notification(implode("\n", $lines));
}
