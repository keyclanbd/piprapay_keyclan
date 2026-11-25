<?php
$plugin_meta = [
    'Plugin Name'       => 'bKash Agent',
    'Description'       => 'Accept bKash Agent payments directly from customers. Perfect for freelancers and small businesses.',
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

// The views read their slug through pp_current_gateway_slug(), so the admin can
// make copies of this gateway (a second agent number as its own checkout entry).
function bkash_agent_supports_copies() {
    return true;
}

// Load the admin UI rendering function
function bkash_agent_admin_page() {
    $viewFile = __DIR__ . '/views/admin-ui.php';

    if (file_exists($viewFile)) {
        include $viewFile;
    } else {
        echo "<div class='alert alert-warning'>Admin UI not found.</div>";
    }
}

// Load the checkout UI rendering function
function bkash_agent_checkout_page($payment_id) {
    $viewFile = __DIR__ . '/views/checkout-ui.php';

    if (file_exists($viewFile)) {
        include $viewFile;
    } else {
        echo "<div class='alert alert-warning'>Checkout UI not found.</div>";
    }
}