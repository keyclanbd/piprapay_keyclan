<?php
    if (!defined('pp_allowed_access')) {
        die('Direct access not allowed');
    }

    $landingSettings = $global_setting_response['response'][0] ?? [];
    $landingName = trim((string) ($landingSettings['site_name'] ?? ''));
    if ($landingName === '' || $landingName === '--') {
        $landingName = 'Secure Payments';
    }
    $landingFavicon = trim((string) ($landingSettings['favicon'] ?? ''));
    if (!preg_match('#^https?://#i', $landingFavicon)) {
        $landingFavicon = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8Xw8AAoMBgDTD2qgAAAAASUVORK5CYII=';
    }
    $landingSupportEmail = trim((string) ($landingSettings['support_email_address'] ?? ''));
    if (filter_var($landingSupportEmail, FILTER_VALIDATE_EMAIL) === false) {
        $landingSupportEmail = '';
    }
    $landingEscape = function ($value) {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    };
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?php echo $landingEscape($landingName); ?></title>
    <link rel="icon" href="<?php echo $landingEscape($landingFavicon); ?>">
    <style>
        :root {
            --bg: #f4f6f8;
            --card: #ffffff;
            --text: #16202c;
            --muted: #5d6b7a;
            --line: #e3e8ee;
            --accent: #1f9d6b;
            --accent-soft: #e4f5ee;
        }
        @media (prefers-color-scheme: dark) {
            :root {
                --bg: #0f151c;
                --card: #18212b;
                --text: #eef2f6;
                --muted: #9dabb9;
                --line: #2a3644;
                --accent: #3ccb92;
                --accent-soft: #17352b;
            }
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px 16px;
            background: var(--bg);
            color: var(--text);
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            line-height: 1.55;
        }
        main {
            width: 100%;
            max-width: 440px;
            padding: 40px 32px 32px;
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: 16px;
            text-align: center;
        }
        .badge {
            width: 60px;
            height: 60px;
            margin: 0 auto 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            background: var(--accent-soft);
            color: var(--accent);
        }
        h1 { margin: 0 0 10px; font-size: 24px; line-height: 1.25; }
        p { margin: 0; color: var(--muted); font-size: 15px; }
        .steps {
            margin: 26px 0 0;
            padding: 18px 0 0;
            list-style: none;
            border-top: 1px solid var(--line);
            text-align: left;
        }
        .steps li { display: flex; gap: 10px; padding: 6px 0; color: var(--muted); font-size: 14px; }
        .steps svg { flex: none; margin-top: 3px; color: var(--accent); }
        .notice {
            margin-top: 20px;
            padding: 16px;
            background: var(--bg);
            border: 1px solid var(--line);
            border-radius: 10px;
            text-align: left;
        }
        .notice h2 { margin: 0 0 6px; font-size: 14px; }
        .notice p { font-size: 13px; }
        .notice p + p { margin-top: 8px; }
        .notice a { color: var(--accent); overflow-wrap: anywhere; }
        footer { margin-top: 22px; color: var(--muted); font-size: 13px; }
    </style>
</head>
<body>
    <main>
        <div class="badge" aria-hidden="true">
            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>
        </div>
        <h1>Secure payment portal</h1>
        <p>Payments here open through a private link. To pay, please use the link you received from the merchant.</p>
        <ul class="steps">
            <li><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 13l4 4L19 7"/></svg>Your payment link opens the checkout directly</li>
            <li><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 13l4 4L19 7"/></svg>Payment details travel over an encrypted connection</li>
            <li><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 13l4 4L19 7"/></svg>Lost your link? Ask the merchant to send it again</li>
        </ul>
        <section class="notice">
            <h2>Security research &amp; bug bounty</h2>
            <p>Want to test this system? Please contact our support team<?php if ($landingSupportEmail !== '') { ?> at <a href="mailto:<?php echo $landingEscape($landingSupportEmail); ?>"><?php echo $landingEscape($landingSupportEmail); ?></a><?php } ?> and get written permission before you start. If you find a vulnerability, report it to us privately and give us time to fix it. We will acknowledge valid reports.</p>
            <p>Testing without permission is not allowed. Attacks and attempts to interfere with this system are logged and may be reported to the authorities.</p>
        </section>
        <footer>&copy; <?php echo date('Y'); ?> <?php echo $landingEscape($landingName); ?></footer>
    </main>
</body>
</html>
