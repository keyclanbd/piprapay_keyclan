<?php
    if (!defined('pp_allowed_access')) {
        die('Direct access not allowed');
    }

    add_action('pp_transaction_ipn', 'send_smtp_mailer_transaction_ipn');
    add_action('pp_transaction_ipn', 'send_smtp_mailer_transaction_admin_ipn');
    add_action('pp_invoice_ipn', 'send_smtp_mailer_invoice_ipn');
            
    function send_smtp_mailer_transaction_admin_ipn($transaction_id) {
        global $conn;
    
        $transaction_details = pp_get_transation($transaction_id);
        $setting = pp_get_settings();
    
        $plugin_slug = 'smtp-mailer';
        $smtp_settings = pp_get_plugin_setting($plugin_slug);
     
        if (empty($smtp_settings)) {
            return; // SMTP settings not found
        }
        
        $admin_details = pp_get_admin(1);
        
        if (filter_var($admin_details['response'][0]['email'], FILTER_VALIDATE_EMAIL)) {
            
        }else{
           return; // Email not valid
        }
    
        $to_email = $admin_details['response'][0]['email'];
        $subject = 'Transaction Notification - ' . $transaction_details['response'][0]['transaction_status'];
        
        // HTML Email Body with Bootstrap-like styling (inline CSS for email clients)
        $body = '
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Transaction Notification</title>
            <style>
                body {
                    font-family: Arial, sans-serif;
                    line-height: 1.6;
                    color: #333;
                    margin: 0;
                    padding: 0;
                }
                .email-container {
                    max-width: 600px;
                    margin: 0 auto;
                    padding: 20px;
                    border: 1px solid #e0e0e0;
                    border-radius: 5px;
                }
                .header {
                    background-color: #f8f9fa;
                    padding: 20px;
                    text-align: center;
                    border-bottom: 1px solid #e0e0e0;
                }
                .content {
                    padding: 20px;
                }
                .transaction-details {
                    background-color: #f8f9fa;
                    border-radius: 5px;
                    padding: 15px;
                    margin-bottom: 20px;
                }
                .detail-row {
                    display: flex;
                    margin-bottom: 10px;
                }
                .detail-label {
                    font-weight: bold;
                    width: 120px;
                }
                .footer {
                    text-align: center;
                    padding: 20px;
                    font-size: 12px;
                    color: #6c757d;
                    border-top: 1px solid #e0e0e0;
                }
                .status-success {
                    color: #28a745;
                    font-weight: bold;
                }
                .status-pending {
                    color: #ffc107;
                    font-weight: bold;
                }
                .status-failed {
                    color: #dc3545;
                    font-weight: bold;
                }
            </style>
        </head>
        <body>
            <div class="email-container">
                <div class="header">
                    <h2>Transaction Notification</h2>
                </div>
                
                <div class="content">
                    <p>Hello ' . htmlspecialchars($transaction_details['response'][0]['c_name']) . ',</p>
                    <p>You have received a new transaction.. Below are the details:</p>
                    
                    <div class="transaction-details">
                        <div class="detail-row">
                            <div class="detail-label">Amount:</div>
                            <div>' . htmlspecialchars($transaction_details['response'][0]['transaction_amount'] . ' ' . $transaction_details['response'][0]['transaction_currency']) . '</div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Fee:</div>
                            <div>' . htmlspecialchars($transaction_details['response'][0]['transaction_fee'] . ' ' . $transaction_details['response'][0]['transaction_currency']) . '</div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Refund:</div>
                            <div>' . htmlspecialchars($transaction_details['response'][0]['transaction_refund_amount'] . ' ' . $transaction_details['response'][0]['transaction_currency']) . '</div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Net:</div>
                            <div>' . htmlspecialchars($transaction_details['response'][0]['transaction_amount']+$transaction_details['response'][0]['transaction_fee']-$transaction_details['response'][0]['transaction_refund_amount'] . ' ' . $transaction_details['response'][0]['transaction_currency']) . '</div>
                        </div>
                        
                        <div class="detail-row">
                            <div class="detail-label">Status:</div>
                            <div class="status-' . strtolower($transaction_details['response'][0]['transaction_status']) . '">' . htmlspecialchars($transaction_details['response'][0]['transaction_status']) . '</div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Date:</div>
                            <div>' . htmlspecialchars($transaction_details['response'][0]['created_at']) . '</div>
                        </div>
                    </div>
                    
                </div>
                
                <div class="footer">
                    <p>&copy; ' . date('Y') . ' ' . htmlspecialchars($setting['response'][0]['site_name'] ?? 'Your Company') . '. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>
        ';
    
        // Load PHPMailer
        if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
            require_once __DIR__ . '/phpmailer/PHPMailer.php';
            require_once __DIR__ . '/phpmailer/SMTP.php';
            require_once __DIR__ . '/phpmailer/Exception.php';
        }
    
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        // Server settings
        $mail->isSMTP();
        $mail->Host       = $smtp_settings['host'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $smtp_settings['username'];
        $mail->Password   = $smtp_settings['password'];
        $mail->SMTPSecure = $smtp_settings['secure']; // 'tls' or 'ssl'
        $mail->Port       = $smtp_settings['port'];
        $mail->Timeout    = 10;
    
        // Sender & Recipient
        $mail->setFrom($smtp_settings['username'], $smtp_settings['from']);
        $mail->addAddress($to_email);
    
        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $body;
        // Add plain text version for email clients that don't support HTML
        $mail->AltBody = "Hello " . $transaction_details['response'][0]['c_name'] . ",\n\n" .
                        "Your transaction has been processed.\n\n" .
                        "Amount: " . $transaction_details['response'][0]['transaction_amount'] . ' ' . $transaction_details['response'][0]['transaction_currency'] . "\n" .
                        "Fee: " . $transaction_details['response'][0]['transaction_fee'] . ' ' . $transaction_details['response'][0]['transaction_currency'] . "\n" .
                        "Refund: " . $transaction_details['response'][0]['transaction_refund_amount'] . ' ' . $transaction_details['response'][0]['transaction_currency'] . "\n" .
                        "Net: " . $transaction_details['response'][0]['transaction_amount']+$transaction_details['response'][0]['transaction_fee']-$transaction_details['response'][0]['transaction_refund_amount'] . ' ' . $transaction_details['response'][0]['transaction_currency'] . "\n" .
                        "Status: " . $transaction_details['response'][0]['transaction_status'] . "\n" .
                        "Date: " . $transaction_details['response'][0]['created_at'] . "\n\n" .
                        "Thank you for using our service.";
    
        $mail->send();
    }
            
    function send_smtp_mailer_transaction_ipn($transaction_id) {
        global $conn;
    
        $transaction_details = pp_get_transation($transaction_id);
        $setting = pp_get_settings();
    
        $plugin_slug = 'smtp-mailer';
        $smtp_settings = pp_get_plugin_setting($plugin_slug);
    
        if (empty($smtp_settings)) {
            return; // SMTP settings not found
        }
        if (filter_var($transaction_details['response'][0]['c_email_mobile'], FILTER_VALIDATE_EMAIL)) {
            
        }else{
           return; // Email not valid
        }
    
        $to_email = $transaction_details['response'][0]['c_email_mobile'];
        $subject = 'Transaction Notification - ' . $transaction_details['response'][0]['transaction_status'];
        
        // HTML Email Body with Bootstrap-like styling (inline CSS for email clients)
        $body = '
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Transaction Notification</title>
            <style>
                body {
                    font-family: Arial, sans-serif;
                    line-height: 1.6;
                    color: #333;
                    margin: 0;
                    padding: 0;
                }
                .email-container {
                    max-width: 600px;
                    margin: 0 auto;
                    padding: 20px;
                    border: 1px solid #e0e0e0;
                    border-radius: 5px;
                }
                .header {
                    background-color: #f8f9fa;
                    padding: 20px;
                    text-align: center;
                    border-bottom: 1px solid #e0e0e0;
                }
                .content {
                    padding: 20px;
                }
                .transaction-details {
                    background-color: #f8f9fa;
                    border-radius: 5px;
                    padding: 15px;
                    margin-bottom: 20px;
                }
                .detail-row {
                    display: flex;
                    margin-bottom: 10px;
                }
                .detail-label {
                    font-weight: bold;
                    width: 120px;
                }
                .footer {
                    text-align: center;
                    padding: 20px;
                    font-size: 12px;
                    color: #6c757d;
                    border-top: 1px solid #e0e0e0;
                }
                .status-success {
                    color: #28a745;
                    font-weight: bold;
                }
                .status-pending {
                    color: #ffc107;
                    font-weight: bold;
                }
                .status-failed {
                    color: #dc3545;
                    font-weight: bold;
                }
            </style>
        </head>
        <body>
            <div class="email-container">
                <div class="header">
                    <h2>Transaction Notification</h2>
                </div>
                
                <div class="content">
                    <p>Hello ' . htmlspecialchars($transaction_details['response'][0]['c_name']) . ',</p>
                    <p>Your transaction has been processed. Below are the details:</p>
                    
                    <div class="transaction-details">
                        <div class="detail-row">
                            <div class="detail-label">Amount:</div>
                            <div>' . htmlspecialchars($transaction_details['response'][0]['transaction_amount'] . ' ' . $transaction_details['response'][0]['transaction_currency']) . '</div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Fee:</div>
                            <div>' . htmlspecialchars($transaction_details['response'][0]['transaction_fee'] . ' ' . $transaction_details['response'][0]['transaction_currency']) . '</div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Refund:</div>
                            <div>' . htmlspecialchars($transaction_details['response'][0]['transaction_refund_amount'] . ' ' . $transaction_details['response'][0]['transaction_currency']) . '</div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Net:</div>
                            <div>' . htmlspecialchars($transaction_details['response'][0]['transaction_amount']+$transaction_details['response'][0]['transaction_fee']-$transaction_details['response'][0]['transaction_refund_amount'] . ' ' . $transaction_details['response'][0]['transaction_currency']) . '</div>
                        </div>
                        
                        <div class="detail-row">
                            <div class="detail-label">Status:</div>
                            <div class="status-' . strtolower($transaction_details['response'][0]['transaction_status']) . '">' . htmlspecialchars($transaction_details['response'][0]['transaction_status']) . '</div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Date:</div>
                            <div>' . htmlspecialchars($transaction_details['response'][0]['created_at']) . '</div>
                        </div>
                    </div>
                    
                    <p>If you have any questions about this transaction, please contact our support team.</p>
                    <p>Thank you for using our service!</p>
                </div>
                
                <div class="footer">
                    <p>&copy; ' . date('Y') . ' ' . htmlspecialchars($setting['response'][0]['site_name'] ?? 'Your Company') . '. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>
        ';
    
        // Load PHPMailer
        if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
            require_once __DIR__ . '/phpmailer/PHPMailer.php';
            require_once __DIR__ . '/phpmailer/SMTP.php';
            require_once __DIR__ . '/phpmailer/Exception.php';
        }
    
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        // Server settings
        $mail->isSMTP();
        $mail->Host       = $smtp_settings['host'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $smtp_settings['username'];
        $mail->Password   = $smtp_settings['password'];
        $mail->SMTPSecure = $smtp_settings['secure']; // 'tls' or 'ssl'
        $mail->Port       = $smtp_settings['port'];
        $mail->Timeout    = 10;
    
        // Sender & Recipient
        $mail->setFrom($smtp_settings['username'], $smtp_settings['from']);
        $mail->addAddress($to_email);
    
        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $body;
        // Add plain text version for email clients that don't support HTML
        $mail->AltBody = "Hello " . $transaction_details['response'][0]['c_name'] . ",\n\n" .
                        "Your transaction has been processed.\n\n" .
                        "Amount: " . $transaction_details['response'][0]['transaction_amount'] . ' ' . $transaction_details['response'][0]['transaction_currency'] . "\n" .
                        "Fee: " . $transaction_details['response'][0]['transaction_fee'] . ' ' . $transaction_details['response'][0]['transaction_currency'] . "\n" .
                        "Refund: " . $transaction_details['response'][0]['transaction_refund_amount'] . ' ' . $transaction_details['response'][0]['transaction_currency'] . "\n" .
                        "Net: " . $transaction_details['response'][0]['transaction_amount']+$transaction_details['response'][0]['transaction_fee']-$transaction_details['response'][0]['transaction_refund_amount'] . ' ' . $transaction_details['response'][0]['transaction_currency'] . "\n" .
                        "Status: " . $transaction_details['response'][0]['transaction_status'] . "\n" .
                        "Date: " . $transaction_details['response'][0]['created_at'] . "\n\n" .
                        "Thank you for using our service.";
    
        $mail->send();
    }
    
    function send_smtp_mailer_invoice_ipn($invoice_id) {
        global $conn;
    
        // Get invoice data (assuming these functions exist)
        $invoice_details = pp_get_invoice($invoice_id);
        $invoice_details_items = pp_get_invoice_items($invoice_id);
        $setting = pp_get_settings();
        
        // SMTP settings
        $plugin_slug = 'smtp-mailer';
        $smtp_settings = pp_get_plugin_setting($plugin_slug);
        if (empty($smtp_settings)) {
            return; // SMTP settings not found
        }
        if (filter_var($invoice_details['response'][0]['c_email_mobile'], FILTER_VALIDATE_EMAIL)) {
            
        }else{
           return; // Email not valid
        }
        
        // Determine status seal
        $status_class = strtolower($invoice_details['response'][0]['i_status']);
        $status_icon = 'fa-question-circle';
        $to_email = $invoice_details['response'][0]['c_email_mobile'];
        
        switch($invoice_details['response'][0]['i_status']) {
            case 'unpaid':
                $status_icon = 'fa-exclamation-circle';
                $subject = "Action Required: Invoice Payment Due";
                break;
            case 'paid':
                $status_icon = 'fa-check-circle';
                $subject = "We've Received Your Payment - Thank You";
                break;
            case 'refunded':
                $status_icon = 'fa-info-circle';
                $subject = "Your Refund Has Been Processed";
                break;
            case 'canceled':
                $status_icon = 'fa-exclamation-triangle';
                $subject = "Invoice Cancellation Notice";
                break;
        }
        
        // Calculate invoice totals
        $subtotal = 0;
        $total_discount = 0;
        $total_vat = 0;
        $items_html = '';
        
        foreach ($invoice_details_items['response'] as $items) {
            $item_subtotal = $items['amount'] * $items['quantity'];
            $item_discount = min($items['discount'], $item_subtotal);
            $item_amount_after_discount = $item_subtotal - $item_discount;
            $item_vat = $item_amount_after_discount * ($items['vat'] / 100);
    
            $subtotal += $item_subtotal;
            $total_discount += $item_discount;
            $total_vat += $item_vat;
            
            $items_html .= '
            <tr>
                <td style="padding: 8px; border-bottom: 1px solid #eee;">'.htmlspecialchars($items['description']).'</td>
                <td style="padding: 8px; border-bottom: 1px solid #eee; text-align: center;">'.(int)$items['quantity'].'</td>
                <td style="padding: 8px; border-bottom: 1px solid #eee; text-align: right;">'.number_format($items['amount'], 2).$invoice_details['response'][0]['i_currency'].'</td>
                <td style="padding: 8px; border-bottom: 1px solid #eee; text-align: right;">'.number_format($item_subtotal, 2).$invoice_details['response'][0]['i_currency'].'</td>
            </tr>';
        }
        
        $shipping_cost = isset($invoice_details['response'][0]['i_amount_shipping']) ? floatval($invoice_details['response'][0]['i_amount_shipping']) : 0;
        $total_amount = $subtotal - $total_discount + $total_vat + $shipping_cost;
        $currency = $invoice_details['response'][0]['i_currency'];
        
        // HTML Email Body
        $body = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>'.$subject.'</title>
            <style>
                body {
                    font-family: Arial, sans-serif;
                    line-height: 1.6;
                    color: #333;
                    margin: 0;
                    padding: 0;
                    background-color: #f5f5f5;
                }
                .email-container {
                    max-width: 700px;
                    margin: 20px auto;
                    background: #fff;
                    border-radius: 5px;
                    overflow: hidden;
                    box-shadow: 0 0 10px rgba(0,0,0,0.1);
                }
                .header {
                    background-color: #f8f9fa;
                    padding: 20px;
                    text-align: center;
                    border-bottom: 1px solid #e0e0e0;
                }
                .invoice-title {
                    font-size: 24px;
                    font-weight: bold;
                    margin-bottom: 10px;
                }
                .invoice-number {
                    color: #666;
                    margin-bottom: 20px;
                }
                .content {
                    padding: 20px;
                }
                .meta-container {
                    display: flex;
                    flex-wrap: wrap;
                    margin-bottom: 20px;
                }
                .meta-section {
                    flex: 1;
                    min-width: 200px;
                    margin-bottom: 15px;
                }
                .meta-label {
                    font-weight: bold;
                    margin-bottom: 5px;
                    color: #555;
                }
                .meta-value {
                    margin-bottom: 5px;
                }
                table {
                    width: 100%;
                    border-collapse: collapse;
                    margin: 20px 0;
                }
                th {
                    background-color: #f8f9fa;
                    padding: 10px;
                    text-align: left;
                    border-bottom: 2px solid #ddd;
                }
                td {
                    padding: 10px;
                    border-bottom: 1px solid #eee;
                }
                .text-right {
                    text-align: right;
                }
                .text-center {
                    text-align: center;
                }
                .totals-table {
                    width: 50%;
                    margin-left: auto;
                }
                .totals-table td {
                    padding: 8px;
                }
                .totals-table tr:last-child td {
                    font-weight: bold;
                    border-top: 2px solid #ddd;
                }
                .footer {
                    padding: 20px;
                    text-align: center;
                    font-size: 12px;
                    color: #777;
                    border-top: 1px solid #e0e0e0;
                    background-color: #f8f9fa;
                }
                .status-seal {
                    position: absolute;
                    top: 20px;
                    right: 20px;
                    width: 80px;
                    height: 80px;
                    border-radius: 50%;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    color: white;
                    font-weight: bold;
                    transform: rotate(15deg);
                    opacity: 0.9;
                }
                .status-seal-content {
                    display: flex;
                    align-items: center;
                    justify-content: center;
                }
                .status-seal i {
                    font-size: 24px;
                    margin-bottom: 5px;
                }
                .paid {
                    background-color: #28a745;
                }
                .unpaid {
                    background-color: #dc3545;
                }
                .pending {
                    background-color: #ffc107;
                }
                .btn {
                    display: inline-block;
                    padding: 10px 20px;
                    margin: 10px 5px;
                    border-radius: 4px;
                    text-decoration: none;
                    font-weight: bold;
                    color: white !important;
                }
                .btn-pay {
                    background-color: #28a745;
                }
                .btn-print {
                    background-color: #6c757d;
                }
                .notes {
                    padding: 15px;
                    background-color: #f8f9fa;
                    border-radius: 4px;
                    margin-top: 20px;
                }
                @media only screen and (max-width: 600px) {
                    .meta-container {
                        flex-direction: column;
                    }
                    .meta-section {
                        width: 100%;
                    }
                    .totals-table {
                        width: 100%;
                    }
                    .status-seal {
                        width: 60px;
                        height: 60px;
                        font-size: 10px;
                    }
                    .status-seal i {
                        font-size: 18px;
                    }
                }
            </style>
        </head>
        <body>
            <div class="email-container">
                <div class="header">
                    <div class="invoice-title">INVOICE</div>
                    <div class="invoice-number">#'.$invoice_details['response'][0]['i_id'].'</div>
                </div>
                
                <div class="content">
                    <div class="meta-container">
                        <div class="meta-section">
                            <div class="meta-label">From</div>
                            <div class="meta-value">'.$setting['response'][0]['site_name'].'</div>
                            <div class="meta-value">'.$setting['response'][0]['street_address'].'</div>
                            <div class="meta-value">'.$setting['response'][0]['city_town'].' '.$setting['response'][0]['postal_zip_code'].'</div>
                            <div class="meta-value">'.$setting['response'][0]['country'].'</div>
                        </div>
                        
                        <div class="meta-section">
                            <div class="meta-label">Bill To</div>
                            <div class="meta-value">'.$invoice_details['response'][0]['c_name'].'</div>
                            <div class="meta-value">'.$invoice_details['response'][0]['c_email_mobile'].'</div>
                        </div>
                        
                        <div class="meta-section">
                            <div class="meta-label">Invoice Date</div>
                            <div class="meta-value">'.convertToReadableDate($invoice_details['response'][0]['created_at']).'</div>
                            
                            <div class="meta-label">Due Date</div>
                            <div class="meta-value">'.convertToReadableDate($invoice_details['response'][0]['i_due_date']).'</div>
                            
                            <div class="meta-label">Status</div>
                            <div class="meta-value" style="text-transform: capitalize; font-weight: bold; color: '.($status_class == 'paid' ? '#28a745' : ($status_class == 'unpaid' ? '#dc3545' : '#ffc107')).'">
                                '.ucfirst($invoice_details['response'][0]['i_status']).'
                            </div>
                        </div>
                    </div>
                    
                    <table>
                        <thead>
                            <tr>
                                <th>Description</th>
                                <th class="text-center">Qty</th>
                                <th class="text-right">Unit Price</th>
                                <th class="text-right">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            '.$items_html.'
                        </tbody>
                    </table>
                    
                    <table class="totals-table">
                        <tr>
                            <td>Subtotal</td>
                            <td class="text-right">'.number_format($subtotal, 2).$currency.'</td>
                        </tr>';
        
        if ($shipping_cost > 0) {
            $body .= '
                        <tr>
                            <td>Shipping</td>
                            <td class="text-right">'.number_format($shipping_cost, 2).$currency.'</td>
                        </tr>';
        }
        
        if ($total_vat > 0) {
            $body .= '
                        <tr>
                            <td>VAT</td>
                            <td class="text-right">'.number_format($total_vat, 2).$currency.'</td>
                        </tr>';
        }
        
        if ($total_discount > 0) {
            $body .= '
                        <tr>
                            <td>Discount</td>
                            <td class="text-right">-'.number_format($total_discount, 2).$currency.'</td>
                        </tr>';
        }
        
        $body .= '
                        <tr>
                            <td><strong>Total Amount</strong></td>
                            <td class="text-right"><strong>'.number_format(round($total_amount, 2), 2).$currency.'</strong></td>
                        </tr>
                    </table>';
        
        if(!empty($invoice_details['response'][0]['i_note']) && $invoice_details['response'][0]['i_note'] != "--") {
            $body .= '
                    <div class="notes">
                        <strong>Notes:</strong> '.$invoice_details['response'][0]['i_note'].'
                    </div>';
        }
        
        if($invoice_details['response'][0]['i_status'] == "unpaid") {
            $body .= '
                    <div style="text-align: center; margin-top: 30px;">
                        <a href="'.get_invoice_link($invoice_id).'" class="btn btn-pay">Pay Now</a>
                    </div>';
        }
        
        $body .= '
                </div>
                
                <div class="footer">
                    <p>If you have any questions about this invoice, please contact our support team.</p>
                    <p>&copy; '.date('Y').' '.$setting['response'][0]['site_name'].'. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>';
        
        // Plain text version for email clients that don't support HTML
        $plain_text = "INVOICE #".$invoice_details['response'][0]['i_id']."\n\n";
        $plain_text .= "Status: ".ucfirst($invoice_details['response'][0]['i_status'])."\n";
        $plain_text .= "Invoice Date: ".convertToReadableDate($invoice_details['response'][0]['created_at'])."\n";
        $plain_text .= "Due Date: ".convertToReadableDate($invoice_details['response'][0]['i_due_date'])."\n\n";
        $plain_text .= "Bill To: ".$invoice_details['response'][0]['c_name']."\n";
        $plain_text .= "Email: ".$invoice_details['response'][0]['c_email_mobile']."\n\n";
        $plain_text .= "ITEMS:\n";
        
        foreach ($invoice_details_items['response'] as $items) {
            $item_subtotal = $items['amount'] * $items['quantity'];
            $plain_text .= "- ".$items['description']." (Qty: ".$items['quantity'].") @ ".number_format($items['amount'], 2).$currency." = ".number_format($item_subtotal, 2).$currency."\n";
        }
        
        $plain_text .= "\nSUBTOTAL: ".number_format($subtotal, 2).$currency."\n";
        if ($shipping_cost > 0) $plain_text .= "SHIPPING: ".number_format($shipping_cost, 2).$currency."\n";
        if ($total_vat > 0) $plain_text .= "VAT: ".number_format($total_vat, 2).$currency."\n";
        if ($total_discount > 0) $plain_text .= "DISCOUNT: -".number_format($total_discount, 2).$currency."\n";
        $plain_text .= "TOTAL AMOUNT: ".number_format(round($total_amount, 2), 2).$currency."\n\n";
        
        if(!empty($invoice_details['response'][0]['i_note']) && $invoice_details['response'][0]['i_note'] != "--") {
            $plain_text .= "NOTES: ".$invoice_details['response'][0]['i_note']."\n\n";
        }
        
        if($invoice_details['response'][0]['i_status'] == "unpaid") {
            $plain_text .= "PAY NOW: ".get_invoice_link($invoice_id)."\n\n";
        }
        
        $plain_text .= "Thank you for your business!\n";
        $plain_text .= $setting['response'][0]['site_name']."\n";
        
        // Load PHPMailer
        if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
            require_once __DIR__ . '/phpmailer/PHPMailer.php';
            require_once __DIR__ . '/phpmailer/SMTP.php';
            require_once __DIR__ . '/phpmailer/Exception.php';
        }
        
        try {
            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
            
            // Server settings
            $mail->isSMTP();
            $mail->Host       = $smtp_settings['host'];
            $mail->SMTPAuth   = true;
            $mail->Username   = $smtp_settings['username'];
            $mail->Password   = $smtp_settings['password'];
            $mail->SMTPSecure = $smtp_settings['secure']; // 'tls' or 'ssl'
            $mail->Port       = $smtp_settings['port'];
        $mail->Timeout    = 10;
            
            // Recipients
            $mail->setFrom($smtp_settings['username'], $setting['response'][0]['site_name']);
            $mail->addAddress($to_email, $invoice_details['response'][0]['c_name']);
            
            // Attach PDF if available
            
            // Content
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $body;
            $mail->AltBody = $plain_text;
            
            $mail->send();
            return true;
        } catch (Exception $e) {
            error_log("Invoice email could not be sent. Error: {$mail->ErrorInfo}");
            return false;
        }
        
    }
?>