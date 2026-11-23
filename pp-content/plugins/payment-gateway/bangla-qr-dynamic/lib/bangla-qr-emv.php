<?php
/**
 * EMVCo / Bangla QR TLV engine.
 *
 * Parses and rebuilds EMVCo-compliant QR payloads (the format used by Bangladesh
 * Bank's Bangla QR), including the CRC16 checksum in tag 63.
 *
 * Everything here is pure PHP: no extensions, no network, no framework.
 */

if (!defined('pp_allowed_access')) {
    die('Direct access not allowed');
}

/**
 * CRC16/CCITT-FALSE: poly 0x1021, init 0xFFFF, no input/output reflection,
 * no final XOR. This is the checksum EMVCo specifies for tag 63.
 *
 * @param string $data
 * @return string Four uppercase hex characters, e.g. "0B18".
 */
function bqr_crc16($data)
{
    $crc = 0xFFFF;
    $length = strlen($data);
    for ($i = 0; $i < $length; $i++) {
        $crc ^= (ord($data[$i]) << 8) & 0xFFFF;
        for ($bit = 0; $bit < 8; $bit++) {
            if (($crc & 0x8000) !== 0) {
                $crc = (($crc << 1) ^ 0x1021) & 0xFFFF;
            } else {
                $crc = ($crc << 1) & 0xFFFF;
            }
        }
    }

    return strtoupper(str_pad(dechex($crc), 4, '0', STR_PAD_LEFT));
}

/**
 * Split a payload into its top-level TLV nodes.
 *
 * Returns a list of nodes, each with:
 *   tag   - two digit string
 *   len   - declared length
 *   value - raw value bytes (for templates this is itself a TLV sequence)
 *   raw   - the original "tag len value" slice
 *
 * @param string $payload
 * @return array{ok: bool, nodes: array, error: string}
 */
function bqr_parse_tlv($payload)
{
    $payload = (string) $payload;
    $nodes = array();
    $offset = 0;
    $total = strlen($payload);

    while ($offset < $total) {
        if ($offset + 4 > $total) {
            return array('ok' => false, 'nodes' => $nodes, 'error' => 'Truncated element header at offset ' . $offset);
        }

        $tag = substr($payload, $offset, 2);
        $lenRaw = substr($payload, $offset + 2, 2);

        if (!ctype_digit($tag) || !ctype_digit($lenRaw)) {
            return array('ok' => false, 'nodes' => $nodes, 'error' => 'Non-numeric tag or length at offset ' . $offset);
        }

        $len = (int) $lenRaw;
        if ($offset + 4 + $len > $total) {
            return array('ok' => false, 'nodes' => $nodes, 'error' => 'Element ' . $tag . ' runs past the end of the payload');
        }

        $value = substr($payload, $offset + 4, $len);
        $nodes[] = array(
            'tag' => $tag,
            'len' => $len,
            'value' => $value,
            'raw' => substr($payload, $offset, 4 + $len),
        );

        $offset += 4 + $len;
    }

    return array('ok' => true, 'nodes' => $nodes, 'error' => '');
}

/**
 * Encode one TLV element. Lengths are two digits, so a value longer than 99
 * bytes cannot be represented and is rejected rather than silently truncated.
 *
 * @param string $tag
 * @param string $value
 * @return string
 * @throws InvalidArgumentException
 */
function bqr_build_tlv($tag, $value)
{
    $tag = (string) $tag;
    $value = (string) $value;

    if (!preg_match('/^\d{2}$/', $tag)) {
        throw new InvalidArgumentException('TLV tag must be two digits');
    }
    $length = strlen($value);
    if ($length > 99) {
        throw new InvalidArgumentException('TLV value for tag ' . $tag . ' exceeds 99 bytes');
    }

    return $tag . str_pad((string) $length, 2, '0', STR_PAD_LEFT) . $value;
}

/**
 * Template tags whose value is itself a TLV sequence.
 *
 * @param string $tag
 * @return bool
 */
function bqr_is_template_tag($tag)
{
    $n = (int) $tag;
    return ($n >= 26 && $n <= 51) || $n === 62 || $n === 64 || ($n >= 80 && $n <= 99);
}

/**
 * Parse a full EMV payload into semantic fields while keeping the raw node list
 * so the payload can be rebuilt byte-for-byte.
 *
 * @param string $payload
 * @return array{ok: bool, error: string, fields: array, nodes: array}
 */
function bqr_parse_emv($payload)
{
    $payload = (string) $payload;
    $parsed = bqr_parse_tlv($payload);
    if ($parsed['ok'] !== true) {
        return array('ok' => false, 'error' => $parsed['error'], 'fields' => array(), 'nodes' => array());
    }

    $fields = array(
        'raw' => $payload,
        'payload_format_indicator' => '',
        'point_of_initiation' => '',
        'merchant_accounts' => array(),
        'currency' => '',
        'amount' => '',
        'country' => '',
        'merchant_name' => '',
        'merchant_city' => '',
        'additional_data' => '',
        'crc' => '',
        'crc_valid' => false,
        'has_amount' => false,
    );

    foreach ($parsed['nodes'] as $node) {
        $tag = $node['tag'];
        $n = (int) $tag;

        if ($tag === '00') {
            $fields['payload_format_indicator'] = $node['value'];
        } elseif ($tag === '01') {
            $fields['point_of_initiation'] = $node['value'];
        } elseif ($n >= 26 && $n <= 51) {
            $account = array('tag' => $tag, 'raw' => $node['value'], 'gui' => '', 'reference' => '');
            $inner = bqr_parse_tlv($node['value']);
            if ($inner['ok'] === true) {
                foreach ($inner['nodes'] as $sub) {
                    if ($sub['tag'] === '00') {
                        $account['gui'] = $sub['value'];
                    } elseif ($sub['tag'] === '01') {
                        $account['reference'] = $sub['value'];
                    }
                }
            }
            $fields['merchant_accounts'][$tag] = $account;
        } elseif ($tag === '53') {
            $fields['currency'] = $node['value'];
        } elseif ($tag === '54') {
            $fields['amount'] = $node['value'];
            $fields['has_amount'] = true;
        } elseif ($tag === '58') {
            $fields['country'] = $node['value'];
        } elseif ($tag === '59') {
            $fields['merchant_name'] = $node['value'];
        } elseif ($tag === '60') {
            $fields['merchant_city'] = $node['value'];
        } elseif ($tag === '62') {
            $fields['additional_data'] = $node['value'];
        } elseif ($tag === '63') {
            $fields['crc'] = $node['value'];
        }
    }

    // The CRC covers everything before tag 63, including its own "6304" header.
    if (preg_match('/^(.*?)6304([0-9A-Fa-f]{4})$/s', $payload, $m)) {
        $fields['crc_valid'] = hash_equals(strtoupper(bqr_crc16($m[1] . '6304')), strtoupper($m[2]));
    }

    return array('ok' => true, 'error' => '', 'fields' => $fields, 'nodes' => $parsed['nodes']);
}

/**
 * Rebuild a payload from a node list, always recalculating tag 63.
 *
 * Tag 63 is dropped from the input and re-appended so the checksum can never
 * disagree with the data.
 *
 * @param array $nodes
 * @return string
 */
function bqr_build_from_nodes(array $nodes)
{
    $out = '';
    foreach ($nodes as $node) {
        if (!isset($node['tag']) || !array_key_exists('value', $node)) {
            continue;
        }
        if ((string) $node['tag'] === '63') {
            continue;
        }
        $out .= bqr_build_tlv($node['tag'], $node['value']);
    }

    $out .= '6304';

    return $out . bqr_crc16($out);
}

/**
 * Format an amount for EMV tag 54.
 *
 * EMVCo allows up to 13 characters, digits with an optional decimal point.
 * Bangladeshi QR gateways expect two decimals, so whole amounts become "100.00".
 *
 * @param mixed  $amount
 * @param bool   $trimZeros Drop a trailing ".00" (some banks prefer "100").
 * @return string|false
 */
function bqr_format_amount($amount, $trimZeros = false)
{
    if (!is_numeric($amount)) {
        return false;
    }

    $value = (float) $amount;
    if ($value <= 0 || $value > 99999999999.99) {
        return false;
    }

    $formatted = number_format($value, 2, '.', '');
    if ($trimZeros) {
        $formatted = rtrim(rtrim($formatted, '0'), '.');
    }

    if (strlen($formatted) > 13) {
        return false;
    }

    return $formatted;
}

/**
 * Turn a static merchant QR payload into a dynamic, amount-specific payload.
 *
 * Steps: set the point of initiation to 12 (dynamic), ensure a currency, insert
 * or replace tag 54, then recalculate the CRC.
 *
 * @param string $payload    Original merchant payload (static or dynamic).
 * @param string $amount     Human amount, e.g. "100.00".
 * @param string $currency   ISO 4217 numeric code, e.g. "050" for BDT.
 * @param bool   $trimZeros
 * @return array{ok: bool, payload: string, error: string, fields: array}
 */
function bqr_make_dynamic($payload, $amount, $currency = '', $trimZeros = false)
{
    $parsed = bqr_parse_emv($payload);
    if ($parsed['ok'] !== true) {
        return array('ok' => false, 'payload' => '', 'error' => $parsed['error'], 'fields' => array());
    }

    $fields = $parsed['fields'];
    $nodes = $parsed['nodes'];

    if ($fields['payload_format_indicator'] === '') {
        return array('ok' => false, 'payload' => '', 'error' => 'Not an EMV QR payload: tag 00 (payload format indicator) is missing.', 'fields' => $fields);
    }
    if (empty($fields['merchant_accounts']) && $fields['merchant_name'] === '') {
        return array('ok' => false, 'payload' => '', 'error' => 'Not an EMV QR payload: no merchant account information found.', 'fields' => $fields);
    }

    $amountText = bqr_format_amount($amount, $trimZeros);
    if ($amountText === false) {
        return array('ok' => false, 'payload' => '', 'error' => 'Enter a valid amount greater than zero.', 'fields' => $fields);
    }

    $currency = trim((string) $currency);
    if ($currency === '') {
        $currency = $fields['currency'];
    }
    if ($currency !== '' && !preg_match('/^\d{3}$/', $currency)) {
        return array('ok' => false, 'payload' => '', 'error' => 'Currency must be a three digit ISO 4217 code.', 'fields' => $fields);
    }
    if ($currency === '') {
        return array('ok' => false, 'payload' => '', 'error' => 'The merchant QR does not state a currency and none was configured.', 'fields' => $fields);
    }

    // Rebuild in the original element order, replacing the volatile elements.
    $out = '';
    $seenAmount = false;
    $seenCurrency = false;

    foreach ($nodes as $node) {
        $tag = (string) $node['tag'];

        if ($tag === '63') {
            continue;
        }

        if ($tag === '01') {
            $out .= bqr_build_tlv('01', '12');
            continue;
        }

        if ($tag === '53') {
            $out .= bqr_build_tlv('53', $currency);
            $seenCurrency = true;
            continue;
        }

        if ($tag === '54') {
            $out .= bqr_build_tlv('54', $amountText);
            $seenAmount = true;
            continue;
        }

        // Insert tag 54 immediately before the country code, as the Bangla QR
        // layout does, so the result matches what banks expect.
        if ($tag === '58' && !$seenAmount) {
            if (!$seenCurrency) {
                $out .= bqr_build_tlv('53', $currency);
                $seenCurrency = true;
            }
            $out .= bqr_build_tlv('54', $amountText);
            $seenAmount = true;
        }

        $out .= bqr_build_tlv($tag, $node['value']);
    }

    if (!$seenAmount) {
        $out .= bqr_build_tlv('54', $amountText);
    }
    if (!$seenCurrency) {
        $out .= bqr_build_tlv('53', $currency);
    }

    $out .= '6304';
    $out .= bqr_crc16($out);

    return array('ok' => true, 'payload' => $out, 'error' => '', 'fields' => $fields);
}

/**
 * Human readable checks used by the admin screen when a merchant QR is saved.
 *
 * @param string $payload
 * @return array{ok: bool, error: string, fields: array}
 */
function bqr_validate_merchant_payload($payload)
{
    $payload = trim((string) $payload);

    if ($payload === '') {
        return array('ok' => false, 'error' => 'The QR payload is empty.', 'fields' => array());
    }
    if (strlen($payload) > 1024) {
        return array('ok' => false, 'error' => 'The QR payload is too long to be a payment QR code.', 'fields' => array());
    }
    if (!preg_match('/^\d{2}/', $payload)) {
        return array('ok' => false, 'error' => 'This does not look like an EMV QR payload (it must start with tag 00).', 'fields' => array());
    }

    $parsed = bqr_parse_emv($payload);
    if ($parsed['ok'] !== true) {
        return array('ok' => false, 'error' => $parsed['error'], 'fields' => array());
    }

    $fields = $parsed['fields'];
    if ($fields['payload_format_indicator'] === '') {
        return array('ok' => false, 'error' => 'The payload is missing tag 00 (payload format indicator).', 'fields' => $fields);
    }
    if (empty($fields['merchant_accounts']) && $fields['merchant_name'] === '') {
        return array('ok' => false, 'error' => 'The payload has no merchant account information (tags 26-51).', 'fields' => $fields);
    }
    if ($fields['crc'] === '') {
        return array('ok' => false, 'error' => 'The payload has no checksum (tag 63).', 'fields' => $fields);
    }
    if ($fields['crc_valid'] !== true) {
        return array('ok' => false, 'error' => 'The QR checksum does not match. Re-copy the merchant QR payload.', 'fields' => $fields);
    }

    return array('ok' => true, 'error' => '', 'fields' => $fields);
}

/**
 * Short label for a merchant QR, used in the admin summary.
 *
 * @param array $fields
 * @return string
 */
function bqr_describe_merchant(array $fields)
{
    $parts = array();
    if (!empty($fields['merchant_name'])) {
        $parts[] = $fields['merchant_name'];
    }
    foreach (($fields['merchant_accounts'] ?? array()) as $account) {
        if (!empty($account['gui'])) {
            $parts[] = $account['gui'];
            break;
        }
    }
    if (!empty($fields['merchant_city']) && $fields['merchant_city'] !== 'N/A') {
        $parts[] = $fields['merchant_city'];
    }

    return implode(' - ', $parts);
}
