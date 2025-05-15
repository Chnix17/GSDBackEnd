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
            // Handle equipment picture upload
            $picPath = null;
            if (isset($equipmentData['equip_pic']) && !empty($equipmentData['equip_pic'])) {
                $uploadDir = 'uploads/equipment/';
                if (!file_exists($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }
                
                // Get file data and type from base64 string
                $image_parts = explode(";base64,", $equipmentData['equip_pic']);
                $image_type_aux = explode("image/", $image_parts[0]);
                $image_type = $image_type_aux[1];
                $image_base64 = base64_decode($image_parts[1]);
                
                // Generate unique filename
                $filename = 'equipment_' . time() . '.' . $image_type;
                $picPath = $uploadDir . $filename;
                
                file_put_contents($picPath, $image_base64);
            }

            // Prepare the SQL statement for updating equipment details
            $sql = "UPDATE tbl_equipments SET 
                        equip_name = :name, 
                        equip_quantity = :quantity, 
                        equipment_equipment_category_id = :categoryId,
                        status_availability_id = :statusId";
            
            // Add picture update only if a new picture was uploaded
            if ($picPath !== null) {
                $sql .= ", equip_pic = :picPath";
            }
            
            $sql .= " WHERE equip_id = :equipmentId";
    
            $stmt = $this->conn->prepare($sql);
            
            // Bind parameters to prevent SQL injection
            $stmt->bindParam(':name', $equipmentData['name']);
            $stmt->bindParam(':quantity', $equipmentData['quantity']);
            $stmt->bindParam(':categoryId', $equipmentData['categoryId'], PDO::PARAM_INT);
            $stmt->bindParam(':statusId', $equipmentData['statusId'], PDO::PARAM_INT);
            $stmt->bindParam(':equipmentId', $equipmentData['equipmentId'], PDO::PARAM_INT);
            
            // Bind picture path if it exists
            if ($picPath !== null) {
                $stmt->bindParam(':picPath', $picPath);
            }
    
            // Execute the statement and return success or error message
            if ($stmt->execute()) {
                return json_encode(['status' => 'success', 'message' => 'Equipment updated successfully']);
            } else {
                return json_encode(['status' => 'error', 'message' => 'Could not update equipment']);
            }
        } catch (Exception $e) {
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
        // Handle vehicle picture upload
        $picPath = null;
        if (isset($vehicleData['vehicle_pic']) && !empty($vehicleData['vehicle_pic'])) {
            $uploadDir = 'uploads/vehicles/';
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            
            // Get file data and type from base64 string
            $image_parts = explode(";base64,", $vehicleData['vehicle_pic']);
            
            // Validate that we have the expected parts
            if (count($image_parts) === 2) {
                $image_type_aux = explode("image/", $image_parts[0]);
                if (count($image_type_aux) === 2) {
                    $image_type = $image_type_aux[1];
                    $image_base64 = base64_decode($image_parts[1]);
                    
                    // Generate unique filename
                    $filename = 'vehicle_' . time() . '.' . $image_type;
                    $picPath = $uploadDir . $filename;
                    
                    file_put_contents($picPath, $image_base64);
                    $vehicleData['vehicle_pic'] = $picPath;
                }
            }
        }

        $sql = "UPDATE tbl_vehicle 
                SET vehicle_model_id = :modelId,
                    vehicle_license = :license,
                    year = :year,
                    status_availability_id = :statusId,
                    vehicle_pic = :pic
                WHERE vehicle_id = :id";
        
        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':modelId', $vehicleData['vehicle_model_id'], PDO::PARAM_INT);
            $stmt->bindParam(':license', $vehicleData['vehicle_license']);
            $stmt->bindParam(':year', $vehicleData['year']);
            $stmt->bindParam(':statusId', $vehicleData['status_availability_id'], PDO::PARAM_INT);
            $stmt->bindParam(':pic', $vehicleData['vehicle_pic']);
            $stmt->bindParam(':id', $vehicleData['vehicle_id'], PDO::PARAM_INT);
            
            if ($stmt->execute()) {
                return json_encode(['status' => 'success', 'message' => 'Vehicle details updated successfully']);
            } else {
                return json_encode(['status' => 'error', 'message' => 'Could not update vehicle details']);
            }
        } catch (PDOException $e) {
            return json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
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

        default:
            echo json_encode(['status' => 'error', 'message' => 'Invalid operation']);
            break;
    }
}

?>
