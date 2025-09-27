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

// At the top of the file, after other use/require/include statements:f
require_once 'vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

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
          ORDER BY v.vehicle_id DESC
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

    public function fetchVenue() {
        $sql = "SELECT 
                ven_id, ven_name, ven_occupancy, ven_created_at, ven_updated_at, status_availability_id, 
                ven_pic, is_active, event_type, area_type FROM tbl_venue WHERE is_active = 1 ORDER BY ven_id DESC";
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
                te.equip_id DESC
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

    public function fetchEquipment() {
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
    public function fetchUserLevels() {
        $sql = "SELECT user_level_id, user_level_name FROM tbl_user_level ORDER BY user_level_name";
        return $this->executeQuery($sql);
    }

    public function fetchDepartments() {
        $sql = "SELECT departments_id, departments_name, department_type 
                FROM tbl_departments 
                ORDER BY departments_id DESC";
        return $this->executeQuery($sql);
    }

        public function fetchPersonnel() {
        $sql = "SELECT users_id,
                CONCAT(
                    users_fname,
                    CASE 
                        WHEN users_mname != '' THEN CONCAT(' ', LEFT(users_mname, 1), '.')
                        ELSE ''
                    END,
                    ' ',
                    users_lname
                ) AS full_name
                FROM tbl_users
                WHERE is_active = 1 
                AND users_user_level_id = 2
                ORDER BY users_fname";
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


    public function fetchDriver($startDateTime = null, $endDateTime = null, $userId = null) {
        try {
            // Log function call
            error_log("fetchDriver called with startDateTime: " . ($startDateTime ?? 'null') . ", endDateTime: " . ($endDateTime ?? 'null'));
            // Base query to get all active drivers
            $sql = "
                SELECT 
                    u.users_id,
                    u.users_fname,
                    u.users_mname,
                    u.users_lname,
                    u.users_birthdate,
                    u.users_suffix,
                    u.users_email,
                    u.users_school_id,
                    u.users_contact_number,
                    u.users_user_level_id,
                    u.users_password,
                    u.first_login,
                    u.users_department_id,
                    u.users_pic,
                    u.users_created_at,
                    u.users_updated_at,
                    u.is_active,
                    u.is_2FAactive,
                    u.title_id
                FROM 
                    tbl_users u
                WHERE 
                    u.users_user_level_id = 19
                    AND u.is_active = 1
            ";
    
            // If no date filters, return all drivers
            if ($startDateTime === null || $endDateTime === null) {
                error_log("fetchDriver: No date filters provided, returning all active drivers");
                $sql .= " ORDER BY u.users_lname, u.users_fname";
                return $this->executeQuery($sql);
            }
            
            // Check if user is Department Head from COO department or Secretary from GSD department
            $bypassReservationCheck = false;
            if ($userId !== null) {
                $userCheckSql = "SELECT ul.user_level_name, d.departments_name 
                                FROM tbl_users u
                                LEFT JOIN tbl_user_level ul ON u.users_user_level_id = ul.user_level_id
                                LEFT JOIN tbl_departments d ON u.users_department_id = d.departments_id
                                WHERE u.users_id = ?";
                $userCheckStmt = $this->conn->prepare($userCheckSql);
                $userCheckStmt->execute([$userId]);
                $userDetails = $userCheckStmt->fetch(PDO::FETCH_ASSOC);
                
                if ($userDetails) {
                    // If Department Head from COO department or Secretary from GSD department, bypass reservation check
                    if (($userDetails['user_level_name'] === 'Department Head' && $userDetails['departments_name'] === 'COO') ||
                        ($userDetails['user_level_name'] === 'Secretary' && $userDetails['departments_name'] === 'GSD')) {
                        $bypassReservationCheck = true;
                    }
                }
            }
            
            error_log("fetchDriver: Checking driver availability for period: $startDateTime to $endDateTime");
    
            // Get drivers who are already assigned to vehicles during the specified period
            // Modified to handle rescheduled reservations properly - only show one entry for active rescheduled reservations
            $sqlUnavailableDrivers = "
                SELECT DISTINCT rd.reservation_driver_user_id
                FROM tbl_reservation_driver rd
                INNER JOIN tbl_reservation_vehicle rv ON rd.reservation_vehicle_id = rv.reservation_vehicle_id
                INNER JOIN tbl_reservation r ON rv.reservation_reservation_id = r.reservation_id
                LEFT JOIN (
                    SELECT rs1.*
                    FROM tbl_reservation_status rs1
                    INNER JOIN (
                        SELECT reservation_reservation_id, MAX(reservation_updated_at) AS max_updated_at
                        FROM tbl_reservation_status
                        GROUP BY reservation_reservation_id
                    ) mu ON rs1.reservation_reservation_id = mu.reservation_reservation_id
                         AND rs1.reservation_updated_at = mu.max_updated_at
                    INNER JOIN (
                        SELECT x.reservation_reservation_id, MAX(x.reservation_status_id) AS max_id
                        FROM tbl_reservation_status x
                        INNER JOIN (
                            SELECT reservation_reservation_id, MAX(reservation_updated_at) AS max_updated_at
                            FROM tbl_reservation_status
                            GROUP BY reservation_reservation_id
                        ) y ON y.reservation_reservation_id = x.reservation_reservation_id
                           AND y.max_updated_at = x.reservation_updated_at
                        GROUP BY x.reservation_reservation_id
                    ) mid ON rs1.reservation_reservation_id = mid.reservation_reservation_id
                         AND rs1.reservation_status_id = mid.max_id
                ) latest_status ON latest_status.reservation_reservation_id = r.reservation_id
                WHERE 
                    r.reservation_id NOT IN (
                        SELECT DISTINCT reservation_reservation_id 
                        FROM tbl_reservation_status 
                        WHERE reservation_status_status_id IN (2, 5)
                    )
                    AND latest_status.reservation_status_status_id IN (6, 8, 10, 14)
                    AND (
                        (latest_status.reservation_status_status_id = 6 AND latest_status.reservation_active = 1)
                        OR (latest_status.reservation_status_status_id IN (8, 10, 14) AND (latest_status.reservation_active = 0 OR latest_status.reservation_active = 1))
                        OR (latest_status.reservation_status_status_id = 6 AND EXISTS (
                            SELECT 1 FROM tbl_reservation_status rs2 
                            WHERE rs2.reservation_reservation_id = r.reservation_id 
                            AND rs2.reservation_status_status_id IN (10, 14) 
                            AND rs2.reservation_active = 1
                        ))
                    )
                    AND (
                        -- Check for date overlap using appropriate dates based on reservation status
                        (latest_status.reservation_status_status_id IN (6, 8) AND r.reservation_start_date <= :endDateTime AND r.reservation_end_date >= :startDateTime)
                        OR (latest_status.reservation_status_status_id IN (10, 14) AND r.reschedule_start_date IS NOT NULL AND r.reschedule_end_date IS NOT NULL AND r.reschedule_start_date <= :endDateTime AND r.reschedule_end_date >= :startDateTime)
                    )
            ";
    
            // Only execute unavailable driver check if user doesn't bypass it
            if (!$bypassReservationCheck) {
                $stmt = $this->conn->prepare($sqlUnavailableDrivers);
                $stmt->execute([
                    ':startDateTime' => $startDateTime,
                    ':endDateTime' => $endDateTime
                ]);
                $unavailableDriverIds = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
            } else {
                $unavailableDriverIds = [];
            }
            error_log("fetchDriver: Found " . count($unavailableDriverIds) . " unavailable drivers: " . implode(',', $unavailableDriverIds));
    
            // Exclude unavailable drivers from main query
            if (!empty($unavailableDriverIds)) {
                $placeholders = implode(',', array_fill(0, count($unavailableDriverIds), '?'));
                $sql .= " AND u.users_id NOT IN ($placeholders)";
            }
    
            $sql .= " ORDER BY u.users_lname, u.users_fname";
    
            // Execute final query
            $stmt = $this->conn->prepare($sql);
            if (!empty($unavailableDriverIds)) {
                $stmt->execute($unavailableDriverIds);
            } else {
                $stmt->execute();
            }

            $drivers = $stmt->fetchAll(PDO::FETCH_ASSOC);
            error_log("fetchDriver: Returning " . count($drivers) . " available drivers");
            return json_encode(['status' => 'success', 'data' => $drivers]);
    
        } catch (PDOException $e) {
            error_log("fetchDriver: Database error - " . $e->getMessage());
            return json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
        } catch (Exception $e) {
            error_log("fetchDriver: General error - " . $e->getMessage());
            return json_encode(['status' => 'error', 'message' => 'An unexpected error occurred: ' . $e->getMessage()]);
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
                // Non-blocking audit logging for password update
                try {
                    // Fetch user's full name: First + Middle initial + Last
                    $sqlName = "SELECT CONCAT(\n                                    users_fname,\n                                    CASE WHEN users_mname IS NOT NULL AND users_mname != '' THEN CONCAT(' ', LEFT(users_mname, 1), '.') ELSE '' END,\n                                    ' ', users_lname\n                                 ) AS full_name\n                               FROM tbl_users WHERE users_id = :uid";
                    $stmtName = $this->conn->prepare($sqlName);
                    $stmtName->bindParam(':uid', $userId, PDO::PARAM_INT);
                    $stmtName->execute();
                    $rowName = $stmtName->fetch(PDO::FETCH_ASSOC);
                    $fullName = $rowName['full_name'] ?? ('User #' . (int)$userId);

                    $description = 'Password updated by: ' . $fullName;
                    $action = 'UPDATE PASSWORD';

                    $stmtAudit = $this->conn->prepare("INSERT INTO audit_log (description, action, created_at, created_by) VALUES (:description, :action, NOW(), :created_by)");
                    $stmtAudit->bindParam(':description', $description, PDO::PARAM_STR);
                    $stmtAudit->bindParam(':action', $action, PDO::PARAM_STR);
                    $stmtAudit->bindParam(':created_by', $userId, PDO::PARAM_INT);
                    $stmtAudit->execute();
                } catch (Exception $e) { /* ignore audit logging errors */ }

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


    public function updateProfile($userData){
        try {
            $userId = isset($userData['users_id']) ? $userData['users_id'] : null;
            $email = isset($userData['users_email']) ? $userData['users_email'] : null;
            $schoolId = isset($userData['users_school_id']) ? $userData['users_school_id'] : null;
            $original = null;
            if ($userId) {
                $sql = "SELECT users_email, users_school_id FROM tbl_users WHERE users_id = :userId LIMIT 1";
                $stmt = $this->conn->prepare($sql);
                $stmt->execute([':userId' => $userId]);
                $original = $stmt->fetch(PDO::FETCH_ASSOC);
            }
            $checkDuplicate = false;
            if ($original) {
                if (($email && $email !== $original['users_email']) || ($schoolId && $schoolId !== $original['users_school_id'])) {
                    $checkDuplicate = true;
                }
            }
            if ($checkDuplicate) {
                $checkResult = json_decode($this->checkUniqueEmailAndSchoolId($email, $schoolId, $userId), true);
                if ($checkResult && $checkResult['exists'] && !empty($checkResult['duplicates'])) {
                    return json_encode([
                        'status' => 'error',
                        'message' => 'Duplicate found',
                        'duplicates' => $checkResult['duplicates']
                    ]);
                }
            }
            // Define allowed fields to update
            $allowedFields = [
                'users_fname',
                'users_mname',
                'users_lname',
                'users_email',
                'users_school_id',
                'users_contact_number',
                'users_department_id',
                'users_pic',
                'users_suffix', // Added
                'title_id',     // Added
                'users_user_level_id' // Allow updating user level
            ];

            // Fetch pre-update snapshot for comparison (to list only actual changes)
            $beforeUser = null;
            if (!empty($userData['users_id'])) {
                $beforeSql = "SELECT " . implode(', ', $allowedFields) . " FROM tbl_users WHERE users_id = :userId LIMIT 1";
                $beforeStmt = $this->conn->prepare($beforeSql);
                $beforeStmt->execute([':userId' => $userData['users_id']]);
                $beforeUser = $beforeStmt->fetch(PDO::FETCH_ASSOC) ?: [];
            }

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
                $selectSql = "SELECT users_id, users_fname, users_mname, users_lname, users_birthdate, users_suffix, users_email, users_school_id, users_contact_number, users_user_level_id, users_password, first_login, users_department_id, users_pic, users_created_at, users_updated_at, is_active, is_2FAactive, title_id FROM tbl_users WHERE users_id = :userId";
                $selectStmt = $this->conn->prepare($selectSql);
                $selectStmt->execute(['userId' => $userData['users_id']]);
                $updatedUser = $selectStmt->fetch(PDO::FETCH_ASSOC);

                // Non-blocking audit logging for profile update (list only changed fields)
                try {
                    // Determine actor (personnel) id: prefer explicit user_personnel_id, fallback to updated user id
                    $actorId = isset($userData['user_personnel_id']) && $userData['user_personnel_id'] !== ''
                        ? (int)$userData['user_personnel_id']
                        : (int)$userData['users_id'];

                    // Subject name (updated user)
                    $mi = (!empty($updatedUser['users_mname'])) ? (' ' . substr($updatedUser['users_mname'], 0, 1) . '.') : '';
                    $subjectName = trim(($updatedUser['users_fname'] ?? '') . $mi . ' ' . ($updatedUser['users_lname'] ?? ''));
                    if ($subjectName === '') { $subjectName = 'User #' . (int)$updatedUser['users_id']; }

                    // Actor name
                    if ($actorId === (int)$updatedUser['users_id']) {
                        $actorName = $subjectName;
                    } else {
                        $sqlActor = "SELECT CONCAT(\n                                        users_fname,\n                                        CASE WHEN users_mname IS NOT NULL AND users_mname != '' THEN CONCAT(' ', LEFT(users_mname, 1), '.') ELSE '' END,\n                                        ' ', users_lname\n                                     ) AS full_name\n                                   FROM tbl_users WHERE users_id = :uid";
                        $stmtActor = $this->conn->prepare($sqlActor);
                        $stmtActor->bindParam(':uid', $actorId, PDO::PARAM_INT);
                        $stmtActor->execute();
                        $rowActor = $stmtActor->fetch(PDO::FETCH_ASSOC);
                        $actorName = $rowActor['full_name'] ?? ('User #' . $actorId);
                    }

                    // Build list of changed fields (friendly names)
                    $fieldMap = [
                        'users_fname' => 'First Name',
                        'users_mname' => 'Middle Name',
                        'users_lname' => 'Last Name',
                        'users_email' => 'Email',
                        'users_school_id' => 'School ID',
                        'users_contact_number' => 'Contact Number',
                        'users_department_id' => 'Department',
                        'users_pic' => 'Profile Picture',
                        'users_suffix' => 'Suffix',
                        'title_id' => 'Title',
                        'users_user_level_id' => 'User Level',
                    ];
                    $candidateKeys = array_filter(array_keys($params), function($k){ return $k !== 'userId'; });
                    $changedKeys = [];
                    foreach ($candidateKeys as $k) {
                        $newVal = isset($params[$k]) ? (string)$params[$k] : '';
                        $oldVal = isset($beforeUser[$k]) ? (string)$beforeUser[$k] : '';
                        if (trim($newVal) !== trim($oldVal)) {
                            $changedKeys[] = $k;
                        }
                    }
                    // Build detailed change list with old -> new values
                    $changes = [];
                    foreach ($changedKeys as $k) {
                        $label = $fieldMap[$k] ?? $k;
                        $oldVal = isset($beforeUser[$k]) ? (string)$beforeUser[$k] : '';
                        $newVal = isset($params[$k]) ? (string)$params[$k] : '';
                        if ($k === 'users_pic') {
                            $changes[] = $label . ': [changed]';
                        } else {
                            $changes[] = $label . ": '" . $oldVal . "' -> '" . $newVal . "'";
                        }
                    }
                    $changesList = implode(', ', $changes);

                    $description = 'Profile updated for: ' . $subjectName;
                    if ($changesList !== '') { $description .= ' (Changes: ' . $changesList . ')'; }
                    $action = 'UPDATE PROFILE';

                    $stmtAudit = $this->conn->prepare("INSERT INTO audit_log (description, action, created_at, created_by) VALUES (:description, :action, NOW(), :created_by)");
                    $stmtAudit->bindParam(':description', $description, PDO::PARAM_STR);
                    $stmtAudit->bindParam(':action', $action, PDO::PARAM_STR);
                    $stmtAudit->bindParam(':created_by', $actorId, PDO::PARAM_INT);
                    $stmtAudit->execute();
                } catch (Exception $e) { /* ignore audit logging errors */ }

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



    public function fetchHoliday() {
        $sql = "SELECT `holiday_id`, `holiday_name`, `holiday_date` FROM `tbl_holidays` WHERE 1";
        return $this->executeQuery($sql);
    }

    public function updateHoliday($holidayId, $holidayName, $holidayDate, $userId = null) {
        try {
            // Debug: log userId and target holiday
            error_log("updateHoliday userId=" . var_export($userId, true) . ", holidayId=" . var_export($holidayId, true));
            // First, get the current values to check if they're the same
            $getCurrentSql = "SELECT holiday_name, holiday_date FROM `tbl_holidays` 
                             WHERE `holiday_id` = :holiday_id";
            
            $getCurrentStmt = $this->conn->prepare($getCurrentSql);
            $getCurrentStmt->execute([':holiday_id' => $holidayId]);
            $currentData = $getCurrentStmt->fetch(PDO::FETCH_ASSOC);
    
            if (!$currentData) {
                return json_encode([
                    'status' => 'error', 
                    'message' => 'Holiday not found'
                ]);
            }
    
            // If values are the same as current values, still audit and return success
            if ($currentData['holiday_name'] === $holidayName && 
                $currentData['holiday_date'] === $holidayDate) {
                // Audit log (non-blocking)
                try {
                    $auditSql = "INSERT INTO audit_log (description, action, created_at, created_by) VALUES (:description, :action, NOW(), :created_by)";
                    $audit = $this->conn->prepare($auditSql);
                    $desc = "Updated Holiday: '" . ($currentData['holiday_name'] ?? '') . "' on '" . ($currentData['holiday_date'] ?? '') . "' -> '" . ($holidayName ?? '') . "' on '" . ($holidayDate ?? '') . "'";
                    $action = 'UPDATE';
                    $audit->bindParam(':description', $desc, PDO::PARAM_STR);
                    $audit->bindParam(':action', $action, PDO::PARAM_STR);
                    if ($userId !== null) {
                        $audit->bindValue(':created_by', $userId, PDO::PARAM_INT);
                    } else {
                        $audit->bindValue(':created_by', null, PDO::PARAM_NULL);
                    }
                    $audit->execute();
                    try {
                        $sel = $this->conn->prepare("SELECT id, description, action, created_at, created_by FROM audit_log WHERE 1 ORDER BY id DESC LIMIT 1");
                        $sel->execute();
                        $latest = $sel->fetch(PDO::FETCH_ASSOC);
                        error_log("audit_log latest (updateHoliday same-values): " . json_encode($latest));
                    } catch (PDOException $ex) {
                        error_log("Audit log select failed (updateHoliday same-values): " . $ex->getMessage());
                    }
                } catch (PDOException $e) {
                    error_log("Audit log insert failed (updateHoliday same-values): " . $e->getMessage());
                }
                return json_encode([
                    'status' => 'success', 
                    'message' => 'Holiday updated successfully'
                ]);
            }
    
            // Check if another holiday with the same name and date exists (excluding current holiday)
            $checkSql = "SELECT holiday_id FROM `tbl_holidays` 
                         WHERE `holiday_name` = :holiday_name 
                         AND `holiday_date` = :holiday_date 
                         AND `holiday_id` != :holiday_id";
            
            $checkStmt = $this->conn->prepare($checkSql);
            $checkStmt->execute([
                ':holiday_name' => $holidayName,
                ':holiday_date' => $holidayDate,
                ':holiday_id' => $holidayId
            ]);
    
            if ($checkStmt->rowCount() > 0) {
                return json_encode([
                    'status' => 'error', 
                    'message' => 'A holiday with the same name and date already exists'
                ]);
            }
    
            // Proceed with update if no duplicate found
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
                // Audit log (non-blocking)
                try {
                    $auditSql = "INSERT INTO audit_log (description, action, created_at, created_by) VALUES (:description, :action, NOW(), :created_by)";
                    $audit = $this->conn->prepare($auditSql);
                    $desc = "Updated Holiday: '" . ($currentData['holiday_name'] ?? '') . "' on '" . ($currentData['holiday_date'] ?? '') . "' -> '" . ($holidayName ?? '') . "' on '" . ($holidayDate ?? '') . "'";
                    $action = 'UPDATE';
                    $audit->bindParam(':description', $desc, PDO::PARAM_STR);
                    $audit->bindParam(':action', $action, PDO::PARAM_STR);
                    if ($userId !== null) {
                        $audit->bindValue(':created_by', $userId, PDO::PARAM_INT);
                    } else {
                        $audit->bindValue(':created_by', null, PDO::PARAM_NULL);
                    }
                    $audit->execute();
                    try {
                        $sel = $this->conn->prepare("SELECT id, description, action, created_at, created_by FROM audit_log WHERE 1 ORDER BY id DESC LIMIT 1");
                        $sel->execute();
                        $latest = $sel->fetch(PDO::FETCH_ASSOC);
                        error_log("audit_log latest (updateHoliday): " . json_encode($latest));
                    } catch (PDOException $ex) {
                        error_log("Audit log select failed (updateHoliday): " . $ex->getMessage());
                    }
                } catch (PDOException $e) {
                    error_log("Audit log insert failed (updateHoliday): " . $e->getMessage());
                }
                return json_encode([
                    'status' => 'success', 
                    'message' => 'Holiday updated successfully'
                ]);
            } else {
                return json_encode([
                    'status' => 'error', 
                    'message' => 'No changes made or holiday not found'
                ]);
            }
            
        } catch (PDOException $e) {
            // Handle specific duplicate entry error
            if ($e->getCode() == 23000 && strpos($e->getMessage(), 'Duplicate entry') !== false) {
                return json_encode([
                    'status' => 'error', 
                    'message' => 'A holiday with the same name and date already exists'
                ]);
            }
            
            // Handle other database errors
            return json_encode([
                'status' => 'error', 
                'message' => 'Database error: ' . $e->getMessage()
            ]);
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

        // Enforce always-increase behavior: quantity must be positive
        if ($inputQuantity <= 0) {
            return json_encode(['status' => 'error', 'message' => 'Quantity must be greater than 0']);
        }

        // Determine actor id (for audit created_by)
        $actorId = null;
        if (isset($data['user_personnel_id']) && $data['user_personnel_id'] !== '') {
            $actorId = (int)$data['user_personnel_id'];
        } elseif (isset($data['user_admin_id']) && $data['user_admin_id'] !== '') {
            $actorId = (int)$data['user_admin_id'];
        }

        // Fetch equipment name for audit description
        $equipName = null;
        try {
            $enameStmt = $this->conn->prepare("SELECT equip_name FROM tbl_equipments WHERE equip_id = :id");
            $enameStmt->execute([':id' => $equip_id]);
            $row = $enameStmt->fetch(PDO::FETCH_ASSOC);
            if ($row && isset($row['equip_name'])) {
                $equipName = $row['equip_name'];
            }
        } catch (Throwable $te) { /* ignore name fetch errors */ }

        // Check current stock entry
        $checkSql = "SELECT quantity, on_hand_quantity FROM tbl_equipment_quantity WHERE equip_id = :equip_id";
        $checkStmt = $this->conn->prepare($checkSql);
        $checkStmt->bindParam(':equip_id', $equip_id, PDO::PARAM_INT);
        $checkStmt->execute();
        if ($checkStmt->rowCount() > 0) {
            // Always increment existing stock by the provided quantity
            $sql = "UPDATE tbl_equipment_quantity SET 
                        quantity = quantity + :add_qty,
                        on_hand_quantity = on_hand_quantity + :add_qty,
                        last_updated = NOW(),
                        user_admin_id = :user_admin_id
                    WHERE equip_id = :equip_id";

            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':add_qty', $inputQuantity, PDO::PARAM_INT);
            $stmt->bindParam(':user_admin_id', $user_admin_id, PDO::PARAM_INT);
            $stmt->bindParam(':equip_id', $equip_id, PDO::PARAM_INT);

            if ($stmt->execute()) {
                // Audit log (non-blocking): Equipment (name) has increase quantity: X
                try {
                    $desc = 'Equipment (' . ($equipName ?? ('#' . $equip_id)) . ') has increase quantity: ' . $inputQuantity;
                    $auditSql = "INSERT INTO audit_log (description, action, created_at, created_by) VALUES (:description, :action, NOW(), :created_by)";
                    $audit = $this->conn->prepare($auditSql);
                    $audit->execute([
                        ':description' => $desc,
                        ':action' => 'UPDATE QUANTITY',
                        ':created_by' => $actorId
                    ]);
                } catch (Throwable $te) { /* ignore audit errors */ }
                return json_encode([
                    'status' => 'success',
                    'message' => "Stock increased by $inputQuantity successfully"
                ]);
            }
            return json_encode(['status' => 'error', 'message' => 'Failed to update stock']);
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
                // Audit log (non-blocking): Equipment (name) has increase quantity: X
                try {
                    $desc = 'Equipment (' . ($equipName ?? ('#' . $equip_id)) . ') has increase quantity: ' . $inputQuantity;
                    $auditSql = "INSERT INTO audit_log (description, action, created_at, created_by) VALUES (:description, :action, NOW(), :created_by)";
                    $audit = $this->conn->prepare($auditSql);
                    $audit->execute([
                        ':description' => $desc,
                        ':action' => 'UPDATE STOCK',
                        ':created_by' => $actorId
                    ]);
                } catch (Throwable $te) { /* ignore audit errors */ }
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

        // Determine actor id (for audit created_by)
        $actorId = null;
        if (isset($data['user_personnel_id']) && $data['user_personnel_id'] !== '') {
            $actorId = (int)$data['user_personnel_id'];
        } elseif (isset($data['user_admin_id']) && $data['user_admin_id'] !== '') {
            $actorId = (int)$data['user_admin_id'];
        }

        // Fetch equipment name for audit description
        $equipName = null;
        try {
            $enameStmt = $this->conn->prepare("SELECT equip_name FROM tbl_equipments WHERE equip_id = :id");
            $enameStmt->execute([':id' => $data['equip_id']]);
            $row = $enameStmt->fetch(PDO::FETCH_ASSOC);
            if ($row && isset($row['equip_name'])) {
                $equipName = $row['equip_name'];
            }
        } catch (Throwable $te) { /* ignore name fetch errors */ }

        // Insert query (brand, size, color removed)
        $sql = "INSERT INTO tbl_equipment_unit (
                    equip_id, serial_number, status_availability_id, unit_created_at, is_active, user_admin_id
                ) VALUES (
                    :equip_id, :serial_number, :status_id, NOW(), 1, :admin_id
                )";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':equip_id', $data['equip_id'], PDO::PARAM_INT);
        $stmt->bindParam(':serial_number', $data['serial_number']);
        $stmt->bindParam(':status_id', $data['status_availability_id'], PDO::PARAM_INT);
        $stmt->bindParam(':admin_id', $data['user_admin_id'], PDO::PARAM_INT);

        $ok = $stmt->execute();
        if ($ok) {
            // Audit log (non-blocking): Equipment (name) has new Serial Number: {serial}
            try {
                $desc = 'Equipment (' . ($equipName ?? ('#' . $data['equip_id'])) . ') has new Serial Number: ' . $data['serial_number'];
                $auditSql = "INSERT INTO audit_log (description, action, created_at, created_by) VALUES (:description, :action, NOW(), :created_by)";
                $audit = $this->conn->prepare($auditSql);
                $audit->execute([
                    ':description' => $desc,
                    ':action' => 'CREATE UNIT',
                    ':created_by' => $actorId
                ]);
            } catch (Throwable $te) { /* ignore audit errors */ }
            return json_encode(['status' => 'success', 'message' => 'Equipment unit added successfully']);
        } else {
            return json_encode(['status' => 'error', 'message' => 'Failed to add equipment unit']);
        }

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
            'status_availability_id' => PDO::PARAM_INT,
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
        
        // Bind all other parameters (use bindValue since we have literal values, not references)
        foreach ($params as $field => $param) {
            $updateStmt->bindValue(":$field", $param['value'], $param['type']);
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


public function saveHoliday($data, $userId = null) {
        try {
            // Debug: log userId for auditing context
            error_log("saveHoliday userId=" . var_export($userId, true));
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
                // Non-blocking audit log insert
                try {
                    $auditSql = "INSERT INTO audit_log (description, action, created_at, created_by) VALUES (:description, :action, NOW(), :created_by)";
                    $audit = $this->conn->prepare($auditSql);
                    $desc = "Created Holiday: '" . ($data['holiday_name'] ?? '') . "' on '" . ($data['holiday_date'] ?? '') . "'";
                    $action = 'CREATE';
                    $audit->bindParam(':description', $desc, PDO::PARAM_STR);
                    $audit->bindParam(':action', $action, PDO::PARAM_STR);
                    if ($userId !== null) {
                        $audit->bindValue(':created_by', $userId, PDO::PARAM_INT);
                    } else {
                        $audit->bindValue(':created_by', null, PDO::PARAM_NULL);
                    }
                    if (!$audit->execute()) {
                        error_log("Audit log insert failed (saveHoliday): " . print_r($audit->errorInfo(), true));
                    } else {
                        try {
                            $latestAuditStmt = $this->conn->query("SELECT id, description, action, created_at, created_by FROM audit_log ORDER BY id DESC LIMIT 1");
                            $latest = $latestAuditStmt ? $latestAuditStmt->fetch(PDO::FETCH_ASSOC) : null;
                            error_log("audit_log latest (saveHoliday): " . json_encode($latest));
                        } catch (Throwable $te) {
                            error_log("Failed to read back latest audit_log (saveHoliday): " . $te->getMessage());
                        }
                    }
                } catch (Throwable $e2) {
                    error_log("Audit logging error (saveHoliday): " . $e2->getMessage());
                }

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

   
public function saveEquipment($json) {
    try {
        $data = is_array($json) ? $json : json_decode($json, true);
        if (!is_array($data)) {
            return json_encode(['status' => 'error', 'message' => 'Invalid input data']);
        }

        // Validate required fields
        $required = ['name', 'equipments_category_id', 'equip_type', 'user_admin_id'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                return json_encode(['status' => 'error', 'message' => "$field is required or invalid"]);
            }
            
            // Check if numeric fields are actually numeric
            if (in_array($field, ['equipments_category_id']) && !is_numeric($data[$field])) {
                return json_encode(['status' => 'error', 'message' => "$field must be numeric"]);
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

        if ($stmt->execute()) {
            $equipId = $this->conn->lastInsertId();
            // Audit log
            try {
                $auditSql = "INSERT INTO audit_log (description, action, created_at, created_by) VALUES (:description, :action, NOW(), :created_by)";
                $audit = $this->conn->prepare($auditSql);
                $desc = "Created Equipment : " . $data['name'];
                $action = 'CREATE';
                $audit->bindParam(':description', $desc, PDO::PARAM_STR);
                $audit->bindParam(':action', $action, PDO::PARAM_STR);
                $audit->bindParam(':created_by', $data['user_admin_id'], PDO::PARAM_INT);
                $audit->execute();
            } catch (PDOException $e) {
                error_log("Audit log insert failed (saveEquipment): " . $e->getMessage());
            }
            return json_encode(['status' => 'success', 'message' => 'Equipment added successfully', 'equip_id' => $equipId]);
        }
        return json_encode(['status' => 'error', 'message' => 'Failed to insert equipment']);

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

        // Fetch old values for audit (name only)
        $oldName = null;
        try {
            $prevStmt = $this->conn->prepare("SELECT equip_name FROM tbl_equipments WHERE equip_id = :id");
            $prevStmt->execute([':id' => $data['equip_id']]);
            $prev = $prevStmt->fetch(PDO::FETCH_ASSOC);
            if ($prev && isset($prev['equip_name'])) {
                $oldName = $prev['equip_name'];
            }
        } catch (PDOException $e) { /* ignore for main flow */ }

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

        $success = $stmt->execute();
        if ($success) {
            // Non-blocking audit logging
            try {
                // Determine actor id: prefer user_personnel_id, fallback to user_admin_id
                $actorId = null;
                if (isset($data['user_personnel_id']) && $data['user_personnel_id'] !== '') {
                    $actorId = (int)$data['user_personnel_id'];
                } elseif (isset($data['user_admin_id']) && $data['user_admin_id'] !== '') {
                    $actorId = (int)$data['user_admin_id'];
                }

                $newName = $data['equip_name'] ?? '';
                if ($oldName !== null && $oldName !== $newName) {
                    $desc = "Updated Equipment: '" . $oldName . "' -> '" . $newName . "'";
                } else {
                    $desc = 'Updated Equipment: ' . $newName;
                }

                $auditSql = "INSERT INTO audit_log (description, action, created_at, created_by) VALUES (:description, :action, NOW(), :created_by)";
                $audit = $this->conn->prepare($auditSql);
                $audit->execute([
                    ':description' => $desc,
                    ':action' => 'UPDATE',
                    ':created_by' => $actorId
                ]);
            } catch (Throwable $te) { /* ignore audit errors */ }

            return json_encode(['status' => 'success', 'message' => 'Equipment updated successfully']);
        } else {
            return json_encode(['status' => 'error', 'message' => 'Failed to update equipment']);
        }

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

        // Pre-insert validation: check duplicate vehicle_license to avoid SQL duplicate errors
        $licenseToCheck = isset($data['vehicle_license']) ? trim($data['vehicle_license']) : '';
        $dupSql = "SELECT vehicle_id FROM tbl_vehicle WHERE LOWER(TRIM(vehicle_license)) = LOWER(TRIM(:license)) LIMIT 1";
        $dupStmt = $this->conn->prepare($dupSql);
        $dupStmt->bindParam(':license', $licenseToCheck);
        $dupStmt->execute();
        if ($dupStmt->fetch(PDO::FETCH_ASSOC)) {
            return json_encode(['status' => 'error', 'message' => 'Vehicle license already exists', 'field' => 'vehicle_license']);
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

        if ($stmt->execute()) {
            $vehicleId = $this->conn->lastInsertId();
            // Audit log
            try {
                $auditSql = "INSERT INTO audit_log (description, action, created_at, created_by) VALUES (:description, :action, NOW(), :created_by)";
                $audit = $this->conn->prepare($auditSql);
                $desc = "Created Vehicle : " . $data['vehicle_license'];
                $action = 'CREATE';
                $audit->bindParam(':description', $desc, PDO::PARAM_STR);
                $audit->bindParam(':action', $action, PDO::PARAM_STR);
                $audit->bindParam(':created_by', $data['user_admin_id'], PDO::PARAM_INT);
                $audit->execute();
            } catch (PDOException $e) {
                error_log("Audit log insert failed (saveVehicle): " . $e->getMessage());
            }
            return json_encode(['status' => 'success', 'message' => 'Vehicle added successfully.', 'vehicle_id' => $vehicleId]);
        }
        return json_encode(['status' => 'error', 'message' => 'Failed to add vehicle']);
    } catch (PDOException $e) {
        error_log("saveVehicle error: " . $e->getMessage());
        // Translate SQL duplicate errors into a clean message
        if ($e->getCode() === '23000') { // Integrity constraint violation (e.g., duplicate key)
            return json_encode(['status' => 'error', 'message' => 'Vehicle license already exists', 'field' => 'vehicle_license']);
        }
        return json_encode(['status' => 'error', 'message' => 'An unexpected error occurred while saving the vehicle']);
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

        // Fetch old values (for audit description)
        $oldLicense = null;
        try {
            $prevStmt = $this->conn->prepare("SELECT vehicle_license FROM tbl_vehicle WHERE vehicle_id = :id");
            $prevStmt->execute([':id' => $vehicleData['vehicle_id']]);
            $prev = $prevStmt->fetch(PDO::FETCH_ASSOC);
            if ($prev && isset($prev['vehicle_license'])) {
                $oldLicense = $prev['vehicle_license'];
            }
        } catch (PDOException $e) {
            // Non-fatal for main flow; continue without old value
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

        // Execute update
        $success = $stmt->execute();
        if ($success) {
            // Non-blocking audit logging for vehicle update
            try {
                // Determine actor id: prefer user_personnel_id, fallback to user_admin_id
                $actorId = null;
                if (isset($vehicleData['user_personnel_id']) && $vehicleData['user_personnel_id'] !== '') {
                    $actorId = (int)$vehicleData['user_personnel_id'];
                } elseif (isset($vehicleData['user_admin_id']) && $vehicleData['user_admin_id'] !== '') {
                    $actorId = (int)$vehicleData['user_admin_id'];
                }

                // Compose description
                $newLicense = $vehicleData['vehicle_license'] ?? '';
                if ($oldLicense !== null && $oldLicense !== $newLicense) {
                    $desc = "Updated Vehicle: '" . $oldLicense . "' -> '" . $newLicense . "'";
                } else {
                    $desc = 'Updated Vehicle: ' . $newLicense;
                }

                $auditSql = "INSERT INTO audit_log (description, action, created_at, created_by) VALUES (:description, :action, NOW(), :created_by)";
                $auditStmt = $this->conn->prepare($auditSql);
                $auditStmt->execute([
                    ':description' => $desc,
                    ':action' => 'UPDATE VEHICLE',
                    ':created_by' => $actorId
                ]);
            } catch (Throwable $te) { /* ignore audit errors */ }

            return json_encode(['status' => 'success', 'message' => 'Vehicle updated successfully', 'vehicle_id' => $vehicleData['vehicle_id']]);
        } else {
            return json_encode(['status' => 'error', 'message' => 'Failed to update vehicle']);
        }
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
        if (!isset($data['name']) || !isset($data['occupancy']) || !isset($data['event_type']) || !isset($data['area_type'])) {
            return json_encode(['status' => 'error', 'message' => 'Missing required fields']);
        }
        if ($this->venueExists($data['name'])) {
            return json_encode(['status' => 'error', 'message' => 'This venue name is already in use.']);
        }

        $sql = "INSERT INTO tbl_venue 
                (ven_name, ven_occupancy, status_availability_id, user_admin_id, event_type, area_type) 
                VALUES (:name, :occupancy, 1, :admin_id, :event_type, :area_type)";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':name', $data['name'], PDO::PARAM_STR);
        $stmt->bindParam(':occupancy', $data['occupancy'], PDO::PARAM_INT);
        $stmt->bindParam(':admin_id', $data['user_admin_id'], PDO::PARAM_INT);
        $stmt->bindParam(':event_type', $data['event_type'], PDO::PARAM_STR);
        $stmt->bindParam(':area_type', $data['area_type'], PDO::PARAM_STR);

        if ($stmt->execute()) {
            $venueId = $this->conn->lastInsertId();
            // Audit log
            try {
                $auditSql = "INSERT INTO audit_log (description, action, created_at, created_by) VALUES (:description, :action, NOW(), :created_by)";
                $audit = $this->conn->prepare($auditSql);
                $desc = "Created Venue : " . $data['name'];
                $action = 'CREATE';
                $audit->bindParam(':description', $desc, PDO::PARAM_STR);
                $audit->bindParam(':action', $action, PDO::PARAM_STR);
                $audit->bindParam(':created_by', $data['user_admin_id'], PDO::PARAM_INT);
                $audit->execute();
            } catch (PDOException $e) {
                error_log("Audit log insert failed (saveVenue): " . $e->getMessage());
            }
            return json_encode(['status' => 'success', 'message' => 'Venue added successfully', 'venue_id' => $venueId]);
        }

        return json_encode(['status' => 'error', 'message' => 'Failed to add venue']);
    } catch(PDOException $e) {
        error_log("Database error: " . $e->getMessage());
        return json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

public function updateVenue($venueData) {
    try {
        if (!isset($venueData['venue_id'], $venueData['venue_name'], $venueData['max_occupancy'], $venueData['status_availability_id'], $venueData['event_type'], $venueData['area_type'])) {
            return json_encode(['status' => 'error', 'message' => 'Missing required fields']);
        }

        $sql = "UPDATE tbl_venue SET 
                    ven_name = :venue_name, 
                    ven_occupancy = :max_occupancy,
                    status_availability_id = :status_availability_id,
                    event_type = :event_type,
                    area_type = :area_type
                WHERE ven_id = :venue_id";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':venue_name', $venueData['venue_name'], PDO::PARAM_STR);
        $stmt->bindParam(':max_occupancy', $venueData['max_occupancy'], PDO::PARAM_INT);
        $stmt->bindParam(':status_availability_id', $venueData['status_availability_id'], PDO::PARAM_INT);
        $stmt->bindParam(':event_type', $venueData['event_type'], PDO::PARAM_STR);
        $stmt->bindParam(':area_type', $venueData['area_type'], PDO::PARAM_STR);
        $stmt->bindParam(':venue_id', $venueData['venue_id'], PDO::PARAM_INT);

        $success = $stmt->execute();
        if ($success) {
            // Non-blocking audit logging for venue update
            try {
                // Determine actor id: prefer user_personnel_id, fallback to user_admin_id if provided
                $actorId = null;
                if (isset($venueData['user_personnel_id']) && $venueData['user_personnel_id'] !== '') {
                    $actorId = (int)$venueData['user_personnel_id'];
                } elseif (isset($venueData['user_admin_id']) && $venueData['user_admin_id'] !== '') {
                    $actorId = (int)$venueData['user_admin_id'];
                }

                // Get actor name
                $actorName = null;
                if (!empty($actorId)) {
                    $personSql = "SELECT CONCAT(\n                                    users_fname,\n                                    CASE WHEN users_mname IS NOT NULL AND users_mname != '' THEN CONCAT(' ', LEFT(users_mname, 1), '.') ELSE '' END,\n                                    ' ', users_lname\n                                  ) AS full_name\n                               FROM tbl_users WHERE users_id = :id";
                    $personStmt = $this->conn->prepare($personSql);
                    $personStmt->execute([':id' => $actorId]);
                    $row = $personStmt->fetch(PDO::FETCH_ASSOC);
                    $actorName = $row && !empty($row['full_name']) ? $row['full_name'] : ('User #' . $actorId);
                }

                $venueName = $venueData['venue_name'] ?? '';
                $desc = 'Updated Venue: ' . $venueName;

                $auditSql = "INSERT INTO audit_log (description, action, created_at, created_by) VALUES (:description, :action, NOW(), :created_by)";
                $auditStmt = $this->conn->prepare($auditSql);
                $auditStmt->execute([
                    ':description' => $desc,
                    ':action' => 'UPDATE VENUE',
                    ':created_by' => $actorId
                ]);
            } catch (Throwable $te) { /* ignore audit errors */ }

            return json_encode(['status' => 'success', 'message' => 'Venue updated successfully']);
        } else {
            return json_encode(['status' => 'error', 'message' => 'Could not update venue']);
        }
    } catch (PDOException $e) {
        error_log('Database error in updateVenue: ' . $e->getMessage());
        return json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

public function saveUser($data) {
    try {
        // Make sure schoolId is a string
        if (isset($data['schoolId'])) {
            $data['schoolId'] = (string) $data['schoolId'];
        }

        // ——— 1) Check if the SAME school ID + email already exists ———
        $sql = "SELECT COUNT(*) AS cnt
                  FROM tbl_users
                 WHERE users_school_id = :schoolId
                   AND users_email     = :email";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':schoolId', $data['schoolId'], PDO::PARAM_STR);
        $stmt->bindParam(':email',    $data['email'],    PDO::PARAM_STR);
        $stmt->execute();
        if ((int)$stmt->fetch(PDO::FETCH_OBJ)->cnt > 0) {
            return json_encode([
                'status'  => 'error',
                'message' => 'A user with that School ID and Email already exists.'
            ]);
        }

        // ——— 2) Check if school ID alone already exists ———
        $sql = "SELECT COUNT(*) AS cnt
                  FROM tbl_users
                 WHERE users_school_id = :schoolId";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':schoolId', $data['schoolId'], PDO::PARAM_STR);
        $stmt->execute();
        if ((int)$stmt->fetch(PDO::FETCH_OBJ)->cnt > 0) {
            return json_encode([
                'status'  => 'error',
                'message' => 'School ID already exists.'
            ]);
        }

        // ——— 3) Check if email alone already exists ———
        $sql = "SELECT COUNT(*) AS cnt
                  FROM tbl_users
                 WHERE users_email = :email";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':email', $data['email'], PDO::PARAM_STR);
        $stmt->execute();
        if ((int)$stmt->fetch(PDO::FETCH_OBJ)->cnt > 0) {
            return json_encode([
                'status'  => 'error',
                'message' => 'Email address already exists.'
            ]);
        }

        // ——— 4) Check if full personal details already exist ———
        $sql = "SELECT COUNT(*) AS cnt
                  FROM tbl_users
                 WHERE users_fname     = :fname
                   AND users_mname     = :mname
                   AND users_lname     = :lname
                   AND users_birthdate = :birthdate";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':fname',     $data['fname'],     PDO::PARAM_STR);
        $stmt->bindParam(':mname',     $data['mname'],     PDO::PARAM_STR);
        $stmt->bindParam(':lname',     $data['lname'],     PDO::PARAM_STR);
        $stmt->bindParam(':birthdate', $data['birthdate'], PDO::PARAM_STR);
        $stmt->execute();
        if ((int)$stmt->fetch(PDO::FETCH_OBJ)->cnt > 0) {
            return json_encode([
                'status'  => 'error',
                'message' => 'A user with the same personal details already exists.'
            ]);
        }

        // ——— 5) Handle base64 image upload ———
        $picPath = null;
        if (!empty($data['pic'])) {
            $uploadDir = 'uploads/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            list($meta, $b64) = explode(';base64,', $data['pic']);
            $type    = substr($meta, strpos($meta, '/') + 1);
            $decoded = base64_decode($b64);
            $filename = 'profile_' . time() . '.' . $type;
            $picPath   = $uploadDir . $filename;
            file_put_contents($picPath, $decoded);
        }

        // ——— 6) Hash password ———
        $hashedPassword = password_hash($data['password'], PASSWORD_BCRYPT);

        // ——— 7) Insert new user ———
        $sql = "INSERT INTO tbl_users (
                    title_id,
                    users_fname, users_mname, users_lname,
                    users_email, users_school_id, users_contact_number,
                    users_user_level_id, users_password, users_department_id,
                    users_birthdate, users_suffix, users_pic,
                    first_login,
                    users_created_at, users_updated_at
                ) VALUES (
                    :title_id,
                    :fname, :mname, :lname,
                    :email, :schoolId, :contact,
                    :userLevelId, :password, :departmentId,
                    :birthdate, :suffix, :pic,
                    1,               -- force change-password-on-first-login
                    NOW(), NOW()
                )";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':title_id',     $data['title_id'],     PDO::PARAM_INT);
        $stmt->bindParam(':fname',        $data['fname'],        PDO::PARAM_STR);
        $stmt->bindParam(':mname',        $data['mname'],        PDO::PARAM_STR);
        $stmt->bindParam(':lname',        $data['lname'],        PDO::PARAM_STR);
        $stmt->bindParam(':email',        $data['email'],        PDO::PARAM_STR);
        $stmt->bindParam(':schoolId',     $data['schoolId'],     PDO::PARAM_STR);
        $stmt->bindParam(':contact',      $data['contact'],      PDO::PARAM_STR);
        $stmt->bindParam(':userLevelId',  $data['userLevelId'],  PDO::PARAM_INT);
        $stmt->bindParam(':password',     $hashedPassword,       PDO::PARAM_STR);
        $stmt->bindParam(':departmentId', $data['departmentId'], PDO::PARAM_INT);
        $stmt->bindParam(':birthdate',    $data['birthdate'],    PDO::PARAM_STR);
        $stmt->bindParam(':suffix',       $data['suffix'],       PDO::PARAM_STR);
        $stmt->bindParam(':pic',          $picPath,              PDO::PARAM_STR);

        if ($stmt->execute()) {
            // Send the default password to the user's email (not encrypted)
            $mail = new PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host = 'smtp.gmail.com';
                $mail->SMTPAuth = true;
                $mail->Username = 'noreplygsd12@gmail.com';
                $mail->Password = 'ckfo wpow pfmq ziwd'; // App password
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port = 587;
                $mail->SMTPOptions = array(
                    'ssl' => array(
                        'verify_peer' => false,
                        'verify_peer_name' => false,
                        'allow_self_signed' => true
                    )
                );
                $mail->setFrom('vallechristianmark@gmail.com', 'GSD System');
                $mail->addAddress($data['email']);
                $mail->isHTML(true);
                $mail->Subject = 'Your GSD Account Default Password';
                $mail->Body = '<p>Dear ' . htmlspecialchars($data['fname']) . ',</p>' .
                    '<p>Your account has been created.</p>' .
                    '<p><b>Username (School ID):</b> ' . htmlspecialchars($data['schoolId']) . '<br>' .
                    '<b>Default Password:</b> ' . htmlspecialchars($data['password']) . '</p>' .
                    '<p>Please log in and change your password immediately.</p>' .
                    '<p>Thank you,<br>GSD System</p>';
                $mail->send();
            } catch (Exception $e) {
                // Optionally log or handle email sending failure
            }
            // Audit log (non-blocking): User: (fullname) has been created
            try {
                // Determine actor
                $actorId = null;
                if (isset($data['user_personnel_id']) && $data['user_personnel_id'] !== '') {
                    $actorId = (int)$data['user_personnel_id'];
                } elseif (isset($data['user_admin_id']) && $data['user_admin_id'] !== '') {
                    $actorId = (int)$data['user_admin_id'];
                }

                // Build full name from provided data
                $mInitial = (isset($data['mname']) && trim($data['mname']) !== '') ? (' ' . strtoupper(substr($data['mname'], 0, 1)) . '.') : '';
                $fullName = trim(($data['fname'] ?? '') . $mInitial . ' ' . ($data['lname'] ?? ''));
                $desc = 'User: ' . $fullName . ' has been created';

                $auditSql = "INSERT INTO audit_log (description, action, created_at, created_by) VALUES (:description, :action, NOW(), :created_by)";
                $audit = $this->conn->prepare($auditSql);
                $audit->execute([
                    ':description' => $desc,
                    ':action' => 'CREATE USER',
                    ':created_by' => $actorId
                ]);
            } catch (Throwable $te) { /* ignore audit errors */ }
            return json_encode([
                'status'  => 'success',
                'message' => 'User added successfully.'
            ]);
        }

        return json_encode([
            'status'  => 'error',
            'message' => 'Failed to add user.'
        ]);
    } catch (PDOException $e) {
        return json_encode([
            'status'  => 'error',
            'message' => 'Database error: ' . $e->getMessage()
        ]);
    }
}



public function updateUser($userData) {
    try {
        // Check required keys
        if (!isset($userData['userId'], $userData['schoolId'], $userData['email'])) {
            return json_encode([
                'status' => 'error',
                'message' => 'Missing required fields: userId, schoolId, or email.'
            ]);
        }
        // Cast and extract
        $userId    = (int)   $userData['userId'];
        $schoolId  = (string)$userData['schoolId'];
        $email     =           $userData['email'];

        // 1) Check if another user has the same School ID + Email
        $sql = "SELECT COUNT(*) AS cnt
                  FROM tbl_users
                 WHERE users_school_id = :schoolId
                   AND users_email     = :email
                   AND users_id       != :userId";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            ':schoolId' => $schoolId,
            ':email'    => $email,
            ':userId'   => $userId
        ]);
        if ((int)$stmt->fetch(PDO::FETCH_OBJ)->cnt > 0) {
            return json_encode([
                'status'  => 'error',
                'message' => 'Another user with that School ID and Email already exists.'
            ]);
        }

        // 2) Check if another user has the same School ID
        $sql = "SELECT COUNT(*) AS cnt
                  FROM tbl_users
                 WHERE users_school_id = :schoolId
                   AND users_id       != :userId";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            ':schoolId' => $schoolId,
            ':userId'   => $userId
        ]);
        if ((int)$stmt->fetch(PDO::FETCH_OBJ)->cnt > 0) {
            return json_encode([
                'status'  => 'error',
                'message' => 'Another user with that School ID already exists.'
            ]);
        }

        // 3) Check if another user has the same Email
        $sql = "SELECT COUNT(*) AS cnt
                  FROM tbl_users
                 WHERE users_email = :email
                   AND users_id   != :userId";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            ':email'  => $email,
            ':userId' => $userId
        ]);
        if ((int)$stmt->fetch(PDO::FETCH_OBJ)->cnt > 0) {
            return json_encode([
                'status'  => 'error',
                'message' => 'Another user with that Email address already exists.'
            ]);
        }

        // Fetch existing user (for audit diff)
        $oldUser = null;
        try {
            $oldStmt = $this->conn->prepare("SELECT 
                    title_id,
                    users_fname, users_mname, users_lname,
                    users_birthdate, users_suffix,
                    users_email, users_school_id, users_contact_number,
                    users_user_level_id, users_department_id,
                    users_pic, is_active
                FROM tbl_users WHERE users_id = :userId");
            $oldStmt->execute([':userId' => $userId]);
            $oldUser = $oldStmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (Throwable $te) { /* ignore fetch errors */ }

        // —— Build the UPDATE statement ——
        $sql = "UPDATE tbl_users SET 
                    title_id             = :title_id,
                    users_fname          = :fname,
                    users_mname          = :mname,
                    users_lname          = :lname,
                    users_birthdate      = :birthdate,
                    users_suffix         = :suffix,
                    users_email          = :email,
                    users_school_id      = :schoolId,
                    users_contact_number = :contact,
                    users_user_level_id  = :userLevelId,
                    users_department_id  = :departmentId,
                    users_pic            = :pic,
                    is_active            = :isActive,
                    users_updated_at     = NOW()";

        if (!empty($userData['password'])) {
            $sql .= ", users_password = :password";
        }
        $sql .= " WHERE users_id = :userId";

        $stmt = $this->conn->prepare($sql);

        // Bind common params
        $stmt->bindParam(':title_id',     $userData['title_id'],     PDO::PARAM_INT);
        $stmt->bindParam(':fname',        $userData['fname'],        PDO::PARAM_STR);
        $stmt->bindParam(':mname',        $userData['mname'],        PDO::PARAM_STR);
        $stmt->bindParam(':lname',        $userData['lname'],        PDO::PARAM_STR);
        $stmt->bindParam(':birthdate',    $userData['birthdate'],    PDO::PARAM_STR);
        $stmt->bindParam(':suffix',       $userData['suffix'],       PDO::PARAM_STR);
        $stmt->bindParam(':email',        $email,                    PDO::PARAM_STR);
        $stmt->bindParam(':schoolId',     $schoolId,                 PDO::PARAM_STR);
        $stmt->bindParam(':contact',      $userData['contact'],      PDO::PARAM_STR);
        $stmt->bindParam(':userLevelId',  $userData['userLevelId'],  PDO::PARAM_INT);
        $stmt->bindParam(':departmentId', $userData['departmentId'], PDO::PARAM_INT);
        $stmt->bindParam(':pic',          $userData['pic'],          PDO::PARAM_STR);
        $stmt->bindParam(':isActive',     $userData['isActive'],     PDO::PARAM_BOOL);
        $stmt->bindParam(':userId',       $userId,                   PDO::PARAM_INT);

        // Hash & bind the new password if provided
        if (!empty($userData['password'])) {
            $hashed = password_hash($userData['password'], PASSWORD_BCRYPT);
            $stmt->bindParam(':password', $hashed, PDO::PARAM_STR);
        }

        // Execute update
        if ($stmt->execute()) {
            // Audit log (non-blocking): list specific field changes
            try {
                // Determine actor
                $actorId = null;
                if (isset($userData['user_personnel_id']) && $userData['user_personnel_id'] !== '') {
                    $actorId = (int)$userData['user_personnel_id'];
                } elseif (isset($userData['user_admin_id']) && $userData['user_admin_id'] !== '') {
                    $actorId = (int)$userData['user_admin_id'];
                }

                // Compute new full name and changes
                $mInitial = (isset($userData['mname']) && trim($userData['mname']) !== '') ? (' ' . strtoupper(substr($userData['mname'], 0, 1)) . '.') : '';
                $fullName = trim(($userData['fname'] ?? '') . $mInitial . ' ' . ($userData['lname'] ?? ''));

                $changes = [];
                if (is_array($oldUser)) {
                    $map = [
                        'title_id'             => ['label' => 'title',          'new' => $userData['title_id']        ?? null],
                        'users_fname'          => ['label' => 'first name',     'new' => $userData['fname']           ?? null],
                        'users_mname'          => ['label' => 'middle name',    'new' => $userData['mname']           ?? null],
                        'users_lname'          => ['label' => 'last name',      'new' => $userData['lname']           ?? null],
                        'users_birthdate'      => ['label' => 'birthdate',      'new' => $userData['birthdate']       ?? null],
                        'users_suffix'         => ['label' => 'suffix',         'new' => $userData['suffix']          ?? null],
                        'users_email'          => ['label' => 'email',          'new' => $email],
                        'users_school_id'      => ['label' => 'school id',      'new' => $schoolId],
                        'users_contact_number' => ['label' => 'contact number', 'new' => $userData['contact']         ?? null],
                        'users_user_level_id'  => ['label' => 'user level',     'new' => $userData['userLevelId']     ?? null],
                        'users_department_id'  => ['label' => 'department',     'new' => $userData['departmentId']    ?? null],
                        'users_pic'            => ['label' => 'pic',            'new' => $userData['pic']             ?? null],
                        'is_active'            => ['label' => 'is active',      'new' => isset($userData['isActive']) ? (int)$userData['isActive'] : null],
                    ];
                    foreach ($map as $col => $info) {
                        if (array_key_exists($col, $oldUser) && $info['new'] !== null) {
                            $oldVal = $oldUser[$col];
                            $newVal = $info['new'];
                            // normalize boolean-like values
                            if ($col === 'is_active') {
                                $oldVal = ($oldVal === null) ? null : (int)$oldVal;
                                $newVal = ($newVal === null) ? null : (int)$newVal;
                            }
                            if ((string)$oldVal !== (string)$newVal) {
                                $changes[] = $info['label'] . ': ' . ( ($oldVal === null || $oldVal === '') ? 'null' : $oldVal ) . ' -> ' . ( ($newVal === null || $newVal === '') ? 'null' : $newVal );
                            }
                        }
                    }
                    if (!empty($userData['password'])) {
                        $changes[] = 'password: changed';
                    }
                }

                $changeDesc = empty($changes) ? 'no field changes detected' : implode('; ', $changes);
                $desc = 'User: ' . $fullName . ' has been updated. Changes: ' . $changeDesc;

                $auditSql = "INSERT INTO audit_log (description, action, created_at, created_by) VALUES (:description, :action, NOW(), :created_by)";
                $audit = $this->conn->prepare($auditSql);
                $audit->execute([
                    ':description' => $desc,
                    ':action' => 'UPDATE USER',
                    ':created_by' => $actorId
                ]);
            } catch (Throwable $te) { /* ignore audit errors */ }

            return json_encode([
                'status'  => 'success',
                'message' => 'User updated successfully.'
            ]);
        }

        return json_encode([
            'status'  => 'error',
            'message' => 'Could not update user.'
        ]);
    }
    catch (PDOException $e) {
        return json_encode([
            'status'  => 'error',
            'message' => 'Database error: ' . $e->getMessage()
        ]);
    }
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

    // Insert a new reservation driver (for reservation_driver_user_id only, no driver_name)
    public function insertDriver($reservation_driver_user_id, $reservation_vehicle_id = null) {
        try {
            $sql = "INSERT INTO tbl_reservation_driver (
                        reservation_driver_user_id,
                        reservation_vehicle_id,
                        created_at,
                        updated_at
                    ) VALUES (
                        :reservation_driver_user_id,
                        :reservation_vehicle_id,
                        NOW(),
                        NOW()
                    )";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':reservation_driver_user_id', $reservation_driver_user_id, PDO::PARAM_INT);
            $stmt->bindParam(':reservation_vehicle_id', $reservation_vehicle_id, PDO::PARAM_INT);
            if ($stmt->execute()) {
                return json_encode([
                    'status' => 'success',
                    'message' => 'Driver assigned to reservation successfully',
                    'reservation_driver_id' => $this->conn->lastInsertId()
                ]);
            } else {
                return json_encode([
                    'status' => 'error',
                    'message' => 'Failed to assign driver to reservation'
                ]);
            }
        } catch (PDOException $e) {
            return json_encode([
                'status' => 'error',
                'message' => 'Database error: ' . $e->getMessage()
            ]);
        }
    }



    public function sendEnhancedNotification($userId, $message, $reservationId = null, $type = 'info') {
        try {
            // Include notification helper
            require_once 'notification_helper.php';
            $notificationHelper = new NotificationHelper();
            
            // Send notification using helper
            $result = $notificationHelper->sendUserNotification($userId, $message, $reservationId, $type);
            
            return $result;
        } catch (Exception $e) {
            return json_encode(['status' => 'error', 'message' => 'Notification error: ' . $e->getMessage()]);
        }
    }

    // Enhanced department notification with push support
    public function sendEnhancedDepartmentNotification($departmentId, $userLevelId, $message) {
        try {
            // Include notification helper
            require_once 'notification_helper.php';
            $notificationHelper = new NotificationHelper();
            
            // Send department notification using helper
            $result = $notificationHelper->sendDepartmentNotification($departmentId, $userLevelId, $message);
            
            return $result;
        } catch (Exception $e) {
            return json_encode(['status' => 'error', 'message' => 'Department notification error: ' . $e->getMessage()]);
        }
    }

    public function fetchRecord() {
    try {
        $sql = "
            SELECT 
                r.reservation_id, 
                r.reservation_title, 
                r.reservation_description, 
                r.reservation_start_date, 
                r.reservation_end_date, 
                r.reschedule_start_date,
                r.reschedule_end_date,
                r.reservation_participants, 
                r.reservation_user_id, 
                r.reservation_created_at, 
                CONCAT(u.users_fname, ' ', COALESCE(u.users_mname, ''), ' ', u.users_lname) AS user_full_name,
                sm.status_master_name AS reservation_status_name,
                latest_status.reservation_status_status_id,
                latest_status.reservation_updated_at,
                latest_status.reservation_active,
                CASE WHEN active_resched.max_reschedule_status_id IS NULL THEN 0 ELSE 1 END AS has_active_reschedule

            FROM tbl_reservation r

            LEFT JOIN (
                SELECT rs1.*
                FROM tbl_reservation_status rs1
                INNER JOIN (
                    SELECT reservation_reservation_id, MAX(reservation_status_id) AS max_status_id
                    FROM tbl_reservation_status
                    GROUP BY reservation_reservation_id
                ) rs2 ON rs1.reservation_reservation_id = rs2.reservation_reservation_id
                AND rs1.reservation_status_id = rs2.max_status_id
            ) latest_status ON latest_status.reservation_reservation_id = r.reservation_id

            LEFT JOIN (
                SELECT reservation_reservation_id, MAX(reservation_status_id) AS max_reschedule_status_id
                FROM tbl_reservation_status
                WHERE reservation_status_status_id IN (10, 11, 14) AND reservation_active IN (0, 1)
                GROUP BY reservation_reservation_id
            ) active_resched ON active_resched.reservation_reservation_id = r.reservation_id

            LEFT JOIN tbl_status_master sm ON sm.status_master_id = latest_status.reservation_status_status_id
            LEFT JOIN tbl_users u ON u.users_id = r.reservation_user_id

            ORDER BY r.reservation_created_at DESC
        ";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute();

        $reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Process each reservation to determine which dates to display
        // Following the same logic as fetchRequestById and fetchAvailability
        foreach ($reservations as &$reservation) {
            $statusId = (int)$reservation['reservation_status_status_id'];
            $active = (int)$reservation['reservation_active'];
            $hasActiveReschedule = (int)$reservation['has_active_reschedule'];
            
            // Determine the effective dates to use based on reschedule status
            // This follows the same pattern as fetchRequestById in reservation.php
            if ($statusId === 14 && $active === 1 && 
                !empty($reservation['reschedule_start_date']) && 
                !empty($reservation['reschedule_end_date'])) {
                // For active reschedule (status 14 with active=1), use reschedule dates
                $reservation['effective_start_date'] = $reservation['reschedule_start_date'];
                $reservation['effective_end_date'] = $reservation['reschedule_end_date'];
            } elseif ($statusId === 11 && 
                      !empty($reservation['reschedule_start_date']) && 
                      !empty($reservation['reschedule_end_date'])) {
                // For status 11 (rescheduled), use reschedule dates
                $reservation['effective_start_date'] = $reservation['reschedule_start_date'];
                $reservation['effective_end_date'] = $reservation['reschedule_end_date'];
            } elseif ($statusId === 10 && $hasActiveReschedule && 
                      !empty($reservation['reschedule_start_date']) && 
                      !empty($reservation['reschedule_end_date'])) {
                // For status 10 with active reschedule, use reschedule dates
                $reservation['effective_start_date'] = $reservation['reschedule_start_date'];
                $reservation['effective_end_date'] = $reservation['reschedule_end_date'];
            } else {
                // For all other cases, use original dates
                $reservation['effective_start_date'] = $reservation['reservation_start_date'];
                $reservation['effective_end_date'] = $reservation['reservation_end_date'];
            }
            
            // Keep original reschedule dates for reference (like fetchRequestById does)
            // Don't null them out - let the frontend decide what to display
            
            // Remove helper field
            unset($reservation['has_active_reschedule']);
        }

        return json_encode(['status' => 'success', 'data' => $reservations]);

    } catch (PDOException $e) {
        return json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}


    public function insertNotificationTouser($notification_message, $notification_user_id, $reservation_id = null) {
        try {
            $sql = "INSERT INTO tbl_notification_reservation (
                        notification_message, 
                        notification_reservation_reservation_id, 
                        notification_user_id, 
                        notification_created_at, 
                        is_read
                    ) VALUES (
                        :message, 
                        :reservation_id, 
                        :user_id, 
                        NOW(), 
                        0
                    )";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':message', $notification_message, PDO::PARAM_STR);
            $stmt->bindParam(':reservation_id', $reservation_id, PDO::PARAM_INT);
            $stmt->bindParam(':user_id', $notification_user_id, PDO::PARAM_INT);
            if ($stmt->execute()) {
                return json_encode([
                    'status' => 'success',
                    'message' => 'Notification inserted successfully',
                    'notification_id' => $this->conn->lastInsertId()
                ]);
            } else {
                return json_encode([
                    'status' => 'error',
                    'message' => 'Failed to insert notification'
                ]);
            }
        } catch (PDOException $e) {
            return json_encode([
                'status' => 'error',
                'message' => 'Database error: ' . $e->getMessage()
            ]);
        }
    }
    public function fetchStatusAvailability() {
        $sql = "SELECT `status_availability_id`, `status_availability_name` FROM `tbl_status_availability` WHERE 1";
        return $this->executeQuery($sql);
    }

    public function saveModelData($json, $userId = null) {
        // Handle both string and array inputs
        if (is_array($json)) {
            $data = $json;
        } else {
            $data = json_decode($json, true);
        }
        error_log(print_r($data, true));
        error_log("saveModelData userId=" . var_export($userId, true)); 

        try {
            // Check if the model name already exists globally (unique across all models)
            $sql = "SELECT COUNT(*) FROM tbl_vehicle_model WHERE vehicle_model_name = :name";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':name', $data['name']);
            $stmt->execute();
            if ($stmt->fetchColumn() > 0) {
                return json_encode(['status' => 'error', 'message' => 'This vehicle model name already exists.']);
            }

            // Prepare bind variables
            $name = $data['name'];
            $category_id = $data['category_id'];
            $make_id = $data['make_id'];

            // Insert if not existing
            $sql = "INSERT INTO tbl_vehicle_model (vehicle_model_name, vehicle_category_id, vehicle_model_vehicle_make_id) 
                    VALUES (:name, :category_id, :make_id)";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':name', $name, PDO::PARAM_STR);
            $stmt->bindParam(':category_id', $category_id, PDO::PARAM_INT);
            $stmt->bindParam(':make_id', $make_id, PDO::PARAM_INT);
            $stmt->execute();

            // Non-blocking audit log insert
            try {
                $auditSql = "INSERT INTO audit_log (description, action, created_at, created_by) VALUES (:description, :action, NOW(), :created_by)";
                $audit = $this->conn->prepare($auditSql);
                $desc = "Created Vehicle Model: '" . ($name ?? '') . "'";
                $action = 'CREATE';
                $audit->bindParam(':description', $desc, PDO::PARAM_STR);
                $audit->bindParam(':action', $action, PDO::PARAM_STR);
                if ($userId !== null) {
                    $audit->bindValue(':created_by', $userId, PDO::PARAM_INT);
                } else {
                    $audit->bindValue(':created_by', null, PDO::PARAM_NULL);
                }
                if (!$audit->execute()) {
                    error_log("Audit log insert failed (saveModelData): " . print_r($audit->errorInfo(), true));
                } else {
                    try {
                        $latestAuditStmt = $this->conn->query("SELECT id, description, action, created_at, created_by FROM audit_log ORDER BY id DESC LIMIT 1");
                        $latest = $latestAuditStmt ? $latestAuditStmt->fetch(PDO::FETCH_ASSOC) : null;
                        error_log("audit_log latest (saveModelData): " . json_encode($latest));
                    } catch (Throwable $te) {
                        error_log("Failed to read back latest audit_log (saveModelData): " . $te->getMessage());
                    }
                }
            } catch (Throwable $e2) {
                error_log("Audit logging error (saveModelData): " . $e2->getMessage());
            }

            return json_encode(['status' => 'success', 'message' => 'Model added successfully.']);
        } catch (PDOException $e) {
            return json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }

    public function saveCategoryData($json, $userId = null) {
        // Handle both string and array inputs
        if (is_array($json)) {
            $data = $json;
        } else {
            $data = json_decode($json, true);
        }
        error_log("saveCategoryData userId=" . var_export($userId, true));
    
        // Check if category name is set
        if (!isset($data['vehicle_category_name'])) {
            return json_encode(['status' => 'error', 'message' => 'Category name is required.']);
        }
    
        // Inline existence check
        $sql = "SELECT COUNT(*) FROM tbl_vehicle_category WHERE vehicle_category_name = :name";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':name', $data['vehicle_category_name']);
        $stmt->execute();
        if ($stmt->fetchColumn() > 0) {
            return json_encode(['status' => 'error', 'message' => 'Category already exists.']);
        }
    
        try {
            $sql = "INSERT INTO tbl_vehicle_category (vehicle_category_name) VALUES (:name)";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':name', $data['vehicle_category_name']);
            $stmt->execute();

            // Non-blocking audit log insert
            try {
                $auditSql = "INSERT INTO audit_log (description, action, created_at, created_by) VALUES (:description, :action, NOW(), :created_by)";
                $audit = $this->conn->prepare($auditSql);
                $desc = "Created Vehicle Category: '" . ($data['vehicle_category_name'] ?? '') . "'";
                $action = 'CREATE';
                $audit->bindParam(':description', $desc, PDO::PARAM_STR);
                $audit->bindParam(':action', $action, PDO::PARAM_STR);
                if ($userId !== null) {
                    $audit->bindValue(':created_by', $userId, PDO::PARAM_INT);
                } else {
                    $audit->bindValue(':created_by', null, PDO::PARAM_NULL);
                }
                if (!$audit->execute()) {
                    error_log("Audit log insert failed (saveCategoryData): " . print_r($audit->errorInfo(), true));
                } else {
                    try {
                        $latestAuditStmt = $this->conn->query("SELECT id, description, action, created_at, created_by FROM audit_log ORDER BY id DESC LIMIT 1");
                        $latest = $latestAuditStmt ? $latestAuditStmt->fetch(PDO::FETCH_ASSOC) : null;
                        error_log("audit_log latest (saveCategoryData): " . json_encode($latest));
                    } catch (Throwable $te) {
                        error_log("Failed to read back latest audit_log (saveCategoryData): " . $te->getMessage());
                    }
                }
            } catch (Throwable $e2) {
                error_log("Audit logging error (saveCategoryData): " . $e2->getMessage());
            }

            return json_encode(['status' => 'success', 'message' => 'Category added successfully.']);
        } catch(PDOException $e) {
            return json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }

    public function saveMakeData($json, $userId = null) {
        // Check if $json is already an array
        if (is_array($json)) {
            $data = $json;
        } else {
            $data = json_decode($json, true);
        }
        // Log userId for auditing
        error_log("saveMakeData userId=" . var_export($userId, true));
        
        // Inline existence check
        $sql = "SELECT COUNT(*) FROM tbl_vehicle_make WHERE vehicle_make_name = :name";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':name', $data['vehicle_make_name']);
        $stmt->execute();
        if ($stmt->fetchColumn() > 0) {
            return json_encode(['status' => 'error', 'message' => 'Make already exists.']);
        }

        try {
            $sql = "INSERT INTO tbl_vehicle_make (vehicle_make_name) VALUES (:name)";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':name', $data['vehicle_make_name']);
            $stmt->execute();

            // Insert audit log (non-blocking)
            try {
                $auditSql = "INSERT INTO audit_log (description, action, created_at, created_by) VALUES (:description, :action, NOW(), :created_by)";
                $audit = $this->conn->prepare($auditSql);
                $desc = "Created Vehicle Make: " . ($data['vehicle_make_name'] ?? '');
                $action = 'CREATE';
                $audit->bindParam(':description', $desc, PDO::PARAM_STR);
                $audit->bindParam(':action', $action, PDO::PARAM_STR);
                if ($userId !== null) {
                    $audit->bindParam(':created_by', $userId, PDO::PARAM_INT);
                } else {
                    $audit->bindValue(':created_by', null, PDO::PARAM_NULL);
                }
                $audit->execute();
                // Log the latest audit_log entry for verification
                try {
                    $sel = $this->conn->prepare("SELECT id, description, action, created_at, created_by FROM audit_log WHERE 1 ORDER BY id DESC LIMIT 1");
                    $sel->execute();
                    $latest = $sel->fetch(PDO::FETCH_ASSOC);
                    error_log("audit_log latest (saveMakeData): " . json_encode($latest));
                } catch (PDOException $ex) {
                    error_log("Audit log select failed (saveMakeData): " . $ex->getMessage());
                }
            } catch (PDOException $e) {
                error_log("Audit log insert failed (saveMakeData): " . $e->getMessage());
            }

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
    public function saveEquipmentCategory($json, $userId = null) {
        // Handle both string and array inputs
        $data = is_array($json) ? $json : json_decode($json, true);
        error_log("saveEquipmentCategory userId=" . var_export($userId, true));
        
        // Check if category name is set
        if (!isset($data['equipments_category_name'])) {
            return json_encode(['status' => 'error', 'message' => 'Category name is required.']);
        }
    
        if ($this->equipmentCategoryExists($data['equipments_category_name'])) {
            return json_encode(['status' => 'error', 'message' => 'Equipment category already exists.']);
        }
    
        try {
            $sql = "INSERT INTO tbl_equipment_category (equipments_category_name) 
                    VALUES (:name)";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':name', $data['equipments_category_name']);
            $stmt->execute();

            // Non-blocking audit log insert
            try {
                $auditSql = "INSERT INTO audit_log (description, action, created_at, created_by) VALUES (:description, :action, NOW(), :created_by)";
                $audit = $this->conn->prepare($auditSql);
                $desc = "Created Equipment Category: '" . ($data['equipments_category_name'] ?? '') . "'";
                $action = 'CREATE';
                $audit->bindParam(':description', $desc, PDO::PARAM_STR);
                $audit->bindParam(':action', $action, PDO::PARAM_STR);
                if ($userId !== null) {
                    $audit->bindValue(':created_by', $userId, PDO::PARAM_INT);
                } else {
                    $audit->bindValue(':created_by', null, PDO::PARAM_NULL);
                }
                if (!$audit->execute()) {
                    error_log("Audit log insert failed (saveEquipmentCategory): " . print_r($audit->errorInfo(), true));
                } else {
                    try {
                        $latestAuditStmt = $this->conn->query("SELECT id, description, action, created_at, created_by FROM audit_log ORDER BY id DESC LIMIT 1");
                        $latest = $latestAuditStmt ? $latestAuditStmt->fetch(PDO::FETCH_ASSOC) : null;
                        error_log("audit_log latest (saveEquipmentCategory): " . json_encode($latest));
                    } catch (Throwable $te) {
                        error_log("Failed to read back latest audit_log (saveEquipmentCategory): " . $te->getMessage());
                    }
                }
            } catch (Throwable $e2) {
                error_log("Audit logging error (saveEquipmentCategory): " . $e2->getMessage());
            }

            return json_encode(['status' => 'success', 'message' => 'Equipment category added successfully.']);
        } catch(PDOException $e) {
            return json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }

    public function saveDepartmentData($json, $userId = null) {
        // Check if $json is already an array
        if (is_array($json)) {
            $data = $json;
        } else {
            $data = json_decode($json, true);
        }
    
        // Check if the department name exists in the input
        if (!isset($data['departments_name'])) {
            return json_encode(['status' => 'error', 'message' => 'Department name is required.']);
        }
    
        // Check if the department already exists (inline, not via departmentExists)
        $sql = "SELECT COUNT(*) FROM tbl_departments WHERE departments_name = :name";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':name', $data['departments_name']);
        $stmt->execute();
        if ($stmt->fetchColumn() > 0) {
            return json_encode(['status' => 'error', 'message' => 'Department already exists.']);
        }
    
        try {
            // Updated INSERT to include department_type
            $sql = "INSERT INTO tbl_departments (departments_name, department_type) VALUES (:name, :type)";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':name', $data['departments_name']);
            $stmt->bindParam(':type', $data['department_type']);
            $stmt->execute();

            // Insert audit log (non-blocking)
            try {
                $auditSql = "INSERT INTO audit_log (description, action, created_at, created_by) VALUES (:description, :action, NOW(), :created_by)";
                $audit = $this->conn->prepare($auditSql);
                $desc = "Created Department: " . ($data['departments_name'] ?? '') . " (Type: " . ($data['department_type'] ?? '') . ")";
                $action = 'CREATE';
                $audit->bindParam(':description', $desc, PDO::PARAM_STR);
                $audit->bindParam(':action', $action, PDO::PARAM_STR);
                if ($userId !== null) {
                    $audit->bindParam(':created_by', $userId, PDO::PARAM_INT);
                } else {
                    $audit->bindValue(':created_by', null, PDO::PARAM_NULL);
                }
                $audit->execute();
            } catch (PDOException $e) {
                error_log("Audit log insert failed (saveDepartmentData): " . $e->getMessage());
            }

            return json_encode(['status' => 'success', 'message' => 'Department added successfully.']);
        } catch (PDOException $e) {
            return json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }

    public function fetchMake() {
        $sql = "SELECT vehicle_make_id, vehicle_make_name FROM tbl_vehicle_make ORDER BY vehicle_make_id DESC";
        return $this->executeQuery($sql);
    }

    public function updateVehicleMake($id, $name, $userId = null) {
        // Log userId for auditing
        error_log("updateVehicleMake userId=" . var_export($userId, true));
        try {
            // First, get the current vehicle make name to check if it's the same
            $currentSql = "SELECT vehicle_make_name FROM tbl_vehicle_make WHERE vehicle_make_id = :id";
            $currentStmt = $this->conn->prepare($currentSql);
            $currentStmt->bindParam(':id', $id, PDO::PARAM_INT);
            $currentStmt->execute();
            $currentMake = $currentStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$currentMake) {
                return json_encode(['status' => 'error', 'message' => 'Vehicle make not found']);
            }
            
            $currentName = $currentMake['vehicle_make_name'];
            
            // If the new name is the same as the current name, allow the update
            if ($currentName === $name) {
                $sql = "UPDATE tbl_vehicle_make SET vehicle_make_name = :name WHERE vehicle_make_id = :id";
                $stmt = $this->conn->prepare($sql);
                $stmt->bindParam(':name', $name);
                $stmt->bindParam(':id', $id, PDO::PARAM_INT);
                $exec = $stmt->execute();
                if ($exec) {
                    // Audit log (non-blocking)
                    try {
                        $auditSql = "INSERT INTO audit_log (description, action, created_at, created_by) VALUES (:description, :action, NOW(), :created_by)";
                        $audit = $this->conn->prepare($auditSql);
                        $desc = "Updated Vehicle Make: '" . ($currentName ?? '') . "' -> '" . ($name ?? '') . "'";
                        $action = 'UPDATE';
                        $audit->bindParam(':description', $desc, PDO::PARAM_STR);
                        $audit->bindParam(':action', $action, PDO::PARAM_STR);
                        if ($userId !== null) {
                            $audit->bindParam(':created_by', $userId, PDO::PARAM_INT);
                        } else {
                            $audit->bindValue(':created_by', null, PDO::PARAM_NULL);
                        }
                        $audit->execute();
                        // Log the latest audit_log entry for verification
                        try {
                            $sel = $this->conn->prepare("SELECT id, description, action, created_at, created_by FROM audit_log WHERE 1 ORDER BY id DESC LIMIT 1");
                            $sel->execute();
                            $latest = $sel->fetch(PDO::FETCH_ASSOC);
                            error_log("audit_log latest (updateVehicleMake same-name): " . json_encode($latest));
                        } catch (PDOException $ex) {
                            error_log("Audit log select failed (updateVehicleMake same-name): " . $ex->getMessage());
                        }
                    } catch (PDOException $e) {
                        error_log("Audit log insert failed (updateVehicleMake same-name): " . $e->getMessage());
                    }
                    return json_encode(['status' => 'success', 'message' => 'Vehicle make updated successfully']);
                } else {
                    return json_encode(['status' => 'error', 'message' => 'Could not update vehicle make']);
                }
            }
            
            // If the name is different, check if the new name already exists
            $checkSql = "SELECT COUNT(*) FROM tbl_vehicle_make WHERE vehicle_make_name = :name AND vehicle_make_id != :id";
            $checkStmt = $this->conn->prepare($checkSql);
            $checkStmt->bindParam(':name', $name);
            $checkStmt->bindParam(':id', $id, PDO::PARAM_INT);
            $checkStmt->execute();
            
            if ($checkStmt->fetchColumn() > 0) {
                return json_encode(['status' => 'error', 'message' => 'Vehicle make name already exists']);
            }
            
            // If validation passes, proceed with the update
            $sql = "UPDATE tbl_vehicle_make SET vehicle_make_name = :name WHERE vehicle_make_id = :id";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':name', $name);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            
            if ($stmt->execute()) {
                // Audit log (non-blocking)
                try {
                    $auditSql = "INSERT INTO audit_log (description, action, created_at, created_by) VALUES (:description, :action, NOW(), :created_by)";
                    $audit = $this->conn->prepare($auditSql);
                    $desc = "Updated Vehicle Make: '" . ($currentName ?? '') . "' -> '" . ($name ?? '') . "'";
                    $action = 'UPDATE';
                    $audit->bindParam(':description', $desc, PDO::PARAM_STR);
                    $audit->bindParam(':action', $action, PDO::PARAM_STR);
                    if ($userId !== null) {
                        $audit->bindParam(':created_by', $userId, PDO::PARAM_INT);
                    } else {
                        $audit->bindValue(':created_by', null, PDO::PARAM_NULL);
                    }
                    $audit->execute();
                    // Log the latest audit_log entry for verification
                    try {
                        $sel = $this->conn->prepare("SELECT id, description, action, created_at, created_by FROM audit_log WHERE 1 ORDER BY id DESC LIMIT 1");
                        $sel->execute();
                        $latest = $sel->fetch(PDO::FETCH_ASSOC);
                        error_log("audit_log latest (updateVehicleMake): " . json_encode($latest));
                    } catch (PDOException $ex) {
                        error_log("Audit log select failed (updateVehicleMake): " . $ex->getMessage());
                    }
                } catch (PDOException $e) {
                    error_log("Audit log insert failed (updateVehicleMake): " . $e->getMessage());
                }
                return json_encode(['status' => 'success', 'message' => 'Vehicle make updated successfully']);
            } else {
                return json_encode(['status' => 'error', 'message' => 'Could not update vehicle make']);
            }
                                     
        } catch (PDOException $e) {
            return json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
        }
    }

    public function fetchVehicleCategories() {
        $sql = "SELECT vehicle_category_id, vehicle_category_name FROM tbl_vehicle_category ORDER BY vehicle_category_name";
        return $this->executeQuery($sql);
    }


    public function updateVehicleCategory($id, $name, $userId = null) {
        try {
            // First, get the current vehicle category name to check if it's the same
            $currentSql = "SELECT vehicle_category_name FROM tbl_vehicle_category WHERE vehicle_category_id = :id";
            $currentStmt = $this->conn->prepare($currentSql);
            $currentStmt->bindParam(':id', $id, PDO::PARAM_INT);
            $currentStmt->execute();
            $currentCategory = $currentStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$currentCategory) {
                return json_encode(['status' => 'error', 'message' => 'Vehicle category not found']);
            }
            
            $currentName = $currentCategory['vehicle_category_name'];
            
            // If the new name is the same as the current name, allow the update
            if ($currentName === $name) {
                $sql = "UPDATE tbl_vehicle_category SET vehicle_category_name = :name WHERE vehicle_category_id = :id";
                $stmt = $this->conn->prepare($sql);
                $stmt->bindParam(':name', $name);
                $stmt->bindParam(':id', $id, PDO::PARAM_INT);
                
                if ($stmt->execute()) {
                    // Audit log (non-blocking)
                    try {
                        $auditSql = "INSERT INTO audit_log (description, action, created_at, created_by) VALUES (:description, :action, NOW(), :created_by)";
                        $audit = $this->conn->prepare($auditSql);
                        $desc = "Updated Vehicle Category: '" . ($currentName ?? '') . "' -> '" . ($name ?? '') . "'";
                        $action = 'UPDATE';
                        $audit->bindParam(':description', $desc, PDO::PARAM_STR);
                        $audit->bindParam(':action', $action, PDO::PARAM_STR);
                        if ($userId !== null) {
                            $audit->bindValue(':created_by', $userId, PDO::PARAM_INT);
                        } else {
                            $audit->bindValue(':created_by', null, PDO::PARAM_NULL);
                        }
                        $audit->execute();
                        try {
                            $sel = $this->conn->prepare("SELECT id, description, action, created_at, created_by FROM audit_log WHERE 1 ORDER BY id DESC LIMIT 1");
                            $sel->execute();
                            $latest = $sel->fetch(PDO::FETCH_ASSOC);
                            error_log("audit_log latest (updateVehicleCategory same-name): " . json_encode($latest));
                        } catch (PDOException $ex) {
                            error_log("Audit log select failed (updateVehicleCategory same-name): " . $ex->getMessage());
                        }
                    } catch (PDOException $e) {
                        error_log("Audit log insert failed (updateVehicleCategory same-name): " . $e->getMessage());
                    }
                    // Fetch updated list
                    $categories = $this->fetchVehicleCategories();
                    $categoriesData = json_decode($categories, true)['data'] ?? [];
                    return json_encode([
                        'status' => 'success',
                        'message' => 'Vehicle category updated successfully',
                        'categories' => $categoriesData
                    ]);
                } else {
                    return json_encode(['status' => 'error', 'message' => 'Could not update vehicle category']);
                }
            }
            
            // If the name is different, check if the new name already exists
            $checkSql = "SELECT COUNT(*) FROM tbl_vehicle_category WHERE vehicle_category_name = :name AND vehicle_category_id != :id";
            $checkStmt = $this->conn->prepare($checkSql);
            $checkStmt->bindParam(':name', $name);
            $checkStmt->bindParam(':id', $id, PDO::PARAM_INT);
            $checkStmt->execute();
            
            if ($checkStmt->fetchColumn() > 0) {
                return json_encode(['status' => 'error', 'message' => 'Vehicle category name already exists']);
            }
            
            // If validation passes, proceed with the update
            $sql = "UPDATE tbl_vehicle_category SET vehicle_category_name = :name WHERE vehicle_category_id = :id";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':name', $name);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            
            if ($stmt->execute()) {
                // Audit log (non-blocking)
                try {
                    $auditSql = "INSERT INTO audit_log (description, action, created_at, created_by) VALUES (:description, :action, NOW(), :created_by)";
                    $audit = $this->conn->prepare($auditSql);
                    $desc = "Updated Vehicle Category: '" . ($currentName ?? '') . "' -> '" . ($name ?? '') . "'";
                    $action = 'UPDATE';
                    $audit->bindParam(':description', $desc, PDO::PARAM_STR);
                    $audit->bindParam(':action', $action, PDO::PARAM_STR);
                    if ($userId !== null) {
                        $audit->bindValue(':created_by', $userId, PDO::PARAM_INT);
                    } else {
                        $audit->bindValue(':created_by', null, PDO::PARAM_NULL);
                    }
                    $audit->execute();
                    try {
                        $sel = $this->conn->prepare("SELECT id, description, action, created_at, created_by FROM audit_log WHERE 1 ORDER BY id DESC LIMIT 1");
                        $sel->execute();
                        $latest = $sel->fetch(PDO::FETCH_ASSOC);
                        error_log("audit_log latest (updateVehicleCategory): " . json_encode($latest));
                    } catch (PDOException $ex) {
                        error_log("Audit log select failed (updateVehicleCategory): " . $ex->getMessage());
                    }
                } catch (PDOException $e) {
                    error_log("Audit log insert failed (updateVehicleCategory): " . $e->getMessage());
                }
                // Fetch updated list
                $categories = $this->fetchVehicleCategories();
                $categoriesData = json_decode($categories, true)['data'] ?? [];
                return json_encode([
                    'status' => 'success',
                    'message' => 'Vehicle category updated successfully',
                    'categories' => $categoriesData
                ]);
            } else {
                return json_encode(['status' => 'error', 'message' => 'Could not update vehicle category']);
            }
                                     
        } catch (PDOException $e) {
            return json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
        }
    }

    public function fetchModels() {
        $sql = "
            SELECT 
                vm.vehicle_model_id,
                vm.vehicle_model_name,
                vm.vehicle_model_created_at,
                vm.vehicle_model_updated_at,
                vm.vehicle_model_vehicle_make_id,
                vm.vehicle_category_id,
                make.vehicle_make_name,
                category.vehicle_category_name
            FROM 
                tbl_vehicle_model vm
            LEFT JOIN 
                tbl_vehicle_make make ON vm.vehicle_model_vehicle_make_id = make.vehicle_make_id
            LEFT JOIN 
                tbl_vehicle_category category ON vm.vehicle_category_id = category.vehicle_category_id
            ORDER BY 
                vm.vehicle_model_id DESC
        ";
    
        return $this->executeQuery($sql);
    }

    public function updateVehicleModel($modelData, $userId = null) {
        // Validate input data
        if (!isset($modelData['id']) || !isset($modelData['name']) || 
            !isset($modelData['category_id']) || !isset($modelData['make_id'])) {
            error_log("Missing required fields in modelData: " . print_r($modelData, true));
            return json_encode(['status' => 'error', 'message' => 'Missing required fields']);
        }

        error_log("Starting vehicle model update with data: " . print_r($modelData, true));
        error_log("updateVehicleModel userId=" . var_export($userId, true));
        
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
                    // Non-blocking audit log insert
                    try {
                        $auditSql = "INSERT INTO audit_log (description, action, created_at, created_by) VALUES (:description, :action, NOW(), :created_by)";
                        $audit = $this->conn->prepare($auditSql);
                        $desc = "Updated Vehicle Model: '" . ($currentName ?? '') . "' -> '" . ($modelData['name'] ?? '') . "'";
                        $action = 'UPDATE';
                        $audit->bindParam(':description', $desc, PDO::PARAM_STR);
                        $audit->bindParam(':action', $action, PDO::PARAM_STR);
                        if ($userId !== null) {
                            $audit->bindValue(':created_by', $userId, PDO::PARAM_INT);
                        } else {
                            $audit->bindValue(':created_by', null, PDO::PARAM_NULL);
                        }
                        if (!$audit->execute()) {
                            error_log("Audit log insert failed (updateVehicleModel): " . print_r($audit->errorInfo(), true));
                        } else {
                            try {
                                $latestAuditStmt = $this->conn->query("SELECT id, description, action, created_at, created_by FROM audit_log ORDER BY id DESC LIMIT 1");
                                $latest = $latestAuditStmt ? $latestAuditStmt->fetch(PDO::FETCH_ASSOC) : null;
                                error_log("audit_log latest (updateVehicleModel): " . json_encode($latest));
                            } catch (Throwable $te) {
                                error_log("Failed to read back latest audit_log (updateVehicleModel): " . $te->getMessage());
                            }
                        }
                    } catch (Throwable $e2) {
                        error_log("Audit logging error (updateVehicleModel): " . $e2->getMessage());
                    }
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

    public function fetchEquipmentsCategory() {
        $sql = "SELECT equipments_category_id, equipments_category_name FROM tbl_equipment_category ORDER BY equipments_category_id DESC";
        return $this->executeQuery($sql);
    }

    public function updateEquipmentCategory($categoryData, $userId = null) {
        try {
            // First, get the current equipment category name to check if it's the same
            $currentSql = "SELECT equipments_category_name FROM tbl_equipment_category WHERE equipments_category_id = :id";
            $currentStmt = $this->conn->prepare($currentSql);
            $currentStmt->bindParam(':id', $categoryData['categoryId'], PDO::PARAM_INT);
            $currentStmt->execute();
            $currentCategory = $currentStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$currentCategory) {
                return json_encode(['status' => 'error', 'message' => 'Equipment category not found']);
            }
            
            $currentName = $currentCategory['equipments_category_name'];
            
            // If the new name is the same as the current name, allow the update
            if ($currentName === $categoryData['name']) {
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
                    // Audit log (non-blocking)
                    try {
                        $auditSql = "INSERT INTO audit_log (description, action, created_at, created_by) VALUES (:description, :action, NOW(), :created_by)";
                        $audit = $this->conn->prepare($auditSql);
                        $desc = "Updated Equipment Category: '" . ($currentName ?? '') . "' -> '" . ($categoryData['name'] ?? '') . "'";
                        $action = 'UPDATE';
                        $audit->bindParam(':description', $desc, PDO::PARAM_STR);
                        $audit->bindParam(':action', $action, PDO::PARAM_STR);
                        if ($userId !== null) {
                            $audit->bindValue(':created_by', $userId, PDO::PARAM_INT);
                        } else {
                            $audit->bindValue(':created_by', null, PDO::PARAM_NULL);
                        }
                        $audit->execute();
                        try {
                            $sel = $this->conn->prepare("SELECT id, description, action, created_at, created_by FROM audit_log WHERE 1 ORDER BY id DESC LIMIT 1");
                            $sel->execute();
                            $latest = $sel->fetch(PDO::FETCH_ASSOC);
                            error_log("audit_log latest (updateEquipmentCategory same-name): " . json_encode($latest));
                        } catch (PDOException $ex) {
                            error_log("Audit log select failed (updateEquipmentCategory same-name): " . $ex->getMessage());
                        }
                    } catch (PDOException $e) {
                        error_log("Audit log insert failed (updateEquipmentCategory same-name): " . $e->getMessage());
                    }
                    return json_encode(['status' => 'success', 'message' => 'Category updated successfully.']);
                } else {
                    return json_encode(['status' => 'error', 'message' => 'Failed to update category.']);
                }
            }
            
            // If the name is different, check if the new name already exists
            $checkSql = "SELECT COUNT(*) FROM tbl_equipment_category WHERE equipments_category_name = :name AND equipments_category_id != :id";
            $checkStmt = $this->conn->prepare($checkSql);
            $checkStmt->bindParam(':name', $categoryData['name']);
            $checkStmt->bindParam(':id', $categoryData['categoryId'], PDO::PARAM_INT);
            $checkStmt->execute();
            
            if ($checkStmt->fetchColumn() > 0) {
                return json_encode(['status' => 'error', 'message' => 'Equipment category name already exists']);
            }
            
            // If validation passes, proceed with the update
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
                // Audit log (non-blocking)
                try {
                    $auditSql = "INSERT INTO audit_log (description, action, created_at, created_by) VALUES (:description, :action, NOW(), :created_by)";
                    $audit = $this->conn->prepare($auditSql);
                    $desc = "Updated Equipment Category: '" . ($currentName ?? '') . "' -> '" . ($categoryData['name'] ?? '') . "'";
                    $action = 'UPDATE';
                    $audit->bindParam(':description', $desc, PDO::PARAM_STR);
                    $audit->bindParam(':action', $action, PDO::PARAM_STR);
                    if ($userId !== null) {
                        $audit->bindValue(':created_by', $userId, PDO::PARAM_INT);
                    } else {
                        $audit->bindValue(':created_by', null, PDO::PARAM_NULL);
                    }
                    $audit->execute();
                    try {
                        $sel = $this->conn->prepare("SELECT id, description, action, created_at, created_by FROM audit_log WHERE 1 ORDER BY id DESC LIMIT 1");
                        $sel->execute();
                        $latest = $sel->fetch(PDO::FETCH_ASSOC);
                        error_log("audit_log latest (updateEquipmentCategory): " . json_encode($latest));
                    } catch (PDOException $ex) {
                        error_log("Audit log select failed (updateEquipmentCategory): " . $ex->getMessage());
                    }
                } catch (PDOException $e) {
                    error_log("Audit log insert failed (updateEquipmentCategory): " . $e->getMessage());
                }
                return json_encode(['status' => 'success', 'message' => 'Category updated successfully.']);
            } else {
                return json_encode(['status' => 'error', 'message' => 'Failed to update category.']);
            }
                                     
        } catch (PDOException $e) {
            error_log("PDO Exception: " . $e->getMessage());
            return json_encode(['status' => 'error', 'message' => 'Error: ' . $e->getMessage()]);
        }
    }

    public function updateDepartment($id, $name, $type, $userId = null) {
        try {
            // First, get the current department data
            $currentSql = "SELECT departments_name, department_type 
                          FROM tbl_departments 
                          WHERE departments_id = :id";
            $currentStmt = $this->conn->prepare($currentSql);
            $currentStmt->bindParam(':id', $id, PDO::PARAM_INT);
            $currentStmt->execute();
            $currentDepartment = $currentStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$currentDepartment) {
                return json_encode(['status' => 'error', 'message' => 'Department not found']);
            }
            
            $currentName = $currentDepartment['departments_name'];
            $currentType = $currentDepartment['department_type'];
            
            // If both name and type are the same, no update needed
            if ($currentName === $name && $currentType === $type) {
                return json_encode(['status' => 'success', 'message' => 'No changes detected.']);
            }
            
            // Check if another department with the same name exists
            $checkSql = "SELECT COUNT(*) FROM tbl_departments 
                        WHERE departments_name = :name 
                        AND departments_id != :id";
            $checkStmt = $this->conn->prepare($checkSql);
            $checkStmt->bindParam(':name', $name);
            $checkStmt->bindParam(':id', $id, PDO::PARAM_INT);
            $checkStmt->execute();
            
            if ($checkStmt->fetchColumn() > 0) {
                return json_encode(['status' => 'error', 'message' => 'Another department with this name already exists.']);
            }
            
            // Update the department
            $sql = "UPDATE tbl_departments 
                   SET departments_name = :name, 
                       department_type = :type 
                   WHERE departments_id = :id";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':name', $name);
            $stmt->bindParam(':type', $type);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            
            if ($stmt->execute()) {
                // Insert audit log (non-blocking)
                try {
                    $auditSql = "INSERT INTO audit_log (description, action, created_at, created_by) VALUES (:description, :action, NOW(), :created_by)";
                    $audit = $this->conn->prepare($auditSql);
                    $desc = "Updated Department: {$currentName} ({$currentType}) -> {$name} ({$type})";
                    $action = 'UPDATE';
                    $audit->bindParam(':description', $desc, PDO::PARAM_STR);
                    $audit->bindParam(':action', $action, PDO::PARAM_STR);
                    if ($userId !== null) {
                        $audit->bindParam(':created_by', $userId, PDO::PARAM_INT);
                    } else {
                        $audit->bindValue(':created_by', null, PDO::PARAM_NULL);
                    }
                    $audit->execute();
                } catch (PDOException $e) {
                    error_log("Audit log insert failed (updateDepartment): " . $e->getMessage());
                }
                return json_encode([
                    'status' => 'success', 
                    'message' => 'Department updated successfully.'
                ]);
            } else {
                return json_encode([
                    'status' => 'error', 
                    'message' => 'Failed to update department.'
                ]);
            }
        } catch (PDOException $e) {
            return json_encode([
                'status' => 'error', 
                'message' => 'Database error: ' . $e->getMessage()
            ]);
        }
    }

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

    public function fetchUsersById($id) {
        if (!is_numeric($id)) {
            return json_encode(['status' => 'error', 'message' => 'Invalid ID format']);
        }
    
        $sql = "SELECT 
                    u.*,
                    t.abbreviation AS title_abbreviation,
                    d.departments_name,
                    ul.user_level_name
                FROM 
                    tbl_users u
                LEFT JOIN 
                    titles t ON u.title_id = t.id
                LEFT JOIN 
                    tbl_departments d ON u.users_department_id = d.departments_id
                LEFT JOIN 
                    tbl_user_level ul ON u.users_user_level_id = ul.user_level_id
                WHERE 
                    u.users_id = :id";
    
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
 
            v.is_active, 
            v.user_admin_id,
            sa.status_availability_name,
            v.event_type,
            v.area_type
        FROM tbl_venue v
        INNER JOIN tbl_status_availability sa ON v.status_availability_id = sa.status_availability_id
        WHERE v.ven_id = :id";
        
        return $this->executeQuery($sql, [':id' => $id]);
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
            $onHandQty = 0;
            $formattedUnits = [];
    
            // Determine logic based on equip_type from the fetched equipment
            if ($equip['equip_type'] === 'Bulk') {
                // Fetch quantity from tbl_equipment_quantity for Bulk type
                $quantitySql = "
                    SELECT quantity, on_hand_quantity
                    FROM tbl_equipment_quantity
                    WHERE equip_id = :id
                    ORDER BY last_updated DESC
                    LIMIT 1
                ";
                $quantityStmt = $this->conn->prepare($quantitySql);
                $quantityStmt->execute([':id' => $id]);
                $quantityResult = $quantityStmt->fetch(PDO::FETCH_ASSOC);
                $displayQty = $quantityResult ? (int)$quantityResult['quantity'] : 0;
                $onHandQty = $quantityResult ? (int)$quantityResult['on_hand_quantity'] : 0;
            } else {
                // For Serialized types, fetch from tbl_equipment_unit
                $unitSql = "
                    SELECT
                        unit_id,
                        equip_id,
                        serial_number,
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
                        'status_availability_id'=> (int)$u['status_availability_id'],
                        'unit_created_at'       => $u['unit_created_at'],
                        'user_admin_id'         => (int)$u['user_admin_id'],
                    ];
                }, $units);
    
                $displayQty = count($formattedUnits);
                $onHandQty = $displayQty; // For Serialized equipment, on_hand_quantity equals total units
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
                'on_hand_quantity' => $onHandQty,
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

    public function getTotals() {
        try {
            // Query for approved reservations (status_id = 3)


            // Query for vehicles
            $vehicleQuery = "SELECT COUNT(*) AS total FROM tbl_vehicle WHERE is_active = 1";

            // Query for venues
            $venueQuery = "SELECT COUNT(*) AS total FROM tbl_venue WHERE is_active = 1";

            // Query for equipment
            $equipmentQuery = "SELECT COUNT(*) AS total FROM tbl_equipments WHERE is_active = 1";

            // Query for users (only from tbl_users)
            $userQuery = "SELECT COUNT(*) AS total FROM tbl_users WHERE is_active = 1";

            // Execute all queries
            $queries = [
                'vehicles' => $vehicleQuery,
                'venues' => $venueQuery,
                'equipments' => $equipmentQuery,
                'users' => $userQuery
            ];

            $totals = [];
            foreach ($queries as $key => $query) {
                $stmt = $this->conn->query($query);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                $totals[$key] = (int)$result['total'];
            }

            return json_encode([
                'status' => 'success',
                'data' => $totals
            ]);

        } catch (PDOException $e) {
            return json_encode([
                'status' => 'error',
                'message' => $e->getMessage()
            ]);
        }
    }

    
    public function fetchVenues() {
        $sql = "SELECT 
                    ven_id, 
                    ven_name, 
                    ven_occupancy, 
                    ven_created_at, 
                    ven_updated_at, 
                    v.status_availability_id,
                    sa.status_availability_name,
                    ven_pic
                FROM 
                    tbl_venue v
                INNER JOIN 
                    tbl_status_availability sa ON v.status_availability_id = sa.status_availability_id 
                WHERE 
                    v.status_availability_id != 7 AND v.status_availability_id != 8 
                ORDER BY 
                    ven_name"; 
        return $this->executeQuery($sql);
    }

    public function fetchVehicles() {
        $sql = "SELECT  
                    v.vehicle_id,
                    v.vehicle_pic,
                    v.year,
                    vm.vehicle_make_name, 
                    vc.vehicle_category_name,
                    vmd.vehicle_model_name,      
                    v.vehicle_license,
                    sa.status_availability_name
                FROM 
                    tbl_vehicle v 
                INNER JOIN 
                    tbl_vehicle_model vmd ON v.vehicle_model_id = vmd.vehicle_model_id 
                INNER JOIN 
                    tbl_vehicle_make vm ON vmd.vehicle_model_vehicle_make_id = vm.vehicle_make_id 
                INNER JOIN 
                    tbl_vehicle_category vc ON vmd.vehicle_category_id = vc.vehicle_category_id
                INNER JOIN
                    tbl_status_availability sa ON v.status_availability_id = sa.status_availability_id
                WHERE 
                    v.status_availability_id != 7 AND v.status_availability_id != 8";
                 // Added condition for availability

        return $this->executeQuery($sql);
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

public function fetchConditions() {
    $sql = "SELECT `id`, `condition_name` FROM `tbl_condition` WHERE 1";
    return $this->executeQuery($sql);
}

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

public function fetchEquipmentCategoryById($id) {
    $sql = "SELECT equipments_category_id, equipments_category_name FROM tbl_equipment_category WHERE equipments_category_id = :id";
    return $this->executeQuery($sql, [':id' => $id]);
}

public function fetchAudit() {
        $sql = "
            SELECT 
                a.id,
                a.description,
                a.action,
                a.created_at,
                a.created_by,
                CONCAT_WS(' ', u.users_fname, u.users_mname, u.users_lname) AS created_by_name
            FROM audit_log a
            LEFT JOIN tbl_users u ON a.created_by = u.users_id
            ORDER BY a.created_at DESC
        ";
        return $this->executeQuery($sql);
    }

public function fetchDeansApproval($reservationId) {
    try {
        $sql = "
            SELECT 
                da.department_approval_id,
                da.department_is_approved,
                da.department_approval_department_id,
                da.department_user_id,
                da.department_updated_at,
                da.department_request_reservation_id,
                d.departments_name,
                CONCAT_WS(' ', u.users_fname, u.users_mname, u.users_lname) as user_name
            FROM 
                tbl_department_approval da
            LEFT JOIN 
                tbl_departments d ON da.department_approval_department_id = d.departments_id
            LEFT JOIN 
                tbl_users u ON da.department_user_id = u.users_id
            WHERE 
                da.department_request_reservation_id = :reservation_id
            ORDER BY 
                da.department_updated_at DESC
        ";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':reservation_id', $reservationId, PDO::PARAM_INT);
        $stmt->execute();

        $approvals = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $approvals[] = [
                'approval_id' => $row['department_approval_id'],
                'is_approved' => $row['department_is_approved'],
                'department_id' => $row['department_approval_department_id'],
                'department_name' => $row['departments_name'],
                'user_id' => $row['department_user_id'] ?: '',
                'user_name' => $row['user_name'] ?: '',
                'updated_at' => $row['department_updated_at'],
                'reservation_id' => $row['department_request_reservation_id']
            ];
        }

        return json_encode([
            'status' => 'success',
            'data' => $approvals
        ]);

    } catch (PDOException $e) {
        return json_encode([
            'status' => 'error',
            'message' => 'Error fetching department approvals: ' . $e->getMessage()
        ]);
    }
}
public function handleRequest($reservationId, $isAccepted, $userId, $notificationMessage = '', $notification_user_id = null) {
    try {
        $this->conn->beginTransaction();

        if ($isAccepted) {
            // For user ID 99, handle acceptance
            if ($userId == 99) {
                // Update status ID 1 to active = 1
                $sqlUpdate = "
                    UPDATE tbl_reservation_status 
                    SET reservation_active = 1 
                    WHERE reservation_reservation_id = :reservation_id 
                    AND reservation_status_status_id = 8";
                
                $stmtUpdate = $this->conn->prepare($sqlUpdate);
                $stmtUpdate->bindParam(':reservation_id', $reservationId, PDO::PARAM_INT);
                $stmtUpdate->execute();
                
                // Insert 2 new statuses: status_id 6 and status_id 12
                

                $sqlInsert12 = "
                    INSERT INTO tbl_reservation_status 
                    (reservation_reservation_id, reservation_status_status_id, reservation_active, reservation_updated_at, reservation_users_id) 
                    VALUES (:reservation_id, 12, 1, NOW(), :user_id)";
                
                $stmtInsert12 = $this->conn->prepare($sqlInsert12);
                $stmtInsert12->bindParam(':reservation_id', $reservationId, PDO::PARAM_INT);
                $stmtInsert12->bindParam(':user_id', $userId, PDO::PARAM_INT);
                $stmtInsert12->execute();

                $sqlInsert6 = "
                    INSERT INTO tbl_reservation_status 
                    (reservation_reservation_id, reservation_status_status_id, reservation_active, reservation_updated_at, reservation_users_id) 
                    VALUES (:reservation_id, 6, 1, NOW(), :user_id)";
                
                $stmtInsert6 = $this->conn->prepare($sqlInsert6);
                $stmtInsert6->bindParam(':reservation_id', $reservationId, PDO::PARAM_INT);
                $stmtInsert6->bindParam(':user_id', $userId, PDO::PARAM_INT);
                $stmtInsert6->execute();
            } else {
                // For other users, handle acceptance
                // Update status ID 1 to active = 1
                $sqlUpdate = "
                    UPDATE tbl_reservation_status 
                    SET reservation_active = 1 
                    WHERE reservation_reservation_id = :reservation_id 
                    AND reservation_status_status_id = 1";
                
                $stmtUpdate = $this->conn->prepare($sqlUpdate);
                $stmtUpdate->bindParam(':reservation_id', $reservationId, PDO::PARAM_INT);
                $stmtUpdate->execute();

                // Insert new status: status_id 7
                $sqlInsert7 = "
                    INSERT INTO tbl_reservation_status 
                    (reservation_reservation_id, reservation_status_status_id, reservation_active, reservation_updated_at, reservation_users_id) 
                    VALUES (:reservation_id, 7, 1, NOW(), :user_id)";
                
                $stmtInsert7 = $this->conn->prepare($sqlInsert7);
                $stmtInsert7->bindParam(':reservation_id', $reservationId, PDO::PARAM_INT);
                $stmtInsert7->bindParam(':user_id', $userId, PDO::PARAM_INT);
                $stmtInsert7->execute();
            }
        } else {
            // DECLINE logic
            if ($userId == 99) {
                // For user ID 99, handle decline
                // Update status ID 8 to active = 1 (do NOT set to -1)
                $sqlUpdate = "
                    UPDATE tbl_reservation_status 
                    SET reservation_active = 1 
                    WHERE reservation_reservation_id = :reservation_id 
                    AND reservation_status_status_id = 8";
                
                $stmtUpdate = $this->conn->prepare($sqlUpdate);
                $stmtUpdate->bindParam(':reservation_id', $reservationId, PDO::PARAM_INT);
                $stmtUpdate->execute();

                // Insert 2 new statuses: status_id 13 and status_id 2
                $sqlInsert13 = "
                    INSERT INTO tbl_reservation_status 
                    (reservation_reservation_id, reservation_status_status_id, reservation_active, reservation_updated_at, reservation_users_id) 
                    VALUES (:reservation_id, 13, 1, NOW(), :user_id)";
                
                $stmtInsert13 = $this->conn->prepare($sqlInsert13);
                $stmtInsert13->bindParam(':reservation_id', $reservationId, PDO::PARAM_INT);
                $stmtInsert13->bindParam(':user_id', $userId, PDO::PARAM_INT);
                $stmtInsert13->execute();

                $sqlInsert2 = "
                    INSERT INTO tbl_reservation_status 
                    (reservation_reservation_id, reservation_status_status_id, reservation_active, reservation_updated_at, reservation_users_id) 
                    VALUES (:reservation_id, 2, 1, NOW(), :user_id)";
                
                $stmtInsert2 = $this->conn->prepare($sqlInsert2);
                $stmtInsert2->bindParam(':reservation_id', $reservationId, PDO::PARAM_INT);
                $stmtInsert2->bindParam(':user_id', $userId, PDO::PARAM_INT);
                $stmtInsert2->execute();
            } else {
                // For other users, handle decline
                // Insert only status_id 9 with active = 1

                $sqlUpdate = "
                UPDATE tbl_reservation_status 
                SET reservation_active = 1 
                WHERE reservation_reservation_id = :reservation_id 
                AND reservation_status_status_id = 1";
            
                $stmtUpdate = $this->conn->prepare($sqlUpdate);
                $stmtUpdate->bindParam(':reservation_id', $reservationId, PDO::PARAM_INT);
                $stmtUpdate->execute();

                $sqlInsert9 = "
                    INSERT INTO tbl_reservation_status 
                    (reservation_reservation_id, reservation_status_status_id, reservation_active, reservation_updated_at, reservation_users_id) 
                    VALUES (:reservation_id, 9, 1, NOW(), :user_id)";
                
                $stmtInsert9 = $this->conn->prepare($sqlInsert9);
                $stmtInsert9->bindParam(':reservation_id', $reservationId, PDO::PARAM_INT);
                $stmtInsert9->bindParam(':user_id', $userId, PDO::PARAM_INT);
                $stmtInsert9->execute();
            }
        }

        // Insert notification with proper notification_user_id
        if (!empty($notificationMessage)) {
            $sqlNotification = "INSERT INTO tbl_notification_reservation 
                             (notification_message, notification_reservation_reservation_id, notification_user_id, notification_created_at) 
                             VALUES (:message, :reservation_id, :notification_user_id, NOW())";
            
            $stmtNotification = $this->conn->prepare($sqlNotification);
            $message = $notificationMessage;
            $notification_user = $notification_user_id ?? $userId;
            $stmtNotification->bindParam(':message', $message, PDO::PARAM_STR);
            $stmtNotification->bindParam(':reservation_id', $reservationId, PDO::PARAM_INT);
            $stmtNotification->bindParam(':notification_user_id', $notification_user, PDO::PARAM_INT);
            $stmtNotification->execute();
        }

        $this->conn->commit();
        
        // Audit log: who approved/declined and which reservation
        try {
            // Fetch approver name
            $approverName = 'User ID ' . (string)$userId;
            try {
                $unameStmt = $this->conn->prepare("SELECT CONCAT_WS(' ', users_fname, users_mname, users_lname) AS full_name FROM tbl_users WHERE users_id = :uid");
                $unameStmt->bindParam(':uid', $userId, PDO::PARAM_INT);
                $unameStmt->execute();
                $uname = $unameStmt->fetch(PDO::FETCH_ASSOC);
                if ($uname && !empty(trim((string)$uname['full_name']))) {
                    $approverName = trim((string)$uname['full_name']);
                }
            } catch (Throwable $te) {
                error_log("Audit approver lookup failed (handleRequest): " . $te->getMessage());
            }

            // Fetch reservation title
            $resTitle = 'Reservation';
            try {
                $resStmt = $this->conn->prepare("SELECT reservation_title FROM tbl_reservation WHERE reservation_id = :rid");
                $resStmt->bindParam(':rid', $reservationId, PDO::PARAM_INT);
                $resStmt->execute();
                $res = $resStmt->fetch(PDO::FETCH_ASSOC);
                if ($res && isset($res['reservation_title']) && trim((string)$res['reservation_title']) !== '') {
                    $resTitle = (string)$res['reservation_title'];
                }
            } catch (Throwable $te) {
                error_log("Audit reservation lookup failed (handleRequest): " . $te->getMessage());
            }

            $statusText = $isAccepted ? 'approved' : 'declined';
            $desc = "Reservation '" . $resTitle . "' " . $statusText . " by " . $approverName;
            $auditSql = "INSERT INTO audit_log (description, action, created_at, created_by) VALUES (:description, :action, NOW(), :created_by)";
            $audit = $this->conn->prepare($auditSql);
            $action = $isAccepted ? 'APPROVE' : 'DECLINE';
            $audit->bindParam(':description', $desc, PDO::PARAM_STR);
            $audit->bindParam(':action', $action, PDO::PARAM_STR);
            $audit->bindValue(':created_by', $userId, PDO::PARAM_INT);
            if (!$audit->execute()) {
                error_log("Audit log insert failed (handleRequest): " . print_r($audit->errorInfo(), true));
            } else {
                try {
                    $latest = $this->conn->query("SELECT id, description, action, created_at, created_by FROM audit_log ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
                    error_log("audit_log latest (handleRequest): " . json_encode($latest));
                } catch (Throwable $te) {
                    error_log("Failed to read latest audit_log (handleRequest): " . $te->getMessage());
                }
            }
        } catch (Throwable $te) {
            error_log("Audit logging error (handleRequest): " . $te->getMessage());
        }

        // Send push notification to the requester after successful database operations
        $notificationUserId = $notification_user_id ?? $userId;
        // Compose notification content
        $status = $isAccepted ? 'approved' : 'declined';
        $title = "Reservation " . ucfirst($status);
        $body = "Your reservation has been {$status}.";
        $data = [
            'reservation_id' => $reservationId,
            'status' => $status,
            'type' => 'reservation_approval'
        ];
        $this->sendPushNotificationToUser($notificationUserId, $title, $body, $data);

        return json_encode([
            'status' => 'success', 
            'message' => 'Request ' . ($isAccepted ? 'approved' : 'declined') . ' successfully',
            'reservation_id' => $reservationId
        ]);

    } catch (PDOException $e) {
        $this->conn->rollBack();
        return json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}


public function archiveUser($userType, $userId) {
    try {
        // Validate inputs
        if (empty($userType) || empty($userId)) {
            return json_encode(array('status' => 'error', 'message' => 'User type and ID are required.'));
        }

        // Convert single ID to array for consistent handling
        if (!is_array($userId)) {
            $userId = [$userId];
        }

        // Create placeholders for IN clause
        $placeholders = implode(',', array_fill(0, count($userId), '?'));
        
        switch ($userType) {
            case 'user':
                $query = "UPDATE tbl_users SET is_active = 0 WHERE users_id IN ($placeholders)";
                break;
            case 'driver':
                $query = "UPDATE tbl_driver SET is_active = 0 WHERE driver_id IN ($placeholders)";
                break;
            default:
                return json_encode(array('status' => 'error', 'message' => 'Invalid user type. Only user and driver types are supported.'));
        }

        $stmt = $this->conn->prepare($query);
        
        if ($stmt->execute($userId)) {
            $count = $stmt->rowCount();
            if ($count > 0) {
                return json_encode([
                    'status' => 'success', 
                    'message' => $count . ' user(s) archived successfully.'
                ]);
            } else {
                return json_encode([
                    'status' => 'error', 
                    'message' => 'No users found with the given IDs.'
                ]);
            }
        }

        return json_encode(array('status' => 'error', 'message' => 'Error archiving user(s).'));
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

        // Convert single ID to array for consistent handling
        if (!is_array($userId)) {
            $userId = [$userId];
        }

        // Create placeholders for IN clause
        $placeholders = implode(',', array_fill(0, count($userId), '?'));
        
        switch ($userType) {
            case 'user':
                $query = "UPDATE tbl_users SET is_active = 1 WHERE users_id IN ($placeholders)";
                break;
            case 'driver':
                $query = "UPDATE tbl_driver SET is_active = 1 WHERE driver_id IN ($placeholders)";
                break;
            default:
                return json_encode(array('status' => 'error', 'message' => 'Invalid user type. Only user and driver types are supported.'));
        }

        $stmt = $this->conn->prepare($query);
        
        if ($stmt->execute($userId)) {
            $count = $stmt->rowCount();
            if ($count > 0) {
                return json_encode([
                    'status' => 'success', 
                    'message' => $count . ' user(s) unarchived successfully.'
                ]);
            } else {
                return json_encode([
                    'status' => 'error', 
                    'message' => 'No users found with the given IDs.'
                ]);
            }
        }

        return json_encode(array('status' => 'error', 'message' => 'Error unarchiving user(s).'));
    } catch (PDOException $e) {
        return json_encode(array('status' => 'error', 'message' => 'Database error: ' . $e->getMessage()));
    }
}

public function archiveResource($resourceType, $resourceId, $is_serialize = false, $userId = null) {
    try {
        // Debug: log context
        error_log("archiveResource userId=" . var_export($userId, true) . ", type=" . var_export($resourceType, true));
        if (empty($resourceType) || empty($resourceId)) {
            return json_encode(['status' => 'error', 'message' => 'Resource type and ID are required.']);
        }

        $query = "";

        // Convert single ID to array for consistent handling
        if (!is_array($resourceId)) {
            $resourceId = [$resourceId];
        }

        // Create placeholders for IN clause
        $placeholders = implode(',', array_fill(0, count($resourceId), '?'));

        switch ($resourceType) {
            case 'vehicle':
                $query = "UPDATE tbl_vehicle SET is_active = 0 WHERE vehicle_id IN ($placeholders)";
                break;

            case 'venue':
                $query = "UPDATE tbl_venue SET is_active = 0 WHERE ven_id IN ($placeholders)";
                break;

            case 'equipment':
                $query = "UPDATE tbl_equipment_unit SET is_active = 0 WHERE unit_id IN ($placeholders)";
                break;

            default:
                return json_encode(['status' => 'error', 'message' => 'Invalid resource type.']);
        }

        $stmt = $this->conn->prepare($query);
        
        // Execute with array of IDs
        if ($stmt->execute($resourceId)) {
            $count = $stmt->rowCount();
            if ($count > 0) {
                // Non-blocking audit log insert without IDs
                try {
                    $typeLabel = ucfirst((string)$resourceType);
                    $desc = "Archived " . $typeLabel . " resource(s): " . $count;
                    $auditSql = "INSERT INTO audit_log (description, action, created_at, created_by) VALUES (:description, :action, NOW(), :created_by)";
                    $audit = $this->conn->prepare($auditSql);
                    $action = 'ARCHIVE';
                    $audit->bindParam(':description', $desc, PDO::PARAM_STR);
                    $audit->bindParam(':action', $action, PDO::PARAM_STR);
                    if ($userId !== null) {
                        $audit->bindValue(':created_by', $userId, PDO::PARAM_INT);
                    } else {
                        $audit->bindValue(':created_by', null, PDO::PARAM_NULL);
                    }
                    if (!$audit->execute()) {
                        error_log("Audit log insert failed (archiveResource): " . print_r($audit->errorInfo(), true));
                    } else {
                        try {
                            $latest = $this->conn->query("SELECT id, description, action, created_at, created_by FROM audit_log ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
                            error_log("audit_log latest (archiveResource): " . json_encode($latest));
                        } catch (Throwable $te) {
                            error_log("Failed to read latest audit_log (archiveResource): " . $te->getMessage());
                        }
                    }
                } catch (Throwable $te) {
                    error_log("Audit logging error (archiveResource): " . $te->getMessage());
                }
                return json_encode([
                    'status' => 'success', 
                    'message' => $count . ' resource(s) archived successfully.'
                ]);
            } else {
                return json_encode([
                    'status' => 'error', 
                    'message' => 'No resources found with the given IDs.'
                ]);
            }
        }

        return json_encode(['status' => 'error', 'message' => 'Error archiving resource(s).']);

    } catch (PDOException $e) {
        return json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
    }
}


    public function unarchiveResource($resourceType, $resourceId, $is_serialize = false, $userId = null) {
    try {
        // Debug: log context
        error_log("unarchiveResource userId=" . var_export($userId, true) . ", type=" . var_export($resourceType, true));
        if (empty($resourceType) || empty($resourceId)) {
            return json_encode(['status' => 'error', 'message' => 'Resource type and ID are required.']);
        }

        // Convert single ID to array for consistent handling
        if (!is_array($resourceId)) {
            $resourceId = [$resourceId];
        }

        // Create placeholders for IN clause
        $placeholders = implode(',', array_fill(0, count($resourceId), '?'));

        $query = "";
        switch ($resourceType) {
            case 'vehicle':
                $query = "UPDATE tbl_vehicle SET is_active = 1 WHERE vehicle_id IN ($placeholders)";
                break;

            case 'venue':
                $query = "UPDATE tbl_venue SET is_active = 1 WHERE ven_id IN ($placeholders)";
                break;

            case 'equipment':
                $query = "UPDATE tbl_equipment_unit SET is_active = 1 WHERE unit_id IN ($placeholders)";
                break;

            default:
                return json_encode(['status' => 'error', 'message' => 'Invalid resource type.']);
        }

        $stmt = $this->conn->prepare($query);
        
        // Execute with array of IDs
        if ($stmt->execute($resourceId)) {
            $count = $stmt->rowCount();
            if ($count > 0) {
                // Non-blocking audit log insert without IDs
                try {
                    $typeLabel = ucfirst((string)$resourceType);
                    $desc = "Unarchived " . $typeLabel . " resource(s): " . $count;
                    $auditSql = "INSERT INTO audit_log (description, action, created_at, created_by) VALUES (:description, :action, NOW(), :created_by)";
                    $audit = $this->conn->prepare($auditSql);
                    $action = 'UNARCHIVE';
                    $audit->bindParam(':description', $desc, PDO::PARAM_STR);
                    $audit->bindParam(':action', $action, PDO::PARAM_STR);
                    if ($userId !== null) {
                        $audit->bindValue(':created_by', $userId, PDO::PARAM_INT);
                    } else {
                        $audit->bindValue(':created_by', null, PDO::PARAM_NULL);
                    }
                    if (!$audit->execute()) {
                        error_log("Audit log insert failed (unarchiveResource): " . print_r($audit->errorInfo(), true));
                    } else {
                        try {
                            $latest = $this->conn->query("SELECT id, description, action, created_at, created_by FROM audit_log ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
                            error_log("audit_log latest (unarchiveResource): " . json_encode($latest));
                        } catch (Throwable $te) {
                            error_log("Failed to read latest audit_log (unarchiveResource): " . $te->getMessage());
                        }
                    }
                } catch (Throwable $te) {
                    error_log("Audit logging error (unarchiveResource): " . $te->getMessage());
                }
                return json_encode([
                    'status' => 'success', 
                    'message' => $count . ' resource(s) unarchived successfully.'
                ]);
            } else {
                return json_encode([
                    'status' => 'error', 
                    'message' => 'No resources found with the given IDs.'
                ]);
            }
        }

        return json_encode(['status' => 'error', 'message' => 'Error unarchiving resource(s).']);

    } catch (PDOException $e) {
        return json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

public function fetchInactiveUser() {
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

        

        // Combine all results and execute
        $sql = "($usersSql) ORDER BY type, lname";
        return $this->executeQuery($sql);
    } catch (PDOException $e) {
        return json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
    }
}


public function fetchEquipmentAndInactiveUnits() {
    // Fetch only inactive units, but include their equipment name
    $unitSql = "SELECT 
                    eu.unit_id, 
                    eu.equip_id, 
                    eu.serial_number,
                    e.equip_name
                FROM 
                    tbl_equipment_unit eu
                INNER JOIN 
                    tbl_equipments e ON e.equip_id = eu.equip_id
                WHERE 
                    eu.is_active = 0";

    $stmt = $this->conn->prepare($unitSql);
    $stmt->execute();
    $inactiveUnits = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $response = [];

    foreach ($inactiveUnits as $unit) {
        $response[] = [
            'equip_id' => (int)$unit['equip_id'],
            'equip_name' => $unit['equip_name'],
            'unit_id' => (int)$unit['unit_id'],
            'serial_number' => $unit['serial_number'] ?? null,
            'quantity' => isset($unit['quantity']) ? (int)$unit['quantity'] : null
        ];
    }

    return json_encode(['status' => 'success', 'data' => $response]);
}

    public function fetchInactiveVenue() {
        $sql = "SELECT 
                ven_id, 
                ven_name, 
                ven_occupancy, 
                ven_created_at, 
                ven_updated_at, 
                status_availability_id, 
                ven_pic, 
                is_active
                FROM tbl_venue
                WHERE is_active = 0";
        return $this->executeQuery($sql);
    }

    public function fetchInactiveVehicle() {
        $sql = "SELECT  
                    v.vehicle_id,
                    v.vehicle_license,
                    v.year,
                    v.vehicle_pic,
                    v.status_availability_id,
                    vmk.vehicle_make_name, 
                    vc.vehicle_category_name,
                    vmd.vehicle_model_name,
                    sa.status_availability_name,
                    v.is_active
                FROM 
                    tbl_vehicle v
                INNER JOIN
                    tbl_vehicle_model vmd ON v.vehicle_model_id = vmd.vehicle_model_id
                INNER JOIN
                    tbl_vehicle_make vmk ON vmd.vehicle_model_vehicle_make_id = vmk.vehicle_make_id
                INNER JOIN
                    tbl_vehicle_category vc ON vmd.vehicle_category_id = vc.vehicle_category_id
                INNER JOIN
                    tbl_status_availability sa ON v.status_availability_id = sa.status_availability_id
                WHERE 
                    v.is_active = 0";
    
        return $this->executeQuery($sql);
    }
    
    public function sendPushNotificationToUser($userId, $title = 'Notification', $body = 'You have a new notification', $data = []) {
        try {
            // Make a POST request to the push notification service
            $pushNotificationUrl = 'http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']) . '/server/send-push-notification.php';
            
            $postData = json_encode([
                'operation' => 'send',
                'user_id' => $userId,
                'title' => $title,
                'body' => $body,
                'data' => $data
            ]);
            
            $context = stream_context_create([
                'http' => [
                    'method' => 'POST',
                    'header' => [
                        'Content-Type: application/json',
                        'Content-Length: ' . strlen($postData)
                    ],
                    'content' => $postData,
                    'timeout' => 10
                ]
            ]);
            
            $result = file_get_contents($pushNotificationUrl, false, $context);
            
            if ($result === false) {
                error_log("Failed to send push notification to user {$userId}");
                return false;
            }
            
            $response = json_decode($result, true);
            if ($response && isset($response['status']) && $response['status'] === 'success') {
                error_log("Push notification sent successfully to user {$userId}");
                return true;
            } else {
                error_log("Push notification failed for user {$userId}: " . ($response['message'] ?? 'Unknown error'));
                return false;
            }
            
        } catch (Exception $e) {
            error_log("Exception in sendPushNotificationToUser: " . $e->getMessage());
            return false;
        }
    }

}

// Handle the request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        $input = $_POST;
    }
    $operation = $input['operation'] ?? '';   
    $user = new User();   
    switch ($operation) {

        case 'fetchInactiveUser':
            echo $user->fetchInactiveUser();
            break;

        case 'fetchInactiveVehicle':
            echo $user->fetchInactiveVehicle();
            break;

        case 'fetchInactiveVenue':
            echo $user->fetchInactiveVenue();
            break;

        case 'fetchEquipmentAndInactiveUnits':
            echo $user->fetchEquipmentAndInactiveUnits();
            break;

        case 'archiveResource':
            // Get data from JSON input or fall back to $_POST
            $resourceType = $input['resourceType'] ?? ($_POST['resourceType'] ?? null);
            $resourceId = $input['resourceId'] ?? ($_POST['resourceId'] ?? null);
            $is_serialize = $input['is_serialize'] ?? ($_POST['is_serialize'] ?? false);
            $userId = $input['userid'] ?? ($_POST['userid'] ?? null);
            
            if ($resourceType && $resourceId) {
                error_log("route archiveResource - Type: $resourceType, ID: " . print_r($resourceId, true) . ", User: " . ($userId ?? 'null'));
                echo $user->archiveResource($resourceType, $resourceId, $is_serialize, $userId);
            } else {
                error_log('Archive resource failed - Missing parameters. Input: ' . print_r($input, true));
                echo json_encode(['status' => 'error', 'message' => 'Missing required parameters. resourceType and resourceId are required.']);
            }
            break;
            
        case 'unarchiveResource':
            // Get data from JSON input or fall back to $_POST
            $resourceType = $input['resourceType'] ?? ($_POST['resourceType'] ?? null);
            $resourceId = $input['resourceId'] ?? ($_POST['resourceId'] ?? null);
            $is_serialize = $input['is_serialize'] ?? ($_POST['is_serialize'] ?? false);
            $userId = $input['userid'] ?? ($_POST['userid'] ?? null);
            
            if ($resourceType && $resourceId) {
                error_log("route unarchiveResource - Type: $resourceType, ID: " . print_r($resourceId, true) . ", User: " . ($userId ?? 'null'));
                echo $user->unarchiveResource($resourceType, $resourceId, $is_serialize, $userId);
            } else {
                error_log('Unarchive resource failed - Missing parameters. Input: ' . print_r($input, true));
                echo json_encode(['status' => 'error', 'message' => 'Missing required parameters. resourceType and resourceId are required.']);
            }
            break;

        case 'archiveUser':
            // Get data from JSON input or fall back to $_POST
            $userType = $input['userType'] ?? ($_POST['userType'] ?? null);
            $userId = $input['userId'] ?? ($_POST['userId'] ?? null);
            
            if ($userType && $userId) {
                echo $user->archiveUser($userType, $userId);
            } else {
                error_log('Archive user failed - Missing parameters. Input: ' . print_r($input, true));
                echo json_encode(['status' => 'error', 'message' => 'Missing required parameters. userType and userId are required.']);
            }
            break;
        case 'unarchiveUser':
            // Get data from JSON input or fall back to $_POST
            $userType = $input['userType'] ?? ($_POST['userType'] ?? null);
            $userId = $input['userId'] ?? ($_POST['userId'] ?? null);
            
            if ($userType && $userId) {
                echo $user->unArchive($userType, $userId);
            } else {
                error_log('Unarchive user failed - Missing parameters. Input: ' . print_r($input, true));
                echo json_encode(['status' => 'error', 'message' => 'Missing required parameters. userType and userId are required.']);
            }
            break;

        case 'updateEquipmentUnit':
            $data = $input['json'] ?? ($input['unitData'] ?? ($input['data'] ?? $input ?? null));
            $result = $user->updateEquipmentUnit($data);
            echo $result;
            break;

        case "handleRequest":
                $reservationId = $input['reservation_id'] ?? null;
                $isAccepted = $input['is_accepted'] ?? false;
                $userId = $input['user_id'] ?? null;
                $notificationMessage = $input['notification_message'] ?? '';
                $notificationUserId = $input['notification_user_id'] ?? null;
                if ($reservationId === null) {
                    echo json_encode(['status' => 'error', 'message' => 'Reservation ID is required']);
                    break;
                }
                if ($userId === null) {
                    echo json_encode(['status' => 'error', 'message' => 'User ID is required']);
                    break;
                }
                echo $user->handleRequest($reservationId, $isAccepted, $userId, $notificationMessage, $notificationUserId);
                break;
        case "fetchEquipmentCategoryById": // Fetch equipment category by ID
            $equipmentId = $input['id'] ?? ($_POST['id'] ?? null);
            if ($equipmentId) {
                echo $user->fetchEquipmentCategoryById($equipmentId); 
            } else {
                echo json_encode(['status' => 'error', 'message' => 'ID parameter is missing.']);
            }
            break;

        case "fetchVehicleById":
            $vehicleId = $input['id'] ?? ($_POST['id'] ?? null);
            if ($vehicleId) {
                echo $user->fetchVehicleById($vehicleId); // Fetch vehicle by ID
            } else {
                echo json_encode(['status' => 'error', 'message' => 'ID parameter is missing.']);
            }
            break;

        case "getConsumableUsage":
            $equipId = $input['equipId'] ?? null;
            if ($equipId) {
                echo $user->getConsumableUsage($equipId);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Equipment ID is required']);
            }
            break;


        case "fetchModelById":
            $vehicleModelId = $input['id'] ?? ($_POST['id'] ?? null);
            if ($vehicleModelId) {
                echo $user->fetchModelById($vehicleModelId); // Fetch vehicle model by ID
            } else {
                echo json_encode(['status' => 'error', 'message' => 'ID parameter is missing.']);
            }
            break;

        case 'fetchConditions':
            echo $user->fetchConditions();
            break;

        case "get_message":
            $userId = $input['userid'] ?? ($_POST['userid'] ?? null);
            if ($userId) {
                echo $user->get_message($userId);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'userid parameter is missing']);
            }
            break;

        case "fetchVenueById":
            $venueId = $input['id'] ?? ($_POST['id'] ?? null);
            if ($venueId) {
                echo $user->fetchVenueById($venueId);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'ID parameter is missing.']);
            }
            break;

        case "updateDepartment":
            $id = $input['id'] ?? '';
            $name = $input['name'] ?? '';
            $type = $input['type'] ?? '';
            $userId = $input['userid'] ?? ($_POST['userid'] ?? null);
            echo $user->updateDepartment($id, $name, $type, $userId);
            break;

        case "updateVehicleMake":
            $id = $input['id'] ?? null;
            $name = $input['name'] ?? null;
            if (!$id || !$name) {
                echo json_encode(['status' => 'error', 'message' => 'Missing id or name']);
                break;
            }
            $userId = $input['userid'] ?? ($_POST['userid'] ?? null);
            echo $user->updateVehicleMake($id, $name, $userId);
            break;

        case "fetchMake":
            echo $user->fetchMake();
            break;
        case "fetchRecord":
            echo $user->fetchRecord();
            break;
        case "fetchAudit":
            echo $user->fetchAudit();
            break;
        case "fetchStatusAvailability":
            echo $user->fetchStatusAvailability();
            break;

        case "fetchEquipments":
            $startDateTime = $input['startDateTime'] ?? null;
            $endDateTime = $input['endDateTime'] ?? null;
            $user->fetchEquipments($startDateTime, $endDateTime);
            break;

       
        case "fetchDeansApproval":
            $reservationId = $input['reservation_id'] ?? null;
            if ($reservationId === null) {
                echo json_encode(['status' => 'error', 'message' => 'Reservation ID is required']);
                break;
            }
            echo $user->fetchDeansApproval($reservationId);
            break;

        case "fetchReadApprovalNotification":
            echo $user->fetchReadApprovalNotification();
            break;
        case "insertDriver":
            $reservation_driver_user_id = $input['reservation_driver_user_id'] ?? null;
            $reservation_vehicle_id = $input['reservation_vehicle_id'] ?? null;
            echo $user->insertDriver($reservation_driver_user_id, $reservation_vehicle_id);
            break;

        case "saveHoliday":
            $userId = $input['userid'] ?? ($_POST['userid'] ?? null);
            error_log("route saveHoliday userId=" . var_export($userId, true));
            echo $user->saveHoliday($input, $userId);
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

        case "fetchVenueScheduled":
            echo $user->fetchVenueScheduled();
            break;

        case "fetchTitle":
            echo $user->fetchTitle();
            break;

        case "fetchHoliday":
            echo $user->fetchHoliday();
            break;        
        case "updateHoliday":
            $holidayId = $input['holiday_id'] ?? null;
            $holidayName = $input['holiday_name'] ?? null;
            $holidayDate = $input['holiday_date'] ?? null;
            
            if (!$holidayId || !$holidayName || !$holidayDate) {
                echo json_encode(['status' => 'error', 'message' => 'Missing required parameters']);
                break;
            }
            $userId = $input['userid'] ?? ($_POST['userid'] ?? null);
            error_log("route updateHoliday userId=" . var_export($userId, true));
            echo $user->updateHoliday($holidayId, $holidayName, $holidayDate, $userId);
            break;
        
        case "fetchDepartments":
            echo $user->fetchDepartments();
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
        case "fetchEquipment": 
            echo $user->fetchEquipment();
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


        case "fetchDriver":
            $startDateTime = $input['startDateTime'] ?? null;
            $endDateTime = $input['endDateTime'] ?? null;   
            $userId = $input['userId'] ?? null;
            echo $user->fetchDriver($startDateTime, $endDateTime, $userId);
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

        case "countReservationTrends":
            echo $user->countReservationTrends();
            break;
        case "countTrendReservations":
            echo $user->countTrendReservations();
            break;
        case "countCompletedAndCancelledReservations":
            echo $user->countCompletedAndCancelledReservations();
            break;
        case "insertNotificationTouser":
            $notification_message = $input['notification_message'] ?? null;
            $notification_user_id = $input['notification_user_id'] ?? null;
            $reservation_id = $input['reservation_id'] ?? null;
            if (!$notification_message || !$notification_user_id) {
                echo json_encode(['status' => 'error', 'message' => 'Missing notification message or user ID']);
                break;
            }
            echo $user->insertNotificationTouser($notification_message, $notification_user_id, $reservation_id);
            break;
        
        case "saveModelData":
            $userId = $input['userid'] ?? ($_POST['userid'] ?? null);
            error_log("route saveModelData userId=" . var_export($userId, true));
            echo $user->saveModelData($input, $userId);
            break;
        case "saveMakeData":
            $userId = $input['userid'] ?? ($_POST['userid'] ?? null);
            error_log("route saveMakeData userId=" . var_export($userId, true));
            echo $user->saveMakeData($input, $userId);
            break;
        case "saveEquipmentCategory":
            $userId = $input['userid'] ?? ($_POST['userid'] ?? null);
            error_log("route saveEquipmentCategory userId=" . var_export($userId, true));
            echo $user->saveEquipmentCategory($input, $userId);
            break;
        case "saveDepartmentData":
            $userId = $input['userid'] ?? ($_POST['userid'] ?? null);
            echo $user->saveDepartmentData($input, $userId);
            break;
        case "saveCategoryData":
            $userId = $input['userid'] ?? ($_POST['userid'] ?? null);
            error_log("route saveCategoryData userId=" . var_export($userId, true));
            echo $user->saveCategoryData($input, $userId);
            break;

        case "fetchVehicleCategories":
            echo $user->fetchVehicleCategories();
            break;

        case "updateVehicleMake":
            $id = $input['id'] ?? null;
            $name = $input['name'] ?? null;
            if (!$id || !$name) {
                echo json_encode(['status' => 'error', 'message' => 'Missing id or name']);
                break;
            }
            $userId = $input['userid'] ?? ($_POST['userid'] ?? null);
            echo $user->updateVehicleMake($id, $name, $userId);
            break;

        case "updateVehicleCategory":
            $id = $input['id'] ?? null;
            $name = $input['name'] ?? null;
            if (!$id || !$name) {
                echo json_encode(['status' => 'error', 'message' => 'Missing id or name']);
                break;
            }
            $userId = $input['userid'] ?? ($_POST['userid'] ?? null);
            error_log("route updateVehicleCategory userId=" . var_export($userId, true));
            echo $user->updateVehicleCategory($id, $name, $userId);
            break;

        case "updateVehicleModel":
            if (isset($input['modelData'])) {
                $userId = $input['userid'] ?? ($_POST['userid'] ?? null);
                error_log("route updateVehicleModel userId=" . var_export($userId, true));
                echo $user->updateVehicleModel($input['modelData'], $userId);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Missing modelData']);
            }
            break;

        case "fetchModels":
            echo $user->fetchModels();
            break;

        case "fetchEquipmentsCategory":
            echo $user->fetchEquipmentsCategory();
            break;
        case "updateEquipmentCategory":
            $categoryData = $input['categoryData'] ?? null;
            if (!$categoryData) {
                echo json_encode(['status' => 'error', 'message' => 'Missing categoryData']);
                break;
            }
            $userId = $input['userid'] ?? ($_POST['userid'] ?? null);
            error_log("route updateEquipmentCategory userId=" . var_export($userId, true));
            echo $user->updateEquipmentCategory($categoryData, $userId);
            break;
        
        case "fetchModelsByCategoryAndMake":
            $categoryId = $input['categoryId'] ?? null;
            $makeId = $input['makeId'] ?? null;
            if ($categoryId && $makeId) {
                echo $user->fetchModelsByCategoryAndMake($categoryId, $makeId);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Category ID and Make ID are required']);
            }
            break;
        
        case "fetchUsersById":
            $id = $input['id'] ?? null;
            if (!$id) {
                echo json_encode(['status' => 'error', 'message' => 'Missing id']);
                break;
            }
            echo $user->fetchUsersById($id);
            break;
        
        case "fetchEquipmentById":
            $equipmentId = $input['id'] ?? ($_POST['id'] ?? null);
            if ($equipmentId) {
                echo $user->fetchEquipmentById($equipmentId);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'ID parameter is missing.']);
            }
            break;       
        case "getTotals":
            echo $user->getTotals();
            break;
        case "fetchVenues":
            echo $user->fetchVenues();
            break;
        case "fetchVehicles":
            echo $user->fetchVehicles();
            break;
       
        default:
            echo json_encode(['status' => 'error', 'message' => 'Invalid operation']);
            break;
    }
}
?>