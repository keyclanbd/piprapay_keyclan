<?php
    if (!defined('pp_allowed_access')) {
        die('Direct access not allowed');
    }

add_action('pp_cron', 'auto_update_checker');

function auto_update_checker() {
    $logFile = __DIR__ . '/pp-auto-update-log.json';
    $checkInterval = 24 * 60 * 60; // 24 hours in seconds
    
    // Load last log
    $lastLog = [];
    if (file_exists($logFile)) {
        $lastLog = json_decode(file_get_contents($logFile), true);
    }
    
    // Check if last update was less than 24 hours ago
    $lastCheckTime = isset($lastLog['last_check']) ? strtotime($lastLog['last_check']) : 0;
    $now = time();
    
    // Return rather than exit, so the other pp_cron hooks still run.
    if ($now - $lastCheckTime < $checkInterval) {
        return;
    }
    
    // Perform cURL update
    $url = "https://{$_SERVER['HTTP_HOST']}/pp-auto-update";
    $postData = [
        'auto-update' => 'start',
        'mh-piprapay-auto-update' => pp_internal_token('auto-update')
    ];
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
    
    $response = curl_exec($ch);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    // Prepare log entry
    $logEntry = [
        'last_check' => date('Y-m-d H:i:s'),
        'response_raw' => $response,
        'error' => $curlError ?: 'none'
    ];
    
    // Save log
    file_put_contents($logFile, json_encode($logEntry, JSON_PRETTY_PRINT));
    
    // Output response
    if ($curlError) {
        echo json_encode([
            'status' => 'false',
            'message' => 'cURL error: ' . $curlError
        ]);
    } else {
        echo $response; // assuming it's already JSON from server
    }
}