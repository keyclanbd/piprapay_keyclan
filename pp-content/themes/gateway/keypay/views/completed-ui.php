<?php
    if (!defined('pp_allowed_access')) {
        die('Direct access not allowed');
    }

    require_once __DIR__ . '/status-layout.php';

    $summary = keypay_status_summary($transaction_details);
    $redirectUrl = $transaction_details['response'][0]['transaction_redirect_url'] ?? '';
    $hasRedirect = $redirectUrl !== '' && $redirectUrl !== '--';
    $returnType = strtoupper($transaction_details['response'][0]['transaction_return_type'] ?? 'GET') === 'POST' ? 'POST' : 'GET';

    keypay_status_open($setting, 'Payment Completed', 'success', 'fa-check', 'Payment Successful',
        'Your payment has been processed successfully.',
        ['Method' => $summary['method'], 'Total Paid' => $summary['total']]);
?>
        <div class="actions">
            <?php if ($hasRedirect): ?>
            <form action="<?php echo htmlspecialchars($redirectUrl); ?>" method="<?php echo $returnType; ?>" id="redirect-form">
                <input type="hidden" name="pp_id" value="<?php echo htmlspecialchars($transaction_details['response'][0]['pp_id'] ?? ''); ?>">
                <button type="submit" class="btn-main"><i class="fas fa-arrow-right"></i> Back to Website</button>
            </form>
            <?php else: ?>
            <a href="<?php echo htmlspecialchars(keypay_status_back_url('')); ?>" class="btn-main"><i class="fas fa-arrow-right"></i> Back to Website</a>
            <?php endif; ?>
        </div>

        <script>
            (function() {
                // Submit the same form as the button, so the merchant gets pp_id with its chosen method.
                var form = document.getElementById('redirect-form');
                if (!form) return;
                var remaining = 4;
                var msg = document.createElement('p');
                msg.className = 'note';
                document.querySelector('.actions').after(msg);

                var tick = function() {
                    msg.textContent = 'Redirecting in ' + remaining + ' seconds...';
                    if (remaining-- <= 0) {
                        form.submit();
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
