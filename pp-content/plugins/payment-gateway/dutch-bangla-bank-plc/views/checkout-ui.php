<?php
    $transaction_details = pp_get_transation($payment_id);
    $setting = pp_get_settings();
    $plugin_slug = 'dutch-bangla-bank-plc';
    $plugin_info = pp_get_plugin_info($plugin_slug);
    $settings = pp_get_plugin_setting($plugin_slug);

    $transaction_amount = convertToDefault($transaction_details['response'][0]['transaction_amount'], $transaction_details['response'][0]['transaction_currency'], $settings['currency']);
    $transaction_fee = safeNumber($settings['fixed_charge']) + ($transaction_amount * (safeNumber($settings['percent_charge']) / 100));
    $transaction_amount += $transaction_fee;
    $formatted_amount = number_format($transaction_amount, 2);
    $site_name = $setting['response'][0]['site_name'] ?? 'KeyClan';
    $payment_link = pp_get_paymentlink($payment_id);
    $account_name = $settings['account_name'] ?? '';
    $account_number = $settings['account_number'] ?? '';
    $branch_name = $settings['branch_name'] ?? '';
    $routing_number = $settings['routing_number'] ?? '';

    if (isset($_POST['dutch-bangla-bank-plc'])) {
        if (pp_set_transaction_byslip($payment_id, $plugin_slug, $plugin_info['plugin_name'], $_FILES['payment_slip'] ?? null, 'pending')) {
            echo json_encode(["status" => "true", "message" => "Initialize Payment Slip"]);
        } else {
            echo json_encode(["status" => "false", "message" => "Failed to upload payment slip"]);
        }
        exit();
    }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($settings['display_name'] ?? 'Dutch-Bangla Bank Payment', ENT_QUOTES, 'UTF-8'); ?> - <?php echo htmlspecialchars($site_name, ENT_QUOTES, 'UTF-8'); ?></title>
    <link rel="icon" type="image/x-icon" href="<?php echo htmlspecialchars($setting['response'][0]['favicon'] ?? 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8Xw8AAoMBgDTD2qgAAAAASUVORK5CYII=', ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --ink: #3A0E14;
            --muted: #8A6E72;
            --line: #F0E2E4;
            --surface: #FDF6F7;
            --blue: #0B4EB8;
            --deep-blue: #083A87;
            --red: #D72631;
            --green: #118935;
        }

        * { box-sizing: border-box; }

        body {
            min-height: 100vh;
            margin: 0;
            padding: 0 24px;
            color: var(--ink);
            background: #FBF2F3;
            font-family: 'DM Sans', Arial, sans-serif;
            -webkit-font-smoothing: antialiased;
        }

        button, input { font: inherit; }
        button { cursor: pointer; }

        .gateway {
            width: min(620px, calc(100% - 32px));
            margin: 2rem auto;
            padding: 0 20px 24px;
            background: #fff;
            border-radius: 24px;
            box-shadow: 0 14px 40px rgba(26, 26, 26, .06);
        }

        .top {
            display: grid;
            grid-template-columns: 42px 1fr 42px;
            align-items: center;
            margin-bottom: 24px;
            padding: 18px 20px 0;
        }

        .back {
            width: auto;
            height: auto;
            padding: 0;
            border: 0;
            background: transparent;
            border-radius: 0;
            color: var(--muted);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
            font: 600 14px Inter, sans-serif;
            line-height: 1;
        }

        .back i { font-size: 18px; }

        .top .bank-logo { justify-self: center; }
        .top > span { display: block; }

        .bank-logo {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .bank-logo-image {
            display: block;
            width: auto;
            height: 48px;
            max-width: 170px;
            object-fit: contain;
        }

        .card {
            overflow: hidden;
            border: 1px solid var(--line);
            border-radius: 24px;
            background: #fff;
            box-shadow: 0 14px 40px rgba(26, 26, 26, .06);
        }

        .amount {
            position: relative;
            overflow: hidden;
            min-height: 116px;
            padding: 20px 30px;
            border-radius: 16px 16px 0 0;
            border-bottom: 0;
            color: #fff;
            background: radial-gradient(circle at 85% 50%, rgba(255, 255, 255, .16), transparent 45%), linear-gradient(90deg, #0B4EB8 0%, #0B4EB8 55%, #D72631 135%);
            box-shadow: none;
        }

        .amount::after {
            position: absolute;
            right: -20px;
            top: 50%;
            width: 145px;
            height: 145px;
            content: '';
            border: 1px solid rgba(255, 255, 255, .2);
            border-radius: 50%;
            box-shadow: 0 0 0 20px rgba(255, 255, 255, .05), 0 0 0 42px rgba(255, 255, 255, .035);
            transform: translateY(-50%);
        }

        .eyebrow {
            position: relative;
            z-index: 1;
            display: flex;
            align-items: center;
            gap: 9px;
            margin: 0;
            font-size: 13px;
            font-weight: 600;
            opacity: .85;
        }

        .price {
            position: relative;
            z-index: 1;
            margin-top: 4px;
            font-size: 36px;
            font-weight: 800;
            letter-spacing: -1.5px;
        }

        .main {
            margin-top: 0;
            padding: 30px;
            border-top: 0;
            border-radius: 0 0 16px 16px;
            box-shadow: 0 14px 40px rgba(26, 26, 26, .06);
        }

        .content {
            width: 100%;
            margin: 0;
            padding: 0;
        }

        .section-heading {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 14px;
        }

        .section-heading h2 {
            margin: 0;
            font-family: 'Plus Jakarta Sans', Arial, sans-serif;
            font-size: 14px;
            letter-spacing: -.02em;
        }

        .account-card {
            overflow: hidden;
            border: 1px solid var(--line);
            border-radius: 16px;
            background: #fff;
        }

        .account-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            min-height: 61px;
            padding: 14px 18px;
            border-bottom: 1px solid var(--line);
        }

        .account-row:last-child { border-bottom: 0; }
        .account-label { color: var(--muted); font-size: 13px; }
        .account-value { display: flex; align-items: center; gap: 11px; color: var(--ink); font-size: 14px; font-weight: 700; text-align: right; }
        .account-value .copy-button { margin-left: 8px; }

        .copy-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 22px;
            height: 22px;
            padding: 0;
            border: 0;
            background: transparent;
            color: var(--blue);
        }

        .copy-button:hover { color: var(--deep-blue); }
        .copy-button svg, .upload-icon svg { width: 16px; height: 16px; fill: none; stroke: currentColor; stroke-linecap: round; stroke-linejoin: round; stroke-width: 1.8; }

        .upload-section { margin-top: 30px; }
        .upload-label { display: block; margin-bottom: 10px; color: var(--ink); font-size: 13px; font-weight: 700; }

        .upload-box {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            align-items: center;
            gap: 10px 14px;
            min-height: 96px;
            padding: 14px 16px;
            border: 1.5px dashed #E3B3BC;
            border-radius: 16px;
            background: #FFFBFB;
            text-align: left;
            transition: border-color .2s, background .2s;
        }

        .upload-box:hover, .upload-box.has-file { border-color: var(--blue); background: #FFF5F6; }
        .upload-copy { display: flex; align-items: center; min-width: 0; gap: 10px; }
        .upload-icon { display: grid; flex: 0 0 auto; width: 34px; height: 34px; place-items: center; border-radius: 10px; background: #FBE9EC; color: var(--blue); }
        .upload-title { margin: 0; color: #263958; font-size: 13px; font-weight: 700; white-space: nowrap; }

        .choose-file {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            align-self: center;
            padding: 9px 14px;
            border: 1px solid #E3B3BC;
            border-radius: 9px;
            background: #fff;
            color: var(--blue);
            font-size: 12px;
            font-weight: 700;
            white-space: nowrap;
            transition: border-color .2s, background .2s;
        }

        .choose-file:hover { border-color: var(--blue); background: #FFF0F2; }
        .file-helper { grid-column: 1; grid-row: 2; align-self: center; margin: 0 0 0 44px; color: #9aa7ba; font-size: 10px; }
        .file-name { grid-column: 1 / -1; max-width: 100%; margin: 0; overflow: hidden; color: var(--green); font-size: 12px; font-weight: 700; text-overflow: ellipsis; white-space: nowrap; }
        .payment-id { display: none; }

        .response { display: block; margin-top: 14px; }
        .response-message { padding: 11px 13px; border-radius: 10px; background: #fff1f1; color: #b52e38; font-size: 13px; font-weight: 600; }

        .verify-button {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 9px;
            width: 100%;
            margin-top: 18px;
            padding: 15px 20px;
            border: 0;
            border-radius: 11px;
            background: linear-gradient(105deg, #0B4EB8, #D72631);
            color: #fff;
            font-size: 14px;
            font-weight: 700;
            box-shadow: 0 10px 18px rgba(11, 78, 184, .22);
            transition: transform .2s, box-shadow .2s, filter .2s;
        }

        .verify-button:hover { filter: brightness(1.05); box-shadow: 0 13px 24px rgba(11, 78, 184, .28); transform: translateY(-1px); }
        .verify-button:active { transform: translateY(0); }
        .verify-button:disabled { cursor: wait; opacity: .75; transform: none; }

        .gateway-footer {
            margin-top: 22px;
            padding: 0;
            color: #9a9da3;
            font-size: 12px;
            text-align: center;
        }

        .gateway-footer strong { font-weight: 700; }

        @media (max-width: 640px) {
            body { padding: 0; }
            .gateway { width: calc(100% - 16px); margin: 10px auto 24px; min-height: auto; border: 0; border-radius: 24px; padding: 0 14px 18px; }
            .top { margin-bottom: 12px; }
            .amount { padding: 18px 20px; }
            .price { font-size: 28px; letter-spacing: -.5px; }
            .bank-logo-image { height: 45px; max-width: 150px; }
            .content { width: 100%; padding-top: 0; min-width: 0; } .main { padding: 16px; }
            .account-row { align-items: flex-start; flex-direction: column; gap: 6px; padding: 13px 15px; }
            .account-value { width: 100%; justify-content: space-between; text-align: left; }
        }

        @media (max-width: 480px) {
            .upload-box {
                grid-template-columns: 1fr;
                gap: 8px;
                min-height: auto;
            }

            .upload-copy { justify-content: center; }
            .upload-title { white-space: normal; text-align: center; }
            .choose-file { justify-self: center; margin-left: 0; }
            .file-helper { grid-column: 1; margin-left: 0; text-align: center; }
        }
    </style>
</head>
<body>
    <main class="gateway">
        <header class="top">
            <button class="back" type="button" onclick="location.href='<?php echo htmlspecialchars($payment_link, ENT_QUOTES, 'UTF-8'); ?>'" aria-label="Back"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i><span>Back</span></button>
            <div class="bank-logo" aria-label="Dutch-Bangla Bank">
                <img class="bank-logo-image" src="<?php echo htmlspecialchars(pp_get_site_url().'/pp-content/plugins/payment-gateway/dutch-bangla-bank-plc/assets/icon.png', ENT_QUOTES, 'UTF-8'); ?>" alt="Dutch-Bangla Bank">
            </div>
            <span aria-hidden="true"></span>
        </header>

        <section class="card amount">
            <div class="eyebrow">Amount to Pay</div>
            <div class="price"><?php echo $formatted_amount; ?> ৳</div>
        </section>

        <section class="card main">
            <div class="content">
            <div class="section-heading">
                <h2>Bank account details</h2>
            </div>
            <div class="account-card">
                <div class="account-row">
                    <span class="account-label">Bank name</span>
                    <span class="account-value"><span>Dutch-Bangla Bank PLC</span><button type="button" class="copy-button" onclick="copyText('Al-Arafah Islami Bank PLC', this)"><svg viewBox="0 0 24 24"><rect x="8" y="8" width="11" height="11" rx="2"></rect><path d="M16 8V6a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h2"></path></svg></button></span>
                </div>
                <?php if ($account_name !== ''): ?>
                <div class="account-row">
                    <span class="account-label">Account name</span>
                    <span class="account-value"><span><?php echo htmlspecialchars($account_name, ENT_QUOTES, 'UTF-8'); ?></span><button type="button" class="copy-button" onclick="copyText(<?php echo htmlspecialchars(json_encode($account_name), ENT_QUOTES, 'UTF-8'); ?>, this)"><svg viewBox="0 0 24 24"><rect x="8" y="8" width="11" height="11" rx="2"></rect><path d="M16 8V6a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h2"></path></svg></button></span>
                </div>
                <?php endif; ?>
                <?php if ($account_number !== ''): ?>
                <div class="account-row">
                    <span class="account-label">Account number</span>
                    <span class="account-value"><span><?php echo htmlspecialchars($account_number, ENT_QUOTES, 'UTF-8'); ?></span><button type="button" class="copy-button" onclick="copyText(<?php echo htmlspecialchars(json_encode($account_number), ENT_QUOTES, 'UTF-8'); ?>, this)"><svg viewBox="0 0 24 24"><rect x="8" y="8" width="11" height="11" rx="2"></rect><path d="M16 8V6a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h2"></path></svg></button></span>
                </div>
                <?php endif; ?>
                <?php if ($branch_name !== ''): ?>
                <div class="account-row">
                    <span class="account-label">Branch name</span>
                    <span class="account-value"><span><?php echo htmlspecialchars($branch_name, ENT_QUOTES, 'UTF-8'); ?></span><button type="button" class="copy-button" onclick="copyText(<?php echo htmlspecialchars(json_encode($branch_name), ENT_QUOTES, 'UTF-8'); ?>, this)"><svg viewBox="0 0 24 24"><rect x="8" y="8" width="11" height="11" rx="2"></rect><path d="M16 8V6a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h2"></path></svg></button></span>
                </div>
                <?php endif; ?>
                <?php if ($routing_number !== ''): ?>
                <div class="account-row">
                    <span class="account-label">Routing number</span>
                    <span class="account-value"><span><?php echo htmlspecialchars($routing_number, ENT_QUOTES, 'UTF-8'); ?></span><button type="button" class="copy-button" onclick="copyText(<?php echo htmlspecialchars(json_encode($routing_number), ENT_QUOTES, 'UTF-8'); ?>, this)"><svg viewBox="0 0 24 24"><rect x="8" y="8" width="11" height="11" rx="2"></rect><path d="M16 8V6a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h2"></path></svg></button></span>
                </div>
                <?php endif; ?>
            </div>

            <div class="upload-section">
                <label class="upload-label" for="payment-slip">Upload payment receipt</label>
                <label class="upload-box" for="payment-slip">
                    <span class="upload-copy"><span class="upload-icon"><svg viewBox="0 0 24 24"><path d="M12 16V4"></path><path d="m7 9 5-5 5 5"></path><path d="M5 20h14"></path></svg></span><span class="upload-title">Drop your receipt here</span></span>
                    <span class="choose-file">Choose File</span>
                    <span class="file-helper">JPG • PNG • PDF • Max 5MB</span>
                    <span class="file-name" aria-live="polite"></span>
                </label>
                <input id="payment-slip" class="payment-id" type="file" accept="image/jpeg,image/png,application/pdf">
            </div>

            <span class="response" aria-live="polite"></span>
            <button type="button" class="verify-button">
                <i class="fa-solid fa-shield-halved" aria-hidden="true"></i>
                <span>Verify</span>
            </button>
            </div>
        </section>
        <footer class="gateway-footer">Secure payment verification · Powered by <strong>KeyClan</strong></footer>
    </main>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.4.1/jquery.min.js"></script>
    <script>
        const fileInput = document.querySelector('.payment-id');
        const uploadBox = document.querySelector('.upload-box');
        const fileName = document.querySelector('.file-name');
        const verifyButton = document.querySelector('.verify-button');
        const response = document.querySelector('.response');

        function copyText(text, button) {
            const copied = () => {
                button.innerHTML = '<svg viewBox="0 0 24 24"><path d="m5 12 4 4L19 6"></path></svg>';
                window.setTimeout(() => {
                    button.innerHTML = '<svg viewBox="0 0 24 24"><rect x="8" y="8" width="11" height="11" rx="2"></rect><path d="M16 8V6a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2"></path></svg>';
                }, 1600);
            };

            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(text).then(copied);
                return;
            }

            const textarea = document.createElement('textarea');
            textarea.value = text;
            textarea.style.position = 'fixed';
            textarea.style.opacity = '0';
            document.body.appendChild(textarea);
            textarea.select();
            document.execCommand('copy');
            textarea.remove();
            copied();
        }

        fileInput.addEventListener('change', function () {
            const file = this.files[0];
            if (!file) return;
            uploadBox.classList.add('has-file');
            fileName.textContent = file.name;
        });

        verifyButton.addEventListener('click', function () {
            const file = fileInput.files[0];
            response.innerHTML = '';

            if (!file) {
                response.innerHTML = '<div class="response-message">Please upload your payment receipt before verifying.</div>';
                return;
            }

            if (file.size > 5 * 1024 * 1024) {
                response.innerHTML = '<div class="response-message">Your receipt must be smaller than 5MB.</div>';
                return;
            }

            verifyButton.disabled = true;
            verifyButton.querySelector('span').textContent = 'Verifying receipt…';

            const formData = new FormData();
            formData.append('dutch-bangla-bank-plc', '<?php echo $payment_id; ?>');
            formData.append('payment_slip', file);

            $.ajax({
                type: 'POST',
                url: '<?php echo $payment_link; ?>?method=dutch-bangla-bank-plc',
                data: formData,
                contentType: false,
                processData: false,
                success: function (data) {
                    try {
                        const result = JSON.parse(data);
                        if (result.status === 'false') {
                            response.innerHTML = '<div class="response-message">' + result.message + '</div>';
                            verifyButton.disabled = false;
                            verifyButton.querySelector('span').textContent = 'Verify';
                        } else {
                            location.href = '<?php echo $payment_link; ?>';
                        }
                    } catch (error) {
                        response.innerHTML = '<div class="response-message">Unable to process the receipt. Please try again.</div>';
                        verifyButton.disabled = false;
                        verifyButton.querySelector('span').textContent = 'Verify';
                    }
                },
                error: function () {
                    response.innerHTML = '<div class="response-message">Unable to upload the receipt. Please try again.</div>';
                    verifyButton.disabled = false;
                    verifyButton.querySelector('span').textContent = 'Verify';
                }
            });
        });
    </script>
</body>
</html>
