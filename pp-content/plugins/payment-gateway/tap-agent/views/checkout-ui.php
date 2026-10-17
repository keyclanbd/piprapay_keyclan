<?php
    $transaction_details = pp_get_transation($payment_id);
    $setting = pp_get_settings();
    $plugin_slug = 'tap-agent';
    $plugin_info = pp_get_plugin_info($plugin_slug);
    $settings = pp_get_plugin_setting($plugin_slug);
    $base_amount = convertToDefault($transaction_details['response'][0]['transaction_amount'], $transaction_details['response'][0]['transaction_currency'], $settings['currency']);
    $transaction_fee = safeNumber($settings['fixed_charge']) + ($base_amount * (safeNumber($settings['percent_charge']) / 100));
    $transaction_amount = $base_amount + $transaction_fee;
    $formatted_amount = number_format($transaction_amount, 2);

    if(isset($_POST['tap-agent'])){
        $trxid = trim((string) ($_POST['trxid'] ?? ''));
        $number = trim((string) ($_POST['number'] ?? ''));
        if($trxid === '' || strlen($trxid) > 100 || !preg_match('/^[A-Za-z0-9_-]+$/', $trxid)){
            echo json_encode(["status" => "false", "message" => "Invalid Transaction ID", "numberbox" => "false"]);
        }else{
            $check_transactionid = pp_check_transaction_exits($trxid);
            if($check_transactionid['status'] == false){
                $verify_status = pp_verify_transaction($payment_id, $plugin_slug, 'Tap', $trxid);
                if($verify_status['status'] == true){
                    if(pp_set_transaction_byid($payment_id, $plugin_slug, $plugin_info['plugin_name'], $verify_status['response'][0]['mobile_number'], $verify_status['response'][0]['transaction_id'], 'completed', $verify_status['response'][0]['id'])) echo json_encode(["status" => "true", "message" => "Initialize Transaction ID"]);
                }else{
                    if(($settings['pending_payment'] ?? '') == "enable"){
                        if($number == '') echo json_encode(["status" => "false", "message" => "Enter mobile number", "numberbox" => "true"]);
                        elseif(pp_set_transaction_byid($payment_id, $plugin_slug, $plugin_info['plugin_name'], $number, $trxid, 'pending')) echo json_encode(["status" => "true", "message" => "Initialize Transaction ID"]);
                    }else echo json_encode(["status" => "false", "message" => "Invalid Transaction ID", "numberbox" => "false"]);
                }
            }else echo json_encode(["status" => "false", "message" => "Transaction ID already exits", "numberbox" => "false"]);
        }
        exit();
    }

    $site_name = $setting['response'][0]['site_name'] ?? 'KeyClan';
    $payment_link = pp_get_paymentlink($payment_id);
    $account_name = $settings['account_name'] ?? '';
    $payment_number = $settings['payment_number'] ?? '';
    $display_payment_number = preg_match('/^(\d{3})(\d{4})(\d{4})$/', preg_replace('/\D/', '', (string) $payment_number), $number_parts) ? $number_parts[1].' '.$number_parts[2].' '.$number_parts[3] : $payment_number;
    $currency_symbols = ['BDT' => '৳', 'USD' => '$', 'EUR' => '€', 'GBP' => '£', 'INR' => '₹', 'PKR' => '₨', 'AED' => 'د.إ', 'SAR' => '﷼', 'CAD' => 'C$', 'AUD' => 'A$', 'JPY' => '¥', 'CNY' => '¥'];
    $currency_symbol = $currency_symbols[strtoupper($settings['currency'] ?? '')] ?? ($settings['currency'] ?? '');
    $amount = number_format($transaction_amount, 2).' '.$currency_symbol;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($settings['display_name'] ?? 'Taptap Send Payment', ENT_QUOTES, 'UTF-8'); ?> - <?php echo htmlspecialchars($site_name, ENT_QUOTES, 'UTF-8'); ?></title>
    <link rel="icon" type="image/x-icon" href="<?php echo htmlspecialchars($setting['response'][0]['favicon'] ?? 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8Xw8AAoMBgDTD2qgAAAAASUVORK5CYII=', ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --ink: #0B2E23;
            --muted: #6B7F76;
            --line: #E2EEE8;
            --surface: #F4FAF6;
            --blue: #00B26B;
            --deep-blue: #046C44;
            --red: #e52c39;
            --green: #14885f;
        }

        * { box-sizing: border-box; }

        body {
            min-height: 100vh;
            margin: 0;
            padding: 0 24px;
            color: var(--ink);
            background: #EFF6F1;
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
            background: radial-gradient(circle at 85% 50%, rgba(255, 255, 255, .16), transparent 45%), linear-gradient(90deg, #00B26B 0%, #00965C 60%, #046C44 100%);
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

        .instruction {
            margin: 0 0 26px;
            color: var(--muted);
            font-size: 13px;
            line-height: 1.6;
            text-align: center;
        }

        .instruction strong { color: var(--ink); font-weight: 700; }

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

        .section-heading span {
            color: var(--muted);
            font-size: 11px;
            font-weight: 600;
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
        .copy-button svg { width: 16px; height: 16px; fill: none; stroke: currentColor; stroke-linecap: round; stroke-linejoin: round; stroke-width: 1.8; }

        .steps { margin: 18px 0 0; padding: 16px 18px; border: 1px dashed #B9DDC9; border-radius: 16px; background: #F4FAF6; }
        .steps h3 { margin: 0 0 10px; font-size: 13px; }
        .steps ol { margin: 0; padding-left: 18px; color: #33544A; font-size: 13px; line-height: 1.8; }

        .form-area { border-top: 1px solid var(--line); margin-top: 30px; padding-top: 26px; }
        .field { margin-bottom: 18px; }
        .field label { display: block; margin-bottom: 8px; color: var(--ink); font-size: 13px; font-weight: 700; }
        .mobile-number-box { display: none; }
        .input-wrap { position: relative; }
        .input-wrap i { position: absolute; left: 17px; top: 50%; transform: translateY(-50%); color: #A2A6AD; font-size: 15px; line-height: 1; z-index: 1; }
        .input { width: 100%; height: 54px; border: 1px solid var(--line); border-radius: 15px; padding: 0 18px 0 46px; line-height: 54px; font: inherit; outline: 0; background: #FCFCFD; }
        .input:focus { border-color: var(--blue); box-shadow: 0 0 0 4px rgba(0,178,107,.12); }

        .response { display: block; margin-bottom: 12px; }
        .alert { padding: 12px; border-radius: 12px; font-size: 13px; background: #fff0f0; color: #c0392b; }

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
            background: linear-gradient(105deg, #00B26B, #046C44);
            color: #fff;
            font-size: 14px;
            font-weight: 700;
            box-shadow: 0 10px 18px rgba(0, 178, 107, .22);
            transition: transform .2s, box-shadow .2s, filter .2s;
        }

        .verify-button:hover { filter: brightness(1.05); box-shadow: 0 13px 24px rgba(0, 178, 107, .28); transform: translateY(-1px); }
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
            .gateway { min-height: 100vh; border: 0; border-radius: 0; padding: 0 14px 20px; }
            .top { margin-bottom: 12px; }
            .amount { padding: 18px 20px; }
            .price { font-size: 28px; letter-spacing: -.5px; }
            .main { padding: 16px; border-radius: 0; }
            .bank-logo-image { height: 45px; max-width: 150px; }
            .content { width: 100%; padding-top: 0; min-width: 0; }
            .account-row { align-items: flex-start; flex-direction: column; gap: 6px; padding: 13px 15px; }
            .account-value { width: 100%; justify-content: space-between; text-align: left; }
        }
    </style>
</head>
<body>
    <main class="gateway">
        <header class="top">
            <button class="back" type="button" onclick="location.href='<?php echo htmlspecialchars($payment_link, ENT_QUOTES, 'UTF-8'); ?>'" aria-label="Back"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i><span>Back</span></button>
            <div class="bank-logo" aria-label="Taptap Send">
                <img class="bank-logo-image" src="<?php echo htmlspecialchars(pp_get_site_url().'/pp-content/plugins/payment-gateway/tap-agent/assets/icon.png', ENT_QUOTES, 'UTF-8'); ?>" alt="Taptap Send">
            </div>
            <span aria-hidden="true"></span>
        </header>

        <section class="card amount">
            <div class="eyebrow">Amount to Pay</div>
            <div class="price"><?php echo htmlspecialchars($amount, ENT_QUOTES, 'UTF-8'); ?></div>
        </section>

        <section class="card main">
            <div class="content">
            <div class="section-heading">
                <h2>Taptap Send payment details</h2>
            </div>
            <div class="account-card">
                <?php if ($account_name !== ''): ?>
                <div class="account-row">
                    <span class="account-label">Account name</span>
                    <span class="account-value"><span><?php echo htmlspecialchars($account_name, ENT_QUOTES, 'UTF-8'); ?></span><button type="button" class="copy-button" onclick="copyText(<?php echo htmlspecialchars(json_encode($account_name), ENT_QUOTES, 'UTF-8'); ?>, this)"><svg viewBox="0 0 24 24"><rect x="8" y="8" width="11" height="11" rx="2"></rect><path d="M16 8V6a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h2"></path></svg></button></span>
                </div>
                <?php endif; ?>
                <div class="account-row">
                    <span class="account-label">Number</span>
                    <span class="account-value"><span><?php echo htmlspecialchars($display_payment_number, ENT_QUOTES, 'UTF-8'); ?></span><button type="button" class="copy-button" onclick="copyText(<?php echo htmlspecialchars(json_encode($payment_number), ENT_QUOTES, 'UTF-8'); ?>, this)"><svg viewBox="0 0 24 24"><rect x="8" y="8" width="11" height="11" rx="2"></rect><path d="M16 8V6a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h2"></path></svg></button></span>
                </div>
                <div class="account-row">
                    <span class="account-label">Amount</span>
                    <span class="account-value"><span><?php echo htmlspecialchars($amount, ENT_QUOTES, 'UTF-8'); ?></span><button type="button" class="copy-button" onclick="copyText(<?php echo htmlspecialchars(json_encode($formatted_amount), ENT_QUOTES, 'UTF-8'); ?>, this)"><svg viewBox="0 0 24 24"><rect x="8" y="8" width="11" height="11" rx="2"></rect><path d="M16 8V6a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h2"></path></svg></button></span>
                </div>
            </div>

            <div class="steps"><h3>How to pay</h3><ol><li>Open the Taptap Send app.</li><li>Enter the amount: <strong><?php echo htmlspecialchars($amount, ENT_QUOTES, 'UTF-8'); ?></strong></li><li>Click: Add new recipient</li><li>Choose: bKash Personal</li><li>Enter the number: <strong><?php echo htmlspecialchars($payment_number, ENT_QUOTES, 'UTF-8'); ?></strong></li><li>Now click Send and confirm.</li></ol></div>

            <div class="form-area">
                <div class="field mobile-number-box"><label>Your Mobile Number</label><div class="input-wrap"><i class="fa-solid fa-mobile-screen-button"></i><input class="input mobile-number" placeholder="01XXXXXXXXX"></div></div>
                <div class="field"><label>Transaction ID</label><div class="input-wrap"><i class="fa-solid fa-hashtag"></i><input class="input transaction-id" placeholder="Enter Transaction ID"></div></div>
                <span class="response"></span>
                <button type="button" class="verify-button">
                    <i class="fa-solid fa-shield-halved" aria-hidden="true"></i>
                    <span>Verify</span>
                </button>
            </div>
            </div>
        </section>
        <footer class="gateway-footer">Secure payment verification · Powered by <strong>KeyClan</strong></footer>
    </main>

    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.4.1/jquery.min.js"></script>
    <script>
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

        $('.verify-button').on('click', function(){
            var btn = this, trxid = $('.transaction-id').val(), number = $('.mobile-number').val();
            if(!trxid){ $('.response').text('Enter transaction ID').addClass('alert'); return; }
            btn.disabled = true; btn.querySelector('span').textContent = 'Verifying…';
            $.ajax({
                type: 'POST',
                url: '<?php echo $payment_link; ?>?method=tap-agent',
                data: {'tap-agent': '<?php echo $payment_id; ?>', trxid: trxid, number: number},
                success: function(data){
                    btn.disabled = false; btn.querySelector('span').textContent = 'Verify';
                    var result = JSON.parse(data);
                    if(result.status === 'false'){
                        if(result.numberbox === 'true'){ $('.mobile-number-box').show(); $('.response').text('Enter mobile number').addClass('alert'); }
                        else $('.response').text(result.message || 'Request failed').addClass('alert');
                    } else location.href = '<?php echo $payment_link; ?>';
                }
            });
        });
    </script>
</body>
</html>
