<?php
    if (file_exists(__DIR__."/../pp-config.php")) {
        if (file_exists(__DIR__.'/../maintenance.lock')) {
            if (file_exists(__DIR__.'/../pp-include/pp-maintenance.php')) {
               include(__DIR__."/../pp-include/pp-maintenance.php");
            }else{
                die('System is under maintenance. Please try again later.');
            }
            exit();
        }else{
            if (file_exists(__DIR__.'/../pp-include/pp-controller.php')) {
                include(__DIR__."/../pp-include/pp-controller.php");
            }else{
                echo 'System is under maintenance. Please try again later.';
                exit();
            }
            
            if (file_exists(__DIR__.'/../pp-include/pp-model.php')) {
                include(__DIR__."/../pp-include/pp-model.php");
            }else{
                echo 'System is under maintenance. Please try again later.';
                exit();
            }
            
            if (file_exists(__DIR__.'/../pp-include/pp-view.php')) {
                include(__DIR__."/../pp-include/pp-view.php");
            }else{
                echo 'System is under maintenance. Please try again later.';
                exit();
            }
        }
    }else{
?>
        <script>
            location.href="https://<?php echo $_SERVER['HTTP_HOST']?>/install/";
        </script>
<?php
        exit();
    }
    
    if (!defined('pp_allowed_access')) {
        die('Direct access not allowed');
    }
    
    header('Content-Type: application/json; charset=utf-8');
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Methods: POST, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Authorization, mh-piprapay-api-key");
    header("Access-Control-Max-Age: 86400");
    header("X-Content-Type-Options: nosniff");

    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
    header("X-Frame-Options: SAMEORIGIN");
    header("X-XSS-Protection: 1; mode=block");
        
    if(isset($_GET['name'])){
        $endpoint_type = trim((string) $_GET['name']);
        if (!in_array($endpoint_type, ['create-charge', 'verify-payments'], true)) {
            http_response_code(404);
            echo json_encode(["status" => false, "message" => "Unknown endpoint"]);
            exit;
        }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            header('Allow: POST, OPTIONS');
            echo json_encode(["status" => false, "message" => "Method not allowed"]);
            exit;
        }
        $contentLength = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
        if ($contentLength > 65536) {
            http_response_code(413);
            echo json_encode(["status" => false, "message" => "Request too large"]);
            exit;
        }
        $rate_limit_file = __DIR__ . '/../cache/api_rate_limit.json';
        $rate_limit_window = 60;
        $rate_limit_max = $endpoint_type === 'verify-payments' ? 60 : 30;
        $rate_limit_key = hash('sha256', ($endpoint_type ?: 'unknown') . '|' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        $rate_limit_data = [];
        if (is_file($rate_limit_file)) {
            $rate_limit_data = json_decode((string) file_get_contents($rate_limit_file), true) ?? [];
        }
        $now = time();
        $entry = $rate_limit_data[$rate_limit_key] ?? ['count' => 0, 'window_start' => $now];
        if (($now - (int) ($entry['window_start'] ?? 0)) >= $rate_limit_window) {
            $entry = ['count' => 0, 'window_start' => $now];
        }
        $entry['count']++;
        $rate_limit_data[$rate_limit_key] = $entry;
        foreach ($rate_limit_data as $key => $item) {
            if ($now - (int) ($item['window_start'] ?? 0) >= $rate_limit_window) {
                unset($rate_limit_data[$key]);
            }
        }
        @mkdir(dirname($rate_limit_file), 0755, true);
        file_put_contents($rate_limit_file, json_encode($rate_limit_data), LOCK_EX);
        if ($entry['count'] > $rate_limit_max) {
            header('Retry-After: ' . $rate_limit_window);
            http_response_code(429);
            echo json_encode(["status" => false, "message" => "Too many requests"]);
            exit;
        }
        
        if($endpoint_type == "create-charge") {
            $received_api_key = getAuthorizationHeader();
        
            $configured_api_key = $global_setting_response['response'][0]['api_key'] ?? '';
            if (empty($received_api_key) || empty($configured_api_key) || !hash_equals($configured_api_key, $received_api_key)) {
                http_response_code(401);
                echo json_encode(["status" => false, "message" => "Unauthorized request"]);
                exit;
            }
        
            $json = file_get_contents("php://input");
            $data = json_decode($json, true);
            if (!is_array($data) || json_last_error() !== JSON_ERROR_NONE) {
                http_response_code(400);
                echo json_encode(["status" => false, "message" => "Invalid JSON request"]);
                exit;
            }
        
            // Required field validation
            $required_fields = ['full_name', 'email_mobile', 'amount', 'metadata', 'redirect_url', 'cancel_url', 'webhook_url', 'return_type', 'currency'];
            $missing_fields = [];
        
            foreach ($required_fields as $field) {
                if (empty($data[$field])) {
                    $missing_fields[] = $field;
                }
            }
        
            if (!empty($missing_fields)) {
                http_response_code(400);
                echo json_encode(["status" => false, "message" => "Missing required field(s): " . implode(', ', $missing_fields)]);
                exit;
            }
        
            // Proceed if all fields are present
            $full_name = trim((string) $data['full_name']);
            $email_mobile = trim((string) $data['email_mobile']);
            $amount = $data['amount'];
            $redirect_url = trim((string) $data['redirect_url']);
            $cancel_url = trim((string) $data['cancel_url']);
            $webhook_url = trim((string) $data['webhook_url']);
            $return_type = trim((string) $data['return_type']);
            $currency = strtoupper(trim((string) $data['currency']));

            $supported_currencies = ['BDT', 'USD', 'EUR', 'GBP', 'INR', 'PKR', 'AED', 'SAR', 'CAD', 'AUD', 'JPY', 'CNY'];
            $urls = [$redirect_url, $cancel_url, $webhook_url];
            if ($full_name === '' || strlen($full_name) > 150 || strlen($email_mobile) > 150 ||
                !is_numeric($amount) || (float) $amount <= 0 || strlen((string) $amount) > 20 ||
                !in_array($currency, $supported_currencies, true) ||
                $return_type === '' || strlen($return_type) > 30 ||
                count(array_filter($urls, static function ($url) {
                    if (filter_var($url, FILTER_VALIDATE_URL) === false) {
                        return true;
                    }
                    $scheme = parse_url($url, PHP_URL_SCHEME);
                    $host = parse_url($url, PHP_URL_HOST) ?: '';
                    $isLocal = in_array($host, ['localhost', '127.0.0.1', '::1'], true);
                    return $scheme !== 'https' && !$isLocal;
                })) > 0) {
                http_response_code(400);
                echo json_encode(["status" => false, "message" => "Invalid request data"]);
                exit;
            }

            $full_name = escape_string($full_name);
            $email_mobile = escape_string($email_mobile);
            $amount = escape_string((string) $amount);
            $meta_data = json_encode($data['metadata']);
            $redirect_url = escape_string($redirect_url);
            $cancel_url = escape_string($cancel_url);
            $webhook_url = escape_string($webhook_url);
            $return_type = escape_string($return_type);
            $currency = escape_string($currency);
        
            $c_id = isset($data['c_id']) ? trim((string) $data['c_id']) : '--';
            $product_name = isset($data['product_name']) ? trim((string) $data['product_name']) : '--';
            $product_description = isset($data['product_description']) ? trim((string) $data['product_description']) : '--';
            $product_meta_value = $data['product_meta'] ?? '--';
            $metadata_json = json_encode($data['metadata']);
            $product_meta = json_encode($product_meta_value);
            if (strlen($c_id) > 100 || strlen($product_name) > 255 || strlen($product_description) > 1000 ||
                $metadata_json === false || strlen($metadata_json) > 10000 ||
                $product_meta === false || strlen($product_meta) > 10000) {
                http_response_code(400);
                echo json_encode(["status" => false, "message" => "Invalid request data"]);
                exit;
            }
            $c_id = escape_string($c_id);
            $product_name = escape_string($product_name);
            $product_description = escape_string($product_description);
            $product_meta = escape_string($product_meta);
        
            $pp_id = bin2hex(random_bytes(16));
        
            $columns = ['pp_id', 'c_id', 'c_name', 'c_email_mobile', 'transaction_amount', 'transaction_fee', 'transaction_refund_amount', 'transaction_currency', 'transaction_redirect_url', 'transaction_return_type', 'transaction_cancel_url', 'transaction_webhook_url', 'transaction_metadata', 'transaction_status', 'transaction_product_name', 'transaction_product_description', 'transaction_product_meta', 'created_at'];
            $values = [$pp_id, $c_id, $full_name, $email_mobile, safeNumber($amount), 0, 0, $currency, $redirect_url, $return_type, $cancel_url, $webhook_url, $meta_data, 'initialize', $product_name, $product_description, $product_meta, getCurrentDatetime('Y-m-d H:i:s')];
        
            insertData($db_prefix.'transaction', $columns, $values);

            $existing_customer = json_decode(getData($db_prefix.'customer',"WHERE c_email_mobile='".escape_string($email_mobile)."'"),true);
            if(($existing_customer['status'] ?? false) !== true || empty($existing_customer['response'])){
                $customer_columns = ['c_id','c_name','c_email_mobile','c_status','created_at'];
                $customer_values = ['--', $full_name, $email_mobile, 'active', getCurrentDatetime('Y-m-d H:i:s')];
                insertData($db_prefix.'customer', $customer_columns, $customer_values);
            }
        
            http_response_code(200); 
            echo json_encode(["status" => true, "pp_id" => $pp_id, "pp_url" => "https://".$_SERVER['HTTP_HOST']."/payment/".$pp_id]);
        }
        
        if($endpoint_type == "verify-payments"){
            $received_api_key = getAuthorizationHeader();
            
            $configured_api_key = $global_setting_response['response'][0]['api_key'] ?? '';
            if (empty($received_api_key) || empty($configured_api_key) || !hash_equals($configured_api_key, $received_api_key)) {
                http_response_code(401);
                echo json_encode(["status" => false, "message" => "Unauthorized request"]);
                exit;
            }
            
            // Get JSON raw input
            $json = file_get_contents("php://input");
            $data = json_decode($json, true);
            if (!is_array($data) || json_last_error() !== JSON_ERROR_NONE) {
                http_response_code(400);
                echo json_encode(["status" => false, "message" => "Invalid JSON request"]);
                exit;
            }
            
            // Validate required data
            if (!empty($data['pp_id']) && is_string($data['pp_id']) && preg_match('/^[a-f0-9]{32}$/i', $data['pp_id'])) {
                $pp_id = escape_string($data['pp_id']);
                
                $transaction_details = pp_get_transation($pp_id);
                
                if($transaction_details['status'] == true){
                    if($transaction_details['response'][0]['transaction_status'] !== "initialize"){
                        http_response_code(200); 
                        
                        $meta = json_decode($transaction_details['response'][0]['transaction_metadata'], true) ?? [];
                        
                        $payload = [
                            'pp_id' => $transaction_details['response'][0]['pp_id'],
                            'customer_name' => $transaction_details['response'][0]['c_name'],
                            'customer_email_mobile' => $transaction_details['response'][0]['c_email_mobile'],
                            'payment_method' => $transaction_details['response'][0]['payment_method'],
                            'amount' => safeNumber($transaction_details['response'][0]['transaction_amount']),
                            'fee' => safeNumber($transaction_details['response'][0]['transaction_fee']),
                            'refund_amount' => safeNumber($transaction_details['response'][0]['transaction_refund_amount']),
                            'total' => safeNumber($transaction_details['response'][0]['transaction_amount']) + safeNumber($transaction_details['response'][0]['transaction_fee']) - safeNumber($transaction_details['response'][0]['transaction_refund_amount']),
                            'currency' => $transaction_details['response'][0]['transaction_currency'],
                            'metadata' => $meta,
                            'sender_number' => $transaction_details['response'][0]['payment_sender_number'],
                            'transaction_id' => $transaction_details['response'][0]['payment_verify_id'],
                            'status' => $transaction_details['response'][0]['transaction_status'],
                            'date' => $transaction_details['response'][0]['created_at']
                        ];
                        
                        echo json_encode($payload);
                    }else{
                        http_response_code(400); 
                        echo json_encode(["status" => false, "message" => "Invalid Transaction"]);
                    }
                }else{
                    http_response_code(400); 
                    echo json_encode(["status" => false, "message" => "Invalid Transaction"]);
                }
            } else {
                http_response_code(400); 
                echo json_encode(["status" => false, "message" => "Missing required fields"]);
            }
        }
    }else{
        echo 'System is under maintenance. Please try again later.';
        exit();
    }