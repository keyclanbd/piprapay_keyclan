<?php
    $plugin_slug = 'cryptomus';
    $plugin_info = pp_get_plugin_info($plugin_slug);
    $settings = pp_get_plugin_setting($plugin_slug);
?>

<form id="smtpSettingsForm" method="post" action="">
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
                    <label for="host" class="col-sm-12 col-form-label form-label">Name</label>
                    <div class="input-group">
                        <input type="text" class="form-control" name="name" id="name" value="<?= htmlspecialchars($settings['name'] ?? $plugin_info['plugin_name']) ?>" readonly>
                    </div>
                    <div class="text-secondary mt-2"> </div>
                  </div>
                  <div class="col-sm-6">
                    <label for="display_name" class="col-sm-12 col-form-label form-label">Display name</label>
                    <div class="input-group">
                        <input type="text" class="form-control" name="display_name" id="display_name" value="<?= htmlspecialchars($settings['display_name'] ?? $plugin_info['plugin_name']) ?>">
                    </div>
                    <div class="text-secondary mt-2"> </div>
                  </div>
                </div>

                <div class="row mb-4">
                  <div class="col-sm-6">
                    <label for="min_amount" class="col-sm-12 col-form-label form-label">Min amount</label>
                    <div class="input-group">
                        <span class="input-group-text" id="basic-addon1">USD</span>
                        <input type="text" class="form-control" name="min_amount" id="min_amount" oninput="this.value = this.value.replace(/[^0-9.]/g, '').replace(/(\..*?)\..*/g, '$1');" value="<?= htmlspecialchars($settings['min_amount'] ?? '0') ?>">
                    </div>
                  </div>
                  <div class="col-sm-6">
                    <label for="max_amount" class="col-sm-12 col-form-label form-label">Max amount</label>
                    <div class="input-group">
                        <span class="input-group-text" id="basic-addon1">USD</span>
                        <input type="text" class="form-control" name="max_amount" id="max_amount" oninput="this.value = this.value.replace(/[^0-9.]/g, '').replace(/(\..*?)\..*/g, '$1');" value="<?= htmlspecialchars($settings['max_amount'] ?? '0') ?>">
                    </div>
                  </div>
                </div>
                
                <div class="row mb-4">
                  <div class="col-sm-6">
                    <label for="fixed_charge" class="col-sm-12 col-form-label form-label">Fixed charge</label>
                    <div class="input-group">
                        <span class="input-group-text" id="basic-addon1">USD</span>
                        <input type="text" class="form-control" name="fixed_charge" id="fixed_charge" oninput="this.value = this.value.replace(/[^0-9.]/g, '').replace(/(\..*?)\..*/g, '$1');" value="<?= htmlspecialchars($settings['fixed_charge'] ?? '0') ?>">
                    </div>
                    <div class="text-secondary mt-2"> </div>
                  </div>
                    
                  <div class="col-sm-6">
                    <label for="percent_charge" class="col-sm-12 col-form-label form-label">Percent charge</label>
                    <div class="input-group">
                        <span class="input-group-text" id="basic-addon1">%</span>
                        <input type="text" class="form-control" name="percent_charge" id="percent_charge" oninput="this.value = this.value.replace(/[^0-9.]/g, '').replace(/(\..*?)\..*/g, '$1');" value="<?= htmlspecialchars($settings['percent_charge'] ?? '0') ?>">
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
                      <select class="form-control" name="category" id="category">
                        <?php 
                            $current_category = $settings['category'] ?? 'International';
                            $categories = ['Mobile Banking', 'IBanking', 'Card', 'International'];
                            foreach ($categories as $cat) {
                                $selected = ($cat === $current_category) ? 'selected' : '';
                                echo "<option value=\"$cat\" $selected>$cat</option>";
                            }
                        ?>
                      </select>
                    </div>
                  </div>
                  
                  <div class="col-sm-6">
                    <label for="currency" class="col-sm-12 col-form-label form-label">Currency</label>
                    <div class="input-group">
                      <input type="text" class="form-control" name="currency" id="currency" value="USD" readonly>
                    </div>
                  </div>
                </div>
            </div>
            <!-- End Body -->
          </div>
          
          
          <div class="card">
            <div class="card-header">
              <h2 class="card-title h4">Cryptomus Configuration</h2>
            </div>

            <!-- Body -->
            <div class="card-body">
                <div class="row mb-4">
                  <div class="col-sm-12">
                    <label for="merchant_uuid" class="col-sm-12 col-form-label form-label">Merchant ID <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <input type="text" class="form-control" name="merchant_uuid" id="merchant_uuid" value="<?= htmlspecialchars($settings['merchant_uuid'] ?? '') ?>" placeholder="Your Merchant ID">
                    </div>
                  </div>

                  <div class="col-sm-12">
                    <label for="payment_key" class="col-sm-12 col-form-label form-label">Payment API Key <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <input type="text" class="form-control" name="payment_key" id="payment_key" value="<?= htmlspecialchars($settings['payment_key'] ?? '') ?>" placeholder="Your Payment API Key">
                    </div>
                  </div>
                  
                  <div class="col-sm-6">
                    <label for="lifetime" class="col-sm-12 col-form-label form-label">Invoice Lifetime</label>
                    <div class="input-group">
                      <select class="form-control" name="lifetime" id="lifetime">
                        <?php 
                            $lifetime_value = isset($settings['lifetime']) ? intval($settings['lifetime']) : 3600;
                            $lifetimes = [
                                '1800' => '30 Minutes',
                                '3600' => '1 Hour',
                                '7200' => '2 Hours',
                                '10800' => '3 Hours',
                                '14400' => '4 Hours',
                                '21600' => '6 Hours',
                                '43200' => '12 Hours'
                            ];
                            foreach ($lifetimes as $seconds => $label) {
                                $selected = ($lifetime_value == $seconds) ? 'selected' : '';
                                echo "<option value=\"$seconds\" $selected>$label</option>";
                            }
                        ?>
                      </select>
                    </div>
                  </div>
                  
                  <div class="col-sm-6">
                    <label for="subtract" class="col-sm-12 col-form-label form-label">Client Commission (%)</label>
                    <div class="input-group">
                        <span class="input-group-text" id="basic-addon1">%</span>
                        <input type="number" class="form-control" name="subtract" id="subtract" value="<?= htmlspecialchars($settings['subtract'] ?? '0') ?>" min="0" max="100">
                    </div>
                  </div>
                  
                  <div class="col-sm-6">
                    <label for="is_payment_multiple" class="col-sm-12 col-form-label form-label">Allow Partial Payment</label>
                    <div class="input-group">
                      <select class="form-control" name="is_payment_multiple" id="is_payment_multiple">
                        <?php $is_payment_multiple = isset($settings['is_payment_multiple']) ? $settings['is_payment_multiple'] : 'true'; ?>
                        <option value="true" <?php echo ($is_payment_multiple === 'true') ? 'selected' : ''; ?>>Yes</option>
                        <option value="false" <?php echo ($is_payment_multiple === 'false') ? 'selected' : ''; ?>>No</option>
                      </select>
                    </div>
                  </div>
                  
                  <div class="col-sm-6">
                    <label for="accuracy_payment_percent" class="col-sm-12 col-form-label form-label">Payment Accuracy (%)</label>
                    <div class="input-group">
                        <span class="input-group-text" id="basic-addon1">%</span>
                        <input type="number" class="form-control" name="accuracy_payment_percent" id="accuracy_payment_percent" value="<?= htmlspecialchars($settings['accuracy_payment_percent'] ?? '0') ?>" min="0" max="5" step="0.1">
                    </div>
                  </div>
                </div>
            </div>
            <!-- End Body -->
          </div>

          <div id="ajaxResponse"></div>

          <button type="submit" class="btn btn-primary btn-primary-add" style=" max-width: 150px; ">Save Settings</button>
          <!-- End Card -->
        <div id="stickyBlockEndPoint"></div>
      </div>
    </div>
</form>


        
<script src="../external/assets/vendor/tom-select/dist/js/tom-select.complete.min.js"></script>
<script>
    $(document).ready(function() {
        // Form submission handling
        $('#smtpSettingsForm').on('submit', function(e) {
            e.preventDefault();
    
            document.querySelector(".btn-primary-add").innerHTML = '<div class="spinner-border text-light spinner-border-sm" role="status"> <span class="visually-hidden">Loading...</span> </div>';
    
            $.ajax({
                url: $(this).attr('action'),
                type: 'POST',
                data: new FormData(this), processData: false, contentType: false,
                dataType: 'json',
                success: function(response) {
                    document.querySelector(".btn-primary-add").innerHTML = 'Save Settings';
                    
                    if(response.status) {
                        $('#ajaxResponse').addClass('alert alert-success mb-3').html(response.message);
                    } else {
                        $('#ajaxResponse').addClass('alert alert-danger mb-3').html(response.message);
                    }
                },
                error: function() {
                    $('#ajaxResponse').addClass('alert alert-danger').html('An error occurred. Please try again.');
                }
            });
        });

    });
</script>

