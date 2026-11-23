<?php
/**
 * Settings preparation and payload building for the Bangla QR Dynamic gateway.
 *
 * `bangla_qr_dynamic_prepare_settings()` is called by the core
 * `plugin_update-submit` handler before settings are written, which is the only
 * point where the freshly uploaded QR file is still available for decoding.
 */

if (!defined('pp_allowed_access')) {
    die('Direct access not allowed');
}

/**
 * ISO 4217 numeric code for a currency, as used by EMV tag 53.
 *
 * @param string $alpha
 * @return string Three digit code, or '' when unknown.
 */
function bangla_qr_dynamic_currency_numeric($alpha)
{
    $map = array(
        'BDT' => '050', 'USD' => '840', 'EUR' => '978', 'GBP' => '826',
        'INR' => '356', 'PKR' => '586', 'AED' => '784', 'SAR' => '682',
        'MYR' => '458', 'SGD' => '702', 'CAD' => '124', 'AUD' => '036',
        'JPY' => '392', 'CNY' => '156', 'KWD' => '414', 'QAR' => '634',
        'OMR' => '512', 'BHD' => '048', 'NPR' => '524', 'LKR' => '144',
    );

    $alpha = strtoupper(trim((string) $alpha));

    return $map[$alpha] ?? '';
}

/**
 * Human label for an EMV numeric currency code.
 *
 * @param string $numeric
 * @return string
 */
function bangla_qr_dynamic_currency_label($numeric)
{
    $map = array(
        '050' => 'BDT', '840' => 'USD', '978' => 'EUR', '826' => 'GBP',
        '356' => 'INR', '586' => 'PKR', '784' => 'AED', '682' => 'SAR',
    );
    $numeric = trim((string) $numeric);

    return $map[$numeric] ?? $numeric;
}

/**
 * Shared validation of the fields this gateway adds on top of the core ones.
 *
 * @param array $settings
 * @return string Empty when valid, otherwise a message for the admin.
 */
function bangla_qr_dynamic_validate_extra_settings(array $settings)
{
    if (isset($settings['qr_scale'])) {
        $scale = (int) $settings['qr_scale'];
        if ($scale < 4 || $scale > 20) {
            return 'QR size must be between 4 and 20 pixels per module.';
        }
    }

    if (isset($settings['error_correction']) && !in_array($settings['error_correction'], array('L', 'M', 'Q', 'H'), true)) {
        return 'Error correction must be one of L, M, Q or H.';
    }

    if (isset($settings['qr_lifetime_minutes'])) {
        $minutes = (int) $settings['qr_lifetime_minutes'];
        if ($minutes < 1 || $minutes > 30) {
            return 'QR lifetime must be between 1 and 30 minutes.';
        }
    }

    if (isset($settings['match_grace_minutes'])) {
        $grace = (int) $settings['match_grace_minutes'];
        if ($grace < 1 || $grace > 60) {
            return 'Match grace must be between 1 and 60 minutes.';
        }
    }

    if (isset($settings['auto_verify']) && !in_array(strtolower((string) $settings['auto_verify']), array('enable', 'disable'), true)) {
        return 'Auto verify must be enabled or disabled.';
    }

    return '';
}

/**
 * Core hook: validate and enrich a gateway settings submission.
 *
 * @param array $newData         Posted settings.
 * @param array $currentSettings Settings currently stored.
 * @param array $files           Uploaded files ($_FILES).
 * @return array{status:bool, message?:string, settings?:array, notice?:string}
 */
function bangla_qr_dynamic_prepare_settings($newData, $currentSettings, $files)
{
    $newData = is_array($newData) ? $newData : array();
    $currentSettings = is_array($currentSettings) ? $currentSettings : array();

    $fail = function ($message) {
        return array('status' => false, 'message' => $message);
    };

    $invalid = bangla_qr_dynamic_validate_extra_settings($newData);
    if ($invalid !== '') {
        return $fail($invalid);
    }

    $uploaded = isset($files['qr_code']) && is_array($files['qr_code']) ? $files['qr_code'] : null;
    $hasUpload = $uploaded !== null && ($uploaded['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;

    $pasted = isset($newData['merchant_payload']) ? trim((string) $newData['merchant_payload']) : '';
    $stored = isset($currentSettings['merchant_payload']) ? trim((string) $currentSettings['merchant_payload']) : '';

    $payload = '';
    $source = 'stored';
    $notice = '';

    if ($hasUpload) {
        // A newly uploaded image is the merchant's intent; decode it server side
        // so the stored payload never depends on the browser. These guards run
        // before decoding because this hook fires ahead of the core file checks
        // and decoding is the expensive part.
        if (($uploaded['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            return $fail('Settings were not saved. The QR image upload failed. Please try again.');
        }

        $size = (int) ($uploaded['size'] ?? 0);
        if ($size <= 0 || $size > 5 * 1024 * 1024) {
            return $fail('Settings were not saved. The QR image must be smaller than 5MB.');
        }

        $tmp = (string) ($uploaded['tmp_name'] ?? '');
        if ($tmp === '' || !is_file($tmp)) {
            return $fail('Settings were not saved. The QR image upload failed. Please try again.');
        }

        $info = @getimagesize($tmp);
        $allowedTypes = array(IMAGETYPE_PNG, IMAGETYPE_JPEG, IMAGETYPE_WEBP);
        if (!$info || !in_array($info[2] ?? 0, $allowedTypes, true)) {
            return $fail('Settings were not saved. The QR image must be a PNG, JPG or WEBP file.');
        }

        $decoded = bqr_decode_image($tmp);
        if (($decoded['ok'] ?? false) !== true) {
            return $fail('Settings were not saved. ' . ($decoded['error'] ?? 'The QR image could not be read.'));
        }
        $payload = trim($decoded['payload']);
        $source = 'image';
    } elseif ($pasted !== '') {
        $payload = $pasted;
        $source = $pasted === $stored ? 'stored' : 'payload';
    } elseif ($stored !== '') {
        $payload = $stored;
    } else {
        return $fail('Settings were not saved. Upload your merchant QR image or paste its payload.');
    }

    $validated = bqr_validate_merchant_payload($payload);
    if (($validated['ok'] ?? false) !== true) {
        $message = $validated['error'] ?? 'That is not a usable Bangla QR payload.';
        if ($source === 'image') {
            $message = 'The uploaded image decoded, but it is not a Bangla QR: ' . $message;
        }

        return $fail('Settings were not saved. ' . $message);
    }

    $fields = $validated['fields'];

    $newData['merchant_payload'] = $payload;
    $newData['merchant_name'] = (string) ($fields['merchant_name'] ?? '');
    $newData['merchant_city'] = (string) ($fields['merchant_city'] ?? '');
    $newData['merchant_currency'] = (string) ($fields['currency'] ?? '');
    $newData['merchant_points'] = (string) ($fields['point_of_initiation'] ?? '');

    $gui = '';
    foreach (($fields['merchant_accounts'] ?? array()) as $account) {
        if (!empty($account['gui'])) {
            $gui = (string) $account['gui'];
            break;
        }
    }
    $newData['merchant_gui'] = $gui;

    if ($source === 'image') {
        $notice = ' Merchant QR decoded from the uploaded image.';
    } elseif ($source === 'payload') {
        $notice = ' Merchant QR payload updated.';
    }

    $payloadCurrency = (string) ($fields['currency'] ?? '');
    $gatewayCurrency = strtoupper((string) ($newData['currency'] ?? ''));
    if ($payloadCurrency !== '' && $gatewayCurrency !== '') {
        $expected = bangla_qr_dynamic_currency_numeric($gatewayCurrency);
        if ($expected !== '' && $expected !== $payloadCurrency) {
            $notice .= ' Warning: the QR states ' . bangla_qr_dynamic_currency_label($payloadCurrency)
                . ' but the gateway is set to ' . $gatewayCurrency . '. Amounts will not match.';
        }
    }

    return array('status' => true, 'settings' => $newData, 'notice' => $notice);
}

/**
 * Build the dynamic, amount-specific payload for a checkout.
 *
 * @param array $settings      Gateway settings.
 * @param mixed $amount        Amount due in the gateway currency.
 * @param bool  $trimZeroCents Drop ".00" from whole amounts.
 * @return array{ok:bool, payload:string, error:string, fields:array}
 */
function bangla_qr_dynamic_build_payload(array $settings, $amount, $trimZeroCents = false)
{
    $payload = trim((string) ($settings['merchant_payload'] ?? ''));
    if ($payload === '') {
        return array('ok' => false, 'payload' => '', 'error' => 'No merchant QR has been configured for this gateway.', 'fields' => array());
    }

    $currency = (string) ($settings['merchant_currency'] ?? '');
    if ($currency === '') {
        $currency = bangla_qr_dynamic_currency_numeric((string) ($settings['currency'] ?? ''));
    }

    return bqr_make_dynamic($payload, $amount, $currency, $trimZeroCents);
}
