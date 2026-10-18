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
    
    if(isset($_GET['name'])){
        $payment_id = escape_string($_GET['name']);
        
        if($payment_id == ""){
            $error_title = "Invalid Payment";
            $error_description = "The transaction you're trying to use is not valid. Please check your details and try again, or contact support for help.";
            
            include(__DIR__."/../error.php");
            
            exit();
        }else{
            $transaction_details = pp_get_transation($payment_id);
            $setting = pp_get_settings();
            
            if($transaction_details['status'] == true){
                $createdAt = $transaction_details['response'][0]['created_at'] ?? '--';
                $timeout = defined('PP_PAYMENT_SESSION_TIMEOUT') ? (int) PP_PAYMENT_SESSION_TIMEOUT : 600;
                $createdTime = ($createdAt !== '--') ? strtotime($createdAt) : false;

                if ($createdTime !== false && (time() - $createdTime) > $timeout) {
                    // Session window has closed. Mark it failed so a refresh can
                    // never hand the customer a fresh countdown.
                    pp_set_transaction_status($payment_id, 'failed');
                    $cancelRedirect = $transaction_details['response'][0]['transaction_cancel_url'] ?? '';
                    $expiredTarget = ($cancelRedirect !== '' && $cancelRedirect !== '--')
                        ? $cancelRedirect
                        : 'https://' . $_SERVER['HTTP_HOST'];
                    header('Location: ' . $expiredTarget);
                    exit();
                }
                theme_include('gateway', $setting['response'][0]['gateway_theme'], $setting['response'][0]['gateway_theme'].'-class.php', $payment_id, 'payment_id');
                        
                $function = str_replace('-', '_', $setting['response'][0]['gateway_theme']) . '_checkout_load';
                if (function_exists($function)) {
                    call_user_func($function, $payment_id);
                }
                
                if (function_exists('pp_trigger_hook')) {
                    pp_trigger_hook('pp_payment_initialize');
                }
            }else{
                $error_title = "Invalid Payment";
                $error_description = "The transaction you're trying to use is not valid. Please check your details and try again, or contact support for help.";
                
                include(__DIR__."/../error.php");
                
                exit();
            }
        }
    }else{
        $error_title = "Invalid Payment";
        $error_description = "The transaction you're trying to use is not valid. Please check your details and try again, or contact support for help.";
        
        include(__DIR__."/../error.php");
        
        exit();
    }