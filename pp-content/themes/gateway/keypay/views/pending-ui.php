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
        <div class="actions">
            <button type="button" class="btn-main" onclick="window.location.reload()">
                <i class="fas fa-rotate"></i> Refresh Status
            </button>
            <?php if ($hasRedirect): ?>
            <form action="<?php echo htmlspecialchars($redirectUrl); ?>" method="<?php echo $returnType; ?>">
                <input type="hidden" name="pp_id" value="<?php echo htmlspecialchars($transaction_details['response'][0]['pp_id'] ?? ''); ?>">
                <button type="submit" class="btn-secondary"><i class="fas fa-arrow-left"></i> Back to Website</button>
            </form>
            <?php else: ?>
            <a href="<?php echo htmlspecialchars(keypay_status_back_url('')); ?>" class="btn-secondary"><i class="fas fa-arrow-left"></i> Back to Website</a>
            <?php endif; ?>
        </div>

        <script>
            // Stay here and re-check: once the payment is approved this page becomes
            // the success page, which sends the customer back with pp_id.
            (function() {
                var remaining = 15;
                var msg = document.createElement('p');
                msg.className = 'note';
                document.querySelector('.actions').after(msg);

                var tick = function() {
                    msg.textContent = 'Checking status again in ' + remaining + ' seconds...';
                    if (remaining-- <= 0) {
                        window.location.reload();
                        return;
                    }
                    setTimeout(tick, 1000);
                };
                tick();
            })();
        </script>
<?php
    keypay_status_close();
?>
