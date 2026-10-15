<?php
$plugin_meta = [
    'Plugin Name'       => 'Cryptomus',
    'Description'       => 'Accept cryptocurrency payments through Cryptomus. Support for Bitcoin, USDT, Ethereum and 20+ cryptocurrencies with automatic conversion.',
    'Version'           => '1.0.0',
    'Author'            => 'iPayees',
    'Author URI'        => 'https://ipayees.com/',
    'License'           => 'Proprietary',
    'License URI'       => 'https://ipayees.com/license/',
    'Requires at least' => '1.0.0',
    'Plugin URI'        => 'https://ipayees.com/',
    'Text Domain'       => 'cryptomus',
    'Domain Path'       => '/languages',
    'Requires PHP'      => '7.4'
];

// Include the API file
require_once __DIR__ . '/cryptomus-api.php';

// Load the admin UI rendering function
function cryptomus_admin_page() {
    $viewFile = __DIR__ . '/views/admin-ui.php';

    if (file_exists($viewFile)) {
        include $viewFile;
    } else {
        echo "<div class='alert alert-warning'>Admin UI not found.</div>";
    }
}

// Load the checkout UI rendering function
function cryptomus_checkout_page($payment_id) {
    $viewFile = __DIR__ . '/views/checkout-ui.php';

    if (file_exists($viewFile)) {
        include $viewFile;
    } else {
        echo "<div class='alert alert-warning'>Checkout UI not found.</div>";
    }
}
?>

