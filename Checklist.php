<?php
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

class Checklist {
    private $conn;

    public function __construct() {
        include 'connection-pdo.php';
        $this->conn = $conn;
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


 

    
    

}

// Handle incoming requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $checklist = new Checklist();
    $data = json_decode(file_get_contents('php://input'), true);

    if (isset($data['operation'])) {
        switch ($data['operation']) {

            case "fetchAllResources":
                $type = $data['type'] ?? ($_POST['type'] ?? null);
                echo $checklist->fetchAllResources($type);
                break;

            case "saveMasterChecklist":
                $checklistNames = $data['checklistNames'] ?? [];
                $type = $data['type'] ?? '';
                $id = $data['id'] ?? 0;
                $user_personnel_id = $data['user_personnel_id'] ?? ($_POST['user_personnel_id'] ?? null);
                echo $checklist->saveMasterChecklist($checklistNames, $type, $id, $user_personnel_id);
                break;
    
            case "updateChecklist":
                $data = $data['data'] ?? null;
                if (!$data) {
                    echo json_encode(['status' => 'error', 'message' => 'No data provided']);
                    break;
                }
                // Support user_personnel_id passed at the top-level of the request
                $top_user_pid = $data['user_personnel_id'] ?? ($_POST['user_personnel_id'] ?? null);
                if (is_array($data) && !isset($data['user_personnel_id']) && $top_user_pid !== null) {
                    $data['user_personnel_id'] = $top_user_pid;
                }
                echo $checklist->updateChecklist($data);
                break;
    
            case "fetchChecklistById":
                $type = $data['type'] ?? '';
                $id = $data['id'] ?? 0;
                echo $checklist->fetchChecklistById($type, $id);
                break;
    
            case "fetchChecklist":
                echo $checklist->fetchChecklist();
                break;
           

            default:
                echo json_encode(['status' => 'error', 'message' => 'Invalid operation.']);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'No operation specified.']);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method.']);
}