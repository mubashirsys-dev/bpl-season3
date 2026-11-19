<?php
/**
 * BUDDILANE PREMIER LEAGUE — SEASON 3 (2026–27)
 * Official Razorpay Payment Integration & Verification Module
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';

class RazorpayService {
    public const API_BASE = 'https://api.razorpay.com/v1';

    /**
     * Get active Razorpay credentials and configuration from database settings
     */
    public static function getConfig(): array {
        static $cachedConfig = null;
        if ($cachedConfig !== null) {
            return $cachedConfig;
        }

        try {
            $db = getDB();
            $stmt = $db->query("SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE 'razorpay_%'");
            $rows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

            $cachedConfig = [
                'mode'           => $rows['razorpay_mode'] ?? 'test',
                'key_id'         => trim($rows['razorpay_key_id'] ?? ''),
                'key_secret'     => trim($rows['razorpay_key_secret'] ?? ''),
                'webhook_secret' => trim($rows['razorpay_webhook_secret'] ?? ''),
            ];
        } catch (Exception $e) {
            error_log("Failed to load Razorpay settings: " . $e->getMessage());
            $cachedConfig = [
                'mode'           => 'test',
                'key_id'         => '',
                'key_secret'     => '',
                'webhook_secret' => '',
            ];
        }

        return $cachedConfig;
    }

    /**
     * Create an official Razorpay Order via REST API
     *
     * @param int $amountInPaise Amount in lowest denomination (e.g., 10000 for ₹100)
     * @param string $receipt Unique receipt identifier (e.g., Registration ID)
     * @param array $notes Optional metadata
     * @return array [success => bool, order_id => string, data => array, error => string]
     */
    public static function createOrder(int $amountInPaise, string $receipt, array $notes = []): array {
        $config = self::getConfig();
        $keyId = $config['key_id'];
        $keySecret = $config['key_secret'];

        if (empty($keyId) || empty($keySecret)) {
            return [
                'success' => false,
                'error'   => 'Razorpay API keys are not configured. Please contact the administrator.'
            ];
        }

        $payload = [
            'amount'   => $amountInPaise,
            'currency' => 'INR',
            'receipt'  => $receipt,
            'notes'    => $notes,
            'payment_capture' => 1 // Auto-capture payments
        ];

        // Send request to Razorpay Orders API
        $response = self::callApi('POST', '/orders', $payload, $keyId, $keySecret);

        if ($response['http_code'] === 200 || $response['http_code'] === 201) {
            $orderData = json_decode($response['body'], true);
            if (isset($orderData['id'])) {
                return [
                    'success'  => true,
                    'order_id' => $orderData['id'],
                    'amount'   => $orderData['amount'],
                    'currency' => $orderData['currency'],
                    'data'     => $orderData
                ];
            }
        }

        // If in test mode and network is isolated or using dummy placeholder keys, allow graceful simulation for testing
        if ($config['mode'] === 'test' && (str_contains($keyId, 'placeholder') || str_contains($keyId, 'rzp_test_51a2b3c4d5e6f7') || $response['http_code'] === 0)) {
            $simulatedOrderId = 'order_test_' . substr(md5($receipt . time()), 0, 14);
            return [
                'success'   => true,
                'order_id'  => $simulatedOrderId,
                'amount'    => $amountInPaise,
                'currency'  => 'INR',
                'is_mock'   => true,
                'data'      => [
                    'id'       => $simulatedOrderId,
                    'entity'   => 'order',
                    'amount'   => $amountInPaise,
                    'currency' => 'INR',
                    'receipt'  => $receipt,
                    'status'   => 'created'
                ]
            ];
        }

        $errMsg = 'Failed to create Razorpay order.';
        $respData = json_decode($response['body'], true);
        if (isset($respData['error']['description'])) {
            $errMsg .= ' ' . $respData['error']['description'];
        }

        return [
            'success'   => false,
            'error'     => $errMsg,
            'http_code' => $response['http_code']
        ];
    }

    /**
     * Verify payment signature securely using HMAC-SHA256
     * Expected: HMAC-SHA256 of (order_id + "|" + payment_id) with key_secret
     */
    public static function verifyPaymentSignature(string $orderId, string $paymentId, string $signature): bool {
        $config = self::getConfig();
        $keySecret = $config['key_secret'];

        if (empty($keySecret) || empty($orderId) || empty($paymentId) || empty($signature)) {
            return false;
        }

        $expectedSignature = hash_hmac('sha256', $orderId . '|' . $paymentId, $keySecret);
        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Verify webhook signature using separate Razorpay Webhook Secret
     */
    public static function verifyWebhookSignature(string $rawPayload, string $webhookSignature): bool {
        $config = self::getConfig();
        $webhookSecret = $config['webhook_secret'];

        if (empty($webhookSecret) || empty($rawPayload) || empty($webhookSignature)) {
            return false;
        }

        $expectedSignature = hash_hmac('sha256', $rawPayload, $webhookSecret);
        return hash_equals($expectedSignature, $webhookSignature);
    }

    /**
     * Independently fetch and confirm payment details from Razorpay REST API
     */
    public static function fetchPayment(string $paymentId): array {
        $config = self::getConfig();
        $keyId = $config['key_id'];
        $keySecret = $config['key_secret'];

        if (empty($keyId) || empty($keySecret)) {
            return ['success' => false, 'error' => 'API keys missing'];
        }

        // Test mode simulation check
        if ($config['mode'] === 'test' && (str_contains($keyId, 'placeholder') || str_contains($keyId, 'rzp_test_51a2b3c4d5e6f7'))) {
            return [
                'success' => true,
                'data' => [
                    'id'       => $paymentId,
                    'status'   => 'captured',
                    'amount'   => 10000,
                    'currency' => 'INR',
                    'method'   => 'upi',
                    'captured' => true
                ]
            ];
        }

        $response = self::callApi('GET', '/payments/' . urlencode($paymentId), [], $keyId, $keySecret);

        if ($response['http_code'] === 200) {
            $data = json_decode($response['body'], true);
            return [
                'success' => true,
                'data'    => $data
            ];
        }

        return [
            'success'   => false,
            'error'     => 'Failed to fetch payment details from Razorpay API',
            'http_code' => $response['http_code']
        ];
    }

    /**
     * Helper to make secure cURL calls to Razorpay API
     */
    private static function callApi(string $method, string $endpoint, array $data, string $keyId, string $keySecret): array {
        $url = self::API_BASE . $endpoint;
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERPWD, $keyId . ':' . $keySecret);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'BPL-Payment-Client/3.0');

        $headers = ['Content-Type: application/json'];

        if (strtoupper($method) === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        } elseif (strtoupper($method) === 'GET' && !empty($data)) {
            $url .= '?' . http_build_query($data);
            curl_setopt($ch, CURLOPT_URL, $url);
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $body = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        return [
            'http_code' => $httpCode,
            'body'      => $body,
            'error'     => $curlError
        ];
    }
}

