<?php
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

class Vehicle {
    private $conn;

    public function __construct() {
        include 'connection-pdo.php';  // Connection file
        $this->conn = $conn;
    }



    public function fetchCategoriesAndMakes() {
        try {
            // Fetch categories
            $categoriesStmt = $this->conn->prepare("SELECT * FROM tbl_vehicle_category");
            $categoriesStmt->execute();
            $categories = $categoriesStmt->fetchAll(PDO::FETCH_ASSOC);
    
            // Fetch makes
            $makesStmt = $this->conn->prepare("SELECT * FROM tbl_vehicle_make");
            $makesStmt->execute();
            $makes = $makesStmt->fetchAll(PDO::FETCH_ASSOC);
    
            return json_encode([
                'status' => 'success',
                'categories' => $categories,
                'makes' => $makes
            ]);
        } catch (PDOException $e) {
            return json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }


    public function personnelPositionExists($positionName) {
        try {
            $sql = "SELECT COUNT(*) FROM tbl_personnel_position WHERE position_name = :name";
            $stmt = $this->conn->prepare($sql);
            // Fixed the typo in variable name
            $stmt->bindParam(':name', $positionName); 
            $stmt->execute();
            return $stmt->fetchColumn() > 0;
        } catch(PDOException $e) {
            return false; // Treat as not existing on error
        }
    }
    
    // Insert personnel position
    public function savePositionData($json) {
        $json = json_decode($json, true);
        
        // Updated method call to correct method name
        if ($this->personnelPositionExists($json['position_name'])) {
            return json_encode(['status' => 'error', 'message' => 'Position already exists.']);
        }
        
        try {
            $sql = "INSERT INTO tbl_personnel_position (position_name) VALUES (:name)";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':name', $json['position_name']);
            $stmt->execute();
            return json_encode(['status' => 'success', 'message' => 'Position added successfully.']);
        } catch(PDOException $e) {
            return json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }
    


    public function modelExists($modelName) {
        try {
            $sql = "SELECT COUNT(*) FROM tbl_vehicle_model WHERE vehicle_model_name = :name";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':name', $modelName);
            $stmt->execute();
            return $stmt->fetchColumn() > 0;
        } catch(PDOException $e) {
            return false; // Treat as not existing on error
        }
    }

    public function saveModelData($json) {
        $json = json_decode($json, true);
        error_log(print_r($json, true)); 
        try {
            $sql = "INSERT INTO tbl_vehicle_model (vehicle_model_name, vehicle_category_id, vehicle_model_vehicle_make_id) 
                    VALUES (:name, :category_id, :make_id)";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':name', $json['name']);
            $stmt->bindParam(':category_id', $json['category_id']);
            $stmt->bindParam(':make_id', $json['make_id']);
            $stmt->execute();
    
            return json_encode(['status' => 'success', 'message' => 'Model added successfully.']);
        } catch (PDOException $e) {
            return json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }
    

    // Check if vehicle category exists
    public function categoryExists($categoryName) {
        try {
            $sql = "SELECT COUNT(*) FROM tbl_vehicle_category WHERE vehicle_category_name = :name";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':name', $categoryName);
            $stmt->execute();
            return $stmt->fetchColumn() > 0;
        } catch(PDOException $e) {
            return false; // Treat as not existing on error
        }
    }

    // Insert vehicle category
    public function saveCategoryData($json) {
        $json = json_decode($json, true);
    
        if ($this->categoryExists($json['vehicle_category_name'])) {
            return json_encode(['status' => 'error', 'message' => 'Category already exists.']);
        }
    
        try {
            $sql = "INSERT INTO tbl_vehicle_category (vehicle_category_name) VALUES (:name)";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':name', $json['vehicle_category_name']);
            $stmt->execute(); // No need to bind the primary key
            return json_encode(['status' => 'success', 'message' => 'Category added successfully.']);
        } catch(PDOException $e) {
            return json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }

    // Check if vehicle make exists
    public function makeExists($makeName) {
        try {
            $sql = "SELECT COUNT(*) FROM tbl_vehicle_make WHERE vehicle_make_name = :name"; // Fixed here
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':name', $makeName);
            $stmt->execute();
            return $stmt->fetchColumn() > 0;
        } catch(PDOException $e) {
            return false; // Treat as not existing on error
        }
    }

    // Insert vehicle make
    public function saveMakeData($json) {
        $json = json_decode($json, true);
        
        // Log the incoming data
        error_log("Incoming data for make: " . print_r($json, true));
    
        if ($this->makeExists($json['vehicle_make_name'])) {
            return json_encode(['status' => 'error', 'message' => 'Make already exists.']);
        }
    
        try {
            $sql = "INSERT INTO tbl_vehicle_make (vehicle_make_name) VALUES (:name)";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':name', $json['vehicle_make_name']);
            $stmt->execute();
            return json_encode(['status' => 'success', 'message' => 'Make added successfully.']);
        } catch(PDOException $e) {
            // Log the error message
            error_log("Error inserting make: " . $e->getMessage());
            return json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }


// Check if equipment category exists
public function equipmentCategoryExists($categoryName) {
    try {
        $sql = "SELECT COUNT(*) FROM tbl_equipment_category WHERE equipments_category_name = :name";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':name', $categoryName);
        $stmt->execute();
        return $stmt->fetchColumn() > 0;
    } catch(PDOException $e) {
        return false; // Treat as not existing on error
    }
}

// Insert equipment category
public function saveEquipmentCategory($json) {
    $json = json_decode($json, true);
    
    // Check if category name is set
    if (!isset($json['equipments_category_name'])) {
        return json_encode(['status' => 'error', 'message' => 'Category name is required.']);
    }

    if ($this->equipmentCategoryExists($json['equipments_category_name'])) {
        return json_encode(['status' => 'error', 'message' => 'Equipment category already exists.']);
    }

    try {
        // Set admin ID to 1

        $sql = "INSERT INTO tbl_equipment_category (equipments_category_name) 
                VALUES (:name)";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':name', $json['equipments_category_name']); // Use admin ID of 1
        $stmt->execute();
        return json_encode(['status' => 'success', 'message' => 'Equipment category added successfully.']);
    } catch(PDOException $e) {
        return json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}


public function userLevelExists($userLevelName) {
    try {
        $sql = "SELECT COUNT(*) FROM tbl_user_levels WHERE user_level_name = :name";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':name', $userLevelName);
        $stmt->execute();
        return $stmt->fetchColumn() > 0;
    } catch(PDOException $e) {
        return false; // Treat as not existing on error
    }
}

// Insert user level data
public function saveUserLevelData($json) {
    $json = json_decode($json, true);
    
    if ($this->userLevelExists($json['user_level_name'])) {
        return json_encode(['status' => 'error', 'message' => 'User level already exists.']);
    }
    
    try {
        $sql = "INSERT INTO tbl_user_level (user_level_name, user_level_desc) VALUES (:name, :desc)";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':name', $json['user_level_name']);
        $stmt->bindParam(':desc', $json['user_level_desc']);
        $stmt->execute();
        return json_encode(['status' => 'success', 'message' => 'User level added successfully.']);
    } catch(PDOException $e) {
        return json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}
public function departmentExists($departmentName) {
    try {
        $sql = "SELECT COUNT(*) FROM tbl_departments WHERE departments_name = :name";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':name', $departmentName);
        $stmt->execute();
        return $stmt->fetchColumn() > 0;
    } catch(PDOException $e) {
        return false; // Treat as not existing on error
    }
}

// Insert department
public function saveDepartmentData($json) {
    $json = json_decode($json, true); // Decode the incoming JSON

    // Check if the department name exists in the input
    if (!isset($json['departments_name'])) {
        return json_encode(['status' => 'error', 'message' => 'Department name is required.']);
    }

    // Check if the department already exists
    if ($this->departmentExists($json['departments_name'])) {
        return json_encode(['status' => 'error', 'message' => 'Department already exists.']);
    }

    try {
        $sql = "INSERT INTO tbl_departments (departments_name) VALUES (:name)";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':name', $json['departments_name']);
        $stmt->execute();
        return json_encode(['status' => 'success', 'message' => 'Department added successfully.']);
    } catch (PDOException $e) {
        return json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}

public function conditionExists($conditionName) {
    try {
        $sql = "SELECT COUNT(*) FROM tbl_condition WHERE condition_name = :name";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':name', $conditionName);
        $stmt->execute();
        return $stmt->fetchColumn() > 0;
    } catch(PDOException $e) {
        return false;
    }
}

public function saveConditionData($json) {
    $json = json_decode($json, true);
    
    if (!isset($json['condition_name'])) {
        return json_encode(['status' => 'error', 'message' => 'Condition name is required.']);
    }

    if ($this->conditionExists($json['condition_name'])) {
        return json_encode(['status' => 'error', 'message' => 'Condition already exists.']);
    }

    try {
        $sql = "INSERT INTO tbl_condition (condition_name) VALUES (:name)";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':name', $json['condition_name']);
        $stmt->execute();
        return json_encode(['status' => 'success', 'message' => 'Condition added successfully.']);
    } catch(PDOException $e) {
        return json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}

}

// Handle incoming requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $vehicle = new Vehicle();
    $data = json_decode(file_get_contents('php://input'), true);

    if (isset($data['operation'])) {
        switch ($data['operation']) {
            case 'fetchCategoriesAndMakes':
                echo $vehicle->fetchCategoriesAndMakes();
                break;
            case 'saveDepartmentData':
                echo $vehicle->saveDepartmentData($data['json']);
                break;
                
            case 'saveCategoryData':
                echo $vehicle->saveCategoryData($data['json']);
                break;
            case 'saveMakeData':
                echo $vehicle->saveMakeData($data['json']);
                break;
            case 'saveModelData':
                echo $vehicle->saveModelData($data['json']);
                break;
                case 'saveEquipmentCategory':  // New case for saving equipment category
                    echo $vehicle->saveEquipmentCategory($data['json']);
                    break;
            
         case 'savePosition':  // New case for saving equipment category
        echo $vehicle->savePositionData($data['json']);
            break;


            case 'saveUserLevelData':
            echo $vehicle->saveUserLevelData($data['json']);
            break;
            case 'saveConditionData':
                echo $vehicle->saveConditionData($data['json']);
                break;
            default:
                echo json_encode(['status' => 'error', 'message' => 'Invalid operation.']);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'No operation specified.']);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method.']);
}

