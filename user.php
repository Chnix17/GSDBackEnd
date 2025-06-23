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
    try {
        $sql = "
            SELECT
                te.equip_id,
                te.equip_name,
                tec.equipments_category_name AS category_name,
                te.is_active,
                te.user_admin_id,
                te.equip_type,
                te.equip_created_at,
                -- Use quantity if available, else count units
                COALESCE(
                    (SELECT SUM(quantity) FROM tbl_equipment_quantity WHERE equip_id = te.equip_id),
                    (SELECT COUNT(*) FROM tbl_equipment_unit WHERE equip_id = te.equip_id AND is_active = 1)
                ) AS total_quantity
            FROM
                tbl_equipments AS te
            INNER JOIN
                tbl_equipment_category AS tec ON te.equipments_category_id = tec.equipments_category_id
            ORDER BY
                te.equip_id
        ";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        $equipments = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return json_encode(['status' => 'success', 'data' => $equipments]);
    } catch (PDOException $e) {
        error_log("Database error in fetchEquipmentsWithStatus: " . $e->getMessage());
        return json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    } catch (Exception $e) {
        error_log("Error in fetchEquipmentsWithStatus: " . $e->getMessage());
        return json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
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
                t.abbreviation AS title_abbreviation,
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
            LEFT JOIN titles t ON u.title_id = t.id
            LEFT JOIN tbl_departments d ON u.users_department_id = d.departments_id
            LEFT JOIN tbl_user_level ul ON u.users_user_level_id = ul.user_level_id 
            WHERE u.is_active = 1
            ORDER BY u.users_id DESC";

    return $this->executeQuery($sql);
}
    public function checkUniqueEmailAndSchoolId($email, $schoolId, $excludeId = null, $excludeType = null) {
        try {
            // Only check in tbl_users
            $sql = "SELECT users_id as id, users_email as email, users_school_id as school_id 
                   FROM tbl_users 
                   WHERE (users_email = :email OR users_school_id = :schoolId)
                   AND is_active = 1";
            
            // Add exclusion if we're updating an existing user
            if ($excludeId) {
                $sql .= " AND users_id != :excludeId";
            }
            
            $stmt = $this->conn->prepare($sql);
            $params = [':email' => $email, ':schoolId' => $schoolId];
            
            if ($excludeId) {
                $params[':excludeId'] = $excludeId;
            }
            
            $stmt->execute($params);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $response = [
                'status' => 'success',
                'exists' => false,
                'duplicates' => []
            ];
            
            if (!empty($results)) {
                $response['exists'] = true;
                foreach ($results as $result) {
                    if ($result['email'] === $email) {
                        $response['duplicates'][] = [
                            'field' => 'email',
                            'value' => $email,
                            'message' => 'Email already exists'
                        ];
                    }
                    if ($result['school_id'] === $schoolId) {
                        $response['duplicates'][] = [
                            'field' => 'school_id',
                            'value' => $schoolId,
                            'message' => 'School ID already exists'
                        ];
                    }
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
                LEFT JOIN tbl_reservation_venue rv
                    ON v.ven_id = rv.reservation_venue_venue_id
                LEFT JOIN tbl_reservation r
                    ON rv.reservation_reservation_id = r.reservation_id
                LEFT JOIN tbl_reservation_status rs
                    ON r.reservation_id = rs.reservation_reservation_id
                WHERE v.ven_id IN ($placeholders)
                  AND (rs.reservation_status_status_id = 6 OR rs.reservation_status_status_id IS NULL)
                  AND rs.reservation_active = 1
                GROUP BY
                    v.ven_id, v.ven_name, v.ven_occupancy,
                    r.reservation_id, r.reservation_title,
                    r.reservation_start_date, r.reservation_end_date,
                    rs.reservation_status_status_id
            ";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($itemIds);

            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($results as &$result) {
                $result['is_available']     = ($result['reservation_status_status_id'] !== 6);
                $result['reservation_status'] = ($result['reservation_status_status_id'] === 6)
                    ? 'Reserved' : 'Available';
            }

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
                LEFT JOIN tbl_vehicle_model vmd
                    ON v.vehicle_model_id = vmd.vehicle_model_id
                LEFT JOIN tbl_vehicle_make vm
                    ON vmd.vehicle_model_vehicle_make_id = vm.vehicle_make_id
                LEFT JOIN tbl_reservation_vehicle rv
                    ON v.vehicle_id = rv.reservation_vehicle_vehicle_id
                LEFT JOIN tbl_reservation r
                    ON rv.reservation_reservation_id = r.reservation_id
                LEFT JOIN tbl_reservation_status rs
                    ON r.reservation_id = rs.reservation_reservation_id
                WHERE v.vehicle_id IN ($placeholders)
                  AND (rs.reservation_status_status_id = 6 OR rs.reservation_status_status_id IS NULL)
                  AND rs.reservation_active = 1
                GROUP BY
                    v.vehicle_id, v.vehicle_license,
                    vm.vehicle_make_name, vmd.vehicle_model_name,
                    r.reservation_id, r.reservation_title,
                    r.reservation_start_date, r.reservation_end_date,
                    rs.reservation_status_status_id
            ";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($itemIds);

            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($results as &$result) {
                $result['is_available']     = ($result['reservation_status_status_id'] !== 6);
                $result['reservation_status'] = ($result['reservation_status_status_id'] === 6)
                    ? 'Reserved' : 'Available';
            }

        } elseif ($itemType === 'equipment') {
            $sql = "
                SELECT
                    e.equip_id,
                    e.equip_name,
                    e.equip_type, -- Need equip_type to differentiate consumable vs unit-based
                    tec.equipments_category_name AS category_name, -- Include category name from join

                    /* Total on hand: Conditionally based on equip_type */
                    CASE
                        WHEN e.equip_type = 'Consumable' THEN COALESCE(eq.quantity, 0)
                        ELSE COALESCE(eu.unit_count, 0)
                    END AS current_quantity,

                    /* Total reserved */
                    COALESCE(r.reserved_qty, 0) AS total_reserved,

                    /* Available = on hand - reserved */
                    (
                        CASE
                            WHEN e.equip_type = 'Consumable' THEN COALESCE(eq.quantity, 0)
                            ELSE COALESCE(eu.unit_count, 0)
                        END
                        - COALESCE(r.reserved_qty, 0)
                    ) AS total_available,

                    /* Reservation window */
                    r.min_start_date    AS reservation_start_date,
                    r.max_end_date      AS reservation_end_date,

         
                    e.equip_created_at,
                    -- e.status_availability_id, -- Removed, as status is derived from availability
                    -- sa.status_availability_name, -- Removed, as status is derived from availability
                    e.equipments_category_id -- Use the correct column name from tbl_equipments
                FROM tbl_equipments e

                -- Join for Consumable quantities (most recent quantity)
                LEFT JOIN (
                    SELECT
                        equip_id,
                        quantity
                    FROM tbl_equipment_quantity
                    WHERE (equip_id, last_updated) IN (
                        SELECT equip_id, MAX(last_updated)
                        FROM tbl_equipment_quantity
                        GROUP BY equip_id
                    )
                ) AS eq ON e.equip_id = eq.equip_id AND e.equip_type = 'Consumable'

                -- Join for Unit-based equipment (count active units)
                LEFT JOIN (
                    SELECT
                        equip_id,
                        COUNT(*) AS unit_count
                    FROM tbl_equipment_unit
                    WHERE is_active = 1 -- Only count active units
                    GROUP BY equip_id
                ) AS eu ON e.equip_id = eu.equip_id AND e.equip_type != 'Consumable'

                /* Sub-query: sum reserved quantities + dates */
                LEFT JOIN (
                    SELECT
                        re.reservation_equipment_equip_id   AS equip_id,
                        SUM(re.reservation_equipment_quantity) AS reserved_qty,
                        MIN(r.reservation_start_date)       AS min_start_date,
                        MAX(r.reservation_end_date)         AS max_end_date
                    FROM tbl_reservation_equipment re
                    JOIN tbl_reservation r
                      ON r.reservation_id = re.reservation_reservation_id
                    JOIN tbl_reservation_status rs
                      ON rs.reservation_reservation_id = r.reservation_id
                      AND rs.reservation_active = 1
                      AND rs.reservation_status_status_id = 6 -- Only consider 'Reserved' status (assuming 6 is Reserved)
                    WHERE re.reservation_equipment_equip_id IN ($placeholders)
                    GROUP BY re.reservation_equipment_equip_id
                ) AS r
                    ON e.equip_id = r.equip_id

                /* Lookups */
                -- LEFT JOIN tbl_status_availability sa -- Not needed if availability is calculated
                --    ON e.status_availability_id = sa.status_availability_id
                LEFT JOIN tbl_equipment_category tec
                    ON e.equipments_category_id = tec.equipments_category_id -- Use correct column name for join

                WHERE e.equip_id IN ($placeholders)
                ORDER BY e.equip_name
            ";            // Create an array with enough parameters for all placeholders
            $params = array_merge(
                $itemIds, // For the first IN clause in subquery
                $itemIds  // For the second IN clause in main query
            );
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Add inputted_quantity without altering total_available
            foreach ($results as &$result) {
                $eid = $result['equip_id'];
                $result['inputted_quantity'] = isset($inputQuantities[$eid])
                    ? (int)$inputQuantities[$eid]
                    : 0;

                // Determine overall availability status
                if ($result['total_available'] > 0) {
                    $result['is_available'] = true;
                    $result['availability_status'] = 'Available';
                } else {
                    $result['is_available'] = false;
                    $result['availability_status'] = 'Unavailable';
                }
            }
        }

        return json_encode([
            'status' => 'success',
            'data'   => $results
        ]);
    } catch (PDOException $e) {
        error_log("Database error in fetchAvailability: " . $e->getMessage());
        return json_encode([
            'status'  => 'error',
            'message' => 'Database error: ' . $e->getMessage()
        ]);
    } catch (Exception $e) {
        error_log("General error in fetchAvailability: " . $e->getMessage());
        return json_encode([
            'status'  => 'error',
            'message' => 'An unexpected error occurred: ' . $e->getMessage()
        ]);
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

    public function fetchTitle() {
        $sql = "SELECT id, abbreviation FROM titles WHERE 1";
        return $this->executeQuery($sql);
    }

    public function getUnitById($unitId) {
        $sql = "SELECT 
                    eu.unit_id,
                    eu.equip_id,
                    eu.serial_number,
                    eu.status_availability_id,
                    sa.status_availability_name,
                    e.equip_name,
                    e.equip_pic,
                    e.equipment_equipment_category_id,
                    ec.equipments_category_name
                FROM 
                    tbl_equipment_unit eu
                LEFT JOIN 
                    tbl_status_availability sa ON eu.status_availability_id = sa.status_availability_id
                LEFT JOIN 
                    tbl_equipments e ON eu.equip_id = e.equip_id
                LEFT JOIN 
                    tbl_equipment_category ec ON e.equipment_equipment_category_id = ec.equipments_category_id
                WHERE 
                    eu.unit_id = :unitId";

        return $this->executeQuery($sql, [':unitId' => $unitId]);
    }

    public function fetchEquipmentName() {
        $sql = "SELECT 
                    equip_id, 
                    equip_name,
                    category_name
                FROM 
                    tbl_equipments 
                WHERE 1";
        
        return $this->executeQuery($sql);
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

    public function getVehicleUsage($vehicleId) {
    try {
        // First, get the vehicle details
        $vehicleSql = "
            SELECT 
                v.vehicle_id,
                v.vehicle_license,
                v.year,
                v.vehicle_pic,
                v.is_active,
                v.created_at,
                v.updated_at,
                vm.vehicle_model_name,
                vmk.vehicle_make_name,
                vc.vehicle_category_name,
                sa.status_availability_name
            FROM tbl_vehicle v
            INNER JOIN tbl_vehicle_model vm ON v.vehicle_model_id = vm.vehicle_model_id
            INNER JOIN tbl_vehicle_make vmk ON vm.vehicle_model_vehicle_make_id = vmk.vehicle_make_id
            INNER JOIN tbl_vehicle_category vc ON vm.vehicle_category_id = vc.vehicle_category_id
            INNER JOIN tbl_status_availability sa ON v.status_availability_id = sa.status_availability_id
            WHERE v.vehicle_id = :vehicleId
        ";
        
        $stmt = $this->conn->prepare($vehicleSql);
        $stmt->execute([':vehicleId' => $vehicleId]);
        $vehicleDetails = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$vehicleDetails) {
            return json_encode(['status' => 'error', 'message' => 'Vehicle not found']);
        }
        
        // Get all reservations for this vehicle with status_id = 6 and active = 0
        $reservationsSql = "
            SELECT 
                r.reservation_id,
                r.reservation_title,
                r.reservation_description,
                r.reservation_start_date,
                r.reservation_end_date,
                r.reservation_participants,
                CONCAT(u.users_fname, ' ', u.users_lname) AS reserved_by,
                rs.reservation_status_status_id,
                sm.status_master_name AS reservation_status,
                rv.reservation_vehicle_id,
                rv.active AS reservation_vehicle_active,
                c.condition_name,
                c.id AS condition_id
            FROM tbl_reservation_vehicle rv
            INNER JOIN tbl_reservation r ON rv.reservation_reservation_id = r.reservation_id
            LEFT JOIN tbl_users u ON r.reservation_user_id = u.users_id
            LEFT JOIN tbl_reservation_status rs ON r.reservation_id = rs.reservation_reservation_id
                AND rs.reservation_status_status_id = 6
                AND rs.reservation_active = 0
            LEFT JOIN tbl_status_master sm ON rs.reservation_status_status_id = sm.status_master_id
            LEFT JOIN tbl_reservation_condition_vehicle rcv ON rcv.reservation_vehicle_id = rv.reservation_vehicle_id
            LEFT JOIN tbl_condition c ON rcv.condition_id = c.id
            WHERE rv.reservation_vehicle_vehicle_id = :vehicleId
            ORDER BY r.reservation_start_date DESC

        ";
        
        $stmt = $this->conn->prepare($reservationsSql);
        $stmt->execute([':vehicleId' => $vehicleId]);
        $reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Calculate statistics
        $totalUsage = count($reservations);
        $brokenCount = 0;
        $missingCount = 0;
        
        foreach ($reservations as $reservation) {
            if ($reservation['condition_id'] == 4) {
                $brokenCount++;
            } elseif ($reservation['condition_id'] == 3) {
                $missingCount++;
            }
        }
        
        // Prepare the response
        $response = [
            'status' => 'success',
            'data' => [
                'vehicle_details' => $vehicleDetails,
                'usage_statistics' => [
                    'total_usage' => $totalUsage,
                    'broken_count' => $brokenCount,
                    'missing_count' => $missingCount,
                    'good_condition_count' => $totalUsage - ($brokenCount + $missingCount)
                ],
                'reservations' => $reservations
            ]
        ];
        
        return json_encode($response);
        
    } catch (PDOException $e) {
        return json_encode([
            'status' => 'error',
            'message' => 'Database error: ' . $e->getMessage()
        ]);
    }
}

public function getVenueUsage($venueId) {
    try {
        // Fetch venue details
        $venueSql = "
            SELECT 
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
            LEFT JOIN tbl_status_availability sa ON v.status_availability_id = sa.status_availability_id
            WHERE v.ven_id = :venueId
        ";

        $stmt = $this->conn->prepare($venueSql);
        $stmt->execute([':venueId' => $venueId]);
        $venueDetails = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$venueDetails) {
            return json_encode(['status' => 'error', 'message' => 'Venue not found']);
        }

        // Fetch reservations using this venue (status_id = 6, active = 0)
        $reservationsSql = "
            SELECT 
                r.reservation_id,
                r.reservation_title,
                r.reservation_description,
                r.reservation_start_date,
                r.reservation_end_date,
                r.reservation_participants,
                CONCAT(u.users_fname, ' ', u.users_lname) AS reserved_by,
                rs.reservation_status_status_id,
                sm.status_master_name AS reservation_status,
                rv.reservation_venue_id,
                rv.active AS reservation_venue_active,
                c.condition_name,
                c.id AS condition_id
            FROM tbl_reservation_venue rv
            INNER JOIN tbl_reservation r ON rv.reservation_reservation_id = r.reservation_id
            LEFT JOIN tbl_users u ON r.reservation_user_id = u.users_id
            LEFT JOIN tbl_reservation_status rs ON r.reservation_id = rs.reservation_reservation_id
                AND rs.reservation_status_status_id = 6
                AND rs.reservation_active = 0
            LEFT JOIN tbl_status_master sm ON rs.reservation_status_status_id = sm.status_master_id
            LEFT JOIN tbl_reservation_condition_venue rcv ON rcv.reservation_venue_id = rv.reservation_venue_id
            LEFT JOIN tbl_condition c ON rcv.condition_id = c.id
            WHERE rv.reservation_venue_venue_id = :venueId
            ORDER BY r.reservation_start_date DESC
        ";

        $stmt = $this->conn->prepare($reservationsSql);
        $stmt->execute([':venueId' => $venueId]);
        $reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Count conditions
        $totalUsage = count($reservations);
        $brokenCount = 0;
        $missingCount = 0;

        foreach ($reservations as $reservation) {
            if ($reservation['condition_id'] == 4) {
                $brokenCount++;
            } elseif ($reservation['condition_id'] == 3) {
                $missingCount++;
            }
        }

        // Prepare the response
        $response = [
            'status' => 'success',
            'data' => [
                'venue_details' => $venueDetails,
                'usage_statistics' => [
                    'total_usage' => $totalUsage,
                    'broken_count' => $brokenCount,
                    'missing_count' => $missingCount,
                    'good_condition_count' => $totalUsage - ($brokenCount + $missingCount)
                ],
                'reservations' => $reservations
            ]
        ];

        return json_encode($response);

    } catch (PDOException $e) {
        return json_encode([
            'status' => 'error',
            'message' => 'Database error: ' . $e->getMessage()
        ]);
    }
}

public function getEquipmentUnitUsage($unitId) {
    try {
        // Fetch unit and equipment details with category
        $unitSql = "
            SELECT 
                eu.unit_id,
                eu.serial_number,
                eu.brand,
                eu.size,
                eu.color,
                eu.status_availability_id,
                sa.status_availability_name,
                eu.unit_created_at,
                eu.user_admin_id,
                CONCAT(u.users_fname, ' ', 
                    IFNULL(CONCAT(u.users_mname, ' '), ''), 
                    u.users_lname, 
                    IFNULL(CONCAT(' ', u.users_suffix), '')
                ) AS admin_full_name,
                e.equip_name,
                e.equip_type,
                e.equip_created_at,
                ec.equipments_category_name
            FROM tbl_equipment_unit eu
            INNER JOIN tbl_equipments e ON eu.equip_id = e.equip_id
            LEFT JOIN tbl_equipment_category ec ON e.equipments_category_id = ec.equipments_category_id
            LEFT JOIN tbl_users u ON eu.user_admin_id = u.users_id
            LEFT JOIN tbl_status_availability sa ON eu.status_availability_id = sa.status_availability_id
            WHERE eu.unit_id = :unitId
        ";



        $stmt = $this->conn->prepare($unitSql);
        $stmt->execute([':unitId' => $unitId]);
        $unitDetails = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$unitDetails) {
            return json_encode(['status' => 'error', 'message' => 'Equipment unit not found']);
        }

        // Fetch reservations for this unit
        $reservationsSql = "
            SELECT 
                r.reservation_id,
                r.reservation_title,
                r.reservation_description,
                r.reservation_start_date,
                r.reservation_end_date,
                r.reservation_participants,
                CONCAT(u.users_fname, ' ', u.users_lname) AS reserved_by,
                rs.reservation_status_status_id,
                sm.status_master_name AS reservation_status,
                ru.reservation_unit_id,
                ru.active AS reservation_unit_active,
                c.condition_name,
                c.id AS condition_id
            FROM tbl_reservation_unit ru
            INNER JOIN tbl_reservation_equipment re ON ru.reservation_equipment_id = re.reservation_equipment_id
            INNER JOIN tbl_reservation r ON re.reservation_reservation_id = r.reservation_id
            LEFT JOIN tbl_users u ON r.reservation_user_id = u.users_id
            LEFT JOIN tbl_reservation_status rs ON r.reservation_id = rs.reservation_reservation_id
                AND rs.reservation_status_status_id = 6
                AND rs.reservation_active = 0
            LEFT JOIN tbl_status_master sm ON rs.reservation_status_status_id = sm.status_master_id
            LEFT JOIN tbl_reservation_condition_unit rcu ON rcu.reservation_unit_id = ru.reservation_unit_id
            LEFT JOIN tbl_condition c ON rcu.condition_id = c.id
            WHERE ru.unit_id = :unitId
            ORDER BY r.reservation_start_date DESC
        ";

        $stmt = $this->conn->prepare($reservationsSql);
        $stmt->execute([':unitId' => $unitId]);
        $reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Count conditions
        $totalUsage = count($reservations);
        $brokenCount = 0;
        $missingCount = 0;

        foreach ($reservations as $reservation) {
            if ($reservation['condition_id'] == 4) {
                $brokenCount++;
            } elseif ($reservation['condition_id'] == 3) {
                $missingCount++;
            }
        }

        // Response
        $response = [
            'status' => 'success',
            'data' => [
                'equipment_unit_details' => $unitDetails,
                'usage_statistics' => [
                    'total_usage' => $totalUsage,
                    'broken_count' => $brokenCount,
                    'missing_count' => $missingCount,
                    'good_condition_count' => $totalUsage - ($brokenCount + $missingCount)
                ],
                'reservations' => $reservations
            ]
        ];

        return json_encode($response);

    } catch (PDOException $e) {
        return json_encode([
            'status' => 'error',
            'message' => 'Database error: ' . $e->getMessage()
        ]);
    }
}

public function getConsumableUsage($equipId) {
    try {
        // Fetch equipment details
        $equipmentSql = "
            SELECT 
                e.equip_id,
                e.equip_name,
                e.equip_type,
                e.equip_created_at,
                e.equipments_category_id,
                e.is_active,
                e.user_admin_id
            FROM tbl_equipments e
            WHERE e.equip_id = :equipId
        ";

        $stmt = $this->conn->prepare($equipmentSql);
        $stmt->execute([':equipId' => $equipId]);
        $equipmentDetails = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$equipmentDetails) {
            return json_encode(['status' => 'error', 'message' => 'Consumable equipment not found']);
        }

        // Fetch reservations of this equipment
        $reservationsSql = "
            SELECT 
                r.reservation_id,
                r.reservation_title,
                r.reservation_description,
                r.reservation_start_date,
                r.reservation_end_date,
                r.reservation_participants,
                CONCAT(u.users_fname, ' ', u.users_lname) AS reserved_by,
                rs.reservation_status_status_id,
                sm.status_master_name AS reservation_status,
                re.reservation_equipment_id,
                re.reservation_equipment_quantity,
                re.active AS reservation_equipment_active,
                rce.qty_bad
            FROM tbl_reservation_equipment re
            INNER JOIN tbl_reservation r ON re.reservation_reservation_id = r.reservation_id
            LEFT JOIN tbl_users u ON r.reservation_user_id = u.users_id
            LEFT JOIN tbl_reservation_status rs ON r.reservation_id = rs.reservation_reservation_id
                AND rs.reservation_status_status_id = 6
                AND rs.reservation_active = 0
            LEFT JOIN tbl_status_master sm ON rs.reservation_status_status_id = sm.status_master_id
            LEFT JOIN tbl_reservation_condition_equipment rce ON rce.reservation_equipment_id = re.reservation_equipment_id
            WHERE re.reservation_equipment_equip_id = :equipId
            ORDER BY r.reservation_start_date DESC
        ";

        $stmt = $this->conn->prepare($reservationsSql);
        $stmt->execute([':equipId' => $equipId]);
        $reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Calculate statistics
        $totalUsage = 0;
        $totalQtyBad = 0;

        foreach ($reservations as $reservation) {
            $totalUsage += (int) $reservation['reservation_equipment_quantity'];
            $totalQtyBad += (int) $reservation['qty_bad'];
        }

        // Response
        $response = [
            'status' => 'success',
            'data' => [
                'equipment_details' => $equipmentDetails,
                'usage_statistics' => [
                    'total_usage_quantity' => $totalUsage,
                    'total_qty_bad' => $totalQtyBad,
                    'total_good_quantity' => $totalUsage - $totalQtyBad
                ],
                'reservations' => $reservations
            ]
        ];

        return json_encode($response);

    } catch (PDOException $e) {
        return json_encode([
            'status' => 'error',
            'message' => 'Database error: ' . $e->getMessage()
        ]);
    }
}

public function fetchAllAssignedReleases() {
    try {
        $reservations = [];

        // 1) Venue checklist items
        $sqlVenue = "
            SELECT
                rc_venue.checklist_venue_id,
                rc_venue.reservation_checklist_venue_id,
                rc_venue.isChecked AS venue_isChecked,
                rc_venue.personnel_id AS venue_personnel_id,
                rv.reservation_reservation_id,
                rv.reservation_venue_id,
                rv.reservation_venue_venue_id,
                rv.active AS venue_active,
                v.ven_name AS venue_name,
                cvc.checklist_name AS checklist_venue_name,
                tsa.status_availability_name AS venue_availability_status_name
            FROM tbl_reservation_checklist_venue rc_venue
            INNER JOIN tbl_reservation_venue rv
                ON rc_venue.reservation_venue_id = rv.reservation_venue_id
            LEFT JOIN tbl_venue v
                ON rv.reservation_venue_venue_id = v.ven_id
            LEFT JOIN tbl_checklist_venue_master cvc
                ON rc_venue.checklist_venue_id = cvc.checklist_venue_id
            INNER JOIN tbl_reservation r
                ON rv.reservation_reservation_id = r.reservation_id
            INNER JOIN tbl_reservation_status rs
                ON r.reservation_id = rs.reservation_reservation_id
            LEFT JOIN tbl_status_availability tsa
                ON v.status_availability_id = tsa.status_availability_id
            WHERE rs.reservation_status_status_id = 6
              AND rs.reservation_active = 1
        ";
        $stmtVenue = $this->conn->query($sqlVenue);
        $venueData = $stmtVenue->fetchAll(PDO::FETCH_ASSOC);

        // 2) Vehicle checklist items
        $sqlVehicle = "
            SELECT
                rc_vehicle.checklist_vehicle_id,
                rc_vehicle.reservation_checklist_vehicle_id,
                rc_vehicle.isChecked AS vehicle_isChecked,
                rc_vehicle.personnel_id AS vehicle_personnel_id,
                rv.reservation_reservation_id,
                rv.reservation_vehicle_id,
                rv.reservation_vehicle_vehicle_id,
                rv.active AS vehicle_active,
                vm.vehicle_license,
                vm.vehicle_model_id,
                cvcv.checklist_name AS checklist_vehicle_name,
                tsa.status_availability_name AS vehicle_availability_status_name
            FROM tbl_reservation_checklist_vehicle rc_vehicle
            INNER JOIN tbl_reservation_vehicle rv
                ON rc_vehicle.reservation_vehicle_id = rv.reservation_vehicle_id
            LEFT JOIN tbl_vehicle vm
                ON rv.reservation_vehicle_vehicle_id = vm.vehicle_id
            LEFT JOIN tbl_checklist_vehicle_master cvcv
                ON rc_vehicle.checklist_vehicle_id = cvcv.checklist_vehicle_id
            INNER JOIN tbl_reservation r
                ON rv.reservation_reservation_id = r.reservation_id
            INNER JOIN tbl_reservation_status rs
                ON r.reservation_id = rs.reservation_reservation_id
            LEFT JOIN tbl_status_availability tsa
                ON vm.status_availability_id = tsa.status_availability_id
            WHERE rs.reservation_status_status_id = 6
              AND rs.reservation_active = 1
        ";
        $stmtVehicle = $this->conn->query($sqlVehicle);
        $vehicleData = $stmtVehicle->fetchAll(PDO::FETCH_ASSOC);

        // 3) Equipment checklist items
        $sqlEquipment = "
            SELECT  
                rc_equipment.checklist_equipment_id,
                rc_equipment.reservation_checklist_equipment_id,
                rc_equipment.isChecked AS equipment_isChecked,
                rc_equipment.personnel_id AS equipment_personnel_id,
                re.reservation_reservation_id,
                re.reservation_equipment_id,
                re.reservation_equipment_equip_id,
                re.reservation_equipment_quantity AS quantity,
                re.active AS equipment_active,
                e.equip_name,
                cvce.checklist_name AS checklist_equipment_name,
                eq.quantity_id,
                tsa.status_availability_name AS equipment_availability_status_name
            FROM tbl_reservation_checklist_equipment rc_equipment
            INNER JOIN tbl_reservation_equipment re
                ON rc_equipment.reservation_equipment_id = re.reservation_equipment_id
            LEFT JOIN tbl_equipments e
                ON re.reservation_equipment_equip_id = e.equip_id
            LEFT JOIN tbl_checklist_equipment_master cvce
                ON rc_equipment.checklist_equipment_id = cvce.checklist_equipment_id
            INNER JOIN tbl_reservation r
                ON re.reservation_reservation_id = r.reservation_id
            INNER JOIN tbl_reservation_status rs
                ON r.reservation_id = rs.reservation_reservation_id
            LEFT JOIN tbl_equipment_quantity eq
                ON re.reservation_equipment_equip_id = eq.equip_id
            LEFT JOIN tbl_status_availability tsa
                ON eq.status_availability_id = tsa.status_availability_id
            WHERE rs.reservation_status_status_id = 6
              AND rs.reservation_active = 1
        ";
        $stmtEquipment = $this->conn->query($sqlEquipment);
        $equipmentData = $stmtEquipment->fetchAll(PDO::FETCH_ASSOC);

        // Get all personnel IDs from all checklist items
        $personnelIds = [];
        foreach (array_merge($venueData, $vehicleData, $equipmentData) as $row) {
            if (!empty($row['venue_personnel_id'])) {
                $personnelIds[] = $row['venue_personnel_id'];
            }
            if (!empty($row['vehicle_personnel_id'])) {
                $personnelIds[] = $row['vehicle_personnel_id'];
            }
            if (!empty($row['equipment_personnel_id'])) {
                $personnelIds[] = $row['equipment_personnel_id'];
            }
        }
        
        // Remove duplicates and empty values
        $personnelIds = array_unique(array_filter($personnelIds));
        
        // Fetch personnel names in a single query
        $personnelNames = [];
        if (!empty($personnelIds)) {
            $placeholders = implode(',', array_fill(0, count($personnelIds), '?'));
            $sqlPersonnel = "
                SELECT 
                    users_id,
                    CONCAT(users_fname, ' ', COALESCE(users_mname, ''), ' ', users_lname) AS full_name
                FROM tbl_users
                WHERE users_id IN ($placeholders)
            ";
            $stmtPersonnel = $this->conn->prepare($sqlPersonnel);
            $stmtPersonnel->execute($personnelIds);
            $personnelData = $stmtPersonnel->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($personnelData as $person) {
                $personnelNames[$person['users_id']] = $person['full_name'];
            }
        }

        foreach (array_merge($venueData, $vehicleData, $equipmentData) as $row) {
            $rid = $row['reservation_reservation_id'];
            if (!isset($reservations[$rid])) {
                $reservations[$rid] = [
                    'reservation_id'          => $rid,
                    'reservation_title'       => '',
                    'reservation_description' => '',
                    'reservation_start_date'  => '',
                    'reservation_end_date'    => '',
                    'reservation_participants'=> '',
                    'reservation_user_id'     => '',
                    'user_details'            => [],
                    'venues'                  => [],
                    'vehicles'                => [],
                    'equipments'              => [],
                ];
            }
            // VENUE
            if (isset($row['reservation_venue_id'])) {
                $found = false;
                foreach ($reservations[$rid]['venues'] as &$v) {
                    if ($v['reservation_venue_id'] == $row['reservation_venue_id']) {
                        $v['checklists'][] = [
                            'checklist_venue_id'             => $row['checklist_venue_id'],
                            'reservation_checklist_venue_id' => $row['reservation_checklist_venue_id'],
                            'checklist_name'                 => $row['checklist_venue_name'],
                            'isChecked'                      => (int)$row['venue_isChecked'],
                            'personnel_id'                   => $row['venue_personnel_id'],
                            'personnel_name'                 => $personnelNames[$row['venue_personnel_id']] ?? 'N/A'
                        ];
                        $found = true;
                        break;
                    }
                }
                if (!$found) {
                    $reservations[$rid]['venues'][] = [
                        'reservation_venue_id'       => $row['reservation_venue_id'],
                        'reservation_venue_venue_id' => $row['reservation_venue_venue_id'],
                        'name'                       => $row['venue_name'],
                        'availability_status'        => $row['venue_availability_status_name'],
                        'active'                     => (int)$row['venue_active'],
                        'checklists'                 => [[
                            'checklist_venue_id'             => $row['checklist_venue_id'],
                            'reservation_checklist_venue_id' => $row['reservation_checklist_venue_id'],
                            'checklist_name'                 => $row['checklist_venue_name'],
                            'isChecked'                      => (int)$row['venue_isChecked'],
                            'personnel_id'                   => $row['venue_personnel_id'],
                            'personnel_name'                 => $personnelNames[$row['venue_personnel_id']] ?? 'N/A'
                        ]]
                    ];
                }
            }
            // VEHICLE
            if (isset($row['reservation_vehicle_id'])) {
                $found = false;
                foreach ($reservations[$rid]['vehicles'] as &$v) {
                    if ($v['reservation_vehicle_id'] == $row['reservation_vehicle_id']) {
                        $v['checklists'][] = [
                            'checklist_vehicle_id'             => $row['checklist_vehicle_id'],
                            'reservation_checklist_vehicle_id' => $row['reservation_checklist_vehicle_id'],
                            'checklist_name'                   => $row['checklist_vehicle_name'],
                            'isChecked'                        => (int)$row['vehicle_isChecked'],
                            'personnel_id'                     => $row['vehicle_personnel_id'],
                            'personnel_name'                   => $personnelNames[$row['vehicle_personnel_id']] ?? 'N/A'
                        ];
                        $found = true;
                        break;
                    }
                }
                if (!$found) {
                    $reservations[$rid]['vehicles'][] = [
                        'reservation_vehicle_id'       => $row['reservation_vehicle_id'],
                        'reservation_vehicle_vehicle_id' => $row['reservation_vehicle_vehicle_id'],
                        'vehicle_license'                => $row['vehicle_license'],
                        'vehicle_model_id'               => $row['vehicle_model_id'],
                        'availability_status'            => $row['vehicle_availability_status_name'],
                        'active'                         => (int)$row['vehicle_active'],
                        'checklists'                     => [[
                            'checklist_vehicle_id'             => $row['checklist_vehicle_id'],
                            'reservation_checklist_vehicle_id' => $row['reservation_checklist_vehicle_id'],
                            'checklist_name'                   => $row['checklist_vehicle_name'],
                            'isChecked'                        => (int)$row['vehicle_isChecked'],
                            'personnel_id'                     => $row['vehicle_personnel_id'],
                            'personnel_name'                   => $personnelNames[$row['vehicle_personnel_id']] ?? 'N/A'
                        ]]
                    ];
                }
            }
            // EQUIPMENT
            if (isset($row['reservation_equipment_id'])) {
                $found = false;
                foreach ($reservations[$rid]['equipments'] as &$e) {
                    if ($e['reservation_equipment_id'] == $row['reservation_equipment_id']) {
                        $e['checklists'][] = [
                            'checklist_equipment_id'             => $row['checklist_equipment_id'],
                            'reservation_checklist_equipment_id' => $row['reservation_checklist_equipment_id'],
                            'checklist_name'                     => $row['checklist_equipment_name'],
                            'isChecked'                          => (int)$row['equipment_isChecked'],
                            'personnel_id'                       => $row['equipment_personnel_id'],
                            'personnel_name'                     => $personnelNames[$row['equipment_personnel_id']] ?? 'N/A'
                        ];
                        $found = true;
                        break;
                    }
                }
                if (!$found) {
                    $reservations[$rid]['equipments'][] = [
                        'reservation_equipment_id'       => $row['reservation_equipment_id'],
                        'reservation_equipment_equip_id' => $row['reservation_equipment_equip_id'],
                        'name'                           => $row['equip_name'],
                        'quantity'                       => $row['quantity'],
                        'quantity_id'                    => $row['quantity_id'],
                        'units'                          => [], // will fill below
                        'availability_status'            => $row['equipment_availability_status_name'],
                        'active'                         => (int)$row['equipment_active'],
                        'checklists'                     => [[
                            'checklist_equipment_id'             => $row['checklist_equipment_id'],
                            'reservation_checklist_equipment_id' => $row['reservation_checklist_equipment_id'],
                            'checklist_name'                     => $row['checklist_equipment_name'],
                            'isChecked'                          => (int)$row['equipment_isChecked'],
                            'personnel_id'                       => $row['equipment_personnel_id'],
                            'personnel_name'                     => $personnelNames[$row['equipment_personnel_id']] ?? 'N/A'
                        ]]
                    ];
                }
            }
        }

        // 4) Fetch & merge reservation units
        $allEqIds = [];
        foreach ($reservations as $res) {
            foreach ($res['equipments'] as $eq) {
                $allEqIds[] = $eq['reservation_equipment_id'];
            }
        }

        if (!empty($allEqIds)) {
            $placeholders = implode(',', array_fill(0, count($allEqIds), '?'));
            $sqlUnits = "
                SELECT
                    ru.reservation_unit_id,
                    ru.reservation_equipment_id,
                    ru.unit_id,
                    ru.active AS unit_active,
                    eu.serial_number AS unit_serial_number,
                    tsa.status_availability_name AS unit_availability_status_name
                FROM tbl_reservation_unit ru
                LEFT JOIN tbl_equipment_unit eu
                    ON ru.unit_id = eu.unit_id
                LEFT JOIN tbl_status_availability tsa
                    ON eu.status_availability_id = tsa.status_availability_id
                WHERE ru.reservation_equipment_id IN ($placeholders)
            ";
            $stmtUnits = $this->conn->prepare($sqlUnits);
            $stmtUnits->execute($allEqIds);
            $unitsData = $stmtUnits->fetchAll(PDO::FETCH_ASSOC);

            // group by reservation_equipment_id
            $unitsByResEquip = [];
            foreach ($unitsData as $u) {
                $unitsByResEquip[$u['reservation_equipment_id']][] = [
                    'reservation_unit_id' => $u['reservation_unit_id'],
                    'unit_id'             => $u['unit_id'],
                    'unit_serial_number'  => $u['unit_serial_number'],
                    'availability_status' => $u['unit_availability_status_name'],
                    'active'              => (int)$u['unit_active'],
                ];
            }

            // inject into reservations
            foreach ($reservations as &$res) {
                foreach ($res['equipments'] as &$eq) {
                    $rid = $eq['reservation_equipment_id'];
                    $eq['units'] = $unitsByResEquip[$rid] ?? [];
                }
            }
            unset($eq, $res);
        }

        // 5) Fetch reservation header & user info
        foreach ($reservations as $rid => &$res) {
            $sqlR = "
                SELECT
                    r.reservation_title,
                    r.reservation_description,
                    r.reservation_start_date,
                    r.reservation_end_date,
                    r.reservation_participants,
                    r.reservation_user_id,
                    u.users_fname,
                    u.users_mname,
                    u.users_lname,
                    d.departments_name,
                    ul.user_level_name AS role
                FROM tbl_reservation r
                INNER JOIN tbl_users u
                    ON r.reservation_user_id = u.users_id
                LEFT JOIN tbl_departments d
                    ON u.users_department_id = d.departments_id
                LEFT JOIN tbl_user_level ul
                    ON u.users_user_level_id = ul.user_level_id
                WHERE r.reservation_id = :rid
            ";
            $st  = $this->conn->prepare($sqlR);
            $st->execute(['rid' => $rid]);
            $hdr = $st->fetch(PDO::FETCH_ASSOC);
            if ($hdr) {
                $res['reservation_title']        = $hdr['reservation_title'];
                $res['reservation_description']  = $hdr['reservation_description'];
                $res['reservation_start_date']   = $hdr['reservation_start_date'];
                $res['reservation_end_date']     = $hdr['reservation_end_date'];
                $res['reservation_participants'] = $hdr['reservation_participants'];
                $res['reservation_user_id']      = $hdr['reservation_user_id'];

                $fullName = trim(
                    $hdr['users_fname'] . ' ' .
                    (!empty($hdr['users_mname']) ? $hdr['users_mname'].' ' : '') .
                    $hdr['users_lname']
                );
                $res['user_details'] = [
                    'full_name'  => $fullName,
                    'department' => $hdr['departments_name'] ?? 'N/A',
                    'role'       => $hdr['role']           ?? 'N/A'
                ];
            }
        }
        unset($res); // Unset the last reference

        // Filter reservations that have associated items
        $filteredReservations = array_filter($reservations, function($res) {
            return !empty($res['venues']) || !empty($res['vehicles']) || !empty($res['equipments']);
        });

        // Final return
        return json_encode([
            'status' => 'success',
            'data'   => array_values($reservations)
        ]);

    } catch (PDOException $e) {
        error_log("Error in fetchAllAssignedReleases: " . $e->getMessage());
        return json_encode([
            'status' => 'error',
            'message' => 'An error occurred while fetching releases.'
        ]);
    }
}

public function displayedMaintenanceResourcesDone() {
    try {
        $records = [];

        // 1) Equipment (consumable) under maintenance — display qty_bad
        $sql = "
            SELECT 
                rce.id                             AS record_id,
                'equipment_consumable'            AS resource_type,
                e.equip_name                       AS resource_name,
                rce.qty_bad                        AS quantity,
                re.reservation_equipment_equip_id  AS resource_id,
                c.condition_name                   AS condition_name,
                rce.remarks                        AS remarks
            FROM tbl_reservation_condition_equipment rce
            JOIN tbl_reservation_equipment     re ON rce.reservation_equipment_id = re.reservation_equipment_id
            JOIN tbl_equipments                e  ON re.reservation_equipment_equip_id = e.equip_id
            JOIN tbl_condition                 c  ON rce.condition_id = c.id
            WHERE rce.condition_id != 2
              AND rce.is_active = 0
        ";
        foreach ($this->conn->query($sql, PDO::FETCH_ASSOC) as $row) {
            $records[] = $row;
        }

        // 2) Venues under maintenance
        $sql = "
            SELECT 
                rcv.id                            AS record_id,
                'venue'                           AS resource_type,
                v.ven_name                        AS resource_name,
                NULL                              AS quantity,
                rv.reservation_venue_venue_id     AS resource_id,
                c.condition_name                  AS condition_name,
                rcv.remarks                       AS remarks
            FROM tbl_reservation_condition_venue rcv
            JOIN tbl_reservation_venue         rv ON rcv.reservation_venue_id = rv.reservation_venue_id
            JOIN tbl_venue                     v  ON rv.reservation_venue_venue_id = v.ven_id
            JOIN tbl_condition                 c  ON rcv.condition_id = c.id
            WHERE rcv.condition_id != 2
              AND rcv.is_active = 0
        ";
        foreach ($this->conn->query($sql, PDO::FETCH_ASSOC) as $row) {
            $records[] = $row;
        }

        // 3) Vehicles under maintenance
        $sql = "
            SELECT
                rcvh.id                           AS record_id,
                'vehicle'                         AS resource_type,
                CONCAT(vm.vehicle_model_name, ' (', vh.vehicle_license, ')') AS resource_name,
                NULL                              AS quantity,
                rv.reservation_vehicle_vehicle_id AS resource_id,
                c.condition_name                  AS condition_name,
                rcvh.remarks                      AS remarks
            FROM tbl_reservation_condition_vehicle rcvh
            JOIN tbl_reservation_vehicle        rv ON rcvh.reservation_vehicle_id = rv.reservation_vehicle_id
            JOIN tbl_vehicle                    vh ON rv.reservation_vehicle_vehicle_id = vh.vehicle_id
            JOIN tbl_vehicle_model              vm ON vh.vehicle_model_id = vm.vehicle_model_id
            JOIN tbl_condition                  c  ON rcvh.condition_id = c.id
            WHERE rcvh.condition_id != 2
              AND rcvh.is_active = 0
        ";
        foreach ($this->conn->query($sql, PDO::FETCH_ASSOC) as $row) {
            $records[] = $row;
        }

        // 4) Equipment units (non-consumable) under maintenance
        $sql = "
            SELECT
                rcu.id           AS record_id,
                'equipment_unit' AS resource_type,
                eu.serial_number AS resource_name,
                eu.unit_id       AS resource_id,
                c.condition_name AS condition_name,
                rcu.remarks     AS remarks
            FROM tbl_reservation_condition_unit rcu
            JOIN tbl_reservation_unit           ru ON rcu.reservation_unit_id = ru.reservation_unit_id
            JOIN tbl_equipment_unit             eu ON ru.unit_id = eu.unit_id
            JOIN tbl_condition                  c  ON rcu.condition_id = c.id
            WHERE rcu.condition_id != 2
              AND rcu.is_active = 0
        ";
        foreach ($this->conn->query($sql, PDO::FETCH_ASSOC) as $row) {
            $records[] = $row;
        }

        return json_encode([
            'status' => 'success',
            'data'   => $records
        ]);
    } catch (PDOException $e) {
        return json_encode([
            'status'  => 'error',
            'message' => $e->getMessage()
        ]);
    }
}

    public function fetchHoliday() {
        $sql = "SELECT `holiday_id`, `holiday_name`, `holiday_date` FROM `tbl_holidays` WHERE 1";
        return $this->executeQuery($sql);
    }

    public function updateHoliday($holidayId, $holidayName, $holidayDate) {
        try {
            $sql = "UPDATE `tbl_holidays` 
                   SET `holiday_name` = :holiday_name, 
                       `holiday_date` = :holiday_date 
                   WHERE `holiday_id` = :holiday_id";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([
                ':holiday_name' => $holidayName,
                ':holiday_date' => $holidayDate,
                ':holiday_id' => $holidayId
            ]);

            if ($stmt->rowCount() > 0) {
                return json_encode(['status' => 'success', 'message' => 'Holiday updated successfully']);
            } else {
                return json_encode(['status' => 'error', 'message' => 'No changes made or holiday not found']);
            }
        } catch (PDOException $e) {
            return json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
        }
    }

    public function deleteHoliday($holidayId) {
        try {
            $sql = "DELETE FROM `tbl_holidays` WHERE `holiday_id` = :holiday_id";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([':holiday_id' => $holidayId]);

            if ($stmt->rowCount() > 0) {
                return json_encode(['status' => 'success', 'message' => 'Holiday deleted successfully']);
            } else {
                return json_encode(['status' => 'error', 'message' => 'Holiday not found']);
            }
        } catch (PDOException $e) {
            return json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
        }
    }


   public function countTrendReservations() {
    try {
        $sql = "
            SELECT 
                r.reservation_id,
                r.reservation_created_at,
                rs.reservation_status_status_id AS status_id
            FROM 
                tbl_reservation r
            LEFT JOIN tbl_reservation_status rs 
                ON rs.reservation_reservation_id = r.reservation_id
            WHERE 
                rs.reservation_status_status_id = 6
            GROUP BY 
                r.reservation_id
            ORDER BY 
                r.reservation_created_at DESC
        ";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        $reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return json_encode([
            'status' => 'success',
            'data' => [ 
                'countTrendReservation' => count($reservations),
                'reservations' => $reservations
            ]
        ]);
    } catch (PDOException $e) {
        return json_encode([
            'status' => 'error',
            'message' => 'Database error: ' . $e->getMessage()
        ]);
    }
}



    public function countCompletedAndCancelledReservations() {
    try {
        $sql = "
            SELECT 
                r.reservation_id,
                r.reservation_created_at,
                rs.reservation_status_status_id AS status_id
            FROM 
                tbl_reservation r
            LEFT JOIN 
                tbl_reservation_status rs 
                ON rs.reservation_reservation_id = r.reservation_id
            WHERE 
                rs.reservation_status_status_id IN (4, 5)
            GROUP BY 
                r.reservation_id
            ORDER BY 
                r.reservation_created_at DESC
        ";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        $reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return json_encode([
            'status' => 'success',
            'data' => [
                'countCompletedAndCancelled' => count($reservations),
                'reservations' => $reservations
            ]
        ]);
    } catch (PDOException $e) {
        return json_encode([
            'status' => 'error',
            'message' => 'Database error: ' . $e->getMessage()
        ]);
    }
}

public function fetchVenueScheduled() {
        try {
            // Fetch all scheduled venues with their names, days, and times
            // The query now directly joins class_venue_schedule with venue
            // without any WHERE clause related to semester.
            $query = "SELECT 
                        v.ven_id,
                        v.ven_name, 
                        cvs.day_of_week, 
                        cvs.start_time, 
                        cvs.end_time,
                        cvs.semester_id,  -- Include semester_id if you want to know which semester it belongs to
                        s.semester_name   -- Include semester_name for better context if needed
                      FROM tbl_class_venue_schedule cvs
                      INNER JOIN tbl_venue v ON cvs.ven_id = v.ven_id
                      INNER JOIN tbl_semester s ON cvs.semester_id = s.semester_id
                      ORDER BY s.semester_id, v.ven_name, cvs.day_of_week, cvs.start_time";
            
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return json_encode([
                'status' => 'success',
                'data' => $result
            ]);
        } catch (PDOException $e) {
            return json_encode([
                'status' => 'error',
                'message' => 'Database error: ' . $e->getMessage()
            ]);
        } catch (Exception $e) {
            return json_encode([
                'status' => 'error',
                'message' => 'Error: ' . $e->getMessage()
            ]);
        }
    }

    public function handleReview($reservationId, $userId) {
    try {
        $this->conn->beginTransaction();

        // Step 1: Insert new reservation status
        $sqlInsert = "
            INSERT INTO tbl_reservation_status (
                reservation_status_status_id,
                reservation_reservation_id,
                reservation_active,
                reservation_users_id,
                reservation_updated_at
            ) VALUES (
                :status_id,
                :reservation_id,
                1,
                :user_id,
                NOW()
            )
        ";

        $stmtInsert = $this->conn->prepare($sqlInsert);
        $statusReviewed = 7;
        $stmtInsert->bindParam(':status_id', $statusReviewed, PDO::PARAM_INT);
        $stmtInsert->bindParam(':reservation_id', $reservationId, PDO::PARAM_INT);
        $stmtInsert->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmtInsert->execute();

        // Step 2: Insert department-level notification for review
        $sqlDepartmentNotification = "
            INSERT INTO notification_requests (
                notification_message,
                notification_department_id,
                notification_user_level_id,
                notification_create
            ) VALUES (
                :message,
                :department_id,
                :user_level_id,
                NOW()
            )
        ";

        $stmtNotif = $this->conn->prepare($sqlDepartmentNotification);
        $message = "Venue Availability Request Check By GSD";
        $departmentId = 44;
        $userLevelId = 18;

        $stmtNotif->bindParam(':message', $message, PDO::PARAM_STR);
        $stmtNotif->bindParam(':department_id', $departmentId, PDO::PARAM_INT);
        $stmtNotif->bindParam(':user_level_id', $userLevelId, PDO::PARAM_INT);
        $stmtNotif->execute();

        // Commit transaction
        $this->conn->commit();

        return json_encode([
            'status' => 'success',
            'message' => 'Reservation ID ' . $reservationId . ' has been marked as reviewed and notification sent.',
            'reservation_id' => $reservationId
        ]);

    } catch (PDOException $e) {
        $this->conn->rollBack();
        return json_encode([
            'status' => 'error',
            'message' => 'Database error: ' . $e->getMessage()
        ]);
    } catch (Exception $e) {
        $this->conn->rollBack();
        return json_encode([
            'status' => 'error',
            'message' => 'General error: ' . $e->getMessage()
        ]);
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

public function saveUnit($data) {
    try {
        if (is_string($data)) $data = json_decode($data, true);
        if (!is_array($data)) return json_encode(['status' => 'error', 'message' => 'Invalid input data']);

        // Required fields and types
        $requiredFields = [
            'equip_id' => 'int',
            'serial_number' => 'string',
            'status_availability_id' => 'int',
            'user_admin_id' => 'int'
        ];

        foreach ($requiredFields as $field => $type) {
            if (!isset($data[$field]) || ($type === 'string' && trim($data[$field]) === '')) {
                return json_encode(['status' => 'error', 'message' => ucfirst(str_replace('_', ' ', $field)) . ' is required']);
            }
            if ($type === 'int' && !is_numeric($data[$field])) {
                return json_encode(['status' => 'error', 'message' => ucfirst(str_replace('_', ' ', $field)) . ' must be a number']);
            }
        }

        // Check for duplicate serial number
        $check = $this->conn->prepare("SELECT COUNT(*) FROM tbl_equipment_unit WHERE serial_number = :serial_number");
        $check->bindParam(':serial_number', $data['serial_number']);
        $check->execute();
        if ($check->fetchColumn() > 0) {
            return json_encode(['status' => 'error', 'message' => 'This serial number already exists']);
        }

        // Optional fields
        $brand = $data['brand'] ?? null;
        $size = $data['size'] ?? null;
        $color = $data['color'] ?? null;

        // Insert query
        $sql = "INSERT INTO tbl_equipment_unit (
                    equip_id, serial_number, brand, size, color, 
                    status_availability_id, unit_created_at, is_active, user_admin_id
                ) VALUES (
                    :equip_id, :serial_number, :brand, :size, :color,
                    :status_id, NOW(), 1, :admin_id
                )";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':equip_id', $data['equip_id'], PDO::PARAM_INT);
        $stmt->bindParam(':serial_number', $data['serial_number']);
        $stmt->bindParam(':brand', $brand);
        $stmt->bindParam(':size', $size);
        $stmt->bindParam(':color', $color);
        $stmt->bindParam(':status_id', $data['status_availability_id'], PDO::PARAM_INT);
        $stmt->bindParam(':admin_id', $data['user_admin_id'], PDO::PARAM_INT);

        return $stmt->execute()
            ? json_encode(['status' => 'success', 'message' => 'Equipment unit added successfully'])
            : json_encode(['status' => 'error', 'message' => 'Failed to add equipment unit']);

    } catch (PDOException $e) {
        error_log("Database error in saveUnit: " . $e->getMessage());
        return json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    } catch (Exception $e) {
        error_log("Error in saveUnit: " . $e->getMessage());
        return json_encode(['status' => 'error', 'message' => $e->getMessage()]);
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
            'brand' => PDO::PARAM_STR,
            'size' => PDO::PARAM_STR,
            'color' => PDO::PARAM_STR,
            'is_active' => PDO::PARAM_BOOL,
            'user_admin_id' => PDO::PARAM_INT
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

public function saveEquipment($json) {
    try {
        $data = is_array($json) ? $json : json_decode($json, true);
        if (!is_array($data)) {
            return json_encode(['status' => 'error', 'message' => 'Invalid input data']);
        }

        // Validate required fields
        $required = ['name', 'equipments_category_id', 'equip_type', 'user_admin_id'];
        foreach ($required as $field) {
            if (empty($data[$field]) || ($field !== 'name' && !is_numeric($data[$field]))) {
                return json_encode(['status' => 'error', 'message' => "$field is required or invalid"]);
            }
        }

        // Check for duplicate equipment name (case-insensitive)
        $check = $this->conn->prepare("SELECT equip_id FROM tbl_equipments WHERE LOWER(equip_name) = LOWER(:name)");
        $check->bindParam(':name', $data['name']);
        $check->execute();
        if ($check->rowCount() > 0) {
            return json_encode(['status' => 'error', 'message' => 'Equipment already exists']);
        }

        // Insert equipment
        $sql = "INSERT INTO tbl_equipments (
                    equip_name, equipments_category_id, equip_type,
                    is_active, user_admin_id, equip_created_at
                ) VALUES (
                    :name, :category_id, :type, 1, :admin_id, NOW()
                )";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':name', $data['name']);
        $stmt->bindParam(':category_id', $data['equipments_category_id'], PDO::PARAM_INT);
        $stmt->bindParam(':type', $data['equip_type']);
        $stmt->bindParam(':admin_id', $data['user_admin_id'], PDO::PARAM_INT);

        return $stmt->execute()
            ? json_encode(['status' => 'success', 'message' => 'Equipment added successfully', 'equip_id' => $this->conn->lastInsertId()])
            : json_encode(['status' => 'error', 'message' => 'Failed to insert equipment']);

    } catch (PDOException $e) {
        error_log("DB error in saveEquipment: " . $e->getMessage());
        return json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}

public function updateEquipment($data) {
    try {
        // Validate required fields
        $required = ['equip_id', 'equip_name', 'equip_type', 'equipments_category_id'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                return json_encode(['status' => 'error', 'message' => "$field is required"]);
            }
            
            // Check if numeric fields are actually numeric
            if (in_array($field, ['equip_id', 'equipments_category_id']) && !is_numeric($data[$field])) {
                return json_encode(['status' => 'error', 'message' => "$field must be numeric"]);
            }
        }

        // Prepare the update statement
        $sql = "UPDATE tbl_equipments SET 
                    equip_name = :name, 
                    equip_type = :type, 
                    equipments_category_id = :category_id
                WHERE equip_id = :id";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':name', $data['equip_name']);
        $stmt->bindParam(':type', $data['equip_type']);
        $stmt->bindParam(':category_id', $data['equipments_category_id'], PDO::PARAM_INT);
        $stmt->bindParam(':id', $data['equip_id'], PDO::PARAM_INT);

        return $stmt->execute()
            ? json_encode(['status' => 'success', 'message' => 'Equipment updated successfully'])
            : json_encode(['status' => 'error', 'message' => 'Failed to update equipment']);

    } catch (PDOException $e) {
        error_log("Database error in updateEquipment: " . $e->getMessage());
        return json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}



public function saveVehicle($data) {
    try {
        if (is_string($data)) $data = json_decode($data, true);
        if (!is_array($data)) return json_encode(['status' => 'error', 'message' => 'Invalid input data']);

        foreach (['vehicle_model_id', 'vehicle_license', 'year', 'user_admin_id'] as $field) {
            if (empty($data[$field])) return json_encode(['status' => 'error', 'message' => ucfirst(str_replace('_', ' ', $field)) . ' is required']);
        }

        $sql = "INSERT INTO tbl_vehicle (
                    vehicle_model_id, vehicle_license, year, 
                    status_availability_id, user_admin_id, 
                    is_active, created_at, updated_at
                ) VALUES (
                    :modelId, :license, :year, 
                    1, :adminId, 
                    1, NOW(), NOW()
                )";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':modelId', $data['vehicle_model_id']);
        $stmt->bindParam(':license', $data['vehicle_license']);
        $stmt->bindParam(':year', $data['year']);
        $stmt->bindParam(':adminId', $data['user_admin_id']);

        return $stmt->execute()
            ? json_encode(['status' => 'success', 'message' => 'Vehicle added successfully.'])
            : json_encode(['status' => 'error', 'message' => 'Failed to add vehicle']);
    } catch (PDOException $e) {
        error_log("saveVehicle error: " . $e->getMessage());
        return json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}

public function updateVehicleLicense($vehicleData) {
    try {
        $required = ['vehicle_id', 'vehicle_model_id', 'vehicle_license', 'year', 'status_availability_id', 'user_admin_id', 'is_active'];
        foreach ($required as $field) {
            if (!isset($vehicleData[$field])) {
                return json_encode(['status' => 'error', 'message' => "$field is required"]);
            }
        }

        $sql = "UPDATE tbl_vehicle SET 
                    vehicle_model_id = :model_id,
                    vehicle_license = :license,
                    year = :year,
                    status_availability_id = :status_id,
                    user_admin_id = :user_admin_id,
                    is_active = :is_active,
                    updated_at = NOW()
                WHERE vehicle_id = :id";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':model_id', $vehicleData['vehicle_model_id'], PDO::PARAM_INT);
        $stmt->bindParam(':license', $vehicleData['vehicle_license']);
        $stmt->bindParam(':year', $vehicleData['year']);
        $stmt->bindParam(':status_id', $vehicleData['status_availability_id'], PDO::PARAM_INT);
        $stmt->bindParam(':user_admin_id', $vehicleData['user_admin_id'], PDO::PARAM_INT);
        $stmt->bindParam(':is_active', $vehicleData['is_active'], PDO::PARAM_BOOL);
        $stmt->bindParam(':id', $vehicleData['vehicle_id'], PDO::PARAM_INT);

        return $stmt->execute()
            ? json_encode(['status' => 'success', 'message' => 'Vehicle updated successfully', 'vehicle_id' => $vehicleData['vehicle_id']])
            : json_encode(['status' => 'error', 'message' => 'Failed to update vehicle']);
    } catch (PDOException $e) {
        error_log("Database error in updateVehicleLicense: " . $e->getMessage());
        return json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
    }
}




public function saveVenue($data) {
    error_log("saveVenue received data: " . print_r($data, true));

    try {
        if (!isset($data['user_admin_id'])) {
            return json_encode(['status' => 'error', 'message' => 'Admin ID is required']);
        }
        if (!isset($data['name']) || !isset($data['occupancy'])) {
            return json_encode(['status' => 'error', 'message' => 'Missing required fields']);
        }
        if ($this->venueExists($data['name'])) {
            return json_encode(['status' => 'error', 'message' => 'This venue name is already in use.']);
        }

        $sql = "INSERT INTO tbl_venue (
            ven_name, ven_occupancy, ven_created_at, ven_updated_at, 
            status_availability_id, is_active, user_admin_id
        ) VALUES (
            :name, :occupancy, NOW(), NOW(), 
            1, 1, :admin_id
        )";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':name', $data['name'], PDO::PARAM_STR);
        $stmt->bindParam(':occupancy', $data['occupancy'], PDO::PARAM_INT);
        $stmt->bindParam(':admin_id', $data['user_admin_id'], PDO::PARAM_INT);

        if ($stmt->execute()) {
            return json_encode(['status' => 'success', 'message' => 'Venue added successfully']);
        }

        return json_encode(['status' => 'error', 'message' => 'Failed to add venue']);
    } catch(PDOException $e) {
        error_log("Database error: " . $e->getMessage());
        return json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

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

        // Updated SQL statement to include title_id
        $sql = "INSERT INTO tbl_users (
                    title_id,
                    users_fname, users_mname, users_lname, 
                    users_email, users_school_id, users_contact_number, 
                    users_user_level_id, users_password, users_department_id, 
                    users_birthdate, users_suffix, users_pic, 
                    users_created_at, users_updated_at
                ) VALUES (
                    :title_id,
                    :fname, :mname, :lname, 
                    :email, :schoolId, :contact, 
                    :userLevelId, :password, :departmentId, 
                    :birthdate, :suffix, :pic, 
                    NOW(), NOW()
                )";

        $stmt = $this->conn->prepare($sql);

        // Bind parameters
        $stmt->bindParam(':title_id', $data['title_id'], PDO::PARAM_INT); // Nullable INT
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

        if ($stmt->execute()) {
            return json_encode(['status' => 'success', 'message' => 'User added successfully.']);
        }

        return json_encode(['status' => 'error', 'message' => 'Failed to add user.']);
    } catch (PDOException $e) {
        return json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
    }
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
        case "saveDriver":
            echo $user->saveDriver($input);
            break;
        case "saveHoliday":
            echo $user->saveHoliday($input);
            break;
        case "updateDriver":
            echo $user->updateDriver($input);
            break;  


        case "saveVenue":
            echo $user->saveVenue($input);
            break;

        case "updateVenue":
            echo $user->updateVenue($input);
            break;

        case "saveVehicle":
            echo $user->saveVehicle($input);
            break;

        case "updateVehicleLicense":
            echo $user->updateVehicleLicense($input);
            break;

        case "saveEquipment":
            echo $user->saveEquipment($input);
            break;

        case "updateEquipment":
            echo $user->updateEquipment($input);
            break;
        case "saveUnit":
            echo $user->saveUnit($input);
            break;
        case "updateUnit":
            echo $user->updateUnit($input);
            break;
        case "saveStock":
            echo $user->saveStock($input);
            break;
        case "saveUser":
            echo $user->saveUser($input);
            break;
        case "updateUser":
            echo $user->updateUser($input);
            break;

        case "handleReview":
            $reservationId = $input['reservationId'] ?? null;
            $userId = $input['userId'] ?? null;
            
            if (!$reservationId || !$userId) {
                echo json_encode(['status' => 'error', 'message' => 'Missing reservation ID or user ID']);
                break;
            }
            echo $user->handleReview($reservationId, $userId);
            break;
           

        case "fetchVenueScheduled":
            echo $user->fetchVenueScheduled();
            break;

        case "fetchTitle":
            echo $user->fetchTitle();
            break;

        case "fetchHolday":
            echo $user->fetchHoliday();
            break;        case "updateHoliday":
            $holidayId = $input['holiday_id'] ?? null;
            $holidayName = $input['holiday_name'] ?? null;
            $holidayDate = $input['holiday_date'] ?? null;
            
            if (!$holidayId || !$holidayName || !$holidayDate) {
                echo json_encode(['status' => 'error', 'message' => 'Missing required parameters']);
                break;
            }
            echo $user->updateHoliday($holidayId, $holidayName, $holidayDate);
            break;

        case "deleteHoliday":
            $holidayId = $input['holidayId'] ?? null;
            
            if (!$holidayId) {
                echo json_encode(['status' => 'error', 'message' => 'Holiday ID is required']);
                break;
            }
            echo $user->deleteHoliday($holidayId);
            break;

        case "displayedMaintenanceResourcesDone":
            echo $user->displayedMaintenanceResourcesDone();
            break;

        case "fetchAllAssignedReleases":
            echo $user->fetchAllAssignedReleases();
            break;

        case "getConsumableUsage":
            $equipId = $input['equipId'] ?? null;
            if ($equipId) {
                echo $user->getConsumableUsage($equipId);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Equipment ID is required']);
            }
            break;

        case "getEquipmentUnitUsage":
            $unitId = $input['unitId'] ?? null;
            if ($unitId) {
                echo $user->getEquipmentUnitUsage($unitId);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Unit ID is required']);
            }
            break;

        case "getVenueUsage":
            $venueId = $input['venueId'] ?? null;
            if ($venueId) {
                echo $user->getVenueUsage($venueId);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Venue ID is required']);
            }
            break;

        case "getVehicleUsage":
            $vehicleId = $input['vehicleId'] ?? null;
            if ($vehicleId) {
                echo $user->getVehicleUsage($vehicleId);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Vehicle ID is required']);
            }
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
        case "getUnitById":
            $unitId = $input['unitId'] ?? null;
            if ($unitId) {
                echo $user->getUnitById($unitId);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Unit ID is required']);
            }
            break;
        case "fetchEquipmentName":
            echo $user->fetchEquipmentName();
            break;
        case "countReservationTrends":
            echo $user->countReservationTrends();
            break;
        case "countTrendReservations":
            echo $user->countTrendReservations();
            break;
        case "countCompletedAndCancelledReservations":
            echo $user->countCompletedAndCancelledReservations();
            break;
        
        default:
            echo json_encode(['status' => 'error', 'message' => 'Invalid operation']);
            break;
    }
}
?>
