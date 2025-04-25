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
                    CONCAT(u.users_fname, ' ', u.users_lname) AS user_full_name,  -- Concatenate first and last name
                    sm.status_master_name AS reservation_status_name,  -- Get the status name from tbl_status_master
                    rs.reservation_active  -- Get the active status from tbl_reservation_status
                    
                FROM 
                    tbl_reservation r
                JOIN
                    tbl_reservation_status rs ON r.reservation_id = rs.reservation_reservation_id  -- Join reservation status
                JOIN
                    tbl_users u ON r.reservation_user_id = u.users_id  -- Join user information
                JOIN
                    tbl_status_master sm ON rs.reservation_status_status_id = sm.status_master_id  -- Join status master table
                    
                WHERE 
                    rs.reservation_status_status_id IN (2, 3, 6)  -- Declined (2), Approved (3), Reserved (6)
                    AND rs.reservation_active = 1  -- Only active reservations
        
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
    
    
    
    

    public function getReservationDetailsById($reservationId) {
        if (!$reservationId) {
            return json_encode(['status' => 'error', 'message' => 'Reservation ID is required']);
        }
    
        try {
            // The corrected SQL query to include user and department details
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
                    rs.reservation_status_status_id,  
                    sm.status_master_name AS status_request,  
                    rs.reservation_updated_at,
                    
                    -- Venue details with DISTINCT to avoid duplicates
                    GROUP_CONCAT(DISTINCT v.ven_name) AS venue_names,  
    
                    -- Vehicle details with DISTINCT to avoid duplicates
                    GROUP_CONCAT(DISTINCT ve.vehicle_license) AS vehicle_licenses,
                    GROUP_CONCAT(DISTINCT vm.vehicle_model_name) AS vehicle_models,
                    GROUP_CONCAT(DISTINCT vmake.vehicle_make_name) AS vehicle_makes,
                    GROUP_CONCAT(DISTINCT vc.vehicle_category_name) AS vehicle_categories,
    
                    -- Equipment details with DISTINCT to avoid duplicates
                    GROUP_CONCAT(DISTINCT e.equip_name) AS equipment_names,
    
                    -- Passenger details
                    GROUP_CONCAT(rp.reservation_passenger_name) as passenger_names,
                    GROUP_CONCAT(rp.reservation_passenger_id) as passenger_ids,
    
                    -- User details (Full name)
                    CONCAT(u.users_fname, ' ', u.users_lname) AS full_name,
                    u.users_department_id,
                    
                    -- Department name
                    d.departments_name AS department_name
    
                FROM 
                    tbl_reservation r
                LEFT JOIN 
                    tbl_reservation_status rs ON r.reservation_id = rs.reservation_reservation_id
                LEFT JOIN 
                    tbl_status_master sm ON rs.reservation_status_status_id = sm.status_master_id
                LEFT JOIN 
                    tbl_reservation_passenger rp ON r.reservation_id = rp.reservation_reservation_id
    
                -- Venue details
                LEFT JOIN 
                    tbl_reservation_venue rv ON r.reservation_id = rv.reservation_reservation_id
                LEFT JOIN 
                    tbl_venue v ON rv.reservation_venue_venue_id = v.ven_id
    
                -- Vehicle details
                LEFT JOIN 
                    tbl_reservation_vehicle rvh ON r.reservation_id = rvh.reservation_reservation_id
                LEFT JOIN 
                    tbl_vehicle ve ON rvh.reservation_vehicle_vehicle_id = ve.vehicle_id
                LEFT JOIN 
                    tbl_vehicle_model vm ON ve.vehicle_model_id = vm.vehicle_model_id
                LEFT JOIN 
                    tbl_vehicle_make vmake ON vm.vehicle_model_vehicle_make_id = vmake.vehicle_make_id
                LEFT JOIN 
                    tbl_vehicle_category vc ON vm.vehicle_category_id = vc.vehicle_category_id
    
                -- Equipment details
                LEFT JOIN 
                    tbl_reservation_equipment re ON r.reservation_id = re.reservation_reservation_id
                LEFT JOIN 
                    tbl_equipments e ON re.reservation_equipment_equip_id = e.equip_id
    
                -- User details
                LEFT JOIN 
                    tbl_users u ON r.reservation_user_id = u.users_id
    
                -- Department details
                LEFT JOIN 
                    tbl_departments d ON u.users_department_id = d.departments_id
    
                WHERE 
                    r.reservation_id = :reservation_id
                    AND rs.reservation_status_status_id IN (2, 3, 6)  
                GROUP BY 
                    r.reservation_id
                LIMIT 1;
            ";
    
            // Prepare the statement
            $stmt = $this->conn->prepare($sql);
            
            // Bind the parameter correctly
            $stmt->bindParam(':reservation_id', $reservationId, PDO::PARAM_INT);
    
            // Execute the statement
            $stmt->execute();
    
            // Check if any rows are returned
            if ($stmt->rowCount() === 0) {
                return json_encode([ 
                    'status' => 'error', 
                    'message' => 'No record found' 
                ]);
            }
    
            $reservation = $stmt->fetch(PDO::FETCH_ASSOC);
    
            // Handle the status and passenger details
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
    
            // Process venue details (ensure it's an array)
            $venueDetails = [];
            if (!empty($reservation['venue_names'])) {
                $venueNames = explode(',', $reservation['venue_names']);
                foreach ($venueNames as $venue) {
                    $venueDetails[] = [
                        'venue_name' => $venue
                    ];
                }
            }
    
            // Process vehicle details (ensure it's an array)
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
    
            // Process equipment details (ensure it's an array)
            $equipmentDetails = [];
            if (!empty($reservation['equipment_names'])) {
                $equipmentNames = explode(',', $reservation['equipment_names']);
                foreach ($equipmentNames as $equipment) {
                    $equipmentDetails[] = [
                        'equipment_name' => $equipment
                    ];
                }
            }
    
            // Reservation response
            $response = [
                'reservation_id' => $reservation['reservation_id'],
                'reservation_created_at' => $reservation['reservation_created_at'],
                'reservation_title' => $reservation['reservation_title'],
                'reservation_description' => $reservation['reservation_description'],
                'reservation_start_date' => $reservation['reservation_start_date'],
                'reservation_end_date' => $reservation['reservation_end_date'],
                'reservation_participants' => $reservation['reservation_participants'],
                'status_request' => $statusRequest,
                'full_name' => $reservation['full_name'],
                'department_name' => $reservation['department_name'],
                'passengers' => $passengerDetails,
                'vehicles' => $vehicleDetails,
                'equipment' => $equipmentDetails,
                'venues' => $venueDetails
            ];
    
            return json_encode(['status' => 'success', 'data' => $response]);
    
        } catch (PDOException $e) {
            // Log the actual error message for debugging
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
                    r.reservation_created_at
                FROM 
                    tbl_reservation r
                LEFT JOIN 
                    tbl_reservation_status rs ON r.reservation_id = rs.reservation_reservation_id
                LEFT JOIN 
                    tbl_reservation_passenger rp ON r.reservation_id = rp.reservation_reservation_id
                WHERE 
                    (rs.reservation_status_status_id = 6 OR rs.reservation_status_status_id IS NULL)
                    AND r.reservation_id IS NOT NULL
                    AND rp.reservation_passenger_id IS NULL
                    AND NOT EXISTS (
                        SELECT 1
                        FROM tbl_reservation_venue rv
                        INNER JOIN tbl_reservation_checklist_venue cv 
                            ON rv.reservation_venue_id = cv.checklist_venue_id
                        WHERE rv.reservation_reservation_id = r.reservation_id
                    )
                    AND NOT EXISTS (
                        SELECT 1
                        FROM tbl_reservation_vehicle rvh
                        INNER JOIN tbl_reservation_checklist_vehicle cvh 
                            ON rvh.reservation_vehicle_id = cvh.checklist_vehicle_id
                        WHERE rvh.reservation_reservation_id = r.reservation_id
                    )
                    AND NOT EXISTS (
                        SELECT 1
                        FROM tbl_reservation_equipment re
                        INNER JOIN tbl_reservation_checklist_equipment ce 
                            ON re.reservation_equipment_id = ce.checklist_equipment_id
                        WHERE re.reservation_reservation_id = r.reservation_id
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
    
    
    

    
    
    
    
    

    // Insert Release Checklist
    public function insertRelease($data) {
        try {
            $this->conn->beginTransaction();
            
            // First insert into checklist master
            $sqlMaster = "INSERT INTO tbl_checklist_master 
                (checklist_reservation_id, checklist_admin_id, checklist_personnel_id) 
                VALUES (:reservation_id, :admin_id, :personnel_id)";
            
            $stmtMaster = $this->conn->prepare($sqlMaster);
            $stmtMaster->execute([
                'reservation_id' => $data['reservation_id'],
                'admin_id' => $data['admin_id'],
                'personnel_id' => $data['personnel_id']
            ]);
            
            // Get the last inserted ID
            $checklistMasterId = $this->conn->lastInsertId();
            error_log("Checklist Master ID: " . $checklistMasterId); // Debugging
    
            // Handle venue equipment checklist
            if (!empty($data['venue_equipment'])) {
                $sqlVenue = "INSERT INTO tbl_release_venue_equipment 
                    (release_checklist_name, release_isActive, checklist_master_id) 
                    VALUES (:checklist_name, :is_active, :checklist_master_id)";
                
                $stmtVenue = $this->conn->prepare($sqlVenue);
                foreach ($data['venue_equipment'] as $item) {
                    $stmtVenue->execute([
                        'checklist_name' => $item['name'],
                        'is_active' => $item['isActive'],
                        'checklist_master_id' => $checklistMasterId
                    ]);
                }
            }
    
            // Handle vehicle checklist
            if (!empty($data['vehicle'])) {
                $sqlVehicle = "INSERT INTO tbl_release_vehicle 
                    (release_checklist_name, release_isActive, checklist_master_id) 
                    VALUES (:vehicle_name, :is_active, :checklist_master_id)";
                
                $stmtVehicle = $this->conn->prepare($sqlVehicle);
                foreach ($data['vehicle'] as $item) {
                    $stmtVehicle->execute([
                        'vehicle_name' => $item['name'],
                        'is_active' => $item['isActive'],
                        'checklist_master_id' => $checklistMasterId
                    ]);
                }
            }
    
            // Insert checklist status
            $sqlStatus = "INSERT INTO tbl_checklist_status 
                (checklist_status_status_checklist_id, checklist_checklist_id) 
                VALUES (:status_id, :checklist_id)";
            
            $stmtStatus = $this->conn->prepare($sqlStatus);
            $stmtStatus->execute([
                'status_id' => 1, // Set initial status to 1
                'checklist_id' => $checklistMasterId
            ]);
    
            $this->conn->commit();
            
            return json_encode([
                'status' => 'success', 
                'checklist_master_id' => $checklistMasterId
            ]);
    
        } catch (PDOException $e) {
            $this->conn->rollBack();
            error_log("Error: " . $e->getMessage()); // Debugging
            return json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }

    // Insert Return Checklist
    public function insertReturn($data) {
        try {
            $this->conn->beginTransaction();
            
            $returnVenueId = null;
            $returnVehicleId = null;

            // Handle venue equipment return checklist first
            if (!empty($data['venue_equipment'])) {
                $sqlVenue = "INSERT INTO tbl_return_venue_equipment 
                    (return_checklist_name, return_isActive) 
                    VALUES (:checklist_name, :is_active)";
                
                $stmtVenue = $this->conn->prepare($sqlVenue);
                foreach ($data['venue_equipment'] as $item) {
                    $stmtVenue->execute([
                        'checklist_name' => $item['name'],
                        'is_active' => $item['isActive']
                    ]);
                }
                $returnVenueId = $this->conn->lastInsertId();
            }

            // Handle vehicle return checklist
            if (!empty($data['vehicle'])) {
                $sqlVehicle = "INSERT INTO tbl_return_vehicle 
                    (return_checklist_name, return_isActive) 
                    VALUES (:checklist_name, :is_active)";
                
                $stmtVehicle = $this->conn->prepare($sqlVehicle);
                foreach ($data['vehicle'] as $item) {
                    $stmtVehicle->execute([
                        'checklist_name' => $item['name'],
                        'is_active' => $item['isActive']
                    ]);
                }
                $returnVehicleId = $this->conn->lastInsertId();
            }

            // Then insert into checklist master
            $sqlMaster = "INSERT INTO tbl_checklist_master 
                (checklist_reservation_id, checklist_return_venue_id, 
                checklist_return_vehicle_id, checklist_admin_id, 
                checklist_personnel_id) 
                VALUES (:reservation_id, :return_venue_id, 
                :return_vehicle_id, :admin_id, :personnel_id)";
            
            $stmtMaster = $this->conn->prepare($sqlMaster);
            $stmtMaster->execute([
                'reservation_id' => $data['reservation_id'],
                'return_venue_id' => $returnVenueId,
                'return_vehicle_id' => $returnVehicleId,
                'admin_id' => $data['admin_id'],
                'personnel_id' => $data['personnel_id']
            ]);

            $checklistId = $this->conn->lastInsertId();

            // Insert checklist status
            $sqlStatus = "INSERT INTO tbl_checklist_status 
                (checklist_status_status_checklist_id, checklist_checklist_id) 
                VALUES (:status_id, :checklist_id)";
            
            $stmtStatus = $this->conn->prepare($sqlStatus);
            $stmtStatus->execute([
                'status_id' => 1, // Set initial status to 1
                'checklist_id' => $checklistId
            ]);

            $this->conn->commit();
            
            return json_encode([
                'status' => 'success',
                'checklist_id' => $checklistId,
                'return_venue_id' => $returnVenueId,
                'return_vehicle_id' => $returnVehicleId
            ]);

        } catch (PDOException $e) {
            $this->conn->rollBack();
            return json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }

    // Fetch assigned release records
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
