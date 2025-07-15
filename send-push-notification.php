<?php
// Fix OpenSSL configuration issue
if (function_exists('openssl_get_cipher_methods')) {
    // Try to create a more comprehensive OpenSSL config
    $configContent = "openssl_conf = default_conf\n[default_conf]\nssl_conf = ssl_sect\n[ssl_sect]\nsystem_default = system_default_sect\n[system_default_sect]\nMinProtocol = TLSv1.2\nCipherString = DEFAULT@SECLEVEL=1\n\n[req]\ndefault_bits = 2048\ndefault_keyfile = server-key.pem\ndistinguished_name = req_distinguished_name\nreq_extensions = v3_req\n\n[req_distinguished_name]\ncountryName = Country Name (2 letter code)\ncountryName_default = US\nstateOrProvinceName = State or Province Name (full name)\nstateOrProvinceName_default = NY\nlocalityName = Locality Name (eg, city)\nlocalityName_default = New York\norganizationName = Organization Name (eg, company)\norganizationName_default = Internet Widgits Pty Ltd\ncommonName = Common Name (e.g. server FQDN or YOUR name)\ncommonName_default = localhost\n\n[v3_req]\nbasicConstraints = CA:FALSE\nkeyUsage = nonRepudiation, digitalSignature, keyEncipherment\n";
    
    $configPath = __DIR__ . '/temp_openssl.cnf';
    file_put_contents($configPath, $configContent);
    putenv('OPENSSL_CONF=' . $configPath);
    
    // Register shutdown function to clean up
    register_shutdown_function(function() use ($configPath) {
        if (file_exists($configPath)) {
            unlink($configPath);
        }
    });
    
    // Test OpenSSL functionality
    if (!function_exists('openssl_pkey_new')) {
        error_log("OpenSSL key functions not available");
    } else {
        error_log("OpenSSL key functions available");
    }
    
    // Check for required extensions
    $requiredExtensions = ['openssl', 'curl', 'json', 'mbstring'];
    foreach ($requiredExtensions as $ext) {
        if (!extension_loaded($ext)) {
            error_log("Missing required extension: " . $ext);
        } else {
            error_log("Extension loaded: " . $ext);
        }
    }
    
    // Test EC key creation specifically
    try {
        $testKey = openssl_pkey_new([
            'curve_name' => 'prime256v1',
            'private_key_type' => OPENSSL_KEYTYPE_EC,
        ]);
        if ($testKey) {
            error_log("OpenSSL EC key creation test successful");
            openssl_free_key($testKey);
        } else {
            error_log("OpenSSL EC key creation test failed");
        }
    } catch (Exception $e) {
        error_log("OpenSSL EC key creation test exception: " . $e->getMessage());
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

// VAPID Configuration
const VAPID_SUBJECT = 'mailto:your-email@example.com';
const VAPID_PUBLIC_KEY = 'BELqHYNGLPs3EIxn6y7lMopZIpyXAKWY84Kci2FvTIW_bBSBj2l7d6e8Hp1kFKYhwF2miGYrjj9kDSX_oUfa070';
const VAPID_PRIVATE_KEY = '68XR8t32_vFeVU3l6PMqCcoJbjjOHAkj0qqtVbjHL1w';


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
            
            $this->webPush = new WebPush($auth);
            error_log("WebPush initialized successfully");
            
            // Try to create a local key object to test OpenSSL functionality
            try {
                $this->localKeyObject = $this->createLocalKeyObject();
                error_log("Local key object created successfully");
            } catch (Exception $e) {
                error_log("Failed to create local key object: " . $e->getMessage());
                // Continue anyway, the WebPush library might handle it differently
            }
            
            // Test VAPID key functionality
            try {
                $testKey = openssl_pkey_new([
                    'curve_name' => 'prime256v1',
                    'private_key_type' => OPENSSL_KEYTYPE_EC,
                ]);
                if ($testKey) {
                    error_log("OpenSSL EC key creation test successful");
                    openssl_free_key($testKey);
                } else {
                    error_log("OpenSSL EC key creation returned null");
                }
            } catch (Exception $e) {
                error_log("OpenSSL EC key creation test failed: " . $e->getMessage());
            }
            
            // Test if we can create a simple WebPush instance without VAPID
            try {
                $testWebPush = new WebPush([]);
                error_log("WebPush without VAPID created successfully");
            } catch (Exception $e) {
                error_log("WebPush without VAPID failed: " . $e->getMessage());
            }
        } catch (Exception $e) {
            error_log("WebPush initialization error: " . $e->getMessage());
            throw $e;
        }
    }

    public function sendTestNotification($userId) {
        try {
            $stmt = $this->conn->prepare("SELECT * FROM tbl_push_subscriptions WHERE user_id = ? AND is_active = 1");
            $stmt->execute([$userId]);
            $subscription = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$subscription) {
                return ['status' => 'error', 'message' => 'No active subscription found for user ID: ' . $userId];
            }

            error_log("Raw subscription data: " . print_r($subscription, true));

            if (empty($subscription['endpoint']) || empty($subscription['p256dh_key']) || empty($subscription['auth_key'])) {
                return ['status' => 'error', 'message' => 'Incomplete subscription data.'];
            }

            // Use custom VAPID notification instead of WebPush library
            return $this->sendVAPIDNotification($subscription);

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
    
    public function sendNotification($userId, $title = 'Notification', $body = 'You have a new notification', $data = []) {
        try {
            $stmt = $this->conn->prepare("SELECT * FROM tbl_push_subscriptions WHERE user_id = ? AND is_active = 1");
            $stmt->execute([$userId]);
            $subscription = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$subscription) {
                return ['status' => 'error', 'message' => 'No active subscription found for user ID: ' . $userId];
            }

            if (empty($subscription['endpoint']) || empty($subscription['p256dh_key']) || empty($subscription['auth_key'])) {
                return ['status' => 'error', 'message' => 'Incomplete subscription data.'];
            }

            // Use custom VAPID notification
            return $this->sendVAPIDNotification($subscription, $title, $body, $data);

        } catch (Exception $e) {
            error_log("Exception in sendNotification: " . $e->getMessage());
            return ['status' => 'error', 'message' => 'Server error: ' . $e->getMessage()];
        }
    }
    
    private function sendVAPIDNotification($subscription, $title = 'Notification', $body = 'You have a new notification', $data = []) {
        try {
            $endpoint = trim($subscription['endpoint']);
            
            // Create notification payload (without encryption)
            $payload = json_encode([
                'title' => $title,
                'body' => $body,
                'data' => $data,
                'timestamp' => time()
            ]);
            
            // Create headers
            $headers = [
                'Content-Type: application/json',
                'TTL: 86400'
            ];
            
            // Add VAPID headers
            try {
                $audience = parse_url($endpoint, PHP_URL_SCHEME) . '://' . parse_url($endpoint, PHP_URL_HOST);
                
                // Decode the VAPID keys to get uncompressed format
                $publicKeyDecoded = \Base64Url\Base64Url::decode(VAPID_PUBLIC_KEY);
                $privateKeyDecoded = \Base64Url\Base64Url::decode(VAPID_PRIVATE_KEY);
                
                $vapidHeaders = \Minishlink\WebPush\VAPID::getVapidHeaders(
                    $audience,
                    VAPID_SUBJECT,
                    $publicKeyDecoded,  // Use decoded (uncompressed) public key
                    $privateKeyDecoded,  // Use decoded private key
                    'aesgcm'
                );
                
                foreach ($vapidHeaders as $key => $value) {
                    $headers[] = "$key: $value";
                }
                
                error_log("VAPID headers created successfully");
            } catch (Exception $e) {
                error_log("Failed to create VAPID headers: " . $e->getMessage());
                return ['status' => 'error', 'message' => 'VAPID header creation failed: ' . $e->getMessage()];
            }
            
            // Send the request using cURL
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $endpoint);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // For development
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);
            
            error_log("HTTP Response Code: " . $httpCode);
            error_log("cURL Error: " . $error);
            error_log("Response: " . $response);
            
            if ($error) {
                return ['status' => 'error', 'message' => 'cURL error: ' . $error];
            }
            
            if ($httpCode >= 200 && $httpCode < 300) {
                return ['status' => 'success', 'message' => 'VAPID notification sent successfully'];
            } else {
                return ['status' => 'error', 'message' => "HTTP error: $httpCode, Response: $response"];
            }
            
        } catch (Exception $e) {
            error_log("Exception in sendVAPIDNotification: " . $e->getMessage());
            return ['status' => 'error', 'message' => 'VAPID notification failed: ' . $e->getMessage()];
        }
    }

    private function cleanKey($key) {
        $cleaned = trim($key);
        // Keep keys in base64url format as expected by WebPush library
        return $cleaned;
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
    
    private function createLocalKeyObject() {
        // Try multiple approaches to create the local key
        $attempts = [
            [
                'curve_name' => 'prime256v1',
                'private_key_type' => OPENSSL_KEYTYPE_EC,
            ],
            [
                'curve_name' => 'secp256r1', // Alternative name for prime256v1
                'private_key_type' => OPENSSL_KEYTYPE_EC,
            ],
            [
                'curve_name' => 'prime256v1',
                'private_key_type' => OPENSSL_KEYTYPE_EC,
                'private_key_bits' => 256,
            ]
        ];
        
        foreach ($attempts as $i => $config) {
            try {
                error_log("Attempting to create local key with config " . ($i + 1));
                $keyResource = openssl_pkey_new($config);
                if ($keyResource) {
                    $details = openssl_pkey_get_details($keyResource);
                    if ($details) {
                        error_log("Local key created successfully with config " . ($i + 1));
                        return [$keyResource, $details];
                    }
                }
            } catch (Exception $e) {
                error_log("Local key creation attempt " . ($i + 1) . " failed: " . $e->getMessage());
            }
        }
        
        throw new Exception("Failed to create local key object after multiple attempts");
    }
    
    private function sendCustomNotification($subscription, $payload) {
        // Custom notification approach that bypasses the WebPush library's encryption
        $endpoint = $subscription->getEndpoint();
        
        // Create a simple HTTP request without encryption
        $headers = [
            'Content-Type: application/json',
            'TTL: 86400'
        ];
        
        // Add VAPID headers if available
        try {
            $vapidHeaders = \Minishlink\WebPush\VAPID::getVapidHeaders(
                parse_url($endpoint, PHP_URL_SCHEME) . '://' . parse_url($endpoint, PHP_URL_HOST),
                VAPID_SUBJECT,
                VAPID_PUBLIC_KEY,
                VAPID_PRIVATE_KEY,
                'aesgcm'
            );
            
            foreach ($vapidHeaders as $key => $value) {
                $headers[] = "$key: $value";
            }
        } catch (Exception $e) {
            error_log("Failed to create VAPID headers: " . $e->getMessage());
        }
        
        // Send the request
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $endpoint);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            throw new Exception("cURL error: " . $error);
        }
        
        if ($httpCode >= 200 && $httpCode < 300) {
            return ['status' => 'success', 'message' => 'Custom notification sent successfully'];
        } else {
            return ['status' => 'error', 'message' => "HTTP error: $httpCode, Response: $response"];
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
                    $body = $input['body'] ?? 'You have a new notification';
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
}
