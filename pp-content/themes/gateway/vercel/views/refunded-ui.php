<?php
    if (!defined('pp_allowed_access')) {
        die('Direct access not allowed');
    }
    
    $theme_slug = 'vercel';
    $settings = pp_get_theme_setting($theme_slug);
    $currencySymbolMap = [
        'BDT' => '৳', 'TK' => '৳', 'TKA' => '৳',
        'USD' => '$', 'EUR' => '€', 'GBP' => '£',
        'INR' => '₹', 'JPY' => '¥', 'CNY' => '¥',
        'SAR' => '﷼', 'AED' => 'د.إ', 'PKR' => '₨', 'NPR' => '₨',
    ];
    $pp_currency = $transaction_details['response'][0]['transaction_currency'] ?? '';
    $pp_currency = $currencySymbolMap[strtoupper(trim($pp_currency))] ?? $pp_currency;
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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --stripe-blue: #f5ca99;
            --stripe-blue-dark: #f5ca99;
            --stripe-text: #1a1a1a;
            --stripe-light-text: #6b7c93;
            --stripe-border: #e0e6ed;
            --stripe-light-bg: #f6f9fc;
            --stripe-success: #24b47e;
            --stripe-error: #ff5252;
            --stripe-step-active: #635bff;
            --stripe-step-completed: #24b47e;
            --stripe-step-pending: #e0e6ed;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            color: var(--stripe-text);
            background-color: var(--stripe-light-bg);
            line-height: 1.5;
            -webkit-font-smoothing: antialiased;
        }
        
        .payment-container {
            max-width: 600px;
            margin: 2rem auto;
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
            overflow: hidden;
            border: 1px solid var(--stripe-border);
        }
        
        /* Success page styles */
        .success-page {
            text-align: center;
            padding: 40px 24px;
        }
        
        .success-animation {
            position: relative;
            width: 100%;
            height: 200px;
            margin-bottom: 30px;
            overflow: hidden;
        }
        
        .printer {
            width: 220px;
            height: 120px;
            background: #f0f0f0;
            border-radius: 8px;
            margin: 0 auto;
            position: relative;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        
        .printer-top {
            width: 100%;
            height: 20px;
            background: #e0e0e0;
            border-radius: 8px 8px 0 0;
        }
        
        .printer-paper-out {
            position: absolute;
            top: 30px;
            left: 50%;
            transform: translateX(-50%);
            width: 80%;
            height: 100px;
            background: white;
            border: 1px dashed var(--stripe-border);
            overflow: hidden;
        }
        
        .receipt {
            width: 100%;
            background: white;
            position: absolute;
            bottom: 0;
            left: 0;
            padding: 12px;
            box-sizing: border-box;
            text-align: left;
            font-size: 12px;
            transform: translateY(100%);
            animation: printReceipt 2s ease-in-out forwards;
            animation-delay: 0.5s;
            box-shadow: 0 -2px 4px rgba(0, 0, 0, 0.05);
        }
        
        @keyframes printReceipt {
            0% { transform: translateY(100%); }
            100% { transform: translateY(0); }
        }
        
        .receipt-header {
            text-align: center;
            margin-bottom: 8px;
            padding-bottom: 8px;
            border-bottom: 1px dashed var(--stripe-border);
        }
        
        .receipt-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 4px;
        }
        
        .receipt-label {
            font-weight: 500;
        }
        
        .receipt-value {
            font-weight: 600;
        }
        
        .receipt-total {
            margin-top: 8px;
            padding-top: 8px;
            border-top: 1px dashed var(--stripe-border);
            font-weight: 700;
        }
        
        .success-icon {
            width: 60px;
            height: 60px;
            background: var(--stripe-blue);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            color: #000000;
            font-size: 24px;
            animation: bounceIn 0.5s ease-in-out;
        }
        
        @keyframes bounceIn {
            0% { transform: scale(0.5); opacity: 0; }
            70% { transform: scale(1.1); }
            100% { transform: scale(1); opacity: 1; }
        }
        
        .success-title {
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 12px;
            color: #000000;
        }
        
        .success-message {
            color: var(--stripe-light-text);
            margin-bottom: 24px;
            font-size: 15px;
        }
        
        .success-details {
            background: var(--stripe-light-bg);
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 24px;
            text-align: left;
        }
        
        .detail-row {
            display: flex;
            margin-bottom: 12px;
        }
        
        .detail-label {
            font-weight: 500;
            min-width: 120px;
            color: var(--stripe-light-text);
            font-size: 14px;
        }
        
        .detail-value {
            font-weight: 500;
            flex: 1;
        }
        
        .btn-done {
            background: var(--stripe-blue);
            color: #000000;
            border: none;
            width: 100%;
            padding: 14px;
            font-size: 15px;
            font-weight: 500;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s ease;
            margin-top: 16px;
        }
        
        .btn-done:hover {
            background: var(--stripe-blue-dark);
        }
        
        .btn-print {
            background: white;
            color: var(--stripe-blue);
            border: 1px solid var(--stripe-blue);
            width: 100%;
            padding: 14px;
            font-size: 15px;
            font-weight: 500;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s ease;
            margin-top: 12px;
        }
        
        
        .btn-retry {
            background: var(--stripe-blue);
            color: white;
            border: none;
            width: 100%;
            padding: 14px;
            font-size: 15px;
            font-weight: 500;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s ease;
            margin-top: 16px;
        }
        
        .btn-retry:hover {
            background: var(--stripe-blue-dark);
        }
        
        .btn-cancel {
            background: white;
            color: var(--stripe-error);
            border: 1px solid var(--stripe-error);
            width: 100%;
            padding: 14px;
            font-size: 15px;
            font-weight: 500;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s ease;
            margin-top: 12px;
        }
        
        .btn-cancel:hover {
            background: rgba(255, 82, 82, 0.05);
        }
    </style>
</head>
<body>
    <!-- Success Page (shown after successful payment) -->
    <div class="payment-container" id="success-page" style="display: none;">
        <div class="success-page">
            <div class="success-animation">
                <div class="printer">
                    <div class="printer-top"></div>
                    <div class="printer-paper-out">
                        <div class="receipt">
                            <div class="receipt-header">
                                <strong>PAYMENT RECEIPT</strong><br>
                                <small><?php echo $setting['response'][0]['site_name']?></small>
                            </div>
                            <div class="receipt-row">
                                <span class="receipt-label">Method:</span>
                                <span class="receipt-value"><?php echo $transaction_details['response'][0]['payment_method']?></span>
                            </div>
                            <div class="receipt-row">
                                <span class="receipt-label">Amount:</span>
                                <span class="receipt-value"><?php echo number_format($transaction_details['response'][0]['transaction_amount']+$transaction_details['response'][0]['transaction_fee'], 2).' '.$pp_currency?></span>
                            </div>
                            <div class="receipt-row receipt-total">
                                <span class="receipt-label">Status:</span>
                                <span class="receipt-value" style="color: #000000;">Refunded</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="success-icon">
                <i class="fa fa-exclamation-triangle"></i>
            </div>
            <h1 class="success-title">Payment Pending!</h1>
            <p class="success-message">Your payment is currently pending..</p>
            
            <?php
                if(isset($settings['auto_redirect']) && $settings['auto_redirect'] == "Enable"){
            ?>
                   <p class="countdown-message">Redirecting in <span id="countdown">4</span> seconds...</p>
            <?php
                }
            ?>
            
            <div class="success-details">
                <div class="detail-row">
                    <div class="detail-label">Payment Method</div>
                    <div class="detail-value"><?php echo $transaction_details['response'][0]['payment_method']?></div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Amount Paid</div>
                    <div class="detail-value"><?php echo number_format($transaction_details['response'][0]['transaction_amount']+$transaction_details['response'][0]['transaction_fee'], 2).' '.$pp_currency?></div>
                </div>
            </div>
            
            <a href="<?php echo $transaction_details['response'][0]['transaction_redirect_url']?>">
                <button class="btn-done" id="done-button">
                    <i class="fa fa-exclamation-triangle"></i> Back to website
                </button>
            </a>
            
            <button class="btn-print" onclick="window.print()">
                <i class="fas fa-print"></i> Print Receipt
            </button>
        </div>
    </div>
    
    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <?php
        if(isset($settings['auto_redirect']) && $settings['auto_redirect'] == "Enable"){
    ?>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    // Show the success page (in case it was hidden)
                    document.getElementById('success-page').style.display = 'block';
                    
                    // Countdown functionality
                    let countdown = 4;
                    const countdownElement = document.getElementById('countdown');
                    
                    // Update countdown every second
                    const countdownInterval = setInterval(function() {
                        countdown--;
                        countdownElement.textContent = countdown;
                        
                        if (countdown <= 0) {
                            clearInterval(countdownInterval);
                            document.getElementById('done-button').click();
                        }
                    }, 1000);
                });
            </script>
    <?php
        }
    ?>
    
    <script>
        // Function to format date for display
        function formatDate(date) {
            const options = { 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric', 
                hour: '2-digit', 
                minute: '2-digit',
                hour12: true
            };
            return date.toLocaleDateString('en-US', options);
        }
        
        // Function to format date for receipt
        function formatReceiptDate(date) {
            const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
            const month = months[date.getMonth()];
            const day = date.getDate();
            const year = date.getFullYear();
            let hours = date.getHours();
            const minutes = date.getMinutes().toString().padStart(2, '0');
            const ampm = hours >= 12 ? 'PM' : 'AM';
            hours = hours % 12;
            hours = hours ? hours : 12; // the hour '0' should be '12'
            
            return `${month} ${day}, ${year} ${hours}:${minutes}`;
        }
        
        // Function to show success page
        function showSuccessPage() {
            document.getElementById('success-page').style.display = 'block';
        }
        showSuccessPage();
    </script>
</body>
</html>