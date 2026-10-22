<?php
    if (!defined('pp_allowed_access')) {
        die('Direct access not allowed');
    }

    require_once __DIR__ . '/status-layout.php';

    $row = $transaction_details['response'][0];
    $summary = keypay_status_summary($transaction_details);

    keypay_status_open($setting, 'Payment Failed', 'failed', 'fa-xmark', 'Payment Failed',
        'We could not complete your payment.',
        ['Method' => $summary['method'], 'Total' => $summary['total']]);

    // Failed payments return to the merchant's cancel page.
    keypay_status_redirect($row['transaction_cancel_url'] ?? '');

    keypay_status_close();
?>
