<?php
    if (!defined('pp_allowed_access')) {
        die('Direct access not allowed');
    }

    $merchantName = $setting['response'][0]['site_name'] ?? 'PipraPay';
    $merchantLogo = isset($setting['response'][0]['favicon']) && $setting['response'][0]['favicon'] !== '--'
        ? $setting['response'][0]['favicon']
        : 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8Xw8AAoMBgDTD2qgAAAAASUVORK5CYII=';
    $currency = $transaction_details['response'][0]['transaction_currency'] ?? '';
    $currencySymbolMap = [
        'BDT' => '৳', 'TK' => '৳', 'TKA' => '৳',
        'USD' => '$', 'EUR' => '€', 'GBP' => '£',
        'INR' => '₹', 'JPY' => '¥', 'CNY' => '¥',
        'SAR' => '﷼', 'AED' => 'د.إ', 'PKR' => '₨', 'NPR' => '₨',
    ];
    $currency = $currencySymbolMap[strtoupper(trim($currency))] ?? $currency;
    $subtotal = number_format((float)($transaction_details['response'][0]['transaction_amount'] ?? 0), 2);
    $fee = number_format((float)($transaction_details['response'][0]['transaction_fee'] ?? 0), 2);
    $total = number_format((float)($transaction_details['response'][0]['transaction_amount'] ?? 0) + (float)($transaction_details['response'][0]['transaction_fee'] ?? 0), 2);
    $redirectUrl = $transaction_details['response'][0]['transaction_redirect_url'] ?? '';
    $returnType = strtoupper($transaction_details['response'][0]['transaction_return_type'] ?? 'GET') === 'POST' ? 'POST' : 'GET';
    $paymentMethod = $transaction_details['response'][0]['payment_method'] ?? 'Unknown';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Completed - <?php echo htmlspecialchars($merchantName); ?></title>
    <link rel="icon" type="image/x-icon" href="<?php echo htmlspecialchars($merchantLogo); ?>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body{margin:0;font-family:'Inter',sans-serif;-webkit-font-smoothing:antialiased;background:#f4f7fb;color:#101828;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px}
        .rowx strong{color:#101828}
        .status-card{width:100%;max-width:560px;background:#fff;border-radius:20px;box-shadow:0 24px 60px rgba(16,24,40,.08);overflow:hidden}
        .status-head{padding:18px 22px;border-bottom:1px solid #edf2f7;display:flex;align-items:center;justify-content:space-between;gap:16px}
        .brand{display:flex;align-items:center;gap:10px}
        .brand img{width:34px;height:34px;object-fit:contain;border-radius:8px}
        .title{font-size:18px;font-weight:800;margin:0}
        .status-body{padding:28px 22px}
        .badge-icon{width:74px;height:74px;border-radius:50%;display:flex;align-items:center;justify-content:center;background:#e8fff3;color:#12b76a;font-size:30px;margin:0 auto 18px}
        .headline{text-align:center;font-size:28px;font-weight:900;margin:0}
        .sub{text-align:center;color:#667085;margin:8px 0 22px}
        .summary{border:1px solid #edf2f7;border-radius:16px;padding:16px;margin-bottom:18px;background:#fafbff}
        .rowx{display:flex;justify-content:space-between;gap:12px;padding:8px 0;font-size:14px;color:#475467}
        .actions{display:grid;gap:12px;margin-top:18px}
        .btn-main,.btn-secondary{width:100%;min-height:48px;border-radius:14px;font-weight:800;border:none;text-decoration:none;display:inline-flex;align-items:center;justify-content:center;gap:8px}
        .btn-main{background:linear-gradient(180deg,#6C63FF 0%,#5B52F5 100%);color:#fff}
        .btn-secondary{background:#fff;color:#6C63FF;border:1px solid #d6dcff}
        .footer{padding:16px 22px;border-top:1px solid #edf2f7;color:#667085;font-size:12px;display:flex;justify-content:center;text-align:center;gap:12px;flex-wrap:wrap}
        @media (max-width: 520px){body{padding:0}.status-card{min-height:100vh;border-radius:0}}
    </style>
</head>
<body>
<div class="status-card">
    <div class="status-head">
        <div class="brand"><img src="<?php echo htmlspecialchars($merchantLogo); ?>" alt="logo"><div><p class="title"><?php echo htmlspecialchars($merchantName); ?></p></div></div>
    </div>
    <div class="status-body">
        <div class="badge-icon"><i class="fas fa-check"></i></div>
        <h1 class="headline">Payment Successful</h1>
        <p class="sub">Your payment has been processed successfully.</p>
        <div class="summary">
            <div class="rowx"><span>Method</span><strong><?php echo htmlspecialchars($paymentMethod); ?></strong></div>
            <div class="rowx"><span>Total Paid</span><strong><?php echo htmlspecialchars($total . ' ' . $currency); ?></strong></div>
        </div>
        <?php if ($redirectUrl !== '' && $redirectUrl !== '--'): ?>
        <form action="<?php echo htmlspecialchars($redirectUrl); ?>" method="<?php echo htmlspecialchars($returnType); ?>" id="redirect-form">
            <input type="hidden" name="pp_id" value="<?php echo htmlspecialchars($transaction_details['response'][0]['pp_id'] ?? ''); ?>">
            <div class="actions">
                <button type="submit" class="btn-main"><i class="fas fa-arrow-right"></i> Back to website</button>
            </div>
        </form>
        <?php endif; ?>

        <script>
            (function() {
                // Submit the same form as the button, so the merchant gets pp_id with its chosen method.
                var form = document.getElementById('redirect-form');
                if (!form) return;
                var delay = 4000;
                var remaining = Math.ceil(delay / 1000);
                var msg = document.createElement('p');
                msg.style.cssText = 'color:#667085;font-size:13px;margin-top:12px;text-align:center';
                msg.textContent = 'Redirecting in ' + remaining + ' seconds...';
                var actionsContainer = document.querySelector('.actions');
                if (actionsContainer) actionsContainer.after(msg);

                var interval = setInterval(function() {
                    remaining--;
                    if (remaining > 0) {
                        msg.textContent = 'Redirecting in ' + remaining + ' seconds...';
                    } else {
                        clearInterval(interval);
                        form.submit();
                    }
                }, 1000);
            })();
        </script>
    </div>
    <div class="footer">
        <span>Powered by KeyClan</span>
    </div>
</div>
</body>
</html>
