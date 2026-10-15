<?php
    if (!defined('pp_allowed_access')) {
        die('Direct access not allowed');
    }

    $invoice_details = pp_get_invoice($invoice_id);
    $invoice_details_items = pp_get_invoice_items($invoice_id);
    $setting = pp_get_settings();

    $merchantName = $setting['response'][0]['site_name'] ?? 'PipraPay';
    $merchantLogo = isset($setting['response'][0]['logo']) && $setting['response'][0]['logo'] !== '--'
        ? $setting['response'][0]['logo']
        : 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8Xw8AAoMBgDTD2qgAAAAASUVORK5CYII=';
    $favicon = isset($setting['response'][0]['favicon']) && $setting['response'][0]['favicon'] !== '--'
        ? $setting['response'][0]['favicon']
        : 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8Xw8AAoMBgDTD2qgAAAAASUVORK5CYII=';

    $themeColor = $setting['response'][0]['global_text_color'] ?? '#0f172a';
    $primaryColor = $setting['response'][0]['primary_button_color'] ?? '#0f172a';
    $buttonTextColor = $setting['response'][0]['button_text_color'] ?? '#ffffff';
    $buttonHoverColor = $setting['response'][0]['button_hover_color'] ?? '#111827';
    $buttonHoverTextColor = $setting['response'][0]['button_hover_text_color'] ?? '#ffffff';

    $subtotal = 0;
    $total_discount = 0;
    $total_vat = 0;

    foreach ($invoice_details_items['response'] as $items) {
        $item_subtotal = $items['amount'] * $items['quantity'];
        $item_discount = min($items['discount'], $item_subtotal);
        $item_amount_after_discount = $item_subtotal - $item_discount;
        $item_vat = $item_amount_after_discount * ($items['vat'] / 100);

        $subtotal += $item_subtotal;
        $total_discount += $item_discount;
        $total_vat += $item_vat;
    }

    $shipping_cost = isset($invoice_details['response'][0]['i_amount_shipping']) ? floatval($invoice_details['response'][0]['i_amount_shipping']) : 0;
    $total_amount = $subtotal - $total_discount + $total_vat + $shipping_cost;
    $currency = $invoice_details['response'][0]['i_currency'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice - <?php echo htmlspecialchars($merchantName); ?></title>
    <link rel="icon" type="image/x-icon" href="<?php echo htmlspecialchars($favicon); ?>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.4.1/jquery.min.js"></script>
    <style>
        :root {
            --keypay-bg: #f5f7fb;
            --keypay-surface: #ffffff;
            --keypay-border: #e5e7eb;
            --keypay-text: #111827;
            --keypay-muted: #6b7280;
            --keypay-primary: <?php echo $themeColor; ?>;
            --keypay-button: <?php echo $primaryColor; ?>;
            --keypay-button-text: <?php echo $buttonTextColor; ?>;
            --keypay-button-hover: <?php echo $buttonHoverColor; ?>;
            --keypay-button-hover-text: <?php echo $buttonHoverTextColor; ?>;
            --success: #16a34a;
            --danger: #dc2626;
            --warning: #f59e0b;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            font-family: 'Inter', sans-serif;
            background: radial-gradient(circle at top, #ffffff 0%, var(--keypay-bg) 60%);
            color: var(--keypay-text);
            min-height: 100vh;
        }

        .keypay-shell {
            max-width: 860px;
            margin: 32px auto;
            padding: 0 16px;
        }

        .keypay-card {
            background: var(--keypay-surface);
            border: 1px solid var(--keypay-border);
            border-radius: 24px;
            box-shadow: 0 18px 40px rgba(15, 23, 42, 0.08);
            overflow: hidden;
        }

        .keypay-header {
            padding: 24px;
            border-bottom: 1px solid var(--keypay-border);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .brand img {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            object-fit: cover;
            border: 1px solid var(--keypay-border);
        }

        .brand h1 {
            margin: 0;
            font-size: 18px;
            font-weight: 800;
        }

        .brand p {
            margin: 2px 0 0;
            color: var(--keypay-muted);
            font-size: 13px;
        }

        .keypay-body {
            padding: 24px;
        }

        .invoice-top {
            display: flex;
            justify-content: space-between;
            gap: 20px;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }

        .hero h2 {
            font-size: 30px;
            margin: 0 0 8px;
            font-weight: 800;
            letter-spacing: -0.03em;
        }

        .hero p {
            margin: 0;
            color: var(--keypay-muted);
            font-size: 15px;
        }

        .status-pill {
            padding: 10px 14px;
            border-radius: 999px;
            font-size: 13px;
            font-weight: 700;
            height: fit-content;
            background: rgba(17, 24, 39, 0.05);
        }

        .status-unpaid { color: var(--danger); background: rgba(220, 38, 38, 0.08); }
        .status-paid { color: var(--success); background: rgba(22, 163, 74, 0.08); }
        .status-refunded { color: var(--warning); background: rgba(245, 158, 11, 0.12); }

        .invoice-summary {
            display: grid;
            grid-template-columns: 1.2fr 0.8fr;
            gap: 16px;
            margin-bottom: 24px;
        }

        .panel {
            border: 1px solid var(--keypay-border);
            border-radius: 18px;
            padding: 18px;
            background: linear-gradient(180deg, rgba(17, 24, 39, 0.03), rgba(17, 24, 39, 0.02));
        }

        .panel-title {
            color: var(--keypay-muted);
            font-size: 13px;
            margin-bottom: 6px;
        }

        .panel-value {
            font-weight: 700;
            font-size: 15px;
            line-height: 1.5;
        }

        .amount-big {
            font-size: 28px;
            font-weight: 800;
            letter-spacing: -0.03em;
        }

        .table-wrap {
            border: 1px solid var(--keypay-border);
            border-radius: 18px;
            overflow: hidden;
            margin-bottom: 22px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        thead th {
            background: #f9fafb;
            color: var(--keypay-muted);
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            padding: 14px 16px;
            text-align: left;
            border-bottom: 1px solid var(--keypay-border);
        }

        tbody td {
            padding: 16px;
            border-bottom: 1px solid var(--keypay-border);
            vertical-align: top;
            font-size: 14px;
        }

        tbody tr:last-child td { border-bottom: none; }

        .right { text-align: right; }
        .center { text-align: center; }

        .totals {
            max-width: 360px;
            margin-left: auto;
            border: 1px solid var(--keypay-border);
            border-radius: 18px;
            overflow: hidden;
        }

        .totals-row {
            display: flex;
            justify-content: space-between;
            gap: 20px;
            padding: 12px 16px;
            border-bottom: 1px solid var(--keypay-border);
            font-size: 14px;
        }

        .totals-row:last-child {
            border-bottom: none;
            font-size: 18px;
            font-weight: 800;
        }

        .footer {
            padding: 18px 24px 24px;
            border-top: 1px solid var(--keypay-border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            flex-wrap: wrap;
        }

        .notes {
            color: var(--keypay-muted);
            font-size: 14px;
            max-width: 520px;
        }

        .actions {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        .btn-soft,
        .btn-pay {
            border: none;
            border-radius: 14px;
            padding: 13px 18px;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            transition: transform 0.15s ease, background 0.2s ease;
        }

        .btn-soft {
            background: #fff;
            color: var(--keypay-text);
            border: 1px solid var(--keypay-border);
        }

        .btn-soft:hover { transform: translateY(-1px); background: #f9fafb; }

        .btn-soft:focus,
        .btn-pay:focus {
            outline: none;
            box-shadow: 0 0 0 4px rgba(17, 24, 39, 0.12);
        }

        .btn-pay {
            background: var(--keypay-button);
            color: var(--keypay-button-text);
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }

        .btn-pay:hover {
            background: var(--keypay-button-hover);
            color: var(--keypay-button-hover-text);
            transform: translateY(-1px);
        }

        .btn-pay:disabled {
            opacity: 0.7;
            cursor: not-allowed;
            transform: none;
        }

        .spinner-wrap {
            display: none;
            align-items: center;
            justify-content: center;
            gap: 10px;
            color: var(--keypay-muted);
            margin-top: 14px;
        }

        #response { display: block; margin-top: 14px; }

        @media (max-width: 768px) {
            .invoice-summary { grid-template-columns: 1fr; }
            .keypay-shell { margin: 0; padding: 0; }
            .keypay-card { border-radius: 0; min-height: 100vh; }
            .keypay-header, .keypay-body, .footer { padding-left: 18px; padding-right: 18px; }
        }
    </style>
</head>
<body>
    <div class="keypay-shell">
        <div class="keypay-card">
            <div class="keypay-header">
                <div class="brand">
                    <img src="<?php echo htmlspecialchars($merchantLogo); ?>" alt="<?php echo htmlspecialchars($merchantName); ?>">
                    <div>
                        <h1><?php echo htmlspecialchars($merchantName); ?></h1>
                        <p>Invoice checkout by KeyPay</p>
                    </div>
                </div>

                <?php if ($invoice_details['response'][0]['i_status'] === 'unpaid'): ?>
                    <div class="status-pill status-unpaid">Unpaid</div>
                <?php elseif ($invoice_details['response'][0]['i_status'] === 'paid'): ?>
                    <div class="status-pill status-paid">Paid</div>
                <?php elseif ($invoice_details['response'][0]['i_status'] === 'refunded'): ?>
                    <div class="status-pill status-refunded">Refunded</div>
                <?php else: ?>
                    <div class="status-pill"><?php echo htmlspecialchars(ucfirst($invoice_details['response'][0]['i_status'] ?? 'Unknown')); ?></div>
                <?php endif; ?>
            </div>

            <div class="keypay-body">
                <div class="invoice-top">
                    <div class="hero">
                        <h2>Invoice #<?php echo htmlspecialchars($invoice_details['response'][0]['id'] ?? ''); ?></h2>
                        <p>Review the invoice and complete payment securely.</p>
                    </div>
                    <div class="status-pill">Due <?php echo htmlspecialchars(convertToReadableDate($invoice_details['response'][0]['i_due_date'] ?? '')); ?></div>
                </div>

                <div class="invoice-summary">
                    <div class="panel">
                        <div class="panel-title">Bill to</div>
                        <div class="panel-value"><?php echo htmlspecialchars($invoice_details['response'][0]['c_name'] ?? ''); ?></div>
                        <div class="panel-value" style="font-weight:600; color: var(--keypay-muted); margin-top:4px;"><?php echo htmlspecialchars($invoice_details['response'][0]['c_email_mobile'] ?? ''); ?></div>
                    </div>
                    <div class="panel">
                        <div class="panel-title">Total amount</div>
                        <div class="amount-big"><?php echo htmlspecialchars(number_format((float)$total_amount, 2) . $currency); ?></div>
                    </div>
                </div>

                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Description</th>
                                <th class="center">Qty</th>
                                <th class="right">Unit Price</th>
                                <th class="right">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($invoice_details_items['response'] as $items): ?>
                                <?php $item_subtotal = $items['amount'] * $items['quantity']; ?>
                                <tr>
                                    <td>
                                        <div style="font-weight:700;"><?php echo htmlspecialchars($items['description']); ?></div>
                                    </td>
                                    <td class="center"><?php echo (int)$items['quantity']; ?></td>
                                    <td class="right"><?php echo number_format((float)$items['amount'], 2) . $currency; ?></td>
                                    <td class="right"><?php echo number_format((float)$item_subtotal, 2) . $currency; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="totals">
                    <div class="totals-row"><span>Subtotal</span><span><?php echo number_format((float)$subtotal, 2) . $currency; ?></span></div>
                    <div class="totals-row"><span>Shipping</span><span><?php echo number_format((float)$shipping_cost, 2) . $currency; ?></span></div>
                    <?php if ($total_vat > 0): ?><div class="totals-row"><span>VAT</span><span><?php echo number_format((float)$total_vat, 2) . $currency; ?></span></div><?php endif; ?>
                    <?php if ($total_discount > 0): ?><div class="totals-row"><span>Discount</span><span>-<?php echo number_format((float)$total_discount, 2) . $currency; ?></span></div><?php endif; ?>
                    <div class="totals-row"><span>Total</span><span><?php echo number_format(round((float)$total_amount, 2), 2) . $currency; ?></span></div>
                </div>

                <div id="response"></div>
                <div class="spinner-wrap" id="spinner-wrap"><span class="spinner-border spinner-border-sm"></span> Preparing payment...</div>
            </div>

            <div class="footer">
                <div class="notes">
                    <?php if (!empty($invoice_details['response'][0]['i_note']) && $invoice_details['response'][0]['i_note'] !== '--'): ?>
                        <strong>Notes:</strong> <?php echo htmlspecialchars($invoice_details['response'][0]['i_note']); ?>
                    <?php endif; ?>
                </div>
                <div class="actions">
                    <button class="btn-soft" onclick="window.print()" type="button"><i class="fas fa-print me-2"></i>Print</button>
                    <?php if ($invoice_details['response'][0]['i_status'] === 'unpaid'): ?>
                        <button class="btn-pay" id="pay-button" type="button"><i class="fas fa-credit-card"></i> Pay Now</button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <p style="text-align:center; margin: 18px 0 22px; color: var(--keypay-muted); font-size: 14px;">
        Your payment data is secured with 256-bit encryption
    </p>

    <?php if ($invoice_details['response'][0]['i_status'] === 'unpaid'): ?>
    <script>
        $(document).ready(function () {
            $('#pay-button').click(function (e) {
                e.preventDefault();

                const btn = $(this);
                btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Processing');
                $('#spinner-wrap').show();

                $.ajax({
                    type: 'POST',
                    url: '',
                    data: { action: 'pp-invoice-link', 'pp-invoiceid': '<?php echo $invoice_id; ?>' },
                    success: async function (data) {
                        btn.prop('disabled', false).html('<i class="fas fa-credit-card"></i> Pay Now');
                        $('#spinner-wrap').hide();

                        let response;
                        try {
                            const raw = (typeof data === 'string') ? data.trim() : '';
                            if (!raw) throw new Error('Empty server response');
                            response = JSON.parse(raw);
                        } catch (error) {
                            console.error(error, data);
                            $('#response').html('<div class="alert alert-danger mt-3 mb-3">Server returned an invalid response. Please try again.</div>');
                            return;
                        }

                        if (response.status === false || response.status === 'false') {
                            $('#response').html('<div class="alert alert-danger mt-3 mb-3">' + (response.message || 'Something went wrong.') + '</div>');
                            return;
                        }

                        if (!response.pp_url) {
                            $('#response').html('<div class="alert alert-danger mt-3 mb-3">Payment gateway did not return a redirect URL.</div>');
                            return;
                        }

                        $('#response').html('');
                        location.href = response.pp_url;
                    },
                    error: function (xhr) {
                        btn.prop('disabled', false).html('<i class="fas fa-credit-card"></i> Pay Now');
                        $('#spinner-wrap').hide();
                        $('#response').html('<div class="alert alert-danger mt-3 mb-3">Request failed. Please try again.</div>');
                        console.error(xhr);
                    }
                });
            });
        });
    </script>
    <?php endif; ?>
</body>
</html>