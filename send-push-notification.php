<?php
// Fix OpenSSL configuration issue for Windows/XAMPP
if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
    // Set OpenSSL configuration path for Windows
    $opensslConf = 'C:\xampp\php\extras\ssl\openssl.cnf';
    if (file_exists($opensslConf)) {
        putenv("OPENSSL_CONF=$opensslConf");
    }
    
    // Alternative: Create temporary config if main one doesn't exist
    if (!getenv('OPENSSL_CONF') || !file_exists(getenv('OPENSSL_CONF'))) {
        $tempConf = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'openssl_temp.cnf';
        $configContent = "[req]
default_bits = 2048
distinguished_name = req_distinguished_name
req_extensions = v3_req

[req_distinguished_name]

[v3_req]
basicConstraints = CA:FALSE
keyUsage = nonRepudiation, digitalSignature, keyEncipherment

[v3_ca]
subjectKeyIdentifier=hash
authorityKeyIdentifier=keyid:always,issuer:always
basicConstraints = CA:true
";
        file_put_contents($tempConf, $configContent);
        putenv("OPENSSL_CONF=$tempConf");
    }
}

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once 'connection-pdo.php';
require_once './vendor/autoload.php';

use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;

// VAPID Configuration - Using recommended keys from memory
const VAPID_SUBJECT = 'mailto:vallechristianmark@gmail.com';
const VAPID_PUBLIC_KEY = 'BBCHDpLuOIsXy3cOBIpaNPEs5SMLDDlqeyEsbVHaZbVVRccII2zkSkt4vm3AcZp8kKZkhXFBTXLcTZEaJaBTuVo';
const VAPID_PRIVATE_KEY = 'WRxRXgxTI9MT9K7vwPVjUQWkWOyj867e8xOtzTUMeLM';

class PushNotificationHandler {
    private $conn;
    private $webPush;
    private $localKeyObject;

    public function __construct() {
        global $conn;
        $this->conn = $conn;

        $auth = [
            'VAPID' => [
                'subject' => VAPID_SUBJECT,
                'publicKey' => VAPID_PUBLIC_KEY,
                'privateKey' => VAPID_PRIVATE_KEY,
            ]
        ];

        try {
            // Validate VAPID keys first
            if (!$this->validateVAPIDKeys()) {
                throw new Exception("Invalid VAPID keys");
            }
            
            // VAPID keys should be in base64url format as expected by the library
            error_log("VAPID private key: " . $auth['VAPID']['privateKey']);
            error_log("VAPID public key: " . $auth['VAPID']['publicKey']);
            
            // Try to initialize WebPush with additional options for Windows/XAMPP compatibility
            $options = [
                'timeout' => 30,
                'TTL' => 2419200, // 4 weeks
                'urgency' => 'normal',
                'topic' => null,
                'batchSize' => 1000,
            ];
            
            $this->webPush = new WebPush($auth, $options);
            error_log("WebPush initialized successfully");
            
        } catch (Exception $e) {
            error_log("WebPush initialization error: " . $e->getMessage());
            error_log("Error trace: " . $e->getTraceAsString());
            
            // Try alternative initialization without some options
            try {
                error_log("Attempting fallback WebPush initialization...");
                $this->webPush = new WebPush($auth);
                error_log("Fallback WebPush initialization successful");
            } catch (Exception $e2) {
                error_log("Fallback WebPush initialization also failed: " . $e2->getMessage());
                throw new Exception("WebPush initialization failed: " . $e->getMessage() . " | Fallback: " . $e2->getMessage());
            }
        }
    }

    public function sendTestNotification($userId) {
        try {
            $stmt = $this->conn->prepare("SELECT * FROM tbl_push_subscriptions WHERE user_id = ? AND is_active = 1");
            $stmt->execute([$userId]);
            $subscriptionData = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$subscriptionData) {
                return ['status' => 'error', 'message' => 'No active subscription found for user ID: ' . $userId];
            }

            error_log("Raw subscription data: " . print_r($subscriptionData, true));

            if (empty($subscriptionData['endpoint']) || empty($subscriptionData['p256dh_key']) || empty($subscriptionData['auth_key'])) {
                return ['status' => 'error', 'message' => 'Incomplete subscription data.'];
            }
            
            $subscription = Subscription::create([
                'endpoint' => $subscriptionData['endpoint'],
                'publicKey' => $subscriptionData['p256dh_key'],
                'authToken' => $subscriptionData['auth_key'],
            ]);

            $payload = json_encode([
                'title' => 'Test Notification',
                'body' => 'This is a test from the server!',
                'data' => ['url' => '/viewRequest']
            ]);

            $this->webPush->queueNotification($subscription, $payload);
            
            foreach ($this->webPush->flush() as $report) {
                if ($report->isSuccess()) {
                    error_log("Message sent successfully for subscription {$report->getEndpoint()}.");
                } else {
                    error_log("Message failed to send for subscription {$report->getEndpoint()}: {$report->getReason()}");
                    if ($report->isSubscriptionExpired()) {
                        $this->markSubscriptionInactive($userId);
                    }
                }
            }

            return ['status' => 'success', 'message' => 'Test notification sent.'];

        } catch (Exception $e) {
            error_log("Exception in sendTestNotification: " . $e->getMessage());
            error_log("Trace: " . $e->getTraceAsString());
            http_response_code(500);
            return [
                'error' => 'Server error: ' . $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ];
        }
    }
    
    public function sendNotification($userId, $title = 'Notification', $body = 'You have a new basta', $data = []) {
        try {
            $stmt = $this->conn->prepare("SELECT * FROM tbl_push_subscriptions WHERE user_id = ? AND is_active = 1");
            $stmt->execute([$userId]);
            $subscriptionData = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$subscriptionData) {
                return ['status' => 'error', 'message' => 'No active subscription found for user ID: ' . $userId];
            }

            if (empty($subscriptionData['endpoint']) || empty($subscriptionData['p256dh_key']) || empty($subscriptionData['auth_key'])) {
                return ['status' => 'error', 'message' => 'Incomplete subscription data.'];
            }

            $subscription = Subscription::create([
                'endpoint' => $subscriptionData['endpoint'],
                'publicKey' => $subscriptionData['p256dh_key'],
                'authToken' => $subscriptionData['auth_key'],
            ]);

            $payload = json_encode([
                'title' => $title,
                'body' => $body,
                'data' => $data,
            ]);

            $this->webPush->queueNotification($subscription, $payload);

            foreach ($this->webPush->flush() as $report) {
                if ($report->isSuccess()) {
                    error_log("Notification for user {$userId} sent successfully.");
                } else {
                    error_log("Notification for user {$userId} failed: {$report->getReason()}");
                    if ($report->isSubscriptionExpired()) {
                        $this->markSubscriptionInactive($userId);
                    }
                }
            }

            return ['status' => 'success', 'message' => 'Notification sent.'];

        } catch (Exception $e) {
            error_log("Exception in sendNotification: " . $e->getMessage());
            return ['status' => 'error', 'message' => 'Server error: ' . $e->getMessage()];
        }
    }

    private function isValidBase64($str) {
        if (empty($str)) return false;
        // Check if it's valid base64url format using the library's method
        try {
            $decoded = \Base64Url\Base64Url::decode($str);
            return $decoded !== false && strlen($decoded) > 0;
        } catch (Exception $e) {
            return false;
        }
    }

    private function markSubscriptionInactive($userId) {
        try {
            $stmt = $this->conn->prepare("UPDATE tbl_push_subscriptions SET is_active = 0 WHERE user_id = ?");
            $stmt->execute([$userId]);
        } catch (Exception $e) {
            error_log("Failed to mark subscription inactive: " . $e->getMessage());
        }
    }

    public function getPublicKey() {
        return ['status' => 'success', 'publicKey' => VAPID_PUBLIC_KEY];
    }
    
    public function generateNewVAPIDKeys() {
        try {
            $keys = \Minishlink\WebPush\VAPID::createVapidKeys();
            return [
                'status' => 'success',
                'publicKey' => $keys['publicKey'],
                'privateKey' => $keys['privateKey']
            ];
        } catch (Exception $e) {
            return ['status' => 'error', 'message' => 'Failed to generate VAPID keys: ' . $e->getMessage()];
        }
    }
    
    public function testOpenSSLConfiguration() {
        $results = [];
        
        // Test different OpenSSL configurations
        $configs = [
            [
                'name' => 'Default',
                'config' => []
            ],
            [
                'name' => 'Prime256v1',
                'config' => [
                    'curve_name' => 'prime256v1',
                    'private_key_type' => OPENSSL_KEYTYPE_EC,
                ]
            ],
            [
                'name' => 'Secp256r1',
                'config' => [
                    'curve_name' => 'secp256r1',
                    'private_key_type' => OPENSSL_KEYTYPE_EC,
                ]
            ],
            [
                'name' => 'With bits',
                'config' => [
                    'curve_name' => 'prime256v1',
                    'private_key_type' => OPENSSL_KEYTYPE_EC,
                    'private_key_bits' => 256,
                ]
            ]
        ];
        
        foreach ($configs as $config) {
            try {
                $key = openssl_pkey_new($config['config']);
                if ($key) {
                    $details = openssl_pkey_get_details($key);
                    if ($details) {
                        $results[$config['name']] = 'SUCCESS';
                        openssl_free_key($key);
                    } else {
                        $results[$config['name']] = 'FAILED - No details';
                    }
                } else {
                    $results[$config['name']] = 'FAILED - Null key';
                }
            } catch (Exception $e) {
                $results[$config['name']] = 'FAILED - ' . $e->getMessage();
            }
        }
        
        return [
            'status' => 'success',
            'results' => $results
        ];
    }
    
    private function validateVAPIDKeys() {
        try {
            // Use Base64Url decode as expected by the WebPush library
            $privateKeyDecoded = \Base64Url\Base64Url::decode(VAPID_PRIVATE_KEY);
            if ($privateKeyDecoded === false) {
                error_log("Invalid VAPID private key format");
                return false;
            }
            
            // Decode the public key
            $publicKeyDecoded = \Base64Url\Base64Url::decode(VAPID_PUBLIC_KEY);
            if ($publicKeyDecoded === false) {
                error_log("Invalid VAPID public key format");
                return false;
            }
            
            error_log("VAPID private key length: " . strlen($privateKeyDecoded));
            error_log("VAPID public key length: " . strlen($publicKeyDecoded));
            
            // Check expected lengths
            if (strlen($privateKeyDecoded) !== 32) {
                error_log("VAPID private key should be 32 bytes, got " . strlen($privateKeyDecoded));
                return false;
            }
            
            if (strlen($publicKeyDecoded) !== 65) {
                error_log("VAPID public key should be 65 bytes, got " . strlen($publicKeyDecoded));
                return false;
            }
            
            return true;
        } catch (Exception $e) {
            error_log("VAPID key validation error: " . $e->getMessage());
            return false;
        }
    }
}

// Handle request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rawInput = file_get_contents('php://input');
    error_log("Raw input received: " . $rawInput);

    $input = json_decode($rawInput, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON: ' . json_last_error_msg()]);
        exit;
    }

    if (!$input || !isset($input['operation'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid request data']);
        exit;
    }

    try {
        $handler = new PushNotificationHandler();

        switch ($input['operation']) {
            case 'test':
                if (isset($input['user_id'])) {
                    echo json_encode($handler->sendTestNotification($input['user_id']));
                } else {
                    http_response_code(400);
                    echo json_encode(['error' => 'User ID required']);
                }
                break;

            case 'send':
                if (isset($input['user_id'])) {
                    $title = $input['title'] ?? 'Notification';
                    $body = $input['body'] ?? 'mao ni syang notification';
                    $data = $input['data'] ?? [];
                    echo json_encode($handler->sendNotification($input['user_id'], $title, $body, $data));
                } else {
                    http_response_code(400);
                    echo json_encode(['error' => 'User ID required']);
                }
                break;

            case 'getPublicKey':
                echo json_encode($handler->getPublicKey());
                break;

            case 'generateVAPIDKeys':
                echo json_encode($handler->generateNewVAPIDKeys());
                break;

            case 'testOpenSSL':
                echo json_encode($handler->testOpenSSLConfiguration());
                break;

            default:
                http_response_code(400);
                echo json_encode(['error' => 'Invalid operation']);
        }
    } catch (Exception $e) {
        error_log("Fatal error: " . $e->getMessage());
        error_log("Trace: " . $e->getTraceAsString());
        http_response_code(500);
        echo json_encode([
            'error' => 'Server error: ' . $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
    }
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}