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
    
    // New method to fetch models by category and make ID
    public function fetchModelsByCategoryAndMake($categoryId, $makeId) {
        $sql = "
            SELECT 
                vm.vehicle_model_id, 
                vm.vehicle_model_name
            FROM 
                tbl_vehicle_model AS vm
            WHERE 
                vm.vehicle_category_id = :categoryId
            AND 
                vm.vehicle_model_vehicle_make_id = :makeId
            ORDER BY 
                vm.vehicle_model_name
        ";
    
        return $this->executeQuery($sql, [':categoryId' => $categoryId, ':makeId' => $makeId]);
    }

    public function __destruct() {
        unset($this->conn); // Clean up the database connection
    }

    public function fetchHoliday() {
        $sql = "SELECT `holiday_id`, `holiday_name`, `holiday_date` FROM `tbl_holidays` WHERE 1";
        return $this->executeQuery($sql);
    }


    public function fetchEquipmentById($id) { // Removed $type as it's not used directly in the initial fetch
    try {
        // 1) Fetch the equipment with its category name
        $sql = "
            SELECT
                te.equip_id,
                te.equip_name,
                tec.equipments_category_name AS category_name, -- Fetch category name from joined table
                te.is_active,
                te.user_admin_id,
                te.equip_created_at,
                te.equip_type,
                te.equipments_category_id -- Include category ID if needed for other logic
            FROM
                tbl_equipments AS te
            INNER JOIN
                tbl_equipment_category AS tec ON te.equipments_category_id = tec.equipments_category_id
            WHERE
                te.equip_id = :id
                AND te.is_active = 1
            LIMIT 1
        ";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':id' => $id]);
        $equip = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$equip) {
            return json_encode([
                'status'  => 'error',
                'message' => 'Equipment not found or inactive.'
            ]);
        }

        $displayQty = 0;
        $formattedUnits = [];

        // Determine logic based on equip_type from the fetched equipment
        if ($equip['equip_type'] === 'Consumable') {
            // Fetch quantity from tbl_equipment_quantity for Consumable type
            $quantitySql = "
                SELECT quantity
                FROM tbl_equipment_quantity
                WHERE equip_id = :id
                ORDER BY last_updated DESC
                LIMIT 1
            ";
            $quantityStmt = $this->conn->prepare($quantitySql);
            $quantityStmt->execute([':id' => $id]);
            $quantityResult = $quantityStmt->fetch(PDO::FETCH_ASSOC);
            $displayQty = $quantityResult ? (int)$quantityResult['quantity'] : 0;
        } else {
            // For non-Consumable types, fetch from tbl_equipment_unit
            $unitSql = "
                SELECT
                    unit_id,
                    equip_id,
                    serial_number,
                    brand,
                    size,
                    color,
                    status_availability_id,
                    unit_created_at,
                    is_active,
                    user_admin_id
                FROM tbl_equipment_unit
                WHERE equip_id = :id
                AND is_active = 1
                ORDER BY unit_id
            ";
            $unitStmt = $this->conn->prepare($unitSql);
            $unitStmt->execute([':id' => $id]);
            $units = $unitStmt->fetchAll(PDO::FETCH_ASSOC);

            $formattedUnits = array_map(function($u) {
                return [
                    'unit_id'               => (int)$u['unit_id'],
                    'serial_number'         => $u['serial_number'],
                    'brand'                 => $u['brand'],
                    'size'                  => $u['size'],
                    'color'                 => $u['color'],
                    'status_availability_id'=> (int)$u['status_availability_id'],
                    'unit_created_at'       => $u['unit_created_at'],
                    'user_admin_id'         => (int)$u['user_admin_id'],
                ];
            }, $units);

            $displayQty = count($formattedUnits);
        }

        // Build and return final response
        $response = [
            'equip_id'         => (int)$equip['equip_id'],
            'equip_name'       => $equip['equip_name'],
            'category_name'    => $equip['category_name'], // This now comes from the joined table
            'equip_type'       => $equip['equip_type'],
            'user_admin_id'    => (int)$equip['user_admin_id'],
            'equip_created_at' => $equip['equip_created_at'],
            'is_active'        => (bool)$equip['is_active'],
            'equip_quantity'   => $displayQty,
            'units'            => $formattedUnits,
        ];

        return json_encode([
            'status' => 'success',
            'data'   => $response
        ]);

    } catch (PDOException $e) {
        error_log("Database error in fetchEquipmentById: " . $e->getMessage());
        return json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    } catch (Exception $e) {
        error_log("Error in fetchEquipmentById: " . $e->getMessage());
        return json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}



public function fetchVehicleById($id) {
    $sql = "
        SELECT 
            v.vehicle_id,
            v.vehicle_license,
            v.year,
            v.vehicle_pic,

            -- Names only
            vmd.vehicle_model_name,
            vmk.vehicle_make_name,
            vc.vehicle_category_name,
            sa.status_availability_name

        FROM tbl_vehicle v
        JOIN tbl_vehicle_model vmd 
            ON v.vehicle_model_id = vmd.vehicle_model_id
        JOIN tbl_vehicle_make vmk 
            ON vmd.vehicle_model_vehicle_make_id = vmk.vehicle_make_id
        JOIN tbl_vehicle_category vc 
            ON vmd.vehicle_category_id = vc.vehicle_category_id
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

    public function fetchMake() {
        $sql = "SELECT vehicle_make_id, vehicle_make_name FROM tbl_vehicle_make ORDER BY vehicle_make_name";
        return $this->executeQuery($sql);
    }

    public function fetchAllVehicles() {
    $sql = "
        SELECT  
            v.vehicle_id,
            v.vehicle_license,
            v.year,
            v.vehicle_pic,
            v.is_active,
            v.created_at,

            -- Only names
            vmk.vehicle_make_name,
            vmd.vehicle_model_name,
            vc.vehicle_category_name,
            sa.status_availability_name

        FROM tbl_vehicle v
        INNER JOIN tbl_vehicle_model vmd 
            ON v.vehicle_model_id = vmd.vehicle_model_id
        INNER JOIN tbl_vehicle_make vmk 
            ON vmd.vehicle_model_vehicle_make_id = vmk.vehicle_make_id
        INNER JOIN tbl_vehicle_category vc 
            ON vmd.vehicle_category_id = vc.vehicle_category_id
        INNER JOIN tbl_status_availability sa 
            ON v.status_availability_id = sa.status_availability_id
        WHERE v.is_active = 1
    ";

    return $this->executeQuery($sql);
}
    
}

// Handle incoming requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' || $_SERVER['REQUEST_METHOD'] === 'GET') {

    $vehicleMake = new VehicleMake();

    switch ($operation) {
        case "fetchAllVehicles":
            echo $vehicleMake->fetchAllVehicles(); // Fetch all vehicles
            break;
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
        
        case "fetchEquipmentById":
            $equipmentId = $input['id'] ?? ($_POST['id'] ?? null);
            $type = $input['type'] ?? ($_POST['type'] ?? 'Consumable'); // Default to 'consumable'
            if ($equipmentId) {
                echo $vehicleMake->fetchEquipmentById($equipmentId, $type); // Fetch equipment by ID
            } else {
                echo json_encode(['status' => 'error', 'message' => 'ID parameter is missing.']);
            }
            break;
        
        case "fetchMake":
            echo $vehicleMake->fetchMake(); // Fetch all vehicle makes
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
        case "fetchModelsByCategoryAndMake":
            $categoryId = $input['categoryId'] ?? ($_POST['categoryId'] ?? null);
            $makeId = $input['makeId'] ?? ($_POST['makeId'] ?? null);
            if ($categoryId && $makeId) {
                echo $vehicleMake->fetchModelsByCategoryAndMake($categoryId, $makeId);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Category ID and Make ID parameters are required.']);
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
