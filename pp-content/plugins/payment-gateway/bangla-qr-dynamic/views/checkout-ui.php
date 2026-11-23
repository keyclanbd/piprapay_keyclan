<?php

if (!defined('pp_allowed_access')) {
    die('Direct access not allowed');
}

$plugin_slug = 'bangla-qr-dynamic';
$plugin_info = pp_get_plugin_info($plugin_slug);
$settings = pp_get_plugin_setting($plugin_slug);
$settings = is_array($settings) ? $settings : array();
$transaction_details = pp_get_transation($payment_id);
$setting = pp_get_settings();

if (isset($_POST['bangla-qr-dynamic-slip'])) {
    header('Content-Type: application/json');
    if (pp_set_transaction_byslip($payment_id, $plugin_slug, $plugin_info['plugin_name'], $_FILES['payment_slip'] ?? null, 'pending')) {
        echo json_encode(array('status' => 'true', 'message' => 'Receipt uploaded'));
    } else {
        echo json_encode(array('status' => 'false', 'message' => 'Failed to upload receipt'));
    }
    exit();
}

if (isset($_POST['bangla-qr-dynamic'])) {
    header('Content-Type: application/json');
    $trx = trim((string) ($_POST['trxid'] ?? ''));
    if (!preg_match('/^[A-Za-z0-9_-]{1,100}$/', $trx)) {
        echo json_encode(array('status' => 'false', 'message' => 'Enter the transaction ID shown in your payment app (letters and numbers only).'));
        exit();
    }

    if (($settings['pending_payment'] ?? 'enable') !== 'enable') {
        echo json_encode(array('status' => 'false', 'message' => 'Automatic verification is unavailable. Please upload your payment receipt instead.'));
        exit();
    }

    if (pp_set_transaction_byid($payment_id, $plugin_slug, $plugin_info['plugin_name'], $trx, $trx, 'pending')) {
        echo json_encode(array('status' => 'true', 'message' => 'Payment submitted for review'));
    } else {
        echo json_encode(array('status' => 'false', 'message' => 'Unable to submit this transaction ID. Please try again.'));
    }
    exit();
}

$payment = pp_calculate_gateway_payment_amount($payment_id, $plugin_slug);
$payable = ($payment['status'] ?? false) === true
    ? safeNumber($payment['total'] ?? 0)
    : safeNumber($transaction_details['response'][0]['transaction_amount'] ?? 0);

$currency_code = strtoupper((string) ($payment['currency'] ?? ($settings['currency'] ?? 'BDT')));
$trim_zero_cents = strtolower((string) ($settings['trim_zero_cents'] ?? 'disable')) === 'enable';
$built = bangla_qr_dynamic_build_payload($settings, $payable, $trim_zero_cents);

$qr_src = '';
$qr_error = '';
if (($built['ok'] ?? false) === true) {
    try {
        $matrix = BqrQrEncoder::encode(
            $built['payload'],
            (string) ($settings['error_correction'] ?? 'M')
        );
        $qr_src = bqr_render_data_uri($matrix, (int) ($settings['qr_scale'] ?? 8), 4);
    } catch (Throwable $e) {
        $qr_error = 'The payment QR could not be generated.';
    }
} else {
    $qr_error = (string) ($built['error'] ?? 'The payment QR is not available.');
}

$encoded_amount = $built['ok'] ? (bqr_parse_emv($built['payload'])['fields']['amount'] ?? '') : '';
$display_amount = number_format($payable, 2, '.', '');
$merchant_name = (string) ($settings['account_name'] ?? '');
if ($merchant_name === '') {
    $merchant_name = (string) ($settings['merchant_name'] ?? '');
}
$qr_download_name = 'bangla-qr-' . preg_replace('/[^0-9.]/', '', $display_amount) . '.png';
$logo = pp_get_site_url() . '/pp-content/plugins/' . ($plugin_info['plugin_dir'] ?? 'payment-gateway') . '/' . $plugin_slug . '/assets/icon.png';
$site_name = (string) ($setting['response'][0]['site_name'] ?? '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo htmlspecialchars((string) ($settings['display_name'] ?? $plugin_info['plugin_name'])); ?> - <?php echo htmlspecialchars($site_name); ?></title>
<link rel="icon" href="<?php echo isset($setting['response'][0]['favicon']) && $setting['response'][0]['favicon'] !== '--' ? htmlspecialchars($setting['response'][0]['favicon']) : 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8Xw8AAoMBgDTD2qgAAAAASUVORK5CYII='; ?>">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet" media="print" onload="this.media='all'"><noscript><link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet"></noscript>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" media="print" onload="this.media='all'"><noscript><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"></noscript>
<style>
:root{--green:#006A4E;--green2:#00805E;--red:#F42A41;--ink:#1A1A1A;--muted:#70757D;--line:#F1F2F5}
*{box-sizing:border-box}
body{margin:0;background:#FAFAFC;color:var(--ink);font-family:Inter,sans-serif}
.page{width:min(620px,calc(100% - 32px));margin:34px auto 48px;background:#fff;border-radius:24px;padding:0 20px 24px;box-shadow:0 14px 40px rgba(26,26,26,.06)}
.top{display:grid;grid-template-columns:auto 1fr auto;align-items:center;gap:12px;padding:18px 20px 0;margin-bottom:24px}
.top img{height:44px;justify-self:center}
.back{width:auto;height:auto;padding:0;border:0;background:transparent;color:var(--muted);cursor:pointer;display:flex;align-items:center;gap:7px;font:600 14px Inter,sans-serif}
.back i{font-size:18px}
.card{background:#fff;border:1px solid var(--line);border-radius:24px;box-shadow:0 14px 40px rgba(26,26,26,.06)}
.amount{position:relative;overflow:hidden;padding:20px 30px;background:radial-gradient(circle at 85% 50%,rgba(0,0,0,.08),transparent 45%),linear-gradient(90deg,#006A4E 0%,#00805E 60%,#F42A41 160%);color:#fff;border-radius:16px 16px 0 0;box-shadow:none}
.eyebrow{font-size:13px;font-weight:600;opacity:.85}
.price{font-size:36px;font-weight:800;letter-spacing:-1.5px;margin-top:4px}
.main{padding:30px;border-radius:0 0 16px 16px;border-top:0}
.scan{text-align:center}
.scan h2{font-size:16px;font-weight:700;margin:0 0 14px}
.qr-frame{position:relative;display:inline-block;padding:14px;border:1px solid var(--line);border-radius:20px;background:#fff}
.qr{display:block;width:280px;max-width:100%;height:auto;border-radius:8px}
.qr-dl{position:absolute;right:-10px;top:-10px;width:38px;height:38px;border-radius:50%;border:1px solid var(--line);background:#fff;color:var(--green);cursor:pointer;display:flex;align-items:center;justify-content:center;box-shadow:0 6px 18px rgba(26,26,26,.12)}
.recipient{display:flex;flex-direction:column;gap:6px;align-items:center;margin:16px 0 4px}
.recipient strong{font-size:16px}
.account-name{color:#fff;background:var(--green);border-radius:10px;padding:5px 10px}
.recipient-name{display:flex;align-items:center;justify-content:center;gap:6px}
.alert{background:#FFF4F5;color:#9B1C2B;border:1px solid #FFD5DA;border-radius:12px;padding:12px 14px;font-size:14px;font-weight:600;margin:14px 0 0}
.verify-tabs{display:flex;gap:8px;margin:26px 0 16px;background:#F6F7F9;padding:5px;border-radius:14px}
.verify-tab{flex:1;border:0;background:transparent;padding:10px;border-radius:10px;font:600 13px Inter,sans-serif;color:var(--muted);cursor:pointer}
.verify-tab.active{background:#fff;color:var(--ink);box-shadow:0 4px 12px rgba(26,26,26,.08)}
.verify-pane{display:none}
.verify-pane.active{display:block}
.field label{display:block;font-size:13px;color:var(--muted);font-weight:600;margin-bottom:8px}
.input{width:100%;padding:13px 14px;border:1px solid var(--line);border-radius:12px;font:500 15px Inter,sans-serif;color:var(--ink);background:#fff}
.input:focus{outline:2px solid rgba(0,106,78,.25);border-color:var(--green)}
.btn-pay{width:100%;margin-top:12px;padding:14px;border:0;border-radius:14px;background:var(--green);color:#fff;font:700 15px Inter,sans-serif;cursor:pointer}
.btn-pay:disabled{opacity:.7;cursor:default}
.upload-box{display:flex;align-items:center;gap:12px;justify-content:space-between;border:1px dashed #C9CDD4;border-radius:14px;padding:14px;cursor:pointer;background:#FCFCFD}
.upload-box.has-file{border-color:var(--green);background:#F3FBF8}
.upload-copy{display:flex;align-items:center;gap:10px;font-size:14px;font-weight:600}
.upload-icon{width:34px;height:34px;border-radius:50%;background:#EAF6F1;color:var(--green);display:flex;align-items:center;justify-content:center}
.upload-title{font-weight:600}
.choose-file{font:700 12px Inter,sans-serif;color:var(--green);border:1px solid rgba(0,106,78,.25);padding:7px 10px;border-radius:9px;white-space:nowrap}
.file-helper,.file-name{display:block;font-size:12px;color:var(--muted);font-weight:500}
.file-name{margin-top:2px}
.slip-input{display:none}
.response,.response-slip{display:block;margin-top:10px;font-size:13px;font-weight:600;color:#9B1C2B}
.footer{text-align:center;font-size:12px;color:var(--muted);margin-top:22px}
@media(max-width:520px){.qr{width:230px}.main{padding:22px}}
</style>
</head>
<body>
<main class="page">
  <header class="top">
    <button class="back" type="button" onclick="location.href='<?php echo pp_get_paymentlink($payment_id); ?>'" aria-label="Back"><i class="fa-solid fa-arrow-left"></i><span>Back</span></button>
    <img src="<?php echo htmlspecialchars($logo); ?>" alt="Bangla QR">
    <span aria-hidden="true"></span>
  </header>

  <section class="card amount">
    <div class="eyebrow">Amount to Pay</div>
    <div class="price"><?php echo htmlspecialchars($display_amount . ' ' . $currency_code); ?></div>
  </section>

  <section class="card main">
    <div class="scan">
      <?php if ($qr_src !== ''): ?>
        <h2>Scan to Pay</h2>
        <div class="qr-frame">
          <img class="qr" id="bqd-qr-img" src="<?php echo $qr_src; ?>" alt="Payment QR code" data-download="<?php echo htmlspecialchars($qr_download_name); ?>">
          <button type="button" class="qr-dl" id="bqd-qr-dl" aria-label="Download QR code" title="Download QR"><i class="fa-solid fa-download"></i></button>
        </div>
        <div class="recipient">
          <?php if ($merchant_name !== ''): ?>
            <div class="recipient-name"><span class="account-name"><?php echo htmlspecialchars($merchant_name); ?></span></div>
          <?php endif; ?>
          <span class="account-type">Scan with any Bangla QR enabled app. The amount is already filled in.</span>
        </div>
        <?php if ($encoded_amount !== '' && abs((float) $encoded_amount - (float) $payable) > 0.004): ?>
          <div class="alert">The QR was generated for <?php echo htmlspecialchars($encoded_amount); ?> but the total is <?php echo htmlspecialchars($display_amount); ?>.</div>
        <?php endif; ?>
      <?php else: ?>
        <div class="alert"><?php echo htmlspecialchars($qr_error !== '' ? $qr_error : 'Payment QR is not available right now. Please go back and choose another payment method.'); ?></div>
      <?php endif; ?>
    </div>

    <?php if ($qr_src !== ''): ?>
    <div class="verify-tabs" role="tablist" aria-label="Verification method">
      <button type="button" class="verify-tab active" data-pane="trxid-pane" role="tab" aria-selected="true"><i class="fa-solid fa-receipt"></i> Transaction ID</button>
      <button type="button" class="verify-tab" data-pane="slip-pane" role="tab" aria-selected="false"><i class="fa-solid fa-cloud-arrow-up"></i> Upload Receipt</button>
    </div>

    <div class="verify-pane active" id="trxid-pane">
      <div class="field">
        <label for="bqd-trxid">Transaction ID from your payment app</label>
        <input class="input" id="bqd-trxid" name="bqd-trxid" placeholder="e.g. 9F2K7LQ1XZ" autocomplete="off">
      </div>
      <span class="response" id="bqd-response" aria-live="polite"></span>
      <button class="btn-pay" type="button" id="bqd-verify"><i class="fa-solid fa-shield-halved"></i>&nbsp; Verify Payment</button>
    </div>

    <div class="verify-pane" id="slip-pane">
      <div class="field">
        <label class="upload-label" for="bqd-slip" style="color:var(--ink)">Upload payment receipt</label>
        <label class="upload-box" for="bqd-slip" id="bqd-slip-box">
          <span class="upload-copy"><span class="upload-icon"><i class="fa-solid fa-cloud-arrow-up"></i></span><span><span class="upload-title">Choose your receipt</span><span class="file-helper">JPG &bull; PNG &bull; PDF &bull; Max 5MB</span><span class="file-name" aria-live="polite"></span></span></span>
          <span class="choose-file">Browse</span>
        </label>
        <input id="bqd-slip" name="payment_slip" class="slip-input" type="file" accept="image/jpeg,image/png,application/pdf">
      </div>
      <span class="response-slip" id="bqd-slip-response" aria-live="polite"></span>
      <button class="btn-pay" type="button" id="bqd-slip-submit"><i class="fa-solid fa-cloud-arrow-up"></i>&nbsp; Upload Receipt</button>
    </div>
    <?php endif; ?>
  </section>

  <footer class="footer">Secure payment verification &middot; Powered by <strong>KeyClan</strong></footer>
</main>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.4.1/jquery.min.js"></script>
<script>
(function () {
    var endpoint = <?php echo json_encode(pp_get_paymentlink($payment_id) . '?method=' . $plugin_slug); ?>;
    var paymentId = <?php echo json_encode((string) $payment_id); ?>;

    var downloadButton = document.getElementById('bqd-qr-dl');
    if (downloadButton) {
        downloadButton.addEventListener('click', function () {
            var img = document.getElementById('bqd-qr-img');
            if (!img || !img.src) { return; }
            var link = document.createElement('a');
            link.href = img.src;
            link.download = img.getAttribute('data-download') || 'bangla-qr-payment.png';
            document.body.appendChild(link);
            link.click();
            link.remove();
        });
    }

    $('.verify-tab').on('click', function () {
        var pane = $(this).data('pane');
        $('.verify-tab').removeClass('active').attr('aria-selected', 'false');
        $(this).addClass('active').attr('aria-selected', 'true');
        $('.verify-pane').removeClass('active');
        $('#' + pane).addClass('active');
    });

    var slipInput = document.getElementById('bqd-slip');
    if (slipInput) {
        slipInput.addEventListener('change', function () {
            var file = this.files[0];
            var box = document.getElementById('bqd-slip-box');
            var name = box ? box.querySelector('.file-name') : null;
            if (!file) { return; }
            if (box) { box.classList.add('has-file'); }
            if (name) { name.textContent = file.name; }
        });
    }

    $('#bqd-slip-submit').on('click', function () {
        var button = this;
        var box = $('#bqd-slip-response');
        var file = slipInput ? slipInput.files[0] : null;
        box.text('');
        if (!file) {
            box.text('Please choose your payment receipt first.');
            return;
        }
        if (file.size > 5 * 1024 * 1024) {
            box.text('Your receipt must be smaller than 5MB.');
            return;
        }

        var original = button.innerHTML;
        button.disabled = true;
        button.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>&nbsp; Uploading...';

        var data = new FormData();
        data.append('bangla-qr-dynamic-slip', paymentId);
        data.append('payment_slip', file);

        $.ajax({
            type: 'POST',
            url: endpoint,
            data: data,
            contentType: false,
            processData: false,
            success: function (response) {
                var result = null;
                try { result = JSON.parse(response); } catch (e) { result = null; }
                if (result && result.status === 'true') {
                    location.href = <?php echo json_encode(pp_get_paymentlink($payment_id)); ?>;
                } else {
                    box.text((result && result.message) ? result.message : 'Upload failed.');
                    button.disabled = false;
                    button.innerHTML = original;
                }
            },
            error: function () {
                box.text('Unable to upload the receipt. Please try again.');
                button.disabled = false;
                button.innerHTML = original;
            }
        });
    });

    $('#bqd-verify').on('click', function () {
        var button = this;
        var field = $('#bqd-response');
        var trxid = $.trim($('#bqd-trxid').val() || '');
        var pattern = /^[A-Za-z0-9_-]{1,100}$/;
        field.text('');

        if (!pattern.test(trxid)) {
            field.text('Enter the transaction ID from your payment app.');
            return;
        }

        var original = button.innerHTML;
        button.disabled = true;
        button.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>&nbsp; Verifying...';

        $.ajax({
            type: 'POST',
            url: endpoint,
            data: { 'bangla-qr-dynamic': paymentId, trxid: trxid },
            success: function (response) {
                var result = null;
                try { result = JSON.parse(response); } catch (e) { result = null; }
                if (result && result.status === 'true') {
                    location.href = <?php echo json_encode(pp_get_paymentlink($payment_id)); ?>;
                    return;
                }
                field.text((result && result.message) ? result.message : 'Request failed.');
                button.disabled = false;
                button.innerHTML = original;
            },
            error: function () {
                field.text('Unable to verify the payment. Please try again.');
                button.disabled = false;
                button.innerHTML = original;
            }
        });
    });
})();
</script>
</body>
</html>
