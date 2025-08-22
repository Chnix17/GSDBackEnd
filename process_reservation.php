<?php
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

error_reporting(E_ALL & ~E_NOTICE);

class User {
    private $conn;

    public function __construct() {
        include 'connection-pdo.php'; // Ensure this file contains the correct PDO connection
        $this->conn = $conn;
    }

    // Fetch approval requests based on department ID
//    public function fetchApprovalByDept(int $departmentId, int $userLevelId, int $currentUserId): string
//         {
//     if (!$departmentId || !$userLevelId) {
//         return json_encode([
//             'status'  => 'error',
//             'message' => 'Department ID and User Level ID are required'
//         ]);
//     }

//     try {
//         $sql = "
//             SELECT 
//                 r.reservation_id,
//                 r.reservation_created_at,
//                 r.reservation_title,
//                 r.reservation_description,
//                 r.reservation_start_date,
//                 r.reservation_end_date,
//                 r.reservation_participants,
//                 r.reservation_user_id,
//                 rs.reservation_status_status_id    AS status_id,
//                 rs.reservation_active              AS active,
//                 u_req.users_user_level_id          AS user_level_id,
//                 CONCAT_WS(' ',
//                     u_req.users_fname,
//                     u_req.users_mname,
//                     u_req.users_lname
//                 )                                    AS requester_name,
//                 dep.departments_name               AS department_name,

//                 -- Venues
//                 GROUP_CONCAT(DISTINCT
//                     CONCAT_WS(':',
//                         v.reservation_venue_venue_id,
//                         venue.ven_name,
//                         venue.ven_occupancy,
//                         venue.ven_operating_hours,
//                         IFNULL(venue.ven_pic,'')
//                     )
//                 ) AS venue_data,

//                 -- Vehicles
//                 GROUP_CONCAT(DISTINCT
//                     CONCAT_WS(':',
//                         ve.reservation_vehicle_vehicle_id,
//                         vm.vehicle_license,
//                         vmm.vehicle_model_name
//                     )
//                 ) AS vehicle_data,

//                 -- Equipment
//                 GROUP_CONCAT(DISTINCT
//                     CONCAT_WS(':',
//                         e.reservation_equipment_equip_id,
//                         equip.equip_name,
//                         e.reservation_equipment_quantity
//                     )
//                 ) AS equipment_data,

//                 -- Driver assignment
//                 d.reservation_driver_id,
//                 d.reservation_driver_user_id       AS driver_id,
//                 CONCAT_WS(' ',
//                     drv.driver_first_name,
//                     drv.driver_middle_name,
//                     drv.driver_last_name
//                 )                                    AS driver_name,

//                 -- Passengers
//                 GROUP_CONCAT(DISTINCT
//                     CONCAT_WS(':',
//                         p.reservation_passenger_id,
//                         p.reservation_passenger_name
//                     )
//                 ) AS passenger_data

//             FROM tbl_reservation r
//             LEFT JOIN tbl_users u_req 
//               ON r.reservation_user_id = u_req.users_id
//             LEFT JOIN tbl_departments dep 
//               ON u_req.users_department_id = dep.departments_id
//             LEFT JOIN tbl_reservation_status rs 
//               ON r.reservation_id = rs.reservation_reservation_id
//             LEFT JOIN tbl_reservation_venue v 
//               ON r.reservation_id = v.reservation_reservation_id
//             LEFT JOIN tbl_venue venue 
//               ON v.reservation_venue_venue_id = venue.ven_id
//             LEFT JOIN tbl_reservation_vehicle ve 
//               ON r.reservation_id = ve.reservation_reservation_id
//             LEFT JOIN tbl_vehicle vm 
//               ON ve.reservation_vehicle_vehicle_id = vm.vehicle_id
//             LEFT JOIN tbl_vehicle_model vmm 
//               ON vm.vehicle_model_id = vmm.vehicle_model_id
//             LEFT JOIN tbl_reservation_equipment e 
//               ON r.reservation_id = e.reservation_reservation_id
//             LEFT JOIN tbl_equipments equip 
//               ON e.reservation_equipment_equip_id = equip.equip_id
//             LEFT JOIN tbl_reservation_driver d 
//               ON r.reservation_id = d.reservation_reservation_id
//             LEFT JOIN tbl_driver drv 
//               ON d.reservation_driver_user_id = drv.driver_id
//             LEFT JOIN tbl_reservation_passenger p 
//               ON r.reservation_id = p.reservation_reservation_id

//             WHERE
//                 /* 1) If caller is level 6 (secretary), then:
//                       - r.reservation_user_id != caller
//                       - u_req.users_user_level_id != 6 (exclude all secretaries' requests)
//                    Otherwise (other levels), allow everything */
//                 (
//                     :user_level_id = 6
                  
//                       AND u_req.users_user_level_id != 6
//                   OR :user_level_id != 6
//                 )
//                 /* 2) Department-based filter (unchanged) */
//                 AND (
//                     (:department_id = 29 AND u_req.users_user_level_id IN (16,17))
//                   OR (:department_id != 29
//                         AND u_req.users_department_id      = :department_id
//                         AND u_req.users_user_level_id NOT IN (16,17)
//                      )
//                 )
//                 /* 3) Only new reservations */
//                 AND EXISTS (
//                     SELECT 1
//                     FROM tbl_reservation_status rs1
//                     WHERE
//                         rs1.reservation_reservation_id    = r.reservation_id
//                       AND rs1.reservation_status_status_id = 1
//                       AND rs1.reservation_active           IS NULL
//                 )
//                 /* 4) Exclude any that have progressed past status 1 */
//                 AND NOT EXISTS (
//                     SELECT 1
//                     FROM tbl_reservation_status rs2
//                     WHERE
//                         rs2.reservation_reservation_id    = r.reservation_id
//                       AND rs2.reservation_status_status_id IN (2,3,4,5,6)
//                 )
//             GROUP BY r.reservation_id
//         ";

//         $stmt = $this->conn->prepare($sql);
//         $stmt->execute([
//             'department_id'     => $departmentId,
//             'user_level_id'     => $userLevelId,
//             'current_user_id'   => $currentUserId
//         ]);

//         $result = [];
//         while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
//             // Decode concatenated fields into structured arrays...
//             $venues     = [];
//             $vehicles   = [];
//             $equipment  = [];
//             $drivers    = [];
//             $passengers = [];

//             if (!empty($row['venue_data'])) {
//                 foreach (explode(',', $row['venue_data']) as $str) {
//                     list($vid,$vname,$occ,$hours,$pic) = explode(':', $str) + [null,null,null,null,null];
//                     $venues[] = [
//                         'venue_id'        => $vid,
//                         'venue_name'      => $vname,
//                         'occupancy'       => $occ,
//                         'operating_hours' => $hours,
//                         'picture'         => $pic
//                     ];
//                 }
//             }

//             if (!empty($row['vehicle_data'])) {
//                 foreach (explode(',', $row['vehicle_data']) as $str) {
//                     list($vid,$license,$model) = explode(':', $str) + [null,null,null];
//                     $vehicles[] = [
//                         'vehicle_id' => $vid,
//                         'license'    => $license,
//                         'model'      => $model
//                     ];
//                 }
//             }

//             if (!empty($row['equipment_data'])) {
//                 foreach (explode(',', $row['equipment_data']) as $str) {
//                     list($eid,$ename,$qty) = explode(':', $str) + [null,null,null];
//                     $equipment[] = [
//                         'equipment_id' => $eid,
//                         'name'         => $ename,
//                         'quantity'     => $qty
//                     ];
//                 }
//             }

//             // Fetch all drivers for this reservation
//             $driverStmt = $this->conn->prepare("SELECT reservation_driver_id, reservation_driver_user_id, driver_name, created_at, updated_at, reservation_vehicle_id FROM tbl_reservation_driver WHERE reservation_reservation_id = :reservation_id");
//             $driverStmt->execute([':reservation_id' => $row['reservation_id']]);
//             while ($driverRow = $driverStmt->fetch(PDO::FETCH_ASSOC)) {
//                 $driverName = null;
//                 if (!empty($driverRow['reservation_driver_user_id'])) {
//                     // Try to get the joined name from tbl_driver
//                     $nameStmt = $this->conn->prepare("SELECT CONCAT_WS(' ', driver_first_name, driver_middle_name, driver_last_name) AS full_name FROM tbl_driver WHERE driver_id = :driver_id");
//                     $nameStmt->execute([':driver_id' => $driverRow['reservation_driver_user_id']]);
//                     $nameResult = $nameStmt->fetch(PDO::FETCH_ASSOC);
//                     $driverName = $nameResult && !empty($nameResult['full_name']) ? $nameResult['full_name'] : null;
//                 }
//                 if (empty($driverName)) {
//                     $driverName = $driverRow['driver_name'];
//                 }

//                 // Fetch assigned vehicle for this driver (if any)
//                 $assignedVehicle = null;
//                 if (!empty($driverRow['reservation_vehicle_id'])) {
//                     $vehicleStmt = $this->conn->prepare("SELECT ve.reservation_vehicle_id, ve.reservation_vehicle_vehicle_id AS vehicle_id, v.vehicle_license, vmm.vehicle_model_name AS model FROM tbl_reservation_vehicle ve LEFT JOIN tbl_vehicle v ON ve.reservation_vehicle_vehicle_id = v.vehicle_id LEFT JOIN tbl_vehicle_model vmm ON v.vehicle_model_id = vmm.vehicle_model_id WHERE ve.reservation_vehicle_id = :reservation_vehicle_id");
//                     $vehicleStmt->execute([':reservation_vehicle_id' => $driverRow['reservation_vehicle_id']]);
//                     $vehicleRow = $vehicleStmt->fetch(PDO::FETCH_ASSOC);
//                     if ($vehicleRow) {
//                         $assignedVehicle = [
//                             'reservation_vehicle_id' => $vehicleRow['reservation_vehicle_id'],
//                             'vehicle_id' => $vehicleRow['vehicle_id'],
//                             'license' => $vehicleRow['vehicle_license'],
//                             'model' => $vehicleRow['model']
//                         ];
//                     }
//                 }

//                 $drivers[] = [
//                     'reservation_driver_id' => $driverRow['reservation_driver_id'],
//                     'driver_id' => $driverRow['reservation_driver_user_id'],
//                     'name' => $driverName,
//                     'created_at' => $driverRow['created_at'],
//                     'updated_at' => $driverRow['updated_at'],
//                     'assigned_vehicle' => $assignedVehicle
//                 ];
//             }

//             if (!empty($row['passenger_data'])) {
//                 foreach (explode(',', $row['passenger_data']) as $str) {
//                     list($pid,$pname) = explode(':', $str) + [null,null];
//                     $passengers[] = [
//                         'passenger_id' => $pid,
//                         'name'         => $pname
//                     ];
//                 }
//             }

//             $result[] = [
//                 'reservation_id'           => $row['reservation_id'],
//                 'reservation_created_at'   => $row['reservation_created_at'],
//                 'reservation_title'        => $row['reservation_title'],
//                 'reservation_description'  => $row['reservation_description'],
//                 'reservation_start_date'   => $row['reservation_start_date'],
//                 'reservation_end_date'     => $row['reservation_end_date'],
//                 'reservation_participants' => $row['reservation_participants'],
//                 'reservation_user_id'      => $row['reservation_user_id'],
//                 'user_level_id'            => $row['user_level_id'],
//                 'status_id'                => $row['status_id'],
//                 'active'                   => $row['active'],
//                 'requester_name'           => $row['requester_name'],
//                 'department_name'          => $row['department_name'],
//                 'venues'                   => $venues,
//                 'vehicles'                 => $vehicles,
//                 'equipment'                => $equipment,
//                 'drivers'                  => $drivers,
//                 'passengers'               => $passengers
//             ];
//         }

//         return json_encode([
//             'status' => 'success',
//             'data'   => $result
//         ]);
//     } catch (PDOException $e) {
//         error_log("Database error: " . $e->getMessage());
//         return json_encode([
//             'status'  => 'error',
//             'message' => 'Database error: ' . $e->getMessage()
//         ]);
//     }
// }


    

    

    // Fetch request reservation details (General)
    // public function fetchRequestReservation() {
    //     try {
    //         $sql = "
    //             SELECT 
    //                 r.reservation_id, 
    //                 r.reservation_created_at, 
    //                 r.reservation_title,
    //                 r.reservation_description,
    //                 r.reservation_start_date,
    //                 r.reservation_end_date,
    //                 r.reservation_participants,
    //                 r.reservation_user_id AS requester_id,
    //                 CONCAT(u.users_fname, ' ', u.users_mname, ' ', u.users_lname) AS requester_name,
    //                 d.departments_name,
    //                 rs.reservation_status_status_id AS status_id,
    //                 rs.reservation_active AS active,
    //                 sm.status_master_name AS reservation_status
    //             FROM 
    //                 tbl_reservation r
    //             INNER JOIN 
    //                 tbl_users u ON r.reservation_user_id = u.users_id
    //             INNER JOIN 
    //                 tbl_departments d ON u.users_department_id = d.departments_id
    //             LEFT JOIN 
    //                 tbl_reservation_status rs ON r.reservation_id = rs.reservation_reservation_id
    //             LEFT JOIN 
    //                 tbl_status_master sm ON rs.reservation_status_status_id = sm.status_master_id
    //             WHERE
    
    //                 (
    //                     (rs.reservation_status_status_id = 1 AND rs.reservation_active IN (0, 1)) OR rs.reservation_active IS NULL
    //                 )
    
    //             GROUP BY 
    //                 r.reservation_id
    //             ORDER BY 
    //                 r.reservation_created_at DESC;
    //         ";
    
    //         $stmt = $this->conn->prepare($sql);
    //         $stmt->execute();
    
    //         $reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    //         return json_encode(['status' => 'success', 'data' => $reservations]);
    
    //     } catch (PDOException $e) {
    //         return json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    //     }
    // }
    
    
    // Fetch request details by approval ID
    // public function fetchApprovalNotification($departmentId, $userLevelId) {
    //     try {
    //         if ($departmentId === null || $userLevelId === null) {
    //             return json_encode(['status' => 'error', 'message' => 'Missing department ID or user level ID']);
    //         }
    
    //         $sql = "SELECT 
    //                     notification_id, 
    //                     notification_message, 
    //                     notification_department_id, 
    //                     notification_user_level_id, 
    //                     notification_create 
    //                 FROM notification_requests 
    //                 WHERE notification_department_id = :department_id
    //                   AND notification_user_level_id = :user_level_id
    //                 ORDER BY notification_create DESC";
    
    //         $stmt = $this->conn->prepare($sql);
    //         $stmt->execute([
    //             ':department_id' => $departmentId,
    //             ':user_level_id' => $userLevelId
    //         ]);
    
    //         $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    //         return json_encode(['status' => 'success', 'data' => $notifications]);
    
    //     } catch (PDOException $e) {
    //         return json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    //     }
    // }
    

    // Fetch read status of notifications
    // public function fetchReadApprovalNotification() {
    //     try {
    //         $sql = "SELECT 
    //                 notification_read_id, 
    //                 notification_id, 
    //                 user_id, 
    //                 is_read, 
    //                 read_at 
    //             FROM notification_reads 
    //             WHERE 1";

    //         $stmt = $this->conn->prepare($sql);
    //         $stmt->execute();

    //         $notificationReads = $stmt->fetchAll(PDO::FETCH_ASSOC);
    //         return json_encode(['status' => 'success', 'data' => $notificationReads]);

    //     } catch (PDOException $e) {
    //         return json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    //     }
    // }

    // Update notification read status
    public function updateReadApprovalNotification($notificationIds, $userId) {
        try {
            if (!is_array($notificationIds)) {
                $notificationIds = [$notificationIds];
            }
            $this->conn->beginTransaction();
            $successCount = 0;
            $errors = [];

            foreach ($notificationIds as $notificationId) {
                try {
                    // First check if a read record already exists
                    $checkSql = "SELECT notification_read_id FROM notification_reads 
                                WHERE notification_id = :notification_id AND user_id = :user_id";
                    
                    $checkStmt = $this->conn->prepare($checkSql);
                    $checkStmt->bindParam(':notification_id', $notificationId, PDO::PARAM_INT);
                    $checkStmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
                    $checkStmt->execute();
                    
                    if ($checkStmt->rowCount() > 0) {
                        // Update existing record
                        $sql = "UPDATE notification_reads 
                                SET is_read = 1, read_at = NOW() 
                                WHERE notification_id = :notification_id AND user_id = :user_id";
                    } else {
                        // Insert new record
                        $sql = "INSERT INTO notification_reads (notification_id, user_id, is_read, read_at) 
                                VALUES (:notification_id, :user_id, 1, NOW())";
                    }
                    
                    $stmt = $this->conn->prepare($sql);
                    $stmt->bindParam(':notification_id', $notificationId, PDO::PARAM_INT);
                    $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
                    $stmt->execute();
                    
                    $successCount++;
                } catch (PDOException $e) {
                    $errors[] = "Error with notification ID $notificationId: " . $e->getMessage();
                }
            }

            if (count($errors) === 0) {
                $this->conn->commit();

                // Audit log (non-blocking)
                try {
                    $desc = 'Marked approval notifications as read (count: ' . (int)$successCount . ')';
                    $auditSql = "INSERT INTO audit_log (description, action, created_at, created_by) VALUES (:description, :action, NOW(), :created_by)";
                    $audit = $this->conn->prepare($auditSql);
                    $audit->execute([
                        ':description' => $desc,
                        ':action' => 'READ  NOTIFICATION',
                        ':created_by' => $userId
                    ]);
                } catch (Throwable $te) { /* ignore audit errors */ }

                return json_encode([
                    'status' => 'success',
                    'message' => 'All notifications marked as read successfully',
                    'updated_count' => $successCount
                ]);
            } else {
                $this->conn->rollBack();
                return json_encode([
                    'status' => 'error',
                    'message' => 'Some notifications could not be marked as read',
                    'errors' => $errors,
                    'updated_count' => $successCount
                ]);
            }

        } catch (PDOException $e) {
            $this->conn->rollBack();
            return json_encode([
                'status' => 'error',
                'message' => $e->getMessage()
            ]);
        }
    }

    // Fetch request details by approval ID
    public function fetchRequestById($reservationId) {
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

                latest_status.reservation_status_status_id AS status_id,
                sm.status_master_name AS status_name,
                latest_status.reservation_active AS active,

                ul.user_level_name,
                dep.departments_name,
                CONCAT(u_req.users_fname, ' ', u_req.users_mname, ' ', u_req.users_lname) AS requester_name,
                dep.departments_name AS department_name,

                -- Venue details
                GROUP_CONCAT(DISTINCT 
                    CONCAT(
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

                -- Driver details
                d.reservation_driver_user_id as driver_id,
                d.reservation_driver_id,
                drv.driver_first_name,
                drv.driver_middle_name,
                drv.driver_last_name,
         

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

            LEFT JOIN tbl_reservation_driver d ON r.reservation_id = d.reservation_reservation_id
            LEFT JOIN tbl_driver drv ON d.reservation_driver_user_id = drv.driver_id

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
                'reservation_participants' => $row['reservation_participants'],
                'status_name' => $row['status_name'], // ✅ status name instead of status ID
                'active' => $row['active'],
                'reservation_user_id' => $row['reservation_user_id'],
                'requester_name' => $row['requester_name'],
                'department_name' => $row['department_name'],
                'user_level_name' => $row['user_level_name']
            ];

            // VENUES
            if (!empty($row['venue_data']) && $row['venue_data'] !== ':::::') {
                foreach (explode('|', $row['venue_data']) as $venueStr) {
                    $venueParts = explode(':', $venueStr);
                    if (count($venueParts) >= 5 && !empty(array_filter($venueParts))) {
                        $venues[] = [
                            'venue_id' => $venueParts[0] ?: '',
                            'venue_name' => $venueParts[1] ?: '',
                            'occupancy' => $venueParts[2] ?: '',
                            'operating_hours' => $venueParts[3] ?: '',
                            'picture' => $venueParts[4] ?: ''
                        ];
                    }
                }
            }

            // VEHICLES
            if (!empty($row['vehicle_data'])) {
                foreach (explode('|', $row['vehicle_data']) as $vehicleStr) {
                    $vehicleParts = explode(':', $vehicleStr);
                    if (count($vehicleParts) >= 3) {
                        $vehicles[] = [
                            'reservation_vehicle_id' => $vehicleParts[0],
                            'vehicle_id' => $vehicleParts[0],
                            'license' => $vehicleParts[1],
                            'model' => $vehicleParts[2]
                        ];
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

            // DRIVERS
            // Instead of using only the joined row, fetch all drivers for this reservation and their assigned vehicles
            $drivers = [];
            $driverStmt = $this->conn->prepare("SELECT reservation_driver_id, reservation_driver_user_id, driver_name, created_at, updated_at, reservation_vehicle_id FROM tbl_reservation_driver WHERE reservation_reservation_id = :reservation_id");
            $driverStmt->execute([':reservation_id' => $row['reservation_id']]);
            while ($driverRow = $driverStmt->fetch(PDO::FETCH_ASSOC)) {
                $driverName = null;
                if (!empty($driverRow['reservation_driver_user_id'])) {
                    // Try to get the joined name from tbl_driver
                    $nameStmt = $this->conn->prepare("SELECT CONCAT_WS(' ', driver_first_name, driver_middle_name, driver_last_name) AS full_name FROM tbl_driver WHERE driver_id = :driver_id");
                    $nameStmt->execute([':driver_id' => $driverRow['reservation_driver_user_id']]);
                    $nameResult = $nameStmt->fetch(PDO::FETCH_ASSOC);
                    $driverName = $nameResult && !empty($nameResult['full_name']) ? $nameResult['full_name'] : null;
                }
                if (empty($driverName)) {
                    $driverName = $driverRow['driver_name'];
                }

                // Fetch assigned vehicle for this driver (if any)
                $assignedVehicle = null;
                if (!empty($driverRow['reservation_vehicle_id'])) {
                    $vehicleStmt = $this->conn->prepare("SELECT ve.reservation_vehicle_id, ve.reservation_vehicle_vehicle_id AS vehicle_id, v.vehicle_license, vmm.vehicle_model_name AS model FROM tbl_reservation_vehicle ve LEFT JOIN tbl_vehicle v ON ve.reservation_vehicle_vehicle_id = v.vehicle_id LEFT JOIN tbl_vehicle_model vmm ON v.vehicle_model_id = vmm.vehicle_model_id WHERE ve.reservation_vehicle_id = :reservation_vehicle_id");
                    $vehicleStmt->execute([':reservation_vehicle_id' => $driverRow['reservation_vehicle_id']]);
                    $vehicleRow = $vehicleStmt->fetch(PDO::FETCH_ASSOC);
                    if ($vehicleRow) {
                        $assignedVehicle = [
                            'reservation_vehicle_id' => $vehicleRow['reservation_vehicle_id'],
                            'vehicle_id' => $vehicleRow['vehicle_id'],
                            'license' => $vehicleRow['vehicle_license'],
                            'model' => $vehicleRow['model']
                        ];
                    }
                }

                $drivers[] = [
                    'reservation_driver_id' => $driverRow['reservation_driver_id'],
                    'driver_id' => $driverRow['reservation_driver_user_id'],
                    'name' => $driverName,
                    'created_at' => $driverRow['created_at'],
                    'updated_at' => $driverRow['updated_at'],
                    'assigned_vehicle' => $assignedVehicle
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


    
       
public function handleApproval($reservationId, $isAccepted, $userId, $notificationMessage = '', $notification_user_id = null) {
    try {
        $this->conn->beginTransaction();

        // 1. First get the user's department_id
        $userSql = "SELECT users_department_id FROM tbl_users WHERE users_id = :user_id";
        $userStmt = $this->conn->prepare($userSql);
        $userStmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $userStmt->execute();
        $user = $userStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user || !isset($user['users_department_id'])) {
            throw new Exception("User department not found");
        }

        $departmentId = $user['users_department_id'];

        // 2. Update only the specific department approval record
        $sql = "UPDATE tbl_department_approval 
                SET department_is_approved = :approval_status,
                    department_user_id = :user_id,
                    department_updated_at = NOW()
                WHERE department_request_reservation_id = :reservation_id
                AND department_approval_department_id = :department_id";
        
        $stmt = $this->conn->prepare($sql);
        $approval_status = $isAccepted ? 1 : -1;
        $stmt->bindParam(':approval_status', $approval_status, PDO::PARAM_INT);
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindParam(':reservation_id', $reservationId, PDO::PARAM_INT);
        $stmt->bindParam(':department_id', $departmentId, PDO::PARAM_INT);
        $stmt->execute();

        // Check if any rows were affected
        if ($stmt->rowCount() === 0) {
            throw new Exception("No matching department approval record found");
        }

        // Set notification message based on approval status
        $defaultMessage = $isAccepted ? 'Request Approved by Department' : 'Request Declined by Department';
        $notificationMessage = !empty($notificationMessage) ? $notificationMessage : $defaultMessage;
        
        // Insert notification with proper notification_user_id
        $sqlNotification = "INSERT INTO tbl_notification_reservation 
                         (notification_message, notification_reservation_reservation_id, notification_user_id, notification_created_at) 
                         VALUES (:message, :reservation_id, :notification_user_id, NOW())";
        
        $stmtNotification = $this->conn->prepare($sqlNotification);
        $stmtNotification->bindParam(':message', $notificationMessage, PDO::PARAM_STR);
        $stmtNotification->bindParam(':reservation_id', $reservationId, PDO::PARAM_INT);
        $notificationUserId = $notification_user_id ?? $userId;
        $stmtNotification->bindParam(':notification_user_id', $notificationUserId, PDO::PARAM_INT);
        $stmtNotification->execute();

        // Insert department notification
        $sqlDepartmentNotification = "INSERT INTO notification_requests 
                                   (notification_message, notification_department_id, notification_user_level_id, notification_create) 
                                   VALUES (:message, :department_id, 1, NOW())";
        
        $stmtDepartmentNotification = $this->conn->prepare($sqlDepartmentNotification);
        $stmtDepartmentNotification->bindParam(':message', $defaultMessage, PDO::PARAM_STR);
        $deptNotifyId = 27; // Always notify department 27
        $stmtDepartmentNotification->bindParam(':department_id', $deptNotifyId, PDO::PARAM_INT);
        $stmtDepartmentNotification->execute();
        
        $this->conn->commit();
        
        // Audit log: who approved/declined and which reservation
        try {
            // Fetch approver name
            $approverName = 'User ID ' . (string)$userId;
            try {
                $unameStmt = $this->conn->prepare("SELECT CONCAT_WS(' ', users_fname, users_mname, users_lname) AS full_name FROM tbl_users WHERE users_id = :uid");
                $unameStmt->bindParam(':uid', $userId, PDO::PARAM_INT);
                $unameStmt->execute();
                $uname = $unameStmt->fetch(PDO::FETCH_ASSOC);
                if ($uname && !empty(trim((string)$uname['full_name']))) {
                    $approverName = trim((string)$uname['full_name']);
                }
            } catch (Throwable $te) {
                error_log("Audit approver lookup failed (handleApproval): " . $te->getMessage());
            }

            // Fetch reservation title
            $resTitle = 'Reservation';
            try {
                $resStmt = $this->conn->prepare("SELECT reservation_title FROM tbl_reservation WHERE reservation_id = :rid");
                $resStmt->bindParam(':rid', $reservationId, PDO::PARAM_INT);
                $resStmt->execute();
                $res = $resStmt->fetch(PDO::FETCH_ASSOC);
                if ($res && isset($res['reservation_title']) && trim((string)$res['reservation_title']) !== '') {
                    $resTitle = (string)$res['reservation_title'];
                }
            } catch (Throwable $te) {
                error_log("Audit reservation lookup failed (handleApproval): " . $te->getMessage());
            }

            $statusText = $isAccepted ? 'approved' : 'declined';
            $desc = "Reservation '" . $resTitle . "' " . $statusText . " by " . $approverName;
            $auditSql = "INSERT INTO audit_log (description, action, created_at, created_by) VALUES (:description, :action, NOW(), :created_by)";
            $audit = $this->conn->prepare($auditSql);
            $action = $isAccepted ? 'APPROVE' : 'DECLINE';
            $audit->bindParam(':description', $desc, PDO::PARAM_STR);
            $audit->bindParam(':action', $action, PDO::PARAM_STR);
            $audit->bindValue(':created_by', $userId, PDO::PARAM_INT);
            if (!$audit->execute()) {
                error_log("Audit log insert failed (handleApproval): " . print_r($audit->errorInfo(), true));
            } else {
                try {
                    $latest = $this->conn->query("SELECT id, description, action, created_at, created_by FROM audit_log ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
                    error_log("audit_log latest (handleApproval): " . json_encode($latest));
                } catch (Throwable $te) {
                    error_log("Failed to read latest audit_log (handleApproval): " . $te->getMessage());
                }
            }
        } catch (Throwable $te) {
            error_log("Audit logging error (handleApproval): " . $te->getMessage());
        }

        // Send push notification
        $this->sendApprovalPushNotification($reservationId, $isAccepted, $notificationUserId);
        
        return json_encode([
            'status' => 'success', 
            'message' => 'Department request ' . ($isAccepted ? 'approved' : 'declined') . ' successfully',
            'reservation_id' => $reservationId,
            'department_id' => $departmentId,
            'approval_status' => $approval_status
        ]);

    } catch (Exception $e) {
        $this->conn->rollBack();
        return json_encode([
            'status' => 'error', 
            'message' => $e->getMessage()
        ]);
    }
}
    
    private function sendApprovalPushNotification($reservationId, $isAccepted, $requesterUserId) {
        try {
            // Get reservation details and requester info for the notification
            $sql = "SELECT 
                        r.reservation_title,
                        r.reservation_description,
                        r.reservation_start_date,
                        r.reservation_end_date,
                        r.reservation_user_id,
                        CONCAT(u.users_fname, ' ', u.users_mname, ' ', u.users_lname) AS requester_name,
                        u.users_department_id,
                        u.users_user_level_id,
                        d.departments_name,
                        ul.user_level_name
                    FROM tbl_reservation r
                    LEFT JOIN tbl_users u ON r.reservation_user_id = u.users_id
                    LEFT JOIN tbl_departments d ON u.users_department_id = d.departments_id
                    LEFT JOIN tbl_user_level ul ON u.users_user_level_id = ul.user_level_id
                    WHERE r.reservation_id = :reservation_id";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':reservation_id', $reservationId, PDO::PARAM_INT);
            $stmt->execute();
            $reservation = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$reservation) {
                error_log("Reservation not found for push notification: " . $reservationId);
                return;
            }
            
            // Prepare notification content
            $status = $isAccepted ? 'approved' : 'declined';
            $title = "Reservation " . ucfirst($status);
            
            // Check if this is a cancellation (status 5) or approval/decline
            $sqlStatus = "SELECT reservation_status_status_id FROM tbl_reservation_status 
                         WHERE reservation_reservation_id = :reservation_id 
                         AND reservation_active = 1 
                         ORDER BY reservation_status_id DESC LIMIT 1";
            $stmtStatus = $this->conn->prepare($sqlStatus);
            $stmtStatus->bindParam(':reservation_id', $reservationId, PDO::PARAM_INT);
            $stmtStatus->execute();
            $currentStatus = $stmtStatus->fetch(PDO::FETCH_ASSOC);
            
            if ($currentStatus && $currentStatus['reservation_status_status_id'] == 5) {
                // This is a cancellation
                $title = "Reservation Cancelled";
                $body = "Your reservation '{$reservation['reservation_title']}' has been cancelled.";
                $status = 'cancelled';
            } else {
                $body = "Your reservation '{$reservation['reservation_title']}' has been {$status}.";
            }
            
            // Additional data for the notification
            $data = [
                'reservation_id' => $reservationId,
                'status' => $status,
                'title' => $reservation['reservation_title'],
                'requester_name' => $reservation['requester_name'],
                'department_name' => $reservation['departments_name'],
                'user_level_name' => $reservation['user_level_name'],
                'start_date' => $reservation['reservation_start_date'],
                'end_date' => $reservation['reservation_end_date'],
                'type' => 'reservation_approval'
            ];
            
            // Send push notification to the requester
            $this->sendPushNotificationToUser($requesterUserId, $title, $body, $data);
            
            // Also send notification to department 27 and user level 1 users (administrators)
            $this->sendPushNotificationToAdmins($reservation, $status, $title, $body, $data);
            
        } catch (Exception $e) {
            error_log("Error sending push notifications: " . $e->getMessage());
        }
    }
    
    private function sendPushNotificationToUser($userId, $title, $body, $data) {
        try {
            // Check if user has active push subscription
            $sql = "SELECT 
                        u.users_id,
                        CONCAT(u.users_fname, ' ', u.users_mname, ' ', u.users_lname) AS full_name,
                        ps.subscription_id,
                        ps.endpoint,
                        ps.p256dh_key,
                        ps.auth_key
                    FROM tbl_users u
                    INNER JOIN tbl_push_subscriptions ps ON u.users_id = ps.user_id
                    WHERE u.users_id = :user_id 
                    AND ps.is_active = 1";

            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $stmt->execute();
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user) {
                error_log("No active push subscription found for user ID: " . $userId);
                return;
            }
            
            $pushUrl = 'http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']) . '/send-push-notification.php';
            
            // Add unique identifier to prevent notification replacement
            $uniqueData = array_merge($data, [
                'notification_id' => uniqid('user_', true),
                'timestamp' => time(),
                'recipient_type' => 'user'
            ]);
            
            $pushData = [
                'operation' => 'send',
                'user_id' => $user['users_id'],
                'title' => $title,
                'body' => $body,
                'data' => $uniqueData
            ];
            
            error_log("Sending push notification to user {$user['users_id']}: " . json_encode($pushData));
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $pushUrl);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($pushData));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Content-Length: ' . strlen(json_encode($pushData))
            ]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);
            
            error_log("Push notification response for user {$user['users_id']}: HTTP $httpCode, Response: $response");
            
            if ($error || $httpCode < 200 || $httpCode >= 300) {
                error_log("Push notification failed for user {$user['users_id']}: " . ($error ?: "HTTP $httpCode"));
            } else {
                error_log("Push notification sent successfully to user {$user['users_id']}");
            }
            
        } catch (Exception $e) {
            error_log("Error sending push notification to user: " . $e->getMessage());
        }
    }
    
    private function sendPushNotificationToAdmins($reservation, $status, $title, $body, $data) {
        try {
            // Get the requester's user level and department to determine notification targets
            $requesterUserId = $reservation['reservation_user_id'];
            $sqlRequester = "SELECT users_user_level_id, users_department_id FROM tbl_users WHERE users_id = :user_id";
            $stmtRequester = $this->conn->prepare($sqlRequester);
            $stmtRequester->bindParam(':user_id', $requesterUserId, PDO::PARAM_INT);
            $stmtRequester->execute();
            $requester = $stmtRequester->fetch(PDO::FETCH_ASSOC);
            
            if (!$requester) {
                error_log("Could not find requester information for user ID: " . $requesterUserId);
                return;
            }
            
            // Determine notification targets based on requester's user level
            $notificationTargets = [];
            
            switch ($requester['users_user_level_id']) {
                case 3: // Student
                    // Notify department heads (level 5) and secretaries (level 6) in the same department
                    $notificationTargets = [
                        ['dept_id' => $requester['users_department_id'], 'user_level_id' => 5],
                        ['dept_id' => $requester['users_department_id'], 'user_level_id' => 6]
                    ];
                    break;
                    
                case 6: // Secretary
                    // Notify department heads (level 5) in the same department
                    $notificationTargets = [
                        ['dept_id' => $requester['users_department_id'], 'user_level_id' => 5]
                    ];
                    break;
                    
                case 16: // Dean
                case 17: // Vice President
                    // Notify administrators (level 1) in department 27
                    $notificationTargets = [
                        ['dept_id' => 27, 'user_level_id' => 1]
                    ];
                    break;
                    
                default:
                    // Default case - notify department heads (level 5) in the same department
                    $notificationTargets = [
                        ['dept_id' => $requester['users_department_id'], 'user_level_id' => 5]
                    ];
                    break;
            }
            
            $allUsers = [];
            
            // Get users for each notification target
            foreach ($notificationTargets as $target) {
                $sqlUsers = "SELECT 
                                u.users_id,
                                CONCAT(u.users_fname, ' ', u.users_mname, ' ', u.users_lname) AS full_name,
                                ps.subscription_id,
                                ps.endpoint,
                                ps.p256dh_key,
                                ps.auth_key
                            FROM tbl_users u
                            INNER JOIN tbl_push_subscriptions ps ON u.users_id = ps.user_id
                            WHERE u.users_department_id = :dept_id 
                            AND u.users_user_level_id = :user_level_id
                            AND ps.is_active = 1";
                
                $stmtUsers = $this->conn->prepare($sqlUsers);
                $stmtUsers->bindParam(':dept_id', $target['dept_id'], PDO::PARAM_INT);
                $stmtUsers->bindParam(':user_level_id', $target['user_level_id'], PDO::PARAM_INT);
                $stmtUsers->execute();
                $users = $stmtUsers->fetchAll(PDO::FETCH_ASSOC);
                
                $allUsers = array_merge($allUsers, $users);
            }
            
            if (empty($allUsers)) {
                // If no users found, fetch all admins in department 27 with active push subscriptions
                $sqlAdmins = "SELECT 
                                u.users_id,
                                CONCAT(u.users_fname, ' ', u.users_mname, ' ', u.users_lname) AS full_name,
                                ps.subscription_id,
                                ps.endpoint,
                                ps.p256dh_key,
                                ps.auth_key
                            FROM tbl_users u
                            INNER JOIN tbl_push_subscriptions ps ON u.users_id = ps.user_id
                            WHERE u.users_department_id = 27 
                            AND u.users_user_level_id = 1
                            AND ps.is_active = 1";
                $stmtAdmins = $this->conn->prepare($sqlAdmins);
                $stmtAdmins->execute();
                $admins = $stmtAdmins->fetchAll(PDO::FETCH_ASSOC);
                if (empty($admins)) {
                    error_log("No admin users with push subscriptions found in department 27");
                    return;
                }
                $allUsers = $admins;
            }
            
            // Prepare admin notification message
            $adminTitle = $title;
            $adminBody = $body;
            
            if ($status === 'cancelled') {
                $adminTitle = "Reservation Cancelled";
                $adminBody = "A reservation '{$reservation['reservation_title']}' by {$reservation['requester_name']} has been cancelled.";
            } else if ($status === 'approved') {
                 // Approved by Dept Head, waiting for GSD
                $adminTitle = "New Request for GSD";
                $adminBody = "Waiting Approval For GSD";
            }
            else {
                $adminTitle = "Reservation " . ucfirst($status);
                $adminBody = "A reservation '{$reservation['reservation_title']}' by {$reservation['requester_name']} has been {$status}.";
            }
            
            // Add unique identifier to prevent notification replacement
            $adminData = array_merge($data, [
                'notification_id' => uniqid('admin_', true),
                'timestamp' => time(),
                'recipient_type' => 'admin'
            ]);
            
            $pushUrl = 'http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']) . '../send-push-notification.php';
            $successCount = 0;
            $errorCount = 0;
            
            // Send push notification to all target users
            foreach ($allUsers as $user) {
                $pushData = [
                    'operation' => 'send',
                    'user_id' => $user['users_id'],
                    'title' => $adminTitle,
                    'body' => $adminBody,
                    'data' => $adminData
                ];
                
                error_log("Sending admin push notification to user {$user['users_id']}: " . json_encode($pushData));
                
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $pushUrl);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($pushData));
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Content-Type: application/json',
                    'Content-Length: ' . strlen(json_encode($pushData))
                ]);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 10);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                
                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $error = curl_error($ch);
                curl_close($ch);
                
                error_log("Admin push notification response for user {$user['users_id']}: HTTP $httpCode, Response: $response");
                
                if ($error || $httpCode < 200 || $httpCode >= 300) {
                    error_log("Push notification failed for admin user {$user['users_id']}: " . ($error ?: "HTTP $httpCode"));
                    $errorCount++;
                } else {
                    $successCount++;
                }
            }
            
            error_log("Push notifications sent to admins: $successCount successful, $errorCount failed");
            
        } catch (Exception $e) {
            error_log("Error sending push notifications to admins: " . $e->getMessage());
        }
    }


    public function handleRequest($reservationId, $isAccepted, $userId, $notificationMessage = '', $notification_user_id = null) {
        try {
            $this->conn->beginTransaction();
    
            if ($isAccepted) {
                // For user ID 99, handle status ID 8
                if ($userId == 99) {
                    $sqlUpdate = "
                        UPDATE tbl_reservation_status 
                        SET reservation_active = 1 
                        WHERE reservation_reservation_id = :reservation_id 
                        AND reservation_status_status_id = 8";
                    
                    $stmtUpdate = $this->conn->prepare($sqlUpdate);
                    $reservation_id = $reservationId;
                    $stmtUpdate->bindParam(':reservation_id', $reservation_id, PDO::PARAM_INT);
                    $stmtUpdate->execute();
                    
                    // Insert status 6
                    $sqlInsert = "
                        INSERT INTO tbl_reservation_status 
                        (reservation_reservation_id, reservation_status_status_id, reservation_active, reservation_updated_at, reservation_users_id) 
                        VALUES (:reservation_id, 6, 1, NOW(), :user_id)";
                    
                    $stmtInsert = $this->conn->prepare($sqlInsert);
                    $stmtInsert->bindParam(':reservation_id', $reservationId, PDO::PARAM_INT);
                    $stmtInsert->bindParam(':user_id', $userId, PDO::PARAM_INT);
                    $stmtInsert->execute();
                } else {
                    // For other users, handle status ID 1 normally
                    $sqlUpdate = "
                        UPDATE tbl_reservation_status 
                        SET reservation_active = 1 
                        WHERE reservation_reservation_id = :reservation_id 
                        AND reservation_status_status_id = 1";
                    
                    $stmtUpdate = $this->conn->prepare($sqlUpdate);
                    $stmtUpdate->bindParam(':reservation_id', $reservationId, PDO::PARAM_INT);
                    $stmtUpdate->execute();
                }
            } else {
                if ($userId == 99) {
                    $sqlUpdate = "
                        UPDATE tbl_reservation_status 
                        SET reservation_active = -1 
                        WHERE reservation_reservation_id = :reservation_id AND reservation_status_status_id = 8";
                    $stmtUpdate = $this->conn->prepare($sqlUpdate);
                    $reservation_id = $reservationId; // Store in variable
                    $stmtUpdate->bindParam(':reservation_id', $reservation_id, PDO::PARAM_INT);
                    $stmtUpdate->execute();

                    // Insert declined status (2) for user 99
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
                } else {
                    $sqlUpdate = "
                        UPDATE tbl_reservation_status 
                        SET reservation_active = -1 
                        WHERE reservation_reservation_id = :reservation_id AND reservation_status_status_id = 1";
                    $stmtUpdate = $this->conn->prepare($sqlUpdate);
                    $reservation_id = $reservationId; // Store in variable
                    $stmtUpdate->bindParam(':reservation_id', $reservation_id, PDO::PARAM_INT);
                    $stmtUpdate->execute();
                    
                    // If acting user is 114 and not accepted, do NOT insert a new status row; only update active to -1
                    if ($userId != 114) {
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
                }
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
            
            // Audit log: who approved/declined and which reservation
            try {
                // Fetch approver name
                $approverName = 'User ID ' . (string)$userId;
                try {
                    $unameStmt = $this->conn->prepare("SELECT CONCAT_WS(' ', users_fname, users_mname, users_lname) AS full_name FROM tbl_users WHERE users_id = :uid");
                    $unameStmt->bindParam(':uid', $userId, PDO::PARAM_INT);
                    $unameStmt->execute();
                    $uname = $unameStmt->fetch(PDO::FETCH_ASSOC);
                    if ($uname && !empty(trim((string)$uname['full_name']))) {
                        $approverName = trim((string)$uname['full_name']);
                    }
                } catch (Throwable $te) {
                    error_log("Audit approver lookup failed (handleRequest): " . $te->getMessage());
                }

                // Fetch reservation title
                $resTitle = 'Reservation';
                try {
                    $resStmt = $this->conn->prepare("SELECT reservation_title FROM tbl_reservation WHERE reservation_id = :rid");
                    $resStmt->bindParam(':rid', $reservationId, PDO::PARAM_INT);
                    $resStmt->execute();
                    $res = $resStmt->fetch(PDO::FETCH_ASSOC);
                    if ($res && isset($res['reservation_title']) && trim((string)$res['reservation_title']) !== '') {
                        $resTitle = (string)$res['reservation_title'];
                    }
                } catch (Throwable $te) {
                    error_log("Audit reservation lookup failed (handleRequest): " . $te->getMessage());
                }

                $statusText = $isAccepted ? 'approved' : 'declined';
                $desc = "Reservation '" . $resTitle . "' " . $statusText . " by " . $approverName;
                $auditSql = "INSERT INTO audit_log (description, action, created_at, created_by) VALUES (:description, :action, NOW(), :created_by)";
                $audit = $this->conn->prepare($auditSql);
                $action = $isAccepted ? 'APPROVE' : 'DECLINE';
                $audit->bindParam(':description', $desc, PDO::PARAM_STR);
                $audit->bindParam(':action', $action, PDO::PARAM_STR);
                $audit->bindValue(':created_by', $userId, PDO::PARAM_INT);
                if (!$audit->execute()) {
                    error_log("Audit log insert failed (handleRequest): " . print_r($audit->errorInfo(), true));
                } else {
                    try {
                        $latest = $this->conn->query("SELECT id, description, action, created_at, created_by FROM audit_log ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
                        error_log("audit_log latest (handleRequest): " . json_encode($latest));
                    } catch (Throwable $te) {
                        error_log("Failed to read latest audit_log (handleRequest): " . $te->getMessage());
                    }
                }
            } catch (Throwable $te) {
                error_log("Audit logging error (handleRequest): " . $te->getMessage());
            }

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

    public function handleCancelReservation($reservationId, $userId) {
        try {
            $this->conn->beginTransaction();
            $sqlDeactivate = "
                UPDATE tbl_reservation_status 
                SET reservation_active = -1 
                WHERE reservation_reservation_id = :reservation_id 
                AND reservation_status_status_id IN (1, 3, 6)";
            
            $stmtDeactivate = $this->conn->prepare($sqlDeactivate);
            $stmtDeactivate->bindParam(':reservation_id', $reservationId, PDO::PARAM_INT);
            $stmtDeactivate->execute();

            // Fetch reservation and requester info for notification message
            $sqlInfo = "SELECT 
                            r.reservation_title,
                            CONCAT(u.users_fname, ' ', u.users_mname, ' ', u.users_lname) AS requester_name
                        FROM tbl_reservation r
                        LEFT JOIN tbl_users u ON r.reservation_user_id = u.users_id
                        WHERE r.reservation_id = :reservation_id";
            $stmtInfo = $this->conn->prepare($sqlInfo);
            $stmtInfo->bindParam(':reservation_id', $reservationId, PDO::PARAM_INT);
            $stmtInfo->execute();
            $info = $stmtInfo->fetch(PDO::FETCH_ASSOC);

            if ($info) {
                $cancelMessage = "Reservation '{$info['reservation_title']}' by {$info['requester_name']} has been cancelled.";
            } else {
                $cancelMessage = 'Reservation Cancelled';
            }

            // Then insert: Add new cancelled status (5) with active = 1
            $sqlInsert = "
                INSERT INTO tbl_reservation_status 
                (reservation_reservation_id, reservation_status_status_id, reservation_active, reservation_updated_at, reservation_users_id) 
                VALUES (:reservation_id, 5, 1, NOW(), :user_id)";
            $stmtInsert = $this->conn->prepare($sqlInsert);
            $stmtInsert->bindParam(':reservation_id', $reservationId, PDO::PARAM_INT);
            $stmtInsert->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $stmtInsert->execute();

            // Insert notification for GSD (department 27, user level 1)
            $sqlDepartmentNotification = "INSERT INTO notification_requests 
                                           (notification_message, notification_department_id, notification_user_level_id, notification_create) 
                                           VALUES (:message, 27, 1, NOW())";
            $stmtDepartmentNotification = $this->conn->prepare($sqlDepartmentNotification);
            $stmtDepartmentNotification->bindParam(':message', $cancelMessage, PDO::PARAM_STR);
            $stmtDepartmentNotification->execute();

            $this->conn->commit();
            
            // Non-blocking audit logging for reservation cancellation
            try {
                // Get canceller full name (First + Middle initial + Last)
                $sqlCanceller = "SELECT CONCAT(\n                                    users_fname,\n                                    CASE WHEN users_mname IS NOT NULL AND users_mname != '' THEN CONCAT(' ', LEFT(users_mname, 1), '.') ELSE '' END,\n                                    ' ', users_lname\n                                 ) AS full_name\n                               FROM tbl_users WHERE users_id = :uid";
                $stmtCanceller = $this->conn->prepare($sqlCanceller);
                $stmtCanceller->bindParam(':uid', $userId, PDO::PARAM_INT);
                $stmtCanceller->execute();
                $canceller = $stmtCanceller->fetch(PDO::FETCH_ASSOC);
                $cancellerName = $canceller['full_name'] ?? ('User #' . (int)$userId);

                $reservationTitle = $info['reservation_title'] ?? 'Reservation';
                $description = "Reservation (" . $reservationTitle . ") was cancelled by: " . $cancellerName;
                $action = 'UPDATE STATUS';

                $stmtAudit = $this->conn->prepare("INSERT INTO audit_log (description, action, created_at, created_by) VALUES (:description, :action, NOW(), :created_by)");
                $stmtAudit->bindParam(':description', $description, PDO::PARAM_STR);
                $stmtAudit->bindParam(':action', $action, PDO::PARAM_STR);
                $stmtAudit->bindParam(':created_by', $userId, PDO::PARAM_INT);
                $stmtAudit->execute();
            } catch (Exception $e) { /* ignore audit logging errors */ }

            // Send push notification to the requester after successful cancellation
            $this->sendApprovalPushNotification($reservationId, false, $userId);

            // Send push notification to GSD admins (department 27, user level 1)
            $this->sendPushNotificationToAdminsAfterCancel($reservationId);

            return json_encode([
                'status' => 'success', 
                'message' => 'Reservation cancelled successfully',
                'reservation_id' => $reservationId
            ]);

        } catch (PDOException $e) {
            $this->conn->rollBack();
            return json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }

    // Send push notification to GSD admins after cancellation
    private function sendPushNotificationToAdminsAfterCancel($reservationId) {
        try {
            // Get reservation and requester info
            $sql = "SELECT 
                        r.reservation_title,
                        r.reservation_description,
                        r.reservation_start_date,
                        r.reservation_end_date,
                        r.reservation_user_id,
                        CONCAT(u.users_fname, ' ', u.users_mname, ' ', u.users_lname) AS requester_name
                    FROM tbl_reservation r
                    LEFT JOIN tbl_users u ON r.reservation_user_id = u.users_id
                    WHERE r.reservation_id = :reservation_id";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':reservation_id', $reservationId, PDO::PARAM_INT);
            $stmt->execute();
            $reservation = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$reservation) return;

            // Fetch all GSD admins (department 27, user level 1) with active push subscriptions
            $sqlAdmins = "SELECT 
                            u.users_id,
                            CONCAT(u.users_fname, ' ', u.users_mname, ' ', u.users_lname) AS full_name,
                            ps.subscription_id,
                            ps.endpoint,
                            ps.p256dh_key,
                            ps.auth_key
                        FROM tbl_users u
                        INNER JOIN tbl_push_subscriptions ps ON u.users_id = ps.user_id
                        WHERE u.users_department_id = 27 
                        AND u.users_user_level_id = 1
                        AND ps.is_active = 1";
            $stmtAdmins = $this->conn->prepare($sqlAdmins);
            $stmtAdmins->execute();
            $admins = $stmtAdmins->fetchAll(PDO::FETCH_ASSOC);
            if (empty($admins)) return;

            $title = 'Reservation Cancelled';
            $body = "A reservation '{$reservation['reservation_title']}' by {$reservation['requester_name']} has been cancelled.";
            $data = [
                'reservation_id' => $reservationId,
                'status' => 'cancelled',
                'title' => $reservation['reservation_title'],
                'requester_name' => $reservation['requester_name'],
                'type' => 'reservation_cancelled',
                'notification_id' => uniqid('admin_cancel_', true),
                'timestamp' => time(),
                'recipient_type' => 'admin'
            ];
            $pushUrl = 'http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']) . '../send-push-notification.php';
            foreach ($admins as $user) {
                $pushData = [
                    'operation' => 'send',
                    'user_id' => $user['users_id'],
                    'title' => $title,
                    'body' => $body,
                    'data' => $data
                ];
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $pushUrl);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($pushData));
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Content-Type: application/json',
                    'Content-Length: ' . strlen(json_encode($pushData))
                ]);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 10);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_exec($ch);
                curl_close($ch);
            }
        } catch (Exception $e) {
            error_log("Error sending push notifications to GSD admins after cancel: " . $e->getMessage());
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

            // --- Query for Reserved Users (reservation_users) with latest-status and reschedule logic ---
            $userQuery = "
                SELECT DISTINCT
                    r.reservation_user_id,
                    r.reservation_id,
                    r.reservation_title,
                    r.reservation_description,
                    r.reservation_start_date,
                    r.reservation_end_date,
                    CONCAT(u.users_fname, ' ', u.users_mname, ' ', u.users_lname) AS full_name,
                    ul.user_level_name,
                    d.departments_name
                FROM tbl_reservation r
                INNER JOIN tbl_users u ON r.reservation_user_id = u.users_id
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
                LEFT JOIN tbl_user_level ul ON u.users_user_level_id = ul.user_level_id
                LEFT JOIN tbl_departments d ON u.users_department_id = d.departments_id
                WHERE (
                    latest_status.reservation_status_status_id IN (1, 6, 8, 10)
                    AND (
                        (latest_status.reservation_status_status_id = 6 AND latest_status.reservation_active = 1)
                        OR (latest_status.reservation_status_status_id IN (1, 8, 10) AND (latest_status.reservation_active IN (0, 1)))
                    )
                )
                AND (
                    (CASE
                        WHEN (
                                (latest_status.reservation_status_status_id = 10 AND latest_status.reservation_active = 1)
                                OR (
                                    latest_status.reservation_status_status_id = 6 AND latest_status.reservation_active = 1
                                    AND active_resched.max_reschedule_status_id IS NOT NULL
                                )
                             )
                             AND r.reschedule_start_date IS NOT NULL AND r.reschedule_end_date IS NOT NULL
                        THEN r.reschedule_start_date ELSE r.reservation_start_date END) <= :endDateTime
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
                        THEN r.reschedule_end_date ELSE r.reservation_end_date END) >= :startDateTime
                )
            ";
            $stmt = $this->conn->prepare($userQuery);
            $stmt->execute([
                ':startDateTime' => $startDateTime,
                ':endDateTime' => $endDateTime
            ]);
            $result['reservation_users'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // --- Unavailable Vehicles: pick effective vehicle when rescheduled, apply latest-status and reschedule dates ---
            $vehicleQuery = "
                SELECT DISTINCT
                    CASE 
                        WHEN (
                            (latest_status.reservation_status_status_id = 10 AND latest_status.reservation_active = 1)
                            OR (
                                latest_status.reservation_status_status_id = 6 AND latest_status.reservation_active = 1
                                AND active_resched.max_reschedule_status_id IS NOT NULL
                            )
                        ) AND rv.reservation_change_vehicle_id IS NOT NULL
                        THEN rv.reservation_change_vehicle_id
                        ELSE v.vehicle_id
                    END AS vehicle_id,
                    CASE 
                        WHEN (
                            (latest_status.reservation_status_status_id = 10 AND latest_status.reservation_active = 1)
                            OR (
                                latest_status.reservation_status_status_id = 6 AND latest_status.reservation_active = 1
                                AND active_resched.max_reschedule_status_id IS NOT NULL
                            )
                        ) AND rv.reservation_change_vehicle_id IS NOT NULL
                        THEN cv.vehicle_license
                        ELSE v.vehicle_license
                    END AS vehicle_license,
                    CASE 
                        WHEN (
                            (latest_status.reservation_status_status_id = 10 AND latest_status.reservation_active = 1)
                            OR (
                                latest_status.reservation_status_status_id = 6 AND latest_status.reservation_active = 1
                                AND active_resched.max_reschedule_status_id IS NOT NULL
                            )
                        ) AND rv.reservation_change_vehicle_id IS NOT NULL
                        THEN cvmk.vehicle_make_name
                        ELSE vm.vehicle_make_name
                    END AS vehicle_make_name,
                    CASE 
                        WHEN (
                            (latest_status.reservation_status_status_id = 10 AND latest_status.reservation_active = 1)
                            OR (
                                latest_status.reservation_status_status_id = 6 AND latest_status.reservation_active = 1
                                AND active_resched.max_reschedule_status_id IS NOT NULL
                            )
                        ) AND rv.reservation_change_vehicle_id IS NOT NULL
                        THEN cvmd.vehicle_model_name
                        ELSE vmd.vehicle_model_name
                    END AS vehicle_model_name
                FROM tbl_reservation r
                INNER JOIN tbl_reservation_vehicle rv ON r.reservation_id = rv.reservation_reservation_id
                INNER JOIN tbl_vehicle v ON rv.reservation_vehicle_vehicle_id = v.vehicle_id
                INNER JOIN tbl_vehicle_model vmd ON v.vehicle_model_id = vmd.vehicle_model_id
                INNER JOIN tbl_vehicle_make vm ON vmd.vehicle_model_vehicle_make_id = vm.vehicle_make_id
                LEFT JOIN tbl_vehicle cv ON rv.reservation_change_vehicle_id = cv.vehicle_id
                LEFT JOIN tbl_vehicle_model cvmd ON cv.vehicle_model_id = cvmd.vehicle_model_id
                LEFT JOIN tbl_vehicle_make cvmk ON cvmd.vehicle_model_vehicle_make_id = cvmk.vehicle_make_id
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
                WHERE r.reservation_id NOT IN (
                    SELECT DISTINCT reservation_reservation_id 
                    FROM tbl_reservation_status 
                    WHERE reservation_status_status_id IN (2, 5)
                )
                AND (
                    latest_status.reservation_status_status_id IN (1, 6, 8, 10)
                    AND (
                        (latest_status.reservation_status_status_id = 6 AND latest_status.reservation_active = 1)
                        OR (latest_status.reservation_status_status_id IN (1, 8, 10) AND (latest_status.reservation_active IN (0, 1)))
                    )
                )
                AND (
                    (CASE
                        WHEN (
                                (latest_status.reservation_status_status_id = 10 AND latest_status.reservation_active = 1)
                                OR (
                                    latest_status.reservation_status_status_id = 6 AND latest_status.reservation_active = 1
                                    AND active_resched.max_reschedule_status_id IS NOT NULL
                                )
                             )
                             AND r.reschedule_start_date IS NOT NULL AND r.reschedule_end_date IS NOT NULL
                        THEN r.reschedule_start_date ELSE r.reservation_start_date END) <= :endDateTime
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
                        THEN r.reschedule_end_date ELSE r.reservation_end_date END) >= :startDateTime
                )
            ";
            $stmt = $this->conn->prepare($vehicleQuery);
            $stmt->execute([
                ':startDateTime' => $startDateTime,
                ':endDateTime' => $endDateTime
            ]);
            $result['unavailable_vehicles'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // --- Unavailable Venues: pick effective venue when rescheduled, apply latest-status and reschedule dates ---
            $venueQuery = "
                SELECT DISTINCT
                    CASE 
                        WHEN (
                            (latest_status.reservation_status_status_id = 10 AND latest_status.reservation_active = 1)
                            OR (
                                latest_status.reservation_status_status_id = 6 AND latest_status.reservation_active = 1
                                AND active_resched.max_reschedule_status_id IS NOT NULL
                            )
                        ) AND rv.reservation_change_venue_id IS NOT NULL
                        THEN rv.reservation_change_venue_id
                        ELSE v.ven_id
                    END AS ven_id,
                    CASE 
                        WHEN (
                            (latest_status.reservation_status_status_id = 10 AND latest_status.reservation_active = 1)
                            OR (
                                latest_status.reservation_status_status_id = 6 AND latest_status.reservation_active = 1
                                AND active_resched.max_reschedule_status_id IS NOT NULL
                            )
                        ) AND rv.reservation_change_venue_id IS NOT NULL
                        THEN change_venue.ven_name
                        ELSE v.ven_name
                    END AS ven_name
                FROM tbl_reservation r
                INNER JOIN tbl_reservation_venue rv ON r.reservation_id = rv.reservation_reservation_id
                INNER JOIN tbl_venue v ON rv.reservation_venue_venue_id = v.ven_id
                LEFT JOIN tbl_venue change_venue ON rv.reservation_change_venue_id = change_venue.ven_id
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
                WHERE r.reservation_id NOT IN (
                    SELECT DISTINCT reservation_reservation_id 
                    FROM tbl_reservation_status 
                    WHERE reservation_status_status_id IN (2, 5)
                )
                AND (
                    latest_status.reservation_status_status_id IN (1, 6, 8, 10)
                    AND (
                        (latest_status.reservation_status_status_id = 6 AND latest_status.reservation_active = 1)
                        OR (latest_status.reservation_status_status_id IN (1, 8, 10) AND (latest_status.reservation_active IN (0, 1)))
                    )
                )
                AND (
                    (CASE
                        WHEN (
                                (latest_status.reservation_status_status_id = 10 AND latest_status.reservation_active = 1)
                                OR (
                                    latest_status.reservation_status_status_id = 6 AND latest_status.reservation_active = 1
                                    AND active_resched.max_reschedule_status_id IS NOT NULL
                                )
                             )
                             AND r.reschedule_start_date IS NOT NULL AND r.reschedule_end_date IS NOT NULL
                        THEN r.reschedule_start_date ELSE r.reservation_start_date END) <= :endDateTime
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
                        THEN r.reschedule_end_date ELSE r.reservation_end_date END) >= :startDateTime
                )
            ";
            $stmt = $this->conn->prepare($venueQuery);
            $stmt->execute([
                ':startDateTime' => $startDateTime,
                ':endDateTime' => $endDateTime
            ]);
            $result['unavailable_venues'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // --- Unavailable Equipment: block when reserved qty >= total qty with latest-status & reschedule-aware overlap ---
            $equipmentQuery = "
                WITH EquipmentTotalQuantities AS (
                    SELECT
                        e.equip_id,
                        CASE
                            WHEN EXISTS (SELECT 1 FROM tbl_equipment_unit u WHERE u.equip_id = e.equip_id) THEN (
                                SELECT COUNT(*) FROM tbl_equipment_unit u_inner WHERE u_inner.equip_id = e.equip_id
                            )
                            ELSE (
                                SELECT eq.quantity FROM tbl_equipment_quantity eq WHERE eq.equip_id = e.equip_id LIMIT 1
                            )
                        END AS total_quantity
                    FROM tbl_equipments e
                )
                SELECT 
                    e.equip_id,
                    e.equip_name,
                    COALESCE(etq.total_quantity, 0) AS total_quantity,
                    COALESCE(SUM(re.reservation_equipment_quantity), 0) AS reserved_quantity
                FROM tbl_equipments e
                INNER JOIN EquipmentTotalQuantities etq ON e.equip_id = etq.equip_id
                LEFT JOIN tbl_reservation_equipment re ON e.equip_id = re.reservation_equipment_equip_id
                LEFT JOIN tbl_reservation r ON r.reservation_id = re.reservation_reservation_id
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
                WHERE r.reservation_id NOT IN (
                    SELECT DISTINCT reservation_reservation_id 
                    FROM tbl_reservation_status 
                    WHERE reservation_status_status_id IN (2, 5)
                )
                AND (
                    latest_status.reservation_status_status_id IN (1, 6, 8, 10)
                    AND (
                        (latest_status.reservation_status_status_id = 6 AND latest_status.reservation_active = 1)
                        OR (latest_status.reservation_status_status_id IN (1, 8, 10) AND (latest_status.reservation_active IN (0, 1)))
                    )
                )
                AND (
                    (CASE
                        WHEN (
                                (latest_status.reservation_status_status_id = 10 AND latest_status.reservation_active = 1)
                                OR (
                                    latest_status.reservation_status_status_id = 6 AND latest_status.reservation_active = 1
                                    AND active_resched.max_reschedule_status_id IS NOT NULL
                                )
                             )
                             AND r.reschedule_start_date IS NOT NULL AND r.reschedule_end_date IS NOT NULL
                        THEN r.reschedule_start_date ELSE r.reservation_start_date END) <= :endDateTime
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
                        THEN r.reschedule_end_date ELSE r.reservation_end_date END) >= :startDateTime
                )
                GROUP BY e.equip_id, e.equip_name, etq.total_quantity
                HAVING COALESCE(SUM(re.reservation_equipment_quantity), 0) >= COALESCE(etq.total_quantity, 0)
            ";
            $stmt = $this->conn->prepare($equipmentQuery);
            $stmt->execute([
                ':startDateTime' => $startDateTime,
                ':endDateTime' => $endDateTime
            ]);
            $result['unavailable_equipment'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // --- Unavailable Drivers: use latest-status and reschedule-aware overlap to find reserved drivers ---
            $sqlReservedDrivers = "
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
                WHERE r.reservation_id NOT IN (
                    SELECT DISTINCT reservation_reservation_id 
                    FROM tbl_reservation_status 
                    WHERE reservation_status_status_id IN (2, 5)
                )
                AND (
                    latest_status.reservation_status_status_id IN (1, 6, 8, 10)
                    AND (
                        (latest_status.reservation_status_status_id = 6 AND latest_status.reservation_active = 1)
                        OR (latest_status.reservation_status_status_id IN (1, 8, 10) AND (latest_status.reservation_active IN (0, 1)))
                    )
                )
                AND (
                    (CASE
                        WHEN (
                                (latest_status.reservation_status_status_id = 10 AND latest_status.reservation_active = 1)
                                OR (
                                    latest_status.reservation_status_status_id = 6 AND latest_status.reservation_active = 1
                                    AND active_resched.max_reschedule_status_id IS NOT NULL
                                )
                             )
                             AND r.reschedule_start_date IS NOT NULL AND r.reschedule_end_date IS NOT NULL
                        THEN r.reschedule_start_date ELSE r.reservation_start_date END) <= :endDateTime
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
                        THEN r.reschedule_end_date ELSE r.reservation_end_date END) >= :startDateTime
                )
            ";
            $stmt = $this->conn->prepare($sqlReservedDrivers);
            $stmt->execute([
                ':startDateTime' => $startDateTime,
                ':endDateTime' => $endDateTime
            ]);
            $reservedDriverIds = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);

            if (!empty($reservedDriverIds)) {
                $placeholders = implode(',', array_fill(0, count($reservedDriverIds), '?'));
                $driverDetailsSql = "
                    SELECT u.users_id, CONCAT(u.users_fname, ' ', u.users_mname, ' ', u.users_lname) AS full_name
                    FROM tbl_users u
                    WHERE u.users_id IN ($placeholders)
                ";
                $stmt = $this->conn->prepare($driverDetailsSql);
                $stmt->execute($reservedDriverIds);
                $result['unavailable_drivers'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } else {
                $result['unavailable_drivers'] = [];
            }

            // Return the results as a JSON success response
            return json_encode(['status' => 'success', 'data' => $result]);
        } catch (PDOException $e) {
            // Catch any PDO exceptions (database errors) and return an error JSON response
            return json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }
    
    // public function updateTripTicket($reservationDriverId) {
    //     try {
    //         $sql = "UPDATE tbl_reservation_driver 
    //                 SET is_accepted_trip = 1,
    //                     updated_at = NOW()
    //                 WHERE reservation_driver_id = :reservation_driver_id
    //                 AND is_accepted_trip = 0";
            
    //         $stmt = $this->conn->prepare($sql);
    //         $stmt->bindParam(':reservation_driver_id', $reservationDriverId, PDO::PARAM_INT);
    //         $stmt->execute();

    //         if ($stmt->rowCount() > 0) {
    //             return json_encode([
    //                 'status' => 'success',
    //                 'message' => 'Trip ticket updated successfully'
    //             ]);
    //         } else {
    //             return json_encode([
    //                 'status' => 'error',
    //                 'message' => 'No changes made. Trip ticket may already be accepted or not found.'
    //             ]);
    //         }

    //     } catch (PDOException $e) {
    //         return json_encode([
    //             'status' => 'error',
    //             'message' => $e->getMessage()
    //         ]);
    //     }
    // }
    
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



}

// Handle the request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $user = new User();
    $operation = $data['operation'] ?? '';
    $userId = $data['user_id'] ?? 24; 

    switch ($operation) {        
        // case "fetchApprovalByDept":
        //     // Extract parameters from the JSON payload
        //     $departmentId    = $data['json']['department_id']    ?? null;
        //     $userLevelId     = $data['json']['user_level_id']     ?? null;
        //     $currentUserId   = $data['json']['current_user_id']   ?? null;

        //     // Validate presence
        //     if ($departmentId === null || $userLevelId === null || $currentUserId === null) {
        //         echo json_encode([
        //             'status'  => 'error',
        //             'message' => 'Department ID, User Level ID, and Current User ID are required'
        //         ]);
        //         break;
        //     }

        //     // Call your service method
        //     echo $user->fetchApprovalByDept(
        //         (int)$departmentId,
        //         (int)$userLevelId,
        //         (int)$currentUserId
        //     );
        //     break;

        case "handleCancelReservation":
            $reservationId = $data['reservation_id'] ?? null;
            if ($reservationId === null) {
                echo json_encode(['status' => 'error', 'message' => 'Reservation ID is required']);
                break;
            }
            echo $user->handleCancelReservation($reservationId, $userId);
            break;

        // case "fetchRequestReservation":
        //     echo $user->fetchRequestReservation(); 
        //     break;

        case "fetchRequestById":
            $reservationId = $data['reservation_id'] ?? null;
            if ($reservationId === null) {
                echo json_encode(['status' => 'error', 'message' => 'Reservation ID is required']);
                break;
            }
            echo $user->fetchRequestById($reservationId); 
            break;

        case "handleApproval":
                $reservationId = $data['reservation_id'] ?? null;
                $isAccepted = $data['is_accepted'] ?? false;
                $notificationMessage = $data['notification_message'] ?? '';
                $notificationUserId = $data['notification_user_id'] ?? null;
                if ($reservationId === null) {
                    echo json_encode(['status' => 'error', 'message' => 'Reservation ID is required']);
                    break;
                }
                echo $user->handleApproval($reservationId, $isAccepted, $userId, $notificationMessage, $notificationUserId);
                break;

        case "declineRequest":
            $approvalId = $data['approval_id'] ?? null;
            if ($approvalId === null) {
                echo json_encode(['status' => 'error', 'message' => 'Approval ID is required']);
                break;
            }
            echo $user->declineRequest($approvalId); 
            break;

        case "handleRequest":
            $reservationId = $data['reservation_id'] ?? null;
            $isAccepted = $data['is_accepted'] ?? false;
            $notificationMessage = $data['notification_message'] ?? '';
            $notificationUserId = $data['notification_user_id'] ?? null;
            if ($reservationId === null) {
                echo json_encode(['status' => 'error', 'message' => 'Reservation ID is required']);
                break;
            }
            echo $user->handleRequest($reservationId, $isAccepted, $userId, $notificationMessage, $notificationUserId);
            break;

        case "updateReadApprovalNotification":
            $notificationIds = $data['notificationIds'] ?? ($data['notification_ids'] ?? null);
            if ($notificationIds === null) {
                echo json_encode(['status' => 'error', 'message' => 'Notification IDs are required']);
                break;
            }
            echo $user->updateReadApprovalNotification($notificationIds, $userId);
            break;

        case "doubleCheckAvailability":
            $startDateTime = $data['start_datetime'] ?? null;
            $endDateTime = $data['end_datetime'] ?? null;
            if ($startDateTime === null || $endDateTime === null) {
                echo json_encode(['status' => 'error', 'message' => 'Start and end datetime are required']);
                break;
            }
            echo $user->doubleCheckAvailability($startDateTime, $endDateTime);
            break;
        case "updateTripTicket":
            $reservationDriverId = $data['reservation_driver_id'] ?? null;
            if ($reservationDriverId === null) {
                echo json_encode(['status' => 'error', 'message' => 'Reservation Driver ID is required']);
                break;
            }
            echo $user->updateTripTicket($reservationDriverId);
            break;

            case "fetchApprovalNotification":
                $departmentId = $data['department_id'] ?? null;
                $userLevelId = $data['user_level_id'] ?? null;
                if ($departmentId === null || $userLevelId === null) {
                    echo json_encode(['status' => 'error', 'message' => 'Department ID and User Level ID are required']);
                    break;
                }
                echo $user->fetchApprovalNotification($departmentId, $userLevelId);
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

            echo $user->insertUnits($equipIds, $quantities, $reservationId, $startDate, $endDate);
            break;
        case "updateReadApprovalNotification":
            $notificationIds = $data['notification_ids'] ?? [];
            $userId = $data['user_id'] ?? null;
            if (empty($notificationIds) || $userId === null) {
                echo json_encode(['status' => 'error', 'message' => 'Notification IDs and user ID are required']);
                break;
            }
            echo $user->updateReadApprovalNotification($notificationIds, $userId);
            break;
            
        // case "fetchReadApprovalNotification":
        //     echo $user->fetchReadApprovalNotification();
        //     break;

        default:
            echo json_encode(['status' => 'error', 'message' => 'Invalid operation']);
            break;
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
}