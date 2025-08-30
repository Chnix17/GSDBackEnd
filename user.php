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
                is_active,
                event_type,
                area_type
                FROM tbl_venue
                WHERE is_active = 1
                ORDER BY ven_id DESC
                ";
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

    public function hasConflictRequest($resourceType, $resourceId, $startDate, $endDate, $requestedQuantity = 0) {
        if (!in_array($resourceType, ['venue', 'vehicle', 'equipment'])) {
            error_log("Invalid resource type: " . $resourceType);
            return ['status' => false, 'count' => 0];
        }

        try {
            $sql = "";

            switch ($resourceType) {
                case 'venue':
                    $sql = "SELECT COUNT(DISTINCT r.reservation_id) AS conflict_count
                            FROM tbl_reservation r
                            INNER JOIN tbl_reservation_venue v
                              ON r.reservation_id = v.reservation_reservation_id
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
                            ) ls ON ls.reservation_reservation_id = r.reservation_id
                            LEFT JOIN (
                                SELECT reservation_reservation_id, MAX(reservation_status_id) AS max_reschedule_status_id
                                FROM tbl_reservation_status
                                WHERE reservation_status_status_id = 10 AND reservation_active = 1
                                GROUP BY reservation_reservation_id
                            ) active_resched ON active_resched.reservation_reservation_id = r.reservation_id
                            WHERE (
                              CASE 
                                WHEN (
                                      (ls.reservation_status_status_id = 10 AND ls.reservation_active = 1)
                                      OR (
                                          ls.reservation_status_status_id = 6 AND ls.reservation_active = 1
                                          AND active_resched.max_reschedule_status_id IS NOT NULL
                                      )
                                    )
                                    AND v.reservation_change_venue_id IS NOT NULL
                                THEN v.reservation_change_venue_id
                                ELSE v.reservation_venue_venue_id
                              END
                            ) = :resource_id
                              AND (
                                ls.reservation_status_status_id IN (1, 6, 8, 10)
                                AND (
                                  (ls.reservation_status_status_id = 6 AND ls.reservation_active = 1)
                                  OR (ls.reservation_status_status_id IN (1, 8, 10) AND (ls.reservation_active = 0 OR ls.reservation_active = 1))
                                )
                              )
                              AND (
                                (CASE
                                  WHEN (
                                        (ls.reservation_status_status_id = 10 AND ls.reservation_active = 1)
                                        OR (
                                            ls.reservation_status_status_id = 6 AND ls.reservation_active = 1
                                            AND active_resched.max_reschedule_status_id IS NOT NULL
                                        )
                                      )
                                      AND r.reschedule_start_date IS NOT NULL AND r.reschedule_end_date IS NOT NULL
                                  THEN r.reschedule_start_date ELSE r.reservation_start_date END) <= :end_date
                                AND (CASE
                                  WHEN (
                                        (ls.reservation_status_status_id = 10 AND ls.reservation_active = 1)
                                        OR (
                                            ls.reservation_status_status_id = 6 AND ls.reservation_active = 1
                                            AND active_resched.max_reschedule_status_id IS NOT NULL
                                        )
                                      )
                                      AND r.reschedule_start_date IS NOT NULL AND r.reschedule_end_date IS NOT NULL
                                  THEN r.reschedule_end_date ELSE r.reservation_end_date END) >= :start_date
                              )";
                    break;

                case 'vehicle':
                    $sql = "SELECT COUNT(DISTINCT r.reservation_id) AS conflict_count
                            FROM tbl_reservation r
                            INNER JOIN tbl_reservation_vehicle v
                              ON r.reservation_id = v.reservation_reservation_id
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
                            ) ls ON ls.reservation_reservation_id = r.reservation_id
                            LEFT JOIN (
                                SELECT reservation_reservation_id, MAX(reservation_status_id) AS max_reschedule_status_id
                                FROM tbl_reservation_status
                                WHERE reservation_status_status_id = 10 AND reservation_active = 1
                                GROUP BY reservation_reservation_id
                            ) active_resched ON active_resched.reservation_reservation_id = r.reservation_id
                            WHERE (
                              CASE 
                                WHEN (
                                      (ls.reservation_status_status_id = 10 AND ls.reservation_active = 1)
                                      OR (
                                          ls.reservation_status_status_id = 6 AND ls.reservation_active = 1
                                          AND active_resched.max_reschedule_status_id IS NOT NULL
                                      )
                                    )
                                    AND v.reservation_change_vehicle_id IS NOT NULL
                                THEN v.reservation_change_vehicle_id
                                ELSE v.reservation_vehicle_vehicle_id
                              END
                            ) = :resource_id
                              AND (
                                ls.reservation_status_status_id IN (1, 6, 8, 10)
                                AND (
                                  (ls.reservation_status_status_id = 6 AND ls.reservation_active = 1)
                                  OR (ls.reservation_status_status_id IN (1, 8, 10) AND (ls.reservation_active = 0 OR ls.reservation_active = 1))
                                )
                              )
                              AND (
                                (CASE
                                  WHEN (
                                        (ls.reservation_status_status_id = 10 AND ls.reservation_active = 1)
                                        OR (
                                            ls.reservation_status_status_id = 6 AND ls.reservation_active = 1
                                            AND active_resched.max_reschedule_status_id IS NOT NULL
                                        )
                                      )
                                      AND r.reschedule_start_date IS NOT NULL AND r.reschedule_end_date IS NOT NULL
                                  THEN r.reschedule_start_date ELSE r.reservation_start_date END) <= :end_date
                                AND (CASE
                                  WHEN (
                                        (ls.reservation_status_status_id = 10 AND ls.reservation_active = 1)
                                        OR (
                                            ls.reservation_status_status_id = 6 AND ls.reservation_active = 1
                                            AND active_resched.max_reschedule_status_id IS NOT NULL
                                        )
                                      )
                                      AND r.reschedule_start_date IS NOT NULL AND r.reschedule_end_date IS NOT NULL
                                  THEN r.reschedule_end_date ELSE r.reservation_end_date END) >= :start_date
                              )";
                    break;
                    // driver conflict check removed

                case 'equipment':
                    // Compute capacity based on inventory model (bulk or serial)
                    //  - Bulk: use tbl_equipment_quantity.on_hand_quantity (fallback to quantity if null)
                    //  - Serial: count of active units in tbl_equipment_unit whose status is not 2 (only 2 is considered unavailable)
                    // Then subtract overlapping reserved quantities from tbl_reservation_equipment.
                    $sql = "SELECT t.equip_id,
                                   t.equip_name,
                                   t.total_capacity,
                                   COALESCE(ur.used_quantity, 0) AS used_quantity,
                                   (t.total_capacity - COALESCE(ur.used_quantity, 0)) AS remaining_quantity
                            FROM (
                              SELECT eq.equip_id,
                                     eq.equip_name,
                                     CASE 
                                       WHEN LOWER(eq.equip_type) = 'bulk' THEN (
                                         SELECT COALESCE(SUM(COALESCE(q.on_hand_quantity, q.quantity)), 0)
                                         FROM tbl_equipment_quantity q
                                         WHERE q.equip_id = eq.equip_id
                                           AND (q.status_availability_id IS NULL OR q.status_availability_id <> 2)
                                       )
                                       ELSE (
                                         SELECT COALESCE(SUM(CASE WHEN u.is_active = 1 AND (u.status_availability_id IS NULL OR u.status_availability_id <> 2) THEN 1 ELSE 0 END), 0)
                                         FROM tbl_equipment_unit u
                                         WHERE u.equip_id = eq.equip_id
                                       )
                                     END AS total_capacity
                              FROM tbl_equipments eq
                              WHERE eq.equip_id = :resource_id
                            ) t
                            LEFT JOIN (
                              SELECT e.reservation_equipment_equip_id AS equip_id,
                                     COALESCE(SUM(e.reservation_equipment_quantity), 0) AS used_quantity
                              FROM tbl_reservation_equipment e
                              INNER JOIN tbl_reservation r ON e.reservation_reservation_id = r.reservation_id
                              INNER JOIN tbl_reservation_status rs ON r.reservation_id = rs.reservation_reservation_id
                              WHERE e.reservation_equipment_equip_id = :resource_id
                                AND rs.reservation_status_status_id = 1
                                AND r.reservation_id NOT IN (
                                  SELECT DISTINCT reservation_reservation_id 
                                  FROM tbl_reservation_status 
                                  WHERE reservation_status_status_id IN (2,5)
                                )
                                AND (:start_date <= r.reservation_end_date AND :end_date >= r.reservation_start_date)
                            ) ur ON ur.equip_id = t.equip_id";

                    $stmt = $this->conn->prepare($sql);
                    $stmt->bindValue(':resource_id', $resourceId, PDO::PARAM_INT);
                    $stmt->bindValue(':start_date', $startDate);
                    $stmt->bindValue(':end_date', $endDate);
                    $stmt->execute();
                    $result = $stmt->fetch(PDO::FETCH_ASSOC);

                    if (!$result) {
                        return ['status' => true, 'count' => 0];
                    }
                    $remainingQuantity = (int)$result['remaining_quantity'];
                    error_log('Equipment check equip_id=' . (int)$resourceId . ' remaining=' . $remainingQuantity . ' requested=' . (int)$requestedQuantity . ' total_capacity=' . (int)$result['total_capacity']);
                    return ['status' => true, 'count' => ($requestedQuantity > $remainingQuantity ? 1 : 0)];
            }

            // Generic path for venue/vehicle
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':resource_id', $resourceId, PDO::PARAM_INT);
            $stmt->bindParam(':start_date', $startDate);
            $stmt->bindParam(':end_date', $endDate);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            $conflictCount = (int)($result['conflict_count'] ?? 0);
            return ['status' => true, 'count' => $conflictCount];

        } catch (PDOException $e) {
            error_log("Conflict check error: " . $e->getMessage());
            return ['status' => false, 'count' => 0];
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

    public function getReservedById($reservation_id) {
        try {
            // 1. Fetch the reservation details
            $reservationSql = "
                SELECT 
                    reservation_id,
                    reservation_title,
                    reservation_description,
                    reservation_start_date,
                    reservation_end_date,
                    reservation_participants,
                    reservation_user_id,
                    reservation_created_at
                FROM 
                    tbl_reservation
                WHERE 
                    reservation_id = :reservation_id
            ";
            $stmt = $this->conn->prepare($reservationSql);
            $stmt->bindParam(':reservation_id', $reservation_id, PDO::PARAM_INT);
            $stmt->execute();
            $reservation = $stmt->fetch(PDO::FETCH_ASSOC);
    
            if (!$reservation) {
                return json_encode(['status' => 'error', 'message' => 'Reservation not found']);
            }
    
            $result = [
                'reservation' => $reservation,
                'venues'      => [],
                'vehicles'    => [],
                'equipments'  => []
            ];
    
            // 1b. Check status to decide if we should use change venue for checklist
            $statusSql = "
                SELECT 1
                FROM tbl_reservation_status
                WHERE reservation_reservation_id = :reservation_id
                  AND reservation_status_status_id IN (6, 10)
                  AND reservation_active = 1
                LIMIT 1
            ";
            $stmtStatus = $this->conn->prepare($statusSql);
            $stmtStatus->bindParam(':reservation_id', $reservation_id, PDO::PARAM_INT);
            $stmtStatus->execute();
            $hasRescheduleStatus = (bool)$stmtStatus->fetchColumn();

            // 2. Fetch venues
            $venueSql = "
                SELECT 
                    rv.reservation_venue_id, 
                    rv.reservation_venue_venue_id AS original_venue_id,
                    rv.reservation_change_venue_id,
                    rv.reservation_reservation_id,
                    rv.active
                FROM 
                    tbl_reservation_venue rv
                WHERE 
                    rv.reservation_reservation_id = :reservation_id
            ";
            $stmt = $this->conn->prepare($venueSql);
            $stmt->bindParam(':reservation_id', $reservation_id, PDO::PARAM_INT);
            $stmt->execute();
            $venues = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
            foreach ($venues as $venueRow) {
                // If has reschedule status but change_venue_id is null, use original venue_id
                $venueIdToUse = ($hasRescheduleStatus && !empty($venueRow['reservation_change_venue_id'])) ? (int)$venueRow['reservation_change_venue_id'] : (int)$venueRow['original_venue_id'];
    
                $venueDetail = [
                    'reservation_venue_id' => $venueRow['reservation_venue_id'],
                    'venue_id' => $venueIdToUse,
                    'original_venue_id' => $venueRow['original_venue_id'],
                    'change_venue_id' => $venueRow['reservation_change_venue_id'],
                    'active' => $venueRow['active'],
                    'name' => null,
                    'checklists' => []
                ];
    
                $venNameSql = "SELECT ven_name FROM tbl_venue WHERE ven_id = :venue_id";
                $stmtName = $this->conn->prepare($venNameSql);
                $stmtName->bindParam(':venue_id', $venueIdToUse, PDO::PARAM_INT);
                $stmtName->execute();
                $venueName = $stmtName->fetch(PDO::FETCH_ASSOC);
                $venueDetail['name'] = $venueName ? $venueName['ven_name'] : null;
    
                $checklistVenueSql = "
                    SELECT checklist_venue_id, checklist_name 
                    FROM tbl_checklist_venue_master 
                    WHERE checklist_venue_ven_id = :venue_id
                ";
                $stmtChecklist = $this->conn->prepare($checklistVenueSql);
                $stmtChecklist->bindParam(':venue_id', $venueIdToUse, PDO::PARAM_INT);
                $stmtChecklist->execute();
                $venueDetail['checklists'] = $stmtChecklist->fetchAll(PDO::FETCH_ASSOC);
    
                $result['venues'][] = $venueDetail;
            }
    
            // 3. Fetch vehicles
            $vehicleSql = "
                SELECT 
                    rvh.reservation_vehicle_id, 
                    rvh.reservation_vehicle_vehicle_id AS vehicle_id,
                    rvh.reservation_change_vehicle_id,
                    rvh.active
                FROM 
                    tbl_reservation_vehicle rvh
                WHERE 
                    rvh.reservation_reservation_id = :reservation_id
            ";
            $stmt = $this->conn->prepare($vehicleSql);
            $stmt->bindParam(':reservation_id', $reservation_id, PDO::PARAM_INT);
            $stmt->execute();
            $vehicles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
            foreach ($vehicles as $vehicleRow) {
                // If has reschedule status but change_vehicle_id is null, use original vehicle_id
                $vehicleIdToUse = ($hasRescheduleStatus && !empty($vehicleRow['reservation_change_vehicle_id']))
                    ? (int)$vehicleRow['reservation_change_vehicle_id']
                    : (int)$vehicleRow['vehicle_id'];

                $vehicleDetail = [
                    'reservation_vehicle_id' => $vehicleRow['reservation_vehicle_id'],
                    'vehicle_id' => $vehicleIdToUse,
                    'original_vehicle_id' => $vehicleRow['vehicle_id'],
                    'change_vehicle_id' => $vehicleRow['reservation_change_vehicle_id'],
                    'active' => isset($vehicleRow['active']) ? $vehicleRow['active'] : null,
                    'model' => null,
                    'license' => null,
                    'checklists' => []
                ];

                $vehicleDetailSql = "
                    SELECT 
                        vm.vehicle_model_name, 
                        v.vehicle_license 
                    FROM 
                        tbl_vehicle v
                    LEFT JOIN 
                        tbl_vehicle_model vm ON v.vehicle_model_id = vm.vehicle_model_id
                    WHERE 
                        v.vehicle_id = :vehicle_id
                ";
                $stmtVehicle = $this->conn->prepare($vehicleDetailSql);
                $stmtVehicle->bindParam(':vehicle_id', $vehicleIdToUse, PDO::PARAM_INT);
                $stmtVehicle->execute();
                $vehicleInfo = $stmtVehicle->fetch(PDO::FETCH_ASSOC);

                if ($vehicleInfo) {
                    $vehicleDetail['model'] = $vehicleInfo['vehicle_model_name'];
                    $vehicleDetail['license'] = $vehicleInfo['vehicle_license'];
                }

                $checklistVehicleSql = "
                    SELECT checklist_vehicle_id, checklist_name 
                    FROM tbl_checklist_vehicle_master 
                    WHERE checklist_vehicle_vehicle_id = :vehicle_id
                ";
                $stmtChecklist = $this->conn->prepare($checklistVehicleSql);
                $stmtChecklist->bindParam(':vehicle_id', $vehicleIdToUse, PDO::PARAM_INT);
                $stmtChecklist->execute();
                $vehicleDetail['checklists'] = $stmtChecklist->fetchAll(PDO::FETCH_ASSOC);

                $result['vehicles'][] = $vehicleDetail;
            }
    
            // 4. Fetch equipments
            $equipmentSql = "
                SELECT 
                    re.reservation_equipment_id, 
                    re.reservation_equipment_quantity,
                    re.reservation_equipment_equip_id AS equipment_id
                FROM 
                    tbl_reservation_equipment re
                WHERE 
                    re.reservation_reservation_id = :reservation_id
            ";
            $stmt = $this->conn->prepare($equipmentSql);
            $stmt->bindParam(':reservation_id', $reservation_id, PDO::PARAM_INT);
            $stmt->execute();
            $equipments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
            foreach ($equipments as $equipRow) {
                $equipmentDetail = [
                    'reservation_equipment_id' => $equipRow['reservation_equipment_id'],
                    'equipment_id' => $equipRow['equipment_id'],
                    'quantity' => $equipRow['reservation_equipment_quantity'],
                    'name' => null,
                    'checklists' => []
                ];
    
                $equipNameSql = "SELECT equip_name FROM tbl_equipments WHERE equip_id = :equipment_id";
                $stmtEquip = $this->conn->prepare($equipNameSql);
                $stmtEquip->bindParam(':equipment_id', $equipRow['equipment_id'], PDO::PARAM_INT);
                $stmtEquip->execute();
                $equipInfo = $stmtEquip->fetch(PDO::FETCH_ASSOC);
                $equipmentDetail['name'] = $equipInfo ? $equipInfo['equip_name'] : null;
    
                $checklistEquipSql = "
                    SELECT checklist_equipment_id, checklist_name 
                    FROM tbl_checklist_equipment_master 
                    WHERE checklist_equipment_equip_id = :equipment_id
                ";
                $stmtChecklist = $this->conn->prepare($checklistEquipSql);
                $stmtChecklist->bindParam(':equipment_id', $equipRow['equipment_id'], PDO::PARAM_INT);
                $stmtChecklist->execute();
                $equipmentDetail['checklists'] = $stmtChecklist->fetchAll(PDO::FETCH_ASSOC);
    
                $result['equipments'][] = $equipmentDetail;
            }
    
            return json_encode(['status' => 'success', 'data' => $result]);
    
        } catch (PDOException $e) {
            return json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
        }
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

    public function fetchAvailability($itemType, $itemId, $inputQuantities = [], $startDateTime = null, $endDateTime = null) {
        try {
            $itemIds = is_array($itemId) ? $itemId : [$itemId];
            $placeholders = implode(',', array_fill(0, count($itemIds), '?'));
    
            if ($itemType === 'venue') {
                // First get original venue reservations
                $sql1 = "
                    SELECT
                        v.ven_id,
                        v.ven_name,
                        v.ven_occupancy,
                        r.reservation_id,
                        r.reservation_title,
                        r.reservation_user_id,
                        ul.user_level_name AS user_level_name,
                        d.departments_name AS department_name,
                        r.reservation_start_date,
                        r.reservation_end_date,
                        r.reschedule_start_date,
                        r.reschedule_end_date,
                        latest_status.reservation_status_status_id AS reservation_status_status_id,
                        latest_status.reservation_active AS reservation_active,
                        CASE WHEN active_resched.max_reschedule_status_id IS NULL THEN 0 ELSE 1 END AS has_active_reschedule,
                        rv.reservation_change_venue_id,
                        'original' AS venue_type
                    FROM tbl_venue v
                    INNER JOIN tbl_reservation_venue rv
                        ON v.ven_id = rv.reservation_venue_venue_id
                    INNER JOIN tbl_reservation r
                        ON rv.reservation_reservation_id = r.reservation_id
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
                    ) latest_status
                        ON latest_status.reservation_reservation_id = r.reservation_id
                    LEFT JOIN (
                        SELECT reservation_reservation_id, MAX(reservation_status_id) AS max_reschedule_status_id
                        FROM tbl_reservation_status
                        WHERE reservation_status_status_id = 10 AND reservation_active = 1
                        GROUP BY reservation_reservation_id
                    ) active_resched ON active_resched.reservation_reservation_id = r.reservation_id
                    LEFT JOIN tbl_users u ON r.reservation_user_id = u.users_id
                    LEFT JOIN tbl_user_level ul ON u.users_user_level_id = ul.user_level_id
                    LEFT JOIN tbl_departments d ON u.users_department_id = d.departments_id
                    WHERE v.ven_id IN ($placeholders)
                      AND r.reservation_id NOT IN (
                          SELECT DISTINCT reservation_reservation_id 
                          FROM tbl_reservation_status 
                          WHERE reservation_status_status_id IN (2, 5)
                      )
                      AND NOT (latest_status.reservation_status_status_id = 6 AND active_resched.max_reschedule_status_id IS NOT NULL)
                    
                    UNION ALL
                    
                    SELECT
                        v.ven_id,
                        v.ven_name,
                        v.ven_occupancy,
                        r.reservation_id,
                        r.reservation_title,
                        r.reservation_user_id,
                        ul.user_level_name AS user_level_name,
                        d.departments_name AS department_name,
                        r.reschedule_start_date AS reservation_start_date,
                        r.reschedule_end_date AS reservation_end_date,
                        r.reschedule_start_date,
                        r.reschedule_end_date,
                        latest_status.reservation_status_status_id AS reservation_status_status_id,
                        latest_status.reservation_active AS reservation_active,
                        CASE WHEN active_resched.max_reschedule_status_id IS NULL THEN 0 ELSE 1 END AS has_active_reschedule,
                        rv.reservation_change_venue_id,
                        'reschedule_original' AS venue_type
                    FROM tbl_venue v
                    INNER JOIN tbl_reservation_venue rv
                        ON v.ven_id = rv.reservation_venue_venue_id
                    INNER JOIN tbl_reservation r
                        ON rv.reservation_reservation_id = r.reservation_id
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
                    ) latest_status
                        ON latest_status.reservation_reservation_id = r.reservation_id
                    LEFT JOIN (
                        SELECT reservation_reservation_id, MAX(reservation_status_id) AS max_reschedule_status_id
                        FROM tbl_reservation_status
                        WHERE reservation_status_status_id = 10 AND reservation_active = 1
                        GROUP BY reservation_reservation_id
                    ) active_resched ON active_resched.reservation_reservation_id = r.reservation_id
                    LEFT JOIN tbl_users u ON r.reservation_user_id = u.users_id
                    LEFT JOIN tbl_user_level ul ON u.users_user_level_id = ul.user_level_id
                    LEFT JOIN tbl_departments d ON u.users_department_id = d.departments_id
                    WHERE v.ven_id IN ($placeholders)
                      AND latest_status.reservation_status_status_id IN (6, 10)
                      AND rv.reservation_change_venue_id IS NULL
                      AND r.reschedule_start_date IS NOT NULL
                      AND r.reschedule_end_date IS NOT NULL
                      AND r.reservation_id NOT IN (
                          SELECT DISTINCT reservation_reservation_id 
                          FROM tbl_reservation_status 
                          WHERE reservation_status_status_id IN (2, 5)
                      )
                    
                    UNION ALL
                    
                    SELECT
                        cv.ven_id,
                        cv.ven_name,
                        cv.ven_occupancy,
                        r.reservation_id,
                        r.reservation_title,
                        r.reservation_user_id,
                        ul.user_level_name AS user_level_name,
                        d.departments_name AS department_name,
                        r.reservation_start_date,
                        r.reservation_end_date,
                        r.reschedule_start_date,
                        r.reschedule_end_date,
                        latest_status.reservation_status_status_id AS reservation_status_status_id,
                        latest_status.reservation_active AS reservation_active,
                        CASE WHEN active_resched.max_reschedule_status_id IS NULL THEN 0 ELSE 1 END AS has_active_reschedule,
                        rv.reservation_change_venue_id,
                        'change' AS venue_type
                    FROM tbl_venue cv
                    INNER JOIN tbl_reservation_venue rv
                        ON cv.ven_id = rv.reservation_change_venue_id
                    INNER JOIN tbl_reservation r
                        ON rv.reservation_reservation_id = r.reservation_id
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
                    ) latest_status
                        ON latest_status.reservation_reservation_id = r.reservation_id
                    LEFT JOIN (
                        SELECT reservation_reservation_id, MAX(reservation_status_id) AS max_reschedule_status_id
                        FROM tbl_reservation_status
                        WHERE reservation_status_status_id = 10 AND reservation_active = 1
                        GROUP BY reservation_reservation_id
                    ) active_resched ON active_resched.reservation_reservation_id = r.reservation_id
                    LEFT JOIN tbl_users u ON r.reservation_user_id = u.users_id
                    LEFT JOIN tbl_user_level ul ON u.users_user_level_id = ul.user_level_id
                    LEFT JOIN tbl_departments d ON u.users_department_id = d.departments_id
                    WHERE cv.ven_id IN ($placeholders)
                      AND rv.reservation_change_venue_id IS NOT NULL
                      AND r.reservation_id NOT IN (
                          SELECT DISTINCT reservation_reservation_id 
                          FROM tbl_reservation_status 
                          WHERE reservation_status_status_id IN (2, 5)
                      )
                ";
                
                // Add date range filtering if provided
                if ($startDateTime && $endDateTime) {
                    $dateFilter = " AND (
                        (
                            (CASE
                                WHEN latest_status.reservation_status_status_id = 10
                                     AND r.reschedule_start_date IS NOT NULL AND r.reschedule_end_date IS NOT NULL
                                THEN r.reschedule_start_date ELSE r.reservation_start_date END)
                            BETWEEN :startDateTime AND :endDateTime
                        )
                        OR (
                            (CASE
                                WHEN latest_status.reservation_status_status_id = 10
                                     AND r.reschedule_start_date IS NOT NULL AND r.reschedule_end_date IS NOT NULL
                                THEN r.reschedule_end_date ELSE r.reservation_end_date END)
                            BETWEEN :startDateTime AND :endDateTime
                        )
                        OR (
                            :startDateTime BETWEEN 
                                (CASE
                                    WHEN latest_status.reservation_status_status_id = 10
                                         AND r.reschedule_start_date IS NOT NULL AND r.reschedule_end_date IS NOT NULL
                                    THEN r.reschedule_start_date ELSE r.reservation_start_date END)
                                AND 
                                (CASE
                                    WHEN latest_status.reservation_status_status_id = 10
                                         AND r.reschedule_start_date IS NOT NULL AND r.reschedule_end_date IS NOT NULL
                                    THEN r.reschedule_end_date ELSE r.reservation_end_date END)
                        )
                        OR (
                            :endDateTime BETWEEN 
                                (CASE
                                    WHEN latest_status.reservation_status_status_id = 10
                                         AND r.reschedule_start_date IS NOT NULL AND r.reschedule_end_date IS NOT NULL
                                    THEN r.reschedule_start_date ELSE r.reservation_start_date END)
                                AND 
                                (CASE
                                    WHEN latest_status.reservation_status_status_id = 10
                                         AND r.reschedule_start_date IS NOT NULL AND r.reschedule_end_date IS NOT NULL
                                    THEN r.reschedule_end_date ELSE r.reservation_end_date END)
                        )
                    )";
                    
                    // Insert date filter before UNION ALL
                    $sql1 = str_replace('WHERE cv.ven_id IN', $dateFilter . ' WHERE cv.ven_id IN', 
                             str_replace('WHERE v.ven_id IN', $dateFilter . ' WHERE v.ven_id IN', $sql1));
                }
                
                $stmt = $this->conn->prepare($sql1);
                
                if ($startDateTime && $endDateTime) {
                    $params = array_merge($itemIds, [$startDateTime, $endDateTime, $startDateTime, $endDateTime], 
                                         $itemIds, [$startDateTime, $endDateTime, $startDateTime, $endDateTime],
                                         $itemIds, [$startDateTime, $endDateTime, $startDateTime, $endDateTime]);
                    $stmt->execute($params);
                } else {
                    $params = array_merge($itemIds, $itemIds, $itemIds);
                    $stmt->execute($params);
                }
    
                $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
                foreach ($results as &$result) {
                    $isRescheduled = ((int)$result['reservation_status_status_id'] === 10);
                    $isReserved = ((int)$result['reservation_status_status_id'] === 6);
                    $hasActiveReschedule = (!empty($result['has_active_reschedule']) && (int)$result['has_active_reschedule'] === 1);
                    $venueType = $result['venue_type'];
                    
                    // For rescheduled status (10) OR reserved with active reschedule, use reschedule dates for change venues
                    if (($isRescheduled || ($isReserved && $hasActiveReschedule)) && $venueType === 'change') {
                        if (!empty($result['reschedule_start_date']) && !empty($result['reschedule_end_date'])) {
                            $result['reservation_start_date'] = $result['reschedule_start_date'];
                            $result['reservation_end_date'] = $result['reschedule_end_date'];
                        }
                    }
                    
                    // Clean up helper fields
                    unset($result['reschedule_start_date']);
                    unset($result['reschedule_end_date']);
                    unset($result['venue_type']);
                    unset($result['has_active_reschedule']);
                    unset($result['reservation_change_venue_id']);
                    
                    // Set availability based on status
                    $result['is_available'] = ($result['reservation_status_status_id'] !== 6);
                    
                    // Set reservation status text
                    switch ($result['reservation_status_status_id']) {
                        case 1:
                            $result['reservation_status'] = 'Pending';
                            break;
                        case 6:
                            $result['reservation_status'] = 'Reserved';
                            break;
                        case 8:
                            $result['reservation_status'] = 'Pending Department Approval';
                            break;
                        case 10:
                            $result['reservation_status'] = 'Rescheduled';
                            break;
                        default:
                            $result['reservation_status'] = 'Available';
                    }
                }
    
            } elseif ($itemType === 'vehicle') {
                // First get original vehicle reservations
                $sql2 = "
                    SELECT
                        v.vehicle_id,
                        v.vehicle_license,
                        vm.vehicle_make_name,
                        vmd.vehicle_model_name,
                        r.reservation_id,
                        r.reservation_title,
                        r.reservation_user_id,
                        ul.user_level_name AS user_level_name,
                        d.departments_name AS department_name,
                        r.reservation_start_date,
                        r.reservation_end_date,
                        r.reschedule_start_date,
                        r.reschedule_end_date,
                        latest_status.reservation_status_status_id,
                        latest_status.reservation_active,
                        CASE WHEN active_resched.max_reschedule_status_id IS NULL THEN 0 ELSE 1 END AS has_active_reschedule,
                        rv.reservation_change_vehicle_id,
                        'original' AS vehicle_type,
                        (
                            SELECT COUNT(DISTINCT u2.users_id)
                            FROM tbl_users u2
                            WHERE u2.users_user_level_id = 19
                                AND u2.is_active = 1
                        ) AS available_drivers_count
                    FROM tbl_vehicle v
                    LEFT JOIN tbl_vehicle_model vmd
                        ON v.vehicle_model_id = vmd.vehicle_model_id
                    LEFT JOIN tbl_vehicle_make vm
                        ON vmd.vehicle_model_vehicle_make_id = vm.vehicle_make_id
                    INNER JOIN tbl_reservation_vehicle rv
                        ON v.vehicle_id = rv.reservation_vehicle_vehicle_id
                    INNER JOIN tbl_reservation r
                        ON rv.reservation_reservation_id = r.reservation_id
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
                    ) latest_status
                        ON latest_status.reservation_reservation_id = r.reservation_id
                    LEFT JOIN (
                        SELECT reservation_reservation_id, MAX(reservation_status_id) AS max_reschedule_status_id
                        FROM tbl_reservation_status
                        WHERE reservation_status_status_id = 10 AND reservation_active = 1
                        GROUP BY reservation_reservation_id
                    ) active_resched ON active_resched.reservation_reservation_id = r.reservation_id
                    LEFT JOIN tbl_users u ON r.reservation_user_id = u.users_id
                    LEFT JOIN tbl_user_level ul ON u.users_user_level_id = ul.user_level_id
                    LEFT JOIN tbl_departments d ON u.users_department_id = d.departments_id
                    WHERE v.vehicle_id IN ($placeholders)
                      AND r.reservation_id NOT IN (
                          SELECT DISTINCT reservation_reservation_id 
                          FROM tbl_reservation_status 
                          WHERE reservation_status_status_id IN (2, 5)
                      )
                      AND NOT (latest_status.reservation_status_status_id = 6 AND active_resched.max_reschedule_status_id IS NOT NULL)
                    
                    UNION ALL
                    
                    SELECT
                        v.vehicle_id,
                        v.vehicle_license,
                        vm.vehicle_make_name,
                        vmd.vehicle_model_name,
                        r.reservation_id,
                        r.reservation_title,
                        r.reservation_user_id,
                        ul.user_level_name AS user_level_name,
                        d.departments_name AS department_name,
                        r.reschedule_start_date AS reservation_start_date,
                        r.reschedule_end_date AS reservation_end_date,
                        r.reschedule_start_date,
                        r.reschedule_end_date,
                        latest_status.reservation_status_status_id,
                        latest_status.reservation_active,
                        CASE WHEN active_resched.max_reschedule_status_id IS NULL THEN 0 ELSE 1 END AS has_active_reschedule,
                        rv.reservation_change_vehicle_id,
                        'reschedule_original' AS vehicle_type,
                        (
                            SELECT COUNT(DISTINCT u2.users_id)
                            FROM tbl_users u2
                            WHERE u2.users_user_level_id = 19
                                AND u2.is_active = 1
                        ) AS available_drivers_count
                    FROM tbl_vehicle v
                    LEFT JOIN tbl_vehicle_model vmd
                        ON v.vehicle_model_id = vmd.vehicle_model_id
                    LEFT JOIN tbl_vehicle_make vm
                        ON vmd.vehicle_model_vehicle_make_id = vm.vehicle_make_id
                    INNER JOIN tbl_reservation_vehicle rv
                        ON v.vehicle_id = rv.reservation_vehicle_vehicle_id
                    INNER JOIN tbl_reservation r
                        ON rv.reservation_reservation_id = r.reservation_id
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
                    ) latest_status
                        ON latest_status.reservation_reservation_id = r.reservation_id
                    LEFT JOIN (
                        SELECT reservation_reservation_id, MAX(reservation_status_id) AS max_reschedule_status_id
                        FROM tbl_reservation_status
                        WHERE reservation_status_status_id = 10 AND reservation_active = 1
                        GROUP BY reservation_reservation_id
                    ) active_resched ON active_resched.reservation_reservation_id = r.reservation_id
                    LEFT JOIN tbl_users u ON r.reservation_user_id = u.users_id
                    LEFT JOIN tbl_user_level ul ON u.users_user_level_id = ul.user_level_id
                    LEFT JOIN tbl_departments d ON u.users_department_id = d.departments_id
                    WHERE v.vehicle_id IN ($placeholders)
                      AND latest_status.reservation_status_status_id IN (6, 10)
                      AND rv.reservation_change_vehicle_id IS NULL
                      AND r.reschedule_start_date IS NOT NULL
                      AND r.reschedule_end_date IS NOT NULL
                      AND r.reservation_id NOT IN (
                          SELECT DISTINCT reservation_reservation_id 
                          FROM tbl_reservation_status 
                          WHERE reservation_status_status_id IN (2, 5)
                      )
                    
                    UNION ALL
                    
                    SELECT
                        cv.vehicle_id,
                        cv.vehicle_license,
                        cvmk.vehicle_make_name,
                        cvmd.vehicle_model_name,
                        r.reservation_id,
                        r.reservation_title,
                        r.reservation_user_id,
                        ul.user_level_name AS user_level_name,
                        d.departments_name AS department_name,
                        r.reservation_start_date,
                        r.reservation_end_date,
                        r.reschedule_start_date,
                        r.reschedule_end_date,
                        latest_status.reservation_status_status_id,
                        latest_status.reservation_active,
                        CASE WHEN active_resched.max_reschedule_status_id IS NULL THEN 0 ELSE 1 END AS has_active_reschedule,
                        rv.reservation_change_vehicle_id,
                        'change' AS vehicle_type,
                        (
                            SELECT COUNT(DISTINCT u2.users_id)
                            FROM tbl_users u2
                            WHERE u2.users_user_level_id = 19
                                AND u2.is_active = 1
                        ) AS available_drivers_count
                    FROM tbl_vehicle cv
                    LEFT JOIN tbl_vehicle_model cvmd
                        ON cv.vehicle_model_id = cvmd.vehicle_model_id
                    LEFT JOIN tbl_vehicle_make cvmk
                        ON cvmd.vehicle_model_vehicle_make_id = cvmk.vehicle_make_id
                    INNER JOIN tbl_reservation_vehicle rv
                        ON cv.vehicle_id = rv.reservation_change_vehicle_id
                    INNER JOIN tbl_reservation r
                        ON rv.reservation_reservation_id = r.reservation_id
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
                    ) latest_status
                        ON latest_status.reservation_reservation_id = r.reservation_id
                    LEFT JOIN (
                        SELECT reservation_reservation_id, MAX(reservation_status_id) AS max_reschedule_status_id
                        FROM tbl_reservation_status
                        WHERE reservation_status_status_id = 10 AND reservation_active = 1
                        GROUP BY reservation_reservation_id
                    ) active_resched ON active_resched.reservation_reservation_id = r.reservation_id
                    LEFT JOIN tbl_users u ON r.reservation_user_id = u.users_id
                    LEFT JOIN tbl_user_level ul ON u.users_user_level_id = ul.user_level_id
                    LEFT JOIN tbl_departments d ON u.users_department_id = d.departments_id
                    WHERE cv.vehicle_id IN ($placeholders)
                      AND rv.reservation_change_vehicle_id IS NOT NULL
                      AND r.reservation_id NOT IN (
                          SELECT DISTINCT reservation_reservation_id 
                          FROM tbl_reservation_status 
                          WHERE reservation_status_status_id IN (2, 5)
                      )
                ";
                
                $stmt = $this->conn->prepare($sql2);
                $params = array_merge($itemIds, $itemIds, $itemIds);
                $stmt->execute($params);
    
                $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
                foreach ($results as &$result) {
                    $isRescheduled = ((int)$result['reservation_status_status_id'] === 10);
                    $isReserved = ((int)$result['reservation_status_status_id'] === 6);
                    $hasActiveReschedule = (!empty($result['has_active_reschedule']) && (int)$result['has_active_reschedule'] === 1);
                    $vehicleType = $result['vehicle_type'];
                    
                    // For rescheduled status (10) OR reserved with active reschedule, use reschedule dates for change vehicles
                    if (($isRescheduled || ($isReserved && $hasActiveReschedule)) && $vehicleType === 'change') {
                        if (!empty($result['reschedule_start_date']) && !empty($result['reschedule_end_date'])) {
                            $result['reservation_start_date'] = $result['reschedule_start_date'];
                            $result['reservation_end_date'] = $result['reschedule_end_date'];
                        }
                    }
                    
                    // Clean up helper fields
                    unset($result['reschedule_start_date']);
                    unset($result['reschedule_end_date']);
                    unset($result['vehicle_type']);
                    unset($result['has_active_reschedule']);
                    unset($result['reservation_change_vehicle_id']);
                    
                    // Set availability based on status
                    $result['is_available'] = ($result['reservation_status_status_id'] !== 6);
                    
                    // Set reservation status text
                    switch ($result['reservation_status_status_id']) {
                        case 1:
                            $result['reservation_status'] = 'Pending';
                            break;
                        case 6:
                            $result['reservation_status'] = 'Reserved';
                            break;
                        case 8:
                            $result['reservation_status'] = 'Pending Department Approval';
                            break;
                        case 10:
                            $result['reservation_status'] = 'Rescheduled';
                            break;
                        default:
                            $result['reservation_status'] = 'Available';
                    }
                }
            } elseif ($itemType === 'equipment') {
                // Remove the complex status filtering from the WHERE clause
                $sql = "
                    SELECT
                        e.equip_id,
                        e.equip_name,
                        e.equip_type,
                        tec.equipments_category_name AS category_name,
                        CASE
                            WHEN e.equip_type = 'Bulk' THEN COALESCE(eq.quantity, 0)
                            ELSE COALESCE(eu.unit_count, 0)
                        END AS current_quantity,
                        COALESCE(re.reservation_equipment_quantity, 0) AS reserved_quantity,
                        r.reservation_user_id,
                        ul.user_level_name AS user_level_name,
                        d.departments_name AS department_name,
                        r.reservation_start_date,
                        r.reservation_end_date,
                        r.reschedule_start_date,
                        r.reschedule_end_date,
                        latest_status.reservation_status_status_id,
                        latest_status.reservation_active,
                        CASE WHEN active_resched.max_reschedule_status_id IS NULL THEN 0 ELSE 1 END AS has_active_reschedule,
                        r.reservation_id,
                        e.equip_created_at,
                        e.equipments_category_id
                    FROM tbl_equipments e
                    LEFT JOIN tbl_equipment_category tec ON e.equipments_category_id = tec.equipments_category_id
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
                    ) AS eq ON e.equip_id = eq.equip_id AND e.equip_type = 'Bulk'
                    LEFT JOIN (
                        SELECT
                            equip_id,
                            COUNT(*) AS unit_count
                        FROM tbl_equipment_unit
                        WHERE is_active = 1
                        GROUP BY equip_id
                    ) AS eu ON e.equip_id = eu.equip_id AND e.equip_type != 'Bulk'
                    LEFT JOIN tbl_reservation_equipment re ON e.equip_id = re.reservation_equipment_equip_id
                    LEFT JOIN tbl_reservation r ON re.reservation_reservation_id = r.reservation_id
                    LEFT JOIN tbl_users u ON r.reservation_user_id = u.users_id
                    LEFT JOIN tbl_user_level ul ON u.users_user_level_id = ul.user_level_id
                    LEFT JOIN tbl_departments d ON u.users_department_id = d.departments_id
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
                    LEFT JOIN (
                        SELECT reservation_reservation_id, MAX(reservation_status_id) AS max_reschedule_status_id
                        FROM tbl_reservation_status
                        WHERE reservation_status_status_id = 10 AND reservation_active = 1
                        GROUP BY reservation_reservation_id
                    ) active_resched ON active_resched.reservation_reservation_id = r.reservation_id
                    WHERE e.equip_id IN ($placeholders)
                      AND r.reservation_id NOT IN (
                          SELECT DISTINCT reservation_reservation_id 
                          FROM tbl_reservation_status 
                          WHERE reservation_status_status_id IN (2, 5)
                      )
                ";
                $stmt = $this->conn->prepare($sql);
                $stmt->execute($itemIds);
                $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
                foreach ($results as &$result) {
                    $eid = $result['equip_id'];
                    $result['inputted_quantity'] = isset($inputQuantities[$eid])
                        ? (int)$inputQuantities[$eid]
                        : 0;
                    $result['is_available'] = ($result['current_quantity'] - $result['reserved_quantity']) > 0;
                    $result['availability_status'] = ($result['current_quantity'] - $result['reserved_quantity']) > 0 ? 'Available' : 'Unavailable';
                    $result['total_available'] = $result['current_quantity'] - $result['reserved_quantity'];
                    
                    // Use reschedule dates when an active reschedule exists (status 10, active 1)
                    if (((int)$result['reservation_status_status_id'] === 10 && (int)$result['reservation_active'] === 1)
                        || (!empty($result['has_active_reschedule']) && (int)$result['has_active_reschedule'] === 1)) {
                        if (!empty($result['reschedule_start_date']) && !empty($result['reschedule_end_date'])) {
                            $result['reservation_start_date'] = $result['reschedule_start_date'];
                            $result['reservation_end_date'] = $result['reschedule_end_date'];
                        }
                    }
                    // Do not expose reschedule_* keys; keep only reservation_* naming
                    if (array_key_exists('reschedule_start_date', $result)) {
                        unset($result['reschedule_start_date']);
                    }
                    if (array_key_exists('reschedule_end_date', $result)) {
                        unset($result['reschedule_end_date']);
                    }
                    if (array_key_exists('has_active_reschedule', $result)) {
                        unset($result['has_active_reschedule']);
                    }
                    
                    // Set reservation status text
                    switch ($result['reservation_status_status_id']) {
                        case 1:
                            $result['reservation_status'] = 'Pending';
                            break;
                        case 6:
                            $result['reservation_status'] = 'Reserved';
                            break;
                        case 10:
                            $result['reservation_status'] = 'Rescheduled';
                            break;
                        default:
                            $result['reservation_status'] = 'Available';
                    }
                }
            }
    
            return json_encode([
                'status' => 'success',
                'data' => $results
            ]);
        } catch (PDOException $e) {
            error_log("Database error in fetchAvailability: " . $e->getMessage());
            return json_encode([
                'status' => 'error',
                'message' => 'Database error: ' . $e->getMessage()
            ]);
        } catch (Exception $e) {
            error_log("General error in fetchAvailability: " . $e->getMessage());
            return json_encode([
                'status' => 'error',
                'message' => 'An unexpected error occurred: ' . $e->getMessage()
            ]);
        }
    }
    

    

    public function fetchDriver($startDateTime = null, $endDateTime = null) {
        try {
            // Updated query to get all users with user_level_id = 19 (Driver)
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
                $sql .= " ORDER BY u.users_lname, u.users_fname";
                return $this->executeQuery($sql);
            }
    
            // Get user IDs of drivers already reserved during the period
            $sqlCheckUserReservation = "
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
                LEFT JOIN (
                    SELECT reservation_reservation_id, MAX(reservation_status_id) AS max_reschedule_status_id
                    FROM tbl_reservation_status
                    WHERE reservation_status_status_id = 10 AND reservation_active = 1
                    GROUP BY reservation_reservation_id
                ) active_resched ON active_resched.reservation_reservation_id = r.reservation_id
                WHERE 
                    (
                        latest_status.reservation_status_status_id IN (1, 6, 8, 10)
                        AND (
                            (latest_status.reservation_status_status_id = 6 AND latest_status.reservation_active = 1)
                            OR (latest_status.reservation_status_status_id IN (1, 8, 10) AND (latest_status.reservation_active = 0 OR latest_status.reservation_active = 1))
                        )
                    )
                    AND (
                        (
                            (CASE
                                WHEN (
                                        (latest_status.reservation_status_status_id = 10 AND latest_status.reservation_active = 1)
                                        OR (
                                            latest_status.reservation_status_status_id = 6 AND latest_status.reservation_active = 1
                                            AND active_resched.max_reschedule_status_id IS NOT NULL
                                        )
                                     )
                                     AND r.reschedule_start_date IS NOT NULL AND r.reschedule_end_date IS NOT NULL
                                THEN r.reschedule_start_date ELSE r.reservation_start_date END)
                            BETWEEN :startDateTime AND :endDateTime
                        )
                        OR (
                            (CASE
                                WHEN (
                                        (latest_status.reservation_status_status_id = 10 AND latest_status.reservation_active = 1)
                                        OR (
                                            latest_status.reservation_status_status_id = 6 AND latest_status.reservation_active = 1
                                            AND active_resched.max_reschedule_status_id IS NOT NULL
                                        )
                                     )
                                     AND r.reschedule_start_date IS NOT NULL AND r.reschedule_end_date IS NOT NULL
                                THEN r.reschedule_end_date ELSE r.reservation_end_date END)
                            BETWEEN :startDateTime AND :endDateTime
                        )
                        OR (
                            :startDateTime BETWEEN 
                                (CASE
                                    WHEN (
                                            (latest_status.reservation_status_status_id = 10 AND latest_status.reservation_active = 1)
                                            OR (
                                                latest_status.reservation_status_status_id = 6 AND latest_status.reservation_active = 1
                                                AND active_resched.max_reschedule_status_id IS NOT NULL
                                            )
                                         )
                                         AND r.reschedule_start_date IS NOT NULL AND r.reschedule_end_date IS NOT NULL
                                    THEN r.reschedule_start_date ELSE r.reservation_start_date END)
                                AND 
                                (CASE
                                    WHEN (
                                            (latest_status.reservation_status_status_id = 10 AND latest_status.reservation_active = 1)
                                            OR (
                                                latest_status.reservation_status_status_id = 6 AND latest_status.reservation_active = 1
                                                AND active_resched.max_reschedule_status_id IS NOT NULL
                                            )
                                         )
                                         AND r.reschedule_start_date IS NOT NULL AND r.reschedule_end_date IS NOT NULL
                                    THEN r.reschedule_end_date ELSE r.reservation_end_date END)
                        )
                        OR (
                            :endDateTime BETWEEN 
                                (CASE
                                    WHEN (
                                            (latest_status.reservation_status_status_id = 10 AND latest_status.reservation_active = 1)
                                            OR (
                                                latest_status.reservation_status_status_id = 6 AND latest_status.reservation_active = 1
                                                AND active_resched.max_reschedule_status_id IS NOT NULL
                                            )
                                         )
                                         AND r.reschedule_start_date IS NOT NULL AND r.reschedule_end_date IS NOT NULL
                                    THEN r.reschedule_start_date ELSE r.reservation_start_date END)
                                AND 
                                (CASE
                                    WHEN (
                                            (latest_status.reservation_status_status_id = 10 AND latest_status.reservation_active = 1)
                                            OR (
                                                latest_status.reservation_status_status_id = 6 AND latest_status.reservation_active = 1
                                                AND active_resched.max_reschedule_status_id IS NOT NULL
                                            )
                                         )
                                         AND r.reschedule_start_date IS NOT NULL AND r.reschedule_end_date IS NOT NULL
                                    THEN r.reschedule_end_date ELSE r.reservation_end_date END)
                        )
                    )
            ";
    
            $stmt = $this->conn->prepare($sqlCheckUserReservation);
            $stmt->execute([
                ':startDateTime' => $startDateTime,
                ':endDateTime' => $endDateTime
            ]);
            $reservedUserIds = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
    
            // Exclude reserved drivers from main query
            if (!empty($reservedUserIds)) {
                $placeholders = implode(',', array_fill(0, count($reservedUserIds), '?'));
                $sql .= " AND u.users_id NOT IN ($placeholders)";
            }
    
            $sql .= " ORDER BY u.users_lname, u.users_fname";
    
            // Prepare final statement
            $stmt = $this->conn->prepare($sql);
            if (!empty($reservedUserIds)) {
                $stmt->execute($reservedUserIds);
            } else {
                $stmt->execute();
            }

            $drivers = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return json_encode(['status' => 'success', 'data' => $drivers]);
    
        } catch (PDOException $e) {
            return json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
        }
    }

        public function fetchAvailableDrivers() {
            try {
                $sql = "
                    SELECT 
                        u.users_id,
                        u.users_fname,
                        u.users_mname,
                        u.users_lname,
                        u.users_suffix,

                        u.title_id,
                        r.reservation_id,
                        r.reservation_title,
                        r.reservation_start_date,
                        r.reservation_end_date,
                        rs.reservation_status_status_id,
                        rs.reservation_active,
                        CASE 
                            WHEN rs.reservation_status_status_id = 6 AND rs.reservation_active = 1 THEN 'Reserved'
                            ELSE 'Available'
                        END AS availability_status,
                        CASE 
                            WHEN rs.reservation_status_status_id = 6 AND rs.reservation_active = 1 THEN 0
                            ELSE 1
                        END AS is_available
                    FROM 
                        tbl_users u
                    LEFT JOIN tbl_reservation_driver rd ON u.users_id = rd.reservation_driver_user_id
                    LEFT JOIN tbl_reservation_vehicle rv ON rd.reservation_vehicle_id = rv.reservation_vehicle_id
                    LEFT JOIN tbl_reservation r ON rv.reservation_reservation_id = r.reservation_id
                    LEFT JOIN tbl_reservation_status rs ON r.reservation_id = rs.reservation_reservation_id
                    WHERE 
                        u.users_user_level_id = 19
                        AND u.is_active = 1
                    ORDER BY 
                        u.users_lname, u.users_fname, r.reservation_start_date DESC
                ";

                $stmt = $this->conn->prepare($sql);
                $stmt->execute();
                $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

                // Group drivers by user_id to handle multiple reservations
                $drivers = [];
                foreach ($results as $row) {
                    $userId = $row['users_id'];
                    
                    if (!isset($drivers[$userId])) {
                        // Initialize driver data
                        $drivers[$userId] = [
                            'users_id' => $row['users_id'],
                            'users_fname' => $row['users_fname'],
                            'users_mname' => $row['users_mname'],
                            'users_lname' => $row['users_lname'],
                            'users_suffix' => $row['users_suffix'],
                            'title_id' => $row['title_id'],
                            'availability_status' => $row['availability_status'],
                            'is_available' => $row['is_available'],
                            'reservations' => []
                        ];
                    }

                    // Add reservation data if exists and matches criteria
                    if ($row['reservation_id'] && $row['reservation_status_status_id'] == 6 && $row['reservation_active'] == 1) {
                        $drivers[$userId]['reservations'][] = [
                            'reservation_id' => $row['reservation_id'],
                            'reservation_title' => $row['reservation_title'],
                            'reservation_start_date' => $row['reservation_start_date'],
                            'reservation_end_date' => $row['reservation_end_date'],
                            'reservation_status_status_id' => $row['reservation_status_status_id'],
                            'reservation_active' => $row['reservation_active']
                        ];
                    }
                }

                return json_encode(['status' => 'success', 'data' => array_values($drivers)]);

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
            WHERE (
                CASE 
                    WHEN EXISTS (
                        SELECT 1
                          FROM tbl_reservation_status rs2
                         WHERE rs2.reservation_reservation_id = r.reservation_id
                           AND rs2.reservation_status_status_id IN (6, 10)
                           AND rs2.reservation_active = 1
                    )
                     AND rv.reservation_change_vehicle_id IS NOT NULL
                     AND rv.reservation_change_vehicle_id > 0
                    THEN rv.reservation_change_vehicle_id
                    ELSE rv.reservation_vehicle_vehicle_id
                END
            ) = :vehicleId
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
            WHERE (
                CASE 
                    WHEN EXISTS (
                        SELECT 1 
                          FROM tbl_reservation_status rs2 
                         WHERE rs2.reservation_reservation_id = r.reservation_id 
                           AND rs2.reservation_status_status_id = 10 
                           AND rs2.reservation_active = 1
                    )
                     AND rv.reservation_change_venue_id IS NOT NULL
                     AND rv.reservation_change_venue_id > 0
                    THEN rv.reservation_change_venue_id
                    ELSE rv.reservation_venue_venue_id
                END
            ) = :venueId
            ORDER BY r.reservation_start_date DESC
        ";

        $stmt = $this->conn->prepare($reservationsSql);
        $stmt->execute([':venueId' => $venueId]);
        $reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Count conditions
        $totalUsage = count($reservations);
        $brokenCount = 0;
        $inspectionCount = 0;
        
        $missingCount = 0;

        foreach ($reservations as $reservation) {
            if ($reservation['condition_id'] == 4) {
                $brokenCount++;
            } elseif ($reservation['condition_id'] == 3) {
                $missingCount++;
            } elseif ($reservation['condition_id'] == 7) {
                $inspectionCount++;
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
                    'inspection_count' => $inspectionCount,
                    'missing_count' => $missingCount,
                    'good_condition_count' => $totalUsage - ($brokenCount + $missingCount + $inspectionCount)
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

public function getBulkUsage($equipId) {
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
            return json_encode(['status' => 'error', 'message' => 'Bulk equipment not found']);
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
        error_log('[fetchAllAssignedReleases] start');
        $reservations = [];

        // 1) Venue checklist items
        $sqlVenue = "
            SELECT
                rc_venue.checklist_venue_id,
                rc_venue.reservation_checklist_venue_id,
                rc_venue.isChecked AS venue_isChecked,
                rc_venue.personnel_id AS venue_personnel_id,
                rc_venue.admin_id AS venue_admin_id,
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
              AND rs.reservation_active IN (0, 1)
            ORDER BY rv.reservation_reservation_id DESC
        ";
        $stmtVenue = $this->conn->query($sqlVenue);
        if ($stmtVenue === false) {
            $ei = $this->conn->errorInfo();
            throw new PDOException('Venue query failed: ' . ($ei[2] ?? 'unknown error'));
        }
        $venueData = $stmtVenue->fetchAll(PDO::FETCH_ASSOC);
        error_log('[fetchAllAssignedReleases] venue rows=' . count($venueData));

        // 2) Vehicle checklist items
        $sqlVehicle = "
            SELECT
                rc_vehicle.checklist_vehicle_id,
                rc_vehicle.reservation_checklist_vehicle_id,
                rc_vehicle.isChecked AS vehicle_isChecked,
                rc_vehicle.personnel_id AS vehicle_personnel_id,
                rc_vehicle.admin_id AS vehicle_admin_id,
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
              AND rs.reservation_active IN (0, 1)
            ORDER BY rv.reservation_reservation_id DESC
        ";
        $stmtVehicle = $this->conn->query($sqlVehicle);
        if ($stmtVehicle === false) {
            $ei = $this->conn->errorInfo();
            throw new PDOException('Vehicle query failed: ' . ($ei[2] ?? 'unknown error'));
        }
        $vehicleData = $stmtVehicle->fetchAll(PDO::FETCH_ASSOC);
        error_log('[fetchAllAssignedReleases] vehicle rows=' . count($vehicleData));

        // 3) Equipment checklist items
        $sqlEquipment = "
            SELECT  
                rc_equipment.checklist_equipment_id,
                rc_equipment.reservation_checklist_equipment_id,
                rc_equipment.isChecked AS equipment_isChecked,
                rc_equipment.personnel_id AS equipment_personnel_id,
                rc_equipment.admin_id AS equipment_admin_id,
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
              AND rs.reservation_active IN (0, 1)
            ORDER BY re.reservation_reservation_id DESC
        ";
        $stmtEquipment = $this->conn->query($sqlEquipment);
        if ($stmtEquipment === false) {
            $ei = $this->conn->errorInfo();
            throw new PDOException('Equipment query failed: ' . ($ei[2] ?? 'unknown error'));
        }
        $equipmentData = $stmtEquipment->fetchAll(PDO::FETCH_ASSOC);
        error_log('[fetchAllAssignedReleases] equipment rows=' . count($equipmentData));

        // Collect all relevant user IDs (personnel and admins) from checklist items
        $userIds = [];
        foreach (array_merge($venueData, $vehicleData, $equipmentData) as $row) {
            if (!empty($row['venue_personnel_id'])) { $userIds[] = $row['venue_personnel_id']; }
            if (!empty($row['vehicle_personnel_id'])) { $userIds[] = $row['vehicle_personnel_id']; }
            if (!empty($row['equipment_personnel_id'])) { $userIds[] = $row['equipment_personnel_id']; }
            if (!empty($row['venue_admin_id'])) { $userIds[] = $row['venue_admin_id']; }
            if (!empty($row['vehicle_admin_id'])) { $userIds[] = $row['vehicle_admin_id']; }
            if (!empty($row['equipment_admin_id'])) { $userIds[] = $row['equipment_admin_id']; }
        }

        //  Remove duplicates and empty values, then reindex for positional params
        $userIds = array_values(array_unique(array_filter($userIds)));
        error_log('[fetchAllAssignedReleases] unique userIds count=' . count($userIds));

        // Fetch names in a single query
        $userNames = [];
        if (!empty($userIds)) {
            $placeholders = implode(',', array_fill(0, count($userIds), '?'));
            $sqlUsers = "
                SELECT 
                    u.users_id,
                    TRIM(CONCAT(
                        u.users_fname, ' ',
                        CASE 
                            WHEN u.users_mname IS NULL OR u.users_mname = '' THEN '' 
                            ELSE CONCAT(SUBSTRING(u.users_mname, 1, 1), '. ')
                        END,
                        u.users_lname
                    )) AS full_name
                FROM tbl_users u
                WHERE u.users_id IN ($placeholders)
            ";
            $stmtUsers = $this->conn->prepare($sqlUsers);
            $stmtUsers->execute($userIds);
            $userData = $stmtUsers->fetchAll(PDO::FETCH_ASSOC);

            foreach ($userData as $person) {
                $userNames[$person['users_id']] = $person['full_name'];
            }
            error_log('[fetchAllAssignedReleases] fetched user names=' . count($userData));
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
                            'personnel_name'                 => $userNames[$row['venue_personnel_id']] ?? 'N/A',
                            'admin_id'                       => $row['venue_admin_id'],
                            'admin_name'                     => $userNames[$row['venue_admin_id']] ?? 'N/A'
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
                            'personnel_name'                 => $userNames[$row['venue_personnel_id']] ?? 'N/A',
                            'admin_id'                       => $row['venue_admin_id'],
                            'admin_name'                     => $userNames[$row['venue_admin_id']] ?? 'N/A'
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
                            'personnel_name'                   => $userNames[$row['vehicle_personnel_id']] ?? 'N/A',
                            'admin_id'                         => $row['vehicle_admin_id'],
                            'admin_name'                       => $userNames[$row['vehicle_admin_id']] ?? 'N/A'
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
                            'personnel_name'                   => $userNames[$row['vehicle_personnel_id']] ?? 'N/A',
                            'admin_id'                         => $row['vehicle_admin_id'],
                            'admin_name'                       => $userNames[$row['vehicle_admin_id']] ?? 'N/A'
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
                            'personnel_name'                     => $userNames[$row['equipment_personnel_id']] ?? 'N/A',
                            'admin_id'                           => $row['equipment_admin_id'],
                            'admin_name'                         => $userNames[$row['equipment_admin_id']] ?? 'N/A'
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
                            'personnel_name'                     => $userNames[$row['equipment_personnel_id']] ?? 'N/A',
                            'admin_id'                           => $row['equipment_admin_id'],
                            'admin_name'                         => $userNames[$row['equipment_admin_id']] ?? 'N/A'
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
            error_log('[fetchAllAssignedReleases] units rows=' . count($unitsData));

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
            if (!$st->execute(['rid' => $rid])) {
                $ei = $st->errorInfo();
                throw new PDOException('Header query failed for reservation ' . $rid . ': ' . ($ei[2] ?? 'unknown error'));
            }
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
        error_log('[fetchAllAssignedReleases] success reservations=' . count($reservations) . ' filtered=' . count($filteredReservations));
        return json_encode([
            'status' => 'success',
            'data'   => array_values($filteredReservations)
        ]);

    } catch (PDOException $e) {
        error_log("Error in fetchAllAssignedReleases: " . $e->getMessage());
        return json_encode([
            'status' => 'error',
            'message' => 'An error occurred while fetching assigned releases.'
        ]);
    } catch (Exception $e) {
        error_log("Unexpected error in fetchAllAssignedReleases: " . $e->getMessage());
        return json_encode([
            'status' => 'error',
            'message' => 'An error occurred while fetching assigned releases.'
        ]);
    }
}

public function fetchDoneAssignedReleases() {
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
              AND rs.reservation_active = 0
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
              AND rs.reservation_active = 0
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
              AND rs.reservation_active = 0
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
        error_log("Error in fetchDoneAssignedReleases: " . $e->getMessage());
        return json_encode([
            'status' => 'error',
            'message' => 'An error occurred while fetching done releases.'
        ]);
    }
}

public function displayedMaintenanceResourcesDone() {
    try {
        $records = [];

        // 1) Equipment (Bulk) under maintenance — display qty_bad
        $sql = "
            SELECT 
                rce.id                             AS record_id,
                'equipment_bulk'                  AS resource_type,
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

        // 4) Equipment units (Serialized) under maintenance
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
            if (empty($data[$field])) {
                return json_encode(['status' => 'error', 'message' => "$field is required or invalid"]);
            }
        }
        // Numeric validation for specific fields
        if (!is_numeric($data['equipments_category_id'])) {
            return json_encode(['status' => 'error', 'message' => 'equipments_category_id must be numeric']);
        }
        if (!is_numeric($data['user_admin_id'])) {
            return json_encode(['status' => 'error', 'message' => 'user_admin_id must be numeric']);
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

public function updateVenueReschedule($reservation_venue_id, $reservation_change_venue_id) {
    try {
        if ($reservation_venue_id === null || $reservation_change_venue_id === null) {
            return json_encode(['status' => 'error', 'message' => 'Missing required parameters']);
        }

        $sql = "UPDATE tbl_reservation_venue
                SET reservation_change_venue_id = :reservation_change_venue_id
                WHERE reservation_venue_id = :reservation_venue_id";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':reservation_change_venue_id', (int)$reservation_change_venue_id, PDO::PARAM_INT);
        $stmt->bindValue(':reservation_venue_id', (int)$reservation_venue_id, PDO::PARAM_INT);

        $ok = $stmt->execute();
        if ($ok) {
            return json_encode(['status' => 'success', 'message' => 'Venue reschedule updated successfully']);
        }
        return json_encode(['status' => 'error', 'message' => 'Failed to update venue reschedule']);
    } catch (PDOException $e) {
        error_log('Database error in updateVenueReschedule: ' . $e->getMessage());
        return json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

public function updateVehicleReschedule($reservation_vehicle_id, $reservation_change_vehicle_id) {
    try {
        if ($reservation_vehicle_id === null || $reservation_change_vehicle_id === null) {
            return json_encode(['status' => 'error', 'message' => 'Missing required parameters']);
        }

        $sql = "UPDATE tbl_reservation_vehicle
                SET reservation_change_vehicle_id = :reservation_change_vehicle_id
                WHERE reservation_vehicle_id = :reservation_vehicle_id";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':reservation_change_vehicle_id', (int)$reservation_change_vehicle_id, PDO::PARAM_INT);
        $stmt->bindValue(':reservation_vehicle_id', (int)$reservation_vehicle_id, PDO::PARAM_INT);

        $ok = $stmt->execute();
        if ($ok) {
            return json_encode(['status' => 'success', 'message' => 'Vehicle reschedule updated successfully']);
        }
        return json_encode(['status' => 'error', 'message' => 'Failed to update vehicle reschedule']);
    } catch (PDOException $e) {
        error_log('Database error in updateVehicleReschedule: ' . $e->getMessage());
        return json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

public function updateReservationReschedule($reservation_id, $reschedule_start_date, $reschedule_end_date, $user_admin_id = null) {
    try {
        if ($reservation_id === null || $reschedule_start_date === null || $reschedule_end_date === null) {
            return json_encode(['status' => 'error', 'message' => 'Missing required parameters']);
        }

        $this->conn->beginTransaction();

        // 1) Update reschedule dates on reservation
        $sql = "UPDATE tbl_reservation
                SET reschedule_start_date = :reschedule_start_date,
                    reschedule_end_date = :reschedule_end_date
                WHERE reservation_id = :reservation_id";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':reschedule_start_date', $reschedule_start_date, PDO::PARAM_STR);
        $stmt->bindValue(':reschedule_end_date', $reschedule_end_date, PDO::PARAM_STR);
        $stmt->bindValue(':reservation_id', (int)$reservation_id, PDO::PARAM_INT);
        $stmt->execute();

        // 2) Update status ID 8 to active (reservation_active = 1) for this reservation
        $updateStatusSql = "UPDATE tbl_reservation_status 
                           SET reservation_active = 1, 
                               reservation_updated_at = NOW(),
                               reservation_users_id = :user_admin_id
                           WHERE reservation_reservation_id = :reservation_id 
                           AND reservation_status_status_id = 8";
        $updateStmt = $this->conn->prepare($updateStatusSql);
        $updateStmt->bindValue(':reservation_id', (int)$reservation_id, PDO::PARAM_INT);
        if ($user_admin_id === null || $user_admin_id === '') {
            $updateStmt->bindValue(':user_admin_id', null, PDO::PARAM_NULL);
        } else {
            $updateStmt->bindValue(':user_admin_id', (int)$user_admin_id, PDO::PARAM_INT);
        }
        $updateStmt->execute();

        // 3) Insert a new status row with reservation_active = 0 and fixed status id 10
        $ins = $this->conn->prepare("INSERT INTO tbl_reservation_status
            (reservation_status_status_id, reservation_reservation_id, reservation_active, reservation_updated_at, reservation_users_id)
            VALUES (:status_id, :reservation_id, 0, NOW(), :user_admin_id)");
        $ins->bindValue(':status_id', 10, PDO::PARAM_INT);
        $ins->bindValue(':reservation_id', (int)$reservation_id, PDO::PARAM_INT);
        if ($user_admin_id === null || $user_admin_id === '') {
            $ins->bindValue(':user_admin_id', null, PDO::PARAM_NULL);
        } else {
            $ins->bindValue(':user_admin_id', (int)$user_admin_id, PDO::PARAM_INT);
        }
        $ins->execute();

        $this->conn->commit();

        return json_encode(['status' => 'success', 'message' => 'Reservation reschedule updated successfully']);
    } catch (PDOException $e) {
        if ($this->conn->inTransaction()) {
            $this->conn->rollBack();
        }
        error_log('Database error in updateReservationReschedule: ' . $e->getMessage());
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

    public function fetchRequestReservation() {
        try {
            $sql = "
                SELECT 
                    r.reservation_id, 
                    r.reservation_created_at, 
                    r.reservation_title,
                    r.reservation_description,
                    r.reservation_start_date,
                    r.reservation_end_date,
                    r.reservation_participants,
                    r.reservation_user_id AS requester_id,
                    CONCAT_WS(' ', u.users_fname, u.users_mname, u.users_lname) AS requester_name,
                    d.departments_name,
                    rs.reservation_status_status_id AS status_id,
                    rs.reservation_active AS active,
                    sm.status_master_name AS reservation_status
                FROM 
                    tbl_reservation r
                LEFT JOIN 
                    tbl_users u ON r.reservation_user_id = u.users_id
                LEFT JOIN 
                    tbl_departments d ON u.users_department_id = d.departments_id
                LEFT JOIN 
                    tbl_reservation_status rs ON r.reservation_id = rs.reservation_reservation_id
                LEFT JOIN 
                    tbl_status_master sm ON rs.reservation_status_status_id = sm.status_master_id
                WHERE
    
                    (
                        (rs.reservation_status_status_id IN (1, 8) AND rs.reservation_active IN (0, 1))
                        AND NOT EXISTS (
                            SELECT 1 
                            FROM tbl_reservation_status rs2 
                            WHERE rs2.reservation_reservation_id = r.reservation_id 
                            AND rs2.reservation_status_status_id = 6
                        )
                        AND NOT EXISTS (
                            SELECT 1
                            FROM tbl_reservation_status rs3
                            WHERE rs3.reservation_reservation_id = r.reservation_id
                            AND rs3.reservation_status_status_id = 2
                            AND rs3.reservation_active = 1
                        )
                    )
    
                GROUP BY 
                    r.reservation_id
                ORDER BY 
                    r.reservation_created_at DESC;
            ";
    
            $stmt = $this->conn->prepare($sql);
            $stmt->execute();
    
            $reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return json_encode(['status' => 'success', 'data' => $reservations]);
    
        } catch (PDOException $e) {
            return json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }

    public function fetchApprovalNotification($departmentId, $userLevelId) {
        try {
            if ($departmentId === null || $userLevelId === null) {
                return json_encode(['status' => 'error', 'message' => 'Missing department ID or user level ID']);
            }
    
            $sql = "SELECT 
                        notification_id, 
                        notification_message, 
                        notification_department_id, 
                        notification_user_level_id, 
                        notification_create 
                    FROM notification_requests 
                    WHERE notification_department_id = :department_id
                      AND notification_user_level_id = :user_level_id
                    ORDER BY notification_create DESC";
    
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([
                ':department_id' => $departmentId,
                ':user_level_id' => $userLevelId
            ]);
    
            $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return json_encode(['status' => 'success', 'data' => $notifications]);
    
        } catch (PDOException $e) {
            return json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }

    // Enhanced notification method with push notification support
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

    public function fetchReadApprovalNotification() {
        try {
            $sql = "SELECT 
                    notification_read_id, 
                    notification_id, 
                    user_id, 
                    is_read, 
                    read_at 
                FROM notification_reads 
                WHERE 1";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute();

            $notificationReads = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return json_encode(['status' => 'success', 'data' => $notificationReads]);

        } catch (PDOException $e) {
            return json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }

    public function fetchRequestById($reservationId) {
        try {
            // First, get all status history for this reservation
            $statusHistorySql = "
                SELECT 
                    rs.reservation_status_id,
                    rs.reservation_status_status_id AS status_id,
                    sm.status_master_name AS status_name,
                    rs.reservation_active,
                    rs.reservation_updated_at,
                    rs.reservation_users_id,
                    CONCAT_WS(' ', u.users_fname, u.users_mname, u.users_lname) AS updated_by_name
                FROM tbl_reservation_status rs
                JOIN tbl_status_master sm ON rs.reservation_status_status_id = sm.status_master_id
                LEFT JOIN tbl_users u ON rs.reservation_users_id = u.users_id
                WHERE rs.reservation_reservation_id = :reservation_id
                ORDER BY rs.reservation_updated_at DESC
            ";
            
            $statusStmt = $this->conn->prepare($statusHistorySql);
            $statusStmt->bindParam(':reservation_id', $reservationId, PDO::PARAM_INT);
            $statusStmt->execute();
            $statusHistory = $statusStmt->fetchAll(PDO::FETCH_ASSOC);

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
                    r.additional_note,

                    latest_status.reservation_status_status_id AS status_id,
                    sm.status_master_name AS status_name,
                    latest_status.reservation_active AS active,

                    ul.user_level_name,
                    dep.departments_name,
                    TRIM(
                        CONCAT(
                            COALESCE(u_req.users_fname, ''),
                            ' ',
                            COALESCE(u_req.users_mname, ''),
                            ' ',
                            COALESCE(u_req.users_lname, '')
                        )
                    ) AS requester_name,
                    dep.departments_name AS department_name,

                    -- Venue details
                    GROUP_CONCAT(DISTINCT
                        CONCAT_WS(0x1F,
                            COALESCE(v.reservation_venue_id, ''),
                            COALESCE(v.reservation_venue_venue_id, ''),
                            COALESCE(venue.ven_name, ''),
                            COALESCE(venue.ven_occupancy, ''),
                            COALESCE(venue.ven_operating_hours, ''),
                            COALESCE(venue.ven_pic, ''),
                            COALESCE(v.reservation_change_venue_id, ''),
                            COALESCE(change_venue.ven_name, '')
                        )
                        SEPARATOR 0x1E
                    ) as venue_data,

                    -- Vehicle details with complete information
                    GROUP_CONCAT(DISTINCT
                        CONCAT_WS(0x1F,
                            COALESCE(ve.reservation_vehicle_id, ''),
                            COALESCE(ve.reservation_vehicle_vehicle_id, ''),
                            COALESCE(vm.vehicle_license, ''),
                            COALESCE(vmm.vehicle_model_name, ''),
                            COALESCE(vm.year, ''),
                            COALESCE(vma.vehicle_make_name, ''),
                            COALESCE(vc.vehicle_category_name, ''),
                            COALESCE(vm.vehicle_pic, ''),
                            COALESCE(ve.reservation_change_vehicle_id, ''),
                            COALESCE(change_vm.vehicle_license, ''),
                            COALESCE(change_vmm.vehicle_model_name, ''),
                            COALESCE(change_vm.year, ''),
                            COALESCE(change_vma.vehicle_make_name, ''),
                            COALESCE(change_vc.vehicle_category_name, ''),
                            COALESCE(change_vm.vehicle_pic, '')
                        )
                        SEPARATOR 0x1E
                    ) as vehicle_data,

                    -- Equipment details
                    GROUP_CONCAT(DISTINCT 
                        CONCAT(
                            e.reservation_equipment_equip_id, ':',
                            equip.equip_name, ':',
                            e.reservation_equipment_quantity
                        ) SEPARATOR '|'
                    ) as equipment_data,

                    -- Passenger details
                    GROUP_CONCAT(DISTINCT 
                        CONCAT(
                            p.reservation_passenger_id, ':',
                            p.reservation_passenger_name
                        ) SEPARATOR '|'
                    ) as passenger_data

                FROM tbl_reservation r

                -- Latest status subquery
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

                LEFT JOIN tbl_status_master sm ON sm.status_master_id = latest_status.reservation_status_status_id
                LEFT JOIN tbl_users u_req ON r.reservation_user_id = u_req.users_id
                LEFT JOIN tbl_user_level ul ON u_req.users_user_level_id = ul.user_level_id
                LEFT JOIN tbl_departments dep ON u_req.users_department_id = dep.departments_id

                LEFT JOIN tbl_reservation_venue v ON r.reservation_id = v.reservation_reservation_id
                LEFT JOIN tbl_venue venue ON v.reservation_venue_venue_id = venue.ven_id
                LEFT JOIN tbl_venue change_venue ON v.reservation_change_venue_id = change_venue.ven_id

                LEFT JOIN tbl_reservation_vehicle ve ON r.reservation_id = ve.reservation_reservation_id
                -- Original vehicle joins with complete details
                LEFT JOIN tbl_vehicle vm ON ve.reservation_vehicle_vehicle_id = vm.vehicle_id
                LEFT JOIN tbl_vehicle_model vmm ON vm.vehicle_model_id = vmm.vehicle_model_id
                LEFT JOIN tbl_vehicle_make vma ON vmm.vehicle_model_vehicle_make_id = vma.vehicle_make_id
                LEFT JOIN tbl_vehicle_category vc ON vmm.vehicle_category_id = vc.vehicle_category_id
                -- Change vehicle joins with complete details
                LEFT JOIN tbl_vehicle change_vm ON ve.reservation_change_vehicle_id = change_vm.vehicle_id
                LEFT JOIN tbl_vehicle_model change_vmm ON change_vm.vehicle_model_id = change_vmm.vehicle_model_id
                LEFT JOIN tbl_vehicle_make change_vma ON change_vmm.vehicle_model_vehicle_make_id = change_vma.vehicle_make_id
                LEFT JOIN tbl_vehicle_category change_vc ON change_vmm.vehicle_category_id = change_vc.vehicle_category_id

                LEFT JOIN tbl_reservation_equipment e ON r.reservation_id = e.reservation_reservation_id
                LEFT JOIN tbl_equipments equip ON e.reservation_equipment_equip_id = equip.equip_id

                LEFT JOIN tbl_reservation_passenger p ON r.reservation_id = p.reservation_reservation_id

                WHERE r.reservation_id = :reservation_id
                GROUP BY r.reservation_id
            ";

            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':reservation_id', $reservationId, PDO::PARAM_INT);
            $stmt->execute();

            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($row) {
                $venues = [];
                $vehicles = [];
                $equipment = [];
                $drivers = [];
                $passengers = [];

                $response = [
                    'reservation_id' => $row['reservation_id'],
                    'reservation_created_at' => $row['reservation_created_at'],
                    'reservation_title' => $row['reservation_title'],
                    'reservation_description' => $row['reservation_description'],
                    'reservation_start_date' => $row['reservation_start_date'],
                    'reservation_end_date' => $row['reservation_end_date'],
                    'reschedule_start_date' => $row['reschedule_start_date'],
                    'reschedule_end_date' => $row['reschedule_end_date'],
                    'reservation_participants' => $row['reservation_participants'],
                    'additional_note' => $row['additional_note'],
                    'status_name' => $row['status_name'],  // Keep for backward compatibility
                    'status_id' => $row['status_id'],      // Keep for backward compatibility
                    'active' => $row['active'],            // Keep for backward compatibility
                    'reservation_user_id' => $row['reservation_user_id'],
                    'requester_name' => $row['requester_name'],
                    'department_name' => $row['department_name'],
                    'user_level_name' => $row['user_level_name'],
                    'status_history' => $statusHistory     // Add full status history
                ];

                // VENUES
                if (!empty($row['venue_data'])) {
                    foreach (explode(chr(30), $row['venue_data']) as $venueStr) {
                        if ($venueStr === '' || $venueStr === null) { continue; }
                        $venueParts = explode(chr(31), $venueStr);
                        // Ensure at least base fields exist and not all empty
                        if (count($venueParts) >= 6 && trim(implode('', $venueParts)) !== '') {
                            $venueItem = [
                                'reservation_venue_id' => $venueParts[0] ?? '',
                                'venue_id' => $venueParts[1] ?? '',
                                'venue_name' => $venueParts[2] ?? '',
                                'occupancy' => $venueParts[3] ?? '',
                                'operating_hours' => $venueParts[4] ?? '',
                                'picture' => $venueParts[5] ?? ''
                            ];
                            if (count($venueParts) >= 8) {
                                $venueItem['change_venue_id'] = $venueParts[6] ?? '';
                                $venueItem['change_venue_name'] = $venueParts[7] ?? '';
                            }
                            $venues[] = $venueItem;
                        }
                    }
                }

                // VEHICLES - Updated to include all vehicle details
                if (!empty($row['vehicle_data'])) {
                    foreach (explode(chr(30), $row['vehicle_data']) as $vehicleStr) {
                        if ($vehicleStr === '' || $vehicleStr === null) { continue; }
                        $vehicleParts = explode(chr(31), $vehicleStr);

                        // Skip entries where all parts are empty
                        if (trim(implode('', $vehicleParts)) === '') { continue; }

                        if (count($vehicleParts) >= 8) {
                            $vehicleItem = [
                                'reservation_vehicle_id' => $vehicleParts[0] ?? '',
                                'vehicle_id' => $vehicleParts[1] ?? '',
                                'license' => $vehicleParts[2] ?? '',
                                'model' => $vehicleParts[3] ?? '',
                                'year' => $vehicleParts[4] ?? '',
                                'make' => $vehicleParts[5] ?? '',
                                'category' => $vehicleParts[6] ?? '',
                                'picture' => $vehicleParts[7] ?? ''
                            ];
                            
                            // If change vehicle data is present (15 parts total), include it as well
                            if (count($vehicleParts) >= 15) {
                                $vehicleItem['change_vehicle_id'] = $vehicleParts[8] ?? '';
                                $vehicleItem['change_vehicle_license'] = $vehicleParts[9] ?? '';
                                $vehicleItem['change_vehicle_model'] = $vehicleParts[10] ?? '';
                                $vehicleItem['change_vehicle_year'] = $vehicleParts[11] ?? '';
                                $vehicleItem['change_vehicle_make'] = $vehicleParts[12] ?? '';
                                $vehicleItem['change_vehicle_category'] = $vehicleParts[13] ?? '';
                                $vehicleItem['change_vehicle_picture'] = $vehicleParts[14] ?? '';
                            }

                            // Only push if at least one primary field has a value
                            if (
                                $vehicleItem['reservation_vehicle_id'] !== '' ||
                                $vehicleItem['vehicle_id'] !== '' ||
                                $vehicleItem['license'] !== '' ||
                                $vehicleItem['model'] !== ''
                            ) {
                                $vehicles[] = $vehicleItem;
                            }
                        }
                    }
                }

                // EQUIPMENT
                if (!empty($row['equipment_data'])) {
                    foreach (explode('|', $row['equipment_data']) as $equipStr) {
                        $equipParts = explode(':', $equipStr);
                        if (count($equipParts) >= 3) {
                            $equipment[] = [
                                'equipment_id' => $equipParts[0],
                                'name' => $equipParts[1],
                                'quantity' => $equipParts[2]
                            ];
                        }
                    }
                }

                // DRIVERS: fetch all drivers for this reservation and their details
                $drivers = [];
                $driverStmt = $this->conn->prepare(
                    "SELECT rd.reservation_driver_id, rd.reservation_driver_user_id, rd.reservation_vehicle_id, rv.reservation_vehicle_vehicle_id, rd.is_accepted_trip, rd.driver_name, rd.created_at, rd.updated_at, u.users_fname, u.users_mname, u.users_lname, u.users_birthdate, u.users_suffix, u.users_email, u.users_school_id, u.users_contact_number, u.users_user_level_id, u.users_pic, u.is_active, u.title_id
                     FROM tbl_reservation_driver rd
                     JOIN tbl_reservation_vehicle rv ON rd.reservation_vehicle_id = rv.reservation_vehicle_id
                     LEFT JOIN tbl_users u ON rd.reservation_driver_user_id = u.users_id
                     WHERE rv.reservation_reservation_id = :reservation_id"
                );
                $driverStmt->execute([':reservation_id' => $row['reservation_id']]);
                while ($driverRow = $driverStmt->fetch(PDO::FETCH_ASSOC)) {
                    $drivers[] = [
                        'reservation_driver_id' => $driverRow['reservation_driver_id'],
                        'driver_id' => $driverRow['reservation_driver_user_id'],
                        'driver_name' => $driverRow['driver_name'] ?? trim($driverRow['users_fname'] . ' ' . $driverRow['users_mname'] . ' ' . $driverRow['users_lname']),
                        'reservation_vehicle_id' => $driverRow['reservation_vehicle_id'],
                        'reservation_vehicle_vehicle_id' => $driverRow['reservation_vehicle_vehicle_id'],
                        'is_accepted_trip' => $driverRow['is_accepted_trip'],
                        'created_at' => $driverRow['created_at'],
                        'updated_at' => $driverRow['updated_at'],
                        'user_details' => [
                            'users_fname' => $driverRow['users_fname'],
                            'users_mname' => $driverRow['users_mname'],
                            'users_lname' => $driverRow['users_lname'],
                            'users_birthdate' => $driverRow['users_birthdate'],
                            'users_suffix' => $driverRow['users_suffix'],
                            'users_email' => $driverRow['users_email'],
                            'users_school_id' => $driverRow['users_school_id'],
                            'users_contact_number' => $driverRow['users_contact_number'],
                            'users_user_level_id' => $driverRow['users_user_level_id'],
                            'users_pic' => $driverRow['users_pic'],
                            'is_active' => $driverRow['is_active'],
                            'title_id' => $driverRow['title_id']
                        ]
                    ];
                }

                // PASSENGERS
                if (!empty($row['passenger_data'])) {
                    foreach (explode('|', $row['passenger_data']) as $passengerStr) {
                        $passengerParts = explode(':', $passengerStr);
                        if (count($passengerParts) >= 2) {
                            $passengers[] = [
                                'passenger_id' => $passengerParts[0],
                                'name' => $passengerParts[1]
                            ];
                        }
                    }
                }

                $response['venues'] = $venues;
                $response['vehicles'] = $vehicles;
                $response['equipment'] = $equipment;
                $response['drivers'] = $drivers;
                $response['passengers'] = $passengers;

                return json_encode(['status' => 'success', 'data' => $response]);
            } else {
                return json_encode(['status' => 'error', 'message' => 'Reservation not found']);
            }

        } catch (PDOException $e) {
            return json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }
    public function fetchEquipments($startDateTime = null, $endDateTime = null) {
        header('Content-Type: application/json');
    
        try {
            $mainQuery = "
                SELECT
                    e.equip_id,
                    e.equip_name,
                    e.equip_type,
                    e.equip_created_at,
                    tec.equipments_category_name AS category_name,
                    e.equipments_category_id,
                    CASE
                        WHEN e.equip_type = 'Bulk' THEN COALESCE(eq.quantity, 0)
                        ELSE COALESCE(eu.unit_count, 0)
                    END AS total_on_hand,
                    COALESCE(r.reserved_qty, 0) AS total_reserved,
                    (
                        CASE
                            WHEN e.equip_type = 'Bulk' THEN COALESCE(eq.quantity, 0)
                            ELSE COALESCE(eu.unit_count, 0)
                        END
                        - COALESCE(r.reserved_qty, 0)
                    ) AS total_available
                FROM tbl_equipments e
                LEFT JOIN tbl_equipment_category tec
                    ON e.equipments_category_id = tec.equipments_category_id
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
                ) AS eq ON e.equip_id = eq.equip_id AND e.equip_type = 'Bulk'
                LEFT JOIN (
                    SELECT
                        equip_id,
                        COUNT(*) AS unit_count
                    FROM tbl_equipment_unit
                    WHERE is_active = 1 AND status_availability_id != 2
                    GROUP BY equip_id
                ) AS eu ON e.equip_id = eu.equip_id AND e.equip_type != 'Bulk'
                LEFT JOIN (
                    SELECT
                        re.reservation_equipment_equip_id AS equip_id,
                        SUM(re.reservation_equipment_quantity) AS reserved_qty
                    FROM tbl_reservation_equipment re
                    JOIN tbl_reservation r
                        ON r.reservation_id = re.reservation_reservation_id
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
                    ) ls ON ls.reservation_reservation_id = r.reservation_id
                    LEFT JOIN (
                        SELECT reservation_reservation_id, MAX(reservation_status_id) AS max_reschedule_status_id
                        FROM tbl_reservation_status
                        WHERE reservation_status_status_id = 10 AND reservation_active = 1
                        GROUP BY reservation_reservation_id
                    ) active_resched ON active_resched.reservation_reservation_id = r.reservation_id
                    WHERE (
                        ls.reservation_status_status_id IN (1, 6, 8, 10)
                        AND (
                            (ls.reservation_status_status_id = 6 AND ls.reservation_active = 1)
                            OR (ls.reservation_status_status_id IN (1, 8, 10) AND (ls.reservation_active = 0 OR ls.reservation_active = 1))
                        )
                    )
                    AND (
                        (CASE
                            WHEN (
                                    (ls.reservation_status_status_id = 10 AND ls.reservation_active = 1)
                                    OR (
                                        ls.reservation_status_status_id = 6 AND ls.reservation_active = 1
                                        AND active_resched.max_reschedule_status_id IS NOT NULL
                                    )
                                 )
                                 AND r.reschedule_start_date IS NOT NULL AND r.reschedule_end_date IS NOT NULL
                            THEN r.reschedule_start_date ELSE r.reservation_start_date END) <= :end
                        AND (CASE
                            WHEN (
                                    (ls.reservation_status_status_id = 10 AND ls.reservation_active = 1)
                                    OR (
                                        ls.reservation_status_status_id = 6 AND ls.reservation_active = 1
                                        AND active_resched.max_reschedule_status_id IS NOT NULL
                                    )
                                 )
                                 AND r.reschedule_start_date IS NOT NULL AND r.reschedule_end_date IS NOT NULL
                            THEN r.reschedule_end_date ELSE r.reservation_end_date END) >= :start
                    )
                    GROUP BY re.reservation_equipment_equip_id
                ) AS r ON e.equip_id = r.equip_id
                WHERE e.is_active = 1
                ORDER BY e.equip_name";
    
            $mainStmt = $this->conn->prepare($mainQuery);
    
            // Always bind these, even if null (SQL will handle them accordingly)
            $mainStmt->bindParam(':start', $startDateTime);
            $mainStmt->bindParam(':end', $endDateTime);
    
            $mainStmt->execute();
            $equipments = $mainStmt->fetchAll(PDO::FETCH_ASSOC);
    
            $finalResults = [];
            foreach ($equipments as $equip) {
                $totalOnHand = (int)$equip['total_on_hand'];
                $reserved = (int)$equip['total_reserved'];
                $available = (int)$equip['total_available'];
    
                if ($available > 0 || $reserved > 0) {
                    $equip['current_quantity'] = $totalOnHand;
                    $equip['reserved_quantity'] = $reserved;
                    $equip['available_quantity'] = $available;
                    $equip['is_available'] = ($available > 0);
                    $equip['availability_status'] = $available > 0 ? 'Available' : 'Unavailable';
                    $finalResults[] = $equip;
                }
            }
    
            http_response_code(200);
            echo json_encode([
                'status'    => 'success',
                'data'      => $finalResults,
                'timestamp' => date('Y-m-d H:i:s')
            ], JSON_UNESCAPED_SLASHES);
    
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode([
                'status'    => 'error',
                'message'   => 'Database error: ' . $e->getMessage(),
                'timestamp' => date('Y-m-d H:i:s')
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'status'    => 'error',
                'message'   => 'General error: ' . $e->getMessage(),
                'timestamp' => date('Y-m-d H:i:s')
            ]);
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
                r.reservation_participants, 
                r.reservation_user_id, 
                r.reservation_created_at, 
                CONCAT(u.users_fname, ' ', COALESCE(u.users_mname, ''), ' ', u.users_lname) AS user_full_name,
                sm.status_master_name AS reservation_status_name,
                latest_status.reservation_status_status_id,
                latest_status.reservation_updated_at,
                latest_status.reservation_active

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

            LEFT JOIN tbl_status_master sm ON sm.status_master_id = latest_status.reservation_status_status_id
            LEFT JOIN tbl_users u ON u.users_id = r.reservation_user_id

            ORDER BY r.reservation_created_at DESC
        ";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute();

        $reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return json_encode(['status' => 'success', 'data' => $reservations]);

    } catch (PDOException $e) {
        return json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}

public function fetchNoAssignedReservation() {
    try {
        $sql = "
            SELECT DISTINCT
                r.reservation_id, 
                r.reservation_title, 
                r.reservation_description,
                r.reservation_start_date, 
                r.reservation_end_date, 
                r.reservation_participants, 
                r.reservation_user_id, 
                CONCAT(u.users_fname, ' ', 
                       COALESCE(CONCAT(LEFT(u.users_mname, 1), '. '), ''), 
                       u.users_lname, 
                       IF(u.users_suffix IS NOT NULL AND u.users_suffix != '', CONCAT(' ', u.users_suffix), '')
                ) AS requestor_name,
                r.reservation_created_at
            FROM 
                tbl_reservation r
            LEFT JOIN 
                tbl_users u ON r.reservation_user_id = u.users_id
            LEFT JOIN (
                SELECT rs1.*
                FROM tbl_reservation_status rs1
                INNER JOIN (
                    SELECT reservation_reservation_id, MAX(reservation_status_id) AS max_status_id
                    FROM tbl_reservation_status
                    GROUP BY reservation_reservation_id
                ) rs2 ON rs1.reservation_reservation_id = rs2.reservation_reservation_id
                AND rs1.reservation_status_id = rs2.max_status_id
            ) rs ON r.reservation_id = rs.reservation_reservation_id
            LEFT JOIN 
                tbl_reservation_passenger rp ON r.reservation_id = rp.reservation_reservation_id
            WHERE 
                (rs.reservation_status_status_id = 6 AND rs.reservation_active = 1)
                AND r.reservation_id IS NOT NULL
                AND NOT EXISTS (
                    SELECT 1
                    FROM tbl_reservation_checklist_venue cv 
                    WHERE cv.reservation_venue_id IN (
                        SELECT rv.reservation_venue_id 
                        FROM tbl_reservation_venue rv 
                        WHERE rv.reservation_reservation_id = r.reservation_id
                    )
                )
                AND NOT EXISTS (
                    SELECT 1
                    FROM tbl_reservation_checklist_vehicle cvh 
                    WHERE cvh.reservation_vehicle_id IN (
                        SELECT rvh.reservation_vehicle_id 
                        FROM tbl_reservation_vehicle rvh 
                        WHERE rvh.reservation_reservation_id = r.reservation_id
                    )
                )
                AND NOT EXISTS (
                    SELECT 1
                    FROM tbl_reservation_checklist_equipment ce 
                    WHERE ce.reservation_equipment_id IN (
                        SELECT re.reservation_equipment_id 
                        FROM tbl_reservation_equipment re 
                        WHERE re.reservation_reservation_id = r.reservation_id
                    )
                )
            ORDER BY 
                r.reservation_created_at DESC
        ";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute();

        $reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);
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

    public function fetchVenueHistory($venueId = null) {
        try {
            $sql = "
                SELECT 
                    v.ven_name AS venue_name,
                    CONCAT(u.users_fname, ' ', COALESCE(u.users_mname, ''), ' ', u.users_lname) AS requester,
                    r.reservation_start_date,
                    r.reservation_end_date
                FROM tbl_reservation r
                INNER JOIN tbl_reservation_status rs ON r.reservation_id = rs.reservation_reservation_id
                INNER JOIN tbl_reservation_venue rv ON r.reservation_id = rv.reservation_reservation_id
                INNER JOIN tbl_venue v ON rv.reservation_venue_venue_id = v.ven_id
                INNER JOIN tbl_users u ON r.reservation_user_id = u.users_id
                WHERE rs.reservation_status_status_id = 6
                  AND rs.reservation_active = 0
            ";
            if ($venueId !== null) {
                $sql .= " AND v.ven_id = :venueId";
            }
            $sql .= " ORDER BY r.reservation_start_date DESC";
            $stmt = $this->conn->prepare($sql);
            if ($venueId !== null) {
                $stmt->bindParam(':venueId', $venueId, PDO::PARAM_INT);
            }
            $stmt->execute();
            $history = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return json_encode([
                'status' => 'success',
                'data' => $history
            ]);
        } catch (PDOException $e) {
            return json_encode([
                'status' => 'error',
                'message' => 'Database error: ' . $e->getMessage()
            ]);
        }
    }

    public function fetchVehicleHistory($vehicleId = null) {
        try {
            $sql = "
                SELECT 
                    vm.vehicle_model_name,
                    vc.vehicle_category_name,
                    vmake.vehicle_make_name,
                    v.vehicle_license,
                    rd.reservation_driver_user_id,
                    rd.driver_name AS manual_driver_name,
                    udriver.users_fname AS driver_fname,
                    udriver.users_mname AS driver_mname,
                    udriver.users_lname AS driver_lname,
                    CONCAT(u.users_fname, ' ', COALESCE(u.users_mname, ''), ' ', u.users_lname) AS requester,
                    r.reservation_start_date,
                    r.reservation_end_date
                FROM tbl_reservation r
                INNER JOIN tbl_reservation_status rs ON r.reservation_id = rs.reservation_reservation_id
                INNER JOIN tbl_reservation_vehicle rv ON r.reservation_id = rv.reservation_reservation_id
                INNER JOIN tbl_vehicle v ON rv.reservation_vehicle_vehicle_id = v.vehicle_id
                INNER JOIN tbl_vehicle_model vm ON v.vehicle_model_id = vm.vehicle_model_id
                INNER JOIN tbl_vehicle_make vmake ON vm.vehicle_model_vehicle_make_id = vmake.vehicle_make_id
                INNER JOIN tbl_vehicle_category vc ON vm.vehicle_category_id = vc.vehicle_category_id
                LEFT JOIN tbl_reservation_driver rd ON rv.reservation_vehicle_id = rd.reservation_vehicle_id
                LEFT JOIN tbl_users udriver ON rd.reservation_driver_user_id = udriver.users_id
                INNER JOIN tbl_users u ON r.reservation_user_id = u.users_id
                WHERE rs.reservation_status_status_id = 6
                  AND rs.reservation_active = 0
            ";
            if ($vehicleId !== null) {
                $sql .= " AND v.vehicle_id = :vehicleId";
            }
            $sql .= " ORDER BY r.reservation_start_date DESC";
            $stmt = $this->conn->prepare($sql);
            if ($vehicleId !== null) {
                $stmt->bindParam(':vehicleId', $vehicleId, PDO::PARAM_INT);
            }
            $stmt->execute();
            $history = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Post-process driver name logic
            foreach ($history as &$row) {
                if (!empty($row['manual_driver_name']) && empty($row['reservation_driver_user_id'])) {
                    $row['driver_name'] = $row['manual_driver_name'];
                } elseif (empty($row['manual_driver_name']) && !empty($row['reservation_driver_user_id'])) {
                    $row['driver_name'] = trim($row['driver_fname'] . ' ' . ($row['driver_mname'] ? $row['driver_mname'] . ' ' : '') . $row['driver_lname']);
                } else {
                    $row['driver_name'] = null;
                }
                unset($row['manual_driver_name'], $row['driver_fname'], $row['driver_mname'], $row['driver_lname'], $row['reservation_driver_user_id']);
            }

            return json_encode([
                'status' => 'success',
                'data' => $history
            ]);
        } catch (PDOException $e) {
            return json_encode([
                'status' => 'error',
                'message' => 'Database error: ' . $e->getMessage()
            ]);
        }
    }

    public function fetchEquipmentHistory($equipId = null) {
        try {
            $sql = "
                SELECT 
                    e.equip_name,
                    re.reservation_equipment_quantity,
                    CONCAT(u.users_fname, ' ', COALESCE(u.users_mname, ''), ' ', u.users_lname) AS requester,
                    r.reservation_start_date,
                    r.reservation_end_date
                FROM tbl_reservation_equipment re
                INNER JOIN tbl_equipments e ON re.reservation_equipment_equip_id = e.equip_id
                INNER JOIN tbl_reservation r ON re.reservation_reservation_id = r.reservation_id
                INNER JOIN tbl_users u ON r.reservation_user_id = u.users_id
                INNER JOIN tbl_reservation_status rs ON r.reservation_id = rs.reservation_reservation_id
                WHERE rs.reservation_status_status_id = 6
                  AND rs.reservation_active = 0
            ";
            if ($equipId !== null) {
                $sql .= " AND e.equip_id = :equipId";
            }
            $sql .= " ORDER BY r.reservation_start_date DESC";
            $stmt = $this->conn->prepare($sql);
            if ($equipId !== null) {
                $stmt->bindParam(':equipId', $equipId, PDO::PARAM_INT);
            }
            $stmt->execute();
            $history = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return json_encode([
                'status' => 'success',
                'data' => $history
            ]);
        } catch (PDOException $e) {
            return json_encode([
                'status' => 'error',
                'message' => 'Database error: ' . $e->getMessage()
            ]);
        }
    }

    public function fetchEquipmentUnitHistory($unitId = null) {
        try {
            $sql = "
                SELECT 
                    eu.serial_number,
                    e.equip_name,
                    CONCAT(u.users_fname, ' ', COALESCE(u.users_mname, ''), ' ', u.users_lname) AS requester,
                    r.reservation_start_date,
                    r.reservation_end_date
                FROM tbl_reservation_unit ru
                INNER JOIN tbl_equipment_unit eu ON ru.unit_id = eu.unit_id
                INNER JOIN tbl_equipments e ON eu.equip_id = e.equip_id
                INNER JOIN tbl_reservation_equipment re ON ru.reservation_equipment_id = re.reservation_equipment_id
                INNER JOIN tbl_reservation r ON re.reservation_reservation_id = r.reservation_id
                INNER JOIN tbl_users u ON r.reservation_user_id = u.users_id
                INNER JOIN tbl_reservation_status rs ON r.reservation_id = rs.reservation_reservation_id
                WHERE rs.reservation_status_status_id = 6
                  AND rs.reservation_active = 0
            ";
            if ($unitId !== null) {
                $sql .= " AND eu.unit_id = :unitId";
            }
            $sql .= " ORDER BY r.reservation_start_date DESC";
            $stmt = $this->conn->prepare($sql);
            if ($unitId !== null) {
                $stmt->bindParam(':unitId', $unitId, PDO::PARAM_INT);
            }
            $stmt->execute();
            $history = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return json_encode([
                'status' => 'success',
                'data' => $history
            ]);
        } catch (PDOException $e) {
            return json_encode([
                'status' => 'error',
                'message' => 'Database error: ' . $e->getMessage()
            ]);
        }
    }

    public function fetchReservationGenerateReport($month, $user_personnel_id = null){
        try {
            // $month should be in 'YYYY-MM' format
            $startDate = $month . '-01';
            $endDate = date('Y-m-t', strtotime($startDate));

            $sql = "
                SELECT 
                    r.reservation_id, 
                    r.reservation_title, 
                    r.reservation_description, 
                    r.reservation_start_date, 
                    r.reservation_end_date, 
                    r.reservation_participants, 
                    r.reservation_user_id,
                    r.reservation_created_at,
                    r.additional_note,

                    latest_status.reservation_status_status_id AS status_id,
                    sm.status_master_name AS status_name,
                    latest_status.reservation_active AS active,

                    ul.user_level_name,
                    dep.departments_name,
                    TRIM(
                        CONCAT(
                            COALESCE(u_req.users_fname, ''),
                            ' ',
                            COALESCE(u_req.users_mname, ''),
                            ' ',
                            COALESCE(u_req.users_lname, '')
                        )
                    ) AS requester_name,
                    dep.departments_name AS department_name,

                    -- Venue details
                    GROUP_CONCAT(DISTINCT 
                        CONCAT(
                            COALESCE(v.reservation_venue_id, ''), ':', 
                            COALESCE(v.reservation_venue_venue_id, ''), ':',
                            COALESCE(venue.ven_name, ''), ':',
                            COALESCE(venue.ven_occupancy, ''), ':',
                            COALESCE(venue.ven_operating_hours, ''), ':',
                            COALESCE(venue.ven_pic, '')
                        ) SEPARATOR '|'
                    ) as venue_data,

                    -- Vehicle details
                    GROUP_CONCAT(DISTINCT 
                        CONCAT(
                            ve.reservation_vehicle_id, ':',
                            ve.reservation_vehicle_vehicle_id, ':',
                            vm.vehicle_license, ':',
                            vmm.vehicle_model_name
                        ) SEPARATOR '|'
                    ) as vehicle_data,

                    -- Equipment details
                    GROUP_CONCAT(DISTINCT 
                        CONCAT(
                            e.reservation_equipment_equip_id, ':',
                            equip.equip_name, ':',
                            e.reservation_equipment_quantity
                        ) SEPARATOR '|'
                    ) as equipment_data,

                    -- Passenger details
                    GROUP_CONCAT(DISTINCT 
                        CONCAT(
                            p.reservation_passenger_id, ':',
                            p.reservation_passenger_name
                        ) SEPARATOR '|'
                    ) as passenger_data

                FROM tbl_reservation r

                -- Latest status subquery
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

                LEFT JOIN tbl_status_master sm ON sm.status_master_id = latest_status.reservation_status_status_id
                LEFT JOIN tbl_users u_req ON r.reservation_user_id = u_req.users_id
                LEFT JOIN tbl_user_level ul ON u_req.users_user_level_id = ul.user_level_id
                LEFT JOIN tbl_departments dep ON u_req.users_department_id = dep.departments_id

                LEFT JOIN tbl_reservation_venue v ON r.reservation_id = v.reservation_reservation_id
                LEFT JOIN tbl_venue venue ON v.reservation_venue_venue_id = venue.ven_id

                LEFT JOIN tbl_reservation_vehicle ve ON r.reservation_id = ve.reservation_reservation_id
                LEFT JOIN tbl_vehicle vm ON ve.reservation_vehicle_vehicle_id = vm.vehicle_id
                LEFT JOIN tbl_vehicle_model vmm ON vm.vehicle_model_id = vmm.vehicle_model_id

                LEFT JOIN tbl_reservation_equipment e ON r.reservation_id = e.reservation_reservation_id
                LEFT JOIN tbl_equipments equip ON e.reservation_equipment_equip_id = equip.equip_id

                LEFT JOIN tbl_reservation_passenger p ON r.reservation_id = p.reservation_reservation_id

                WHERE r.reservation_created_at BETWEEN :startDate AND :endDate
                GROUP BY r.reservation_id
                ORDER BY r.reservation_start_date DESC
            ";

            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':startDate', $startDate);
            $stmt->bindParam(':endDate', $endDate);
            $stmt->execute();
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $finalResults = [];
            foreach ($results as $row) {
                $venues = [];
                $vehicles = [];
                $equipment = [];
                $passengers = [];

                // Fetch condition data for this reservation first
                $conditionData = [];
                
                // Equipment conditions - get all conditions for this reservation's equipment
                $equipmentConditionStmt = $this->conn->prepare("
                    SELECT rce.`id`, rce.`reservation_equipment_id`, rce.`condition_id`, rce.`qty_bad`, rce.`user_personnel_id`, rce.`remarks`, rce.`created_at`, rce.`updated_at`, rce.`is_active`, c.`condition_name`
                    FROM `tbl_reservation_condition_equipment` rce
                    LEFT JOIN `tbl_condition` c ON rce.`condition_id` = c.`id`
                    INNER JOIN `tbl_reservation_equipment` re ON rce.`reservation_equipment_id` = re.`reservation_equipment_id`
                    WHERE re.`reservation_reservation_id` = :reservation_id
                ");
                $equipmentConditionStmt->execute([':reservation_id' => $row['reservation_id']]);
                $equipmentConditions = $equipmentConditionStmt->fetchAll(PDO::FETCH_ASSOC);
                error_log("FIRST Equipment conditions query result for reservation_id {$row['reservation_id']}: " . json_encode($equipmentConditions));
                
                // Debug: Show all reservation_equipment_id values for this reservation
                $debugStmt = $this->conn->prepare("
                    SELECT `reservation_equipment_id`, `reservation_equipment_quantity`, `reservation_equipment_equip_id`
                    FROM `tbl_reservation_equipment` 
                    WHERE `reservation_reservation_id` = :reservation_id
                ");
                $debugStmt->execute([':reservation_id' => $row['reservation_id']]);
                $debugResults = $debugStmt->fetchAll(PDO::FETCH_ASSOC);
                error_log("All reservation_equipment_id values for reservation_id {$row['reservation_id']}: " . json_encode($debugResults));
                
                // Unit conditions
                $unitConditionStmt = $this->conn->prepare("
                    SELECT rcu.`id`, rcu.`reservation_unit_id`, rcu.`condition_id`, rcu.`is_active`, rcu.`user_personnel_id`, rcu.`remarks`, rcu.`created_at`, rcu.`updated_at`, c.`condition_name`
                    FROM `tbl_reservation_condition_unit` rcu
                    LEFT JOIN `tbl_condition` c ON rcu.`condition_id` = c.`id`
                    WHERE rcu.`reservation_unit_id` IN (
                        SELECT `reservation_unit_id` 
                        FROM `tbl_reservation_unit` 
                        WHERE `reservation_equipment_id` IN (
                            SELECT `reservation_equipment_id` 
                            FROM `tbl_reservation_equipment` 
                            WHERE `reservation_reservation_id` = :reservation_id
                        )
                    )
                ");
                $unitConditionStmt->execute([':reservation_id' => $row['reservation_id']]);
                $unitConditions = $unitConditionStmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Vehicle conditions
                $vehicleConditionStmt = $this->conn->prepare("
                    SELECT rcv.`id`, rcv.`reservation_vehicle_id`, rcv.`condition_id`, rcv.`is_active`, rcv.`user_personnel_id`, rcv.`remarks`, rcv.`created_at`, rcv.`updated_at`, c.`condition_name`
                    FROM `tbl_reservation_condition_vehicle` rcv
                    LEFT JOIN `tbl_condition` c ON rcv.`condition_id` = c.`id`
                    INNER JOIN `tbl_reservation_vehicle` rv ON rcv.`reservation_vehicle_id` = rv.`reservation_vehicle_id`
                    WHERE rv.`reservation_reservation_id` = :reservation_id
                ");
                $vehicleConditionStmt->execute([':reservation_id' => $row['reservation_id']]);
                $vehicleConditions = $vehicleConditionStmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Venue conditions
                $venueConditionStmt = $this->conn->prepare("
                    SELECT rcv.`id`, rcv.`reservation_venue_id`, rcv.`condition_id`, rcv.`is_active`, rcv.`user_personnel_id`, rcv.`remarks`, rcv.`created_at`, rcv.`updated_at`, c.`condition_name`
                    FROM `tbl_reservation_condition_venue` rcv
                    LEFT JOIN `tbl_condition` c ON rcv.`condition_id` = c.`id`
                    INNER JOIN `tbl_reservation_venue` rv ON rcv.`reservation_venue_id` = rv.`reservation_venue_id`
                    WHERE rv.`reservation_reservation_id` = :reservation_id
                ");
                $venueConditionStmt->execute([':reservation_id' => $row['reservation_id']]);
                $venueConditions = $venueConditionStmt->fetchAll(PDO::FETCH_ASSOC);

                // VENUES
                if (!empty($row['venue_data']) && $row['venue_data'] !== '::::::') {
                    foreach (explode('|', $row['venue_data']) as $venueStr) {
                        $venueParts = explode(':', $venueStr);
                        if (count($venueParts) >= 6 && !empty(array_filter($venueParts))) {
                            $venueId = $venueParts[0] ?: '';
                            $venueConditionsForThis = array_filter($venueConditions, function($condition) use ($venueId) {
                                return $condition['reservation_venue_id'] == $venueId;
                            });
                            
                            // Calculate venue issue counts (exclude condition_id = 2)
                            $venueTotalIssues = count(array_filter($venueConditionsForThis, function($condition) {
                                return $condition['condition_id'] != 2;
                            }));
                            
                            $venues[] = [
                                'reservation_venue_id' => $venueId,
                                'venue_id' => $venueParts[1] ?: '',
                                'venue_name' => $venueParts[2] ?: '',
                                'occupancy' => $venueParts[3] ?: '',
                                'operating_hours' => $venueParts[4] ?: '',
                                'picture' => $venueParts[5] ?: '',
                                'conditions' => array_values($venueConditionsForThis),
                                'issue_counts' => [
                                    'total_issues' => $venueTotalIssues
                                ]
                            ];
                        }
                    }
                }

                // VEHICLES
                if (!empty($row['vehicle_data'])) {
                    foreach (explode('|', $row['vehicle_data']) as $vehicleStr) {
                        $vehicleParts = explode(':', $vehicleStr);
                        if (count($vehicleParts) >= 4) {
                            $vehicleId = $vehicleParts[0];
                            $vehicleConditionsForThis = array_filter($vehicleConditions, function($condition) use ($vehicleId) {
                                return $condition['reservation_vehicle_id'] == $vehicleId;
                            });
                            
                            // Calculate vehicle issue counts (exclude condition_id = 2)
                            $vehicleTotalIssues = count(array_filter($vehicleConditionsForThis, function($condition) {
                                return $condition['condition_id'] != 2;
                            }));
                            
                            $vehicles[] = [
                                'reservation_vehicle_id' => $vehicleId,
                                'vehicle_id' => $vehicleParts[1],
                                'license' => $vehicleParts[2],
                                'model' => $vehicleParts[3],
                                'conditions' => array_values($vehicleConditionsForThis),
                                'issue_counts' => [
                                    'total_issues' => $vehicleTotalIssues
                                ]
                            ];
                        }
                    }
                }

                // EQUIPMENT
                if (!empty($row['equipment_data'])) {
                    error_log("Equipment data received: " . $row['equipment_data']);
                    foreach (explode('|', $row['equipment_data']) as $equipStr) {
                        $equipParts = explode(':', $equipStr);
                        error_log("Equipment string: " . $equipStr . ", Parts: " . json_encode($equipParts));
                        if (count($equipParts) >= 3) {
                            $equipmentId = $equipParts[0];
                            error_log("Total equipment conditions fetched: " . count($equipmentConditions));
                            error_log("All equipment conditions fetched: " . json_encode($equipmentConditions));
                            // Get the reservation_equipment_id for this equipment
                            $reservationEquipmentStmt = $this->conn->prepare("
                                SELECT `reservation_equipment_id` 
                                FROM `tbl_reservation_equipment` 
                                WHERE `reservation_equipment_equip_id` = :equip_id 
                                AND `reservation_reservation_id` = :reservation_id
                            ");
                            $reservationEquipmentStmt->execute([
                                ':equip_id' => $equipmentId,
                                ':reservation_id' => $row['reservation_id']
                            ]);
                            $reservationEquipmentResult = $reservationEquipmentStmt->fetch(PDO::FETCH_ASSOC);
                            $reservationEquipmentId = $reservationEquipmentResult ? $reservationEquipmentResult['reservation_equipment_id'] : null;
                            
                            error_log("Equipment ID: {$equipmentId}, Reservation Equipment ID: {$reservationEquipmentId}");
                            
                            $equipmentConditionsForThis = array_filter($equipmentConditions, function($condition) use ($reservationEquipmentId) {
                                return $condition['reservation_equipment_id'] == $reservationEquipmentId;
                            });
                            error_log("Equipment conditions for reservation_equipment_id {$equipmentId}: " . count($equipmentConditionsForThis));
                            foreach ($equipmentConditionsForThis as $condition) {
                                error_log("Found condition for equipment {$equipmentId}: " . json_encode($condition));
                            }
                            
                            // Get equipment type directly using debug approach
                            $debugStmt = $this->conn->prepare("
                                SELECT `reservation_equipment_equip_id` 
                                FROM `tbl_reservation_equipment` 
                                WHERE `reservation_equipment_id` = :equipment_id
                            ");
                            $debugStmt->execute([':equipment_id' => $equipmentId]);
                            $debugResult = $debugStmt->fetch(PDO::FETCH_ASSOC);
                            $reservationEquipId = $debugResult ? $debugResult['reservation_equipment_equip_id'] : null;
                            error_log("Reservation Equipment ID: {$equipmentId}, Reservation Equipment Equip ID: {$reservationEquipId}");
                            
                            // Get equipment type from tbl_equipments
                            $equipmentType = 'unknown';
                            $equipId = null;
                            if ($reservationEquipId) {
                                $debugEquipStmt = $this->conn->prepare("
                                    SELECT `equip_id`, `equip_type` 
                                    FROM `tbl_equipments` 
                                    WHERE `equip_id` = :equip_id
                                ");
                                $debugEquipStmt->execute([':equip_id' => $reservationEquipId]);
                                $debugEquipResult = $debugEquipStmt->fetch(PDO::FETCH_ASSOC);
                                error_log("Equip ID from reservation: {$reservationEquipId}, Found in tbl_equipments: " . ($debugEquipResult ? 'YES' : 'NO'));
                                if ($debugEquipResult) {
                                    $equipmentType = $debugEquipResult['equip_type'];
                                    $equipId = $debugEquipResult['equip_id'];
                                    error_log("Equip Type from tbl_equipments: {$equipmentType}");
                                }
                            } else {
                                // Try to get equipment type by name if reservation_equipment_equip_id is null
                                $equipmentName = $equipParts[1];
                                $debugEquipStmt = $this->conn->prepare("
                                    SELECT `equip_id`, `equip_type` 
                                    FROM `tbl_equipments` 
                                    WHERE `equip_name` = :equip_name
                                ");
                                $debugEquipStmt->execute([':equip_name' => $equipmentName]);
                                $debugEquipResult = $debugEquipStmt->fetch(PDO::FETCH_ASSOC);
                                error_log("Equipment Name: {$equipmentName}, Found in tbl_equipments: " . ($debugEquipResult ? 'YES' : 'NO'));
                                if ($debugEquipResult) {
                                    $equipmentType = $debugEquipResult['equip_type'];
                                    $equipId = $debugEquipResult['equip_id'];
                                    error_log("Equip Type from tbl_equipments by name: {$equipmentType}");
                                }
                            }
                            error_log("Final - Equipment ID: {$equipmentId}, Equip ID: {$equipId}, Equipment Type: {$equipmentType}");
                            
                            // Debug: Check if reservation_equipment_equip_id exists
                            $debugStmt = $this->conn->prepare("
                                SELECT `reservation_equipment_equip_id` 
                                FROM `tbl_reservation_equipment` 
                                WHERE `reservation_equipment_id` = :equipment_id
                            ");
                            $debugStmt->execute([':equipment_id' => $equipmentId]);
                            $debugResult = $debugStmt->fetch(PDO::FETCH_ASSOC);
                            $reservationEquipId = $debugResult ? $debugResult['reservation_equipment_equip_id'] : null;
                            error_log("Reservation Equipment ID: {$equipmentId}, Reservation Equipment Equip ID: {$reservationEquipId}");
                            
                            // Debug: Check if equip_id exists in tbl_equipments
                            if ($reservationEquipId) {
                                $debugEquipStmt = $this->conn->prepare("
                                    SELECT `equip_id`, `equip_type` 
                                    FROM `tbl_equipments` 
                                    WHERE `equip_id` = :equip_id
                                ");
                                $debugEquipStmt->execute([':equip_id' => $reservationEquipId]);
                                $debugEquipResult = $debugEquipStmt->fetch(PDO::FETCH_ASSOC);
                                error_log("Equip ID from reservation: {$reservationEquipId}, Found in tbl_equipments: " . ($debugEquipResult ? 'YES' : 'NO'));
                                if ($debugEquipResult) {
                                    error_log("Equip Type from tbl_equipments: {$debugEquipResult['equip_type']}");
                                }
                            }
                            error_log("Processing equipment ID: {$equipmentId}, Name: {$equipParts[1]}, Type: {$equipmentType}");
                            error_log("Equipment conditions for this equipment: " . json_encode($equipmentConditionsForThis));
                            error_log("DEBUG: Equipment ID: {$equipmentId}, Equipment Type: {$equipmentType}, Equip Parts: " . json_encode($equipParts));
                            
                            if ($equipmentType == 'Bulk') {
                                // For bulk equipment, count qty_bad (no condition_id filter)
                                $bulkTotalIssues = 0;
                                error_log("Processing BULK equipment: {$equipParts[1]} (ID: {$equipmentId})");
                                error_log("All tbl_reservation_condition_equipment data for bulk equipment {$equipParts[1]} (ID: {$equipmentId}): " . json_encode($equipmentConditionsForThis));
                                foreach ($equipmentConditionsForThis as $condition) {
                                    error_log("Bulk condition: " . json_encode($condition));
                                    $bulkTotalIssues += (int)$condition['qty_bad'];
                                    error_log("Added qty_bad: {$condition['qty_bad']} for bulk equipment: {$equipParts[1]} (ID: {$equipmentId})");
                                }
                                error_log("Total bulk issues for {$equipParts[1]} (ID: {$equipmentId}): {$bulkTotalIssues}");
                                error_log("Adding bulk equipment to array with total_issues: {$bulkTotalIssues}");
                                
                                // Remove condition_id from conditions array for bulk equipment
                                $filteredConditions = array_map(function($condition) {
                                    unset($condition['condition_id']);
                                    return $condition;
                                }, $equipmentConditionsForThis);
                                
                                $equipment[] = [
                                    'equipment_id' => $equipmentId,
                                    'name' => $equipParts[1],
                                    'quantity' => $equipParts[2],
                                    'equipment_type' => 'bulk',
                                    'conditions' => array_values($filteredConditions),
                                    'issue_counts' => [
                                        'total_issues' => $bulkTotalIssues
                                    ]
                                ];
                                error_log("Bulk equipment added to array: " . json_encode($equipment[count($equipment)-1]));
                            } elseif ($equipmentType == 'Serialized') {
                                // For serialized equipment, count unit conditions (exclude condition_id = 2)
                                $unitConditionsForThis = [];
                                error_log("Processing SERIALIZED equipment: {$equipParts[1]} (ID: {$equipmentId})");
                                // Get unit conditions for serialized equipment
                                $unitConditionStmt = $this->conn->prepare("
                                    SELECT rcu.`id`, rcu.`reservation_unit_id`, rcu.`condition_id`, rcu.`is_active`, rcu.`user_personnel_id`, rcu.`remarks`, rcu.`created_at`, rcu.`updated_at` 
                                    FROM `tbl_reservation_condition_unit` rcu
                                    INNER JOIN `tbl_reservation_unit` ru ON rcu.`reservation_unit_id` = ru.`reservation_unit_id`
                                    WHERE ru.`reservation_equipment_id` = :reservation_equipment_id
                                ");
                                $unitConditionStmt->execute([':reservation_equipment_id' => $reservationEquipmentId]);
                                $unitConditionsForThis = $unitConditionStmt->fetchAll(PDO::FETCH_ASSOC);
                                error_log("Found " . count($unitConditionsForThis) . " unit conditions for serialized equipment: {$equipParts[1]} (ID: {$equipmentId}, Reservation Equipment ID: {$reservationEquipmentId})");
                                
                                $unitTotalIssues = 0;
                                foreach ($unitConditionsForThis as $condition) {
                                    error_log("Unit condition: " . json_encode($condition));
                                    $conditionId = (int)$condition['condition_id'];
                                    if ($conditionId == 3 || $conditionId == 4 || $conditionId == 7) {
                                        $unitTotalIssues++;
                                        error_log("Counted unit condition (ID: {$conditionId}) for serialized equipment: {$equipParts[1]} (ID: {$equipmentId})");
                                    } else {
                                        error_log("Skipped condition_id {$conditionId} for unit condition in serialized equipment: {$equipParts[1]} (ID: {$equipmentId})");
                                    }
                                }
                                error_log("Total unit issues for serialized equipment {$equipParts[1]} (ID: {$equipmentId}): {$unitTotalIssues}");
                                
                                $equipment[] = [
                                    'equipment_id' => $equipmentId,
                                    'name' => $equipParts[1],
                                    'quantity' => $equipParts[2],
                                    'equipment_type' => 'serialized',
                                    'conditions' => array_values($equipmentConditionsForThis),
                                    'unit_conditions' => $unitConditionsForThis,
                                    'issue_counts' => [
                                        'total_issues' => $unitTotalIssues
                                    ]
                                ];
                            } else {
                                // Unknown equipment type - don't process
                                error_log("Unknown equipment type for {$equipParts[1]} (ID: {$equipmentId}): {$equipmentType}");
                                error_log("Equipment conditions for unknown type: " . json_encode($equipmentConditionsForThis));
                            }
                        }
                    }
                }

                // Fetch condition data for this reservation first
                $conditionData = [];
                
                // Equipment conditions with equipment type (based on getBulkUsage)
                $equipmentConditionStmt = $this->conn->prepare("
                    SELECT rce.`id`, rce.`reservation_equipment_id`, rce.`condition_id`, rce.`qty_bad`, rce.`user_personnel_id`, rce.`remarks`, rce.`created_at`, rce.`updated_at`, rce.`is_active`, e.`equip_type`
                    FROM `tbl_reservation_equipment` re
                    LEFT JOIN `tbl_reservation_condition_equipment` rce ON rce.`reservation_equipment_id` = re.`reservation_equipment_id`
                    LEFT JOIN `tbl_equipments` e ON re.`reservation_equipment_equip_id` = e.`equip_id`
                    WHERE re.`reservation_reservation_id` = :reservation_id
                ");
                $equipmentConditionStmt->execute([':reservation_id' => $row['reservation_id']]);
                $equipmentConditions = $equipmentConditionStmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Unit conditions
                $unitConditionStmt = $this->conn->prepare("
                    SELECT rcu.`id`, rcu.`reservation_unit_id`, rcu.`condition_id`, rcu.`is_active`, rcu.`user_personnel_id`, rcu.`remarks`, rcu.`created_at`, rcu.`updated_at` 
                    FROM `tbl_reservation_condition_unit` rcu
                    INNER JOIN `tbl_reservation_unit` ru ON rcu.`reservation_unit_id` = ru.`reservation_unit_id`
                    INNER JOIN `tbl_reservation_equipment` re ON ru.`reservation_equipment_id` = re.`reservation_equipment_id`
                    WHERE re.`reservation_reservation_id` = :reservation_id
                ");
                $unitConditionStmt->execute([':reservation_id' => $row['reservation_id']]);
                $unitConditions = $unitConditionStmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Vehicle conditions
                $vehicleConditionStmt = $this->conn->prepare("
                    SELECT `id`, `reservation_vehicle_id`, `condition_id`, `is_active`, `user_personnel_id`, `remarks`, `created_at`, `updated_at` 
                    FROM `tbl_reservation_condition_vehicle` 
                    WHERE `reservation_vehicle_id` IN (
                        SELECT `reservation_vehicle_id` 
                        FROM `tbl_reservation_vehicle` 
                        WHERE `reservation_reservation_id` = :reservation_id
                    )
                ");
                $vehicleConditionStmt->execute([':reservation_id' => $row['reservation_id']]);
                $vehicleConditions = $vehicleConditionStmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Venue conditions
                $venueConditionStmt = $this->conn->prepare("
                    SELECT `id`, `reservation_venue_id`, `condition_id`, `is_active`, `user_personnel_id`, `remarks`, `created_at`, `updated_at` 
                    FROM `tbl_reservation_condition_venue` 
                    WHERE `reservation_venue_id` IN (
                        SELECT `reservation_venue_id` 
                        FROM `tbl_reservation_venue` 
                        WHERE `reservation_reservation_id` = :reservation_id
                    )
                ");
                $venueConditionStmt->execute([':reservation_id' => $row['reservation_id']]);
                $venueConditions = $venueConditionStmt->fetchAll(PDO::FETCH_ASSOC);

                // PASSENGERS
                if (!empty($row['passenger_data'])) {
                    foreach (explode('|', $row['passenger_data']) as $passengerStr) {
                        $passengerParts = explode(':', $passengerStr);
                        if (count($passengerParts) >= 2) {
                            $passengers[] = [
                                'passenger_id' => $passengerParts[0],
                                'name' => $passengerParts[1]
                            ];
                        }
                    }
                }

                // DRIVERS: fetch all drivers for this reservation and their details
                $drivers = [];
                $driverStmt = $this->conn->prepare(
                    "SELECT rd.reservation_driver_id, rd.reservation_driver_user_id, rd.reservation_vehicle_id, rd.is_accepted_trip, rd.driver_name, rd.created_at, rd.updated_at, u.users_fname, u.users_mname, u.users_lname, u.users_birthdate, u.users_suffix, u.users_email, u.users_school_id, u.users_contact_number, u.users_user_level_id, u.users_pic, u.is_active, u.title_id
                     FROM tbl_reservation_driver rd
                     JOIN tbl_reservation_vehicle rv ON rd.reservation_vehicle_id = rv.reservation_vehicle_id
                     LEFT JOIN tbl_users u ON rd.reservation_driver_user_id = u.users_id
                     WHERE rv.reservation_reservation_id = :reservation_id"
                );
                $driverStmt->execute([':reservation_id' => $row['reservation_id']]);
                while ($driverRow = $driverStmt->fetch(PDO::FETCH_ASSOC)) {
                    $drivers[] = [
                        'reservation_driver_id' => $driverRow['reservation_driver_id'],
                        'driver_id' => $driverRow['reservation_driver_user_id'],
                        'driver_name' => $driverRow['driver_name'] ?? trim($driverRow['users_fname'] . ' ' . $driverRow['users_mname'] . ' ' . $driverRow['users_lname']),
                        'reservation_vehicle_id' => $driverRow['reservation_vehicle_id'],
                        'is_accepted_trip' => $driverRow['is_accepted_trip'],
                        'created_at' => $driverRow['created_at'],
                        'updated_at' => $driverRow['updated_at'],
                        'user_details' => [
                            'users_fname' => $driverRow['users_fname'],
                            'users_mname' => $driverRow['users_mname'],
                            'users_lname' => $driverRow['users_lname'],
                            'users_birthdate' => $driverRow['users_birthdate'],
                            'users_suffix' => $driverRow['users_suffix'],
                            'users_email' => $driverRow['users_email'],
                            'users_school_id' => $driverRow['users_school_id'],
                            'users_contact_number' => $driverRow['users_contact_number'],
                            'users_user_level_id' => $driverRow['users_user_level_id'],
                            'users_pic' => $driverRow['users_pic'],
                            'is_active' => $driverRow['is_active'],
                            'title_id' => $driverRow['title_id']
                        ]
                    ];
                }



                $finalResults[] = [
                    'reservation_id' => $row['reservation_id'],
                    'reservation_created_at' => $row['reservation_created_at'],
                    'reservation_title' => $row['reservation_title'],
                    'reservation_description' => $row['reservation_description'],
                    'reservation_start_date' => $row['reservation_start_date'],
                    'reservation_end_date' => $row['reservation_end_date'],
                    'reservation_participants' => $row['reservation_participants'],
                    'additional_note' => $row['additional_note'],
                    'status_name' => $row['status_name'],
                    'active' => $row['active'],
                    'reservation_user_id' => $row['reservation_user_id'],
                    'requester_name' => $row['requester_name'],
                    'department_name' => $row['department_name'],
                    'user_level_name' => $row['user_level_name'],
                    'venues' => $venues,
                    'vehicles' => $vehicles,
                    'equipment' => $equipment,
                    'drivers' => $drivers,
                    'passengers' => $passengers
                ];
            }

            // Non-blocking audit logging for report generation
            try {
                $periodLabel = date('F Y', strtotime($startDate));
                $count = count($finalResults);
                $descBase = $count > 0
                    ? "Reservation Report ({$periodLabel}) generated: {$count} record(s) found"
                    : "Reservation Report ({$periodLabel}) generated: no records found";

                // If personnel ID is provided, fetch and include full name
                if (!empty($user_personnel_id)) {
                    $personnelFullName = null;
                    try {
                        $personSql = "SELECT CONCAT(\n                                    u.users_fname,\n                                    CASE \n                                        WHEN u.users_mname IS NOT NULL AND u.users_mname != '' THEN CONCAT(' ', LEFT(u.users_mname, 1), '.')\n                                        ELSE ''\n                                    END,\n                                    ' ',\n                                    u.users_lname\n                                ) AS full_name\n                             FROM tbl_users u WHERE u.users_id = :id";
                        $personStmt = $this->conn->prepare($personSql);
                        $personStmt->execute([':id' => $user_personnel_id]);
                        $row = $personStmt->fetch(PDO::FETCH_ASSOC);
                        $personnelFullName = $row && !empty($row['full_name']) ? $row['full_name'] : null;
                    } catch (PDOException $e) { /* ignore */ }

                    $nameForLog = $personnelFullName ?? ('User #' . (int)$user_personnel_id);
                    $desc = $descBase . " by: {$nameForLog}";
                } else {
                    $desc = $descBase;
                }

                $auditSql = "INSERT INTO audit_log (description, action, created_at, created_by) VALUES (:description, :action, NOW(), :created_by)";
                $auditStmt = $this->conn->prepare($auditSql);
                $auditStmt->execute([
                    ':description' => $desc,
                    ':action' => 'GENERATE REPORT',
                    ':created_by' => $user_personnel_id
                ]);
            } catch (PDOException $e) { /* ignore logging errors */ }

            return json_encode(['status' => 'success', 'data' => $finalResults]);
        } catch (PDOException $e) {
            return json_encode(['status' => 'error', 'message' => $e->getMessage()]);
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
            v.ven_operating_hours, 
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

    public function fetchAvailableVenues($startDateTime = null, $endDateTime = null, $excludeIds = null) {
        header('Content-Type: application/json');
        try {
            $sql = "
                SELECT 
                    v.ven_id, 
                    v.ven_name, 
                    v.ven_occupancy, 
                    v.ven_created_at, 
                    v.ven_updated_at, 
                    v.status_availability_id,
                    sa.status_availability_name,
                    v.ven_pic
                FROM tbl_venue v
                INNER JOIN tbl_status_availability sa ON v.status_availability_id = sa.status_availability_id
                WHERE v.status_availability_id NOT IN (7, 8)";
            
            // Add exclusion for selected venue IDs if provided
            if (!empty($excludeIds)) {
                if (is_array($excludeIds)) {
                    $placeholders = str_repeat('?,', count($excludeIds) - 1) . '?';
                    $sql .= " AND v.ven_id NOT IN ($placeholders)";
                } else {
                    $sql .= " AND v.ven_id != ?";
                }
            }
            
            $sql .= "
                  AND v.ven_id NOT IN (
                    SELECT DISTINCT 
                        CASE 
                            WHEN (
                                (ls.reservation_status_status_id = 10 AND ls.reservation_active = 1)
                                OR (active_resched.max_reschedule_status_id IS NOT NULL AND ls.reservation_status_status_id = 6 AND ls.reservation_active = 1)
                            ) AND rv.reservation_change_venue_id IS NOT NULL
                            THEN rv.reservation_change_venue_id
                            ELSE rv.reservation_venue_venue_id
                        END AS venue_id_in_use
                    FROM tbl_reservation_venue rv
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
                    ) ls ON ls.reservation_reservation_id = r.reservation_id
                    LEFT JOIN (
                        SELECT reservation_reservation_id, MAX(reservation_status_id) AS max_reschedule_status_id
                        FROM tbl_reservation_status
                        WHERE reservation_status_status_id = 10 AND reservation_active = 1
                        GROUP BY reservation_reservation_id
                    ) active_resched ON active_resched.reservation_reservation_id = r.reservation_id
                    WHERE 
                        (
                            ls.reservation_status_status_id IN (1, 6, 8, 10)
                            AND (
                                (ls.reservation_status_status_id = 6 AND ls.reservation_active = 1)
                                OR (ls.reservation_status_status_id IN (1, 8, 10) AND (ls.reservation_active = 0 OR ls.reservation_active = 1))
                            )
                        )
                        AND (
                            (CASE
                                WHEN (
                                        (ls.reservation_status_status_id = 10 AND ls.reservation_active = 1)
                                        OR (
                                            ls.reservation_status_status_id = 6 AND ls.reservation_active = 1
                                            AND active_resched.max_reschedule_status_id IS NOT NULL
                                        )
                                     )
                                     AND r.reschedule_start_date IS NOT NULL AND r.reschedule_end_date IS NOT NULL
                                THEN r.reschedule_start_date ELSE r.reservation_start_date END) <= :end
                            AND (CASE
                                WHEN (
                                        (ls.reservation_status_status_id = 10 AND ls.reservation_active = 1)
                                        OR (
                                            ls.reservation_status_status_id = 6 AND ls.reservation_active = 1
                                            AND active_resched.max_reschedule_status_id IS NOT NULL
                                        )
                                     )
                                     AND r.reschedule_start_date IS NOT NULL AND r.reschedule_end_date IS NOT NULL
                                THEN r.reschedule_end_date ELSE r.reservation_end_date END) >= :start
                        )
                  )
                ORDER BY v.ven_name
            ";

            $stmt = $this->conn->prepare($sql);
            
            // Prepare parameters for execution
            $params = [':start' => $startDateTime, ':end' => $endDateTime];
            
            // Add exclude IDs to parameters if provided
            if (!empty($excludeIds)) {
                if (is_array($excludeIds)) {
                    foreach ($excludeIds as $index => $id) {
                        $params[] = $id;
                    }
                } else {
                    $params[] = $excludeIds;
                }
            }
            
            $stmt->execute($params);
            $venues = $stmt->fetchAll(PDO::FETCH_ASSOC);

            http_response_code(200);
            echo json_encode([
                'status' => 'success',
                'data' => $venues,
                'timestamp' => date('Y-m-d H:i:s')
            ], JSON_UNESCAPED_SLASHES);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode([
                'status' => 'error',
                'message' => 'Database error: ' . $e->getMessage(),
                'timestamp' => date('Y-m-d H:i:s')
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'status' => 'error',
                'message' => 'General error: ' . $e->getMessage(),
                'timestamp' => date('Y-m-d H:i:s')
            ]);
        }
    }

    public function fetchAvailableVehicles($startDateTime = null, $endDateTime = null, $excludeIds = null) {
        header('Content-Type: application/json');
        try {
            $sql = "
                SELECT  
                    v.vehicle_id,
                    v.vehicle_pic,
                    v.year,
                    vm.vehicle_make_name, 
                    vc.vehicle_category_name,
                    vmd.vehicle_model_name,      
                    v.vehicle_license,
                    sa.status_availability_name
                FROM tbl_vehicle v 
                INNER JOIN tbl_vehicle_model vmd ON v.vehicle_model_id = vmd.vehicle_model_id 
                INNER JOIN tbl_vehicle_make vm ON vmd.vehicle_model_vehicle_make_id = vm.vehicle_make_id 
                INNER JOIN tbl_vehicle_category vc ON vmd.vehicle_category_id = vc.vehicle_category_id
                INNER JOIN tbl_status_availability sa ON v.status_availability_id = sa.status_availability_id
                WHERE v.status_availability_id NOT IN (7, 8)";
            
            // Add exclusion for selected vehicle IDs if provided
            if (!empty($excludeIds)) {
                if (is_array($excludeIds)) {
                    $placeholders = str_repeat('?,', count($excludeIds) - 1) . '?';
                    $sql .= " AND v.vehicle_id NOT IN ($placeholders)";
                } else {
                    $sql .= " AND v.vehicle_id != ?";
                }
            }
            
            $sql .= "
                  AND v.vehicle_id NOT IN (
                    SELECT DISTINCT 
                        CASE 
                            WHEN (
                                (ls.reservation_status_status_id = 10 AND ls.reservation_active = 1)
                                OR (active_resched.max_reschedule_status_id IS NOT NULL AND ls.reservation_status_status_id = 6 AND ls.reservation_active = 1)
                            ) AND rv.reservation_change_vehicle_id IS NOT NULL AND rv.reservation_change_vehicle_id > 0
                            THEN rv.reservation_change_vehicle_id
                            ELSE rv.reservation_vehicle_vehicle_id
                        END AS vehicle_id_in_use
                    FROM tbl_reservation_vehicle rv
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
                    ) ls ON ls.reservation_reservation_id = r.reservation_id
                    LEFT JOIN (
                        SELECT reservation_reservation_id, MAX(reservation_status_id) AS max_reschedule_status_id
                        FROM tbl_reservation_status
                        WHERE reservation_status_status_id = 10 AND reservation_active = 1
                        GROUP BY reservation_reservation_id
                    ) active_resched ON active_resched.reservation_reservation_id = r.reservation_id
                    WHERE 
                        (
                            (ls.reservation_status_status_id = 1 AND (ls.reservation_active = 0 OR ls.reservation_active = 1 OR ls.reservation_active = -1 OR ls.reservation_active IS NULL))
                            OR (ls.reservation_status_status_id = 8 AND (ls.reservation_active = 0 OR ls.reservation_active = 1))
                            OR (ls.reservation_status_status_id = 6 AND ls.reservation_active = 1)
                            OR (ls.reservation_status_status_id = 10 AND (ls.reservation_active = 0 OR ls.reservation_active = 1))
                        )
                        AND r.reservation_id NOT IN (
                            SELECT DISTINCT reservation_reservation_id 
                            FROM tbl_reservation_status 
                            WHERE reservation_status_status_id IN (2, 5)
                        )
                        AND (
                            (CASE
                                WHEN (ls.reservation_status_status_id = 10 AND ls.reservation_active = 1)
                                     AND r.reschedule_start_date IS NOT NULL AND r.reschedule_end_date IS NOT NULL
                                THEN r.reschedule_start_date ELSE r.reservation_start_date END) <= :end
                            AND (CASE
                                WHEN (ls.reservation_status_status_id = 10 AND ls.reservation_active = 1)
                                     AND r.reschedule_start_date IS NOT NULL AND r.reschedule_end_date IS NOT NULL
                                THEN r.reschedule_end_date ELSE r.reservation_end_date END) >= :start
                        )
                  )
                ORDER BY v.vehicle_license
            ";

            $stmt = $this->conn->prepare($sql);
            
            // Prepare parameters for execution
            $params = [':start' => $startDateTime, ':end' => $endDateTime];
            
            // Add exclude IDs to parameters if provided
            if (!empty($excludeIds)) {
                if (is_array($excludeIds)) {
                    foreach ($excludeIds as $index => $id) {
                        $params[] = $id;
                    }
                } else {
                    $params[] = $excludeIds;
                }
            }
            
            $stmt->execute($params);
            $vehicles = $stmt->fetchAll(PDO::FETCH_ASSOC);

            http_response_code(200);
            echo json_encode([
                'status' => 'success',
                'data' => $vehicles,
                'timestamp' => date('Y-m-d H:i:s')
            ], JSON_UNESCAPED_SLASHES);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode([
                'status' => 'error',
                'message' => 'Database error: ' . $e->getMessage(),
                'timestamp' => date('Y-m-d H:i:s')
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'status' => 'error',
                'message' => 'General error: ' . $e->getMessage(),
                'timestamp' => date('Y-m-d H:i:s')
            ]);
        }
    }

    public function fetchAllResources($type = null) {
        // SQL query for vehicles - only fetch vehicle_license and vehicle_model_name (vehicle model title)
        $vehicleQuery = "
            SELECT v.vehicle_license AS vehicle_registration, v.vehicle_id,
                   vm.vehicle_model_name AS vehicle_model_title
            FROM tbl_vehicle v
            INNER JOIN tbl_vehicle_model vm ON v.vehicle_model_id = vm.vehicle_model_id
            LEFT JOIN tbl_checklist_vehicle_master cvm ON v.vehicle_id = cvm.checklist_vehicle_vehicle_id
            WHERE cvm.checklist_vehicle_id IS NULL
        ";
    
        // SQL query for venues - only fetch venue name
        $venueQuery = "
            SELECT ve.ven_name, ve.ven_id
            FROM tbl_venue ve
            LEFT JOIN tbl_checklist_venue_master cvm ON ve.ven_id = cvm.checklist_venue_ven_id
            WHERE cvm.checklist_venue_id IS NULL
        ";
    
        // SQL query for equipment - only fetch equipment name
        $equipmentQuery = "
            SELECT eq.equip_name, eq.equip_id
            FROM tbl_equipments eq
            LEFT JOIN tbl_checklist_equipment_master cem ON eq.equip_id = cem.checklist_equipment_equip_id
            WHERE cem.checklist_equipment_id IS NULL
        ";
    
        try {
            $result = [];
            
            switch ($type) {
                case 'vehicle':
                    $stmt = $this->conn->prepare($vehicleQuery);
                    $stmt->execute();
                    $result['vehicles'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    break;
                case 'venue':
                    $stmt = $this->conn->prepare($venueQuery);
                    $stmt->execute();
                    $result['venues'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    break;
                case 'equipment':
                    $stmt = $this->conn->prepare($equipmentQuery);
                    $stmt->execute();
                    $result['equipment'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    break;
                default:
                    // Fetch all resources if no type specified
                    $stmt = $this->conn->prepare($vehicleQuery);
                    $stmt->execute();
                    $result['vehicles'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
                    $stmt = $this->conn->prepare($venueQuery);
                    $stmt->execute();
                    $result['venues'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
                    $stmt = $this->conn->prepare($equipmentQuery);
                    $stmt->execute();
                    $result['equipment'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
    
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


    public function displayedMaintenanceResources() {
        try {
            $records = [];
    
            // 1) Equipment (Bulk) under maintenance — display qty_bad
            $sql = "
                SELECT 
                    rce.id                             AS record_id,
                    'equipment_bulk'                             AS resource_type,
                    e.equip_name                       AS resource_name,
                    rce.qty_bad                        AS quantity,
                    re.reservation_equipment_equip_id  AS resource_id,
                    c.condition_name                   AS condition_name,
                    rce.remarks                        AS remarks,
                    rce.created_at                     AS created_at,
                    r.reservation_title                AS reservation_title,
                    r.reservation_description          AS reservation_description,
                    r.reservation_start_date           AS reservation_date,
                    CONCAT(
                        COALESCE(t.abbreviation, ''),
                        CASE WHEN t.abbreviation IS NOT NULL THEN ' ' ELSE '' END,
                        COALESCE(u.users_fname, ''),
                        CASE WHEN u.users_fname IS NOT NULL AND u.users_lname IS NOT NULL THEN ' ' ELSE '' END,
                        COALESCE(u.users_lname, ''),
                        CASE WHEN u.users_lname IS NOT NULL AND u.users_suffix IS NOT NULL THEN ' ' ELSE '' END,
                        COALESCE(u.users_suffix, '')
                    ) AS requester_name
                FROM tbl_reservation_condition_equipment rce
                JOIN tbl_reservation_equipment     re ON rce.reservation_equipment_id = re.reservation_equipment_id
                JOIN tbl_reservation               r  ON re.reservation_reservation_id = r.reservation_id
                JOIN tbl_users                     u  ON r.reservation_user_id = u.users_id
                LEFT JOIN titles               t  ON u.title_id = t.id
                JOIN tbl_equipments                e  ON re.reservation_equipment_equip_id = e.equip_id
                JOIN tbl_condition                 c  ON rce.condition_id = c.id
                WHERE rce.condition_id != 2
                  AND rce.is_active = 1
                ORDER BY rce.created_at DESC
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
                    CASE 
                        WHEN EXISTS (
                            SELECT 1 
                              FROM tbl_reservation_status rs 
                             WHERE rs.reservation_reservation_id = r.reservation_id 
                               AND rs.reservation_status_status_id = 10 
                               AND rs.reservation_active = 1
                        )
                         AND rv.reservation_change_venue_id IS NOT NULL
                         AND rv.reservation_change_venue_id > 0
                        THEN rv.reservation_change_venue_id
                        ELSE rv.reservation_venue_venue_id
                    END                              AS resource_id,
                    c.condition_name                  AS condition_name,
                    rcv.remarks                       AS remarks,
                    rcv.created_at                    AS created_at,
                    r.reservation_title               AS reservation_title,
                    r.reservation_description         AS reservation_description,
                    r.reservation_start_date          AS reservation_date,
                    CONCAT(
                        COALESCE(t.abbreviation, ''),
                        CASE WHEN t.abbreviation IS NOT NULL THEN ' ' ELSE '' END,
                        COALESCE(u.users_fname, ''),
                        CASE WHEN u.users_fname IS NOT NULL AND u.users_lname IS NOT NULL THEN ' ' ELSE '' END,
                        COALESCE(u.users_lname, ''),
                        CASE WHEN u.users_lname IS NOT NULL AND u.users_suffix IS NOT NULL THEN ' ' ELSE '' END,
                        COALESCE(u.users_suffix, '')
                    ) AS requester_name
                FROM tbl_reservation_condition_venue rcv
                JOIN tbl_reservation_venue         rv ON rcv.reservation_venue_id = rv.reservation_venue_id
                JOIN tbl_reservation               r  ON rv.reservation_reservation_id = r.reservation_id
                JOIN tbl_users                     u  ON r.reservation_user_id = u.users_id
                LEFT JOIN titles               t  ON u.title_id = t.id
                JOIN tbl_venue                     v  ON v.ven_id = CASE 
                        WHEN EXISTS (
                            SELECT 1 
                              FROM tbl_reservation_status rs 
                             WHERE rs.reservation_reservation_id = r.reservation_id 
                               AND rs.reservation_status_status_id = 10 
                               AND rs.reservation_active = 1
                        )
                         AND rv.reservation_change_venue_id IS NOT NULL
                         AND rv.reservation_change_venue_id > 0
                        THEN rv.reservation_change_venue_id
                        ELSE rv.reservation_venue_venue_id
                    END
                JOIN tbl_condition                 c  ON rcv.condition_id = c.id
                WHERE rcv.condition_id != 2
                  AND rcv.is_active = 1
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
                    NULL                                 AS quantity,
                    CASE 
                        WHEN EXISTS (
                            SELECT 1 
                              FROM tbl_reservation_status rs 
                             WHERE rs.reservation_reservation_id = r.reservation_id 
                               AND rs.reservation_status_status_id IN (6, 10) 
                               AND rs.reservation_active = 1
                        )
                         AND rv.reservation_change_vehicle_id IS NOT NULL
                         AND rv.reservation_change_vehicle_id > 0
                        THEN rv.reservation_change_vehicle_id
                        ELSE rv.reservation_vehicle_vehicle_id
                    END                               AS resource_id,
                    c.condition_name                  AS condition_name,
                    rcvh.remarks                      AS remarks,
                    rcvh.created_at                   AS created_at,
                    r.reservation_title              AS reservation_title,
                    r.reservation_description        AS reservation_description,
                    r.reservation_start_date         AS reservation_date,
                    CONCAT(
                        COALESCE(t.abbreviation, ''),
                        CASE WHEN t.abbreviation IS NOT NULL THEN ' ' ELSE '' END,
                        COALESCE(u.users_fname, ''),
                        CASE WHEN u.users_fname IS NOT NULL AND u.users_lname IS NOT NULL THEN ' ' ELSE '' END,
                        COALESCE(u.users_lname, ''),
                        CASE WHEN u.users_lname IS NOT NULL AND u.users_suffix IS NOT NULL THEN ' ' ELSE '' END,
                        COALESCE(u.users_suffix, '')
                    ) AS requester_name
                FROM tbl_reservation_condition_vehicle rcvh
                JOIN tbl_reservation_vehicle        rv ON rcvh.reservation_vehicle_id = rv.reservation_vehicle_id
                JOIN tbl_reservation                r  ON rv.reservation_reservation_id = r.reservation_id
                JOIN tbl_users                      u  ON r.reservation_user_id = u.users_id
                LEFT JOIN titles                t  ON u.title_id = t.id
                JOIN tbl_vehicle                    vh ON vh.vehicle_id = CASE 
                        WHEN EXISTS (
                            SELECT 1 
                              FROM tbl_reservation_status rs 
                             WHERE rs.reservation_reservation_id = r.reservation_id 
                               AND rs.reservation_status_status_id IN (6, 10) 
                               AND rs.reservation_active = 1
                        )
                         AND rv.reservation_change_vehicle_id IS NOT NULL
                         AND rv.reservation_change_vehicle_id > 0
                        THEN rv.reservation_change_vehicle_id
                        ELSE rv.reservation_vehicle_vehicle_id
                    END
                JOIN tbl_vehicle_model              vm ON vh.vehicle_model_id = vm.vehicle_model_id
                JOIN tbl_condition                  c  ON rcvh.condition_id = c.id
                WHERE rcvh.condition_id != 2
                  AND rcvh.is_active = 1
            ";
            foreach ($this->conn->query($sql, PDO::FETCH_ASSOC) as $row) {
                $records[] = $row;
            }
    
            // 4) Equipment units (Serialized) under maintenance
            $sql = "
                SELECT
                    rcu.id           AS record_id,
                    'equipment_unit' AS resource_type,
                    eu.serial_number AS resource_name,
                    eu.unit_id       AS resource_id,
                    c.condition_name AS condition_name,
                    rcu.remarks      AS remarks,
                    rcu.created_at   AS created_at,
                    r.reservation_title             AS reservation_title,
                    r.reservation_description       AS reservation_description,
                    r.reservation_start_date        AS reservation_date,
                    CONCAT(
                        COALESCE(t.abbreviation, ''),
                        CASE WHEN t.abbreviation IS NOT NULL THEN ' ' ELSE '' END,
                        COALESCE(u.users_fname, ''),
                        CASE WHEN u.users_fname IS NOT NULL AND u.users_lname IS NOT NULL THEN ' ' ELSE '' END,
                        COALESCE(u.users_lname, ''),
                        CASE WHEN u.users_lname IS NOT NULL AND u.users_suffix IS NOT NULL THEN ' ' ELSE '' END,
                        COALESCE(u.users_suffix, '')
                    ) AS requester_name
                FROM tbl_reservation_condition_unit rcu
                JOIN tbl_reservation_unit           ru ON rcu.reservation_unit_id = ru.reservation_unit_id
                JOIN tbl_reservation_equipment      re ON ru.reservation_equipment_id = re.reservation_equipment_id
                JOIN tbl_reservation                r  ON re.reservation_reservation_id = r.reservation_id
                JOIN tbl_users                      u  ON r.reservation_user_id = u.users_id
                LEFT JOIN titles                    t  ON u.title_id = t.id
                JOIN tbl_equipment_unit             eu ON ru.unit_id = eu.unit_id
                JOIN tbl_condition                  c  ON rcu.condition_id = c.id
                WHERE rcu.condition_id != 2
                  AND rcu.is_active = 1
            ";
            foreach ($this->conn->query($sql, PDO::FETCH_ASSOC) as $row) {
                $records[] = $row;
            }
    
            // Sort all records by created_at in descending order
            usort($records, function($a, $b) {
                return strcmp($b['created_at'], $a['created_at']);
            });
    
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

     public function updateResourceStatusAndCondition($type, $resourceId, $recordId, $isFixed = false, $user_personnel_id = null) {
    try {
        $type       = strtolower($type);
        $resourceId = (int)$resourceId;
        $recordId   = (int)$recordId;

        // Mappings for venue/vehicle (unchanged)
        $resourceMap  = [
            'equipment' => ['table' => 'tbl_equipment_unit', 'pk' => 'unit_id'],
            'venue'     => ['table' => 'tbl_venue',          'pk' => 'ven_id'],
            'vehicle'   => ['table' => 'tbl_vehicle',        'pk' => 'vehicle_id'],
        ];
        $conditionMap = [
            'equipment_unit'            => ['table' => 'tbl_reservation_condition_unit',      'fk' => 'reservation_unit_id'],
            'venue'                => ['table' => 'tbl_reservation_condition_venue',     'fk' => 'reservation_venue_id'],
            'vehicle'              => ['table' => 'tbl_reservation_condition_vehicle',   'fk' => 'reservation_vehicle_id'],
            'equipment_bulk'       => ['table' => 'tbl_reservation_condition_equipment', 'fk' => 'reservation_equipment_id'],
        ];

        if (! isset($conditionMap[$type])) {
            return json_encode(['status'=>'error','message'=>'Invalid resource type.']);
        }

        $this->conn->beginTransaction();

        switch ($type) {
            case 'venue':
            case 'vehicle':

                // 1) Update resource availability based on isFixed
                $tbl    = $resourceMap[$type]['table'];
                $pk     = $resourceMap[$type]['pk'];
                $status = $isFixed ? 1 : 2;
                $stmt   = $this->conn->prepare("
                    UPDATE {$tbl}
                       SET status_availability_id = :status
                     WHERE {$pk} = :rid
                ");
                $stmt->execute(['status' => $status, 'rid' => $resourceId]);

                // 2) Deactivate condition record
                $ctbl   = $conditionMap[$type]['table'];
                $stmt   = $this->conn->prepare("
                    UPDATE {$ctbl}
                       SET is_active = 0
                     WHERE id = :cid
                ");
                $stmt->execute(['cid' => $recordId]);
                break;

            case 'equipment_unit':
                // 1) Ensure the condition record exists & is active
                $stmt = $this->conn->prepare("
                    SELECT 1
                      FROM tbl_reservation_condition_unit
                     WHERE id = :cid
                       AND is_active = 1
                ");
                $stmt->execute(['cid' => $recordId]);
                if (! $stmt->fetch()) {
                    throw new Exception("Condition record not found or already inactive.");
                }

                // 2) Update unit availability based on isFixed
                $status = $isFixed ? 1 : 2;
                $update = $this->conn->prepare("
                    UPDATE tbl_equipment_unit
                       SET status_availability_id = :status
                     WHERE unit_id = :uid
                ");
                $update->execute(['status' => $status, 'uid' => $resourceId]);

                // 3) Deactivate the condition record
                $this->conn->prepare("
                    UPDATE tbl_reservation_condition_unit
                       SET is_active = 0
                     WHERE id = :cid
                ")->execute(['cid' => $recordId]);
                break;

            case 'equipment_bulk':
                // Bulk equipment
                // 1) fetch qty_bad
                $stmt = $this->conn->prepare("
                    SELECT qty_bad
                      FROM tbl_reservation_condition_equipment
                     WHERE id = :cid
                       AND is_active = 1
                ");
                $stmt->execute(['cid' => $recordId]);
                $cond = $stmt->fetch(PDO::FETCH_ASSOC);
                if (! $cond) {
                    throw new Exception("Condition record not found or already inactive.");
                }

                if ($isFixed) {
                    // if fixed, update on_hand_quantity
                    if ($cond['qty_bad'] > 0) {
                        // find the row in equipment_quantity
                        $stmt2 = $this->conn->prepare("
                            UPDATE tbl_equipment_quantity
                               SET on_hand_quantity = on_hand_quantity + :b
                             WHERE equip_id = (
                                 SELECT reservation_equipment_equip_id
                                   FROM tbl_reservation_equipment
                                  WHERE reservation_equipment_id = :rid
                             )
                        ");
                        $stmt2->execute([
                            'b'   => $cond['qty_bad'],
                            'rid' => $resourceId
                        ]);
                    }
                }

                // 3) deactivate the condition record
                $this->conn->prepare("
                    UPDATE tbl_reservation_condition_equipment
                       SET is_active = 0
                     WHERE id = :cid
                ")->execute(['cid' => $recordId]);
                break;
        }

        $this->conn->commit();

        // Non-blocking audit logging for admin availability updates
        try {
            // Determine availability label
            $availability = $isFixed ? 'Available' : 'Unavailable';

            // Build resource label based on type
            $resourceTypeLabel = '';
            $resourceLabel = '';
            if ($type === 'venue') {
                $resourceTypeLabel = 'Venue';
                $stmtInfo = $this->conn->prepare("SELECT ven_name FROM tbl_venue WHERE ven_id = :id");
                $stmtInfo->execute([':id' => $resourceId]);
                $rowInfo = $stmtInfo->fetch(PDO::FETCH_ASSOC);
                $resourceLabel = $rowInfo && !empty($rowInfo['ven_name']) ? $rowInfo['ven_name'] : ('ID #' . (int)$resourceId);
            } elseif ($type === 'vehicle') {
                $resourceTypeLabel = 'Vehicle';
                $stmtInfo = $this->conn->prepare("SELECT vehicle_license FROM tbl_vehicle WHERE vehicle_id = :id");
                $stmtInfo->execute([':id' => $resourceId]);
                $rowInfo = $stmtInfo->fetch(PDO::FETCH_ASSOC);
                $resourceLabel = $rowInfo && !empty($rowInfo['vehicle_license']) ? $rowInfo['vehicle_license'] : ('ID #' . (int)$resourceId);
            } elseif ($type === 'equipment_unit') {
                $resourceTypeLabel = 'Equipment Unit';
                $stmtInfo = $this->conn->prepare("SELECT eu.serial_number, e.equip_name FROM tbl_equipment_unit eu LEFT JOIN tbl_equipments e ON eu.equip_id = e.equip_id WHERE eu.unit_id = :id");
                $stmtInfo->execute([':id' => $resourceId]);
                $rowInfo = $stmtInfo->fetch(PDO::FETCH_ASSOC);
                $sn = $rowInfo['serial_number'] ?? null;
                $eqn = $rowInfo['equip_name'] ?? null;
                if ($eqn || $sn) {
                    $resourceLabel = trim(($eqn ? $eqn : '') . ($sn ? (' - SN: ' . $sn) : ''));
                } else {
                    $resourceLabel = 'ID #' . (int)$resourceId;
                }
            }

            if ($resourceTypeLabel !== '') {
                // Fetch personnel name if provided
                $nameForLog = null;
                if (!empty($user_personnel_id)) {
                    try {
                        $personSql = "SELECT CONCAT(\n                                    u.users_fname,\n                                    CASE \n                                        WHEN u.users_mname IS NOT NULL AND u.users_mname != '' THEN CONCAT(' ', LEFT(u.users_mname, 1), '.')\n                                        ELSE ''\n                                    END,\n                                    ' ',\n                                    u.users_lname\n                                ) AS full_name\n                             FROM tbl_users u WHERE u.users_id = :id";
                        $personStmt = $this->conn->prepare($personSql);
                        $personStmt->execute([':id' => $user_personnel_id]);
                        $prow = $personStmt->fetch(PDO::FETCH_ASSOC);
                        $personnelFullName = $prow && !empty($prow['full_name']) ? $prow['full_name'] : null;
                        $nameForLog = $personnelFullName ?? ('User #' . (int)$user_personnel_id);
                    } catch (PDOException $e) { /* ignore */ }
                }

                $desc = sprintf('%s (%s) availability set to %s%s',
                                $resourceTypeLabel,
                                $resourceLabel,
                                $availability,
                                $nameForLog ? (' by: ' . $nameForLog) : '');

                try {
                    $auditSql = "INSERT INTO audit_log (description, action, created_at, created_by) VALUES (:description, :action, NOW(), :created_by)";
                    $auditStmt = $this->conn->prepare($auditSql);
                    $auditStmt->execute([
                        ':description' => $desc,
                        ':action' => 'UPDATE AVAILABILITY',
                        ':created_by' => $user_personnel_id
                    ]);
                } catch (PDOException $e) { /* ignore logging insert errors */ }
            }
        } catch (Throwable $te) { /* ignore all logging errors */ }

        return json_encode(['status'=>'success','message'=>"Updated {$type} and deactivated condition #{$recordId}."]);
    }
    catch (Exception $e) {
        $this->conn->rollBack();
        return json_encode(['status'=>'error','message'=>$e->getMessage()]);
    }
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

public function fetchChecklist() {
    try {
        // SQL query for vehicle checklist with vehicle model name
        $vehicleSql = "
            SELECT 
                cvm.checklist_vehicle_vehicle_id,
                vm.vehicle_model_name AS vehicle_model,  -- Join on vehicle_model_name
                v.vehicle_license AS vehicle_license,    -- License from tbl_vehicle
                COUNT(cvm.checklist_vehicle_vehicle_id) AS vehicle_count
            FROM 
                tbl_checklist_vehicle_master cvm
            JOIN 
                tbl_vehicle v ON cvm.checklist_vehicle_vehicle_id = v.vehicle_id
            JOIN 
                tbl_vehicle_model vm ON v.vehicle_model_id = vm.vehicle_model_id  -- Join on vehicle_model_id
            GROUP BY 
                cvm.checklist_vehicle_vehicle_id, vm.vehicle_model_name, v.vehicle_license
            ORDER BY 
                cvm.checklist_vehicle_vehicle_id
        ";

        // SQL query for venue checklist with venue name
        $venueSql = "
            SELECT 
                cve.checklist_venue_ven_id,
                ve.ven_name AS venue_name,  -- Correct column for venue name
                COUNT(cve.checklist_venue_ven_id) AS venue_count
            FROM 
                tbl_checklist_venue_master cve
            JOIN 
                tbl_venue ve ON cve.checklist_venue_ven_id = ve.ven_id  -- Use ven_id and ven_name from tbl_venue
            GROUP BY 
                cve.checklist_venue_ven_id, ve.ven_name
            ORDER BY 
                cve.checklist_venue_ven_id
        ";

        // SQL query for equipment checklist with equipment name
        $equipmentSql = "
            SELECT 
                ce.checklist_equipment_equip_id,
                eq.equip_name AS equipment_name,
                COUNT(ce.checklist_equipment_equip_id) AS equipment_count
            FROM 
                tbl_checklist_equipment_master ce
            JOIN 
                tbl_equipments eq ON ce.checklist_equipment_equip_id = eq.equip_id
            GROUP BY 
                ce.checklist_equipment_equip_id, eq.equip_name
            ORDER BY 
                ce.checklist_equipment_equip_id
        ";

        // Execute the queries
        $vehicleStmt = $this->conn->prepare($vehicleSql);
        $vehicleStmt->execute();
        $vehicles = $vehicleStmt->fetchAll(PDO::FETCH_ASSOC);

        $venueStmt = $this->conn->prepare($venueSql);
        $venueStmt->execute();
        $venues = $venueStmt->fetchAll(PDO::FETCH_ASSOC);

        $equipmentStmt = $this->conn->prepare($equipmentSql);
        $equipmentStmt->execute();
        $equipment = $equipmentStmt->fetchAll(PDO::FETCH_ASSOC);

        // Format the results with only names, counts, and IDs
        $formattedVehicles = $this->formatChecklistDataWithId($vehicles, 'checklist_vehicle_vehicle_id', 'vehicle_model', 'vehicle_license', 'vehicle');
        $formattedVenues = $this->formatChecklistDataWithId($venues, 'checklist_venue_ven_id', 'venue_name', null, 'venue');
        $formattedEquipment = $this->formatChecklistDataWithId($equipment, 'checklist_equipment_equip_id', 'equipment_name', null, 'equipment');

        // Return the result as a structured JSON response with the updated format
        return json_encode([
            'status' => 'success',
            'data' => [
                'vehicles' => $formattedVehicles,
                'venues' => $formattedVenues,
                'equipment' => $formattedEquipment
            ]
        ]);
    } catch (PDOException $e) {
        return json_encode([
            'status' => 'error',
            'message' => 'Database error: ' . $e->getMessage()
        ]);
    }
}

public function formatChecklistDataWithId($data, $idColumn, $nameColumn, $licenseColumn = null, $type) {
    $formattedData = [];
    foreach ($data as $item) {
        $formattedItem = [
            'id' => $item[$idColumn], // Include the ID in the output
            'name' => $item[$nameColumn],
            'count' => $item[$type . '_count']
        ];
        if ($licenseColumn && isset($item[$licenseColumn])) {
            $formattedItem['license'] = $item[$licenseColumn];
        }
        $formattedData[] = $formattedItem;
    }
    return $formattedData;
}


private function formatChecklistData($data, $nameColumn, $licenseColumn = null, $type) {
    $groupedData = [];
    foreach ($data as $item) {
        $name = $item[$nameColumn];
        if (!isset($groupedData[$name])) {
            $groupedData[$name] = [
                'count' => 0,
                'name' => $name
            ];
        }
        
        // For vehicle, we add the vehicle count (in case of different vehicle models)
        if ($type == 'vehicle') {
            $groupedData[$name]['count'] += (int) $item['vehicle_count'];
        } else if ($type == 'venue') {
            $groupedData[$name]['count'] += (int) $item['venue_count'];
        } else if ($type == 'equipment') {
            $groupedData[$name]['count'] += (int) $item['equipment_count'];
        }
    }

    // Return the formatted data with name and count
    return array_map(function($item) {
        return [
            'name' => $item['name'],
            'count' => $item['count']
        ];
    }, $groupedData);
}

public function fetchChecklistById($type, $id) {
    try {
        if (empty($type) || empty($id)) {
            return json_encode([
                'status' => 'error',
                'message' => 'Type or ID is missing.'
            ]);
        }

        $sql = '';
        $idColumn = '';
        $checklistTable = '';
        $checklistIdColumn = '';

        switch ($type) {
            case 'vehicle':
                $checklistTable = 'tbl_checklist_vehicle_master';
                $idColumn = 'checklist_vehicle_vehicle_id';
                $checklistIdColumn = 'checklist_vehicle_id';
                break;
            case 'venue':
                $checklistTable = 'tbl_checklist_venue_master';
                $idColumn = 'checklist_venue_ven_id';
                $checklistIdColumn = 'checklist_venue_id';
                break;
            case 'equipment':
                $checklistTable = 'tbl_checklist_equipment_master';
                $idColumn = 'checklist_equipment_equip_id';
                $checklistIdColumn = 'checklist_equipment_id';
                break;
            default:
                return json_encode([
                    'status' => 'error',
                    'message' => 'Invalid type specified.'
                ]);
        }

        $sql = "
            SELECT 
                $checklistIdColumn AS checklist_id,
                checklist_name,
                $idColumn AS foreign_id
            FROM 
                $checklistTable
            WHERE 
                $idColumn = :id
        ";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':id' => $id]);
        $checklists = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($checklists)) {
            return json_encode([
                'status' => 'error',
                'message' => 'No checklists found for the given ID.'
            ]);
        }

        return json_encode([
            'status' => 'success',
            'data' => $checklists
        ]);
    } catch (PDOException $e) {
        return json_encode([
            'status' => 'error',
            'message' => 'Database error: ' . $e->getMessage()
        ]);
    }
}

public function updateChecklist($data) {
    try {
        if (empty($data['checklist_updates']) || !is_array($data['checklist_updates'])) {
            return json_encode([
                'status' => 'error',
                'message' => 'Missing or invalid checklist updates.'
            ]);
        }

        $this->conn->beginTransaction();
        $results = [];
        $auditItems = [];
        $user_personnel_id = $data['user_personnel_id'] ?? null;

        foreach ($data['checklist_updates'] as $update) {
            if (empty($update['type']) || !isset($update['id']) || empty($update['checklist_name'])) {
                continue;
            }

            $updateSql = '';
            $params = [
                ':id' => $update['id'],
                ':name' => $update['checklist_name']
            ];

            switch ($update['type']) {
                case 'venue':
                    // Fetch current name and resource foreign id for logging
                    try {
                        $sel = $this->conn->prepare("SELECT checklist_name, checklist_venue_ven_id AS fk FROM tbl_checklist_venue_master WHERE checklist_venue_id = :id");
                        $sel->execute([':id' => $update['id']]);
                        $row = $sel->fetch(PDO::FETCH_ASSOC);
                        if ($row) {
                            $auditItems[] = [
                                'type' => 'venue',
                                'fk'   => (int)($row['fk'] ?? 0),
                                'old'  => $row['checklist_name'] ?? '',
                                'new'  => $update['checklist_name']
                            ];
                        }
                    } catch (PDOException $e) { /* ignore */ }
                    $updateSql = "UPDATE tbl_checklist_venue_master 
                                SET checklist_name = :name 
                                WHERE checklist_venue_id = :id";
                    break;

                case 'vehicle':
                    // Fetch current name and resource foreign id for logging
                    try {
                        $sel = $this->conn->prepare("SELECT checklist_name, checklist_vehicle_vehicle_id AS fk FROM tbl_checklist_vehicle_master WHERE checklist_vehicle_id = :id");
                        $sel->execute([':id' => $update['id']]);
                        $row = $sel->fetch(PDO::FETCH_ASSOC);
                        if ($row) {
                            $auditItems[] = [
                                'type' => 'vehicle',
                                'fk'   => (int)($row['fk'] ?? 0),
                                'old'  => $row['checklist_name'] ?? '',
                                'new'  => $update['checklist_name']
                            ];
                        }
                    } catch (PDOException $e) { /* ignore */ }
                    $updateSql = "UPDATE tbl_checklist_vehicle_master 
                                SET checklist_name = :name 
                                WHERE checklist_vehicle_id = :id";
                    break;

                case 'equipment':
                    // Fetch current name and resource foreign id for logging
                    try {
                        $sel = $this->conn->prepare("SELECT checklist_name, checklist_equipment_equip_id AS fk FROM tbl_checklist_equipment_master WHERE checklist_equipment_id = :id");
                        $sel->execute([':id' => $update['id']]);
                        $row = $sel->fetch(PDO::FETCH_ASSOC);
                        if ($row) {
                            $auditItems[] = [
                                'type' => 'equipment',
                                'fk'   => (int)($row['fk'] ?? 0),
                                'old'  => $row['checklist_name'] ?? '',
                                'new'  => $update['checklist_name']
                            ];
                        }
                    } catch (PDOException $e) { /* ignore */ }
                    $updateSql = "UPDATE tbl_checklist_equipment_master 
                                SET checklist_name = :name 
                                WHERE checklist_equipment_id = :id";
                    break;

                default:
                    continue 2;
            }

            $stmt = $this->conn->prepare($updateSql);
            $success = $stmt->execute($params);
            $results[] = [
                'id' => $update['id'],
                'type' => $update['type'],
                'success' => $success
            ];
        }

        $this->conn->commit();

        // Non-blocking audit logging after successful commit
        try {
            // Resolve personnel full name once
            $nameForLog = null;
            if (!empty($user_personnel_id)) {
                try {
                    $personSql = "SELECT CONCAT(
                                        users_fname,
                                        CASE 
                                            WHEN users_mname IS NOT NULL AND users_mname != '' THEN CONCAT(' ', LEFT(users_mname, 1), '.')
                                            ELSE ''
                                        END,
                                        ' ',
                                        users_lname
                                    ) AS full_name
                                 FROM tbl_users WHERE users_id = :id";
                    $personStmt = $this->conn->prepare($personSql);
                    $personStmt->execute([':id' => $user_personnel_id]);
                    $prow = $personStmt->fetch(PDO::FETCH_ASSOC);
                    $personnelFullName = $prow && !empty($prow['full_name']) ? $prow['full_name'] : null;
                    $nameForLog = $personnelFullName ?? ('User #' . (int)$user_personnel_id);
                } catch (PDOException $e) { /* ignore */ }
            }

            foreach ($auditItems as $ai) {
                $typeLower = strtolower($ai['type']);
                $resourceLabel = '';
                if ($typeLower === 'venue') {
                    $stmtInfo = $this->conn->prepare("SELECT ven_name FROM tbl_venue WHERE ven_id = :id");
                    $stmtInfo->execute([':id' => $ai['fk']]);
                    $row = $stmtInfo->fetch(PDO::FETCH_ASSOC);
                    $resourceLabel = $row && !empty($row['ven_name']) ? $row['ven_name'] : ('ID #' . (int)$ai['fk']);
                } elseif ($typeLower === 'equipment') {
                    $stmtInfo = $this->conn->prepare("SELECT equip_name FROM tbl_equipments WHERE equip_id = :id");
                    $stmtInfo->execute([':id' => $ai['fk']]);
                    $row = $stmtInfo->fetch(PDO::FETCH_ASSOC);
                    $resourceLabel = $row && !empty($row['equip_name']) ? $row['equip_name'] : ('ID #' . (int)$ai['fk']);
                } elseif ($typeLower === 'vehicle') {
                    $stmtInfo = $this->conn->prepare(
                        "SELECT mk.vehicle_make_name AS make, vm.vehicle_model_name AS model, v.vehicle_license AS license
                         FROM tbl_vehicle v
                         JOIN tbl_vehicle_model vm ON v.vehicle_model_id = vm.vehicle_model_id
                         JOIN tbl_vehicle_make mk ON vm.vehicle_model_vehicle_make_id = mk.vehicle_make_id
                         WHERE v.vehicle_id = :id"
                    );
                    $stmtInfo->execute([':id' => $ai['fk']]);
                    $row = $stmtInfo->fetch(PDO::FETCH_ASSOC);
                    if ($row && (!empty($row['make']) || !empty($row['model']) || !empty($row['license']))) {
                        $resourceLabel = trim(($row['make'] ?? '') . ' ' . ($row['model'] ?? ''));
                        if (!empty($row['license'])) {
                            $resourceLabel .= ' (' . $row['license'] . ')';
                        }
                        $resourceLabel = trim($resourceLabel);
                    } else {
                        $resourceLabel = 'ID #' . (int)$ai['fk'];
                    }
                }

                $desc = sprintf("Updated Checklist in %s %s: '%s' -> '%s'%s",
                                $typeLower,
                                $resourceLabel,
                                (string)$ai['old'],
                                (string)$ai['new'],
                                $nameForLog ? (' by: ' . $nameForLog) : '');

                try {
                    $auditSql = "INSERT INTO audit_log (description, action, created_at, created_by) VALUES (:description, :action, NOW(), :created_by)";
                    $auditStmt = $this->conn->prepare($auditSql);
                    $auditStmt->execute([
                        ':description' => $desc,
                        ':action' => 'UPDATE CHECKLIST',
                        ':created_by' => $user_personnel_id
                    ]);
                } catch (PDOException $e) { /* ignore insert errors */ }
            }
        } catch (Throwable $te) { /* ignore all logging errors */ }

        return json_encode([
            'status' => 'success',
            'message' => 'Checklists updated successfully.',
            'updates' => $results
        ]);

    } catch (PDOException $e) {
        $this->conn->rollBack();
        return json_encode([
            'status' => 'error', 
            'message' => 'Database error: ' . $e->getMessage()
        ]);
    }
}

public function saveMasterChecklist($checklistNames, $type, $id, $user_personnel_id = null) {
    try {


        $this->conn->beginTransaction();

        // Determine which table to use based on type
        switch ($type) {
            case 'vehicle':
                $sql = "INSERT INTO tbl_checklist_vehicle_master (checklist_name, checklist_vehicle_vehicle_id) VALUES (:name, :id)";
                break;
            case 'venue':
                $sql = "INSERT INTO tbl_checklist_venue_master (checklist_name, checklist_venue_ven_id) VALUES (:name, :id)";
                break;
            case 'equipment':
                $sql = "INSERT INTO tbl_checklist_equipment_master (checklist_name, checklist_equipment_equip_id) VALUES (:name, :id)";
                break;
            default:
                return json_encode([
                    'status' => 'error',
                    'message' => 'Invalid resource type'
                ]);
        }

        $stmt = $this->conn->prepare($sql);
        
        // Insert each checklist item
        foreach ($checklistNames as $name) {
            $stmt->execute([
                ':name' => $name,
                ':id' => $id
            ]);
        }

        $this->conn->commit();

        // Non-blocking audit logging of the master checklist creation
        try {
            $count = is_array($checklistNames) ? count($checklistNames) : 0;
            $typeLower = strtolower($type);
            $resourceLabel = '';

            if ($typeLower === 'venue') {
                $stmtInfo = $this->conn->prepare("SELECT ven_name FROM tbl_venue WHERE ven_id = :id");
                $stmtInfo->execute([':id' => $id]);
                $row = $stmtInfo->fetch(PDO::FETCH_ASSOC);
                $resourceLabel = $row && !empty($row['ven_name']) ? $row['ven_name'] : ('ID #' . (int)$id);
            } elseif ($typeLower === 'equipment') {
                $stmtInfo = $this->conn->prepare("SELECT equip_name FROM tbl_equipments WHERE equip_id = :id");
                $stmtInfo->execute([':id' => $id]);
                $row = $stmtInfo->fetch(PDO::FETCH_ASSOC);
                $resourceLabel = $row && !empty($row['equip_name']) ? $row['equip_name'] : ('ID #' . (int)$id);
            } elseif ($typeLower === 'vehicle') {
                $stmtInfo = $this->conn->prepare(
                    "SELECT mk.vehicle_make_name AS make, vm.vehicle_model_name AS model, v.vehicle_license AS license
                     FROM tbl_vehicle v
                     JOIN tbl_vehicle_model vm ON v.vehicle_model_id = vm.vehicle_model_id
                     JOIN tbl_vehicle_make mk ON vm.vehicle_model_vehicle_make_id = mk.vehicle_make_id
                     WHERE v.vehicle_id = :id"
                );
                $stmtInfo->execute([':id' => $id]);
                $row = $stmtInfo->fetch(PDO::FETCH_ASSOC);
                if ($row && (!empty($row['make']) || !empty($row['model']) || !empty($row['license']))) {
                    $resourceLabel = trim(($row['make'] ?? '') . ' ' . ($row['model'] ?? ''));
                    if (!empty($row['license'])) {
                        $resourceLabel .= ' (' . $row['license'] . ')';
                    }
                    $resourceLabel = trim($resourceLabel);
                } else {
                    $resourceLabel = 'ID #' . (int)$id;
                }
            }

            // Fetch personnel full name if provided
            $nameForLog = null;
            if (!empty($user_personnel_id)) {
                try {
                    $personSql = "SELECT CONCAT(\n                                users_fname,\n                                CASE \n                                    WHEN users_mname IS NOT NULL AND users_mname != '' THEN CONCAT(' ', LEFT(users_mname, 1), '.')\n                                    ELSE ''\n                                END,\n                                ' ',\n                                users_lname\n                            ) AS full_name\n                         FROM tbl_users WHERE users_id = :id";
                    $personStmt = $this->conn->prepare($personSql);
                    $personStmt->execute([':id' => $user_personnel_id]);
                    $prow = $personStmt->fetch(PDO::FETCH_ASSOC);
                    $personnelFullName = $prow && !empty($prow['full_name']) ? $prow['full_name'] : null;
                    $nameForLog = $personnelFullName ?? ('User #' . (int)$user_personnel_id);
                } catch (PDOException $e) { /* ignore name errors */ }
            }

            $desc = sprintf('Added Checklist to %s %s: No. Checklist (%d)%s',
                            $typeLower,
                            $resourceLabel,
                            $count,
                            $nameForLog ? (' by: ' . $nameForLog) : '');

            try {
                $auditSql = "INSERT INTO audit_log (description, action, created_at, created_by) VALUES (:description, :action, NOW(), :created_by)";
                $auditStmt = $this->conn->prepare($auditSql);
                $auditStmt->execute([
                    ':description' => $desc,
                    ':action' => 'ADD CHECKLIST',
                    ':created_by' => $user_personnel_id
                ]);
            } catch (PDOException $e) { /* ignore insert errors */ }
        } catch (Throwable $te) { /* ignore all logging errors */ }

        return json_encode([
            'status' => 'success',
            'message' => 'Checklist items saved successfully'
        ]);

    } catch (PDOException $e) {
        $this->conn->rollBack();
        return json_encode([
            'status' => 'error',
            'message' => 'Database error: ' . $e->getMessage()
        ]);
    }
}

public function saveChecklist($data) {
    try {
        if (empty($data['checklist_ids']) || !is_array($data['checklist_ids'])) {
            return json_encode([
                'status' => 'error',
                'message' => 'Missing or invalid checklist_ids.'
            ]);
        }

        $this->conn->beginTransaction();
        $results = [];
        // Capture actor for audit after commit
        $admin_id = isset($data['admin_id']) ? $data['admin_id'] : null;
        // Prepare personnel full name once (same personnel_id used for all items in this call)
        $personnelFullName = null;
        try {
            $personSql = "SELECT CONCAT(
                                users_fname,
                                CASE 
                                    WHEN users_mname IS NOT NULL AND users_mname != '' THEN CONCAT(' ', LEFT(users_mname, 1), '.')
                                    ELSE ''
                                END,
                                ' ',
                                users_lname
                            ) AS full_name
                         FROM tbl_users WHERE users_id = :id";
            $personStmt = $this->conn->prepare($personSql);
            $personStmt->execute([':id' => $data['personnel_id']]);
            $row = $personStmt->fetch(PDO::FETCH_ASSOC);
            $personnelFullName = $row && !empty($row['full_name']) ? $row['full_name'] : null;
        } catch (PDOException $e) {
            // If name lookup fails, fallback later
            $personnelFullName = null;
        }

        // Pick a single parent reservation_id to notify (can be passed explicitly)
        $reservationIdForNotif = isset($data['notification_reservation_reservation_id'])
            ? (int)$data['notification_reservation_reservation_id']
            : null;

        foreach ($data['checklist_ids'] as $checklist) {
            $admin_id = $data['admin_id'];
            $personnel_id = $data['personnel_id'];
            $isChecked = null;

            // Common parameters
            $params = [
                ':admin_id' => $admin_id,
                ':personnel_id' => $personnel_id,
                ':isChecked' => $isChecked
            ];

            switch ($checklist['type']) {
                case 'venue':
                    if (empty($checklist['reservation_venue_id']) || empty($checklist['checklist_id'])) {
                        continue 2;
                    }
                    $insertSql = "INSERT INTO tbl_reservation_checklist_venue
                                (reservation_venue_id, checklist_venue_id, admin_id, personnel_id, isChecked)
                                VALUES (:reservation_id, :checklist_id, :admin_id, :personnel_id, :isChecked)";
                    $params[':reservation_id'] = $checklist['reservation_venue_id'];
                    $params[':checklist_id'] = $checklist['checklist_id'];
                    $reservationRefId = $checklist['reservation_venue_id'];
                    // Derive parent reservation_id once if not provided
                    if (!$reservationIdForNotif) {
                        try {
                            $parentSql = "SELECT reservation_reservation_id FROM tbl_reservation_venue WHERE reservation_venue_id = :id";
                            $stmtP = $this->conn->prepare($parentSql);
                            $stmtP->execute([':id' => $reservationRefId]);
                            $parentId = $stmtP->fetchColumn();
                            if ($parentId) { $reservationIdForNotif = (int)$parentId; }
                        } catch (PDOException $e) { /* ignore */ }
                    }
                    break;

                case 'vehicle':
                    if (empty($checklist['reservation_vehicle_id']) || empty($checklist['checklist_id'])) {
                        continue 2;
                    }
                    $insertSql = "INSERT INTO tbl_reservation_checklist_vehicle
                                (reservation_vehicle_id, checklist_vehicle_id, admin_id, personnel_id, isChecked)
                                VALUES (:reservation_id, :checklist_id, :admin_id, :personnel_id, :isChecked)";
                    $params[':reservation_id'] = $checklist['reservation_vehicle_id'];
                    $params[':checklist_id'] = $checklist['checklist_id'];
                    $reservationRefId = $checklist['reservation_vehicle_id'];
                    // Derive parent reservation_id once if not provided
                    if (!$reservationIdForNotif) {
                        try {
                            $parentSql = "SELECT reservation_reservation_id FROM tbl_reservation_vehicle WHERE reservation_vehicle_id = :id";
                            $stmtP = $this->conn->prepare($parentSql);
                            $stmtP->execute([':id' => $reservationRefId]);
                            $parentId = $stmtP->fetchColumn();
                            if ($parentId) { $reservationIdForNotif = (int)$parentId; }
                        } catch (PDOException $e) { /* ignore */ }
                    }
                    break;

                case 'equipment':
                    if (empty($checklist['reservation_equipment_id']) || empty($checklist['checklist_id'])) {
                        continue 2;
                    }
                    $insertSql = "INSERT INTO tbl_reservation_checklist_equipment
                                (reservation_equipment_id, checklist_equipment_id, admin_id, personnel_id, isChecked)
                                VALUES (:reservation_id, :checklist_id, :admin_id, :personnel_id, :isChecked)";
                    $params[':reservation_id'] = $checklist['reservation_equipment_id'];
                    $params[':checklist_id'] = $checklist['checklist_id'];
                    $reservationRefId = $checklist['reservation_equipment_id'];
                    // Derive parent reservation_id once if not provided
                    if (!$reservationIdForNotif) {
                        try {
                            $parentSql = "SELECT reservation_reservation_id FROM tbl_reservation_equipment WHERE reservation_equipment_id = :id";
                            $stmtP = $this->conn->prepare($parentSql);
                            $stmtP->execute([':id' => $reservationRefId]);
                            $parentId = $stmtP->fetchColumn();
                            if ($parentId) { $reservationIdForNotif = (int)$parentId; }
                        } catch (PDOException $e) { /* ignore */ }
                    }
                    break;

                default:
                    continue 2;
            }

            $stmt = $this->conn->prepare($insertSql);
            $stmt->execute($params);
            $results[] = $this->conn->lastInsertId();
        }

        // Insert a single reservation notification into tbl_notification_reservation
        $notifMessageRes = "You have been assigned new checklist tasks.";
        if (!empty($reservationIdForNotif)) {
            try {
                $this->insertNotificationTouser($notifMessageRes, (int)$data['personnel_id'], (int)$reservationIdForNotif);
            } catch (Throwable $te) { /* ignore notification errors */ }
        }

        $this->conn->commit();

        // Single audit log for the entire assignment action (non-blocking, not based on count)
        try {
            $nameForLog = $personnelFullName ?? ('User #' . (int)($data['personnel_id'] ?? 0));
            $desc = "Assigned Checklist to: {$nameForLog}";
            $auditSql = "INSERT INTO audit_log (description, action, created_at, created_by) VALUES (:description, :action, NOW(), :created_by)";
            $auditStmt = $this->conn->prepare($auditSql);
            $auditStmt->execute([
                ':description' => $desc,
                ':action' => 'ASSIGN',
                ':created_by' => $admin_id
            ]);
        } catch (Throwable $te) { /* ignore audit errors */ }

        return json_encode([
            'status' => 'success',
            'message' => 'Checklists saved successfully.',
            'inserted_ids' => $results
        ]);

    } catch (PDOException $e) {
        $this->conn->rollBack();
        return json_encode([
            'status' => 'error',
            'message' => 'Database error: ' . $e->getMessage()
        ]);
    } catch (Exception $e) {
        return json_encode([
            'status' => 'error',
            'message' => $e->getMessage()
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

public function doubleCheckAvailability($startDateTime, $endDateTime) {
    try {
        // Initialize the result array with empty arrays for each resource type
        $result = [
            'reservation_users' => [],
            'unavailable_vehicles' => [],
            'unavailable_venues' => [],
            'unavailable_equipment' => [],
            'unavailable_drivers' => []
        ];

        // --- Query for Reserved Users (reservation_users) ---
        // This query fetches details of users who have reservations that overlap with the given date range
        // and are in 'Approved' status (status_id = 6) and active.
        $userQuery = "
            SELECT DISTINCT
                r.reservation_user_id,
                r.reservation_id,
                r.reservation_start_date,
                r.reservation_end_date,
                r.reservation_title,
                r.reservation_description,
                CONCAT(u.users_fname, ' ', u.users_mname, ' ', u.users_lname) AS full_name,
                ul.user_level_name,
                d.departments_name
            FROM tbl_reservation r
            INNER JOIN tbl_users u ON r.reservation_user_id = u.users_id
            INNER JOIN tbl_reservation_status rs ON r.reservation_id = rs.reservation_reservation_id
            LEFT JOIN tbl_user_level ul ON u.users_user_level_id = ul.user_level_id
            LEFT JOIN tbl_departments d ON u.users_department_id = d.departments_id
            WHERE r.reservation_start_date <= :endDate
            AND r.reservation_end_date >= :startDate
            AND rs.reservation_status_status_id = 6 
            AND rs.reservation_active = 1
        ";
        $stmt = $this->conn->prepare($userQuery);
        $stmt->execute([
            ':startDate' => $startDateTime,
            ':endDate' => $endDateTime
        ]);
        $result['reservation_users'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // --- Query for Unavailable Vehicles ---
        // This query identifies vehicles that are part of approved and active reservations
        // overlapping with the given date range.
        $vehicleQuery = "
            SELECT DISTINCT
                v.vehicle_id,
                v.vehicle_license,
                vm.vehicle_model_name,
                vmake.vehicle_make_name
            FROM tbl_reservation r
            INNER JOIN tbl_reservation_status rs ON r.reservation_id = rs.reservation_reservation_id
            INNER JOIN tbl_reservation_vehicle rv ON r.reservation_id = rv.reservation_reservation_id
            INNER JOIN tbl_vehicle v ON rv.reservation_vehicle_vehicle_id = v.vehicle_id
            INNER JOIN tbl_vehicle_model vm ON v.vehicle_model_id = vm.vehicle_model_id
            INNER JOIN tbl_vehicle_make vmake ON vm.vehicle_model_vehicle_make_id = vmake.vehicle_make_id
            WHERE r.reservation_start_date <= :endDate
            AND r.reservation_end_date >= :startDate
            AND rs.reservation_status_status_id = 6
            AND rs.reservation_active = 1
        ";
        $stmt = $this->conn->prepare($vehicleQuery);
        $stmt->execute([
            ':startDate' => $startDateTime,
            ':endDate' => $endDateTime
        ]);
        $result['unavailable_vehicles'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // --- Query for Unavailable Venues ---
        // This query identifies venues that are part of approved and active reservations
        // overlapping with the given date range.
        $venueQuery = "
            SELECT DISTINCT
                v.ven_id,
                v.ven_name
            FROM tbl_reservation r
            INNER JOIN tbl_reservation_status rs ON r.reservation_id = rs.reservation_reservation_id
            INNER JOIN tbl_reservation_venue rv ON r.reservation_id = rv.reservation_reservation_id
            INNER JOIN tbl_venue v ON rv.reservation_venue_venue_id = v.ven_id
            WHERE r.reservation_start_date <= :endDate
            AND r.reservation_end_date >= :startDate
            AND rs.reservation_status_status_id = 6
            AND rs.reservation_active = 1
        ";
        $stmt = $this->conn->prepare($venueQuery);
        $stmt->execute([
            ':startDate' => $startDateTime,
            ':endDate' => $endDateTime
        ]);
        $result['unavailable_venues'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $equipmentQuery = "
            WITH EquipmentTotalQuantities AS (
                SELECT
                    e.equip_id,
                    CASE
                        -- If there are any units for this equipment in tbl_equipment_unit, it's considered serialized.
                        WHEN EXISTS (SELECT 1 FROM tbl_equipment_unit u WHERE u.equip_id = e.equip_id) THEN (
                            -- For serialized equipment, count the available units.
                            SELECT COUNT(*)
                            FROM tbl_equipment_unit u_inner
                            WHERE u_inner.equip_id = e.equip_id
                        )
                        ELSE (
                            -- For non-serialized equipment, get the quantity from tbl_equipment_quantity.
                            -- LIMIT 1 is used here assuming tbl_equipment_quantity holds a single current quantity per equip_id.
                            -- If it's a history, you might need to order by 'last_updated' and take the latest.
                            SELECT eq.quantity
                            FROM tbl_equipment_quantity eq
                            WHERE eq.equip_id = e.equip_id
                            LIMIT 1
                        )
                    END AS total_quantity
                FROM tbl_equipments e
            )
            SELECT DISTINCT
                e.equip_id,
                e.equip_name,
                COALESCE(etq.total_quantity, 0) AS total_quantity, -- Ensure total_quantity is not null
                COALESCE(SUM(re.reservation_equipment_quantity), 0) AS reserved_quantity
            FROM tbl_equipments e
            INNER JOIN EquipmentTotalQuantities etq ON e.equip_id = etq.equip_id -- Join with the calculated total quantities
            LEFT JOIN tbl_reservation_equipment re ON e.equip_id = re.reservation_equipment_equip_id
            LEFT JOIN tbl_reservation r ON r.reservation_id = re.reservation_reservation_id
            LEFT JOIN tbl_reservation_status rs ON rs.reservation_reservation_id = r.reservation_id
            WHERE
                (r.reservation_start_date <= :endDate AND r.reservation_end_date >= :startDate)
                AND rs.reservation_status_status_id = 6
                AND rs.reservation_active = 1
            GROUP BY e.equip_id, e.equip_name, etq.total_quantity
            HAVING COALESCE(SUM(re.reservation_equipment_quantity), 0) >= COALESCE(etq.total_quantity, 0);

        ";
        $stmt = $this->conn->prepare($equipmentQuery);
        $stmt->execute([
            ':startDate' => $startDateTime,
            ':endDate' => $endDateTime
        ]);
        $result['unavailable_equipment'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // --- Query for Unavailable Drivers ---
        // This query identifies drivers who are assigned to approved and active reservations
        // overlapping with the given date range.
        $driverQuery = "
            SELECT DISTINCT
                rd.reservation_driver_user_id as users_id,
                rd.driver_name as full_name
            FROM tbl_reservation r
            INNER JOIN tbl_reservation_status rs ON r.reservation_id = rs.reservation_reservation_id
            INNER JOIN tbl_reservation_vehicle rv ON r.reservation_id = rv.reservation_reservation_id
            INNER JOIN tbl_reservation_driver rd ON rv.reservation_vehicle_id = rd.reservation_vehicle_id
            WHERE r.reservation_start_date <= :endDate
            AND r.reservation_end_date >= :startDate
            AND rs.reservation_status_status_id = 6
            AND rs.reservation_active = 1
        ";
        $stmt = $this->conn->prepare($driverQuery);
        $stmt->execute([
            ':startDate' => $startDateTime,
            ':endDate' => $endDateTime
        ]);
        $result['unavailable_drivers'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Return the results as a JSON success response
        return json_encode(['status' => 'success', 'data' => $result]);
    } catch (PDOException $e) {
        // Catch any PDO exceptions (database errors) and return an error JSON response
        return json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}

public function insertUnits($equipIds, $quantities, $reservationId, $startDate, $endDate) {
    try {
        $this->conn->beginTransaction();
        $results = [];

        for ($i = 0; $i < count($equipIds); $i++) {
            $equipId = $equipIds[$i];
            $quantity = $quantities[$i];

            // Get equipment type
            $stmtType = $this->conn->prepare("SELECT equip_type FROM tbl_equipments WHERE equip_id = :equip_id");
            $stmtType->execute([':equip_id' => $equipId]);
            $equipData = $stmtType->fetch(PDO::FETCH_ASSOC);

            if (!$equipData) {
                throw new Exception("Equipment ID $equipId not found.");
            }

            $equipType = strtolower($equipData['equip_type']);

            // Get reservation_equipment_id
            $stmtReservationEquip = $this->conn->prepare("SELECT reservation_equipment_id 
                                                          FROM tbl_reservation_equipment 
                                                          WHERE reservation_equipment_equip_id = :equip_id 
                                                          AND reservation_reservation_id = :reservation_id");
            $stmtReservationEquip->execute([
                ':equip_id' => $equipId,
                ':reservation_id' => $reservationId
            ]);
            $reservationEquip = $stmtReservationEquip->fetch(PDO::FETCH_ASSOC);

            if (!$reservationEquip) {
                throw new Exception("No reservation_equipment found for equip_id $equipId and reservation_id $reservationId");
            }

            $reservationEquipmentId = $reservationEquip['reservation_equipment_id'];

            if ($equipType === 'bulk') {
                // Check available quantity (but don't deduct)
                $stmtQty = $this->conn->prepare("SELECT quantity FROM tbl_equipment_quantity WHERE equip_id = :equip_id");
                $stmtQty->execute([':equip_id' => $equipId]);
                $qtyData = $stmtQty->fetch(PDO::FETCH_ASSOC);
                $availableQty = $qtyData ? (int)$qtyData['quantity'] : 0;

                if ($availableQty < $quantity) {
                    throw new Exception("Not enough quantity for bulk equipment ID $equipId. Only $availableQty available.");
                }

                // Commented out quantity deduction for bulk equipment
                // $stmtUpdateQty = $this->conn->prepare("UPDATE tbl_equipment_quantity SET quantity = quantity - :qty WHERE equip_id = :equip_id");
                // $stmtUpdateQty->execute([
                //     ':qty' => $quantity,
                //     ':equip_id' => $equipId
                // ]);

                $results[] = [
                    'equip_id' => $equipId,
                    'reservation_equipment_id' => $reservationEquipmentId,
                    'type' => 'bulk',
                    'quantity_used' => $quantity,
                    'can_release' => true
                ];
            } else {
                // Serialized logic
                $sqlUnits = "
                    SELECT eu.unit_id, eu.serial_number 
                    FROM tbl_equipment_unit eu
                    WHERE eu.equip_id = :equip_id 
                        AND eu.status_availability_id != 2 
                        AND eu.is_active = 1
                        AND eu.unit_id NOT IN (
                            SELECT tru.unit_id
                            FROM tbl_reservation_unit tru
                            JOIN tbl_reservation_equipment tre ON tru.reservation_equipment_id = tre.reservation_equipment_id
                            JOIN tbl_reservation tr ON tre.reservation_reservation_id = tr.reservation_id
                            JOIN tbl_reservation_status trs ON tr.reservation_id = trs.reservation_reservation_id
                            WHERE trs.reservation_status_status_id = 6
                                AND trs.reservation_active = 1
                                AND eu.unit_id = tru.unit_id
                                AND (
                                    (tr.reservation_start_date <= :end_date AND tr.reservation_end_date >= :start_date)
                                )
                        )
                    LIMIT :qty";

                $stmtUnits = $this->conn->prepare($sqlUnits);
                $stmtUnits->bindParam(':equip_id', $equipId, PDO::PARAM_INT);
                $stmtUnits->bindValue(':qty', (int)$quantity, PDO::PARAM_INT);
                $stmtUnits->bindParam(':start_date', $startDate);
                $stmtUnits->bindParam(':end_date', $endDate);
                $stmtUnits->execute();
                $units = $stmtUnits->fetchAll(PDO::FETCH_ASSOC);

                $availableUnits = count($units);

                if ($availableUnits === 0) {
                    $results[] = [
                        'equip_id' => $equipId,
                        'reservation_equipment_id' => $reservationEquipmentId,
                        'type' => 'serialized',
                        'units_inserted' => 0,
                        'units' => [],
                        'can_release' => false,
                        'message' => "No available units for equipment ID $equipId"
                    ];
                    continue;
                }

                // Insert available units
                $stmtInsert = $this->conn->prepare("INSERT INTO tbl_reservation_unit 
                                                    (reservation_equipment_id, unit_id, active) 
                                                    VALUES (:reservation_equipment_id, :unit_id, 0)");
                foreach ($units as $unit) {
                    $stmtInsert->execute([
                        ':reservation_equipment_id' => $reservationEquipmentId,
                        ':unit_id' => $unit['unit_id']
                    ]);
                }

                $results[] = [
                    'equip_id' => $equipId,
                    'reservation_equipment_id' => $reservationEquipmentId,
                    'type' => 'serialized',
                    'units_inserted' => $availableUnits,
                    'units_missing' => max(0, $quantity - $availableUnits),
                    'units' => $units,
                    'can_release' => $availableUnits > 0
                ];
            }
        }

        $this->conn->commit();

        return json_encode([
            'operation' => 'insertUnits',
            'status' => 'success',
            'data' => $results
        ]);
    } catch (Exception $e) {
        $this->conn->rollBack();
        return json_encode([
            'operation' => 'insertUnits',
            'status' => 'error',
            'message' => $e->getMessage()
        ]);
    }
}

public function handleRequest($reservationId, $isAccepted, $userId, $notificationMessage = '', $notification_user_id = null) {
    try {
        $this->conn->beginTransaction();

        if ($isAccepted) {
            $sqlUpdate = "
                UPDATE tbl_reservation_status 
                SET reservation_active = 1 
                WHERE reservation_reservation_id = :reservation_id 
                AND reservation_status_status_id = 1";
            
            $stmtUpdate = $this->conn->prepare($sqlUpdate);
            $stmtUpdate->bindParam(':reservation_id', $reservationId, PDO::PARAM_INT);
            $stmtUpdate->execute();
        } else {
            $sqlUpdate = "
                UPDATE tbl_reservation_status 
                SET reservation_active = -1 
                WHERE reservation_reservation_id = :reservation_id AND reservation_status_status_id = 1";
            $stmtUpdate = $this->conn->prepare($sqlUpdate);
            $reservation_id = $reservationId; // Store in variable
            $stmtUpdate->bindParam(':reservation_id', $reservation_id, PDO::PARAM_INT);
            $stmtUpdate->execute();
            
            $sqlInsert = "
                INSERT INTO tbl_reservation_status 
                (reservation_reservation_id, reservation_status_status_id, reservation_active, reservation_updated_at, reservation_users_id) 
                VALUES (:reservation_id, 2, 1, NOW(), :user_id)";
            
            $stmtInsert = $this->conn->prepare($sqlInsert);
            $reservation_id = $reservationId; // Store in variable
            $user_id = $userId; // Store in variable
            $stmtInsert->bindParam(':reservation_id', $reservation_id, PDO::PARAM_INT);
            $stmtInsert->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            $stmtInsert->execute();
        }

        // Insert notification with proper notification_user_id
        if (!empty($notificationMessage)) {
            $sqlNotification = "INSERT INTO tbl_notification_reservation 
                             (notification_message, notification_reservation_reservation_id, notification_user_id, notification_created_at) 
                             VALUES (:message, :reservation_id, :notification_user_id, NOW())";
            
            $stmtNotification = $this->conn->prepare($sqlNotification);
            $message = $notificationMessage; // Store in variable
            $reservation_id = $reservationId; // Store in variable
            $notification_user = $notification_user_id ?? $userId; // Store in variable
            $stmtNotification->bindParam(':message', $message, PDO::PARAM_STR);
            $stmtNotification->bindParam(':reservation_id', $reservation_id, PDO::PARAM_INT);
            $stmtNotification->bindParam(':notification_user_id', $notification_user, PDO::PARAM_INT);
            $stmtNotification->execute();
        }

        $this->conn->commit();
        
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
                ven_operating_hours,
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
            // Accept payload from various shapes: input.json, input.unitData, input.data, or top-level fields/POST
            $data = $input['json'] ?? ($input['unitData'] ?? ($input['data'] ?? $input ?? null));

            // If string JSON slipped through
            if (is_string($data)) {
                $decoded = json_decode($data, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $data = $decoded;
                }
            }

            // Fallback to POST if still not array
            if (!is_array($data) || empty($data)) {
                $data = $_POST ?? null;
            }

            // Normalize keys (camelCase to snake_case)
            if (is_array($data)) {
                if (!isset($data['unit_id']) && isset($data['unitId'])) {
                    $data['unit_id'] = $data['unitId'];
                }
            }

            if (!is_array($data) || empty($data['unit_id'])) {
                echo json_encode(["status" => "error", "message" => "Invalid payload: unit_id is required"]);
                exit;
            }
            $result = $user->updateEquipmentUnit($data);
            // Method already returns JSON-encoded string
            echo $result;
            break;

        case "handleRequest":
            $reservationId = $input['reservation_id'] ?? null;
            $isAccepted = $input['is_accepted'] ?? false;
            $notificationMessage = $input['notification_message'] ?? '';
            $notificationUserId = $input['notification_user_id'] ?? null;
            if ($reservationId === null) {
                echo json_encode(['status' => 'error', 'message' => 'Reservation ID is required']);
                break;
            }
            echo $user->handleRequest($reservationId, $isAccepted, $userId, $notificationMessage, $notificationUserId);
            break;

        case "insertUnits":
            $equipIds = $input['equip_ids'] ?? [];
            $quantities = $input['quantities'] ?? [];
            $reservationId = $input['reservation_id'] ?? null;
            $startDate = $input['start_date'] ?? null;
            $endDate = $input['end_date'] ?? null;

            if (empty($equipIds) || empty($quantities) || $reservationId === null || $startDate === null || $endDate === null) {
                echo json_encode(['status' => 'error', 'message' => 'Equip IDs, Quantities, Reservation ID, Start Date, and End Date are required']);
                break;
            }

            echo $user->insertUnits($equipIds, $quantities, $reservationId, $startDate, $endDate);
            break;

        case "doubleCheckAvailability":
            $startDateTime = $input['start_datetime'] ?? null;
            $endDateTime = $input['end_datetime'] ?? null;
            if ($startDateTime === null || $endDateTime === null) {
                echo json_encode(['status' => 'error', 'message' => 'Start and end datetime are required']);
                break;
            }
            echo $user->doubleCheckAvailability($startDateTime, $endDateTime);
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
        case "saveChecklist":
            $data = $input['data'] ?? null;
            if (!$data) {
                echo json_encode(['status' => 'error', 'message' => 'No data provided']);
                break;
            }
            echo $user->saveChecklist($data);
            break;

        case "saveMasterChecklist":
            $checklistNames = $input['checklistNames'] ?? [];
            $type = $input['type'] ?? '';
            $id = $input['id'] ?? 0;
            $user_personnel_id = $input['user_personnel_id'] ?? ($_POST['user_personnel_id'] ?? null);
            echo $user->saveMasterChecklist($checklistNames, $type, $id, $user_personnel_id);
            break;

        case "updateChecklist":
            $data = $input['data'] ?? null;
            if (!$data) {
                echo json_encode(['status' => 'error', 'message' => 'No data provided']);
                break;
            }
            // Support user_personnel_id passed at the top-level of the request
            $top_user_pid = $input['user_personnel_id'] ?? ($_POST['user_personnel_id'] ?? null);
            if (is_array($data) && !isset($data['user_personnel_id']) && $top_user_pid !== null) {
                $data['user_personnel_id'] = $top_user_pid;
            }
            echo $user->updateChecklist($data);
            break;

        case "fetchChecklistById":
            $type = $input['type'] ?? '';
            $id = $input['id'] ?? 0;
            echo $user->fetchChecklistById($type, $id);
            break;

        case "fetchChecklist":
            echo $user->fetchChecklist();
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

        case "fetchNoAssignedReservation":
            echo $user->fetchNoAssignedReservation();
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

        case "fetchRequestById":
            $reservationId = $input['reservation_id'] ?? null;
            if ($reservationId === null) {
                echo json_encode(['status' => 'error', 'message' => 'Reservation ID is required']);
                break;
            }
            echo $user->fetchRequestById($reservationId); 
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

        case "fetchApprovalNotification":
            $departmentId = $input['department_id'] ?? null;
            $userLevelId = $input['user_level_id'] ?? null;
            if ($departmentId === null || $userLevelId === null) {
                echo json_encode(['status' => 'error', 'message' => 'Department ID and User Level ID are required']);
                break;
            }
            echo $user->fetchApprovalNotification($departmentId, $userLevelId);
            break;

        case "fetchRequestReservation":
            echo $user->fetchRequestReservation(); 
            break;

        case "insertDriver":
            $reservation_driver_user_id = $input['reservation_driver_user_id'] ?? null;
            $reservation_vehicle_id = $input['reservation_vehicle_id'] ?? null;
            echo $user->insertDriver($reservation_driver_user_id, $reservation_vehicle_id);
            break;

        case "saveDriver":
            echo $user->saveDriver($input);
            break;
        case "saveHoliday":
            $userId = $input['userid'] ?? ($_POST['userid'] ?? null);
            error_log("route saveHoliday userId=" . var_export($userId, true));
            echo $user->saveHoliday($input, $userId);
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

        case "updateVenueReschedule":
            $reservation_venue_id = $input['reservation_venue_id'] ?? ($_POST['reservation_venue_id'] ?? null);
            $reservation_change_venue_id = $input['reservation_change_venue_id'] ?? ($_POST['reservation_change_venue_id'] ?? null);
            if ($reservation_venue_id === null || $reservation_change_venue_id === null) {
                echo json_encode(['status' => 'error', 'message' => 'reservation_venue_id and reservation_change_venue_id are required']);
                break;
            }
            echo $user->updateVenueReschedule($reservation_venue_id, $reservation_change_venue_id);
            break;

        case "updateVehicleReschedule":
            $reservation_vehicle_id = $input['reservation_vehicle_id'] ?? ($_POST['reservation_vehicle_id'] ?? null);
            $reservation_change_vehicle_id = $input['reservation_change_vehicle_id'] ?? ($_POST['reservation_change_vehicle_id'] ?? null);
            if ($reservation_vehicle_id === null || $reservation_change_vehicle_id === null) {
                echo json_encode(['status' => 'error', 'message' => 'reservation_vehicle_id and reservation_change_vehicle_id are required']);
                break;
            }
            echo $user->updateVehicleReschedule($reservation_vehicle_id, $reservation_change_vehicle_id);
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

        case "displayedMaintenanceResources":
            echo $user->displayedMaintenanceResources();
            break;

        case "fetchAllAssignedReleases":
            echo $user->fetchAllAssignedReleases();
            break;

        case "fetchDoneAssignedReleases":
            echo $user->fetchDoneAssignedReleases();
            break;

        case "getBulkUsage":
            $equipId = $input['equipId'] ?? null;
            if ($equipId) {
                echo $user->getBulkUsage($equipId);
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
        case "fetchAvailableDrivers":
            echo $user->fetchAvailableDrivers();
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
        case "fetchVenueHistory":
            $venueId = $input['venue_id'] ?? null;
            echo $user->fetchVenueHistory($venueId);
            break;
        case "fetchVehicleHistory":
            $vehicleId = $input['vehicle_id'] ?? null;
            echo $user->fetchVehicleHistory($vehicleId);
            break;
        case "fetchEquipmentHistory":
            $equipId = $input['equip_id'] ?? null;
            echo $user->fetchEquipmentHistory($equipId);
            break;
        case "fetchEquipmentUnitHistory":
            $unitId = $input['unit_id'] ?? null;
            echo $user->fetchEquipmentUnitHistory($unitId);
            break;
        case "fetchReservationGenerateReport":
            $month = $input['month'] ?? null;
            if ($month === null) {
                echo json_encode(['status' => 'error', 'message' => 'Month is required']);
                break;
            }
            $user_personnel_id = $input['user_personnel_id'] ?? null;
            echo $user->fetchReservationGenerateReport($month, $user_personnel_id);
            break;
        // Remove or fix the incomplete fetchReservation case

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
        
        case "getReservedById":
            $reservationId = $input['reservation_id'] ?? ($_POST['reservation_id'] ?? null);
            if ($reservationId) {
                echo $user->getReservedById($reservationId);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Reservation ID parameter is missing.']);
            }
            break;

        case "updateReservationReschedule":
            $reservation_id = $input['reservation_id'] ?? ($_POST['reservation_id'] ?? null);
            $reschedule_start_date = $input['reschedule_start_date'] ?? ($_POST['reschedule_start_date'] ?? null);
            $reschedule_end_date = $input['reschedule_end_date'] ?? ($_POST['reschedule_end_date'] ?? null);
            $user_admin_id = $input['user_admin_id'] ?? ($_POST['user_admin_id'] ?? null);
            if ($reservation_id === null || $reschedule_start_date === null || $reschedule_end_date === null) {
                echo json_encode(['status' => 'error', 'message' => 'reservation_id, reschedule_start_date, and reschedule_end_date are required']);
                break;
            }
            echo $user->updateReservationReschedule($reservation_id, $reschedule_start_date, $reschedule_end_date, $user_admin_id);
            break;

        case "fetchVenues":
            echo $user->fetchVenues();
            break;
        case "fetchVehicles":
            echo $user->fetchVehicles();
            break;
        case "fetchAllResources":
            $type = $input['type'] ?? ($_POST['type'] ?? null);
            echo $user->fetchAllResources($type);
            break;
        case "updateResourceStatusAndCondition":
            $type = $input['type'] ?? ($_POST['type'] ?? null);
            $resourceId = $input['resourceId'] ?? ($_POST['resourceId'] ?? null);
            $recordId = $input['recordId'] ?? ($_POST['recordId'] ?? null);
            $isFixed = $input['isFixed'] ?? ($_POST['isFixed'] ?? false);
            $user_personnel_id = $input['user_personnel_id'] ?? ($_POST['user_personnel_id'] ?? null);
            if ($type && $resourceId && $recordId) {
                echo $user->updateResourceStatusAndCondition($type, $resourceId, $recordId, $isFixed, $user_personnel_id);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Missing required parameters (type, resourceId, recordId)']);
            }
            break;
        case "hasConflictRequest":
            // Accept multiple possible parameter names for flexibility
            $resourceType = $input['resourceType']
                ?? $input['itemType']
                ?? ($_POST['resourceType'] ?? ($_POST['itemType'] ?? null));

            $resourceId = $input['resourceId']
                ?? $input['id']
                ?? ($_POST['resourceId'] ?? ($_POST['id'] ?? null));

            $startDate = $input['startDate']
                ?? $input['start_date']
                ?? $input['startDateTime']
                ?? ($_POST['startDate'] ?? ($_POST['start_date'] ?? ($_POST['startDateTime'] ?? null)));

            $endDate = $input['endDate']
                ?? $input['end_date']
                ?? $input['endDateTime']
                ?? ($_POST['endDate'] ?? ($_POST['end_date'] ?? ($_POST['endDateTime'] ?? null)));

            // Quantity only applies to equipment, but allow optional for others
            $requestedQuantity = $input['requestedQuantity']
                ?? $input['quantity']
                ?? ($_POST['requestedQuantity'] ?? ($_POST['quantity'] ?? 0));
            // Optional per-equipment quantities map or array
            $requestedQuantitiesMap = $input['requestedQuantities']
                ?? $input['quantitiesMap']
                ?? ($_POST['requestedQuantities'] ?? ($_POST['quantitiesMap'] ?? null));

            if (!$resourceType || !$resourceId || !$startDate || !$endDate) {
                echo json_encode(['status' => 'error', 'message' => 'Missing required parameters (resourceType, resourceId, startDate, endDate)']);
                break;
            }
            
            // Handle batch requests if arrays are provided
            $isTypeArray = is_array($resourceType);
            $isIdArray = is_array($resourceId);
            $isQtyArray = is_array($requestedQuantity);
            $isQtyMap   = is_array($requestedQuantitiesMap);

            if ($isTypeArray || $isIdArray) {
                // Normalize arrays
                $types = $isTypeArray ? $resourceType : [];
                $ids   = $isIdArray ? $resourceId : [];

                // If one is scalar and the other is array, broadcast the scalar to match length
                if (!$isTypeArray && $isIdArray) {
                    $types = array_fill(0, count($ids), $resourceType);
                }
                if ($isTypeArray && !$isIdArray) {
                    $ids = array_fill(0, count($types), $resourceId);
                }

                if (count($types) !== count($ids)) {
                    echo json_encode(['status' => 'error', 'message' => 'resourceType and resourceId arrays must have the same length']);
                    break;
                }

                $results = [];
                foreach ($types as $i => $typeVal) {
                    $typeVal = is_string($typeVal) ? strtolower($typeVal) : $typeVal;
                    $idVal = (int)$ids[$i];

                    // Resolve quantity for equipment
                    $qtyForItem = 0;
                    if ($typeVal === 'equipment') {
                        if ($isQtyMap && isset($requestedQuantitiesMap[$idVal])) {
                            $qtyForItem = (int)$requestedQuantitiesMap[$idVal];
                        } elseif ($isQtyArray && isset($requestedQuantity[$i])) {
                            $qtyForItem = (int)$requestedQuantity[$i];
                        } elseif (!$isQtyArray && !$isQtyMap) {
                            // Fallback to scalar requestedQuantity if present and there is only one equipment item
                            // Otherwise, require an explicit quantity per equipment
                            $qtyForItem = (int)$requestedQuantity;
                        }

                        if ($qtyForItem === 0) {
                            $results[] = [
                                'resourceType' => $typeVal,
                                'resourceId' => $idVal,
                                'error' => 'requestedQuantity is required for equipment'
                            ];
                            continue;
                        }
                    }

                    $res = $user->hasConflictRequest($typeVal, $idVal, $startDate, $endDate, $qtyForItem);
                    $results[] = [
                        'resourceType' => $typeVal,
                        'resourceId' => $idVal,
                        'result' => $res
                    ];
                }

                echo json_encode(['status' => 'success', 'results' => $results]);
                break;
            }

            // Single item path (backward compatible)
            if ($resourceType === 'equipment' && ($requestedQuantity === null || $requestedQuantity === '' || (int)$requestedQuantity === 0)) {
                echo json_encode(['status' => 'error', 'message' => 'requestedQuantity is required for equipment']);
                break;
            }

            $result = $user->hasConflictRequest($resourceType, (int)$resourceId, $startDate, $endDate, (int)$requestedQuantity);
            echo json_encode($result);
            break;

        case "fetchAvailableVenues":
            // Accept both camelCase and snake_case for flexibility
            $startDateTime = $input['startDateTime']
                ?? $input['start_datetime']
                ?? ($_POST['startDateTime'] ?? ($_POST['start_datetime'] ?? null));
            $endDateTime = $input['endDateTime']
                ?? $input['end_datetime']
                ?? ($_POST['endDateTime'] ?? ($_POST['end_datetime'] ?? null));
            if ($startDateTime === null || $endDateTime === null) {
                echo json_encode(['status' => 'error', 'message' => 'startDateTime and endDateTime are required']);
                break;
            }
            // Method echoes JSON and sets headers internally
            $user->fetchAvailableVenues($startDateTime, $endDateTime);
            break;

        case "fetchAvailableVehicles":
            // Accept both camelCase and snake_case for flexibility
            $startDateTime = $input['startDateTime']
                ?? $input['start_datetime']
                ?? ($_POST['startDateTime'] ?? ($_POST['start_datetime'] ?? null));
            $endDateTime = $input['endDateTime']
                ?? $input['end_datetime']
                ?? ($_POST['endDateTime'] ?? ($_POST['end_datetime'] ?? null));
            if ($startDateTime === null || $endDateTime === null) {
                echo json_encode(['status' => 'error', 'message' => 'startDateTime and endDateTime are required']);
                break;
            }
            // Method echoes JSON and sets headers internally
            $user->fetchAvailableVehicles($startDateTime, $endDateTime);
            break;
        default:
            echo json_encode(['status' => 'error', 'message' => 'Invalid operation']);
            break;
    }
}
?>
