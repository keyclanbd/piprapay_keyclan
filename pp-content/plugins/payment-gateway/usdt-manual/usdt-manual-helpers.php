<?php
    function usdt_manual_networks() {
        return [
            'trc20' => [
                'name' => 'TRON (TRC20)',
                'color' => '#E5403B',
                'address_pattern' => '^T[1-9A-HJ-NP-Za-km-z]{33}$',
                'address_rule' => 'TRON addresses start with T and have 34 characters.',
                'explorer' => 'Tronscan',
                'tx_url' => 'https://tronscan.org/#/transaction/',
            ],
            'bep20' => [
                'name' => 'BSC (BEP20)',
                'color' => '#D9A400',
                'address_pattern' => '^0x[a-fA-F0-9]{40}$',
                'address_rule' => 'BSC addresses start with 0x and have 42 characters.',
                'explorer' => 'BscScan',
                'tx_url' => 'https://bscscan.com/tx/',
            ],
            'erc20' => [
                'name' => 'Ethereum (ERC20)',
                'color' => '#5B6EE1',
                'address_pattern' => '^0x[a-fA-F0-9]{40}$',
                'address_rule' => 'Ethereum addresses start with 0x and have 42 characters.',
                'explorer' => 'Etherscan',
                'tx_url' => 'https://etherscan.io/tx/',
            ],
        ];
    }

    function usdt_manual_address_is_valid($network, $address) {
        $networks = usdt_manual_networks();
        return isset($networks[$network]) && preg_match('/'.$networks[$network]['address_pattern'].'/', (string) $address) === 1;
    }

    // One spelling per hash, so changing the case or the 0x prefix can't get past the duplicate check.
    function usdt_manual_normalize_hash($network, $hash) {
        $hash = strtolower(trim((string) $hash));
        if (strpos($hash, '0x') === 0) {
            $hash = substr($hash, 2);
        }
        if (!preg_match('/^[a-f0-9]{64}$/', $hash)) {
            return null;
        }
        return $network === 'trc20' ? $hash : '0x'.$hash;
    }

    // Wallets a customer can pay to, in the order of usdt_manual_networks().
    function usdt_manual_active_wallets($settings) {
        $wallets = is_array($settings['wallets'] ?? null) ? $settings['wallets'] : [];
        $active = [];
        foreach (usdt_manual_networks() as $key => $network) {
            $wallet = $wallets[$key] ?? [];
            if (($wallet['enabled'] ?? '') === 'enable' && usdt_manual_address_is_valid($key, $wallet['address'] ?? '')) {
                $active[$key] = [
                    'name' => $network['name'],
                    'color' => $network['color'],
                    'address' => $wallet['address'],
                    'qr_code' => (string) ($wallet['qr_code'] ?? ''),
                ];
            }
        }
        return $active;
    }

    function usdt_manual_store_qr($file) {
        $extensions = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'];
        $extension = $extensions[mime_content_type($file['tmp_name'])] ?? null;
        $uploadDir = __DIR__ . '/../../../uploads/gateway-assets/';
        if ($extension === null || (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true))) {
            return null;
        }
        $filename = 'usdt-manual-qr-'.bin2hex(random_bytes(8)).'.'.$extension;
        if (!move_uploaded_file($file['tmp_name'], $uploadDir.$filename)) {
            return null;
        }
        return pp_get_site_url().'/pp-content/uploads/gateway-assets/'.$filename;
    }

    // Called by plugin_update-submit before settings are saved. Nothing is written
    // unless every wallet passes, so a bad address can never reach checkout.
    function usdt_manual_prepare_settings($data, $old, $files) {
        $fail = function ($message) {
            return ['status' => false, 'message' => $message];
        };
        $postedWallets = is_array($data['wallets'] ?? null) ? $data['wallets'] : [];
        $oldWallets = is_array($old['wallets'] ?? null) ? $old['wallets'] : [];
        $uploads = [];
        $hasActiveWallet = false;

        foreach (usdt_manual_networks() as $key => $network) {
            $address = trim((string) ($postedWallets[$key]['address'] ?? ''));
            $enabled = ($postedWallets[$key]['enabled'] ?? '') === 'enable';

            if ($address !== '' && !usdt_manual_address_is_valid($key, $address)) {
                return $fail('The '.$network['name'].' address is not valid. '.$network['address_rule']);
            }
            if ($enabled && $address === '') {
                return $fail('Add a '.$network['name'].' address or switch that wallet off.');
            }
            $hasActiveWallet = $hasActiveWallet || $enabled;

            $file = $files['wallet_qr_'.$key] ?? null;
            if (is_array($file) && ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                if ($file['error'] !== UPLOAD_ERR_OK || $file['size'] > 5 * 1024 * 1024) {
                    return $fail('The '.$network['name'].' QR code could not be uploaded. It must be under 5MB.');
                }
                if (!in_array(mime_content_type($file['tmp_name']), ['image/png', 'image/jpeg', 'image/webp'], true)) {
                    return $fail('The '.$network['name'].' QR code must be a PNG, JPG or WEBP image.');
                }
                $uploads[$key] = $file;
            }
        }

        if (($data['status'] ?? '') === 'enable' && !$hasActiveWallet) {
            return $fail('Switch on at least one wallet before enabling this gateway.');
        }

        $wallets = [];
        $removedQrs = [];
        foreach (usdt_manual_networks() as $key => $network) {
            $address = trim((string) ($postedWallets[$key]['address'] ?? ''));
            $oldAddress = (string) ($oldWallets[$key]['address'] ?? '');
            $qrCode = (string) ($oldWallets[$key]['qr_code'] ?? '');

            if (isset($uploads[$key])) {
                $qrCode = usdt_manual_store_qr($uploads[$key]);
                if ($qrCode === null) {
                    return $fail('The '.$network['name'].' QR code could not be saved. Please try again.');
                }
            } elseif ($qrCode !== '' && $address !== $oldAddress) {
                // The old QR still points to the old address, so customers must not see it.
                $qrCode = '';
                $removedQrs[] = $network['name'];
            }

            $wallets[$key] = [
                'address' => $address,
                'enabled' => (($postedWallets[$key]['enabled'] ?? '') === 'enable') ? 'enable' : 'disable',
                'qr_code' => $qrCode,
            ];
        }

        // USDT is priced 1:1 with USD, so USD is the only currency it can be charged in directly.
        $data['wallets'] = $wallets;
        $data['currency'] = 'USD';
        $data['direct_currencies'] = 'USD';
        unset($data['csrf_token']);

        $notice = '';
        if (!empty($removedQrs)) {
            $notice = ' The address changed, so the old QR code was removed for: '.implode(', ', $removedQrs).'. Upload the new one.';
        }
        return ['status' => true, 'settings' => $data, 'notice' => $notice];
    }

    function usdt_manual_details_connection() {
        global $db_prefix;
        $conn = connectDatabase();
        $conn->query("CREATE TABLE IF NOT EXISTS `{$db_prefix}usdt_manual_payment` (
            pp_id VARCHAR(64) NOT NULL PRIMARY KEY,
            network VARCHAR(16) NOT NULL,
            address VARCHAR(64) NOT NULL,
            tx_hash VARCHAR(100) NOT NULL,
            receipt_url VARCHAR(255) NOT NULL DEFAULT '',
            created_at VARCHAR(255) NOT NULL,
            UNIQUE KEY uniq_tx_hash (tx_hash)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        return $conn;
    }

    // Returns false if the hash was already claimed. The unique key settles two customers
    // submitting the same hash at the same moment.
    function usdt_manual_save_details($payment_id, $network, $address, $hash, $receipt_url) {
        global $db_prefix;
        $conn = usdt_manual_details_connection();
        $stmt = $conn->prepare("INSERT IGNORE INTO `{$db_prefix}usdt_manual_payment` (pp_id, network, address, tx_hash, receipt_url, created_at) VALUES (?, ?, ?, ?, ?, ?)");
        $now = getCurrentDatetime('Y-m-d H:i:s');
        $stmt->bind_param('ssssss', $payment_id, $network, $address, $hash, $receipt_url, $now);
        $saved = $stmt->execute() && $stmt->affected_rows === 1;
        $stmt->close();
        $conn->close();
        return $saved;
    }

    function usdt_manual_delete_details($payment_id) {
        global $db_prefix;
        $conn = usdt_manual_details_connection();
        $stmt = $conn->prepare("DELETE FROM `{$db_prefix}usdt_manual_payment` WHERE pp_id = ?");
        $stmt->bind_param('s', $payment_id);
        $stmt->execute();
        $stmt->close();
        $conn->close();
    }

    function usdt_manual_get_details($payment_id) {
        global $db_prefix;
        $conn = usdt_manual_details_connection();
        $stmt = $conn->prepare("SELECT network, address, tx_hash, receipt_url FROM `{$db_prefix}usdt_manual_payment` WHERE pp_id = ?");
        $stmt->bind_param('s', $payment_id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $conn->close();
        return $row ?: null;
    }

    // The card an admin uses to check the payment on View Transaction.
    function usdt_manual_render_review($transaction) {
        $details = usdt_manual_get_details($transaction['pp_id'] ?? '');
        $networks = usdt_manual_networks();
        $network = $networks[$details['network'] ?? ''] ?? null;
        $hash = (string) ($details['tx_hash'] ?? ($transaction['payment_verify_id'] ?? ''));
        $expected = safeNumber($transaction['transaction_amount'] ?? 0) + safeNumber($transaction['transaction_fee'] ?? 0);
        $receipt = (string) ($details['receipt_url'] ?? '');
        ?>
        <div class="card mb-3">
          <div class="card-header">
            <h2 class="card-title h4">USDT Payment Check</h2>
          </div>
          <div class="card-body">
            <div class="row">
              <div class="col-sm-4 mb-3">
                <label class="form-label fw-medium text-dark">Amount expected</label>
                <div class="fw-bold text-dark"><?php echo number_format($expected, 2, '.', ''); ?> USDT</div>
              </div>
              <div class="col-sm-4 mb-3">
                <label class="form-label fw-medium text-dark">Network</label>
                <div class="fw-bold text-dark"><?php echo htmlspecialchars($network['name'] ?? (string) ($transaction['payment_sender_number'] ?? ''), ENT_QUOTES); ?></div>
              </div>
              <div class="col-sm-4 mb-3">
                <label class="form-label fw-medium text-dark">Receipt</label>
                <div class="fw-bold text-dark">
                  <?php if (preg_match('#^https?://#i', $receipt)) { ?>
                    <a href="<?php echo htmlspecialchars($receipt, ENT_QUOTES); ?>" target="_blank" rel="noopener">View receipt</a>
                  <?php } else { ?>
                    None uploaded
                  <?php } ?>
                </div>
              </div>
              <div class="col-12 mb-3">
                <label class="form-label fw-medium text-dark">Sent to</label>
                <div class="fw-bold text-dark text-break"><?php echo htmlspecialchars((string) ($details['address'] ?? 'Not recorded'), ENT_QUOTES); ?></div>
              </div>
              <div class="col-12">
                <label class="form-label fw-medium text-dark">Transaction hash</label>
                <div class="fw-bold text-dark text-break"><?php echo htmlspecialchars($hash, ENT_QUOTES); ?></div>
                <?php if ($network !== null && usdt_manual_normalize_hash($details['network'], $hash) !== null) { ?>
                  <a class="btn btn-white btn-sm mt-2" href="<?php echo htmlspecialchars($network['tx_url'].$hash, ENT_QUOTES); ?>" target="_blank" rel="noopener noreferrer">
                    <i class="bi-box-arrow-up-right"></i> Check on <?php echo htmlspecialchars($network['explorer'], ENT_QUOTES); ?>
                  </a>
                <?php } ?>
              </div>
            </div>
          </div>
        </div>
        <?php
    }
?>
