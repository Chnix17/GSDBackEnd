<?php
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

error_reporting(E_ALL & ~E_NOTICE);

class PushSubscriptionManager {
    private $conn;

    public function __construct() {
        include 'connection-pdo.php';
        $this->conn = $conn;
    }

    public function saveSubscription($userId, $subscriptionData) {
        try {
            error_log("Starting saveSubscription for user ID: " . $userId);
            error_log("Subscription data: " . print_r($subscriptionData, true));
            
            // Check if subscription already exists for this user
            $checkSql = "SELECT subscription_id FROM tbl_push_subscriptions WHERE user_id = :user_id";
            $checkStmt = $this->conn->prepare($checkSql);
            $checkStmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $checkStmt->execute();

            if ($checkStmt->rowCount() > 0) {
                // Update existing subscription
                $sql = "UPDATE tbl_push_subscriptions 
                        SET endpoint = :endpoint, 
                            p256dh_key = :p256dh_key, 
                            auth_key = :auth_key, 
                            updated_at = NOW() 
                        WHERE user_id = :user_id";
            } else {
                // Insert new subscription
                $sql = "INSERT INTO tbl_push_subscriptions 
                        (user_id, endpoint, p256dh_key, auth_key, created_at, updated_at) 
                        VALUES (:user_id, :endpoint, :p256dh_key, :auth_key, NOW(), NOW())";
            }

            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $stmt->bindParam(':endpoint', $subscriptionData['endpoint'], PDO::PARAM_STR);
            $stmt->bindParam(':p256dh_key', $subscriptionData['keys']['p256dh'], PDO::PARAM_STR);
            $stmt->bindParam(':auth_key', $subscriptionData['keys']['auth'], PDO::PARAM_STR);
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

    public function getSubscription($userId) {
        try {
            $sql = "SELECT * FROM tbl_push_subscriptions WHERE user_id = :user_id";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $stmt->execute();

            $subscription = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($subscription) {
                return json_encode([
                    'status' => 'success',
                    'data' => $subscription
                ]);
            } else {
                return json_encode([
                    'status' => 'error',
                    'message' => 'No subscription found for this user'
                ]);
            }

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
    
    error_log("Operation: " . $operation);
    error_log("User ID: " . $userId);
    error_log("Subscription data: " . print_r($subscriptionData, true));

    switch ($operation) {
        case 'save':
            if ($userId === null || $subscriptionData === null) {
                echo json_encode(['status' => 'error', 'message' => 'User ID and subscription data are required']);
                break;
            }
            echo $manager->saveSubscription($userId, $subscriptionData);
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
            echo $manager->getSubscription($userId);
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