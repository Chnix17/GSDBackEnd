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
            
            // Updated SQL statement to include users_birthdate and users_suffix
            $sql = "INSERT INTO tbl_users (users_fname, users_mname, users_lname, 
                    users_email, users_school_id, users_contact_number, users_user_level_id, 
                    users_password, users_department_id, users_birthdate, users_suffix,
                    users_pic, users_created_at, users_updated_at) 
                    VALUES (:fname, :mname, :lname, :email, :schoolId, :contact, :userLevelId, 
                    :password, :departmentId, :birthdate, :suffix,
                    :pic, NOW(), NOW())";
    
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
            $stmt->bindParam(':birthdate', $data['birthdate'], PDO::PARAM_STR);
            $stmt->bindParam(':suffix', $data['suffix'], PDO::PARAM_STR);
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
            if (!isset($data['name']) || !isset($data['occupancy']) || !isset($data['status_availability_id'])) {
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
        $data = json_decode($json, true);
        
        // 1. Extract incoming fields
        $modelName            = trim($data['vehicle_model_name'] ?? '');
        $vehicleLicense       = trim($data['vehicle_license'] ?? '');
        $year                 = trim($data['year'] ?? '');
        $userAdminId          = intval($data['user_admin_id'] ?? 0);
        $statusAvailabilityId = intval($data['status_availability_id'] ?? 0);
        $categoryName         = trim($data['vehicle_category_name'] ?? '');
        $makeName             = trim($data['vehicle_make_name'] ?? '');

        // 2. Validate required
        if (
            !$modelName ||
            !$vehicleLicense ||
            !$year ||
            !$statusAvailabilityId ||
            !$userAdminId ||
            !$categoryName ||
            !$makeName
        ) {
            return json_encode([
                'status'  => 'error',
                'message' => 'All fields (model, license, year, status, admin, category, make) are required.'
            ]);
        }

        try {
            $this->conn->beginTransaction();

            //
            // 3. Ensure vehicle model exists (insert if not)
            //
            $sql = "SELECT vehicle_model_id
                    FROM tbl_vehicle_model
                    WHERE vehicle_model_name = :modelName
                    LIMIT 1";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([':modelName' => $modelName]);
            
            if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $vehicleModelId = $row['vehicle_model_id'];
            } else {
                // Insert new model
                $sql = "INSERT INTO tbl_vehicle_model
                            (vehicle_model_name, vehicle_model_created_at, vehicle_model_updated_at)
                        VALUES
                            (:modelName, NOW(), NOW())";
                $stmt = $this->conn->prepare($sql);
                $stmt->execute([':modelName' => $modelName]);
                $vehicleModelId = $this->conn->lastInsertId();
            }

            //
            // 4. Check duplicate vehicle_license
            //
            $sql = "SELECT COUNT(*) FROM tbl_vehicle WHERE vehicle_license = :license";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([':license' => $vehicleLicense]);
            if ($stmt->fetchColumn() > 0) {
                $this->conn->rollBack();
                return json_encode([
                    'status'  => 'error',
                    'message' => 'This vehicle license is already in use.'
                ]);
            }

            //
            // 5. Handle picture upload (if any)
            //
            $picPath = null;
            if (!empty($data['vehicle_pic'])) {
                $uploadDir = 'uploads/vehicles/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }
                list($meta, $b64) = explode(';base64,', $data['vehicle_pic']);
                $ext = str_replace('data:image/', '', $meta);
                $filename = 'vehicle_' . time() . '.' . $ext;
                $picPath  = $uploadDir . $filename;
                file_put_contents($picPath, base64_decode($b64));
            }

            //
            // 6. Insert into tbl_vehicle (including new category & make columns)
            //
            $sql = "INSERT INTO tbl_vehicle
                        (
                            vehicle_model_id,
                            vehicle_license,
                            year,
                            status_availability_id,
                            vehicle_pic,
                            user_admin_id,
                            is_active,
                            vehicle_category_name,
                            vehicle_make_name
                        )
                    VALUES
                        (
                            :modelId,
                            :license,
                            :year,
                            :statusId,
                            :pic,
                            :adminId,
                            1,
                            :category,
                            :make
                        )";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([
                ':modelId'  => $vehicleModelId,
                ':license'  => $vehicleLicense,
                ':year'     => $year,
                ':statusId' => $statusAvailabilityId,
                ':pic'      => $picPath,
                ':adminId'  => $userAdminId,
                ':category' => $categoryName,
                ':make'     => $makeName,
            ]);

            $this->conn->commit();
            return json_encode([
                'status'  => 'success',
                'message' => 'Vehicle added successfully.',
            ]);

        } catch (PDOException $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            return json_encode([
                'status'  => 'error',
                'message' => $e->getMessage()
            ]);
        }
    }

    public function saveEquipment($json) {
        try {
            // If json is already an array, use it directly, otherwise decode it
            $data = is_array($json) ? $json : json_decode($json, true);

            if (!$data || !is_array($data)) {
                return json_encode(['status' => 'error', 'message' => 'Invalid input data']);
            }

            // Check if this is an update to existing equipment
            $isUpdate = isset($data['equip_id']) && !empty($data['equip_id']);

            if ($isUpdate) {
                // Validate equipment exists
                $checkSql = "SELECT equip_id FROM tbl_equipments WHERE equip_id = :equip_id";
                $checkStmt = $this->conn->prepare($checkSql);
                $checkStmt->bindParam(':equip_id', $data['equip_id']);
                $checkStmt->execute();
                
                if ($checkStmt->rowCount() === 0) {
                    return json_encode(['status' => 'error', 'message' => 'Equipment not found']);
                }
                
                $equipmentId = $data['equip_id'];
            } else {
                // For new equipment, validate required fields
                $requiredFields = ['name', 'category_name', 'user_admin_id'];
                $missingFields = array_filter($requiredFields, function($field) use ($data) {
                    return !isset($data[$field]) || $data[$field] === '';
                });

                if (!empty($missingFields)) {
                    return json_encode(['status' => 'error', 'message' => 'Required fields missing: ' . implode(', ', $missingFields)]);
                }
            }

            // Handle equipment picture upload for new equipment
            $picPath = null;
            if (!$isUpdate && isset($data['equip_pic']) && !empty($data['equip_pic'])) {
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

            $this->conn->beginTransaction();

            try {
                if (!$isUpdate) {
                    // Insert new equipment
                    $sql = "INSERT INTO tbl_equipments (
                        equip_name, 
                        equip_created_at,
                        category_name,
                        equip_pic,
                        is_active,
                        user_admin_id
                    ) VALUES (
                        :name, 
                        NOW(), 
                        :category_name,
                        :pic,
                        1,
                        :user_admin_id
                    )";
                    
                    $stmt = $this->conn->prepare($sql);

                    $stmt->bindParam(':name', $data['name'], PDO::PARAM_STR);
                    $stmt->bindParam(':category_name', $data['category_name'], PDO::PARAM_STR);
                    $stmt->bindParam(':pic', $picPath, PDO::PARAM_STR);
                    $stmt->bindParam(':user_admin_id', $data['user_admin_id'], PDO::PARAM_INT);

                    if (!$stmt->execute()) {
                        throw new Exception('Failed to add equipment');
                    }

                    $equipmentId = $this->conn->lastInsertId();
                }

                // Determine if this is serial-based or quantity-based equipment
                $isSerialBased = isset($data['serial_numbers']) && is_array($data['serial_numbers']) && !empty($data['serial_numbers']);
                $hasQuantity = isset($data['quantity']) && $data['quantity'] > 0;

                if ($isSerialBased) {
                    // Handle serial-based equipment
                    // Check for duplicate serial numbers
                    $duplicateSerials = [];
                    $checkSerialSql = "SELECT serial_number FROM tbl_equipment_unit WHERE serial_number = :serial";
                    $checkSerialStmt = $this->conn->prepare($checkSerialSql);

                    foreach ($data['serial_numbers'] as $serial) {
                        $checkSerialStmt->bindParam(':serial', $serial);
                        $checkSerialStmt->execute();
                        if ($checkSerialStmt->rowCount() > 0) {
                            $duplicateSerials[] = $serial;
                        }
                    }

                    if (!empty($duplicateSerials)) {
                        throw new Exception('Duplicate serial numbers found: ' . implode(', ', $duplicateSerials));
                    }

                    // Insert serial numbers
                    $unitSql = "INSERT INTO tbl_equipment_unit (
                        equip_id,
                        serial_number,
                        quantity,
                        status_availability_id,
                        unit_created_at,
                        is_active,
                        user_admin_id
                    ) VALUES (
                        :equip_id,
                        :serial_number,
                        NULL,
                        :status_availability_id,
                        NOW(),
                        1,
                        :user_admin_id
                    )";

                    $unitStmt = $this->conn->prepare($unitSql);
                    $successCount = 0;

                    foreach ($data['serial_numbers'] as $serialNumber) {
                        $statusAvailabilityId = isset($data['status_availability_id']) ? $data['status_availability_id'] : 1;
                        
                        $unitStmt->bindParam(':equip_id', $equipmentId, PDO::PARAM_INT);
                        $unitStmt->bindParam(':serial_number', $serialNumber, PDO::PARAM_STR);
                        $unitStmt->bindParam(':status_availability_id', $statusAvailabilityId, PDO::PARAM_INT);
                        $unitStmt->bindParam(':user_admin_id', $data['user_admin_id'], PDO::PARAM_INT);
                        
                        if ($unitStmt->execute()) {
                            $successCount++;
                        }
                    }
                } elseif ($hasQuantity) {
                    // Handle quantity-based equipment
                    $unitSql = "INSERT INTO tbl_equipment_unit (
                        equip_id,
                        serial_number,
                        quantity,
                        status_availability_id,
                        unit_created_at,
                        is_active,
                        user_admin_id
                    ) VALUES (
                        :equip_id,
                        NULL,
                        :quantity,
                        :status_availability_id,
                        NOW(),
                        1,
                        :user_admin_id
                    )";

                    $unitStmt = $this->conn->prepare($unitSql);
                    $statusAvailabilityId = isset($data['status_availability_id']) ? $data['status_availability_id'] : 1;
                    
                    $unitStmt->bindParam(':equip_id', $equipmentId, PDO::PARAM_INT);
                    $unitStmt->bindParam(':quantity', $data['quantity'], PDO::PARAM_INT);
                    $unitStmt->bindParam(':status_availability_id', $statusAvailabilityId, PDO::PARAM_INT);
                    $unitStmt->bindParam(':user_admin_id', $data['user_admin_id'], PDO::PARAM_INT);
                    
                    if (!$unitStmt->execute()) {
                        throw new Exception('Failed to add equipment quantity');
                    }
                } else {
                    throw new Exception('Either serial numbers or quantity must be provided');
                }

                $this->conn->commit();
                return json_encode([
                    'status' => 'success',
                    'message' => $isUpdate ? 'Equipment units added successfully' : 'Equipment added successfully',
                    'equip_id' => $equipmentId
                ]);

            } catch (Exception $e) {
                $this->conn->rollBack();
                throw $e;
            }

        } catch(PDOException $e) {
            error_log("Database error in saveEquipment: " . $e->getMessage());
            return json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        } catch(Exception $e) {
            error_log("Error in saveEquipment: " . $e->getMessage());
            return json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }

    public function saveDriver($json) {
    try {
        // If json is already an array, use it directly, otherwise decode it
        $data = is_array($json) ? $json : json_decode($json, true);

        if (!$data || !is_array($data)) {
            return json_encode(['status' => 'error', 'message' => 'Invalid input data']);
        }

        // Required fields check
        $requiredFields = [
            'driver_first_name',
            'driver_last_name',
            'driver_contact_number',
            'driver_address',
            'user_admin_id',
            'employee_id',
            'driver_birthdate'
        ];

        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || trim($data[$field]) === '') {
                return json_encode([
                    'status' => 'error',
                    'message' => ucwords(str_replace('_', ' ', $field)) . ' is required'
                ]);
            }
        }

        // Check for duplicate driver
        $checkSql = "SELECT COUNT(*) FROM tbl_driver 
                    WHERE (driver_first_name = :firstName 
                    AND driver_last_name = :lastName)
                    OR employee_id = :employeeId";
        $checkStmt = $this->conn->prepare($checkSql);

        $firstNameCheck = $data['driver_first_name'];
        $lastNameCheck = $data['driver_last_name'];
        $employeeIdCheck = $data['employee_id'];
        $checkStmt->bindParam(':firstName', $firstNameCheck);
        $checkStmt->bindParam(':lastName', $lastNameCheck);
        $checkStmt->bindParam(':employeeId', $employeeIdCheck);
        $checkStmt->execute();

        if ($checkStmt->fetchColumn() > 0) {
            return json_encode(['status' => 'error', 'message' => 'This driver already exists or the employee ID is already in use']);
        }

        // Prepare variables
        $firstName     = $data['driver_first_name'];
        $middleName    = $data['driver_middle_name'] ?? null;
        $lastName      = $data['driver_last_name'];
        $suffix        = $data['driver_suffix'] ?? null;
        $birthdate     = $data['driver_birthdate'];
        $contactNumber = $data['driver_contact_number'];
        $address       = $data['driver_address'];
        $adminId       = $data['user_admin_id'];
        $employeeId    = $data['employee_id'];
        $isActive      = 1;

        // Insert query
        $sql = "INSERT INTO tbl_driver (
            driver_first_name,
            driver_middle_name,
            driver_last_name,
            driver_suffix,
            driver_birthdate,
            driver_contact_number,
            driver_address,
            employee_id,
            created_at,
            updated_at,
            is_active,
            user_admin_id
        ) VALUES (
            :firstName,
            :middleName,
            :lastName,
            :suffix,
            :birthdate,
            :contactNumber,
            :address,
            :employeeId,
            NOW(),
            NOW(),
            :isActive,
            :adminId
        )";

        $stmt = $this->conn->prepare($sql);

        // Bind values
        $stmt->bindParam(':firstName', $firstName, PDO::PARAM_STR);
        $stmt->bindParam(':middleName', $middleName, PDO::PARAM_STR);
        $stmt->bindParam(':lastName', $lastName, PDO::PARAM_STR);
        $stmt->bindParam(':suffix', $suffix, PDO::PARAM_STR);
        $stmt->bindParam(':birthdate', $birthdate, PDO::PARAM_STR);
        $stmt->bindParam(':contactNumber', $contactNumber, PDO::PARAM_STR);
        $stmt->bindParam(':address', $address, PDO::PARAM_STR);
        $stmt->bindParam(':employeeId', $employeeId, PDO::PARAM_STR);
        $stmt->bindParam(':isActive', $isActive, PDO::PARAM_INT);
        $stmt->bindParam(':adminId', $adminId, PDO::PARAM_INT);

        if ($stmt->execute()) {
            return json_encode([
                'status' => 'success',
                'message' => 'Driver added successfully'
            ]);
        }

        return json_encode(['status' => 'error', 'message' => 'Failed to add driver']);
    } catch (PDOException $e) {
        error_log("Database error in saveDriver: " . $e->getMessage());
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

    public function saveEquipmentUnit($json) {
        try {
            // If json is already an array, use it directly, otherwise decode it
            $data = is_array($json) ? $json : json_decode($json, true);

            if (!$data || !is_array($data)) {
                return json_encode(['status' => 'error', 'message' => 'Invalid input data']);
            }

            // Required fields check
            $requiredFields = ['equip_id', 'serial_numbers', 'status_availability_id'];
            foreach ($requiredFields as $field) {
                if (!isset($data[$field])) {
                    return json_encode(['status' => 'error', 'message' => ucfirst(str_replace('_', ' ', $field)) . ' is required']);
                }
            }

            // Ensure serial numbers is an array
            if (!is_array($data['serial_numbers'])) {
                return json_encode(['status' => 'error', 'message' => 'Serial numbers must be provided as an array']);
            }

            // Validate equipment ID exists
            $checkEquipSql = "SELECT COUNT(*) FROM tbl_equipments WHERE equip_id = :equip_id";
            $checkEquipStmt = $this->conn->prepare($checkEquipSql);
            $checkEquipStmt->bindParam(':equip_id', $data['equip_id']);
            $checkEquipStmt->execute();
            if ($checkEquipStmt->fetchColumn() == 0) {
                return json_encode(['status' => 'error', 'message' => 'Invalid equipment ID']);
            }

            // Begin transaction
            $this->conn->beginTransaction();

            try {
                // Check for duplicate serial numbers
                $duplicateSerials = [];
                $checkSerialSql = "SELECT serial_number FROM tbl_equipment_unit WHERE serial_number = :serial";
                $checkSerialStmt = $this->conn->prepare($checkSerialSql);

                foreach ($data['serial_numbers'] as $serial) {
                    $checkSerialStmt->bindParam(':serial', $serial);
                    $checkSerialStmt->execute();
                    if ($checkSerialStmt->rowCount() > 0) {
                        $duplicateSerials[] = $serial;
                    }
                }

                if (!empty($duplicateSerials)) {
                    throw new Exception('Duplicate serial numbers found: ' . implode(', ', $duplicateSerials));
                }

                // Prepare insert statement
                $insertSql = "INSERT INTO tbl_equipment_unit (
                    equip_id,
                    serial_number,
                    status_availability_id,
                    unit_created_at
                ) VALUES (
                    :equip_id,
                    :serial_number,
                    :status_availability_id,
                    NOW()
                )";

                $insertStmt = $this->conn->prepare($insertSql);
                $successCount = 0;
                $errors = [];

                foreach ($data['serial_numbers'] as $serialNumber) {
                    $insertStmt->bindParam(':equip_id', $data['equip_id'], PDO::PARAM_INT);
                    $insertStmt->bindParam(':serial_number', $serialNumber, PDO::PARAM_STR);
                    $insertStmt->bindParam(':status_availability_id', $data['status_availability_id'], PDO::PARAM_INT);

                    if ($insertStmt->execute()) {
                        $successCount++;
                    } else {
                        $errors[] = "Failed to add unit with serial number: $serialNumber";
                    }
                }

                // Update equipment quantity in tbl_equipments
                $updateQuantitySql = "UPDATE tbl_equipments 
                                    SET equip_quantity = equip_quantity + :added_quantity 
                                    WHERE equip_id = :equip_id";
                $updateQuantityStmt = $this->conn->prepare($updateQuantitySql);
                $updateQuantityStmt->bindParam(':added_quantity', $successCount, PDO::PARAM_INT);
                $updateQuantityStmt->bindParam(':equip_id', $data['equip_id'], PDO::PARAM_INT);
                $updateQuantityStmt->execute();

                $this->conn->commit();

                if ($successCount > 0) {
                    $message = "$successCount equipment unit(s) added successfully";
                    if (!empty($errors)) {
                        $message .= ". Some errors occurred: " . implode(", ", $errors);
                    }
                    return json_encode(['status' => 'success', 'message' => $message]);
                } else {
                    throw new Exception('Failed to add any equipment units');
                }

            } catch (Exception $e) {
                $this->conn->rollBack();
                throw $e;
            }

        } catch(PDOException $e) {
            error_log("Database error in saveEquipmentUnit: " . $e->getMessage());
            return json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        } catch(Exception $e) {
            error_log("Error in saveEquipmentUnit: " . $e->getMessage());
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
            case "saveEquipmentUnit":
                echo $user->saveEquipmentUnit(json_encode($data));
                break;
            case "saveDriver":
                echo $user->saveDriver($data);
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
