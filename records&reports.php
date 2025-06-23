<?php
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

class ReservationDetails {
    private $conn;

    public function __construct() {
        include 'connection-pdo.php'; // Include your database connection
        $this->conn = $conn;
    }

    // Fetch all records based on conditions
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
                CONCAT(u.users_fname, ' ', u.users_lname) AS user_full_name,
                sm.status_master_name AS reservation_status_name,
                rs_filtered.reservation_status_status_id,
                rs_filtered.reservation_updated_at,
                rs_filtered.reservation_active

            FROM tbl_reservation r

            LEFT JOIN (
                SELECT rs.*
                FROM tbl_reservation_status rs
                INNER JOIN (
                    SELECT reservation_reservation_id, MAX(reservation_status_id) AS latest_status_id
                    FROM tbl_reservation_status
                    GROUP BY reservation_reservation_id
                ) latest_rs 
                    ON rs.reservation_reservation_id = latest_rs.reservation_reservation_id
                    AND rs.reservation_status_id = latest_rs.latest_status_id
            ) rs_filtered ON rs_filtered.reservation_reservation_id = r.reservation_id

            LEFT JOIN tbl_status_master sm ON sm.status_master_id = rs_filtered.reservation_status_status_id
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




    
    
    
    

    public function getReservationDetailsById($reservationId) {
    if (!$reservationId) {
        return json_encode(['status' => 'error', 'message' => 'Reservation ID is required']);
    }

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

                -- Latest status
                rs.reservation_status_status_id,  
                sm.status_master_name AS status_request,  
                rs.reservation_updated_at AS latest_status_updated_at,

                -- Venue
                GROUP_CONCAT(DISTINCT v.ven_name) AS venue_names,  

                -- Vehicle
                GROUP_CONCAT(DISTINCT ve.vehicle_license) AS vehicle_licenses,
                GROUP_CONCAT(DISTINCT vm.vehicle_model_name) AS vehicle_models,
                GROUP_CONCAT(DISTINCT vmake.vehicle_make_name) AS vehicle_makes,
                GROUP_CONCAT(DISTINCT vc.vehicle_category_name) AS vehicle_categories,

                -- Equipment
                GROUP_CONCAT(DISTINCT e.equip_name) AS equipment_names,

                -- Passenger
                GROUP_CONCAT(rp.reservation_passenger_name) as passenger_names,
                GROUP_CONCAT(rp.reservation_passenger_id) as passenger_ids,

                -- User
                CONCAT(u.users_fname, ' ', u.users_lname) AS full_name,
                u.users_department_id,
                d.departments_name AS department_name,

                -- Driver
                rd.reservation_driver_user_id,
                rd.is_accepted_trip,
                du.users_fname AS driver_fname,
                du.users_lname AS driver_lname

            FROM 
                tbl_reservation r

            -- Latest reservation status
            LEFT JOIN (
                SELECT rs1.*
                FROM tbl_reservation_status rs1
                INNER JOIN (
                    SELECT reservation_reservation_id, MAX(reservation_updated_at) AS max_updated
                    FROM tbl_reservation_status
                    GROUP BY reservation_reservation_id
                ) rs2 ON rs1.reservation_reservation_id = rs2.reservation_reservation_id 
                      AND rs1.reservation_updated_at = rs2.max_updated
            ) rs ON r.reservation_id = rs.reservation_reservation_id

            LEFT JOIN tbl_status_master sm ON rs.reservation_status_status_id = sm.status_master_id
            LEFT JOIN tbl_reservation_passenger rp ON r.reservation_id = rp.reservation_reservation_id

            LEFT JOIN tbl_reservation_venue rv ON r.reservation_id = rv.reservation_reservation_id
            LEFT JOIN tbl_venue v ON rv.reservation_venue_venue_id = v.ven_id

            LEFT JOIN tbl_reservation_vehicle rvh ON r.reservation_id = rvh.reservation_reservation_id
            LEFT JOIN tbl_vehicle ve ON rvh.reservation_vehicle_vehicle_id = ve.vehicle_id
            LEFT JOIN tbl_vehicle_model vm ON ve.vehicle_model_id = vm.vehicle_model_id
            LEFT JOIN tbl_vehicle_make vmake ON vm.vehicle_model_vehicle_make_id = vmake.vehicle_make_id
            LEFT JOIN tbl_vehicle_category vc ON vm.vehicle_category_id = vc.vehicle_category_id

            LEFT JOIN tbl_reservation_equipment re ON r.reservation_id = re.reservation_reservation_id
            LEFT JOIN tbl_equipments e ON re.reservation_equipment_equip_id = e.equip_id

            LEFT JOIN tbl_users u ON r.reservation_user_id = u.users_id
            LEFT JOIN tbl_departments d ON u.users_department_id = d.departments_id

            LEFT JOIN tbl_reservation_driver rd ON r.reservation_id = rd.reservation_reservation_id
            LEFT JOIN tbl_users du ON rd.reservation_driver_user_id = du.users_id

            WHERE r.reservation_id = :reservation_id
            GROUP BY r.reservation_id
        ";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':reservation_id', $reservationId, PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->rowCount() === 0) {
            return json_encode(['status' => 'error', 'message' => 'No record found']);
        }

        $reservation = $stmt->fetch(PDO::FETCH_ASSOC);

        $statusRequest = $reservation['status_request'] ?? 'Unknown Status';

        $passengerDetails = [];
        if (!empty($reservation['passenger_names']) && !empty($reservation['passenger_ids'])) {
            $names = explode(',', $reservation['passenger_names']);
            $ids = explode(',', $reservation['passenger_ids']);
            foreach ($names as $index => $name) {
                $passengerDetails[] = [
                    'passenger_name' => $name,
                    'passenger_id' => $ids[$index] ?? null
                ];
            }
        }

        $venueDetails = [];
        if (!empty($reservation['venue_names'])) {
            $venueNames = explode(',', $reservation['venue_names']);
            foreach ($venueNames as $venue) {
                $venueDetails[] = [
                    'venue_name' => $venue
                ];
            }
        }

        $vehicleDetails = [];
        if (!empty($reservation['vehicle_licenses'])) {
            $vehicleLicenses = explode(',', $reservation['vehicle_licenses']);
            $vehicleModels = explode(',', $reservation['vehicle_models']);
            $vehicleMakes = explode(',', $reservation['vehicle_makes']);
            $vehicleCategories = explode(',', $reservation['vehicle_categories']);
            foreach ($vehicleLicenses as $index => $license) {
                $vehicleDetails[] = [
                    'vehicle_license' => $license,
                    'vehicle_model' => $vehicleModels[$index] ?? null,
                    'vehicle_make' => $vehicleMakes[$index] ?? null,
                    'vehicle_category' => $vehicleCategories[$index] ?? null
                ];
            }
        }

        $equipmentDetails = [];
        if (!empty($reservation['equipment_names'])) {
            $equipmentNames = explode(',', $reservation['equipment_names']);
            foreach ($equipmentNames as $equipment) {
                $equipmentDetails[] = [
                    'equipment_name' => $equipment
                ];
            }
        }

        $driverName = null;
        if (!empty($reservation['driver_fname']) || !empty($reservation['driver_lname'])) {
            $driverName = trim("{$reservation['driver_fname']} {$reservation['driver_lname']}");
        }

        $response = [
            'reservation_id' => $reservation['reservation_id'],
            'reservation_created_at' => $reservation['reservation_created_at'],
            'reservation_title' => $reservation['reservation_title'],
            'reservation_description' => $reservation['reservation_description'],
            'reservation_start_date' => $reservation['reservation_start_date'],
            'reservation_end_date' => $reservation['reservation_end_date'],
            'reservation_participants' => $reservation['reservation_participants'],
            'status_request' => $statusRequest,
            'latest_status_updated_at' => $reservation['latest_status_updated_at'],
            'full_name' => $reservation['full_name'],
            'department_name' => $reservation['department_name'],
            'passengers' => $passengerDetails,
            'vehicles' => $vehicleDetails,
            'equipment' => $equipmentDetails,
            'venues' => $venueDetails,
            'driver_name' => $driverName,
            'driver_user_id' => $reservation['reservation_driver_user_id'],
            'driver_trip_accepted' => $reservation['is_accepted_trip']
        ];

        return json_encode(['status' => 'success', 'data' => $response]);

    } catch (PDOException $e) {
        error_log("Database error: " . $e->getMessage());
        return json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
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
            LEFT JOIN 
                tbl_reservation_status rs ON r.reservation_id = rs.reservation_reservation_id
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


    public function fetchAssignedRelease() {
        try {
            // Get master checklist data
            $sqlMaster = "
                SELECT 
                    cm.checklist_id,
                    cm.checklist_reservation_id,
                    cm.checklist_admin_id,
                    cm.checklist_personnel_id,
                    a.approval_form_venue_id,
                    a.approval_form_vehicle_id,
                    r.reservation_id,
                    r.reservation_date,
                    GROUP_CONCAT(DISTINCT rfv.reservation_form_name) AS venue_form_name,
                    GROUP_CONCAT(DISTINCT rfv_v.reservation_form_name) AS vehicle_form_name,
                    cs.checklist_status_status_checklist_id,
                    sc.status_checklist_name
                FROM 
                    tbl_checklist_master cm
                LEFT JOIN
                    tbl_reservation r ON cm.checklist_reservation_id = r.reservation_id
                LEFT JOIN
                    tbl_approval a ON r.reservation_approval_id = a.approval_id
                LEFT JOIN 
                    tbl_reservation_form_venue rfv ON a.approval_form_venue_id = rfv.reservation_form_venue_id
                LEFT JOIN 
                    tbl_reservation_form_vehicle rfv_v ON a.approval_form_vehicle_id = rfv_v.reservation_form_vehicle_id
                LEFT JOIN
                    tbl_checklist_status cs ON cm.checklist_id = cs.checklist_checklist_id
                INNER JOIN
                    tbl_status_checklist sc ON cs.checklist_status_status_checklist_id = sc.status_checklist_id
                WHERE
                    cm.checklist_id NOT IN (
                        SELECT checklist_checklist_id
                        FROM tbl_checklist_status
                        WHERE checklist_status_status_checklist_id IN (1, 2) 
                        GROUP BY checklist_checklist_id
                        HAVING COUNT(DISTINCT checklist_status_status_checklist_id) = 2  
                    )
                GROUP BY
                    cm.checklist_id
                ORDER BY 
                    cm.checklist_id DESC
            ";

            $stmtMaster = $this->conn->prepare($sqlMaster);
            $stmtMaster->execute();
            $masterData = $stmtMaster->fetchAll(PDO::FETCH_ASSOC);

            if (!$masterData) {
                return json_encode(['status' => 'error', 'message' => 'No checklists found']);
            }

            $response = [];
            foreach ($masterData as $master) {
                $checklistData = [
                    'master_data' => $master,
                    'venue_equipment' => [],
                    'vehicle_checklist' => []
                ];

                // Get venue equipment release checklist
                $sqlVenue = "
                    SELECT 
                        release_venue_id,
                        release_checklist_name,
                        release_isActive,
                        release_updated_at
                    FROM 
                        tbl_release_venue_equipment
                    WHERE 
                        checklist_master_id = :checklist_master_id
                ";
                
                $stmtVenue = $this->conn->prepare($sqlVenue);
                $stmtVenue->execute(['checklist_master_id' => $master['checklist_id']]);
                $checklistData['venue_equipment'] = $stmtVenue->fetchAll(PDO::FETCH_ASSOC);

                $sqlVehicle = "
                    SELECT 
                        release_vehicle_id,
                        release_checklist_name,
                        release_isActive,
                        release_updated_at
                    FROM 
                        tbl_release_vehicle
                    WHERE 
                        checklist_master_id = :checklist_master_id
                ";
                
                $stmtVehicle = $this->conn->prepare($sqlVehicle);
                $stmtVehicle->execute(['checklist_master_id' => $master['checklist_id']]);
                $checklistData['vehicle_checklist'] = $stmtVehicle->fetchAll(PDO::FETCH_ASSOC);

                $response[] = $checklistData;
            }

            return json_encode(['status' => 'success', 'data' => $response]);

        } catch (PDOException $e) {
            return json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }

    public function fetchCompletedRelease() {
        try {
            $sqlMaster = "
                SELECT 
                    cm.checklist_id,
                    cm.checklist_reservation_id,
                    cm.checklist_admin_id,
                    cm.checklist_personnel_id,
                    a.approval_form_venue_id,
                    a.approval_form_vehicle_id,
                    r.reservation_id,
                    r.reservation_date,
                    GROUP_CONCAT(DISTINCT rfv.reservation_form_name) AS venue_form_name,
                    GROUP_CONCAT(DISTINCT rfv_v.reservation_form_name) AS vehicle_form_name,
                    cs.checklist_status_status_checklist_id,
                    sc.status_checklist_name
                FROM 
                    tbl_checklist_master cm
                LEFT JOIN
                    tbl_reservation r ON cm.checklist_reservation_id = r.reservation_id
                LEFT JOIN
                    tbl_approval a ON r.reservation_approval_id = a.approval_id
                LEFT JOIN 
                    tbl_reservation_form_venue rfv ON a.approval_form_venue_id = rfv.reservation_form_venue_id
                LEFT JOIN 
                    tbl_reservation_form_vehicle rfv_v ON a.approval_form_vehicle_id = rfv_v.reservation_form_vehicle_id
                LEFT JOIN
                    tbl_checklist_status cs ON cm.checklist_id = cs.checklist_checklist_id
                INNER JOIN
                    tbl_status_checklist sc ON cs.checklist_status_status_checklist_id = sc.status_checklist_id
                WHERE
                    checklist_status_status_checklist_id = 2
                    
                GROUP BY
                    cm.checklist_id
                ORDER BY 
                    cm.checklist_id DESC
            ";

            $stmtMaster = $this->conn->prepare($sqlMaster);
            $stmtMaster->execute();
            $masterData = $stmtMaster->fetchAll(PDO::FETCH_ASSOC);

            if (!$masterData) {
                return json_encode(['status' => 'error', 'message' => 'No checklists found']);
            }

            $response = [];
            foreach ($masterData as $master) {
                $checklistData = [
                    'master_data' => $master,
                    'venue_equipment' => [],
                    'vehicle_checklist' => []
                ];

                $sqlVenue = "
                    SELECT 
                        release_venue_id,
                        release_checklist_name,
                        release_isActive,
                        release_updated_at
                    FROM 
                        tbl_release_venue_equipment
                    WHERE 
                        checklist_master_id = :checklist_master_id
                ";
                
                $stmtVenue = $this->conn->prepare($sqlVenue);
                $stmtVenue->execute(['checklist_master_id' => $master['checklist_id']]);
                $checklistData['venue_equipment'] = $stmtVenue->fetchAll(PDO::FETCH_ASSOC);

                $sqlVehicle = "
                    SELECT 
                        release_vehicle_id,
                        release_checklist_name,
                        release_isActive,
                        release_updated_at
                    FROM 
                        tbl_release_vehicle
                    WHERE 
                        checklist_master_id = :checklist_master_id
                ";
                
                $stmtVehicle = $this->conn->prepare($sqlVehicle);
                $stmtVehicle->execute(['checklist_master_id' => $master['checklist_id']]);
                $checklistData['vehicle_checklist'] = $stmtVehicle->fetchAll(PDO::FETCH_ASSOC);

                $response[] = $checklistData;
            }

            return json_encode(['status' => 'success', 'data' => $response]);

        } catch (PDOException $e) {
            return json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $input = json_decode(file_get_contents('php://input'), true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid JSON data']);
        exit;
    }

    $operation = $input['operation'] ?? '';

    if (empty($operation)) {
        echo json_encode(['status' => 'error', 'message' => 'Operation is missing']);
        exit;
    }

    $reservationDetails = new ReservationDetails();

    switch ($operation) {
        case "getReservationDetailsById":
            $json = $input['json'] ?? null;
            if (empty($json)) {
                echo json_encode(['status' => 'error', 'message' => 'JSON data is missing']);
                exit;
            }
            $reservationId = $json['reservation_id'] ?? null;
            echo $reservationDetails->getReservationDetailsById($reservationId);
            break;
        
        case "fetchRecord":
            echo $reservationDetails->fetchRecord();
            break;
        
        case "fetchNoAssignedReservation":
            echo $reservationDetails->fetchNoAssignedReservation();
            break;

        case "insertRelease":
            $json = $input['json'] ?? null;
            if (empty($json)) {
                echo json_encode(['status' => 'error', 'message' => 'JSON data is missing']);
                exit;
            }
            echo $reservationDetails->insertRelease($json);
            break;
            
        case "insertReturn":
            $json = $input['json'] ?? null;
            if (empty($json)) {
                echo json_encode(['status' => 'error', 'message' => 'JSON data is missing']);
                exit;
            }
            echo $reservationDetails->insertReturn($json);
            break;

        case "fetchAssignedRelease":
            echo $reservationDetails->fetchAssignedRelease();
            break;

        case "fetchCompletedRelease":
            echo $reservationDetails->fetchCompletedRelease();
            break;
            
        default:
            echo json_encode(['status' => 'error', 'message' => 'Invalid operation']);
            break;
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    // Handle preflight requests
    http_response_code(200);
    exit;
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
}
