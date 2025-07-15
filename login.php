<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  http_response_code(200);
  exit;
}

class Login {
    private $conn;
    private $MAX_ATTEMPTS = 3;
    private $BLOCK_DURATION = 3; // minutes

    public function __construct() {
        include 'connection-pdo.php'; // Include your database connection
        $this->conn = $conn;
    }

    private function checkPassword($inputPassword, $storedHash) {
        // Try direct comparison first
        if ($inputPassword === $storedHash) {
            return true;
        }
        // Try password_verify
        $verified = password_verify($inputPassword, $storedHash);
        return $verified;
    }

    public function handleLoginAttempt($username, $isSuccessful) {
        try {
            if ($isSuccessful) {
                // On successful login, reset any existing failed attempts
                $delete_sql = "DELETE FROM tbl_loginfailed WHERE User_schoolid = :username";    
                $stmt = $this->conn->prepare($delete_sql);
                $stmt->bindParam(':username', $username);
                $stmt->execute();
                return true;
            }

            // First check for expired attempts
            $expired = $this->fetchFailedLoginExpired($username);
            if ($expired) {
                // Delete all existing records for this username first
                $delete_sql = "DELETE FROM tbl_loginfailed WHERE User_schoolid = :username";
                $stmt = $this->conn->prepare($delete_sql);
                $stmt->bindParam(':username', $username);
                $stmt->execute();

                // Then create a new attempt
                $insert_sql = "INSERT INTO tbl_loginfailed (User_schoolid, User_loginattempt, Login_until) 
                              VALUES (:username, 1, NULL)";
                $stmt = $this->conn->prepare($insert_sql);
                $stmt->bindParam(':username', $username);
                $stmt->execute();
                return false;
            }

            // Get the latest record for this user if any exists
            $check_sql = "SELECT loginfailed_id, User_schoolid, User_loginattempt, Login_until 
                         FROM tbl_loginfailed 
                         WHERE User_schoolid = :username 
                         ORDER BY loginfailed_id DESC 
                         LIMIT 1";
            $stmt = $this->conn->prepare($check_sql);
            $stmt->bindParam(':username', $username);
            $stmt->execute();
            $latest_record = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($latest_record) {
                // Update existing record with incremented attempts
                $new_attempts = $latest_record['User_loginattempt'] + 1;
                
                if ($new_attempts >= $this->MAX_ATTEMPTS) {
                    // Set block duration when max attempts reached
                    date_default_timezone_set('Asia/Manila');
                    $block_until = (new DateTime())->add(new DateInterval('PT' . $this->BLOCK_DURATION . 'M'))->format('Y-m-d H:i:s');
                    
                    $update_sql = "UPDATE tbl_loginfailed 
                                 SET User_loginattempt = :attempts,
                                     Login_until = :block_until 
                                 WHERE loginfailed_id = :id";
                    $stmt = $this->conn->prepare($update_sql);
                    $stmt->bindParam(':attempts', $new_attempts);
                    $stmt->bindParam(':block_until', $block_until);
                    $stmt->bindParam(':id', $latest_record['loginfailed_id']);
                    $stmt->execute();
                } else {
                    $update_sql = "UPDATE tbl_loginfailed 
                                 SET User_loginattempt = :attempts 
                                 WHERE loginfailed_id = :id";
                    $stmt = $this->conn->prepare($update_sql);
                    $stmt->bindParam(':attempts', $new_attempts);
                    $stmt->bindParam(':id', $latest_record['loginfailed_id']);
                    $stmt->execute();
                }
            } else {
                // No previous attempts, create new record
                $insert_sql = "INSERT INTO tbl_loginfailed (User_schoolid, User_loginattempt, Login_until) 
                              VALUES (:username, 1, NULL)";
                $stmt = $this->conn->prepare($insert_sql);
                $stmt->bindParam(':username', $username);
                $stmt->execute();
            }

            return false;

        } catch (PDOException $e) {
            return false;
        }
    }
    
    
    

    private function isAccountBlocked($username) {
        date_default_timezone_set('Asia/Manila');
        
        $sql = "SELECT Login_until FROM tbl_loginfailed 
                WHERE User_schoolid = :username 
                AND User_loginattempt >= :max_attempts 
                AND Login_until > NOW()";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':username', $username);
        $stmt->bindParam(':max_attempts', $this->MAX_ATTEMPTS);
        $stmt->execute();
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result) {
            $now = new DateTime();
            $until = new DateTime($result['Login_until']);
            $diff = $now->diff($until);
            $minutes = ($diff->days * 24 * 60) + ($diff->h * 60) + $diff->i;
            return [
                'blocked' => true,
                'minutes_remaining' => $minutes
            ];
        }
        
        return ['blocked' => false];
    }
    

    function login($json)
    {
        include "connection-pdo.php";
        $json = json_decode($json, true);

        try {
            // Check if account is blocked
            $blockStatus = $this->isAccountBlocked($json['username']);
            if ($blockStatus['blocked']) {
                return json_encode([
                    'status' => 'error', 
                    'message' => "Account is temporarily blocked. Please try again after {$blockStatus['minutes_remaining']} minutes."
                ]);
            }
            
            // Single query to check user in tbl_users with JOIN to get user level and department info
            $sql = "SELECT u.*, ul.user_level_name, ul.user_level_desc, d.departments_name 
                    FROM tbl_users u
                    LEFT JOIN tbl_user_level ul ON u.users_user_level_id = ul.user_level_id
                    LEFT JOIN tbl_departments d ON u.users_department_id = d.departments_id
                    WHERE u.users_school_id = :username";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':username', $json['username']);
            $stmt->execute();

            if ($stmt->rowCount() > 0) {
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Verify password and active status
                if ($this->checkPassword($json['password'], $user['users_password']) && $user['is_active'] == 1) {
                    $this->handleLoginAttempt($json['username'], true);
                    
                    // Map user level names based on users_user_level_id
                    $userLevelMap = [
                        1 => 'Admin',
                        2 => 'Personnel',
                        3 => 'Faculty/Staff',
                        4 => 'Super Admin',
                        5 => 'Dean',
                        6 => 'Secretary',
                        19 => 'Driver',
                        15 => 'School Head',
                        16 => 'SBO PRESIDENT',
                        17 => 'CSG PRESIDENT',
                        18 => 'Department Head',
                        
                    ];

                    return json_encode([ 
                        'status' => 'success',
                        'data' => [
                            'user_id' => $user['users_id'],
                            'firstname' => $user['users_fname'] ?? '',
                            'middlename' => $user['users_mname'] ?? '',
                            'lastname' => $user['users_lname'] ?? '',
                            'school_id' => $user['users_school_id'],
                            'contact_number' => $user['users_contact_number'],
                            'user_level_name' => $userLevelMap[$user['users_user_level_id']] ?? 'Unknown',
                            'user_level_desc' => $user['user_level_desc'],
                            'user_level_id' => $user['users_user_level_id'],
                            'department_id' => $user['users_department_id'],
                            'department_name' => $user['departments_name'],
                            'profile_pic' => $user['users_pic'],
                            'created_at' => $user['users_created_at'],
                            'updated_at' => $user['users_updated_at'],
                            'password' => $json['password'],
                            'email' => $user['users_email'],
                            'is_2FAactive' => $user['is_2FAactive'] ?? 0,
                            'first_login' => (bool)$user['first_login']
                        ]
                    ]);
                }
            }

            $this->handleLoginAttempt($json['username'], false);
            return json_encode(['status' => 'error', 'message' => 'Invalid credentials']);
            
        } catch (PDOException $e) {
            return json_encode(['status' => 'error', 'message' => 'Database error']);
        }
    }

    public function fetchFailedLoginExpired($username) {
        try {
            $sql = "SELECT loginfailed_id, User_schoolid, User_loginattempt, Login_until 
                    FROM tbl_loginfailed 
                    WHERE User_schoolid = :username 
                    AND Login_until < NOW()";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':username', $username);
            $stmt->execute();
            
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return false;
        }
    }

    public function checkEmailExists($email) {
        try {
            if (!$this->conn) {
                throw new PDOException("Database connection not established");
            }
            
            $check_sql = "SELECT users_email FROM tbl_users WHERE users_email = :email";
            
            $stmt = $this->conn->prepare($check_sql);
            if (!$stmt) {
                throw new PDOException("Failed to prepare statement");
            }
            
            $stmt->bindParam(':email', $email, PDO::PARAM_STR);
            
            if (!$stmt->execute()) {
                throw new PDOException("Failed to execute statement");
            }
            
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result) {
                return json_encode([
                    'status' => 'exists',
                    'message' => 'Email exists in users table'
                ]);
            }
            
            return json_encode([
                'status' => 'available',
                'message' => 'Email is available'
            ]);

        } catch (PDOException $e) {
            return json_encode([
                'status' => 'error',
                'message' => 'Database error while checking email: ' . $e->getMessage()
            ]);
        } catch (Exception $e) {
            return json_encode([
                'status' => 'error',
                'message' => 'An unexpected error occurred'
            ]);
        }
    }

    public function updateFirstLogin($users_id) {
        try {
            $sql = "UPDATE tbl_users SET first_login = 0 WHERE users_id = :users_id";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':users_id', $users_id, PDO::PARAM_INT);
            $stmt->execute();
            return json_encode([
                'status' => 'success',
                'message' => 'First login updated successfully.'
            ]);
        } catch (PDOException $e) {
            return json_encode([
                'status' => 'error',
                'message' => 'Database error while updating first_login.'
            ]);
        }
    }
}

// Handle the request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid JSON data']);
        exit;
    }

    $operation = $input['operation'] ?? '';
    $json = isset($input['json']) ? json_encode($input['json']) : '';

    if (empty($operation) || empty($json)) {
        echo json_encode(['status' => 'error', 'message' => 'Operation or JSON data is missing']);
        exit;
    }

    $login = new Login();

    switch ($operation) {
        case "admin":
        case "user":
        case "login":
            echo $login->login($json);
            break;      
        case "fetchFailedLoginExpired":
            $username = $input['json']['username'] ?? '';
            if (empty($username)) {
                echo json_encode(['status' => 'error', 'message' => 'Username is required']);
                break;
            }
            $result = $login->fetchFailedLoginExpired($username);
            echo json_encode([
                'status' => 'success',
                'data' => $result
            ]);
            break;
        case "checkEmail":
            $email = $input['json']['email'] ?? '';
            if (empty($email)) {
                echo json_encode(['status' => 'error', 'message' => 'Email is required']);
                break;
            }
            echo $login->checkEmailExists($email);
            break;
        case "updateFirstLogin":
            $users_id = $input['json']['users_id'] ?? '';
            if (empty($users_id)) {
                echo json_encode(['status' => 'error', 'message' => 'users_id is required']);
                break;
            }
            echo $login->updateFirstLogin($users_id);
            break;
        default:
            echo json_encode(['status' => 'error', 'message' => 'Invalid operation']);
            break;
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    // Handle preflight requests
    http_response_code(200);
    exit;
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
}
