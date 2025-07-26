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

    // Revised fetchUsers method (unchanged)
    public function fetchUsers() {
        $sql = "SELECT users_id, users_name, users_school_id, users_contact_number users_username 
                FROM tbl_users 
                ORDER BY users_name";
        return $this->executeQuery($sql);
    }

    // public function fetchVehicles() {
    //     $sql = "SELECT  
    //                 v.vehicle_id,
    //                 v.vehicle_pic,
    //                 v.year,
    //                 vm.vehicle_make_name, 
    //                 vc.vehicle_category_name,
    //                 vmd.vehicle_model_name,      
    //                 v.vehicle_license,
    //                 sa.status_availability_name
    //             FROM 
    //                 tbl_vehicle v 
    //             INNER JOIN 
    //                 tbl_vehicle_model vmd ON v.vehicle_model_id = vmd.vehicle_model_id 
    //             INNER JOIN 
    //                 tbl_vehicle_make vm ON vmd.vehicle_model_vehicle_make_id = vm.vehicle_make_id 
    //             INNER JOIN 
    //                 tbl_vehicle_category vc ON vmd.vehicle_category_id = vc.vehicle_category_id
    //             INNER JOIN
    //                 tbl_status_availability sa ON v.status_availability_id = sa.status_availability_id
    //             WHERE 
    //                 v.status_availability_id != 7 AND v.status_availability_id != 8";
    //              // Added condition for availability

    //     return $this->executeQuery($sql);
    // }

//     public function fetchEquipments($startDateTime = null, $endDateTime = null) {
//     header('Content-Type: application/json');

//     // 1. Validate date inputs (if provided)
//     if ($startDateTime !== null && $endDateTime !== null) {
//         if (!strtotime($startDateTime) || !strtotime($endDateTime)) {
//             http_response_code(400);
//             die(json_encode([
//                 'status'    => 'error',
//                 'message'   => 'Invalid date format',
//                 'timestamp' => date('Y-m-d H:i:s')
//             ]));
//         }
//     }

//     try {
//         // 2. Build reserved-quantity map if date range provided
//         $reservedQuantities = [];
//         if ($startDateTime !== null && $endDateTime !== null) {
//             $reservedStmt = $this->conn->prepare("
//                 SELECT
//                     re.reservation_equipment_equip_id AS equip_id,
//                     SUM(re.reservation_equipment_quantity) AS reserved_quantity
//                 FROM tbl_reservation_equipment re
//                 INNER JOIN tbl_reservation r
//                     ON r.reservation_id = re.reservation_reservation_id
//                 INNER JOIN tbl_reservation_status rs
//                     ON rs.reservation_reservation_id = r.reservation_id
//                 WHERE
//                     rs.reservation_status_status_id = 6 -- Assuming 6 is 'Reserved'
//                     AND rs.reservation_active = 1
//                     AND (
//                         (r.reservation_start_date <= :end AND r.reservation_end_date >= :start)
//                     )
//                 GROUP BY re.reservation_equipment_equip_id
//             ");
//             $reservedStmt->execute([
//                 ':start' => $startDateTime,
//                 ':end'   => $endDateTime
//             ]);
//             while ($row = $reservedStmt->fetch(PDO::FETCH_ASSOC)) {
//                 $reservedQuantities[(int)$row['equip_id']] = (int)$row['reserved_quantity'];
//             }
//         }

//         // 3. Main Query: Fetch all active equipment with their total on-hand quantity
//         // This query combines logic for both consumable and unit-based equipment
//         $mainQuery = "
//             SELECT
//                 e.equip_id,
//                 e.equip_name,
//                 e.equip_type, -- Crucial for distinguishing consumable vs. unit
//                 e.equip_created_at,
//                 tec.equipments_category_name AS category_name, -- Alias for consistency
//                 e.equipments_category_id, -- Correct column name
//                 -- Calculate total on-hand quantity based on equip_type
//                 CASE
//                     WHEN e.equip_type = 'Consumable' THEN COALESCE(eq.quantity, 0)
//                     ELSE COALESCE(eu.unit_count, 0)
//                 END AS total_on_hand
//             FROM tbl_equipments e
//             LEFT JOIN tbl_equipment_category tec
//                 ON e.equipments_category_id = tec.equipments_category_id
//             -- Join for Consumable quantities (get the latest quantity)
//             LEFT JOIN (
//                 SELECT
//                     equip_id,
//                     quantity
//                 FROM tbl_equipment_quantity
//                 WHERE (equip_id, last_updated) IN (
//                     SELECT equip_id, MAX(last_updated)
//                     FROM tbl_equipment_quantity
//                     GROUP BY equip_id
//                 )
//             ) AS eq ON e.equip_id = eq.equip_id AND e.equip_type = 'Consumable'
//             -- Join for Unit-based equipment (count active units)
//             LEFT JOIN (
//                 SELECT
//                     equip_id,
//                     COUNT(*) AS unit_count
//                 FROM tbl_equipment_unit
//                 WHERE is_active = 1 AND status_availability_id = 1 -- Only count active and available units
//                 GROUP BY equip_id
//             ) AS eu ON e.equip_id = eu.equip_id AND e.equip_type != 'Consumable'
//             WHERE e.is_active = 1 -- Only fetch active equipment
//             ORDER BY e.equip_name;
//         ";

//         $mainStmt = $this->conn->prepare($mainQuery);
//         $mainStmt->execute();
//         $equipments = $mainStmt->fetchAll(PDO::FETCH_ASSOC);

//         // 4. Process availability and filter
//         $finalResults = [];
//         foreach ($equipments as $equip) {
//             $id = (int)$equip['equip_id'];
//             $totalOnHand = (int)$equip['total_on_hand'];
//             $reserved = $reservedQuantities[$id] ?? 0;
//             $available = max(0, $totalOnHand - $reserved);

//             // Only include equipment that has availability or has reserved items
//             if ($available > 0 || $reserved > 0) {
//                 $equip['current_quantity'] = $totalOnHand; // Renamed for clarity in output
//                 $equip['reserved_quantity'] = $reserved;
//                 $equip['available_quantity'] = $available;
//                 $equip['is_available'] = ($available > 0); // Boolean flag for availability
//                 $finalResults[] = $equip;
//             }
//         }

//         // 5. Return JSON
//         http_response_code(200);
//         echo json_encode([
//             'status'    => 'success',
//             'data'      => $finalResults,
//             'timestamp' => date('Y-m-d H:i:s')
//         ], JSON_UNESCAPED_SLASHES);

//     } catch (PDOException $e) {
//         http_response_code(500);
//         echo json_encode([
//             'status'    => 'error',
//             'message'   => 'Database error: ' . $e->getMessage(),
//             'timestamp' => date('Y-m-d H:i:s')
//         ]);
//     } catch (Exception $e) {
//         http_response_code(500);
//         echo json_encode([
//             'status'    => 'error',
//             'message'   => 'General error: ' . $e->getMessage(),
//             'timestamp' => date('Y-m-d H:i:s')
//         ]);
//     }
// }
   
    // public function fetchVenue() {
    //     $sql = "SELECT 
    //                 ven_id, 
    //                 ven_name, 
    //                 ven_occupancy, 
    //                 ven_created_at, 
    //                 ven_updated_at, 
    //                 v.status_availability_id,
    //                 sa.status_availability_name,
    //                 ven_pic
    //             FROM 
    //                 tbl_venue v
    //             INNER JOIN 
    //                 tbl_status_availability sa ON v.status_availability_id = sa.status_availability_id 
    //             WHERE 
    //                 v.status_availability_id != 7 AND v.status_availability_id != 8 
    //             ORDER BY 
    //                 ven_name"; 
    //     return $this->executeQuery($sql);
    // }



    public function __destruct() {
        unset($this->conn);
    }

    // public function fetchAllResources($type = null) {
    //     // SQL query for vehicles - only fetch vehicle_license and vehicle_model_name (vehicle model title)
    //     $vehicleQuery = "
    //         SELECT v.vehicle_license AS vehicle_registration, v.vehicle_id,
    //                vm.vehicle_model_name AS vehicle_model_title
    //         FROM tbl_vehicle v
    //         INNER JOIN tbl_vehicle_model vm ON v.vehicle_model_id = vm.vehicle_model_id
    //         LEFT JOIN tbl_checklist_vehicle_master cvm ON v.vehicle_id = cvm.checklist_vehicle_vehicle_id
    //         WHERE cvm.checklist_vehicle_id IS NULL
    //     ";
    
    //     // SQL query for venues - only fetch venue name
    //     $venueQuery = "
    //         SELECT ve.ven_name, ve.ven_id
    //         FROM tbl_venue ve
    //         LEFT JOIN tbl_checklist_venue_master cvm ON ve.ven_id = cvm.checklist_venue_ven_id
    //         WHERE cvm.checklist_venue_id IS NULL
    //     ";
    
    //     // SQL query for equipment - only fetch equipment name
    //     $equipmentQuery = "
    //         SELECT eq.equip_name, eq.equip_id
    //         FROM tbl_equipments eq
    //         LEFT JOIN tbl_checklist_equipment_master cem ON eq.equip_id = cem.checklist_equipment_equip_id
    //         WHERE cem.checklist_equipment_id IS NULL
    //     ";
    
    //     try {
    //         $result = [];
            
    //         switch ($type) {
    //             case 'vehicle':
    //                 $stmt = $this->conn->prepare($vehicleQuery);
    //                 $stmt->execute();
    //                 $result['vehicles'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    //                 break;
    //             case 'venue':
    //                 $stmt = $this->conn->prepare($venueQuery);
    //                 $stmt->execute();
    //                 $result['venues'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    //                 break;
    //             case 'equipment':
    //                 $stmt = $this->conn->prepare($equipmentQuery);
    //                 $stmt->execute();
    //                 $result['equipment'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    //                 break;
    //             default:
    //                 // Fetch all resources if no type specified
    //                 $stmt = $this->conn->prepare($vehicleQuery);
    //                 $stmt->execute();
    //                 $result['vehicles'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    //                 $stmt = $this->conn->prepare($venueQuery);
    //                 $stmt->execute();
    //                 $result['venues'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    //                 $stmt = $this->conn->prepare($equipmentQuery);
    //                 $stmt->execute();
    //                 $result['equipment'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    //         }
    
    //         return json_encode([
    //             'status' => 'success',
    //             'data' => $result
    //         ]);
    //     } catch (PDOException $e) {
    //         return json_encode([
    //             'status' => 'error',
    //             'message' => 'Database error: ' . $e->getMessage()
    //         ]);
    //     }
    // }
   
    
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

    

    // public function getReservedById($reservation_id) {
    //     try {
    //         // 1. Fetch the reservation details
    //         $reservationSql = "
    //             SELECT 
    //                 reservation_id,
    //                 reservation_title,
    //                 reservation_description,
    //                 reservation_start_date,
    //                 reservation_end_date,
    //                 reservation_participants,
    //                 reservation_user_id,
    //                 reservation_created_at
    //             FROM 
    //                 tbl_reservation
    //             WHERE 
    //                 reservation_id = :reservation_id
    //         ";
    //         $stmt = $this->conn->prepare($reservationSql);
    //         $stmt->bindParam(':reservation_id', $reservation_id, PDO::PARAM_INT);
    //         $stmt->execute();
    //         $reservation = $stmt->fetch(PDO::FETCH_ASSOC);
    
    //         if (!$reservation) {
    //             return json_encode(['status' => 'error', 'message' => 'Reservation not found']);
    //         }
    
    //         $result = [
    //             'reservation' => $reservation,
    //             'venues'      => [],
    //             'vehicles'    => [],
    //             'equipments'  => []
    //         ];
    
    //         // 2. Fetch venues
    //         $venueSql = "
    //             SELECT 
    //                 rv.reservation_venue_id, 
    //                 rv.reservation_venue_venue_id AS venue_id
    //             FROM 
    //                 tbl_reservation_venue rv
    //             WHERE 
    //                 rv.reservation_reservation_id = :reservation_id
    //         ";
    //         $stmt = $this->conn->prepare($venueSql);
    //         $stmt->bindParam(':reservation_id', $reservation_id, PDO::PARAM_INT);
    //         $stmt->execute();
    //         $venues = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    //         foreach ($venues as $venueRow) {
    //             $venueDetail = [
    //                 'reservation_venue_id' => $venueRow['reservation_venue_id'],
    //                 'venue_id' => $venueRow['venue_id'],
    //                 'name' => null,
    //                 'checklists' => []
    //             ];
    
    //             $venNameSql = "SELECT ven_name FROM tbl_venue WHERE ven_id = :venue_id";
    //             $stmtName = $this->conn->prepare($venNameSql);
    //             $stmtName->bindParam(':venue_id', $venueRow['venue_id'], PDO::PARAM_INT);
    //             $stmtName->execute();
    //             $venueName = $stmtName->fetch(PDO::FETCH_ASSOC);
    //             $venueDetail['name'] = $venueName ? $venueName['ven_name'] : null;
    
    //             $checklistVenueSql = "
    //                 SELECT checklist_venue_id, checklist_name 
    //                 FROM tbl_checklist_venue_master 
    //                 WHERE checklist_venue_ven_id = :venue_id
    //             ";
    //             $stmtChecklist = $this->conn->prepare($checklistVenueSql);
    //             $stmtChecklist->bindParam(':venue_id', $venueRow['venue_id'], PDO::PARAM_INT);
    //             $stmtChecklist->execute();
    //             $venueDetail['checklists'] = $stmtChecklist->fetchAll(PDO::FETCH_ASSOC);
    
    //             $result['venues'][] = $venueDetail;
    //         }
    
    //         // 3. Fetch vehicles
    //         $vehicleSql = "
    //             SELECT 
    //                 rvh.reservation_vehicle_id, 
    //                 rvh.reservation_vehicle_vehicle_id AS vehicle_id
    //             FROM 
    //                 tbl_reservation_vehicle rvh
    //             WHERE 
    //                 rvh.reservation_reservation_id = :reservation_id
    //         ";
    //         $stmt = $this->conn->prepare($vehicleSql);
    //         $stmt->bindParam(':reservation_id', $reservation_id, PDO::PARAM_INT);
    //         $stmt->execute();
    //         $vehicles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    //         foreach ($vehicles as $vehicleRow) {
    //             $vehicleDetail = [
    //                 'reservation_vehicle_id' => $vehicleRow['reservation_vehicle_id'],
    //                 'vehicle_id' => $vehicleRow['vehicle_id'],
    //                 'model' => null,
    //                 'license' => null,
    //                 'checklists' => []
    //             ];
    
    //             $vehicleDetailSql = "
    //                 SELECT 
    //                     vm.vehicle_model_name, 
    //                     v.vehicle_license 
    //                 FROM 
    //                     tbl_vehicle v
    //                 LEFT JOIN 
    //                     tbl_vehicle_model vm ON v.vehicle_model_id = vm.vehicle_model_id
    //                 WHERE 
    //                     v.vehicle_id = :vehicle_id
    //             ";
    //             $stmtVehicle = $this->conn->prepare($vehicleDetailSql);
    //             $stmtVehicle->bindParam(':vehicle_id', $vehicleRow['vehicle_id'], PDO::PARAM_INT);
    //             $stmtVehicle->execute();
    //             $vehicleInfo = $stmtVehicle->fetch(PDO::FETCH_ASSOC);
    
    //             if ($vehicleInfo) {
    //                 $vehicleDetail['model'] = $vehicleInfo['vehicle_model_name'];
    //                 $vehicleDetail['license'] = $vehicleInfo['vehicle_license'];
    //             }
    
    //             $checklistVehicleSql = "
    //                 SELECT checklist_vehicle_id, checklist_name 
    //                 FROM tbl_checklist_vehicle_master 
    //                 WHERE checklist_vehicle_vehicle_id = :vehicle_id
    //             ";
    //             $stmtChecklist = $this->conn->prepare($checklistVehicleSql);
    //             $stmtChecklist->bindParam(':vehicle_id', $vehicleRow['vehicle_id'], PDO::PARAM_INT);
    //             $stmtChecklist->execute();
    //             $vehicleDetail['checklists'] = $stmtChecklist->fetchAll(PDO::FETCH_ASSOC);
    
    //             $result['vehicles'][] = $vehicleDetail;
    //         }
    
    //         // 4. Fetch equipments
    //         $equipmentSql = "
    //             SELECT 
    //                 re.reservation_equipment_id, 
    //                 re.reservation_equipment_quantity,
    //                 re.reservation_equipment_equip_id AS equipment_id
    //             FROM 
    //                 tbl_reservation_equipment re
    //             WHERE 
    //                 re.reservation_reservation_id = :reservation_id
    //         ";
    //         $stmt = $this->conn->prepare($equipmentSql);
    //         $stmt->bindParam(':reservation_id', $reservation_id, PDO::PARAM_INT);
    //         $stmt->execute();
    //         $equipments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    //         foreach ($equipments as $equipRow) {
    //             $equipmentDetail = [
    //                 'reservation_equipment_id' => $equipRow['reservation_equipment_id'],
    //                 'equipment_id' => $equipRow['equipment_id'],
    //                 'quantity' => $equipRow['reservation_equipment_quantity'],
    //                 'name' => null,
    //                 'checklists' => []
    //             ];
    
    //             $equipNameSql = "SELECT equip_name FROM tbl_equipments WHERE equip_id = :equipment_id";
    //             $stmtEquip = $this->conn->prepare($equipNameSql);
    //             $stmtEquip->bindParam(':equipment_id', $equipRow['equipment_id'], PDO::PARAM_INT);
    //             $stmtEquip->execute();
    //             $equipInfo = $stmtEquip->fetch(PDO::FETCH_ASSOC);
    //             $equipmentDetail['name'] = $equipInfo ? $equipInfo['equip_name'] : null;
    
    //             $checklistEquipSql = "
    //                 SELECT checklist_equipment_id, checklist_name 
    //                 FROM tbl_checklist_equipment_master 
    //                 WHERE checklist_equipment_equip_id = :equipment_id
    //             ";
    //             $stmtChecklist = $this->conn->prepare($checklistEquipSql);
    //             $stmtChecklist->bindParam(':equipment_id', $equipRow['equipment_id'], PDO::PARAM_INT);
    //             $stmtChecklist->execute();
    //             $equipmentDetail['checklists'] = $stmtChecklist->fetchAll(PDO::FETCH_ASSOC);
    
    //             $result['equipments'][] = $equipmentDetail;
    //         }
    
    //         return json_encode(['status' => 'success', 'data' => $result]);
    
    //     } catch (PDOException $e) {
    //         return json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
    //     }
    // }
    
    
    
    
    
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
                    break;

                default:
                    continue 2;
            }

            $stmt = $this->conn->prepare($insertSql);
            $stmt->execute($params);
            $results[] = $this->conn->lastInsertId();
        }

        // Insert notification for the personnel
        $notificationSql = "INSERT INTO notification_user 
                            (notification_message, notification_user_id, is_read, created_at)
                            VALUES (:message, :user_id, 0, NOW())";

        $notifMessage = "You have been assigned new checklist tasks.";
        $stmtNotif = $this->conn->prepare($notificationSql);
        $stmtNotif->bindParam(':message', $notifMessage, PDO::PARAM_STR);
        $stmtNotif->bindParam(':user_id', $data['personnel_id'], PDO::PARAM_INT);
        $stmtNotif->execute();

        $this->conn->commit();

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


    public function saveMasterChecklist($checklistNames, $type, $id) {
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
                        $updateSql = "UPDATE tbl_checklist_venue_master 
                                    SET checklist_name = :name 
                                    WHERE checklist_venue_id = :id";
                        break;

                    case 'vehicle':
                        $updateSql = "UPDATE tbl_checklist_vehicle_master 
                                    SET checklist_name = :name 
                                    WHERE checklist_vehicle_id = :id";
                        break;

                    case 'equipment':
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

    public function deleteChecklist($type, $id) {
        try {
            if (empty($type) || empty($id)) {
                return json_encode([
                    'status' => 'error',
                    'message' => 'Type and ID are required.'
                ]);
            }

            $this->conn->beginTransaction();

            $sql = '';
            switch ($type) {
                case 'venue':
                    $sql = "DELETE FROM tbl_checklist_venue_master WHERE checklist_venue_id = :id";
                    break;

                case 'vehicle':
                    $sql = "DELETE FROM tbl_checklist_vehicle_master WHERE checklist_vehicle_id = :id";
                    break;

                case 'equipment':
                    $sql = "DELETE FROM tbl_checklist_equipment_master WHERE checklist_equipment_id = :id";
                    break;

                default:
                    return json_encode([
                        'status' => 'error',
                        'message' => 'Invalid type specified.'
                    ]);
            }

            $stmt = $this->conn->prepare($sql);
            $stmt->execute([':id' => $id]);

            $this->conn->commit();

            return json_encode([
                'status' => 'success',
                'message' => 'Checklist deleted successfully.'
            ]);

        } catch (PDOException $e) {
            $this->conn->rollBack();
            return json_encode([
                'status' => 'error', 
                'message' => 'Database error: ' . $e->getMessage()
            ]);
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
    $user = new User();
    
    switch ($operation) {
        case "fetchUsers":
            echo $user->fetchUsers();
            break;
        case "fetchVehicles":
            echo $user->fetchVehicles();
            break;
        case "fetchEquipments":
            $startDateTime = $jsonInput['startDateTime'] ?? null;
            $endDateTime = $jsonInput['endDateTime'] ?? null;
            $user->fetchEquipments($startDateTime, $endDateTime);
            break;
        case "fetchVenue": 
            echo $user->fetchVenue();
            break;
        case "fetchChatHistory":
            $userId = $jsonInput['userId'] ?? '';
            if ($userId) {
                echo $user->fetchChatHistory($userId);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'User ID is required']);
            }
            break;
        case "fetchNewMessages":
            $userId = $jsonInput['userId'] ?? '';
            $lastMessageId = $jsonInput['lastMessageId'] ?? 0;
            if ($userId) {
                echo $user->fetchNewMessages($userId, $lastMessageId);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'User ID is required']);
            }
            break;
        case "fetchPersonnel":
            echo $user->fetchPersonnel();
            break;
        case "fetchAllResources":
            $type = $jsonInput['type'] ?? null;
            echo $user->fetchAllResources($type);
            break;
        case "saveChecklist":
            $data = $jsonInput['data'] ?? null;
            if (!$data) {
                echo json_encode(['status' => 'error', 'message' => 'No data provided']);
                break;
            }
            echo $user->saveChecklist($data);
            break;
        case "fetchChecklist":
            echo $user->fetchChecklist();
            break;
        case "fetchChecklistById":
            $type = $jsonInput['type'] ?? '';
            $id = $jsonInput['id'] ?? 0;
            echo $user->fetchChecklistById($type, $id);  // Fix parameter order here
            break;
        case "getReservedById":
            $reservation_id = $jsonInput['reservation_id'] ?? 0;
            echo $user->getReservedById($reservation_id);
            break;
        case "saveMasterChecklist":
            $checklistNames = $jsonInput['checklistNames'] ?? [];
            $type = $jsonInput['type'] ?? '';
            $id = $jsonInput['id'] ?? 0;
            echo $user->saveMasterChecklist($checklistNames, $type, $id);
            break;
        case "updateChecklist":
            $data = $jsonInput['data'] ?? null;
            if (!$data) {
                echo json_encode(['status' => 'error', 'message' => 'No data provided']);
                break;
            }
            echo $user->updateChecklist($data);
            break;
        case "deleteChecklist":
            $type = $jsonInput['type'] ?? '';
            $id = $jsonInput['id'] ?? 0;
            echo $user->deleteChecklist($type, $id);
            break;
        default:
            echo json_encode(['status' => 'error', 'message' => 'Invalid operation']);
            break;
    }
}
?>
