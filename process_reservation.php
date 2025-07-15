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
            $sql = "UPDATE tbl_reservation_status 
                    SET reservation_active = 1
                    WHERE reservation_reservation_id = :reservation_id 
                    AND reservation_active IS NULL";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':reservation_id', $reservationId, PDO::PARAM_INT);
            $stmt->execute();
            
            $newStatusId = $isAccepted ? 3 : 2; 
            $sql = "INSERT INTO tbl_reservation_status 
                    (reservation_reservation_id, reservation_status_status_id, reservation_active, reservation_updated_at, reservation_users_id) 
                    VALUES (:reservation_id, :status_id, 1, NOW(), :user_id)";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':reservation_id', $reservationId, PDO::PARAM_INT);
            $stmt->bindParam(':status_id', $newStatusId, PDO::PARAM_INT);
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $stmt->execute();
            
            // Set notification message based on approval status
            $defaultMessage = $isAccepted ? 'New Request' : 'Request Declined';
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

              $defaultMessage = $isAccepted ? 'New Request' : 'Request Declined';

            // Insert notification into notification_requests table
            $sqlDepartmentNotification = "INSERT INTO notification_requests 
                                       (notification_message, notification_department_id, notification_user_level_id, notification_create) 
                                       VALUES (:message, 27, 1, NOW())";
            
            $stmtDepartmentNotification = $this->conn->prepare($sqlDepartmentNotification);
            $stmtDepartmentNotification->bindParam(':message', $defaultMessage, PDO::PARAM_STR);
            $stmtDepartmentNotification->execute();
            
            $this->conn->commit();
            
            // Send push notification to the requester after successful database operations
            $this->sendApprovalPushNotification($reservationId, $isAccepted, $notificationUserId);
            
            return json_encode([
                'status' => 'success', 
                'message' => 'Request ' . ($isAccepted ? 'approved' : 'declined') . ' successfully',
                'reservation_id' => $reservationId,
                'new_status_id' => $newStatusId
            ]);
    
        } catch (PDOException $e) {
            $this->conn->rollBack();
            return json_encode(['status' => 'error', 'message' => $e->getMessage()]);
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
                error_log("No users with push subscriptions found for notification targets");
                return;
            }
            
            // Prepare admin notification message
            $adminTitle = $title;
            $adminBody = $body;
            
            if ($status === 'cancelled') {
                $adminTitle = "Reservation Cancelled";
                $adminBody = "A reservation '{$reservation['reservation_title']}' by {$reservation['requester_name']} has been cancelled.";
            } else {
                $adminTitle = "Reservation " . ucfirst($status);
                $adminBody = "A reservation '{$reservation['reservation_title']}' by {$reservation['requester_name']} has been {$status}.";
            }
            
            // Add unique identifier to prevent notification replacement
            $adminData = array_merge($data, [
                'notification_id' => uniqid('admin_', true),
                'timestamp' => time(),
                'recipient_type' => 'admin'
            ]);
            
            $pushUrl = 'http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']) . '/send-push-notification.php';
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
                $sqlDeactivate = "
                    UPDATE tbl_reservation_status 
                    SET reservation_active = -1 
                    WHERE reservation_reservation_id = :reservation_id AND reservation_status_status_id = 1";
                
                $stmtDeactivate = $this->conn->prepare($sqlDeactivate);
                $reservation_id = $reservationId; // Store in variable
                $stmtDeactivate->bindParam(':reservation_id', $reservation_id, PDO::PARAM_INT);
                $stmtDeactivate->execute();

                // Then insert: Add new completed status (6)
                $sqlInsert = "
                    INSERT INTO tbl_reservation_status 
                    (reservation_reservation_id, reservation_status_status_id, reservation_active, reservation_updated_at, reservation_users_id) 
                    VALUES (:reservation_id, 6, 1, NOW(), :user_id)";
                
                $stmtInsert = $this->conn->prepare($sqlInsert);
                $reservation_id = $reservationId; // Store in variable
                $user_id = $userId; // Store in variable
                $stmtInsert->bindParam(':reservation_id', $reservation_id, PDO::PARAM_INT);
                $stmtInsert->bindParam(':user_id', $user_id, PDO::PARAM_INT);
                $stmtInsert->execute();
            } else {
                $sqlDeactivate = "
                    UPDATE tbl_reservation_status 
                    SET reservation_active = -1 
                    WHERE reservation_reservation_id = :reservation_id AND reservation_status_status_id = 1";
                $stmtDeactivate = $this->conn->prepare($sqlDeactivate);
                $reservation_id = $reservationId; // Store in variable
                $stmtDeactivate->bindParam(':reservation_id', $reservation_id, PDO::PARAM_INT);
                $stmtDeactivate->execute();
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
            
            // Send push notification to the requester after successful database operations
            $notificationUserId = $notification_user_id ?? $userId;
            $this->sendApprovalPushNotification($reservationId, $isAccepted, $notificationUserId);

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

            // Then insert: Add new cancelled status (5) with active = 1
            $sqlInsert = "
                INSERT INTO tbl_reservation_status 
                (reservation_reservation_id, reservation_status_status_id, reservation_active, reservation_updated_at, reservation_users_id) 
                VALUES (:reservation_id, 5, 1, NOW(), :user_id)";
            
            $stmtInsert = $this->conn->prepare($sqlInsert);
            $stmtInsert->bindParam(':reservation_id', $reservationId, PDO::PARAM_INT);
            $stmtInsert->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $stmtInsert->execute();

            $this->conn->commit();
            
            // Send push notification to the requester after successful cancellation
            $this->sendApprovalPushNotification($reservationId, false, $userId);

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

            // --- Query for Reserved Users (reservation_users) ---
            // This query fetches details of users who have reservations that overlap with the given date range
            // and are in 'Approved' status (status_id = 6) and active.
            $userQuery = "
                SELECT DISTINCT
                    r.reservation_user_id,
                    r.reservation_id,
                    r.reservation_start_date,
                    r.reservation_end_date,
                    r.reservation_title,
                    r.reservation_description,
                    CONCAT(u.users_fname, ' ', u.users_mname, ' ', u.users_lname) AS full_name,
                    ul.user_level_name,
                    d.departments_name
                FROM tbl_reservation r
                INNER JOIN tbl_users u ON r.reservation_user_id = u.users_id
                INNER JOIN tbl_reservation_status rs ON r.reservation_id = rs.reservation_reservation_id
                LEFT JOIN tbl_user_level ul ON u.users_user_level_id = ul.user_level_id
                LEFT JOIN tbl_departments d ON u.users_department_id = d.departments_id
                WHERE r.reservation_start_date <= :endDate
                AND r.reservation_end_date >= :startDate
                AND rs.reservation_status_status_id = 6 -- Assuming 6 is the 'Approved' status ID
                AND rs.reservation_active = 1
            ";
            $stmt = $this->conn->prepare($userQuery);
            $stmt->execute([
                ':startDate' => $startDateTime,
                ':endDate' => $endDateTime
            ]);
            $result['reservation_users'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // --- Query for Unavailable Vehicles ---
            // This query identifies vehicles that are part of approved and active reservations
            // overlapping with the given date range.
            $vehicleQuery = "
                SELECT DISTINCT
                    v.vehicle_id,
                    v.vehicle_license,
                    vm.vehicle_model_name,
                    vmake.vehicle_make_name
                FROM tbl_reservation r
                INNER JOIN tbl_reservation_status rs ON r.reservation_id = rs.reservation_reservation_id
                INNER JOIN tbl_reservation_vehicle rv ON r.reservation_id = rv.reservation_reservation_id
                INNER JOIN tbl_vehicle v ON rv.reservation_vehicle_vehicle_id = v.vehicle_id
                INNER JOIN tbl_vehicle_model vm ON v.vehicle_model_id = vm.vehicle_model_id
                INNER JOIN tbl_vehicle_make vmake ON vm.vehicle_model_vehicle_make_id = vmake.vehicle_make_id
                WHERE r.reservation_start_date <= :endDate
                AND r.reservation_end_date >= :startDate
                AND rs.reservation_status_status_id = 6
                AND rs.reservation_active = 1
            ";
            $stmt = $this->conn->prepare($vehicleQuery);
            $stmt->execute([
                ':startDate' => $startDateTime,
                ':endDate' => $endDateTime
            ]);
            $result['unavailable_vehicles'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // --- Query for Unavailable Venues ---
            // This query identifies venues that are part of approved and active reservations
            // overlapping with the given date range.
            $venueQuery = "
                SELECT DISTINCT
                    v.ven_id,
                    v.ven_name
                FROM tbl_reservation r
                INNER JOIN tbl_reservation_status rs ON r.reservation_id = rs.reservation_reservation_id
                INNER JOIN tbl_reservation_venue rv ON r.reservation_id = rv.reservation_reservation_id
                INNER JOIN tbl_venue v ON rv.reservation_venue_venue_id = v.ven_id
                WHERE r.reservation_start_date <= :endDate
                AND r.reservation_end_date >= :startDate
                AND rs.reservation_status_status_id = 6
                AND rs.reservation_active = 1
            ";
            $stmt = $this->conn->prepare($venueQuery);
            $stmt->execute([
                ':startDate' => $startDateTime,
                ':endDate' => $endDateTime
            ]);
            $result['unavailable_venues'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $equipmentQuery = "
                WITH EquipmentTotalQuantities AS (
                    SELECT
                        e.equip_id,
                        CASE
                            -- If there are any units for this equipment in tbl_equipment_unit, it's considered serialized.
                            WHEN EXISTS (SELECT 1 FROM tbl_equipment_unit u WHERE u.equip_id = e.equip_id) THEN (
                                -- For serialized equipment, count the available units.
                                SELECT COUNT(*)
                                FROM tbl_equipment_unit u_inner
                                WHERE u_inner.equip_id = e.equip_id
                            )
                            ELSE (
                                -- For non-serialized equipment, get the quantity from tbl_equipment_quantity.
                                -- LIMIT 1 is used here assuming tbl_equipment_quantity holds a single current quantity per equip_id.
                                -- If it's a history, you might need to order by 'last_updated' and take the latest.
                                SELECT eq.quantity
                                FROM tbl_equipment_quantity eq
                                WHERE eq.equip_id = e.equip_id
                                LIMIT 1
                            )
                        END AS total_quantity
                    FROM tbl_equipments e
                )
                SELECT DISTINCT
                    e.equip_id,
                    e.equip_name,
                    COALESCE(etq.total_quantity, 0) AS total_quantity, -- Ensure total_quantity is not null
                    COALESCE(SUM(re.reservation_equipment_quantity), 0) AS reserved_quantity
                FROM tbl_equipments e
                INNER JOIN EquipmentTotalQuantities etq ON e.equip_id = etq.equip_id -- Join with the calculated total quantities
                LEFT JOIN tbl_reservation_equipment re ON e.equip_id = re.reservation_equipment_equip_id
                LEFT JOIN tbl_reservation r ON r.reservation_id = re.reservation_reservation_id
                LEFT JOIN tbl_reservation_status rs ON rs.reservation_reservation_id = r.reservation_id
                WHERE
                    (r.reservation_start_date <= :endDate AND r.reservation_end_date >= :startDate)
                    AND rs.reservation_status_status_id = 6
                    AND rs.reservation_active = 1
                GROUP BY e.equip_id, e.equip_name, etq.total_quantity
                HAVING COALESCE(SUM(re.reservation_equipment_quantity), 0) >= COALESCE(etq.total_quantity, 0);

            ";
            $stmt = $this->conn->prepare($equipmentQuery);
            $stmt->execute([
                ':startDate' => $startDateTime,
                ':endDate' => $endDateTime
            ]);
            $result['unavailable_equipment'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // --- Query for Unavailable Drivers ---
            // This query identifies drivers who are assigned to approved and active reservations
            // overlapping with the given date range.
            $driverQuery = "
                SELECT DISTINCT
                    u.users_id,
                    CONCAT(u.users_fname, ' ', u.users_mname, ' ', u.users_lname) AS full_name
                FROM tbl_reservation r
                INNER JOIN tbl_reservation_status rs ON r.reservation_id = rs.reservation_reservation_id
                INNER JOIN tbl_reservation_driver rd ON r.reservation_id = rd.reservation_reservation_id
                INNER JOIN tbl_users u ON rd.reservation_driver_user_id = u.users_id
                WHERE r.reservation_start_date <= :endDate
                AND r.reservation_end_date >= :startDate
                AND rs.reservation_status_status_id = 6
                AND rs.reservation_active = 1
            ";
            $stmt = $this->conn->prepare($driverQuery);
            $stmt->execute([
                ':startDate' => $startDateTime,
                ':endDate' => $endDateTime
            ]);
            $result['unavailable_drivers'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Return the results as a JSON success response
            return json_encode(['status' => 'success', 'data' => $result]);
        } catch (PDOException $e) {
            // Catch any PDO exceptions (database errors) and return an error JSON response
            return json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }
    
    public function updateTripTicket($reservationDriverId) {
        try {
            $sql = "UPDATE tbl_reservation_driver 
                    SET is_accepted_trip = 1,
                        updated_at = NOW()
                    WHERE reservation_driver_id = :reservation_driver_id
                    AND is_accepted_trip = 0";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':reservation_driver_id', $reservationDriverId, PDO::PARAM_INT);
            $stmt->execute();

            if ($stmt->rowCount() > 0) {
                return json_encode([
                    'status' => 'success',
                    'message' => 'Trip ticket updated successfully'
                ]);
            } else {
                return json_encode([
                    'status' => 'error',
                    'message' => 'No changes made. Trip ticket may already be accepted or not found.'
                ]);
            }

        } catch (PDOException $e) {
            return json_encode([
                'status' => 'error',
                'message' => $e->getMessage()
            ]);
        }
    }
    
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

            if ($equipType === 'consumable') {
                // Check available quantity (but don't deduct)
                $stmtQty = $this->conn->prepare("SELECT quantity FROM tbl_equipment_quantity WHERE equip_id = :equip_id");
                $stmtQty->execute([':equip_id' => $equipId]);
                $qtyData = $stmtQty->fetch(PDO::FETCH_ASSOC);
                $availableQty = $qtyData ? (int)$qtyData['quantity'] : 0;

                if ($availableQty < $quantity) {
                    throw new Exception("Not enough quantity for consumable equipment ID $equipId. Only $availableQty available.");
                }

                // Commented out quantity deduction for consumable equipment
                // $stmtUpdateQty = $this->conn->prepare("UPDATE tbl_equipment_quantity SET quantity = quantity - :qty WHERE equip_id = :equip_id");
                // $stmtUpdateQty->execute([
                //     ':qty' => $quantity,
                //     ':equip_id' => $equipId
                // ]);

                $results[] = [
                    'equip_id' => $equipId,
                    'reservation_equipment_id' => $reservationEquipmentId,
                    'type' => 'consumable',
                    'quantity_used' => $quantity,
                    'can_release' => true
                ];
            } else {
                // Non-consumable logic
                $sqlUnits = "
                    SELECT eu.unit_id, eu.serial_number 
                    FROM tbl_equipment_unit eu
                    WHERE eu.equip_id = :equip_id 
                        AND eu.status_availability_id = 1
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
                        'type' => 'non-consumable',
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
                    'type' => 'non-consumable',
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

            // case "fetchApprovalNotification":
            //     $departmentId = $data['department_id'] ?? null;
            //     $userLevelId = $data['user_level_id'] ?? null;
            //     if ($departmentId === null || $userLevelId === null) {
            //         echo json_encode(['status' => 'error', 'message' => 'Department ID and User Level ID are required']);
            //         break;
            //     }
            //     echo $user->fetchApprovalNotification($departmentId, $userLevelId);
            //     break;
            
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