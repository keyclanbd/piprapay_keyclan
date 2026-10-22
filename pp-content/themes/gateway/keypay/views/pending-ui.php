<?php
    if (!defined('pp_allowed_access')) {
        die('Direct access not allowed');
    }

    require_once __DIR__ . '/status-layout.php';

    $row = $transaction_details['response'][0];
    $summary = keypay_status_summary($transaction_details);

    keypay_status_open($setting, 'Payment Pending', 'pending', 'fa-clock', 'Payment Pending',
        'Your payment is under review. Please wait for confirmation.',
        ['Method' => $summary['method'], 'Total' => $summary['total']]);

    // The merchant receives pp_id and can show the order as pending.
    keypay_status_redirect($row['transaction_redirect_url'] ?? '', $row['transaction_return_type'] ?? 'GET', $row['pp_id'] ?? '');

    keypay_status_close();
?>
