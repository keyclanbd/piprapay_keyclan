<?php
$plugin_meta = [
    'Plugin Name'       => 'USDT Manual',
    'Description'       => 'Accept USDT on TRON, BSC and Ethereum using your own wallet addresses and QR codes, checked manually.',
    'Version'           => '1.0.0',
    'Author'            => 'PipraPay',
    'Author URI'        => 'https://piprapay.com/',
    'License'           => 'GPL-2.0+',
    'License URI'       => 'http://www.gnu.org/licenses/gpl-2.0.txt',
    'Requires at least' => '1.0.0',
    'Plugin URI'        => '',
    'Text Domain'       => '',
    'Domain Path'       => '',
    'Requires PHP'      => ''
];

require_once __DIR__ . '/usdt-manual-helpers.php';

// Load the admin UI rendering function
function usdt_manual_admin_page() {
    $viewFile = __DIR__ . '/views/admin-ui.php';

    if (file_exists($viewFile)) {
        include $viewFile;
    } else {
        echo "<div class='alert alert-warning'>Admin UI not found.</div>";
    }
}

// Load the checkout UI rendering function
function usdt_manual_checkout_page($payment_id) {
    $viewFile = __DIR__ . '/views/checkout-ui.php';

    if (file_exists($viewFile)) {
        include $viewFile;
    } else {
        echo "<div class='alert alert-warning'>Checkout UI not found.</div>";
    }
}
