<?php
    if (!defined('pp_allowed_access')) {
        die('Direct access not allowed');
    }

    $merchantLogo = isset($setting['response'][0]['favicon']) && $setting['response'][0]['favicon'] !== '--'
        ? $setting['response'][0]['favicon']
        : 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8Xw8AAoMBgDTD2qgAAAAASUVORK5CYII=';
    $merchantName = $setting['response'][0]['site_name'] ?? 'PipraPay';
    $currencyCode = strtoupper(trim($transaction_details['response'][0]['transaction_currency'] ?? 'USD'));
    $currencySymbolMap = [
        'BDT' => '৳',
        'TK' => '৳',
        'TKA' => '৳',
        'USD' => '$',
        'EUR' => '€',
        'GBP' => '£',
        'INR' => '₹',
        'JPY' => '¥',
        'CNY' => '¥',
        'SAR' => '﷼',
        'AED' => 'د.إ',
        'PKR' => '₨',
        'NPR' => '₨',
        'BDT ' => '৳',
    ];
    $currency = $currencySymbolMap[$currencyCode] ?? $currencyCode;
    $subtotalValue = (float)($transaction_details['response'][0]['transaction_amount'] ?? 0);
    $feeValue = (float)($transaction_details['response'][0]['transaction_fee'] ?? 0);
    $totalValue = $subtotalValue + $feeValue;
    $amountDue = number_format($totalValue, 2);
    $subtotal = number_format($subtotalValue, 2);
    $fee = number_format($feeValue, 2);
    $paymentId = $transaction_details['response'][0]['pp_id'] ?? '';
    $cancelUrl = $transaction_details['response'][0]['transaction_cancel_url'] ?? '';
    $selectedGatewayCharge = $feeValue;
    $defaultMethodCharge = $feeValue > 0 ? $feeValue : 10;
    $clientName = $transaction_details['response'][0]['c_name'] ?? '';
    $clientEmail = $transaction_details['response'][0]['c_email_mobile'] ?? '';
    $accent = '#6C63FF';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Secure Checkout - <?php echo htmlspecialchars($merchantName); ?></title>
    <link rel="icon" type="image/x-icon" href="<?php echo htmlspecialchars($merchantLogo); ?>">
    <meta name="theme-color" content="#f2f5fb">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #f2f5fb;
            --surface: #ffffff;
            --border: #e6eaf2;
            --text: #101828;
            --muted: #667085;
            --accent: <?php echo $accent; ?>;
            --shadow: 0 20px 50px rgba(15, 23, 42, 0.08);
            --radius: 18px;
        }

        * { box-sizing: border-box; }
        html, body {
            min-height: 100%;
            scrollbar-width: none;
            -ms-overflow-style: none;
        }

        html::-webkit-scrollbar,
        body::-webkit-scrollbar {
            display: none;
            width: 0;
            height: 0;
        }

        body {
            margin: 0;
            font-family: 'Inter', sans-serif;
            font-size: 14px;
            line-height: 1.5;
            background:
                radial-gradient(circle at top left, rgba(108, 99, 255, 0.08), transparent 28%),
                radial-gradient(circle at bottom right, rgba(108, 99, 255, 0.04), transparent 24%),
                var(--bg);
            color: var(--text);
            -webkit-font-smoothing: antialiased;
        }

        .checkout-shell {
            width: 100%;
            max-width: 850px;
            margin: 0 auto;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px 16px;
        }

        .checkout-grid {
            width: 100%;
            min-height: 650px;
            display: grid;
            grid-template-columns: minmax(0, 36fr) minmax(0, 64fr);
            background: var(--surface);
            box-shadow: var(--shadow);
            overflow: hidden;
            border-radius: 8px;
        }

        .checkout-grid > * {
            min-width: 0;
        }

        .left-panel,
        .right-panel {
            padding: 22px 22px 18px;
            position: relative;
            display: flex;
            flex-direction: column;
        }

        .right-head {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 16px;
            margin-bottom: 10px;
        }

        .panel-timer {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #000;
            font-size: 13px;
            font-weight: 800;
            white-space: nowrap;
            flex-shrink: 0;
            margin-top: 2px;
        }

        .left-panel {
            border-right: 1px solid var(--border);
            background: linear-gradient(180deg, #ffffff 0%, #fbfcff 100%);
        }

        .top-row {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 18px;
        }

        .offer-trigger {
            display: none;
            align-items: center;
            gap: 6px;
            border: 1px solid var(--border);
            border-radius: 999px;
            background: #fff;
            color: var(--accent);
            padding: 8px 11px;
            font: inherit;
            font-size: 12px;
            font-weight: 800;
            cursor: pointer;
            white-space: nowrap;
        }

        .offer-drawer {
            position: fixed;
            inset: 0;
            z-index: 1000;
            display: flex;
            align-items: flex-start;
            justify-content: center;
            padding: 12px;
            background: rgba(16, 24, 40, .38);
            opacity: 0;
            visibility: hidden;
            transition: opacity .2s ease, visibility .2s ease;
        }

        .offer-drawer.open {
            opacity: 1;
            visibility: visible;
        }

        .offer-drawer-panel {
            width: min(100%, 520px);
            margin-top: 0;
            padding: 20px;
            background: var(--surface);
            border-radius: 0 0 18px 18px;
            box-shadow: 0 18px 45px rgba(16, 24, 40, .18);
            transform: translateY(-110%);
            transition: transform .25s ease;
        }

        .offer-drawer.open .offer-drawer-panel {
            transform: translateY(0);
        }

        .offer-drawer-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 4px;
        }

        .offer-close {
            border: 0;
            background: transparent;
            color: var(--muted);
            font-size: 18px;
            cursor: pointer;
        }

        .brand-stack {
            display: flex;
            flex-direction: column;
            gap: 0;
            min-width: 0;
        }

        .top-left {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            min-width: 0;
        }

        .back-btn {
            margin-top: -2px;
        }

        .merchant-block {
            display: block;
            min-width: 0;
        }

        .merchant-head {
            display: flex;
            align-items: center;
            gap: 10px;
            min-width: 0;
        }

        .merchant-head .mobile-timer {
            align-self: center;
            margin-top: 0;
        }

        .merchant-logo {
            width: 34px;
            height: 34px;
            object-fit: contain;
            border-radius: 8px;
            margin-top: 1px;
        }

        .merchant-name {
            margin: 0;
            font-size: 20px;
            line-height: 1.1;
            font-weight: 800;
            letter-spacing: -0.03em;
            display: block;
        }

        .trx-row {
            margin-top: 3px;
            padding-left: 44px;
            color: #667085;
            font-size: 12px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .timer-pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 0;
            border-radius: 0;
            background: transparent;
            color: #000;
            border: none;
            font-size: 13px;
            font-weight: 800;
            letter-spacing: -0.01em;
            white-space: nowrap;
            flex-shrink: 0;
        }

        .timer-pill i {
            font-size: 12px;
        }

        .mobile-timer {
            display: none;
        }

        .back-btn {
            width: 30px;
            height: 30px;
            border: none;
            background: transparent;
            color: #667085;
            cursor: pointer;
            padding: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .merchant-block {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            gap: 2px;
            min-width: 0;
        }

        .merchant-head {
            display: flex;
            align-items: center;
            gap: 10px;
            min-width: 0;
        }

        .merchant-logo {
            width: 34px;
            height: 34px;
            object-fit: contain;
            border-radius: 8px;
        }

        .merchant-name {
            margin: 0;
            font-size: 18px;
            line-height: 1;
            font-weight: 800;
            letter-spacing: -0.03em;
        }

        .trx-row {
            margin-top: 0;
            padding-left: 44px;
            color: #667085;
            font-size: 12px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .copy-icon { font-size: 12px; opacity: .7; }

        .amount-card {
            background: linear-gradient(180deg, #f8faff 0%, #f5f8ff 100%);
            border: 1px solid #edf1f8;
            border-radius: 16px;
            padding: 16px;
            margin: 4px 0 20px;
            position: relative;
        }

        .amount-summary {
            padding-right: 36px;
        }

        .amount-toggle {
            display: none;
            position: absolute;
            top: 50%;
            right: 14px;
            width: 28px;
            height: 28px;
            border: 1px solid var(--border);
            border-radius: 50%;
            background: #fff;
            color: var(--accent);
            cursor: pointer;
            transform: translateY(-50%);
            transition: transform .2s ease, background .2s ease;
        }

        .amount-toggle[aria-expanded="true"] {
            transform: translateY(-50%) rotate(180deg);
            background: #f1efff;
        }

        .amount-breakdown {
            display: block;
        }

        .amount-breakdown.open {
            display: block;
        }

        .amount-label {
            color: #8a92a3;
            font-size: 10px;
            font-weight: 800;
            letter-spacing: 0.14em;
            text-transform: uppercase;
            margin-bottom: 6px;
        }

        .amount-value {
            font-size: 28px;
            line-height: 1.05;
            font-weight: 800;
            letter-spacing: -0.04em;
            color: #101828;
        }

        .line-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            padding: 10px 0;
            font-size: 13px;
            color: #5d6675;
        }

        .line-value { color: #101828; }

        .divider {
            height: 1px;
            background: #edf0f6;
            margin: 10px 0 12px;
        }

        .total-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 10px;
            font-size: 14px;
            font-weight: 800;
            color: #101828;
        }

        .section { margin-top: 22px; }
        .section-title {
            font-size: 14px;
            font-weight: 800;
            margin: 0;
            letter-spacing: -0.02em;
        }
        .section-subtitle {
            margin: 4px 0 0;
            color: var(--muted);
            font-size: 12px;
        }

        .offers-empty {
            text-align: center;
            padding: 38px 16px 10px;
            color: #c3cad7;
        }
        .offers-empty i {
            font-size: 34px;
            display: block;
            margin-bottom: 12px;
        }
        .offers-empty p {
            margin: 0;
            font-size: 14px;
            color: #8b93a5;
        }

        .bottom-links {
            display: flex;
            justify-content: space-between;
            margin-top: auto;
            padding-top: 24px;
            font-size: 12px;
            font-weight: 700;
        }
        .bottom-links a {
            color: #6c63ff;
            text-decoration: none;
        }

        .welcome {
            font-size: 18px;
            font-weight: 800;
            color: #98a2b3;
            letter-spacing: -0.03em;
            margin: 2px 0 22px;
        }

        .category-row {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 12px;
            margin-bottom: 22px;
        }

        .category-card {
            min-height: 88px;
            border: 1px solid var(--border);
            border-radius: 10px;
            background: #fff;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 10px;
            padding: 15px 5px;
            cursor: pointer;
            transition: all .2s ease;
            color: #6f7583;
            font-weight: 700;
            text-align: center;
        }

        .category-card i { font-size: 28px; }

        .category-card.active {
            border-color: var(--accent);
            box-shadow: 0 0 0 1px rgba(108, 99, 255, 0.12);
            color: var(--accent);
        }

        .methods-title {
            font-size: 14px;
            font-weight: 800;
            margin: 10px 0 18px;
            letter-spacing: -0.02em;
        }

        .methods-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            grid-auto-rows: 1fr;
            gap: 12px;
            margin-bottom: 20px;
            align-items: stretch;
        }

        .method-card {
            width: 100%;
            min-height: 84px;
            height: 100%;
            border: 1px solid var(--border);
            border-radius: 10px;
            background: #fff;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 4px;
            padding: 8px 6px;
            cursor: pointer;
            position: relative;
            overflow: hidden;
            transition: all 0.2s;
        }

        .method-card:hover {
            border-color: rgba(108, 99, 255, 0.18);
            box-shadow: 0 10px 22px rgba(24, 39, 75, 0.06);
            transform: translateY(-1px);
        }

        .method-card.selected {
            border-color: var(--accent);
            box-shadow: 0 0 0 1px rgba(108, 99, 255, 0.14), 0 10px 22px rgba(108, 99, 255, 0.10);
        }

        .method-card.unavailable {
            cursor: not-allowed;
            opacity: .48;
            background: #f8fafc;
            box-shadow: none;
            transform: none;
        }

        .method-card.unavailable:hover {
            border-color: var(--border);
            box-shadow: none;
            transform: none;
        }

        .method-card img {
            max-width: 100%;
            max-height: 24px;
            width: auto;
            height: 24px;
            object-fit: contain;
            flex-shrink: 0;
        }

        .method-badge {
            color: #64748b;
            font-size: 8px;
            font-weight: 800;
            line-height: 1.2;
            text-align: center;
            display: -webkit-box;
            -webkit-line-clamp: 1;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .method-name {
            color: #101828;
            font-size: 10px;
            font-weight: 700;
            line-height: 1.2;
            text-align: center;
            word-break: break-word;
            display: -webkit-box;
            -webkit-line-clamp: 1;
            -webkit-box-orient: vertical;
            overflow: hidden;
            max-width: 100%;
        }

        .method-card.converted .method-badge {
            color: var(--accent);
        }

        .method-card.unavailable .method-badge {
            color: #dc2626;
        }

        .pay-button {
            width: 100%;
            min-height: 54px;
            border: none;
            border-radius: 16px;
            background: linear-gradient(180deg, #6C63FF 0%, #5B52F5 100%);
            color: #fff;
            font-size: 14px;
            font-weight: 800;
            box-shadow: 0 16px 28px rgba(108, 99, 255, 0.25);
            transition: transform .18s ease, box-shadow .18s ease;
        }
        .pay-button:hover {
            transform: translateY(-1px);
            box-shadow: 0 18px 32px rgba(108, 99, 255, 0.28);
        }

        .pay-button:disabled {
            cursor: not-allowed;
            opacity: .55;
            box-shadow: none;
            transform: none;
        }

        .terms {
            color: #7a8291;
            font-size: 11px;
            line-height: 1.7;
            margin-top: 22px;
        }
        .terms a {
            color: var(--accent);
            font-weight: 700;
            text-decoration: none;
        }

        .footer-row {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            align-items: center;
            margin-top: auto;
            padding-top: 28px;
            color: #7a8291;
            font-size: 12px;
        }

        .footer-row .bottom-links {
            display: none;
        }

        .powered {
            display: flex;
            align-items: center;
            gap: 8px;
            font-style: normal;
            font-weight: 700;
        }

        .secure-badges {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
            justify-content: flex-end;
        }

        .secure-badges img {
            width: 76px;
            height: 18px;
            object-fit: fill;
        }

        .method-content { display: none; }
        .method-content.active { display: block; }

        @media (max-width: 980px) {
            .checkout-shell { align-items: stretch; padding: 16px; }
            .checkout-grid { grid-template-columns: 1fr; min-height: auto; }
            .left-panel {
                border-right: 0;
                border-bottom: 1px solid var(--border);
                padding-bottom: 14px;
            }
            .right-panel { padding-top: 14px; }
            .right-head { margin-bottom: 4px; }
            .amount-card { margin: 4px 0 10px; }
            .section { margin-top: 14px; }
            .offers-empty { padding: 18px 16px 4px; }
            .bottom-links { padding-top: 14px; }
        }

        @media (max-width: 640px) {
            .checkout-shell { padding: 0; }
            .checkout-grid {
                display: flex;
                flex-direction: column;
                align-items: stretch;
                gap: 0;
                border-radius: 0;
            }
            .left-panel {
                align-self: stretch;
                width: 100%;
                height: max-content;
                min-height: 0;
                padding-bottom: 12px;
            }
            .right-panel {
                align-self: stretch;
                width: 100%;
                min-height: 0;
                padding-top: 12px;
            }
            .left-panel, .right-panel { padding: 16px 16px; }
            .left-panel { padding-bottom: 10px; }
            .right-panel { padding-top: 10px; }
            .right-head { display: none; }
            .amount-card { margin: 4px 0 8px; }
            .left-panel > .bottom-links { display: none; }
            .footer-row .bottom-links {
                display: flex;
                width: 100%;
                order: -1;
                padding: 0 0 12px;
                margin: 0;
                border-bottom: 1px solid var(--border);
            }
            .top-row { align-items: center; }
            .offer-trigger { display: inline-flex; }
            .mobile-timer {
                display: inline-flex;
                align-items: center;
                align-self: center;
                margin: 0 0 0 8px;
            }
            .merchant-head .mobile-timer { margin-top: 0; }
            .right-head .panel-timer { display: none; }
            .left-panel > .section { display: none; }
            .amount-toggle { display: block; }
            .amount-breakdown { display: none; }
            .amount-breakdown.open { display: block; }
            .left-panel > .line-row,
            .left-panel > .divider,
            .left-panel > .total-row { display: none; }
            .category-row { grid-template-columns: repeat(auto-fit, minmax(0, 1fr)); gap: 6px; }
            .category-card { min-height: 66px; font-size: 11px; padding: 8px 3px; gap: 6px; }
            .category-card span { max-width: 100%; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
            .category-card i { font-size: 20px; }
            .methods-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px; }
            .method-card { min-height: 82px; height: 100%; padding: 6px 5px; gap: 3px; }
            .footer-row {
                display: grid;
                grid-template-columns: minmax(0, 1fr) auto;
                align-items: center;
                gap: 12px;
                width: 100%;
            }
            .footer-row .bottom-links {
                grid-column: 1 / -1;
                grid-row: 1;
                width: 100%;
            }
            .powered {
                grid-column: 1;
                grid-row: 2;
                min-width: 0;
                white-space: nowrap;
            }
            .secure-badges {
                grid-column: 2;
                grid-row: 2;
                width: auto;
                margin-left: 0;
                justify-content: flex-end;
                flex-wrap: nowrap;
            }
            .welcome { font-size: 22px; }
            .amount-value { font-size: 28px; }
            .timer-pill { align-self: flex-end; }
            .offers-empty { padding: 18px 12px 4px; }
            .offers-empty i { font-size: 24px; margin-bottom: 6px; }
            .section { margin-top: 12px; }
            .bottom-links { padding-top: 12px; margin-top: 12px; }
        }

        @media (max-width: 400px) {
            .category-row { grid-template-columns: repeat(auto-fit, minmax(0, 1fr)); gap: 5px; }
            .category-card { min-height: 60px; font-size: 10px; padding: 6px 2px; gap: 4px; }
            .category-card span { font-size: 10px; }
            .category-card i { font-size: 18px; }
            .methods-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .right-head { flex-direction: column; gap: 4px; }
            .timer-pill { align-self: flex-start; }
        }
    </style>
</head>
<body>
    <div class="checkout-shell">
        <div class="checkout-grid">
            <section class="left-panel">
                <div class="top-row">
                    <div class="top-left">
                        <button class="back-btn" type="button" aria-label="Back" data-cancel-url="<?php echo htmlspecialchars($cancelUrl); ?>">
                            <i class="fas fa-arrow-left"></i>
                        </button>
                        <div class="brand-stack">
                            <div class="merchant-block">
                                <div class="merchant-head">
                                    <img src="<?php echo htmlspecialchars($merchantLogo); ?>" alt="<?php echo htmlspecialchars($merchantName); ?>" class="merchant-logo">
                                    <p class="merchant-name">KeyClan</p>
                                    <div class="timer-pill panel-timer mobile-timer" id="mobile-checkout-timer">
                                        <i class="far fa-clock"></i>
                                        <span>10:00</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <button class="offer-trigger" type="button" aria-controls="offer-drawer" aria-expanded="false">
                        <i class="far fa-percent"></i> Offers
                    </button>
                </div>

                <div class="amount-card">
                    <div class="amount-summary">
                        <div class="amount-label">Payment Amount</div>
                        <div class="amount-value"><?php echo htmlspecialchars($amountDue . ' ' . $currency); ?></div>
                    </div>
                    <button class="amount-toggle" type="button" aria-label="Show payment breakdown" aria-controls="amount-breakdown" aria-expanded="false">
                        <i class="fas fa-chevron-down"></i>
                    </button>
                </div>

                <div class="amount-breakdown" id="amount-breakdown">
                <div class="line-row"><span>Subtotal</span><span class="line-value"><?php echo htmlspecialchars($subtotal . ' ' . $currency); ?></span></div>
                <div class="line-row"><span>Convenience Charge</span><span class="line-value"><?php echo htmlspecialchars($fee . ' ' . $currency); ?></span></div>
                <div class="divider"></div>
                <div class="total-row"><span>Total amount</span><span><?php echo htmlspecialchars($amountDue . ' ' . $currency); ?></span></div>
                </div>

                <div class="section">
                    <div class="section-title">Special Offers and Savings</div>
                    <div class="section-subtitle">Automatically Applied with Eligible Payments</div>
                    <div class="offers-empty">
                        <i class="far fa-percent"></i>
                        <p>No offers available for this transaction</p>
                    </div>
                </div>

                <div class="bottom-links">
                    <?php $footerSupportUrl = trim($setting['response'][0]['footer_support_url'] ?? ''); ?>
                    <?php $footerFaqUrl = trim($setting['response'][0]['footer_faq_url'] ?? ''); ?>
                    <?php $footerInformationUrl = trim($setting['response'][0]['footer_information_url'] ?? ''); ?>
                    <?php if ($footerSupportUrl !== '' && $footerSupportUrl !== '--'): ?>
                        <a href="<?php echo htmlspecialchars($footerSupportUrl); ?>" target="_blank" rel="noopener">Support</a>
                    <?php else: ?>
                        <a href="#support-tab" class="global-tab" data-tab="support-tab">Support</a>
                    <?php endif; ?>
                    <?php if ($footerFaqUrl !== '' && $footerFaqUrl !== '--'): ?>
                        <a href="<?php echo htmlspecialchars($footerFaqUrl); ?>" target="_blank" rel="noopener">FAQ</a>
                    <?php else: ?>
                        <a href="#help-tab" class="global-tab" data-tab="help-tab">FAQ</a>
                    <?php endif; ?>
                    <?php if ($footerInformationUrl !== '' && $footerInformationUrl !== '--'): ?>
                        <a href="<?php echo htmlspecialchars($footerInformationUrl); ?>" target="_blank" rel="noopener">Information</a>
                    <?php else: ?>
                        <a href="#information-tab" class="global-tab" data-tab="information-tab">Information</a>
                    <?php endif; ?>
                </div>
            </section>

            <section class="right-panel">
                <div class="right-head">
                    <div class="timer-pill panel-timer" id="checkout-timer">
                        <i class="far fa-clock"></i>
                        <span>10:00</span>
                    </div>
                </div>

                <div class="category-row">
                    <?php
                        $tabs = pp_get_gateway_category_tabs($payment_id, true, 'short');
                    ?>
                    <?php foreach ($tabs as $key => $tab): ?>
                        <?php if (!empty($tab['gateways']['response'])): ?>
                            <div class="global-tab category-card" data-tab="<?php echo htmlspecialchars($key); ?>">
                                <i class="<?php echo htmlspecialchars($tab['icon']); ?>"></i>
                                <span><?php echo htmlspecialchars($tab['title']); ?></span>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>

                <?php foreach ($tabs as $key => $tab): ?>
                    <?php if (!empty($tab['gateways']['response'])): ?>
                        <div class="method-content" id="<?php echo htmlspecialchars($key); ?>">
                            <div class="methods-title">Select a payment method</div>
                            <div class="methods-grid">
                                <?php foreach ($tab['gateways']['response'] as $gateway): ?>
                                    <?php
                                        $gatewayAvailable = ($gateway['is_available'] ?? true) === true;
                                        $gatewayConverted = ($gateway['is_converted'] ?? false) === true;
                                        $gatewayReason = $gateway['availability_reason'] ?? '';
                                    ?>
                                    <div class="method-card<?php echo !$gatewayAvailable ? ' unavailable' : ''; ?><?php echo $gatewayConverted ? ' converted' : ''; ?>"
                                         data-method="<?php echo htmlspecialchars($gateway['plugin_slug']); ?>"
                                         data-fixed-charge="<?php echo htmlspecialchars((string)($gateway['fixed_charge'] ?? 0)); ?>"
                                         data-percent-charge="<?php echo htmlspecialchars((string)($gateway['percent_charge'] ?? 0)); ?>"
                                         data-amount="<?php echo htmlspecialchars((string)($gateway['amount'] ?? 0)); ?>"
                                         data-currency="<?php echo htmlspecialchars($gateway['currency'] ?? $currencyCode); ?>"
                                         data-available="<?php echo $gatewayAvailable ? 'true' : 'false'; ?>"
                                         data-reason="<?php echo htmlspecialchars($gatewayReason); ?>"
                                         aria-disabled="<?php echo $gatewayAvailable ? 'false' : 'true'; ?>"
                                         title="<?php echo htmlspecialchars($gatewayReason); ?>"
                                         aria-label="<?php echo htmlspecialchars($gateway['plugin_name']); ?>">
                                        <img src="<?php echo htmlspecialchars($gateway['plugin_logo']); ?>" alt="<?php echo htmlspecialchars($gateway['plugin_name']); ?>">
                                        <span class="method-name"><?php echo htmlspecialchars($gateway['plugin_name']); ?></span>
                                        <?php if (!$gatewayAvailable || $gatewayConverted): ?>
                                            <span class="method-badge"><?php echo htmlspecialchars($gatewayConverted ? 'Charged in '.($gateway['currency'] ?? '') : $gatewayReason); ?></span>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>

                <button type="button" class="pay-button" id="pay-button" disabled>Pay Now <i class="fas fa-arrow-right ms-2"></i></button>

                <div class="terms">
                    <?php $footerTermsUrl = trim($setting['response'][0]['footer_terms_url'] ?? ''); ?>
                    By clicking the gateway button you agree to our <?php if ($footerTermsUrl !== '' && $footerTermsUrl !== '--'): ?><a href="<?php echo htmlspecialchars($footerTermsUrl); ?>" target="_blank" rel="noopener">Terms of Service</a><?php else: ?><a href="#">Terms of Service</a><?php endif; ?> which is limited to facilitating your payment to <?php echo htmlspecialchars($merchantName); ?>.
                </div>

                <div class="footer-row">
                    <div class="bottom-links">
                        <a href="#support-tab" class="global-tab" data-tab="support-tab">Support</a>
                        <a href="#help-tab" class="global-tab" data-tab="help-tab">FAQ</a>
                        <a href="#information-tab" class="global-tab" data-tab="information-tab">Information</a>
                    </div>
                    <div class="powered">Powered by <span style="font-style: normal; font-weight: 800; color: #111827;">KeyClan</span></div>
                    <div class="secure-badges">
                        <img src="https://img.shields.io/badge/PCI_DSS-Secure-1f8b4c?style=flat-square&labelColor=667085" alt="PCI DSS secure">
                        <img src="https://img.shields.io/badge/TLS-256--bit-1f8b4c?style=flat-square&labelColor=667085" alt="256-bit TLS encryption">
                    </div>
                </div>

                <div class="method-content" id="support-tab"></div>
                <div class="method-content" id="help-tab"></div>
                <div class="method-content" id="information-tab"></div>
            </section>
        </div>
    </div>

    <div class="offer-drawer" id="offer-drawer" role="dialog" aria-modal="true" aria-labelledby="offer-drawer-title">
        <div class="offer-drawer-panel">
            <div class="offer-drawer-head">
                <div>
                    <div class="section-title" id="offer-drawer-title">Special Offers and Savings</div>
                    <div class="section-subtitle">Automatically Applied with Eligible Payments</div>
                </div>
                <button class="offer-close" type="button" aria-label="Close offers"><i class="fas fa-times"></i></button>
            </div>
            <div class="offers-empty">
                <i class="far fa-percent"></i>
                <p>No offers available for this transaction</p>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const tabs = document.querySelectorAll('.global-tab[data-tab]');
            const contents = document.querySelectorAll('.method-content');
            const amountToggle = document.querySelector('.amount-toggle');
            const amountBreakdown = document.getElementById('amount-breakdown');
            const offerTrigger = document.querySelector('.offer-trigger');
            const offerDrawer = document.getElementById('offer-drawer');

            amountToggle?.addEventListener('click', () => {
                const expanded = amountToggle.getAttribute('aria-expanded') === 'true';
                amountToggle.setAttribute('aria-expanded', String(!expanded));
                amountBreakdown?.classList.toggle('open', !expanded);
            });
            const offerClose = document.querySelector('.offer-close');
            const timerEl = document.getElementById('checkout-timer');
            const mobileTimerEl = document.getElementById('mobile-checkout-timer');

            const closeOffers = () => {
                if (!offerDrawer) return;
                offerDrawer.classList.remove('open');
                offerTrigger?.setAttribute('aria-expanded', 'false');
            };

            offerTrigger?.addEventListener('click', () => {
                offerDrawer?.classList.add('open');
                offerTrigger.setAttribute('aria-expanded', 'true');
            });
            offerClose?.addEventListener('click', closeOffers);
            offerDrawer?.addEventListener('click', (event) => {
                if (event.target === offerDrawer) closeOffers();
            });
            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape') closeOffers();
            });
            let remaining = 10 * 60;
            const cancelUrl = <?php echo json_encode($cancelUrl); ?>;
            const mainSiteUrl = 'https://' + window.location.host;

            const formatTime = (seconds) => {
                const mins = String(Math.floor(seconds / 60)).padStart(2, '0');
                const secs = String(seconds % 60).padStart(2, '0');
                return `${mins}:${secs}`;
            };

            const renderTimer = () => {
                const formattedTime = formatTime(remaining);
                const timerText = timerEl ? timerEl.querySelector('span') : null;
                const mobileTimerText = mobileTimerEl ? mobileTimerEl.querySelector('span') : null;
                if (timerText) timerText.textContent = formattedTime;
                if (mobileTimerText) mobileTimerText.textContent = formattedTime;
                if (remaining <= 60 && timerText) {
                    timerText.style.color = '#dc2626';
                }
                if (remaining <= 60 && mobileTimerText) {
                    mobileTimerText.style.color = '#dc2626';
                }
            };

            renderTimer();
            if (timerEl) {
                const timerInterval = setInterval(() => {
                    if (remaining > 0) {
                        remaining -= 1;
                        renderTimer();
                    } else {
                        clearInterval(timerInterval);
                        if (timerEl) {
                            timerEl.style.pointerEvents = 'none';
                            timerEl.style.opacity = '0.5';
                        }
                        if (mobileTimerEl) {
                            mobileTimerEl.style.pointerEvents = 'none';
                            mobileTimerEl.style.opacity = '0.5';
                        }
                        const redirectUrl = cancelUrl && cancelUrl !== '--' ? cancelUrl : mainSiteUrl;
                        window.location.href = redirectUrl;
                    }
                }, 1000);
            }

            if (tabs.length > 0) {
                const categoryTabs = document.querySelectorAll('.category-card[data-tab]');
                const defaultTab = document.querySelector('.category-card[data-tab="mobile-banking"]') || categoryTabs[0];
                if (defaultTab) {
                    defaultTab.classList.add('active');
                    const initial = document.getElementById(defaultTab.getAttribute('data-tab'));
                    if (initial) initial.classList.add('active');
                }
            }

            const backBtn = document.querySelector('.back-btn');
            if (backBtn) {
                backBtn.addEventListener('click', function() {
                    const cancelUrl = this.getAttribute('data-cancel-url') || '';
                    if (cancelUrl && cancelUrl !== '--') {
                        window.location.href = cancelUrl;
                    } else if (document.referrer) {
                        window.history.back();
                    }
                });
            }

            const defaultContent = document.getElementById('mobile-banking') || contents[0];
            const defaultMethod = defaultContent ? defaultContent.querySelector('.method-card[data-available="true"]') : document.querySelector('.method-card[data-available="true"]');
            if (defaultMethod) {
                defaultMethod.classList.add('selected');
            }

            const payButton = document.getElementById('pay-button');
            if (payButton) payButton.disabled = !defaultMethod;

            document.querySelectorAll('.category-card[data-tab]').forEach(tab => {
                tab.addEventListener('click', function() {
                    document.querySelectorAll('.category-card[data-tab]').forEach(item => item.classList.remove('active'));
                    document.querySelectorAll('.method-content').forEach(content => content.classList.remove('active'));
                    this.classList.add('active');
                    document.querySelectorAll('.method-card').forEach(card => card.classList.remove('selected'));
                    if (payButton) payButton.disabled = true;
                    resetSummary();
                    const content = document.getElementById(this.getAttribute('data-tab'));
                    if (content) content.classList.add('active');
                });
            });
            document.querySelectorAll('.method-card').forEach(card => {
                card.addEventListener('click', function() {
                    if (this.getAttribute('data-available') !== 'true') {
                        const payButton = document.getElementById('pay-button');
                        if (payButton) payButton.disabled = true;
                        return;
                    }
                    document.querySelectorAll('.method-card').forEach(c => c.classList.remove('selected'));
                    this.classList.add('selected');
                    const payButton = document.getElementById('pay-button');
                    if (payButton) payButton.disabled = false;
                });
            });

            const amountValueEl = document.querySelector('.amount-value');
            const subtotalValueEl = document.querySelectorAll('.line-value')[0];
            const feeValueEl = document.querySelectorAll('.line-value')[1];
            const totalValueEl = document.querySelector('.total-row span:last-child');

            const currencySymbolMap = <?php echo json_encode($currencySymbolMap); ?>;
            const resolveCurrencySymbol = (code) => {
                const upper = (code || '').toUpperCase().trim();
                return currencySymbolMap[upper] || upper;
            };

            const resetSummary = () => {
                if (!amountValueEl || !subtotalValueEl || !feeValueEl || !totalValueEl) return;

                amountValueEl.textContent = `<?php echo htmlspecialchars($amountDue); ?> <?php echo htmlspecialchars($currency); ?>`;
                subtotalValueEl.textContent = `<?php echo htmlspecialchars($subtotal); ?> <?php echo htmlspecialchars($currency); ?>`;
                feeValueEl.textContent = `<?php echo htmlspecialchars($fee); ?> <?php echo htmlspecialchars($currency); ?>`;
                totalValueEl.textContent = `<?php echo htmlspecialchars($amountDue); ?> <?php echo htmlspecialchars($currency); ?>`;
            };

            const updateSummary = (card) => {
                if (!card || !amountValueEl || !subtotalValueEl || !feeValueEl || !totalValueEl) {
                    resetSummary();
                    return;
                }

                const selectedCurrencyRaw = card.getAttribute('data-currency') || '<?php echo htmlspecialchars($currencyCode); ?>';
                const selectedCurrency = resolveCurrencySymbol(selectedCurrencyRaw);
                const convertedAmount = parseFloat(card.getAttribute('data-amount') || '0') || 0;
                const fixedCharge = parseFloat(card.getAttribute('data-fixed-charge') || '0') || 0;
                const percentCharge = parseFloat(card.getAttribute('data-percent-charge') || '0') || 0;
                const subtotalAmount = convertedAmount || (parseFloat('<?php echo $subtotalValue; ?>') || 0);
                const calculatedFee = fixedCharge > 0 ? fixedCharge : (subtotalAmount * (percentCharge / 100));
                const totalAmount = subtotalAmount + calculatedFee;

                amountValueEl.textContent = `${totalAmount.toFixed(2)} ${selectedCurrency}`;
                subtotalValueEl.textContent = `${subtotalAmount.toFixed(2)} ${selectedCurrency}`;
                feeValueEl.textContent = `${calculatedFee.toFixed(2)} ${selectedCurrency}`;
                totalValueEl.textContent = `${totalAmount.toFixed(2)} ${selectedCurrency}`;
            };

            document.querySelectorAll('.method-card').forEach(card => {
                card.addEventListener('click', function() {
                    if (this.getAttribute('data-available') !== 'true') {
                        resetSummary();
                        return;
                    }
                    document.querySelectorAll('.method-card').forEach(c => c.classList.remove('selected'));
                    this.classList.add('selected');
                    updateSummary(this);
                });
            });

            if (payButton) {
                payButton.addEventListener('click', function() {
                    const selectedCard = document.querySelector('.method-card.selected');
                    const method = selectedCard ? selectedCard.getAttribute('data-method') : '';
                    if (method) {
                        location.href = `?method=${encodeURIComponent(method)}`;
                    }
                });
            }

            const defaultSelected = document.querySelector('.method-card.selected') || document.querySelector('.method-card[data-available="true"]');
            if (defaultSelected) {
                updateSummary(defaultSelected);
            } else {
                resetSummary();
            }
        });
    </script>
</body>
</html>