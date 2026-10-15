<?php
    if (!defined('pp_allowed_access')) {
        die('Direct access not allowed');
    }

    add_action('pp_cron', 'pp_expire_stale_payment_sessions');

    function pp_expire_stale_payment_sessions() {
        $timeout = defined('PP_PAYMENT_SESSION_TIMEOUT') ? PP_PAYMENT_SESSION_TIMEOUT : 600;
        $expiryDateTime = date('Y-m-d H:i:s', strtotime("-{$timeout} seconds"));

        $conn = connectDatabase();
        global $db_prefix;

        $sql = "SELECT pp_id FROM {$db_prefix}transaction WHERE transaction_status = 'initialize' AND created_at < '{$expiryDateTime}'";
        $result = mysqli_query($conn, $sql);

        if ($result && mysqli_num_rows($result) > 0) {
            while ($row = mysqli_fetch_assoc($result)) {
                pp_set_transaction_status($row['pp_id'], 'failed');
            }
        }
    }
?>