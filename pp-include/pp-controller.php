<?php
    define('pp_allowed_access', true);

    if (session_status() === PHP_SESSION_NONE) {
        $secureSession = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (string) ($_SERVER['SERVER_PORT'] ?? '') === '443';
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => $secureSession,
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
        session_start();
    }

    if (file_exists(__DIR__ . '/../pp-config.php')) {
        require __DIR__ . '/../pp-config.php';
    }else{
        echo 'System is under maintenance. Please try again later.';
        exit();
    }
    
    function connectDatabase() {
        global $db_host, $db_user, $db_pass, $db_name;

        $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

        if ($conn->connect_error) {
            error_log('Database connection failed: ' . $conn->connect_error);
            die('System is under maintenance. Please try again later.');
        }

        return $conn;
    }

    function generateCsrfToken() {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    function validateCsrfToken($token) {
        if (empty($_SESSION['csrf_token']) || empty($token)) {
            return false;
        }
        return hash_equals($_SESSION['csrf_token'], $token);
    }

    function isAdminActionAllowed($action) {
        $publicActions = ['login', 'forgot-password', 'pp-invoice-payment-link', 'pp-invoice-link'];
        return !in_array($action, $publicActions, true);
    }
    
    function hexToRgba($hex, $opacity = 1.0) {
        // Remove "#" if present
        $hex = ltrim($hex, '#');
    
        // Convert shorthand to full form (e.g., #F53 to #FF5533)
        if (strlen($hex) == 3) {
            $hex = $hex[0] . $hex[0] .
                   $hex[1] . $hex[1] .
                   $hex[2] . $hex[2];
        }
    
        // Extract RGB values
        if (strlen($hex) != 6) {
            return 'Invalid hex color';
        }
    
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
    
        // Clamp opacity between 0 and 1
        $opacity = max(0, min(1, $opacity));
    
        return "rgba($r, $g, $b, $opacity)";
    }

    $date_default_timezone_set = json_decode(getData($db_prefix.'settings', 'WHERE id="1"'), true);
    date_default_timezone_set($date_default_timezone_set['response'][0]['default_timezone']);

    function timeAgo($datetime) {
        $past = new DateTime($datetime);
        $now = new DateTime();
        $diff = $now->diff($past);
    
        if ($diff->y > 0) {
            return $diff->y . ' year' . ($diff->y > 1 ? 's' : '') . ' ago';
        } elseif ($diff->m > 0) {
            return $diff->m . ' month' . ($diff->m > 1 ? 's' : '') . ' ago';
        } elseif ($diff->d > 0) {
            return $diff->d . ' day' . ($diff->d > 1 ? 's' : '') . ' ago';
        } elseif ($diff->h > 0) {
            return $diff->h . ' hour' . ($diff->h > 1 ? 's' : '') . ' ago';
        } elseif ($diff->i > 0) {
            return $diff->i . ' minute' . ($diff->i > 1 ? 's' : '') . ' ago';
        } else {
            return 'Just now';
        }
    }

    function convertToReadableDate($inputDate) {
        // Try to parse with DateTime automatically (ISO format)
        $date = DateTime::createFromFormat('Y-m-d H:i:s', $inputDate);
    
        // If that fails, try common alternative format (d/m/Y)
        if (!$date) {
            $date = DateTime::createFromFormat('d/m/Y', $inputDate);
        }
    
        // If still not valid, return an error message
        if (!$date) {
            return 'Invalid date format';
        }
    
        // Format as: June 11, 2025
        return $date->format('F j, Y');
    }

    function getConnectionStatus($lastdate, $maximumtime) {
        $past = new DateTime($lastdate);
        $now = new DateTime();
        $diffInMinutes = ($now->getTimestamp() - $past->getTimestamp()) / 60;
    
        return $diffInMinutes <= $maximumtime;
    }

    function isNumber($value) {
        return is_numeric($value);
    }

    function safeNumber($value, $default = 0) {
        return (isset($value) && is_numeric($value)) ? (float)$value : $default;
    }

    function isValidEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    function removeBaseUrl($base_url) {
        $current_url = "https://".$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
    
        $modified_url = str_replace($base_url, "", $current_url);
    
        return $modified_url;
    }

    function getAuthorizationHeader() {
        if (function_exists('getallheaders')) {
            $headers = getallheaders();
            if (isset($headers['mh-piprapay-api-key'])) {
                return trim($headers['mh-piprapay-api-key']);
            }
        }
    
        // Fallback for environments where getallheaders() doesn't work
        foreach ($_SERVER as $key => $value) {
            if (stripos($key, 'HTTP_MH_PIPRAPAY_API_KEY') !== false) {
                return trim($value);
            }
        }
    
        return null;
    }

function getCurrentDatetime($format = 'Y-m-d H:i:s') {
        $currentDatetime = new DateTime();

        return $currentDatetime->format($format);
    }    

function convertDateTime($datetime, $daysToSubtract = 0, $newHour = null, $newMinute = null) {
    try {
        $date = new DateTime($datetime);
    } catch (Exception $e) {
        return $datetime;
    }

    if ($daysToSubtract !== 0) {
        $date->modify(($daysToSubtract > 0 ? '-' : '+') . abs($daysToSubtract) . ' days');
    }

    if ($newHour !== null && $newMinute !== null) {
        $date->setTime((int)$newHour, (int)$newMinute);
    }

    return $date->format('Y-m-d h:i A');
}
    
    function extractPathAndQuery($url) {
        $parsedUrl = parse_url($url);
    
        // If no path, return null or empty
        if (!isset($parsedUrl['path'])) {
            return null;
        }
    
        // Remove "/admin/" from path if present
        $path = str_replace("/admin/", "", $parsedUrl['path']);
    
        // Append query if it exists
        if (isset($parsedUrl['query'])) {
            return $path . '?' . $parsedUrl['query'];
        }
    
        return $path;
    }

    function getCurrentUrl() {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || 
                     $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
    
        $host = $_SERVER['HTTP_HOST'];         // e.g., dev.piprapay.com
        $requestUri = $_SERVER['REQUEST_URI']; // e.g., /admin/view-transaction?p=13
    
        return $protocol . $host . $requestUri;
    }

    function maskEmail($email) {
        list($username, $domain) = explode('@', $email);
    
        $firstPart = substr($username, 0, 4);
        $lastPart = substr($username, -2);
    
        $maskedUsername = $firstPart . '****' . $lastPart;
    
        return $maskedUsername . '@' . $domain;
    }

    function maskTest($string) {
        return !empty($string) ? $string[0] : '';
    }
    
    function calculateDiscount($price, $discountPercentage) {
        $discountAmount = ($price * $discountPercentage) / 100;
        $finalPrice = $price - $discountAmount;
        
        return [
            'discountAmount' => $discountAmount,
            'finalPrice' => $finalPrice
        ];
    }
    
    function getUserDeviceInfo() {
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    
        if (preg_match('/mobile/i', $userAgent)) {
            $deviceType = "Mobile";
        } elseif (preg_match('/tablet/i', $userAgent)) {
            $deviceType = "Tablet";
        } else {
            $deviceType = "Desktop";
        }
    
        if (preg_match('/Windows/i', $userAgent)) {
            $os = "Windows";
        } elseif (preg_match('/Mac/i', $userAgent)) {
            $os = "Mac OS";
        } elseif (preg_match('/Linux/i', $userAgent)) {
            $os = "Linux";
        } elseif (preg_match('/Android/i', $userAgent)) {
            $os = "Android";
        } elseif (preg_match('/iPhone|iPad/i', $userAgent)) {
            $os = "iOS";
        } else {
            $os = "Unknown OS";
        }
    
        if (preg_match('/MSIE|Trident/i', $userAgent)) {
            $browser = "Internet Explorer";
        } elseif (preg_match('/Firefox/i', $userAgent)) {
            $browser = "Firefox";
        } elseif (preg_match('/Chrome/i', $userAgent)) {
            $browser = "Chrome";
        } elseif (preg_match('/Safari/i', $userAgent)) {
            $browser = "Safari";
        } elseif (preg_match('/Opera|OPR/i', $userAgent)) {
            $browser = "Opera";
        } elseif (preg_match('/Edge/i', $userAgent)) {
            $browser = "Edge";
        } else {
            $browser = "Unknown Browser";
        }
    
        return [
            'ip_address' => $ipAddress,
            'device' => $deviceType,
            'os' => $os,
            'browser' => $browser
        ];
    }

    // Set a cookie securely (supports all panels)
    function setsCookie($cookieName, $cookieValue, $days = 7) {
        $expiryTime = time() + ($days * 24 * 60 * 60);
    
        $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (string) ($_SERVER['SERVER_PORT'] ?? '') === '443';
    
        setcookie($cookieName, $cookieValue, [
            'expires' => $expiryTime,
            'path' => '/',
            'secure' => $isSecure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
    
    // Check if a cookie exists
    function checkCookie($cookieName) {
        return isset($_COOKIE[$cookieName]);
    }
    
    // Get the value of a cookie
    function getCookie($cookieName) {
        return $_COOKIE[$cookieName] ?? null;
    }
    
    // Logout: invalidate the application token, then clear browser state.
    function logoutCookie() {
        global $db_prefix;
        $adminCookie = $_COOKIE['pp_admin'] ?? '';
        if ($adminCookie !== '' && isset($db_prefix)) {
            $safeCookie = escape_string($adminCookie);
            updateData($db_prefix . 'browser_log', ['status'], ['inactive'], 'cookie="' . $safeCookie . '"');
        }

        $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
            (string) ($_SERVER['SERVER_PORT'] ?? '') === '443';
        foreach ($_COOKIE as $name => $value) {
            setcookie($name, '', [
                'expires' => time() - 3600,
                'path' => '/',
                'secure' => $isSecure,
                'httponly' => true,
                'samesite' => 'Lax'
            ]);
        }

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        session_unset();
        session_destroy();
    }

    function escape_string($value) {
        $conn = connectDatabase();
        $value = mysqli_real_escape_string($conn, $value);

        return $value;
    }   

    function pp_assert_safe_table($tableName) {
        if (!preg_match('/^[A-Za-z0-9_]+$/', (string) $tableName)) {
            die('Invalid table name');
        }
    }

    function getData($tableName, $coloum_name, $type = "* FROM") {
        pp_assert_safe_table($tableName);
        $conn = connectDatabase();

        $sql = "SELECT $type `$tableName` $coloum_name";
        $result = $conn->query($sql);

        $data = [];
        if ($result->num_rows > 0) {
            while($row = $result->fetch_assoc()) {
                $data[] = $row;
            }

            $result->free();
            $conn->close();
            return json_encode(['status' => true, 'response' => $data]);
        } else {
            $conn->close();
            return json_encode(['status' => false, 'response' => []]);
        }
    }

function insertData($tableName, $columns, $values) {
        pp_assert_safe_table($tableName);
        $conn = connectDatabase();

        $columnsString = implode(', ', $columns);
        $placeholders = implode(', ', array_fill(0, count($values), '?'));
        $sql = "INSERT INTO `$tableName` ($columnsString) VALUES ($placeholders)";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return false;
        }
        $types = str_repeat('s', count($values));
        $stmt->bind_param($types, ...$values);
        $result = $stmt->execute();
        $stmt->close();
        $conn->close();
        return $result;
    }

    function updateData($tableName, $columns, $values, $condition) {
        pp_assert_safe_table($tableName);
        $conn = connectDatabase();

        $setClauses = [];
        foreach ($columns as $col) {
            $setClauses[] = "`$col` = ?";
        }
        $setString = implode(', ', $setClauses);

        $sql = "UPDATE `$tableName` SET $setString WHERE $condition";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            $conn->close();
            return false;
        }
        $types = str_repeat('s', count($values));
        $stmt->bind_param($types, ...$values);
        $result = $stmt->execute();
        $stmt->close();
        $conn->close();
        return $result;
    }

function deleteData($tableName, $condition) {
        pp_assert_safe_table($tableName);
        $conn = connectDatabase();

        $sql = "DELETE FROM `$tableName` WHERE $condition";

        if ($conn->query($sql)) {
            $conn->close();
            return true;
        } else {
            $conn->close();
            return false;
        }
    }
    
    function pp_normalize_currency_code($currency) {
        $currency = strtoupper(trim((string) $currency));
        return preg_match('/^[A-Z0-9]{2,10}$/', $currency) ? $currency : '';
    }

    function pp_currency_rate_cache_seconds() {
        if (defined('PP_CURRENCY_RATE_CACHE_SECONDS')) {
            return max(300, (int) PP_CURRENCY_RATE_CACHE_SECONDS);
        }

        return 21600;
    }

    function pp_auto_currency_rates_enabled() {
        if (defined('PP_AUTO_CURRENCY_RATES')) {
            return filter_var(PP_AUTO_CURRENCY_RATES, FILTER_VALIDATE_BOOLEAN);
        }

        return true;
    }

    function pp_currency_rate_is_stale($updatedAt) {
        $timestamp = strtotime((string) $updatedAt);
        if (!$timestamp) {
            return true;
        }

        return (time() - $timestamp) > pp_currency_rate_cache_seconds();
    }

    function pp_get_currency_row($currency) {
        global $db_prefix;

        $currency = pp_normalize_currency_code($currency);
        if ($currency === '') {
            return null;
        }

        $data = json_decode(getData($db_prefix.'currency', 'WHERE currency_code="'.$currency.'"'), true);
        return ($data['status'] ?? false) === true ? $data['response'][0] : null;
    }

    function pp_fetch_realtime_currency_rates($baseCurrency) {
        $baseCurrency = pp_normalize_currency_code($baseCurrency);
        if ($baseCurrency === '') {
            return ['status' => false, 'message' => 'Invalid base currency'];
        }

        $urls = [
            'https://open.er-api.com/v6/latest/' . rawurlencode($baseCurrency),
            'https://api.exchangerate.host/latest?base=' . rawurlencode($baseCurrency),
        ];

        foreach ($urls as $url) {
            $response = false;
            if (function_exists('curl_init')) {
                $curl = curl_init($url);
                curl_setopt_array($curl, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_CONNECTTIMEOUT => 5,
                    CURLOPT_TIMEOUT => 8,
                    CURLOPT_SSL_VERIFYPEER => true,
                    CURLOPT_SSL_VERIFYHOST => 2,
                    CURLOPT_USERAGENT => 'PipraPay Currency Sync',
                ]);
                $response = curl_exec($curl);
                $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
                curl_close($curl);
                if ($httpCode < 200 || $httpCode >= 300) {
                    $response = false;
                }
            } else {
                $context = stream_context_create([
                    'http' => ['timeout' => 8, 'header' => "User-Agent: PipraPay Currency Sync\r\n"],
                    'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
                ]);
                $response = @file_get_contents($url, false, $context);
            }

            if ($response === false || trim((string) $response) === '') {
                continue;
            }

            $decoded = json_decode($response, true);
            $rates = $decoded['rates'] ?? [];
            if (is_array($rates) && !empty($rates)) {
                return ['status' => true, 'base' => $baseCurrency, 'rates' => $rates];
            }
        }

        return ['status' => false, 'message' => 'Unable to fetch live currency rates'];
    }

    function pp_refresh_currency_rates($baseCurrency = null) {
        global $db_prefix;

        if (!pp_auto_currency_rates_enabled()) {
            return false;
        }

        if ($baseCurrency === null || $baseCurrency === '') {
            $settings = pp_get_settings();
            $baseCurrency = $settings['response'][0]['default_currency'] ?? 'USD';
        }

        $baseCurrency = pp_normalize_currency_code($baseCurrency);
        if ($baseCurrency === '') {
            return false;
        }

        $liveRates = pp_fetch_realtime_currency_rates($baseCurrency);
        if (($liveRates['status'] ?? false) !== true) {
            return false;
        }

        $existingCurrencies = json_decode(getData($db_prefix.'currency', ''), true);
        if (($existingCurrencies['status'] ?? false) !== true) {
            return false;
        }

        $now = getCurrentDatetime('Y-m-d H:i:s');
        $rates = $liveRates['rates'];
        $updated = false;

        foreach ($existingCurrencies['response'] as $row) {
            $code = pp_normalize_currency_code($row['currency_code'] ?? '');
            if ($code === '') {
                continue;
            }

            if ($code === $baseCurrency) {
                $rate = 1;
            } elseif (isset($rates[$code]) && is_numeric($rates[$code]) && (float) $rates[$code] > 0) {
                $rate = (float) $rates[$code];
            } else {
                continue;
            }

            if (updateData($db_prefix.'currency', ['currency_rate', 'created_at'], [(string) $rate, $now], 'id = "'.escape_string($row['id']).'"')) {
                $updated = true;
            }
        }

        return $updated;
    }

    function pp_get_currency_rate($currency, $refreshIfNeeded = true) {
        $currency = pp_normalize_currency_code($currency);
        if ($currency === '') {
            return ['status' => false, 'message' => 'Invalid currency'];
        }

        $row = pp_get_currency_row($currency);
        if ($refreshIfNeeded && ($row === null || pp_currency_rate_is_stale($row['created_at'] ?? '--'))) {
            pp_refresh_currency_rates($currency);
            $row = pp_get_currency_row($currency);
        }

        if ($row === null) {
            return ['status' => false, 'message' => 'Currency rate not found'];
        }

        $rate = safeNumber($row['currency_rate'] ?? 0);
        if ($rate <= 0) {
            return ['status' => false, 'message' => 'Currency rate is not available'];
        }

        return ['status' => true, 'rate' => $rate, 'updated_at' => $row['created_at'] ?? '--'];
    }

    function pp_convert_currency($amount, $sourceCurrency, $targetCurrency, $refreshIfNeeded = true) {
        $amount = safeNumber($amount);
        $sourceCurrency = pp_normalize_currency_code($sourceCurrency);
        $targetCurrency = pp_normalize_currency_code($targetCurrency);

        if ($sourceCurrency === '' || $targetCurrency === '') {
            return ['status' => false, 'message' => 'Invalid currency'];
        }

        if ($sourceCurrency === $targetCurrency) {
            return [
                'status' => true,
                'amount' => $amount,
                'source_currency' => $sourceCurrency,
                'target_currency' => $targetCurrency,
                'rate' => 1,
                'converted' => false,
            ];
        }

        $source = pp_get_currency_rate($sourceCurrency, $refreshIfNeeded);
        $target = pp_get_currency_rate($targetCurrency, false);

        if (($source['status'] ?? false) !== true || ($target['status'] ?? false) !== true) {
            if ($refreshIfNeeded) {
                pp_refresh_currency_rates($sourceCurrency);
                $source = pp_get_currency_rate($sourceCurrency, false);
                $target = pp_get_currency_rate($targetCurrency, false);
            }
        }

        if (($source['status'] ?? false) !== true || ($target['status'] ?? false) !== true) {
            return ['status' => false, 'message' => 'Currency rate unavailable'];
        }

        $convertedAmount = $amount * ($target['rate'] / $source['rate']);

        return [
            'status' => true,
            'amount' => $convertedAmount,
            'source_currency' => $sourceCurrency,
            'target_currency' => $targetCurrency,
            'rate' => $target['rate'] / $source['rate'],
            'converted' => true,
        ];
    }
    
    function convertToDefault($amount, $sourceCurrency, $targetCurrency) {
        $converted = pp_convert_currency($amount, $sourceCurrency, $targetCurrency);
        if (($converted['status'] ?? false) === true) {
            return $converted['amount'];
        }

        if ($sourceCurrency === $targetCurrency) {
            return $amount;
        }
    
        $sourceData = json_decode(getData($GLOBALS['db_prefix'].'currency', 'WHERE currency_code="'.pp_normalize_currency_code($sourceCurrency).'"'), true);
        $targetData = json_decode(getData($GLOBALS['db_prefix'].'currency', 'WHERE currency_code="'.pp_normalize_currency_code($targetCurrency).'"'), true);
    
        $sourceRate = floatval($sourceData['response'][0]['currency_rate'] ?? 1) ?: 1;
        $targetRate = floatval($targetData['response'][0]['currency_rate'] ?? 1) ?: 1;
    
        return safeNumber($amount) * ($targetRate / $sourceRate);
    }
            
    function getDataChangeStats($table, $where = '', $dateColumn = 'created_at', $period = 'monthly') {
        if ($period === 'daily') {
            $current_start = date('Y-m-d');
            $prev_start = date('Y-m-d', strtotime('-1 day'));
            $next = date('Y-m-d', strtotime('+1 day'));
        } elseif ($period === 'monthly') {
            $current_start = date('Y-m-01');
            $prev_start = date('Y-m-01', strtotime('-1 month'));
            $next = date('Y-m-01', strtotime('+1 month'));
        } elseif ($period === 'yearly') {
            $current_start = date('Y-01-01');
            $prev_start = date('Y-01-01', strtotime('-1 year'));
            $next = date('Y-01-01', strtotime('+1 year'));
        } else {
            return ['status' => false, 'message' => 'Invalid period'];
        }
        
        if($where == ""){
            $ishaveand = "";
        }else{
            $ishaveand = "AND";
        }
    
        // Current
        $current_where = "$where $ishaveand `$dateColumn` >= '$current_start' AND `$dateColumn` < '$next'";
        $current = json_decode(getData($table, "WHERE $current_where"), true);
        $current_value = isset($current['response']['value']) ? (int)$current['response']['value'] : 0;
    
        // Previous
        $prev_end = $current_start;
        $prev_where = "$where $ishaveand `$dateColumn` >= '$prev_start' AND `$dateColumn` < '$prev_end'";
        $previous = json_decode(getData($table, "WHERE $prev_where"), true);
        $previous_value = isset($previous['response']['value']) ? (int)$previous['response']['value'] : 0;
    
        // Calculate
        $diff = $current_value - $previous_value;
        $percentage = ($previous_value > 0) ? round(($diff / $previous_value) * 100, 2) : 100;
        $direction = ($diff > 0) ? 'up' : (($diff < 0) ? 'down' : 'same');
    
        return [
            'status' => true,
            'current' => $current_value,
            'previous' => $previous_value,
            'change_percent' => $percentage,
            'direction' => $direction
        ];
    }

    function getChart($table, $dateColumn, $period = 'daily', $startDate = null, $endDate = null, $type = '') {
        $conn = connectDatabase();
        // Validate and sanitize inputs (basic)
        $allowedPeriods = ['today', 'daily', 'monthly', 'yearly', 'range'];
        if (!in_array($period, $allowedPeriods)) {
            return json_encode(['error' => 'Invalid period']);
        }
    
        // Prepare SQL parts
        $select = "";
        $groupBy = "";
        $where = "";
    
        // Build date filtering and grouping based on $period
        switch ($period) {
            case 'today':
                $today = date('Y-m-d');
                $where = "WHERE DATE($dateColumn) = '$today'";
                $select = "DATE($dateColumn) as label";
                $groupBy = "DATE($dateColumn)";
                break;
    
            case 'daily':
                // Last 30 days by default
                $startDate = $startDate ?? date('Y-m-d', strtotime('-29 days'));
                $endDate = $endDate ?? date('Y-m-d');
                $where = "WHERE DATE($dateColumn) BETWEEN '$startDate' AND '$endDate'";
                $select = "DATE($dateColumn) as label";
                $groupBy = "DATE($dateColumn)";
                break;
    
            case 'monthly':
                // Last 12 months by default
                $startDate = $startDate ?? date('Y-m-01', strtotime('-11 months'));
                $endDate = $endDate ?? date('Y-m-t');
                $where = "WHERE DATE($dateColumn) BETWEEN '$startDate' AND '$endDate'";
                $select = "DATE_FORMAT($dateColumn, '%Y-%m') as label";
                $groupBy = "DATE_FORMAT($dateColumn, '%Y-%m')";
                break;
    
            case 'yearly':
                // Last 5 years by default
                $startDate = $startDate ?? date('Y-01-01', strtotime('-4 years'));
                $endDate = $endDate ?? date('Y-12-31');
                $where = "WHERE DATE($dateColumn) BETWEEN '$startDate' AND '$endDate'";
                $select = "YEAR($dateColumn) as label";
                $groupBy = "YEAR($dateColumn)";
                break;
    
            case 'range':
                if (!$startDate || !$endDate) {
                    return json_encode(['error' => 'Start date and end date required for range']);
                }
                $where = "WHERE DATE($dateColumn) BETWEEN '$startDate' AND '$endDate'";
                $select = "DATE($dateColumn) as label";
                $groupBy = "DATE($dateColumn)";
                break;
        }
        
        if($type !== ""){
            if($where == ""){
                $where = ' WHERE '.$type;
            }else{
                $where .= ' AND '.$type;
            }
        }
        
        $labels = [];
        $data = [];
        
        $global_data_count = json_decode(getData($table, $where.' GROUP BY '.$groupBy.' ORDER BY '.$groupBy.' ASC', $select.', COUNT(*) as data FROM'), true);
        foreach($global_data_count['response'] as $row){
                $labels[] = $row['label'];
                $data[] = (int)$row['data'];
        }

        return json_encode(['labels' => $labels, 'data' => $data]);
    }
    
    function generateRandomFilename($extension) {
        $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        return bin2hex(random_bytes(16)) . "." . $extension;
    }

    function pp_qr_cache_dir() {
        $dir = __DIR__ . '/../pp-external/media/qr-cache';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        return is_dir($dir) && is_writable($dir) ? $dir : null;
    }

    // Renders a payment QR server-side and caches it, so the browser never waits
    // on a third-party QR API round-trip. Falls back to the remote URL if the
    // directory is not writable or the remote fetch fails.
    function pp_qr_image_url($payload, $size = 300) {
        $payload = (string) $payload;
        $remote = 'https://api.qrserver.com/v1/create-qr-code/?size=' . (int) $size . 'x' . (int) $size . '&data=' . rawurlencode($payload);
        $dir = pp_qr_cache_dir();
        if ($dir === null || $payload === '') {
            return $remote;
        }

        $file = $dir . '/' . hash('sha256', $payload) . '-' . (int) $size . '.png';
        if (is_file($file) && filesize($file) > 0) {
            return pp_get_site_url() . '/pp-external/media/qr-cache/' . basename($file);
        }

        $png = @file_get_contents($remote, false, stream_context_create([
            'http' => ['timeout' => 8, 'header' => "User-Agent: PipraPay\r\n"],
            'ssl'  => ['verify_peer' => true, 'verify_peer_name' => true],
        ]));

        if ($png === false || strlen($png) < 100 || @getimagesizefromstring($png) === false) {
            return $remote;
        }

        $tmp = $file . '.' . bin2hex(random_bytes(4)) . '.tmp';
        if (@file_put_contents($tmp, $png, LOCK_EX) !== false) {
            @rename($tmp, $file);
            @chmod($file, 0644);
            return pp_get_site_url() . '/pp-external/media/qr-cache/' . basename($file);
        }
        @unlink($tmp);
        return $remote;
    }
        
    function uploadImage($file, $max_file_size) {
        $upload_directory = __DIR__ . '/../pp-external/media/';
        
        // ─────────── VALIDATION ───────────
        if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
            return json_encode(['status' => false, 'message' => 'No file uploaded or upload failed.']);
        }
    
        if ($file['size'] > $max_file_size || $file['size'] <= 0 || !is_uploaded_file($file['tmp_name'])) {
            return json_encode(['status' => false, 'message' => 'Invalid uploaded file.']);
        }
    
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $allowed_mime_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $file_info          = pathinfo($file['name']);
        $file_extension     = strtolower($file_info['extension']);

        if (!in_array($file_extension, $allowed_extensions)) {
            return json_encode(['status' => false, 'message' => 'Only JPG, PNG, GIF, and WEBP files are allowed.']);
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $detected_mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($detected_mime, $allowed_mime_types, true) || @getimagesize($file['tmp_name']) === false) {
            return json_encode(['status' => false, 'message' => 'Invalid file type. Only JPG, PNG, GIF, and WEBP images are allowed.']);
        }
    
        if (!is_dir($upload_directory) && !mkdir($upload_directory, 0755, true)) {
            return json_encode(['status' => false, 'message' => 'Upload directory is unavailable.']);
        }

        // ─────────── FILE NAME ───────────
        $random_filename = generateRandomFilename($file_extension);
        $full_path       = $upload_directory . $random_filename;
    
        // ─────────── TRY IMAGICK ───────────
        try {
            if (!extension_loaded('imagick')) {
                throw new Exception('Imagick extension not installed.');
            }
    
            $img = new Imagick($file['tmp_name']);
    
            $hasAlpha = $img->getImageAlphaChannel();
    
            if ($hasAlpha && Imagick::queryFormats('WEBP')) {
                $img->setImageFormat('webp');
                $img->setOption('webp:lossless', 'true');
                $img->setImageCompressionQuality(85);
                $random_filename = generateRandomFilename('webp');
            } elseif (!$hasAlpha && Imagick::queryFormats('JPEG')) {
                $img->setImageFormat('jpeg');
                $img->setImageCompression(Imagick::COMPRESSION_JPEG);
                $img->setImageCompressionQuality(75);
                $random_filename = generateRandomFilename('jpg');
            } else {
                throw new Exception('Required format not supported by Imagick.');
            }
    
            $full_path = $upload_directory . $random_filename;
    
            $img->stripImage();
            $img->writeImage($full_path);
            $img->clear();
            $img->destroy();
    
            return json_encode(['status' => true, 'file' => $random_filename]);
    
        } catch (Exception $e) {
            // ───── FALLBACK: MOVE FILE DIRECTLY ─────
            if (move_uploaded_file($file['tmp_name'], $full_path)) {
                return json_encode([
                    'status' => true,
                    'file'   => $random_filename,
                    'note'   => 'Imagick not used. File uploaded without processing.'
                ]);
            } else {
                return json_encode(['status' => false, 'message' => 'File upload failed without Imagick: ' . $e->getMessage()]);
            }
        }
    }

    // Payment receipt upload: images go through uploadImage, PDFs are stored as is.
    function uploadReceipt($file, $max_file_size) {
        $file_extension = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
        if ($file_extension !== 'pdf') {
            return uploadImage($file, $max_file_size);
        }

        $upload_directory = __DIR__ . '/../pp-external/media/';

        if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
            return json_encode(['status' => false, 'message' => 'No file uploaded or upload failed.']);
        }

        if ($file['size'] > $max_file_size || $file['size'] <= 0 || !is_uploaded_file($file['tmp_name'])) {
            return json_encode(['status' => false, 'message' => 'Invalid uploaded file.']);
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $detected_mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        $handle = @fopen($file['tmp_name'], 'rb');
        $header = $handle ? fread($handle, 5) : '';
        if ($handle) {
            fclose($handle);
        }

        if ($detected_mime !== 'application/pdf' || $header !== '%PDF-') {
            return json_encode(['status' => false, 'message' => 'Invalid file type. Only JPG, PNG, GIF, WEBP images and PDF files are allowed.']);
        }

        if (!is_dir($upload_directory) && !mkdir($upload_directory, 0755, true)) {
            return json_encode(['status' => false, 'message' => 'Upload directory is unavailable.']);
        }

        $random_filename = generateRandomFilename('pdf');
        if (!move_uploaded_file($file['tmp_name'], $upload_directory . $random_filename)) {
            return json_encode(['status' => false, 'message' => 'File upload failed.']);
        }

        return json_encode(['status' => true, 'file' => $random_filename]);
    }

    function deleteImage($file) {
        // Define the local image directory path
        $upload_directory = __DIR__ . '/../pp-external/media/'; // Update path if different
    
        // Sanitize the filename to prevent directory traversal attacks
        $filename = basename($file);
        $full_path = $upload_directory . $filename;
    
        // Check if the file exists
        if (!file_exists($full_path)) {
            return json_encode(["status" => false, "message" => "File not found."]);
        }
    
        // Attempt to delete the file
        if (unlink($full_path)) {
            return json_encode(["status" => true, "message" => "File deleted successfully!"]);
        } else {
            return json_encode(["status" => false, "message" => "Error deleting file."]);
        }
    }

    
    function generatePDF($htmlContent, $paperSize = 'A4', $orientation = 'portrait') {
        if (!class_exists('Dompdf\\Dompdf')) {
            require_once __DIR__ . '/../pp-external/pdf-generator/autoload.php';
        }

        $dompdfClass = 'Dompdf\\Dompdf';
        $dompdf = new $dompdfClass();
        $dompdf->loadHtml($htmlContent);
        $dompdf->setPaper($paperSize, $orientation);
        $dompdf->render();
    
        return $dompdf->output();
    }
    
    function parsePluginHeader($filePath) {
        $headers = [
            'Plugin Name', 'Plugin URI', 'Description', 'Version',
            'Author', 'Author URI', 'License', 'License URI',
            'Text Domain', 'Domain Path', 'Requires at least', 'Requires PHP'
        ];
    
        $pluginData = array_fill_keys($headers, '');
    
        if (!file_exists($filePath)) return $pluginData;
    
        // Isolate scope using include in function
        $plugin_meta = [];
        include $filePath;
    
        if (isset($plugin_meta) && is_array($plugin_meta)) {
            foreach ($headers as $key) {
                if (!empty($plugin_meta[$key])) {
                    $pluginData[$key] = $plugin_meta[$key];
                }
            }
        }
    
        return $pluginData;
    }
    
    function parseThemeHeader($filePath) {
        $headers = [
            'Theme Name', 'Theme URI', 'Description', 'Version',
            'Author', 'Author URI', 'License', 'License URI',
            'Text Domain', 'Domain Path', 'Requires at least', 'Requires PHP'
        ];
    
        // Initialize default structure
        $themeData = array_fill_keys($headers, '');
    
        if (!file_exists($filePath)) return $themeData;
    
        // Include file to access $theme_meta
        include $filePath;
    
        if (isset($theme_meta) && is_array($theme_meta)) {
            foreach ($headers as $key) {
                if (!empty($theme_meta[$key])) {
                    $themeData[$key] = $theme_meta[$key];
                }
            }
        }
    
        return $themeData;
    }

    function parse_readme_header($content) {
        $header = [];
    
        // Match plugin title from === Plugin Name ===
        if (preg_match('/^===\s*(.+?)\s*===/m', $content, $matches)) {
            $header['name'] = trim($matches[1]);
        }
    
        // Match other key-value lines (e.g. Contributors: John)
        if (preg_match_all('/^([^:\r\n]+):\s*(.+)$/m', $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $key = strtolower(trim($match[1]));
                $value = trim($match[2]);
                $header[$key] = $value;
            }
        }
    
        return $header;
    }
        
    function parse_readme_sections($content) {
        $sections = [];
    
        // Normalize line endings
        $content = str_replace("\r\n", "\n", $content);
        $content = str_replace("\r", "\n", $content);
    
        // Split content into lines
        $lines = explode("\n", $content);
    
        $currentSection = 'header'; // before first section
        $sections[$currentSection] = '';
    
        foreach ($lines as $line) {
            // Detect section headers (like == Description ==, == Installation ==, etc.)
            if (preg_match('/^==\s*(.+?)\s*==$/', $line, $matches)) {
                $currentSection = strtolower(trim($matches[1]));
                $sections[$currentSection] = '';
                continue;
            }
    
            // Append line to current section
            $sections[$currentSection] .= $line . "\n";
        }
    
        // Trim whitespace from each section
        foreach ($sections as &$text) {
            $text = trim($text);
        }
    
        return $sections;
    }
    
    function parse_readme_changelog($changelogText) {
        $lines = explode("\n", trim($changelogText));
        $versions = [];
        $currentVersion = '';
        
        foreach ($lines as $line) {
            $line = trim($line);
            
            // Match version line like "= 1.0.0 ="
            if (preg_match('/^=\s*(.+?)\s*=+$/', $line, $matches)) {
                $currentVersion = $matches[1];
                $versions[$currentVersion] = [];
            } elseif (!empty($line) && $currentVersion) {
                // Each bullet point line (starts with "*")
                $line = ltrim($line, '* ');
                $versions[$currentVersion][] = $line;
            }
        }
        
        return $versions;
    }
        
    function deleteFolder($folderPath) {
        if (!is_dir($folderPath)) {
            return false;
        }
    
        $items = scandir($folderPath);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;
    
            $itemPath = $folderPath . DIRECTORY_SEPARATOR . $item;
    
            if (is_dir($itemPath)) {
                deleteFolder($itemPath); // recursive delete
            } else {
                unlink($itemPath); // delete file
            }
        }
    
        return rmdir($folderPath); // finally delete the folder
    }

    $pp_hooks = [];
    
    function add_action($hook_name, $callback) {
        global $pp_hooks;
        $pp_hooks[$hook_name][] = $callback;
    }
    
    function do_action($hook_name, ...$args) {
        global $pp_hooks;
        if (!empty($pp_hooks[$hook_name])) {
            foreach ($pp_hooks[$hook_name] as $callback) {
                call_user_func_array($callback, $args);
            }
        }
    }

    function pp_get_plugin_info($slug) {
        $conn = connectDatabase();
        global $db_prefix;
        
        $slug = mysqli_real_escape_string($conn, $slug);
    
        $query = "SELECT plugin_name, plugin_slug, plugin_dir FROM " . $db_prefix . "plugins WHERE plugin_slug = '$slug'";
        $result = mysqli_query($conn, $query);
    
        if ($result && mysqli_num_rows($result) > 0) {
            $row = mysqli_fetch_assoc($result);
            return $row; // <-- just return the associative array directly
        }
    
        return [];
    }
    
    function pp_get_plugin_setting($slug, $resolveGatewayCurrency = true) {
        $conn = connectDatabase();
        global $db_prefix;
        
        $slug = mysqli_real_escape_string($conn, $slug);
    
        $query = "SELECT plugin_dir, plugin_array FROM ".$db_prefix."plugins WHERE plugin_slug = '$slug'";
        $result = mysqli_query($conn, $query);
    
        if ($result && mysqli_num_rows($result) > 0) {
            $row = mysqli_fetch_assoc($result);
            $settings = json_decode($row['plugin_array'], true);
            if (!is_array($settings)) {
                return [];
            }

            if ($resolveGatewayCurrency && ($row['plugin_dir'] ?? '') === 'payment-gateway' && !empty($GLOBALS['pp_current_gateway_payment_id']) && function_exists('pp_get_transation') && function_exists('pp_resolve_gateway_payment')) {
                $transaction = pp_get_transation($GLOBALS['pp_current_gateway_payment_id']);
                if (($transaction['status'] ?? false) === true) {
                    $payment = pp_resolve_gateway_payment($settings, $transaction);
                    if (($payment['currency'] ?? '') !== '') {
                        $settings['gateway_currency'] = $payment['gateway_currency'] ?? ($settings['currency'] ?? '');
                        $settings['currency'] = $payment['currency'];
                        $settings['is_currency_converted'] = (($payment['is_converted'] ?? false) === true) ? 'enable' : 'disable';
                    }
                }
            }

            return $settings;
        }
    
        return [];
    }
    
    function pp_set_plugin_setting($slug, $dataArray) {
        $conn = connectDatabase();
        global $db_prefix;

        $slug = mysqli_real_escape_string($conn, $slug);
        $jsonData = mysqli_real_escape_string($conn, json_encode($dataArray));

        $query = "UPDATE ".$db_prefix."plugins SET plugin_array = '$jsonData' WHERE plugin_slug = '$slug'";
        $result = mysqli_query($conn, $query);
        // Upsert: cloned/copied plugins may not have a DB row yet
        // (e.g. files present but never activated via plugin manager).
        if ($result && mysqli_affected_rows($conn) === 0) {
            $check = mysqli_query($conn, "SELECT id FROM ".$db_prefix."plugins WHERE plugin_slug = '$slug' LIMIT 1");
            if ($check && mysqli_num_rows($check) === 0) {
                $pluginName = mysqli_real_escape_string($conn, $dataArray['name'] ?? $slug);
                $now = mysqli_real_escape_string($conn, getCurrentDatetime('Y-m-d H:i:s'));
                $result = mysqli_query($conn, "INSERT INTO ".$db_prefix."plugins (plugin_name, plugin_slug, plugin_dir, plugin_array, status, created_at) VALUES ('$pluginName', '$slug', 'payment-gateway', '$jsonData', 'active', '$now')");
            }
        }
        return $result;
    }
    
    function pp_get_theme_setting($slug) {
        $conn = connectDatabase();
        global $db_prefix;
        
        $slug = mysqli_real_escape_string($conn, $slug);
        
        $jsonPath = __DIR__ . '/../pp-content/themes/gateway/'.$slug.'/config.json';
    
        if (file_exists($jsonPath)) {
            $jsonData = file_get_contents($jsonPath);
        
            return json_decode($jsonData, true);
        } else {
            return [];
        }
    }
    
    function pp_set_theme_setting($slug, $dataArray) {
        $conn = connectDatabase();
        global $db_prefix;
        
        $slug = mysqli_real_escape_string($conn, $slug);

        $jsonPath = __DIR__ . '/../pp-content/themes/gateway/'.$slug.'/config.json';
        
        // Encode PHP array to JSON string
        $jsonString = json_encode($dataArray, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    
        // Save JSON to file
        if (file_put_contents($jsonPath, $jsonString)) {
            return true; // success
        } else {
            return false; // failed to write
        }
    }
    
    function pp_trigger_hook($hook_name, ...$args) {
        $conn = connectDatabase();
        global $db_prefix;
    
        $response = json_decode(getData($db_prefix.'plugins','WHERE status="active"'),true);
        if($response['status'] == true){
            foreach($response['response'] as $row){
                $slug = $row['plugin_slug'];
                $pluginFunctions = __DIR__ . "/../pp-content/plugins/{$row['plugin_dir']}/{$slug}/functions.php";
                if (file_exists($pluginFunctions)) {
                    include_once $pluginFunctions;
                }
            }
        }
    
        // trigger all callbacks added to this hook
        do_action($hook_name, ...$args);
    
        // also support your dynamic function style fallback
        if($response['status'] == true){
            foreach($response['response'] as $row){
                $slug = $row['plugin_slug'];
                $functionName = $hook_name . '_' . str_replace('-', '_', $slug);
                if (function_exists($functionName)) {
                    call_user_func_array($functionName, $args);
                }
            }
        }
    }

    function get_theme_path($maindr, $themes, $file) {
        return __DIR__ . "/../pp-content/themes/".$maindr."/".$themes ."/" . $file;
    }
    
    function get_theme_url($file = '') {
        return __DIR__ . "/../pp-content/themes/".$maindr."/".$themes. "/" . $file;
    }
    
    function theme_include($maindr, $themes, $file, $varValue, $varName = 'paymentid') {
        $$varName = $varValue;
        
        $path = get_theme_path($maindr, $themes, $file);
        if (file_exists($path)) {
            include $path;
        } else {
            echo "<!-- Theme file '$file' not found -->";
        }
    }

    function payment_gateway_include($slug, $payment_id) {
        $conn = connectDatabase();
        global $db_prefix;
        
        $res_plugins = json_decode(getData($db_prefix.'plugins', 'WHERE plugin_slug="'.$slug.'" AND status="active"'), true);
        
        if($res_plugins['status'] == true){
            $availability = pp_get_gateway_availability($slug, $payment_id);
            if (($availability['is_available'] ?? false) !== true) {
                echo '<div class="alert alert-warning m-3">'.htmlspecialchars($availability['availability_reason'] ?? 'This payment gateway is not available for this transaction.').'</div>';
                return;
            }

            $path = __DIR__ . "/../pp-content/plugins/".$res_plugins['response'][0]['plugin_dir']."/".$res_plugins['response'][0]['plugin_slug']. "/" . $res_plugins['response'][0]['plugin_slug'].'-class.php';
            if (file_exists($path)) {
                include $path;
                
                $function = str_replace('-', '_', $res_plugins['response'][0]['plugin_slug']) . '_checkout_page';
                if (function_exists($function)) {
                    $GLOBALS['pp_current_gateway_payment_id'] = $payment_id;
                    try {
                        call_user_func($function, $payment_id);
                    } finally {
                        unset($GLOBALS['pp_current_gateway_payment_id']);
                    }
                }
            } else {
                echo "<!-- Theme file '$path' not found -->";
            }
        }else{
            echo "<!-- Gateway not found -->";
        }
    }






    function pp_get_site_url(){
        return 'https://'.$_SERVER['HTTP_HOST'];
    }

    function pp_get_settings() {
        $conn = connectDatabase();
        global $db_prefix;
        
        return json_decode(getData($db_prefix.'settings','WHERE id="1"'),true);
    }
    
    function pp_get_admin($admin_id = 0) {
        $conn = connectDatabase();
        global $db_prefix;
        
        if($admin_id == 0){
            $sql = "";
        }else{
            $sql = "WHERE id='".$admin_id."'";
        }
        
        return json_decode(getData($db_prefix.'admins',' '.$sql),true);
    }

    function pp_get_transation($id = null, $custom_sql = '') {
        $conn = connectDatabase();
        global $db_prefix;
        
        if (!empty($id)) {
           $id = escape_string($id);
           
           $custom_sql = 'WHERE pp_id="'.$id.'"';
        }
        
        return json_decode(getData($db_prefix.'transaction', $custom_sql),true);
    }
    
    function pp_get_invoice($id) {
        $conn = connectDatabase();
        global $db_prefix;
        
        $id = escape_string($id);
        
        return json_decode(getData($db_prefix.'invoice','WHERE i_id="'.$id.'"'),true);
    }
    
    function pp_get_invoice_items($id) {
        $conn = connectDatabase();
        global $db_prefix;
        
        $id = escape_string($id);
        
        return json_decode(getData($db_prefix.'invoice_items','WHERE i_id="'.$id.'"'),true);
    }

    function pp_get_payment_link($id) {
        $conn = connectDatabase();
        global $db_prefix;
        
        $id = escape_string($id);
        
        return json_decode(getData($db_prefix.'payment_link','WHERE pl_id="'.$id.'"'),true);
    }
    
    function pp_get_payment_link_items($id) {
        $conn = connectDatabase();
        global $db_prefix;
        
        $id = escape_string($id);
        
        return json_decode(getData($db_prefix.'payment_link_input','WHERE pl_id ="'.$id.'"'),true);
    }

    function pp_get_sms_data($id) {
        $conn = connectDatabase();
        global $db_prefix;
        
        $id = escape_string($id);
        
        return json_decode(getData($db_prefix.'sms_data','WHERE id="'.$id.'"'),true);
    }

    function pp_get_faq() {
        $conn = connectDatabase();
        global $db_prefix;
        
        return json_decode(getData($db_prefix.'faq','WHERE status = "active" ORDER BY 1 DESC'),true);
    }
    
    /*function pp_verify_transaction($payment_id, $plugin_slug, $payment_method, $transaction_id) {
        $conn = connectDatabase();
        global $db_prefix;
        
        $payment_id = escape_string($payment_id);
        $plugin_slug = escape_string($plugin_slug);
        $payment_method = escape_string($payment_method);
        $transaction_id = escape_string($transaction_id);
        
        $transaction_details = json_decode(getData($db_prefix.'transaction','WHERE pp_id="'.$payment_id.'"'),true);
        if($transaction_details['status'] == true){
            $plugin_setting = pp_get_plugin_setting($plugin_slug);
            
            $transaction_currency = $plugin_setting['currency'];
            
            $transaction_amount = convertToDefault($transaction_details['response'][0]['transaction_amount'], $transaction_details['response'][0]['transaction_currency'], $plugin_setting['currency']);
            $transaction_fee = safeNumber($plugin_setting['fixed_charge']) + ($transaction_amount * (safeNumber($plugin_setting['percent_charge']) / 100));
            $total = $transaction_amount+$transaction_fee;
        }
        
        if($payment_method == "--"){
            return json_decode(getData($db_prefix.'sms_data','WHERE transaction_id="'.$transaction_id.'" AND status="approved" AND amount="'.number_format($total, 2, '.', '').'" OR transaction_id="'.$transaction_id.'" AND status="approved" AND amount="'.number_format($total, 2).'" OR transaction_id="'.$transaction_id.'" AND status="approved" AND amount="'.$total.'"'),true);
        }else{
           return json_decode(getData($db_prefix.'sms_data','WHERE payment_method="'.$payment_method.'" AND transaction_id="'.$transaction_id.'" AND status="approved" AND amount="'.number_format($total, 2, '.', '').'" OR payment_method="'.$payment_method.'" AND transaction_id="'.$transaction_id.'" AND status="approved" AND amount="'.number_format($total, 2).'" OR payment_method="'.$payment_method.'" AND transaction_id="'.$transaction_id.'" AND status="approved" AND amount="'.$total.'"'),true);
        }
    }*/
    
    
    function pp_verify_transaction($payment_id, $plugin_slug, $payment_method, $transaction_id) {
        $conn = connectDatabase();
        global $db_prefix;
        
        $payment_id = escape_string($payment_id);
        $plugin_slug = escape_string($plugin_slug);
        $payment_method = escape_string($payment_method);
        $transaction_id = escape_string($transaction_id);
        
        $transaction_details = json_decode(getData($db_prefix.'transaction','WHERE pp_id="'.$payment_id.'"'),true);
        if($transaction_details['status'] == true){
            $gateway_payment = pp_calculate_gateway_payment_amount($payment_id, $plugin_slug);
            if (($gateway_payment['status'] ?? false) !== true) {
                return json_decode(getData($db_prefix.'sms_data','WHERE transaction_id="45745756754457-fake"'),true);
            }
            
            $total = safeNumber($gateway_payment['total']);
            $min_total = number_format($total, 2, '.', '');
            $max_total = number_format($total + 0.99, 2, '.', '');
        }else{
            return json_decode(getData($db_prefix.'sms_data','WHERE transaction_id="45745756754457-fake"'),true);
        }

        $escaped_payment_method = escape_string($payment_method);
        $escaped_transaction_id = escape_string($transaction_id);
        
        if($payment_method == "--"){
            $sql = "SELECT * FROM `{$db_prefix}sms_data` WHERE transaction_id='{$escaped_transaction_id}' AND status='approved' AND CAST(amount AS DECIMAL(10,2)) BETWEEN '{$min_total}' AND '{$max_total}' LIMIT 1";
        }else{
            $sql = "SELECT * FROM `{$db_prefix}sms_data` WHERE payment_method='{$escaped_payment_method}' AND transaction_id='{$escaped_transaction_id}' AND status='approved' AND CAST(amount AS DECIMAL(10,2)) BETWEEN '{$min_total}' AND '{$max_total}' LIMIT 1";
        }
        
        $result = $conn->query($sql);
        $data = [];
        if ($result && $result->num_rows > 0) {
            $data = [$result->fetch_assoc()];
            $result->free();
        }
        $conn->close();
        return ['status' => !empty($data), 'response' => $data];
    }
        
    
    function pp_check_transaction_exits($transaction_id) {
        $conn = connectDatabase();
        global $db_prefix;

        $transaction_id = escape_string($transaction_id);
         
        return json_decode(getData($db_prefix.'transaction','WHERE payment_verify_id="'.$transaction_id.'"'),true);
    }
    
    function pp_set_transaction_status($payment_id, $status) {
        $conn = connectDatabase();
        global $db_prefix;
        
        $payment_id = escape_string($payment_id);
        $status = escape_string($status);
        if (!in_array($status, ['initialize', 'pending', 'completed', 'failed', 'refunded'], true)) {
            return false;
        }
        
        $transaction_details = json_decode(getData($db_prefix.'transaction','WHERE pp_id="'.$payment_id.'"'),true);
        if($transaction_details['status'] == true){
            $current_status = $transaction_details['response'][0]['transaction_status'] ?? '';
            $allowed_transitions = [
                'initialize' => ['pending', 'completed', 'failed'],
                'pending' => ['pending', 'completed', 'failed'],
                'completed' => ['completed', 'refunded'],
                'failed' => ['failed', 'pending'],
                'refunded' => ['refunded']
            ];
            if (!isset($allowed_transitions[$current_status]) ||
                !in_array($status, $allowed_transitions[$current_status], true)) {
                return false;
            }
            $columns = ['transaction_status'];
            $values = [$status];
            
            $condition = "pp_id = '".$payment_id."'"; 
            updateData($db_prefix.'transaction', $columns, $values, $condition);
            
            return true;
        }else{
            return false;
        }
    }

    function pp_set_transaction_byslip($id, $payment_method_id, $payment_method, $payment_slip_file, $status) {
        $conn = connectDatabase();
        global $db_prefix;
        
        $id = escape_string($id);
        $payment_method_id = escape_string($payment_method_id);
        $payment_method = escape_string($payment_method);
        $status = escape_string($status);
        
        $transaction_details = json_decode(getData($db_prefix.'transaction','WHERE pp_id="'.$id.'"'),true);
        if($transaction_details['status'] == true){
            $current_status = $transaction_details['response'][0]['transaction_status'] ?? '';
            if (!in_array($status, ['pending', 'completed', 'failed', 'refunded'], true) ||
                in_array($current_status, ['completed', 'refunded'], true)) {
                return false;
            }

            $plugin_info = pp_get_plugin_info($payment_method_id);
            if (empty($plugin_info) || ($plugin_info['plugin_dir'] ?? '') !== 'payment-gateway') {
                return false;
            }
            $plugin_setting = pp_get_plugin_setting($payment_method_id);
            if (!is_array($plugin_setting) || ($plugin_setting['status'] ?? '') !== 'enable' || empty($plugin_setting['currency'])) {
                return false;
            }
            
            $gateway_payment = pp_calculate_gateway_payment_amount($id, $payment_method_id);
            if (($gateway_payment['status'] ?? false) !== true) {
                return false;
            }
            
            $transaction_currency = $gateway_payment['currency'];
            $transaction_amount = safeNumber($gateway_payment['amount']);
            $transaction_fee = safeNumber($gateway_payment['fee']);

            $max_file_size = 10 * 1024 * 1024; 
            
            $transaction_slip = json_decode(uploadReceipt($payment_slip_file, $max_file_size), true);
            // A failed upload must not be reported as a submitted receipt.
            if (($transaction_slip['status'] ?? false) !== true) {
                return false;
            }

            $transaction_slip = 'https://'.$_SERVER['HTTP_HOST'].'/pp-external/media/'.$transaction_slip['file'];

            $columns = ['payment_method_id', 'payment_method', 'payment_verify_way', 'payment_verify_id', 'transaction_status', 'transaction_amount', 'transaction_fee', 'transaction_currency'];
            $values = [$payment_method_id, $payment_method, 'slip', $transaction_slip, $status, $transaction_amount, $transaction_fee, $transaction_currency];

            $condition = "pp_id = '".$id."'";
            updateData($db_prefix.'transaction', $columns, $values, $condition);

            if (function_exists('pp_trigger_hook')) {
                if ($status === 'pending') {
                    // Customer submitted the receipt, waiting for verification.
                    pp_trigger_hook('pp_transaction_pending', $id);
                } else {
                    pp_trigger_hook('pp_transaction_ipn', $id);
                }
            }

            return true;
        }else{
            return false;
        }
    }

    function pp_set_transaction_byid($id, $payment_method_id, $payment_method, $sender, $transaction_id, $status, $sms_data = 0) {
        global $db_prefix;

        if (!preg_match('/^[a-f0-9]{32}$/i', (string) $id) ||
            !preg_match('/^[A-Za-z0-9_-]{1,100}$/', (string) $transaction_id) ||
            !in_array($status, ['pending', 'completed', 'failed', 'refunded'], true)) {
            return false;
        }
        if ($sms_data !== 0 && (!ctype_digit((string) $sms_data) || (int) $sms_data < 1)) {
            return false;
        }

        $transaction = pp_get_transation($id);
        if (($transaction['status'] ?? false) !== true) {
            return false;
        }
        $row = $transaction['response'][0];
        if (in_array($row['transaction_status'] ?? '', ['completed', 'refunded'], true)) {
            return false;
        }

        $plugin_info = pp_get_plugin_info($payment_method_id);
        $plugin_setting = pp_get_plugin_setting($payment_method_id);
        if (empty($plugin_info) || ($plugin_info['plugin_dir'] ?? '') !== 'payment-gateway' ||
            !is_array($plugin_setting) || ($plugin_setting['status'] ?? '') !== 'enable' ||
            empty($plugin_setting['currency'])) {
            return false;
        }

        $conn = connectDatabase();
        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare("SELECT status FROM `{$db_prefix}sms_data` WHERE id = ? AND status = 'approved' FOR UPDATE");
            if (!$stmt) {
                throw new RuntimeException('Unable to prepare payment claim');
            }
            $smsId = (int) $sms_data;
            $stmt->bind_param('i', $smsId);
            if (!$stmt->execute()) {
                throw new RuntimeException('Unable to claim payment');
            }
            if ($sms_data !== 0 && $stmt->get_result()->num_rows !== 1) {
                throw new RuntimeException('Payment already used');
            }
            $stmt->close();

            $gateway_payment = pp_calculate_gateway_payment_amount($id, $payment_method_id);
            if (($gateway_payment['status'] ?? false) !== true) {
                throw new RuntimeException('Gateway is not available');
            }
            $amount = safeNumber($gateway_payment['amount']);
            $fee = safeNumber($gateway_payment['fee']);
            $currency = pp_normalize_currency_code($gateway_payment['currency'] ?? $plugin_setting['currency']);
            $stmt = $conn->prepare("UPDATE `{$db_prefix}transaction` SET payment_method_id=?, payment_method=?, payment_verify_way='id', payment_sender_number=?, payment_verify_id=?, transaction_status=?, transaction_amount=?, transaction_fee=?, transaction_currency=? WHERE pp_id=? AND transaction_status NOT IN ('completed','refunded') AND (payment_verify_id='--' OR payment_verify_id=? )");
            if (!$stmt) {
                throw new RuntimeException('Unable to prepare transaction update');
            }
            $amountText = (string) $amount;
            $feeText = (string) $fee;
            $stmt->bind_param('ssssssssss', $payment_method_id, $payment_method, $sender, $transaction_id, $status, $amountText, $feeText, $currency, $id, $transaction_id);
            if (!$stmt->execute() || $stmt->affected_rows !== 1) {
                throw new RuntimeException('Transaction was already processed');
            }
            $stmt->close();

            if ($sms_data !== 0) {
                $stmt = $conn->prepare("UPDATE `{$db_prefix}sms_data` SET status='used' WHERE id=? AND status='approved'");
                $stmt->bind_param('i', $smsId);
                if (!$stmt->execute() || $stmt->affected_rows !== 1) {
                    throw new RuntimeException('Payment claim failed');
                }
                $stmt->close();
            }
            $conn->commit();
        } catch (Throwable $exception) {
            $conn->rollback();
            $conn->close();
            return false;
        }
        $conn->close();
        if (function_exists('pp_trigger_hook')) {
            if ($status === 'pending') {
                // Customer submitted the payment reference, waiting for verification.
                pp_trigger_hook('pp_transaction_pending', $id);
            } else {
                pp_trigger_hook('pp_transaction_ipn', $id);
            }
        }
        return true;
    }

    function get_invoice_link($invoice_id){
        $invoice = pp_get_invoice($invoice_id);
        
        if($invoice['status'] == true){
            return 'https://'.$_SERVER['HTTP_HOST'].'/invoice/'.$invoice_id;
        }else{
            return false;
        }
    }
    
    function pp_get_paymentlink($pp_id){
        $payment = pp_get_transation($pp_id);
        
        if($payment['status'] == true){
            return 'https://'.$_SERVER['HTTP_HOST'].'/payment/'.$pp_id;
        }else{
            return false;
        }
    }
        
    function pp_get_support_links() {
        $conn = connectDatabase();
        global $db_prefix;
    
        $res = json_decode(getData($db_prefix.'settings', ''), true);
    
        return [
            'facebook_messenger' => [
                'url' => $res['response'][0]['facebook_messenger'] ?? '',
                'image' => '',
                'text' => 'Click here to chat via Messenger.'
            ],
            'support_email_address' => [
                'url' => 'mailto:' . ($res['response'][0]['support_email_address'] ?? ''),
                'image' => '',
                'text' => 'Click here to send us an email.'
            ],
            'support_phone_number' => [
                'url' => 'tel:' . ($res['response'][0]['support_phone_number'] ?? ''),
                'image' => '',
                'text' => 'Click here to call us.'
            ],
            'whatsapp_number' => [
                'url' => $res['response'][0]['whatsapp_number'] ?? '',
                'image' => '',
                'text' => 'Chat with us on WhatsApp.'
            ],
            'facebook_page' => [
                'url' => $res['response'][0]['facebook_page'] ?? '',
                'image' => '',
                'text' => 'Visit our Facebook page.'
            ],
            'telegram' => [
                'url' => $res['response'][0]['telegram'] ?? '',
                'image' => '',
                'text' => 'Join us on Telegram.'
            ],
            'support_website' => [
                'url' => $res['response'][0]['support_website'] ?? '',
                'image' => '',
                'text' => 'Visit our website.'
            ],
            'youtube_channel' => [
                'url' => $res['response'][0]['youtube_channel'] ?? '',
                'image' => '',
                'text' => 'Subscribe to our YouTube channel.'
            ]
        ];
    }

    
    function pp_normalize_gateway_currency_list($value, $fallbackCurrency = '') {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                $value = $decoded;
            } else {
                $value = preg_split('/[\s,|]+/', $value);
            }
        }

        if (!is_array($value)) {
            $value = [];
        }

        $currencies = [];
        foreach ($value as $currency) {
            $currency = pp_normalize_currency_code($currency);
            if ($currency !== '') {
                $currencies[] = $currency;
            }
        }

        $fallbackCurrency = pp_normalize_currency_code($fallbackCurrency);
        if (empty($currencies) && $fallbackCurrency !== '') {
            $currencies[] = $fallbackCurrency;
        }

        return array_values(array_unique($currencies));
    }

    function pp_gateway_setting_enabled($value, $default = false) {
        if ($value === null || $value === '') {
            return $default;
        }

        if (is_bool($value)) {
            return $value;
        }

        return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'enable', 'enabled', 'on'], true);
    }

    function pp_resolve_gateway_payment($plugin_setting, $transaction_details) {
        $transaction = $transaction_details['response'][0] ?? [];
        $transactionCurrency = pp_normalize_currency_code($transaction['transaction_currency'] ?? '');
        $gatewayCurrency = pp_normalize_currency_code($plugin_setting['gateway_currency'] ?? ($plugin_setting['currency'] ?? $transactionCurrency));
        $directCurrencies = pp_normalize_gateway_currency_list($plugin_setting['direct_currencies'] ?? [], $gatewayCurrency);
        $supportedCurrencies = pp_normalize_gateway_currency_list($plugin_setting['supported_currencies'] ?? [], '');
        $legacySupportedCurrencies = !array_key_exists('direct_currencies', $plugin_setting) || trim((string) ($plugin_setting['direct_currencies'] ?? '')) === '';
        if ($legacySupportedCurrencies && empty($supportedCurrencies)) {
            $supportedCurrencies = pp_normalize_gateway_currency_list($plugin_setting['supported_currencies'] ?? [], $gatewayCurrency);
        }
        $allowConversion = pp_gateway_setting_enabled($plugin_setting['allow_currency_conversion'] ?? null, false);
        $baseAmount = safeNumber($transaction['transaction_amount'] ?? 0) + safeNumber($transaction['transaction_fee'] ?? 0);
        $payableCurrency = $gatewayCurrency ?: $transactionCurrency;
        $payableAmount = $baseAmount;
        $isConverted = false;
        $isAvailable = true;
        $reason = 'Available';

        if (($plugin_setting['status'] ?? '') !== 'enable') {
            $isAvailable = false;
            $reason = 'Temporarily unavailable';
        } elseif ($transactionCurrency === '' || $gatewayCurrency === '') {
            $isAvailable = false;
            $reason = 'Currency is not configured';
        } elseif (in_array($transactionCurrency, $directCurrencies, true)) {
            $payableCurrency = $transactionCurrency;
            $payableAmount = $baseAmount;
            $reason = 'Direct currency';
        } elseif ($allowConversion && in_array($transactionCurrency, $supportedCurrencies, true)) {
            $conversion = pp_convert_currency($baseAmount, $transactionCurrency, $gatewayCurrency);
            if (($conversion['status'] ?? false) === true) {
                $payableAmount = $conversion['amount'];
                $payableCurrency = $gatewayCurrency;
                $isConverted = $transactionCurrency !== $gatewayCurrency;
                $reason = $isConverted ? 'Currency converted from '.$transactionCurrency.' to '.$gatewayCurrency : 'Supported currency';
            } else {
                $isAvailable = false;
                $reason = $conversion['message'] ?? 'Currency rate unavailable';
            }
        } else {
            $isAvailable = false;
            $allowedCurrencies = array_values(array_unique(array_merge($directCurrencies, $allowConversion ? $supportedCurrencies : [])));
            $reason = empty($allowedCurrencies) ? 'Currency is not supported' : 'Supports '.implode(', ', $allowedCurrencies).' only';
        }

        return [
            'is_available' => $isAvailable,
            'reason' => $reason,
            'amount' => $payableAmount,
            'currency' => $payableCurrency ?: $transactionCurrency,
            'gateway_currency' => $gatewayCurrency ?: $transactionCurrency,
            'transaction_currency' => $transactionCurrency,
            'direct_currencies' => $directCurrencies,
            'supported_currencies' => $supportedCurrencies,
            'is_converted' => $isConverted,
        ];
    }

    function pp_build_gateway_availability($plugin, $plugin_setting, $transaction_details) {
        $payment = pp_resolve_gateway_payment($plugin_setting, $transaction_details);
        $payableAmount = safeNumber($payment['amount'] ?? 0);
        $payableCurrency = pp_normalize_currency_code($payment['currency'] ?? '');
        $gatewayCurrency = pp_normalize_currency_code($payment['gateway_currency'] ?? $payableCurrency);
        $isAvailable = ($payment['is_available'] ?? false) === true;
        $reason = $payment['reason'] ?? 'Available';
        $showUnavailable = pp_gateway_setting_enabled($plugin_setting['show_when_unavailable'] ?? null, true);

        $min = $plugin_setting['min_amount'] ?? '';
        $max = $plugin_setting['max_amount'] ?? '';
        if ($isAvailable && $min !== '' && $min !== '--' && $payableAmount < safeNumber($min)) {
            $isAvailable = false;
            $minVal = safeNumber($min);
            $reason = 'Min. '.(floor($minVal) == $minVal ? number_format($minVal, 0) : number_format($minVal, 2)).' '.$payableCurrency;
        }
        if ($isAvailable && $max !== '' && $max !== '--' && safeNumber($max) > 0 && $payableAmount > safeNumber($max)) {
            $isAvailable = false;
            $maxVal = safeNumber($max);
            $reason = 'Max. '.(floor($maxVal) == $maxVal ? number_format($maxVal, 0) : number_format($maxVal, 2)).' '.$payableCurrency;
        }

        $logoPath = __DIR__ . '/../pp-content/plugins/'.$plugin['plugin_dir'].'/'.$plugin['plugin_slug'].'/assets/icon.png';
        if (!file_exists($logoPath)) {
            $isAvailable = false;
            $reason = 'Gateway icon is missing';
        }

        return [
            'plugin_name' => $plugin_setting['display_name'] ?? $plugin['plugin_name'] ?? $plugin['plugin_slug'],
            'plugin_slug' => $plugin['plugin_slug'],
            'plugin_logo' => 'https://'.$_SERVER['HTTP_HOST'].'/pp-content/plugins/'.$plugin['plugin_dir'].'/'.$plugin['plugin_slug'].'/assets/icon.png',
            'fixed_charge' => safeNumber($plugin_setting['fixed_charge'] ?? 0),
            'percent_charge' => safeNumber($plugin_setting['percent_charge'] ?? 0),
            'currency' => $payableCurrency ?: $gatewayCurrency,
            'gateway_currency' => $gatewayCurrency ?: $payableCurrency,
            'transaction_currency' => $payment['transaction_currency'] ?? '',
            'direct_currencies' => $payment['direct_currencies'] ?? [],
            'supported_currencies' => $payment['supported_currencies'] ?? [],
            'amount' => number_format($payableAmount, 2, '.', ''),
            'is_available' => $isAvailable,
            'is_converted' => ($payment['is_converted'] ?? false) === true,
            'availability_reason' => $reason,
            'show_when_unavailable' => $showUnavailable,
        ];
    }

    function pp_calculate_gateway_payment_amount($payment_id, $plugin_slug) {
        $transaction_details = pp_get_transation($payment_id);
        $plugin_setting = pp_get_plugin_setting($plugin_slug, false);
        if (($transaction_details['status'] ?? false) !== true || !is_array($plugin_setting) || empty($plugin_setting)) {
            return [
                'status' => false,
                'amount' => 0,
                'fee' => 0,
                'total' => 0,
                'currency' => '',
                'gateway_currency' => '',
                'is_converted' => false,
                'message' => 'Gateway or transaction not found',
            ];
        }

        $paymentTransaction = $transaction_details;
        if (isset($paymentTransaction['response'][0])) {
            $paymentTransaction['response'][0]['transaction_fee'] = 0;
        }
        $payment = pp_resolve_gateway_payment($plugin_setting, $paymentTransaction);
        if (($payment['is_available'] ?? false) !== true) {
            return [
                'status' => false,
                'amount' => 0,
                'fee' => 0,
                'total' => 0,
                'currency' => $payment['currency'] ?? '',
                'gateway_currency' => $payment['gateway_currency'] ?? '',
                'is_converted' => ($payment['is_converted'] ?? false) === true,
                'message' => $payment['reason'] ?? 'Gateway is not available',
            ];
        }

        $amount = safeNumber($payment['amount'] ?? 0);
        $fee = safeNumber($plugin_setting['fixed_charge'] ?? 0) + ($amount * safeNumber($plugin_setting['percent_charge'] ?? 0) / 100);

        return [
            'status' => true,
            'amount' => $amount,
            'fee' => $fee,
            'total' => $amount + $fee,
            'currency' => $payment['currency'] ?? '',
            'gateway_currency' => $payment['gateway_currency'] ?? '',
            'transaction_currency' => $payment['transaction_currency'] ?? '',
            'is_converted' => ($payment['is_converted'] ?? false) === true,
            'message' => $payment['reason'] ?? 'Available',
        ];
    }

    function pp_get_gateway_payable_currency($payment_id, $plugin_slug) {
        $payment = pp_calculate_gateway_payment_amount($payment_id, $plugin_slug);
        return pp_normalize_currency_code($payment['currency'] ?? '');
    }

    function pp_get_gateway_payable_amount($payment_id, $plugin_slug) {
        $payment = pp_calculate_gateway_payment_amount($payment_id, $plugin_slug);
        return safeNumber($payment['total'] ?? 0);
    }

    function pp_get_gateway_payable_fee($payment_id, $plugin_slug) {
        $payment = pp_calculate_gateway_payment_amount($payment_id, $plugin_slug);
        return safeNumber($payment['fee'] ?? 0);
    }

    function pp_get_gateway_availability($plugin_slug, $payment_id) {
        global $db_prefix;

        $plugin_slug = escape_string($plugin_slug);
        $plugins = json_decode(getData($db_prefix.'plugins', 'WHERE plugin_dir="payment-gateway" AND plugin_slug="'.$plugin_slug.'" AND status="active"'), true);
        $transaction_details = pp_get_transation($payment_id);

        if (($plugins['status'] ?? false) !== true || ($transaction_details['status'] ?? false) !== true) {
            return ['is_available' => false, 'availability_reason' => 'Gateway or transaction not found'];
        }

        $plugin_setting = pp_get_plugin_setting($plugin_slug);
        if (!is_array($plugin_setting) || empty($plugin_setting) || $plugin_setting === '--') {
            return ['is_available' => false, 'availability_reason' => 'Gateway is not configured'];
        }

        return pp_build_gateway_availability($plugins['response'][0], $plugin_setting, $transaction_details);
    }

    function pp_get_payment_gateways($category, $payment_id, $includeUnavailable = true) {
        global $db_prefix;
        
        $res_plugins = json_decode(getData($db_prefix.'plugins', 'WHERE plugin_dir="payment-gateway" AND status="active"'), true);
        $transaction_details = pp_get_transation($payment_id);
        $data = [];
        $availableCount = 0;
        
        if(($res_plugins['status'] ?? false) == true && ($transaction_details['status'] ?? false) == true){
            foreach($res_plugins['response'] as $plugins){
                $plugin_setting = pp_get_plugin_setting($plugins['plugin_slug']);
                if (!is_array($plugin_setting) || empty($plugin_setting) || ($plugin_setting['category'] ?? '') !== $category) {
                    continue;
                }

                $gateway = pp_build_gateway_availability($plugins, $plugin_setting, $transaction_details);
                if (($gateway['is_available'] ?? false) === true) {
                    $availableCount++;
                }

                if (($gateway['is_available'] ?? false) === true || ($includeUnavailable && ($gateway['show_when_unavailable'] ?? true))) {
                    $data[] = $gateway;
                }
            }
        }

        usort($data, static function ($a, $b) {
            return (int) empty($a['is_available']) <=> (int) empty($b['is_available']);
        });
            
        return [ "status" => $availableCount > 0, "response" => $data, "available_count" => $availableCount];
    }

    function pp_gateway_category_key($category) {
        $key = strtolower(trim((string) $category));
        $key = preg_replace('/[^a-z0-9]+/', '-', $key);
        $key = trim($key, '-');
        return $key !== '' ? $key : 'gateway';
    }

    function pp_gateway_category_meta($category, $titleStyle = 'full') {
        $normalizedCategory = strtolower(trim((string) $category));
        $meta = [
            'mobile banking' => ['title' => $titleStyle === 'short' ? 'MFS' : 'Mobile Banking', 'icon' => 'fas fa-mobile-screen-button'],
            'ibanking' => ['title' => $titleStyle === 'short' ? 'iBanking' : 'IBanking', 'icon' => 'fas fa-building-columns'],
            'international' => ['title' => 'International', 'icon' => 'fas fa-globe'],
            'crypto' => ['title' => 'Crypto', 'icon' => 'fab fa-bitcoin'],
        ];

        return $meta[$normalizedCategory] ?? ['title' => trim((string) $category), 'icon' => 'fas fa-wallet'];
    }

    function pp_get_gateway_category_tabs($payment_id, $includeUnavailable = true, $titleStyle = 'full') {
        global $db_prefix;

        $res_plugins = json_decode(getData($db_prefix.'plugins', 'WHERE plugin_dir="payment-gateway" AND status="active"'), true);
        $categories = [];
        $tabs = [];

        if (($res_plugins['status'] ?? false) !== true) {
            return $tabs;
        }

        foreach ($res_plugins['response'] as $plugin) {
            $pluginSettings = json_decode($plugin['plugin_array'] ?? '', true);
            if (!is_array($pluginSettings)) {
                continue;
            }

            $category = trim((string) ($pluginSettings['category'] ?? ''));
            if ($category === '' || $category === '--') {
                continue;
            }

            $categories[$category] = $category;
        }

        foreach ($categories as $category) {
            $gateways = pp_get_payment_gateways($category, $payment_id, $includeUnavailable);
            if (empty($gateways['response'])) {
                continue;
            }

            $key = pp_gateway_category_key($category);
            $baseKey = $key;
            $counter = 2;
            while (isset($tabs[$key])) {
                $key = $baseKey.'-'.$counter;
                $counter++;
            }

            $meta = pp_gateway_category_meta($category, $titleStyle);
            $tabs[$key] = [
                'title' => $meta['title'],
                'category' => $category,
                'icon' => $meta['icon'],
                'gateways' => $gateways,
            ];
        }

        return $tabs;
    }
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    

?>