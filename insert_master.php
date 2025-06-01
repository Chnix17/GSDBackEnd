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

    public function saveVehicle($data) {
        try {
            // If data is a JSON string, decode it
            if (is_string($data)) {
                $data = json_decode($data, true);
            }

            // Validate decoded data
            if (!$data || !is_array($data)) {
                return json_encode(['status' => 'error', 'message' => 'Invalid input data']);
            }

            // Required fields check
            $requiredFields = ['vehicle_model_id', 'vehicle_license', 'year', 'user_admin_id'];
            foreach ($requiredFields as $field) {
                if (!isset($data[$field]) || trim($data[$field]) === '') {
                    return json_encode([
                        'status' => 'error',
                        'message' => ucfirst(str_replace('_', ' ', $field)) . ' is required'
                    ]);
                }
            }

            // Handle vehicle picture upload
            $picPath = null;
            if (isset($data['vehicle_pic']) && !empty($data['vehicle_pic'])) {
                $uploadDir = 'uploads/vehicles/';
                if (!file_exists($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }
                
                $image_parts = explode(";base64,", $data['vehicle_pic']);
                $image_type_aux = explode("image/", $image_parts[0]);
                $image_type = $image_type_aux[1];
                $image_base64 = base64_decode($image_parts[1]);
                
                $filename = 'vehicle_' . time() . '.' . $image_type;
                $picPath = $uploadDir . $filename;
                
                file_put_contents($picPath, $image_base64);
            }
    
            // Insert vehicle into tbl_vehicle
            $sql = "INSERT INTO tbl_vehicle (
                vehicle_model_id, 
                vehicle_license, 
                year,
                status_availability_id,
                vehicle_pic,
                user_admin_id,
                is_active,
                created_at,
                updated_at
            ) VALUES (
                :modelId,
                :license,
                :year,
                1,
                :pic,
                :adminId,
                1,
                NOW(),
                NOW()
            )";

            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':modelId', $data['vehicle_model_id']);
            $stmt->bindParam(':license', $data['vehicle_license']);
            $stmt->bindParam(':year', $data['year']);
            $stmt->bindParam(':pic', $picPath);
            $stmt->bindParam(':adminId', $data['user_admin_id']);
    
            if ($stmt->execute()) {
                return json_encode([
                    'status' => 'success', 
                    'message' => 'Vehicle added successfully.'
                ]);
            }
    
            return json_encode(['status' => 'error', 'message' => 'Failed to add vehicle']);
        } catch(PDOException $e) {
            error_log("Database error in saveVehicle: " . $e->getMessage());
            return json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }

    public function saveEquipment($json) {
    try {
        $data = is_array($json) ? $json : json_decode($json, true);

        if (!$data || !is_array($data)) {
            return json_encode(['status' => 'error', 'message' => 'Invalid input data']);
        }

        // Validate required fields
        // Changed 'category_name' to 'equipments_category_id'
        $requiredFields = ['name', 'equipments_category_id', 'equip_type', 'user_admin_id'];
        $missingFields = array_filter($requiredFields, function ($field) use ($data) {
            // Check for existence and non-empty string/non-null for ID fields
            if (in_array($field, ['equipments_category_id', 'user_admin_id'])) {
                return !isset($data[$field]) || !is_numeric($data[$field]);
            }
            return !isset($data[$field]) || trim($data[$field]) === '';
        });

        if (!empty($missingFields)) {
            return json_encode(['status' => 'error', 'message' => 'Required fields missing or invalid: ' . implode(', ', $missingFields)]);
        }

        // Check for existing equipment (case-insensitive match)
        $checkSql = "SELECT equip_id FROM tbl_equipments WHERE LOWER(equip_name) = LOWER(:name)";
        $checkStmt = $this->conn->prepare($checkSql);
        $checkStmt->bindParam(':name', $data['name'], PDO::PARAM_STR);
        $checkStmt->execute();

        if ($checkStmt->rowCount() > 0) {
            return json_encode(['status' => 'error', 'message' => 'Equipment already exists']);
        }

        // Insert new equipment
         $sql = "INSERT INTO tbl_equipments (
                            equip_name,
                            equipments_category_id,
                            equip_type,
                            is_active,
                            user_admin_id,
                            equip_created_at
                        ) VALUES (
                            :name,
                            :equipments_category_id,
                            :equip_type,
                            1,
                            :user_admin_id,
                            NOW()
                        )";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':name', $data['name'], PDO::PARAM_STR);
        $stmt->bindParam(':equipments_category_id', $data['equipments_category_id'], PDO::PARAM_INT);
        $stmt->bindParam(':equip_type', $data['equip_type'], PDO::PARAM_STR);
        $stmt->bindParam(':user_admin_id', $data['user_admin_id'], PDO::PARAM_INT);

        if ($stmt->execute()) {
            return json_encode([
                'status' => 'success',
                'message' => 'Equipment added successfully',
                'equip_id' => $this->conn->lastInsertId()
            ]);
        } else {
            return json_encode(['status' => 'error', 'message' => 'Failed to insert equipment']);
        }

    } catch (PDOException $e) {
        error_log("Database error in saveEquipment: " . $e->getMessage());
        return json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    } catch (Exception $e) {
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



public function saveUnit($data) {
    try {
        // If data is a JSON string, decode it
        if (is_string($data)) {
            $data = json_decode($data, true);
        }

        // Validate decoded data
        if (!$data || !is_array($data)) {
            return json_encode(['status' => 'error', 'message' => 'Invalid input data']);
        }

        // Required fields check
        $requiredFields = ['equip_id', 'serial_number', 'status_availability_id', 'user_admin_id'];
        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || trim($data[$field]) === '') {
                return json_encode([
                    'status' => 'error', 
                    'message' => ucfirst(str_replace('_', ' ', $field)) . ' is required'
                ]);
            }
        }

        // Check for duplicate serial number
        $checkSql = "SELECT COUNT(*) FROM tbl_equipment_unit WHERE serial_number = :serial_number";
        $checkStmt = $this->conn->prepare($checkSql);
        $checkStmt->bindParam(':serial_number', $data['serial_number']);
        $checkStmt->execute();
        
        if ($checkStmt->fetchColumn() > 0) {
            return json_encode(['status' => 'error', 'message' => 'This serial number already exists']);
        }

        // Prepare optional values
        $brand = isset($data['brand']) ? $data['brand'] : null;
        $size = isset($data['size']) ? $data['size'] : null;
        $color = isset($data['color']) ? $data['color'] : null;

        // Insert query
        $sql = "INSERT INTO tbl_equipment_unit (
            equip_id, serial_number, brand, size, color, status_availability_id, 
            unit_created_at, is_active, user_admin_id
        ) VALUES (
            :equip_id, :serial_number, :brand, :size, :color, :status_availability_id,
            NOW(), 1, :user_admin_id
        )";

        $stmt = $this->conn->prepare($sql);
        
        // Bind parameters
        $stmt->bindParam(':equip_id', $data['equip_id'], PDO::PARAM_INT);
        $stmt->bindParam(':serial_number', $data['serial_number'], PDO::PARAM_STR);
        $stmt->bindParam(':brand', $brand, PDO::PARAM_STR);
        $stmt->bindParam(':size', $size, PDO::PARAM_STR);
        $stmt->bindParam(':color', $color, PDO::PARAM_STR);        
        $stmt->bindParam(':status_availability_id', $data['status_availability_id'], PDO::PARAM_INT);
        $stmt->bindParam(':user_admin_id', $data['user_admin_id'], PDO::PARAM_INT);       
         if ($stmt->execute()) {
            return json_encode([
                'status' => 'success',
                'message' => 'Equipment unit added successfully'
            ]);
        }

        return json_encode(['status' => 'error', 'message' => 'Failed to add equipment unit']);

    } catch(PDOException $e) {
        error_log("Database error in saveUnit: " . $e->getMessage());
        return json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    } catch(Exception $e) {
        error_log("Error in saveUnit: " . $e->getMessage());
        return json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}

public function saveStock($data) {
    try {
        if (is_string($data)) {
            $data = json_decode($data, true);
        }

        if (!$data || !is_array($data)) {
            return json_encode(['status' => 'error', 'message' => 'Invalid input data']);
        }

        $requiredFields = ['equip_id', 'quantity', 'user_admin_id'];
        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || trim($data[$field]) === '') {
                return json_encode([
                    'status' => 'error', 
                    'message' => ucfirst(str_replace('_', ' ', $field)) . ' is required'
                ]);
            }
        }

        $equip_id = (int)$data['equip_id'];
        $inputQuantity = (int)$data['quantity'];
        $user_admin_id = (int)$data['user_admin_id'];

        // Check current stock entry
        $checkSql = "SELECT quantity, on_hand_quantity FROM tbl_equipment_quantity WHERE equip_id = :equip_id";
        $checkStmt = $this->conn->prepare($checkSql);
        $checkStmt->bindParam(':equip_id', $equip_id, PDO::PARAM_INT);
        $checkStmt->execute();

        if ($checkStmt->rowCount() > 0) {
            $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);
            $currentQuantity = (int)$existing['quantity'];
            $currentOnHand = (int)$existing['on_hand_quantity'];

            if ($inputQuantity < $currentQuantity) {
                // Subtract difference
                $diff = $currentQuantity - $inputQuantity;

                $sql = "UPDATE tbl_equipment_quantity SET 
                            quantity = quantity - :diff,
                            on_hand_quantity = on_hand_quantity - :diff,
                            last_updated = NOW(),
                            user_admin_id = :user_admin_id
                        WHERE equip_id = :equip_id";

                $stmt = $this->conn->prepare($sql);
                $stmt->bindParam(':diff', $diff, PDO::PARAM_INT);
                $stmt->bindParam(':user_admin_id', $user_admin_id, PDO::PARAM_INT);
                $stmt->bindParam(':equip_id', $equip_id, PDO::PARAM_INT);

                if ($stmt->execute()) {
                    return json_encode([
                        'status' => 'success',
                        'message' => "Stock decreased by $diff successfully"
                    ]);
                }
                return json_encode(['status' => 'error', 'message' => 'Failed to update stock (decrease)']);
            } elseif ($inputQuantity > $currentQuantity) {
                // Add difference
                $diff = $inputQuantity - $currentQuantity;

                $sql = "UPDATE tbl_equipment_quantity SET 
                            quantity = quantity + :diff,
                            on_hand_quantity = on_hand_quantity + :diff,
                            last_updated = NOW(),
                            user_admin_id = :user_admin_id
                        WHERE equip_id = :equip_id";

                $stmt = $this->conn->prepare($sql);
                $stmt->bindParam(':diff', $diff, PDO::PARAM_INT);
                $stmt->bindParam(':user_admin_id', $user_admin_id, PDO::PARAM_INT);
                $stmt->bindParam(':equip_id', $equip_id, PDO::PARAM_INT);

                if ($stmt->execute()) {
                    return json_encode([
                        'status' => 'success',
                        'message' => "Stock increased by $diff successfully"
                    ]);
                }
                return json_encode(['status' => 'error', 'message' => 'Failed to update stock (increase)']);
            } else {
                // Quantity is the same, update timestamp and user only
                $sql = "UPDATE tbl_equipment_quantity SET 
                            last_updated = NOW(),
                            user_admin_id = :user_admin_id
                        WHERE equip_id = :equip_id";

                $stmt = $this->conn->prepare($sql);
                $stmt->bindParam(':user_admin_id', $user_admin_id, PDO::PARAM_INT);
                $stmt->bindParam(':equip_id', $equip_id, PDO::PARAM_INT);

                if ($stmt->execute()) {
                    return json_encode([
                        'status' => 'success',
                        'message' => 'Stock unchanged, updated timestamp'
                    ]);
                }
                return json_encode(['status' => 'error', 'message' => 'Failed to update timestamp']);
            }
        } else {
            // Insert new stock record
            $sql = "INSERT INTO tbl_equipment_quantity (
                        equip_id, quantity, on_hand_quantity, last_updated, user_admin_id
                    ) VALUES (
                        :equip_id, :quantity, :quantity, NOW(), :user_admin_id
                    )";

            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':equip_id', $equip_id, PDO::PARAM_INT);
            $stmt->bindParam(':quantity', $inputQuantity, PDO::PARAM_INT);
            $stmt->bindParam(':user_admin_id', $user_admin_id, PDO::PARAM_INT);

            if ($stmt->execute()) {
                return json_encode([
                    'status' => 'success',
                    'message' => 'Equipment stock added successfully'
                ]);
            }
            return json_encode(['status' => 'error', 'message' => 'Failed to add equipment stock']);
        }
    } catch(PDOException $e) {
        return json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    } catch(Exception $e) {
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
            case "saveUnit":
                echo $user->saveUnit($data);
                break;
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
            case "saveStock":
                echo $user->saveStock($data);
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
