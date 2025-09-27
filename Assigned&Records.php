<?php
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

class Assigned {
    private $conn;

    public function __construct() {
        include 'connection-pdo.php';
        $this->conn = $conn;
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
                    rv.reservation_change_venue_id,
                    rv.active AS venue_active,
                    CASE 
                        WHEN rs.reservation_status_status_id IN (6, 10, 14) AND rv.reservation_change_venue_id IS NOT NULL 
                        THEN cv.ven_name 
                        ELSE v.ven_name 
                    END AS venue_name,
                    cvc.checklist_name AS checklist_venue_name,
                    CASE 
                        WHEN rs.reservation_status_status_id IN (6, 10, 14) AND rv.reservation_change_venue_id IS NOT NULL 
                        THEN ctsa.status_availability_name 
                        ELSE tsa.status_availability_name 
                    END AS venue_availability_status_name
                FROM tbl_reservation_checklist_venue rc_venue
                INNER JOIN tbl_reservation_venue rv
                    ON rc_venue.reservation_venue_id = rv.reservation_venue_id
                LEFT JOIN tbl_venue v
                    ON rv.reservation_venue_venue_id = v.ven_id
                LEFT JOIN tbl_venue cv
                    ON rv.reservation_change_venue_id = cv.ven_id
                LEFT JOIN tbl_checklist_venue_master cvc
                    ON rc_venue.checklist_venue_id = cvc.checklist_venue_id
                INNER JOIN tbl_reservation r
                    ON rv.reservation_reservation_id = r.reservation_id
                INNER JOIN tbl_reservation_status rs
                    ON r.reservation_id = rs.reservation_reservation_id
                LEFT JOIN tbl_status_availability tsa
                    ON v.status_availability_id = tsa.status_availability_id
                LEFT JOIN tbl_status_availability ctsa
                    ON cv.status_availability_id = ctsa.status_availability_id
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
                    rv.reservation_change_vehicle_id,
                    rv.active AS vehicle_active,
                    CASE 
                        WHEN rs.reservation_status_status_id IN (6, 10, 14) AND rv.reservation_change_vehicle_id IS NOT NULL 
                        THEN cv.vehicle_license 
                        ELSE vm.vehicle_license 
                    END AS vehicle_license,
                    CASE 
                        WHEN rs.reservation_status_status_id IN (6, 10, 14) AND rv.reservation_change_vehicle_id IS NOT NULL 
                        THEN cv.vehicle_model_id 
                        ELSE vm.vehicle_model_id 
                    END AS vehicle_model_id,
                    cvcv.checklist_name AS checklist_vehicle_name,
                    CASE 
                        WHEN rs.reservation_status_status_id IN (6, 10, 14) AND rv.reservation_change_vehicle_id IS NOT NULL 
                        THEN ctsa.status_availability_name 
                        ELSE tsa.status_availability_name 
                    END AS vehicle_availability_status_name
                FROM tbl_reservation_checklist_vehicle rc_vehicle
                INNER JOIN tbl_reservation_vehicle rv
                    ON rc_vehicle.reservation_vehicle_id = rv.reservation_vehicle_id
                LEFT JOIN tbl_vehicle vm
                    ON rv.reservation_vehicle_vehicle_id = vm.vehicle_id
                LEFT JOIN tbl_vehicle cv
                    ON rv.reservation_change_vehicle_id = cv.vehicle_id
                LEFT JOIN tbl_checklist_vehicle_master cvcv
                    ON rc_vehicle.checklist_vehicle_id = cvcv.checklist_vehicle_id
                INNER JOIN tbl_reservation r
                    ON rv.reservation_reservation_id = r.reservation_id
                INNER JOIN tbl_reservation_status rs
                    ON r.reservation_id = rs.reservation_reservation_id
                LEFT JOIN tbl_status_availability tsa
                    ON vm.status_availability_id = tsa.status_availability_id
                LEFT JOIN tbl_status_availability ctsa
                    ON cv.status_availability_id = ctsa.status_availability_id
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

    public function fetchReAssignPersonnel($reservationId) {
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
            $stmt->bindParam(':reservation_id', $reservationId, PDO::PARAM_INT);
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
                  AND reservation_status_status_id IN (6, 10, 14)
                  AND reservation_active = 1
                LIMIT 1
            ";
            $stmtStatus = $this->conn->prepare($statusSql);
            $stmtStatus->bindParam(':reservation_id', $reservationId, PDO::PARAM_INT);
            $stmtStatus->execute();
            $hasRescheduleStatus = (bool)$stmtStatus->fetchColumn();
    
            if (!$hasRescheduleStatus) {
                return json_encode(['status' => 'error', 'message' => 'Reservation is not in reschedule status']);
            }
    
            // 2. Fetch venues with reassignment data
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
            $stmt->bindParam(':reservation_id', $reservationId, PDO::PARAM_INT);
            $stmt->execute();
            $venues = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
            foreach ($venues as $venueRow) {
                // Determine which venue ID to use for checklists (original or changed)
                $venueIdToUse = ($hasRescheduleStatus && !empty($venueRow['reservation_change_venue_id'])) 
                    ? (int)$venueRow['reservation_change_venue_id'] 
                    : (int)$venueRow['original_venue_id'];
    
                $venueDetail = [
                    'reservation_venue_id' => $venueRow['reservation_venue_id'],
                    'venue_id' => $venueIdToUse,
                    'original_venue_id' => $venueRow['original_venue_id'],
                    'change_venue_id' => $venueRow['reservation_change_venue_id'],
                    'active' => $venueRow['active'],
                    'original_name' => null,
                    'change_name' => null,
                    'old_checklists' => [],
                    'new_checklists' => [],
                    'current_personnel' => []
                ];
    
                // Get original venue name
                if ($venueRow['original_venue_id']) {
                    $venNameSql = "SELECT ven_name FROM tbl_venue WHERE ven_id = :venue_id";
                    $stmtName = $this->conn->prepare($venNameSql);
                    $stmtName->bindParam(':venue_id', $venueRow['original_venue_id'], PDO::PARAM_INT);
                    $stmtName->execute();
                    $venueName = $stmtName->fetch(PDO::FETCH_ASSOC);
                    $venueDetail['original_name'] = $venueName ? $venueName['ven_name'] : null;
                }
    
                // Get changed venue name if exists
                if ($venueRow['reservation_change_venue_id']) {
                    $venNameSql = "SELECT ven_name FROM tbl_venue WHERE ven_id = :venue_id";
                    $stmtName = $this->conn->prepare($venNameSql);
                    $stmtName->bindParam(':venue_id', $venueRow['reservation_change_venue_id'], PDO::PARAM_INT);
                    $stmtName->execute();
                    $venueName = $stmtName->fetch(PDO::FETCH_ASSOC);
                    $venueDetail['change_name'] = $venueName ? $venueName['ven_name'] : null;
                }
    
                // Get old checklists (from original venue with reservation checklist IDs)
                if ($venueRow['original_venue_id']) {
                    $checklistVenueSql = "
                        SELECT 
                            cvm.checklist_venue_id, 
                            cvm.checklist_name,
                            rcv.reservation_checklist_venue_id
                        FROM tbl_checklist_venue_master cvm
                        LEFT JOIN tbl_reservation_checklist_venue rcv ON cvm.checklist_venue_id = rcv.checklist_venue_id 
                            AND rcv.reservation_venue_id = :reservation_venue_id
                        WHERE cvm.checklist_venue_ven_id = :venue_id
                    ";
                    $stmtChecklist = $this->conn->prepare($checklistVenueSql);
                    $stmtChecklist->bindParam(':venue_id', $venueRow['original_venue_id'], PDO::PARAM_INT);
                    $stmtChecklist->bindParam(':reservation_venue_id', $venueRow['reservation_venue_id'], PDO::PARAM_INT);
                    $stmtChecklist->execute();
                    $venueDetail['old_checklists'] = $stmtChecklist->fetchAll(PDO::FETCH_ASSOC);
                }
    
                // Get new checklists (from changed venue)
                if ($venueRow['reservation_change_venue_id']) {
                    $checklistVenueSql = "
                        SELECT checklist_venue_id, checklist_name 
                        FROM tbl_checklist_venue_master 
                        WHERE checklist_venue_ven_id = :venue_id
                    ";
                    $stmtChecklist = $this->conn->prepare($checklistVenueSql);
                    $stmtChecklist->bindParam(':venue_id', $venueRow['reservation_change_venue_id'], PDO::PARAM_INT);
                    $stmtChecklist->execute();
                    $venueDetail['new_checklists'] = $stmtChecklist->fetchAll(PDO::FETCH_ASSOC);
                }
    
                // Get current personnel assignments for this venue
                $personnelSql = "
                    SELECT DISTINCT
                        rc.personnel_id,
                        CONCAT(u.users_fname, ' ', 
                               COALESCE(CONCAT(LEFT(u.users_mname, 1), '. '), ''), 
                               u.users_lname, 
                               IF(u.users_suffix IS NOT NULL AND u.users_suffix != '', CONCAT(' ', u.users_suffix), '')
                        ) AS personnel_name,
                        rc.checklist_venue_id,
                        cvm.checklist_name,
                        rc.isChecked
                    FROM tbl_reservation_checklist_venue rc
                    LEFT JOIN tbl_users u ON rc.personnel_id = u.users_id
                    LEFT JOIN tbl_checklist_venue_master cvm ON rc.checklist_venue_id = cvm.checklist_venue_id
                    WHERE rc.reservation_venue_id = :reservation_venue_id
                ";
                
                $stmt = $this->conn->prepare($personnelSql);
                $stmt->bindParam(':reservation_venue_id', $venueRow['reservation_venue_id'], PDO::PARAM_INT);
                $stmt->execute();
                $venueDetail['current_personnel'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
                $result['venues'][] = $venueDetail;
            }
    
            // 3. Fetch vehicles with reassignment data
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
            $stmt->bindParam(':reservation_id', $reservationId, PDO::PARAM_INT);
            $stmt->execute();
            $vehicles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
            foreach ($vehicles as $vehicleRow) {
                // Determine which vehicle ID to use for checklists (original or changed)
                $vehicleIdToUse = ($hasRescheduleStatus && !empty($vehicleRow['reservation_change_vehicle_id']))
                    ? (int)$vehicleRow['reservation_change_vehicle_id']
                    : (int)$vehicleRow['vehicle_id'];
    
                $vehicleDetail = [
                    'reservation_vehicle_id' => $vehicleRow['reservation_vehicle_id'],
                    'vehicle_id' => $vehicleIdToUse,
                    'original_vehicle_id' => $vehicleRow['vehicle_id'],
                    'change_vehicle_id' => $vehicleRow['reservation_change_vehicle_id'],
                    'active' => isset($vehicleRow['active']) ? $vehicleRow['active'] : null,
                    'original_model' => null,
                    'original_license' => null,
                    'change_model' => null,
                    'change_license' => null,
                    'old_checklists' => [],
                    'new_checklists' => [],
                    'current_personnel' => []
                ];
    
                // Get original vehicle details
                if ($vehicleRow['vehicle_id']) {
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
                    $stmtVehicle->bindParam(':vehicle_id', $vehicleRow['vehicle_id'], PDO::PARAM_INT);
                    $stmtVehicle->execute();
                    $vehicleInfo = $stmtVehicle->fetch(PDO::FETCH_ASSOC);
    
                    if ($vehicleInfo) {
                        $vehicleDetail['original_model'] = $vehicleInfo['vehicle_model_name'];
                        $vehicleDetail['original_license'] = $vehicleInfo['vehicle_license'];
                    }
                }
    
                // Get changed vehicle details if exists
                if ($vehicleRow['reservation_change_vehicle_id']) {
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
                    $stmtVehicle->bindParam(':vehicle_id', $vehicleRow['reservation_change_vehicle_id'], PDO::PARAM_INT);
                    $stmtVehicle->execute();
                    $vehicleInfo = $stmtVehicle->fetch(PDO::FETCH_ASSOC);
    
                    if ($vehicleInfo) {
                        $vehicleDetail['change_model'] = $vehicleInfo['vehicle_model_name'];
                        $vehicleDetail['change_license'] = $vehicleInfo['vehicle_license'];
                    }
                }
    
                // Get old checklists (from original vehicle with reservation checklist IDs)
                if ($vehicleRow['vehicle_id']) {
                    $checklistVehicleSql = "
                        SELECT 
                            cvm.checklist_vehicle_id, 
                            cvm.checklist_name,
                            rcv.reservation_checklist_vehicle_id
                        FROM tbl_checklist_vehicle_master cvm
                        LEFT JOIN tbl_reservation_checklist_vehicle rcv ON cvm.checklist_vehicle_id = rcv.checklist_vehicle_id 
                            AND rcv.reservation_vehicle_id = :reservation_vehicle_id
                        WHERE cvm.checklist_vehicle_vehicle_id = :vehicle_id
                    ";
                    $stmtChecklist = $this->conn->prepare($checklistVehicleSql);
                    $stmtChecklist->bindParam(':vehicle_id', $vehicleRow['vehicle_id'], PDO::PARAM_INT);
                    $stmtChecklist->bindParam(':reservation_vehicle_id', $vehicleRow['reservation_vehicle_id'], PDO::PARAM_INT);
                    $stmtChecklist->execute();
                    $vehicleDetail['old_checklists'] = $stmtChecklist->fetchAll(PDO::FETCH_ASSOC);
                }
    
                // Get new checklists (from changed vehicle)
                if ($vehicleRow['reservation_change_vehicle_id']) {
                    $checklistVehicleSql = "
                        SELECT checklist_vehicle_id, checklist_name 
                        FROM tbl_checklist_vehicle_master 
                        WHERE checklist_vehicle_vehicle_id = :vehicle_id
                    ";
                    $stmtChecklist = $this->conn->prepare($checklistVehicleSql);
                    $stmtChecklist->bindParam(':vehicle_id', $vehicleRow['reservation_change_vehicle_id'], PDO::PARAM_INT);
                    $stmtChecklist->execute();
                    $vehicleDetail['new_checklists'] = $stmtChecklist->fetchAll(PDO::FETCH_ASSOC);
                }
    
                // Get current personnel assignments for this vehicle
                $personnelSql = "
                    SELECT DISTINCT
                        rc.personnel_id,
                        CONCAT(u.users_fname, ' ', 
                               COALESCE(CONCAT(LEFT(u.users_mname, 1), '. '), ''), 
                               u.users_lname, 
                               IF(u.users_suffix IS NOT NULL AND u.users_suffix != '', CONCAT(' ', u.users_suffix), '')
                        ) AS personnel_name,
                        rc.checklist_vehicle_id,
                        cvm.checklist_name,
                        rc.isChecked
                    FROM tbl_reservation_checklist_vehicle rc
                    LEFT JOIN tbl_users u ON rc.personnel_id = u.users_id
                    LEFT JOIN tbl_checklist_vehicle_master cvm ON rc.checklist_vehicle_id = cvm.checklist_vehicle_id
                    WHERE rc.reservation_checklist_vehicle_id = :reservation_vehicle_id
                ";
                
                $stmt = $this->conn->prepare($personnelSql);
                $stmt->bindParam(':reservation_vehicle_id', $vehicleRow['reservation_vehicle_id'], PDO::PARAM_INT);
                $stmt->execute();
                $vehicleDetail['current_personnel'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
                $result['vehicles'][] = $vehicleDetail;
            }
    
            return json_encode(['status' => 'success', 'data' => $result]);
    
        } catch (PDOException $e) {
            return json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
        }
    }
    
    public function updateReassignChecklist($data) {
        try {
            if (empty($data['reservation_id']) || empty($data['personnel_assignments'])) {
                return json_encode([
                    'status' => 'error',
                    'message' => 'Missing reservation_id or personnel_assignments.'
                ]);
            }
            
            $this->conn->beginTransaction();
            $results = [];
            $reservationId = $data['reservation_id'];
            $personnelAssignments = $data['personnel_assignments'];
            $admin_id = $data['admin_id'] ?? null;
            
            foreach ($personnelAssignments as $assignment) {
                $resourceType = $assignment['resource_type']; // 'venue' or 'vehicle'
                $resourceId = $assignment['resource_id']; // reservation_venue_id or reservation_vehicle_id
                $personnelId = $assignment['personnel_id'];
                $checklistItems = $assignment['checklist_items'] ?? [];
                
                if ($resourceType === 'venue') {
                    // Delete existing venue checklist personnel assignments first
                    foreach ($checklistItems as $item) {
                        $deleteSql = "
                            DELETE FROM tbl_reservation_checklist_venue 
                            WHERE reservation_venue_id = :resource_id
                        ";
                        
                        $deleteStmt = $this->conn->prepare($deleteSql);
                        $deleteStmt->bindParam(':resource_id', $resourceId, PDO::PARAM_INT);
                        $deleteStmt->execute();
                        
                        // Insert new venue checklist personnel assignment
                        $insertSql = "
                            INSERT INTO tbl_reservation_checklist_venue 
                            (reservation_venue_id, checklist_venue_id, personnel_id, admin_id)
                            VALUES (:reservation_venue_id, :checklist_id, :personnel_id, :admin_id)
                        ";
                        
                        $insertStmt = $this->conn->prepare($insertSql);
                        $insertStmt->bindParam(':reservation_venue_id', $resourceId, PDO::PARAM_INT);
                        $insertStmt->bindParam(':checklist_id', $item['checklist_id'], PDO::PARAM_INT);
                        $insertStmt->bindParam(':personnel_id', $personnelId, PDO::PARAM_INT);
                        $insertStmt->bindParam(':admin_id', $admin_id, PDO::PARAM_INT);
                        
                        $success = $insertStmt->execute();
                        $results[] = [
                            'type' => 'venue',
                            'resource_id' => $resourceId,
                            'checklist_id' => $item['checklist_id'],
                            'personnel_id' => $personnelId,
                            'success' => $success
                        ];
                    }
                } elseif ($resourceType === 'vehicle') {
                    // Delete existing vehicle checklist personnel assignments first
                    foreach ($checklistItems as $item) {
                        $deleteSql = "
                            DELETE FROM tbl_reservation_checklist_vehicle 
                            WHERE reservation_vehicle_id = :resource_id
                  
                        ";
                        
                        $deleteStmt = $this->conn->prepare($deleteSql);
                        $deleteStmt->bindParam(':resource_id', $resourceId, PDO::PARAM_INT);
                       
                        $deleteStmt->execute();
                        
                        // Insert new vehicle checklist personnel assignment
                        $insertSql = "
                            INSERT INTO tbl_reservation_checklist_vehicle 
                            (reservation_vehicle_id, checklist_vehicle_id, personnel_id, admin_id)
                            VALUES (:reservation_vehicle_id, :checklist_id, :personnel_id, :admin_id)
                        ";
                        
                        $insertStmt = $this->conn->prepare($insertSql);
                        $insertStmt->bindParam(':reservation_vehicle_id', $resourceId, PDO::PARAM_INT);
                        $insertStmt->bindParam(':checklist_id', $item['checklist_id'], PDO::PARAM_INT);
                        $insertStmt->bindParam(':personnel_id', $personnelId, PDO::PARAM_INT);
                        $insertStmt->bindParam(':admin_id', $admin_id, PDO::PARAM_INT);
                        
                        $success = $insertStmt->execute();
                        $results[] = [
                            'type' => 'vehicle',
                            'resource_id' => $resourceId,
                            'checklist_id' => $item['checklist_id'],
                            'personnel_id' => $personnelId,
                            'success' => $success
                        ];
                    }
                }
            }
            
            $this->conn->commit();
            
            // Non-blocking audit logging
            try {
                $personnelFullName = null;
                if ($admin_id) {
                    $nameSql = "SELECT CONCAT_WS(' ', users_fname, users_mname, users_lname) AS full_name FROM tbl_users WHERE users_id = :admin_id";
                    $nameStmt = $this->conn->prepare($nameSql);
                    $nameStmt->bindParam(':admin_id', $admin_id, PDO::PARAM_INT);
                    $nameStmt->execute();
                    $nameResult = $nameStmt->fetch(PDO::FETCH_ASSOC);
                    $personnelFullName = $nameResult['full_name'] ?? null;
                }
                
                $desc = sprintf('Updated reassign checklist personnel for reservation #%d%s',
                              $reservationId,
                              $personnelFullName ? (' by: ' . $personnelFullName) : '');
                
                $auditSql = "INSERT INTO tbl_audit_log (audit_action, audit_description, audit_table, audit_record_id, created_by, created_at) 
                            VALUES ('UPDATE', :description, 'tbl_reservation_checklist', :reservation_id, :admin_id, NOW())";
                $auditStmt = $this->conn->prepare($auditSql);
                $auditStmt->bindParam(':description', $desc, PDO::PARAM_STR);
                $auditStmt->bindParam(':reservation_id', $reservationId, PDO::PARAM_INT);
                $auditStmt->bindParam(':admin_id', $admin_id, PDO::PARAM_INT);
                $auditStmt->execute();
            } catch (PDOException $e) {
                error_log('Audit logging error in updateReassignChecklist: ' . $e->getMessage());
            }
            
            return json_encode([
                'status' => 'success',
                'message' => 'Reassign checklist updated successfully',
                'results' => $results
            ]);
            
        } catch (PDOException $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            return json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
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

            // Send push notification to the assigned personnel (non-blocking)
            try {
                $pushTitle = 'New Checklist Task Assigned';
                $pushBody = 'You have been assigned new checklist tasks.';
                $pushData = [
                    'type' => 'checklist_assign',
                    'reservation_id' => $reservationIdForNotif ?? null
                ];
                $this->sendPushNotificationToUser((int)$data['personnel_id'], $pushTitle, $pushBody, $pushData);
            } catch (Throwable $te) { /* ignore push errors */ }

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

    // Local helper: insert notification record to tbl_notification_reservation
    private function insertNotificationTouser($notification_message, $notification_user_id, $reservation_id = null) {
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
            }
            return json_encode([
                'status' => 'error',
                'message' => 'Failed to insert notification'
            ]);
        } catch (PDOException $e) {
            return json_encode([
                'status' => 'error',
                'message' => 'Database error: ' . $e->getMessage()
            ]);
        }
    }

    // Local helper: send push notification via PHP push service
    private function sendPushNotificationToUser($userId, $title = 'Notification', $body = 'You have a new notification', $data = []) {
        try {
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

            $result = @file_get_contents($pushNotificationUrl, false, $context);
            if ($result === false) {
                error_log("[Assigned.saveChecklist] Failed to send push notification to user {$userId}");
                return false;
            }
            $response = json_decode($result, true);
            if ($response && isset($response['status']) && $response['status'] === 'success') {
                return true;
            }
            error_log("[Assigned.saveChecklist] Push notification failed for user {$userId}: " . ($response['message'] ?? 'Unknown error'));
            return false;
        } catch (Throwable $e) {
            error_log("[Assigned.saveChecklist] Exception sending push: " . $e->getMessage());
            return false;
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
                        CONCAT(
                            COALESCE(v.reservation_venue_id, ''), ':', 
                            COALESCE(v.reservation_venue_venue_id, ''), ':',
                            COALESCE(venue.ven_name, ''), ':',
                            COALESCE(venue.ven_occupancy, '')
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
                        if (count($venueParts) >= 4 && !empty(array_filter($venueParts))) {
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
                    'reschedule_start_date' => $row['reschedule_start_date'],
                    'reschedule_end_date' => $row['reschedule_end_date'],
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

    public function insertUnits($equipIds, $quantities, $reservationId, $startDate, $endDate) {
        try {
            $this->conn->beginTransaction();
            $results = [];
            $allocatedUnits = []; // Track units allocated in this session
            
            // Log the start of unit allocation
            error_log("insertUnits: Starting allocation for reservation_id: $reservationId, equipIds: " . json_encode($equipIds) . ", quantities: " . json_encode($quantities));
    
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
                    // New approach: Get all available units first, then filter step by step
                    error_log("insertUnits: Processing serialized equipment $equipId, quantity needed: $quantity");
    
                    // Determine effective start/end datetime for the CURRENT reservation (use reschedule dates when applicable)
                    // This avoids false negatives if $startDate/$endDate are passed as DATE only without time.
                    $sqlLatestStatusCurrent = "
                        SELECT 
                            rs.reservation_status_status_id,
                            rs.reservation_active,
                            rs.reservation_updated_at
                        FROM tbl_reservation_status rs
                        WHERE rs.reservation_reservation_id = :reservation_id
                        ORDER BY rs.reservation_updated_at DESC, rs.reservation_status_id DESC
                        LIMIT 1";
    
                    $stmtLatestCur = $this->conn->prepare($sqlLatestStatusCurrent);
                    $stmtLatestCur->bindParam(':reservation_id', $reservationId, PDO::PARAM_INT);
                    $stmtLatestCur->execute();
                    $latestCur = $stmtLatestCur->fetch(PDO::FETCH_ASSOC) ?: [];
    
                    $sqlResHeader = "
                        SELECT reservation_start_date, reservation_end_date, reschedule_start_date, reschedule_end_date
                        FROM tbl_reservation
                        WHERE reservation_id = :reservation_id";
                    $stmtResHdr = $this->conn->prepare($sqlResHeader);
                    $stmtResHdr->bindParam(':reservation_id', $reservationId, PDO::PARAM_INT);
                    $stmtResHdr->execute();
                    $resHdr = $stmtResHdr->fetch(PDO::FETCH_ASSOC) ?: [];
    
                    $useResched = false;
                    if (!empty($latestCur) && in_array((int)$latestCur['reservation_status_status_id'], [10,11,14], true)
                        && !empty($resHdr['reschedule_start_date']) && !empty($resHdr['reschedule_end_date'])) {
                        $useResched = true;
                    }
    
                    $effectiveStart = $useResched ? $resHdr['reschedule_start_date'] : $resHdr['reservation_start_date'];
                    $effectiveEnd   = $useResched ? $resHdr['reschedule_end_date']   : $resHdr['reservation_end_date'];
    
                    // Fallback: if for some reason header is missing, use provided parameters
                    if (empty($effectiveStart)) { $effectiveStart = $startDate; }
                    if (empty($effectiveEnd))   { $effectiveEnd   = $endDate; }
    
                    error_log("insertUnits: Effective window for reservation $reservationId -> start: $effectiveStart, end: $effectiveEnd (useResched=" . ($useResched ? '1' : '0') . ")");
                    
                    // Step 1: Get all units for this equipment that are active and not broken
                    $sqlAllUnits = "
                        SELECT eu.unit_id, eu.serial_number 
                        FROM tbl_equipment_unit eu
                        WHERE eu.equip_id = :equip_id 
                            AND eu.status_availability_id != 2 
                            AND eu.is_active = 1
                        ORDER BY eu.unit_id ASC";
                    
                    $stmtAllUnits = $this->conn->prepare($sqlAllUnits);
                    $stmtAllUnits->bindParam(':equip_id', $equipId, PDO::PARAM_INT);
                    $stmtAllUnits->execute();
                    $allUnits = $stmtAllUnits->fetchAll(PDO::FETCH_ASSOC);
                    
                    error_log("insertUnits: Found " . count($allUnits) . " total units for equipment $equipId");
                    error_log("insertUnits: All units from tbl_equipment_unit: " . json_encode($allUnits));
                    
                    // Step 2: Get units already allocated to current reservation
                    $sqlCurrentReservationUnits = "
                        SELECT DISTINCT tru.unit_id
                        FROM tbl_reservation_unit tru
                        INNER JOIN tbl_reservation_equipment tre ON tru.reservation_equipment_id = tre.reservation_equipment_id
                        WHERE tre.reservation_reservation_id = :reservation_id";
                    
                    $stmtCurrentUnits = $this->conn->prepare($sqlCurrentReservationUnits);
                    $stmtCurrentUnits->bindParam(':reservation_id', $reservationId, PDO::PARAM_INT);
                    $stmtCurrentUnits->execute();
                    $currentReservationUnits = $stmtCurrentUnits->fetchAll(PDO::FETCH_COLUMN);
                    
                    error_log("insertUnits: Current reservation query: " . $sqlCurrentReservationUnits);
                    error_log("insertUnits: Current reservation_id parameter: " . $reservationId);
                    error_log("insertUnits: Units already allocated to current reservation: " . json_encode($currentReservationUnits));
                    
                    // Step 3: Get units reserved in other active reservations with date overlap
                    $sqlOtherReservationUnits = "
                        SELECT DISTINCT tru.unit_id
                        FROM tbl_reservation_unit tru
                        INNER JOIN tbl_reservation_equipment tre ON tru.reservation_equipment_id = tre.reservation_equipment_id
                        INNER JOIN tbl_reservation tr ON tre.reservation_reservation_id = tr.reservation_id
                        INNER JOIN (
                            SELECT 
                                rs1.reservation_reservation_id,
                                rs1.reservation_status_status_id,
                                rs1.reservation_active,
                                rs1.reservation_updated_at
                            FROM tbl_reservation_status rs1
                            WHERE rs1.reservation_status_id = (
                                SELECT MAX(rs2.reservation_status_id)
                                FROM tbl_reservation_status rs2
                                WHERE rs2.reservation_reservation_id = rs1.reservation_reservation_id
                                AND rs2.reservation_updated_at = (
                                    SELECT MAX(rs3.reservation_updated_at)
                                    FROM tbl_reservation_status rs3
                                    WHERE rs3.reservation_reservation_id = rs1.reservation_reservation_id
                                )
                            )
                        ) latest_status ON latest_status.reservation_reservation_id = tr.reservation_id
                        WHERE tr.reservation_id != :reservation_id
                            AND latest_status.reservation_status_status_id IN (6, 8, 10, 11, 14)
                            AND (
                                (latest_status.reservation_status_status_id IN (10, 11, 14) AND 
                                 COALESCE(tr.reschedule_start_date, tr.reservation_start_date) <= :end_date AND 
                                 COALESCE(tr.reschedule_end_date, tr.reservation_end_date) >= :start_date)
                                OR
                                (latest_status.reservation_status_status_id NOT IN (10, 11, 14) AND 
                                 tr.reservation_start_date <= :end_date AND tr.reservation_end_date >= :start_date)
                            )";
                    
                    $stmtOtherUnits = $this->conn->prepare($sqlOtherReservationUnits);
                    $stmtOtherUnits->bindParam(':reservation_id', $reservationId, PDO::PARAM_INT);
                    // Use effective start/end which include proper times and reschedules
                    $stmtOtherUnits->bindParam(':start_date', $effectiveStart);
                    $stmtOtherUnits->bindParam(':end_date', $effectiveEnd);
                    
                    error_log("insertUnits: SQL Query for other reservations: " . $sqlOtherReservationUnits);
                    error_log("insertUnits: Query parameters - reservation_id: $reservationId, start_date: $effectiveStart, end_date: $effectiveEnd");
                    
                    $stmtOtherUnits->execute();
                    $otherReservationUnits = $stmtOtherUnits->fetchAll(PDO::FETCH_COLUMN);
                    
                    error_log("insertUnits: Units reserved in other overlapping reservations: " . json_encode($otherReservationUnits));
                    
                    // Debug: Check if there are ANY units for equipment 48 in tbl_reservation_unit
                    $debugSql = "
                        SELECT DISTINCT tru.unit_id, tre.reservation_reservation_id
                        FROM tbl_reservation_unit tru
                        INNER JOIN tbl_reservation_equipment tre ON tru.reservation_equipment_id = tre.reservation_equipment_id
                        WHERE tre.reservation_equipment_equip_id = :equip_id";
                    $debugStmt = $this->conn->prepare($debugSql);
                    $debugStmt->bindParam(':equip_id', $equipId, PDO::PARAM_INT);
                    $debugStmt->execute();
                    $debugUnits = $debugStmt->fetchAll(PDO::FETCH_ASSOC);
                    error_log("insertUnits: DEBUG - All units for equipment $equipId in tbl_reservation_unit: " . json_encode($debugUnits));
                    
                    // Debug: Check reservation statuses for any reservations with unit 20
                    if (!empty($debugUnits)) {
                        foreach ($debugUnits as $debugUnit) {
                            $statusSql = "
                                SELECT rs.reservation_status_status_id, rs.reservation_active, rs.reservation_updated_at, tr.reservation_start_date, tr.reservation_end_date
                                FROM tbl_reservation_status rs
                                INNER JOIN tbl_reservation tr ON rs.reservation_reservation_id = tr.reservation_id
                                WHERE rs.reservation_reservation_id = :res_id
                                ORDER BY rs.reservation_updated_at DESC, rs.reservation_status_id DESC
                                LIMIT 1";
                            $statusStmt = $this->conn->prepare($statusSql);
                            $statusStmt->bindParam(':res_id', $debugUnit['reservation_reservation_id'], PDO::PARAM_INT);
                            $statusStmt->execute();
                            $statusInfo = $statusStmt->fetch(PDO::FETCH_ASSOC);
                            error_log("insertUnits: DEBUG - Unit " . $debugUnit['unit_id'] . " in reservation " . $debugUnit['reservation_reservation_id'] . " has status: " . json_encode($statusInfo));
                        }
                    }
                    
                    // Step 4: Combine all excluded units
                    $excludedUnits = array_merge($currentReservationUnits, $otherReservationUnits, $allocatedUnits);
                    $excludedUnits = array_unique($excludedUnits);
                    
                    error_log("insertUnits: All excluded units: " . json_encode($excludedUnits));
                    
                    // Step 5: Filter available units (get ALL available, don't break early)
                    $availableUnits = [];
                    foreach ($allUnits as $unit) {
                        error_log("insertUnits: Checking unit " . $unit['unit_id'] . " - excluded: " . (in_array($unit['unit_id'], $excludedUnits) ? 'YES' : 'NO'));
                        if (!in_array($unit['unit_id'], $excludedUnits)) {
                            $availableUnits[] = $unit;
                        }
                    }
                    
                    error_log("insertUnits: Available units after filtering: " . json_encode(array_column($availableUnits, 'unit_id')));
                    
                    $actualUnitsToInsert = array_slice($availableUnits, 0, $quantity);
                    
                    if (count($actualUnitsToInsert) === 0) {
                        error_log("insertUnits: No available units for equipment $equipId");
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
    
                    // Step 6: Insert the selected units and track them
                    $stmtInsert = $this->conn->prepare("INSERT INTO tbl_reservation_unit 
                                                        (reservation_equipment_id, unit_id, active) 
                                                        VALUES (:reservation_equipment_id, :unit_id, 0)");
                    
                    $insertedUnits = [];
                    foreach ($actualUnitsToInsert as $unit) {
                        $stmtInsert->execute([
                            ':reservation_equipment_id' => $reservationEquipmentId,
                            ':unit_id' => $unit['unit_id']
                        ]);
                        $insertedUnits[] = $unit;
                        $allocatedUnits[] = $unit['unit_id']; // Track for next iteration
                    }
                    
                    error_log("insertUnits: Inserted units for equipment $equipId: " . json_encode(array_column($insertedUnits, 'unit_id')));
    
                    $results[] = [
                        'equip_id' => $equipId,
                        'reservation_equipment_id' => $reservationEquipmentId,
                        'type' => 'serialized',
                        'units_inserted' => count($insertedUnits),
                        'units_missing' => max(0, $quantity - count($insertedUnits)),
                        'units' => $insertedUnits,
                        'can_release' => count($insertedUnits) > 0
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
                  AND reservation_status_status_id IN (6, 10, 14)
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
                    (
                        (rs.reservation_status_status_id = 6 AND rs.reservation_active = 1)
                        OR
                        (rs.reservation_status_status_id IN (10, 14) AND rs.reservation_active = 1)
                    )
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
    
    public function fetchAllReassign() {
        try {
            $sql = "
                SELECT DISTINCT
                    r.reservation_id, 
                    r.reservation_title, 
                    CONCAT(u.users_fname, ' ', 
                           COALESCE(CONCAT(LEFT(u.users_mname, 1), '. '), ''), 
                           u.users_lname, 
                           IF(u.users_suffix IS NOT NULL AND u.users_suffix != '', CONCAT(' ', u.users_suffix), '')
                    ) AS requestor_name,
                    r.reservation_start_date, 
                    r.reservation_end_date,
                    GROUP_CONCAT(
                        DISTINCT CONCAT(
                            COALESCE(CONCAT(pu.users_fname, ' ', 
                                           COALESCE(CONCAT(LEFT(pu.users_mname, 1), '. '), ''), 
                                           pu.users_lname, 
                                           IF(pu.users_suffix IS NOT NULL AND pu.users_suffix != '', CONCAT(' ', pu.users_suffix), '')
                                    ), 'Unassigned')
                        ) SEPARATOR ', '
                    ) AS assigned_personnel
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
                LEFT JOIN tbl_reservation_venue rv ON r.reservation_id = rv.reservation_reservation_id
                LEFT JOIN tbl_reservation_vehicle rvh ON r.reservation_id = rvh.reservation_reservation_id
                LEFT JOIN tbl_reservation_checklist_venue cv ON rv.reservation_venue_id = cv.reservation_venue_id
                LEFT JOIN tbl_reservation_checklist_vehicle cvh ON rvh.reservation_vehicle_id = cvh.reservation_vehicle_id
                LEFT JOIN tbl_users pu ON (cv.personnel_id = pu.users_id OR cvh.personnel_id = pu.users_id)
                WHERE 
                    (rs.reservation_status_status_id IN (10, 14) AND rs.reservation_active = 1)
                    AND r.reservation_id IS NOT NULL
                    AND (
                        -- Show venue changes that need checklist updates (personnel not yet updated to new venue checklists)
                        (rv.reservation_change_venue_id IS NOT NULL 
                         AND rv.reservation_change_venue_id != rv.reservation_venue_venue_id
                         AND NOT EXISTS (
                             SELECT 1 FROM tbl_reservation_checklist_venue rcv_updated
                             INNER JOIN tbl_checklist_venue_master cvm_updated ON rcv_updated.checklist_venue_id = cvm_updated.checklist_venue_id
                             WHERE rcv_updated.reservation_venue_id = rv.reservation_venue_id 
                             AND cvm_updated.checklist_venue_ven_id = rv.reservation_change_venue_id
                             AND rcv_updated.personnel_id IS NOT NULL
                         ))
                        OR 
                        -- Show vehicle changes that need checklist updates (personnel not yet updated to new vehicle checklists)
                        (rvh.reservation_change_vehicle_id IS NOT NULL 
                         AND rvh.reservation_change_vehicle_id != rvh.reservation_vehicle_vehicle_id
                         AND NOT EXISTS (
                             SELECT 1 FROM tbl_reservation_checklist_vehicle rcvh_updated
                             INNER JOIN tbl_checklist_vehicle_master cvhm_updated ON rcvh_updated.checklist_vehicle_id = cvhm_updated.checklist_vehicle_id
                             WHERE rcvh_updated.reservation_vehicle_id = rvh.reservation_vehicle_id 
                             AND cvhm_updated.checklist_vehicle_vehicle_id = rvh.reservation_change_vehicle_id
                             AND rcvh_updated.personnel_id IS NOT NULL
                         ))
                    )
                GROUP BY 
                    r.reservation_id, r.reservation_title, requestor_name, r.reservation_start_date, r.reservation_end_date
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
                               AND rs2.reservation_status_status_id IN (6, 10, 14)
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
                               AND rs2.reservation_status_status_id IN (10, 14) 
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

    public function displayedMaintenanceResourcesDone() {
        try {
            $records = [];

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
                               AND rs.reservation_status_status_id IN (10, 14) 
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
                               AND rs.reservation_status_status_id IN (10, 14) 
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
                               AND rs.reservation_status_status_id IN (6, 10, 14) 
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
                               AND rs.reservation_status_status_id IN (6, 10, 14) 
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
    
    
}

// Handle incoming requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $assigned = new Assigned();
    $data = json_decode(file_get_contents('php://input'), true);

    if (isset($data['operation'])) {
        switch ($data['operation']) {

            
        case "getConsumableUsage":
            $equipId = $data['equipId'] ?? null;
            if ($equipId) {
                echo $assigned->getConsumableUsage($equipId);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Equipment ID is required']);
            }
            break;

            case "updateResourceStatusAndCondition":
                $type = $data['type'] ?? ($_POST['type'] ?? null);
                $resourceId = $data['resourceId'] ?? ($_POST['resourceId'] ?? null);
                $recordId = $data['recordId'] ?? ($_POST['recordId'] ?? null);
                $isFixed = $data['isFixed'] ?? ($_POST['isFixed'] ?? false);
                $user_personnel_id = $data['user_personnel_id'] ?? ($_POST['user_personnel_id'] ?? null);
                if ($type && $resourceId && $recordId) {
                    echo $assigned->updateResourceStatusAndCondition($type, $resourceId, $recordId, $isFixed, $user_personnel_id);
                } else {
                    echo json_encode(['status' => 'error', 'message' => 'Missing required parameters (type, resourceId, recordId)']);
                }
                break;

            case "displayedMaintenanceResourcesDone":
                echo $assigned->displayedMaintenanceResourcesDone();
                break;
    
            case "displayedMaintenanceResources":
                echo $assigned->displayedMaintenanceResources();
                break;
            case "getBulkUsage":
                $equipId = $data['equipId'] ?? null;
                if ($equipId) {
                    echo $assigned->getBulkUsage($equipId);
                } else {
                    echo json_encode(['status' => 'error', 'message' => 'Equipment ID is required']);
                }
                break;
    
            case "getEquipmentUnitUsage":
                $unitId = $data['unitId'] ?? null;
                if ($unitId) {
                    echo $assigned->getEquipmentUnitUsage($unitId);
                } else {
                    echo json_encode(['status' => 'error', 'message' => 'Unit ID is required']);
                }
                break;
    
            case "getVenueUsage":
                $venueId = $data['venueId'] ?? null;
                if ($venueId) {
                    echo $assigned->getVenueUsage($venueId);
                } else {
                    echo json_encode(['status' => 'error', 'message' => 'Venue ID is required']);
                }
                break;
    
            case "getVehicleUsage":
                $vehicleId = $data['vehicleId'] ?? null;
                if ($vehicleId) {
                    echo $assigned->getVehicleUsage($vehicleId);
                } else {
                    echo json_encode(['status' => 'error', 'message' => 'Vehicle ID is required']);
                }
                break;
    
            case "fetchNoAssignedReservation":
                echo $assigned->fetchNoAssignedReservation();
                break;

            case "fetchAllReassign":
                echo $assigned->fetchAllReassign();
                break;

            case "getReservedById":
                $reservationId = $data['reservation_id'] ?? ($_POST['reservation_id'] ?? null);
                if ($reservationId) {
                    echo $assigned->getReservedById($reservationId);
                } else {
                    echo json_encode(['status' => 'error', 'message' => 'Reservation ID parameter is missing.']);
                }
                break;

            case "fetchVenueHistory":
                $venueId = $data['venue_id'] ?? null;
                echo $assigned->fetchVenueHistory($venueId);
                break;
            case "fetchVehicleHistory":
                $vehicleId = $data['vehicle_id'] ?? null;
                echo $assigned->fetchVehicleHistory($vehicleId);
                break;
            case "fetchEquipmentHistory":
                $equipId = $data['equip_id'] ?? null;
                echo $assigned->fetchEquipmentHistory($equipId);
                break;
            case "fetchEquipmentUnitHistory":
                $unitId = $data['unit_id'] ?? null;
                echo $assigned->fetchEquipmentUnitHistory($unitId);
                break;
           

            case "insertUnits":
                $equipIds = $data['equip_ids'] ?? [];
                $quantities = $data['quantities'] ?? [];
                $reservationId = $data['reservation_id'] ?? null;
                $startDate = $data['start_date'] ?? null;
                $endDate = $data['end_date'] ?? null;
    
                if (empty($equipIds) || empty($quantities) || $reservationId === null || $startDate === null || $endDate === null) {
                    echo json_encode(['status' => 'error', 'message' => 'Equip IDs, Quantities, Reservation ID, Start Date, and End Date are required']);
                    break;
                }
    
                echo $assigned->insertUnits($equipIds, $quantities, $reservationId, $startDate, $endDate);
                break;

            case "fetchReservationGenerateReport":
                $month = $data['month'] ?? null;
                if ($month === null) {
                    echo json_encode(['status' => 'error', 'message' => 'Month is required']);
                    break;
                }
                $user_personnel_id = $data['user_personnel_id'] ?? null;
                echo $assigned->fetchReservationGenerateReport($month, $user_personnel_id);
                break;

            case "fetchReAssignPersonnel":
                $reservationId = $data['reservation_id'] ?? ($_POST['reservation_id'] ?? null);
                if (!$reservationId) {
                    echo json_encode(['status' => 'error', 'message' => 'Reservation ID is required']);
                    break;
                }
                echo $assigned->fetchReAssignPersonnel($reservationId);
                break;
    
            case "updateReassignChecklist":
                $data = $data['data'] ?? null;
                if (!$data) {
                    echo json_encode(['status' => 'error', 'message' => 'No data provided']);
                    break;
                }
                echo $assigned->updateReassignChecklist($data);
                break;
            case "fetchAllAssignedReleases":
                echo $assigned->fetchAllAssignedReleases();
                break;
    
            case "fetchDoneAssignedReleases":
                echo $assigned->fetchDoneAssignedReleases();
                break;
           
            case "saveChecklist":
                $data = $data['data'] ?? null;
                if (!$data) {
                    echo json_encode(['status' => 'error', 'message' => 'No data provided']);
                    break;
                }
                echo $assigned->saveChecklist($data);
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