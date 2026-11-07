<?php
    $setting = pp_get_settings();
    $plugin_slug = 'usdt-manual';
    $plugin_info = pp_get_plugin_info($plugin_slug);
    $settings = pp_get_plugin_setting($plugin_slug);
    $wallets = usdt_manual_active_wallets($settings);
    $gateway_payment = pp_calculate_gateway_payment_amount($payment_id, $plugin_slug);
    $amount = number_format(safeNumber($gateway_payment['total'] ?? 0), 2, '.', '');

    if (isset($_POST['usdt-manual'])) {
        $respond = function ($ok, $message) {
            echo json_encode(["status" => $ok ? "true" : "false", "message" => $message]);
            exit();
        };

        $network = (string) ($_POST['network'] ?? '');
        if (!isset($wallets[$network])) {
            $respond(false, 'Choose a network to continue.');
        }
        $hash = usdt_manual_normalize_hash($network, $_POST['tx_hash'] ?? '');
        if ($hash === null) {
            $respond(false, 'That is not a valid '.$wallets[$network]['name'].' transaction hash. Copy it from your wallet or exchange.');
        }
        $existing = pp_check_transaction_exits($hash);
        if (($existing['status'] ?? false) == true) {
            $respond(false, 'This transaction hash has already been submitted.');
        }

        $receipt_url = '';
        $receipt = $_FILES['receipt'] ?? null;
        if (is_array($receipt) && ($receipt['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $max_size = 5 * 1024 * 1024;
            $upload = ($receipt['error'] === UPLOAD_ERR_OK && $receipt['size'] <= $max_size) ? json_decode(uploadReceipt($receipt, $max_size), true) : null;
            if (($upload['status'] ?? false) !== true) {
                $respond(false, 'Your receipt could not be uploaded. Use a JPG, PNG or PDF under 5MB.');
            }
            $receipt_url = 'https://'.$_SERVER['HTTP_HOST'].'/pp-external/media/'.$upload['file'];
        }

        if (!usdt_manual_save_details($payment_id, $network, $wallets[$network]['address'], $hash, $receipt_url)) {
            $respond(false, 'This transaction hash has already been submitted.');
        }
        if (!pp_set_transaction_byid($payment_id, $plugin_slug, $plugin_info['plugin_name'], $wallets[$network]['name'], $hash, 'pending')) {
            usdt_manual_delete_details($payment_id);
            $respond(false, 'Unable to submit your payment. Please try again.');
        }
        $respond(true, 'Payment submitted');
    }

    $display_name = $settings['display_name'] ?? $plugin_info['plugin_name'];
    $site_name = $setting['response'][0]['site_name'] ?? '';
    $logo = pp_get_site_url().'/pp-content/plugins/'.$plugin_info['plugin_dir'].'/'.$plugin_slug.'/assets/icon.png';
    $payment_link = pp_get_paymentlink($payment_id);
    $first = reset($wallets);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo htmlspecialchars($display_name); ?> - <?php echo htmlspecialchars($site_name); ?></title>
<link rel="icon" href="<?php echo isset($setting['response'][0]['favicon']) && $setting['response'][0]['favicon'] != '--' ? htmlspecialchars($setting['response'][0]['favicon'], ENT_QUOTES) : 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8Xw8AAoMBgDTD2qgAAAAASUVORK5CYII='?>">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;700&display=swap" rel="stylesheet" media="print" onload="this.media='all'"><noscript><link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;700&display=swap" rel="stylesheet"></noscript><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" media="print" onload="this.media='all'"><noscript><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"></noscript>
<style>
:root{--accent:#0D9488;--accent2:#0B7F75;--accent-soft:#E6F6F4;--ink:#1A1A1A;--muted:#70757D;--line:#F1F2F5;--line2:#E7EAF3}
*{box-sizing:border-box}
body{margin:0;background:#FAFAFC;color:var(--ink);font-family:Inter,sans-serif}
button,input{font:inherit}
.page{width:min(620px,calc(100% - 32px));margin:16px auto;background:#fff;border-radius:24px;padding:0 20px 14px;box-shadow:0 14px 40px rgba(26,26,26,.06)}
.top{display:grid;grid-template-columns:70px 1fr 70px;align-items:center;padding:10px 0}
.top img{height:34px;justify-self:center}
.back{border:0;background:transparent;color:var(--muted);cursor:pointer;display:flex;align-items:center;gap:7px;font-weight:600;font-size:14px;padding:0}
.back i{font-size:18px}
.amount{padding:12px 22px;background:radial-gradient(circle at 90% 40%,rgba(45,212,191,.28),transparent 50%),linear-gradient(95deg,#0B1F2A,#123845);color:#fff;border-radius:16px 16px 0 0}
.eyebrow{font-size:12px;font-weight:600;opacity:.85}
.price{font-size:26px;font-weight:800;letter-spacing:-.8px;margin-top:1px}
.main{border:1px solid var(--line);border-top:0;border-radius:0 0 16px 16px;padding:14px 22px 16px;display:grid;gap:12px}
.label{display:block;font-size:13px;font-weight:700;margin-bottom:6px}
.opt{font-weight:500;color:var(--muted)}
.chips{display:flex;flex-wrap:wrap;gap:8px}
.chip{display:inline-flex;align-items:center;gap:8px;border:1.5px solid var(--line2);background:#fff;color:var(--ink);border-radius:11px;padding:7px 12px;font-size:13px;font-weight:600;cursor:pointer}
.chip:hover{border-color:var(--accent)}
.chip[aria-pressed="true"]{border-color:var(--accent);background:var(--accent-soft);color:var(--accent2)}
.net-icon{flex:none;display:block}
.warn{display:flex;gap:9px;background:#FFF7E6;border:1px solid #F5DFA8;color:#8A5A00;font-size:13px;line-height:1.4;border-radius:11px;padding:8px 12px}
.warn i{margin-top:3px}
.pay{display:grid;gap:10px;text-align:center}
.qr-frame{position:relative;width:158px;height:158px;margin:auto;padding:4px;background:linear-gradient(var(--accent),var(--accent)) top left/18px 3px no-repeat,linear-gradient(var(--accent),var(--accent)) top left/3px 18px no-repeat,linear-gradient(var(--accent),var(--accent)) top right/18px 3px no-repeat,linear-gradient(var(--accent),var(--accent)) top right/3px 18px no-repeat,linear-gradient(var(--accent),var(--accent)) bottom left/18px 3px no-repeat,linear-gradient(var(--accent),var(--accent)) bottom left/3px 18px no-repeat,linear-gradient(var(--accent),var(--accent)) bottom right/18px 3px no-repeat,linear-gradient(var(--accent),var(--accent)) bottom right/3px 18px no-repeat}
.qr-frame img{display:block;width:150px;height:150px;object-fit:contain;background:#fff;padding:5px}
.addr-box{display:flex;align-items:center;gap:8px;background:#FCFCFD;border:1px solid var(--line);border-radius:12px;padding:3px 4px 3px 12px;text-align:left}
.addr{flex:1;min-width:0;font-family:"JetBrains Mono",ui-monospace,Consolas,monospace;font-size:13.5px;line-height:1.5;overflow-wrap:anywhere;color:var(--muted)}
.addr b{color:var(--ink);font-weight:700}
.copy{flex:none;width:36px;height:36px;display:grid;place-items:center;border:0;background:transparent;color:var(--accent2);border-radius:9px;cursor:pointer;font-size:16px}
.copy:hover{background:var(--accent-soft)}
.hint{font-size:12px;color:var(--muted);line-height:1.4;margin:5px 0 0}
.form{border-top:1px solid var(--line);padding-top:12px;display:grid;gap:10px}
.input{width:100%;height:42px;border:1px solid var(--line2);border-radius:12px;padding:0 15px;background:#FCFCFD;outline:0;font-family:"JetBrains Mono",ui-monospace,Consolas,monospace;font-size:13px;color:var(--ink)}
.input:focus{border-color:var(--accent);box-shadow:0 0 0 4px rgba(13,148,136,.14)}
.upload{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:0 14px;align-items:center;padding:7px 12px;border:1.5px dashed #9FD8D2;border-radius:12px;background:#FAFDFC;cursor:pointer}
.upload:hover{border-color:var(--accent)}
.upload-title{font-size:13px;font-weight:700;min-width:0}
.upload-sub{grid-column:1;font-size:11px;color:var(--muted);overflow-wrap:anywhere}
.choose{grid-column:2;grid-row:1 / span 2;background:#fff;border:1px solid #9FD8D2;color:var(--accent2);padding:6px 12px;border-radius:9px;font-size:12px;font-weight:700}
.payment-file{position:absolute;width:1px;height:1px;overflow:hidden;clip-path:inset(50%)}
.response-message{padding:11px 13px;border-radius:10px;background:#fff1f1;color:#b52e38;font-size:13px;font-weight:600}
.btn-pay{width:100%;padding:12px 20px;border:0;border-radius:14px;background:linear-gradient(100deg,var(--accent),var(--accent2));color:#fff;font-weight:700;font-size:14px;cursor:pointer;box-shadow:0 10px 20px rgba(13,148,136,.22)}
.btn-pay:disabled{opacity:.7;cursor:default}
.footer{text-align:center;color:#9a9da3;font-size:12px;margin-top:10px}
[hidden]{display:none!important}
@media(max-width:600px){.page{width:calc(100% - 16px);margin:8px auto;padding:0 12px 12px}.main{padding:12px 14px 14px}.amount{padding:10px 16px}.top{grid-template-columns:60px 1fr 60px}}
</style></head>
<body><main class="page">
<header class="top"><button class="back" type="button" onclick="location.href='<?php echo htmlspecialchars($payment_link, ENT_QUOTES); ?>'" aria-label="Back"><i class="fa-solid fa-arrow-left"></i><span>Back</span></button><img src="<?php echo htmlspecialchars($logo, ENT_QUOTES); ?>" alt="USDT"><span aria-hidden="true"></span></header>
<section class="amount"><div class="eyebrow">Amount to Pay</div><div class="price"><?php echo $amount; ?> USDT</div></section>
<section class="main">
<?php if ($first === false): ?>
    <div class="warn"><span>USDT payments are not available right now. Go back and choose another payment method.</span></div>
<?php else: ?>
    <div>
        <span class="label">Network</span>
        <div class="chips">
            <?php foreach ($wallets as $key => $wallet): ?>
                <button class="chip" type="button" data-network="<?php echo $key; ?>" aria-pressed="<?php echo $wallet === $first ? 'true' : 'false'; ?>"><?php echo usdt_manual_networks()[$key]['icon']; ?><?php echo htmlspecialchars($wallet['name']); ?></button>
            <?php endforeach; ?>
        </div>
    </div>
    <div class="warn"><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i><span>Send only <strong>USDT</strong> on <strong id="warn-network"></strong>. Funds sent on other networks will be lost.</span></div>
    <div class="pay">
        <div id="qr-block">
            <span class="label">Scan to pay</span>
            <div class="qr-frame"><img id="qr-image" alt="Payment QR code" fetchpriority="high"></div>
        </div>
        <div>
            <span class="label">Wallet address</span>
            <div class="addr-box"><div class="addr" id="address"></div><button class="copy" id="copy-address" type="button" aria-label="Copy address" title="Copy address"><i class="fa-regular fa-copy"></i></button></div>
            <p class="hint">Check the first and last 6 characters. Send <?php echo $amount; ?> USDT plus your network fee.</p>
        </div>
    </div>
    <div class="form">
        <div>
            <label class="label" for="tx-hash">Transaction hash</label>
            <input class="input" id="tx-hash" type="text" autocomplete="off" spellcheck="false" placeholder="Paste the hash from your wallet or exchange">
        </div>
        <div>
            <span class="label">Payment receipt <span class="opt">(optional)</span></span>
            <label class="upload" for="receipt">
                <span class="upload-title">Add a screenshot of your transfer</span>
                <span class="upload-sub" id="receipt-name">JPG, PNG or PDF, up to 5MB</span>
                <span class="choose">Choose File</span>
            </label>
            <input class="payment-file" id="receipt" type="file" accept="image/jpeg,image/png,application/pdf">
        </div>
        <div class="response-message" id="response" aria-live="polite" hidden></div>
        <button class="btn-pay" id="verify" type="button"><span>I've sent the payment</span></button>
    </div>
<?php endif; ?>
</section>
<footer class="footer">Secure payment verification · Powered by <strong>KeyClan</strong></footer>
</main>
<?php if ($first !== false): ?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.4.1/jquery.min.js"></script>
<script>
(function () {
    var wallets = <?php echo json_encode($wallets, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
    var paymentLink = <?php echo json_encode($payment_link, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
    var selected = <?php echo json_encode(array_key_first($wallets)); ?>;
    var addressBox = document.getElementById('address');
    var copyButton = document.getElementById('copy-address');
    var hashInput = document.getElementById('tx-hash');
    var receiptInput = document.getElementById('receipt');
    var response = document.getElementById('response');
    var verify = document.getElementById('verify');

    function showError(message) {
        response.textContent = message;
        response.hidden = false;
    }

    function render() {
        var wallet = wallets[selected];
        var address = wallet.address;
        document.querySelectorAll('[data-network]').forEach(function (chip) {
            chip.setAttribute('aria-pressed', chip.dataset.network === selected ? 'true' : 'false');
        });
        document.getElementById('warn-network').textContent = wallet.name;
        document.getElementById('qr-block').hidden = !wallet.qr_code;
        if (wallet.qr_code) {
            document.getElementById('qr-image').src = wallet.qr_code;
        }
        addressBox.textContent = '';
        var head = document.createElement('b');
        head.textContent = address.slice(0, 6);
        var tail = document.createElement('b');
        tail.textContent = address.slice(-6);
        addressBox.append(head, address.slice(6, -6), tail);
        response.hidden = true;
    }

    document.querySelector('.chips').addEventListener('click', function (event) {
        var chip = event.target.closest('[data-network]');
        if (chip) {
            selected = chip.dataset.network;
            render();
        }
    });

    copyButton.addEventListener('click', function () {
        var flash = function () {
            copyButton.innerHTML = '<i class="fa-solid fa-check"></i>';
            setTimeout(function () { copyButton.innerHTML = '<i class="fa-regular fa-copy"></i>'; }, 1500);
        };
        var selectText = function () {
            var range = document.createRange();
            range.selectNodeContents(addressBox);
            var selection = window.getSelection();
            selection.removeAllRanges();
            selection.addRange(range);
        };
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(wallets[selected].address).then(flash, selectText);
        } else {
            selectText();
        }
    });

    receiptInput.addEventListener('change', function () {
        var file = this.files[0];
        document.getElementById('receipt-name').textContent = file ? file.name : 'JPG, PNG or PDF, up to 5MB';
    });

    function setBusy(busy) {
        verify.disabled = busy;
        verify.querySelector('span').textContent = busy ? 'Submitting…' : "I've sent the payment";
    }

    verify.addEventListener('click', function () {
        var hash = hashInput.value.trim();
        var file = receiptInput.files[0];
        response.hidden = true;
        if (hash === '') {
            showError('Enter the transaction hash from your wallet or exchange. It is the long ID shown on the transfer details.');
            return;
        }
        if (file && file.size > 5 * 1024 * 1024) {
            showError('Your receipt must be smaller than 5MB.');
            return;
        }
        setBusy(true);
        var formData = new FormData();
        formData.append('usdt-manual', '1');
        formData.append('network', selected);
        formData.append('tx_hash', hash);
        if (file) {
            formData.append('receipt', file);
        }
        $.ajax({
            type: 'POST',
            url: paymentLink + '?method=usdt-manual',
            data: formData,
            contentType: false,
            processData: false,
            success: function (data) {
                var result = null;
                try {
                    result = JSON.parse(data);
                } catch (error) {}
                if (result && result.status === 'true') {
                    location.href = paymentLink;
                    return;
                }
                showError(result ? result.message : 'Unable to submit your payment. Please try again.');
                setBusy(false);
            },
            error: function () {
                showError('Unable to submit your payment. Please try again.');
                setBusy(false);
            }
        });
    });

    render();
})();
</script>
<?php endif; ?>
</body></html>
