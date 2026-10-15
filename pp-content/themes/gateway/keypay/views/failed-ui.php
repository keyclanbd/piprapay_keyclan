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
    $redirectUrl = $transaction_details['response'][0]['transaction_cancel_url'] ?? '';
    $paymentMethod = $transaction_details['response'][0]['payment_method'] ?? 'Unknown';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Failed - <?php echo htmlspecialchars($merchantName); ?></title>
    <link rel="icon" type="image/x-icon" href="<?php echo htmlspecialchars($merchantLogo); ?>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body{margin:0;font-family:Inter,system-ui,sans-serif;background:#f4f7fb;color:#101828;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px}
        .status-card{width:100%;max-width:560px;background:#fff;border-radius:20px;box-shadow:0 24px 60px rgba(16,24,40,.08);overflow:hidden}
        .status-body{padding:28px 22px;text-align:center}
        .badge-icon{width:74px;height:74px;border-radius:50%;display:flex;align-items:center;justify-content:center;background:#ffecec;color:#f04438;font-size:30px;margin:0 auto 18px}
        .headline{font-size:28px;font-weight:900;margin:0}
        .sub{color:#667085;margin:8px 0 22px}
        .summary{border:1px solid #edf2f7;border-radius:16px;padding:16px;margin-bottom:18px;background:#fafbff;text-align:left}
        .rowx{display:flex;justify-content:space-between;gap:12px;padding:8px 0;font-size:14px;color:#475467}
        .rowx strong{color:#101828}
        .actions{display:grid;gap:12px;margin-top:18px}
        .btn-main,.btn-secondary{width:100%;min-height:48px;border-radius:14px;font-weight:800;border:none;text-decoration:none;display:inline-flex;align-items:center;justify-content:center;gap:8px}
        .btn-main{background:linear-gradient(180deg,#6C63FF 0%,#5B52F5 100%);color:#fff}
        .btn-secondary{background:#fff;color:#6C63FF;border:1px solid #d6dcff}
    </style>
</head>
<body>
<div class="status-card">
    <div class="status-body">
        <div class="badge-icon"><i class="fas fa-xmark"></i></div>
        <h1 class="headline">Payment Failed</h1>
        <p class="sub">We could not complete your payment.</p>
        <div class="summary">
            <div class="rowx"><span>Method</span><strong><?php echo htmlspecialchars($paymentMethod); ?></strong></div>
            <div class="rowx"><span>Total</span><strong><?php echo htmlspecialchars($total . ' ' . $currency); ?></strong></div>
        </div>
        <div class="actions">
            <?php if ($redirectUrl !== '' && $redirectUrl !== '--'): ?>
            <a href="<?php echo htmlspecialchars($redirectUrl); ?>" class="btn-main"><i class="fas fa-arrow-left"></i> Try Again</a>
            <?php endif; ?>
            <button type="button" class="btn-secondary" onclick="window.history.back()"><i class="fas fa-rotate-left"></i> Go Back</button>
        </div>
    </div>
</div>
</body>
</html>
