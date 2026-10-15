<?php
    if (!defined('pp_allowed_access')) {
        die('Direct access not allowed');
    }

    $payment_details = pp_get_payment_link($paymentid);
    $payment_details_items = pp_get_payment_link_items($paymentid);
    $setting = pp_get_settings();

    $merchantName = $setting['response'][0]['site_name'] ?? 'PipraPay';
    $merchantLogo = isset($setting['response'][0]['logo']) && $setting['response'][0]['logo'] !== '--'
        ? $setting['response'][0]['logo']
        : 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8Xw8AAoMBgDTD2qgAAAAASUVORK5CYII=';
    $favicon = isset($setting['response'][0]['favicon']) && $setting['response'][0]['favicon'] !== '--'
        ? $setting['response'][0]['favicon']
        : 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8Xw8AAoMBgDTD2qgAAAAASUVORK5CYII=';

    $themeColor = $setting['response'][0]['global_text_color'] ?? '#0f172a';
    $primaryColor = $setting['response'][0]['primary_button_color'] ?? '#0f172a';
    $buttonTextColor = $setting['response'][0]['button_text_color'] ?? '#ffffff';
    $buttonHoverColor = $setting['response'][0]['button_hover_color'] ?? '#111827';
    $buttonHoverTextColor = $setting['response'][0]['button_hover_text_color'] ?? '#ffffff';

    $plCurrencyCode = strtoupper(trim((string)($payment_details['response'][0]['pl_currency'] ?? '')));
    $plCurrencySymbolMap = [
        'BDT' => '৳', 'TK' => '৳', 'TKA' => '৳',
        'USD' => '$', 'EUR' => '€', 'GBP' => '£',
        'INR' => '₹', 'JPY' => '¥', 'CNY' => '¥',
        'SAR' => '﷼', 'AED' => 'د.إ', 'PKR' => '₨', 'NPR' => '₨',
    ];
    $plCurrency = $plCurrencySymbolMap[$plCurrencyCode] ?? $plCurrencyCode;
    $amount = number_format((float)($payment_details['response'][0]['pl_amount'] ?? 0), 2) . $plCurrency;
    $title = $payment_details['response'][0]['pl_name'] ?? 'Payment';
    $description = $payment_details['response'][0]['pl_description'] ?? '';
    $fields = $payment_details_items['response'] ?? [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($title); ?> - <?php echo htmlspecialchars($merchantName); ?></title>
    <link rel="icon" type="image/x-icon" href="<?php echo htmlspecialchars($favicon); ?>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.4.1/jquery.min.js"></script>
    <style>
        :root {
            --keypay-bg: #f5f7fb;
            --keypay-surface: #ffffff;
            --keypay-border: #e5e7eb;
            --keypay-text: #111827;
            --keypay-muted: #6b7280;
            --keypay-primary: <?php echo $themeColor; ?>;
            --keypay-button: <?php echo $primaryColor; ?>;
            --keypay-button-text: <?php echo $buttonTextColor; ?>;
            --keypay-button-hover: <?php echo $buttonHoverColor; ?>;
            --keypay-button-hover-text: <?php echo $buttonHoverTextColor; ?>;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            font-family: 'Inter', sans-serif;
            background: radial-gradient(circle at top, #ffffff 0%, var(--keypay-bg) 60%);
            color: var(--keypay-text);
            min-height: 100vh;
        }

        .keypay-shell {
            max-width: 560px;
            margin: 32px auto;
            padding: 0 16px;
        }

        .keypay-card {
            background: var(--keypay-surface);
            border: 1px solid var(--keypay-border);
            border-radius: 24px;
            box-shadow: 0 18px 40px rgba(15, 23, 42, 0.08);
            overflow: hidden;
        }

        .keypay-card:hover {
            box-shadow: 0 22px 48px rgba(15, 23, 42, 0.10);
        }

        .keypay-header {
            padding: 24px 24px 18px;
            border-bottom: 1px solid var(--keypay-border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
        }

        .keypay-brand {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .keypay-brand img {
            width: 42px;
            height: 42px;
            border-radius: 12px;
            object-fit: cover;
            border: 1px solid var(--keypay-border);
            background: #fff;
        }

        .keypay-brand-text h1 {
            font-size: 18px;
            margin: 0;
            font-weight: 700;
            color: var(--keypay-text);
        }

        .keypay-brand-text p {
            margin: 2px 0 0;
            color: var(--keypay-muted);
            font-size: 13px;
        }

        .keypay-body {
            padding: 24px;
        }

        .keypay-title {
            font-size: 28px;
            line-height: 1.15;
            font-weight: 800;
            margin: 0 0 8px;
            letter-spacing: -0.03em;
        }

        .keypay-subtitle {
            margin: 0 0 24px;
            color: var(--keypay-muted);
            font-size: 15px;
        }

        .keypay-summary {
            background: linear-gradient(180deg, rgba(17, 24, 39, 0.03), rgba(17, 24, 39, 0.02));
            border: 1px solid var(--keypay-border);
            border-radius: 18px;
            padding: 18px;
            margin-bottom: 20px;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 16px;
        }

        .summary-label {
            color: var(--keypay-muted);
            font-size: 13px;
            margin-bottom: 4px;
        }

        .summary-value {
            font-size: 22px;
            font-weight: 800;
            color: var(--keypay-text);
            letter-spacing: -0.02em;
        }

        .summary-name {
            font-size: 16px;
            font-weight: 600;
            margin-top: 6px;
        }

        .summary-desc {
            color: var(--keypay-muted);
            font-size: 13px;
            margin-top: 4px;
        }

        .keypay-form .form-group {
            margin-bottom: 14px;
        }

        .keypay-form label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 6px;
            color: var(--keypay-text);
        }

        .keypay-form input,
        .keypay-form textarea,
        .keypay-form select {
            width: 100%;
            border: 1px solid var(--keypay-border);
            border-radius: 14px;
            padding: 14px 16px;
            font-size: 15px;
            outline: none;
            transition: all 0.2s ease;
            background: #fff;
        }

        .keypay-form input:focus,
        .keypay-form textarea:focus,
        .keypay-form select:focus {
            border-color: var(--keypay-primary);
            box-shadow: 0 0 0 4px rgba(15, 23, 42, 0.08);
        }

        .keypay-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }

        .keypay-actions {
            margin-top: 20px;
        }

        .keypay-pay {
            width: 100%;
            border: none;
            border-radius: 16px;
            padding: 15px 18px;
            background: linear-gradient(180deg, #6C63FF 0%, #5B52F5 100%);
            color: var(--keypay-button-text);
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            transition: transform 0.15s ease, background 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .keypay-pay:hover {
            background: linear-gradient(180deg, #5B52F5 0%, #4A42E8 100%);
            color: var(--keypay-button-hover-text);
            transform: translateY(-1px);
        }

        .keypay-footer {
            padding: 18px 24px 24px;
            border-top: 1px solid var(--keypay-border);
            color: var(--keypay-muted);
            font-size: 13px;
            text-align: center;
        }

        .keypay-footer strong { color: var(--keypay-text); }

        #response { display: block; margin-top: 14px; }

        @media (max-width: 576px) {
            .keypay-shell { margin: 0; padding: 0; }
            .keypay-card { border-radius: 0; min-height: 100vh; }
            .keypay-header, .keypay-body, .keypay-footer { padding-left: 18px; padding-right: 18px; }
            .keypay-grid { grid-template-columns: 1fr; }
            .keypay-title { font-size: 24px; }
        }
    </style>
</head>
<body>
    <div class="keypay-shell">
        <div class="keypay-card">
            <div class="keypay-header">
                <div class="keypay-brand">
                    <img src="<?php echo htmlspecialchars($merchantLogo); ?>" alt="<?php echo htmlspecialchars($merchantName); ?>">
                    <div class="keypay-brand-text">
                        <h1><?php echo htmlspecialchars($merchantName); ?></h1>
                        <p>Secure checkout by KeyPay</p>
                    </div>
                </div>
            </div>

            <div class="keypay-body">
                <h2 class="keypay-title">Complete your payment</h2>
                <p class="keypay-subtitle">Enter your details to continue securely.</p>

                <div class="keypay-summary">
                    <div class="summary-row">
                        <div>
                            <div class="summary-label">Total due</div>
                            <div class="summary-value"><?php echo htmlspecialchars($amount); ?></div>
                        </div>
                    </div>
                    <div class="summary-name"><?php echo htmlspecialchars($title); ?></div>
                    <?php if ($description !== ''): ?>
                        <div class="summary-desc"><?php echo htmlspecialchars($description); ?></div>
                    <?php endif; ?>
                </div>

                <form enctype="multipart/form-data" class="keypay-form" id="keypay-form">
                    <input type="hidden" name="action" value="pp-invoice-payment-link">
                    <input type="hidden" name="pp-paymentid" value="<?php echo htmlspecialchars($paymentid); ?>">
                    <input type="text" name="website" value="" autocomplete="off" tabindex="-1" aria-hidden="true" style="position:absolute;left:-9999px;top:auto;width:1px;height:1px;opacity:0;">

                    <div class="form-group">
                        <label>Email or Mobile</label>
                        <input type="text" name="email-mobile" placeholder="Enter your email or mobile number" autocomplete="email">
                    </div>

                    <?php if (!empty($fields)): ?>
                    <div class="form-group">
                        <label>Additional details</label>
                        <div class="keypay-grid">
                            <?php foreach ($fields as $row): ?>
                                <div>
                                    <input type="<?php echo htmlspecialchars($row['pl_form_type']); ?>"
                                           name="<?php echo $row['pl_form_type'] === 'file' ? htmlspecialchars($row['pl_field_name']) . '[]' : htmlspecialchars($row['pl_field_name']); ?>"
                                           placeholder="<?php echo htmlspecialchars(ucwords(str_replace('-', ' ', $row['pl_field_name']))); ?>"
                                           <?php echo (($row['pl_is_require'] ?? '') === 'yes') ? 'required' : ''; ?>
                                           <?php echo ($row['pl_form_type'] === 'file') ? 'style="height:auto;"' : ''; ?>>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div id="response"></div>

                    <div class="keypay-actions">
                        <button class="keypay-pay" id="pay-button" type="submit">
                            <i class="fas fa-lock"></i> Pay Now
                        </button>
                    </div>
                </form>
            </div>

            <div class="keypay-footer">
                Payments are secure and encrypted
            </div>
        </div>
    </div>

    <script>
        document.getElementById('keypay-form').addEventListener('submit', function(e) {
            e.preventDefault();

            const btn = document.getElementById('pay-button');
            const originalHtml = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Processing';

            const formData = new FormData(this);

            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(async res => {
                const text = await res.text();
                if (!text.trim()) throw new Error('Empty server response');
                return JSON.parse(text);
            })
            .then(response => {
                btn.disabled = false;
                btn.innerHTML = originalHtml;

                if (response.status === false || response.status === 'false') {
                    document.getElementById('response').innerHTML = '<div class="alert alert-danger mt-3 mb-0">' + (response.message || 'Something went wrong.') + '</div>';
                    return;
                }

                if (!response.pp_url) {
                    document.getElementById('response').innerHTML = '<div class="alert alert-danger mt-3 mb-0">Payment gateway did not return a redirect URL.</div>';
                    return;
                }

                location.href = response.pp_url;
            })
            .catch(error => {
                console.error(error);
                btn.disabled = false;
                btn.innerHTML = originalHtml;
                document.getElementById('response').innerHTML = '<div class="alert alert-danger mt-3 mb-0">Server returned an invalid response. Please try again.</div>';
            });
        });
    </script>
</body>
</html>