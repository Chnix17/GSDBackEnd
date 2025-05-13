<?php
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS, GET");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Max-Age: 3600");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header("HTTP/1.1 200 OK");
    exit();
}

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

class User {
    private $conn;

    public function __construct() {
        include 'connection-pdo.php';
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

    public function login($credentials) {
        $sql = "SELECT * FROM tbl_admin WHERE admin_username = :username AND admin_password = :password";
        return $this->executeQuery($sql, [
            ':username' => $credentials['username'],
            ':password' => $credentials['password']
        ]);
    }


    public function fetchUserProfile($userId) {
        $sql = "SELECT admin_id, admin_name, admin_school_id, admin_contact_number, admin_level, admin_username 
                FROM tbl_admin 
                WHERE admin_id = :userId";
        
        return $this->executeQuery($sql, [':userId' => $userId]);
    }

    public function fetchUsers() {
        $sql = "SELECT 
                    u.users_id,
                    u.users_fname,
                    u.users_mname,
                    u.users_lname,
                    u.users_email,
                    u.users_school_id,
                    u.users_contact_number,
                    u.users_department_id,
                    u.users_password,
                    u.users_pic,
                    u.users_created_at,
                    u.users_updated_at,
                    u.is_active,
                    d.departments_name,
                    ul.user_level_name,
                    ul.user_level_desc
                FROM 
                    tbl_users u
                LEFT JOIN 
                    tbl_departments d ON u.users_department_id = d.departments_id
                LEFT JOIN 
                    tbl_user_level ul ON u.users_user_level_id = ul.user_level_id";
        return $this->executeQuery($sql);
    }
    public function fetchDeanSec() {
        $sql = "SELECT 
                    d.dept_id,
                    d.dept_fname,
                    d.dept_mname,
                    d.dept_lname,
                    d.dept_email,
                    d.dept_school_id,
                    d.dept_contact_number,
                    d.dept_user_level_id,
                    d.dept_department_id,
                    d.dept_pic,
                    d.dept_created_at,
                    d.dept_updated_at,
                    d.is_active,
                    dp.departments_name,
                    ul.user_level_name
                FROM 
                    tbl_dept d
                LEFT JOIN 
                    tbl_departments dp ON d.dept_department_id = dp.departments_id
                LEFT JOIN 
                    tbl_user_level ul ON d.dept_user_level_id = ul.user_level_id";
        return $this->executeQuery($sql);
    }

    public function fetchAdmin() {
        $sql = "SELECT 
                    a.admin_id,
                    a.admin_fname,
                    a.admin_mname,
                    a.admin_lname,
                    a.admin_email,
                    a.admin_school_id,
                    a.admin_contact_number,
                    a.admin_user_level_id,
                    a.admin_department_id,
                    a.admin_pic,
                    a.admin_created_at,
                    a.admin_updated_at,
                    a.is_active,
                    d.departments_name,
                    ul.user_level_name
                FROM 
                    tbl_admin a
                LEFT JOIN 
                    tbl_departments d ON a.admin_department_id = d.departments_id
                LEFT JOIN 
                    tbl_user_level ul ON a.admin_user_level_id = ul.user_level_id";
        return $this->executeQuery($sql);
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
                    v.is_active = 1";
    
        return $this->executeQuery($sql);
    }

    public function fetchCategoriesAndModels($makeId) {
        $categoriesSql = "SELECT DISTINCT vc.vehicle_category_id, vc.vehicle_category_name 
                          FROM tbl_vehicle_category vc
                          INNER JOIN tbl_vehicle_model vm ON vc.vehicle_category_id = vm.vehicle_category_id
                          WHERE vm.vehicle_model_vehicle_make_id = :makeId
                          ORDER BY vc.vehicle_category_name";

        $modelsSql = "SELECT vehicle_model_name, vehicle_category_id, vehicle_model_id
                      FROM tbl_vehicle_model 
                      WHERE vehicle_model_vehicle_make_id = :makeId 
                      ORDER BY vehicle_model_name";

        try {
            $this->conn->beginTransaction();

            // Fetch categories
            $stmtCategories = $this->conn->prepare($categoriesSql);
            $stmtCategories->execute([':makeId' => $makeId]);
            $categories = $stmtCategories->fetchAll(PDO::FETCH_ASSOC);

            // Fetch models
            $stmtModels = $this->conn->prepare($modelsSql);
            $stmtModels->execute([':makeId' => $makeId]);
            $models = $stmtModels->fetchAll(PDO::FETCH_ASSOC);

            $this->conn->commit();

            // Group models by category
            $modelsByCategory = [];
            foreach ($models as $model) {
                $categoryId = $model['vehicle_category_id'];
                $modelsByCategory[$categoryId][] = [
                    'vehicle_model_id' => $model['vehicle_model_id'],
                    'vehicle_model_name' => $model['vehicle_model_name']
                ];
            }

            return json_encode([
                'status' => 'success',
                'data' => [
                    'categories' => $categories,
                    'modelsByCategory' => $modelsByCategory
                ]
            ]);
        } catch (PDOException $e) {
            $this->conn->rollBack();
            return json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
        }
    }

    public function fetchCategories() {
        $sql = "SELECT 
                    equipments_category_id, 
                    equipments_category_name 
                FROM 
                    tbl_equipment_category 
                ORDER BY 
                    equipments_category_name";
        return $this->executeQuery($sql);
    }

    public function fetchEquipmentsByCategory($categoryId) {
        $sql = "SELECT 
                    equip_id, 
                    equip_name, 
                    equip_quantity, 
                    equip_created_at, 
                    equip_updated_at, 
                    status_availability_id, 
                    equipment_equipment_category_id 
                FROM 
                    tbl_equipments 
                WHERE 
                    equipment_equipment_category_id = :categoryId";

        return $this->executeQuery($sql, [':categoryId' => $categoryId]);
    }

    public function fetchMake() {
        $sql = "SELECT vehicle_make_id, vehicle_make_name FROM tbl_vehicle_make ORDER BY vehicle_make_name";
        return $this->executeQuery($sql);
    }

    // New fetchVenue method
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
                WHERE is_active = 1";
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
                    e.is_active = 1
                ORDER BY
                    e.equip_id";
    
        return $this->executeQuery($sql);
    }
    
    public function fetchUserByEmailOrFullname($searchTerm) {
        $sql = "SELECT 
                    users_id,
                    users_fname,
                    users_mname,
                    users_lname,
                    users_email,
                    users_school_id,
                    users_contact_number,
                    users_user_level_id,
                    users_password,
                    users_department_id,
                    users_pic,
                    users_created_at,
                    users_updated_at
                FROM 
                    tbl_users 
                WHERE 
                    users_email LIKE :searchTerm
                    OR CONCAT(users_fname, ' ', users_mname, ' ', users_lname) LIKE :searchTerm
                    OR CONCAT(users_fname, ' ', users_lname) LIKE :searchTerm";
        
        $searchTerm = '%' . $searchTerm . '%';
        return $this->executeQuery($sql, [':searchTerm' => $searchTerm]);
    }

    public function __destruct() {
        unset($this->conn);
    }

    public function fetchEquipments() {
        $sql = "SELECT 
                    equip_id, 
                    equip_name, 
                    equip_quantity, 
                    equip_created_at, 
                    equip_updated_at, 
                    status_availability_id, 
                    equipment_equipment_category_id,
                    is_active
                FROM 
                    tbl_equipments
                WHERE
                    is_active = 1
                ORDER BY 
                    equip_id";

        return $this->executeQuery($sql);
    }

    public function fetchPersonnel() {
        $sql = "SELECT 
                    p.jo_personel_id,
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
                    tbl_user_level ul ON p.jo_user_level_id = ul.user_level_id";
    
        return $this->executeQuery($sql);
    }

    public function fetchPersonnelActive() {
        $sql = "SELECT 
                    p.jo_personel_id,
                    p.jo_personel_lname, 
                    p.jo_personel_fname, 
                    p.jo_personel_contact, 
                    p.username, 
                    p.login_time,
                    ul.user_level_name, 
                    pp.position_name 
            FROM 
                tbl_personel p
            INNER JOIN 
                tbl_personnel_position pp ON p.jo_personel_position_id = pp.position_id
            INNER JOIN 
                tbl_user_level ul ON p.jo_user_level_id = ul.user_level_id
            WHERE 
                p.login_time >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            ORDER BY 
                p.login_time DESC";

        return $this->executeQuery($sql);
    }

    public function fetchPositions() {
        $sql = "SELECT position_id, position_name FROM tbl_personnel_position ORDER BY position_name";
        return $this->executeQuery($sql);
    }

    public function fetchUserLevels() {
        $sql = "SELECT user_level_id, user_level_name FROM tbl_user_level ORDER BY user_level_name";
        return $this->executeQuery($sql);
    }

    public function fetchDepartments() {
        $sql = "SELECT departments_id, departments_name FROM tbl_departments ORDER BY departments_name"; // Adjust table name as needed
        return $this->executeQuery($sql);
    }

    public function fetchAllUserTypes() {
        $sql = "SELECT 
                u.users_id,
                u.users_fname,
                u.users_mname,
                u.users_lname, 
                u.users_email,
                u.users_school_id,
                u.users_contact_number,
                u.users_user_level_id,
                u.users_department_id,
                u.users_pic,
                u.users_created_at,
                u.users_updated_at,
                u.is_active,
                u.is_2FAactive,
                d.departments_name,
                ul.user_level_name,
                ul.user_level_desc
            FROM tbl_users u
            LEFT JOIN tbl_departments d ON u.users_department_id = d.departments_id
            LEFT JOIN tbl_user_level ul ON u.users_user_level_id = ul.user_level_id 
            WHERE u.is_active = 1";
        
        return $this->executeQuery($sql);
    }

    public function checkUniqueEmailAndSchoolId($email, $schoolId, $excludeId = null, $excludeType = null) {
        try {
            // Check in tbl_users
            $usersSql = "SELECT 'user' as type, users_id as id, users_email as email, users_school_id as school_id 
                        FROM tbl_users 
                        WHERE (users_email = :email OR users_school_id = :schoolId)";
            if ($excludeType === 'user' && $excludeId) {
                $usersSql .= " AND users_id != :excludeId";
            }

            // Check in tbl_dept
            $deptSql = "SELECT 'dean_sec' as type, dept_id as id, dept_email as email, dept_school_id as school_id 
                       FROM tbl_dept 
                       WHERE (dept_email = :email OR dept_school_id = :schoolId)";
            if ($excludeType === 'dean_sec' && $excludeId) {
                $deptSql .= " AND dept_id != :excludeId";
            }

            // Check in tbl_admin
            $adminSql = "SELECT 'admin' as type, admin_id as id, admin_email as email, admin_school_id as school_id 
                        FROM tbl_admin 
                        WHERE (admin_email = :email OR admin_school_id = :schoolId)";
            if ($excludeType === 'admin' && $excludeId) {
                $adminSql .= " AND admin_id != :excludeId";
            }

            // Check in tbl_driver
            $driverSql = "SELECT 'driver' as type, driver_id as id, driver_email as email, driver_school_id as school_id 
                         FROM tbl_driver 
                         WHERE (driver_email = :email OR driver_school_id = :schoolId)";
            if ($excludeType === 'driver' && $excludeId) {
                $driverSql .= " AND driver_id != :excludeId";
            }

            // Combine all queries
            $sql = "($usersSql) UNION ALL ($deptSql) UNION ALL ($adminSql) UNION ALL ($driverSql)";
            
            $stmt = $this->conn->prepare($sql);
            $params = [':email' => $email, ':schoolId' => $schoolId];
            
            if ($excludeId && in_array($excludeType, ['user', 'dean_sec', 'admin', 'driver'])) {
                $params[':excludeId'] = $excludeId;
            }
            
            $stmt->execute($params);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $response = ['status' => 'success', 'exists' => false, 'duplicates' => []];
            
            if (!empty($results)) {
                $response['exists'] = true;
                foreach ($results as $result) {
                    $duplicate = [];
                    if ($result['email'] === $email) {
                        $duplicate['field'] = 'email';
                        $duplicate['value'] = $email;
                    }
                    if ($result['school_id'] === $schoolId) {
                        $duplicate['field'] = 'school_id';
                        $duplicate['value'] = $schoolId;
                    }
                    $duplicate['type'] = $result['type'];
                    $response['duplicates'][] = $duplicate;
                }
            }

            return json_encode($response);

        } catch (PDOException $e) {
            return json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
        }
    }

    public function fetchAvailability($itemType, $itemId, $inputQuantities = []) {
        try {
            $itemIds = is_array($itemId) ? $itemId : [$itemId];
            $placeholders = implode(',', array_fill(0, count($itemIds), '?'));
    
            if ($itemType === 'venue') {
                $sql = "
                    SELECT 
                        v.ven_id,
                        v.ven_name,
                        v.ven_occupancy,
                        r.reservation_id,
                        r.reservation_title,
                        r.reservation_start_date,
                        r.reservation_end_date,
                        rs.reservation_status_status_id
                    FROM tbl_venue v
                    LEFT JOIN tbl_reservation_venue rv ON v.ven_id = rv.reservation_venue_venue_id
                    LEFT JOIN tbl_reservation r ON rv.reservation_reservation_id = r.reservation_id
                    LEFT JOIN tbl_reservation_status rs ON r.reservation_id = rs.reservation_reservation_id
                    WHERE v.ven_id IN ($placeholders)
                    AND (rs.reservation_status_status_id = 6 OR rs.reservation_status_status_id IS NULL)
                    AND rs.reservation_active = 1
                    GROUP BY v.ven_id, v.ven_name, v.ven_occupancy, r.reservation_id, r.reservation_title, 
                             r.reservation_start_date, r.reservation_end_date, rs.reservation_status_status_id";
    
                $stmt = $this->conn->prepare($sql);
                $stmt->execute($itemIds);
                
            } elseif ($itemType === 'vehicle') {
                $sql = "
                    SELECT 
                        v.vehicle_id,
                        v.vehicle_license,
                        vm.vehicle_make_name,
                        vmd.vehicle_model_name,
                        r.reservation_id,
                        r.reservation_title,
                        r.reservation_start_date,
                        r.reservation_end_date,
                        rs.reservation_status_status_id
                    FROM tbl_vehicle v
                    LEFT JOIN tbl_vehicle_model vmd ON v.vehicle_model_id = vmd.vehicle_model_id
                    LEFT JOIN tbl_vehicle_make vm ON vmd.vehicle_model_vehicle_make_id = vm.vehicle_make_id
                    LEFT JOIN tbl_reservation_vehicle rv ON v.vehicle_id = rv.reservation_vehicle_vehicle_id
                    LEFT JOIN tbl_reservation r ON rv.reservation_reservation_id = r.reservation_id
                    LEFT JOIN tbl_reservation_status rs ON r.reservation_id = rs.reservation_reservation_id
                    WHERE v.vehicle_id IN ($placeholders)
                    AND (rs.reservation_status_status_id = 6 OR rs.reservation_status_status_id IS NULL)
                    AND rs.reservation_active = 1
                    GROUP BY v.vehicle_id, v.vehicle_license, vm.vehicle_make_name, vmd.vehicle_model_name,
                             r.reservation_id, r.reservation_title, r.reservation_start_date, r.reservation_end_date,
                             rs.reservation_status_status_id";
    
                $stmt = $this->conn->prepare($sql);
                $stmt->execute($itemIds);
    
            } elseif ($itemType === 'equipment') {
                // For equipment type, handle quantities
                $sql = "
                    SELECT 
                        e.equip_id,
                        e.equip_name,
                        e.equip_quantity AS current_quantity,
                        COALESCE(SUM(re.reservation_equipment_quantity), 0) AS total_reserved,
                        (e.equip_quantity - COALESCE(SUM(re.reservation_equipment_quantity), 0)) AS total_available,
                        r.reservation_start_date,
                        r.reservation_end_date
                    FROM tbl_equipments e
                    LEFT JOIN tbl_reservation_equipment re ON e.equip_id = re.reservation_equipment_equip_id
                    LEFT JOIN tbl_reservation r ON re.reservation_reservation_id = r.reservation_id
                    LEFT JOIN tbl_reservation_status rs ON r.reservation_id = rs.reservation_reservation_id
                    WHERE e.equip_id IN ($placeholders)
                    AND (rs.reservation_status_status_id = 6 OR rs.reservation_status_status_id IS NULL)
                    AND rs.reservation_active = 1
                    GROUP BY e.equip_id, e.equip_name, e.equip_quantity, r.reservation_start_date, r.reservation_end_date";
    
                $stmt = $this->conn->prepare($sql);
                $stmt->execute($itemIds);
                $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Process quantities for equipment without subtracting the inputted quantity from the current quantity.
                foreach ($results as &$result) {
                    $equipId = $result['equip_id'];
                    $inputQty = isset($inputQuantities[$equipId]) ? (int)$inputQuantities[$equipId] : 0;
                    $result['inputted_quantity'] = $inputQty;
                    // Note: Do NOT subtract the inputted quantity from total_available.
                }
                
                return json_encode([
                    'status' => 'success',
                    'data' => $results
                ]);
            }
    
            // For venue and vehicle types
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if ($itemType === 'venue' || $itemType === 'vehicle') {
                foreach ($results as &$result) {
                    $result['is_available'] = ($result['reservation_status_status_id'] !== 6);
                    $result['reservation_status'] = ($result['reservation_status_status_id'] === 6) ? 'Reserved' : 'Available';
                }
            }
            
            return json_encode([
                'status' => 'success',
                'data' => $results
            ]);
        } catch (PDOException $e) {
            return json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
        }
    }
    

    public function fetchDriver($startDateTime = null, $endDateTime = null) {
        try {
            // Base query for all drivers
            $sql = "
                SELECT 
                    d.driver_id,
                    d.driver_first_name,
                    d.driver_middle_name,
                    d.driver_last_name,
                    CONCAT(
                        d.driver_first_name,
                        CASE 
                            WHEN d.driver_middle_name IS NOT NULL AND d.driver_middle_name != '' 
                            THEN CONCAT(' ', LEFT(d.driver_middle_name, 1), '. ')
                            ELSE ' '
                        END,
                        d.driver_last_name
                    ) as driver_full_name,
                    d.driver_contact_number,
                    d.driver_address,
                    d.created_at,
                    d.updated_at
                FROM 
                    tbl_driver d
                WHERE 1
            ";
    
            // If no date range is provided, return all drivers
            if ($startDateTime === null || $endDateTime === null) {
                $sql .= " ORDER BY d.driver_last_name, d.driver_first_name";
                return $this->executeQuery($sql);
            }
    
            // Check for reserved drivers within the given date range
            $sqlCheckUserReservation = "
                SELECT DISTINCT rd.reservation_driver_user_id
                FROM 
                    tbl_reservation_driver rd
                INNER JOIN 
                    tbl_reservation r ON rd.reservation_reservation_id = r.reservation_id
                INNER JOIN 
                    tbl_reservation_status rs ON r.reservation_id = rs.reservation_reservation_id
                WHERE 
                    rs.reservation_status_status_id = 6 -- Approved status
                    AND (
                        (r.reservation_start_date BETWEEN :startDateTime AND :endDateTime)
                        OR
                        (r.reservation_end_date BETWEEN :startDateTime AND :endDateTime)
                        OR
                        (:startDateTime BETWEEN r.reservation_start_date AND r.reservation_end_date)
                        OR
                        (:endDateTime BETWEEN r.reservation_start_date AND r.reservation_end_date)
                    )
                    AND rs.reservation_active = 1
            ";
    
            $stmt = $this->conn->prepare($sqlCheckUserReservation);
            $stmt->execute([
                ':startDateTime' => $startDateTime,
                ':endDateTime' => $endDateTime
            ]);
    
            $reservedUserIds = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
    
            // Build the main query to fetch available drivers
            $sqlAvailableDrivers = "
                SELECT 
                    d.driver_id,
                    d.driver_first_name,
                    d.driver_middle_name,
                    d.driver_last_name,
                    CONCAT(
                        d.driver_first_name,
                        CASE 
                            WHEN d.driver_middle_name IS NOT NULL AND d.driver_middle_name != '' 
                            THEN CONCAT(' ', LEFT(d.driver_middle_name, 1), '. ')
                            ELSE ' '
                        END,
                        d.driver_last_name
                    ) as driver_full_name,
                    d.driver_contact_number,
                    d.driver_address,
                    d.created_at,
                    d.updated_at
                FROM 
                    tbl_driver d
                WHERE 1
            ";
    
            // Exclude reserved drivers if there are any
            if (!empty($reservedUserIds)) {
                $sqlAvailableDrivers .= " AND d.driver_id NOT IN (" . implode(',', $reservedUserIds) . ")";
            }
    
            $sqlAvailableDrivers .= " ORDER BY d.driver_last_name, d.driver_first_name";
    
            return $this->executeQuery($sqlAvailableDrivers);
    
        } catch (PDOException $e) {
            return json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
        }
    }

    public function fetchAllReservations() {
        try {
            $query = "
                SELECT 
                    r.reservation_id,
                    r.reservation_title,
                    r.reservation_description,
                    r.reservation_start_date,
                    r.reservation_end_date,
                    r.reservation_participants,
                    r.reservation_user_id,
                    r.reservation_created_at,
                    rs.reservation_active,
                    sm.status_master_name AS reservation_status,
                    CONCAT(u.users_fname, ' ', u.users_mname, ' ', u.users_lname) AS user_full_name,
                    d.departments_name AS department_name
                FROM tbl_reservation AS r
    
                /* join exactly the one status row having the latest updated_at,
                   tie‐broken by the highest reservation_status_id */
                LEFT JOIN tbl_reservation_status AS rs
                  ON rs.reservation_status_id = (
                        SELECT reservation_status_id
                        FROM tbl_reservation_status
                        WHERE reservation_reservation_id = r.reservation_id
                        ORDER BY 
                          reservation_updated_at DESC,
                          reservation_status_id DESC
                        LIMIT 1
                  )
    
                LEFT JOIN tbl_status_master AS sm
                  ON rs.reservation_status_status_id = sm.status_master_id
    
                LEFT JOIN tbl_users AS u
                  ON r.reservation_user_id = u.users_id
    
                LEFT JOIN tbl_departments AS d
                  ON u.users_department_id = d.departments_id
    
                ORDER BY r.reservation_start_date DESC
            ";
    
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            $reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
            return json_encode([
                'status' => 'success',
                'data'   => $reservations
            ]);
        } catch (PDOException $e) {
            return json_encode([
                'status'  => 'error',
                'message' => 'Error fetching reservations: ' . $e->getMessage()
            ]);
        }
    }
    

    public function getInUse() {
        try {
            // Get all active reservations with status 6 (In Use)
            $statusQuery = "SELECT reservation_reservation_id 
                          FROM tbl_reservation_status 
                          WHERE reservation_status_status_id = 6 
                          AND reservation_active = 1";
            $statusStmt = $this->conn->query($statusQuery);
            $activeReservationIds = $statusStmt->fetchAll(PDO::FETCH_COLUMN);
    
            if (empty($activeReservationIds)) {
                return json_encode(['status' => 'success', 'data' => []]);
            }
    
            // Get equipment reservations with user details
            $equipmentQuery = "
                SELECT 
                    r.reservation_id,
                    r.reservation_title,
                    r.reservation_description,
                    r.reservation_start_date,
                    r.reservation_end_date,
                    CONCAT_WS(' ', u.users_fname, u.users_mname, u.users_lname) AS user_full_name,
                    e.equip_name AS resource_name,
                    re.reservation_equipment_quantity AS quantity,
                    'equipment' AS resource_type
                FROM tbl_reservation_equipment re
                JOIN tbl_equipments e ON re.reservation_equipment_equip_id = e.equip_id
                JOIN tbl_reservation r ON re.reservation_reservation_id = r.reservation_id
                JOIN tbl_users u ON r.reservation_user_id = u.users_id
                WHERE re.reservation_reservation_id IN (" . implode(',', $activeReservationIds) . ")";
    
            // Get venue reservations with user details
            $venueQuery = "
                SELECT 
                    r.reservation_id,
                    r.reservation_title,
                    r.reservation_description,
                    r.reservation_start_date,
                    r.reservation_end_date,
                    CONCAT_WS(' ', u.users_fname, u.users_mname, u.users_lname) AS user_full_name,
                    v.ven_name AS resource_name,
                    1 AS quantity,
                    'venue' AS resource_type
                FROM tbl_reservation_venue rv
                JOIN tbl_venue v ON rv.reservation_venue_venue_id = v.ven_id
                JOIN tbl_reservation r ON rv.reservation_reservation_id = r.reservation_id
                JOIN tbl_users u ON r.reservation_user_id = u.users_id
                WHERE rv.reservation_reservation_id IN (" . implode(',', $activeReservationIds) . ")";
    
            // Get vehicle reservations with user details
            $vehicleQuery = "
                SELECT 
                    r.reservation_id,
                    r.reservation_title,
                    r.reservation_description,
                    r.reservation_start_date,
                    r.reservation_end_date,
                    CONCAT_WS(' ', u.users_fname, u.users_mname, u.users_lname) AS user_full_name,
                    CONCAT(vm.vehicle_model_name, ' (', v.vehicle_license, ')') AS resource_name,
                    1 AS quantity,
                    'vehicle' AS resource_type
                FROM tbl_reservation_vehicle rv
                JOIN tbl_vehicle v ON rv.reservation_vehicle_vehicle_id = v.vehicle_id
                JOIN tbl_vehicle_model vm ON v.vehicle_model_id = vm.vehicle_model_id
                JOIN tbl_reservation r ON rv.reservation_reservation_id = r.reservation_id
                JOIN tbl_users u ON r.reservation_user_id = u.users_id
                WHERE rv.reservation_reservation_id IN (" . implode(',', $activeReservationIds) . ")";
    
            // Execute all queries
            $equipment = $this->conn->query($equipmentQuery)->fetchAll(PDO::FETCH_ASSOC);
            $venues = $this->conn->query($venueQuery)->fetchAll(PDO::FETCH_ASSOC);
            $vehicles = $this->conn->query($vehicleQuery)->fetchAll(PDO::FETCH_ASSOC);
    
            // Combine all results
            $result = array_merge($equipment, $venues, $vehicles);
    
            return json_encode([
                'status' => 'success',
                'data' => $result
            ]);
    
        } catch (PDOException $e) {
            return json_encode([
                'status' => 'error',
                'message' => 'Database error: ' . $e->getMessage()
            ]);
        }
    }

    public function updatePassword($userId, $oldPassword, $newPassword){
        try {
            // First, get the user's current password
            $sql = "SELECT users_password FROM tbl_users WHERE users_id = :userId";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute(['userId' => $userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                return json_encode([
                    'status' => 'error',
                    'message' => 'User not found'
                ]);
            }            // Verify old password
            if (!password_verify($oldPassword, $user['users_password'])) {
                return json_encode([
                    'status' => 'error',
                    'message' => 'Current password is incorrect'
                ]);
            }

            // Check if new password is same as old password
            if (password_verify($newPassword, $user['users_password'])) {
                return json_encode([
                    'status' => 'error',
                    'message' => 'New password cannot be the same as current password'
                ]);
            }

            // Hash new password
            $hashedNewPassword = password_hash($newPassword, PASSWORD_DEFAULT);

            // Update password
            $updateSql = "UPDATE tbl_users SET users_password = :newPassword WHERE users_id = :userId";
            $updateStmt = $this->conn->prepare($updateSql);
            $success = $updateStmt->execute([
                'newPassword' => $hashedNewPassword,
                'userId' => $userId
            ]);

            if ($success) {
                return json_encode([
                    'status' => 'success',
                    'message' => 'Password updated successfully'
                ]);
            } else {
                return json_encode([
                    'status' => 'error',
                    'message' => 'Failed to update password'
                ]);
            }

        } catch (PDOException $e) {
            return json_encode([
                'status' => 'error',
                'message' => 'Database error: ' . $e->getMessage()
            ]);
        }
    }

    public function updateProfile($userData){
        try {
            // Define allowed fields to update
            $allowedFields = [
                'users_fname',
                'users_mname',
                'users_lname',
                'users_email',
                'users_school_id',
                'users_contact_number',
                'users_department_id',
                'users_pic'
            ];

            // Build update query dynamically based on provided data
            $updateFields = [];
            $params = [];
            
            foreach ($allowedFields as $field) {
                if (isset($userData[$field])) {
                    $updateFields[] = "$field = :$field";
                    $params[$field] = $userData[$field];
                }
            }

            // Add updated_at timestamp
            $updateFields[] = "users_updated_at = CURRENT_TIMESTAMP";

            // If no fields to update, return error
            if (empty($updateFields)) {
                return json_encode([
                    'status' => 'error',
                    'message' => 'No valid fields to update'
                ]);
            }

            // Add user ID to params
            $params['userId'] = $userData['users_id'];

            // Construct and execute update query
            $sql = "UPDATE tbl_users SET " . implode(', ', $updateFields) . " WHERE users_id = :userId";
            $stmt = $this->conn->prepare($sql);
            $success = $stmt->execute($params);

            if ($success) {
                // Fetch updated user data
                $selectSql = "SELECT users_id, users_fname, users_mname, users_lname, users_email, 
                                    users_school_id, users_contact_number, users_department_id, 
                                    users_pic, users_updated_at 
                             FROM tbl_users 
                             WHERE users_id = :userId";
                $selectStmt = $this->conn->prepare($selectSql);
                $selectStmt->execute(['userId' => $userData['users_id']]);
                $updatedUser = $selectStmt->fetch(PDO::FETCH_ASSOC);

                return json_encode([
                    'status' => 'success',
                    'message' => 'Profile updated successfully',
                    'data' => $updatedUser
                ]);
            } else {
                return json_encode([
                    'status' => 'error',
                    'message' => 'Failed to update profile'
                ]);
            }

        } catch (PDOException $e) {
            return json_encode([
                'status' => 'error',
                'message' => 'Database error: ' . $e->getMessage()
            ]);
        }
    }

}

// Handle the request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get raw input and decode JSON
    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true);
    
    // If JSON parsing failed, try POST data
    if (json_last_error() !== JSON_ERROR_NONE) {
        $input = $_POST;
    }
    
    // Get operation from input
    $operation = $input['operation'] ?? '';
    
    $user = new User();
    
    switch ($operation) {
        case "login":
            echo $user->login($json);
            break;
        case "fetchDepartments":
            echo $user->fetchDepartments($json);
            break;
        case "fetchVehicles":
            echo $user->fetchVehicles();
            break;
        case "fetchPositions":
            echo $user->fetchPositions();
            break;
        case "fetchUserLevels":
            echo $user->fetchUserLevels();
            break;
        case "fetchMake":
            echo $user->fetchMake();
            break;
        case "fetchCategoriesAndModels":
            $makeId = $_POST['make_id'] ?? null;
            echo $user->fetchCategoriesAndModels($makeId);
            break;
        case "fetchAllUser":
            echo $user->fetchAllUserTypes();
            break;
        case "fetchUserProfile":
            $userId = $_POST['userId'] ?? null; 
            echo $user->fetchUserProfile($userId);
            break;
        case "fetchVenue": 
            echo $user->fetchVenue();
            break;
        case "fetchCategories": 
            echo $user->fetchCategories();
            break;
        case "fetchEquipments": 
            echo $user->fetchEquipments();
            break;
        case "fetchEquipmentsWithStatus":
            echo $user->fetchEquipmentsWithStatus();
            break;
        case "fetchEquipmentsByCategory":
            $categoryId = $_POST['category_id'] ?? null; 
            echo $user->fetchEquipmentsByCategory($categoryId);
            break;
        case "fetchAllVehicles":
            echo $user->fetchALlVehicles();
            break;
        case "fetchPersonnel":
            echo $user->fetchPersonnel();
            break;
        case "fetchPersonnelActive":
            echo $user->fetchPersonnelActive();
            break;
        case "fetchSuperAdminByEmail":
            $email = $_POST['email'] ?? null;
            echo $user->fetchUserByEmail($email);
            break;
        case "fetchUserByEmail":
            $email = $input['email'] ?? $_POST['email'] ?? null;
            if ($email) {
                echo $user->fetchUserByEmail($email);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Email is required']);
            }
            break;
        case "fetchUserByEmailOrFullname":
            $searchTerm = $input['searchTerm'] ?? $_POST['searchTerm'] ?? null;
            if ($searchTerm) {
                echo $user->fetchUserByEmailOrFullname($searchTerm);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Search term is required']);
            }
            break;
        case "checkUniqueEmailAndSchoolId":
            $email = $input['email'] ?? '';
            $schoolId = $input['schoolId'] ?? '';
            $excludeId = $input['excludeId'] ?? null;
            $excludeType = $input['excludeType'] ?? null;
            echo $user->checkUniqueEmailAndSchoolId($email, $schoolId, $excludeId, $excludeType);
            break;
        case "fetchDeanSec":
            echo $user->fetchDeanSec();
            break;
        case "fetchAdmin":
            echo $user->fetchAdmin();
            break;
        case "fetchAvailability":
            $itemType = $input['itemType'] ?? '';
            $itemId = $input['itemId'] ?? [];
            $quantities = $input['quantity'] ?? [];
            
            // Initialize empty array for quantities if not dealing with equipment
            $inputQuantities = [];
            if ($itemType === 'equipment' && !empty($quantities)) {
                // Only create the combined array if we have quantities
                $inputQuantities = array_combine($itemId, $quantities);
            }
            
            echo $user->fetchAvailability($itemType, $itemId, $inputQuantities);
            break;
        case "fetchDriver":
            $startDateTime = $input['startDateTime'] ?? null;
            $endDateTime = $input['endDateTime'] ?? null;
            echo $user->fetchDriver($startDateTime, $endDateTime);
            break;
        case "updateUsers":
            echo $user->updateUsers($input);
            break;
        case "fetchAllReservations":
            echo $user->fetchAllReservations();
            break;
        case "getInUse":
            echo $user->getInUse();
            break;
        case "updatePassword":
            $userId = $input['userId'] ?? null;
            $oldPassword = $input['oldPassword'] ?? null;
            $newPassword = $input['newPassword'] ?? null;
            
            if (!$userId || !$oldPassword || !$newPassword) {
                echo json_encode(['status' => 'error', 'message' => 'Missing required parameters']);
                break;
            }
            
            echo $user->updatePassword($userId, $oldPassword, $newPassword);
            break;
        case "updateProfile":
            echo $user->updateProfile($input);
            break;
    
        default:
            echo json_encode(['status' => 'error', 'message' => 'Invalid operation']);
            break;
    }
}
?>
