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
                include_once(__DIR__."/../pp-include/pp-controller.php");
            }else{
                echo 'System is under maintenance. Please try again later.';
                exit();
            }
        }
    }else{
        echo 'System is under maintenance. Please try again later.';
        exit();
    }
    
    if (!defined('pp_allowed_access')) {
        die('Direct access not allowed');
    }
    
    $global_setting_response = json_decode(getData($db_prefix.'settings', 'WHERE id="1"'), true);
    $global_version = json_decode(file_get_contents(__DIR__.'/../version.json'), true);
    
    $global_user_ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';

    // Session tokens are bin2hex(random_bytes(32)); anything else never reaches the query.
    $isSessionToken = function($token) {
        return is_string($token) && preg_match('/^[a-f0-9]{64}$/i', $token) === 1;
    };

    if(checkCookie('pp_admin')){
        $adminCookie = getCookie('pp_admin');
        $global_cookie_response = $isSessionToken($adminCookie)
            ? json_decode(getData($db_prefix.'browser_log', 'WHERE cookie="'.$adminCookie.'" AND status="active"'), true)
            : ['status' => false];
        if($global_cookie_response['status'] == true){
            $storedIp = $global_cookie_response['response'][0]['ip'] ?? '';
            if ($storedIp !== $global_user_ip) {
                $global_user_login = false;
            }else{
                $global_user_response = json_decode(getData($db_prefix.'admins', 'WHERE id="'.$global_cookie_response['response'][0]['a_id'].'" AND a_status="active"'), true);
                if($global_user_response['status'] == true){
                    $global_user_login = true;
                }else{
                    $global_user_login = false;
                }
            }
        }else{
            $global_user_login = false;
        }
    }else{
        if(isset($_POST['pp_admin_session'])){
            $sessionToken = $_POST['pp_admin_session'];
            $global_cookie_response = $isSessionToken($sessionToken)
                ? json_decode(getData($db_prefix.'browser_log', 'WHERE cookie="'.$sessionToken.'" AND status="active"'), true)
                : ['status' => false];
            if($global_cookie_response['status'] == true){
                $storedIp = $global_cookie_response['response'][0]['ip'] ?? '';
                if ($storedIp !== $global_user_ip) {
                    $global_user_login = false;
                }else{
                    $global_user_response = json_decode(getData($db_prefix.'admins', 'WHERE id="'.$global_cookie_response['response'][0]['a_id'].'" AND a_status="active"'), true);
                    if($global_user_response['status'] == true){
                        $global_user_login = true;
                    }else{
                        $global_user_login = false;
                    }
                }
            }else{
                $global_user_login = false;
            }
        }else{
           $global_user_login = false;
        }
    }
    
    if(isset($_POST['action'])){
        $action = escape_string($_POST['action']);

        if($action == ""){
            echo json_encode(['status' => 'false', 'message' => 'Something Wrong!']);
        }else{
            // CSRF default-deny: every POST action requires a valid token
            // except pre-auth and public guest actions (no session to bind a token to).
            $csrfExempt = [
                'login',
                'forgot-password',
                'pp-invoice-payment-link',
                'pp-invoice-link',
            ];
            $requiresCsrf = !in_array($action, $csrfExempt, true);
            $csrfToken = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
            if ($requiresCsrf && !validateCsrfToken($csrfToken)) {
                echo json_encode(['status' => "false", 'message' => 'Invalid security token. Please refresh the page and try again.']);
                exit();
            }
            if (isAdminActionAllowed($action) && !$global_user_login) {
                echo json_encode(['status' => "false", 'message' => 'Unauthorized request']);
                exit();
            }
            if($action == "pp_admin_info"){
                echo json_encode(['status' => "true", 'full_name' => $global_user_response['response'][0]['name'], 'username' => $global_user_response['response'][0]['username'], 'email' => $global_user_response['response'][0]['email']]);
            }
            
if($action == "login"){
                $clientIp = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
                $rateLimitFile = __DIR__ . '/../cache/login_rate_limit.json';
                $rateLimitData = [];
                if (file_exists($rateLimitFile)) {
                    $rateLimitData = json_decode(file_get_contents($rateLimitFile), true) ?? [];
                }
                $rateLimitKey = 'ip_' . $clientIp;
                $now = time();
                if (isset($rateLimitData[$rateLimitKey])) {
                    $attempts = $rateLimitData[$rateLimitKey];
                    if ($attempts['count'] >= 5 && ($now - $attempts['first_attempt']) < 300) {
                        echo json_encode(['status' => "false", 'message' => 'Too many login attempts. Please try again later.']);
                        exit();
                    }
                    if (($now - $attempts['first_attempt']) > 300) {
                        $rateLimitData[$rateLimitKey] = ['count' => 0, 'first_attempt' => $now];
                    }
                } else {
                    $rateLimitData[$rateLimitKey] = ['count' => 0, 'first_attempt' => $now];
                }

                $trackFailedAttempt = function() use ($rateLimitFile, $rateLimitData, $rateLimitKey) {
                    if (!isset($rateLimitData[$rateLimitKey])) {
                        $rateLimitData[$rateLimitKey] = ['count' => 1, 'first_attempt' => time()];
                    } else {
                        $rateLimitData[$rateLimitKey]['count']++;
                    }
                    @mkdir(dirname($rateLimitFile), 0755, true);
                    file_put_contents($rateLimitFile, json_encode($rateLimitData), LOCK_EX);
                };

                $email_username = escape_string($_POST['email_username']);
                $password = escape_string($_POST['password']);
        
                if($email_username == "" || $password == ""){
                    $trackFailedAttempt();
                    echo json_encode(['status' => "false", 'message' => 'Incorrect credentials']);
                }else{
                    if (filter_var($email_username, FILTER_VALIDATE_EMAIL)) {
                        $sql_email_username = 'email = "'.$email_username.'" AND a_status = "active"';
                    }else{
                       $sql_email_username = 'username = "'.$email_username.'" AND a_status = "active"';
                    }
                     
                    $response = json_decode(getData($db_prefix.'admins','WHERE '.$sql_email_username),true);
        
                    if($response['status'] == true){
                        if (password_verify($password, $response['response'][0]['password'])) {
                            if (isset($rateLimitData[$rateLimitKey])) {
                                unset($rateLimitData[$rateLimitKey]);
                                @file_put_contents($rateLimitFile, json_encode($rateLimitData));
                            }
                            if (session_status() === PHP_SESSION_ACTIVE) {
                                session_regenerate_id(true);
                            }
                            $cookie = bin2hex(random_bytes(32));
                            $userInfo = getUserDeviceInfo();
                            
                            $columns = ['a_id', 'cookie', 'browser', 'device', 'ip', 'status', 'created_at'];
                            $values = [$response['response'][0]['id'], $cookie, $userInfo['browser'], $userInfo['device'], $userInfo['ip_address'], 'active', getCurrentDatetime('Y-m-d H:i:s')];
            
                            insertData($db_prefix.'browser_log', $columns, $values);
                            
                            setsCookie('pp_admin', $cookie);
                            
                            echo json_encode(['status' => "true", 'target' => "dashboard"]);
                        }else{
                            $trackFailedAttempt();
                            echo json_encode(['status' => "false", 'message' => 'Incorrect credentials']);
                        }
                    }else{
                        $trackFailedAttempt();
                        echo json_encode(['status' => "false", 'message' => 'Incorrect credentials']);
                    }
                }
            }
            
if($action == "forgot-password"){
                $token = escape_string($_POST['token'] ?? '');
                $password = escape_string($_POST['password'] ?? '');
                $confirmPassword = escape_string($_POST['confirm_password'] ?? $password);

                if($token == "" || $password == ""){
                    echo json_encode(['status' => "false", 'message' => 'Enter reset token and new password']);
                }elseif(strlen($password) < 8){
                    echo json_encode(['status' => "false", 'message' => 'Password must be at least 8 characters long']);
                }elseif($password !== $confirmPassword){
                    echo json_encode(['status' => "false", 'message' => 'Passwords do not match']);
                }else{
                    $resetRecord = json_decode(getData($db_prefix.'admins', 'WHERE reset_token="'.$token.'" AND reset_token_used_at="--"'), true);
                    if ($resetRecord['status'] !== true) {
                        echo json_encode(['status' => "false", 'message' => 'Invalid or expired reset token']);
                    } else {
                        $expiresAt = $resetRecord['response'][0]['reset_token_expires_at'] ?? '--';
                        if ($expiresAt !== '--' && strtotime($expiresAt) < time()) {
                            echo json_encode(['status' => "false", 'message' => 'Invalid or expired reset token']);
                        } else {
                            $hashedPass = password_hash($password, PASSWORD_BCRYPT);
                            $adminId = $resetRecord['response'][0]['id'];
                            updateData($db_prefix.'admins', ['password', 'reset_token', 'reset_token_expires_at', 'reset_token_used_at'], [$hashedPass, '--', '--', getCurrentDatetime('Y-m-d H:i:s')], "id = '".$adminId."'");
                            updateData($db_prefix.'browser_log', ['status'], ['inactive'], 'a_id="' . $adminId . '"');
                            logoutCookie();
                            echo json_encode(['status' => "true", 'message' => 'Password changed successfully']);
                        }
                    }
                }
            }

            if ($action == "pp_view_dashboard") {
                $total_transaction = 0;
                $pending_transaction = 0;
                $unpaid_invoice = 0;
                $total_customers = 0;
                $total_payment_links = 0;
                $total_amount_received = 0;
                $total_amount_refunded = 0;
                
                $response = json_decode(getData($db_prefix.'transaction','WHERE transaction_status NOT IN ("initialize")'),true);
                foreach($response['response'] as $row){
                    $total_transaction = $total_transaction+1;
                    
                    if($row['transaction_status'] == "pending"){
                        $pending_transaction = $pending_transaction+1;
                    }
                    
                    if($row['transaction_status'] == "refunded"){
                        $total_amount_refunded = $total_amount_refunded+1;
                    }else{
                        if($row['transaction_status'] == "failed"){
                            $total_amount_received = $total_amount_received+1;
                        }else{
                           $total_amount_received = $total_amount_received+1;
                        }
                    }
                }

                $response = json_decode(getData($db_prefix.'invoice','WHERE i_status = "unpaid"'),true);
                foreach($response['response'] as $row){
                    $unpaid_invoice = $unpaid_invoice+1;
                }
                
                $response = json_decode(getData($db_prefix.'customer',''),true);
                foreach($response['response'] as $row){
                    $total_customers = $total_customers+1;
                }
                
                $response = json_decode(getData($db_prefix.'payment_link','WHERE pl_status="active"'),true);
                foreach($response['response'] as $row){
                    $total_payment_links = $total_payment_links+1;
                }
                
                
                $normal_data = [
                    "full_name" => $global_user_response['response'][0]['name'],
                    "total_transaction" => $total_transaction,
                    "pending_transaction" => $pending_transaction,
                    "unpaid_invoice" => $unpaid_invoice,
                    "total_customers" => $total_customers,
                    "total_payment_links" => $total_payment_links,
                    "total_amount_received" => $total_amount_received,
                    "total_amount_refunded" => $total_amount_refunded
                ];
                
                $total_report_overview = 0;
                
                $total_report_overview_complete = 0;
                $global_cal= json_decode(getData($db_prefix.'transaction', 'WHERE transaction_status = "completed"'), true);
                foreach($global_cal['response'] as $cal){
                    $total_amount = $cal['transaction_amount']+$cal['transaction_fee'];
                    $net_amount = $total_amount-$cal['transaction_refund_amount'];
                    
                    $total_report_overview_complete += convertToDefault($net_amount, $cal['transaction_currency'], $global_setting_response['response'][0]['default_currency']);
                }
                
                $total_report_overview_pending = 0;
                $global_cal= json_decode(getData($db_prefix.'transaction', 'WHERE transaction_status = "pending"'), true);
                foreach($global_cal['response'] as $cal){
                    $total_amount = $cal['transaction_amount']+$cal['transaction_fee'];
                    $net_amount = $total_amount-$cal['transaction_refund_amount'];
                    
                    $total_report_overview_pending += convertToDefault($net_amount, $cal['transaction_currency'], $global_setting_response['response'][0]['default_currency']);
                }
                
                $total_report_overview_refunded = 0;
                $global_cal= json_decode(getData($db_prefix.'transaction', 'WHERE transaction_status = "refunded"'), true);
                foreach($global_cal['response'] as $cal){
                    $total_amount = $cal['transaction_amount']+$cal['transaction_fee'];
                    $net_amount = $total_amount-$cal['transaction_refund_amount'];
                    
                    $total_report_overview_refunded += convertToDefault($net_amount, $cal['transaction_currency'], $global_setting_response['response'][0]['default_currency']);
                }
                
                $total_report_overview_failed = 0;
                $global_cal= json_decode(getData($db_prefix.'transaction', 'WHERE transaction_status = "failed"'), true);
                foreach($global_cal['response'] as $cal){
                    $total_amount = $cal['transaction_amount']+$cal['transaction_fee'];
                    $net_amount = $total_amount-$cal['transaction_refund_amount'];
                    
                    $total_report_overview_failed += convertToDefault($net_amount, $cal['transaction_currency'], $global_setting_response['response'][0]['default_currency']);
                }
                
                $total_report_overview = $total_report_overview_complete+$total_report_overview_pending+$total_report_overview_refunded+$total_report_overview_failed;
                
                $total = $total_report_overview_complete 
                       + $total_report_overview_pending 
                       + $total_report_overview_refunded 
                       + $total_report_overview_failed;
                
                if ($total > 0) {
                    $percent_complete = round(($total_report_overview_complete / $total) * 100, 2);
                    $percent_pending = round(($total_report_overview_pending / $total) * 100, 2);
                    $percent_refunded = round(($total_report_overview_refunded / $total) * 100, 2);
                    $percent_failed = round(($total_report_overview_failed / $total) * 100, 2);
                } else {
                    // All values are 0 — so all percentages are 0%
                    $percent_complete = 0;
                    $percent_pending = 0;
                    $percent_refunded = 0;
                    $percent_failed = 0;
                }
                
                $data_full_life_report = [
                    "completed" => $total_report_overview_complete,
                    "pending" => $total_report_overview_pending,
                    "refunded" => $total_report_overview_refunded,
                    "failed" => $total_report_overview_failed,
                    "currency" => $global_setting_response['response'][0]['default_currency']
                ];
                
                
                
                
                
                
                
                $chartJson = getChart($db_prefix.'transaction', 'created_at', 'today', null, null, 'transaction_status = "completed"');
                $completedchartData = json_decode($chartJson, true);

                $chartJson = getChart($db_prefix.'transaction', 'created_at', 'today', null, null, 'transaction_status = "pending"');
                $pendingchartData = json_decode($chartJson, true);

                $chartJson = getChart($db_prefix.'transaction', 'created_at', 'today', null, null, 'transaction_status = "refunded"');
                $refundedchartData = json_decode($chartJson, true);

                $chartJson = getChart($db_prefix.'transaction', 'created_at', 'today', null, null, 'transaction_status = "failed"');
                $failedchartData = json_decode($chartJson, true);

                $data_every_day_month_year_report = [];
                $data_every_day_month_year_report['today'] = [
                  "completed" => isset($completedchartData['data'][0]) ? str_replace(['[', ']'], '', $completedchartData['data'][0]) : "0",
                  "pending" => isset($pendingchartData['data'][0]) ? str_replace(['[', ']'], '', $pendingchartData['data'][0]) : "0",
                  "refunded" => isset($refundedchartData['data'][0]) ? str_replace(['[', ']'], '', $refundedchartData['data'][0]) : "0",
                  "failed" => isset($failedchartData['data'][0]) ? str_replace(['[', ']'], '', $failedchartData['data'][0]) : "0",
                  "currency" => ''
                ];
                    

                $chartJson = getChart($db_prefix.'transaction', 'created_at', 'monthly', null, null, 'transaction_status = "completed"');
                $completedchartData = json_decode($chartJson, true);

                $chartJson = getChart($db_prefix.'transaction', 'created_at', 'monthly', null, null, 'transaction_status = "pending"');
                $pendingchartData = json_decode($chartJson, true);

                $chartJson = getChart($db_prefix.'transaction', 'created_at', 'monthly', null, null, 'transaction_status = "refunded"');
                $refundedchartData = json_decode($chartJson, true);

                $chartJson = getChart($db_prefix.'transaction', 'created_at', 'monthly', null, null, 'transaction_status = "failed"');
                $failedchartData = json_decode($chartJson, true);

                $data_every_day_month_year_report['monthly'] = [
                  "completed" => isset($completedchartData['data'][0]) ? str_replace(['[', ']'], '', $completedchartData['data'][0]) : "0",
                  "pending" => isset($pendingchartData['data'][0]) ? str_replace(['[', ']'], '', $pendingchartData['data'][0]) : "0",
                  "refunded" => isset($refundedchartData['data'][0]) ? str_replace(['[', ']'], '', $refundedchartData['data'][0]) : "0",
                  "failed" => isset($failedchartData['data'][0]) ? str_replace(['[', ']'], '', $failedchartData['data'][0]) : "0",
                  "currency" => ''
                ];
                
                
                $chartJson = getChart($db_prefix.'transaction', 'created_at', 'yearly', null, null, 'transaction_status = "completed"');
                $completedchartData = json_decode($chartJson, true);

                $chartJson = getChart($db_prefix.'transaction', 'created_at', 'yearly', null, null, 'transaction_status = "pending"');
                $pendingchartData = json_decode($chartJson, true);

                $chartJson = getChart($db_prefix.'transaction', 'created_at', 'yearly', null, null, 'transaction_status = "refunded"');
                $refundedchartData = json_decode($chartJson, true);

                $chartJson = getChart($db_prefix.'transaction', 'created_at', 'yearly', null, null, 'transaction_status = "failed"');
                $failedchartData = json_decode($chartJson, true);

                $data_every_day_month_year_report['yearly'] = [
                  "completed" => isset($completedchartData['data'][0]) ? str_replace(['[', ']'], '', $completedchartData['data'][0]) : "0",
                  "pending" => isset($pendingchartData['data'][0]) ? str_replace(['[', ']'], '', $pendingchartData['data'][0]) : "0",
                  "refunded" => isset($refundedchartData['data'][0]) ? str_replace(['[', ']'], '', $refundedchartData['data'][0]) : "0",
                  "failed" => isset($failedchartData['data'][0]) ? str_replace(['[', ']'], '', $failedchartData['data'][0]) : "0",
                  "currency" => ''
                ];
                

                $json = [
                    "status" => "true",
                    "normal_data" => $normal_data,
                    "data_full_life_report" => $data_full_life_report,
                    "data_every_day_month_year_report" => $data_every_day_month_year_report
                ];
            
                echo json_encode($json);
            }
            
            
            
            if($action == "pp_transaction"){
                $transaction_status = escape_string($_POST['transaction_status'] ?? '');
                $search = escape_string($_POST['search']);
                $visibility = escape_string($_POST['visibility']);
                
                if($transaction_status == "all"){
                    $sql_rn = 'transaction_status NOT IN ("initialize") AND c_name LIKE "%'.$search.'%" OR transaction_status NOT IN ("initialize") AND c_email_mobile LIKE "%'.$search.'%" OR transaction_status NOT IN ("initialize") AND payment_method LIKE "%'.$search.'%" OR transaction_status NOT IN ("initialize") AND transaction_product_name LIKE "%'.$search.'%"';
                }else{
                   $sql_rn = 'transaction_status = "'.$transaction_status.'" AND c_name LIKE "%'.$search.'%" OR transaction_status = "'.$transaction_status.'" AND c_email_mobile LIKE "%'.$search.'%" OR transaction_status = "'.$transaction_status.'" AND payment_method LIKE "%'.$search.'%" OR transaction_status = "'.$transaction_status.'" AND transaction_product_name LIKE "%'.$search.'%"'; 
                }
                
                if($visibility == 'limited'){
                   $limit = 10; 
                }else{
                   $limit = 20;  
                }
                
                $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
                $offset = ($page - 1) * $limit;
                
                $total = 0;
                $response = json_decode(getData($db_prefix.'transaction','WHERE '.$sql_rn),true);
                foreach($response['response'] as $res){
                    $total = $total+1;
                }

                $totalPages = ceil($total / $limit);
                
                $pp_transaction = [];
                $showing = 0;
                
                $response = json_decode(getData($db_prefix.'transaction','WHERE '.$sql_rn.' ORDER BY 1 DESC LIMIT '.$limit.' OFFSET '.$offset),true);
                foreach($response['response'] as $row){
                    $showing = $showing+1;
                    
                    $pp_transaction[] = [
                      "id" => $row["id"],
                      "pp_id" => $row["pp_id"],
                      "c_name" => $row["c_name"],
                      "c_email_mobile" => $row["c_email_mobile"],
                      "payment_method" => $row["payment_method"],
                      "transaction_amount" => number_format($row["transaction_amount"]+$row["transaction_fee"]-$row["transaction_refund_amount"], 2).' '.$row["transaction_currency"],
                      "sender" => $row["payment_sender_number"],
                      "transaction_id" => ($row["payment_verify_way"] ?? '') === 'slip' ? 'View Slip' : ($row["payment_verify_id"] ?? ''),
                      "transaction_fee" => $row["transaction_fee"],
                      "transaction_refund_amount" => $row["transaction_refund_amount"],
                      "transaction_currency" => $row["transaction_currency"],
                      "transaction_status" => $row["transaction_status"],
                      "created_at" => convertDateTime($row["created_at"])
                    ];
                }
                
                echo json_encode([
                    "status" => 'true',
                    "data" => pp_escape_html_deep($pp_transaction),
                    "showing" => $showing,
                    "total" => $total,
                    "totalPages" => $totalPages,
                    "currentPage" => $page
                ]);
            }
            
            
            if($action == "pp_view_transaction"){
                $payment_id = (int) ($_POST['payment_id'] ?? 0);

                // Fetch the transaction
                $response = json_decode(getData($db_prefix . 'transaction', 'WHERE id="' . $payment_id . '"'), true);
                
                $row = $response['response'][0]; // just to shorten notation
                
                $product_meta = json_decode($row['transaction_product_meta'], true);
                
                $view_product = [];
                
                if (!empty($product_meta)) {
                    foreach ($product_meta as $key => $value) {
                        $view_product[] = [
                            "value_name" => ucwords(str_replace('_', ' ', $key)),
                            "value" => $value
                        ];
                    }
                }
                
                $product_visibility = "true";
                
                if($row["transaction_product_name"] == "" || $row["transaction_product_name"] == "--"){
                    $product_visibility = "false";
                }
                
                $json = [
                    "status" => "true",
                    "view_product" => $view_product,
                    "payment_id" => "#" . $row["id"],
                    "payment_date" => convertDateTime($row["created_at"]),
                    "payment_status" => $row["transaction_status"],
                    "txninfo_payment_method" => $row["payment_method"],
                    "txninfo_payment_currency" => $row["transaction_currency"],
                    "txninfo_transaction_id" => $row["payment_verify_id"],
                    "txninfo_amount" => number_format($row["transaction_amount"], 2).' '.$row["transaction_currency"],
                    "txninfo_processing_fee" => number_format($row["transaction_fee"], 2).' '.$row["transaction_currency"],
                    "txninfo_total_amount" => number_format($row["transaction_amount"] + $row["transaction_fee"], 2).' '.$row["transaction_currency"],
                    "txninfo_refunded_amount" => number_format($row["transaction_refund_amount"], 2).' '.$row["transaction_currency"],
                    "txninfo_net_amount" => number_format($row["transaction_amount"] + $row["transaction_fee"] - $row["transaction_refund_amount"], 2).' '.$row["transaction_currency"],
                    "customer_name" => $row["c_name"],
                    "customer_email_mobile" => $row["c_email_mobile"],
                    "product_visibility" => $product_visibility,
                    "product_name" => $row["transaction_product_name"] ?? "N/A",
                    "product_description" => $row["transaction_product_description"] ?? "N/A"
                ];
                
                echo json_encode($json, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            }
            
            
            
            if($action == "pp_basicinfo"){
                if ($mode == "demo") {
                    echo json_encode(["status" => "false", "message" => "Error: Demo mode is active"]);
                    exit();
                }
                
                $fullname = escape_string($_POST['fullname']);
                $username = escape_string($_POST['username']);
        
                if($fullname == "" || $username == ""){
                    echo json_encode(['status' => "false", 'message' => 'Fill all required field']);
                }else{
                    if($global_user_login == true){
                        $columns = ['name', 'username'];
                        $values = [$fullname, $username];
                        $condition = "id = '".$global_user_response['response'][0]['id']."'"; 
                        
                        updateData($db_prefix.'admins', $columns, $values, $condition);
                        
                        echo json_encode(['status' => "true", 'message' => "Basic information updated"]);
                    }else{
                        echo json_encode(['status' => "false", 'message' => 'Invalid data']);
                    }
                }
            }
            
            if($action == "pp_basicemail"){
                if ($mode == "demo") {
                    echo json_encode(["status" => "false", "message" => "Error: Demo mode is active"]);
                    exit();
                }
                
                $email = escape_string($_POST['email']);
        
                if($email == ""){
                    echo json_encode(['status' => "false", 'message' => 'Fill all required field']);
                }else{
                    if($global_user_login == true){
                        $columns = ['email'];
                        $values = [$email];
                        $condition = "id = '".$global_user_response['response'][0]['id']."'"; 
                        
                        updateData($db_prefix.'admins', $columns, $values, $condition);
                        
                        echo json_encode(['status' => "true", 'message' => "Email address updated"]);
                    }else{
                        echo json_encode(['status' => "false", 'message' => 'Invalid data']);
                    }
                }
            }
            
            if($action == "pp_newpassword"){
                if ($mode == "demo") {
                    echo json_encode(["status" => "false", "message" => "Error: Demo mode is active"]);
                    exit();
                }
                
                $currentpassword = escape_string($_POST['currentpassword']);
                $newPassword = escape_string($_POST['newPassword']);
                $confirmpassword = escape_string($_POST['confirmpassword']);
                
                if($currentpassword == "" || $newPassword == "" || $confirmpassword == ""){
                    echo json_encode(['status' => "false", 'message' => 'Fill all required field']);
                }elseif(strlen($newPassword) < 8){
                    echo json_encode(['status' => "false", 'message' => 'Password must be at least 8 characters long']);
                }else{
                    if($global_user_login == true){
                        if (password_verify($currentpassword, $global_user_response['response'][0]['password'])) {
                            if($newPassword == $confirmpassword){
                                $hashedPass = password_hash($newPassword, PASSWORD_BCRYPT);
                                
                                $columns = ['password'];
                                $values = [$hashedPass];
                                $condition = "id = '".$global_user_response['response'][0]['id']."'"; 
                                
                                updateData($db_prefix.'admins', $columns, $values, $condition);
                                updateData($db_prefix.'browser_log', ['status'], ['inactive'], 'a_id="' . $global_user_response['response'][0]['id'] . '"');
                                logoutCookie();
                                echo json_encode(['status' => "true", 'message' => "Password updated"]);
                            }else{
                                echo json_encode(['status' => "false", 'message' => 'Incorrect New password & Confirm password']);
                            }
                        }else{
                            echo json_encode(['status' => "false", 'message' => 'Incorrect current password']);
                        }
                    }else{
                        echo json_encode(['status' => "false", 'message' => 'Invalid data']);
                    }
                }
            }
            
            if($action == "pp_systembasicinfo"){
                if ($mode == "demo") {
                    echo json_encode(["status" => "false", "message" => "Error: Demo mode is active"]);
                    exit();
                }
                
                $sitename = escape_string($_POST['sitename']);
                $default_timezone = escape_string($_POST['default_timezone']);
                $default_currency = escape_string($_POST['default_currency']);
                $currency_symbol = escape_string($_POST['currency_symbol']);
                $currency_rate = escape_string($_POST['currency_rate']);
                
                if($sitename == "" || $default_timezone == "" || $default_currency == "" || $currency_symbol == "" || $currency_rate == ""){
                    echo json_encode(['status' => "false", 'message' => 'Fill all required field']);
                }else{
                    if($global_user_login == true){
                        $columns = ['site_name', 'default_timezone', 'default_currency', 'currency_symbol'];
                        $values = [$sitename, $default_timezone, $default_currency, $currency_symbol];
                        $condition = "id = '1'"; 
                        
                        updateData($db_prefix.'settings', $columns, $values, $condition);
                        
                        echo json_encode(['status' => "true", 'message' => "Basic information updated"]);
                    }else{
                        echo json_encode(['status' => "false", 'message' => 'Invalid data']);
                    }
                }
            }
            
            
            if($action == "pp-theme-plugins-import"){
                if ($mode == "demo") {
                    echo json_encode(["status" => "error", "message" => "Error: Demo mode is active"]);
                    exit();
                }

                // Declared up front: the error paths below clean up the extracted temp folder.
                if (!function_exists('deleteFolders')) {
                    function deleteFolders($folder) {
                        foreach (glob($folder . '/*') as $file) {
                            if (is_dir($file)) {
                                deleteFolders($file);
                            } else {
                                unlink($file);
                            }
                        }
                        rmdir($folder);
                    }
                }

                
                if (!isset($_FILES['zip_file']) || $_FILES['zip_file']['error'] !== 0) {
                    echo json_encode(["status" => "error", "message" => "No file uploaded."]);
                    exit;
                }
                
                $upload = $_FILES['zip_file'];
                if ($upload['size'] <= 0 || $upload['size'] > 25 * 1024 * 1024 || !is_uploaded_file($upload['tmp_name'])) {
                    echo json_encode(["status" => "error", "message" => "Invalid ZIP upload."]);
                    exit;
                }

                $zipTmpPath = $upload['tmp_name'];
                $tempDir = __DIR__ . "/../temp_upload_" . bin2hex(random_bytes(8)) . "/";
                mkdir($tempDir, 0755, true);
                
                // Extract ZIP
                $zip = new ZipArchive();
                if ($zip->open($zipTmpPath) === TRUE) {
                    if ($zip->numFiles > 5000) {
                        $zip->close();
                        deleteFolders($tempDir);
                        echo json_encode(["status" => "error", "message" => "ZIP contains too many files."]);
                        exit;
                    }
                    for ($i = 0; $i < $zip->numFiles; $i++) {
                        $entryName = str_replace('\\', '/', (string) $zip->getNameIndex($i));
                        if ($entryName === '' || $entryName[0] === '/' || preg_match('#(^|/)\.\.(/|$)#', $entryName) || preg_match('#^[A-Za-z]:#', $entryName)) {
                            $zip->close();
                            deleteFolders($tempDir);
                            echo json_encode(["status" => "error", "message" => "Invalid ZIP path."]);
                            exit;
                        }
                    }
                    if (!$zip->extractTo($tempDir)) {
                        $zip->close();
                        deleteFolders($tempDir);
                        echo json_encode(["status" => "error", "message" => "Failed to extract ZIP."]);
                        exit;
                    }
                    $zip->close();
                } else {
                    echo json_encode(["status" => "error", "message" => "Failed to extract ZIP."]);
                    exit;
                }
                
                // Find plugin.json
                $pluginJson = "";
                foreach (glob($tempDir . "*/meta.json") as $file) {
                    $pluginJson = $file;
                    break;
                }
                if (!$pluginJson) {
                    // Try directly in root
                    if (file_exists($tempDir . "meta.json")) {
                        $pluginJson = $tempDir . "meta.json";
                    } else {
                        deleteFolders($tempDir);
                        echo json_encode(["status" => "error", "message" => "meta.json not found in the ZIP."]);
                        exit;
                    }
                }
                
                // Parse plugin.json
                $data = json_decode(file_get_contents($pluginJson), true);
                $type = $data['type'] ?? null;       // plugin or theme
                $slug = $data['slug'] ?? null;
                $mrdr = $data['mrdr'] ?? null;
                
                if (!in_array($type, ['plugins', 'themes'], true) ||
                    !is_string($slug) || !preg_match('/^[a-z0-9][a-z0-9_-]{0,63}$/i', $slug) ||
                    !is_string($mrdr) || !preg_match('/^[a-z0-9][a-z0-9_-]{0,63}$/i', $mrdr)) {
                    echo json_encode(["status" => "error", "message" => "Invalid plugin metadata."]);
                    deleteFolders($tempDir);
                    exit;
                }
                
                if (!$type || !$slug || !$mrdr) {
                    echo json_encode(["status" => "error", "message" => "plugin.json must include type, slug, and mrdr."]);
                    exit;
                }
                
                // Final destination: pp-content/plugins/modules/myplugin/
                $basePath = __DIR__ . "/../pp-content/" . $type . "/" . $mrdr . "/" . $slug . "/";
                if (!is_dir($basePath)) {
                    mkdir($basePath, 0755, true);
                }
                
                // Copy files safely - prevent ZIP path traversal
                $allowedBase = realpath($basePath);

                function copyFolderSecure($src, $dst, $allowedBase) {
                    $dir = opendir($src);
                    @mkdir($dst, 0755, true);
                    while (false !== ($file = readdir($dir))) {
                        if ($file != '.' && $file != '..') {
                            $srcPath = $src . '/' . $file;
                            $dstPath = $dst . '/' . $file;
                            $realDst = realpath(dirname($dstPath));
                            if ($realDst === false || strpos($realDst, $allowedBase) !== 0) {
                                continue;
                            }
                            if (is_dir($srcPath)) {
                                copyFolderSecure($srcPath, $dstPath, $allowedBase);
                            } elseif (is_file($srcPath)) {
                                copy($srcPath, $dstPath);
                            }
                        }
                    }
                    closedir($dir);
                }

                // Validate extracted ZIP contents stay within tempDir
                function validateExtractedPath($baseDir) {
                    $iterator = new RecursiveIteratorIterator(
                        new RecursiveDirectoryIterator($baseDir, RecursiveDirectoryIterator::SKIP_DOTS),
                        RecursiveIteratorIterator::SELF_FIRST
                    );
                    $realBase = realpath($baseDir);
                    foreach ($iterator as $item) {
                        $realPath = realpath($item->getPathname());
                        if ($realPath === false || strpos($realPath, $realBase) !== 0) {
                            return false;
                        }
                    }
                    return true;
                }

                if (!validateExtractedPath($tempDir)) {
                    echo json_encode(["status" => "error", "message" => "Invalid ZIP contents."]);
                    deleteFolders($tempDir);
                    exit;
                }

                // Figure out if zip had subfolder (e.g., myplugin/plugin.json)
                $rootItems = glob($tempDir . "*");
                if (count($rootItems) === 1 && is_dir($rootItems[0])) {
                    copyFolderSecure($rootItems[0], $basePath, $allowedBase);
                } else {
                    copyFolderSecure($tempDir, $basePath, $allowedBase);
                }
                
                // Cleanup
                deleteFolders($tempDir);
                
                echo json_encode([
                    "status" => "success",
                    "message" => "Uploaded successfully to $type/$mrdr/$slug/"
                ]);
            }
            
            
            if($action == "pp_savecolorscheme"){
                if ($mode == "demo") {
                    echo json_encode(["status" => "false", "message" => "Error: Demo mode is active"]);
                    exit();
                }
                
                $global_text_color = escape_string($_POST['global_text_color']);
                $primary_button_color = escape_string($_POST['primary_button_color']);
                $button_text_color = escape_string($_POST['button_text_color']);
                $button_hover_color = escape_string($_POST['button_hover_color']);
                $button_hover_text_color = escape_string($_POST['button_hover_text_color']);
                $navigation_background = escape_string($_POST['navigation_background']);

                $navigation_text_color = escape_string($_POST['navigation_text_color']);
                $active_tab_color = escape_string($_POST['active_tab_color']);
                $active_tab_text_color = escape_string($_POST['active_tab_text_color']);
                
                if($global_text_color == "" || $primary_button_color == "" || $button_text_color == "" || $button_hover_color == "" || $button_hover_text_color == "" || $navigation_background == "" || $navigation_text_color == "" || $active_tab_color == "" || $active_tab_text_color == ""){
                    echo json_encode(['status' => "false", 'message' => 'Fill all required field']);
                }else{
                    if($global_user_login == true){
                        $columns = ['global_text_color', 'primary_button_color', 'button_text_color', 'button_hover_color', 'button_hover_text_color', 'navigation_background', 'navigation_text_color', 'active_tab_color', 'active_tab_text_color'];
                        $values = [$global_text_color, $primary_button_color, $button_text_color, $button_hover_color, $button_hover_text_color, $navigation_background, $navigation_text_color, $active_tab_color, $active_tab_text_color];
                        $condition = "id = '1'"; 
                        
                        updateData($db_prefix.'settings', $columns, $values, $condition);
                        
                        echo json_encode(['status' => "true", 'message' => "Color scheme updated"]);
                    }else{
                        echo json_encode(['status' => "false", 'message' => 'Invalid data']);
                    }
                }
            }
            
            if($action == "pp_resetcolorscheme"){
                if ($mode == "demo") {
                    echo json_encode(["status" => "false", "message" => "Error: Demo mode is active"]);
                    exit();
                }

                if($global_user_login == true){
                    $columns = ['global_text_color', 'primary_button_color', 'button_text_color', 'button_hover_color', 'button_hover_text_color', 'navigation_background', 'navigation_text_color', 'active_tab_color', 'active_tab_text_color'];
                    $values = ['#3bb77e', '#3bb77e', '#FFFFFF', '#20bb74', '#FFFFFF', '#20bb74', '#FFFFFF', '#20bb74', '#FFFFFF'];
                    $condition = "id = '1'"; 
                    
                    updateData($db_prefix.'settings', $columns, $values, $condition);
                    
                    echo json_encode(['status' => "true", 'message' => "Color scheme updated"]);
                }else{
                    echo json_encode(['status' => "false", 'message' => 'Invalid data']);
                }
            }
            
            if($action == "pp_branding"){
                if ($mode == "demo") {
                    echo json_encode(["status" => "false", "message" => "Error: Demo mode is active"]);
                    exit();
                }

                if($global_user_login == true){
                    $max_file_size = 10 * 1024 * 1024; 
                    
                    $branding_favicon = json_decode(uploadImage($_FILES['branding_favicon']?? null, $max_file_size), true);
                    if($branding_favicon['status'] == true){
                         $branding_favicon = 'https://'.$_SERVER['HTTP_HOST'].'/pp-external/media/'.$branding_favicon['file'];
                         
                         deleteImage($global_setting_response['response'][0]['favicon']);
                    }else{
                        $branding_favicon = $global_setting_response['response'][0]['favicon'];
                    }
                    
                    $branding_logo = json_decode(uploadImage($_FILES['branding_logo']?? null, $max_file_size), true);
                    if($branding_logo['status'] == true){
                         $branding_logo = 'https://'.$_SERVER['HTTP_HOST'].'/pp-external/media/'.$branding_logo['file'];
                         
                         deleteImage($global_setting_response['response'][0]['logo']);
                    }else{
                        $branding_logo = $global_setting_response['response'][0]['logo'];
                    }
                    
                    $columns = ['logo', 'favicon'];
                    $values = [$branding_logo, $branding_favicon];
                    $condition = "id = '1'"; 
                    
                    updateData($db_prefix.'settings', $columns, $values, $condition);
                    
                    echo json_encode(['status' => "true", 'message' => "Branding updated"]);
                }else{
                    echo json_encode(['status' => "false", 'message' => 'Invalid data']);
                }
            }
            
            
            if($action == "pp_savebusiness_details"){
                if ($mode == "demo") {
                    echo json_encode(["status" => "false", "message" => "Error: Demo mode is active"]);
                    exit();
                }
                
                $business_details_street_address = escape_string($_POST['business_details_street_address']);
                $business_details_city_town = escape_string($_POST['business_details_city_town']);
                $business_details_postal_code = escape_string($_POST['business_details_postal_code']);
                $business_details_country = escape_string($_POST['business_details_country']);

                if($business_details_street_address == "" || $business_details_city_town == "" || $business_details_postal_code == "" || $business_details_country == ""){
                    echo json_encode(['status' => "false", 'message' => 'Fill all required field']);
                }else{
                    if($global_user_login == true){
                        $columns = ['street_address', 'city_town', 'postal_zip_code', 'country'];
                        $values = [$business_details_street_address, $business_details_city_town, $business_details_postal_code, $business_details_country];
                        $condition = "id = '1'"; 
                        
                        updateData($db_prefix.'settings', $columns, $values, $condition);
                        
                        echo json_encode(['status' => "true", 'message' => "Business details updated"]);
                    }else{
                        echo json_encode(['status' => "false", 'message' => 'Invalid data']);
                    }
                }
            }
            
            
            
            if($action == "pp_savesupport_contact"){
                if ($mode == "demo") {
                    echo json_encode(["status" => "false", "message" => "Error: Demo mode is active"]);
                    exit();
                }
                
                $support_contact_phone_mobile = escape_string($_POST['support_contact_phone_mobile']);
                $support_contact_email_addrss = escape_string($_POST['support_contact_email_addrss']);
                $support_contact_support_website = escape_string($_POST['support_contact_support_website']);
                $support_contact_facebok_page = escape_string($_POST['support_contact_facebok_page']);

                $support_contact_messenger = escape_string($_POST['support_contact_messenger']);
                $support_contact_whatsapp = escape_string($_POST['support_contact_whatsapp']);
                $support_contact_telegram = escape_string($_POST['support_contact_telegram']);
                $support_contact_youtube = escape_string($_POST['support_contact_youtube']);
                
                
                if($support_contact_phone_mobile == "" || $support_contact_email_addrss == "" || $support_contact_support_website == "" || $support_contact_facebok_page == "" || $support_contact_messenger == "" || $support_contact_whatsapp == "" || $support_contact_telegram == "" || $support_contact_youtube == ""){
                    echo json_encode(['status' => "false", 'message' => 'Fill all required field']);
                }else{
                    if($global_user_login == true){
                        $columns = ['support_phone_number', 'support_email_address', 'support_website', 'facebook_page', 'facebook_messenger', 'whatsapp_number', 'telegram', 'youtube_channel'];
                        $values = [$support_contact_phone_mobile, $support_contact_email_addrss, $support_contact_support_website, $support_contact_facebok_page, $support_contact_messenger, $support_contact_whatsapp, $support_contact_telegram, $support_contact_youtube];
                        $condition = "id = '1'"; 
                        
                        updateData($db_prefix.'settings', $columns, $values, $condition);
                        
                        echo json_encode(['status' => "true", 'message' => "Contact information updated"]);
                    }else{
                        echo json_encode(['status' => "false", 'message' => 'Invalid data']);
                    }
                }
            }
            
            
            
            if($action == "pp_savefooter_links"){
                if ($mode == "demo") {
                    echo json_encode(["status" => "false", "message" => "Error: Demo mode is active"]);
                    exit();
                }

                // Self-migrate for existing installs
                try {
                    $__pp_conn = connectDatabase();
                    foreach (['footer_support_url', 'footer_faq_url', 'footer_information_url', 'footer_terms_url'] as $col) {
                        $check = $__pp_conn->query("SHOW COLUMNS FROM `".$db_prefix."settings` LIKE '".$col."'");
                        if ($check && $check->num_rows == 0) {
                            $__pp_conn->query("ALTER TABLE `".$db_prefix."settings` ADD COLUMN `".$col."` VARCHAR(255) DEFAULT '--'");
                        }
                    }
                    $__pp_conn->close();
                } catch (Throwable $e) {}

                $footer_support_url = escape_string($_POST['footer_support_url'] ?? '');
                $footer_faq_url = escape_string($_POST['footer_faq_url'] ?? '');
                $footer_information_url = escape_string($_POST['footer_information_url'] ?? '');
                $footer_terms_url = escape_string($_POST['footer_terms_url'] ?? '');

                if($footer_support_url == "" || $footer_faq_url == "" || $footer_information_url == "" || $footer_terms_url == ""){
                    echo json_encode(['status' => "false", 'message' => 'Fill all required field']);
                }else{
                    if($global_user_login == true){
                        $columns = ['footer_support_url', 'footer_faq_url', 'footer_information_url', 'footer_terms_url'];
                        $values = [$footer_support_url, $footer_faq_url, $footer_information_url, $footer_terms_url];
                        $condition = "id = '1'";

                        updateData($db_prefix.'settings', $columns, $values, $condition);

                        echo json_encode(['status' => "true", 'message' => "Footer links updated"]);
                    }else{
                        echo json_encode(['status' => "false", 'message' => 'Invalid data']);
                    }
                }
            }



            if($action == "pp_generate_api"){
                if ($mode == "demo") {
                    echo json_encode(["status" => "false", "message" => "Error: Demo mode is active"]);
                    exit();
                }
                
                if($global_user_login == true){
                    try {
                        $api_key = bin2hex(random_bytes(32));
                    } catch (Exception $e) {
                        echo json_encode(['status' => "false", 'message' => 'Unable to generate api key']);
                        exit();
                    }

                    $columns = ['api_key'];
                    $values = [$api_key];
                    $condition = "id = '1'"; 
                    $updated = updateData($db_prefix.'settings', $columns, $values, $condition);
                    
                    if ($updated) {
                        echo json_encode(['status' => "true", 'api' => $api_key]);
                    } else {
                        echo json_encode(['status' => "false", 'message' => 'Unable to save api key']);
                    }
                }else{
                    echo json_encode(['status' => "false", 'message' => 'Invalid data']);
                }
            }
            
            if($action == "pp_view_currency_list"){
                $pp_transaction = [];
                $response = json_decode(getData($db_prefix.'currency',""),true);
                foreach($response['response'] as $row){
                    $pp_transaction[] = [
                      "name" => $row["currency_name"].' - ('.$row["currency_code"].')'
                    ];
                }
                
                echo json_encode([
                    "status" => 'true',
                    "data" => pp_escape_html_deep($pp_transaction),
                ]);
            }
            
            if($action == "pp_currency_list"){
                $limit = 50; 
                $search = escape_string($_POST['search']);
                $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
                $offset = ($page - 1) * $limit;
                
                $total = 0;
                $response = json_decode(getData($db_prefix.'currency',"WHERE currency_code LIKE '%$search%' OR currency_name LIKE '%$search%'"),true);
                foreach($response['response'] as $res){
                    $total = $total+1;
                }

                $totalPages = ceil($total / $limit);
                
                $pp_transaction = [];
                $showing = 0;
                
                $response = json_decode(getData($db_prefix.'currency',"WHERE currency_code LIKE '%$search%' OR currency_name LIKE '%$search%' LIMIT ".$limit." OFFSET ".$offset),true);
                foreach($response['response'] as $row){
                    $showing =$showing+1;
                    
                    $pp_transaction[] = [
                      "id" => $row["id"],
                      "currency_code" => $row["currency_code"],
                      "currency_name" => $row["currency_name"],
                      "currency_symbol" => $row["currency_symbol"],
                      "currency_rate" => $row["currency_rate"],
                      "created_at" => $row["created_at"]
                    ];
                }
                
                echo json_encode([
                    "status" => 'true',
                    "data" => pp_escape_html_deep($pp_transaction),
                    "total" => $total,
                    "showing" => $showing,
                    "totalPages" => $totalPages,
                    "currentPage" => $page
                ]);
            }
            
            
            if($action == "pp_currencysetting"){
                if ($mode == "demo") {
                    echo json_encode(["status" => "false", "message" => "Error: Demo mode is active"]);
                    exit();
                }
                $currency_id = escape_string($_POST['currency_id']);
                $currency_rate = escape_string($_POST['currency_rate']);
                
                $response = json_decode(getData($db_prefix.'currency',"WHERE id ='".$currency_id."'"),true);
                if($response['status'] == true){
                    $columns = ['currency_rate', 'created_at'];
                    $values = [$currency_rate, getCurrentDatetime('Y-m-d H:i:s')];
                    $condition = "id = '".$currency_id."'"; 
                    
                    updateData($db_prefix.'currency', $columns, $values, $condition);
                    
                    echo json_encode(['status' => "true", 'message' => "Currency Updated"]);
                }else{
                    echo json_encode(['status' => "false", 'message' => 'Invalid data']);
                }
            }
            
            
            if($action == "pp_faq_list"){
                $limit = 50; 
                $search = escape_string($_POST['search']);
                $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
                $offset = ($page - 1) * $limit;
                
                $total = 0;
                $response = json_decode(getData($db_prefix.'faq',"WHERE title LIKE '%$search%'"),true);
                foreach($response['response'] as $res){
                    $total = $total+1;
                }

                $totalPages = ceil($total / $limit);
                
                $pp_transaction = [];
                $showing = 0;
                
                $response = json_decode(getData($db_prefix.'faq',"WHERE title LIKE '%$search%' ORDER BY 1 DESC LIMIT ".$limit." OFFSET ".$offset),true);
                foreach($response['response'] as $row){
                    $showing =$showing+1;
                    
                    $pp_transaction[] = [
                      "id" => $row["id"],
                      "title" => $row["title"],
                      "content" => $row["content"],
                      "status" => $row["status"],
                      "created_at" => $row["created_at"]
                    ];
                }
                
                echo json_encode([
                    "status" => 'true',
                    "data" => pp_escape_html_deep($pp_transaction),
                    "total" => $total,
                    "showing" => $showing,
                    "totalPages" => $totalPages,
                    "currentPage" => $page
                ]);
            }
            
            if($action == "pp_addfaq"){
                if ($mode == "demo") {
                    echo json_encode(["status" => "false", "message" => "Error: Demo mode is active"]);
                    exit();
                }
                $faq_title = escape_string($_POST['faq_title']);
                $faq_content = escape_string($_POST['faq_content']);
                
                if($faq_title == "" || $faq_content == ""){
                    echo json_encode(['status' => "false", 'message' => 'Invalid data']);
                }else{
                    $columns = ['title', 'content', 'status', 'created_at'];
                    $values = [$faq_title, $faq_content, 'active', getCurrentDatetime('Y-m-d H:i:s')];
    
                    insertData($db_prefix.'faq', $columns, $values);
                    
                    echo json_encode(['status' => "true", 'message' => "FAQ added"]);
                }
            }
            
            if($action == "pp_editfaq"){
                if ($mode == "demo") {
                    echo json_encode(["status" => "false", "message" => "Error: Demo mode is active"]);
                    exit();
                }
                $faq_id = escape_string($_POST['id']);
                $faq_title = escape_string($_POST['faq_title']);
                $faq_content = escape_string($_POST['faq_content']);
                $faq_status = escape_string($_POST['status']);
                
                if($faq_title == "" || $faq_content == ""){
                    echo json_encode(['status' => "false", 'message' => 'Invalid data']);
                }else{
                    $columns = ['title', 'content', 'status', 'created_at'];
                    $values = [$faq_title, $faq_content, $faq_status, getCurrentDatetime('Y-m-d H:i:s')];
                    $condition = "id = '".$faq_id."'"; 
                    
                    updateData($db_prefix.'faq', $columns, $values, $condition);
                    
                    echo json_encode(['status' => "true", 'message' => "FAQ updated"]);
                }
            }
            
            if($action == "pp_bulk_action_transaction"){
                if ($mode == "demo") {
                    echo json_encode(["status" => "false", "message" => "Error: Demo mode is active"]);
                    exit();
                }
            
                $ids = escape_string($_POST['ids']);
                
                if (!is_array($ids)) {
                    $ids = explode(",", $ids); 
                }
                
                $ids = array_map('intval', $ids);
                
                $count = 0;

                foreach ($ids as $id) {
                    $action_name = escape_string($_POST['action_name']);
                    
                    $response_transaction_checker = json_decode(getData($db_prefix.'transaction','WHERE id="'.$id.'"'),true);
                    if($response_transaction_checker['status'] == true){
                        $count = 1;
                            
                        if($action_name == "delete"){
                            $condition = "id = '".$id."'"; 
                            
                            deleteData($db_prefix."transaction", $condition);
                        }

                        if($action_name == "refund"){
                            $columns = ['transaction_status', 'transaction_refund_amount'];
                            $values = ['refunded', $response_transaction_checker['response'][0]['transaction_amount']];
                            $condition = "id = '".$id."'"; 
                            
                            updateData($db_prefix."transaction", $columns, $values, $condition);
                            
                            if (function_exists('pp_trigger_hook')) {
                                pp_trigger_hook('pp_transaction_ipn', $response_transaction_checker['response'][0]['pp_id']);
                            }
                        }

                        if($action_name == "approved"){
                            $columns = ['transaction_status'];
                            $values = ['completed'];
                            $condition = "id = '".$id."'"; 
                            
                            updateData($db_prefix."transaction", $columns, $values, $condition);
                            
                            if (function_exists('pp_trigger_hook')) {
                                pp_trigger_hook('pp_transaction_ipn', $response_transaction_checker['response'][0]['pp_id']);
                            }
                        }
                        
                        if($action_name == "send-ipn"){   
                            if (function_exists('pp_trigger_hook')) {
                                pp_trigger_hook('pp_transaction_ipn', $response_transaction_checker['response'][0]['pp_id']);
                            }
                        }
                    }
                }
    
                if($count == 0){
                    echo json_encode(['status' => "false", 'message' => 'Invalid Data']);
                }else{
                    echo json_encode(['status' => "true", 'message' => 'Transaction Updated']);
                }
            }
            
            if($action == "pp_addcustomer"){
                if ($mode == "demo") {
                    echo json_encode(["status" => "false", "message" => "Error: Demo mode is active"]);
                    exit();
                }
                $c_name = escape_string($_POST['c_name']);
                $c_email_mobile = escape_string($_POST['c_email_mobile']);
                
                if($c_name == "" || $c_email_mobile == ""){
                    echo json_encode(['status' => "false", 'message' => 'Invalid data']);
                }else{
                    $response = json_decode(getData($db_prefix.'customer','WHERE c_email_mobile="'.$c_email_mobile.'"'),true);
                    if($response['status'] == true){
                        echo json_encode(['status' => "false", 'message' => "Email address already exit."]); 
                    }else{
                        $columns = ['c_id', 'c_name', 'c_email_mobile', 'c_status', 'created_at'];
                        $values = [bin2hex(random_bytes(16)), $c_name, $c_email_mobile, 'active', getCurrentDatetime('Y-m-d H:i:s')];
        
                        insertData($db_prefix.'customer', $columns, $values);
                        
                        echo json_encode(['status' => "true", 'message' => "Customer added"]);
                    }
                }
            }
            
            if($action == "pp_customer_list"){
                $limit = 50; 
                $search = escape_string($_POST['search']);
                $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
                $offset = ($page - 1) * $limit;
                
                $total = 0;
                $response = json_decode(getData($db_prefix.'customer',"WHERE c_name LIKE '%$search%' OR c_email_mobile LIKE '%$search%'"),true);
                foreach($response['response'] as $res){
                    $total = $total+1;
                }

                $totalPages = ceil($total / $limit);
                
                $pp_transaction = [];
                $showing = 0;
                
                $response = json_decode(getData($db_prefix.'customer',"WHERE c_name LIKE '%$search%' OR c_email_mobile LIKE '%$search%' ORDER BY 1 DESC LIMIT ".$limit." OFFSET ".$offset),true);
                foreach($response['response'] as $row){
                    $showing =$showing+1;
                    
                    $pp_transaction[] = [
                      "id" => $row["id"],
                      "c_id" => $row["c_id"],
                      "c_name" => $row["c_name"],
                      "c_email_mobile" => $row["c_email_mobile"],
                      "c_status" => $row["c_status"],
                      "created_at" => $row["created_at"]
                    ];
                }
                
                echo json_encode([
                    "status" => 'true',
                    "data" => pp_escape_html_deep($pp_transaction),
                    "total" => $total,
                    "showing" => $showing,
                    "totalPages" => $totalPages,
                    "currentPage" => $page
                ]);
            }
            
            if($action == "pp_editcustomer"){
                if ($mode == "demo") {
                    echo json_encode(["status" => "false", "message" => "Error: Demo mode is active"]);
                    exit();
                }
                
                $c_id = escape_string($_POST['id']);
                $c_name = escape_string($_POST['c_name']);
                $c_email_mobile = escape_string($_POST['c_email_mobile']);
                $c_status = escape_string($_POST['status']);
                
                
                if($c_name == "" || $c_email_mobile == "" || $c_status == ""){
                    echo json_encode(['status' => "false", 'message' => 'Invalid data']);
                }else{
                    $response = json_decode(getData($db_prefix.'customer','WHERE id="'.$c_id.'"'),true);
                    if($response['status'] == true){
                        if (!in_array($c_status, ['active', 'inactive'], true)) {
                            echo json_encode(['status' => "false", 'message' => 'Invalid data']);
                            exit();
                        }
                        $columns = ['c_name', 'c_email_mobile', 'c_status'];
                        $values = [$c_name, $c_email_mobile, $c_status];
                        
                        $condition = "id = '".$c_id."'"; 
                        updateData($db_prefix.'customer', $columns, $values, $condition);
                        
                        echo json_encode(['status' => "true", 'message' => "Customer Edited"]);
                    }else{
                        echo json_encode(['status' => "false", 'message' => "Invalid Data."]); 
                    }
                }
            }
            
            if($action == "pp_bulk_action_customer"){
                if ($mode == "demo") {
                    echo json_encode(["status" => "false", "message" => "Error: Demo mode is active"]);
                    exit();
                }
            
                $ids = escape_string($_POST['ids']);
                
                if (!is_array($ids)) {
                    $ids = explode(",", $ids); 
                }
                
                $ids = array_map('intval', $ids);
                
                $count = 0;

                foreach ($ids as $id) {
                    $action_name = escape_string($_POST['action_name']);
                    
                    $response_transaction_checker = json_decode(getData($db_prefix.'customer','WHERE id="'.$id.'"'),true);
                    if($response_transaction_checker['status'] == true){
                        $count = 1;
                            
                        if($action_name == "delete"){
                            $condition = "id = '".$id."'"; 
                            
                            deleteData($db_prefix."customer", $condition);
                        }

                        if($action_name == "active"){
                            $columns = ['c_status'];
                            $values = ['active'];
                            $condition = "id = '".$id."'"; 
                            
                            updateData($db_prefix."customer", $columns, $values, $condition);
                        }
                        
                        if($action_name == "inactive"){
                            $columns = ['c_status'];
                            $values = ['inactive'];
                            $condition = "id = '".$id."'"; 
                            
                            updateData($db_prefix."customer", $columns, $values, $condition);
                        }
                    }
                }
    
                if($count == 0){
                    echo json_encode(['status' => "false", 'message' => 'Invalid Data']);
                }else{
                    echo json_encode(['status' => "true", 'message' => 'Invalid Data']);
                }
            }
            
            
            
            if($action == "pp_sms_data_devices"){
                $limit = 50; 
                $search = escape_string($_POST['search']);
                $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
                $offset = ($page - 1) * $limit;
                
                $sms_status = escape_string($_POST['sms_status'] ?? '');
                
                if($sms_status == "all"){
                    $sms_status = "";
                }else{
                    $sms_status = "AND d_status='".$sms_status."'";
                }
                
                $total = 0;
                $response = json_decode(getData($db_prefix.'devices',"WHERE d_model LIKE '%$search%' ".$sms_status." OR d_brand LIKE '%$search%' ".$sms_status." OR d_version LIKE '%$search%' ".$sms_status),true);
                foreach($response['response'] as $res){
                    $total = $total+1;
                }

                $totalPages = ceil($total / $limit);
                
                $pp_transaction = [];
                $showing = 0;
                
                $response = json_decode(getData($db_prefix.'devices',"WHERE d_model LIKE '%$search%' ".$sms_status." OR d_brand LIKE '%$search%' ".$sms_status." OR d_version LIKE '%$search%' ".$sms_status." ORDER BY 1 DESC LIMIT ".$limit." OFFSET ".$offset),true);
                foreach($response['response'] as $row){
                    $showing =$showing+1;
                    
                    if($row["d_status"] == "Disconnected"){
                        $d_status = $row["d_status"];
                    }else{
                        if (getConnectionStatus($row["created_at"], 31)) {
                            $d_status = 'Connected';
                        } else {
                            $d_status = 'Disconnected';
                        }
                    }
                    
                    $pp_transaction[] = [
                      "id" => $row["id"],
                      "d_model" => $row["d_model"],
                      "d_brand" => $row["d_brand"],
                      "d_version" => $row["d_version"],
                      "d_api_level" => $row["d_api_level"],
                      "d_status" => $d_status,
                      "created_at" => $row["created_at"]
                    ];
                }
                
                echo json_encode([
                    "status" => 'true',
                    "data" => pp_escape_html_deep($pp_transaction),
                    "total" => $total,
                    "showing" => $showing,
                    "totalPages" => $totalPages,
                    "currentPage" => $page
                ]);
            }
            

            if($action == "pp_bulk_action_devices"){
                if ($mode == "demo") {
                    echo json_encode(["status" => "false", "message" => "Error: Demo mode is active"]);
                    exit();
                }
            
                $ids = escape_string($_POST['ids']);
                
                if (!is_array($ids)) {
                    $ids = explode(",", $ids); 
                }
                
                $ids = array_map('intval', $ids);
                
                $count = 0;

                foreach ($ids as $id) {
                    $action_name = escape_string($_POST['action_name']);
                    
                    $response_transaction_checker = json_decode(getData($db_prefix.'devices','WHERE id="'.$id.'"'),true);
                    if($response_transaction_checker['status'] == true){
                        $count = 1;
                            
                        if($action_name == "delete"){
                            $condition = "id = '".$id."'"; 
                            
                            deleteData($db_prefix."devices", $condition);
                        }

                        if($action_name == "Connected"){
                            $columns = ['d_status'];
                            $values = ['Connected'];
                            $condition = "id = '".$id."'"; 
                            
                            updateData($db_prefix."devices", $columns, $values, $condition);
                        }
                        
                        if($action_name == "review"){
                            $columns = ['d_status'];
                            $values = ['Disconnected'];
                            $condition = "id = '".$id."'"; 
                            
                            updateData($db_prefix."devices", $columns, $values, $condition);
                        }
                    }
                }
    
                if($count == 0){
                    echo json_encode(['status' => "false", 'message' => 'Invalid Data']);
                }else{
                    echo json_encode(['status' => "true", 'message' => 'Invalid Data']);
                }
            }
            




            if($action == "pp_sms_data_list"){
                $limit = 50; 
                $search = escape_string($_POST['search']);
                $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
                $offset = ($page - 1) * $limit;
                
                $sms_status = escape_string($_POST['sms_status'] ?? '');
                
                if($sms_status == "all"){
                    $sms_status = "";
                }else{
                    $sms_status = "AND status='".$sms_status."'";
                }
                
                $total = 0;
                $response = json_decode(getData($db_prefix.'sms_data',"WHERE (payment_method LIKE '%$search%' OR sim LIKE '%$search%' OR message LIKE '%$search%' OR mobile_number LIKE '%$search%' OR transaction_id LIKE '%$search%') ".$sms_status),true);
                foreach($response['response'] as $res){
                    $total = $total+1;
                }

                $totalPages = ceil($total / $limit);
                
                $pp_transaction = [];
                $showing = 0;
                
                $response = json_decode(getData($db_prefix.'sms_data',"WHERE (payment_method LIKE '%$search%' OR sim LIKE '%$search%' OR message LIKE '%$search%' OR mobile_number LIKE '%$search%' OR transaction_id LIKE '%$search%') ".$sms_status." ORDER BY 1 DESC LIMIT ".$limit." OFFSET ".$offset),true);
                foreach($response['response'] as $row){
                    $showing =$showing+1;
                    
                    $pp_transaction[] = [
                      "id" => $row["id"],
                      "entry_type" => isset($row["entry_type"]) ? ($row["entry_type"] === 'manual' ? 'Manual' : ($row["entry_type"] === 'automatic' ? 'Automatic' : 'Unknown')) : 'Not set',
                      "payment_method" => $row["payment_method"],
                      "sim" => isset($row["sim"]) ? ($row["sim"] === 'sim1' ? 'SIM 1' : ($row["sim"] === 'sim2' ? 'SIM 2' : 'Unknown')) : 'Not set',
                      "mobile_number" => $row["mobile_number"],
                      "transaction_id" => $row["transaction_id"],
                      "message" => $row["message"],
                      "amount" => $row["amount"],
                      "balance" => $row["balance"],
                      "status" => $row["status"],
                      "created_at" => $row["created_at"]
                    ];
                }
                
                echo json_encode([
                    "status" => 'true',
                    "data" => pp_escape_html_deep($pp_transaction),
                    "total" => $total,
                    "showing" => $showing,
                    "totalPages" => $totalPages,
                    "currentPage" => $page
                ]);
            }

            if($action == "pp_bulk_action_sms_data"){
                if ($mode == "demo") {
                    echo json_encode(["status" => "false", "message" => "Error: Demo mode is active"]);
                    exit();
                }
            
                $ids = escape_string($_POST['ids']);
                
                if (!is_array($ids)) {
                    $ids = explode(",", $ids); 
                }
                
                $ids = array_map('intval', $ids);
                
                $count = 0;

                foreach ($ids as $id) {
                    $action_name = escape_string($_POST['action_name']);
                    
                    $response_transaction_checker = json_decode(getData($db_prefix.'sms_data','WHERE id="'.$id.'"'),true);
                    if($response_transaction_checker['status'] == true){
                        $count = 1;
                            
                        if($action_name == "delete"){
                            $condition = "id = '".$id."'"; 
                            
                            deleteData($db_prefix."sms_data", $condition);
                        }

                        if($action_name == "approved"){
                            $columns = ['status'];
                            $values = ['approved'];
                            $condition = "id = '".$id."'"; 
                            
                            updateData($db_prefix."sms_data", $columns, $values, $condition);
                        }
                        
                        if($action_name == "review"){
                            $columns = ['status'];
                            $values = ['review'];
                            $condition = "id = '".$id."'"; 
                            
                            updateData($db_prefix."sms_data", $columns, $values, $condition);
                        }
                        
                        if($action_name == "used"){
                            $columns = ['status'];
                            $values = ['used'];
                            $condition = "id = '".$id."'"; 
                            
                            updateData($db_prefix."sms_data", $columns, $values, $condition);
                        }
                    }
                }
    
                if($count == 0){
                    echo json_encode(['status' => "false", 'message' => 'Invalid Data']);
                }else{
                    echo json_encode(['status' => "true", 'message' => 'Invalid Data']);
                }
            }
            
            if($action == "pp_generate_webhook"){
                if ($mode == "demo") {
                    echo json_encode(["status" => "false", "message" => "Error: Demo mode is active"]);
                    exit();
                }
                
                if($global_user_login == true){
                    try {
                        $webhook_key = bin2hex(random_bytes(32));
                    } catch (Exception $e) {
                        echo json_encode(['status' => "false", 'message' => 'Unable to generate webhook url']);
                        exit();
                    }

                    $columns = ['webhook'];
                    $values = [$webhook_key];
                    $condition = "id = '1'"; 
                    $updated = updateData($db_prefix.'settings', $columns, $values, $condition);
                    
                    if ($updated) {
                        echo json_encode(['status' => "true", 'webhook' => $webhook_key]);
                    } else {
                        echo json_encode(['status' => "false", 'message' => 'Unable to save webhook url']);
                    }
                }else{
                    echo json_encode(['status' => "false", 'message' => 'Invalid data']);
                }
            }
            
            if($action == "pp_sms-new-message"){
                if ($mode == "demo") {
                    echo json_encode(["status" => "false", "message" => "Error: Demo mode is active"]);
                    exit();
                }
                $method = escape_string($_POST['method']);
                $amount = escape_string($_POST['amount']);
                $phone_number = escape_string($_POST['phone_number']);
                $transaction_id = escape_string($_POST['transaction_id']);
                $sim_slot = escape_string($_POST['sim_slot']);
                $status = escape_string($_POST['status']);
                
                if($method == "" || $amount == "" || $phone_number == "" || $transaction_id == ""){
                    echo json_encode(['status' => "false", 'message' => 'Invalid data']);
                }else{
                    $response = json_decode(getData($db_prefix.'sms_data','WHERE transaction_id="'.$transaction_id.'"'),true);
                    if($response['status'] == true){
                        echo json_encode(['status' => "false", 'message' => "Transaction id already exit."]); 
                    }else{
                        $columns = ['entry_type', 'sim', 'payment_method', 'mobile_number', 'transaction_id', 'amount', 'balance', 'status', 'created_at'];
                        $values = ['manual', $sim_slot, $method, $phone_number, $transaction_id, $amount, 0, $status, getCurrentDatetime('Y-m-d H:i:s')];
        
                        insertData($db_prefix.'sms_data', $columns, $values);
                        
                        echo json_encode(['status' => "true", 'message' => "SMS added"]);
                    }
                }
            }
            
            if($action == "pp_sms-edit-message"){
                if ($mode == "demo") {
                    echo json_encode(["status" => "false", "message" => "Error: Demo mode is active"]);
                    exit();
                }
                
                $sms_id = escape_string($_POST['sms_id']);
                $method = escape_string($_POST['method']);
                $amount = escape_string($_POST['amount']);
                $phone_number = escape_string($_POST['phone_number']);
                $transaction_id = escape_string($_POST['transaction_id']);
                $sim_slot = escape_string($_POST['sim_slot']);
                $status = escape_string($_POST['status']);
                
                if($method == "" || $amount == "" || $phone_number == "" || $transaction_id == "" || $sms_id == ""){
                    echo json_encode(['status' => "false", 'message' => 'Invalid data']);
                }else{
                    $response = json_decode(getData($db_prefix.'sms_data','WHERE id="'.$sms_id.'"'),true);
                    if($response['status'] == true){
                        $columns = ['sim', 'payment_method', 'mobile_number', 'transaction_id', 'amount', 'status'];
                        $values = [$sim_slot, $method, $phone_number, $transaction_id, $amount, $status];
        
                        $condition = "id = '".$sms_id."'"; 
                        updateData($db_prefix.'sms_data', $columns, $values, $condition);
                        
                        echo json_encode(['status' => "true", 'message' => "SMS Edited"]);
                    }else{
                       echo json_encode(['status' => "false", 'message' => "Invalid Data."]);  
                    }
                }
            }
            
            
            
            if($action == "pp_payment_links_list"){
                $limit = 50; 
                $search = escape_string($_POST['search']);
                $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
                $offset = ($page - 1) * $limit;
                
                $payment_link_status = escape_string($_POST['payment_link_status'] ?? '');
                
                if($payment_link_status == "all"){
                    $payment_link_status = "";
                }else{
                    $payment_link_status = "AND pl_status='".$payment_link_status."'";
                }
                
                $total = 0;
                $response = json_decode(getData($db_prefix.'payment_link',"WHERE pl_name LIKE '%$search%' ".$payment_link_status." OR pl_description LIKE '%$search%' ".$payment_link_status." OR pl_currency LIKE '%$search%' ".$payment_link_status),true);
                foreach($response['response'] as $res){
                    $total = $total+1;
                }

                $totalPages = ceil($total / $limit);
                
                $pp_transaction = [];
                $showing = 0;
                
                $response = json_decode(getData($db_prefix.'payment_link',"WHERE pl_name LIKE '%$search%' ".$payment_link_status." OR pl_description LIKE '%$search%' ".$payment_link_status." OR pl_currency LIKE '%$search%' ".$payment_link_status." ORDER BY 1 DESC LIMIT ".$limit." OFFSET ".$offset),true);
                foreach($response['response'] as $row){
                    $showing =$showing+1;
                    
                    $pp_transaction[] = [
                      "id" => $row["id"],
                      "pl_id" =>$row['pl_id'],
                      "pl_link" =>'https://'.$_SERVER['HTTP_HOST'].'/payment-link/'.$row['pl_id'],
                      "pl_name" => $row["pl_name"],
                      "pl_quantity" => $row["pl_quantity"],
                      "pl_description" => $row["pl_description"],
                      "pl_currency" => $row["pl_currency"],
                      "pl_amount" => number_format($row["pl_amount"], 2).' '.$row["pl_currency"],
                      "pl_expiry_date" => $row["pl_expiry_date"],
                      "pl_status" => $row["pl_status"],
                      "created_at" => $row["created_at"]
                    ];
                }
                
                echo json_encode([
                    "status" => 'true',
                    "data" => pp_escape_html_deep($pp_transaction),
                    "total" => $total,
                    "showing" => $showing,
                    "totalPages" => $totalPages,
                    "currentPage" => $page
                ]);
            }
            
            if ($action == "pp_view_payment_link") {
                $pl_id = escape_string($_POST['pl_id'] ?? '');
                
                // Fetch the transaction
                $response = json_decode(getData($db_prefix . 'payment_link', 'WHERE pl_id="' . $pl_id . '"'), true);
                $row = $response['response'][0];
            
                $input_filed = json_decode(getData($db_prefix . 'payment_link_input', 'WHERE pl_id="' . $row['pl_id'] . '"'), true);
                $input_fild = [];
            
                if (!empty($input_filed)) {
                    foreach ($input_filed['response'] as $input) {
                        $input_fild[] = [
                            "form_type" => $input['pl_form_type'],
                            "required" => $input['pl_is_require'],
                            "field_name" => $input['pl_field_name']
                        ];
                    }
                }
                
                $currency_response = json_decode(getData($db_prefix . 'currency', 'WHERE currency_code="' . $row['pl_currency'] . '"'), true);
                
                $data = [
                    "product_name" => $row['pl_name'],
                    "invoice_quantity" => $row['pl_quantity'],
                    "product_amount" => $row['pl_amount'],
                    "expire_date" => $row['pl_expiry_date'],
                    "product_currency" => $currency_response['response'][0]['currency_name'].' - ('.$currency_response['response'][0]['currency_code'].')',
                    "product_description" => $row['pl_description'],
                    "product_status" => $row['pl_status']
                ];
                
                $json = [
                    "status" => "true",
                    "input_fild" => $input_fild,
                    "data" => $data
                ];
            
                echo json_encode($json);
            }
            
            if($action == "pp_payment_links_manage"){
                if ($mode == "demo") {
                    echo json_encode(["status" => "false", "message" => "Error: Demo mode is active"]);
                    exit();
                }
                
                $pl_id = escape_string($_POST['pl_id']);
                $pl_product_name = escape_string($_POST['payment-link-product-name']);
                $pl_quantity = escape_string($_POST['payment-link-quantity']);
                $pl_description = escape_string($_POST['payment-link-product-description']);
                $pl_currency = escape_string($_POST['payment-link-currency']);
                $pl_amount = escape_string($_POST['payment-link-amount']);
                $pl_expiry = escape_string($_POST['payment-link-expiry']);
                $pl_status = escape_string($_POST['payment-link-status']);


                if (preg_match('/\((.*?)\)/', $pl_currency, $matches)) {
                    $pl_currency = $matches[1]; // Found in parentheses
                } else {
                    $pl_currency = trim($pl_currency); // Just plain code like "USD"
                }
                
                if($pl_product_name == "" || $pl_quantity == "" || $pl_description == "" || $pl_currency == "" || $pl_amount == "" || $pl_expiry == "" || $pl_status == ""){
                    echo json_encode(['status' => "false", 'message' => 'Invalid data']);
                }else{
                    if($pl_currency == "-"){
                        echo json_encode(['status' => "false", 'message' => 'Invalid data']);
                        exit();
                    }
                    if($pl_id == ""){
                        $payment_link_create = true;
                        $pl_id = bin2hex(random_bytes(12));
                    }else{
                        $response_payment_link_checker = json_decode(getData($db_prefix.'payment_link','WHERE pl_id="'.$pl_id.'"'),true);
                        if($response_payment_link_checker['status'] == true){
                            $payment_link_create = false;
                        }else{
                            $payment_link_create = true;
$pl_id = bin2hex(random_bytes(12));
                        }
                    }
                    
                    if($payment_link_create == true){
                        $columns = ['pl_id', 'pl_name', 'pl_quantity', 'pl_description', 'pl_currency', 'pl_amount', 'pl_expiry_date', 'pl_status', 'created_at'];
                        $values = [$pl_id, $pl_product_name, safeNumber($pl_quantity), $pl_description, $pl_currency, safeNumber($pl_amount), $pl_expiry, $pl_status, getCurrentDatetime('Y-m-d H:i:s')];
        
                        insertData($db_prefix.'payment_link', $columns, $values);
                    }else{
                        $columns = ['pl_name', 'pl_quantity', 'pl_description', 'pl_currency', 'pl_amount', 'pl_expiry_date', 'pl_status'];
                        $values = [$pl_product_name, safeNumber($pl_quantity, $response_payment_link_checker['response'][0]['pl_quantity']), $pl_description, $pl_currency, safeNumber($pl_amount, $response_payment_link_checker['response'][0]['pl_amount']), $pl_expiry, $pl_status];
        
                        $condition = "pl_id = '".$pl_id."'"; 
                        updateData($db_prefix.'payment_link', $columns, $values, $condition);   
                    }
            
                    $condition = "pl_id = '".$pl_id."'"; 
                    
                    deleteData($db_prefix."payment_link_input", $condition);
                    
                    if(isset($_POST['payment-link-input-field-type'])){
                        $fieldTypes = $_POST['payment-link-input-field-type'];
                        $fieldNames = $_POST['payment-link-input-field-name'];
                        $fieldRequirements = $_POST['payment-link-input-field-is-require'];
                    
                        $fields = [];
                    
                        for ($i = 0; $i < count($fieldTypes); $i++) {
                            $columns = ['pl_id', 'pl_form_type', 'pl_field_name', 'pl_is_require', 'created_at'];
                            $values = [$pl_id, escape_string($fieldTypes[$i]), escape_string($fieldNames[$i]), escape_string($fieldRequirements[$i]), getCurrentDatetime('Y-m-d H:i:s')];
            
                            insertData($db_prefix.'payment_link_input', $columns, $values);
                        }
                    }
                    
                    echo json_encode(['status' => "true", 'message' => 'Payment Link Created']);
                }
            }
            
            if($action == "pp_bulk_action_payment_link"){
                if ($mode == "demo") {
                    echo json_encode(["status" => "false", "message" => "Error: Demo mode is active"]);
                    exit();
                }
            
                $ids = escape_string($_POST['ids']);
                
                if (!is_array($ids)) {
                    $ids = explode(",", $ids); 
                }
                
                $ids = array_map('intval', $ids);
                
                $count = 0;

                foreach ($ids as $id) {
                    $action_name = escape_string($_POST['action_name']);
                    
                    $response_transaction_checker = json_decode(getData($db_prefix.'payment_link','WHERE id="'.$id.'"'),true);
                    if($response_transaction_checker['status'] == true){
                        $count = 1;
                            
                        if($action_name == "delete"){
                            $condition = "id = '".$id."'"; 
                            
                            deleteData($db_prefix."payment_link", $condition);
                            
                            $condition = "pl_id = '".$response_transaction_checker['response'][0]['pl_id']."'"; 
                            
                            deleteData($db_prefix."payment_link_input", $condition);
                        }

                    }
                }
    
                if($count == 0){
                    echo json_encode(['status' => "false", 'message' => 'Invalid Data']);
                }else{
                    echo json_encode(['status' => "true", 'message' => 'Invalid Data']);
                }
            }
            
            
            
            
            
            if($action == "pp_invoice_list"){
                $limit = 50; 
                $search = escape_string($_POST['search']);
                $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
                $offset = ($page - 1) * $limit;
                
                $payment_link_status = escape_string($_POST['payment_link_status'] ?? '');
                
                if($payment_link_status == "all"){
                    $payment_link_status = "";
                }else{
                    $payment_link_status = "AND i_status='".$payment_link_status."'";
                }
                
                $total = 0;
                $response = json_decode(getData($db_prefix.'invoice',"WHERE c_name LIKE '%$search%' ".$payment_link_status." OR c_email_mobile LIKE '%$search%' ".$payment_link_status." OR i_currency LIKE '%$search%' ".$payment_link_status),true);
                foreach($response['response'] as $res){
                    $total = $total+1;
                }

                $totalPages = ceil($total / $limit);
                
                $pp_transaction = [];
                $showing = 0;
                
                $response = json_decode(getData($db_prefix.'invoice',"WHERE c_name LIKE '%$search%' ".$payment_link_status." OR c_email_mobile LIKE '%$search%' ".$payment_link_status." OR i_currency LIKE '%$search%' ".$payment_link_status." ORDER BY 1 DESC LIMIT ".$limit." OFFSET ".$offset),true);
                foreach($response['response'] as $row){
                    $showing =$showing+1;

                    $subtotal = 0;
                    $totalVat = 0;
                    $totalDiscount = 0;
                    
                    $response_items = json_decode(getData($db_prefix.'invoice_items',"WHERE i_id='".$row['i_id']."'"),true);
                    foreach ($response_items['response'] as $item) {
                        $quantity = isset($item['quantity']) ? floatval($item['quantity']) : 0;
                        $amount = isset($item['amount']) ? floatval($item['amount']) : 0;
                        $discount = isset($item['discount']) ? floatval($item['discount']) : 0;
                        $vatPercentage = isset($item['vat']) ? floatval($item['vat']) : 0;
                    
                        $itemSubtotal = $quantity * $amount;
                    
                        $itemDiscount = min($discount, $itemSubtotal);
                        $totalDiscount += $itemDiscount;

                        $itemAmountAfterDiscount = $itemSubtotal - $itemDiscount;
                        $itemVat = $itemAmountAfterDiscount * ($vatPercentage / 100);
                        $totalVat += $itemVat;

                        $subtotal += $itemSubtotal;
                    }
                    
                    $shipping = $row['i_amount_shipping'];

                    $totalAmount = $subtotal - $totalDiscount + $totalVat + $shipping;

                    $pp_transaction[] = [
                      "id" => $row["id"],
                      "i_id" =>$row['i_id'],
                      "c_name" => $row["c_name"],
                      "c_email_mobile" => $row["c_email_mobile"],
                      "i_currency" => $row["i_currency"],
                      "i_due_date" => $row["i_due_date"],
                      "i_status" => $row["i_status"],
                      "i_note" => $row["i_note"],
                      "amount" => number_format($totalAmount, 2).' '.$row["i_currency"],
                      "i_amount_shipping" => number_format($row["i_amount_shipping"], 2).' '.$row["i_currency"],
                      "i_link" => 'https://'.$_SERVER['HTTP_HOST'].'/invoice/'.$row['i_id'],
                      "created_at" => $row["created_at"],
                    ];
                }
                
                echo json_encode([
                    "status" => 'true',
                    "data" => pp_escape_html_deep($pp_transaction),
                    "total" => $total,
                    "showing" => $showing,
                    "totalPages" => $totalPages,
                    "currentPage" => $page
                ]);
            }
            
            if ($action == "pp_view_invoice") {
                $invoice_id = (int) ($_POST['invoice_id'] ?? 0);
                
                // Fetch the transaction
                $response = json_decode(getData($db_prefix . 'invoice', 'WHERE id="' . $invoice_id . '"'), true);
                $row = $response['response'][0];
            
                // Fix here: make it an object, not array
                $selected_customer = [
                    "id" => $row['c_id'],
                    "name" => $row['c_name']
                ];
            
                $invoice_prefill_response = json_decode(getData($db_prefix . 'invoice_items', 'WHERE i_id="' . $row['i_id'] . '"'), true);
                $invoice_prefill = [];
            
                if (!empty($invoice_prefill_response)) {
                    foreach ($invoice_prefill_response['response'] as $invoice) {
                        $invoice_prefill[] = [
                            "description" => $invoice['description'],
                            "quantity" => $invoice['quantity'],
                            "amount" => $invoice['amount'],
                            "discount" => $invoice['discount'],
                            "vat" => $invoice['vat']
                        ];
                    }
                }
                
                $customer_response = json_decode(getData($db_prefix . 'customer', 'WHERE c_status="active"'), true);
                $customer_list = [];
            
                if (!empty($customer_response)) {
                    foreach ($customer_response['response'] as $customer) {
                        $customer_list[] = [
                            "c_id" => $customer['c_id'],
                            "c_name" => $customer['c_name']
                        ];
                    }
                }
                
                $currency_response = json_decode(getData($db_prefix . 'currency', 'WHERE currency_code="' . $row['i_currency'] . '"'), true);
                
                $json = [
                    "status" => "true",
                    "customer_list" => $customer_list,
                    "selected_customer" => $selected_customer,
                    "invoice_prefill" => $invoice_prefill,
                    "currency" => $currency_response['response'][0]['currency_name'].' - ('.$currency_response['response'][0]['currency_code'].')',
                    "payment_status" => $row['i_status'],
                    "due_date" => $row["i_due_date"],
                    "etShipping" => $row['i_amount_shipping'],
                    "invoicenote" => $row['i_note']
                ];
            
                echo json_encode($json);
            }

            if ($action == "pp_view_invoice_customer") {
                $customer_response = json_decode(getData($db_prefix . 'customer', 'WHERE c_status="active"'), true);
                $customer_list = [];
            
                if (!empty($customer_response)) {
                    foreach ($customer_response['response'] as $customer) {
                        $customer_list[] = [
                            "c_id" => $customer['c_id'],
                            "c_name" => $customer['c_name']
                        ];
                    }
                }
            
                $json = [
                    "status" => "true",
                    "customer_list" => $customer_list,
                ];
            
                echo json_encode($json);
            }
            
            if($action == "pp_invoice_manage"){
                if ($mode == "demo") {
                    echo json_encode(["status" => "false", "message" => "Error: Demo mode is active"]);
                    exit();
                }
                
                $i_id = escape_string($_POST['i_id']);
                
                $invoice_customer = escape_string($_POST['invoice_customer']);
                $invoice_currency = escape_string($_POST['invoice_currency']);
                $invoice_due = escape_string($_POST['invoice_due']);
                $invoice_status = strtolower(escape_string($_POST['invoice_status']));
                $invoice_notes = escape_string($_POST['invoice_notes']);
                $invoice_shipping = escape_string($_POST['invoice_shipping']);

                if (preg_match('/\((.*?)\)/', $invoice_currency, $matches)) {
                    $invoice_currency = $matches[1]; // Found in parentheses
                } else {
                    $invoice_currency = trim($invoice_currency); // Just plain code like "USD"
                }

                if($invoice_customer == "" || $invoice_currency == "" || $invoice_due == "" || $invoice_status == "" || $invoice_shipping == ""){
                    echo json_encode(['status' => "false", 'message' => 'Invalid data']);
                }else{
                    if($invoice_currency == "--"){
                        echo json_encode(['status' => "false", 'message' => 'Invalid data']);
                        exit();
                    }
                    if($i_id == ""){
                        $invoice_create = true;
                        $i_id = bin2hex(random_bytes(12));
                    }else{
                        $response_invoice_checker = json_decode(getData($db_prefix.'invoice','WHERE i_id="'.$i_id.'"'),true);
                        if($response_invoice_checker['status'] == true){
                            $invoice_create = false;
                        }else{
                            $invoice_create = true;
$i_id = bin2hex(random_bytes(12));
                        }
                    }
                    
                    $response_invoice_customer = json_decode(getData($db_prefix.'customer','WHERE c_id="'.$invoice_customer.'"'),true);
                    if($response_invoice_customer['status'] == true){

                    }else{
                        echo json_encode(['status' => "false", 'message' => 'Invalid data']);
                        exit();
                    }
                
                    if($invoice_create == true){
                        $columns = ['i_id', 'c_id', 'c_name', 'c_email_mobile', 'i_currency', 'i_due_date', 'i_status', 'i_note', 'i_amount_shipping', 'created_at'];
                        $values = [$i_id, $invoice_customer, $response_invoice_customer['response'][0]['c_name'], $response_invoice_customer['response'][0]['c_email_mobile'], $invoice_currency, $invoice_due, $invoice_status, $invoice_notes, safeNumber($invoice_shipping), getCurrentDatetime('Y-m-d H:i:s')];
        
                        insertData($db_prefix.'invoice', $columns, $values);
                    }else{
                        $columns = ['c_id', 'c_name', 'c_email_mobile', 'i_currency', 'i_due_date', 'i_status', 'i_note', 'i_amount_shipping', ];
                        $values = [$invoice_customer, $response_invoice_customer['response'][0]['c_name'], $response_invoice_customer['response'][0]['c_email_mobile'], $invoice_currency, $invoice_due, $invoice_status, $invoice_notes, safeNumber($invoice_shipping),];
        
                        $condition = "i_id = '".$i_id."'"; 
                        updateData($db_prefix.'invoice', $columns, $values, $condition);   
                    }
            
                    $condition = "i_id = '".$i_id."'"; 
                    
                    deleteData($db_prefix."invoice_items", $condition);
                    
                    if(isset($_POST['invoice-items-description'])){
                        $invoice_items_description = $_POST['invoice-items-description'];
                        $invoice_items_quantity = $_POST['invoice-items-quantity'];
                        $invoice_items_amount = $_POST['invoice-items-amount'];
                        $invoice_items_discount = $_POST['invoice-items-discount'];
                        $invoice_items_vat = $_POST['invoice-items-vat'];
                        
                        $fields = [];
                    
                        for ($i = 0; $i < count($invoice_items_description); $i++) {
                            $columns = ['i_id', 'description', 'quantity', 'amount', 'discount', 'vat'];
                            $values = [$i_id, escape_string($invoice_items_description[$i]), escape_string(safeNumber($invoice_items_quantity[$i])), escape_string(safeNumber($invoice_items_amount[$i])), escape_string(safeNumber($invoice_items_discount[$i])), escape_string(safeNumber($invoice_items_vat[$i]))];
            
                            insertData($db_prefix.'invoice_items', $columns, $values);
                        }
                    }
                    
                    if($invoice_status == "paid" || $invoice_status == "canceled" || $invoice_status == "refunded" || $invoice_status == "unpaid"){
                        if (function_exists('pp_trigger_hook')) {
                            pp_trigger_hook('pp_invoice_ipn', $i_id);
                        }
                    }
                    
                    echo json_encode(['status' => "true", 'message' => 'Invoice Saved']);
                }
            }
            
            if($action == "pp_bulk_action_invoice"){
                if ($mode == "demo") {
                    echo json_encode(["status" => "false", "message" => "Error: Demo mode is active"]);
                    exit();
                }
            
                $ids = escape_string($_POST['ids']);
                
                if (!is_array($ids)) {
                    $ids = explode(",", $ids); 
                }
                
                $ids = array_map('intval', $ids);
                
                $count = 0;

                foreach ($ids as $id) {
                    $action_name = escape_string($_POST['action_name']);
                    
                    $response_transaction_checker = json_decode(getData($db_prefix.'invoice','WHERE id="'.$id.'"'),true);
                    if($response_transaction_checker['status'] == true){
                        $count = 1;
                            
                        if($action_name == "delete"){
                            $condition = "id = '".$id."'"; 
                            
                            deleteData($db_prefix."invoice", $condition);
                            
                            $condition = "i_id = '".$response_transaction_checker['response'][0]['i_id']."'"; 
                            
                            deleteData($db_prefix."invoice_items", $condition);
                        }

                        if($action_name == "paid"){
                            $columns = ['i_status'];
                            $values = ['paid'];
                            $condition = "id = '".$id."'"; 
                            
                            updateData($db_prefix."invoice", $columns, $values, $condition);
                            
                            if (function_exists('pp_trigger_hook')) {
                                pp_trigger_hook('pp_invoice_ipn', $response_transaction_checker['response'][0]['i_id']);
                            }
                        }
                        
                        if($action_name == "unpaid"){
                            $columns = ['i_status'];
                            $values = ['unpaid'];
                            $condition = "id = '".$id."'"; 
                            
                            updateData($db_prefix."invoice", $columns, $values, $condition);
                            
                            if (function_exists('pp_trigger_hook')) {
                                pp_trigger_hook('pp_invoice_ipn', $response_transaction_checker['response'][0]['i_id']);
                            }
                        }
                        
                        if($action_name == "refund"){
                            $columns = ['i_status'];
                            $values = ['refunded'];
                            $condition = "id = '".$id."'"; 
                            
                            updateData($db_prefix."invoice", $columns, $values, $condition);
                            
                            if (function_exists('pp_trigger_hook')) {
                                pp_trigger_hook('pp_invoice_ipn', $response_transaction_checker['response'][0]['i_id']);
                            }
                        }
                    }
                }
    
                if($count == 0){
                    echo json_encode(['status' => "false", 'message' => 'Invalid Data']);
                }else{
                    echo json_encode(['status' => "true", 'message' => 'Invalid Data']);
                }
            }
            
            if($action == "pp_plugin_manager"){
                if ($mode == "demo") {
                    echo json_encode(["status" => "false", "message" => "Error: Demo mode is active"]);
                    exit();
                }
                
                $type = escape_string($_POST['type']);
                $mainfolder = escape_string($_POST['mainfolder']);
                $pluginfolder = escape_string($_POST['pluginfolder']);
                
                if($type == "" || $mainfolder == "" || $pluginfolder == ""){
                    echo json_encode(['status' => "false", 'message' => 'Invalid data']);
                }elseif (!preg_match('/^[A-Za-z0-9_-]+$/', $mainfolder) || !preg_match('/^[A-Za-z0-9_-]+$/', $pluginfolder) || !in_array($type, ['delete', 'activate', 'deactivate'], true)) {
                    echo json_encode(['status' => "false", 'message' => 'Invalid data']);
                }else{
                    if($type == "delete"){
                        if (is_dir(__DIR__.'/../pp-content/plugins/'.$mainfolder.'/'.$pluginfolder)) {
                            if (deleteFolder(__DIR__.'/../pp-content/plugins/'.$mainfolder.'/'.$pluginfolder)) {
                                $condition = "plugin_slug = '".$pluginfolder."'"; 
                                
                                deleteData($db_prefix."plugins", $condition);
                                
                                echo json_encode(['status' => "true", 'message' => "Plugin delete successfully."]);  
                            } else {
                                echo json_encode(['status' => "false", 'message' => "Failed to delete plugin."]);  
                            }
                        }else{
                            echo json_encode(['status' => "false", 'message' => "Plugin not exits."]);  
                        }
                    }else{
                        $response = json_decode(getData($db_prefix.'plugins','WHERE plugin_slug="'.$pluginfolder.'"'),true);
                        if($response['status'] == true){
                            if($type == "activate"){
                                $columns = ['status', 'plugin_dir'];
                                $values = ['active', $mainfolder];
                
                                $condition = "id = '".$response['response'][0]['id']."'"; 
                                updateData($db_prefix.'plugins', $columns, $values, $condition);
                                
                                echo json_encode(['status' => "true", 'message' => ""]);
                            }
                            if($type == "deactivate"){
                                $columns = ['status', 'plugin_dir'];
                                $values = ['inactive', $mainfolder];
                
                                $condition = "id = '".$response['response'][0]['id']."'"; 
                                updateData($db_prefix.'plugins', $columns, $values, $condition);
                                
                                echo json_encode(['status' => "true", 'message' => ""]);
                            }
                        }else{
                            $pluginInfo = parsePluginHeader(__DIR__.'/../pp-content/plugins/'.$mainfolder.'/'.$pluginfolder.'/'.$pluginfolder.'-class.php');
                            
                            if($type == "activate"){
                                $columns = ['plugin_name', 'plugin_slug', 'plugin_dir', 'plugin_array', 'status', 'created_at'];
                                $values = [escape_string(htmlspecialchars($pluginInfo['Plugin Name'] ?? '')), escape_string($pluginfolder), $mainfolder, '--', 'active', escape_string(getCurrentDatetime('Y-m-d H:i:s'))];
                
                                insertData($db_prefix.'plugins', $columns, $values);
                                
                                echo json_encode(['status' => "true", 'message' => ""]);
                            }
                        }
                    }
                }
            }
            
            
            
            if($action == "pp_appearance_themes_manager"){
                if ($mode == "demo") {
                    echo json_encode(["status" => "false", "message" => "Error: Demo mode is active"]);
                    exit();
                }
                
                $type = escape_string($_POST['type']);
                $mainfolder = escape_string($_POST['mainfolder']);
                $themesfolder = escape_string($_POST['themesfolder']);
                
                if($type == "" || $mainfolder == "" || $themesfolder == ""){
                    echo json_encode(['status' => "false", 'message' => 'Invalid data']);
                }elseif (!preg_match('/^[A-Za-z0-9_-]+$/', $mainfolder) || !preg_match('/^[A-Za-z0-9_-]+$/', $themesfolder) || !in_array($type, ['delete', 'activate', 'deactivate'], true)) {
                    echo json_encode(['status' => "false", 'message' => 'Invalid data']);
                }else{
                    if($global_setting_response['response'][0]['gateway_theme'] == $themesfolder){

                    }else{
                        if($type == "activate"){
                            $columns = ['gateway_theme', 'invoice_theme'];
                            $values = [$themesfolder, $themesfolder];
            
                            $condition = "id = '1'"; 
                            updateData($db_prefix.'settings', $columns, $values, $condition);
                            
                            echo json_encode(['status' => "true", 'message' => ""]);
                        }
                    }
                }
            }
            
            
            
            
            if($action == "plugin_update-submit"){
                if ($mode == "demo") {
                    echo json_encode(["status" => "false", "message" => "Error: Demo mode is active"]);
                    exit();
                }
                
                $plugin_slug = $_POST['plugin_slug']; 
            
                $newData = $_POST;
                unset($newData['action']);
                unset($newData['plugin_slug']);
                if (isset($newData['currency'])) {
                    $newData['currency'] = pp_normalize_currency_code($newData['currency']);
                }
                if (!isset($newData['direct_currencies']) || trim((string) $newData['direct_currencies']) === '') {
                    $newData['direct_currencies'] = $newData['currency'] ?? '';
                } elseif (is_string($newData['direct_currencies'])) {
                    $newData['direct_currencies'] = implode(',', pp_normalize_gateway_currency_list($newData['direct_currencies'], $newData['currency'] ?? ''));
                }
                if (!isset($newData['supported_currencies']) || trim((string) $newData['supported_currencies']) === '') {
                    $newData['supported_currencies'] = '';
                } elseif (is_string($newData['supported_currencies'])) {
                    $newData['supported_currencies'] = implode(',', pp_normalize_gateway_currency_list($newData['supported_currencies'], ''));
                }
                if (!isset($newData['allow_currency_conversion'])) {
                    $newData['allow_currency_conversion'] = 'disable';
                }
                if (!isset($newData['show_when_unavailable'])) {
                    $newData['show_when_unavailable'] = 'enable';
                }
                unset($newData['gateway_currency'], $newData['is_currency_converted']);
                if (isset($_FILES['qr_code']) && $_FILES['qr_code']['error'] === UPLOAD_ERR_OK) {
                    $allowedTypes = ['image/png', 'image/jpeg', 'image/webp'];
                    $mime = mime_content_type($_FILES['qr_code']['tmp_name']);
                    if (in_array($mime, $allowedTypes, true) && $_FILES['qr_code']['size'] <= 5 * 1024 * 1024) {
                        $uploadDir = __DIR__ . '/../pp-content/uploads/gateway-assets/';
                        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                        $extension = strtolower(pathinfo($_FILES['qr_code']['name'], PATHINFO_EXTENSION));
                        $filename = $plugin_slug . '-qr-' . bin2hex(random_bytes(8)) . '.' . $extension;
                        if (move_uploaded_file($_FILES['qr_code']['tmp_name'], $uploadDir . $filename)) {
                            $newData['qr_code'] = pp_get_site_url() . '/pp-content/uploads/gateway-assets/' . $filename;
                        }
                    }
                }
                $success = pp_set_plugin_setting($plugin_slug, $newData);
            
                header('Content-Type: application/json');
                if ($success) {
                    echo json_encode(['status' => true, 'message' => 'Settings saved successfully!']);
                } else {
                    echo json_encode(['status' => false, 'message' => 'Failed to save settings.']);
                }
            }
            
            
            
            if($action == "theme_update-submit"){
                if ($mode == "demo") {
                    echo json_encode(["status" => "false", "message" => "Error: Demo mode is active"]);
                    exit();
                }
                
                $theme_slug = $_POST['theme_slug']; 
            
                $newData = $_POST;
                unset($newData['action']);
                unset($newData['theme_slug']);
                
                $success = pp_set_theme_setting($theme_slug, $newData);
            
                header('Content-Type: application/json');
                
                if ($success) {
                    echo json_encode(['status' => true, 'message' => 'Settings saved successfully!']);
                } else {
                    echo json_encode(['status' => false, 'message' => 'Failed to save settings.']);
                }
            }
            
            
            
            
            if($action == "pp-invoice-payment-link"){
                header('Content-Type: application/json');

                if (!empty($_POST['website'] ?? '')) {
                    echo json_encode(['status' => false, 'message' => 'Please fill all required payment details.']);
                    exit();
                }

                $plRateIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
                $plRateFile = __DIR__ . '/../cache/payment_link_rate_limit.json';
                $plRateData = [];
                if (file_exists($plRateFile)) {
                    $plRateData = json_decode(@file_get_contents($plRateFile), true) ?? [];
                }
                $plRateKey = 'ip_' . preg_replace('/[^a-zA-Z0-9_:\.]/', '', $plRateIp);
                $plNow = time();
                if (isset($plRateData[$plRateKey]) && ($plNow - $plRateData[$plRateKey]['first']) < 600) {
                    if ($plRateData[$plRateKey]['count'] >= 20) {
                        echo json_encode(['status' => false, 'message' => 'Too many requests. Please try again later.']);
                        exit();
                    }
                    $plRateData[$plRateKey]['count']++;
                } else {
                    $plRateData[$plRateKey] = ['count' => 1, 'first' => $plNow];
                }
                @file_put_contents($plRateFile, json_encode($plRateData));

                $full_name = trim((string)($_POST['full-name'] ?? ''));
                $email_mobile = trim((string)($_POST['email-mobile'] ?? ''));
                $paymentid = preg_replace('/[^a-zA-Z0-9\-_]/', '', (string)($_POST['pp-paymentid'] ?? ''));

                if (strlen($full_name) > 100) {
                    $full_name = substr($full_name, 0, 100);
                }
                $emailIsValid = filter_var($email_mobile, FILTER_VALIDATE_EMAIL) !== false;
                $mobileIsValid = preg_match('/^\+?[0-9]{6,15}$/', $email_mobile) === 1;
                if ($email_mobile === '' || strlen($email_mobile) > 100 || (!$emailIsValid && !$mobileIsValid)) {
                    echo json_encode(['status' => false, 'message' => 'Enter a valid email address or mobile number.']);
                    exit();
                }
                
                $response_transaction_checker = json_decode(getData($db_prefix.'payment_link','WHERE pl_id="'.$paymentid.'"'),true);
                if($response_transaction_checker['status'] == true){
                    $definedFields = [];
                    $linkItems = function_exists('pp_get_payment_link_items') ? pp_get_payment_link_items($paymentid) : ['status' => false];
                    if (isset($linkItems['status']) && $linkItems['status'] == true && !empty($linkItems['response'])) {
                        foreach ($linkItems['response'] as $itemRow) {
                            if (!empty($itemRow['pl_field_name'])) {
                                $definedFields[] = (string)$itemRow['pl_field_name'];
                            }
                        }
                    }
                    $internalKeys = ['full-name', 'email-mobile', 'submit', 'action', 'pp-paymentid', 'website', 'csrf_token'];

                    $meta = [];

                    foreach ($_POST as $key => $value) {
                        if (in_array($key, $internalKeys, true)) {
                            continue;
                        }
                        if (!in_array($key, $definedFields, true)) {
                            continue;
                        }
                        if (is_array($value)) {
                            continue;
                        }
                        $meta[$key] = substr(trim((string)$value), 0, 1000);
                    }

                    foreach ($_FILES as $key => $file) {
                        if (!in_array($key, $definedFields, true)) {
                            continue;
                        }
                        if (is_array($file['name'])) {
                            // Multiple files (file input had name="something[]")
                            for ($i = 0; $i < count($file['name']); $i++) {
                                if ($file['error'][$i] === 0) {
                                    $single_file = [
                                        'name'     => $file['name'][$i],
                                        'type'     => $file['type'][$i],
                                        'tmp_name' => $file['tmp_name'][$i],
                                        'error'    => $file['error'][$i],
                                        'size'     => $file['size'][$i],
                                    ];
                                    $upload = json_decode(uploadImage($single_file, 10 * 1024 * 1024), true);
                    
                                    if ($upload['status'] === true) {
                                        $file_url = 'https://' . $_SERVER['HTTP_HOST'] . '/pp-external/media/' . $upload['file'];
                                        $meta[$key][] = $file_url;
                                    } else {
                                        $meta[$key][] = 'upload_failed';
                                    }
                                }
                            }
                        } else {
                            // Single file input
                            if ($file['error'] === 0) {
                                $upload = json_decode(uploadImage($file, 10 * 1024 * 1024), true);
                    
                                if ($upload['status'] === true) {
                                    $file_url = 'https://' . $_SERVER['HTTP_HOST'] . '/pp-external/media/' . $upload['file'];
                                    $meta[$key] = $file_url;
                                } else {
                                    $meta[$key] = 'upload_failed';
                                }
                            }
                        }
                    }
    
                    if ($full_name === '') {
                        $full_name = $email_mobile;
                    }
                    $amount = safeNumber($response_transaction_checker['response'][0]['pl_amount']);
                    $currency = strtoupper(trim((string) $response_transaction_checker['response'][0]['pl_currency']));
                    $metadata = json_encode(['paymentid' => $paymentid]);
                    $product_meta = json_encode($meta);
                    $pp_id = bin2hex(random_bytes(16));
                    $payment_link_url = 'https://' . $_SERVER['HTTP_HOST'] . '/payment-link/' . $paymentid;

                    if ($amount <= 0 || $currency === '' || $metadata === false || $product_meta === false) {
                        echo json_encode(['status' => false, 'message' => 'Please fill all required payment details.']);
                        exit();
                    }
                    
                    $columns = ['pp_id', 'c_id', 'c_name', 'c_email_mobile', 'transaction_amount', 'transaction_fee', 'transaction_refund_amount', 'transaction_currency', 'transaction_redirect_url', 'transaction_return_type', 'transaction_cancel_url', 'transaction_webhook_url', 'transaction_metadata', 'transaction_status', 'transaction_product_name', 'transaction_product_description', 'transaction_product_meta', 'created_at'];
                    $values = [
                        $pp_id,
                        '--',
                        $full_name,
                        $email_mobile,
                        $amount,
                        0,
                        0,
                        $currency,
                        '--',
                        'POST',
                        $payment_link_url,
                        '--',
                        $metadata,
                        'initialize',
                        $response_transaction_checker['response'][0]['pl_name'],
                        $response_transaction_checker['response'][0]['pl_description'],
                        $product_meta,
                        getCurrentDatetime('Y-m-d H:i:s')
                    ];
                    
                    if (insertData($db_prefix.'transaction', $columns, $values)) {
                        echo json_encode(['status' => true, 'pp_id' => $pp_id, 'pp_url' => 'https://' . $_SERVER['HTTP_HOST'] . '/payment/' . $pp_id]);
                        if (function_exists('pp_trigger_hook')) {
                            // New transaction created, customer has not paid yet.
                            pp_trigger_hook('pp_transaction_created', $pp_id);
                        }
                    } else {
                        echo json_encode(['status' => false, 'message' => 'Unable to initialize payment. Please try again.']);
                    }
                }else{
                    header('Content-Type: application/json');
                    echo json_encode(['status' => false, 'message' => 'Failed.']);
                }
            }
            
            
            
            
            
            
            if($action == "pp-invoice-link"){

                $invoiceid = $_POST['pp-invoiceid'] ?? '';
                
                $response_transaction_checker = json_decode(getData($db_prefix.'invoice','WHERE i_id="'.$invoiceid.'"'),true);
                if($response_transaction_checker['status'] == true){
                    $invoice_details = pp_get_invoice($invoiceid);
                    $invoice_details_items = pp_get_invoice_items($invoiceid);

                    $subtotal = 0;
                    $total_discount = 0;
                    $total_vat = 0;
        
                    foreach ($invoice_details_items['response'] as $items) {
                        $item_subtotal = $items['amount'] * $items['quantity'];
                        $item_discount = min($items['discount'], $item_subtotal); 
                        $item_amount_after_discount = $item_subtotal - $item_discount;
                        $item_vat = $item_amount_after_discount * ($items['vat'] / 100);
        
                        $subtotal += $item_subtotal;
                        $total_discount += $item_discount;
                        $total_vat += $item_vat;
                    }
        
                    $shipping_cost = isset($invoice_details['response'][0]['i_amount_shipping']) ? floatval($invoice_details['response'][0]['i_amount_shipping']) : 0;
        
                    $total_amount = $subtotal - $total_discount + $total_vat + $shipping_cost;
                    $currency = $invoice_details['response'][0]['i_currency'];

                    $baseURL = 'https://'.$_SERVER['HTTP_HOST'].'/api/create-charge';
                    
                    $payload = [
                        'full_name'         => $invoice_details['response'][0]['c_name'],
                        'email_mobile'      => $invoice_details['response'][0]['c_email_mobile'],
                        'amount'            => $total_amount,
                        'currency'          => $currency,
                        'metadata'          => [
                            'invoiceid'   => $invoiceid
                        ],
                        'redirect_url'      => 'https://'.$_SERVER['HTTP_HOST'].'/invoice/'.$invoiceid,
                        'cancel_url'        => 'https://'.$_SERVER['HTTP_HOST'].'/invoice/'.$invoiceid,
                        'webhook_url'       => 'https://'.$_SERVER['HTTP_HOST'].'/invoice/'.$invoiceid,
                        'return_type'       => 'POST'
                    ];
                    
                    // Initialize cURL
                    $curl = curl_init();
                    
                    curl_setopt_array($curl, [
                        CURLOPT_URL            => $baseURL,
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_POST           => true,
                        CURLOPT_POSTFIELDS     => json_encode($payload),
                        CURLOPT_HTTPHEADER     => [
                            'Content-Type: application/json',
                            'mh-piprapay-api-key:' .$global_setting_response['response'][0]['api_key']
                        ],
                    ]);
                    
                    $response = curl_exec($curl);
                    echo $response;
                    curl_close($curl);
                }else{
                    echo json_encode(['status' => false, 'message' => 'Failed.']);
                }
            }
            
            
            
        }
        exit();
    }
?>