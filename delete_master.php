<?php
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

class User {
    private $conn;

    public function __construct() {
        include 'connection-pdo.php'; 
        $this->conn = $conn;
    }
    public function archiveUser($userType, $userId) {
        try {
            // Validate inputs
            if (empty($userType) || empty($userId)) {
                return json_encode(array('status' => 'error', 'message' => 'User type and ID are required.'));
            }
            $query = "";
            
            switch ($userType) {
                case 'admin':
                    $query = "UPDATE tbl_admin SET is_active = 0 WHERE admin_id = :userId";
                    break;
                case 'user':
                    $query = "UPDATE tbl_users SET is_active = 0 WHERE users_id = :userId";
                    break;
                case 'driver':
                    $query = "UPDATE tbl_driver SET is_active = 0 WHERE driver_id = :userId";
                    break;
                case 'dept':
                    $query = "UPDATE tbl_dept SET is_active = 0 WHERE dept_id = :userId";
                    break;
                case 'personel':
                    $query = "UPDATE tbl_personel SET is_active = 0 WHERE jo_personel_id = :userId";
                    break;
                default:
                    return json_encode(array('message' => 'Invalid user type.'));
            }
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':userId', $userId);
            if ($stmt->execute()) {
                return json_encode(array('status' => 'success', 'message' => 'User archived successfully.'));
            } else {
                return json_encode(array('status' => 'error', 'message' => 'Error archiving user.'));
            }
        } catch (PDOException $e) {
            return json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
        }
    }
    public function unArchive($userType, $userId) {
        try {
            // Validate inputs
            if (empty($userType) || empty($userId)) {
                return json_encode(array('status' => 'error', 'message' => 'User type and ID are required.'));
            }
            $query = "";
            switch ($userType) {
                case 'admin':
                    $query = "UPDATE tbl_admin SET is_active = 1 WHERE admin_id = :userId";
                    break;
                case 'user':
                    $query = "UPDATE tbl_users SET is_active = 1 WHERE users_id = :userId";
                    break;
                case 'driver':
                    $query = "UPDATE tbl_driver SET is_active = 1 WHERE driver_id = :userId";
                    break;
                case 'dept':
                    $query = "UPDATE tbl_dept SET is_active = 1 WHERE dept_id = :userId";
                    break;
                case 'personel':
                    $query = "UPDATE tbl_personel SET is_active = 1 WHERE jo_personel_id = :userId";
                    break;
                default:
                    return json_encode(array('message' => 'Invalid user type.'));
            }
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':userId', $userId);
            if ($stmt->execute()) {
                return json_encode(array('status' => 'success', 'message' => 'User unarchived successfully.'));
            } else {
                return json_encode(array('status' => 'error', 'message' => 'Error unarchiving user.'));
            }
        } catch (PDOException $e) {
            return json_encode(array('status' => 'error', 'message' => 'Database error: ' . $e->getMessage()));
        }
    }

    private function executeQuery($sql) {
        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->execute();
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return json_encode(['status' => 'success', 'data' => $result]);
        } catch (PDOException $e) {
            return json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
        }
    }

    public function fetchAllUserTypes() {
        try {
            $usersSql = "SELECT 
                    'user' as type,
                    u.users_id as id,
                    u.users_fname as fname,
                    u.users_mname as mname,
                    u.users_lname as lname,
                    u.users_email as email,
                    u.users_school_id as school_id,
                    u.users_contact_number as contact_number,
                    u.users_department_id as department_id,
                    u.users_pic as pic,
                    u.users_created_at as created_at,
                    u.users_updated_at as updated_at,
                    u.is_active,
                    d.departments_name,
                    ul.user_level_name,
                    ul.user_level_desc
                FROM 
                    tbl_users u
                LEFT JOIN 
                    tbl_departments d ON u.users_department_id = d.departments_id
                LEFT JOIN 
                    tbl_user_level ul ON u.users_user_level_id = ul.user_level_id
                WHERE u.is_active = 0";

            // Fetch Dean/Secretary
            $deanSecSql = "SELECT 
                    'dean_sec' as type,
                    d.dept_id as id,
                    d.dept_fname as fname,
                    d.dept_mname as mname,
                    d.dept_lname as lname,
                    d.dept_email as email,
                    d.dept_school_id as school_id,
                    d.dept_contact_number as contact_number,
                    d.dept_department_id as department_id,
                    d.dept_pic as pic,
                    d.dept_created_at as created_at,
                    d.dept_updated_at as updated_at,
                    d.is_active,
                    dp.departments_name,
                    ul.user_level_name,
                    '' as user_level_desc
                FROM 
                    tbl_dept d
                LEFT JOIN 
                    tbl_departments dp ON d.dept_department_id = dp.departments_id
                LEFT JOIN 
                    tbl_user_level ul ON d.dept_user_level_id = ul.user_level_id
                WHERE d.is_active = 0";

            // Fetch Admin
            $adminSql = "SELECT 
                    'admin' as type,
                    a.admin_id as id,
                    a.admin_fname as fname,
                    a.admin_mname as mname,
                    a.admin_lname as lname,
                    a.admin_email as email,
                    a.admin_school_id as school_id,
                    a.admin_contact_number as contact_number,
                    a.admin_department_id as department_id,
                    a.admin_pic as pic,
                    a.admin_created_at as created_at,
                    a.admin_updated_at as updated_at,
                    a.is_active,
                    d.departments_name,
                    ul.user_level_name,
                    '' as user_level_desc
                FROM 
                    tbl_admin a
                LEFT JOIN 
                    tbl_departments d ON a.admin_department_id = d.departments_id
                LEFT JOIN 
                    tbl_user_level ul ON a.admin_user_level_id = ul.user_level_id
                WHERE a.is_active = 0";

            // Fetch Drivers
            $driverSql = "SELECT 
                    'driver' as type,
                    d.driver_id as id,
                    d.driver_full_name as fname,
                    '' as mname,
                    '' as lname,
                    d.driver_email as email,
                    d.driver_school_id as school_id,
                    d.driver_contact_number as contact_number,
                    d.driver_department_id as department_id,
                    d.driver_pic as pic,
                    d.driver_created_at as created_at,
                    d.driver_updated_at as updated_at,
                    d.is_active,
                    dp.departments_name,
                    ul.user_level_name,
                    '' as user_level_desc
                FROM 
                    tbl_driver d
                LEFT JOIN 
                    tbl_departments dp ON d.driver_department_id = dp.departments_id
                LEFT JOIN 
                    tbl_user_level ul ON d.driver_user_level_id = ul.user_level_id
                WHERE d.is_active = 0";

            // Fetch Personnel
            $personnelSql = "SELECT 
                    'personnel' as type,
                    p.jo_personel_id as id,
                    p.jo_personel_fname as fname,
                    p.jo_personel_mname as mname,
                    p.jo_personel_lname as lname,
                    '' as email,
                    p.jo_personel_school_id as school_id,
                    p.jo_personel_contact_number as contact_number,
                    p.jo_personel_department_id as department_id,
                    p.jo_personel_pic as pic,
                    p.jo_personel_created_at as created_at,
                    p.jo_personel_updated_at as updated_at,
                    p.is_active,
                    d.departments_name,
                    ul.user_level_name,
                    '' as user_level_desc
                FROM 
                    tbl_personel p
                LEFT JOIN 
                    tbl_departments d ON p.jo_personel_department_id = d.departments_id
                LEFT JOIN 
                    tbl_user_level ul ON p.jo_user_level_id = ul.user_level_id
                WHERE p.is_active = 0";

            // Combine all results and execute
            $sql = "($usersSql) UNION ALL ($deanSecSql) UNION ALL ($adminSql) UNION ALL ($driverSql) UNION ALL ($personnelSql) ORDER BY type, lname";
            return $this->executeQuery($sql);
        } catch (PDOException $e) {
            return json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
        }
    }

    public function archiveResource($resourceType, $resourceId) {
        try {
            if (empty($resourceType) || empty($resourceId)) {
                return json_encode(array('status' => 'error', 'message' => 'Resource type and ID are required.'));
            }

            $query = "";
            switch ($resourceType) {
                case 'vehicle':
                    $query = "UPDATE tbl_vehicle SET is_active = 0 WHERE vehicle_id = :resourceId";
                    break;
                case 'venue':
                    $query = "UPDATE tbl_venue SET is_active = 0 WHERE ven_id = :resourceId";
                    break;
                case 'equipment':
                    $query = "UPDATE tbl_equipments SET is_active = 0 WHERE equip_id = :resourceId";
                    break;
                default:
                    return json_encode(array('message' => 'Invalid resource type.'));
            }

            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':resourceId', $resourceId);

            if ($stmt->execute()) {
                return json_encode(array('status' => 'success', 'message' => 'Resource archived successfully.'));
            } else {
                return json_encode(array('status' => 'error', 'message' => 'Error archiving resource.'));
            }
        } catch (PDOException $e) {
            return json_encode(array('status' => 'error', 'message' => 'Database error: ' . $e->getMessage()));
        }
    }

    public function unarchiveResource($resourceType, $resourceId) {
        try {
            if (empty($resourceType) || empty($resourceId)) {
                return json_encode(array('status' => 'error', 'message' => 'Resource type and ID are required.'));
            }

            $query = "";
            switch ($resourceType) {
                case 'vehicle':
                    $query = "UPDATE tbl_vehicle SET is_active = 1 WHERE vehicle_id = :resourceId";
                    break;
                case 'venue':
                    $query = "UPDATE tbl_venue SET is_active = 1 WHERE ven_id = :resourceId";
                    break;
                case 'equipment':
                    $query = "UPDATE tbl_equipments SET is_active = 1 WHERE equip_id = :resourceId";
                    break;
                default:
                    return json_encode(array('message' => 'Invalid resource type.'));
            }

            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':resourceId', $resourceId);

            if ($stmt->execute()) {
                return json_encode(array('status' => 'success', 'message' => 'Resource unarchived successfully.'));
            } else {
                return json_encode(array('status' => 'error', 'message' => 'Error unarchiving resource.'));
            }
        } catch (PDOException $e) {
            return json_encode(array('status' => 'error', 'message' => 'Database error: ' . $e->getMessage()));
        }
    }
    public function fetchAllVehicles() {
        $sql = "SELECT  
                    v.vehicle_id,
                    v.vehicle_license,
                    v.year,
                    v.vehicle_pic,
                    v.status_availability_id,
                    vm.vehicle_make_name, 
                    vc.vehicle_category_name,
                    vmd.vehicle_model_name,
                    sa.status_availability_name,
                    v.is_active
                FROM 
                    tbl_vehicle_model vmd 
                INNER JOIN 
                    tbl_vehicle_make vm ON vmd.vehicle_model_vehicle_make_id = vm.vehicle_make_id 
                INNER JOIN 
                    tbl_vehicle_category vc ON vmd.vehicle_category_id = vc.vehicle_category_id 
                INNER JOIN 
                    tbl_vehicle v ON vmd.vehicle_model_id = v.vehicle_model_id
                INNER JOIN
                    tbl_status_availability sa ON v.status_availability_id = sa.status_availability_id
                WHERE 
                    v.is_active = 0";
    
        return $this->executeQuery($sql);
    }

    public function fetchEquipmentsWithStatus() {
        $sql = "SELECT 
                    e.equip_id, 
                    e.equip_name, 
                    e.equip_quantity, 
                    e.equip_created_at, 
                    e.equip_updated_at,
                    e.equip_pic,
                    e.equipment_equipment_category_id,
                    sa.status_availability_name,
                    e.is_active
                FROM 
                    tbl_equipments e
                INNER JOIN 
                    tbl_status_availability sa ON e.status_availability_id = sa.status_availability_id
                WHERE
                    e.is_active = 0
                ORDER BY
                    e.equip_id";
    
        return $this->executeQuery($sql);
    }

    public function fetchVenue() {
        $sql = "SELECT 
                ven_id, 
                ven_name, 
                ven_occupancy, 
                ven_created_at, 
                ven_updated_at, 
                status_availability_id, 
                ven_pic, 
                ven_operating_hours,
                is_active
                FROM tbl_venue
                WHERE is_active = 0";
        return $this->executeQuery($sql);
    }

    public function deleteVehicleMake($vehicleMakeId) {
        try {
            $sql = "DELETE FROM tbl_vehicle_make WHERE vehicle_make_id = :vehicleMakeId";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':vehicleMakeId', $vehicleMakeId);
            $stmt->execute();

            if ($stmt->rowCount() > 0) {
                return json_encode(['status' => 'success', 'message' => 'Vehicle make deleted successfully.']);
            } else {
                return json_encode(['status' => 'error', 'message' => 'No vehicle make found with the given ID.']);
            }
        } catch(PDOException $e) {
            return json_encode(['status' => 'error', 'message' => 'Could not delete vehicle make: ' . $e->getMessage()]);
        }
    }

    public function deleteVehicleCategory($vehicleCategoryId) {
        try {
            $sql = "DELETE FROM tbl_vehicle_category WHERE vehicle_category_id = :vehicleCategoryId";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':vehicleCategoryId', $vehicleCategoryId);
            $stmt->execute();

            if ($stmt->rowCount() > 0) {
                return json_encode(['status' => 'success', 'message' => 'Vehicle category deleted successfully.']);
            } else {
                return json_encode(['status' => 'error', 'message' => 'No vehicle category found with the given ID.']);
            }
        } catch(PDOException $e) {
            return json_encode(['status' => 'error', 'message' => 'Could not delete vehicle category: ' . $e->getMessage()]);
        }
    }

    public function deleteDepartment($departmentId) {
        try {
            $sql = "DELETE FROM tbl_departments WHERE departments_id = :departmentId";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':departmentId', $departmentId);
            $stmt->execute();

            if ($stmt->rowCount() > 0) {
                return json_encode(['status' => 'success', 'message' => 'Department deleted successfully.']);
            } else {
                return json_encode(['status' => 'error', 'message' => 'No department found with the given ID.']);
            }
        } catch(PDOException $e) {
            return json_encode(['status' => 'error', 'message' => 'Could not delete department: ' . $e->getMessage()]);
        }
    }

public function deleteModel($modelId) {
        try {
            $sql = "DELETE FROM tbl_vehicle_model WHERE vehicle_model_id = :modelId";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':modelId', $modelId);
            $stmt->execute();

            if ($stmt->rowCount() > 0) {
                return json_encode(['status' => 'success', 'message' => 'Vehicle model deleted successfully.']);
            } else {
                return json_encode(['status' => 'error', 'message' => 'No vehicle model found with the given ID.']);
            }
        } catch(PDOException $e) {
            return json_encode(['status' => 'error', 'message' => 'Could not delete vehicle model: ' . $e->getMessage()]);
        }
    }

    public function deleteEquipmentCategory($equipmentCategoryId) {
        try {
            $sql = "DELETE FROM tbl_equipment_category WHERE equipments_category_id = :equipmentCategoryId";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':equipmentCategoryId', $equipmentCategoryId);
            $stmt->execute();

            if ($stmt->rowCount() > 0) {
                return json_encode(['status' => 'success', 'message' => 'Equipment category deleted successfully.']);
            } else {
                return json_encode(['status' => 'error', 'message' => 'No equipment category found with the given ID.']);
            }
        } catch(PDOException $e) {
            return json_encode(['status' => 'error', 'message' => 'Could not delete equipment category: ' . $e->getMessage()]);
        }
    }


public function deleteCondition($conditionId) {
        try {
            $sql = "DELETE FROM tbl_condition_master WHERE condition_id = :conditionId";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':conditionId', $conditionId, PDO::PARAM_INT);

            if ($stmt->execute()) {
                if ($stmt->rowCount() > 0) {
                    return json_encode(['status' => 'success', 'message' => 'Condition deleted successfully']);
                } else {
                    return json_encode(['status' => 'error', 'message' => 'No condition found with the given ID']);
                }
            } else {
                return json_encode(['status' => 'error', 'message' => 'Could not delete condition']);
            }
        } catch (PDOException $e) {
            return json_encode(['status' => 'error', 'message' => 'Error: ' . $e->getMessage()]);
        }
    }



    
}

// Handle requests
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $user = new User();
    echo $user->fetchAllUserTypes();
    exit;
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (isset($data['operation'])) {
        $user = new User();
        
        switch ($data['operation']) {
            case 'archiveUser':
                if (isset($data['userType']) && isset($data['userId'])) {
                    echo $user->archiveUser($data['userType'], $data['userId']);
                } else {
                    echo json_encode(array('status' => 'error', 'message' => 'Missing required parameters.'));
                }
                break;
                
            case 'fetchAllUserTypes':
                echo $user->fetchAllUserTypes();
                break;
            case 'unarchiveUser':
                if (isset($data['userType']) && isset($data['userId'])) {
                    echo $user->unArchive($data['userType'], $data['userId']);
                } else {
                    echo json_encode(array('status' => 'error', 'message' => 'Missing required parameters.'));
                }
                break;
            case 'archiveResource':
                if (isset($data['resourceType']) && isset($data['resourceId'])) {
                    echo $user->archiveResource($data['resourceType'], $data['resourceId']);
                } else {
                    echo json_encode(array('status' => 'error', 'message' => 'Missing required parameters.'));
                }
                break;
            case 'unarchiveResource':
                if (isset($data['resourceType']) && isset($data['resourceId'])) {
                    echo $user->unarchiveResource($data['resourceType'], $data['resourceId']);
                } else {
                    echo json_encode(array('status' => 'error', 'message' => 'Missing required parameters.'));
                }
                break;
            case 'fetchAllVehicles':
                echo $user->fetchAllVehicles();
                break;
            case 'fetchEquipmentsWithStatus':
                echo $user->fetchEquipmentsWithStatus();
                break;
            case 'fetchVenue': 
                echo $user->fetchVenue();
                break;
            case 'deleteVehicleCategory':
                if (isset($data['vehicleCategoryId'])) {
                    echo $user->deleteVehicleCategory($data['vehicleCategoryId']);
                } else {
                    echo json_encode(['status' => 'error', 'message' => 'Missing vehicle category ID']);
                }
                break;
            case 'deleteDepartment':
                if (isset($data['departmentId'])) {
                    echo $user->deleteDepartment($data['departmentId']);
                } else {
                    echo json_encode(['status' => 'error', 'message' => 'Missing department ID']);
                }
                break;
            case 'deleteModel':
                if (isset($data['modelId'])) {
                    echo $user->deleteModel($data['modelId']);
                } else {
                    echo json_encode(['status' => 'error', 'message' => 'Missing model ID']);
                }
                break;
            case 'deleteEquipmentCategory':
                if (isset($data['equipmentCategoryId'])) {
                    echo $user->deleteEquipmentCategory($data['equipmentCategoryId']);
                } else {
                    echo json_encode(['status' => 'error', 'message' => 'Missing equipment category ID']);
                }
                break;
            case 'deleteCondition':
                if (isset($data['conditionId'])) {
                    echo $user->deleteCondition($data['conditionId']);
                } else {
                    echo json_encode(['status' => 'error', 'message' => 'Missing condition ID']);
                }
                break;
            case 'deleteVehicleMake':
                if (isset($data['vehicleMakeId'])) {
                    echo $user->deleteVehicleMake($data['vehicleMakeId']);
                } else {
                    echo json_encode(['status' => 'error', 'message' => 'Missing vehicle make ID']);
                }
                break;
            default:
                echo json_encode(array('status' => 'error', 'message' => 'Invalid operation.'));
        }
    } else {
        echo json_encode(array('status' => 'error', 'message' => 'Operation not specified.'));
    }
} else {
    echo json_encode(array('status' => 'error', 'message' => 'Invalid request method.'));
}
?>
