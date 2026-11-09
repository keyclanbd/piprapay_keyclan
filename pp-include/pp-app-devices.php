<?php
if (!defined('pp_allowed_access')) {
    die('Direct access not allowed');
}

// Shared by the device API and the admin actions. The tables are created on first use so
// existing installs need no manual migration.
function pp_app_devices_ensure_schema($conn, $prefix) {
    static $done = false;
    if ($done) {
        return;
    }
    $conn->query("CREATE TABLE IF NOT EXISTS `{$prefix}app_device_codes` (
        id INT AUTO_INCREMENT PRIMARY KEY,
        code_hash CHAR(64) NOT NULL UNIQUE,
        attempts INT NOT NULL DEFAULT 0,
        expires_at INT NOT NULL,
        used_at INT NOT NULL DEFAULT 0
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $conn->query("CREATE TABLE IF NOT EXISTS `{$prefix}app_devices` (
        id INT AUTO_INCREMENT PRIMARY KEY,
        device_uid CHAR(36) NOT NULL UNIQUE,
        name VARCHAR(120) NOT NULL,
        public_key TEXT NOT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'pending',
        last_seen INT NOT NULL DEFAULT 0,
        created_at INT NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $conn->query("CREATE TABLE IF NOT EXISTS `{$prefix}app_device_messages` (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        device_id INT NOT NULL,
        msg_id CHAR(64) NOT NULL UNIQUE,
        seq BIGINT NOT NULL,
        sender VARCHAR(64) NOT NULL,
        result VARCHAR(20) NOT NULL,
        created_at INT NOT NULL,
        INDEX idx_device (device_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    // Added after the first release, so existing tables need the column too.
    $column = $conn->query("SHOW COLUMNS FROM `{$prefix}app_devices` LIKE 'paused'");
    if ($column && $column->num_rows === 0) {
        $conn->query("ALTER TABLE `{$prefix}app_devices` ADD COLUMN paused TINYINT NOT NULL DEFAULT 0");
    }
    $done = true;
}

function pp_app_device_normalize_code($code) {
    return preg_replace('/[^A-Z2-9]/', '', strtoupper((string) $code));
}

// Eight characters from an alphabet with no look-alikes (0/O, 1/I), so it's easy to type.
function pp_app_device_create_code($conn, $prefix) {
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $code = '';
    for ($i = 0; $i < 8; $i++) {
        $code .= $alphabet[random_int(0, 31)];
    }
    $hash = hash('sha256', $code);
    $expires = time() + 300;
    $now = time();

    $stmt = $conn->prepare("UPDATE `{$prefix}app_device_codes` SET used_at = ? WHERE used_at = 0");
    $stmt->bind_param('i', $now);
    $stmt->execute();

    $stmt = $conn->prepare("INSERT INTO `{$prefix}app_device_codes` (code_hash, expires_at) VALUES (?, ?)");
    $stmt->bind_param('si', $hash, $expires);
    $stmt->execute();

    return substr($code, 0, 4) . '-' . substr($code, 4);
}

// Both screens show this, so an admin can tell the phone they approve is the one that paired.
function pp_app_device_match_code($publicKeyPem) {
    $base64 = preg_replace('/-----[A-Z ]+-----|\s/', '', $publicKeyPem);
    return str_pad((string) (hexdec(substr(hash('sha256', $base64), 0, 8)) % 1000000), 6, '0', STR_PAD_LEFT);
}

function pp_app_device_list($conn, $prefix) {
    $devices = [];
    $result = $conn->query("SELECT d.*, COUNT(m.id) AS received, COALESCE(MAX(m.seq), 0) AS max_seq,
            COALESCE(MIN(m.seq), 0) AS min_seq, COALESCE(SUM(m.result = 'forwarded'), 0) AS forwarded
        FROM `{$prefix}app_devices` d
        LEFT JOIN `{$prefix}app_device_messages` m ON m.device_id = d.id
        GROUP BY d.id ORDER BY d.id DESC");
    while ($row = $result->fetch_assoc()) {
        $devices[] = [
            'id' => (int) $row['id'],
            'name' => $row['name'],
            'status' => $row['status'],
            'paused' => (int) $row['paused'] === 1,
            'match_code' => pp_app_device_match_code($row['public_key']),
            'created_at' => date('Y-m-d H:i:s', (int) $row['created_at']),
            'last_seen' => (int) $row['last_seen'] > 0 ? date('Y-m-d H:i:s', (int) $row['last_seen']) : 'Never',
            'received' => (int) $row['received'],
            'forwarded' => (int) $row['forwarded'],
            'missing' => (int) $row['received'] > 0 ? max(0, (int) $row['max_seq'] - (int) $row['min_seq'] + 1 - (int) $row['received']) : 0,
        ];
    }
    return $devices;
}

// A removed phone can't be approved again. It has to pair again with a new code first.
function pp_app_device_set_status($conn, $prefix, $id, $operation) {
    if ($operation === 'delete') {
        $stmt = $conn->prepare("DELETE FROM `{$prefix}app_devices` WHERE id = ?");
        $stmt->bind_param('i', $id);
    } elseif ($operation === 'approve') {
        $stmt = $conn->prepare("UPDATE `{$prefix}app_devices` SET status = 'active' WHERE id = ? AND status = 'pending'");
        $stmt->bind_param('i', $id);
    } elseif ($operation === 'revoke') {
        $stmt = $conn->prepare("UPDATE `{$prefix}app_devices` SET status = 'revoked' WHERE id = ?");
        $stmt->bind_param('i', $id);
    } else {
        return false;
    }
    $stmt->execute();
    return true;
}

// One page of the admin Devices table. A site has few phones, so filtering here is fine.
function pp_app_device_table($conn, $prefix, $search, $status, $page) {
    $limit = 50;
    $search = mb_strtolower(trim($search));
    $devices = array_values(array_filter(pp_app_device_list($conn, $prefix), function ($device) use ($search, $status) {
        $shown = $device['status'] === 'active' && $device['paused'] ? 'paused' : $device['status'];
        return ($status === 'all' || $shown === $status)
            && ($search === '' || mb_strpos(mb_strtolower($device['name']), $search) !== false);
    }));
    $page = max(1, $page);
    $rows = array_slice($devices, ($page - 1) * $limit, $limit);
    return [
        'status' => 'true',
        'data' => $rows,
        'total' => count($devices),
        'showing' => count($rows),
        'totalPages' => (int) ceil(count($devices) / $limit),
        'currentPage' => $page,
    ];
}
