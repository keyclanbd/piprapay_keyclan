<?php
// API for the KC Payment Android app. A phone pairs with a one-time code from the admin,
// and an admin must approve it. After that every request is signed by a private key that
// never leaves the phone, so there is no shared secret to leak.

if (!file_exists(__DIR__ . '/pp-config.php') || file_exists(__DIR__ . '/maintenance.lock')) {
    http_response_code(503);
    exit();
}

include_once(__DIR__ . '/pp-include/pp-controller.php');
include_once(__DIR__ . '/pp-include/pp-sms-parser.php');
include_once(__DIR__ . '/pp-include/pp-app-devices.php');

header('Content-Type: application/json');
header('Cache-Control: no-store');

function pp_api_reply($code, $data) {
    http_response_code($code);
    echo json_encode($data);
    exit();
}

function pp_api_public_key_pem($base64) {
    $der = base64_decode((string) $base64, true);
    if ($der === false || strlen($der) < 60 || strlen($der) > 200) {
        return null;
    }
    $pem = "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END PUBLIC KEY-----\n";
    $key = openssl_pkey_get_public($pem);
    if ($key === false) {
        return null;
    }
    $details = openssl_pkey_get_details($key);
    if (($details['type'] ?? null) !== OPENSSL_KEYTYPE_EC || ($details['ec']['curve_name'] ?? '') !== 'prime256v1') {
        return null;
    }
    return $pem;
}

function pp_api_pair($conn, $prefix, $body) {
    $code = pp_app_device_normalize_code($body['code'] ?? '');
    $uid = (string) ($body['device_uid'] ?? '');
    $name = mb_substr(trim((string) ($body['name'] ?? 'Android phone')), 0, 120);
    $publicKey = pp_api_public_key_pem($body['public_key'] ?? '');

    if ($code === '' || $publicKey === null || !preg_match('/^[a-f0-9-]{36}$/', $uid)) {
        pp_api_reply(400, ['status' => false, 'message' => 'Invalid pairing request']);
    }

    $now = time();
    $hash = hash('sha256', $code);
    $conn->begin_transaction();

    $stmt = $conn->prepare("SELECT id FROM `{$prefix}app_device_codes` WHERE code_hash = ? AND used_at = 0 AND expires_at > ? AND attempts < 5 FOR UPDATE");
    $stmt->bind_param('si', $hash, $now);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();

    if (!$row) {
        // Every wrong guess counts against all live codes, which caps guessing at 5 tries per code.
        $stmt = $conn->prepare("UPDATE `{$prefix}app_device_codes` SET attempts = attempts + 1 WHERE used_at = 0 AND expires_at > ?");
        $stmt->bind_param('i', $now);
        $stmt->execute();
        $conn->commit();
        pp_api_reply(403, ['status' => false, 'message' => 'Pairing code is wrong or has expired']);
    }

    $stmt = $conn->prepare("UPDATE `{$prefix}app_device_codes` SET used_at = ? WHERE id = ?");
    $stmt->bind_param('ii', $now, $row['id']);
    $stmt->execute();

    // Pairing again always drops the phone back to pending, so a new key needs a new approval.
    $stmt = $conn->prepare("INSERT INTO `{$prefix}app_devices` (device_uid, name, public_key, status, created_at) VALUES (?, ?, ?, 'pending', ?)
        ON DUPLICATE KEY UPDATE name = VALUES(name), public_key = VALUES(public_key), status = 'pending'");
    $stmt->bind_param('sssi', $uid, $name, $publicKey, $now);
    $stmt->execute();
    $conn->commit();

    pp_api_reply(200, ['status' => true, 'device_status' => 'pending']);
}

function pp_api_authenticate($conn, $prefix, $rawBody) {
    $uid = (string) ($_SERVER['HTTP_X_DEVICE_ID'] ?? '');
    $timestamp = (string) ($_SERVER['HTTP_X_TIMESTAMP'] ?? '');
    $signature = base64_decode((string) ($_SERVER['HTTP_X_SIGNATURE'] ?? ''), true);

    if ($uid === '' || !ctype_digit($timestamp) || $signature === false) {
        pp_api_reply(401, ['status' => false, 'message' => 'Unsigned request']);
    }
    if (abs(time() - (int) $timestamp) > 300) {
        pp_api_reply(401, ['status' => false, 'message' => 'Phone clock is wrong. Turn on automatic date and time.']);
    }

    $stmt = $conn->prepare("SELECT * FROM `{$prefix}app_devices` WHERE device_uid = ?");
    $stmt->bind_param('s', $uid);
    $stmt->execute();
    $device = $stmt->get_result()->fetch_assoc();

    if (!$device) {
        pp_api_reply(401, ['status' => false, 'message' => 'Unknown device']);
    }

    $signed = $uid . "\n" . $timestamp . "\n" . hash('sha256', $rawBody);
    if (openssl_verify($signed, $signature, $device['public_key'], OPENSSL_ALGO_SHA256) !== 1) {
        pp_api_reply(401, ['status' => false, 'message' => 'Invalid signature']);
    }
    if ($device['status'] === 'revoked') {
        pp_api_reply(403, ['status' => false, 'device_status' => 'revoked', 'message' => 'This phone was removed in admin']);
    }

    $now = time();
    $stmt = $conn->prepare("UPDATE `{$prefix}app_devices` SET last_seen = ? WHERE id = ?");
    $stmt->bind_param('ii', $now, $device['id']);
    $stmt->execute();

    return $device;
}

function pp_api_record_message($conn, $prefix, $device, $message) {
    $id = (string) ($message['id'] ?? '');
    $from = trim((string) ($message['from'] ?? ''));
    $text = (string) ($message['text'] ?? '');
    $sentStamp = (string) ($message['sentStamp'] ?? '');
    $receivedStamp = (string) ($message['receivedStamp'] ?? '');
    $seq = (int) ($message['seq'] ?? 0);
    $sim = in_array((int) ($message['sim'] ?? 0), [1, 2], true) ? 'sim' . (int) $message['sim'] : '--';

    if (!preg_match('/^[a-f0-9]{64}$/', $id)) {
        return ['id' => $id, 'result' => 'rejected', 'reason' => 'Invalid message ID'];
    }
    if ($from === '' || strlen($from) > 64 || $text === '' || strlen($text) > 5000
        || !ctype_digit($sentStamp) || !ctype_digit($receivedStamp)) {
        return ['id' => $id, 'result' => 'rejected', 'reason' => 'Invalid message'];
    }

    // The ID is rebuilt here so a phone can't dodge duplicate protection by inventing new ones.
    if (!hash_equals(hash('sha256', $device['device_uid'] . "\n" . $from . "\n" . $sentStamp . "\n" . $text), $id)) {
        return ['id' => $id, 'result' => 'rejected', 'reason' => 'Message ID does not match'];
    }

    $receivedSeconds = (int) intdiv((int) $receivedStamp, 1000);
    if ($receivedSeconds < time() - 8 * 86400) {
        return ['id' => $id, 'result' => 'rejected', 'reason' => 'Message is too old'];
    }

    $now = time();
    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare("INSERT IGNORE INTO `{$prefix}app_device_messages` (device_id, msg_id, seq, sender, result, created_at) VALUES (?, ?, ?, ?, 'processing', ?)");
        $stmt->bind_param('isisi', $device['id'], $id, $seq, $from, $now);
        $stmt->execute();
        if ($stmt->affected_rows === 0) {
            $conn->rollback();
            return ['id' => $id, 'result' => 'duplicate', 'reason' => 'Already received'];
        }

        $finish = function ($result, $reason) use ($conn, $prefix, $id) {
            $stmt = $conn->prepare("UPDATE `{$prefix}app_device_messages` SET result = ? WHERE msg_id = ?");
            $stmt->bind_param('ss', $result, $id);
            $stmt->execute();
            $conn->commit();
            return ['id' => $id, 'result' => $result, 'reason' => $reason];
        };

        $parsed = pp_parse_forwarded_sms($from, $text, date('Y-m-d H:i:s', $receivedSeconds));
        if ($parsed === null) {
            return $finish('ignored', 'Sender is not supported on the website');
        }
        if (!$parsed['store']) {
            return $finish('ignored', 'Message format is not a payment');
        }

        $data = $parsed['data'];
        if ($data['trxid'] !== '--') {
            // Backstop for the same payment arriving by another phone or the legacy webhook.
            $stmt = $conn->prepare("SELECT id FROM `{$prefix}sms_data` WHERE payment_method = ? AND transaction_id = ? LIMIT 1 FOR UPDATE");
            $stmt->bind_param('ss', $parsed['provider'], $data['trxid']);
            $stmt->execute();
            if ($stmt->get_result()->fetch_assoc()) {
                return $finish('duplicate', 'Transaction already recorded');
            }
        }

        $stored = mb_substr($text, 0, 755);
        $stmt = $conn->prepare("INSERT INTO `{$prefix}sms_data` (entry_type, sim, payment_method, mobile_number, transaction_id, amount, balance, message, status, created_at) VALUES ('automatic', ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param('sssssssss', $sim, $parsed['provider'], $data['mobile'], $data['trxid'], $data['amount'], $data['balance'], $stored, $parsed['status'], $data['datetime']);
        $stmt->execute();

        return $finish('forwarded', $parsed['status'] === 'approved' ? 'Saved and approved' : 'Saved for admin review');
    } catch (Throwable $e) {
        $conn->rollback();
        throw $e;
    }
}

try {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        pp_api_reply(405, ['status' => false, 'message' => 'POST only']);
    }

    $rawBody = file_get_contents('php://input', false, null, 0, 1048576);
    $body = json_decode((string) $rawBody, true);
    if (!is_array($body)) {
        pp_api_reply(400, ['status' => false, 'message' => 'Invalid JSON']);
    }

    $conn = connectDatabase();
    $prefix = $db_prefix;
    pp_app_devices_ensure_schema($conn, $prefix);

    $action = (string) ($_GET['action'] ?? '');

    if ($action === 'pair') {
        pp_api_pair($conn, $prefix, $body);
    }

    $device = pp_api_authenticate($conn, $prefix, $rawBody);

    if ($action === 'status') {
        if (array_key_exists('paused', $body)) {
            $paused = $body['paused'] === true ? 1 : 0;
            $stmt = $conn->prepare("UPDATE `{$prefix}app_devices` SET paused = ? WHERE id = ?");
            $stmt->bind_param('ii', $paused, $device['id']);
            $stmt->execute();
        }
        pp_api_reply(200, [
            'status' => true,
            'device_status' => $device['status'],
            'supported_senders' => array_keys(pp_sms_provider_senders()),
        ]);
    }

    if ($action === 'disconnect') {
        pp_app_device_set_status($conn, $prefix, (int) $device['id'], 'revoke');
        pp_api_reply(200, ['status' => true]);
    }

    if ($action === 'sms') {
        if ($device['status'] !== 'active') {
            pp_api_reply(403, ['status' => false, 'device_status' => $device['status'], 'message' => 'Waiting for admin approval']);
        }
        $messages = $body['messages'] ?? null;
        if (!is_array($messages) || count($messages) > 50) {
            pp_api_reply(400, ['status' => false, 'message' => 'Invalid batch']);
        }
        $results = [];
        foreach ($messages as $message) {
            $results[] = pp_api_record_message($conn, $prefix, $device, is_array($message) ? $message : []);
        }
        pp_api_reply(200, ['status' => true, 'results' => $results]);
    }

    pp_api_reply(404, ['status' => false, 'message' => 'Unknown action']);
} catch (Throwable $e) {
    error_log('device-api: ' . $e->getMessage());
    pp_api_reply(500, ['status' => false, 'message' => 'Server error, will retry']);
}
