<?php
    if (!defined('pp_allowed_access')) {
        die('Direct access not allowed');
    }

$plugin_meta = [
    'Plugin Name'       => 'Telegram Bot Notification',
    'Description'       => 'Telegram Bot Notification plugin for PipraPay delivers real-time transaction and security alerts directly to your Telegram via a bot integration.',
    'Version'           => '1.1.0',
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

$funcFile = __DIR__ . '/functions.php';
if (file_exists($funcFile)) {
    require_once $funcFile;
}

// Load the admin UI rendering function
function telegram_bot_notification_admin_page() {
    $viewFile = __DIR__ . '/views/admin-ui.php';

    if (file_exists($viewFile)) {
        include $viewFile;
    } else {
        echo "<div class='alert alert-warning'>Admin UI not found.</div>";
    }
}