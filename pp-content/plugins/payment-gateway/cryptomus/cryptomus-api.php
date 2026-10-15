<?php
/**
 * Cryptomus API Integration
 * 
 * @package iPayees
 * @author iPayees Team
 * @version 1.0.0
 */

class CryptomusAPI {
    
    private $payment_key;
    private $merchant_uuid;
    private $base_url = 'https://api.cryptomus.com/v1';
    
    /**
     * Constructor
     */
    public function __construct($payment_key, $merchant_uuid) {
        $this->payment_key = trim($payment_key);
        $this->merchant_uuid = trim($merchant_uuid);
        
        error_log("CryptomusAPI initialized");
        error_log("Merchant UUID: " . $this->merchant_uuid);
    }
    
    /**
     * Generate signature for API request
     * Signature = MD5(base64_encode(json_body) + API_KEY)
     */
    private function generateSignature($data) {
        $json_data = json_encode($data, JSON_UNESCAPED_UNICODE);
        $base64_data = base64_encode($json_data);
        $signature = md5($base64_data . $this->payment_key);
        
        error_log("Signature generated for data: " . $json_data);
        
        return $signature;
    }
    
    /**
     * Make API request to Cryptomus
     */
    private function makeRequest($method, $endpoint, $data = []) {
        $url = $this->base_url . $endpoint;
        
        // Generate signature
        $signature = $this->generateSignature($data);
        
        // Prepare headers
        $headers = [
            'merchant: ' . $this->merchant_uuid,
            'sign: ' . $signature,
            'Content-Type: application/json'
        ];
        
        error_log("=== Cryptomus API Request ===");
        error_log("Method: " . $method);
        error_log("URL: " . $url);
        error_log("Data: " . json_encode($data));
        
        // Initialize cURL
        $ch = curl_init();
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_CONNECTTIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_USERAGENT => 'iPayees-Cryptomus/1.0'
        ]);
        
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
        
        // Execute request
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        $curl_errno = curl_errno($ch);
        
        curl_close($ch);
        
        error_log("=== Cryptomus API Response ===");
        error_log("HTTP Code: " . $http_code);
        error_log("Response: " . $response);
        
        // Handle cURL errors
        if ($curl_errno) {
            error_log("cURL Error: " . $curl_error);
            return [
                'success' => false,
                'error' => 'Network error: ' . $curl_error,
                'error_code' => 'CURL_ERROR'
            ];
        }
        
        // Parse response
        $decoded = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("JSON Error: " . json_last_error_msg());
            return [
                'success' => false,
                'error' => 'Invalid JSON response',
                'error_code' => 'JSON_ERROR',
                'raw_response' => $response
            ];
        }
        
        // Check API response status
        // Cryptomus returns: state: 0 = success, state: 1 = error
        if (isset($decoded['state']) && $decoded['state'] == 0) {
            return [
                'success' => true,
                'data' => $decoded['result'] ?? $decoded,
                'http_code' => $http_code
            ];
        } else {
            $error_message = $decoded['message'] ?? $decoded['errors'] ?? 'Unknown error';
            if (is_array($error_message)) {
                $error_message = json_encode($error_message);
            }
            
            return [
                'success' => false,
                'error' => $error_message,
                'error_code' => 'API_ERROR',
                'http_code' => $http_code,
                'raw_response' => $response
            ];
        }
    }
    
    /**
     * Create Payment Invoice
     * 
     * @param array $data Payment data
     * @return array API response
     */
    public function createPayment($data) {
        error_log("Creating Cryptomus payment with data: " . json_encode($data));
        
        // Required fields validation
        $required = ['amount', 'currency', 'order_id'];
        foreach ($required as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                return [
                    'success' => false,
                    'error' => "Missing required field: {$field}",
                    'error_code' => 'VALIDATION_ERROR'
                ];
            }
        }
        
        // Create payment request
        $result = $this->makeRequest('POST', '/payment', $data);
        
        if ($result['success']) {
            error_log("Payment created successfully. UUID: " . ($result['data']['uuid'] ?? 'N/A'));
        } else {
            error_log("Payment creation failed: " . $result['error']);
        }
        
        return $result;
    }
    
    /**
     * Get Payment Information
     * 
     * @param string $uuid Payment UUID
     * @param string $order_id Order ID (alternative to UUID)
     * @return array API response
     */
    public function getPaymentInfo($uuid = null, $order_id = null) {
        if (empty($uuid) && empty($order_id)) {
            return [
                'success' => false,
                'error' => 'UUID or Order ID is required',
                'error_code' => 'VALIDATION_ERROR'
            ];
        }
        
        $data = [];
        if (!empty($uuid)) {
            $data['uuid'] = $uuid;
        } else {
            $data['order_id'] = $order_id;
        }
        
        error_log("Getting payment info for: " . json_encode($data));
        
        return $this->makeRequest('POST', '/payment/info', $data);
    }
    
    /**
     * Create Static Wallet
     * 
     * @param array $data Wallet data
     * @return array API response
     */
    public function createWallet($data) {
        error_log("Creating Cryptomus wallet with data: " . json_encode($data));
        
        // Required fields validation
        $required = ['currency', 'network', 'order_id'];
        foreach ($required as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                return [
                    'success' => false,
                    'error' => "Missing required field: {$field}",
                    'error_code' => 'VALIDATION_ERROR'
                ];
            }
        }
        
        return $this->makeRequest('POST', '/wallet', $data);
    }
    
    /**
     * Generate QR Code for Payment
     * 
     * @param string $payment_uuid Payment UUID
     * @return array API response
     */
    public function generatePaymentQR($payment_uuid) {
        if (empty($payment_uuid)) {
            return [
                'success' => false,
                'error' => 'Payment UUID is required',
                'error_code' => 'VALIDATION_ERROR'
            ];
        }
        
        $data = ['merchant_payment_uuid' => $payment_uuid];
        
        return $this->makeRequest('POST', '/payment/qr', $data);
    }
    
    /**
     * Verify webhook signature
     * 
     * @param array $data Webhook data
     * @return bool True if signature is valid
     */
    public function verifyWebhookSignature($data) {
        if (!isset($data['sign'])) {
            error_log("Webhook validation failed: Missing signature");
            return false;
        }
        
        $received_signature = $data['sign'];
        unset($data['sign']);
        
        $expected_signature = $this->generateSignature($data);
        
        $is_valid = hash_equals($expected_signature, $received_signature);
        
        error_log("Webhook signature validation: " . ($is_valid ? 'VALID' : 'INVALID'));
        
        return $is_valid;
    }
    
    /**
     * Test API connection
     * 
     * @return array Connection test result
     */
    public function testConnection() {
        try {
            // Try to create a dummy payment to test credentials
            $test_data = [
                'amount' => '1',
                'currency' => 'USD',
                'order_id' => 'test_' . time()
            ];
            
            $result = $this->makeRequest('POST', '/payment', $test_data);
            
            // Even if payment creation fails due to minimum amount, connection is OK
            if ($result['success'] || (isset($result['http_code']) && $result['http_code'] == 422)) {
                return [
                    'success' => true,
                    'message' => 'Cryptomus API connection successful',
                    'timestamp' => date('Y-m-d H:i:s')
                ];
            }
            
            return [
                'success' => false,
                'message' => 'Connection failed: ' . ($result['error'] ?? 'Unknown error'),
                'timestamp' => date('Y-m-d H:i:s')
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Connection test failed: ' . $e->getMessage(),
                'timestamp' => date('Y-m-d H:i:s')
            ];
        }
    }
}
?>

