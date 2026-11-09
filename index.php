<?php
if (file_exists(__DIR__."/pp-config.php")) {
    if (file_exists(__DIR__.'/maintenance.lock')) {
        if (file_exists(__DIR__.'/pp-include/pp-maintenance.php')) {
            include(__DIR__."/pp-include/pp-maintenance.php");
        } else {
            die('System is under maintenance. Please try again later.');
        }
    } else {
        if (file_exists(__DIR__.'/pp-include/pp-controller.php')) {
            include_once(__DIR__."/pp-include/pp-controller.php");
        } else {
            echo 'System is under maintenance. Please try again later.';
            exit();
        }
        if (file_exists(__DIR__.'/pp-include/pp-model.php')) {
            include_once(__DIR__."/pp-include/pp-model.php");
        } else {
            echo 'System is under maintenance. Please try again later.';
            exit();
        }

        if (isset($_GET['webhook'])) {
            $webhook = (string) $_GET['webhook'];

            // A site that never generated a key still holds the '--' default, which must not match.
            if (!preg_match('/^[A-Za-z0-9]{16,}$/', $webhook)) {
                echo 'System is under maintenance. Please try again later.';
                exit();
            } else {
                $response = json_decode(getData($db_prefix.'settings', 'WHERE webhook="'.$webhook.'"'), true);
                if ($response['status'] == true) {
                    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
                    if ($userAgent !== 'mh-piprapay-api-key') {
                        echo json_encode(['status' => "false", 'message' => "Invalid Webhook"]);
                        exit();
                    }
                    $d_status = "Pairing";
                    if (isset($_POST['d_model']) && isset($_POST['d_brand']) && isset($_POST['d_version']) && isset($_POST['d_api_level'])) {
                        $d_model = escape_string($_POST['d_model']);
                        $d_brand = escape_string($_POST['d_brand']);
                        $d_version = escape_string($_POST['d_version']);
                        $d_api_level = escape_string($_POST['d_api_level']);

                        $response_device = json_decode(getData($db_prefix.'devices', 'WHERE d_model="'.$d_model.'" AND  d_brand="'.$d_brand.'" AND  d_version="'.$d_version.'" AND  d_api_level="'.$d_api_level.'" '), true);
                        if ($response_device['status'] == true) {

                            if (isset($_POST['connection_status'])) {
                                $d_status = escape_string($_POST['connection_status']);
                            } else {
                                $d_status = "Connected";
                            }

                            $columns = ['d_status',
                                'created_at'];
                            $values = [$d_status,
                                getCurrentDatetime('Y-m-d H:i:s')];
                            $condition = "id = '".$response_device['response'][0]['id']."'";

                            updateData($db_prefix.'devices', $columns, $values, $condition);
                        } else {
                            $columns = ['d_id',
                                'd_model',
                                'd_brand',
                                'd_version',
                                'd_api_level',
                                'd_status',
                                'created_at'];
                            $values = [rand(),
                                $d_model,
                                $d_brand,
                                $d_version,
                                $d_api_level,
                                'Connected',
                                getCurrentDatetime('Y-m-d H:i:s')];

                            insertData($db_prefix.'devices', $columns, $values);
                        }
                    }

                    $payload = file_get_contents('php://input');
                    $decoded = json_decode($payload, true);

                    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

                    if ($userAgent === 'mh-piprapay-api-key') {
                        $from = $decoded['from'] ?? ($_POST['from'] ?? '');
                        $text = $decoded['text'] ?? ($_POST['text'] ?? '');
                        $sentStamp = $decoded['sentStamp'] ?? ($_POST['sentStamp'] ?? '');
                        $receivedStamp = $decoded['receivedStamp'] ?? ($_POST['receivedStamp'] ?? '');
                        $sim = $decoded['sim'] ?? ($_POST['sim'] ?? '');

                        if ($sim == 1) {
                            $sim = "sim1";
                        } else {
                            if ($sim == 2) {
                                $sim = "sim2";
                            }
                        }

                        include_once(__DIR__ . '/pp-include/pp-sms-parser.php');
                        $parsedSms = pp_parse_forwarded_sms($from, $text, $receivedStamp);

                        if ($parsedSms !== null && $parsedSms['store']) {
                            $columns = ['entry_type',
                                'sim',
                                'payment_method',
                                'mobile_number',
                                'transaction_id',
                                'amount',
                                'balance',
                                'message',
                                'status',
                                'created_at'];
                            $values = [
                                'automatic',
                                $sim,
                                $parsedSms['provider'],
                                $parsedSms['data']['mobile'],
                                $parsedSms['data']['trxid'],
                                $parsedSms['data']['amount'],
                                $parsedSms['data']['balance'],
                                $text,
                                $parsedSms['status'],
                                $parsedSms['data']['datetime']
                            ];
                            insertData($db_prefix . 'sms_data', $columns, $values);
                        }
                    }


                    echo json_encode(['status' => "true", 'message' => "Device ".$d_status]);
                    exit();
                } else {
                    echo json_encode(['status' => "false", 'message' => "Invalid Webhook"]);
                    exit();
                }
            }
        } else {
            if (isset($_GET['cron'])) {
                if (function_exists('pp_trigger_hook')) {
                    pp_trigger_hook('pp_cron');
                }

                echo json_encode(['status' => "false", 'message' => "Direct access not allowed"]);
                exit();
            } else {
                // The public root doesn't point visitors at the admin area.
                include __DIR__ . '/pp-include/pp-landing.php';
                exit();
            }
        }
    }
} else {
    ?>
    <script>
        location.href = "https://<?php echo $_SERVER['HTTP_HOST'] ?>/install/";
    </script>
    <?php
}
?>