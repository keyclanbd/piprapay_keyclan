<?php
    if (!defined('pp_allowed_access')) {
        die('Direct access not allowed');
    }

    $invoice_details = pp_get_invoice($invoice_id);
    $invoice_details_items = pp_get_invoice_items($invoice_id);
    $setting = pp_get_settings();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice - <?php echo $setting['response'][0]['site_name']?></title>
    <link rel="icon" type="image/x-icon" href="<?php if(isset($setting['response'][0]['favicon'])){if($setting['response'][0]['favicon'] == "--"){echo 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8Xw8AAoMBgDTD2qgAAAAASUVORK5CYII=';}else{echo $setting['response'][0]['favicon'];};}else{echo 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8Xw8AAoMBgDTD2qgAAAAASUVORK5CYII=';}?>">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #635bff;
            --primary-dark: #4a42d4;
            --success-color: #24b47e;
            --error-color: #ff5252;
            --warning-color: #ff9500;
            --text-color: #1a1a1a;
            --light-text: #6b7c93;
            --border-color: #e0e6ed;
            --light-bg: #f6f9fc;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            color: var(--text-color);
            background-color: #f8f9fa;
            line-height: 1.5;
            -webkit-font-smoothing: antialiased;
        }
        
        .invoice-container {
            max-width: 800px;
            margin: 2rem auto;
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.02), 0 1px 3px rgba(0, 0, 0, 0.05);
            overflow: hidden;
            border: 1px solid var(--border-color);
            position: relative;
        }
        
        /* Status seal - positioned absolutely */
        .status-seal {
            position: absolute;
            top: 20px;
            right: 20px;
            width: 120px;
            height: 120px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transform: rotate(15deg);
            opacity: 0.9;
            z-index: 1;
        }
        
        .status-seal.paid {
            background-color: rgba(36, 180, 126, 0.1);
            border: 4px solid var(--success-color);
            color: var(--success-color);
        }
        
        .status-seal.unpaid {
            background-color: rgba(255, 82, 82, 0.1);
            border: 4px solid var(--error-color);
            color: var(--error-color);
        }
        
        .status-seal.pending {
            background-color: rgba(255, 149, 0, 0.1);
            border: 4px solid var(--warning-color);
            color: var(--warning-color);
        }
        
        .status-seal-content {
            text-align: center;
            font-weight: 700;
            font-size: 16px;
            text-transform: uppercase;
        }
        
        .invoice-header {
            padding: 30px;
            border-bottom: 1px solid var(--border-color);
            background: <?php echo hexToRgba($setting['response'][0]['primary_button_color'], 0.2)?>;
        }
        
        .invoice-title {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 5px;
            color: var(--primary-color);
        }
        
        .invoice-number {
            font-size: 16px;
            color: var(--light-text);
        }
        
        .invoice-meta {
            display: flex;
            justify-content: space-between;
            margin-top: 30px;
        }
        
        .invoice-from, .invoice-to {
            flex: 1;
        }
        
        .meta-label {
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: var(--light-text);
            margin-bottom: 8px;
        }
        
        .meta-value {
            font-weight: 500;
            margin-bottom: 5px;
        }
        
        .invoice-dates {
            margin-top: 30px;
            display: flex;
            gap: 30px;
        }
        
        .date-item {
            flex: 1;
        }
        
        .invoice-body {
            padding: 30px;
        }
        
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        
        .items-table th {
            text-align: left;
            padding: 12px 15px;
            background: var(--light-bg);
            font-weight: 600;
            font-size: 14px;
            color: var(--light-text);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .items-table td {
            padding: 15px;
            border-bottom: 1px solid var(--border-color);
        }
        
        .items-table tr:last-child td {
            border-bottom: none;
        }
        
        .item-description {
            font-weight: 500;
        }
        
        .item-notes {
            font-size: 13px;
            color: var(--light-text);
            margin-top: 5px;
        }
        
        .text-right {
            text-align: right;
        }
        
        .text-center {
            text-align: center;
        }
        
        .totals-table {
            width: 300px;
            margin-left: auto;
            border-collapse: collapse;
        }
        
        .totals-table td {
            padding: 10px 15px;
            border-bottom: 1px solid var(--border-color);
        }
        
        .totals-table tr:last-child td {
            border-bottom: none;
            font-weight: 600;
            font-size: 18px;
        }
        
        .invoice-footer {
            padding: 30px;
            border-top: 1px solid var(--border-color);
            background: var(--light-bg);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .payment-actions {
            display: flex;
            gap: 15px;
        }
        
        .btn-pay {
            background: <?php echo $setting['response'][0]['primary_button_color']?>;
            color: <?php echo $setting['response'][0]['button_text_color']?>;
            border: none;
            padding: 12px 24px;
            font-size: 15px;
            font-weight: 500;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-pay:hover {
            background: <?php echo $setting['response'][0]['button_hover_color']?>;
            color: <?php echo $setting['response'][0]['button_hover_text_color']?>;
            transform: translateY(-1px);
        }
        
        .btn-pay:active {
            transform: translateY(0);
        }
        
        .btn-print {
            background: white;
            color: var(--text-color);
            border: 1px solid var(--border-color);
            padding: 12px 24px;
            font-size: 15px;
            font-weight: 500;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-print:hover {
            background: var(--light-bg);
        }
        
        .payment-methods {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }
        
        .payment-method-icon {
            width: 40px;
            height: 25px;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }
        
        .payment-method-icon img {
            width: 30px;
            height: auto;
        }
        
        .notes {
            font-size: 14px;
            color: var(--light-text);
            max-width: 400px;
        }
        
        .notes strong {
            color: var(--text-color);
        }
        
        /* Watermark for overdue invoices */
        .watermark {
            position: absolute;
            opacity: 0.1;
            font-size: 120px;
            font-weight: 700;
            color: var(--error-color);
            transform: rotate(-30deg);
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-30deg);
            z-index: 0;
            pointer-events: none;
            user-select: none;
        }
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            .invoice-meta {
                flex-direction: column;
                gap: 20px;
            }
            
            .invoice-dates {
                flex-direction: column;
                gap: 15px;
            }
            
            .totals-table {
                width: 100%;
            }
            
            .invoice-footer {
                padding: 20px;
                flex-direction: column;
                gap: 20px;
                align-items: flex-start;
            }
            
            .status-seal {
                width: 80px;
                height: 80px;
                top: 10px;
                right: 10px;
            }
            
            .status-seal-content {
                font-size: 12px;
            }
            
            .invoice-body {
                padding: 20px;
                white-space: nowrap;
            }
            .payment-actions{
                width: 100%;
                justify-content: space-between;
            }
        }
    </style>
</head>
<body>
    <div class="invoice-container" id="invoice">
        <!-- Status Seal - Change class to "paid", "unpaid", or "pending" -->
        <?php
            if($invoice_details['response'][0]['i_status'] == "unpaid"){
        ?>
                <div class="status-seal unpaid">
                    <div class="status-seal-content">
                        <i class="fas fa-exclamation-circle"></i><br>
                        Unpaid
                    </div>
                </div>
        <?php
            }else{
                if($invoice_details['response'][0]['i_status'] == "paid"){
        ?>
                    <div class="status-seal paid">
                        <div class="status-seal-content">
                            <i class="fas fa-check-circle"></i><br>
                            Paid
                        </div>
                    </div>
        <?php
                }else{
                    if($invoice_details['response'][0]['i_status'] == "refunded"){
        ?>
                        <div class="status-seal pending">
                            <div class="status-seal-content">
                                <i class="fa fa-info-circle"></i><br>
                                Refunded
                            </div>
                        </div>
        <?php
                    }else{
                        if($invoice_details['response'][0]['i_status'] == "canceled"){
        ?>
                            <div class="status-seal unpaid">
                                <div class="status-seal-content">
                                    <i class="fa fa-exclamation-triangle"></i><br>
                                    Canceled
                                </div>
                            </div>
        <?php
                        }
                    }
                }
            }
        ?>
        
        <!-- For overdue invoices, uncomment this: -->
        <!-- <div class="watermark">OVERDUE</div> -->
        
        <div class="invoice-header">
            <div class="invoice-title">
                <img src="<?php if(isset($setting['response'][0]['logo'])){if($setting['response'][0]['logo'] == "--"){echo 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8Xw8AAoMBgDTD2qgAAAAASUVORK5CYII=';}else{echo $setting['response'][0]['logo'];};}else{echo 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8Xw8AAoMBgDTD2qgAAAAASUVORK5CYII=';}?>" style=" height: 38px; ">
            </div>
            
            <div class="invoice-meta">
                <div class="invoice-from">
                    <div class="meta-label">From</div>
                    <div class="meta-value"><?php echo $setting['response'][0]['site_name']?></div>
                    <div class="meta-value"><?php echo $setting['response'][0]['street_address']?></div>
                    <div class="meta-value"><?php echo $setting['response'][0]['city_town'].' '.$setting['response'][0]['postal_zip_code']?></div>
                    <div class="meta-value"><?php echo $setting['response'][0]['country']?></div>
                </div>
                
                <div class="invoice-to">
                    <div class="meta-label">Bill To</div>
                    <div class="meta-value"><?php echo htmlspecialchars((string) ($invoice_details['response'][0]['c_name'] ?? ''), ENT_QUOTES)?></div>
                    <div class="meta-value"><?php echo htmlspecialchars((string) ($invoice_details['response'][0]['c_email_mobile'] ?? ''), ENT_QUOTES)?></div>
                </div>
            </div>
            
            <div class="invoice-dates">
                <div class="date-item">
                    <div class="meta-label">Invoice Date</div>
                    <div class="meta-value"><?php echo convertToReadableDate($invoice_details['response'][0]['created_at'])?></div>
                </div>
                <div class="date-item">
                    <div class="meta-label">Due Date</div>
                    <div class="meta-value"><?php echo convertToReadableDate($invoice_details['response'][0]['i_due_date'])?></div>
                </div>
                <div class="date-item">
                    <div class="meta-label">Terms</div>
                    <div class="meta-value">Net 30</div>
                </div>
            </div>
        </div>
        
        <div class="invoice-body">
            <div style=" border: 1px solid #f6f9fc; overflow-x: auto; border-radius: 4px; ">
                <table class="items-table">
                    <thead>
                        <tr>
                            <th>Description</th>
                            <th>Quantity</th>
                            <th>Unit Price</th>
                            <th>Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                            $subtotal = 0;
                            $total_discount = 0;
                            $total_vat = 0;
                
                            foreach ($invoice_details_items['response'] as $items) {
                                $item_subtotal = $items['amount'] * $items['quantity'];
                                $item_discount = min($items['discount'], $item_subtotal);  // discount capped by subtotal
                                $item_amount_after_discount = $item_subtotal - $item_discount;
                                $item_vat = $item_amount_after_discount * ($items['vat'] / 100);
                
                                $subtotal += $item_subtotal;
                                $total_discount += $item_discount;
                                $total_vat += $item_vat;
                            ?>
                                <tr>
                                    <td>
                                        <div class="item-description"><?php echo htmlspecialchars($items['description']); ?></div>
                                    </td>
                                    <td class="text-center"><?php echo (int)$items['quantity']; ?></td>
                                    <td class="text-right"><?php echo number_format($items['amount'], 2) . $invoice_details['response'][0]['i_currency']; ?></td>
                                    <td class="text-right"><?php echo number_format($item_subtotal, 2) . $invoice_details['response'][0]['i_currency']; ?></td>
                                </tr>
                            <?php
                            }
                
                            // Shipping cost from invoice details or zero if missing
                            $shipping_cost = isset($invoice_details['response'][0]['i_amount_shipping']) ? floatval($invoice_details['response'][0]['i_amount_shipping']) : 0;
                
                            // Calculate final total amount
                            $total_amount = $subtotal - $total_discount + $total_vat + $shipping_cost;
                            $currency = $invoice_details['response'][0]['i_currency'];
                        ?>
                    </tbody>
                </table>
            </div>
        
        
            <table class="totals-table">
                <tr>
                    <td>Subtotal</td>
                    <td class="text-right"><?php echo number_format($subtotal, 2) . $currency; ?></td>
                </tr>
                <tr>
                    <td>Shipping</td>
                    <td class="text-right"><?php echo number_format($shipping_cost, 2) . $currency; ?></td>
                </tr>
        
                <?php if ($total_vat > 0): ?>
                    <tr>
                        <td>VAT</td>
                        <td class="text-right"><?php echo number_format($total_vat, 2) . $currency; ?></td>
                    </tr>
                <?php endif; ?>
        
                <?php if ($total_discount > 0): ?>
                    <tr>
                        <td>Discount</td>
                        <td class="text-right">-<?php echo number_format($total_discount, 2) . $currency; ?></td>
                    </tr>
                <?php endif; ?>
        
                <tr>
                    <td><strong>Total Amount</strong></td>
                    <td class="text-right"><strong><?php echo number_format(round($total_amount, 2), 2) . $currency; ?></strong></td>
                </tr>
            </table>
        </div>
        
        <span id="response"></span>
        
        <div class="invoice-footer">
            <div class="notes">
                <?php
                   if($invoice_details['response'][0]['i_note'] == "" || $invoice_details['response'][0]['i_note'] == "--"){
                       
                   }else{
                ?>
                     <strong>Notes:</strong> <?php echo $invoice_details['response'][0]['i_note']?>
                <?php
                   }
                ?>
            </div>
            
            <div class="payment-actions">
                <button class="btn-print" onclick="window.print()">
                    <i class="fas fa-print"></i> Print
                </button>
                
                <?php
                    if($invoice_details['response'][0]['i_status'] == "unpaid"){
                ?>
                        <button class="btn-pay" id="pay-button"><i class="fas fa-credit-card"></i> Pay Now</button>
                <?php
                    }
                ?>
            </div>
        </div>
    </div>

    <p style=" text-align: center; margin-bottom: 19px; font-size: 14px; ">Your payment data is secured with <a href="https://piprapay.com" target="blank" style="color:<?php echo $setting['response'][0]['global_text_color']?>;text-decoration: none;"><strong>PipraPay</strong></a> 256-bit encryption</p>
    
    <?php
        if($invoice_details['response'][0]['i_status'] == "unpaid"){
    ?>
            <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.4.1/jquery.min.js"></script>
            <script>
                $(document).ready(function () {
                    $('#pay-button').click(function (e) {
                      e.preventDefault();
                      
                      $("#pay-button").html('<div class="spinner-border spinner-border-sm" role="status"> <span class="visually-hidden">Loading...</span> </div>');
                      $("#pay-button").prop("disabled", true);
                      
                      
                      $.ajax
                        ({
                          type: "POST",
                          url: "",
                          data: { "action": "pp-invoice-link", "pp-invoiceid": "<?php echo $invoice_id?>" },
                          success: function (data) {
                                $("#pay-button").html('<i class="fas fa-credit-card"></i> Pay Now');
                                $("#pay-button").prop("disabled", true);
                                
                                var response = JSON.parse(data);
                
                                if (response.status === false) {
                                    $("#response").html('<div class="alert alert-danger" style="margin-top: 20px; margin-bottom: 20px;" role="alert">'+response.message+'</div>');
                                } else {
                                    $("#response").html('');
                                    location.href = response.pp_url;
                                }
                          }
                        });
                    });
                });
            </script>
    <?php
        }
    ?>
</body>
</html>