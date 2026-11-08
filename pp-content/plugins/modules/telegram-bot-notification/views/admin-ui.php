<?php
    if (!defined('pp_allowed_access')) {
        die('Direct access not allowed');
    }

    $plugin_slug = 'telegram-bot-notification';
    $settings = pp_get_plugin_setting($plugin_slug);
    $status = strtolower((string) ($settings['status'] ?? 'enable'));
    $notifyCreated = strtolower((string) ($settings['notify_created'] ?? 'disable'));
    $notifyPending = strtolower((string) ($settings['notify_pending'] ?? 'enable'));
    $notifyCompleted = strtolower((string) ($settings['notify_completed'] ?? 'enable'));
    $notifyAdminActivity = strtolower((string) ($settings['notify_admin_activity'] ?? 'enable'));
?>

<form id="telegramBotSettingsForm" method="post" action="">
    <!-- Page Header -->
    <div class="page-header">
      <div class="row align-items-end">
        <div class="col-sm mb-2 mb-sm-0">
          <h1 class="page-header-title">Telegram Bot Notification Settings</h1>
        </div>
      </div>
    </div>

    <div class="row justify-content-center">
      <div class="col-lg-8">
        <div class="d-grid gap-3 gap-lg-5">
          <!-- Card -->
          <div class="card">
            <div class="card-header">
              <h2 class="card-title h4">Bot Connection</h2>
            </div>

            <!-- Body -->
            <div class="card-body">
                <input type="hidden" name="telegram-bot-notification-action" value="save_settings">
                <input type="hidden" name="plugin_slug" value="<?php echo $plugin_slug?>">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCsrfToken()); ?>">

                <div class="row mb-4">
                  <div class="col-sm-12">
                    <label for="telegram_bot_token" class="col-sm-12 col-form-label form-label">Telegram Bot Token</label>
                    <div class="input-group">
                      <input type="text" class="form-control" name="telegram_bot_token" id="telegram_bot_token" placeholder="Enter Telegram Bot Token" aria-label="Enter Telegram Bot Token" value="<?= htmlspecialchars($settings['telegram_bot_token'] ?? '') ?>" autocomplete="off">
                    </div>
                    <div class="text-secondary mt-2">Create a bot with @BotFather and copy the bot token.</div>
                  </div>

                  <div class="col-sm-12">
                    <label for="chat_id" class="col-sm-12 col-form-label form-label">Chat ID</label>
                    <div class="input-group">
                      <input type="text" class="form-control" name="chat_id" id="chat_id" placeholder="e.g. 123456789 or @mychannel" aria-label="Enter Telegram Chat ID" value="<?= htmlspecialchars($settings['chat_id'] ?? '') ?>" autocomplete="off">
                    </div>
                    <div class="text-secondary mt-2">Your user, group or channel ID. Add the bot as an administrator when using a group or channel.</div>
                  </div>

                  <div class="col-sm-12">
                    <label for="status" class="col-sm-12 col-form-label form-label">Status</label>
                    <div class="input-group">
                      <select class="form-control" name="status" id="status">
                        <option value="enable" <?php echo ($status === 'disable') ? '' : 'selected' ?>>Enable</option>
                        <option value="disable" <?php echo ($status === 'disable') ? 'selected' : '' ?>>Disable</option>
                      </select>
                    </div>
                    <div class="text-secondary mt-2">Disable to stop sending all alerts: transactions, invoices and security.</div>
                  </div>
                </div>

                <div id="ajaxResponse" class="mb-3"></div>

                <div class="d-flex gap-2">
                  <button type="submit" class="btn btn-primary btn-primary-add">Save Settings</button>
                  <button type="button" class="btn btn-outline-primary" id="telegramTestNotificationBtn">Send Test Notification</button>
                </div>
            </div>
            <!-- End Body -->
          </div>
          <!-- End Card -->

          <!-- Card -->
          <div class="card">
            <div class="card-header">
              <h2 class="card-title h4">Notifications</h2>
            </div>

            <!-- Body -->
            <div class="card-body">
                <div class="row mb-4">
                  <div class="col-sm-12">
                    <label for="notify_created" class="col-sm-12 col-form-label form-label">New Transaction</label>
                    <div class="input-group">
                      <select class="form-control" name="notify_created" id="notify_created">
                        <option value="enable" <?php echo ($notifyCreated === 'disable') ? '' : 'selected' ?>>Enable</option>
                        <option value="disable" <?php echo ($notifyCreated === 'disable') ? 'selected' : '' ?>>Disable</option>
                      </select>
                    </div>
                    <div class="text-secondary mt-2">Alert when a customer starts a checkout. Most checkouts are abandoned before payment, so this is off by default. Turn it on if you want to know about every checkout attempt.</div>
                  </div>

                  <div class="col-sm-12">
                    <label for="notify_pending" class="col-sm-12 col-form-label form-label">Payment Submitted</label>
                    <div class="input-group">
                      <select class="form-control" name="notify_pending" id="notify_pending">
                        <option value="enable" <?php echo ($notifyPending === 'disable') ? '' : 'selected' ?>>Enable</option>
                        <option value="disable" <?php echo ($notifyPending === 'disable') ? 'selected' : '' ?>>Disable</option>
                      </select>
                    </div>
                    <div class="text-secondary mt-2">Alert when the customer submits the receipt or payment reference, with the receipt attached.</div>
                  </div>

                  <div class="col-sm-12">
                    <label for="notify_completed" class="col-sm-12 col-form-label form-label">Payment Completed</label>
                    <div class="input-group">
                      <select class="form-control" name="notify_completed" id="notify_completed">
                        <option value="enable" <?php echo ($notifyCompleted === 'disable') ? '' : 'selected' ?>>Enable</option>
                        <option value="disable" <?php echo ($notifyCompleted === 'disable') ? 'selected' : '' ?>>Disable</option>
                      </select>
                    </div>
                    <div class="text-secondary mt-2">Alert when a transaction is completed, failed or refunded.</div>
                  </div>

                  <div class="col-sm-12">
                    <label for="notify_admin_activity" class="col-sm-12 col-form-label form-label">Security &amp; Admin Activity</label>
                    <div class="input-group">
                      <select class="form-control" name="notify_admin_activity" id="notify_admin_activity">
                        <option value="enable" <?php echo ($notifyAdminActivity === 'disable') ? '' : 'selected' ?>>Enable</option>
                        <option value="disable" <?php echo ($notifyAdminActivity === 'disable') ? 'selected' : '' ?>>Disable</option>
                      </select>
                    </div>
                    <div class="text-secondary mt-2">Alert on admin logins, blocked login attempts, gateway changes (such as a new phone number or wallet address), password, email, API key and 2FA changes, and plugin installs. Secret values are never sent, only that they changed. Recommended: keep this on.</div>
                  </div>

                  <div class="col-sm-12">
                    <label for="security_chat_id" class="col-sm-12 col-form-label form-label">Security Alerts Chat ID (optional)</label>
                    <div class="input-group">
                      <input type="text" class="form-control" name="security_chat_id" id="security_chat_id" placeholder="e.g. -1001234567890 or @mysecuritychannel" aria-label="Enter Security Alerts Chat ID" value="<?= htmlspecialchars($settings['security_chat_id'] ?? '') ?>" autocomplete="off">
                    </div>
                    <div class="text-secondary mt-2">Chat for security and admin activity alerts, separate from transactions. Uses the same bot, so add the bot as an administrator when using a group or channel. Leave empty to send them to the main Chat ID.</div>
                  </div>
                </div>
            </div>
            <!-- End Body -->
          </div>
          <!-- End Card -->
        <div id="stickyBlockEndPoint"></div>
      </div>
    </div>
</form>


<script>
    $(document).ready(function() {
        const form = document.getElementById('telegramBotSettingsForm');
        const responseBox = document.getElementById('ajaxResponse');
        const testButton = document.getElementById('telegramTestNotificationBtn');

        function showResponse(message, isSuccess) {
            responseBox.className = isSuccess ? 'alert alert-success mb-3' : 'alert alert-danger mb-3';
            responseBox.textContent = message || (isSuccess ? 'Done.' : 'Something went wrong.');
        }

        function postSettings(action) {
            const formData = new FormData(form);
            formData.set('telegram-bot-notification-action', action);

            return fetch(window.location.href, {
                method: 'POST',
                headers: { 'X-CSRF-Token': window.PP_CSRF_TOKEN || '' },
                body: formData
            })
            .then(res => res.json())
            .then(response => {
                showResponse(response.message, response.status === true);
            })
            .catch(() => {
                showResponse('An error occurred. Please try again.', false);
            });
        }

        form.addEventListener('submit', function(e) {
            e.preventDefault();

            const button = form.querySelector('.btn-primary-add');
            button.innerHTML = '<div class="spinner-border text-light spinner-border-sm" role="status"> <span class="visually-hidden">Loading...</span> </div>';

            postSettings('save_settings').finally(() => {
                button.innerHTML = 'Save Settings';
            });
        });

        testButton.addEventListener('click', function() {
            testButton.innerHTML = '<div class="spinner-border spinner-border-sm" role="status"> <span class="visually-hidden">Loading...</span> </div>';

            postSettings('test_notification').finally(() => {
                testButton.innerHTML = 'Send Test Notification';
            });
        });
    });
</script>
