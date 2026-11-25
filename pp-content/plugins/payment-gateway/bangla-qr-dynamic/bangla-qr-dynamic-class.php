<?php

if (!defined('pp_allowed_access')) {
    die('Direct access not allowed');
}

$plugin_meta = [
    'Plugin Name'       => 'Bangla QR Dynamic',
    'Description'       => 'Turns your existing merchant Bangla QR into an amount-specific dynamic QR at checkout.',
    'Version'           => '1.0.0',
    'Author'            => 'KeyClan',
    'Author URI'        => 'https://keyclan.com',
    'License'           => 'GPL-2.0+',
    'License URI'       => 'http://www.gnu.org/licenses/gpl-2.0.txt',
    'Requires at least' => '1.0.0',
    'Plugin URI'        => 'https://keyclan.com',
    'Text Domain'       => 'bangla-qr-dynamic',
    'Domain Path'       => '',
    'Requires PHP'      => '8.0',
];

require_once __DIR__ . '/lib/bangla-qr-emv.php';
require_once __DIR__ . '/lib/bangla-qr-codec.php';
require_once __DIR__ . '/lib/bangla-qr-image.php';
require_once __DIR__ . '/lib/bangla-qr-decode.php';
require_once __DIR__ . '/lib/bangla-qr-amounts.php';
require_once __DIR__ . '/bangla-qr-dynamic-helpers.php';
require_once __DIR__ . '/functions.php';

if (!function_exists('bangla_qr_dynamic_supports_copies')) {
    /**
     * This gateway reads its settings through pp_current_gateway_slug(), so the
     * admin can make copies of it (a second merchant QR as its own checkout entry).
     *
     * @return bool
     */
    function bangla_qr_dynamic_supports_copies()
    {
        return true;
    }
}

if (!function_exists('bangla_qr_dynamic_admin_page')) {
    /**
     * Admin settings screen for the gateway.
     *
     * @return void
     */
    function bangla_qr_dynamic_admin_page()
    {
        $viewFile = __DIR__ . '/views/admin-ui.php';
        if (file_exists($viewFile)) {
            include $viewFile;
        } else {
            echo "<div class='alert alert-warning'>Admin UI not found.</div>";
        }
    }
}

if (!function_exists('bangla_qr_dynamic_checkout_page')) {
    /**
     * Checkout screen shown to the customer.
     *
     * @param string $payment_id Transaction id.
     * @return void
     */
    function bangla_qr_dynamic_checkout_page($payment_id)
    {
        $viewFile = __DIR__ . '/views/checkout-ui.php';
        if (file_exists($viewFile)) {
            include $viewFile;
        } else {
            echo "<div class='alert alert-warning'>Checkout UI not found.</div>";
        }
    }
}
