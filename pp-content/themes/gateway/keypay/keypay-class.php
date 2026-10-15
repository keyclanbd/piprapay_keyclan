<?php
    $theme_meta = [
        'Theme Name'        => 'KeyPay',
        'Theme URI'         => 'https://piprapay.com/',
        'Description'       => 'A clean, modern checkout theme for PipraPay payment links and invoices.',
        'Version'           => '1.0.0',
        'Author'            => 'PipraPay',
        'Author URI'        => 'https://piprapay.com/',
        'License'           => 'GPL-2.0+',
        'License URI'       => 'http://www.gnu.org/licenses/gpl-2.0.txt',
        'Text Domain'       => 'piprapay-keypay',
        'Domain Path'       => '/languages',
        'Requires at least' => '1.0.0',
        'Requires PHP'      => '7.4',
    ];

    $funcFile = __DIR__ . '/functions.php';
    if (file_exists($funcFile)) {
        require_once $funcFile;
    }
?>