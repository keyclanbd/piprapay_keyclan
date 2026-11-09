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
} else {
    ?>
    <script>
        location.href = "https://<?php echo $_SERVER['HTTP_HOST'] ?>/install/";
    </script>
    <?php
}
?>