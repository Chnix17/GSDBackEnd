<?php
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS, GET");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

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
    public function fetchAssignedRelease($personnel_id) {
        if (!$personnel_id) {
            return json_encode(['status' => 'error', 'message' => 'Personnel ID is required']);
        }
    
        try {
            // Prepare the final response array
            $response = [];
            $reservations = [];
    
            // Fetch checklist venue data where personnel_id is present
            $sqlVenue = "
                SELECT 
                    rc_venue.checklist_venue_id,
                    rc_venue.reservation_checklist_venue_id,
                    rc_venue.isChecked AS venue_isChecked,
                    rv.reservation_reservation_id AS reservation_reservation_id,
                    rv.reservation_venue_venue_id,
                    v.ven_name AS venue_name,
                    cvc.checklist_name AS checklist_venue_name
                FROM tbl_reservation_checklist_venue rc_venue
                LEFT JOIN tbl_reservation_venue rv ON rc_venue.reservation_venue_id = rv.reservation_venue_id
                LEFT JOIN tbl_venue v ON rv.reservation_venue_venue_id = v.ven_id
                LEFT JOIN tbl_checklist_venue_master cvc ON rc_venue.checklist_venue_id = cvc.checklist_venue_id
                WHERE rc_venue.personnel_id = :personnel_id
            ";
            $stmtVenue = $this->conn->prepare($sqlVenue);
            $stmtVenue->execute(['personnel_id' => $personnel_id]);
            $venueData = $stmtVenue->fetchAll(PDO::FETCH_ASSOC);
    
            // Fetch checklist vehicle data where personnel_id is present
            $sqlVehicle = "
                SELECT 
                    rc_vehicle.checklist_vehicle_id,
                    rc_vehicle.reservation_checklist_vehicle_id,
                    rc_vehicle.isChecked AS vehicle_isChecked,
                    rv.reservation_reservation_id AS reservation_reservation_id,
                    rv.reservation_vehicle_vehicle_id,
                    cvcv.checklist_name AS checklist_vehicle_name
                FROM tbl_reservation_checklist_vehicle rc_vehicle
                LEFT JOIN tbl_reservation_vehicle rv ON rc_vehicle.reservation_vehicle_id = rv.reservation_vehicle_id
                LEFT JOIN tbl_checklist_vehicle_master cvcv ON rc_vehicle.checklist_vehicle_id = cvcv.checklist_vehicle_id
                WHERE rc_vehicle.personnel_id = :personnel_id
            ";
            $stmtVehicle = $this->conn->prepare($sqlVehicle);
            $stmtVehicle->execute(['personnel_id' => $personnel_id]);
            $vehicleData = $stmtVehicle->fetchAll(PDO::FETCH_ASSOC);
    
            // Fetch checklist equipment data where personnel_id is present
            $sqlEquipment = "
                SELECT 
                    rc_equipment.checklist_equipment_id,
                    rc_equipment.reservation_checklist_equipment_id,
                    rc_equipment.isChecked AS equipment_isChecked,
                    re.reservation_reservation_id AS reservation_reservation_id,
                    re.reservation_equipment_equip_id,
                    cec.checklist_name AS checklist_equipment_name
                FROM tbl_reservation_checklist_equipment rc_equipment
                LEFT JOIN tbl_reservation_equipment re ON rc_equipment.reservation_equipment_id = re.reservation_equipment_id
                LEFT JOIN tbl_checklist_equipment_master cec ON rc_equipment.checklist_equipment_id = cec.checklist_equipment_id
                WHERE rc_equipment.personnel_id = :personnel_id
            ";
            $stmtEquipment = $this->conn->prepare($sqlEquipment);
            $stmtEquipment->execute(['personnel_id' => $personnel_id]);
            $equipmentData = $stmtEquipment->fetchAll(PDO::FETCH_ASSOC);
    
            // Combine the data into reservations array by reservation_reservation_id
            foreach (array_merge($venueData, $vehicleData, $equipmentData) as $data) {
                // Use reservation_reservation_id to identify the reservation
                $reservation_id = isset($data['reservation_reservation_id']) ? $data['reservation_reservation_id'] : null;
    
                // Initialize reservation if not already initialized
                if (!isset($reservations[$reservation_id])) {
                    $reservations[$reservation_id] = [
                        'reservation_id' => $reservation_id, // Added reservation_id
                        'reservation_title' => '', // Will set title once fetched
                        'venue' => [
                            'reservation_venue_id' => null,  
                            'reservation_venue_venue_id' => null,  
                            'name' => '',
                            'checklists' => []
                        ],
                        'vehicle' => [
                            'reservation_vehicle_id' => null, 
                            'vehicle_license' => '',
                            'vehicle_model_id' => null,
                            'vehicle_model_name' => '',
                            'checklists' => []
                        ],
                        'equipment' => [
                            'reservation_equipment_id' => null, 
                            'name' => '',
                            'checklists' => []
                        ]
                    ];
                }
    
                // Add venue checklist data if present
                if (isset($data['reservation_venue_venue_id'])) {
                    $reservations[$reservation_id]['venue']['reservation_venue_venue_id'] = $data['reservation_venue_venue_id']; 
                    $reservations[$reservation_id]['venue']['checklists'][] = [
                        'checklist_venue_id' => $data['checklist_venue_id'],
                        'reservation_checklist_venue_id' => $data['reservation_checklist_venue_id'],
                        'checklist_name' => $data['checklist_venue_name'],  // Added checklist name
                        'isChecked' => $data['venue_isChecked']
                    ];
                    if (isset($data['venue_name'])) {
                        $reservations[$reservation_id]['venue']['name'] = $data['venue_name'];
                    }
                }
    
                // Add vehicle checklist data if present
                if (isset($data['reservation_vehicle_vehicle_id'])) {
                    $reservations[$reservation_id]['vehicle']['reservation_vehicle_id'] = $data['reservation_vehicle_vehicle_id']; 
                    $reservations[$reservation_id]['vehicle']['checklists'][] = [
                        'checklist_vehicle_id' => $data['checklist_vehicle_id'],
                        'reservation_checklist_vehicle_id' => $data['reservation_checklist_vehicle_id'],
                        'checklist_name' => $data['checklist_vehicle_name'], // Added checklist name
                        'isChecked' => $data['vehicle_isChecked']
                    ];
                }
    
                // Add equipment checklist data if present
                if (isset($data['reservation_equipment_equip_id'])) {
                    $reservations[$reservation_id]['equipment']['reservation_equipment_id'] = $data['reservation_equipment_equip_id']; 
                    $reservations[$reservation_id]['equipment']['checklists'][] = [
                        'checklist_equipment_id' => $data['checklist_equipment_id'],
                        'reservation_checklist_equipment_id' => $data['reservation_checklist_equipment_id'],
                        'checklist_name' => $data['checklist_equipment_name'], // Added checklist name
                        'isChecked' => $data['equipment_isChecked']
                    ];
                }
            }
    
            // Fetch reservation title using reservation_reservation_id
            foreach ($reservations as $reservation_id => $reservation) {
                if ($reservation['venue']['reservation_venue_venue_id'] || $reservation['vehicle']['reservation_vehicle_id'] || $reservation['equipment']['reservation_equipment_id']) {
                    $sqlReservation = "
                        SELECT reservation_id, reservation_title
                        FROM tbl_reservation
                        WHERE reservation_id = :reservation_id
                    ";
                    $stmtReservation = $this->conn->prepare($sqlReservation);
                    $stmtReservation->execute(['reservation_id' => $reservation_id]);
                    $reservationData = $stmtReservation->fetch(PDO::FETCH_ASSOC);
    
                    if ($reservationData) {
                        $reservations[$reservation_id]['reservation_title'] = $reservationData['reservation_title'];
                    }
                }
            }
    
            // Return the response with reservation_id included
            return json_encode(['status' => 'success', 'data' => array_values($reservations)]);
    
        } catch (PDOException $e) {
            return json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }
    
    
    
    
    
    

    public function updateTask($data) {
        if (!isset($data['type']) || !isset($data['id']) || !isset($data['isActive'])) {
            return json_encode(['status' => 'error', 'message' => 'Missing required parameters']);
        }
    
        try {
            $type = $data['type'];
            $id = $data['id'];
            $isActive = $data['isActive'];
            $timestamp = date('Y-m-d H:i:s');
    
            // Initialize SQL and prepare query based on type
            $sql = '';
            
            if ($type === 'venue') {
                // SQL for updating tbl_reservation_checklist_venue
                $sql = "UPDATE tbl_reservation_checklist_venue 
                        SET isChecked = :isActive
                        WHERE reservation_checklist_venue_id = :id";
            } else if ($type === 'vehicle') {
                // SQL for updating tbl_reservation_checklist_vehicle
                $sql = "UPDATE tbl_reservation_checklist_vehicle 
                        SET isChecked = :isActive
                        WHERE reservation_checklist_vehicle_id = :id";
            } else if ($type === 'equipment') {
                // SQL for updating tbl_reservation_checklist_equipment
                $sql = "UPDATE tbl_reservation_checklist_equipment 
                        SET isChecked = :isActive
                        WHERE reservation_checklist_equipment_id = :id";
            } else {
                return json_encode(['status' => 'error', 'message' => 'Invalid type specified']);
            }
    
            // Prepare and execute the SQL query
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([
                'isActive' => $isActive,  // isActive corresponds to isChecked
                'id' => $id
            ]);
    
            // Check if any rows were affected by the update
            if ($stmt->rowCount() === 0) {
                return json_encode([
                    'status' => 'error',
                    'message' => 'No record found to update'
                ]);
            }
    
            return json_encode([
                'status' => 'success',
                'message' => 'Task updated successfully',
                'timestamp' => $timestamp
            ]);
    
        } catch (PDOException $e) {
            return json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }
    

    public function fetchTaskById($data) {
        if (!isset($data['checklist_id'])) {
            return json_encode(['status' => 'error', 'message' => 'Checklist ID is required']);
        }

        try {
            // Get the checklist master data
            $sqlMaster = "SELECT 
                checklist_id,
                checklist_reservation_id,
                checklist_admin_id,
                checklist_personnel_id
            FROM tbl_checklist_master 
            WHERE checklist_id = :checklist_id";

            $stmtMaster = $this->conn->prepare($sqlMaster);
            $stmtMaster->execute(['checklist_id' => $data['checklist_id']]);
            $masterData = $stmtMaster->fetch(PDO::FETCH_ASSOC);

            if (!$masterData) {
                return json_encode(['status' => 'error', 'message' => 'Checklist not found']);
            }

            $response = [
                'master_data' => $masterData,
                'venue_tasks' => [],
                'vehicle_tasks' => []
            ];

            // Get venue tasks
            $sqlVenue = "SELECT 
                release_venue_id,
                release_checklist_name,
                release_isActive,
                release_updated_at
            FROM tbl_release_venue_equipment 
            WHERE checklist_master_id = :checklist_master_id";
            
            $stmtVenue = $this->conn->prepare($sqlVenue);
            $stmtVenue->execute(['checklist_master_id' => $data['checklist_id']]);
            $response['venue_tasks'] = $stmtVenue->fetchAll(PDO::FETCH_ASSOC);

            // Get vehicle tasks
            $sqlVehicle = "SELECT 
                release_vehicle_id,
                release_checklist_name,
                release_isActive,
                release_updated_at
            FROM tbl_release_vehicle 
            WHERE checklist_master_id = :checklist_master_id";
            
            $stmtVehicle = $this->conn->prepare($sqlVehicle);
            $stmtVehicle->execute(['checklist_master_id' => $data['checklist_id']]);
            $response['vehicle_tasks'] = $stmtVehicle->fetchAll(PDO::FETCH_ASSOC);

            return json_encode(['status' => 'success', 'data' => $response]);

        } catch (PDOException $e) {
            return json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }

    public function insertComplete($checklist_id) {
        if (!$checklist_id) {
            return json_encode(['status' => 'error', 'message' => 'Checklist ID is required']);
        }

        try {
            $timestamp = date('Y-m-d H:i:s');

            // Insert single status record
            $sql = "INSERT INTO tbl_checklist_status 
                    (checklist_status_status_checklist_id, checklist_checklist_id, checklist_updated_at)
                    VALUES (2, :checklist_id, :timestamp)";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([
                'checklist_id' => $checklist_id,
                'timestamp' => $timestamp
            ]);

            return json_encode([
                'status' => 'success',
                'message' => 'Completion status inserted successfully',
                'timestamp' => $timestamp
            ]);

        } catch (PDOException $e) {
            return json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }

    public function fetchRecent() {
        try {
            $sql = "SELECT 
                cm.checklist_id,  
                cs.checklist_updated_at  
            FROM 
                tbl_checklist_master cm
            JOIN
                tbl_checklist_status cs ON cm.checklist_id = cs.checklist_checklist_id  
            WHERE 
                checklist_status_status_checklist_id IN (1)
            ORDER BY
                cs.checklist_updated_at DESC";

            return $this->executeQuery($sql);
        } catch (PDOException $e) {
            return json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }
    
    public function fetchCompletedTask($personnel_id) {
        if (!$personnel_id) {
            return json_encode(['status' => 'error', 'message' => 'Personnel ID is required']);
        }
    
        try {
            // Get master checklist data with status filter (checklist_status_status_checklist_id = 2)
            $sqlMaster = "
                SELECT 
                    cm.checklist_id,
                    cm.checklist_reservation_id,
                    cm.checklist_admin_id,
                    cm.checklist_personnel_id,
                    r.reservation_date,
                    cs.checklist_status_id,
                    cs.checklist_status_status_checklist_id,
                    cs.checklist_checklist_id,
                    cs.checklist_updated_at,
                    sc.status_checklist_name,
                    a.approval_form_venue_id,
                    a.approval_form_vehicle_id,
                    GROUP_CONCAT(DISTINCT rfv.reservation_form_name) AS venue_form_name,
                    GROUP_CONCAT(DISTINCT rfv_v.reservation_form_name) AS vehicle_form_name,
                    GROUP_CONCAT(DISTINCT rfv.reservation_form_start_date) AS venue_form_start_date,
                    GROUP_CONCAT(DISTINCT rfv_v.reservation_form_start_date) AS vehicle_form_start_date,
                    GROUP_CONCAT(DISTINCT rfv.reservation_form_end_date) AS venue_form_end_date,
                    GROUP_CONCAT(DISTINCT rfv_v.reservation_form_end_date) AS vehicle_form_end_date
                FROM 
                    tbl_checklist_master cm
                LEFT JOIN
                    tbl_reservation r ON cm.checklist_reservation_id = r.reservation_id
                LEFT JOIN
                    tbl_checklist_status cs ON cm.checklist_id = cs.checklist_checklist_id
                INNER JOIN
                    tbl_status_checklist sc ON cs.checklist_status_status_checklist_id = sc.status_checklist_id
                LEFT JOIN 
                    tbl_approval a ON r.reservation_approval_id = a.approval_id
                LEFT JOIN 
                    tbl_reservation_form_venue rfv ON a.approval_form_venue_id = rfv.reservation_form_venue_id
                LEFT JOIN 
                    tbl_reservation_form_vehicle rfv_v ON a.approval_form_vehicle_id = rfv_v.reservation_form_vehicle_id
                WHERE
                    cm.checklist_personnel_id = :personnel_id
                    AND cs.checklist_status_status_checklist_id = 2
                GROUP BY
                    cm.checklist_id
            ";

            $stmtMaster = $this->conn->prepare($sqlMaster);
            $stmtMaster->execute(['personnel_id' => $personnel_id]);
            $masterData = $stmtMaster->fetchAll(PDO::FETCH_ASSOC);

            if (!$masterData) {
                return json_encode(['status' => 'error', 'message' => 'No completed tasks found']);
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

                // Get vehicle release checklist
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

// Handle the request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get JSON input
    $jsonInput = json_decode(file_get_contents('php://input'), true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        die(json_encode([
            'status' => 'error',
            'message' => 'Invalid JSON format',
            'timestamp' => date('Y-m-d H:i:s')
        ]));
    }

    $operation = $jsonInput['operation'] ?? '';
    $personnel_id = $jsonInput['personnel_id'] ?? null;
    
    // Validate required parameters
    if (!$operation) {
        die(json_encode([
            'status' => 'error',
            'message' => 'Operation is required',
            'timestamp' => date('Y-m-d H:i:s')
        ]));
    }

    $user = new User();
    
    switch ($operation) {
        case 'fetchAssignedRelease':
            if (!$personnel_id) {
                die(json_encode([
                    'status' => 'error',
                    'message' => 'Personnel ID is required',
                    'timestamp' => date('Y-m-d H:i:s')
                ]));
            }
            echo $user->fetchAssignedRelease($personnel_id);
            break;
    
        case 'fetchCompletedTask':  // Add this case for fetchCompletedTask
            if (!$personnel_id) {
                die(json_encode([
                    'status' => 'error',
                    'message' => 'Personnel ID is required',
                    'timestamp' => date('Y-m-d H:i:s')
                ]));
            }
            echo $user->fetchCompletedTask($personnel_id);
            break;
    
        case 'updateTask':
            echo $user->updateTask($jsonInput);
            break;
    
        case 'fetchTaskById':
            echo $user->fetchTaskById($jsonInput);
            break;
    
        case 'insertComplete':
            $checklist_id = $jsonInput['checklist_id'] ?? null;
            if (!$checklist_id) {
                die(json_encode([
                    'status' => 'error',
                    'message' => 'Checklist ID is required',
                    'timestamp' => date('Y-m-d H:i:s')
                ]));
            }
            echo $user->insertComplete($checklist_id);
            break;
    
        case 'fetchRecent':
            echo $user->fetchRecent();
            break;
    
        default:
            echo json_encode(['status' => 'error', 'message' => 'Invalid operation']);
            break;
    }
}
?>
