<?php
    if (!defined('pp_allowed_access')) {
        die('Direct access not allowed');
    }

    require_once __DIR__ . '/status-layout.php';

    $row = $transaction_details['response'][0];
    $summary = keypay_status_summary($transaction_details);

    keypay_status_open($setting, 'Payment Refunded', 'refunded', 'fa-rotate-left', 'Payment Refunded',
        'This transaction has been refunded.',
        ['Method' => $summary['method'], 'Total' => $summary['total']]);

    keypay_status_redirect($row['transaction_redirect_url'] ?? '', $row['transaction_return_type'] ?? 'GET', $row['pp_id'] ?? '');

    keypay_status_close();
?>
