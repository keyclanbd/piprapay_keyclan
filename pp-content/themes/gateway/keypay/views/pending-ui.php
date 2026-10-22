<?php
    if (!defined('pp_allowed_access')) {
        die('Direct access not allowed');
    }

    require_once __DIR__ . '/status-layout.php';

    $summary = keypay_status_summary($transaction_details);
    $redirectUrl = $transaction_details['response'][0]['transaction_redirect_url'] ?? '';
    $hasRedirect = $redirectUrl !== '' && $redirectUrl !== '--';
    $returnType = strtoupper($transaction_details['response'][0]['transaction_return_type'] ?? 'GET') === 'POST' ? 'POST' : 'GET';

    keypay_status_open($setting, 'Payment Pending', 'pending', 'fa-clock', 'Payment Pending',
        'Your payment is under review. Please wait for confirmation.',
        ['Method' => $summary['method'], 'Total' => $summary['total']]);
?>
        <div class="notice" role="status">
            <i class="fas fa-circle-info"></i>
            <span>Please don't close this page. You will be redirected to the website automatically. Closing it early may lose your payment status.</span>
        </div>

        <div class="actions">
            <?php if ($hasRedirect): ?>
            <form action="<?php echo htmlspecialchars($redirectUrl); ?>" method="<?php echo $returnType; ?>" id="redirect-form">
                <input type="hidden" name="pp_id" value="<?php echo htmlspecialchars($transaction_details['response'][0]['pp_id'] ?? ''); ?>">
                <button type="submit" class="btn-main"><i class="fas fa-arrow-left"></i> Back to Website</button>
            </form>
            <?php else: ?>
            <a href="<?php echo htmlspecialchars(keypay_status_back_url('')); ?>" class="btn-main" id="redirect-link"><i class="fas fa-arrow-left"></i> Back to Website</a>
            <?php endif; ?>
        </div>

        <script>
            // Send the customer back to the website; the merchant receives pp_id and can show the pending order.
            setTimeout(function() {
                var form = document.getElementById('redirect-form');
                var link = document.getElementById('redirect-link');
                if (form) form.submit();
                else if (link) window.location.href = link.href;
            }, 5000);
        </script>
<?php
    keypay_status_close();
?>
