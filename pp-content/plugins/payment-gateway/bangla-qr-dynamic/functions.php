<?php

if (!defined('pp_allowed_access')) {
    die('Direct access not allowed');
}

require_once __DIR__ . '/lib/bangla-qr-amounts.php';

if (!function_exists('bangla_qr_dynamic_on_sms')) {
    /**
     * A message just landed in sms_data: try to confirm a checkout with it.
     *
     * @param int $smsId
     * @return void
     */
    function bangla_qr_dynamic_on_sms($smsId)
    {
        try {
            bangla_qr_dynamic_match_sms($smsId);
        } catch (Throwable $e) {
            // A failed match must never break the phone's upload.
            error_log('bangla-qr-dynamic sms: ' . $e->getMessage());
        }
    }
}

if (!function_exists('bangla_qr_dynamic_on_cron')) {
    /**
     * Releases closed amounts and retries messages that arrived off-hours.
     *
     * @return void
     */
    function bangla_qr_dynamic_on_cron()
    {
        try {
            bangla_qr_dynamic_cron();
        } catch (Throwable $e) {
            error_log('bangla-qr-dynamic cron: ' . $e->getMessage());
        }
    }
}

if (!function_exists('bangla_qr_dynamic_register_hooks')) {
    /**
     * Register once per request; pp_trigger_hook includes this file with
     * include_once, so the guard is only a safety net.
     *
     * @return void
     */
    function bangla_qr_dynamic_register_hooks()
    {
        static $registered = false;
        if ($registered) {
            return;
        }
        $registered = true;

        // The hook API lives in the core controller. Guarded so this file stays
        // safe to include from tooling or tests that load the plugin alone.
        if (!function_exists('add_action')) {
            return;
        }

        add_action('pp_sms_received', 'bangla_qr_dynamic_on_sms');
        add_action('pp_cron', 'bangla_qr_dynamic_on_cron');
    }
}

bangla_qr_dynamic_register_hooks();
