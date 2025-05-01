<?php
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

class Dashboard {
    private $conn;

    public function __construct() {
        include 'connection-pdo.php'; 
        $this->conn = $conn;
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

    public function fetchRequest() {
        try {
            // Query to fetch pending requests (reservation_status_status_reservation_id = 1)
            $query = "
                SELECT 
                    r.reservation_id,
                    r.reservation_date,
                    v.reservation_form_name AS venue_name,
                    vh.reservation_form_name AS vehicle_name
                FROM tbl_reservation r
                JOIN tbl_reservation_status rs ON r.reservation_id = rs.reservation_status_reservation_id
                LEFT JOIN tbl_approval a ON r.reservation_approval_id = a.approval_id
                LEFT JOIN tbl_reservation_form_venue v ON a.approval_form_venue_id = v.reservation_form_venue_id
                LEFT JOIN tbl_reservation_form_vehicle vh ON a.approval_form_vehicle_id = vh.reservation_form_vehicle_id
                WHERE rs.reservation_status_status_reservation_id = 1
            ";

            $stmt = $this->conn->query($query);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Format the results
            $formattedResults = [];
            foreach ($results as $row) {
                $formattedResults[] = [
                    'reservation_id' => $row['reservation_id'],
                    'reservation_date' => $row['reservation_date'],
                    'reservation_form_name' => $row['venue_name'] ?? $row['vehicle_name']
                ];
            }

            return json_encode([
                'status' => 'success',
                'data' => $formattedResults
            ]);

        } catch (PDOException $e) {
            return json_encode([
                'status' => 'error',
                'message' => $e->getMessage()
            ]);
        }
    }

    public function fetchCompletedTask() {
        try {
            // Query to fetch completed tasks for personnel (checklist_status_status_checklist_id = 2)
            $query = "
                SELECT 
                    p.jo_personel_fname,
                    p.jo_personel_mname,
                    p.jo_personel_lname,
                    sc.status_checklist_name
                FROM tbl_checklist_master cm
                JOIN tbl_checklist_status cs ON cm.checklist_id = cs.checklist_checklist_id
                JOIN tbl_status_checklist sc ON cs.checklist_status_status_checklist_id = sc.status_checklist_id
                JOIN tbl_personel p ON cm.checklist_personnel_id = p.jo_personel_id
                WHERE cs.checklist_status_status_checklist_id = 2
            ";

            $stmt = $this->conn->query($query);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Format the results
            $formattedResults = [];
            foreach ($results as $row) {
                $formattedResults[] = [
                    'personnel_full_name' => trim($row['jo_personel_fname'] . ' ' . $row['jo_personel_mname'] . ' ' . $row['jo_personel_lname']),
                    'status_name' => $row['status_checklist_name']
                ];
            }

            return json_encode([
                'status' => 'success',
                'data' => $formattedResults
            ]);

        } catch (PDOException $e) {
            return json_encode([
                'status' => 'error',
                'message' => $e->getMessage()
            ]);
        }
    }

    public function fetchReservedAndDeclinedReservations() {
        try {
            // Query to fetch reserved and declined reservations (status_id = 2 for reserved, status_id = 6 for declined)
            // Left join with tbl_status_master to get the status names
            $query = "
                SELECT 
                    r.reservation_id,
                    rs.reservation_status_status_id,
                    sm.status_master_name AS status_name,
                    rs.reservation_active,
                    rs.reservation_updated_at
                FROM tbl_reservation r
                JOIN tbl_reservation_status rs ON r.reservation_id = rs.reservation_reservation_id
                LEFT JOIN tbl_status_master sm ON rs.reservation_status_status_id = sm.status_master_id
                WHERE rs.reservation_status_status_id IN (2, 6)
            ";

            $stmt = $this->conn->query($query);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Format the results
            $formattedResults = [];
            foreach ($results as $row) {
                $formattedResults[] = [
                    'reservation_id' => $row['reservation_id'],
                    'status_id' => $row['reservation_status_status_id'],
                    'status_name' => $row['status_name'],
                    'reservation_active' => $row['reservation_active'],
                    'reservation_updated_at' => $row['reservation_updated_at']
                ];
            }

            return json_encode([
                'status' => 'success',
                'data' => $formattedResults
            ]);

        } catch (PDOException $e) {
            return json_encode([
                'status' => 'error',
                'message' => $e->getMessage()
            ]);
        }
    }

    public function getAvailabilityStatus() {
        try {
            // Aggregate totals
            $totals = [
                'vehicles'          => (int)$this->conn->query("SELECT COUNT(*) FROM tbl_vehicle WHERE is_active = 1")->fetchColumn(),
                'venues'            => (int)$this->conn->query("SELECT COUNT(*) FROM tbl_venue WHERE is_active = 1")->fetchColumn(),
                'equipments'        => (int)$this->conn->query("SELECT COUNT(*) FROM tbl_equipments WHERE is_active = 1")->fetchColumn(),
                'vehicles_in_use'   => 0,
                'venues_in_use'     => 0,
                'equipments_in_use' => 0,
            ];
    
            // Vehicles in use
            $sql = "
                SELECT COUNT(DISTINCT rv.reservation_vehicle_vehicle_id) AS in_use
                FROM tbl_reservation_vehicle rv
                JOIN tbl_reservation r ON rv.reservation_reservation_id = r.reservation_id
                JOIN tbl_reservation_status rs ON rs.reservation_reservation_id = r.reservation_id
                WHERE rs.reservation_status_status_id = 6
                  AND rs.reservation_active = 1
                  AND NOW() BETWEEN r.reservation_start_date AND r.reservation_end_date
            ";
            $totals['vehicles_in_use'] = (int)$this->conn->query($sql)->fetchColumn();
    
            // Venues in use
            $sql = "
                SELECT COUNT(DISTINCT rv.reservation_venue_venue_id) AS in_use
                FROM tbl_reservation_venue rv
                JOIN tbl_reservation r ON rv.reservation_reservation_id = r.reservation_id
                JOIN tbl_reservation_status rs ON rs.reservation_reservation_id = r.reservation_id
                WHERE rs.reservation_status_status_id = 6
                  AND rs.reservation_active = 1
                  AND NOW() BETWEEN r.reservation_start_date AND r.reservation_end_date
            ";
            $totals['venues_in_use'] = (int)$this->conn->query($sql)->fetchColumn();
    
            // Equipments in use
            $sql = "
                SELECT COUNT(DISTINCT re.reservation_equipment_equip_id) AS in_use
                FROM tbl_reservation_equipment re
                JOIN tbl_reservation r ON re.reservation_reservation_id = r.reservation_id
                JOIN tbl_reservation_status rs ON rs.reservation_reservation_id = r.reservation_id
                WHERE rs.reservation_status_status_id = 6
                  AND rs.reservation_active = 1
                  AND NOW() BETWEEN r.reservation_start_date AND r.reservation_end_date
            ";
            $totals['equipments_in_use'] = (int)$this->conn->query($sql)->fetchColumn();
    
            return json_encode([
                'status' => 'success',
                'data'   => $totals
            ]);
        } catch (PDOException $e) {
            return json_encode([
                'status'  => 'error',
                'message' => $e->getMessage()
            ]);
        }
    }

    public function displayedMaintenanceResources() {
        try {
            $records = [];
    
            // 1) Equipment under maintenance
            $sql = "
                SELECT 
                    rce.id AS record_id,
                    'equipment' AS resource_type,
                    e.equip_name AS resource_name,
                    re.reservation_equipment_quantity AS quantity,
                    re.reservation_equipment_equip_id AS resource_id,
                    CASE 
                        WHEN c.condition_name = 'Other' THEN rce.other_reason 
                        ELSE c.condition_name 
                    END AS condition_name
                FROM tbl_reservation_condition_equipment rce
                JOIN tbl_reservation_equipment re ON rce.reservation_equipment_id = re.reservation_equipment_id
                JOIN tbl_equipments e ON re.reservation_equipment_equip_id = e.equip_id
                JOIN tbl_condition c ON rce.condition_id = c.id
                WHERE rce.condition_id != 2
                  AND rce.is_active = 1
            ";
            foreach ($this->conn->query($sql, PDO::FETCH_ASSOC) as $row) {
                $records[] = $row;
            }
    
            // 2) Venues under maintenance
            $sql = "
                SELECT 
                    rcv.id AS record_id,
                    'venue' AS resource_type,
                    v.ven_name AS resource_name,
                    NULL AS quantity,
                    rv.reservation_venue_venue_id AS resource_id,
                    CASE 
                        WHEN c.condition_name = 'Other' THEN rcv.other_reason 
                        ELSE c.condition_name 
                    END AS condition_name
                FROM tbl_reservation_condition_venue rcv
                JOIN tbl_reservation_venue rv ON rcv.reservation_venue_id = rv.reservation_venue_id
                JOIN tbl_venue v ON rv.reservation_venue_venue_id = v.ven_id
                JOIN tbl_condition c ON rcv.condition_id = c.id
                WHERE rcv.condition_id != 2
                  AND rcv.is_active = 1
            ";
            foreach ($this->conn->query($sql, PDO::FETCH_ASSOC) as $row) {
                $records[] = $row;
            }
    
            // 3) Vehicles under maintenance
            $sql = "
                SELECT
                    rcvh.id AS record_id,
                    'vehicle' AS resource_type,
                    CONCAT(vm.vehicle_model_name, ' (', vh.vehicle_license, ')') AS resource_name,
                    NULL AS quantity,
                    rv.reservation_vehicle_vehicle_id AS resource_id,
                    CASE 
                        WHEN c.condition_name = 'Other' THEN rcvh.other_reason 
                        ELSE c.condition_name 
                    END AS condition_name
                FROM tbl_reservation_condition_vehicle rcvh
                JOIN tbl_reservation_vehicle rv ON rcvh.reservation_vehicle_id = rv.reservation_vehicle_id
                JOIN tbl_vehicle vh ON rv.reservation_vehicle_vehicle_id = vh.vehicle_id
                JOIN tbl_vehicle_model vm ON vh.vehicle_model_id = vm.vehicle_model_id
                JOIN tbl_condition c ON rcvh.condition_id = c.id
                WHERE rcvh.condition_id != 2
                  AND rcvh.is_active = 1
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
                'status' => 'error',
                'message' => $e->getMessage()
            ]);
        }
    }
    
    
    
    public function countMaintenanceResources() {
        try {
            // Total available counts
            $totalVehicles   = (int)$this->conn->query("SELECT COUNT(*) FROM tbl_vehicle WHERE is_active = 1")->fetchColumn();
            $totalVenues     = (int)$this->conn->query("SELECT COUNT(*) FROM tbl_venue WHERE is_active = 1")->fetchColumn();
            $totalEquipments = (int)$this->conn->query("SELECT COUNT(*) FROM tbl_equipments WHERE is_active = 1")->fetchColumn();
    
            // Vehicles in use
            $vehiclesInUse = (int)$this->conn->query("
                SELECT COUNT(DISTINCT rv.reservation_vehicle_vehicle_id)
                FROM tbl_reservation_vehicle rv
                JOIN tbl_reservation r ON rv.reservation_reservation_id = r.reservation_id
                JOIN tbl_reservation_status rs ON rs.reservation_reservation_id = r.reservation_id
                WHERE rs.reservation_status_status_id = 6
                  AND rs.reservation_active = 1
                  AND NOW() BETWEEN r.reservation_start_date AND r.reservation_end_date
            ")->fetchColumn();
    
            // Venues in use
            $venuesInUse = (int)$this->conn->query("
                SELECT COUNT(DISTINCT rv.reservation_venue_venue_id)
                FROM tbl_reservation_venue rv
                JOIN tbl_reservation r ON rv.reservation_reservation_id = r.reservation_id
                JOIN tbl_reservation_status rs ON rs.reservation_reservation_id = r.reservation_id
                WHERE rs.reservation_status_status_id = 6
                  AND rs.reservation_active = 1
                  AND NOW() BETWEEN r.reservation_start_date AND r.reservation_end_date
            ")->fetchColumn();
    
            // Equipments in use
            $equipmentsInUse = (int)$this->conn->query("
                SELECT COUNT(DISTINCT re.reservation_equipment_equip_id)
                FROM tbl_reservation_equipment re
                JOIN tbl_reservation r ON re.reservation_reservation_id = r.reservation_id
                JOIN tbl_reservation_status rs ON rs.reservation_reservation_id = r.reservation_id
                WHERE rs.reservation_status_status_id = 6
                  AND rs.reservation_active = 1
                  AND NOW() BETWEEN r.reservation_start_date AND r.reservation_end_date
            ")->fetchColumn();
    
            // Categorize as Good and Poor
            $good = ($totalVehicles - $vehiclesInUse)
                  + ($totalVenues - $venuesInUse)
                  + ($totalEquipments - $equipmentsInUse);
    
            $poor = $vehiclesInUse + $venuesInUse + $equipmentsInUse;
    
            return json_encode([
                'status' => 'success',
                'data'   => [
                    'good' => $good,
                    'poor' => $poor
                ]
            ]);
        } catch (PDOException $e) {
            return json_encode([
                'status'  => 'error',
                'message' => $e->getMessage()
            ]);
        }
    }

    public function updateResourceStatusAndCondition($type, $resourceId, $recordId) {
        try {
            $type = strtolower($type); // normalize input
            $resourceId = (int)$resourceId;
            $recordId = (int)$recordId;
    
            // Define table and column mappings for resources
            $resourceMap = [
                'equipment' => ['table' => 'tbl_equipments', 'column' => 'equip_id'],
                'venue'     => ['table' => 'tbl_venue',     'column' => 'ven_id'],
                'vehicle'   => ['table' => 'tbl_vehicle',   'column' => 'vehicle_id'],
            ];
    
            // Define table mappings for conditions
            $conditionMap = [
                'equipment' => 'tbl_reservation_condition_equipment',
                'venue'     => 'tbl_reservation_condition_venue',
                'vehicle'   => 'tbl_reservation_condition_vehicle',
            ];
    
            // Check if resource type is valid
            if (!isset($resourceMap[$type]) || !isset($conditionMap[$type])) {
                return json_encode([
                    'status' => 'error',
                    'message' => 'Invalid resource type.'
                ]);
            }
    
            // Get table and column names
            $resourceTable = $resourceMap[$type]['table'];
            $resourceColumn = $resourceMap[$type]['column'];
            $conditionTable = $conditionMap[$type];
    
            // Start transaction to ensure atomicity
            $this->conn->beginTransaction();
    
            // 1) Update the resource status availability to 1 (Available)
            $stmt = $this->conn->prepare("UPDATE $resourceTable SET status_availability_id = 1 WHERE $resourceColumn = :resourceId");
            $stmt->bindParam(':resourceId', $resourceId, PDO::PARAM_INT);
            $stmt->execute();
    
            // 2) Update the condition record's is_active to 0
            $stmt = $this->conn->prepare("UPDATE $conditionTable SET is_active = 0 WHERE id = :recordId");
            $stmt->bindParam(':recordId', $recordId, PDO::PARAM_INT);
            $stmt->execute();
    
            // Commit transaction
            $this->conn->commit();
    
            return json_encode([
                'status' => 'success',
                'message' => "Updated $type (ID: $resourceId) to status_availability_id = 1 and set condition record ID $recordId to is_active = 0."
            ]);
        } catch (PDOException $e) {
            // Rollback transaction if an error occurs
            $this->conn->rollBack();
            return json_encode([
                'status' => 'error',
                'message' => $e->getMessage()
            ]);
        }
    }
    
    
    
    
    
    
    
    
    
    
    
}

// Handle requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' || $_SERVER['REQUEST_METHOD'] === 'GET') {
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);

    if (isset($data['operation'])) {
        $dashboard = new Dashboard();

        switch ($data['operation']) {
            case 'getTotals':
                echo $dashboard->getTotals();
                break;

            case 'fetchRequest':
                echo $dashboard->fetchRequest();
                break;

            case 'fetchCompletedTask':
                echo $dashboard->fetchCompletedTask();
                break;
            
            case 'fetchReservedAndDeclinedReservations':
                echo $dashboard->fetchReservedAndDeclinedReservations();
                break;

            case 'getAvailabilityStatus':
                echo $dashboard->getAvailabilityStatus();
                break;

            case 'displayedMaintenanceResources':
                echo $dashboard->displayedMaintenanceResources();
                break;

            case 'countMaintenanceResources':
                echo $dashboard->countMaintenanceResources();
                break;

            case 'updateResourceStatusAndCondition':
            case 'updateSingleResourceAvailability':  // Add support for the alternate operation name
                if (isset($data['type']) && 
                    (isset($data['resourceId']) || isset($data['resource_id'])) && 
                    (isset($data['recordId']) || isset($data['record_id']))) {
                    
                    // Handle both parameter naming conventions
                    $resourceId = isset($data['resourceId']) ? $data['resourceId'] : $data['resource_id'];
                    $recordId = isset($data['recordId']) ? $data['recordId'] : $data['record_id'];
                    
                    echo $dashboard->updateResourceStatusAndCondition($data['type'], $resourceId, $recordId);
                } else {
                    echo json_encode([
                        'status' => 'error',
                        'message' => 'Missing required parameters: type, resource_id/resourceId, and record_id/recordId are required.'
                    ]);
                }
                break;
                
            default:
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Invalid operation'
                ]);
                break;
        }
    } else {
        echo json_encode([
            'status' => 'error',
            'message' => 'Operation not specified'
        ]);
    }
}
?>