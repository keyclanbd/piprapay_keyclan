<?php
    if (!defined('pp_allowed_access')) {
        die('Direct access not allowed');
    }

    $currencySymbolMap = [
        'BDT' => '৳', 'TK' => '৳', 'TKA' => '৳',
        'USD' => '$', 'EUR' => '€', 'GBP' => '£',
        'INR' => '₹', 'JPY' => '¥', 'CNY' => '¥',
        'SAR' => '﷼', 'AED' => 'د.إ', 'PKR' => '₨', 'NPR' => '₨',
    ];
    $pp_currency = $transaction_details['response'][0]['transaction_currency'] ?? '';
    $pp_currency = $currencySymbolMap[strtoupper(trim($pp_currency))] ?? $pp_currency;
    $display_amount = $transaction_details['response'][0]['transaction_amount'] + $transaction_details['response'][0]['transaction_fee'];
    $display_currency = $pp_currency;
    $display_gateway = null;
    foreach (pp_get_gateway_category_tabs($payment_id) as $gateway_tab) {
        foreach (($gateway_tab['gateways']['response'] ?? []) as $gateway_option) {
            if (($gateway_option['is_available'] ?? true) === true) {
                $display_gateway = $gateway_option;
                break 2;
            }
        }
    }
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Secure Checkout - <?php echo $setting['response'][0]['site_name']?></title>
    <link rel="icon" type="image/x-icon" href="<?php if(isset($setting['response'][0]['favicon'])){if($setting['response'][0]['favicon'] == "--"){echo 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8Xw8AAoMBgDTD2qgAAAAASUVORK5CYII=';}else{echo $setting['response'][0]['favicon'];};}else{echo 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8Xw8AAoMBgDTD2qgAAAAASUVORK5CYII=';}?>">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
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
          
          .payment-header{
            margin-top: 15px;
            margin-left: 15px;
            margin-right: 15px;
          }
          .payment-body {
            padding: 15px;
          }
          
          .method-tab {
            font-weight: 500;
            font-size: 13px;
          }
          
            .btn-pay {
                width: 100%;
                padding: 0.9rem;
                background: <?php echo $setting['response'][0]['primary_button_color']?>;
                color: <?php echo $setting['response'][0]['button_text_color']?>;
                border: none;
                border-radius: 8px;
                font-size: 0.9rem;
                font-weight: 600;
                cursor: pointer;
                transition: all 0.2s;
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 0.5rem;
            }
        }
        

        .grid-wrapper {
          display: grid;
          grid-template-columns: repeat(auto-fill, minmax(130px, 1fr));
          gap: 20px;
          margin-bottom: 20px;
          justify-items: left; /* This helps center single items */
        }
        
        .grid-box {
          width: 100%;
          max-width: 170px;
          border: 1px solid #e5eefa;
          border-radius: 7px;
          cursor: pointer;
          overflow: hidden;
          background-color: #fff;
          transition: box-shadow 0.2s ease;
        }
        
        .grid-box:hover {
          box-shadow: 0 4px 10px rgba(0,0,0,0.08);
        }

        .grid-box.unavailable {
          opacity: .48;
          cursor: not-allowed;
          background: #f8fafc;
        }

        .grid-box.unavailable:hover {
          box-shadow: none;
        }
        
        .grid-box img {
          height: 45px;
          max-width: 100%;
          margin-top: 7px;
          display: block;
          margin-left: auto;
          margin-right: auto;
        }
        
        .grid-box-footer {
          font-size: 12px;
          border-top: 1px solid #dddddd;
          margin-top: 7px;
          min-height: 35px;
          display: flex;
          flex-direction: column;
          align-items: center;
          justify-content: center;
          padding: 4px;
          text-align: center;
        }

        .grid-box-note {
          color: #dc2626;
          font-size: 10px;
          line-height: 1.2;
        }

        .grid-box.converted .grid-box-note {
          color: <?php echo $setting['response'][0]['active_tab_color']?>;
        }
        
        @media (max-width: 767px) {
            .grid-wrapper {
              grid-template-columns: repeat(auto-git, minmax(130px, 1fr));
            }
            .grid-box {
              max-width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="payment-container">
        <div class="payment-header">
            <i class="fas fa-times" style=" cursor: pointer; " onclick="location.href='?cancel'"></i>
        </div>
        
        <div class="payment-body">
            <!-- Updated Payment Amount Section -->
            <div class="payment-amount">
                <img src="<?php if(isset($setting['response'][0]['favicon'])){if($setting['response'][0]['favicon'] == "--"){echo 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8Xw8AAoMBgDTD2qgAAAAASUVORK5CYII=';}else{echo $setting['response'][0]['favicon'];};}else{echo 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8Xw8AAoMBgDTD2qgAAAAASUVORK5CYII=';}?>" alt="Merchant Logo" class="merchant-logo">
                <div class="merchant-details">
                    <div class="merchant-name"><?php echo $setting['response'][0]['site_name']?></div>
                    <div class="amount-value"><?php echo number_format((float)($display_gateway['amount'] ?? $display_amount), 2).' '.htmlspecialchars($display_gateway['currency'] ?? $display_currency)?></div>
                    <div class="amount-label">Invoice: <?php echo $transaction_details['response'][0]['pp_id']?></div>
                </div>
                <div class="payment-actions">
                    <button class="global-tab action-btn" data-tab="support-tab">
                        <i class="fas fa-headset"></i>
                    </button>
                    <button class="global-tab action-btn" data-tab="help-tab">
                        <i class="fas fa-question"></i>
                    </button>
                    <button class="global-tab action-btn" data-tab="information-tab">
                        <i class="fas fa-info"></i>
                    </button>
                </div>
            </div>
            
            <div class="payment-methods">
                <div class="method-tabs">
                    <?php
                        $tabs = pp_get_gateway_category_tabs($payment_id);
                    ?>
                    <?php foreach ($tabs as $key => $tab): ?>
                        <?php if (!empty($tab['gateways']['response'])): ?>
                            <div class="global-tab method-tab" data-tab="<?= $key ?>"><?= $tab['title'] ?></div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
                
                <?php foreach ($tabs as $key => $tab): ?>
                    <?php if (!empty($tab['gateways']['response'])): ?>
                    <?php $first_gateway = $tab['gateways']['response'][0]; ?>
                    <div class="method-content" id="<?= $key ?>">
                        <form class="card-form">
                            <div class="container" style="padding: 0px;">
                                <div class="grid-wrapper">
                                    <?php foreach ($tab['gateways']['response'] as $gateway): ?>
                                        <?php
                                            $gatewayAvailable = ($gateway['is_available'] ?? true) === true;
                                            $gatewayConverted = ($gateway['is_converted'] ?? false) === true;
                                            $gatewayReason = $gateway['availability_reason'] ?? '';
                                        ?>
                                        <div class="grid-box<?php echo !$gatewayAvailable ? ' unavailable' : ''; ?><?php echo $gatewayConverted ? ' converted' : ''; ?>" <?php if ($gatewayAvailable): ?>onclick="location.href='?method=<?= htmlspecialchars($gateway['plugin_slug']) ?>'"<?php endif; ?> title="<?= htmlspecialchars($gatewayReason) ?>">
                                            <img src="<?= htmlspecialchars($gateway['plugin_logo']) ?>" alt="<?= htmlspecialchars($gateway['plugin_name']) ?>">
                                            <div class="grid-box-footer">
                                                <span><?= htmlspecialchars($gateway['plugin_name']) ?></span>
                                                <?php if (!$gatewayAvailable || $gatewayConverted): ?>
                                                    <span class="grid-box-note"><?= htmlspecialchars($gatewayConverted ? 'Charged in '.($gateway['currency'] ?? '') : $gatewayReason) ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <button type="button" class="btn-pay" id="pay-button">
                                <i class="fas fa-lock"></i> Pay <?= number_format((float)($display_gateway['amount'] ?? $display_amount), 2) . ' ' . htmlspecialchars($display_gateway['currency'] ?? $display_currency) ?>
                            </button>
                        </form>
                    </div>
                    <?php endif; ?>
                <?php endforeach; ?>

                <div class="method-content" id="support-tab">
                    <div class="netbanking-form rounded">
                        <div class="custom-contact-grid">
                            <?php foreach ($support_links as $key => $support_link): ?>
                                <?php $url = trim($support_link['url']); ?>
                                <?php if ($url !== '' && $url !== '--'): ?>
                                    <a href="<?php echo htmlspecialchars($url); ?>" target="_blank" class="contact-box">
                                        <div class="contact-inner">
                                            <img src="<?php echo htmlspecialchars($support_link['image']); ?>" alt="icon">
                                            <span><?php echo htmlspecialchars($support_link['text']); ?></span>
                                        </div>
                                    </a>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                
                <div class="method-content" id="help-tab">
                    <div class="netbanking-form shadow-sm bg-white rounded">
                        <div class="accordion" id="accordionGroupExample">
                            <?php
                                foreach($faq_list['response'] as $faq){
                                    $rand_faq = rand();
                            ?>
                                    <div class="accordion-item border-0 mb-2" style="border-bottom: 1px solid #dddddd63 !important;">
                                        <div class="accordion-header">
                                            <button class="accordion-button collapsed" 
                                                    type="button" 
                                                    data-bs-toggle="collapse" 
                                                    data-bs-target="#collapse<?php echo $rand_faq?>">
                                                <span class="text-secondary"><?php echo $faq['title'];?></span>
                                            </button>
                                        </div>
                                        <div id="collapse<?php echo $rand_faq?>" class="accordion-collapse collapse" 
                                             data-bs-parent="#accordionGroupExample">
                                            <div class="accordion-body text-muted small">
                                                <?php echo $faq['content'];?>
                                            </div>
                                        </div>
                                    </div>
                            <?php
                                }
                            ?>
                        </div>
                    </div>
                </div>
                
                <div class="method-content" id="information-tab">
                    <div class="netbanking-form shadow-sm bg-white rounded">
                        <ul class="list-unstyled p-4" style="font-size: 15px;margin: 0;">
                            <li class="d-flex justify-content-between">
                                <span class="fw-semibold text-secondary">Name:</span>
                                <span class="fw-semibold text-secondary"><?php echo htmlspecialchars((string) ($transaction_details['response'][0]['c_name'] ?? ''), ENT_QUOTES)?></span>
                            </li>
                            <hr class="my-2 border-secondary opacity-10">
                            <li class="d-flex justify-content-between">
                                <span class="fw-semibold text-secondary">Email or Mobile:</span>
                                <span class="fw-semibold text-secondary"><?php echo htmlspecialchars((string) ($transaction_details['response'][0]['c_email_mobile'] ?? ''), ENT_QUOTES)?></span>
                            </li>
                            <hr class="my-2 border-secondary opacity-10">
                            <li class="d-flex justify-content-between">
                                <span class="fw-semibold text-secondary">Amount:</span>
                                <span class="fw-semibold" style="color:<?php echo $setting['response'][0]['global_text_color']?>"><?php echo number_format($transaction_details['response'][0]['transaction_amount'], 2).' '.$pp_currency?></span>
                            </li>
                            <hr class="my-2 border-secondary opacity-10">
                            <li class="d-flex justify-content-between">
                                <span class="text-secondary">Fee:</span>
                                <span class="text-secondary"><?php echo number_format($transaction_details['response'][0]['transaction_fee'], 2).' '.$pp_currency?></span>
                            </li>
                            <hr class="my-2 border-secondary opacity-10">
                            <li class="d-flex justify-content-between">
                                <span class="fw-semibold text-secondary">Total Payable Amount:</span>
                                <span class="fw-semibold" style="color:<?php echo $setting['response'][0]['global_text_color']?>"><?php echo number_format($transaction_details['response'][0]['transaction_amount']+$transaction_details['response'][0]['transaction_fee'], 2).' '.$pp_currency?></span>
                            </li>
                        </ul>
                    </div>
                </div>
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
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const tabs = document.querySelectorAll('.method-tab');
            const contents = document.querySelectorAll('.method-content');
        
            // Activate the first available tab
            if (tabs.length > 0 && contents.length > 0) {
                tabs[0].classList.add('active');
                const tabId = tabs[0].getAttribute('data-tab');
                document.getElementById(tabId).classList.add('active');
            }
        
            // Tab switching logic
            tabs.forEach(tab => {
                tab.addEventListener('click', function () {
                    tabs.forEach(t => t.classList.remove('active'));
                    contents.forEach(c => c.classList.remove('active'));
        
                    this.classList.add('active');
                    const tabId = this.getAttribute('data-tab');
                    document.getElementById(tabId).classList.add('active');
                });
            });
        });
    </script>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Tab switching
            const tabs = document.querySelectorAll('.global-tab');
            tabs.forEach(tab => {
                tab.addEventListener('click', function() {
                    // Remove active class from all tabs and contents
                    tabs.forEach(t => t.classList.remove('active'));
                    document.querySelectorAll('.method-content').forEach(c => c.classList.remove('active'));
                    
                    // Add active class to clicked tab and corresponding content
                    this.classList.add('active');
                    const tabId = this.getAttribute('data-tab');
                    document.getElementById(tabId).classList.add('active');
                });
            });

        });
    </script>
</body>
</html>