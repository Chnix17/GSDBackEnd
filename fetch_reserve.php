<?php
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
error_reporting(0);

error_reporting(E_ALL & ~E_NOTICE);

// Log POST data
error_log("POST data: " . print_r($_POST, true));

// Check if 'operation' key exists before logging it
if (isset($_POST['operation'])) {
    error_log("Operation received: " . $_POST['operation']);
}

class User {
    private $conn;

    public function __construct() {
        include 'connection-pdo.php'; // Ensure this file contains the correct PDO connection
        $this->conn = $conn;
    }

    // Fetch all pending reservations for a specific user
    public function fetchPendingReservations($userId) {
        try {
            $sql = "
                SELECT 
                    r.reservation_id, 
                    r.reservation_name, 
                    r.reservation_event_title, 
                    r.reservation_description, 
                    r.reservation_start_date, 
                    r.reservation_end_date, 
                    r.reservation_status_master_id, 
                    sm.status_master_name AS reservation_status_name,
                    r.reservations_users_id,
                    GROUP_CONCAT(DISTINCT e.equip_name) AS equipment_names,
                    GROUP_CONCAT(DISTINCT v.ven_name) AS venue_names,
                    GROUP_CONCAT(DISTINCT veh.vehicle_id) AS vehicle_ids
                FROM 
                    tbl_reservations r
                LEFT JOIN 
                    tbl_reservation_equipment re ON r.reservation_id = re.reservation_id
                LEFT JOIN 
                    tbl_equipments e ON re.equipments_reservation_equip_id = e.equip_id
                LEFT JOIN 
                    tbl_reservation_venue rv ON r.reservation_id = rv.reservation_id
                LEFT JOIN 
                    tbl_venue v ON rv.venue_reservation_ven_id = v.ven_id
                LEFT JOIN 
                    tbl_reservation_vehicle rvh ON r.reservation_id = rvh.reservation_id
                LEFT JOIN 
                    tbl_vehicle veh ON rvh.vehicle_reservation_vehicle_id = veh.vehicle_id
                INNER JOIN 
                    tbl_status_master sm ON r.reservation_status_master_id = sm.status_master_id
                WHERE 
                    r.reservation_status_master_id = 2 
                    AND r.reservations_users_id = :user_id
                GROUP BY 
                    r.reservation_id;
            ";

            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':user_id', $userId);
            $stmt->execute();

            $reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return json_encode(['status' => 'success', 'data' => $reservations]);
        } catch (PDOException $e) {
            return json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }

    // Fetch all pending reservations for all users
    public function fetchAllPendingReservations() {
        try {
            $sql = "
                SELECT 
                    r.reservation_id, 
                    r.reservation_name, 
                    r.reservation_event_title, 
                    r.reservation_description, 
                    r.reservation_start_date, 
                    r.reservation_end_date, 
                    r.reservation_status_master_id, 
                    sm.status_master_name AS reservation_status_name,
                    r.reservations_users_id,
                    GROUP_CONCAT(DISTINCT e.equip_name) AS equipment_names,
                    GROUP_CONCAT(DISTINCT v.ven_name) AS venue_names,
                    GROUP_CONCAT(DISTINCT veh.vehicle_id) AS vehicle_ids
                FROM 
                    tbl_reservations r
                LEFT JOIN 
                    tbl_reservation_equipment re ON r.reservation_id = re.reservation_id
                LEFT JOIN 
                    tbl_equipments e ON re.equipments_reservation_equip_id = e.equip_id
                LEFT JOIN 
                    tbl_reservation_venue rv ON r.reservation_id = rv.reservation_id
                LEFT JOIN 
                    tbl_venue v ON rv.venue_reservation_ven_id = v.ven_id
                LEFT JOIN 
                    tbl_reservation_vehicle rvh ON r.reservation_id = rvh.reservation_id
                LEFT JOIN 
                    tbl_vehicle veh ON rvh.vehicle_reservation_vehicle_id = veh.vehicle_id
                INNER JOIN 
                    tbl_status_master sm ON r.reservation_status_master_id = sm.status_master_id
                WHERE 
                    r.reservation_status_master_id = 2
                GROUP BY 
                    r.reservation_id;
            ";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute();

            $reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return json_encode(['status' => 'success', 'data' => $reservations]);
        } catch (PDOException $e) {
            return json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }

    // Fetch all reservations without filtering by user
    public function fetchAllReservations() {
        try {
            $sql = "
                SELECT 
                    r.reservation_id, 
                    r.reservation_name, 
                    r.reservation_event_title, 
                    r.reservation_description, 
                    r.reservation_start_date, 
                    r.reservation_end_date, 
                    r.reservation_status_master_id, 
                    sm.status_master_name AS reservation_status_name,
                    r.reservations_users_id,
                    GROUP_CONCAT(DISTINCT e.equip_name) AS equipment_names,
                    GROUP_CONCAT(DISTINCT v.ven_name) AS venue_names,
                    GROUP_CONCAT(DISTINCT veh.vehicle_id) AS vehicle_ids,
                    r.date_created
                FROM 
                    tbl_reservations r
                LEFT JOIN 
                    tbl_reservation_equipment re ON r.reservation_id = re.reservation_id
                LEFT JOIN 
                    tbl_equipments e ON re.equipments_reservation_equip_id = e.equip_id
                LEFT JOIN 
                    tbl_reservation_venue rv ON r.reservation_id = rv.reservation_id
                LEFT JOIN 
                    tbl_venue v ON rv.venue_reservation_ven_id = v.ven_id
                LEFT JOIN 
                    tbl_reservation_vehicle rvh ON r.reservation_id = rvh.reservation_id
                LEFT JOIN 
                    tbl_vehicle veh ON rvh.vehicle_reservation_vehicle_id = veh.vehicle_id
                INNER JOIN 
                    tbl_status_master sm ON r.reservation_status_master_id = sm.status_master_id
                GROUP BY 
                    r.reservation_id;
            ";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute();

            $reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return json_encode(['status' => 'success', 'data' => $reservations]);
        } catch (PDOException $e) {
            return json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }

    // Fetch reservation details by reservation ID
    public function getReservationDetailsById($reservationId) {
        try {
            // Fetch reservation details
            $sql = "
                SELECT 
                    r.reservation_id,
                    sm.status_master_name,
                    r.reservation_name,
                    r.reservation_event_title,
                    r.reservation_description,
                    r.reservation_start_date,
                    r.reservation_end_date,
                    r.date_created,
                    CONCAT(u.users_fname, ' ', u.users_mname, ' ', u.users_lname) AS users_full_name,
                    u.users_contact_number,
                    u.users_pic
                FROM 
                    tbl_reservations r
                INNER JOIN 
                    tbl_users u ON r.reservations_users_id = u.users_id
                INNER JOIN 
                    tbl_status_master sm ON r.reservation_status_master_id = sm.status_master_id
                WHERE 
                    r.reservation_id = :reservation_id;
            ";

            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':reservation_id', $reservationId);
            $stmt->execute();
            $reservationDetails = $stmt->fetch(PDO::FETCH_ASSOC);

            // Fetch reservation equipment
            $sqlEquipment = "
                SELECT 
                    e.equip_id,
                    e.equip_name, 
                    re.quantity
                FROM 
                    tbl_reservation_equipment re
                INNER JOIN 
                    tbl_equipments e ON re.equipments_reservation_equip_id = e.equip_id
                WHERE 
                    re.reservation_id = :reservation_id;
            ";
            $stmtEquipment = $this->conn->prepare($sqlEquipment);
            $stmtEquipment->bindParam(':reservation_id', $reservationId);
            $stmtEquipment->execute();
            $equipmentDetails = $stmtEquipment->fetchAll(PDO::FETCH_ASSOC);

            // Fetch reserved vehicles
            $sqlVehicles = "
                SELECT 
                    v.vehicle_id,
                    v.vehicle_license, 
                    rv.vehicle_reservation_vehicle_id
                FROM 
                    tbl_reservation_vehicle rv
                INNER JOIN 
                    tbl_vehicle v ON rv.vehicle_reservation_vehicle_id = v.vehicle_id
                WHERE 
                    rv.reservation_id = :reservation_id;
            ";
            $stmtVehicles = $this->conn->prepare($sqlVehicles);
            $stmtVehicles->bindParam(':reservation_id', $reservationId);
            $stmtVehicles->execute();
            $vehicleDetails = $stmtVehicles->fetchAll(PDO::FETCH_ASSOC);

            // Fetch venue details
            $sqlVenue = "
                SELECT 
                    ven.ven_id,
                    ven.ven_name
                FROM 
                    tbl_reservation_venue rv
                INNER JOIN 
                    tbl_venue ven ON rv.venue_reservation_ven_id = ven.ven_id
                WHERE 
                    rv.reservation_id = :reservation_id;
            ";
            $stmtVenue = $this->conn->prepare($sqlVenue);
            $stmtVenue->bindParam(':reservation_id', $reservationId);
            $stmtVenue->execute();
            $venueDetails = $stmtVenue->fetchAll(PDO::FETCH_ASSOC);

            // Combine all details into one response
            return json_encode([
                'status' => 'success',
                'data' => [
                    'reservation' => $reservationDetails,
                    'equipment' => $equipmentDetails,
                    'vehicles' => $vehicleDetails,
                    'venues' => $venueDetails
                ]
            ]);
        } catch (PDOException $e) {
            return json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }

    public function fetchReleaseFacilities() {
        try {
            $sql = "
                SELECT 
                    r.reservation_id,
                    r.reservation_name,
                    r.reservation_event_title,
                    GROUP_CONCAT(DISTINCT v.vehicle_license) AS vehicle_names,
                    GROUP_CONCAT(DISTINCT e.equip_name) AS equipment_names,
                    GROUP_CONCAT(DISTINCT ven.ven_name) AS venue_names,
                    r.reservation_start_date
                FROM 
                    tbl_reservations r
                INNER JOIN 
                    (
                        SELECT DISTINCT reservation_id 
                        FROM tbl_reservation_vehicle
                        UNION
                        SELECT DISTINCT reservation_id 
                        FROM tbl_reservation_equipment
                        UNION
                        SELECT DISTINCT reservation_id 
                        FROM tbl_reservation_venue
                    ) AS has_facilities ON r.reservation_id = has_facilities.reservation_id
                LEFT JOIN 
                    tbl_reservation_vehicle rv ON r.reservation_id = rv.reservation_id
                LEFT JOIN 
                    tbl_vehicle v ON rv.vehicle_reservation_vehicle_id = v.vehicle_id
                LEFT JOIN 
                    tbl_reservation_equipment re ON r.reservation_id = re.reservation_id
                LEFT JOIN 
                    tbl_equipments e ON re.equipments_reservation_equip_id = e.equip_id
                LEFT JOIN 
                    tbl_reservation_venue rvn ON r.reservation_id = rvn.reservation_id
                LEFT JOIN 
                    tbl_venue ven ON rvn.venue_reservation_ven_id = ven.ven_id
                WHERE 
                    r.reservation_start_date = CURDATE()
                    AND r.reservation_id NOT IN (
                        SELECT DISTINCT reservation_equipment_checklist_reservation_id
                        FROM tbl_release_reservation_equipment
                        UNION
                        SELECT DISTINCT reservation_vehicle_checklist_reservation_id
                        FROM tbl_release_reservation_vehicle
                        UNION
                        SELECT DISTINCT reservation_venue_checklist_reservation_id
                        FROM tbl_release_reservation_venue
                    )
                GROUP BY
                    r.reservation_id
                ORDER BY
                    r.reservation_id
                LIMIT 0, 25
            ";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute();

            $facilities = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return json_encode(['status' => 'success', 'data' => $facilities]);
        } catch (PDOException $e) {
            return json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }
    
    public function insertRelease($reservationId, $personelId = null, $adminId = null) {
        try {
            if (!$reservationId || (!$personelId && !$adminId)) {
                throw new Exception("Missing required parameters");
            }

            // Check if equipment exists for the reservation
            $sqlEquipment = "
                SELECT e.equip_id
                FROM tbl_reservation_equipment re
                INNER JOIN tbl_equipments e ON re.equipments_reservation_equip_id = e.equip_id
                WHERE re.reservation_id = :reservation_id;
            ";
            $stmtEquipment = $this->conn->prepare($sqlEquipment);
            $stmtEquipment->bindParam(':reservation_id', $reservationId);
            $stmtEquipment->execute();
            $equipmentDetails = $stmtEquipment->fetchAll(PDO::FETCH_ASSOC);

            // Check if vehicles exist for the reservation
            $sqlVehicles = "
                SELECT v.vehicle_id
                FROM tbl_reservation_vehicle rv
                INNER JOIN tbl_vehicle v ON rv.vehicle_reservation_vehicle_id = v.vehicle_id
                WHERE rv.reservation_id = :reservation_id;
            ";
            $stmtVehicles = $this->conn->prepare($sqlVehicles);
            $stmtVehicles->bindParam(':reservation_id', $reservationId);
            $stmtVehicles->execute();
            $vehicleDetails = $stmtVehicles->fetchAll(PDO::FETCH_ASSOC);

            // Check if venues exist for the reservation
            $sqlVenues = "
                SELECT ven.ven_id
                FROM tbl_reservation_venue rv
                INNER JOIN tbl_venue ven ON rv.venue_reservation_ven_id = ven.ven_id
                WHERE rv.reservation_id = :reservation_id;
            ";
            $stmtVenues = $this->conn->prepare($sqlVenues);
            $stmtVenues->bindParam(':reservation_id', $reservationId);
            $stmtVenues->execute();
            $venueDetails = $stmtVenues->fetchAll(PDO::FETCH_ASSOC);

            // Adjust the logic for setting personelId and adminId
            if ($adminId) {
                $personelId = null;
            } elseif ($personelId) {
                $adminId = null;
            } else {
                // If neither is provided, use default values
                $adminId = $this->getDefaultAdminId();
                $personelId = $this->getDefaultPersonelId();
            }

            $sqlInsertEquipment = "
                INSERT INTO tbl_release_reservation_equipment 
                (reservation_equipment_checklist_reservation_id, 
                reservation_equipment_checklist_personel_id,
                reservation_equipment_checklist_admin_id)
                VALUES (:reservation_id, :personel_id, :admin_id);
            ";
            $sqlInsertVehicle = "
                INSERT INTO tbl_release_reservation_vehicle 
                (reservation_vehicle_checklist_reservation_id, 
                reservation_vehicle_checklist_personel_id,
                reservation_vehicle_checklist_admin_id)
                VALUES (:reservation_id, :personel_id, :admin_id);
            ";
            $sqlInsertVenue = "
                INSERT INTO tbl_release_reservation_venue 
                (reservation_venue_checklist_reservation_id, 
                reservation_venue_checklist_personel_id,
                reservation_venue_checklist_admin_id)
                VALUES (:reservation_id, :personel_id, :admin_id);
            ";

            // Function to prepare and execute insert statements
            $executeInsert = function($sql, $details) use ($reservationId, $personelId, $adminId) {
                if ($details) {
                    foreach ($details as $detail) {
                        $stmt = $this->conn->prepare($sql);
                        $stmt->bindParam(':reservation_id', $reservationId, PDO::PARAM_INT);
                        $stmt->bindParam(':personel_id', $personelId, PDO::PARAM_INT);
                        $stmt->bindParam(':admin_id', $adminId, PDO::PARAM_INT);
                        $stmt->execute();
                    }
                }
            };

            // Execute inserts for equipment, vehicles, and venues
            $executeInsert($sqlInsertEquipment, $equipmentDetails);
            $executeInsert($sqlInsertVehicle, $vehicleDetails);
            $executeInsert($sqlInsertVenue, $venueDetails);

            return json_encode(['status' => 'success', 'message' => 'Release records inserted successfully.']);
        
        } catch (Exception $e) {
            return json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }
    
    // Add these two new methods to your class
    private function getDefaultAdminId() {
        $stmt = $this->conn->query("SELECT admin_id FROM tbl_admin LIMIT 1");
        return $stmt->fetchColumn() ?: 1; // Return 1 if no admin found
    }

    private function getDefaultPersonelId() {
        $stmt = $this->conn->query("SELECT jo_personel_id FROM tbl_personel LIMIT 1");
        return $stmt->fetchColumn() ?: 1; // Return 1 if no personel found
    }

    public function fetchReturnFacilities() {
        try {
            $sql = "
                SELECT 
                    r.reservation_id,
                    r.reservation_name,
                    r.reservation_event_title,
                    GROUP_CONCAT(DISTINCT v.vehicle_license) AS vehicle_names,
                    GROUP_CONCAT(DISTINCT e.equip_name) AS equipment_names,
                    GROUP_CONCAT(DISTINCT ven.ven_name) AS venue_names,
                    r.reservation_end_date
                FROM 
                    tbl_reservations r
                INNER JOIN 
                    (
                        SELECT DISTINCT reservation_id 
                        FROM tbl_reservation_vehicle
                        UNION
                        SELECT DISTINCT reservation_id 
                        FROM tbl_reservation_equipment
                        UNION
                        SELECT DISTINCT reservation_id 
                        FROM tbl_reservation_venue
                    ) AS has_facilities ON r.reservation_id = has_facilities.reservation_id
                LEFT JOIN 
                    tbl_reservation_vehicle rv ON r.reservation_id = rv.reservation_id
                LEFT JOIN 
                    tbl_vehicle v ON rv.vehicle_reservation_vehicle_id = v.vehicle_id
                LEFT JOIN 
                    tbl_reservation_equipment re ON r.reservation_id = re.reservation_id
                LEFT JOIN 
                    tbl_equipments e ON re.equipments_reservation_equip_id = e.equip_id
                LEFT JOIN 
                    tbl_reservation_venue rvn ON r.reservation_id = rvn.reservation_id
                LEFT JOIN 
                    tbl_venue ven ON rvn.venue_reservation_ven_id = ven.ven_id
                WHERE 
                    r.reservation_end_date <= CURDATE()
                    AND r.reservation_id IN (
                        SELECT DISTINCT reservation_equipment_checklist_reservation_id
                        FROM tbl_release_reservation_equipment
                        UNION
                        SELECT DISTINCT reservation_vehicle_checklist_reservation_id
                        FROM tbl_release_reservation_vehicle
                        UNION
                        SELECT DISTINCT reservation_venue_checklist_reservation_id
                        FROM tbl_release_reservation_venue
                    )
                    AND r.reservation_id NOT IN (
                        SELECT DISTINCT reservation_equipment_checklist_reservation_id
                        FROM tbl_return_reservation_equipment
                        UNION
                        SELECT DISTINCT reservation_vehicle_checklist_reservation_id
                        FROM tbl_return_reservation_vehicle
                        UNION
                        SELECT DISTINCT reservation_venue_checklist_reservation_id
                        FROM tbl_return_reservation_venue
                    )
                GROUP BY
                    r.reservation_id
                ORDER BY
                    r.reservation_id
                LIMIT 0, 25
            ";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute();

            $facilities = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return json_encode(['status' => 'success', 'data' => $facilities]);
        } catch (PDOException $e) {
            return json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }

    // Add this new method to the User class
    public function fetchConditions() {
        try {
            $sql = "SELECT `condition_id`, `condition_name` FROM `tbl_condition_master` WHERE 1";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute();

            $conditions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return json_encode(['status' => 'success', 'data' => $conditions]);
        } catch (PDOException $e) {
            return json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }

    public function insertReturn($reservationId, $personelId = null, $adminId = null, $conditions = [], $returnQuantities = []) {
        try {
            // Validate input parameters
            if (!$reservationId) {
                throw new Exception("Reservation ID is required");
            }
            if (!$personelId && !$adminId) {
                throw new Exception("Either Personnel ID or Admin ID is required");
            }
            if (empty($conditions)) {
                throw new Exception("Conditions are required");
            }

            // Adjust the logic for setting personelId and adminId
            if ($adminId) {
                $personelId = null;
            } elseif ($personelId) {
                $adminId = null;
            } else {
                // If neither is provided, use default values
                $adminId = $this->getDefaultAdminId();
                $personelId = $this->getDefaultPersonelId();
            }

            $this->conn->beginTransaction();

            $sqlInsertEquipment = "
                INSERT INTO tbl_return_reservation_equipment 
                (reservation_equipment_checklist_reservation_id, 
                reservation_equipment_checklist_personel_id,
                reservation_equipment_checklist_condition_id,
                reservation_equipment_checklist_admin_id)
                VALUES (:reservation_id, :personel_id, :condition_id, :admin_id);
            ";
            $sqlInsertVehicle = "
                INSERT INTO tbl_return_reservation_vehicle 
                (reservation_vehicle_checklist_reservation_id, 
                reservation_vehicle_checklist_personel_id,
                reservation_vehicle_checklist_condition_id,
                reservation_vehicle_checklist_admin_id)
                VALUES (:reservation_id, :personel_id, :condition_id, :admin_id);
            ";
            $sqlInsertVenue = "
                INSERT INTO tbl_return_reservation_venue 
                (reservation_venue_checklist_reservation_id, 
                reservation_venue_checklist_personel_id,
                reservation_venue_checklist_condition_id,
                reservation_venue_checklist_admin_id)
                VALUES (:reservation_id, :personel_id, :condition_id, :admin_id);
            ";

            // Update equipment quantity and status
            $sqlUpdateEquipment = "
                UPDATE tbl_equipments 
                SET status_availability_id = 1, 
                    equip_quantity = equip_quantity + :return_quantity 
                WHERE equip_id = :equip_id
            ";

            // Update vehicle status
            $sqlUpdateVehicle = "UPDATE tbl_vehicle SET status_availability_id = 1 WHERE vehicle_id = :vehicle_id";

            // Update venue status
            $sqlUpdateVenue = "UPDATE tbl_venue SET status_availability_id = 1 WHERE ven_id = :ven_id";

            // Function to prepare and execute insert statements
            $executeInsert = function($sql, $type) use ($reservationId, $personelId, $adminId, $conditions, $returnQuantities, $sqlUpdateEquipment, $sqlUpdateVehicle, $sqlUpdateVenue) {
                if (isset($conditions[$type]) && is_array($conditions[$type])) {
                    foreach ($conditions[$type] as $itemId => $conditionId) {
                        $stmt = $this->conn->prepare($sql);
                        $stmt->bindParam(':reservation_id', $reservationId, PDO::PARAM_INT);
                        $stmt->bindParam(':personel_id', $personelId, PDO::PARAM_INT);
                        $stmt->bindParam(':admin_id', $adminId, PDO::PARAM_INT);
                        $stmt->bindParam(':condition_id', $conditionId, PDO::PARAM_INT);
                        $stmt->execute();

                        // Update item status and quantity
                        switch ($type) {
                            case 'equipment':
                                $returnQuantity = $returnQuantities[$type][$itemId] ?? 0;
                                $stmtUpdate = $this->conn->prepare($sqlUpdateEquipment);
                                $stmtUpdate->bindParam(':equip_id', $itemId, PDO::PARAM_INT);
                                $stmtUpdate->bindParam(':return_quantity', $returnQuantity, PDO::PARAM_INT);
                                $stmtUpdate->execute();
                                break;
                            case 'vehicle':
                                $stmtUpdate = $this->conn->prepare($sqlUpdateVehicle);
                                $stmtUpdate->bindParam(':vehicle_id', $itemId, PDO::PARAM_INT);
                                $stmtUpdate->execute();
                                break;
                            case 'ven':
                                $stmtUpdate = $this->conn->prepare($sqlUpdateVenue);
                                $stmtUpdate->bindParam(':ven_id', $itemId, PDO::PARAM_INT);
                                $stmtUpdate->execute();
                                break;
                        }
                    }
                }
            };

            // Execute inserts and updates for equipment, vehicles, and venues
            $executeInsert($sqlInsertEquipment, 'equipment');
            $executeInsert($sqlInsertVehicle, 'vehicle');
            $executeInsert($sqlInsertVenue, 'ven');

            $this->conn->commit();
            return json_encode(['status' => 'success', 'message' => 'Return records inserted and statuses updated successfully.']);
        
        } catch (Exception $e) {
            $this->conn->rollBack();
            return json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }

    public function fetchAllReleaseFacilities() {
        try {
            $sql = "
            -- Released Vehicles
SELECT 
    r.reservation_id,
    r.reservation_name,
    r.reservation_event_title,
    a.admin_name,
    p.jo_personel_fname AS personnel_name,
    'Vehicle' AS type,
    v.vehicle_license,
    NULL AS equipment,
    NULL AS venue_name
FROM 
    tbl_reservations r
LEFT JOIN 
    tbl_release_reservation_vehicle rv ON r.reservation_id = rv.reservation_vehicle_checklist_reservation_id
LEFT JOIN 
    tbl_admin a ON a.admin_id = rv.reservation_vehicle_checklist_admin_id
LEFT JOIN 
    tbl_personel p ON p.jo_personel_id = rv.reservation_vehicle_checklist_personel_id
LEFT JOIN 
    tbl_reservation_vehicle res_v ON r.reservation_id = res_v.reservation_id
LEFT JOIN 
    tbl_vehicle v ON res_v.vehicle_reservation_vehicle_id = v.vehicle_id
WHERE 
    rv.release_vehicle_checklist_id IS NOT NULL

UNION ALL

-- Released Equipment
SELECT 
    r.reservation_id,
    r.reservation_name,
    r.reservation_event_title,
    a.admin_name,
    p.jo_personel_fname AS personnel_name,
    'Equipment' AS type,
    NULL AS vehicle_license,
    GROUP_CONCAT(DISTINCT CONCAT(e.equip_name, ' (', re_qty.quantity, ')') SEPARATOR ', ') AS equipment,
    NULL AS venue_name
FROM 
    tbl_reservations r
LEFT JOIN 
    tbl_release_reservation_equipment re ON r.reservation_id = re.reservation_equipment_checklist_reservation_id
LEFT JOIN 
    tbl_admin a ON a.admin_id = re.reservation_equipment_checklist_admin_id
LEFT JOIN 
    tbl_personel p ON p.jo_personel_id = re.reservation_equipment_checklist_personel_id
LEFT JOIN 
    tbl_reservation_equipment re_qty ON r.reservation_id = re_qty.reservation_id
LEFT JOIN 
    tbl_equipments e ON re_qty.equipments_reservation_equip_id = e.equip_id
WHERE 
    re.release_equipment_checklist_id IS NOT NULL
GROUP BY 
    r.reservation_id

UNION ALL

-- Released Venues
SELECT 
    r.reservation_id,
    r.reservation_name,
    r.reservation_event_title,
    a.admin_name,
    p.jo_personel_fname AS personnel_name,
    'Venue' AS type,
    NULL AS vehicle_license,
    NULL AS equipment,
    vn.ven_name AS venue_name
FROM 
    tbl_reservations r
LEFT JOIN 
    tbl_release_reservation_venue rvn ON r.reservation_id = rvn.reservation_venue_checklist_reservation_id
LEFT JOIN 
    tbl_admin a ON a.admin_id = rvn.reservation_venue_checklist_admin_id
LEFT JOIN 
    tbl_personel p ON p.jo_personel_id = rvn.reservation_venue_checklist_personel_id
LEFT JOIN 
    tbl_reservation_venue res_vn ON r.reservation_id = res_vn.reservation_id
LEFT JOIN 
    tbl_venue vn ON res_vn.venue_reservation_ven_id = vn.ven_id
WHERE 
    rvn.release_venue_checklist_id IS NOT NULL;

            ";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute();

            $facilities = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return json_encode(['status' => 'success', 'data' => $facilities]);
        } catch (PDOException $e) {
            return json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }

    public function fetchAllReturnedFacilities() {
        try {
            $sql = "
            -- Returned Vehicles
SELECT 
    r.reservation_id,
    r.reservation_name,
    r.reservation_event_title,
    a.admin_name,
    p.jo_personel_fname AS personnel_name,
    'Vehicle' AS type,
    v.vehicle_license,
    NULL AS equipment,
    NULL AS venue_name,
    cm.condition_name
FROM 
    tbl_reservations r
LEFT JOIN 
    tbl_return_reservation_vehicle rrv ON r.reservation_id = rrv.reservation_vehicle_checklist_reservation_id
LEFT JOIN 
    tbl_admin a ON a.admin_id = rrv.reservation_vehicle_checklist_admin_id
LEFT JOIN 
    tbl_personel p ON p.jo_personel_id = rrv.reservation_vehicle_checklist_personel_id
LEFT JOIN 
    tbl_condition_master cm ON cm.condition_id = rrv.reservation_vehicle_checklist_condition_id
LEFT JOIN 
    tbl_reservation_vehicle res_v ON r.reservation_id = res_v.reservation_id
LEFT JOIN 
    tbl_vehicle v ON res_v.vehicle_reservation_vehicle_id = v.vehicle_id
WHERE 
    rrv.reservation_vehicle_checklist_id IS NOT NULL

UNION ALL

-- Returned Equipment
SELECT 
    r.reservation_id,
    r.reservation_name,
    r.reservation_event_title,
    a.admin_name,
    p.jo_personel_fname AS personnel_name,
    'Equipment' AS type,
    NULL AS vehicle_license,
    GROUP_CONCAT(DISTINCT CONCAT(e.equip_name, ' (', re_qty.quantity, ')') SEPARATOR ', ') AS equipment,
    NULL AS venue_name,
    cm.condition_name
FROM 
    tbl_reservations r
LEFT JOIN 
    tbl_return_reservation_equipment rre ON r.reservation_id = rre.reservation_equipment_checklist_reservation_id
LEFT JOIN 
    tbl_admin a ON a.admin_id = rre.reservation_equipment_checklist_admin_id
LEFT JOIN 
    tbl_personel p ON p.jo_personel_id = rre.reservation_equipment_checklist_personel_id
LEFT JOIN 
    tbl_condition_master cm ON cm.condition_id = rre.reservation_equipment_checklist_condition_id
LEFT JOIN 
    tbl_reservation_equipment re_qty ON r.reservation_id = re_qty.reservation_id
LEFT JOIN 
    tbl_equipments e ON re_qty.equipments_reservation_equip_id = e.equip_id
WHERE 
    rre.equipment_checklist_id IS NOT NULL
GROUP BY 
    r.reservation_id

UNION ALL

-- Returned Venues
SELECT 
    r.reservation_id,
    r.reservation_name,
    r.reservation_event_title,
    a.admin_name,
    p.jo_personel_fname AS personnel_name,
    'Venue' AS type,
    NULL AS vehicle_license,
    NULL AS equipment,
    vn.ven_name AS venue_name,
    cm.condition_name
FROM 
    tbl_reservations r
LEFT JOIN 
    tbl_return_reservation_venue rrn ON r.reservation_id = rrn.reservation_venue_checklist_reservation_id
LEFT JOIN 
    tbl_admin a ON a.admin_id = rrn.reservation_venue_checklist_admin_id
LEFT JOIN 
    tbl_personel p ON p.jo_personel_id = rrn.reservation_venue_checklist_personel_id
LEFT JOIN 
    tbl_condition_master cm ON cm.condition_id = rrn.reservation_venue_checklist_condition_id
LEFT JOIN 
    tbl_reservation_venue res_vn ON r.reservation_id = res_vn.reservation_id
LEFT JOIN 
    tbl_venue vn ON res_vn.venue_reservation_ven_id = vn.ven_id
WHERE 
    rrn.venue_checklist_id IS NOT NULL;

            ";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute();

            $facilities = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return json_encode(['status' => 'success', 'data' => $facilities]);
        } catch (PDOException $e) {
            return json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }

    public function fetchAllReleasedFacilities() {
        try {
            $sql = "
            SELECT 
                r.reservation_id,
                r.reservation_name,
                r.reservation_event_title,
                a.admin_name,
                p.jo_personel_fname AS personnel_name,
                CASE
                    WHEN rv.release_vehicle_checklist_id IS NOT NULL THEN 'Vehicle'
                    WHEN re.release_equipment_checklist_id IS NOT NULL THEN 'Equipment'
                    WHEN rvn.release_venue_checklist_id IS NOT NULL THEN 'Venue'
                END AS type,
                v.vehicle_license,
                GROUP_CONCAT(DISTINCT CONCAT(e.equip_name, ' (', re_qty.quantity, ')') SEPARATOR ', ') AS equipment,
                vn.ven_name AS venue_name
            FROM 
                tbl_reservations r
            LEFT JOIN 
                tbl_release_reservation_vehicle rv ON r.reservation_id = rv.reservation_vehicle_checklist_reservation_id
            LEFT JOIN 
                tbl_release_reservation_equipment re ON r.reservation_id = re.reservation_equipment_checklist_reservation_id
            LEFT JOIN 
                tbl_release_reservation_venue rvn ON r.reservation_id = rvn.reservation_venue_checklist_reservation_id
            LEFT JOIN 
                tbl_admin a ON a.admin_id = COALESCE(rv.reservation_vehicle_checklist_admin_id, 
                                                       re.reservation_equipment_checklist_admin_id, 
                                                       rvn.reservation_venue_checklist_admin_id)
            LEFT JOIN 
                tbl_personel p ON p.jo_personel_id = COALESCE(rv.reservation_vehicle_checklist_personel_id, 
                                                                re.reservation_equipment_checklist_personel_id, 
                                                                rvn.reservation_venue_checklist_personel_id)
            LEFT JOIN 
                tbl_reservation_vehicle res_v ON r.reservation_id = res_v.reservation_id
            LEFT JOIN 
                tbl_vehicle v ON res_v.vehicle_reservation_vehicle_id = v.vehicle_id
            LEFT JOIN 
                tbl_reservation_equipment re_qty ON r.reservation_id = re_qty.reservation_id
            LEFT JOIN 
                tbl_equipments e ON re_qty.equipments_reservation_equip_id = e.equip_id
            LEFT JOIN 
                tbl_reservation_venue res_vn ON r.reservation_id = res_vn.reservation_id
            LEFT JOIN 
                tbl_venue vn ON res_vn.venue_reservation_ven_id = vn.ven_id
            WHERE 
                rv.release_vehicle_checklist_id IS NOT NULL 
                OR re.release_equipment_checklist_id IS NOT NULL 
                OR rvn.release_venue_checklist_id IS NOT NULL
            GROUP BY 
                r.reservation_id, 
                CASE
                    WHEN rv.release_vehicle_checklist_id IS NOT NULL THEN 'Vehicle'
                    WHEN re.release_equipment_checklist_id IS NOT NULL THEN 'Equipment'
                    WHEN rvn.release_venue_checklist_id IS NOT NULL THEN 'Venue'
                END
            ORDER BY 
                r.reservation_id, 
                CASE
                    WHEN rv.release_vehicle_checklist_id IS NOT NULL THEN 'Vehicle'
                    WHEN re.release_equipment_checklist_id IS NOT NULL THEN 'Equipment'
                    WHEN rvn.release_venue_checklist_id IS NOT NULL THEN 'Venue'
                END
            ";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute();

            $facilities = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return json_encode(['status' => 'success', 'data' => $facilities]);
        } catch (PDOException $e) {
            return json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }

    // Add new method for user-specific reservations
    public function fetchAllUserReservations($userId) {
        try {
            $sql = "
                SELECT 
                    r.reservation_id, 
                    r.reservation_name, 
                    r.reservation_event_title, 
                    r.reservation_description, 
                    r.reservation_start_date, 
                    r.reservation_end_date, 
                    r.reservation_status_master_id, 
                    sm.status_master_name AS reservation_status_name,
                    r.reservations_users_id,
                    GROUP_CONCAT(DISTINCT e.equip_name) AS equipment_names,
                    GROUP_CONCAT(DISTINCT v.ven_name) AS venue_names,
                    GROUP_CONCAT(DISTINCT veh.vehicle_id) AS vehicle_ids,
                    r.date_created
                FROM 
                    tbl_reservations r
                LEFT JOIN 
                    tbl_reservation_equipment re ON r.reservation_id = re.reservation_id
                LEFT JOIN 
                    tbl_equipments e ON re.equipments_reservation_equip_id = e.equip_id
                LEFT JOIN 
                    tbl_reservation_venue rv ON r.reservation_id = rv.reservation_id
                LEFT JOIN 
                    tbl_venue v ON rv.venue_reservation_ven_id = v.ven_id
                LEFT JOIN 
                    tbl_reservation_vehicle rvh ON r.reservation_id = rvh.reservation_id
                LEFT JOIN 
                    tbl_vehicle veh ON rvh.vehicle_reservation_vehicle_id = veh.vehicle_id
                INNER JOIN 
                    tbl_status_master sm ON r.reservation_status_master_id = sm.status_master_id
                WHERE 
                    r.reservations_users_id = :user_id
                GROUP BY 
                    r.reservation_id;
            ";

            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $stmt->execute();

            $reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return json_encode(['status' => 'success', 'data' => $reservations]);
        } catch (PDOException $e) {
            return json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }

    // Add new method to cancel a reservation
    public function cancelReservation($reservationId) {
        try {
            $sql = "UPDATE tbl_reservations SET reservation_status_master_id = 5 WHERE reservation_id = :reservation_id";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':reservation_id', $reservationId, PDO::PARAM_INT);
            $stmt->execute();

            return json_encode(['status' => 'success', 'message' => 'Reservation cancelled successfully.']);
        } catch (PDOException $e) {
            return json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }

    // Add new method to fetch notifications by user ID
    public function fetchNotificationsByUserId($userId) {
        try {
            header('Content-Type: application/json');
            
            $sql = "
                SELECT 
                    notification_id, 
                    reservation_id, 
                    user_id, 
                    notification_status, 
                    notification_message, 
                    is_read_by_user, 
                    is_read_by_superadmin, 
                    notification_created_at, 
                    notification_updated_at 
                FROM 
                    tbl_notification_reservation 
                WHERE 
                    user_id = :user_id
                ORDER BY 
                    notification_created_at DESC;
            ";

            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $stmt->execute();

            $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (empty($notifications)) {
                return json_encode([
                    'status' => 'success',
                    'message' => 'No notifications found',
                    'data' => []
                ]);
            }

            return json_encode([
                'status' => 'success',
                'message' => 'Notifications retrieved successfully',
                'data' => $notifications
            ]);
        } catch (PDOException $e) {
            return json_encode([
                'status' => 'error',
                'message' => $e->getMessage(),
                'data' => null
            ]);
        }
    }

    public function checkTimeSlotAvailability($startDate, $endDate, $venueId = null) {
        try {
            $startDateTime = date('Y-m-d H:i:s', strtotime($startDate));
            $endDateTime = date('Y-m-d H:i:s', strtotime($endDate));

            // Check for time slot conflicts
            $query = "
                SELECT 
                    r.reservation_id,
                    r.reservation_name,
                    r.reservation_event_title,
                    DATE_FORMAT(r.reservation_start_date, '%Y-%m-%d %H:%i:%s') as reservation_start_date,
                    DATE_FORMAT(r.reservation_end_date, '%Y-%m-%d %H:%i:%s') as reservation_end_date,
                    v.ven_name
                FROM tbl_reservations r
                INNER JOIN tbl_reservation_venue rv ON r.reservation_id = rv.reservation_id
                INNER JOIN tbl_venue v ON rv.venue_reservation_ven_id = v.ven_id
                WHERE 
                    r.reservation_status_master_id = 3 /* Only approved reservations */
                    AND (r.reservation_start_date <= :end_date AND r.reservation_end_date >= :start_date)";

            // Add venue filter if venueId is provided
            if ($venueId) {
                $query .= " AND rv.venue_reservation_ven_id = :venue_id";
            }
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':start_date', $startDateTime);
            $stmt->bindParam(':end_date', $endDateTime);
            if ($venueId) {
                $stmt->bindParam(':venue_id', $venueId);
            }
            $stmt->execute();
            
            $conflicts = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($conflicts)) {
                return json_encode([
                    'status' => 'success',
                    'available' => true,
                    'message' => 'Time slot is available for the venue'
                ]);
            } else {
                return json_encode([
                    'status' => 'success',
                    'available' => false,
                    'conflicts' => $conflicts,
                    'message' => 'Time slot has conflicts with approved reservations'
                ]);
            }
        } catch (PDOException $e) {
            return json_encode([
                'status' => 'error',
                'message' => $e->getMessage()
            ]);
        }
    }

    public function fetchRecords() {
        try {
            $sql = "
                SELECT 
                    r.reservation_id,
                    r.reservation_name,
                    r.reservation_event_title,
                    r.reservation_description,
                    r.reservation_start_date,
                    r.reservation_end_date,
                    r.reservation_status_master_id,
                    r.reservations_users_id,
                    r.date_created,
                    r.reservation_participants,
                    v.vehicle_reservation_id,
                    v.vehicle_reservation_vehicle_id,
                    vn.venue_reservation_id,
                    vn.venue_reservation_ven_id,
                    e.equipments_reservation_id,
                    e.equipments_reservation_equip_id,
                    e.quantity
                FROM 
                    tbl_reservations r
                LEFT JOIN 
                    tbl_reservation_vehicle v ON r.reservation_id = v.reservation_id
                LEFT JOIN 
                    tbl_reservation_venue vn ON r.reservation_id = vn.reservation_id
                LEFT JOIN 
                    tbl_reservation_equipment e ON r.reservation_id = e.reservation_id
                WHERE 
                    1
                ORDER BY 
                    r.reservation_id DESC
            ";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute();

            $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return json_encode(['status' => 'success', 'data' => $records]);
        } catch (PDOException $e) {
            return json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }

}

// Handle the request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // First try to get JSON input
    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput, true);
    
    // If JSON parsing fails, use regular POST data
    if (json_last_error() !== JSON_ERROR_NONE) {
        $data = $_POST;
    }

    // Log the received data for debugging
    error_log("Received data: " . print_r($data, true));

    $user = new User();
    $operation = $data['operation'] ?? '';
    $userId = $data['user_id'] ?? 24; // Default to user ID 24
    $reservationId = $data['reservation_id'] ?? null;

    // Log the operation being attempted
    error_log("Attempting operation: " . $operation);

    switch ($operation) {
        case "fetchPendingReservations":
            echo $user->fetchPendingReservations($userId);
            break;
        case "fetchAllPendingReservations":
            echo $user->fetchAllPendingReservations();
            break;
        case "fetchAllReservations":
            echo $user->fetchAllReservations();
            break;
        case "getReservationDetailsById":
            if ($reservationId) {
                echo $user->getReservationDetailsById($reservationId);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Reservation ID is required']);
            }
            break;
        case "fetchReleaseFacilities":
            echo $user->fetchReleaseFacilities();
            break;
        case "fetchReturnFacilities":
            echo $user->fetchReturnFacilities();
            break;
        case "fetchConditions":
            echo $user->fetchConditions();
            break;
        case "insertRelease":
            $reservationId = $data['reservationId'] ?? null;
            $personelId = $data['personelId'] ?? null;
            $adminId = $data['adminId'] ?? null;
            
            if ($reservationId && ($personelId || $adminId)) {
                echo $user->insertRelease($reservationId, $personelId, $adminId);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Missing required parameters']);
            }
            break;
        case "insertReturn":
            $reservationId = $data['reservationId'] ?? null;
            $personelId = $data['personelId'] ?? null;
            $adminId = $data['adminId'] ?? null;
            $conditions = $data['conditions'] ?? [];
            $returnQuantities = $data['returnQuantities'] ?? [];
            
            if ($reservationId && ($personelId || $adminId)) {
                echo $user->insertReturn($reservationId, $personelId, $adminId, $conditions, $returnQuantities);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Missing required parameters']);
            }
            break;
            
        case "checkTimeSlotAvailability":
            $startDate = $data['startDate'] ?? null;
            $endDate = $data['endDate'] ?? null;
            $venueId = $data['venueId'] ?? null;
            
            if ($startDate && $endDate) {
                echo $user->checkTimeSlotAvailability($startDate, $endDate, $venueId);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Start date and end date are required']);
            }
            break;
            
        case "fetchNotificationsByUserId":
            echo $user->fetchNotificationsByUserId($userId);
            break;
        case "fetchRecords":
            echo $user->fetchRecords();
            break;
        default:
            echo json_encode(['status' => 'error', 'message' => 'Invalid operation']);
            break;
    }
}
?>
