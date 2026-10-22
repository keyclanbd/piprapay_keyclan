<?php
    if (!defined('pp_allowed_access')) {
        die('Direct access not allowed');
    }

    require_once __DIR__ . '/status-layout.php';

    $summary = keypay_status_summary($transaction_details);
    $backUrl = keypay_status_back_url($transaction_details['response'][0]['transaction_cancel_url'] ?? '');

    keypay_status_open($setting, 'Payment Failed', 'failed', 'fa-xmark', 'Payment Failed',
        'We could not complete your payment.',
        ['Method' => $summary['method'], 'Total' => $summary['total']]);
?>
        <div class="actions">
            <a href="<?php echo htmlspecialchars($backUrl); ?>" class="btn-main"><i class="fas fa-arrow-left"></i> Back to Website</a>
        </div>
<?php
    keypay_status_close();
?>
