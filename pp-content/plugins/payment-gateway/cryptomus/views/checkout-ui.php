<?php
    global $db_prefix;
    $transaction_details = pp_get_transation($payment_id);
    $setting = pp_get_settings();
    $faq_list = pp_get_faq();
    $support_links = pp_get_support_links();    
    // Use system standards for branding (colors + info)
    $brand_colors = [
        'global_text_color' => $setting['response'][0]['global_text_color'] ?? '#000',
        'primary_button_color' => $setting['response'][0]['primary_button_color'] ?? '#007bff',
        'button_text_color' => $setting['response'][0]['button_text_color'] ?? '#fff',
        'button_hover_color' => $setting['response'][0]['button_hover_color'] ?? '#0056b3',
        'button_hover_text_color' => $setting['response'][0]['button_hover_text_color'] ?? '#fff'
    ];

    $plugin_slug = 'cryptomus';
    $plugin_info = pp_get_plugin_info($plugin_slug);
    $settings = pp_get_plugin_setting($plugin_slug);
    
    // Check if credentials are configured
    $credentials_configured = !empty($settings['merchant_uuid'] ?? '') && !empty($settings['payment_key'] ?? '');
    
    // Calculate amount + fees
    $transaction_amount = convertToDefault($transaction_details['response'][0]['transaction_amount'], $transaction_details['response'][0]['transaction_currency'], $settings['currency'] ?? 'USD');
    $transaction_fee = safeNumber($settings['fixed_charge'] ?? 0) + ($transaction_amount * (safeNumber($settings['percent_charge'] ?? 0) / 100));
    $transaction_amount = $transaction_amount+$transaction_fee;
    
    // Include Cryptomus API class
    require_once __DIR__ . '/../cryptomus-api.php';
    
    // Track payment cancellation using session
    session_start();
    $payment_cancelled = false;
    $payment_success = false;
    
    // Check if user returned from Cryptomus (callback)
    if (isset($_GET['status']) && !isset($_SESSION['cryptomus_payment_processed_' . $payment_id])) {
        $return_status = strtolower($_GET['status']);
        
        // Check for cancellation or failure
        if ($return_status == 'cancel' || $return_status == 'fail') {
            // Set session variable to show cancel message
            $_SESSION['cryptomus_payment_cancelled_' . $payment_id] = true;
            
            // Redirect to clean URL
            // Redirect to clean URL
            $clean_url = pp_get_site_url() . '/payment/' . $payment_id . '?method=cryptomus';
            header('Location: ' . $clean_url);
            exit();
        }
    }
    
    // Check if we should show cancel message (from session)
    if (isset($_SESSION['cryptomus_payment_cancelled_' . $payment_id])) {
        $payment_cancelled = true;
        // Clear the cancel flag after showing
        unset($_SESSION['cryptomus_payment_cancelled_' . $payment_id]);
    }
    
    // Get brand name for title and favicon
    $brand_name_for_title = $setting['response'][0]['site_name'] ?? 'PipraPay';
    $favicon_url = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8Xw8AAoMBgDTD2qgAAAAASUVORK5CYII=';
    $brand_logo_for_display = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8Xw8AAoMBgDTD2qgAAAAASUVORK5CYII=';
    $brand_name_for_display = $setting['response'][0]['site_name'] ?? 'PipraPay';
    
    if(!empty($transaction_details['response'][0]['brand_id'])){
        $brand_response_title = json_decode(getData($db_prefix.'brands', 'WHERE id="'.$transaction_details['response'][0]['brand_id'].'"'), true);
        if($brand_response_title['status'] == true && !empty($brand_response_title['response'])){
            if(!empty($brand_response_title['response'][0]['brand_name'])){
                $brand_name_for_title = $brand_response_title['response'][0]['brand_name'];
                $brand_name_for_display = $brand_response_title['response'][0]['brand_name'];
            }
            if(!empty($brand_response_title['response'][0]['brand_icon'])){
                $favicon_url = $brand_response_title['response'][0]['brand_icon'];
                $brand_logo_for_display = $brand_response_title['response'][0]['brand_icon'];
            }
        }
    }else{
        if(isset($setting['response'][0]['favicon']) && $setting['response'][0]['favicon'] != "--"){
            $favicon_url = $setting['response'][0]['favicon'];
            $brand_logo_for_display = $setting['response'][0]['favicon'];
        }
    }
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo ($settings['display_name'] ?? 'Cryptomus')?> - <?php echo $brand_name_for_title?></title>
    <link rel="icon" type="image/x-icon" href="<?php echo $favicon_url?>">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.4.1/jquery.min.js"></script>
    <style>
        :root {
            --secondary: #00cec9;
            --success: #00b894;
            --danger: #d63031;
            --warning: #fdcb6e;
            --dark: #2d3436;
            --light: #f5f6fa;
            --gray: #636e72;
            --border: #dfe6e9;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f8f9fa;
            color: var(--dark);
            line-height: 1.6;
        }
        
        .payment-container {
            max-width: 600px;
            margin: 2rem auto;
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
            overflow: hidden;
        }
        
        .payment-header {
            display: flex;
            background: var(--light);
            border-radius: 8px;
            padding: 1rem;
            align-items: center;
            margin-top: 1.5rem;
            margin-left: 1.5rem;
            color: <?php echo $setting['response'][0]['global_text_color'] ?? '#000'?>;
            margin-right: 1.5rem;
            justify-content: space-between;
        }
        
        .payment-body {
            padding: 1.5rem;
        }
        
        .payment-amount {
            display: flex;
            background: var(--light);
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1.5rem;
            align-items: center;
            position: relative;
        }
        
        .merchant-logo {
            width: 50px;
            height: 50px;
            border-radius: 8px;
            object-fit: cover;
            margin-right: 1rem;
            background: white;
            padding: 5px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        
        .merchant-details {
            flex: 1;
        }
        
        .merchant-name {
            font-weight: 600;
            margin-bottom: 0.25rem;
        }
        
        .amount-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: <?php echo $setting['response'][0]['global_text_color'] ?? '#000'?>;
        }
        
        .amount-label {
            font-size: 0.8rem;
            color: var(--gray);
        }
        
        .btn-pay {
            width: 100%;
            padding: 1rem;
            background: <?php echo $setting['response'][0]['primary_button_color'] ?? '#007bff'?>;
            color: <?php echo $setting['response'][0]['button_text_color'] ?? '#fff'?>;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }
        
        .btn-pay:hover {
            background: <?php echo $setting['response'][0]['button_hover_color'] ?? '#0056b3'?>;
            color: <?php echo $setting['response'][0]['button_hover_text_color'] ?? '#fff'?>;
            transform: translateY(-1px);
        }
        
        .payment-footer {
            padding: 1rem 1.5rem;
            border-top: 1px solid var(--border);
            font-size: 0.8rem;
            color: var(--gray);
            text-align: center;
        }
        
        .secure-badge {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            margin-top: 0.5rem;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        @media (max-width: 576px) {
            .payment-container {
                margin: 0;
                border-radius: 0;
                min-height: 100vh;
            }
            .payment-amount {
                flex-direction: column;
                align-items: flex-start;
            }
            .merchant-logo {
                margin-right: 0;
                margin-bottom: 1rem;
            }
        }
    </style>
</head>
<body>
    <div class="payment-container">
        <div class="payment-header">
            <i class="fas fa-arrow-left" style="cursor: pointer;" onclick="location.href='<?php echo pp_get_paymentlink($payment_id)?>'"></i>
        </div>
        
        <div class="payment-body">
            <center>
                <img src="<?php echo pp_get_site_url().'/pp-content/plugins/'.$plugin_info['plugin_dir'].'/'.$plugin_slug.'/assets/icon.png';?>" style=" height: 50px; margin-bottom: 20px; ">
            </center>

            <div class="payment-amount">
                <img src="<?php echo $brand_logo_for_display?>" alt="Merchant Logo" class="merchant-logo">
                <div class="merchant-details">
                    <div class="merchant-name"><?php echo $brand_name_for_display?></div>
                    <div class="amount-value"><?php echo number_format($transaction_amount,2).' '.($settings['currency'] ?? 'USD')?></div>
                </div>
            </div>
            
            <div class="payment-form">
                <?php if($payment_cancelled): ?>
                    <div class="alert alert-warning" role="alert">
                        Payment was cancelled. You can try again.
                    </div>
                <?php endif; ?>

                <?php
                    $php_error = '';
                    if(!$credentials_configured) {
                        $php_error = 'Failed to initialize payment gateway.';
                        error_log("Cryptomus Config Error ($payment_id): Merchant UUID or Payment Key missing.");
                    }

                    if(empty($php_error) && isset($_GET['status']) && $_GET['status'] == 'success'){
                            $payment_uuid = $_SESSION['cryptomus_payment_uuid_' . $payment_id] ?? '';
                            
                            if(!empty($payment_uuid)) {
                                $_SESSION['cryptomus_payment_processed_' . $payment_id] = true;
                                try {
                                    $cryptomus = new CryptomusAPI($settings['payment_key'], $settings['merchant_uuid']);
                                    $result = $cryptomus->getPaymentInfo($payment_uuid);
                                    
                                    if($result['success']) {
                                        $payment_data = $result['data'];
                                        $payment_status = $payment_data['payment_status'] ?? '';
                                        
                                        if($payment_status == 'paid' || $payment_status == 'paid_over') {
                                            $transaction_id = $payment_data['txid'] ?? $payment_data['uuid'];
                                            $sender = $payment_data['from'] ?? 'Cryptomus';
                                            
                                            $check_transactionid = pp_check_transaction_exits($transaction_id);
                                            if($check_transactionid['status'] == false){
                                                if(pp_set_transaction_byid($payment_id, $plugin_slug, $plugin_info['plugin_name'], $sender, $transaction_id, 'completed')){
                                                    echo '<script>location.href="'.pp_get_paymentlink($payment_id).'";</script>';
                                                    exit();
                                                }
                                            }
                                        }
                                    }
                                } catch (Exception $e) {
                                    error_log("Cryptomus Exception ($payment_id): " . $e->getMessage());
                                }
                            }
                    }else{
                            $_SESSION['cryptomus_payment_initiated_' . $payment_id] = true;
                            try {
                                $cryptomus = new CryptomusAPI($settings['payment_key'], $settings['merchant_uuid']);
                                $separator = (strpos(getCurrentUrl(), '?') !== false) ? '&' : '?';
                                $invoice_id = $transaction_details['response'][0]['invoice_id'] ?? $payment_id;
                                $order_id = $invoice_id . rand(100, 999);
                                
                                $payment_data = [
                                    'amount' => strval($transaction_amount),
                                    'currency' => $settings['currency'] ?? 'USD',
                                    'order_id' => $order_id,
                                    'url_return' => getCurrentUrl() . $separator . "status=cancel",
                                    'url_success' => getCurrentUrl() . $separator . "status=success",
                                    'url_callback' => pp_get_site_url() . '/webhook/cryptomus/' . $payment_id,
                                    'is_payment_multiple' => ($settings['is_payment_multiple'] ?? 'true') === 'true',
                                    'lifetime' => intval($settings['lifetime'] ?? 3600),
                                    'is_refresh' => true,
                                ];
                                
                                if(!empty($settings['subtract']) && intval($settings['subtract']) > 0) {
                                    $payment_data['subtract'] = intval($settings['subtract']);
                                }
                                
                                if(!empty($settings['accuracy_payment_percent'])) {
                                    $payment_data['accuracy_payment_percent'] = floatval($settings['accuracy_payment_percent']);
                                }
                                
                                $result = $cryptomus->createPayment($payment_data);
                                if($result['success']) {
                                    $payment_response = $result['data'];
                                    $payment_url = $payment_response['url'] ?? '';
                                    $payment_uuid = $payment_response['uuid'] ?? '';
                                    $_SESSION['cryptomus_payment_uuid_' . $payment_id] = $payment_uuid;
                                    
                                    if(!empty($payment_url)) {
                                        echo '<script>location.href="'.$payment_url.'";</script>';
                                        exit();
                                    } else {
                                        $php_error = 'Payment URL could not be generated.';
                                    }
                                }else{
                                    $php_error = 'Failed to initialize payment gateway.';
                                }
                            } catch (Exception $e) {
                                $php_error = 'An internal error occurred.';
                            }
                    }
                    
                    if(!empty($php_error)):
                ?>
                    <div class="alert alert-danger" role="alert">
                        <?php echo $php_error; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="payment-footer">
            <div>Your payment is secured with 256-bit encryption</div>
            <div class="secure-badge">
                <span>Powered by <a href="https://piprapay.com/" target="blank" style="color: <?php echo $setting['response'][0]['global_text_color']?>; text-decoration: none"><strong style="cursor: pointer">PipraPay</strong></a></span>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

