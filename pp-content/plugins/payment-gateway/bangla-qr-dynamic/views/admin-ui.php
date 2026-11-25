<?php

if (!defined('pp_allowed_access')) {
    die('Direct access not allowed');
}

// A copy of this gateway runs this same code under its own slug and settings.
$plugin_slug = pp_current_gateway_slug(BQR_DYNAMIC_SLUG);
$plugin_info = pp_get_plugin_info($plugin_slug);
$settings = pp_get_plugin_setting($plugin_slug);
$settings = is_array($settings) ? $settings : array();

$merchant_payload = (string) ($settings['merchant_payload'] ?? '');
$merchant_state = $merchant_payload === '' ? array() : bqr_validate_merchant_payload($merchant_payload);
$merchant_fields = ($merchant_state['ok'] ?? false) ? $merchant_state['fields'] : array();
$merchant_currency = (string) ($merchant_fields['currency'] ?? '');
$merchant_gui = (string) ($settings['merchant_gui'] ?? '');
$gateway_currency = (string) ($settings['currency'] ?? 'BDT');
$currency_mismatch = $merchant_currency !== ''
    && bangla_qr_dynamic_currency_numeric($gateway_currency) !== ''
    && bangla_qr_dynamic_currency_numeric($gateway_currency) !== $merchant_currency;
$logo_url = pp_get_site_url() . '/pp-content/plugins/' . ($plugin_info['plugin_dir'] ?? 'payment-gateway') . '/' . BQR_DYNAMIC_SLUG . '/assets/icon.png';
?>
<form id="bqdSettingsForm" method="post" action="" enctype="multipart/form-data">
    <div class="page-header">
      <div class="row align-items-end">
        <div class="col-sm mb-2 mb-sm-0">
          <h1 class="page-header-title">Edit Gateway</h1>
        </div>
      </div>
    </div>

    <div class="row justify-content-center">
      <div class="col-lg-8">
        <div class="d-grid gap-3 gap-lg-5">

          <div class="card">
            <div class="card-header">
              <h2 class="card-title h4">Gateway Information</h2>
            </div>
            <div class="card-body">
                <input type="hidden" name="action" value="plugin_update-submit">
                <input type="hidden" name="plugin_slug" value="<?php echo htmlspecialchars($plugin_slug); ?>">

                <div class="row mb-4">
                  <div class="col-sm-6">
                    <label for="name" class="col-sm-12 col-form-label form-label">Name</label>
                    <div class="input-group">
                        <input type="text" class="form-control" name="name" id="name" value="<?php echo htmlspecialchars($settings['name'] ?? $plugin_info['plugin_name']); ?>" readonly>
                    </div>
                  </div>
                  <div class="col-sm-6">
                    <label for="display_name" class="col-sm-12 col-form-label form-label">Display name</label>
                    <div class="input-group">
                        <input type="text" class="form-control" name="display_name" id="display_name" value="<?php echo htmlspecialchars($settings['display_name'] ?? $plugin_info['plugin_name']); ?>">
                    </div>
                  </div>
                </div>

                <div class="row mb-4">
                  <div class="col-sm-6">
                    <label for="min_amount" class="col-sm-12 col-form-label form-label">Min amount</label>
                    <div class="input-group">
                        <span class="input-group-text">BDT</span>
                        <input type="text" class="form-control" name="min_amount" id="min_amount" value="<?php echo htmlspecialchars($settings['min_amount'] ?? '0'); ?>">
                    </div>
                  </div>
                  <div class="col-sm-6">
                    <label for="max_amount" class="col-sm-12 col-form-label form-label">Max amount</label>
                    <div class="input-group">
                        <span class="input-group-text">BDT</span>
                        <input type="text" class="form-control" name="max_amount" id="max_amount" value="<?php echo htmlspecialchars($settings['max_amount'] ?? '0'); ?>">
                    </div>
                  </div>
                </div>

                <div class="row mb-4">
                  <div class="col-sm-6">
                    <label for="fixed_charge" class="col-sm-12 col-form-label form-label">Fixed charge</label>
                    <div class="input-group">
                        <span class="input-group-text">BDT</span>
                        <input type="text" class="form-control" name="fixed_charge" id="fixed_charge" value="<?php echo htmlspecialchars($settings['fixed_charge'] ?? '0'); ?>">
                    </div>
                  </div>
                  <div class="col-sm-6">
                    <label for="percent_charge" class="col-sm-12 col-form-label form-label">Percent charge</label>
                    <div class="input-group">
                        <span class="input-group-text">%</span>
                        <input type="text" class="form-control" name="percent_charge" id="percent_charge" value="<?php echo htmlspecialchars($settings['percent_charge'] ?? '0'); ?>">
                    </div>
                  </div>
                </div>

                <div class="row mb-4">
                  <div class="col-sm-6">
                    <label for="status" class="col-sm-12 col-form-label form-label">Status</label>
                    <div class="input-group">
                      <select class="form-control" name="status" id="status">
                        <?php $status_gateway = strtolower((string) ($settings['status'] ?? '')); ?>
                        <option value="disable" <?php echo $status_gateway === 'disable' ? 'selected' : ''; ?>>Disable</option>
                        <option value="enable" <?php echo $status_gateway === 'enable' ? 'selected' : ''; ?>>Enable</option>
                      </select>
                    </div>
                  </div>
                  <div class="col-sm-6">
                    <label for="category" class="col-sm-12 col-form-label form-label">Category</label>
                    <div class="input-group">
                      <input type="text" class="form-control" name="category" id="category" value="Mobile Banking" readonly>
                    </div>
                  </div>
                </div>

                <div class="row mb-4">
                  <div class="col-sm-6">
                    <label for="currency" class="col-sm-12 col-form-label form-label">Currency</label>
                    <div class="input-group">
                      <input type="text" class="form-control" name="currency" id="currency" value="BDT" readonly>
                    </div>
                    <div class="text-secondary mt-2">Bangla QR payloads are BDT (EMV code 050).</div>
                  </div>
                  <div class="col-sm-6">
                    <label class="col-sm-12 col-form-label form-label">Gateway icon</label>
                    <div><img src="<?php echo htmlspecialchars($logo_url); ?>" alt="Gateway icon" style="height:44px;border-radius:8px"></div>
                  </div>
                </div>
            </div>
          </div>

          <div class="card">
            <div class="card-header">
              <h2 class="card-title h4">Merchant QR</h2>
            </div>
            <div class="card-body">

                <?php if (($merchant_state['ok'] ?? false) === true): ?>
                  <div class="alert alert-success d-flex flex-wrap gap-2 align-items-center mb-4">
                    <strong>Merchant QR saved.</strong>
                    <span>Merchant: <?php echo htmlspecialchars((string) ($merchant_fields['merchant_name'] ?? '-')); ?></span>
                    <span>GUI: <?php echo htmlspecialchars($merchant_gui !== '' ? $merchant_gui : '-'); ?></span>
                    <span>Currency: <?php echo htmlspecialchars(bangla_qr_dynamic_currency_label($merchant_currency)); ?></span>
                    <span>Checksum: <?php echo htmlspecialchars((string) ($merchant_fields['crc'] ?? '-')); ?></span>
                  </div>
                <?php elseif ($merchant_payload !== ''): ?>
                  <div class="alert alert-danger mb-4">The stored merchant QR payload is no longer valid: <?php echo htmlspecialchars((string) ($merchant_state['error'] ?? 'unknown error')); ?>. Upload it again.</div>
                <?php else: ?>
                  <div class="alert alert-warning mb-4">No merchant QR yet. Upload your existing Bangla QR image or paste its payload to start accepting payments.</div>
                <?php endif; ?>

                <?php if ($currency_mismatch): ?>
                  <div class="alert alert-warning mb-4">The saved QR states <strong><?php echo htmlspecialchars(bangla_qr_dynamic_currency_label($merchant_currency)); ?></strong> but the gateway currency is <strong><?php echo htmlspecialchars($gateway_currency); ?></strong>. Amounts in the generated QR will not match what the gateway charges.</div>
                <?php endif; ?>

                <div class="row mb-4">
                  <div class="col-sm-6">
                    <label for="qr_code" class="col-sm-12 col-form-label form-label">Upload merchant QR image</label>
                    <input type="file" class="form-control" name="qr_code" id="qr_code" accept="image/png,image/jpeg,image/webp">
                    <div class="text-secondary mt-2">PNG, JPG or WEBP under 5MB. The code is decoded on the server; the image itself is only kept as a reference.</div>
                    <?php if (!empty($settings['qr_code'])): ?>
                      <img src="<?php echo htmlspecialchars($settings['qr_code']); ?>" alt="Uploaded merchant QR" style="max-width:140px;margin-top:10px;border-radius:8px">
                    <?php endif; ?>
                  </div>
                  <div class="col-sm-6">
                    <label for="merchant_payload" class="col-sm-12 col-form-label form-label">Or paste the QR payload</label>
                    <textarea class="form-control" name="merchant_payload" id="merchant_payload" rows="6" spellcheck="false" placeholder="0002010102112651...6304XXXX"><?php echo htmlspecialchars($merchant_payload); ?></textarea>
                    <div class="text-secondary mt-2">Useful when the QR cannot be decoded from an image. An uploaded image takes priority over this box.</div>
                  </div>
                </div>

                <div class="row mb-4">
                  <div class="col-sm-6">
                    <label for="pending_payment" class="col-sm-12 col-form-label form-label">Pending payment</label>
                    <div class="input-group">
                      <select class="form-control" name="pending_payment" id="pending_payment">
                        <?php $pending = strtolower((string) ($settings['pending_payment'] ?? 'enable')); ?>
                        <option value="disable" <?php echo $pending === 'disable' ? 'selected' : ''; ?>>Disable</option>
                        <option value="enable" <?php echo $pending === 'enable' ? 'selected' : ''; ?>>Enable</option>
                      </select>
                    </div>
                    <div class="text-secondary mt-2">When enabled, a payment the customer reports is saved as pending for review.</div>
                  </div>
                </div>
            </div>
          </div>

          <div class="card">
            <div class="card-header">
              <h2 class="card-title h4">Dynamic QR options</h2>
            </div>
            <div class="card-body">
                <div class="row mb-4">
                  <div class="col-sm-4">
                    <label for="qr_scale" class="col-sm-12 col-form-label form-label">QR size</label>
                    <div class="input-group">
                        <input type="number" class="form-control" name="qr_scale" id="qr_scale" min="4" max="20" value="<?php echo htmlspecialchars((string) ($settings['qr_scale'] ?? '8')); ?>">
                        <span class="input-group-text">px</span>
                    </div>
                    <div class="text-secondary mt-2">Pixels per module.</div>
                  </div>
                  <div class="col-sm-4">
                    <label for="error_correction" class="col-sm-12 col-form-label form-label">Error correction</label>
                    <div class="input-group">
                      <select class="form-control" name="error_correction" id="error_correction">
                        <?php $ec = strtoupper((string) ($settings['error_correction'] ?? 'M')); ?>
                        <?php foreach (array('L', 'M', 'Q', 'H') as $level): ?>
                          <option value="<?php echo $level; ?>" <?php echo $ec === $level ? 'selected' : ''; ?>><?php echo $level; ?></option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                    <div class="text-secondary mt-2">M balances size and readability.</div>
                  </div>
                  <div class="col-sm-4">
                    <label for="trim_zero_cents" class="col-sm-12 col-form-label form-label">Whole taka only</label>
                    <div class="input-group">
                      <select class="form-control" name="trim_zero_cents" id="trim_zero_cents">
                        <?php $trim = strtolower((string) ($settings['trim_zero_cents'] ?? 'disable')); ?>
                        <option value="disable" <?php echo $trim === 'disable' ? 'selected' : ''; ?>>100.00</option>
                        <option value="enable" <?php echo $trim === 'enable' ? 'selected' : ''; ?>>100</option>
                      </select>
                    </div>
                    <div class="text-secondary mt-2">How whole amounts appear in the QR.</div>
                  </div>
                </div>

                <div class="row mb-4">
                  <div class="col-sm-4">
                    <label for="qr_lifetime_minutes" class="col-sm-12 col-form-label form-label">QR lifetime</label>
                    <div class="input-group">
                        <input type="number" class="form-control" name="qr_lifetime_minutes" id="qr_lifetime_minutes" min="1" max="30" value="<?php echo htmlspecialchars((string) ($settings['qr_lifetime_minutes'] ?? '3')); ?>">
                        <span class="input-group-text">min</span>
                    </div>
                    <div class="text-secondary mt-2">How long the QR stays valid after the page loads.</div>
                  </div>
                  <div class="col-sm-4">
                    <label for="match_grace_minutes" class="col-sm-12 col-form-label form-label">Match grace</label>
                    <div class="input-group">
                        <input type="number" class="form-control" name="match_grace_minutes" id="match_grace_minutes" min="1" max="60" value="<?php echo htmlspecialchars((string) ($settings['match_grace_minutes'] ?? '2')); ?>">
                        <span class="input-group-text">min</span>
                    </div>
                    <div class="text-secondary mt-2">Extra time a late SMS can still confirm the payment.</div>
                  </div>
                  <div class="col-sm-4">
                    <label for="auto_verify" class="col-sm-12 col-form-label form-label">Auto verify from SMS</label>
                    <div class="input-group">
                      <select class="form-control" name="auto_verify" id="auto_verify">
                        <?php $auto = strtolower((string) ($settings['auto_verify'] ?? 'enable')); ?>
                        <option value="disable" <?php echo $auto === 'disable' ? 'selected' : ''; ?>>Disable</option>
                        <option value="enable" <?php echo $auto === 'enable' ? 'selected' : ''; ?>>Enable</option>
                      </select>
                    </div>
                    <div class="text-secondary mt-2">Completes a payment when an incoming SMS matches the exact amount.</div>
                  </div>
                </div>

                <div class="row mb-4">
                  <div class="col-sm-4">
                    <label for="reuse_cooldown_minutes" class="col-sm-12 col-form-label form-label">Amount reuse cooldown</label>
                    <div class="input-group">
                        <input type="number" class="form-control" name="reuse_cooldown_minutes" id="reuse_cooldown_minutes" min="2" max="120" value="<?php echo htmlspecialchars((string) ($settings['reuse_cooldown_minutes'] ?? '10')); ?>">
                        <span class="input-group-text">min</span>
                    </div>
                    <div class="text-secondary mt-2">How long a released amount (for example 1020.11) is kept from other customers, in case that customer's SMS arrives late.</div>
                  </div>
                </div>
            </div>
          </div>

          <div id="bqdResponse"></div>

          <button type="submit" class="btn btn-primary btn-primary-add" style="max-width: 150px;">Save Settings</button>
        </div>
      </div>
    </div>
</form>

<script>
    $(document).ready(function() {
        $('#bqdSettingsForm').on('submit', function(e) {
            e.preventDefault();

            var button = document.querySelector('.btn-primary-add');
            var original = button.innerHTML;
            button.innerHTML = '<div class="spinner-border text-light spinner-border-sm" role="status"><span class="visually-hidden">Loading...</span></div>';

            $.ajax({
                url: $(this).attr('action'),
                type: 'POST',
                data: new FormData(this),
                processData: false,
                contentType: false,
                dataType: 'json',
                success: function(response) {
                    button.innerHTML = original;
                    if (response.status) {
                        $('#bqdResponse').removeClass('alert-danger').addClass('alert alert-success mb-3').html(response.message);
                    } else {
                        $('#bqdResponse').removeClass('alert-success').addClass('alert alert-danger mb-3').html(response.message);
                    }
                },
                error: function(xhr, textStatus) {
                    button.innerHTML = original;
                    // Show what actually came back. A bare "An error occurred"
                    // hides whether this is a fatal, a 403 or a timeout.
                    var detail = 'An error occurred. Please try again.';
                    if (xhr && xhr.status) {
                        detail += ' (HTTP ' + xhr.status + ')';
                    } else if (textStatus) {
                        detail += ' (' + textStatus + ')';
                    }
                    var body = (xhr && xhr.responseText) ? String(xhr.responseText).replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim() : '';
                    if (body) {
                        detail += '<br><small>' + $('<div>').text(body.slice(0, 300)).html() + '</small>';
                    } else if (xhr && xhr.status === 500) {
                        detail += '<br><small>The server stopped while saving. Check the PHP error log for a fatal error.</small>';
                    }
                    $('#bqdResponse').removeClass('alert-success').addClass('alert alert-danger mb-3').html(detail);
                }
            });
        });
    });
</script>
