<?php
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// At the beginning of your script
$jsonInput = json_decode(file_get_contents('php://input'), true);

// Add this debugging output
error_log('Received JSON: ' . print_r($jsonInput, true));

class User {
    private $conn;

    public function __construct() {
        include 'connection-pdo.php'; 
        $this->conn = $conn;
    }

    public function getUserDetails($userId) {
        $sql = "SELECT  
                    u.users_name, 
                    u.users_school_id, 
                    u.users_contact_number,  
                    u.users_username, 
                    u.users_password, 
                    d.departments_name
                FROM 
                    tbl_users u
                INNER JOIN 
                    tbl_departments d ON u.users_department_id = d.departments_id
                WHERE 
                    u.users_id = :userId";
    
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
    
        if ($stmt->execute()) {
            $userDetails = $stmt->fetch(PDO::FETCH_ASSOC);
            return $userDetails ? json_encode(['status' => 'success', 'data' => $userDetails]) 
                                : json_encode(['status' => 'error', 'message' => 'User not found']);
        } 
        return json_encode(['status' => 'error', 'message' => 'Could not fetch user details']);
    }

   public function updateUser($userData) {
    // Build the SQL query dynamically based on whether password is provided
    $sql = "UPDATE tbl_users SET 
                title_id = :title_id,
                users_fname = :fname,
                users_mname = :mname,
                users_lname = :lname,
                users_birthdate = :birthdate,
                users_suffix = :suffix,
                users_email = :email,
                users_school_id = :schoolId,
                users_contact_number = :contact,
                users_user_level_id = :userLevelId,
                users_department_id = :departmentId,
                users_pic = :pic,
                users_updated_at = NOW(),
                is_active = :isActive";

    // Only include password in update if it's provided
    if (isset($userData['password']) && !empty($userData['password'])) {
        $sql .= ", users_password = :password";
    }

    $sql .= " WHERE users_id = :userId";

    $stmt = $this->conn->prepare($sql);

    // Bind all parameters
    $stmt->bindParam(':title_id', $userData['title_id'], PDO::PARAM_INT);
    $stmt->bindParam(':fname', $userData['fname']);
    $stmt->bindParam(':mname', $userData['mname']);
    $stmt->bindParam(':lname', $userData['lname']);
    $stmt->bindParam(':birthdate', $userData['birthdate']);
    $stmt->bindParam(':suffix', $userData['suffix']);
    $stmt->bindParam(':email', $userData['email']);
    $stmt->bindParam(':schoolId', $userData['schoolId']);
    $stmt->bindParam(':contact', $userData['contact']);
    $stmt->bindParam(':userLevelId', $userData['userLevelId']);
    $stmt->bindParam(':departmentId', $userData['departmentId']);
    $stmt->bindParam(':pic', $userData['pic']);
    $stmt->bindParam(':isActive', $userData['isActive'], PDO::PARAM_BOOL);
    $stmt->bindParam(':userId', $userData['userId'], PDO::PARAM_INT);

    // Hash and bind password only if provided
    if (isset($userData['password']) && !empty($userData['password'])) {
        $hashedPassword = password_hash($userData['password'], PASSWORD_DEFAULT);
        $stmt->bindParam(':password', $hashedPassword);
    }

    return $stmt->execute()
        ? json_encode(['status' => 'success', 'message' => 'User updated successfully'])
        : json_encode(['status' => 'error', 'message' => 'Could not update user']);
}



    public function getPersonnelDetails($personId) {
        $sql = "SELECT  
                    p.jo_personel_lname, 
                    p.jo_personel_fname, 
                    p.jo_personel_contact, 
                    p.username, 
                    p.password, 
                    ul.user_level_name, 
                    pp.position_name 
                FROM 
                    tbl_personel p
                INNER JOIN 
                    tbl_personnel_position pp ON p.jo_personel_position_id = pp.position_id
                INNER JOIN 
                    tbl_user_level ul ON p.jo_user_level_id = ul.user_level_id
                WHERE 
                    p.jo_personel_id = :personId";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':personId', $personId, PDO::PARAM_INT);
        
        if ($stmt->execute()) {
            $personnelDetails = $stmt->fetch(PDO::FETCH_ASSOC);
            return $personnelDetails ? json_encode(['status' => 'success', 'data' => $personnelDetails]) 
                                     : json_encode(['status' => 'error', 'message' => 'Personnel not found']);
        } 
        return json_encode(['status' => 'error', 'message' => 'Could not fetch personnel details']);
    }



    public function updateVehicle($id, $vehicleModelId, $vehicleLicense) {
        $sql = "UPDATE tbl_vehicle 
                SET vehicle_model_id = :vehicleModelId, vehicle_license = :vehicleLicense 
                WHERE vehicle_id = :id";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':vehicleModelId', $vehicleModelId, PDO::PARAM_INT);
        $stmt->bindParam(':vehicleLicense', $vehicleLicense);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        
        if ($stmt->execute()) {
            return json_encode(['status' => 'success', 'message' => 'Vehicle updated successfully']);
        } else {
            return json_encode(['status' => 'error', 'message' => 'Could not update vehicle']);
        }
    }
    

    public function updatePosition($id, $name) {
        try {
            $sql = "UPDATE tbl_personnel_position SET position_name = :name WHERE position_id = :id";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':name', $name);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            
            // Add these lines for debugging
            error_log("Updating position: ID = $id, Name = $name");
            $result = $stmt->execute();
            error_log("Update result: " . ($result ? "true" : "false"));
            error_log("Rows affected: " . $stmt->rowCount());

            if ($result) {
                // Check the current value after update
                $checkSql = "SELECT position_name FROM tbl_personnel_position WHERE position_id = :id";
                $checkStmt = $this->conn->prepare($checkSql);
                $checkStmt->bindParam(':id', $id, PDO::PARAM_INT);
                $checkStmt->execute();
                $currentValue = $checkStmt->fetchColumn();
                error_log("Current position name after update: " . $currentValue);

                return json_encode(['status' => 'success', 'message' => 'Position updated successfully']);
            } else {
                return json_encode(['status' => 'error', 'message' => 'Could not update position']);
            }
        } catch (PDOException $e) {
            error_log("PDO Exception: " . $e->getMessage());
            return json_encode(['status' => 'error', 'message' => 'Error: ' . $e->getMessage()]);
        }
    }
    

    public function updatePersonnel($personnelData) {
        $sql = "UPDATE tbl_personel SET 
                    jo_personel_lname = :lname, 
                    jo_personel_fname = :fname, 
                    jo_personel_contact = :contact, 
                    username = :username, 
                    password = :password, 
                    jo_user_level_id = :userLevelId, 
                    jo_personel_position_id = :positionId 
                WHERE 
                    jo_personel_id = :personnelId";
    
        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':lname', $personnelData['lname']);
            $stmt->bindParam(':fname', $personnelData['fname']);
            $stmt->bindParam(':contact', $personnelData['contact']);
            $stmt->bindParam(':username', $personnelData['username']);
            $stmt->bindParam(':password', $personnelData['password']);
            $stmt->bindParam(':userLevelId', $personnelData['userLevelId']);
            $stmt->bindParam(':positionId', $personnelData['positionId']);
            $stmt->bindParam(':personnelId', $personnelData['personnelId'], PDO::PARAM_INT);
            
            $stmt->execute();
            return json_encode(['status' => 'success', 'message' => 'Personnel updated successfully']);
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') { // Check for duplicate entry error code
                return json_encode(['status' => 'info', 'message' => 'Duplicate entry ignored.']);
            }
            return json_encode(['status' => 'error', 'message' => 'Could not update personnel: ' . $e->getMessage()]);
        }
    }

    // public function updateVehicleMake($id, $name) {
    //     $sql = "UPDATE tbl_vehicle_make SET vehicle_make_name = :name WHERE vehicle_make_id = :id";
    //     $stmt = $this->conn->prepare($sql);
    //     $stmt->bindParam(':name', $name);
    //     $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        
    //     return $stmt->execute() ? json_encode(['status' => 'success', 'message' => 'Vehicle make updated successfully']) 
    //                              : json_encode(['status' => 'error', 'message' => 'Could not update vehicle make']);
    // }

    // New method to update vehicle category
    // public function updateVehicleCategory($id, $name) {
    //     $sql = "UPDATE tbl_vehicle_category SET vehicle_category_name = :name WHERE vehicle_category_id = :id";
    //     $stmt = $this->conn->prepare($sql);
    //     $stmt->bindParam(':name', $name);
    //     $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        
    //     return $stmt->execute() ? json_encode(['status' => 'success', 'message' => 'Vehicle category updated successfully']) 
    //                              : json_encode(['status' => 'error', 'message' => 'Could not update vehicle category']);
    // }
    // public function updateDepartment($id, $name) {
    //     $sql = "UPDATE tbl_departments SET departments_name = :name WHERE departments_id = :id";
    //     $stmt = $this->conn->prepare($sql);
    //     $stmt->bindParam(':name', $name);
    //     $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        
    //     return $stmt->execute() ? json_encode(['status' => 'success', 'message' => 'Department updated successfully']) 
    //                              : json_encode(['status' => 'error', 'message' => 'Could not update department']);
    // }


    public function updateVenue($venueData) {
        try {
            if (!isset($venueData['venue_id'], $venueData['venue_name'], $venueData['max_occupancy'], $venueData['status_availability_id'])) {
                return json_encode(['status' => 'error', 'message' => 'Missing required fields']);
            }
    
            $sql = "UPDATE tbl_venue SET 
                        ven_name = :venue_name, 
                        ven_occupancy = :max_occupancy,
                        status_availability_id = :status_availability_id
                    WHERE ven_id = :venue_id";
    
            $stmt = $this->conn->prepare($sql);
    
            $stmt->bindParam(':venue_name', $venueData['venue_name']);
            $stmt->bindParam(':max_occupancy', $venueData['max_occupancy']);
            $stmt->bindParam(':status_availability_id', $venueData['status_availability_id'], PDO::PARAM_INT);
            $stmt->bindParam(':venue_id', $venueData['venue_id'], PDO::PARAM_INT);
    
            return $stmt->execute()
                ? json_encode(['status' => 'success', 'message' => 'Venue updated successfully'])
                : json_encode(['status' => 'error', 'message' => 'Could not update venue']);
        } catch (PDOException $e) {
            error_log('Database error in updateVenue: ' . $e->getMessage());
            return json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
        }
    }
    

    public function updateUserLevel($userLevelData) {
        try {
            $sql = "UPDATE tbl_user_level SET 
                        user_level_name = :name, 
                        user_level_desc = :description
                    WHERE 
                        user_level_id = :userLevelId";
    
            $stmt = $this->conn->prepare($sql);
    
            $stmt->bindParam(':name', $userLevelData['name']);
            $stmt->bindParam(':description', $userLevelData['description']);
            $stmt->bindParam(':userLevelId', $userLevelData['userLevelId'], PDO::PARAM_INT);
    
            if ($stmt->execute()) {
                return json_encode(['status' => 'success', 'message' => 'User level updated successfully']);
            } else {
                return json_encode(['status' => 'error', 'message' => 'Could not update user level']);
            }
        } catch (Exception $e) {
            return json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }
    // public function updateEquipmentCategory($categoryData) {
    //     try {
    //         $sql = "UPDATE tbl_equipment_category SET 
    //                     equipments_category_name = :name
    //                 WHERE 
    //                     equipments_category_id = :categoryId";

    //         $stmt = $this->conn->prepare($sql);
    //         $stmt->bindParam(':name', $categoryData['name']);
    //         $stmt->bindParam(':categoryId', $categoryData['categoryId'], PDO::PARAM_INT);

    //         // Add these lines for debugging
    //         error_log("Updating category: " . print_r($categoryData, true));
    //         $result = $stmt->execute();
    //         error_log("Update result: " . ($result ? "true" : "false"));
    //         error_log("Rows affected: " . $stmt->rowCount());

    //         if ($result) {
    //             return json_encode(['status' => 'success', 'message' => 'Category updated successfully.']);
    //         } else {
    //             return json_encode(['status' => 'error', 'message' => 'Failed to update category.']);
    //         }
    //     } catch (PDOException $e) {
    //         error_log("PDO Exception: " . $e->getMessage());
    //         return json_encode(['status' => 'error', 'message' => 'Error: ' . $e->getMessage()]);
    //     }
    // }

    public function updateVehicleModel($modelData) {
        // Validate input data
        if (!isset($modelData['id']) || !isset($modelData['name']) || 
            !isset($modelData['category_id']) || !isset($modelData['make_id'])) {
            error_log("Missing required fields in modelData: " . print_r($modelData, true));
            return json_encode(['status' => 'error', 'message' => 'Missing required fields']);
        }

        error_log("Starting vehicle model update with data: " . print_r($modelData, true));
        
        try {
            // First, get the current vehicle model name to check if it's the same
            $currentSql = "SELECT vehicle_model_name FROM tbl_vehicle_model WHERE vehicle_model_id = :id";
            $currentStmt = $this->conn->prepare($currentSql);
            $currentStmt->bindParam(':id', $modelData['id'], PDO::PARAM_INT);
            $currentStmt->execute();
            $currentModel = $currentStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$currentModel) {
                return json_encode(['status' => 'error', 'message' => 'Vehicle model not found']);
            }
            
            $currentName = $currentModel['vehicle_model_name'];
            
            // If the new name is the same as the current name, allow the update
            if ($currentName === $modelData['name']) {
                $sql = "UPDATE tbl_vehicle_model SET 
                            vehicle_model_name = :modelName, 
                            vehicle_category_id = :categoryId, 
                            vehicle_model_vehicle_make_id = :makeId 
                        WHERE 
                            vehicle_model_id = :modelId";

                $stmt = $this->conn->prepare($sql);
                
                // Convert values to appropriate types
                $modelId = intval($modelData['id']);
                $categoryId = intval($modelData['category_id']);
                $makeId = intval($modelData['make_id']);
                
                $stmt->bindValue(':modelName', $modelData['name'], PDO::PARAM_STR);
                $stmt->bindValue(':categoryId', $categoryId, PDO::PARAM_INT);
                $stmt->bindValue(':makeId', $makeId, PDO::PARAM_INT);
                $stmt->bindValue(':modelId', $modelId, PDO::PARAM_INT);

                // Log the actual values being used
                error_log("Executing query with values: modelId=$modelId, name={$modelData['name']}, categoryId=$categoryId, makeId=$makeId");
                
                $result = $stmt->execute();
                $rowCount = $stmt->rowCount();
                
                error_log("Query execution result: " . ($result ? "success" : "failed"));
                error_log("Rows affected: $rowCount");
                
                if ($result) {
                    if ($rowCount > 0) {
                        return json_encode(['status' => 'success', 'message' => 'Vehicle model updated successfully']);
                    } else {
                        return json_encode(['status' => 'error', 'message' => 'No matching record found']);
                    }
                } else {
                    $error = $stmt->errorInfo();
                    error_log("Database error: " . print_r($error, true));
                    return json_encode(['status' => 'error', 'message' => 'Database error: ' . $error[2]]);
                }
            }
            
            // If the name is different, check if the new name already exists
            $checkSql = "SELECT COUNT(*) FROM tbl_vehicle_model WHERE vehicle_model_name = :name AND vehicle_model_id != :id";
            $checkStmt = $this->conn->prepare($checkSql);
            $checkStmt->bindParam(':name', $modelData['name']);
            $checkStmt->bindParam(':id', $modelData['id'], PDO::PARAM_INT);
            $checkStmt->execute();
            
            if ($checkStmt->fetchColumn() > 0) {
                return json_encode(['status' => 'error', 'message' => 'Vehicle model name already exists']);
            }
            
            // If validation passes, proceed with the update
            $sql = "UPDATE tbl_vehicle_model SET 
                        vehicle_model_name = :modelName, 
                        vehicle_category_id = :categoryId, 
                        vehicle_model_vehicle_make_id = :makeId 
                    WHERE 
                        vehicle_model_id = :modelId";

            $stmt = $this->conn->prepare($sql);
            
            // Convert values to appropriate types
            $modelId = intval($modelData['id']);
            $categoryId = intval($modelData['category_id']);
            $makeId = intval($modelData['make_id']);
            
            $stmt->bindValue(':modelName', $modelData['name'], PDO::PARAM_STR);
            $stmt->bindValue(':categoryId', $categoryId, PDO::PARAM_INT);
            $stmt->bindValue(':makeId', $makeId, PDO::PARAM_INT);
            $stmt->bindValue(':modelId', $modelId, PDO::PARAM_INT);

            // Log the actual values being used
            error_log("Executing query with values: modelId=$modelId, name={$modelData['name']}, categoryId=$categoryId, makeId=$makeId");
            
            $result = $stmt->execute();
            $rowCount = $stmt->rowCount();
            
            error_log("Query execution result: " . ($result ? "success" : "failed"));
            error_log("Rows affected: $rowCount");
            
            if ($result) {
                if ($rowCount > 0) {
                    return json_encode(['status' => 'success', 'message' => 'Vehicle model updated successfully']);
                } else {
                    return json_encode(['status' => 'error', 'message' => 'No matching record found']);
                }
            } else {
                $error = $stmt->errorInfo();
                error_log("Database error: " . print_r($error, true));
                return json_encode(['status' => 'error', 'message' => 'Database error: ' . $error[2]]);
            }
                                     
        } catch (PDOException $e) {
            error_log("PDO Exception: " . $e->getMessage());
            return json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
        }
    }

    public function updateCondition($conditionData) {
        try {
            $sql = "UPDATE tbl_condition_master SET 
                        condition_name = :name
                    WHERE 
                        condition_id = :conditionId";

            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':name', $conditionData['name']);
            $stmt->bindParam(':conditionId', $conditionData['conditionId'], PDO::PARAM_INT);

            if ($stmt->execute()) {
                return json_encode(['status' => 'success', 'message' => 'Condition updated successfully']);
            } else {
                return json_encode(['status' => 'error', 'message' => 'Could not update condition']);
            }
        } catch (PDOException $e) {
            return json_encode(['status' => 'error', 'message' => 'Error: ' . $e->getMessage()]);
        }
    }

    public function updateVehicleLicense($vehicleData) {
        try {
            // Validate required fields
            if (!isset($vehicleData['vehicle_id']) || 
                !isset($vehicleData['vehicle_model_id']) || 
                !isset($vehicleData['vehicle_license']) || 
                !isset($vehicleData['status_availability_id'])) {
                return json_encode(['status' => 'error', 'message' => 'Required fields are missing']);
            }

            // Handle image upload if present
            $picPath = null;
            if (!empty($vehicleData['vehicle_pic'])) {
                $uploadDir = 'uploads/vehicles/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }
                
                if (strpos($vehicleData['vehicle_pic'], ';base64,') !== false) {
                    list(, $base64Image) = explode(';base64,', $vehicleData['vehicle_pic']);
                    $picPath = $uploadDir . 'vehicle_' . time() . '.jpeg';
                    file_put_contents($picPath, base64_decode($base64Image));
                }
            }

            // Prepare SQL query
            $sql = "UPDATE tbl_vehicle SET 
                    vehicle_model_id = :model_id,
                    vehicle_license = :license,
                    year = :year,
                    status_availability_id = :status_id,
                    user_admin_id = :user_admin_id,
                    is_active = :is_active,
                    updated_at = NOW()";

            // Add vehicle_pic to update if new image was uploaded
            if ($picPath !== null) {
                $sql .= ", vehicle_pic = :pic";
            }

            $sql .= " WHERE vehicle_id = :id";

            $stmt = $this->conn->prepare($sql);

            // Bind parameters
            $stmt->bindParam(':model_id', $vehicleData['vehicle_model_id'], PDO::PARAM_INT);
            $stmt->bindParam(':license', $vehicleData['vehicle_license']);
            $stmt->bindParam(':year', $vehicleData['year']);
            $stmt->bindParam(':status_id', $vehicleData['status_availability_id'], PDO::PARAM_INT);
            $stmt->bindParam(':user_admin_id', $vehicleData['user_admin_id'], PDO::PARAM_INT);
            $stmt->bindParam(':is_active', $vehicleData['is_active'], PDO::PARAM_BOOL);
            $stmt->bindParam(':id', $vehicleData['vehicle_id'], PDO::PARAM_INT);

            // Bind pic path if image was uploaded
            if ($picPath !== null) {
                $stmt->bindParam(':pic', $picPath);
            }

            if ($stmt->execute()) {
                return json_encode([
                    'status' => 'success',
                    'message' => 'Vehicle updated successfully',
                    'vehicle_id' => $vehicleData['vehicle_id']
                ]);
            } else {
                return json_encode([
                    'status' => 'error',
                    'message' => 'Failed to update vehicle'
                ]);
            }

        } catch (PDOException $e) {
            error_log("Database error in updateVehicleLicense: " . $e->getMessage());
            return json_encode([
                'status' => 'error',
                'message' => 'Database error: ' . $e->getMessage()
            ]);
        }
    }

    public function updateEquipmentUnit($unitData) {
        try {
            // Validate required fields
            if (empty($unitData['unit_id'])) {
                return json_encode([
                    "status" => "error",
                    "message" => "Unit ID is required"
                ]);
            }

            // Begin transaction
            $this->conn->beginTransaction();

            // Check if the unit exists
            $checkStmt = $this->conn->prepare("SELECT unit_id FROM tbl_equipment_unit WHERE unit_id = :unit_id FOR UPDATE");
            $checkStmt->bindParam(':unit_id', $unitData['unit_id'], PDO::PARAM_INT);
            $checkStmt->execute();
            
            if (!$checkStmt->fetch(PDO::FETCH_ASSOC)) {
                $this->conn->rollBack();
                return json_encode([
                    "status" => "error",
                    "message" => "Equipment unit not found"
                ]);
            }

            // Map the allowed fields that can be updated
            $allowedFields = [
                'serial_number' => PDO::PARAM_STR,
                'is_active' => PDO::PARAM_BOOL,
                'user_admin_id' => PDO::PARAM_INT,
                'status_availability_id' => PDO::PARAM_INT // <-- add this line
            ];

            $updateFields = [];
            $params = [];

            foreach ($allowedFields as $field => $paramType) {
                if (isset($unitData[$field]) && $unitData[$field] !== '') {
                    $updateFields[] = "$field = :$field";
                    $params[$field] = [
                        'value' => $unitData[$field],
                        'type' => $paramType
                    ];
                }
            }

            if (empty($updateFields)) {
                $this->conn->rollBack();
                return json_encode([
                    "status" => "error",
                    "message" => "No fields to update"
                ]);
            }

            // Construct and execute the update query
            $sql = "UPDATE tbl_equipment_unit SET " . implode(", ", $updateFields) . " WHERE unit_id = :unit_id";
            
            $updateStmt = $this->conn->prepare($sql);
            
            // Bind unit_id parameter
            $updateStmt->bindParam(':unit_id', $unitData['unit_id'], PDO::PARAM_INT);
            
            // Bind all other parameters
            foreach ($params as $field => $param) {
                $updateStmt->bindParam(":$field", $param['value'], $param['type']);
            }
            
            // Add debug logging
            error_log("Executing SQL: $sql");
            error_log("Parameters: " . print_r($params, true));
            
            $updateStmt->execute();
            $this->conn->commit();

            if ($updateStmt->rowCount() > 0) {
                // Verify the update
                $verifyStmt = $this->conn->prepare("SELECT * FROM tbl_equipment_unit WHERE unit_id = :unit_id");
                $verifyStmt->bindParam(':unit_id', $unitData['unit_id'], PDO::PARAM_INT);
                $verifyStmt->execute();
                $updatedData = $verifyStmt->fetch(PDO::FETCH_ASSOC);
                
                return json_encode([
                    "status" => "success",
                    "message" => "Equipment unit updated successfully",
                    "unit_id" => $unitData['unit_id'],
                    "updated_data" => $updatedData
                ]);
            } else {
                return json_encode([
                    "status" => "info",
                    "message" => "No changes were made to the equipment unit",
                    "unit_id" => $unitData['unit_id']
                ]);
            }
        } catch (PDOException $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            error_log("Database error in updateEquipmentUnit: " . $e->getMessage());
            return json_encode([
                "status" => "error",
                "message" => "Database error: " . $e->getMessage()
            ]);
        } catch (Exception $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            error_log("Error in updateEquipmentUnit: " . $e->getMessage());
            return json_encode([
                "status" => "error",
                "message" => "An unexpected error occurred: " . $e->getMessage()
            ]);
        }
    }

    public function updateDriver($driverData) {
        try {
            // Handle driver picture upload if present
           

            $sql = "UPDATE tbl_driver SET 
                    driver_first_name = :firstName,
                    driver_middle_name = :middleName,
                    driver_last_name = :lastName,
                    driver_suffix = :suffix,
                    employee_id = :employeeId,
                    driver_birthdate = :birthdate,
                    driver_contact_number = :contactNumber,
                    driver_address = :address,
                    is_active = :isActive,
                    updated_at = NOW()";

            // Add picture update only if a new picture was uploaded
            
            $sql .= " WHERE driver_id = :driverId";

            $stmt = $this->conn->prepare($sql);

            // Bind the parameters
            $stmt->bindParam(':firstName', $driverData['first_name']);
            $stmt->bindParam(':middleName', $driverData['middle_name']);
            $stmt->bindParam(':lastName', $driverData['last_name']);
            $stmt->bindParam(':suffix', $driverData['suffix']);
            $stmt->bindParam(':employeeId', $driverData['employee_id']);
            $stmt->bindParam(':birthdate', $driverData['birthdate']);
            $stmt->bindParam(':contactNumber', $driverData['contact_number']);
            $stmt->bindParam(':address', $driverData['address']);
            $stmt->bindParam(':isActive', $driverData['is_active'], PDO::PARAM_BOOL);
            $stmt->bindParam(':driverId', $driverData['driver_id'], PDO::PARAM_INT);



            if ($stmt->execute()) {
                return json_encode([
                    'status' => 'success',
                    'message' => 'Driver updated successfully',
                    'driver_id' => $driverData['driver_id']
                ]);
            } else {
                return json_encode([
                    'status' => 'error',
                    'message' => 'Could not update driver'
                ]);
            }
        } catch (PDOException $e) {
            error_log("PDO Exception in updateDriver: " . $e->getMessage());
            return json_encode([
                'status' => 'error',
                'message' => 'Database error: ' . $e->getMessage()
            ]);
        } catch (Exception $e) {
            error_log("General Exception in updateDriver: " . $e->getMessage());
            return json_encode([
                'status' => 'error',
                'message' => 'An unexpected error occurred: ' . $e->getMessage()
            ]);
        }
    }

}


// Handle the request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    error_log("Raw input: " . file_get_contents('php://input'));
    $jsonInput = json_decode(file_get_contents('php://input'), true);
    error_log("Decoded input: " . print_r($jsonInput, true));
    
    $operation = $jsonInput['operation'] ?? '';
    error_log("Operation detected: " . $operation);
    
    $user = new User();
    
    switch($operation) {
        case "updateEquipment":
            echo $user->updateEquipment($jsonInput['equipmentData'] ?? []);
            break;

        case "updateEquipmentCategory":
            $categoryData = $jsonInput['categoryData'] ?? [];
            error_log("Received updateEquipmentCategory request: " . print_r($categoryData, true));
            echo $user->updateEquipmentCategory($categoryData);
            break;
            
        case "updatePosition":
            $id = $jsonInput['id'] ?? '';
            $name = $jsonInput['name'] ?? '';
            echo $user->updatePosition($id, $name);
            break;
        
        case "getUserDetails":
            echo $user->getUserDetails($jsonInput['userId'] ?? '');
            break;
        
        case "updateVenue":
            echo $user->updateVenue($jsonInput['venueData'] ?? []);
            break;
            
        case "updateDepartment":
            $id = $jsonInput['id'] ?? '';
            $name = $jsonInput['name'] ?? '';
            echo $user->updateDepartment($id, $name);
            break;
            
        case "updateVehicleMake":
            $id = $jsonInput['id'] ?? '';
            $name = $jsonInput['name'] ?? '';
            $userId = $jsonInput['userid'] ?? null;
            error_log("update_master1 updateVehicleMake userId=" . var_export($userId, true));
            echo $user->updateVehicleMake($id, $name, $userId);
            break;
            
        case "updateVehicleCategory":
            $id = $jsonInput['id'] ?? '';
            $name = $jsonInput['name'] ?? '';
            echo $user->updateVehicleCategory($id, $name);
            break;
            
        case "updateVehicle":
            $vehicleData = $jsonInput['vehicleData'] ?? [];
            echo $user->updateVehicle(
                $vehicleData['vehicle_id'] ?? '',
                $vehicleData['vehicle_model_id'] ?? '',
                $vehicleData['vehicle_license'] ?? ''
            );
            break;
        
        case "updateUser":
            echo $user->updateUser($jsonInput['userData'] ?? []);
            break;

        case "getPersonnelDetails":
            echo $user->getPersonnelDetails($jsonInput['personId'] ?? '');
            break;

        case "updatePersonnel":
            echo $user->updatePersonnel($jsonInput['personnelData'] ?? []);
            break;

        case "updateUserLevel":
            echo $user->updateUserLevel($jsonInput['userLevelData'] ?? []);
            break;

        case "updateVehicleModel":
            if (isset($jsonInput['modelData'])) {
                error_log("Processing updateVehicleModel with data: " . print_r($jsonInput['modelData'], true));
                echo $user->updateVehicleModel($jsonInput['modelData']);
            } else {
                error_log("Missing modelData in request");
                echo json_encode(['status' => 'error', 'message' => 'Missing modelData']);
            }
            break;

        case "updateCondition":
            echo $user->updateCondition($jsonInput['conditionData'] ?? []);
            break;

        case "updateVehicleLicense":
            $vehicleData = $jsonInput['vehicleData'] ?? [];
            echo $user->updateVehicleLicense($vehicleData);
            break;
        case "updateEquipmentUnit":
            $unitData = $jsonInput['unitData'] ?? [];
            echo $user->updateEquipmentUnit($unitData);
            break;


        case "updateDriver":
            echo $user->updateDriver($jsonInput['driverData'] ?? []);
            break;

        default:
            echo json_encode(['status' => 'error', 'message' => 'Invalid operation']);
            break;
    }
}

?>
