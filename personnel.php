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
            $reservations = [];

            // 1) Venue checklist items with availability status name
            $sqlVenue = "
                SELECT
                    rc_venue.checklist_venue_id,
                    rc_venue.reservation_checklist_venue_id,
                    rc_venue.isChecked AS venue_isChecked,
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
                -- JOIN to get venue availability status name
                LEFT JOIN tbl_status_availability tsa
                    ON v.status_availability_id = tsa.status_availability_id
                WHERE rc_venue.personnel_id = :personnel_id
                  AND rs.reservation_status_status_id = 6
                  AND (rs.reservation_active = 1 OR rs.reservation_active = 0)
                GROUP BY rc_venue.reservation_checklist_venue_id
            ";
            $stmtVenue = $this->conn->prepare($sqlVenue);
            $stmtVenue->execute(['personnel_id' => $personnel_id]);
            $venueData = $stmtVenue->fetchAll(PDO::FETCH_ASSOC);

            // 2) Vehicle checklist items with availability status name
            $sqlVehicle = "
                SELECT
                    rc_vehicle.checklist_vehicle_id,
                    rc_vehicle.reservation_checklist_vehicle_id,
                    rc_vehicle.isChecked AS vehicle_isChecked,
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
                -- JOIN to get vehicle availability status name
                LEFT JOIN tbl_status_availability tsa
                    ON vm.status_availability_id = tsa.status_availability_id
                WHERE rc_vehicle.personnel_id = :personnel_id
                  AND rs.reservation_status_status_id = 6
                  AND (rs.reservation_active = 1 OR rs.reservation_active = 0)
                GROUP BY rc_vehicle.reservation_checklist_vehicle_id
            ";
            $stmtVehicle = $this->conn->prepare($sqlVehicle);
            $stmtVehicle->execute(['personnel_id' => $personnel_id]);
            $vehicleData = $stmtVehicle->fetchAll(PDO::FETCH_ASSOC);

            // 3) Equipment checklist items with availability status name
            $sqlEquipment = "                SELECT
                    rc_equipment.checklist_equipment_id,
                    rc_equipment.reservation_checklist_equipment_id,
                    rc_equipment.isChecked AS equipment_isChecked,
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
                -- JOIN to get equipment quantity and availability status
                LEFT JOIN tbl_equipment_quantity eq
                    ON re.reservation_equipment_equip_id = eq.equip_id
                LEFT JOIN tbl_status_availability tsa
                    ON eq.status_availability_id = tsa.status_availability_id
                WHERE rc_equipment.personnel_id = :personnel_id
                  AND rs.reservation_status_status_id = 6
                  AND (rs.reservation_active = 1 OR rs.reservation_active = 0)
                GROUP BY rc_equipment.reservation_checklist_equipment_id
            ";
            $stmtEquipment = $this->conn->prepare($sqlEquipment);
            $stmtEquipment->execute(['personnel_id' => $personnel_id]);
            $equipmentData = $stmtEquipment->fetchAll(PDO::FETCH_ASSOC);

            // Merge all checklist rows into $reservations
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
                            $checklistExists = false;
                            foreach ($v['checklists'] as $checklist) {
                                if ($checklist['reservation_checklist_venue_id'] == $row['reservation_checklist_venue_id']) {
                                    $checklistExists = true;
                                    break;
                                }
                            }
                            if (!$checklistExists) {
                                $v['checklists'][] = [
                                    'checklist_venue_id'             => $row['checklist_venue_id'],
                                    'reservation_checklist_venue_id' => $row['reservation_checklist_venue_id'],
                                    'checklist_name'                 => $row['checklist_venue_name'],
                                    'isChecked'                      => (int)$row['venue_isChecked']
                                ];
                            }
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
                                'isChecked'                      => (int)$row['venue_isChecked']
                            ]]
                        ];
                    }
                }
                // VEHICLE
                if (isset($row['reservation_vehicle_id'])) {
                    $found = false;
                    foreach ($reservations[$rid]['vehicles'] as &$v) {
                        if ($v['reservation_vehicle_id'] == $row['reservation_vehicle_id']) {
                            $checklistExists = false;
                            foreach ($v['checklists'] as $checklist) {
                                if ($checklist['reservation_checklist_vehicle_id'] == $row['reservation_checklist_vehicle_id']) {
                                    $checklistExists = true;
                                    break;
                                }
                            }
                            if (!$checklistExists) {
                                $v['checklists'][] = [
                                    'checklist_vehicle_id'             => $row['checklist_vehicle_id'],
                                    'reservation_checklist_vehicle_id' => $row['reservation_checklist_vehicle_id'],
                                    'checklist_name'                   => $row['checklist_vehicle_name'],
                                    'isChecked'                        => (int)$row['vehicle_isChecked']
                                ];
                            }
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
                                'isChecked'                        => (int)$row['vehicle_isChecked']
                            ]]
                        ];
                    }
                }
                // EQUIPMENT
                if (isset($row['reservation_equipment_id'])) {
                    $found = false;
                    foreach ($reservations[$rid]['equipments'] as &$e) {
                        if ($e['reservation_equipment_id'] == $row['reservation_equipment_id']) {
                            $checklistExists = false;
                            foreach ($e['checklists'] as $checklist) {
                                if ($checklist['reservation_checklist_equipment_id'] == $row['reservation_checklist_equipment_id']) {
                                    $checklistExists = true;
                                    break;
                                }
                            }
                            if (!$checklistExists) {
                                $e['checklists'][] = [
                                    'checklist_equipment_id'             => $row['checklist_equipment_id'],
                                    'reservation_checklist_equipment_id' => $row['reservation_checklist_equipment_id'],
                                    'checklist_name'                     => $row['checklist_equipment_name'],
                                    'isChecked'                          => (int)$row['equipment_isChecked']
                                ];
                            }
                            $found = true;
                            break;
                        }
                    }
                    if (!$found) {                        $reservations[$rid]['equipments'][] = [
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
                                'isChecked'                          => (int)$row['equipment_isChecked']
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
            ul.user_level_name AS role,
            sm.status_master_name AS reservation_status
        FROM tbl_reservation r
        INNER JOIN tbl_users u
            ON r.reservation_user_id = u.users_id
        LEFT JOIN tbl_departments d
            ON u.users_department_id = d.departments_id
        LEFT JOIN tbl_user_level ul
            ON u.users_user_level_id = ul.user_level_id
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
        ) rs_latest ON rs_latest.reservation_reservation_id = r.reservation_id
        LEFT JOIN tbl_status_master sm
            ON rs_latest.reservation_status_status_id = sm.status_master_id
        WHERE r.reservation_id = :rid
    ";

    $st = $this->conn->prepare($sqlR);
    $st->execute(['rid' => $rid]);
    $hdr = $st->fetch(PDO::FETCH_ASSOC);

    if ($hdr) {
        $res['reservation_title']        = $hdr['reservation_title'];
        $res['reservation_description']  = $hdr['reservation_description'];
        $res['reservation_start_date']   = $hdr['reservation_start_date'];
        $res['reservation_end_date']     = $hdr['reservation_end_date'];
        $res['reservation_participants'] = $hdr['reservation_participants'];
        $res['reservation_user_id']      = $hdr['reservation_user_id'];
        $res['reservation_status']       = $hdr['reservation_status'] ?? 'N/A';

        $fullName = trim(
            $hdr['users_fname'] . ' ' .
            (!empty($hdr['users_mname']) ? $hdr['users_mname'] . ' ' : '') .
            $hdr['users_lname']
        );

        $res['user_details'] = [
            'full_name'  => $fullName,
            'department' => $hdr['departments_name'] ?? 'N/A',
            'role'       => $hdr['role'] ?? 'N/A'
        ];
    }
}


            unset($res); // Unset the last reference

            // Filter reservations that have associated items
            $filteredReservations = array_filter($reservations, function($res) {
                return !empty($res['venues']) || !empty($res['vehicles']) || !empty($res['equipments']);
            });

            return json_encode([
                'status' => 'success',
                'data'   => array_values($filteredReservations) // Reset array keys
            ]);

        } catch (PDOException $e) {
            error_log("Error in fetchAssignedRelease: " . $e->getMessage());
            return json_encode([
                'status'  => 'error',
                'message' => 'An error occurred while fetching assigned releases. Please try again later.'
            ]);
        }
    }
    

    public function fetchTaskById($data) {
        if (!isset($data['checklist_id'])) {
            return json_encode(['status' => 'error', 'message' => 'Checklist ID is required']);
        }

        try {
            // Get the checklist master data with reservation details
            $sqlMaster = "SELECT 
                cm.checklist_id,
                cm.checklist_reservation_id,
                cm.checklist_admin_id,
                cm.checklist_personnel_id,
                r.reservation_title,
                r.reservation_description,
                r.reservation_start_date,
                r.reservation_end_date
            FROM tbl_checklist_master cm
            LEFT JOIN tbl_reservation r ON cm.checklist_reservation_id = r.reservation_id
            WHERE cm.checklist_id = :checklist_id";

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

    public function updateTask($data) {
    // Validate required parameters
    if (!isset($data['type']) || !isset($data['id']) || !isset($data['isActive'])) {
        return json_encode([
            'status' => 'error',
            'message' => 'Type, ID, and isActive are required'
        ]);
    }

    try {
        $type = strtolower($data['type']);
        $id = (int)$data['id'];
        $isActive = (int)$data['isActive'];
        
        // Determine which table to update based on type
        switch ($type) {
            case 'venue':
                $table = 'tbl_reservation_checklist_venue';
                $idField = 'reservation_checklist_venue_id';
                break;
            case 'vehicle':
                $table = 'tbl_reservation_checklist_vehicle';
                $idField = 'reservation_checklist_vehicle_id';
                break;
            case 'equipment':
                $table = 'tbl_reservation_checklist_equipment';
                $idField = 'reservation_checklist_equipment_id';
                break;
            default:
                return json_encode([
                    'status' => 'error',
                    'message' => 'Invalid type specified. Must be venue, vehicle, or equipment'
                ]);
        }

        // Update the isChecked field
        $sql = "UPDATE $table 
                SET isChecked = :isActive
                WHERE $idField = :id";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            'isActive' => $isActive,
            'id' => $id
        ]);

        return json_encode([
            'status' => 'success',
            'message' => 'Checklist item updated successfully',
            'affected_rows' => $stmt->rowCount()
        ]);

    } catch (PDOException $e) {
        return json_encode([
            'status' => 'error',
            'message' => 'Database error: ' . $e->getMessage()
        ]);
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


    public function submitCondition($data) {
    if (!isset($data['conditions'])) {
        return json_encode([
            'status' => 'error',
            'message' => 'Missing required parameter (conditions)',
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    }

    try {
        $timestamp = date('Y-m-d H:i:s');
        $conditions = $data['conditions'];
        $results = [];
        
        // Begin transaction for all operations
        $this->conn->beginTransaction();
        
        // Process each type of condition
        foreach ($conditions as $type => $typeData) {
            if (!isset($typeData['reservation_ids']) || !isset($typeData['condition_ids']) || 
                !is_array($typeData['reservation_ids']) || !is_array($typeData['condition_ids']) ||
                count($typeData['reservation_ids']) != count($typeData['condition_ids'])) {
                $results[$type] = [
                    'status' => 'error',
                    'message' => 'Invalid or mismatched reservation_ids and condition_ids arrays',
                    'timestamp' => $timestamp
                ];
                continue;
            }

            // Initialize SQL and table names based on type
            $tables = [
                'venue' => [
                    'reservationTable' => 'tbl_reservation_venue',
                    'conditionTable' => 'tbl_reservation_condition_venue',
                    'reservationIdField' => 'reservation_venue_id',
                    'checkField' => 'reservation_venue_id'
                ],
                'vehicle' => [
                    'reservationTable' => 'tbl_reservation_vehicle',
                    'conditionTable' => 'tbl_reservation_condition_vehicle',
                    'reservationIdField' => 'reservation_vehicle_id',
                    'checkField' => 'reservation_vehicle_id'
                ],
                'equipment' => [
                    'reservationTable' => 'tbl_reservation_equipment',
                    'conditionTable' => 'tbl_reservation_condition_equipment',
                    'reservationIdField' => 'reservation_equipment_id',
                    'checkField' => 'reservation_equipment_id'
                ]
            ];

            if (!isset($tables[$type])) {
                $results[$type] = [
                    'status' => 'error',
                    'message' => 'Invalid type specified',
                    'timestamp' => $timestamp
                ];
                continue;
            }

            $tableInfo = $tables[$type];
            $reservation_ids = $typeData['reservation_ids'];
            $condition_ids = $typeData['condition_ids'];
            $other_reasons = $typeData['other_reasons'] ?? array_fill(0, count($reservation_ids), null);
            // New: Get qty_bad for equipment type
            $qty_bad = ($type === 'equipment') ? ($typeData['qty_bad'] ?? array_fill(0, count($reservation_ids), null)) : null;

            // Validate all reservation_ids exist
            $placeholders = str_repeat('?,', count($reservation_ids) - 1) . '?';
            $checkReservation = "SELECT COUNT(*) FROM {$tableInfo['reservationTable']} WHERE {$tableInfo['reservationIdField']} IN ($placeholders)";
            $stmtReservation = $this->conn->prepare($checkReservation);
            $stmtReservation->execute($reservation_ids);
            if ($stmtReservation->fetchColumn() != count($reservation_ids)) {
                $results[$type] = [
                    'status' => 'error',
                    'message' => 'One or more invalid reservation IDs',
                    'timestamp' => $timestamp
                ];
                continue;
            }

            $typeResults = [];
            $insertedCount = 0;
            $skippedCount = 0;

            // Process each reservation-condition pair
            for ($i = 0; $i < count($reservation_ids); $i++) {
                $reservation_id = $reservation_ids[$i];
                $condition_id = $condition_ids[$i];
                $other_reason = isset($other_reasons[$i]) ? $other_reasons[$i] : null;
                // New: Get qty_bad value for this iteration if it's equipment type
                $current_qty_bad = ($type === 'equipment' && isset($qty_bad[$i])) ? $qty_bad[$i] : null;

                // Validate other_reason is provided when condition_id is 6
                if ($condition_id == '6' && empty($other_reason)) {
                    $typeResults[$reservation_id] = [
                        'status' => 'error',
                        'condition_id' => $condition_id,
                        'message' => 'Other reason is required when condition is Others'
                    ];
                    continue;
                }

                // Check for existing condition record
                $checkExisting = "SELECT COUNT(*) FROM {$tableInfo['conditionTable']} 
                                WHERE {$tableInfo['checkField']} = :reservation_id 
                                AND condition_id = :condition_id";
                $stmtExisting = $this->conn->prepare($checkExisting);
                $stmtExisting->execute([
                    'reservation_id' => $reservation_id,
                    'condition_id' => $condition_id
                ]);
                
                if ($type === 'equipment') {
                    // Insert the record with qty_bad for equipment
                    $sql = "INSERT INTO {$tableInfo['conditionTable']} 
                            ({$tableInfo['checkField']}, condition_id, other_reason, qty_bad, is_active) 
                            VALUES (:reservation_id, :condition_id, :other_reason, :qty_bad, 1)";
                    
                    $stmt = $this->conn->prepare($sql);
                    $stmt->execute([
                        'reservation_id' => $reservation_id,
                        'condition_id' => $condition_id,
                        'other_reason' => $other_reason,
                        'qty_bad' => $current_qty_bad
                    ]);
                } else {
                    // Insert the record without qty_bad for venue and vehicle
                    $sql = "INSERT INTO {$tableInfo['conditionTable']} 
                            ({$tableInfo['checkField']}, condition_id, other_reason, is_active) 
                            VALUES (:reservation_id, :condition_id, :other_reason, 1)";
                    
                    $stmt = $this->conn->prepare($sql);
                    $stmt->execute([
                        'reservation_id' => $reservation_id,
                        'condition_id' => $condition_id,
                        'other_reason' => $other_reason
                    ]);
                }
                
                $insertedCount++;
                
                $typeResults[$reservation_id] = [
                    'status' => 'success',
                    'condition_id' => $condition_id,
                    'other_reason' => $other_reason,
                    'qty_bad' => $type === 'equipment' ? $current_qty_bad : null,
                    'message' => 'Condition inserted successfully'
                ];
            }

            $results[$type] = [
                'status' => 'success',
                'summary' => "Processed: $insertedCount inserted",
                'details' => $typeResults,
                'timestamp' => $timestamp
            ];
        }

        // Commit all changes
        $this->conn->commit();
        
        return json_encode([
            'status' => 'success',
            'results' => $results,
            'timestamp' => $timestamp
        ]);
        
    } catch (PDOException $e) {
        // Rollback transaction on error
        if ($this->conn->inTransaction()) {
            $this->conn->rollBack();
        }
        return json_encode([
            'status' => 'error',
            'message' => 'Database error: ' . $e->getMessage(),
            'timestamp' => date('Y-m-d H:i:s')
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

public function updateReservationStatus($reservation_id) {
    if (!$reservation_id) {
        return json_encode([
            'status' => 'error',
            'message' => 'Reservation ID is required',
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    }

    try {
        $timestamp = date('Y-m-d H:i:s');

        // 1. Deactivate current active status where status_id = 6
        $deactivateSql = "UPDATE tbl_reservation_status 
                          SET reservation_active = 0, 
                              reservation_updated_at = :timestamp 
                          WHERE reservation_reservation_id = :reservation_id 
                            AND reservation_status_status_id = 6";

        $stmtDeactivate = $this->conn->prepare($deactivateSql);
        $stmtDeactivate->execute([
            'reservation_id' => $reservation_id,
            'timestamp' => $timestamp
        ]);

        // 2. Insert new status row with status_id = 4
        $insertSql = "INSERT INTO tbl_reservation_status (
                          reservation_status_status_id, 
                          reservation_reservation_id, 
                          reservation_active, 
                          reservation_updated_at
                      ) VALUES (
                          4, :reservation_id, 1, :timestamp
                      )";

        $stmtInsert = $this->conn->prepare($insertSql);
        $stmtInsert->execute([
            'reservation_id' => $reservation_id,
            'timestamp' => $timestamp
        ]);

        return json_encode([
            'status' => 'success',
            'message' => 'Reservation status updated: deactivated status_id 6, added new status_id 4',
            'timestamp' => $timestamp
        ]);

    } catch (PDOException $e) {
        return json_encode([
            'status' => 'error',
            'message' => $e->getMessage(),
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    }
}

public function updateRelease($type, $reservation_id, $resource_id, $quantity = null) {
    try {
        $status_availability_id = 5;

        switch ($type) {
            case 'venue':
                $stmtFetchVenueId = $this->conn->prepare("SELECT reservation_venue_venue_id FROM tbl_reservation_venue WHERE reservation_venue_id = :reservation_id");
                $stmtFetchVenueId->execute(['reservation_id' => $reservation_id]);
                $venue = $stmtFetchVenueId->fetch(PDO::FETCH_ASSOC);

                if (!$venue) {
                    return json_encode([
                        'status' => 'error',
                        'message' => 'Reservation venue ID not found.'
                    ]);
                }

                $venue_id = $venue['reservation_venue_venue_id'];

                $sqlUpdateStatus = "UPDATE tbl_venue SET status_availability_id = :status_availability_id WHERE ven_id = :venue_id";
                $stmtStatus = $this->conn->prepare($sqlUpdateStatus);
                $stmtStatus->execute([
                    'status_availability_id' => $status_availability_id,
                    'venue_id' => $venue_id
                ]);

                $sqlUpdateActive = "UPDATE tbl_reservation_venue SET active = 1 WHERE reservation_venue_id = :reservation_id";
                $stmtActive = $this->conn->prepare($sqlUpdateActive);
                $stmtActive->execute(['reservation_id' => $reservation_id]);
                break;

            case 'vehicle':
                $sqlUpdateStatus = "UPDATE tbl_vehicle SET status_availability_id = :status_availability_id WHERE vehicle_id = :resource_id";
                $sqlUpdateActive = "UPDATE tbl_reservation_vehicle SET active = 1 WHERE reservation_vehicle_id = :reservation_id";
                $stmtStatus = $this->conn->prepare($sqlUpdateStatus);
                $stmtStatus->execute([
                    'status_availability_id' => $status_availability_id,
                    'resource_id' => $resource_id
                ]);
                $stmtActive = $this->conn->prepare($sqlUpdateActive);
                $stmtActive->execute(['reservation_id' => $reservation_id]);
                break;

            case 'equipment':
                $sqlUpdateStatus = "UPDATE tbl_equipment_unit SET status_availability_id = :status_availability_id WHERE unit_id = :resource_id";
                $sqlUpdateActive = "UPDATE tbl_reservation_unit SET active = 1 WHERE reservation_unit_id = :reservation_id";
                $stmtStatus = $this->conn->prepare($sqlUpdateStatus);
                $stmtStatus->execute([
                    'status_availability_id' => $status_availability_id,
                    'resource_id' => $resource_id
                ]);
                $stmtActive = $this->conn->prepare($sqlUpdateActive);
                $stmtActive->execute(['reservation_id' => $reservation_id]);
                break;

            case 'equipment_consumable':
                // Update active flag in tbl_reservation_equipment
                $sqlUpdateActive = "UPDATE tbl_reservation_equipment SET active = 1 WHERE reservation_equipment_id = :reservation_id";
                $stmtActive = $this->conn->prepare($sqlUpdateActive);
                $stmtActive->execute(['reservation_id' => $reservation_id]);

                // Fetch current on_hand_quantity from tbl_equipment_quantity
                $sqlFetchQuantity = "SELECT on_hand_quantity FROM tbl_equipment_quantity WHERE quantity_id = :quantity_id";
                $stmtFetch = $this->conn->prepare($sqlFetchQuantity);
                $stmtFetch->execute(['quantity_id' => $resource_id]);
                $quantityData = $stmtFetch->fetch(PDO::FETCH_ASSOC);

                if (!$quantityData) {
                    $currentOnHandQuantity = 0;
                } else {
                    $currentOnHandQuantity = (int)$quantityData['on_hand_quantity'];
                }

                // Validate quantity parameter
                if ($quantity === null || !is_numeric($quantity) || $quantity <= 0) {
                    return json_encode([
                        'status' => 'error',
                        'message' => 'Invalid quantity parameter for deduction.'
                    ]);
                }

                // Calculate new on_hand_quantity
                $deductedQuantity = $currentOnHandQuantity - (int)$quantity;
                $newOnHandQuantity = $deductedQuantity <= 0 ? 10 : $deductedQuantity;

                // Update on_hand_quantity (status_availability_id is NOT updated)
                $sqlUpdateQuantity = "UPDATE tbl_equipment_quantity 
                                    SET on_hand_quantity = :new_quantity 
                                    WHERE quantity_id = :quantity_id";
                $stmtUpdateQuantity = $this->conn->prepare($sqlUpdateQuantity);
                $stmtUpdateQuantity->execute([
                    'new_quantity' => $newOnHandQuantity,
                    'quantity_id' => $resource_id
                ]);

                // Check if update was successful
                if ($stmtUpdateQuantity->rowCount() === 0) {
                    return json_encode([
                        'status' => 'error',
                        'message' => 'Failed to update equipment quantity. No matching quantity_id found.'
                    ]);
                }
                break;


            default:
                return json_encode([
                    'status' => 'error',
                    'message' => 'Invalid type'
                ]);
        }

        return json_encode([
            'status' => 'success',
            'message' => 'Status and active flag updated successfully'
        ]);
    } catch (PDOException $e) {
        return json_encode([
            'status' => 'error',
            'message' => $e->getMessage()
        ]);
    }
}







public function updateReturn($type, $reservation_id, $resource_id, $condition, $user_personnel_id, $bad_quantity = 0, $good_quantity = 0, $remarks = null) {
    try {
        // If both condition and remarks are empty, return error
        if ((empty($condition) || strtolower($condition) === 'null') && (empty($remarks) || strtolower($remarks) === 'null')) {
            return json_encode([
                'status' => 'error',
                'message' => 'Either condition or remarks must be provided.'
            ]);
        }

        // If remarks is not provided or empty, set to null
        $remarksValue = (!isset($remarks) || $remarks === '' || strtolower($remarks) === 'null') ? null : $remarks;

        // If remarks is provided, set condition_id to 7
        $useRemarksCondition = ($remarksValue !== null);

        // Map condition to status and condition_id (if not using remarks)
        if ($useRemarksCondition) {
            $condition_id = 7;
            $status_availability_id = 1; // You may adjust this if needed for remarks
        } else {
            switch (strtolower($condition)) {
                case 'good':
                    $status_availability_id = 1;
                    $condition_id = 2;
                    break;
                case 'damage':
                    $status_availability_id = 8;
                    $condition_id = 4;
                    break;
                case 'missing':
                    $status_availability_id = 7;
                    $condition_id = 3;
                    break;
                default:
                    return json_encode([
                        'status' => 'error',
                        'message' => 'Invalid condition provided.'
                    ]);
            }
        }

        switch ($type) {
            case 'venue':
                $stmtFetchVenueId = $this->conn->prepare("SELECT reservation_venue_venue_id FROM tbl_reservation_venue WHERE reservation_venue_id = :reservation_id");
                $stmtFetchVenueId->execute(['reservation_id' => $reservation_id]);
                $venue = $stmtFetchVenueId->fetch(PDO::FETCH_ASSOC);
                if (!$venue) {
                    return json_encode([
                        'status' => 'error',
                        'message' => 'Reservation venue ID not found.'
                    ]);
                }
                $venue_id = $venue['reservation_venue_venue_id'];
                $stmtStatus = $this->conn->prepare("UPDATE tbl_venue SET status_availability_id = :status_availability_id WHERE ven_id = :venue_id");
                $stmtStatus->execute([
                    'status_availability_id' => $status_availability_id,
                    'venue_id' => $venue_id
                ]);
                $stmtActive = $this->conn->prepare("UPDATE tbl_reservation_venue SET active = -1 WHERE reservation_venue_id = :reservation_id");
                $stmtActive->execute(['reservation_id' => $reservation_id]);
                $stmtCond = $this->conn->prepare("INSERT INTO tbl_reservation_condition_venue (reservation_venue_id, condition_id, is_active, user_personnel_id, remarks) VALUES (:reservation_id, :condition_id, 1, :user_personnel_id, :remarks)");
                $stmtCond->execute([
                    'reservation_id' => $reservation_id,
                    'condition_id' => $condition_id,
                    'user_personnel_id' => $user_personnel_id,
                    'remarks' => $remarksValue
                ]);
                break;
            case 'vehicle':
                $stmtStatus = $this->conn->prepare("UPDATE tbl_vehicle SET status_availability_id = :status_availability_id WHERE vehicle_id = :resource_id");
                $stmtStatus->execute([
                    'status_availability_id' => $status_availability_id,
                    'resource_id' => $resource_id
                ]);
                $stmtActive = $this->conn->prepare("UPDATE tbl_reservation_vehicle SET active = -1 WHERE reservation_vehicle_id = :reservation_id");
                $stmtActive->execute(['reservation_id' => $reservation_id]);
                $stmtCond = $this->conn->prepare("INSERT INTO tbl_reservation_condition_vehicle (reservation_vehicle_id, condition_id, is_active, user_personnel_id, remarks) VALUES (:reservation_id, :condition_id, 1, :user_personnel_id, :remarks)");
                $stmtCond->execute([
                    'reservation_id' => $reservation_id,
                    'condition_id' => $condition_id,
                    'user_personnel_id' => $user_personnel_id,
                    'remarks' => $remarksValue
                ]);
                break;
            case 'equipment':
                $stmtStatus = $this->conn->prepare("UPDATE tbl_equipment_unit SET status_availability_id = :status_availability_id WHERE unit_id = :resource_id");
                $stmtStatus->execute([
                    'status_availability_id' => $status_availability_id,
                    'resource_id' => $resource_id
                ]);
                $stmtActive = $this->conn->prepare("UPDATE tbl_reservation_unit SET active = -1 WHERE reservation_unit_id = :reservation_id");
                $stmtActive->execute(['reservation_id' => $reservation_id]);
                $stmtCond = $this->conn->prepare("INSERT INTO tbl_reservation_condition_unit (reservation_unit_id, condition_id, is_active, user_personnel_id, remarks) VALUES (:reservation_id, :condition_id, 1, :user_personnel_id, :remarks)");
                $stmtCond->execute([
                    'reservation_id' => $reservation_id,
                    'condition_id' => $condition_id,
                    'user_personnel_id' => $user_personnel_id,
                    'remarks' => $remarksValue
                ]);
                // Also deactivate consumable equipment reservation if exists
                $stmtDeactivateConsumable = $this->conn->prepare("UPDATE tbl_reservation_equipment SET active = -1 WHERE reservation_equipment_id = :reservation_id");
                $stmtDeactivateConsumable->execute(['reservation_id' => $reservation_id]);
                break;
            case 'equipment_consumable':
                $stmtFetchEquip = $this->conn->prepare("SELECT reservation_equipment_equip_id FROM tbl_reservation_equipment WHERE reservation_equipment_id = :rid");
                $stmtFetchEquip->execute(['rid' => $reservation_id]);
                $equipRow = $stmtFetchEquip->fetch(PDO::FETCH_ASSOC);
                if (!$equipRow) {
                    return json_encode([
                        'status'  => 'error',
                        'message' => 'Reservation equipment not found.'
                    ]);
                }
                $equip_id = $equipRow['reservation_equipment_equip_id'];
                if ($good_quantity > 0) {
                    $stmtUpdateQty = $this->conn->prepare("UPDATE tbl_equipment_quantity SET on_hand_quantity = on_hand_quantity + :g, last_updated = NOW(), status_availability_id = :stat WHERE equip_id = :eid");
                    $stmtUpdateQty->execute([
                        'g'    => $good_quantity,
                        'stat' => $status_availability_id,
                        'eid'  => $equip_id
                    ]);
                }
                if ($bad_quantity > 0 || $remarksValue !== null) {
                    $stmtInsertBad = $this->conn->prepare("INSERT INTO tbl_reservation_condition_equipment (reservation_equipment_id, condition_id, qty_bad, user_personnel_id, is_active, remarks) VALUES (:rid, :cid, :b, :uid, 1, :remarks)");
                    $stmtInsertBad->execute([
                        'rid' => $reservation_id,
                        'cid' => $condition_id,
                        'b'   => $bad_quantity,
                        'uid' => $user_personnel_id,
                        'remarks' => $remarksValue
                    ]);
                }
                $stmtDeactivate = $this->conn->prepare("UPDATE tbl_reservation_equipment SET active = -1 WHERE reservation_equipment_id = :rid");
                $stmtDeactivate->execute(['rid' => $reservation_id]);
                break;
            default:
                return json_encode([
                    'status' => 'error',
                    'message' => 'Invalid type.'
                ]);
        }
        return json_encode([
            'status' => 'success',
            'message' => 'Status, condition, and remarks updated successfully.'
        ]);
    } catch (PDOException $e) {
        return json_encode([
            'status' => 'error',
            'message' => $e->getMessage()
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
            $type = $jsonInput['type'] ?? null;
            $id = $jsonInput['id'] ?? null;
            $isActive = $jsonInput['isActive'] ?? null;
            if (!$type || !$id || !isset($isActive)) {
                die(json_encode([
                    'status' => 'error',
                    'message' => 'Type, ID, and isActive are required',
                    'timestamp' => date('Y-m-d H:i:s')
                ]));
            }
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

        case 'submitCondition':
            echo $user->submitCondition($jsonInput);
            break;
        case 'updateResourceStatusAndCondition':
            $type = $jsonInput['type'] ?? null;
            $resourceId = $jsonInput['resource_id'] ?? null;
            $recordId = $jsonInput['record_id'] ?? null;
            if (!$type || !$resourceId || !$recordId) {
                die(json_encode([
                    'status' => 'error',
                    'message' => 'Type, Resource ID, and Record ID are required',
                    'timestamp' => date('Y-m-d H:i:s')
                ]));
            }
            echo $user->updateResourceStatusAndCondition($type, $resourceId, $recordId);
            break;
        case 'updateReservationStatus':
            $reservation_id = $jsonInput['reservation_id'] ?? null;
            if (!$reservation_id) {
                die(json_encode([
                    'status' => 'error',
                    'message' => 'Reservation ID is required',
                    'timestamp' => date('Y-m-d H:i:s')
                ]));
            }
            echo $user->updateReservationStatus($reservation_id);
            break;
        case "updateRelease":
            $type = $jsonInput['type'] ?? null;
            $reservation_id = $jsonInput['reservation_id'] ?? null;
            $resource_id = $jsonInput['resource_id'] ?? null;
            $quantity = $jsonInput['quantity'] ?? null;

            if (!$type || !$reservation_id || !$resource_id) {
                die(json_encode([
                    'status' => 'error',
                    'message' => 'Type, Reservation ID, and Resource ID are required',
                    'timestamp' => date('Y-m-d H:i:s')
                ]));
            }
            echo $user->updateRelease($type, $reservation_id, $resource_id, $quantity);
            break;
        case "updateReturn":
            $type = $jsonInput['type'] ?? null;
            $reservation_id = $jsonInput['reservation_id'] ?? null;
            $resource_id = $jsonInput['resource_id'] ?? null;
            $condition = $jsonInput['condition'] ?? null;
            $user_personnel_id = $jsonInput['user_personnel_id'] ?? null;
            $bad_quantity = $jsonInput['bad_quantity'] ?? 0; // Default to 0 if not provided
            $good_quantity = $jsonInput['good_quantity'] ?? 0; // Default to 0 if not provided
            $remarks = $jsonInput['remarks'] ?? null; // Get remarks from input

            if (!$type || !$reservation_id || !$resource_id || !$user_personnel_id || (empty($condition) && (empty($remarks) || strtolower($remarks) === 'null'))) {
                die(json_encode([
                    'status' => 'error',
                    'message' => 'Type, Reservation ID, Resource ID, Condition or Remarks, and User Personnel ID are required',
                    'timestamp' => date('Y-m-d H:i:s')
                ]));
            }
            echo $user->updateReturn($type, $reservation_id, $resource_id, $condition, $user_personnel_id, $bad_quantity, $good_quantity, $remarks);
            break;
            
            
    
    
    
        default:
            echo json_encode(['status' => 'error', 'message' => 'Invalid operation']);
            break;
    }
}
?>
