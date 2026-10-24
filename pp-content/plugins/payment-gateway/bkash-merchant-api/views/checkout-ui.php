<?php
    require_once __DIR__ . '/../vendor-bkash-merchant/autoload.php';

    $transaction_details = pp_get_transation($payment_id);
    $setting = pp_get_settings();
    $faq_list = pp_get_faq();
    $support_links = pp_get_support_links();

    $plugin_slug = 'bkash-merchant-api';
    $plugin_info = pp_get_plugin_info($plugin_slug);
    $settings = pp_get_plugin_setting($plugin_slug);
    
    $transaction_amount = convertToDefault($transaction_details['response'][0]['transaction_amount'], $transaction_details['response'][0]['transaction_currency'], $settings['currency']);
    $transaction_fee = safeNumber($settings['fixed_charge']) + ($transaction_amount * (safeNumber($settings['percent_charge']) / 100));
    $transaction_amount = $transaction_amount+$transaction_fee;
    
    $app_key = $settings['bkash_app_key'];
    $app_secret = $settings['bkash_secret_key'];
    $username = $settings['bkash_username'];
    $password = $settings['bkash_password'];
    
    if($settings['bkash_mode'] == "live"){
        $base_url = 'https://tokenized.pay.bka.sh';
    }else{
        $base_url = 'https://tokenized.sandbox.bka.sh';
    }
    
    // Start Grant Token
    $client = new \GuzzleHttp\Client();
    $response = $client->request('POST', $base_url.'/v1.2.0-beta/tokenized/checkout/token/grant', [
        'body' => json_encode([
            'app_key' => $app_key,
            'app_secret' => $app_secret
        ]),
        'headers' => [
            'accept' => 'application/json',
            'content-type' => 'application/json',
            'password' => $password,
            'username' => $username,
        ],
    ]);
    $response = json_decode($response->getBody());
    $id_token = $response->id_token;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $settings['display_name']?> - <?php echo $setting['response'][0]['site_name']?></title>
    <link rel="icon" type="image/x-icon" href="<?php if(isset($setting['response'][0]['favicon'])){if($setting['response'][0]['favicon'] == "--"){echo 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8Xw8AAoMBgDTD2qgAAAAASUVORK5CYII=';}else{echo $setting['response'][0]['favicon'];};}else{echo 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8Xw8AAoMBgDTD2qgAAAAASUVORK5CYII=';}?>">
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
        
        .accordion-button:not(.collapsed) {
            color: var(--bs-accordion-active-color);
            background-color: transparent;
            box-shadow: inset 0 calc(-1 * transparent) 0 transparent;
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
            color: <?php echo $setting['response'][0]['global_text_color']?>;
            margin-right: 1.5rem;
            justify-content: space-between;
        }
        
        .payment-logo {
            font-weight: 700;
            font-size: 1.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        
        .payment-logo img {
            height: 30px;
        }
        
        .merchant-info {
            margin-top: 1rem;
            font-size: 0.9rem;
            opacity: 0.9;
        }
        
        .payment-body {
            padding: 1.5rem;
        }
        
        /* Updated Payment Amount Section */
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
            color: <?php echo $setting['response'][0]['global_text_color']?>;
        }
        
        .amount-label {
            font-size: 0.8rem;
            color: var(--gray);
        }
        
        .payment-actions {
            display: flex;
            gap: 0.5rem;
            position: absolute;
            right: 1rem;
            bottom: 1rem;
        }
        
        .action-btn {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: white;
            border: none;
            color: var(--gray);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        
        .action-btn:hover {
            background: <?php echo $setting['response'][0]['active_tab_color']?>;
            color: <?php echo $setting['response'][0]['active_tab_text_color']?>;
            transform: translateY(-2px);
        }
        
        .action-btn i {
            font-size: 0.8rem;
        }
        
        .method-tabs {
            display: flex;
            border-bottom: 1px solid var(--border);
            margin-bottom: 1rem;
        }
        
        .method-tab {
            padding: 0.75rem 1rem;
            font-weight: 500;
            cursor: pointer;
            border-bottom: 2px solid transparent;
            transition: all 0.2s;
        }
        
        .method-tab.active {
            border-bottom-color: <?php echo $setting['response'][0]['active_tab_color']?>;
            color: <?php echo $setting['response'][0]['active_tab_color']?>;
        }
        
        .method-content {
            display: none;
        }
        
        .method-content.active {
            display: block;
        }
        
        .card-form .form-group {
            margin-bottom: 1rem;
        }
        
        .form-label {
            font-size: 0.85rem;
            font-weight: 500;
            margin-bottom: 0.5rem;
            display: block;
        }
        
        .form-control {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.2s;
        }
        
        .form-control:focus {
            border-color: <?php echo $setting['response'][0]['global_text_color']?>;
            box-shadow: 0 0 0 3px rgba(108, 92, 231, 0.1);
            outline: none;
        }
        
        .card-icons {
            display: flex;
            gap: 0.5rem;
            margin-top: 0.5rem;
        }
        
        .card-icon {
            width: 40px;
            height: 25px;
            object-fit: contain;
            opacity: 0.3;
            transition: opacity 0.2s;
        }
        
        .card-icon.active {
            opacity: 1;
        }
        
        .row {
            display: flex;
            gap: 1rem;
        }
        
        .col {
            flex: 1;
        }
        
        .btn-pay {
            width: 100%;
            padding: 1rem;
            background: <?php echo $setting['response'][0]['primary_button_color']?>;
            color: <?php echo $setting['response'][0]['button_text_color']?>;
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
            background: <?php echo $setting['response'][0]['button_hover_color']?>;
            color: <?php echo $setting['response'][0]['button_hover_text_color']?>;
            transform: translateY(-1px);
        }
        
        .btn-pay:active {
            transform: translateY(0);
        }
        
        .upi-form {
            text-align: center;
        }
        
        .upi-id {
            background: var(--light);
            border-radius: 8px;
            padding: 1rem;
            font-weight: 500;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }
        
        .qr-code {
            width: 200px;
            height: 200px;
            margin: 1rem auto;
            background: #f0f0f0;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--gray);
        }
        
        .netbanking-form select {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 1rem;
            margin-bottom: 1rem;
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
        
        .processing {
            display: none;
            text-align: center;
            padding: 2rem;
        }
        
        .spinner {
            width: 40px;
            height: 40px;
            border: 4px solid rgba(0, 0, 0, 0.1);
            border-left-color: <?php echo $setting['response'][0]['global_text_color']?>;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 1rem;
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
            
            .payment-actions {
                position: static;
                margin-top: 1rem;
                align-self: flex-end;
            }
        }
                
        .custom-contact-grid {
          display: flex;
          flex-wrap: wrap;
          gap: 16px;
        }
        .contact-box {
          flex: 1 1 calc(50% - 8px);
          text-decoration: none;
        }
        .contact-inner {
          display: flex;
          align-items: center;
          padding: 16px;
          background: #f8f9fa;
          border-radius: 12px;
          box-shadow: 0 2px 5px rgba(0,0,0,0.05);
          transition: 0.3s;
        }
        .contact-inner:hover {
          background-color: #e2e6ea;
        }
        .contact-inner img {
          width: 28px;
          height: 28px;
          margin-right: 12px;
        }
        .contact-inner span {
          font-size: 14px;
          color: #212529;
        }
        @media (max-width: 767px) {
          .contact-box {
            flex: 1 1 100%;
          }
        }
        .list-unstyled{
            border: 1px solid #dddddd;
            border-radius: 8px;
            padding: 19px;
        }
        .list-unstyled li{
            height: 40px;
            font-size: 15px;
            align-items: center;
        }
        .list-unstyled li button{
            font-size: 10px;
        }
        
        .bg-primary{
            background-color: <?php echo hexToRgba($setting['response'][0]['global_text_color'], 0.1);?> !important;
        }
        .text-primary{
            color: <?php echo $setting['response'][0]['global_text_color']?> !important;
        }
        
        .btn-primary{
            background-color: <?php echo $setting['response'][0]['primary_button_color'];?> !important;
            border: 1px solid <?php echo $setting['response'][0]['primary_button_color'];?> !important;
            color: <?php echo $setting['response'][0]['button_text_color'];?> !important;
        }
    </style>
</head>
<body>
    <div class="payment-container">
        <div class="payment-header">
            <i class="fas fa-arrow-left" style=" cursor: pointer; " onclick="location.href='<?php echo pp_get_paymentlink($payment_id)?>'"></i>
        </div>
        
        <div class="payment-body">
            <!-- Updated Payment Amount Section -->
            <center><img src="<?php echo pp_get_site_url().'/pp-content/plugins/'.$plugin_info['plugin_dir'].'/'.$plugin_slug.'/assets/icon.png';?>" style=" height: 50px; margin-bottom: 20px; "></center>

            <div class="payment-amount">
                <img src="<?php if(isset($setting['response'][0]['favicon'])){if($setting['response'][0]['favicon'] == "--"){echo 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8Xw8AAoMBgDTD2qgAAAAASUVORK5CYII=';}else{echo $setting['response'][0]['favicon'];};}else{echo 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8Xw8AAoMBgDTD2qgAAAAASUVORK5CYII=';}?>" alt="Merchant Logo" class="merchant-logo">
                <div class="merchant-details">
                    <div class="merchant-name"><?php echo $setting['response'][0]['site_name']?></div>
                    <div class="amount-value"><?php echo number_format($transaction_amount,2).' '.$settings['currency']?></div>
                </div>
            </div>
            
            <div class="payment-form">
                  <?php
                        if(isset($_GET['paymentID'])){
                            if($_GET['status'] == "success"){
                                $paymentID= escape_string($_GET['paymentID']);

                                $auth = $id_token;
                                $post_token = array( 'paymentID' => $paymentID );
                                $url = curl_init($base_url.'/v1.2.0-beta/tokenized/checkout/execute');       
                                $posttoken = json_encode($post_token);
                                $header = array(
                                    'Content-Type:application/json',
                                    'Authorization:' . $auth,
                                    'X-APP-Key:'.$app_key
                                );
                                curl_setopt($url, CURLOPT_HTTPHEADER, $header);
                                curl_setopt($url, CURLOPT_CUSTOMREQUEST, "POST");
                                curl_setopt($url, CURLOPT_RETURNTRANSFER, true);
                                curl_setopt($url, CURLOPT_POSTFIELDS, $posttoken);
                                curl_setopt($url, CURLOPT_FOLLOWLOCATION, 1);
                                curl_setopt($url, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
                                $resultdata = curl_exec($url);
                                curl_close($url);
                                $obj = json_decode($resultdata);

                                if(isset($obj->transactionStatus)){
                                    $transactionStatus = $obj->transactionStatus;
                                    
                                    if($transactionStatus == "Completed"){
                                        $customerMsisdn = $obj->customerMsisdn;
                                        $paymentID = $obj->paymentID;
                                        $trxID = $obj->trxID;
                                        $merchantInvoiceNumber = $obj->merchantInvoiceNumber;
                                        $time = $obj->paymentExecuteTime;
                                        $amount = $obj->amount;
                                        $payerAccount = $obj->payerAccount;
                                        
                                        $parts = explode("_", $merchantInvoiceNumber);
                                        $order_id = $parts[1] ?? '';
                                        
                                        if ($settings['bkash_mode'] == "sandbox") {
                                            // Make sure $resultdata is valid JSON
                                            echo '
                                            <pre class="bg-light border p-3 rounded"><code id="json-output"></code></pre>
                                            <script>
                                              const rawJson = ' . json_encode($resultdata) . ';
                                              document.getElementById("json-output").textContent = JSON.stringify(JSON.parse(rawJson), null, 2);
                                            </script>
                                            ';
                                        } elseif ((string) $order_id !== (string) $payment_id) {
                                            // The bKash payment belongs to another checkout.
                   ?>
                                                <div class="alert alert-danger" role="alert">
                                                  Unable to verify this payment. Please contact support.
                                                </div>
                   <?php
                                        } else {
                                            $check_transactionid = pp_check_transaction_exits($trxID);
                                            if($check_transactionid['status'] == false){
                                                if(pp_set_transaction_byid($payment_id, $plugin_slug, $plugin_info['plugin_name'], $payerAccount, $trxID, 'completed')){
                                                    echo '<script>location.href="'.pp_get_paymentlink($payment_id).'";</script>';
                                                }
                                            }else{
                   ?>
                                                <div class="alert alert-danger" role="alert">
                                                  Transaction ID already exits
                                                </div>
                   <?php
                                            }
                                        }
                                    }else{
                   ?>
                                        <div class="alert alert-danger" role="alert">
                                          Transaction <?php echo $transactionStatus;?>
                                        </div>
                   <?php
                                    }
                                }else{
                   ?>
                                <div class="alert alert-danger" role="alert">
                                  <?php echo $resultdata;?>
                                </div>
                   <?php
                                }
                            }else{
                   ?>
                                <div class="alert alert-danger" role="alert">
                                  Transaction <?php echo $_GET['status']?>
                                </div>
                   <?php
                            }
                        }else{
                            $auth = $id_token;
                            $requestbody = array(
                            'mode' => '0011',
                            'amount' => ceil($transaction_amount),
                            'currency' => 'BDT',
                            'intent' => 'sale',
                            'payerReference' => rand().'_'.$payment_id,
                            'merchantInvoiceNumber' => rand().'_'.$payment_id,
                            'callbackURL' => getCurrentUrl()
                            );
                            
                            $url = curl_init($base_url.'/v1.2.0-beta/tokenized/checkout/create');
                            
                            $requestbodyJson = json_encode($requestbody);
                            $header = array(
                            'Content-Type:application/json',
                            'Authorization:'.$auth,
                            'X-APP-Key:'.$app_key
                            );
                            curl_setopt($url, CURLOPT_HTTPHEADER, $header);
                            curl_setopt($url, CURLOPT_CUSTOMREQUEST, "POST");
                            curl_setopt($url, CURLOPT_RETURNTRANSFER, true);
                            curl_setopt($url, CURLOPT_POSTFIELDS, $requestbodyJson);
                            curl_setopt($url, CURLOPT_FOLLOWLOCATION, 1);
                            curl_setopt($url, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
                            $resultdata = curl_exec($url);
                            curl_close($url);
                            $obj = json_decode($resultdata);
                                                    
                            if ($settings['bkash_mode'] == "sandbox") {
                                // Make sure $resultdata is valid JSON
                                echo '
                                <pre class="bg-light border p-3 rounded"><code id="json-output"></code></pre>
                                <script>
                                  const rawJson = ' . json_encode($resultdata) . ';
                                  document.getElementById("json-output").textContent = JSON.stringify(JSON.parse(rawJson), null, 2);
                                </script>
                                ';
                            
                                // Decode JSON to use bkashURL
                                $obj = json_decode($resultdata, true);
                  ?>
                                <a href="<?php echo $obj['bkashURL']; ?>" class="mt-2" style=" text-decoration: none; ">
                                    <button class="btn-pay">Continue</button>
                                </a>
                  <?php
                            } else {
                                $obj = json_decode($resultdata, true);
                                
                                echo '<script>location.href="'.$obj['bkashURL'].'";</script>';
                            }
                        }
                  ?>
            </div>
        </div>
        
        <div class="payment-footer">
            <div>Your payment is secured with 256-bit encryption</div>
            <div class="secure-badge">
                <span>Powered by <a href="https://piprapay.com/" target="blank" style="color: <?php echo $setting['response'][0]['global_text_color']?>; text-decoration: none"><strong style="cursor: pointer">PipraPay</strong></a></span>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>