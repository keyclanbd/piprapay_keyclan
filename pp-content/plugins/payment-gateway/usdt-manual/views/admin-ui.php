<?php
    $plugin_slug = 'usdt-manual';
    $plugin_info = pp_get_plugin_info($plugin_slug);
    $settings = pp_get_plugin_setting($plugin_slug);
    $networks = usdt_manual_networks();
    $wallets = is_array($settings['wallets'] ?? null) ? $settings['wallets'] : [];
?>

<form id="usdtManualSettingsForm" method="post" action="" enctype="multipart/form-data">
    <!-- Page Header -->
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
          <!-- Card -->
          <div class="card">
            <div class="card-header">
              <h2 class="card-title h4">Gateway Information</h2>
            </div>

            <!-- Body -->
            <div class="card-body">
                <input type="hidden" name="action" value="plugin_update-submit">
                <input type="hidden" name="plugin_slug" value="<?php echo $plugin_slug?>">

                <div class="row mb-4">
                  <div class="col-sm-6">
                    <label for="name" class="col-sm-12 col-form-label form-label">Name</label>
                    <div class="input-group">
                        <input type="text" class="form-control" name="name" id="name" value="<?= htmlspecialchars($settings['name'] ?? $plugin_info['plugin_name']) ?>" readonly>
                    </div>
                    <div class="text-secondary mt-2"> </div>
                  </div>
                  <div class="col-sm-6">
                    <label for="display_name" class="col-sm-12 col-form-label form-label">Display name</label>
                    <div class="input-group">
                        <input type="text" class="form-control" name="display_name" id="display_name" value="<?= htmlspecialchars($settings['display_name'] ?? 'USDT') ?>">
                    </div>
                    <div class="text-secondary mt-2"> </div>
                  </div>
                </div>

                <div class="row mb-4">
                  <div class="col-sm-6">
                    <label for="min_amount" class="col-sm-12 col-form-label form-label">Min amount</label>
                    <div class="input-group">
                        <span class="input-group-text">USD</span>
                        <input type="text" class="form-control" name="min_amount" id="min_amount" value="<?= htmlspecialchars($settings['min_amount'] ?? '0') ?>">
                    </div>
                    <div class="text-secondary mt-2"> </div>
                  </div>
                  <div class="col-sm-6">
                    <label for="max_amount" class="col-sm-12 col-form-label form-label">Max amount</label>
                    <div class="input-group">
                        <span class="input-group-text">USD</span>
                        <input type="text" class="form-control" name="max_amount" id="max_amount" value="<?= htmlspecialchars($settings['max_amount'] ?? '0') ?>">
                    </div>
                    <div class="text-secondary mt-2"> </div>
                  </div>
                </div>

                <div class="row mb-4">
                  <div class="col-sm-6">
                    <label for="fixed_charge" class="col-sm-12 col-form-label form-label">Fixed charge</label>
                    <div class="input-group">
                        <span class="input-group-text">USD</span>
                        <input type="text" class="form-control" name="fixed_charge" id="fixed_charge" value="<?= htmlspecialchars($settings['fixed_charge'] ?? '0') ?>">
                    </div>
                    <div class="text-secondary mt-2"> </div>
                  </div>

                  <div class="col-sm-6">
                    <label for="percent_charge" class="col-sm-12 col-form-label form-label">Percent charge</label>
                    <div class="input-group">
                        <span class="input-group-text">%</span>
                        <input type="text" class="form-control" name="percent_charge" id="percent_charge" value="<?= htmlspecialchars($settings['percent_charge'] ?? '0') ?>">
                    </div>
                    <div class="text-secondary mt-2"> </div>
                  </div>

                  <div class="col-sm-6">
                    <label for="status" class="col-sm-12 col-form-label form-label">Status</label>
                    <div class="input-group">
                      <select class="form-control" name="status" id="status">
                        <?php $status_gateway = isset($settings['status']) ? strtolower($settings['status']) : ''; ?>
                        <option value="disable" <?php echo ($status_gateway === 'disable') ? 'selected' : ''; ?>>Disable</option>
                        <option value="enable" <?php echo ($status_gateway === 'enable') ? 'selected' : ''; ?>>Enable</option>
                      </select>
                    </div>
                    <div class="text-secondary mt-2"> </div>
                  </div>

                  <div class="col-sm-6">
                    <label for="category" class="col-sm-12 col-form-label form-label">Category</label>
                    <div class="input-group">
                      <input type="text" class="form-control" name="category" id="category" value="<?= htmlspecialchars($settings['category'] ?? 'Crypto') ?>" readonly>
                    </div>
                    <div class="text-secondary mt-2"> </div>
                  </div>

                  <div class="col-sm-6">
                    <label for="currency" class="col-sm-12 col-form-label form-label">Currency</label>
                    <div class="input-group">
                      <input type="text" class="form-control" name="currency" id="currency" value="USD" readonly>
                    </div>
                    <div class="text-secondary mt-2"> </div>
                  </div>
                </div>
            </div>
            <!-- End Body -->
          </div>

          <div class="card">
            <div class="card-header">
              <h2 class="card-title h4">USDT Wallets</h2>
              <p class="card-text text-secondary mt-1 mb-0">Customers only see wallets that are switched on.</p>
            </div>

            <div class="card-body">
              <?php $last_key = array_key_last($networks); ?>
              <?php foreach ($networks as $key => $network): ?>
                <?php
                    $wallet = is_array($wallets[$key] ?? null) ? $wallets[$key] : [];
                    $address = (string) ($wallet['address'] ?? '');
                    $qr_code = (string) ($wallet['qr_code'] ?? '');
                ?>
                <div class="<?php echo $key === $last_key ? '' : 'border-bottom pb-4 mb-4'; ?>" data-usdt-wallet="<?php echo $key; ?>">
                  <div class="d-flex justify-content-between align-items-center mb-3">
                    <h3 class="h5 mb-0">
                      <span style="display:inline-block;width:10px;height:10px;border-radius:50%;margin-right:6px;background:<?php echo $network['color']; ?>"></span>
                      <?php echo htmlspecialchars($network['name']); ?>
                    </h3>
                    <div class="form-check form-switch mb-0">
                      <input class="form-check-input" type="checkbox" role="switch" name="wallets[<?php echo $key; ?>][enabled]" id="wallet_<?php echo $key; ?>_enabled" value="enable" aria-label="Show <?php echo htmlspecialchars($network['name']); ?> at checkout" <?php echo (($wallet['enabled'] ?? '') === 'enable') ? 'checked' : ''; ?>>
                    </div>
                  </div>

                  <div class="mb-3">
                    <label for="wallet_<?php echo $key; ?>_address" class="form-label">Wallet address</label>
                    <input type="text" class="form-control" style="font-family:monospace" name="wallets[<?php echo $key; ?>][address]" id="wallet_<?php echo $key; ?>_address" value="<?php echo htmlspecialchars($address, ENT_QUOTES); ?>" data-saved="<?php echo htmlspecialchars($address, ENT_QUOTES); ?>" data-has-qr="<?php echo $qr_code !== '' ? '1' : '0'; ?>" data-pattern="<?php echo htmlspecialchars($network['address_pattern'], ENT_QUOTES); ?>" data-rule="<?php echo htmlspecialchars($network['address_rule'], ENT_QUOTES); ?>" autocomplete="off" spellcheck="false">
                    <div class="small text-danger mt-1" data-usdt-address-message></div>
                  </div>

                  <div>
                    <label for="wallet_qr_<?php echo $key; ?>" class="form-label">QR code</label>
                    <div class="d-flex align-items-center gap-3">
                      <?php if ($qr_code !== ''): ?>
                        <img src="<?php echo htmlspecialchars($qr_code, ENT_QUOTES); ?>" alt="<?php echo htmlspecialchars($network['name']); ?> QR code" style="width:76px;height:76px;object-fit:contain;border:1px solid #e7eaf3;border-radius:8px;flex:none">
                      <?php endif; ?>
                      <div class="flex-grow-1" style="min-width:0">
                        <input type="file" class="form-control" name="wallet_qr_<?php echo $key; ?>" id="wallet_qr_<?php echo $key; ?>" accept="image/png,image/jpeg,image/webp">
                        <div class="small text-secondary mt-1">PNG, JPG or WEBP, up to 5MB. Scan it with your wallet first to confirm it shows this exact address. Without a QR, customers just see the address.</div>
                      </div>
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>

          <div id="ajaxResponse"></div>

          <button type="submit" class="btn btn-primary btn-primary-add" style=" max-width: 150px; ">Save Settings</button>
          <!-- End Card -->
        <div id="stickyBlockEndPoint"></div>
      </div>
    </div>
</form>

<script>
    $(document).ready(function() {
        // USDT is charged 1:1 with USD. plugin-loader turns these fields into editable ones
        // right after this script runs, so lock them on the next tick.
        setTimeout(function() {
            var currency = document.querySelector('#usdtManualSettingsForm [name="currency"]');
            var direct = document.querySelector('#usdtManualSettingsForm [name="direct_currencies"]');
            if (currency && currency.tagName === 'SELECT') {
                if (![].some.call(currency.options, function(o) { return o.value === 'USD'; })) {
                    currency.add(new Option('USD', 'USD'));
                }
                currency.value = 'USD';
                currency.disabled = true;
            }
            if (direct) {
                direct.value = 'USD';
                direct.readOnly = true;
            }
        }, 0);

        $('#usdtManualSettingsForm').on('input', '[data-pattern]', function() {
            var value = this.value.trim();
            var message = '';
            if (value !== '' && !new RegExp(this.dataset.pattern).test(value)) {
                message = this.dataset.rule;
            } else if (this.dataset.hasQr === '1' && value !== this.dataset.saved) {
                message = 'Saving a new address removes the current QR code. Upload the matching one.';
            }
            $(this).closest('[data-usdt-wallet]').find('[data-usdt-address-message]').text(message);
        });

        $('#usdtManualSettingsForm').on('submit', function(e) {
            e.preventDefault();

            document.querySelector(".btn-primary-add").innerHTML = '<div class="spinner-border text-light spinner-border-sm" role="status"> <span class="visually-hidden">Loading...</span> </div>';

            $.ajax({
                url: $(this).attr('action'),
                type: 'POST',
                data: new FormData(this),
                processData: false,
                contentType: false,
                dataType: 'json',
                success: function(response) {
                    document.querySelector(".btn-primary-add").innerHTML = 'Save Settings';

                    if(response.status) {
                        $('#ajaxResponse').removeClass('alert-danger').addClass('alert alert-success mb-3').text(response.message);
                    } else {
                        $('#ajaxResponse').removeClass('alert-success').addClass('alert alert-danger mb-3').text(response.message);
                    }
                },
                error: function() {
                    document.querySelector(".btn-primary-add").innerHTML = 'Save Settings';
                    $('#ajaxResponse').removeClass('alert-success').addClass('alert alert-danger').text('An error occurred. Please try again.');
                }
            });
        });
    });
</script>
