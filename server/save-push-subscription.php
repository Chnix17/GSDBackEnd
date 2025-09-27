<?php
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

error_reporting(E_ALL & ~E_NOTICE);

class PushSubscriptionManager {
    private $conn;

    public function __construct() {
        include '../connection-pdo.php';
        $this->conn = $conn;
    }

    public function saveSubscription($userId, $subscriptionData, $deviceInfo = null) {
        try {
            error_log("Starting saveSubscription for user ID: " . $userId);
            error_log("Subscription data: " . print_r($subscriptionData, true));
            error_log("Device info: " . print_r($deviceInfo, true));
            
            // Normalize keys to base64url (browser btoa gives base64)
            $normalizeBase64Url = function ($s) {
                // If it's already url-safe, this will effectively be a no-op
                $s = rtrim($s, '=');
                $s = strtr($s, '+/', '-_');
                return $s;
            };

            if (isset($subscriptionData['keys']['p256dh'])) {
                $subscriptionData['keys']['p256dh'] = $normalizeBase64Url($subscriptionData['keys']['p256dh']);
            }
            if (isset($subscriptionData['keys']['auth'])) {
                $subscriptionData['keys']['auth'] = $normalizeBase64Url($subscriptionData['keys']['auth']);
            }

            // Extract device information from User-Agent or provided data
            $userAgent = $deviceInfo['user_agent'] ?? $_SERVER['HTTP_USER_AGENT'] ?? '';
            $deviceType = $deviceInfo['device_type'] ?? $this->detectDeviceType($userAgent);
            $deviceOS = $deviceInfo['device_os'] ?? $this->detectOS($userAgent);
            $browser = $deviceInfo['browser'] ?? $this->detectBrowser($userAgent);

            // Check if subscription already exists for this user on this specific device
            $checkSql = "SELECT subscription_id FROM tbl_push_subscriptions 
                         WHERE user_id = :user_id 
                         AND device_type = :device_type 
                         AND device_os = :device_os 
                         AND browser = :browser
                         AND user_agent = :user_agent";
            
            $checkStmt = $this->conn->prepare($checkSql);
            $checkStmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $checkStmt->bindParam(':device_type', $deviceType, PDO::PARAM_STR);
            $checkStmt->bindParam(':device_os', $deviceOS, PDO::PARAM_STR);
            $checkStmt->bindParam(':browser', $browser, PDO::PARAM_STR);
            $checkStmt->bindParam(':user_agent', $userAgent, PDO::PARAM_STR);
            $checkStmt->execute();

            if ($checkStmt->rowCount() > 0) {
                // Update existing subscription for this device
                $sql = "UPDATE tbl_push_subscriptions 
                        SET endpoint = :endpoint, 
                            p256dh_key = :p256dh_key, 
                            auth_key = :auth_key, 
                            device_type = :device_type,
                            device_os = :device_os,
                            browser = :browser,
                            user_agent = :user_agent,
                            is_active = 1,
                            updated_at = NOW() 
                        WHERE user_id = :user_id 
                        AND device_type = :device_type 
                        AND device_os = :device_os 
                        AND browser = :browser
                        AND user_agent = :user_agent";
            } else {
                // Check if user has any subscription on other devices
                $checkAnySql = "SELECT subscription_id FROM tbl_push_subscriptions WHERE user_id = :user_id";
                $checkAnyStmt = $this->conn->prepare($checkAnySql);
                $checkAnyStmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
                $checkAnyStmt->execute();
                
                if ($checkAnyStmt->rowCount() > 0) {
                    // User has subscriptions on other devices, update the one for this device if it exists
                    // or insert a new one for this device
                    $sql = "INSERT INTO tbl_push_subscriptions 
                            (user_id, endpoint, p256dh_key, auth_key, device_type, device_os, browser, user_agent, is_active, created_at, updated_at) 
                            VALUES (:user_id, :endpoint, :p256dh_key, :auth_key, :device_type, :device_os, :browser, :user_agent, 1, NOW(), NOW()) 
                            ON DUPLICATE KEY UPDATE 
                            endpoint = :endpoint, 
                            p256dh_key = :p256dh_key, 
                            auth_key = :auth_key, 
                            is_active = 1,
                            updated_at = NOW()";
                } else {
                    // Insert new subscription
                    $sql = "INSERT INTO tbl_push_subscriptions 
                            (user_id, endpoint, p256dh_key, auth_key, device_type, device_os, browser, user_agent, is_active, created_at, updated_at) 
                            VALUES (:user_id, :endpoint, :p256dh_key, :auth_key, :device_type, :device_os, :browser, :user_agent, 1, NOW(), NOW())";
                }
            }

            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $stmt->bindParam(':endpoint', $subscriptionData['endpoint'], PDO::PARAM_STR);
            $stmt->bindParam(':p256dh_key', $subscriptionData['keys']['p256dh'], PDO::PARAM_STR);
            $stmt->bindParam(':auth_key', $subscriptionData['keys']['auth'], PDO::PARAM_STR);
            $stmt->bindParam(':device_type', $deviceType, PDO::PARAM_STR);
            $stmt->bindParam(':device_os', $deviceOS, PDO::PARAM_STR);
            $stmt->bindParam(':browser', $browser, PDO::PARAM_STR);
            $stmt->bindParam(':user_agent', $userAgent, PDO::PARAM_STR);
            $stmt->execute();

            error_log("Database operation completed successfully");
            error_log("Rows affected: " . $stmt->rowCount());

            return json_encode([
                'status' => 'success',
                'message' => 'Push subscription saved successfully'
            ]);

        } catch (PDOException $e) {
            error_log("Database error: " . $e->getMessage());
            return json_encode([
                'status' => 'error',
                'message' => 'Database error: ' . $e->getMessage()
            ]);
        }
    }

	public function repairSubscription($userId, $subscriptionData, $deviceInfo = null) {
		// For now, repairing is equivalent to saving/upserting the latest
		// subscription for this specific device and marking it active.
		return $this->saveSubscription($userId, $subscriptionData, $deviceInfo);
	}

    private function detectDeviceType($userAgent) {
        $userAgent = strtolower($userAgent);
        
        if (strpos($userAgent, 'mobile') !== false || 
            strpos($userAgent, 'android') !== false || 
            strpos($userAgent, 'iphone') !== false || 
            strpos($userAgent, 'ipod') !== false) {
            return 'Mobile';
        } elseif (strpos($userAgent, 'tablet') !== false || 
                  strpos($userAgent, 'ipad') !== false) {
            return 'Tablet';
        } else {
            return 'Desktop';
        }
    }

    private function detectOS($userAgent) {
        $userAgent = strtolower($userAgent);
        
        if (strpos($userAgent, 'windows nt') !== false) {
            return 'Windows';
        } elseif (strpos($userAgent, 'mac os x') !== false || strpos($userAgent, 'macintosh') !== false) {
            return 'macOS';
        } elseif (strpos($userAgent, 'linux') !== false) {
            return 'Linux';
        } elseif (strpos($userAgent, 'android') !== false) {
            return 'Android';
        } elseif (strpos($userAgent, 'iphone') !== false || strpos($userAgent, 'ipad') !== false || strpos($userAgent, 'ipod') !== false) {
            return 'iOS';
        } else {
            return 'Unknown';
        }
    }

    private function detectBrowser($userAgent) {
        $userAgent = strtolower($userAgent);
        
        if (strpos($userAgent, 'edg') !== false) {
            return 'Microsoft Edge';
        } elseif (strpos($userAgent, 'chrome') !== false && strpos($userAgent, 'edg') === false) {
            return 'Google Chrome';
        } elseif (strpos($userAgent, 'firefox') !== false) {
            return 'Mozilla Firefox';
        } elseif (strpos($userAgent, 'safari') !== false && strpos($userAgent, 'chrome') === false) {
            return 'Safari';
        } elseif (strpos($userAgent, 'opera') !== false || strpos($userAgent, 'opr') !== false) {
            return 'Opera';
        } elseif (strpos($userAgent, 'trident') !== false || strpos($userAgent, 'msie') !== false) {
            return 'Internet Explorer';
        } else {
            return 'Unknown';
        }
    }

    public function deleteSubscription($userId) {
        try {
            $sql = "DELETE FROM tbl_push_subscriptions WHERE user_id = :user_id";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $stmt->execute();

            return json_encode([
                'status' => 'success',
                'message' => 'Push subscription deleted successfully'
            ]);

        } catch (PDOException $e) {
            error_log("Database error: " . $e->getMessage());
            return json_encode([
                'status' => 'error',
                'message' => 'Database error: ' . $e->getMessage()
            ]);
        }
    }

    public function getSubscription($userId, $deviceInfo = null) {
        try {
            // Always perform a device-specific lookup using provided device_info
            // or the current request's User-Agent if device_info is missing.
            $userAgent = $deviceInfo['user_agent'] ?? $_SERVER['HTTP_USER_AGENT'] ?? '';
            $deviceType = $deviceInfo['device_type'] ?? $this->detectDeviceType($userAgent);
            $deviceOS = $deviceInfo['device_os'] ?? $this->detectOS($userAgent);
            $browser = $deviceInfo['browser'] ?? $this->detectBrowser($userAgent);

            // 1) Try to find a subscription tied to this exact device
            $sqlDevice = "SELECT * FROM tbl_push_subscriptions 
                          WHERE user_id = :user_id 
                          AND device_type = :device_type 
                          AND device_os = :device_os 
                          AND browser = :browser
                          AND user_agent = :user_agent
                          ORDER BY updated_at DESC, created_at DESC
                          LIMIT 1";
            $stmtDevice = $this->conn->prepare($sqlDevice);
            $stmtDevice->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $stmtDevice->bindParam(':device_type', $deviceType, PDO::PARAM_STR);
            $stmtDevice->bindParam(':device_os', $deviceOS, PDO::PARAM_STR);
            $stmtDevice->bindParam(':browser', $browser, PDO::PARAM_STR);
            $stmtDevice->bindParam(':user_agent', $userAgent, PDO::PARAM_STR);
            $stmtDevice->execute();
            $deviceSubscription = $stmtDevice->fetch(PDO::FETCH_ASSOC);

            // 2) Also load any latest subscription for the user (other devices)
            $sqlAny = "SELECT * FROM tbl_push_subscriptions 
                       WHERE user_id = :user_id 
                       ORDER BY updated_at DESC, created_at DESC
                       LIMIT 1";
            $stmtAny = $this->conn->prepare($sqlAny);
            $stmtAny->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $stmtAny->execute();
            $anySubscription = $stmtAny->fetch(PDO::FETCH_ASSOC);

            // Derive flags
            $hasDeviceSubscription = !!$deviceSubscription;
            $isActive = $hasDeviceSubscription ? ($deviceSubscription['is_active'] == 1) : false;
            $keysPresent = $hasDeviceSubscription 
                ? (!empty($deviceSubscription['p256dh_key']) && !empty($deviceSubscription['auth_key']))
                : false;
            $endpointPresent = $hasDeviceSubscription ? !empty($deviceSubscription['endpoint']) : false;

            $needResubscribe = false;
            $reason = '';
            if (!$hasDeviceSubscription) {
                $needResubscribe = true;
                $reason = 'no_device_subscription';
            } elseif (!$isActive) {
                $needResubscribe = true;
                $reason = 'inactive_subscription';
            } elseif (!$keysPresent || !$endpointPresent) {
                $needResubscribe = true;
                $reason = !$keysPresent ? 'missing_keys' : 'missing_endpoint';
            }

            // Prefer device-specific data when available; otherwise, return the latest user subscription for visibility
            $data = $deviceSubscription ?: ($anySubscription ?: []);

            return json_encode([
                'status' => 'success',
                'data' => $data,
                'is_active' => $isActive,
                'device_match' => $hasDeviceSubscription,
                'need_resubscribe' => $needResubscribe,
                'reason' => $reason,
                'device_fingerprint' => [
                    'device_type' => $deviceType,
                    'device_os' => $deviceOS,
                    'browser' => $browser,
                    'user_agent' => $userAgent,
                ]
            ]);

        } catch (PDOException $e) {
            error_log("Database error: " . $e->getMessage());
            return json_encode([
                'status' => 'error',
                'message' => 'Database error: ' . $e->getMessage()
            ]);
        }
    }

    public function getAllSubscriptions() {
        try {
            $sql = "SELECT ps.*, u.users_fname, u.users_mname, u.users_lname 
                    FROM tbl_push_subscriptions ps 
                    LEFT JOIN tbl_users u ON ps.user_id = u.users_id 
                    WHERE ps.is_active = 1";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute();

            $subscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return json_encode([
                'status' => 'success',
                'data' => $subscriptions
            ]);

        } catch (PDOException $e) {
            error_log("Database error: " . $e->getMessage());
            return json_encode([
                'status' => 'error',
                'message' => 'Database error: ' . $e->getMessage()
            ]);
        }
    }
}

// Handle the request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = file_get_contents('php://input');
    error_log("Received request data: " . $input);
    
    $data = json_decode($input, true);
    error_log("Decoded data: " . print_r($data, true));
    
    $manager = new PushSubscriptionManager();
    
    $operation = $data['operation'] ?? 'save';
    $userId = $data['user_id'] ?? null;
    $subscriptionData = $data['subscription'] ?? null;
    $deviceInfo = $data['device_info'] ?? null;
    
    error_log("Operation: " . $operation);
    error_log("User ID: " . $userId);
    error_log("Subscription data: " . print_r($subscriptionData, true));
    error_log("Device info: " . print_r($deviceInfo, true));

    switch ($operation) {
        case 'save':
            if ($userId === null || $subscriptionData === null) {
                echo json_encode(['status' => 'error', 'message' => 'User ID and subscription data are required']);
                break;
            }
            echo $manager->saveSubscription($userId, $subscriptionData, $deviceInfo);
            break;

			case 'repair':
				if ($userId === null || $subscriptionData === null) {
					echo json_encode(['status' => 'error', 'message' => 'User ID and subscription data are required']);
					break;
				}
				echo $manager->repairSubscription($userId, $subscriptionData, $deviceInfo);
				break;

        case 'delete':
            if ($userId === null) {
                echo json_encode(['status' => 'error', 'message' => 'User ID is required']);
                break;
            }
            echo $manager->deleteSubscription($userId);
            break;

        case 'get':
            if ($userId === null) {
                echo json_encode(['status' => 'error', 'message' => 'User ID is required']);
                break;
            }
            // Pass device info to getSubscription method if provided
            echo $manager->getSubscription($userId, $deviceInfo);
            break;

        case 'getAll':
            echo $manager->getAllSubscriptions();
            break;

        default:
            echo json_encode(['status' => 'error', 'message' => 'Invalid operation']);
            break;
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
} 