<?php
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// Get JSON input and decode - consolidated input handling
$rawInput = file_get_contents('php://input');
error_log("Raw input: " . $rawInput); // Debug log

// Ensure valid JSON input
$input = json_decode($rawInput, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid JSON input']);
    exit;
}

// Validate required input structure
if (!isset($input['operation']) || !isset($input['data'])) {
    echo json_encode(['status' => 'error', 'message' => 'Missing operation or data']);
    exit;
}

// Enable error reporting for debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

class User {
    private $conn;

    public function __construct() {
        include 'connection-pdo.php'; 
        $this->conn = $conn;
    }

    // Check if Venue exists
    public function venueExists($venueName) {
        try {
            $sql = "SELECT COUNT(*) FROM tbl_venue WHERE ven_name = :name";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':name', $venueName);
            $stmt->execute();
            return $stmt->fetchColumn() > 0;
        } catch(PDOException $e) {
            return false; // Treat as not existing on error
        }
    }

    // Save User function
    public function saveUser($data) {
        try {
            // Ensure schoolId is treated as a string
            if (isset($data['schoolId'])) {
                $data['schoolId'] = (string) $data['schoolId'];
            }

            // Handle base64 image
            $picPath = null;
            if (isset($data['pic']) && !empty($data['pic'])) {
                $uploadDir = 'uploads/';
                if (!file_exists($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }
                
                // Get file data and type from base64 string
                $image_parts = explode(";base64,", $data['pic']);
                $image_type_aux = explode("image/", $image_parts[0]);
                $image_type = $image_type_aux[1];
                $image_base64 = base64_decode($image_parts[1]);
                
                // Generate unique filename using timestamp
                $filename = 'profile_' . time() . '.' . $image_type;
                $picPath = $uploadDir . $filename;
                
                file_put_contents($picPath, $image_base64);
            }

            // Hash the password before saving
            $hashedPassword = password_hash($data['password'], PASSWORD_BCRYPT);
            
            // Updated SQL statement to include users_email
            $sql = "INSERT INTO tbl_users (users_fname, users_mname, users_lname, 
                    users_email, users_school_id, users_contact_number, users_user_level_id, 
                    users_password, users_department_id, 
                    users_pic, users_created_at, users_updated_at) 
                    VALUES (:fname, :mname, :lname, :email, :schoolId, :contact, :userLevelId, 
                    :password, :departmentId, :pic, NOW(), NOW())";
    
            $stmt = $this->conn->prepare($sql);
            
            // Bind parameters with explicit PDO parameter types
            $stmt->bindParam(':fname', $data['fname'], PDO::PARAM_STR);
            $stmt->bindParam(':mname', $data['mname'], PDO::PARAM_STR);
            $stmt->bindParam(':lname', $data['lname'], PDO::PARAM_STR);
            $stmt->bindParam(':email', $data['email'], PDO::PARAM_STR);
            $stmt->bindParam(':schoolId', $data['schoolId'], PDO::PARAM_STR);
            $stmt->bindParam(':contact', $data['contact'], PDO::PARAM_STR);
            $stmt->bindParam(':userLevelId', $data['userLevelId'], PDO::PARAM_INT);
            $stmt->bindParam(':password', $hashedPassword, PDO::PARAM_STR);
            $stmt->bindParam(':departmentId', $data['departmentId'], PDO::PARAM_INT);
            $stmt->bindParam(':pic', $picPath, PDO::PARAM_STR);
    
            // Execute the statement and return the appropriate response
            if ($stmt->execute()) {
                return json_encode(['status' => 'success', 'message' => 'User added successfully.']);
            }
    
            return json_encode(['status' => 'error', 'message' => 'Failed to add user.']);
        } catch(PDOException $e) {
            return json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
        }
    }
    
    // Insert Personnel function
    public function savePersonnel($data) {
        try {
            // If data is a JSON string, decode it
            if (is_string($data)) {
                $data = json_decode($data, true);
            }

            // Validate decoded data
            if (!$data || !is_array($data)) {
                return json_encode(['status' => 'error', 'message' => 'Invalid input data']);
            }

            // Check if personnel already exists
            $checkSql = "SELECT COUNT(*) FROM tbl_personel 
                        WHERE jo_personel_fname = :firstName 
                        AND jo_personel_lname = :lastName";
            $checkStmt = $this->conn->prepare($checkSql);
            $checkStmt->bindParam(':firstName', $data['firstName']);
            $checkStmt->bindParam(':lastName', $data['lastName']);
            $checkStmt->execute();
            
            if ($checkStmt->fetchColumn() > 0) {
                return json_encode(['status' => 'error', 'message' => 'This personnel already exists.']);
            }

            // Handle profile picture upload
            $picPath = null;
            if (isset($data['pic']) && !empty($data['pic'])) {
                $uploadDir = 'uploads/personnel/';
                if (!file_exists($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }
                
                $image_parts = explode(";base64,", $data['pic']);
                $image_type_aux = explode("image/", $image_parts[0]);
                $image_type = $image_type_aux[1];
                $image_base64 = base64_decode($image_parts[1]);
                
                $filename = 'personnel_' . time() . '.' . $image_type;
                $picPath = $uploadDir . $filename;
                
                file_put_contents($picPath, $image_base64);
            }

            // Hash the password
            $hashedPassword = password_hash($data['password'], PASSWORD_BCRYPT);

            $sql = "INSERT INTO tbl_personel (
                    admin_id,
                    jo_personel_fname,
                    jo_personel_mname,
                    jo_personel_lname,
                    jo_personel_contact,
                    jo_personel_school_id,
                    jo_personel_contact_number,
                    jo_user_level_id,
                    jo_personel_password,
                    jo_personel_department_id,
                    jo_personel_pic,
                    jo_personel_created_at,
                    jo_personel_updated_at,
                    is_active
                ) VALUES (
                    :adminId,
                    :firstName,
                    :middleName,
                    :lastName,
                    :contact,
                    :schoolId,
                    :contactNumber,
                    :userLevelId,
                    :password,
                    :departmentId,
                    :pic,
                    NOW(),
                    NOW(),
                    1
                )";

            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':adminId', $data['adminId']);
            $stmt->bindParam(':firstName', $data['firstName']);
            $stmt->bindParam(':middleName', $data['middleName']);
            $stmt->bindParam(':lastName', $data['lastName']);
            $stmt->bindParam(':contact', $data['contact']);
            $stmt->bindParam(':schoolId', $data['schoolId']);
            $stmt->bindParam(':contactNumber', $data['contactNumber']);
            $stmt->bindParam(':userLevelId', $data['userLevelId']);
            $stmt->bindParam(':password', $hashedPassword);
            $stmt->bindParam(':departmentId', $data['departmentId']);
            $stmt->bindParam(':pic', $picPath);

            if ($stmt->execute()) {
                return json_encode(['status' => 'success', 'message' => 'Personnel added successfully']);
            }

            return json_encode(['status' => 'error', 'message' => 'Failed to add personnel']);
        } catch(PDOException $e) {
            return json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }

    // Insert Venue function
    public function saveVenue($data) {
        error_log("saveVenue received data: " . print_r($data, true));

        try {
            if (!isset($data['user_admin_id'])) {
                return json_encode(['status' => 'error', 'message' => 'Admin ID is required']);
            }
            if (!isset($data['name']) || !isset($data['occupancy']) || 
                !isset($data['operating_hours']) || !isset($data['status_availability_id'])) {
                return json_encode(['status' => 'error', 'message' => 'Missing required fields']);
            }
            if ($this->venueExists($data['name'])) {
                return json_encode(['status' => 'error', 'message' => 'This venue name is already in use.']);
            }
            $picPath = null;
            if (isset($data['ven_pic']) && !empty($data['ven_pic'])) {
                $uploadDir = 'uploads/venues/';
                if (!file_exists($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }
                
                $image_parts = explode(";base64,", $data['ven_pic']);
                $image_type_aux = explode("image/", $image_parts[0]);
                $image_type = $image_type_aux[1];
                $image_base64 = base64_decode($image_parts[1]);
                
                $filename = 'venue_' . time() . '.' . $image_type;
                $picPath = $uploadDir . $filename;
                
                file_put_contents($picPath, $image_base64);
            }

            $sql = "INSERT INTO tbl_venue (
                ven_name, ven_occupancy, ven_created_at, ven_updated_at, 
                status_availability_id, ven_pic, ven_operating_hours, 
                is_active, user_admin_id
            ) VALUES (
                :name, :occupancy, NOW(), NOW(), 
                :status_availability_id, :pic, :operating_hours, 
                1, :admin_id
            )";

            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':name', $data['name'], PDO::PARAM_STR);
            $stmt->bindParam(':occupancy', $data['occupancy'], PDO::PARAM_INT);
            $stmt->bindParam(':pic', $picPath, PDO::PARAM_STR);
            $stmt->bindParam(':operating_hours', $data['operating_hours'], PDO::PARAM_STR);
            $stmt->bindParam(':admin_id', $data['user_admin_id'], PDO::PARAM_INT);
            $stmt->bindParam(':status_availability_id', $data['status_availability_id'], PDO::PARAM_INT);

            if ($stmt->execute()) {
                return json_encode(['status' => 'success', 'message' => 'Venue added successfully']);
            }

            return json_encode(['status' => 'error', 'message' => 'Failed to add venue']);
        } catch(PDOException $e) {
            error_log("Database error: " . $e->getMessage());
            return json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
        }
    }

    public function saveVehicle($json) {
        $json = json_decode($json, true);
        
        $vehicle_model_id = $json['vehicle_model_id'] ?? null;
        $vehicle_license = $json['vehicle_license'] ?? null;
        $year = $json['year'] ?? null;
        $user_admin_id = $json['user_admin_id'] ?? null;
        $status_availability_id = $json['status_availability_id'] ?? null;
    
        if (!$vehicle_model_id || !$vehicle_license || !$year || !$status_availability_id || !$user_admin_id) {
            return json_encode(['status' => 'error', 'message' => 'Vehicle model ID, license, year, admin ID and status availability ID are required.']);
        }
    
        try {
            // Handle vehicle picture upload
            $picPath = null;
            if (isset($json['vehicle_pic']) && !empty($json['vehicle_pic'])) {
                $uploadDir = 'uploads/vehicles/';
                if (!file_exists($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }

                $image_parts = explode(";base64,", $json['vehicle_pic']);
                $image_type_aux = explode("image/", $image_parts[0]);
                $image_type = $image_type_aux[1];
                $image_base64 = base64_decode($image_parts[1]);

                $filename = 'vehicle_' . time() . '.' . $image_type;
                $picPath = $uploadDir . $filename;
                
                file_put_contents($picPath, $image_base64);
            }

            // Validate vehicle model exists
            $checkModelSql = "SELECT COUNT(*) FROM tbl_vehicle_model WHERE vehicle_model_id = :modelId";
            $checkModelStmt = $this->conn->prepare($checkModelSql);
            $checkModelStmt->bindParam(':modelId', $vehicle_model_id);
            $checkModelStmt->execute();
            if ($checkModelStmt->fetchColumn() == 0) {
                return json_encode(['status' => 'error', 'message' => 'Invalid vehicle model ID.']);
            }

            // Check for duplicate license
            $checkSql = "SELECT COUNT(*) FROM tbl_vehicle WHERE vehicle_license = :license";
            $checkStmt = $this->conn->prepare($checkSql);
            $checkStmt->bindParam(':license', $vehicle_license);
            $checkStmt->execute();
            if ($checkStmt->fetchColumn() > 0) {
                return json_encode(['status' => 'error', 'message' => 'This vehicle license is already in use.']);
            }
    
            $sqlVehicle = "INSERT INTO tbl_vehicle (
                vehicle_model_id, 
                vehicle_license, 
                year, 
                status_availability_id, 
                vehicle_pic, 
                user_admin_id,
                is_active
            ) VALUES (
                :modelId, 
                :licensed, 
                :year, 
                :statusAvailabilityId, 
                :pic, 
                :adminId,
                1
            )";
            $stmtVehicle = $this->conn->prepare($sqlVehicle);
            $stmtVehicle->bindParam(':modelId', $vehicle_model_id);
            $stmtVehicle->bindParam(':licensed', $vehicle_license);
            $stmtVehicle->bindParam(':year', $year);
            $stmtVehicle->bindParam(':pic', $picPath);
            $stmtVehicle->bindParam(':adminId', $user_admin_id);
            $stmtVehicle->bindParam(':statusAvailabilityId', $status_availability_id);
            $stmtVehicle->execute();
    
            return json_encode([
                'status' => 'success', 
                'message' => 'Vehicle added successfully.'
            ]);
        } catch(PDOException $e) {
            return json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }

    // Insert Equipment function
    public function saveEquipment($json) {
        try {
            // If json is already an array, use it directly, otherwise decode it
            $data = is_array($json) ? $json : json_decode($json, true);

            if (!$data || !is_array($data)) {
                return json_encode(['status' => 'error', 'message' => 'Invalid input data']);
            }

            // Validate required fields
            $requiredFields = ['name', 'quantity', 'status_availability_id', 'categoryId', 'user_admin_id'];
            $missingFields = array_filter($requiredFields, function($field) use ($data) {
                return !isset($data[$field]) || $data[$field] === '';
            });

            if (!empty($missingFields)) {
                return json_encode(['status' => 'error', 'message' => 'Required fields missing: ' . implode(', ', $missingFields)]);
            }

            // Handle equipment picture upload
            $picPath = null;
            if (isset($data['equip_pic']) && !empty($data['equip_pic'])) {
                $uploadDir = 'uploads/equipment/';
                if (!file_exists($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }
                
                $image_parts = explode(";base64,", $data['equip_pic']);
                $image_type_aux = explode("image/", $image_parts[0]);
                $image_type = $image_type_aux[1];
                $image_base64 = base64_decode($image_parts[1]);
                
                $filename = 'equipment_' . time() . '.' . $image_type;
                $picPath = $uploadDir . $filename;
                
                file_put_contents($picPath, $image_base64);
            }
    
            $sql = "INSERT INTO tbl_equipments (
                        equip_name, 
                        equip_quantity,  
                        status_availability_id, 
                        equip_created_at, 
                        equip_updated_at, 
                        equipment_equipment_category_id,
                        equip_pic,
                        is_active,
                        user_admin_id
                    ) VALUES (
                        :name, 
                        :quantity, 
                        :status_availability_id,
                        NOW(), 
                        NOW(), 
                        :categoryId,
                        :pic,
                        1,
                        :user_admin_id
                    )";
            
            $stmt = $this->conn->prepare($sql);

            // Bind parameters with explicit types
            $stmt->bindParam(':name', $data['name'], PDO::PARAM_STR);
            $stmt->bindParam(':quantity', $data['quantity'], PDO::PARAM_INT);
            $stmt->bindParam(':status_availability_id', $data['status_availability_id'], PDO::PARAM_INT);
            $stmt->bindParam(':categoryId', $data['categoryId'], PDO::PARAM_INT);
            $stmt->bindParam(':pic', $picPath, PDO::PARAM_STR);
            $stmt->bindParam(':user_admin_id', $data['user_admin_id'], PDO::PARAM_INT);

            if ($stmt->execute()) {
                return json_encode([
                    'status' => 'success',
                    'message' => 'Equipment added successfully'
                ]);
            }

            return json_encode(['status' => 'error', 'message' => 'Failed to add equipment']);
        } catch(PDOException $e) {
            error_log("Database error in saveEquipment: " . $e->getMessage());
            return json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }

    public function saveHoliday($data) {
        try {
            // If data is a JSON string, decode it
            if (is_string($data)) {
                $data = json_decode($data, true);
            }

            // Validate decoded data
            if (!$data || !is_array($data)) {
                return json_encode(['status' => 'error', 'message' => 'Invalid input data']);
            }

            // Check required fields
            if (!isset($data['holiday_name']) || !isset($data['holiday_date'])) {
                return json_encode(['status' => 'error', 'message' => 'Holiday name and date are required']);
            }

            // Check if holiday already exists on the same date
            $checkSql = "SELECT COUNT(*) FROM tbl_holidays WHERE holiday_date = :holiday_date";
            $checkStmt = $this->conn->prepare($checkSql);
            $checkStmt->bindParam(':holiday_date', $data['holiday_date']);
            $checkStmt->execute();
            
            if ($checkStmt->fetchColumn() > 0) {
                return json_encode(['status' => 'error', 'message' => 'A holiday already exists on this date']);
            }

            // Insert the holiday
            $sql = "INSERT INTO tbl_holidays (holiday_name, holiday_date) VALUES (:name, :date)";
            $stmt = $this->conn->prepare($sql);
            
            $stmt->bindParam(':name', $data['holiday_name'], PDO::PARAM_STR);
            $stmt->bindParam(':date', $data['holiday_date'], PDO::PARAM_STR);

            if ($stmt->execute()) {
                return json_encode([
                    'status' => 'success',
                    'message' => 'Holiday added successfully'
                ]);
            }

            return json_encode(['status' => 'error', 'message' => 'Failed to add holiday']);
        } catch(PDOException $e) {
            error_log("Database error in saveHoliday: " . $e->getMessage());
            return json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }
}

// Handle the request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $user = new User();
        $operation = $input['operation'];
        $data = $input['data'];

        error_log("Processing operation: " . $operation);
        error_log("With data: " . print_r($data, true));

        switch($operation) {
            case "saveUser":
                echo $user->saveUser($data);
                break;
            case "saveVenue":
                echo $user->saveVenue($data);
                break;
            case "saveVehicle":
                echo $user->saveVehicle(json_encode($data));
                break;
            case "saveEquipment": 
                echo $user->saveEquipment(json_encode($data));
                break;
            case "saveHoliday":
                echo $user->saveHoliday($data);
                break;
            default:
                echo json_encode(['status' => 'error', 'message' => 'Invalid operation']);
                break;
        }
    } catch (Exception $e) {
        error_log("Error in switch: " . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'Server error: ' . $e->getMessage()]);
    }
}
?>
