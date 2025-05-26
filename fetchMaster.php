<?php
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

// Handle both POST data and JSON input
$operation = $input['operation'] ?? ($_POST['operation'] ?? '');

class VehicleMake {
    private $conn;

    public function __construct() {
        include 'connection-pdo.php'; // Include your database connection file
        $this->conn = $conn;
    }

    private function executeQuery($sql, $params = []) {
        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            return json_encode(['status' => 'success', 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        } catch (PDOException $e) {
            return json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
        }
    }

    public function fetchMakes() {
        $sql = "SELECT vehicle_make_id, vehicle_make_name FROM tbl_vehicle_make ORDER BY vehicle_make_name";
        return $this->executeQuery($sql);
    }

    public function fetchVehicleMakeById($id) {
        $sql = "SELECT vehicle_make_id, vehicle_make_name FROM tbl_vehicle_make WHERE vehicle_make_id = :id";
        return $this->executeQuery($sql, [':id' => $id]);
    }

    // New method to fetch vehicle categories
    public function fetchVehicleCategories() {
        $sql = "SELECT vehicle_category_id, vehicle_category_name FROM tbl_vehicle_category ORDER BY vehicle_category_name";
        return $this->executeQuery($sql);
    }

    // New method to fetch vehicle category by ID
    public function fetchVehicleCategoryById($id) {
        $sql = "SELECT vehicle_category_id, vehicle_category_name FROM tbl_vehicle_category WHERE vehicle_category_id = :id";
        return $this->executeQuery($sql, [':id' => $id]);
    }

    // New method to fetch departments
    public function fetchDepartments() {
        $sql = "SELECT departments_id, departments_name FROM tbl_departments ORDER BY departments_name";
        return $this->executeQuery($sql);
    }

    // New method to fetch department by ID
    public function fetchDepartmentById($id) {
        $sql = "SELECT departments_id, departments_name FROM tbl_departments WHERE departments_id = :id";
        return $this->executeQuery($sql, [':id' => $id]);
    }

    public function fetchPositions() {
        $sql = "SELECT position_id, position_name FROM tbl_personnel_position ORDER BY position_name";
        return $this->executeQuery($sql);
    }

    public function fetchPositionById($id) {
        $sql = "SELECT position_id, position_name FROM tbl_personnel_position WHERE position_id = :id";
        return $this->executeQuery($sql, [':id' => $id]);
    }

    // New method to fetch equipment categories
    public function fetchEquipments() {
        $sql = "SELECT equipments_category_id, equipments_category_name FROM tbl_equipment_category ORDER BY equipments_category_name";
        return $this->executeQuery($sql);
    }

    // New method to fetch equipment category by ID
    public function fetchEquipmentCategoryById($id) {
        $sql = "SELECT equipments_category_id, equipments_category_name FROM tbl_equipment_category WHERE equipments_category_id = :id";
        return $this->executeQuery($sql, [':id' => $id]);
    }

    // New method to fetch user levels
    public function fetchUserLevels() {
        $sql = "SELECT user_level_id, user_level_name, user_level_desc FROM tbl_user_level ORDER BY user_level_name";
        return $this->executeQuery($sql);
    }

    // New method to fetch user level by ID
    public function fetchUserLevelById($id) {
        $sql = "SELECT user_level_id, user_level_name, user_level_desc FROM tbl_user_level WHERE user_level_id = :id";
        return $this->executeQuery($sql, [':id' => $id]);
    }

    public function fetchModels() {
        $sql = "
            SELECT 
                vm.vehicle_model_id,
                vm.vehicle_model_name
            FROM 
                tbl_vehicle_model vm
            ORDER BY 
                vm.vehicle_model_name
        ";
    
        return $this->executeQuery($sql);
    }
    

    // New method to fetch vehicle model by ID
    public function fetchModelById($id) {
        $sql = "
            SELECT 
                vm.vehicle_model_id, 
                vm.vehicle_model_name, 
                vc.vehicle_category_name, 
                vm2.vehicle_make_name
            FROM 
                tbl_vehicle_model AS vm
            INNER JOIN 
                tbl_vehicle_category AS vc ON vm.vehicle_category_id = vc.vehicle_category_id
            INNER JOIN 
                tbl_vehicle_make AS vm2 ON vm.vehicle_model_vehicle_make_id = vm2.vehicle_make_id
            WHERE 
                vm.vehicle_model_id = :id
        ";
    
        return $this->executeQuery($sql, [':id' => $id]);
    }
    

    public function __destruct() {
        unset($this->conn); // Clean up the database connection
    }

    public function fetchHoliday() {
        $sql = "SELECT `holiday_id`, `holiday_name`, `holiday_date` FROM `tbl_holidays` WHERE 1";
        return $this->executeQuery($sql);
    }


    // New method to fetch equipment by ID and join with equipment category
    public function fetchEquipmentById($id) {
            // 1) Fetch the equipment
            $sql = "
                SELECT 
                    e.equip_id,
                    e.equip_name,
                    e.category_name,
                    e.equip_pic,
                    e.is_active,
                    e.user_admin_id,
                    e.equip_created_at
                FROM tbl_equipments AS e
                WHERE e.equip_id = :id
                AND e.is_active = 1
                LIMIT 1
            ";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([':id' => $id]);
            $equip = $stmt->fetch(PDO::FETCH_ASSOC);

            if (! $equip) {
                return json_encode([
                    'status'  => 'error',
                    'message' => 'Equipment not found or inactive.'
                ]);
            }

            // 2) Fetch its active units, with status names
            $unitSql = "
                SELECT
                    eu.unit_id,
                    eu.serial_number,
                    eu.quantity,
                    eu.unit_created_at,
                    sua.status_availability_name AS availability_status
                FROM tbl_equipment_unit AS eu
                LEFT JOIN tbl_status_availability AS sua
                ON eu.status_availability_id = sua.status_availability_id
                WHERE eu.equip_id   = :id
                AND eu.is_active  = 1
                ORDER BY eu.unit_id
            ";
            $unitStmt = $this->conn->prepare($unitSql);
            $unitStmt->execute([':id' => $id]);
            $units = $unitStmt->fetchAll(PDO::FETCH_ASSOC);

            // 3) Compute displayed equip_quantity
            $displayQty = count($units);

            // 4) Build response
            $response = [
                'equip_id'        => (int)$equip['equip_id'],
                'equip_name'      => $equip['equip_name'],
                'category_name'   => $equip['category_name'],
                'equip_pic'       => $equip['equip_pic'],
                'user_admin_id'   => (int)$equip['user_admin_id'],
                'equip_created_at'=> $equip['equip_created_at'],
                'is_active'       => (bool)$equip['is_active'],
                'equip_quantity'  => $displayQty,
                'units'           => array_map(function($u) {
                    return [
                        'unit_id'            => (int)$u['unit_id'],
                        'serial_number'      => $u['serial_number'],
                        'quantity'           => (int)$u['quantity'],
                        'availability_status'=> $u['availability_status'],
                        'unit_created_at'    => $u['unit_created_at'],
                    ];
                }, $units),
            ];

            return json_encode([
                'status' => 'success',
                'data'   => $response
            ]);
        }


    public function fetchVehicleById($id) {
    $sql = "
        SELECT 
            vmd.vehicle_model_name,
            v.vehicle_make_name,
            v.vehicle_category_name,
            v.vehicle_license,
            sa.status_availability_name,
            v.year,
            v.vehicle_pic
        FROM tbl_vehicle v
        JOIN tbl_vehicle_model vmd 
            ON v.vehicle_model_id = vmd.vehicle_model_id
        JOIN tbl_status_availability sa 
            ON v.status_availability_id = sa.status_availability_id
        WHERE v.vehicle_id = :id
    ";

    return $this->executeQuery($sql, [':id' => $id]);
    }


    public function fetchConditions() {
        $sql = "SELECT `id`, `condition_name` FROM `tbl_condition` WHERE 1";
        return $this->executeQuery($sql);
    }
    
    // New method to fetch condition by ID
    public function fetchConditionById($id) {
        $sql = "SELECT `condition_id`, `condition_name` FROM `tbl_condition_master` WHERE condition_id = :id";
        return $this->executeQuery($sql, [':id' => $id]);
    }
    
    public function fetchVenueById($id) {
        $sql = "SELECT 
            v.ven_id, 
            v.ven_name, 
            v.ven_occupancy, 
            v.ven_created_at, 
            v.ven_updated_at, 
            v.status_availability_id, 
            v.ven_pic, 
            v.ven_operating_hours, 
            v.is_active, 
            v.user_admin_id,
            sa.status_availability_name
        FROM tbl_venue v
        INNER JOIN tbl_status_availability sa ON v.status_availability_id = sa.status_availability_id
        WHERE v.ven_id = :id";
        
        return $this->executeQuery($sql, [':id' => $id]);
    }
    
    public function fetchUsersById($id) {
        if (!is_numeric($id)) {
            return json_encode(['status' => 'error', 'message' => 'Invalid ID format']);
        }

        $sql = "SELECT 
                    u.*,
                    d.departments_name,
                    ul.user_level_name
                FROM 
                    tbl_users u
                LEFT JOIN 
                    tbl_departments d ON u.users_department_id = d.departments_id
                LEFT JOIN 
                    tbl_user_level ul ON u.users_user_level_id = ul.user_level_id
                WHERE 
                    u.users_id = :id";
        return $this->executeQuery($sql, [':id' => $id]);
    }




    

    public function fetchStatusAvailability() {
        $sql = "SELECT `status_availability_id`, `status_availability_name` FROM `tbl_status_availability` WHERE 1";
        return $this->executeQuery($sql);
    }

    public function fetchDriverById($id) {
        if (!is_numeric($id)) {
            return json_encode(['status' => 'error', 'message' => 'Invalid ID format']);
        }

        $sql = "SELECT 
                    d.*
                FROM 
                    tbl_driver d
                WHERE 
                    d.driver_id = :id";
        return $this->executeQuery($sql, [':id' => $id]);
    }

    public function fetchDriver() {
        $sql = "SELECT `driver_id`, `driver_first_name`, `driver_middle_name`, `driver_last_name`, `driver_suffix`, `employee_id`, `driver_birthdate`, `user_admin_id`, `is_active`, `driver_contact_number`, `driver_address`, `created_at`, `updated_at` FROM `tbl_driver` WHERE `is_active` = 1";
        return $this->executeQuery($sql);
    }
    public function fetchInactiveDriver() {
        $sql = "SELECT `driver_id`, `driver_first_name`, `driver_middle_name`, `driver_last_name`, `driver_suffix`, `employee_id`, `driver_birthdate`, `user_admin_id`, `is_active`, `driver_contact_number`, `driver_address`, `created_at`, `updated_at` FROM `tbl_driver` WHERE `is_active` = 0";
        return $this->executeQuery($sql);
    }

    public function fetchPersonnelById($id) {
        if (!is_numeric($id)) {
            return json_encode(['status' => 'error', 'message' => 'Invalid ID format']);
        }

        $sql = "SELECT 
                    p.*,
                    d.departments_name,
                    ul.user_level_name
                FROM 
                    tbl_personel p
                LEFT JOIN 
                    tbl_departments d ON p.jo_personel_department_id = d.departments_id
                LEFT JOIN 
                    tbl_user_level ul ON p.jo_user_level_id = ul.user_level_id
                WHERE 
                    p.jo_personel_id = :id";
        return $this->executeQuery($sql, [':id' => $id]);
    }

    public function enable2FAById($userId, $userType) {
        if (!is_numeric($userId)) {
            return json_encode(['status' => 'error', 'message' => 'Invalid user ID']);
        }

        try {
            // Check if user exists
            $checkSql = "SELECT COUNT(*) as count FROM tbl_users WHERE users_id = :userId";
            $checkStmt = $this->conn->prepare($checkSql);
            $checkStmt->execute([':userId' => $userId]);
            $count = $checkStmt->fetchColumn();

            if ($count > 0) {
                // Update 2FA status
                $updateSql = "UPDATE tbl_users SET is_2FAactive = 1 WHERE users_id = :userId";
                $updateStmt = $this->conn->prepare($updateSql);
                $updateStmt->execute([':userId' => $userId]);
                
                return json_encode([
                    'status' => 'success',
                    'message' => '2FA enabled successfully'
                ]);
            }

            return json_encode([
                'status' => 'error',
                'message' => 'User ID not found'
            ]);

        } catch (PDOException $e) {
            return json_encode([
                'status' => 'error',
                'message' => "Database error: " . $e->getMessage()
            ]);
        }
    }

    public function unenable2FA($userId, $userType) {
        if (!is_numeric($userId)) {
            return json_encode(['status' => 'error', 'message' => 'Invalid user ID']);
        }

        try {
            // Check if user exists
            $checkSql = "SELECT COUNT(*) as count FROM tbl_users WHERE users_id = :userId";
            $checkStmt = $this->conn->prepare($checkSql);
            $checkStmt->execute([':userId' => $userId]);
            $count = $checkStmt->fetchColumn();

            if ($count > 0) {
                // Update 2FA status
                $updateSql = "UPDATE tbl_users SET is_2FAactive = 0 WHERE users_id = :userId";
                $updateStmt = $this->conn->prepare($updateSql);
                $updateStmt->execute([':userId' => $userId]);
                
                return json_encode([
                    'status' => 'success',
                    'message' => '2FA disabled successfully'
                ]);
            }

            return json_encode([
                'status' => 'error', 
                'message' => 'User ID not found'
            ]);

        } catch (PDOException $e) {
            return json_encode([
                'status' => 'error',
                'message' => "Database error: " . $e->getMessage()
            ]);
        }
    }

    public function get_message($userid) {
        $sql = "
            SELECT 
                c.*, 
                CONCAT(u.users_fname, ' ', u.users_lname)     AS sender_name,
                CONCAT(u2.users_fname, ' ', u2.users_lname)   AS receiver_name
            FROM tbl_chat c
            JOIN tbl_users u  ON c.sender_id   = u.users_id
            JOIN tbl_users u2 ON c.receiver_id = u2.users_id
            WHERE c.sender_id   = :userid
               OR c.receiver_id = :userid
            ORDER BY c.created_at ASC
        ";
    
        return $this->executeQuery($sql, [':userid' => $userid]);
    }
    
}

// Handle incoming requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' || $_SERVER['REQUEST_METHOD'] === 'GET') {

    $vehicleMake = new VehicleMake();

    switch ($operation) {
        case "fetchVehicleById":
            $vehicleId = $input['id'] ?? ($_POST['id'] ?? null);
            if ($vehicleId) {
                echo $vehicleMake->fetchVehicleById($vehicleId); // Fetch vehicle by ID
            } else {
                echo json_encode(['status' => 'error', 'message' => 'ID parameter is missing.']);
            }
            break;
            
        case "fetchDriverById":
            $driverId = $input['id'] ?? ($_POST['id'] ?? null);
            if ($driverId) {
                echo $vehicleMake->fetchDriverById($driverId);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'ID parameter is missing.']);
            }
            break;
        
        case "fetchEquipmentById": // Fetch equipment by ID with category
            $equipmentId = $input['id'] ?? ($_POST['id'] ?? null);
            if ($equipmentId) {
                echo $vehicleMake->fetchEquipmentById($equipmentId); // Fetch equipment by ID
            } else {
                echo json_encode(['status' => 'error', 'message' => 'ID parameter is missing.']);
            }
            break;
        
        case "fetchMake":
            echo $vehicleMake->fetchMakes(); // Fetch all vehicle makes
            break;
        case "fetchVehicleMakeById":
            $vehicleMakeId = $input['id'] ?? ($_POST['id'] ?? null);
            if ($vehicleMakeId) {
                echo $vehicleMake->fetchVehicleMakeById($vehicleMakeId); // Fetch vehicle make by ID
            } else {
                echo json_encode(['status' => 'error', 'message' => 'ID parameter is missing.']);
            }
            break;
        case "fetchVehicleCategories":
            echo $vehicleMake->fetchVehicleCategories(); // Fetch all vehicle categories
            break;
        case "fetchVehicleCategoryById":
            $vehicleCategoryId = $input['id'] ?? ($_POST['id'] ?? null);
            if ($vehicleCategoryId) {
                echo $vehicleMake->fetchVehicleCategoryById($vehicleCategoryId); // Fetch vehicle category by ID
            } else {
                echo json_encode(['status' => 'error', 'message' => 'ID parameter is missing.']);
            }
            break;
        case "fetchDepartments":
            echo $vehicleMake->fetchDepartments(); // Fetch all departments
            break;
        case "fetchDepartmentById":
            $departmentId = $input['id'] ?? ($_POST['id'] ?? null);
            if ($departmentId) {
                echo $vehicleMake->fetchDepartmentById($departmentId); // Fetch department by ID
            } else {
                echo json_encode(['status' => 'error', 'message' => 'ID parameter is missing.']);
            }
            break;
        case "fetchPositions":
            echo $vehicleMake->fetchPositions(); // Fetch all positions
            break;
        case "fetchPositionById":
            $positionId = $input['id'] ?? ($_POST['id'] ?? null);
            if ($positionId) {
                echo $vehicleMake->fetchPositionById($positionId); // Fetch position by ID
            } else {
                echo json_encode(['status' => 'error', 'message' => 'ID parameter is missing.']);
            }
            break;
        case "fetchEquipments": // Fetch all equipment categories
            echo $vehicleMake->fetchEquipments();
            break;
        case "fetchEquipmentCategoryById": // Fetch equipment category by ID
            $equipmentId = $input['id'] ?? ($_POST['id'] ?? null);
            if ($equipmentId) {
                echo $vehicleMake->fetchEquipmentCategoryById($equipmentId); 
            } else {
                echo json_encode(['status' => 'error', 'message' => 'ID parameter is missing.']);
            }
            break;
        case "fetchUserLevels": // Fetch all user levels
            echo $vehicleMake->fetchUserLevels();
            break;
        case "fetchUserLevelById": // Fetch user level by ID
            $userLevelId = $input['id'] ?? ($_POST['id'] ?? null);
            if ($userLevelId) {
                echo $vehicleMake->fetchUserLevelById($userLevelId); 
            } else {
                echo json_encode(['status' => 'error', 'message' => 'ID parameter is missing.']);
            }
            break;
        case "fetchModels":
            echo $vehicleMake->fetchModels(); // Fetch all vehicle models
            break;
        case "fetchModelById":
            $vehicleModelId = $input['id'] ?? ($_POST['id'] ?? null);
            if ($vehicleModelId) {
                echo $vehicleMake->fetchModelById($vehicleModelId); // Fetch vehicle model by ID
            } else {
                echo json_encode(['status' => 'error', 'message' => 'ID parameter is missing.']);
            }
            break;
        case "fetchConditions":
            echo $vehicleMake->fetchConditions(); // Fetch all conditions
            break;
        case "fetchConditionById":
            $conditionId = $input['id'] ?? ($_POST['id'] ?? null);
            if ($conditionId) {
                echo $vehicleMake->fetchConditionById($conditionId); // Fetch condition by ID
            } else {
                echo json_encode(['status' => 'error', 'message' => 'ID parameter is missing.']);
            }
            break;
        case "fetchVenueById":
            $venueId = $input['id'] ?? ($_POST['id'] ?? null);
            if ($venueId) {
                echo $vehicleMake->fetchVenueById($venueId);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'ID parameter is missing.']);
            }
            break;
        case "fetchUsersById":

            $id = $input['id'] ?? ($_POST['id'] ?? null);
            if ($id) {
                echo $vehicleMake->fetchUsersById($id);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'ID parameter is missing']);
            }
            break;
        case "fetchStatusAvailability":
            echo $vehicleMake->fetchStatusAvailability();
            break;
              
        case "enable2FA":
            $userId = $input['id'] ?? ($_POST['id'] ?? null);
            $userType = $input['userType'] ?? ($_POST['userType'] ?? null);
            if ($userId && $userType) {
                echo $vehicleMake->enable2FAById($userId, $userType);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'ID and userType parameters are required']);
            }
            break;
        case "unenable2FA":
            $userId = $input['id'] ?? ($_POST['id'] ?? null);
            $userType = $input['userType'] ?? ($_POST['userType'] ?? null);
            if ($userId && $userType) {
                echo $vehicleMake->unenable2FA($userId, $userType);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'ID and userType parameters are required']);
            }
            break;
        case "get_message":
            $userId = $input['userid'] ?? ($_POST['userid'] ?? null);
            if ($userId) {
                echo $vehicleMake->get_message($userId);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'userid parameter is missing']);
            }
            break;
        case "fetchHoliday":
            echo $vehicleMake->fetchHoliday(); // Fetch all holidays
            break;
        case "fetchDriver":
            echo $vehicleMake->fetchDriver(); // Fetch all drivers
            break;
        case "fetchInactiveDriver":
            echo $vehicleMake->fetchInactiveDriver(); // Fetch all inactive drivers
            break;
        default:
            echo json_encode(['status' => 'error', 'message' => 'Invalid operation']);
            break;
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method. Use POST.']);
}
?>
