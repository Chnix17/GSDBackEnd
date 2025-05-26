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
        $sql = "UPDATE tbl_users SET 
                    users_name = :name, 
                    users_school_id = :schoolId, 
                    users_contact_number = :contact, 
                    users_username = :username, 
                    users_password = :password, 
                    users_department_id = :departmentId 
                WHERE 
                    users_id = :userId";
    
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':name', $userData['name']);
        $stmt->bindParam(':schoolId', $userData['schoolId']);
        $stmt->bindParam(':contact', $userData['contact']);
        $stmt->bindParam(':username', $userData['username']);
        $stmt->bindParam(':password', $userData['password']);
        $stmt->bindParam(':departmentId', $userData['departmentId']);
        $stmt->bindParam(':userId', $userData['userId'], PDO::PARAM_INT);
    
        return $stmt->execute() ? json_encode(['status' => 'success', 'message' => 'User updated successfully']) 
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

    public function updateVehicleMake($id, $name) {
        $sql = "UPDATE tbl_vehicle_make SET vehicle_make_name = :name WHERE vehicle_make_id = :id";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        
        return $stmt->execute() ? json_encode(['status' => 'success', 'message' => 'Vehicle make updated successfully']) 
                                 : json_encode(['status' => 'error', 'message' => 'Could not update vehicle make']);
    }

    // New method to update vehicle category
    public function updateVehicleCategory($id, $name) {
        $sql = "UPDATE tbl_vehicle_category SET vehicle_category_name = :name WHERE vehicle_category_id = :id";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        
        return $stmt->execute() ? json_encode(['status' => 'success', 'message' => 'Vehicle category updated successfully']) 
                                 : json_encode(['status' => 'error', 'message' => 'Could not update vehicle category']);
    }
    public function updateDepartment($id, $name) {
        $sql = "UPDATE tbl_departments SET departments_name = :name WHERE departments_id = :id";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        
        return $stmt->execute() ? json_encode(['status' => 'success', 'message' => 'Department updated successfully']) 
                                 : json_encode(['status' => 'error', 'message' => 'Could not update department']);
    }


    public function updateVenue($venueData) {
        // Handle image upload if present
        $picPath = null;
        if (isset($venueData['ven_pic']) && !empty($venueData['ven_pic'])) {
            $uploadDir = 'uploads/venues/';
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            
            $image_parts = explode(";base64,", $venueData['ven_pic']);
            $image_type_aux = explode("image/", $image_parts[0]);
            $image_type = $image_type_aux[1];
            $image_base64 = base64_decode($image_parts[1]);
            
            $filename = 'venue_' . time() . '.' . $image_type;
            $picPath = $uploadDir . $filename;
            
            file_put_contents($picPath, $image_base64);
            $venueData['ven_pic'] = $picPath;
        }

        // SQL query to update venue data
        $sql = "UPDATE tbl_venue SET 
                    ven_name = :venue_name, 
                    ven_occupancy = :max_occupancy,
                    ven_pic = :venue_pic,
                    status_availability_id = :status_availability_id,
                    ven_operating_hours = :operating_hours 
                WHERE 
                    ven_id = :venue_id";
    
        // Prepare the SQL statement
        $stmt = $this->conn->prepare($sql);
    
        // Bind parameters with the values from the $venueData array
        $stmt->bindParam(':venue_name', $venueData['venue_name']);
        $stmt->bindParam(':max_occupancy', $venueData['max_occupancy']);
        $stmt->bindParam(':venue_pic', $venueData['ven_pic']);
        $stmt->bindParam(':status_availability_id', $venueData['status_availability_id'], PDO::PARAM_INT);
        $stmt->bindParam(':operating_hours', $venueData['operating_hours']);
        $stmt->bindParam(':venue_id', $venueData['venue_id'], PDO::PARAM_INT);
    
        // Execute the query and return the response
        return $stmt->execute() 
            ? json_encode(['status' => 'success', 'message' => 'Venue updated successfully'])
            : json_encode(['status' => 'error', 'message' => 'Could not update venue']);
    }
    
    

    
    public function updateEquipment($equipmentData) {
        try {
            $this->conn->beginTransaction();

            // Handle equipment picture upload if present
            if (isset($equipmentData['equip_pic']) && !empty($equipmentData['equip_pic'])) {
                $uploadDir = 'uploads/equipment/';
                if (!file_exists($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }

                // Validate the image data format
                $image_parts = explode(";base64,", $equipmentData['equip_pic']);
                if (count($image_parts) !== 2) {
                    throw new Exception('Invalid image format');
                }

                $image_type_aux = explode("image/", $image_parts[0]);
                if (count($image_type_aux) !== 2) {
                    throw new Exception('Invalid image type');
                }

                $image_type = $image_type_aux[1];
                if (!in_array($image_type, ['jpeg', 'jpg', 'png', 'gif'])) {
                    throw new Exception('Unsupported image type');
                }

                $image_base64 = base64_decode($image_parts[1]);
                if ($image_base64 === false) {
                    throw new Exception('Invalid base64 encoding');
                }

                $filename = 'equipment_' . time() . '.' . $image_type;
                $picPath = $uploadDir . $filename;

                if (!file_put_contents($picPath, $image_base64)) {
                    throw new Exception('Failed to save image');
                }

                $sql = "UPDATE tbl_equipments SET 
                            equip_name = :name, 
                            category_name = :categoryName,
                            equip_pic = :picPath
                        WHERE equip_id = :equipmentId";
                $stmt = $this->conn->prepare($sql);
                $stmt->bindParam(':picPath', $picPath);
            } else {
                $sql = "UPDATE tbl_equipments SET 
                            equip_name = :name, 
                            category_name = :categoryName
                        WHERE equip_id = :equipmentId";
                $stmt = $this->conn->prepare($sql);
            }

            // Bind common parameters
            $stmt->bindParam(':name', $equipmentData['name']);
            $stmt->bindParam(':categoryName', $equipmentData['category_name']);
            $stmt->bindParam(':equipmentId', $equipmentData['equipmentId'], PDO::PARAM_INT);

            if (!$stmt->execute()) {
                throw new Exception('Could not update equipment');
            }

            // Now update quantity in tbl_equipment_unit
            if (isset($equipmentData['quantity'])) {
                $updateQuantitySQL = "UPDATE tbl_equipment_unit 
                                    SET quantity = :quantity 
                                    WHERE equip_id = :equipmentId";
                $qtyStmt = $this->conn->prepare($updateQuantitySQL);
                $qtyStmt->bindParam(':quantity', $equipmentData['quantity'], PDO::PARAM_INT);
                $qtyStmt->bindParam(':equipmentId', $equipmentData['equipmentId'], PDO::PARAM_INT);

                if (!$qtyStmt->execute()) {
                    throw new Exception('Could not update equipment quantity');
                }
            }

            $this->conn->commit();
            return json_encode(['status' => 'success', 'message' => 'Equipment and quantity updated successfully']);
        } catch (Exception $e) {
            $this->conn->rollBack();
            error_log('Error in updateEquipment: ' . $e->getMessage());
            return json_encode(['status' => 'error', 'message' => $e->getMessage()]);
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
    public function updateEquipmentCategory($categoryData) {
        try {
            $sql = "UPDATE tbl_equipment_category SET 
                        equipments_category_name = :name
                    WHERE 
                        equipments_category_id = :categoryId";

            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':name', $categoryData['name']);
            $stmt->bindParam(':categoryId', $categoryData['categoryId'], PDO::PARAM_INT);

            // Add these lines for debugging
            error_log("Updating category: " . print_r($categoryData, true));
            $result = $stmt->execute();
            error_log("Update result: " . ($result ? "true" : "false"));
            error_log("Rows affected: " . $stmt->rowCount());

            if ($result) {
                return json_encode(['status' => 'success', 'message' => 'Category updated successfully.']);
            } else {
                return json_encode(['status' => 'error', 'message' => 'Failed to update category.']);
            }
        } catch (PDOException $e) {
            error_log("PDO Exception: " . $e->getMessage());
            return json_encode(['status' => 'error', 'message' => 'Error: ' . $e->getMessage()]);
        }
    }

    public function updateVehicleModel($modelData) {
        // Validate input data
        if (!isset($modelData['id']) || !isset($modelData['name']) || 
            !isset($modelData['category_id']) || !isset($modelData['make_id'])) {
            error_log("Missing required fields in modelData: " . print_r($modelData, true));
            return json_encode(['status' => 'error', 'message' => 'Missing required fields']);
        }

        error_log("Starting vehicle model update with data: " . print_r($modelData, true));
        
        $sql = "UPDATE tbl_vehicle_model SET 
                    vehicle_model_name = :modelName, 
                    vehicle_category_id = :categoryId, 
                    vehicle_model_vehicle_make_id = :makeId 
                WHERE 
                    vehicle_model_id = :modelId";

        try {
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
        $this->conn->beginTransaction();

        // Extract values
        $modelName            = trim($vehicleData['vehicle_model_name'] ?? '');
        $vehicleLicense       = trim($vehicleData['vehicle_license'] ?? '');
        $year                 = trim($vehicleData['year'] ?? '');
        $vehicleId            = intval($vehicleData['vehicle_id'] ?? 0);
        $statusAvailabilityId = intval($vehicleData['status_availability_id'] ?? 0);
        $categoryName         = trim($vehicleData['vehicle_category_name'] ?? '');
        $makeName             = trim($vehicleData['vehicle_make_name'] ?? '');

        if (!$modelName || !$vehicleLicense || !$year || !$vehicleId || !$statusAvailabilityId || !$categoryName || !$makeName) {
            return json_encode(['status' => 'error', 'message' => 'All fields are required.']);
        }

        // 1. Check or insert vehicle model name
        $sql = "SELECT vehicle_model_id FROM tbl_vehicle_model WHERE vehicle_model_name = :modelName LIMIT 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':modelName' => $modelName]);

        if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $vehicleModelId = $row['vehicle_model_id'];
        } else {
            $sql = "INSERT INTO tbl_vehicle_model (vehicle_model_name, vehicle_model_created_at, vehicle_model_updated_at)
                    VALUES (:modelName, NOW(), NOW())";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([':modelName' => $modelName]);
            $vehicleModelId = $this->conn->lastInsertId();
        }

        // 2. Optional: Handle vehicle picture upload
        $picPath = null;
        if (!empty($vehicleData['vehicle_pic'])) {
            $uploadDir = 'uploads/vehicles/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            list($meta, $b64) = explode(';base64,', $vehicleData['vehicle_pic']);
            $ext = str_replace('data:image/', '', $meta);
            $filename = 'vehicle_' . time() . '.' . $ext;
            $picPath = $uploadDir . $filename;
            file_put_contents($picPath, base64_decode($b64));
        }

        // 3. Update the vehicle
        $sql = "UPDATE tbl_vehicle SET
                    vehicle_model_id = :modelId,
                    vehicle_license = :license,
                    year = :year,
                    status_availability_id = :statusId,
                    vehicle_category_name = :category,
                    vehicle_make_name = :make"
                . (!empty($picPath) ? ", vehicle_pic = :pic" : "") .
               " WHERE vehicle_id = :id";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':modelId', $vehicleModelId, PDO::PARAM_INT);
        $stmt->bindParam(':license', $vehicleLicense);
        $stmt->bindParam(':year', $year);
        $stmt->bindParam(':statusId', $statusAvailabilityId, PDO::PARAM_INT);
        $stmt->bindParam(':category', $categoryName);
        $stmt->bindParam(':make', $makeName);
        $stmt->bindParam(':id', $vehicleId, PDO::PARAM_INT);
        if (!empty($picPath)) {
            $stmt->bindParam(':pic', $picPath);
        }

        $stmt->execute();
        $this->conn->commit();

        return json_encode(['status' => 'success', 'message' => 'Vehicle updated successfully.']);

    } catch (PDOException $e) {
        if ($this->conn->inTransaction()) {
            $this->conn->rollBack();
        }
        return json_encode(['status' => 'error', 'message' => 'DB error: ' . $e->getMessage()]);
    }
}



    public function updateEquipmentUnit($unit_id, $equip_id, $serial_number, $status_availability_id) {
    try {
        // Validate required fields
        if (empty($unit_id) || empty($serial_number) || empty($status_availability_id)) {
            error_log("Missing required fields: unit_id=$unit_id, serial_number=$serial_number, status_availability_id=$status_availability_id");
            return [
                "status" => "error",
                "message" => "All fields are required (unit_id, serial_number, status_availability_id)"
            ];
        }

        // Begin transaction
        $this->conn->beginTransaction();

        // Check if the unit exists
        $checkStmt = $this->conn->prepare("SELECT unit_id FROM tbl_equipment_unit WHERE unit_id = :unit_id FOR UPDATE");
        $checkStmt->bindParam(':unit_id', $unit_id, PDO::PARAM_INT);
        $checkStmt->execute();
        
        if (!$checkStmt->fetch(PDO::FETCH_ASSOC)) {
            $this->conn->rollBack();
            return [
                "status" => "error",
                "message" => "Equipment unit not found"
            ];
        }

        // Update the equipment unit
        $sql = "UPDATE tbl_equipment_unit 
                SET serial_number = :serial_number, 
                    status_availability_id = :status_availability_id";

        // Add equip_id to update if provided
        if (!empty($equip_id)) {
            $sql .= ", equip_id = :equip_id";
        }

        $sql .= " WHERE unit_id = :unit_id";

        error_log("SQL Query: " . $sql);
        error_log("Parameters: unit_id=$unit_id, serial_number=$serial_number, status_availability_id=$status_availability_id, equip_id=$equip_id");
        
        $updateStmt = $this->conn->prepare($sql);
        
        // Bind parameters
        $updateStmt->bindParam(':serial_number', $serial_number, PDO::PARAM_STR);
        $updateStmt->bindParam(':status_availability_id', $status_availability_id, PDO::PARAM_INT);
        $updateStmt->bindParam(':unit_id', $unit_id, PDO::PARAM_INT);
        
        if (!empty($equip_id)) {
            $updateStmt->bindParam(':equip_id', $equip_id, PDO::PARAM_INT);
        }
        
        $updateStmt->execute();

        $this->conn->commit();

        if ($updateStmt->rowCount() > 0) {
            // Verify the update
            $verifyStmt = $this->conn->prepare("SELECT * FROM tbl_equipment_unit WHERE unit_id = :unit_id");
            $verifyStmt->bindParam(':unit_id', $unit_id, PDO::PARAM_INT);
            $verifyStmt->execute();
            $updatedData = $verifyStmt->fetch(PDO::FETCH_ASSOC);
            
            error_log("Updated data: " . print_r($updatedData, true));
            
            return [
                "status" => "success",
                "message" => "Equipment unit updated successfully",
                "unit_id" => $unit_id,
                "updated_data" => $updatedData
            ];
        } else {
            return [
                "status" => "info",
                "message" => "No changes were made to the equipment unit",
                "unit_id" => $unit_id
            ];
        }
    } catch (PDOException $e) {
        $this->conn->rollBack();
        error_log("Database error in updateEquipmentUnit: " . $e->getMessage());
        return [
            "status" => "error",
            "message" => "Database error: " . $e->getMessage(),
            "error_code" => $e->getCode()
        ];
    } catch (Exception $e) {
        $this->conn->rollBack();
        error_log("Error in updateEquipmentUnit: " . $e->getMessage());
        return [
            "status" => "error",
            "message" => "An unexpected error occurred: " . $e->getMessage()
        ];
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
            echo $user->updateVehicleMake($id, $name);
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
            $unit_id = $jsonInput['unit_id'] ?? '';
            $equip_id = $jsonInput['equip_id'] ?? '';
            $serial_number = $jsonInput['serial_number'] ?? '';
            $status_availability_id = $jsonInput['status_availability_id'] ?? '';

            echo json_encode($user->updateEquipmentUnit($unit_id, $equip_id, $serial_number, $status_availability_id));
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
