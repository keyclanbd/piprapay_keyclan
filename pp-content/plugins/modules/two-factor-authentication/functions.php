<?php
if (!defined('pp_allowed_access')) {
    die('Direct access not allowed');
}

// Hooks
add_action('pp_admin_initialize', 'two_factor_authentication_initialize');

if(isset($_POST['two-factor-authentication-action'])){
    // This file is loaded on every page that fires a hook, so the save checks login and CSRF itself
    // (it runs inside pp_trigger_hook(), hence $GLOBALS).
    if (empty($GLOBALS['global_user_login'])) {
        echo json_encode(['status' => "false", 'message' => 'Unauthorized request']);
        exit();
    }
    if (!validateCsrfToken($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) {
        echo json_encode(['status' => "false", 'message' => 'Invalid security token. Please refresh the page and try again.']);
        exit();
    }
    if (($GLOBALS['mode'] ?? '') == "demo") {
        echo json_encode(['status' => "false", 'message' => 'Error: Demo mode is active']);
        exit();
    }

    $plugin_slug = 'two-factor-authentication';
    $current = pp_get_plugin_setting($plugin_slug);

    // While 2FA is on, only a session that already passed it may change it; a password alone is not enough.
    if (($current['auth_status'] ?? '') == "enable") {
        $currentProof = two_factor_authentication_session_proof((string) ($current['secret_key'] ?? ''));
        if ($currentProof === '' || !hash_equals($currentProof, (string) ($_COOKIE['pp_two_factor_authentication'] ?? ''))) {
            echo json_encode(['status' => "false", 'message' => 'Unauthorized request']);
            exit();
        }
    }

    $secret_key = escape_string($_POST['secret_key'] ?? '');
    $auth_code = escape_string($_POST['auth_code'] ?? '');
    $auth_status = escape_string($_POST['auth_status'] ?? '');

    if($secret_key == "" || $auth_code == "" || !in_array($auth_status, ['enable', 'disable'], true)){
        echo json_encode(['status' => "false", 'message' => 'Enter all info!']);
    }else{
        require __DIR__ . '/vendor-two-factor-authentication/autoload.php'; // If using Composer

        $ga = new PHPGangsta_GoogleAuthenticator();

        $isValid = $ga->verifyCode($secret_key, $auth_code, 2);

        if ($isValid) {
            // Saved directly: the old server-to-server POST to /admin/dashboard had no session or CSRF token, so it was always rejected.
            $saved = pp_set_plugin_setting($plugin_slug, [
                'secret_key' => $secret_key,
                'auth_code' => '',
                'auth_status' => $auth_status,
            ]);

            if($saved){
                // The admin just proved the code, so don't ask for it again on the next page.
                $proof = two_factor_authentication_session_proof($secret_key);
                if ($auth_status == "enable" && $proof !== '') {
                    setsCookie('pp_two_factor_authentication', $proof);
                }
                echo json_encode(['status' => "true", 'message' => 'Code is valid! Authentication successful.']);
            }else{
                echo json_encode(['status' => "false", 'message' => 'Failed to save settings.']);
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