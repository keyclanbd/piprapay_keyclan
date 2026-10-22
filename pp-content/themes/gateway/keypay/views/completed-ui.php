<?php
    if (!defined('pp_allowed_access')) {
        die('Direct access not allowed');
    }

    require_once __DIR__ . '/status-layout.php';

    $row = $transaction_details['response'][0];
    $summary = keypay_status_summary($transaction_details);

    keypay_status_open($setting, 'Payment Completed', 'success', 'fa-check', 'Payment Successful',
        'Your payment has been processed successfully.',
        ['Method' => $summary['method'], 'Total Paid' => $summary['total']]);

    keypay_status_redirect($row['transaction_redirect_url'] ?? '', $row['transaction_return_type'] ?? 'GET', $row['pp_id'] ?? '');

    keypay_status_close();
?>
