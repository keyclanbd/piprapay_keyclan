<?php
if (!defined('pp_allowed_access')) {
    die('Direct access not allowed');
}

// Hooks
add_action('pp_admin_initialize', 'two_factor_authentication_initialize');

if(isset($_POST['two-factor-authentication-action'])){
    $secret_key = escape_string($_POST['secret_key']);
    $auth_code = escape_string($_POST['auth_code']);
    $auth_status = escape_string($_POST['auth_status']);
    
    if($secret_key == "" || $auth_code == "" || $auth_status == ""){
        echo json_encode(['status' => "false", 'message' => 'Enter all info!']);
    }else{
        require __DIR__ . '/vendor-two-factor-authentication/autoload.php'; // If using Composer
        
        $ga = new PHPGangsta_GoogleAuthenticator();
    
        $isValid = $ga->verifyCode($secret_key, $auth_code, 2);
        
        if ($isValid) {
            $targetUrl = pp_get_site_url().'/admin/dashboard'; // change to your actual endpoint
            
            // Data from form (you may fetch this dynamically)
            $data = [
                'action' => 'plugin_update-submit',
                'plugin_slug' => 'two-factor-authentication',
                'secret_key' => $secret_key, // the generated 2FA secret
                'auth_code' => '', // the 2FA code user inputs
                'auth_status' => $auth_status, // or 'disable'
            ];
            
            // Initialize cURL
            $ch = curl_init($targetUrl);
            
            // Set cURL options
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data)); // form-style POST
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            
            // Execute and capture response
            $response = curl_exec($ch);
            
            curl_close($ch);
            
            $response = json_decode($response, true);
            
            if($response['status'] == "true"){
                echo json_encode(['status' => "true", 'message' => 'Code is valid! Authentication successful.']);
            }else{
                echo json_encode(['status' => "false", 'message' => $response['message']]);
            }
        } else {
            echo json_encode(['status' => "false", 'message' => 'Invalid code. Try again.']);
        }
    }
    exit();
}


// Proof that this admin session passed 2FA: bound to the session token and the 2FA secret,
// so it cannot be forged and stops working when the session ends.
function two_factor_authentication_session_proof($secret_key) {
    $session = (string) ($_COOKIE['pp_admin'] ?? '');
    if ($secret_key === '' || !preg_match('/^[a-f0-9]{64}$/i', $session)) {
        return '';
    }
    return hash_hmac('sha256', $session, $secret_key);
}

if(isset($_POST['two-factor-authentication-action-login'])){
    $auth_code = escape_string($_POST['auth_code'] ?? '');

    // At most 5 wrong codes per 5 minutes.
    $attempts = $_SESSION['pp_2fa_attempts'] ?? ['count' => 0, 'first' => time()];
    if (time() - $attempts['first'] > 300) {
        $attempts = ['count' => 0, 'first' => time()];
    }
    if ($attempts['count'] >= 5) {
        echo json_encode(['status' => "false", 'message' => 'Too many attempts. Please try again in a few minutes.']);
        exit();
    }

    if($auth_code == ""){
        echo json_encode(['status' => "false", 'message' => 'Incorrect auth code']);
    }else{
        require __DIR__ . '/vendor-two-factor-authentication/autoload.php'; // If using Composer
        
        $ga = new PHPGangsta_GoogleAuthenticator();
    
        $plugin_slug = 'two-factor-authentication';
        $settings = pp_get_plugin_setting($plugin_slug);
        
        $secret_key = $settings['secret_key'] ?? '';
        
        $isValid = $secret_key !== '' && $ga->verifyCode($secret_key, $auth_code, 2);
        $proof = two_factor_authentication_session_proof($secret_key);

        if ($isValid && $proof !== '') {
            unset($_SESSION['pp_2fa_attempts']);
            setsCookie('pp_two_factor_authentication', $proof);

            echo json_encode(['status' => "true", 'target' => 'dashboard']);
        } else {
            $attempts['count']++;
            $_SESSION['pp_2fa_attempts'] = $attempts;
            echo json_encode(['status' => "false", 'message' => 'Invalid code. Try again.']);
        }
    }
    exit();
}



// ✅ Helper: Send OneSignal Notification to Admin
function two_factor_authentication_initialize() {
    $plugin_slug = 'two-factor-authentication';
    $settings = pp_get_plugin_setting($plugin_slug);
    
    $auth_status = $settings['auth_status'] ?? '';
    
    if($auth_status == "enable"){
        $proof = two_factor_authentication_session_proof((string) ($settings['secret_key'] ?? ''));
        if($proof !== '' && hash_equals($proof, (string) ($_COOKIE['pp_two_factor_authentication'] ?? ''))){
            
        }else{
           // setsCookie('pp_piprapay_vercel_themepaid', "paid");
           
            $viewFile = __DIR__ . '/views/two-factor-authentication-ui.php';
        
            if (file_exists($viewFile)) {
                include $viewFile;
            } else {
                echo "<div class='alert alert-warning'>UI not found.</div>";
            }
            exit();
        }
    }
}