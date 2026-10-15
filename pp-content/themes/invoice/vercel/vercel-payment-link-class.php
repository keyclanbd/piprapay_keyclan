<?php
    if (!defined('pp_allowed_access')) {
        die('Direct access not allowed');
    }

    $payment_details = pp_get_payment_link($paymentid);
    $payment_details_items = pp_get_payment_link_items($paymentid);
    $setting = pp_get_settings();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $payment_details['response'][0]['pl_name']?> - <?php echo $setting['response'][0]['site_name']?></title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <link rel="icon" type="image/x-icon" href="<?php if(isset($setting['response'][0]['favicon'])){if($setting['response'][0]['favicon'] == "--"){echo 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8Xw8AAoMBgDTD2qgAAAAASUVORK5CYII=';}else{echo $setting['response'][0]['favicon'];};}else{echo 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8Xw8AAoMBgDTD2qgAAAAASUVORK5CYII=';}?>">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.4.1/jquery.min.js"></script>
    <!-- Stripe-like fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --stripe-blue: <?php echo $setting['response'][0]['active_tab_color']?>;
            --stripe-blue-dark: <?php echo $setting['response'][0]['active_tab_text_color']?>;
            --stripe-text: #1a1a1a;
            --stripe-light-text: #6b7c93;
            --stripe-border: #e0e6ed;
            --stripe-light-bg: #f6f9fc;
            --stripe-success: #24b47e;
            --stripe-error: #ff5252;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            color: var(--stripe-text);
            background-color: var(--stripe-light-bg);
            line-height: 1.5;
            -webkit-font-smoothing: antialiased;
        }
        
        .payment-container {
            max-width: 480px;
            margin: 2rem auto;
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.02), 0 1px 3px rgba(0, 0, 0, 0.05);
            overflow: hidden;
            border: 1px solid var(--stripe-border);
        }
        
        .payment-header {
            padding: 24px;
            border-bottom: 1px solid var(--stripe-border);
            position: relative;
        }
        
        .payment-logo {
            font-weight: 600;
            font-size: 18px;
            color: <?php echo $setting['response'][0]['global_text_color']?>;
            display: flex;
            align-items: center;
        }
        
        .payment-logo-icon {
            width: 24px;
            height: 24px;
            background: <?php echo $setting['response'][0]['global_text_color']?>;
            border-radius: 4px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-right: 8px;
            color: white;
            font-size: 12px;
        }
        
        .payment-merchant {
            position: absolute;
            right: 24px;
            top: 24px;
            font-size: 14px;
            color: var(--stripe-light-text);
            display: flex;
            align-items: center;
        }
        
        .payment-merchant-logo {
            width: 24px;
            height: 24px;
            border-radius: 4px;
            background: #f0f0f0;
            margin-right: 8px;
            overflow: hidden;
        }
        
        .payment-body {
            padding: 24px;
        }
        
        .payment-title {
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 16px;
            color: var(--stripe-text);
        }
        
        .payment-description {
            color: var(--stripe-light-text);
            font-size: 15px;
            margin-bottom: 24px;
        }
        
        .payment-amount {
            background: var(--stripe-light-bg);
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .amount-label {
            font-size: 14px;
            color: var(--stripe-light-text);
        }
        
        .amount-value {
            font-size: 20px;
            font-weight: 600;
        }
        
        .payment-methods {
            margin-bottom: 24px;
        }
        
        .payment-method {
            border: 1px solid var(--stripe-border);
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 12px;
            cursor: pointer;
            transition: all 0.2s ease;
            position: relative;
        }
        
        .payment-method:hover {
            border-color: var(--stripe-blue);
        }
        
        .payment-method.active {
            border-color: var(--stripe-blue);
            background-color: rgba(99, 91, 255, 0.03);
        }
        
        .payment-method.active::after {
            content: "";
            position: absolute;
            top: -1px;
            right: -1px;
            width: 0;
            height: 0;
            border-style: solid;
            border-width: 0 24px 24px 0;
            border-color: transparent var(--stripe-blue) transparent transparent;
        }
        
        .payment-method.active::before {
            content: "\f00c";
            font-family: "Font Awesome 6 Free";
            font-weight: 900;
            position: absolute;
            top: 1px;
            right: 1px;
            color: white;
            font-size: 10px;
            z-index: 1;
        }
        
        .payment-method-content {
            display: flex;
            align-items: center;
        }
        
        .payment-method-icon {
            width: 40px;
            height: 28px;
            background: white;
            border-radius: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 16px;
            border: 1px solid var(--stripe-border);
            overflow: hidden;
        }
        
        .payment-method-icon img {
            width: 32px;
            height: auto;
        }
        
        .payment-method-details h6 {
            font-weight: 500;
            margin-bottom: 2px;
            font-size: 15px;
        }
        
        .payment-method-details small {
            color: var(--stripe-light-text);
            font-size: 13px;
        }
        
        .payment-form {
            margin-top: 16px;
        }
        
        .form-group {
            margin-bottom: 16px;
        }
        
        .form-label {
            font-size: 13px;
            font-weight: 500;
            color: var(--stripe-text);
            margin-bottom: 6px;
            display: block;
        }
        
        .form-control {
            height: 44px;
            border-radius: 8px;
            border: 1px solid var(--stripe-border);
            padding: 12px 16px;
            font-size: 15px;
            transition: all 0.2s ease;
            width: 100%;
            box-sizing: border-box;
        }
        
        .form-control:focus {
            border-color: var(--stripe-blue);
            box-shadow: 0 0 0 1px var(--stripe-blue);
            outline: none;
        }
        
        .form-control::placeholder {
            color: #c3cad9;
        }
        
        .card-input-wrapper {
            position: relative;
        }
        
        .card-icons {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            display: flex;
            gap: 4px;
        }
        
        .card-icon {
            width: 28px;
            height: 18px;
            opacity: 0.3;
            transition: opacity 0.2s ease;
        }
        
        .card-icon.active {
            opacity: 1;
        }
        
        .form-row {
            display: flex;
            gap: 12px;
        }
        
        .form-col {
            flex: 1;
        }
        
        .btn-pay {
            background: <?php echo $setting['response'][0]['primary_button_color']?>;
            color: <?php echo $setting['response'][0]['button_text_color']?>;
            border: none;
            width: 100%;
            padding: 14px;
            font-size: 15px;
            font-weight: 500;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s ease;
            margin-top: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .btn-pay:hover {
            background: <?php echo $setting['response'][0]['button_hover_color']?>;
            color: <?php echo $setting['response'][0]['button_hover_text_color']?>;
            transform: translateY(-1px);
        }
        
        .btn-pay:active {
            transform: translateY(0);
        }
        
        .btn-pay i {
            margin-right: 8px;
        }
        
        .payment-footer {
            padding: 16px 24px;
            border-top: 1px solid var(--stripe-border);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .secure-payment {
            display: flex;
            align-items: center;
            font-size: 13px;
            color: var(--stripe-light-text);
        }
        
        .secure-payment i {
            color: var(--stripe-success);
            margin-right: 8px;
            font-size: 14px;
        }
        
        .payment-links {
            display: flex;
            gap: 16px;
        }
        
        .payment-links a {
            color: var(--stripe-light-text);
            font-size: 13px;
            text-decoration: none;
            transition: color 0.2s ease;
        }
        
        .payment-links a:hover {
            color: var(--stripe-blue);
        }
        
        /* Stripe-like card element */
        .card-element {
            height: 44px;
            padding: 12px 16px;
            border: 1px solid var(--stripe-border);
            border-radius: 8px;
            background: white;
            display: flex;
            align-items: center;
        }
        
        /* Loading animation */
        .processing-payment {
            display: none;
            text-align: center;
            padding: 24px;
        }
        
        .spinner {
            width: 24px;
            height: 24px;
            border: 3px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 1s ease-in-out infinite;
            display: inline-block;
            margin-right: 8px;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        /* Error state */
        .error-message {
            color: var(--stripe-error);
            font-size: 13px;
            margin-top: 8px;
            display: none;
        }
        
        .has-error .form-control {
            border-color: var(--stripe-error);
        }
        
        .has-error .form-label {
            color: var(--stripe-error);
        }
    </style>
</head>
<body>
    <div class="payment-container">
        <div class="payment-header">
            <div class="payment-logo">
                <img src="<?php if(isset($setting['response'][0]['logo'])){if($setting['response'][0]['logo'] == "--"){echo 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8Xw8AAoMBgDTD2qgAAAAASUVORK5CYII=';}else{echo $setting['response'][0]['logo'];};}else{echo 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8Xw8AAoMBgDTD2qgAAAAASUVORK5CYII=';}?>" style=" height: 30px; ">
            </div>
        </div>
        
        <div class="payment-body">
            <div class="payment-title">Complete your payment</div>
            <div class="payment-description">Enter your details to complete the purchase</div>
            
            <div class="payment-methods">
                <div class="payment-method active" data-method="card">
                    <div class="payment-method-content">
                        <div class="payment-method-details">
                            <h6><?php echo $payment_details['response'][0]['pl_name']?></h6>
                            <small><?php echo $payment_details['response'][0]['pl_description']?></small>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="payment-amount">
                <div class="amount-label">Total due</div>
                <div class="amount-value"><?php echo number_format($payment_details['response'][0]['pl_amount'],2).$payment_details['response'][0]['pl_currency']?></div>
            </div>

            <form enctype="multipart/form-data" class="payment-form" id="card-form">
                <input type="hidden" name="action" value="pp-invoice-payment-link">
                <input type="hidden" name="pp-paymentid" value="<?php echo $paymentid?>">
                <input type="text" name="website" value="" autocomplete="off" tabindex="-1" aria-hidden="true" style="position:absolute;left:-9999px;top:auto;width:1px;height:1px;opacity:0;">
                <!-- Static Fields -->
                <div class="form-group">
                    <label class="form-label">Customer Details</label>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <input type="text" class="form-control" name="full-name" placeholder="Full Name">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <input type="text" class="form-control" name="email-mobile" placeholder="Email or Mobile">
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Dynamic Fields -->
                <div class="form-group">
                    <div class="row">
                        <?php foreach($payment_details_items['response'] as $row){ ?>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <input type="<?= htmlspecialchars($row['pl_form_type']) ?>" 
                                           class="form-control" 
                                           name="<?= $row['pl_form_type'] === 'file' ? $row['pl_field_name'] . '[]' : $row['pl_field_name'] ?>" 
                                           placeholder="<?= ucwords(str_replace('-', ' ', $row['pl_field_name'])) ?>"
                                           <?= $row['pl_is_require'] === 'yes' ? 'required' : '' ?> <?php if($row['pl_form_type'] == "file"){echo "style='height:auto;'";}?>>
                                </div>
                            </div>
                        <?php } ?>
                    </div>
                </div>
                
                <div id="response"></div>

                <button class="btn-pay active" id="pay-button"><i class="fas fa-lock"></i> Pay <?php echo number_format($payment_details['response'][0]['pl_amount'],2).$payment_details['response'][0]['pl_currency']?></button>
            </form>
        </div>
        
        <div class="payment-footer">
            <div class="secure-payment">
                <i class="fas fa-lock"></i>
                <span>Payments are secure and encrypted with <a href="https://piprapay.com" target="blank" style="color:<?php echo $setting['response'][0]['global_text_color']?>;text-decoration: none;"><strong>PipraPay</strong></a></span>
            </div>
        </div>
    </div>


    <script>
        document.getElementById('card-form').addEventListener('submit', function(e) {
            e.preventDefault();
        
            $("#pay-button").html('<div class="spinner-border spinner-border-sm" role="status"> <span class="visually-hidden">Loading...</span> </div>');
            $("pay-button").prop("disabled", true);
    
            const form = document.getElementById('card-form');
            const formData = new FormData(form);
        
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(async res => {
                const text = await res.text();
                if (!text.trim()) {
                    throw new Error('Empty server response');
                }
                return JSON.parse(text);
            })
            .then(response => {
                console.log(response);
                $("#pay-button").html('<i class="fas fa-lock"></i> Pay <?php echo number_format($payment_details['response'][0]['pl_amount'],2).$payment_details['response'][0]['pl_currency']?>');
                $("#pay-button").prop("disabled", false);

                if (response.status === false || response.status === "false") {
                    $("#response").html('<div class="alert alert-danger" style="margin-top: 20px; margin-bottom: -1px;" role="alert">'+(response.message || 'Something went wrong. Please try again.')+'</div>');
                    return;
                }

                if (!response.pp_url) {
                    $("#response").html('<div class="alert alert-danger" style="margin-top: 20px; margin-bottom: -1px;" role="alert">Payment gateway did not return a redirect URL.</div>');
                    return;
                }

                $("#response").html('');
                location.href = response.pp_url;
            })
            .catch(error => {
                console.error('Payment link checkout error:', error);
                $("#pay-button").html('<i class="fas fa-lock"></i> Pay <?php echo number_format($payment_details['response'][0]['pl_amount'],2).$payment_details['response'][0]['pl_currency']?>');
                $("#pay-button").prop("disabled", false);
                $("#response").html('<div class="alert alert-danger" style="margin-top: 20px; margin-bottom: -1px;" role="alert">Server returned an invalid response. Please try again.</div>');
            });
        });
    </script>
</body>
</html>