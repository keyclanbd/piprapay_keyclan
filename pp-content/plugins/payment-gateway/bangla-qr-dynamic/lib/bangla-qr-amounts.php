<?php
/**
 * Unique payable amounts for the Bangla QR Dynamic gateway.
 *
 * Every checkout gets a small tag (0.10-0.99) added to the amount due, and the
 * tag is reserved in a table with a UNIQUE index so two live checkouts can never
 * share a payable total. That makes the amount itself the reconciliation key:
 * when the bank SMS for that exact amount arrives the transaction is completed
 * without the customer typing anything.
 *
 * The reservation is released only after the match window closes, so a payment
 * that lands late still reaches the transaction it belongs to.
 */

if (!defined('pp_allowed_access')) {
    die('Direct access not allowed');
}

/** Gateway slug, used for the settings row and the transaction helpers. */
const BQR_DYNAMIC_SLUG = 'bangla-qr-dynamic';

/**
 * Create the reservation table on first use, so existing installs need no
 * manual migration.
 *
 * `live_payable` is the unique slot: it holds the payable total while the
 * reservation is live and is set to NULL when released. MySQL allows many NULLs
 * in a unique index, which is what lets a released amount be reused while the
 * row stays behind for audit.
 *
 * @param mysqli|null $conn
 * @param string|null $prefix
 * @return bool
 */
function bangla_qr_dynamic_amounts_ensure_schema($conn = null, $prefix = null)
{
    static $done = false;
    if ($done) {
        return true;
    }

    $conn = $conn ?: connectDatabase();
    $prefix = $prefix ?: $GLOBALS['db_prefix'];

    $ok = false;
    try {
        $ok = $conn->query("CREATE TABLE IF NOT EXISTS `{$prefix}bqd_amounts` (
            id INT AUTO_INCREMENT PRIMARY KEY,
            pp_id VARCHAR(64) NOT NULL,
            payable DECIMAL(12,2) NOT NULL,
            live_payable DECIMAL(12,2) NULL DEFAULT NULL,
            base_payable DECIMAL(12,2) NOT NULL,
            status VARCHAR(16) NOT NULL DEFAULT 'open',
            sms_id INT NOT NULL DEFAULT 0,
            expires_at INT NOT NULL,
            match_until INT NOT NULL,
            created_at INT NOT NULL,
            matched_at INT NOT NULL DEFAULT 0,
            UNIQUE KEY uniq_bqd_pp (pp_id),
            UNIQUE KEY uniq_bqd_live (live_payable),
            KEY idx_bqd_open (status, match_until)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4") !== false;
    } catch (Throwable $e) {
        error_log('bangla-qr-dynamic schema: ' . $e->getMessage());
        $ok = false;
    }

    if ($ok) {
        $done = true;
    }

    return $ok;
}

/**
 * Fetch the reservation row for a transaction.
 *
 * @param mysqli $conn
 * @param string $prefix
 * @param string $ppId
 * @return array|null
 */
function bangla_qr_dynamic_amount_row($conn, $prefix, $ppId)
{
    $stmt = null;
    try {
        $stmt = $conn->prepare("SELECT pp_id, payable, status, expires_at, match_until FROM `{$prefix}bqd_amounts` WHERE pp_id = ? LIMIT 1");
    } catch (Throwable $e) {
        error_log('bangla-qr-dynamic lookup: ' . $e->getMessage());
        return null;
    }
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('s', $ppId);
    if (!$stmt->execute()) {
        $stmt->close();
        return null;
    }
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    return $row ?: null;
}

/**
 * Insert one reservation attempt. Returns false when the payable total is
 * already taken (duplicate key on `uniq_bqd_live`).
 *
 * Works whether mysqli is in exception mode or returning false.
 *
 * @param mysqli $conn
 * @param string $prefix
 * @param string $ppId
 * @param string $payable  Two decimal string.
 * @param string $base     Two decimal string, the amount without the tag.
 * @param int    $expiresAt
 * @param int    $matchUntil
 * @param int    $now
 * @return bool
 */
function bangla_qr_dynamic_insert_claim($conn, $prefix, $ppId, $payable, $base, $expiresAt, $matchUntil, $now)
{
    $stmt = null;
    try {
        $stmt = $conn->prepare("INSERT INTO `{$prefix}bqd_amounts`
            (pp_id, payable, live_payable, base_payable, status, sms_id, expires_at, match_until, created_at)
            VALUES (?, ?, ?, ?, 'open', 0, ?, ?, ?)");
    } catch (Throwable $e) {
        return false;
    }
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('ssssiii', $ppId, $payable, $payable, $base, $expiresAt, $matchUntil, $now);

    $ok = false;
    try {
        $ok = $stmt->execute() === true;
    } catch (Throwable $e) {
        // 1062 is a duplicate key: that amount is taken, try the next tag.
        $ok = false;
    }
    $stmt->close();

    return $ok;
}

/**
 * Reopen a released reservation with a new payable total. Used when the
 * customer went back to the gateway list and chose this gateway again: the
 * row for the transaction already exists, so it is updated rather than
 * inserted. Returns false when the payable total is already taken.
 *
 * Only a row that is no longer live is touched; one that was matched to a
 * payment is never reopened.
 *
 * @param mysqli $conn
 * @param string $prefix
 * @param string $ppId
 * @param string $payable
 * @param string $base
 * @param int    $expiresAt
 * @param int    $matchUntil
 * @param int    $now
 * @return bool
 */
function bangla_qr_dynamic_reopen_claim($conn, $prefix, $ppId, $payable, $base, $expiresAt, $matchUntil, $now)
{
    $stmt = null;
    try {
        $stmt = $conn->prepare("UPDATE `{$prefix}bqd_amounts`
            SET payable = ?, live_payable = ?, base_payable = ?, status = 'open', sms_id = 0,
                expires_at = ?, match_until = ?, created_at = ?, matched_at = 0
            WHERE pp_id = ? AND status <> 'matched' AND (status <> 'open' OR match_until < ?)");
    } catch (Throwable $e) {
        return false;
    }
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('sssiiisi', $payable, $payable, $base, $expiresAt, $matchUntil, $now, $ppId, $now);

    $ok = false;
    try {
        $ok = $stmt->execute() === true && $stmt->affected_rows === 1;
    } catch (Throwable $e) {
        // 1062 is a duplicate key: that amount is taken, try the next tag.
        $ok = false;
    }
    $stmt->close();

    return $ok;
}

/**
 * Reserve (or re-use) the payable total for a checkout.
 *
 * The amount never changes while a reservation is live, so refreshing the
 * checkout page cannot hand the customer a different figure than the QR they
 * already scanned.
 *
 * @param string     $ppId
 * @param float      $baseAmount   Amount due before the tag.
 * @param int        $qrSeconds    How long the QR is shown as valid.
 * @param int        $graceSeconds How long after that a late SMS can still match.
 * @param mysqli|null $conn
 * @param string|null $prefix
 * @return array{ok:bool, payable?:string, expires_at?:int, match_until?:int, reused?:bool, error?:string, degraded?:bool, expired?:bool}
 */
function bangla_qr_dynamic_claim_amount($ppId, $baseAmount, $qrSeconds, $graceSeconds, $conn = null, $prefix = null)
{
    $conn = $conn ?: connectDatabase();
    $prefix = $prefix ?: $GLOBALS['db_prefix'];
    $ppId = (string) $ppId;
    $now = time();
    $qrSeconds = max(60, (int) $qrSeconds);
    $graceSeconds = max(60, (int) $graceSeconds);

    if ($ppId === '') {
        return array('ok' => false, 'error' => 'Missing transaction.');
    }
    if (!bangla_qr_dynamic_amounts_ensure_schema($conn, $prefix)) {
        return array('ok' => false, 'degraded' => true, 'error' => 'Amount reservation is unavailable.');
    }

    $existing = bangla_qr_dynamic_amount_row($conn, $prefix, $ppId);
    if ($existing !== null) {
        if ($existing['status'] === 'open' && (int) $existing['match_until'] >= $now) {
            // Same amount, full window. The customer gets the configured time
            // on every page load, not whatever is left of the first one, so the
            // QR never closes earlier than the setting says. The match window
            // is carried forward with it; the transaction's own session timeout
            // still caps the total.
            $expiresAt = $now + $qrSeconds;
            $matchUntil = max((int) $existing['match_until'], $now + $qrSeconds + $graceSeconds);
            $stmt = $conn->prepare("UPDATE `{$prefix}bqd_amounts` SET expires_at = ?, match_until = ? WHERE pp_id = ? AND status = 'open'");
            if ($stmt) {
                $stmt->bind_param('iis', $expiresAt, $matchUntil, $ppId);
                try {
                    $stmt->execute();
                } catch (Throwable $e) {
                    // A failed window refresh is harmless; the old one still holds.
                }
                $stmt->close();
            }

            return array(
                'ok' => true,
                'payable' => (string) $existing['payable'],
                'expires_at' => $expiresAt,
                'match_until' => $matchUntil,
                'reused' => true,
            );
        }

        if ($existing['status'] === 'matched') {
            return array('ok' => false, 'expired' => true, 'error' => 'This payment has already been received.');
        }
        // Released (the customer went back to the gateway list) or left to run
        // out: this is a fresh visit, so it gets a fresh tag below.
    }

    $baseCents = (int) round(((float) $baseAmount) * 100);
    if ($baseCents <= 0) {
        return array('ok' => false, 'error' => 'This payment has no payable amount.');
    }
    $base = number_format($baseCents / 100, 2, '.', '');
    $expiresAt = $now + $qrSeconds;
    $matchUntil = $now + $qrSeconds + $graceSeconds;

    // 90 tags per base amount, taken in order from the lowest, so a checkout
    // pays as close to the total as possible: 1020.10, then 1020.11, then
    // 1020.12 for the next one instead of jumping to 1020.87.
    $tags = range(10, 99);

    foreach ($tags as $cents) {
        $payable = number_format(($baseCents + $cents) / 100, 2, '.', '');
        $claimed = $existing === null
            ? bangla_qr_dynamic_insert_claim($conn, $prefix, $ppId, $payable, $base, $expiresAt, $matchUntil, $now)
            : bangla_qr_dynamic_reopen_claim($conn, $prefix, $ppId, $payable, $base, $expiresAt, $matchUntil, $now);
        if ($claimed) {
            return array(
                'ok' => true,
                'payable' => $payable,
                'expires_at' => $expiresAt,
                'match_until' => $matchUntil,
                'reused' => false,
            );
        }
    }

    return array('ok' => false, 'error' => 'No free payment amount is available right now. Please try again.');
}

/**
 * Release a reservation so the amount can be used again.
 *
 * @param mysqli $conn
 * @param string $prefix
 * @param string $ppId
 * @param string $status Either expired or matched.
 * @return void
 */
function bangla_qr_dynamic_release_amount($conn, $prefix, $ppId, $status)
{
    $status = in_array($status, array('expired', 'matched'), true) ? $status : 'expired';
    $now = time();
    $stmt = $conn->prepare("UPDATE `{$prefix}bqd_amounts` SET live_payable = NULL, status = ?, matched_at = ? WHERE pp_id = ? AND live_payable IS NOT NULL");
    if (!$stmt) {
        return;
    }
    $stmt->bind_param('sis', $status, $now, $ppId);
    try {
        $stmt->execute();
    } catch (Throwable $e) {
        error_log('bqd release: ' . $e->getMessage());
    }
    $stmt->close();
}

/**
 * Complete the transaction that owns a live payable total.
 *
 * @param mysqli $conn
 * @param string $prefix
 * @param string $payable    Two decimal string taken from the SMS.
 * @param int    $smsId
 * @param string $sender
 * @param string $trxid
 * @return string One of: matched, no-amount, not-open, failed.
 */
function bangla_qr_dynamic_complete_by_amount($conn, $prefix, $payable, $smsId, $sender, $trxid)
{
    $stmt = $conn->prepare("SELECT pp_id FROM `{$prefix}bqd_amounts` WHERE live_payable = ? AND status = 'open' AND match_until >= ? LIMIT 1");
    if (!$stmt) {
        return 'failed';
    }
    $now = time();
    $stmt->bind_param('si', $payable, $now);
    if (!$stmt->execute()) {
        $stmt->close();
        return 'failed';
    }
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    if (!$row) {
        return 'no-amount';
    }
    $ppId = (string) $row['pp_id'];

    $transaction = pp_get_transation($ppId);
    if (($transaction['status'] ?? false) !== true) {
        return 'failed';
    }
    $status = (string) ($transaction['response'][0]['transaction_status'] ?? '');
    if (!in_array($status, array('initialize', 'pending'), true)) {
        // Already settled or expired; release the slot so it can be reused.
        bangla_qr_dynamic_release_amount($conn, $prefix, $ppId, 'matched');

        return 'not-open';
    }

    $completed = pp_set_transaction_byid(
        $ppId,
        BQR_DYNAMIC_SLUG,
        'Bangla QR Dynamic',
        $sender,
        $trxid,
        'completed',
        $smsId
    );

    if ($completed) {
        bangla_qr_dynamic_release_amount($conn, $prefix, $ppId, 'matched');

        return 'matched';
    }

    return 'failed';
}

/**
 * Try to match one stored SMS against a live payable total.
 *
 * Called as soon as a message is stored, so a payment confirms while the
 * customer is still on the page, and again from cron as a safety net.
 *
 * @param int $smsId
 * @param mysqli|null $conn
 * @param string|null $prefix
 * @return string matched, skipped, no-amount or failed.
 */
function bangla_qr_dynamic_match_sms($smsId, $conn = null, $prefix = null)
{
    $conn = $conn ?: connectDatabase();
    $prefix = $prefix ?: $GLOBALS['db_prefix'];
    $smsId = (int) $smsId;
    if ($smsId <= 0) {
        return 'skipped';
    }
    if (!bangla_qr_dynamic_amounts_ensure_schema($conn, $prefix)) {
        return 'skipped';
    }

    $stmt = $conn->prepare("SELECT id, payment_method, mobile_number, transaction_id, amount, status FROM `{$prefix}sms_data` WHERE id = ? LIMIT 1");
    if (!$stmt) {
        return 'failed';
    }
    $stmt->bind_param('i', $smsId);
    if (!$stmt->execute()) {
        $stmt->close();
        return 'failed';
    }
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    if (!$row) {
        return 'skipped';
    }

    // "used" means the payment is already attached to a transaction.
    if (!in_array((string) $row['status'], array('approved', 'review'), true)) {
        return 'skipped';
    }

    $settings = pp_get_plugin_setting(BQR_DYNAMIC_SLUG);
    if (!is_array($settings) || strtolower((string) ($settings['auto_verify'] ?? 'enable')) !== 'enable') {
        return 'skipped';
    }

    $trxid = trim((string) $row['transaction_id']);
    // "--" is the placeholder for "this message had no transaction id", which
    // is not a usable reference.
    if ($trxid === '' || $trxid === '--' || !preg_match('/^[A-Za-z0-9_-]{1,100}$/', $trxid)) {
        // Without a usable transaction ID the payment cannot be attached.
        return 'skipped';
    }

    $amount = trim((string) $row['amount']);
    if ($amount === '' || !is_numeric(str_replace(',', '', $amount))) {
        return 'skipped';
    }
    $payable = number_format((float) str_replace(',', '', $amount), 2, '.', '');

    // A bank message is parked as "review" because the sender can be forged.
    // An exact match against a live reservation is the stronger signal here:
    // that figure was issued moments ago to one specific checkout.
    if ((string) $row['status'] === 'review') {
        $approve = $conn->prepare("UPDATE `{$prefix}sms_data` SET status = 'approved' WHERE id = ? AND status = 'review'");
        if ($approve) {
            $approve->bind_param('i', $smsId);
            try {
                $approve->execute();
            } catch (Throwable $e) {
                // Leave it in review; the admin can still approve it by hand.
            }
            $approve->close();
        }
    }

    return bangla_qr_dynamic_complete_by_amount(
        $conn,
        $prefix,
        $payable,
        $smsId,
        (string) $row['mobile_number'],
        $trxid
    );
}

/**
 * Cron: release windows that closed and retry recently stored messages.
 *
 * @param mysqli|null $conn
 * @param string|null $prefix
 * @return void
 */
function bangla_qr_dynamic_cron($conn = null, $prefix = null)
{
    $conn = $conn ?: connectDatabase();
    $prefix = $prefix ?: $GLOBALS['db_prefix'];
    if (!bangla_qr_dynamic_amounts_ensure_schema($conn, $prefix)) {
        return;
    }

    $now = time();

    // Release reservations whose match window has closed, and fail the
    // transaction so a payment can never arrive after the amount is reused.
    $stmt = $conn->prepare("SELECT pp_id FROM `{$prefix}bqd_amounts` WHERE status = 'open' AND match_until < ? LIMIT 100");
    if ($stmt) {
        $stmt->bind_param('i', $now);
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            $closed = array();
            while ($result && ($row = $result->fetch_assoc())) {
                $closed[] = (string) $row['pp_id'];
            }
            $stmt->close();

            foreach ($closed as $ppId) {
                bangla_qr_dynamic_release_amount($conn, $prefix, $ppId, 'expired');

                $transaction = pp_get_transation($ppId);
                if (($transaction['status'] ?? false) === true
                    && ($transaction['response'][0]['transaction_status'] ?? '') === 'initialize') {
                    pp_set_transaction_status($ppId, 'failed');
                }
            }
        } else {
            $stmt->close();
        }
    }

    // Safety net: messages stored while the site was unreachable, or while the
    // gateway was inactive, still get their chance to complete a transaction.
    $stmt = $conn->prepare("SELECT id FROM `{$prefix}sms_data`
        WHERE status IN ('approved', 'review') AND transaction_id <> '--' AND created_at >= ?
        ORDER BY id DESC LIMIT 50");
    if ($stmt) {
        $since = date('Y-m-d H:i:s', $now - 3600);
        $stmt->bind_param('s', $since);
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            $ids = array();
            while ($result && ($row = $result->fetch_assoc())) {
                $ids[] = (int) $row['id'];
            }
            $stmt->close();

            foreach ($ids as $id) {
                bangla_qr_dynamic_match_sms($id, $conn, $prefix);
            }
        } else {
            $stmt->close();
        }
    }
}
