<?php
    if (!defined('pp_allowed_access')) {
        die('Direct access not allowed');
    }

    // Shared shell for the completed, pending, failed and refunded pages so they
    // stay visually identical: same font, header, card, buttons and footer.

    if (!function_exists('keypay_status_open')) {
        function keypay_status_open($setting, $pageTitle, $tone, $icon, $headline, $subline, $rows) {
            $merchantName = $setting['response'][0]['site_name'] ?? 'PipraPay';
            $favicon = $setting['response'][0]['favicon'] ?? '--';
            $merchantLogo = ($favicon !== '' && $favicon !== '--') ? $favicon : '';
            $initial = function_exists('mb_substr') ? mb_strtoupper(mb_substr($merchantName, 0, 1)) : strtoupper(substr($merchantName, 0, 1));

            $tones = [
                'success' => ['#e8fff3', '#12b76a'],
                'pending' => ['#fff8e6', '#f79009'],
                'failed' => ['#ffecec', '#f04438'],
                'refunded' => ['#eff6ff', '#2e90fa'],
            ];
            [$toneBg, $toneFg] = $tones[$tone] ?? $tones['pending'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle . ' - ' . $merchantName); ?></title>
    <?php if ($merchantLogo !== ''): ?><link rel="icon" href="<?php echo htmlspecialchars($merchantLogo); ?>"><?php endif; ?>
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" media="print" onload="this.media='all'"><noscript><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"></noscript>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet" media="print" onload="this.media='all'"><noscript><link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet"></noscript>
    <style>
        *{box-sizing:border-box}
        body{margin:0;font-family:'Inter',system-ui,sans-serif;-webkit-font-smoothing:antialiased;background:#f4f7fb;color:#101828;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px}
        .status-card{width:100%;max-width:560px;background:#fff;border-radius:20px;box-shadow:0 24px 60px rgba(16,24,40,.08);overflow:hidden}
        .status-head{padding:18px 22px;border-bottom:1px solid #edf2f7;display:flex;align-items:center;gap:10px}
        .brand-logo,.brand-initial{width:34px;height:34px;border-radius:8px;flex:0 0 auto}
        .brand-logo{object-fit:contain}
        .brand-initial{display:flex;align-items:center;justify-content:center;background:#efeeff;color:#6C63FF;font-weight:800;font-size:16px}
        .title{font-size:18px;font-weight:800;margin:0}
        .status-body{padding:28px 22px}
        .badge-icon{width:74px;height:74px;border-radius:50%;display:flex;align-items:center;justify-content:center;background:<?php echo $toneBg; ?>;color:<?php echo $toneFg; ?>;font-size:30px;margin:0 auto 18px}
        .headline{text-align:center;font-size:28px;font-weight:900;margin:0}
        .sub{text-align:center;color:#667085;margin:8px 0 22px;line-height:1.5}
        .summary{border:1px solid #edf2f7;border-radius:16px;padding:16px;margin-bottom:18px;background:#fafbff}
        .rowx{display:flex;justify-content:space-between;gap:12px;padding:8px 0;font-size:14px;color:#475467}
        .rowx strong{color:#101828;text-align:right}
        .actions{display:grid;gap:12px;margin-top:18px}
        .actions form{margin:0}
        .btn-main,.btn-secondary{width:100%;min-height:48px;border-radius:14px;font:800 15px 'Inter',system-ui,sans-serif;text-decoration:none;display:inline-flex;align-items:center;justify-content:center;gap:8px;cursor:pointer}
        .btn-main{border:none;background:linear-gradient(180deg,#6C63FF 0%,#5B52F5 100%);color:#fff}
        .btn-secondary{background:#fff;color:#6C63FF;border:1px solid #d6dcff}
        .note{color:#667085;font-size:13px;margin:12px 0 0;text-align:center}
        .notice{display:flex;gap:10px;align-items:flex-start;border:1px solid #fde7b0;background:#fffaeb;color:#93370d;border-radius:14px;padding:12px 14px;font-size:13px;line-height:1.5;margin-bottom:6px}
        .notice i{margin-top:2px;color:#f79009}
        .footer{padding:16px 22px;border-top:1px solid #edf2f7;color:#667085;font-size:12px;text-align:center}
        @media (max-width: 520px){body{padding:0}.status-card{min-height:100vh;border-radius:0}}
    </style>
</head>
<body>
<div class="status-card">
    <div class="status-head">
        <?php if ($merchantLogo !== ''): ?>
        <img class="brand-logo" src="<?php echo htmlspecialchars($merchantLogo); ?>" alt="" onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
        <div class="brand-initial" style="display:none"><?php echo htmlspecialchars($initial); ?></div>
        <?php else: ?>
        <div class="brand-initial"><?php echo htmlspecialchars($initial); ?></div>
        <?php endif; ?>
        <p class="title"><?php echo htmlspecialchars($merchantName); ?></p>
    </div>
    <div class="status-body">
        <div class="badge-icon"><i class="fas <?php echo htmlspecialchars($icon); ?>"></i></div>
        <h1 class="headline"><?php echo htmlspecialchars($headline); ?></h1>
        <p class="sub"><?php echo htmlspecialchars($subline); ?></p>
        <div class="summary">
            <?php foreach ($rows as $label => $value): ?>
            <div class="rowx"><span><?php echo htmlspecialchars($label); ?></span><strong><?php echo htmlspecialchars($value); ?></strong></div>
            <?php endforeach; ?>
        </div>
<?php
        }

        function keypay_status_close() {
?>
    </div>
    <div class="footer">Powered by KeyClan</div>
</div>
</body>
</html>
<?php
        }

        // Amount and method shown in the summary box of every status page.
        function keypay_status_summary($transaction_details) {
            $row = $transaction_details['response'][0] ?? [];
            $currency = $row['transaction_currency'] ?? '';
            $symbols = [
                'BDT' => '৳', 'TK' => '৳', 'TKA' => '৳',
                'USD' => '$', 'EUR' => '€', 'GBP' => '£',
                'INR' => '₹', 'JPY' => '¥', 'CNY' => '¥',
                'SAR' => '﷼', 'AED' => 'د.إ', 'PKR' => '₨', 'NPR' => '₨',
            ];
            $currency = $symbols[strtoupper(trim($currency))] ?? $currency;
            $total = number_format((float) ($row['transaction_amount'] ?? 0) + (float) ($row['transaction_fee'] ?? 0), 2);

            return [
                'method' => (string) ($row['payment_method'] ?? 'Unknown'),
                'total' => $total . ' ' . $currency,
            ];
        }

        // "Don't close this page" notice, the Back to Website button and the automatic
        // redirect, identical on every status page. With $ppId set, the button and the
        // redirect post pp_id to the merchant using its return method.
        function keypay_status_redirect($url, $returnType = 'GET', $ppId = '') {
            $hasUrl = ($url !== '' && $url !== '--');
            $target = keypay_status_back_url($url);
            $method = strtoupper((string) $returnType) === 'POST' ? 'POST' : 'GET';
?>
        <div class="notice" role="status">
            <i class="fas fa-circle-info"></i>
            <span>Please don't close this page. You will be redirected to the website automatically. Closing it early may lose your payment status.</span>
        </div>

        <div class="actions">
            <?php if ($hasUrl && $ppId !== ''): ?>
            <form action="<?php echo htmlspecialchars($target); ?>" method="<?php echo $method; ?>" id="redirect-form">
                <input type="hidden" name="pp_id" value="<?php echo htmlspecialchars($ppId); ?>">
                <button type="submit" class="btn-main"><i class="fas fa-arrow-left"></i> Back to Website</button>
            </form>
            <?php else: ?>
            <a href="<?php echo htmlspecialchars($target); ?>" class="btn-main" id="redirect-link"><i class="fas fa-arrow-left"></i> Back to Website</a>
            <?php endif; ?>
        </div>
        <p class="note" id="redirect-timer" aria-live="polite">Redirecting in 5 seconds...</p>

        <script>
            (function() {
                var remaining = 5;
                var timer = document.getElementById('redirect-timer');
                var tick = setInterval(function() {
                    remaining--;
                    if (remaining > 0) {
                        timer.textContent = 'Redirecting in ' + remaining + (remaining === 1 ? ' second...' : ' seconds...');
                        return;
                    }
                    clearInterval(tick);
                    timer.textContent = 'Redirecting...';
                    var form = document.getElementById('redirect-form');
                    var link = document.getElementById('redirect-link');
                    if (form) form.submit();
                    else if (link) window.location.href = link.href;
                }, 1000);
            })();
        </script>
<?php
        }

        // Where "Back to Website" goes: the merchant URL if one was given, else this site.
        function keypay_status_back_url($url) {
            $url = (string) $url;
            return ($url !== '' && $url !== '--') ? $url : 'https://' . ($_SERVER['HTTP_HOST'] ?? '');
        }
    }
?>
