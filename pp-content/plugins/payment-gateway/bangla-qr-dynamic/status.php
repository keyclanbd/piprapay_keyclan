<?php
/**
 * Payment status for the checkout poll.
 *
 * The checkout used to poll the payment page itself, but that page answers with
 * HTML as soon as anything about the request changes (the gateway is no longer
 * available, the transaction moved on, a guard fired early). The poll read that
 * as "payment finished" and sent the customer back to the checkout mid-payment.
 *
 * This endpoint always answers JSON, so only a real status change moves the
 * customer.
 */

// Anything printed by an included file would corrupt the JSON, so hold it back.
ob_start();

/**
 * Emit one JSON answer and stop.
 *
 * @param int   $code
 * @param array $payload
 * @return void
 */
function bqd_status_reply($code, array $payload)
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code($code);
    header('Content-Type: application/json');
    header('Cache-Control: no-store');
    echo json_encode($payload);
    exit();
}

$root = __DIR__ . '/../../../..';

if (!file_exists($root . '/pp-config.php') || file_exists($root . '/maintenance.lock')) {
    bqd_status_reply(503, array('status' => false, 'message' => 'Unavailable'));
}

$paymentId = isset($_GET['id']) ? (string) $_GET['id'] : '';
if (!preg_match('/^[a-f0-9]{32}$/i', $paymentId)) {
    bqd_status_reply(400, array('status' => false, 'message' => 'Invalid payment'));
}

try {
    include_once($root . '/pp-include/pp-controller.php');

    $transaction = pp_get_transation($paymentId);
    if (($transaction['status'] ?? false) !== true) {
        bqd_status_reply(404, array('status' => false, 'message' => 'Unknown payment'));
    }

    // Only the status leaves this endpoint; nothing about the customer or amount.
    bqd_status_reply(200, array(
        'status' => true,
        'transaction_status' => (string) ($transaction['response'][0]['transaction_status'] ?? ''),
    ));
} catch (Throwable $e) {
    error_log('bangla-qr-dynamic status: ' . $e->getMessage());
    bqd_status_reply(503, array('status' => false, 'message' => 'Unavailable'));
}
