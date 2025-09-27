<?php 

header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS, GET");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

class FacultyStaff {
    private $conn;

    public function __construct() {
        include 'connection-pdo.php'; 
        $this->conn = $conn;
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

    public function fetchMyReservation($userId) {
    try {
        $query = "
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

            LEFT JOIN tbl_status_master sm 
                ON sm.status_master_id = rs_filtered.reservation_status_status_id

            WHERE r.reservation_user_id = :userId
            ORDER BY r.reservation_id DESC
        ";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
        $stmt->execute();

        $reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Process each reservation to apply reschedule date logic
        foreach ($reservations as &$reservation) {
            $statusId = $reservation['reservation_status_status_id'];
            $active = $reservation['reservation_active'];
            
            // Only show reschedule dates when status is 14 and active is 1
            // Otherwise, set reschedule dates to null
            if (!($statusId == 14 && $active == 1)) {
                $reservation['reschedule_start_date'] = null;
                $reservation['reschedule_end_date'] = null;
            }
        }

        return json_encode([
            'status' => 'success',
            'data'   => $reservations
        ]);
    } catch (PDOException $e) {
        return json_encode([
            'status'  => 'error',
            'message' => 'Error fetching reservations: ' . $e->getMessage()
        ]);
    }
}

    
    public function fetchMyReservationById($reservationId){
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
                    rs.reservation_status_status_id AS status_id,
                    rs.reservation_active AS active,
        
                    -- Requester information
                    CONCAT(u_req.users_fname, ' ', u_req.users_mname, ' ', u_req.users_lname) AS requester_name,
                    
                    -- Department information
                    dep.departments_name AS department_name,
                    
                    -- Venue details (as separate records)
                    GROUP_CONCAT(DISTINCT 
                        CONCAT(
                            v.reservation_venue_venue_id, ':', 
                            venue.ven_name, ':',
                            venue.ven_occupancy, ':',
                            IFNULL(venue.ven_pic, '')
                        )
                    ) as venue_data,
                    
                    -- Vehicle details (as separate records)
                    GROUP_CONCAT(DISTINCT 
                        CONCAT(
                            ve.reservation_vehicle_vehicle_id, ':',
                            vm.vehicle_license, ':',
                            vmm.vehicle_model_name
                        )
                    ) as vehicle_data,
                    
                    -- Equipment details
                    GROUP_CONCAT(DISTINCT 
                        CONCAT(
                            e.reservation_equipment_equip_id, ':',
                            equip.equip_name, ':',
                            e.reservation_equipment_quantity
                        )
                    ) as equipment_data,
                    
                    -- Driver details
                    GROUP_CONCAT(DISTINCT 
                        CONCAT(
                            d.reservation_driver_user_id, ':',
                            CONCAT(u_driver.users_fname, ' ', u_driver.users_mname, ' ', u_driver.users_lname)
                        )
                    ) as driver_data,
                    
                    -- Passenger details
                    GROUP_CONCAT(DISTINCT 
                        CONCAT(
                            p.reservation_passenger_id, ':',
                            p.reservation_passenger_name
                        )
                    ) as passenger_data

                FROM 
                    tbl_reservation r
                LEFT JOIN 
                    tbl_reservation_status rs ON r.reservation_id = rs.reservation_reservation_id
                LEFT JOIN 
                    tbl_users u_req ON r.reservation_user_id = u_req.users_id
                LEFT JOIN 
                    tbl_departments dep ON u_req.users_department_id = dep.departments_id
                    
                -- Venue joins
                LEFT JOIN tbl_reservation_venue v ON r.reservation_id = v.reservation_reservation_id
                LEFT JOIN tbl_venue venue ON v.reservation_venue_venue_id = venue.ven_id
                
                -- Vehicle joins
                LEFT JOIN tbl_reservation_vehicle ve ON r.reservation_id = ve.reservation_reservation_id
                LEFT JOIN tbl_vehicle vm ON ve.reservation_vehicle_vehicle_id = vm.vehicle_id
                LEFT JOIN tbl_vehicle_model vmm ON vm.vehicle_model_id = vmm.vehicle_model_id
                
                -- Equipment joins
                LEFT JOIN tbl_reservation_equipment e ON r.reservation_id = e.reservation_reservation_id
                LEFT JOIN tbl_equipments equip ON e.reservation_equipment_equip_id = equip.equip_id
                
                -- Driver joins
                LEFT JOIN tbl_reservation_driver d ON r.reservation_id = d.reservation_reservation_id
                LEFT JOIN tbl_users u_driver ON d.reservation_driver_user_id = u_driver.users_id
                
                -- Passenger joins
                LEFT JOIN tbl_reservation_passenger p ON r.reservation_id = p.reservation_reservation_id
                
                WHERE 
                    r.reservation_id = :reservation_id
                GROUP BY 
                    r.reservation_id";
        
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':reservation_id', $reservationId, PDO::PARAM_INT);
            $stmt->execute();
        
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($row) {
                // Initialize arrays
                $venues = [];
                $vehicles = [];
                $equipment = [];
                $drivers = [];
                $passengers = [];
                
                // Base reservation data
                $response = [
                    'reservation_id' => $row['reservation_id'],
                    'reservation_created_at' => $row['reservation_created_at'],
                    'reservation_title' => $row['reservation_title'],
                    'reservation_description' => $row['reservation_description'],
                    'reservation_start_date' => $row['reservation_start_date'],
                    'reservation_end_date' => $row['reservation_end_date'],
                    'reservation_participants' => $row['reservation_participants'],
                    'status_id' => $row['status_id'],
                    'active' => $row['active'],
                    'requester_name' => $row['requester_name'],
                    'department_name' => $row['department_name']
                ];

                // Process venue data
                if (!empty($row['venue_data'])) {
                    foreach(explode(',', $row['venue_data']) as $venueStr) {
                        $venueParts = explode(':', $venueStr);
                        if (count($venueParts) >= 4) {
                            $venues[] = [
                                'venue_id' => $venueParts[0],
                                'venue_name' => $venueParts[1],
                                'occupancy' => $venueParts[2],
                                'picture' => $venueParts[3]
                            ];
                        }
                    }
                }

                // Process vehicle data
                if (!empty($row['vehicle_data'])) {
                    foreach(explode(',', $row['vehicle_data']) as $vehicleStr) {
                        $vehicleParts = explode(':', $vehicleStr);
                        if (count($vehicleParts) >= 3) {
                            $vehicles[] = [
                                'vehicle_id' => $vehicleParts[0],
                                'license' => $vehicleParts[1],
                                'model' => $vehicleParts[2]
                            ];
                        }
                    }
                }

                // Process equipment data
                if (!empty($row['equipment_data'])) {
                    foreach(explode(',', $row['equipment_data']) as $equipStr) {
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

                // Process driver data
                if (!empty($row['driver_data'])) {
                    foreach(explode(',', $row['driver_data']) as $driverStr) {
                        $driverParts = explode(':', $driverStr);
                        if (count($driverParts) >= 2) {
                            $drivers[] = [
                                'driver_id' => $driverParts[0],
                                'name' => $driverParts[1]
                            ];
                        }
                    }
                }

                // Process passenger data
                if (!empty($row['passenger_data'])) {
                    foreach(explode(',', $row['passenger_data']) as $passengerStr) {
                        $passengerParts = explode(':', $passengerStr);
                        if (count($passengerParts) >= 2) {
                            $passengers[] = [
                                'passenger_id' => $passengerParts[0],
                                'name' => $passengerParts[1]
                            ];
                        }
                    }
                }

                // Add all arrays to response
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

    public function fetchStatusById($reservationId) {
        try {
            $sql = "
                SELECT 
                    rs.reservation_status_id,
                    rs.reservation_status_status_id AS status_id,
                    sm.status_master_name      AS status_name,
                    rs.reservation_active      AS active,
                    rs.reservation_updated_at  AS updated_at,
                    rs.reservation_users_id    AS updated_by_user_id,
                    CONCAT_WS(' ',
                        u.users_fname,
                        u.users_mname,
                        u.users_lname
                    ) AS updated_by_full_name
                FROM tbl_reservation_status rs
                INNER JOIN tbl_status_master sm 
                    ON rs.reservation_status_status_id = sm.status_master_id
                LEFT JOIN tbl_users u
                    ON rs.reservation_users_id = u.users_id
                WHERE rs.reservation_reservation_id = :reservation_id
                ORDER BY rs.reservation_status_id DESC
            ";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':reservation_id', $reservationId, PDO::PARAM_INT);
            $stmt->execute();
    
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if (count($rows) > 0) {
                return json_encode([
                    'status' => 'success',
                    'data'   => array_map(function($row) {
                        return [
                            'reservation_status_id'   => $row['reservation_status_id'],
                            'status_id'               => $row['status_id'],
                            'status_name'             => $row['status_name'],
                            'active'                  => $row['active'],
                            'updated_at'              => $row['updated_at'],
                            'updated_by_user_id'      => $row['updated_by_user_id'],
                            'updated_by_full_name'    => $row['updated_by_full_name'],
                        ];
                    }, $rows)
                ]);
            } else {
                return json_encode([
                    'status'  => 'error',
                    'message' => 'No status history found for that reservation'
                ]);
            }
        } catch (PDOException $e) {
            return json_encode([
                'status'  => 'error',
                'message' => 'Error fetching status history: ' . $e->getMessage()
            ]);
        }
    }

    public function displayedMaintenanceResources(int $reservationId) {
    try {
        $records = [];

        //
        // 1) Equipment
        //
        $sql = "
            SELECT 
                rce.id               AS record_id,
                'equipment'          AS resource_type,
                e.equip_name         AS resource_name,
                re.reservation_equipment_quantity AS quantity,
                re.reservation_equipment_equip_id  AS resource_id,
                CASE 
                    WHEN c.condition_name = 'Other' THEN rce.other_reason 
                    ELSE c.condition_name 
                END                   AS condition_name
            FROM tbl_reservation_condition_equipment rce
            JOIN tbl_reservation_equipment re 
              ON rce.reservation_equipment_id = re.reservation_equipment_id
            JOIN tbl_reservation r
              ON re.reservation_reservation_id = r.reservation_id
            JOIN tbl_equipments e 
              ON re.reservation_equipment_equip_id = e.equip_id
            JOIN tbl_condition c 
              ON rce.condition_id = c.id
            WHERE r.reservation_id = :reservation_id
        ";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute(['reservation_id' => $reservationId]);
        $records = array_merge($records, $stmt->fetchAll(PDO::FETCH_ASSOC));

        //
        // 2) Venues
        //
        $sql = "
            SELECT 
                rcv.id               AS record_id,
                'venue'              AS resource_type,
                v.ven_name           AS resource_name,
                NULL                 AS quantity,
                rv.reservation_venue_venue_id  AS resource_id,
                CASE 
                    WHEN c.condition_name = 'Other' THEN rcv.other_reason 
                    ELSE c.condition_name 
                END                   AS condition_name
            FROM tbl_reservation_condition_venue rcv
            JOIN tbl_reservation_venue rv 
              ON rcv.reservation_venue_id = rv.reservation_venue_id
            JOIN tbl_reservation r
              ON rv.reservation_reservation_id = r.reservation_id
            JOIN tbl_venue v 
              ON rv.reservation_venue_venue_id = v.ven_id
            JOIN tbl_condition c 
              ON rcv.condition_id = c.id
            WHERE r.reservation_id = :reservation_id
        ";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute(['reservation_id' => $reservationId]);
        $records = array_merge($records, $stmt->fetchAll(PDO::FETCH_ASSOC));

        //
        // 3) Vehicles
        //
        $sql = "
            SELECT
                rcvh.id              AS record_id,
                'vehicle'            AS resource_type,
                CONCAT(vm.vehicle_model_name, ' (', vh.vehicle_license, ')') AS resource_name,
                NULL                 AS quantity,
                rv.reservation_vehicle_vehicle_id AS resource_id,
                CASE 
                    WHEN c.condition_name = 'Other' THEN rcvh.other_reason 
                    ELSE c.condition_name 
                END                   AS condition_name
            FROM tbl_reservation_condition_vehicle rcvh
            JOIN tbl_reservation_vehicle rv 
              ON rcvh.reservation_vehicle_id = rv.reservation_vehicle_id
            JOIN tbl_reservation r
              ON rv.reservation_reservation_id = r.reservation_id
            JOIN tbl_vehicle vh 
              ON rv.reservation_vehicle_vehicle_id = vh.vehicle_id
            JOIN tbl_vehicle_model vm 
              ON vh.vehicle_model_id = vm.vehicle_model_id
            JOIN tbl_condition c 
              ON rcvh.condition_id = c.id
            WHERE r.reservation_id = :reservation_id
        ";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute(['reservation_id' => $reservationId]);
        $records = array_merge($records, $stmt->fetchAll(PDO::FETCH_ASSOC));

        return json_encode([
            'status' => 'success',
            'data'   => $records
        ], JSON_THROW_ON_ERROR);

    } catch (PDOException $e) {
        return json_encode([
            'status'  => 'error',
            'message' => $e->getMessage()
        ], JSON_THROW_ON_ERROR);
    }
}


    public function fetchMyActiveReservation($userId) {
        try {
            $query = "
                SELECT 
                    r.reservation_id,
                    r.reservation_title,
                    r.reservation_description,
                    r.reservation_start_date,
                    r.reservation_end_date,
                    r.reservation_participants,
                    r.reservation_user_id,
                    r.reservation_created_at,
                    rs.reservation_active,
                    sm.status_master_name AS reservation_status
                FROM tbl_reservation AS r
                LEFT JOIN tbl_reservation_status AS rs
                  ON rs.reservation_status_id = (
                        SELECT reservation_status_id
                        FROM tbl_reservation_status
                        WHERE reservation_reservation_id = r.reservation_id
                          AND reservation_status_status_id = 6
                          AND reservation_active = 1
                        ORDER BY 
                          reservation_updated_at DESC,
                          reservation_status_id DESC
                        LIMIT 1
                  )
                LEFT JOIN tbl_status_master AS sm
                  ON rs.reservation_status_status_id = sm.status_master_id
                WHERE r.reservation_user_id = :userId
                  AND rs.reservation_status_status_id = 6
                  AND rs.reservation_active = 1
                ORDER BY r.reservation_start_date DESC
            ";
    
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
            $stmt->execute();
            $reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
            return json_encode([
                'status' => 'success',
                'data'   => $reservations
            ]);
        } catch (PDOException $e) {
            return json_encode([
                'status'  => 'error',
                'message' => 'Error fetching active reservations: ' . $e->getMessage()
            ]);
        }
    }

    public function fetchNotification($userId) {
        try {
            $query = "
                SELECT 
                    notification_reservation_id,
                    notification_message,
                    notification_reservation_reservation_id,
                    notification_user_id,
                    notification_created_at,
                    is_read
                FROM tbl_notification_reservation
                WHERE notification_user_id = :userId
                ORDER BY notification_created_at DESC
            ";

            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
            $stmt->execute();
            $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return json_encode([
                'status' => 'success',
                'data'   => $notifications
            ]);
        } catch (PDOException $e) {
            return json_encode([
                'status'  => 'error',
                'message' => 'Error fetching notifications: ' . $e->getMessage()
            ]);
        }
    }

    public function updateReadNotification($notificationIds, $actingUserId = null) {
        try {
            if (!is_array($notificationIds)) {
                $notificationIds = [$notificationIds]; // Convert single ID to array for consistency
            }

            $placeholders = str_repeat('?,', count($notificationIds) - 1) . '?';
            $query = "
                UPDATE tbl_notification_reservation
                SET is_read = 1
                WHERE notification_reservation_id IN ($placeholders)
            ";

            $stmt = $this->conn->prepare($query);
            $stmt->execute($notificationIds);

            if ($stmt->rowCount() > 0) {
                $updatedCount = $stmt->rowCount();

                // Audit log (non-blocking)
                try {
                    // Try to determine the acting user from the targeted notifications
                    $uid = null;
                    $checkQuery = "SELECT DISTINCT notification_user_id FROM tbl_notification_reservation WHERE notification_reservation_id IN ($placeholders)";
                    $checkStmt = $this->conn->prepare($checkQuery);
                    $checkStmt->execute($notificationIds);
                    $uids = $checkStmt->fetchAll(PDO::FETCH_COLUMN, 0);
                    if (is_array($uids) && count($uids) === 1) {
                        $uid = (int)$uids[0];
                    }

                    // Prefer the acting user id when available; fallback to the notification owner
                    $createdBy = $actingUserId !== null ? (int)$actingUserId : $uid;

                    $desc = 'Marked notifications as read (count: ' . (int)$updatedCount . ')';
                    $auditSql = "INSERT INTO audit_log (description, action, created_at, created_by) VALUES (:description, :action, NOW(), :created_by)";
                    $audit = $this->conn->prepare($auditSql);
                    $audit->execute([
                        ':description' => $desc,
                        ':action' => 'READ NOTIFICATION',
                        ':created_by' => $createdBy
                    ]);
                } catch (Throwable $te) { /* ignore audit errors */ }

                return json_encode([
                    'status' => 'success',
                    'message' => 'Notifications marked as read',
                    'updated_count' => $updatedCount
                ]);
            } else {
                return json_encode([
                    'status' => 'error',
                    'message' => 'No notifications were updated'
                ]);
            }
        } catch (PDOException $e) {
            return json_encode([
                'status'  => 'error',
                'message' => 'Error updating notifications: ' . $e->getMessage()
            ]);
        }
    }
    
    public function fetchDeansApproval($reservationId) {
        try {
            $sql = "
                SELECT 
                    da.department_approval_id,
                    da.department_is_approved,
                    da.department_approval_department_id,
                    da.department_user_id,
                    da.department_updated_at,
                    da.department_request_reservation_id,
                    d.departments_name,
                    CONCAT_WS(' ', u.users_fname, u.users_mname, u.users_lname) as user_name
                FROM 
                    tbl_department_approval da
                LEFT JOIN 
                    tbl_departments d ON da.department_approval_department_id = d.departments_id
                LEFT JOIN 
                    tbl_users u ON da.department_user_id = u.users_id
                WHERE 
                    da.department_request_reservation_id = :reservation_id
                ORDER BY 
                    da.department_updated_at DESC
            ";
    
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':reservation_id', $reservationId, PDO::PARAM_INT);
            $stmt->execute();
    
            $approvals = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $approvals[] = [
                    'approval_id' => $row['department_approval_id'],
                    'is_approved' => $row['department_is_approved'],
                    'department_id' => $row['department_approval_department_id'],
                    'department_name' => $row['departments_name'],
                    'user_id' => $row['department_user_id'] ?: '',
                    'user_name' => $row['user_name'] ?: '',
                    'updated_at' => $row['department_updated_at'],
                    'reservation_id' => $row['department_request_reservation_id']
                ];
            }
    
            return json_encode([
                'status' => 'success',
                'data' => $approvals
            ]);
    
        } catch (PDOException $e) {
            return json_encode([
                'status' => 'error',
                'message' => 'Error fetching department approvals: ' . $e->getMessage()
            ]);
        }
    }
    
    public function updateReschedule(int $reservationId, int $active, ?int $actingUserId = null) {
        try {
            if (!in_array($active, [1, -1], true)) {
                return json_encode(['status' => 'error', 'message' => 'Invalid active value. Use 1 for confirm or -1 for decline.']);
            }

            $this->conn->beginTransaction();

            // 1) Update the latest pending status (status 10 with active = 0)
            $updSql = "
                UPDATE tbl_reservation_status
                SET reservation_active = :active,
                    reservation_updated_at = NOW(),
                    reservation_users_id = :user_id
                WHERE reservation_reservation_id = :rid
                  AND reservation_status_status_id = 10
                  AND reservation_active = 0
                ORDER BY reservation_status_id DESC
                LIMIT 1
            ";
            $upd = $this->conn->prepare($updSql);
            $upd->bindValue(':active', $active, PDO::PARAM_INT);
            $upd->bindValue(':user_id', $actingUserId !== null ? (int)$actingUserId : null, $actingUserId !== null ? PDO::PARAM_INT : PDO::PARAM_NULL);
            $upd->bindValue(':rid', (int)$reservationId, PDO::PARAM_INT);
            $upd->execute();

            // 2) Handle status insertion based on active value
            if ($active == 1) {
                // For confirmed reschedule (active = 1), check if status 1 or 8 is active before inserting status 6
                $checkSql = "
                    SELECT COUNT(*) as count 
                    FROM tbl_reservation_status 
                    WHERE reservation_reservation_id = :rid 
                    AND reservation_status_status_id IN (1, 8)
                    AND reservation_active = 1
                ";
                $check = $this->conn->prepare($checkSql);
                $check->bindValue(':rid', (int)$reservationId, PDO::PARAM_INT);
                $check->execute();
                $result = $check->fetch(PDO::FETCH_ASSOC);
            
                // Only insert status 6 if status 1 or 8 is active
                if ($result['count'] > 0) {
                    $checkExistingSql = "
                        SELECT COUNT(*) as count 
                        FROM tbl_reservation_status 
                        WHERE reservation_reservation_id = :rid 
                        AND reservation_status_status_id = 6
                        AND reservation_active = 1
                    ";
                    $checkExisting = $this->conn->prepare($checkExistingSql);
                    $checkExisting->bindValue(':rid', (int)$reservationId, PDO::PARAM_INT);
                    $checkExisting->execute();
                    $existingResult = $checkExisting->fetch(PDO::FETCH_ASSOC);
            
                    // Insert status 6 if it doesn't already exist
                    if ($existingResult['count'] == 0) {
                        $insSql = "
                            INSERT INTO tbl_reservation_status
                                (reservation_status_status_id, reservation_reservation_id, reservation_active, reservation_updated_at, reservation_users_id)
                            VALUES (6, :rid, 1, NOW(), :user_id)
                        ";
                        $ins = $this->conn->prepare($insSql);
                        $ins->bindValue(':rid', (int)$reservationId, PDO::PARAM_INT);
                        $ins->bindValue(':user_id', $actingUserId !== null ? (int)$actingUserId : null, $actingUserId !== null ? PDO::PARAM_INT : PDO::PARAM_NULL);
                        $ins->execute();
                    }
                }
            
                // ✅ Always insert status 14 after confirming reschedule
                $ins14Sql = "
                    INSERT INTO tbl_reservation_status
                        (reservation_status_status_id, reservation_reservation_id, reservation_active, reservation_updated_at, reservation_users_id)
                    VALUES (14, :rid, 1, NOW(), :user_id)
                ";
                $ins14 = $this->conn->prepare($ins14Sql);
                $ins14->bindValue(':rid', (int)$reservationId, PDO::PARAM_INT);
                $ins14->bindValue(':user_id', $actingUserId !== null ? (int)$actingUserId : null, $actingUserId !== null ? PDO::PARAM_INT : PDO::PARAM_NULL);
                $ins14->execute();
            }
             else {
                // For declined reschedule (active = -1), insert status 5
                $insSql = "
                    INSERT INTO tbl_reservation_status
                        (reservation_status_status_id, reservation_reservation_id, reservation_active, reservation_updated_at, reservation_users_id)
                    VALUES (5, :rid, -1, NOW(), :user_id)
                ";
                $ins = $this->conn->prepare($insSql);
                $ins->bindValue(':rid', (int)$reservationId, PDO::PARAM_INT);
                $ins->bindValue(':user_id', $actingUserId !== null ? (int)$actingUserId : null, $actingUserId !== null ? PDO::PARAM_INT : PDO::PARAM_NULL);
                $ins->execute();
            }

            $this->conn->commit();

            return json_encode(['status' => 'success', 'message' => 'Reschedule status updated']);
        } catch (PDOException $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            return json_encode(['status' => 'error', 'message' => 'Error updating reschedule: ' . $e->getMessage()]);
        }
    }

    public function updateVenueReschedule($reservation_venue_id, $reservation_change_venue_id) {
        try {
            if ($reservation_venue_id === null) {
                return json_encode(['status' => 'error', 'message' => 'Missing required reservation_venue_id parameter']);
            }
    
            $sql = "UPDATE tbl_reservation_venue
                    SET reservation_change_venue_id = :reservation_change_venue_id
                    WHERE reservation_venue_id = :reservation_venue_id";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindValue(':reservation_venue_id', (int)$reservation_venue_id, PDO::PARAM_INT);
            
            // Handle null case properly
            if ($reservation_change_venue_id === null) {
                $stmt->bindValue(':reservation_change_venue_id', null, PDO::PARAM_NULL);
            } else {
                $stmt->bindValue(':reservation_change_venue_id', (int)$reservation_change_venue_id, PDO::PARAM_INT);
            }
    
            $ok = $stmt->execute();
            if ($ok) {
                return json_encode(['status' => 'success', 'message' => 'Venue reschedule updated successfully']);
            }
            return json_encode(['status' => 'error', 'message' => 'Failed to update venue reschedule']);
        } catch (PDOException $e) {
            error_log('Database error in updateVenueReschedule: ' . $e->getMessage());
            return json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
        }
    }
    
    public function updateVehicleReschedule($reservation_vehicle_id, $reservation_change_vehicle_id) {
        try {
            if ($reservation_vehicle_id === null) {
                return json_encode(['status' => 'error', 'message' => 'Missing required reservation_vehicle_id parameter']);
            }
    
            $sql = "UPDATE tbl_reservation_vehicle
                    SET reservation_change_vehicle_id = :reservation_change_vehicle_id
                    WHERE reservation_vehicle_id = :reservation_vehicle_id";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindValue(':reservation_vehicle_id', (int)$reservation_vehicle_id, PDO::PARAM_INT);
            
            // Handle null case properly
            if ($reservation_change_vehicle_id === null) {
                $stmt->bindValue(':reservation_change_vehicle_id', null, PDO::PARAM_NULL);
            } else {
                $stmt->bindValue(':reservation_change_vehicle_id', (int)$reservation_change_vehicle_id, PDO::PARAM_INT);
            }
    
            $ok = $stmt->execute();
            if ($ok) {
                return json_encode(['status' => 'success', 'message' => 'Vehicle reschedule updated successfully']);
            }
            return json_encode(['status' => 'error', 'message' => 'Failed to update vehicle reschedule']);
        } catch (PDOException $e) {
            error_log('Database error in updateVehicleReschedule: ' . $e->getMessage());
            return json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
        }
    }

    public function updateReservationReschedule($reservation_id, $reschedule_start_date, $reschedule_end_date, $user_admin_id = null) {
        try {
            if ($reservation_id === null || $reschedule_start_date === null || $reschedule_end_date === null) {
                return json_encode(['status' => 'error', 'message' => 'Missing required parameters']);
            }
    
            $this->conn->beginTransaction();
    
            // 1) Update reschedule dates on reservation
            $sql = "UPDATE tbl_reservation
                    SET reschedule_start_date = :reschedule_start_date,
                        reschedule_end_date = :reschedule_end_date
                    WHERE reservation_id = :reservation_id";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindValue(':reschedule_start_date', $reschedule_start_date, PDO::PARAM_STR);
            $stmt->bindValue(':reschedule_end_date', $reschedule_end_date, PDO::PARAM_STR);
            $stmt->bindValue(':reservation_id', (int)$reservation_id, PDO::PARAM_INT);
            $stmt->execute();
    
            // 2) Update status ID 8 to active (reservation_active = 1) for this reservation
            $updateStatusSql = "UPDATE tbl_reservation_status 
                               SET reservation_active = 1, 
                                   reservation_updated_at = NOW(),
                                   reservation_users_id = :user_admin_id
                               WHERE reservation_reservation_id = :reservation_id 
                               AND reservation_status_status_id = 10";
            $updateStmt = $this->conn->prepare($updateStatusSql);
            $updateStmt->bindValue(':reservation_id', (int)$reservation_id, PDO::PARAM_INT);
            if ($user_admin_id === null || $user_admin_id === '') {
                $updateStmt->bindValue(':user_admin_id', null, PDO::PARAM_NULL);
            } else {
                $updateStmt->bindValue(':user_admin_id', (int)$user_admin_id, PDO::PARAM_INT);
            }
            $updateStmt->execute();
    
            // 3) Insert a new status row with reservation_active = 0 and status id 11
            $ins = $this->conn->prepare("INSERT INTO tbl_reservation_status
                (reservation_status_status_id, reservation_reservation_id, reservation_active, reservation_updated_at, reservation_users_id)
                VALUES (:status_id, :reservation_id, 0, NOW(), :user_admin_id)");
            $ins->bindValue(':status_id', 11, PDO::PARAM_INT);
            $ins->bindValue(':reservation_id', (int)$reservation_id, PDO::PARAM_INT);
            if ($user_admin_id === null || $user_admin_id === '') {
                $ins->bindValue(':user_admin_id', null, PDO::PARAM_NULL);
            } else {
                $ins->bindValue(':user_admin_id', (int)$user_admin_id, PDO::PARAM_INT);
            }
            $ins->execute();
    
            $this->conn->commit();
    
            return json_encode(['status' => 'success', 'message' => 'Reservation reschedule updated successfully']);
        } catch (PDOException $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            error_log('Database error in updateReservationReschedule: ' . $e->getMessage());
            return json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
        }
    }

    // Insert Reservation
    public function insertReservation($formType, $formId, $resourceId) {
        try {
            $sql = "INSERT INTO tbl_reservation 
                    (" . ($formType === 'venue' ? 'reservation_venue_id' : 'reservation_vehicle_id') . ",
                    reservation_date) 
                    VALUES (:resource_id, NOW())";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':resource_id', $resourceId);
            
            if ($stmt->execute()) {
                $reservationId = $this->conn->lastInsertId();
                // Insert initial status
                $this->insertReservationStatus($reservationId);
                return ['status' => 'success', 'reservation_id' => $reservationId];
            } else {
                return ['status' => 'error', 'message' => 'Failed to create reservation.'];
            }
        } catch (PDOException $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }
    private function checkEquipmentAvailability($equipmentId, $requestedQuantity) {
        try {
            $sql = "SELECT equipment_quantity FROM tbl_equipment WHERE equipment_id = :equipment_id";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':equipment_id', $equipmentId);
            $stmt->execute();
            
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($result && $result['equipment_quantity'] >= $requestedQuantity) {
                return true;
            }
            return false;
        } catch (PDOException $e) {
            return false;
        }
    }

    public function hasConflictRequest($resourceType, $resourceId, $startDate, $endDate, $requestedQuantity = 0) {
        if (!in_array($resourceType, ['venue', 'vehicle', 'equipment'])) {
            error_log("Invalid resource type: " . $resourceType);
            return ['status' => false, 'count' => 0];
        }
    
        try {
            $sql = "";
    
            switch ($resourceType) {
                case 'venue':
                    $sql = "SELECT COUNT(DISTINCT r.reservation_id) AS conflict_count
                            FROM tbl_reservation r
                            INNER JOIN tbl_reservation_venue v
                              ON r.reservation_id = v.reservation_reservation_id
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
                            ) ls ON ls.reservation_reservation_id = r.reservation_id
                            LEFT JOIN (
                                SELECT reservation_reservation_id, MAX(reservation_status_id) AS max_reschedule_status_id
                                FROM tbl_reservation_status
                                WHERE reservation_status_status_id = 10 AND reservation_active = 1
                                GROUP BY reservation_reservation_id
                            ) active_resched ON active_resched.reservation_reservation_id = r.reservation_id
                            WHERE v.reservation_venue_venue_id = :resource_id
                              AND (
                                ls.reservation_status_status_id IN (1, 6, 8, 10)
                                AND (
                                  (ls.reservation_status_status_id = 6 AND ls.reservation_active = 1)
                                  OR (ls.reservation_status_status_id IN (1, 8, 10) AND (ls.reservation_active = 0 OR ls.reservation_active = 1))
                                )
                              )
                              AND (
                                (CASE
                                  WHEN (
                                        (ls.reservation_status_status_id = 10 AND ls.reservation_active = 1)
                                        OR (
                                            ls.reservation_status_status_id = 6 AND ls.reservation_active = 1
                                            AND active_resched.max_reschedule_status_id IS NOT NULL
                                        )
                                      )
                                      AND r.reschedule_start_date IS NOT NULL AND r.reschedule_end_date IS NOT NULL
                                  THEN r.reschedule_start_date ELSE r.reservation_start_date END) <= :end_date
                                AND (CASE
                                  WHEN (
                                        (ls.reservation_status_status_id = 10 AND ls.reservation_active = 1)
                                        OR (
                                            ls.reservation_status_status_id = 6 AND ls.reservation_active = 1
                                            AND active_resched.max_reschedule_status_id IS NOT NULL
                                        )
                                      )
                                      AND r.reschedule_start_date IS NOT NULL AND r.reschedule_end_date IS NOT NULL
                                  THEN r.reschedule_end_date ELSE r.reservation_end_date END) >= :start_date
                              )";
                    break;

                case 'vehicle':
                    $sql = "SELECT COUNT(DISTINCT r.reservation_id) AS conflict_count
                            FROM tbl_reservation r
                            INNER JOIN tbl_reservation_vehicle v
                              ON r.reservation_id = v.reservation_reservation_id
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
                            ) ls ON ls.reservation_reservation_id = r.reservation_id
                            LEFT JOIN (
                                SELECT reservation_reservation_id, MAX(reservation_status_id) AS max_reschedule_status_id
                                FROM tbl_reservation_status
                                WHERE reservation_status_status_id = 10 AND reservation_active = 1
                                GROUP BY reservation_reservation_id
                            ) active_resched ON active_resched.reservation_reservation_id = r.reservation_id
                            WHERE v.reservation_vehicle_vehicle_id = :resource_id
                              AND (
                                ls.reservation_status_status_id IN (1, 6, 8, 10)
                                AND (
                                  (ls.reservation_status_status_id = 6 AND ls.reservation_active = 1)
                                  OR (ls.reservation_status_status_id IN (1, 8, 10) AND (ls.reservation_active = 0 OR ls.reservation_active = 1))
                                )
                              )
                              AND (
                                (CASE
                                  WHEN (
                                        (ls.reservation_status_status_id = 10 AND ls.reservation_active = 1)
                                        OR (
                                            ls.reservation_status_status_id = 6 AND ls.reservation_active = 1
                                            AND active_resched.max_reschedule_status_id IS NOT NULL
                                        )
                                      )
                                      AND r.reschedule_start_date IS NOT NULL AND r.reschedule_end_date IS NOT NULL
                                  THEN r.reschedule_start_date ELSE r.reservation_start_date END) <= :end_date
                                AND (CASE
                                  WHEN (
                                        (ls.reservation_status_status_id = 10 AND ls.reservation_active = 1)
                                        OR (
                                            ls.reservation_status_status_id = 6 AND ls.reservation_active = 1
                                            AND active_resched.max_reschedule_status_id IS NOT NULL
                                        )
                                      )
                                      AND r.reschedule_start_date IS NOT NULL AND r.reschedule_end_date IS NOT NULL
                                  THEN r.reschedule_end_date ELSE r.reservation_end_date END) >= :start_date
                              )";
                    break;
                    // driver conflict check removed

                case 'equipment':
                    // Compute capacity based on inventory model (bulk or serial)
                    //  - Bulk: use tbl_equipment_quantity.on_hand_quantity (fallback to quantity if null)
                    //  - Serial: count of active units in tbl_equipment_unit whose status is not 2 (only 2 is considered unavailable)
                    // Then subtract overlapping reserved quantities from tbl_reservation_equipment.
                    $sql = "SELECT t.equip_id,
                                   t.equip_name,
                                   t.total_capacity,
                                   COALESCE(ur.used_quantity, 0) AS used_quantity,
                                   (t.total_capacity - COALESCE(ur.used_quantity, 0)) AS remaining_quantity
                            FROM (
                              SELECT eq.equip_id,
                                     eq.equip_name,
                                     CASE 
                                       WHEN LOWER(eq.equip_type) = 'bulk' THEN (
                                         SELECT COALESCE(SUM(COALESCE(q.on_hand_quantity, q.quantity)), 0)
                                         FROM tbl_equipment_quantity q
                                         WHERE q.equip_id = eq.equip_id
                                           AND (q.status_availability_id IS NULL OR q.status_availability_id <> 2)
                                       )
                                       ELSE (
                                         SELECT COALESCE(SUM(CASE WHEN u.is_active = 1 AND (u.status_availability_id IS NULL OR u.status_availability_id <> 2) THEN 1 ELSE 0 END), 0)
                                         FROM tbl_equipment_unit u
                                         WHERE u.equip_id = eq.equip_id
                                       )
                                     END AS total_capacity
                              FROM tbl_equipments eq
                              WHERE eq.equip_id = :resource_id
                            ) t
                            LEFT JOIN (
                              SELECT e.reservation_equipment_equip_id AS equip_id,
                                     COALESCE(SUM(e.reservation_equipment_quantity), 0) AS used_quantity
                              FROM tbl_reservation_equipment e
                              INNER JOIN tbl_reservation r ON e.reservation_reservation_id = r.reservation_id
                              INNER JOIN tbl_reservation_status rs ON r.reservation_id = rs.reservation_reservation_id
                              WHERE e.reservation_equipment_equip_id = :resource_id
                                AND rs.reservation_status_status_id = 1
                                AND r.reservation_id NOT IN (
                                  SELECT DISTINCT reservation_reservation_id 
                                  FROM tbl_reservation_status 
                                  WHERE reservation_status_status_id IN (2,5)
                                )
                                AND (:start_date <= r.reservation_end_date AND :end_date >= r.reservation_start_date)
                            ) ur ON ur.equip_id = t.equip_id";

                    $stmt = $this->conn->prepare($sql);
                    $stmt->bindValue(':resource_id', $resourceId, PDO::PARAM_INT);
                    $stmt->bindValue(':start_date', $startDate);
                    $stmt->bindValue(':end_date', $endDate);
                    $stmt->execute();
                    $result = $stmt->fetch(PDO::FETCH_ASSOC);

                    if (!$result) {
                        return ['status' => true, 'count' => 0];
                    }
                    $remainingQuantity = (int)$result['remaining_quantity'];
                    error_log('Equipment check equip_id=' . (int)$resourceId . ' remaining=' . $remainingQuantity . ' requested=' . (int)$requestedQuantity . ' total_capacity=' . (int)$result['total_capacity']);
                    return ['status' => true, 'count' => ($requestedQuantity > $remainingQuantity ? 1 : 0)];
            }

            // Generic path for venue/vehicle
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':resource_id', $resourceId, PDO::PARAM_INT);
            $stmt->bindParam(':start_date', $startDate);
            $stmt->bindParam(':end_date', $endDate);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            $conflictCount = (int)($result['conflict_count'] ?? 0);
            return ['status' => true, 'count' => $conflictCount];
    
        } catch (PDOException $e) {
            error_log("Conflict check error: " . $e->getMessage());
            return ['status' => false, 'count' => 0];
        }
    }
    
    public function insertEquipment($reservationId, $equipments) {
        try {
            $sql = "INSERT INTO tbl_reservation_equipment 
                    (reservation_equipment_equip_id, reservation_reservation_id, reservation_equipment_quantity) 
                    VALUES (:equipment_id, :reservation_id, :quantity)";
            $stmt = $this->conn->prepare($sql);
            $errors = [];

            foreach ($equipments as $equip) {
                $stmt->bindParam(':equipment_id', $equip['equipment_id']);
                $stmt->bindParam(':reservation_id', $reservationId);
                $stmt->bindParam(':quantity', $equip['quantity']);

                if (!$stmt->execute()) {
                    $errors[] = 'Failed to insert equipment: ' . $equip['equipment_id'];
                }
            }

            if (!empty($errors)) {
                return ['status' => 'error', 'message' => implode('; ', $errors)];
            }

            return ['status' => 'success', 'message' => 'All equipment inserted successfully.'];
        } catch (PDOException $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }
    
    public function insertVehicles($reservationId, $vehicleIds) {
        try {
            $sql = "INSERT INTO tbl_reservation_vehicle 
                    (reservation_vehicle_vehicle_id, reservation_reservation_id) 
                    VALUES (:vehicle_id, :reservation_id)";

            $stmt = $this->conn->prepare($sql);
            $errors = [];

            foreach ($vehicleIds as $vehicleId) {
                $stmt->bindParam(':vehicle_id', $vehicleId);
                $stmt->bindParam(':reservation_id', $reservationId);

                if (!$stmt->execute()) {
                    $errors[] = 'Failed to insert vehicle: ' . $vehicleId;
                }
            }

            if (!empty($errors)) {
                return ['status' => 'error', 'message' => implode('; ', $errors)];
            }

            return ['status' => 'success', 'message' => 'All vehicles inserted successfully.'];
        } catch (PDOException $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }
    public function insertVenue($reservationId, $venueIds) {
        try {
            $sql = "INSERT INTO tbl_reservation_venue 
                    (reservation_venue_venue_id, reservation_reservation_id) 
                    VALUES (:venue_id, :reservation_id)";

            $stmt = $this->conn->prepare($sql);
            $errors = [];

            foreach ($venueIds as $venueId) {
                $stmt->bindParam(':venue_id', $venueId);
                $stmt->bindParam(':reservation_id', $reservationId);

                if (!$stmt->execute()) {
                    $errors[] = 'Failed to insert venue: ' . $venueId;
                }
            }

            if (!empty($errors)) {
                return ['status' => 'error', 'message' => implode('; ', $errors)];
            }

            return ['status' => 'success', 'message' => 'All venues inserted successfully.'];
        } catch (PDOException $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }
  
    public function insertDriver($drivers = null) {
        try {
            $sql = "INSERT INTO tbl_reservation_driver (reservation_driver_user_id, driver_name, reservation_vehicle_id) VALUES (:driver_id, :driver_name, :reservation_vehicle_id)";
            $stmt = $this->conn->prepare($sql);
            $errors = [];

            // Normalize input to array of drivers
            if (is_null($drivers)) {
                $drivers = [];
            } elseif (is_string($drivers)) {
                $drivers = [['name' => $drivers]];
            } elseif (is_array($drivers) && isset($drivers['name'])) {
                // Single driver object with 'name'
                $drivers = [$drivers];
            } elseif (!is_array($drivers) || (is_array($drivers) && array_keys($drivers) === range(0, count($drivers) - 1))) {
                // Already an array of drivers or single value
                $drivers = is_array($drivers) ? $drivers : [$drivers];
            } else {
                $drivers = [$drivers];
            }

            foreach ($drivers as $driver) {
                $userId = null;
                $name = null;
                $vehicleId = null;
                if (is_array($driver)) {
                    $userId = isset($driver['user_id']) ? $driver['user_id'] : null;
                    $name = array_key_exists('name', $driver) ? $driver['name'] : null;
                    if (isset($driver['reservation_vehicle_id'])) {
                        $vehicleId = $driver['reservation_vehicle_id'];
                    } elseif (isset($driver['vehicle_id'])) {
                        $vehicleId = $driver['vehicle_id'];
                    } else {
                        $vehicleId = null;
                    }
                } elseif (is_numeric($driver)) {
                    $userId = $driver;
                } elseif (is_string($driver)) {
                    $name = $driver;
                }
                $stmt->bindValue(':driver_id', $userId, is_null($userId) ? \PDO::PARAM_NULL : \PDO::PARAM_INT);
                $stmt->bindValue(':driver_name', $name, is_null($name) ? \PDO::PARAM_NULL : \PDO::PARAM_STR);
                $stmt->bindValue(':reservation_vehicle_id', $vehicleId, is_null($vehicleId) ? \PDO::PARAM_NULL : \PDO::PARAM_INT);
                if (!$stmt->execute()) {
                    $errors[] = 'Failed to insert driver: ' . (is_null($userId) ? $name : $userId);
                }
            }
            if (!empty($errors)) {
                return ['status' => 'error', 'message' => implode('; ', $errors)];
            }
            return ['status' => 'success', 'message' => 'All drivers inserted successfully.'];
        } catch (PDOException $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }
    // deprecated: insertVehicleForm removed (legacy form tables no longer used)
    // deprecated: insertVenueForm removed (legacy form tables no longer used)
    
    // deprecated: getAvailableEquipmentQuantity removed (uses legacy form tables)
    
    

    // Add this new method to check for active transactions
    public function hasActiveTransaction() {
        try {
            return $this->conn->inTransaction();
        } catch (PDOException $e) {
            return false;
        }
    }

    // Named lock helpers to prevent race conditions on resource reservation
    private function acquireLock($key, $timeout = 1.0) {
        try {
            $stmt = $this->conn->prepare("SELECT GET_LOCK(:k, :t) AS got");
            $stmt->execute([':k' => $key, ':t' => $timeout]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return isset($row['got']) && (int)$row['got'] === 1;
        } catch (PDOException $e) {
            error_log('acquireLock error: ' . $e->getMessage());
            return false;
        }
    }

    private function releaseLock($key) {
        try {
            $stmt = $this->conn->prepare("SELECT RELEASE_LOCK(:key)");
            $stmt->bindParam(':key', $key);
            $stmt->execute();
        } catch (PDOException $e) {
            error_log('Failed to release lock: ' . $e->getMessage());
        }
    }

    // Determine if the user's role should bypass conflict checks
    private function shouldBypassConflict($userId) {
        try {
            $sql = "SELECT 
                        COALESCE(d.departments_name, '') AS dept_name,
                        COALESCE(ul.user_level_name, '') AS level_name
                    FROM tbl_users u
                    LEFT JOIN tbl_departments d ON d.departments_id = u.users_department_id
                    LEFT JOIN tbl_user_level ul ON ul.user_level_id = u.users_user_level_id
                    WHERE u.users_id = :user_id
                    LIMIT 1";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            if (!$stmt->execute()) {
                return false;
            }
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                return false;
            }
            $dept = strtolower(trim($row['dept_name'] ?? ''));
            $level = strtolower(trim($row['level_name'] ?? ''));
            $bypass = ($dept === 'coo' && $level === 'department head');
            $bypass1 = ($dept === 'gsd' && $level === 'secretary');
                    
            if ($bypass || $bypass1) {
                error_log(sprintf('Bypassing conflict checks for user_id=%d (dept=%s, level=%s)', (int)$userId, $row['dept_name'], $row['level_name']));
            }
            return $bypass || $bypass1;
        } catch (PDOException $e) {
            error_log('shouldBypassConflict error: ' . $e->getMessage());
            return false;
        }
    }

    private function insertReservationStatus($reservationId) {
        try {
            $sql = "INSERT INTO tbl_reservation_status 
                    (reservation_status_reservation_id, 
                    reservation_status_status_reservation_id,
                    reservation_status_updated_at) 
                    VALUES (:reservation_id, 1, NOW())";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':reservation_id', $reservationId);
            return $stmt->execute();
        } catch (PDOException $e) {
            return false;
        }
    }
    public function insertPassengers($reservationId, $passengers) {
        try {
            if (empty($passengers)) {
                return ['status' => 'warning', 'message' => 'No passengers provided.'];
            }

            $sql = "INSERT INTO tbl_reservation_passenger 
                    (reservation_passenger_name, reservation_reservation_id) 
                    VALUES (:passenger_name, :reservation_id)";

            $stmt = $this->conn->prepare($sql);
            $errors = [];

            foreach ($passengers as $passenger) {
                // Normalize passenger value to string name
                if (is_array($passenger)) {
                    $name = isset($passenger['name']) ? (string)$passenger['name'] : '';
                } else {
                    $name = (string)$passenger;
                }
                $stmt->bindParam(':passenger_name', $name);
                $stmt->bindParam(':reservation_id', $reservationId);

                if (!$stmt->execute()) {
                    $errors[] = 'Failed to insert passenger: ' . $name;
                }
            }

            if (!empty($errors)) {
                return ['status' => 'error', 'message' => implode('; ', $errors)];
            }

            return ['status' => 'success', 'message' => 'All passengers inserted successfully.'];
        } catch (PDOException $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }
    
    public function createReservation($data, $type) {
        // Normalize inputs to arrays expected by logic
        if ($type === 'venue') {
            if (!isset($data['venues']) && isset($data['venue'])) {
                $data['venues'] = [(int)$data['venue']];
            }
        } elseif ($type === 'vehicle') {
            if (!isset($data['vehicles']) && isset($data['vehicle'])) {
                $data['vehicles'] = [(int)$data['vehicle']];
            }
        }

        // Collect resource lock keys by type
        $lockKeys = [];
        $start = $data['start_date'] ?? null;
        $end = $data['end_date'] ?? null;
        if ($type === 'venue') {
            if (!empty($data['venues']) && is_array($data['venues'])) {
                foreach ($data['venues'] as $vid) {
                    $lockKeys[] = 'lock:venue:' . (int)$vid;
                }
            }
            if (!empty($data['equipment']) && is_array($data['equipment'])) {
                foreach ($data['equipment'] as $eq) {
                    $lockKeys[] = 'lock:equipment:' . (int)$eq['equipment_id'];
                }
            }
        } elseif ($type === 'vehicle') {
            if (!empty($data['vehicles']) && is_array($data['vehicles'])) {
                foreach ($data['vehicles'] as $vehId) {
                    $lockKeys[] = 'lock:vehicle:' . (int)$vehId;
                }
            }
            if (!empty($data['equipment']) && is_array($data['equipment'])) {
                foreach ($data['equipment'] as $eq) {
                    $lockKeys[] = 'lock:equipment:' . (int)$eq['equipment_id'];
                }
            }
        } elseif ($type === 'equipment') {
            if (!empty($data['equipment']) && is_array($data['equipment'])) {
                foreach ($data['equipment'] as $eq) {
                    $lockKeys[] = 'lock:equipment:' . (int)$eq['equipment_id'];
                }
            }
        }

        // Acquire locks in deterministic order BEFORE transaction
        sort($lockKeys);
        $acquired = [];
        try {
            foreach ($lockKeys as $k) {
                if (!$this->acquireLock($k, 2.0)) { // allow more time for one request to win
                    foreach ($acquired as $ak) { $this->releaseLock($ak); }
                    return ['status' => 'error', 'message' => 'Resource is being reserved by another request. Please try again.'];
                }
                $acquired[] = $k;
            }
            if (!empty($acquired)) {
                error_log('Acquired locks: ' . implode(',', $acquired));
            }

            // Start transaction after locks are acquired
            $this->conn->beginTransaction();

            // Final conflict checks while holding locks
            $bypassConflict = $this->shouldBypassConflict($data['user_id'] ?? 0);
            if (!$bypassConflict) {
                if ($type === 'venue') {
                    if (!empty($data['venues'])) {
                        foreach ($data['venues'] as $vid) {
                            $conf = $this->hasConflictRequest('venue', (int)$vid, $start, $end);
                            error_log('Venue conflict check for venue ' . (int)$vid . ' status=' . ($conf['status'] ? 'ok' : 'err') . ' count=' . ($conf['count'] ?? 'n/a'));
                            if ($conf['status'] === false) {
                                throw new Exception('Failed to check venue availability.');
                            }
                            if ($conf['count'] > 0) {
                                throw new Exception('Selected venue has conflicting reservation.');
                            }
                        }
                    }
                    if (!empty($data['equipment'])) {
                        foreach ($data['equipment'] as $eq) {
                            $conf = $this->hasConflictRequest('equipment', (int)$eq['equipment_id'], $start, $end, (int)$eq['quantity']);
                            if ($conf['status'] === false) {
                                throw new Exception('Failed to check equipment availability.');
                            }
                            if ($conf['count'] > 0) {
                                throw new Exception('Selected equipment has conflicting reservation or insufficient quantity.');
                            }
                        }
                    }
                } elseif ($type === 'vehicle') {
                    if (!empty($data['vehicles'])) {
                        foreach ($data['vehicles'] as $vehId) {
                            $conf = $this->hasConflictRequest('vehicle', (int)$vehId, $start, $end);
                            error_log('Vehicle conflict check for vehicle ' . (int)$vehId . ' status=' . ($conf['status'] ? 'ok' : 'err') . ' count=' . ($conf['count'] ?? 'n/a'));
                            if ($conf['status'] === false) {
                                throw new Exception('Failed to check vehicle availability.');
                            }
                            if ($conf['count'] > 0) {
                                throw new Exception('Selected vehicle has conflicting reservation.');
                            }
                        }
                    }
                    if (!empty($data['equipment'])) {
                        foreach ($data['equipment'] as $eq) {
                            $conf = $this->hasConflictRequest('equipment', (int)$eq['equipment_id'], $start, $end, (int)$eq['quantity']);
                            if ($conf['status'] === false) {
                                throw new Exception('Failed to check equipment availability.');
                            }
                            if ($conf['count'] > 0) {
                                throw new Exception('Selected equipment has conflicting reservation or insufficient quantity.');
                            }
                        }
                    }
                } elseif ($type === 'equipment') {
                    if (!empty($data['equipment'])) {
                        foreach ($data['equipment'] as $eq) {
                            $conf = $this->hasConflictRequest('equipment', (int)$eq['equipment_id'], $start, $end, (int)$eq['quantity']);
                            if ($conf['status'] === false) {
                                throw new Exception('Failed to check equipment availability.');
                            }
                            if ($conf['count'] > 0) {
                                throw new Exception('Selected equipment has conflicting reservation or insufficient quantity.');
                            }
                        }
                    }
                }
            } else {
                error_log('Conflict checks skipped due to bypass policy');
            }

            // Insert into tbl_reservation
            $sql = "INSERT INTO tbl_reservation 
                    (reservation_title, reservation_description, 
                    reservation_start_date, reservation_end_date,
                    reservation_participants, reservation_user_id,
                    reservation_created_at, additional_note) 
                    VALUES (:title, :description,
                    :start_date, :end_date, :participants, :user_id,
                    NOW(), :additional_note)";
            
            $stmt = $this->conn->prepare($sql);

            $additionalNote = isset($data['additional_note']) ? $data['additional_note'] : null;

            switch($type) {
                case 'venue':
                    $stmt->bindParam(':title', $data['title']);
                    $stmt->bindParam(':description', $data['description']);
                    $stmt->bindParam(':start_date', $data['start_date']);
                    $stmt->bindParam(':end_date', $data['end_date']);
                    $stmt->bindParam(':participants', $data['participants']);
                    $stmt->bindParam(':user_id', $data['user_id']);
                    $stmt->bindValue(':additional_note', $additionalNote);
                    break;
                case 'vehicle':
                    $stmt->bindParam(':title', $data['destination']);
                    $stmt->bindParam(':description', $data['purpose']);
                    $stmt->bindValue(':participants', null);
                    $stmt->bindParam(':user_id', $data['user_id']);
                    $stmt->bindParam(':start_date', $data['start_date']);
                    $stmt->bindParam(':end_date', $data['end_date']);
                    $stmt->bindValue(':additional_note', $additionalNote);
                    break;
                case 'equipment':
                    $stmt->bindParam(':title', $data['title']);
                    $stmt->bindParam(':description', $data['description']);
                    $stmt->bindParam(':start_date', $data['start_date']);
                    $stmt->bindParam(':end_date', $data['end_date']);
                    $stmt->bindParam(':participants', $data['participants']);
                    $stmt->bindParam(':user_id', $data['user_id']);
                    $stmt->bindValue(':additional_note', $additionalNote);
                    break;
            }

            if (!$stmt->execute()) {
                throw new Exception('Failed to create reservation record');
            }

            $reservationId = $this->conn->lastInsertId();

            // Insert resource bindings inside the same transaction
            if ($type === 'venue') {
                if (!empty($data['venues'])) {
                    $venueResult = $this->insertVenue($reservationId, $data['venues']);
                    if ($venueResult['status'] !== 'success') {
                        throw new Exception($venueResult['message']);
                    }
                }
                if (!empty($data['equipment'])) {
                    $equipmentResult = $this->insertEquipment($reservationId, $data['equipment']);
                    if ($equipmentResult['status'] !== 'success') {
                        throw new Exception($equipmentResult['message']);
                    }
                }
            } elseif ($type === 'vehicle') {
                if (!empty($data['vehicles'])) {
                    $vehicleResult = $this->insertVehicles($reservationId, $data['vehicles']);
                    if ($vehicleResult['status'] !== 'success') {
                        throw new Exception($vehicleResult['message']);
                    }
                }
                // Map vehicle_id to reservation_vehicle_id for this reservation
                $vehicleIds = !empty($data['vehicles']) ? $data['vehicles'] : [];
                $vehicleMap = [];
                if (!empty($vehicleIds)) {
                    $placeholders = implode(',', array_fill(0, count($vehicleIds), '?'));
                    $sqlMap = "SELECT reservation_vehicle_id, reservation_vehicle_vehicle_id 
                               FROM tbl_reservation_vehicle 
                               WHERE reservation_reservation_id = ? 
                                 AND reservation_vehicle_vehicle_id IN ($placeholders)";
                    $stmtMap = $this->conn->prepare($sqlMap);
                    $params = array_merge([$reservationId], $vehicleIds);
                    $stmtMap->execute($params);
                    while ($row = $stmtMap->fetch(PDO::FETCH_ASSOC)) {
                        $vehicleMap[$row['reservation_vehicle_vehicle_id']] = $row['reservation_vehicle_id'];
                    }
                }
                if (!empty($data['passengers'])) {
                    $passengerResult = $this->insertPassengers($reservationId, $data['passengers']);
                    if ($passengerResult['status'] !== 'success') {
                        throw new Exception($passengerResult['message']);
                    }
                }
                if (!empty($data['drivers'])) {
                    // Attach reservation_vehicle_id to each driver where possible
                    $drivers = $data['drivers'];
                    if (is_array($drivers)) {
                        foreach ($drivers as &$driver) {
                            if (isset($driver['vehicle_id']) && isset($vehicleMap[$driver['vehicle_id']])) {
                                $driver['reservation_vehicle_id'] = $vehicleMap[$driver['vehicle_id']];
                            }
                        }
                        unset($driver);
                    }
                    $driverResult = $this->insertDriver($drivers);
                    if ($driverResult['status'] !== 'success') {
                        throw new Exception($driverResult['message']);
                    }
                }
                if (!empty($data['equipment'])) {
                    $equipmentResult = $this->insertEquipment($reservationId, $data['equipment']);
                    if ($equipmentResult['status'] !== 'success') {
                        throw new Exception($equipmentResult['message']);
                    }
                }
            } elseif ($type === 'equipment') {
                if (!empty($data['equipment'])) {
                    $equipmentResult = $this->insertEquipment($reservationId, $data['equipment']);
                    if ($equipmentResult['status'] !== 'success') {
                        throw new Exception($equipmentResult['message']);
                    }
                }
            }
    
            // Get the user level ID based on the reservation_user_id
            $userLevelSql = "SELECT u.users_user_level_id , u.users_department_id
                             FROM tbl_users u 
                             WHERE u.users_id = :user_id";
            $userLevelStmt = $this->conn->prepare($userLevelSql);
            $userLevelStmt->bindParam(':user_id', $data['user_id'], PDO::PARAM_INT);
    
            if (!$userLevelStmt->execute()) {
                $this->conn->rollBack();
                return ['status' => 'error', 'message' => 'Failed to fetch user level'];
            }
    
            $userLevel = $userLevelStmt->fetch(PDO::FETCH_ASSOC);
            $userLevelId = $userLevel['users_user_level_id'];
            
            // Determine if requester is GSD Secretary (used to skip department approvals)
            $isGsdSecretary = false;
            try {
                $roleSql = "SELECT COALESCE(d.departments_name,'') AS dept_name, COALESCE(ul.user_level_name,'') AS level_name
                            FROM tbl_users u
                            LEFT JOIN tbl_departments d ON d.departments_id = u.users_department_id
                            LEFT JOIN tbl_user_level ul ON ul.user_level_id = u.users_user_level_id
                            WHERE u.users_id = :user_id
                            LIMIT 1";
                $roleStmt = $this->conn->prepare($roleSql);
                $roleStmt->bindValue(':user_id', $data['user_id'], PDO::PARAM_INT);
                if ($roleStmt->execute()) {
                    $roleRow = $roleStmt->fetch(PDO::FETCH_ASSOC);
                    $deptName = strtolower(trim($roleRow['dept_name'] ?? ''));
                    $levelName = strtolower(trim($roleRow['level_name'] ?? ''));
                    $isGsdSecretary = ($deptName === 'gsd' && $levelName === 'secretary');
                    if ($isGsdSecretary) {
                    // Insert notification for Admins (Dept 27, Level 1)
                    try {
                        // Get all users in department 27 (GSD) for notifications
                        $gsdUsersSql = "SELECT users_id FROM tbl_users WHERE users_department_id = 27 AND is_active = 1";
                        $gsdUsersStmt = $this->conn->prepare($gsdUsersSql);
                        
                        if ($gsdUsersStmt->execute()) {
                            $gsdUsers = $gsdUsersStmt->fetchAll(PDO::FETCH_ASSOC);
                            
                            // Insert notification for each user in GSD department
                            $gsdNotifSql = "INSERT INTO tbl_notification_reservation 
                                          (notification_message, notification_reservation_reservation_id, 
                                           notification_user_id, notification_created_at, is_read) 
                                          VALUES ('New Reservation Request', :reservation_id, :user_id, NOW(), 0)";
                            
                            foreach ($gsdUsers as $user) {
                                $gsdNotifStmt = $this->conn->prepare($gsdNotifSql);
                                $gsdNotifStmt->bindValue(':reservation_id', $reservationId, PDO::PARAM_INT);
                                $gsdNotifStmt->bindValue(':user_id', $user['users_id'], PDO::PARAM_INT);
                                
                                if (!$gsdNotifStmt->execute()) {
                                    error_log('Failed to insert GSD notification for user ID: ' . $user['users_id']);
                                }
                            }
                        } else {
                            error_log('Failed to fetch GSD users for notifications');
                        }
                    } catch (Exception $e) {
                        error_log('Exception inserting GSD Secretary admin notification: ' . $e->getMessage());
                    }
                        error_log('Detected GSD Secretary requester - department approvals will be skipped.');
                    }
                }
            } catch (Exception $e) {
                error_log('Failed to determine requester role for department approvals: ' . $e->getMessage());
            }
    
            // Initialize status SQL based on user level
            if ($userLevelId == 3 || $userLevelId == 6 || $userLevelId == 16 || $userLevelId == 17) {
                // Determine event type from all venues
                $reservationActive = null;
                $hasBigEvent = false;
                
                if (!empty($data['venues']) && is_array($data['venues'])) {
                    // Check all venues for event types
                    foreach ($data['venues'] as $venueId) {
                        $stmtEventType = $this->conn->prepare("SELECT event_type FROM tbl_venue WHERE ven_id = :venue_id LIMIT 1");
                        $stmtEventType->bindParam(':venue_id', $venueId, PDO::PARAM_INT);
                        if ($stmtEventType->execute()) {
                            $row = $stmtEventType->fetch(PDO::FETCH_ASSOC);
                            if ($row && isset($row['event_type'])) {
                                $eventType = $row['event_type'];
                                // If any venue is Big Event, set hasBigEvent to true
                                if ($eventType === 'Big Event') {
                                    $hasBigEvent = true;
                                    break; // No need to check further, one Big Event makes it null
                                }
                            }
                        }
                    }
                    
                    // Set reservation_active based on whether any venue is Big Event
                    if ($hasBigEvent) {
                        $reservationActive = null;
                    } else {
                        // If no Big Event found, check if all are Small Event
                        $allSmallEvents = true;
                        foreach ($data['venues'] as $venueId) {
                            $stmtEventType = $this->conn->prepare("SELECT event_type FROM tbl_venue WHERE ven_id = :venue_id LIMIT 1");
                            $stmtEventType->bindParam(':venue_id', $venueId, PDO::PARAM_INT);
                            if ($stmtEventType->execute()) {
                                $row = $stmtEventType->fetch(PDO::FETCH_ASSOC);
                                if ($row && isset($row['event_type']) && $row['event_type'] !== 'Small Event') {
                                    $allSmallEvents = false;
                                    break;
                                }
                            }
                        }
                        
                        if ($allSmallEvents) {
                            $reservationActive = 1;
                        } else {
                            $reservationActive = null;
                        }
                    }
                }
    
                // Check if department approvals are needed based on user level and venue types
                if ((in_array($userLevelId, [3, 6, 16, 17])) && !empty($data['venues']) && is_array($data['venues']) && !$isGsdSecretary) {
                    // Log the start of venue check
                    error_log("Checking venues for department approvals - Reservation ID: " . $reservationId);
                    
                    // Get event_type and area_type for all venues in this reservation
                    $venueIds = implode(",", array_map('intval', $data['venues']));
                    $venueSql = "SELECT ven_id, event_type, area_type, ven_name FROM tbl_venue WHERE ven_id IN ($venueIds)";
                    $venueStmt = $this->conn->query($venueSql);
                    
                    if ($venueStmt) {
                        $venues = $venueStmt->fetchAll(PDO::FETCH_ASSOC);
                        $hasBigEvent = false;
                        $hasOpenArea = false;
                        $hasCloseArea = false;
                        
                        // Log all venues being checked
                        error_log("Venues to check (ID - Name - Event Type - Area Type): " . 
                                json_encode(array_map(function($v) { 
                                    return [
                                        'id' => $v['ven_id'],
                                        'name' => $v['ven_name'] ?? 'N/A',
                                        'event_type' => $v['event_type'] ?? 'not set',
                                        'area_type' => $v['area_type'] ?? 'not set'
                                    ]; 
                                }, $venues)));
                        
                        // Check venue types
                        foreach ($venues as $venue) {
                            $venueId = $venue['ven_id'];
                            $eventType = $venue['event_type'] ?? 'not set';
                            $areaType = $venue['area_type'] ?? 'not set';
                            
                            error_log(sprintf("Checking venue - ID: %d, Name: %s, Event Type: %s, Area Type: %s", 
                                $venueId, 
                                $venue['ven_name'] ?? 'N/A',
                                $eventType,
                                $areaType
                            ));
                            
                            if ($eventType === 'Big Event') {
                                $hasBigEvent = true;
                                if ($areaType === 'Open Area') {
                                    $hasOpenArea = true;
                                    error_log(sprintf("FOUND Big Event with Open Area - Venue ID: %d, Name: %s", 
                                        $venueId, $venue['ven_name'] ?? 'N/A'));
                                } elseif ($areaType === 'Close Area') {
                                    $hasCloseArea = true;
                                    error_log(sprintf("FOUND Big Event with Close Area - Venue ID: %d, Name: %s", 
                                        $venueId, $venue['ven_name'] ?? 'N/A'));
                                }
                            } elseif ($eventType === 'Small Event' && $areaType === 'Close Area') {
                                $hasCloseArea = true;
                                error_log(sprintf("FOUND Small Event with Close Area - Venue ID: %d, Name: %s", 
                                    $venueId, $venue['ven_name'] ?? 'N/A'));
                            }
                        }
                        
                        // Insert department approval based on conditions
                        if (($hasBigEvent && $hasOpenArea) || // Big Event AND Open Area
                            ($hasBigEvent && $hasCloseArea) || // Big Event AND Close Area
                            $hasCloseArea) { // Small Event AND Close Area (handled by the same flag)
                            
                            // Initialize departments array
                            $departmentsToApprove = [];
                            $userDeptId = isset($userLevel['users_department_id']) ? (int)$userLevel['users_department_id'] : 0;
                            
                            // For user levels 3 and 6
                            if (in_array($userLevelId, [3, 6])) {
                                // For Big Event in Open Area - include all academic departments
                                if ($hasBigEvent && $hasOpenArea) {
                                    // Get all academic departments (excluding 48 and 54)
                                    $deptQuery = "SELECT departments_id FROM tbl_departments WHERE department_type = 'Academic' AND departments_id NOT IN (48, 54)";
                                    $deptStmt = $this->conn->query($deptQuery);
                                    
                                    if ($deptStmt) {
                                        $departmentsToApprove = $deptStmt->fetchAll(PDO::FETCH_COLUMN);
                                        error_log(sprintf("Adding all academic departments for user level %d (Big Event in Open Area)", $userLevelId));
                                    }
                                }
                                // For Big Event in Close Area or Small Event in Close Area - include only their department
                                elseif (($hasBigEvent && $hasCloseArea) || $hasCloseArea) {
                                    if ($userDeptId > 0) {
                                        $departmentsToApprove = [$userDeptId];
                                        error_log(sprintf("Adding user's department %d for user level %d (%s in Close Area)", 
                                            $userDeptId, $userLevelId, $hasBigEvent ? 'Big Event' : 'Small Event'));
                                    } else {
                                        error_log(sprintf("Cannot add department approval - invalid department ID for user level %d", $userLevelId));
                                    }
                                }
                            }
                            // For user levels 16 and 17 with Big Event in Open Area - include all academic departments + department 29
                            else if (in_array($userLevelId, [16, 17]) && $hasBigEvent && $hasOpenArea) {
                                // Get all academic departments (excluding 48 and 54)
                                $deptQuery = "SELECT departments_id FROM tbl_departments WHERE department_type = 'Academic' AND departments_id NOT IN (48, 54)";
                                $deptStmt = $this->conn->query($deptQuery);
                                
                                if ($deptStmt) {
                                    $departmentsToApprove = $deptStmt->fetchAll(PDO::FETCH_COLUMN);
                                }
                                
                                // Add department 29 if not already in the list
                                if (!in_array(29, $departmentsToApprove)) {
                                    $departmentsToApprove[] = 29;
                                }
                                
                                // If no academic departments found, just use department 29
                                if (empty($departmentsToApprove)) {
                                    $departmentsToApprove = [29];
                                }
                                
                                // For user levels 5, 6, and 18, exclude their own department from approvals
                                if (in_array($userLevelId, [5, 6, 18]) && isset($userLevel['users_department_id'])) {
                                    $userDeptId = (int)$userLevel['users_department_id'];
                                    $departmentsToApprove = array_filter($departmentsToApprove, function($deptId) use ($userDeptId) {
                                        return (int)$deptId !== $userDeptId;
                                    });
                                    error_log(sprintf("Excluded user's department ID %d from approvals for user level %d", 
                                        $userDeptId, $userLevelId));
                                }
                            } 
                            // For Big Event in Close Area
                            elseif (($hasBigEvent && $hasCloseArea) && in_array($userLevelId, [16, 17])) {
                                if ($userLevelId == 17) {
                                    // For level 17: include department 29 and the user's own department
                                    if ($userDeptId > 0) {
                                        $departmentsToApprove = [29, (int)$userDeptId];
                                    } else {
                                        $departmentsToApprove = [29];
                                    }
                                    // De-duplicate and normalize
                                    $departmentsToApprove = array_values(array_unique(array_map('intval', $departmentsToApprove)));
                                    error_log(sprintf("Adding departments for level 17 (Big Event, Close Area): %s", json_encode($departmentsToApprove)));
                                } else {
                                    // For level 16: only department 29
                                    $departmentsToApprove = [29];
                                }
                                
                                // For user levels 5, 6, and 18, exclude their own department if it's 29
                                if (in_array($userLevelId, [5, 6, 18]) && isset($userLevel['users_department_id'])) {
                                    $userDeptId = (int)$userLevel['users_department_id'];
                                    if ($userDeptId === 29) {
                                        $departmentsToApprove = [];
                                        error_log(sprintf("Excluded user's department ID %d from approvals for user level %d", 
                                            $userDeptId, $userLevelId));
                                    }
                                }
                            } 
                            // For Small Event in Close Area
                            else if ($hasCloseArea && $userLevelId == 17) {
                                // For level 17: include department 29 and the user's own department
                                if ($userDeptId > 0) {
                                    $departmentsToApprove = [29, (int)$userDeptId];
                                } else {
                                    $departmentsToApprove = [29];
                                }
                                // De-duplicate and normalize
                                $departmentsToApprove = array_values(array_unique(array_map('intval', $departmentsToApprove)));
                                error_log(sprintf("Adding departments for level 17 (Small Event, Close Area): %s", json_encode($departmentsToApprove)));
                            }
                            else if ($hasCloseArea) {
                                $departmentsToApprove = [];
                            }
                            
                            // Skip departments 48 and 54 for all venues (they will be added back only for venue 92)
                            $departmentsToApprove = array_filter($departmentsToApprove, function($deptId) {
                                return !in_array($deptId, [48, 54]);
                            });
                            
                            // Check if venue ID 92 is in the reservation and add additional departments
                            if (in_array(92, $data['venues'])) {
                                $additionalDepts = [48, 54];
                                foreach ($additionalDepts as $additionalDept) {
                                    if (!in_array($additionalDept, $departmentsToApprove)) {
                                        $departmentsToApprove[] = $additionalDept;
                                        error_log(sprintf("Added additional department %d for venue ID 92", $additionalDept));
                                    }
                                }
                            }
                            
                            foreach ($departmentsToApprove as $deptId) {
                                // Prepare the department approval insert statement
                                $deptApprovalSql = "INSERT INTO tbl_department_approval 
                                                  (department_is_approved, department_approval_department_id, 
                                                  department_user_id, department_updated_at, department_request_reservation_id) 
                                                  VALUES (0, :dept_id, NULL, NULL, :reservation_id)";
                                $deptApprovalStmt = $this->conn->prepare($deptApprovalSql);
                                
                                $deptApprovalStmt->bindValue(':dept_id', $deptId, PDO::PARAM_INT);
                                $deptApprovalStmt->bindValue(':reservation_id', $reservationId, PDO::PARAM_INT);
                                
                                if (!$deptApprovalStmt->execute()) {
                                    $error = $deptApprovalStmt->errorInfo();
                                    error_log(sprintf("Failed to create department approval - Department ID: %d, Error: %s", 
                                        $deptId,
                                        json_encode($error)
                                    ));
                                    $this->conn->rollBack();
                                    return ['status' => 'error', 'message' => 'Failed to create department approval record'];
                                } else {
                                    error_log(sprintf("Successfully created department approval - Department ID: %d, Reservation ID: %d", 
                                        $deptId,
                                        $reservationId
                                    ));
                                }
                            }
                        }
                    }
                }
    
                // Status SQL 1 and 2 remain the same
                $statusSql1 = "INSERT INTO tbl_reservation_status 
                              (reservation_reservation_id, reservation_status_status_id, 
                               reservation_active, reservation_users_id, reservation_updated_at) 
                              VALUES (:reservation_id, 1, 0, 114, NOW())";
    
                $statusSql2 = "INSERT INTO tbl_reservation_status 
                              (reservation_reservation_id, reservation_status_status_id, 
                               reservation_active, reservation_users_id, reservation_updated_at) 
                              VALUES (:reservation_id, 8, 0, 99, NOW())";
    
                $statusStmt1 = $this->conn->prepare($statusSql1);
                $statusStmt1->bindValue(':reservation_id', $reservationId, PDO::PARAM_INT);
                if (!$statusStmt1->execute()) {
                    $this->conn->rollBack();
                    return ['status' => 'error', 'message' => 'Failed to create first reservation status'];
                }
    
                $statusStmt2 = $this->conn->prepare($statusSql2);
                $statusStmt2->bindValue(':reservation_id', $reservationId, PDO::PARAM_INT);
                if (!$statusStmt2->execute()) {
                    $this->conn->rollBack();
                    return ['status' => 'error', 'message' => 'Failed to create second reservation status'];
                }
    
                // Insert notification for waiting for approval (keeping existing notification)
                $notificationSql = "INSERT INTO tbl_notification_reservation 
                                  (notification_message, notification_reservation_reservation_id, 
                                   notification_user_id, notification_created_at)
                                  VALUES ('Your reservation is waiting for approval', 
                                         :reservation_id, :user_id, NOW())";
                $notificationStmt = $this->conn->prepare($notificationSql);
                $notificationStmt->bindValue(':reservation_id', $reservationId, PDO::PARAM_INT);
                $notificationStmt->bindValue(':user_id', $data['user_id'], PDO::PARAM_INT);
                
                if (!$notificationStmt->execute()) {
                    $this->conn->rollBack();
                    return ['status' => 'error', 'message' => 'Failed to create notification'];
                }
                // Audit: Request submitted (exclude any fetch-only ops)
                try {
                    $desc = ($type === 'venue')
                        ? 'Venue Request submitted'
                        : (($type === 'vehicle')
                            ? 'Vehicle Request submitted'
                            : 'Equipment Request submitted');
                    $action = 'Reservation Request';
                    $created_by = (int)$data['user_id'];

                    $auditSql = "INSERT INTO audit_log (description, action, created_at, created_by) VALUES (:description, :action, NOW(), :created_by)";
                    $auditStmt = $this->conn->prepare($auditSql);
                    $auditStmt->bindParam(':description', $desc, PDO::PARAM_STR);
                    $auditStmt->bindParam(':action', $action, PDO::PARAM_STR);
                    $auditStmt->bindParam(':created_by', $created_by, PDO::PARAM_INT);
                    if (!$auditStmt->execute()) {
                        $err = $auditStmt->errorInfo();
                        error_log('Audit log insert failed (submission block): ' . json_encode($err));
                    }
                } catch (Exception $e) {
                    // Do not fail the main flow if audit logging fails; just log the error
                    error_log('Audit log insert failed: ' . $e->getMessage());
                }
    
                // Insert notifications directly to tbl_notification_reservation for all department users
                if (!$isGsdSecretary) {
                    // Get all users in the department
                    $deptUsersSql = "SELECT users_id FROM tbl_users WHERE users_department_id = :dept_id AND is_active = 1";
                    $deptUsersStmt = $this->conn->prepare($deptUsersSql);
                    $deptUsersStmt->bindValue(':dept_id', $userLevel['users_department_id'], PDO::PARAM_INT);
                    
                    if ($deptUsersStmt->execute()) {
                        $deptUsers = $deptUsersStmt->fetchAll(PDO::FETCH_ASSOC);
                        
                        // Insert notification for each user in the department
                        $notifSql = "INSERT INTO tbl_notification_reservation 
                                   (notification_message, notification_reservation_reservation_id, 
                                    notification_user_id, notification_created_at, is_read) 
                                   VALUES ('New Reservation Request', :reservation_id, :user_id, NOW(), 0)";
                        
                        foreach ($deptUsers as $user) {
                            $notifStmt = $this->conn->prepare($notifSql);
                            $notifStmt->bindValue(':reservation_id', $reservationId, PDO::PARAM_INT);
                            $notifStmt->bindValue(':user_id', $user['users_id'], PDO::PARAM_INT);
                            
                            if (!$notifStmt->execute()) {
                                error_log('Failed to insert notification for user ID: ' . $user['users_id']);
                            }
                        }
                    } else {
                        error_log('Failed to fetch department users for notifications');
                    }
                }
    
                // --- SPECIAL NOTIFICATION FOR DEPARTMENT 27, USER LEVEL 1 (ALWAYS) ---
                // Get all users in department 27 with user level 1
                $specialDeptUsersSql = "SELECT users_id FROM tbl_users WHERE users_department_id = 27 AND users_user_level_id = 1 AND is_active = 1";
                $specialDeptUsersStmt = $this->conn->prepare($specialDeptUsersSql);
                
                if ($specialDeptUsersStmt->execute()) {
                    $specialDeptUsers = $specialDeptUsersStmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    // Insert notification for each user in department 27 with level 1
                    $specialNotifSql = "INSERT INTO tbl_notification_reservation 
                                      (notification_message, notification_reservation_reservation_id, 
                                       notification_user_id, notification_created_at, is_read) 
                                      VALUES ('New Reservation Request', :reservation_id, :user_id, NOW(), 0)";
                    
                    foreach ($specialDeptUsers as $user) {
                        $specialNotifStmt = $this->conn->prepare($specialNotifSql);
                        $specialNotifStmt->bindValue(':reservation_id', $reservationId, PDO::PARAM_INT);
                        $specialNotifStmt->bindValue(':user_id', $user['users_id'], PDO::PARAM_INT);
                        
                        if (!$specialNotifStmt->execute()) {
                            error_log('Failed to insert special notification for department 27, level 1 user ID: ' . $user['users_id']);
                        }
                    }
                } else {
                    error_log('Failed to fetch department 27, level 1 users for special notifications');
                }
    
                // --- PUSH NOTIFICATION LOGIC (for user levels 3, 6, 16, 17) ---
                if ($isGsdSecretary) {
                    // For GSD Secretary: push directly to Admins in department 27
                    $sqlPushUsers = "SELECT u.users_id
                                        FROM tbl_users u
                                        INNER JOIN tbl_push_subscriptions ps ON u.users_id = ps.user_id
                                        WHERE u.users_user_level_id = 1
                                        AND u.users_department_id = 27
                                        AND ps.is_active = 1";
                    $stmtPushUsers = $this->conn->prepare($sqlPushUsers);
                    $stmtPushUsers->execute();
                    $pushUsers = $stmtPushUsers->fetchAll(PDO::FETCH_ASSOC);

                    error_log("GSD Secretary flow: Found " . count($pushUsers) . " admin users in department 27 for push notification.");

                    $pushTitle = 'New Reservation Request';
                    $pushBody = 'A new reservation request waiting for confirmation.';
                    $pushData = [
                        'reservation_id' => $reservationId,
                        'type' => 'reservation_confirmation'
                    ];
                    $pushUrl = 'http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']) . '/server/send-push-notification.php';

                    foreach ($pushUsers as $pushUser) {
                        $pushPayload = [
                            'operation' => 'send',
                            'user_id' => $pushUser['users_id'],
                            'title' => $pushTitle,
                            'body' => $pushBody,
                            'data' => $pushData
                        ];
                        
                        $ch = curl_init();
                        curl_setopt($ch, CURLOPT_URL, $pushUrl);
                        curl_setopt($ch, CURLOPT_POST, true);
                        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($pushPayload));
                        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
                        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                        
                        $response = curl_exec($ch);
                        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                        $error = curl_error($ch);
                        curl_close($ch);
                        
                        if ($error || $httpCode < 200 || $httpCode >= 300) {
                            error_log("GSD Secretary admin push failed for user {$pushUser['users_id']}: " . ($error ?: "HTTP $httpCode"));
                        } else {
                            error_log("GSD Secretary admin push sent successfully to user {$pushUser['users_id']}");
                        }
                    }
                } else {
                    // Default: push to department 5/6 approvers in user's department
                    $sqlPushUsers = "SELECT u.users_id
                                        FROM tbl_users u
                                        INNER JOIN tbl_push_subscriptions ps ON u.users_id = ps.user_id
                                        WHERE u.users_department_id = :dept_id
                                        AND u.users_user_level_id IN (5, 6)
                                        AND ps.is_active = 1";
                    $stmtPushUsers = $this->conn->prepare($sqlPushUsers);
                    $stmtPushUsers->bindValue(':dept_id', $userLevel['users_department_id'], PDO::PARAM_INT);
                    $stmtPushUsers->execute();
                    $pushUsers = $stmtPushUsers->fetchAll(PDO::FETCH_ASSOC);

                    // Log the number of users found for push notifications
                    error_log("Found " . count($pushUsers) . " users with push subscriptions for department " . $userLevel['users_department_id'] . " and user levels 5,6");

                    // Prepare push notification data
                    $pushTitle = 'New Reservation Request';
                    $pushBody = 'A new reservation request is pending approval.';
                    $pushData = [
                        'reservation_id' => $reservationId,
                        'type' => 'reservation_approval',
                        'department_id' => $userLevel['users_department_id'],
                    ];
                    $pushUrl = 'http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']) . '/server/send-push-notification.php';

                    $successCount = 0;
                    $errorCount = 0;

                    foreach ($pushUsers as $pushUser) {
                        $pushPayload = [
                            'operation' => 'send',
                            'user_id' => $pushUser['users_id'],
                            'title' => $pushTitle,
                            'body' => $pushBody,
                            'data' => $pushData
                        ];
                        
                        error_log("Sending push notification to user {$pushUser['users_id']}: " . json_encode($pushPayload));
                        
                        $ch = curl_init();
                        curl_setopt($ch, CURLOPT_URL, $pushUrl);
                        curl_setopt($ch, CURLOPT_POST, true);
                        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($pushPayload));
                        curl_setopt($ch, CURLOPT_HTTPHEADER, [
                            'Content-Type: application/json',
                            'Content-Length: ' . strlen(json_encode($pushPayload))
                        ]);
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
                        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                        
                        $response = curl_exec($ch);
                        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                        $error = curl_error($ch);
                        curl_close($ch);
                        
                        error_log("Push notification response for user {$pushUser['users_id']}: HTTP $httpCode, Response: $response");
                        
                        if ($error || $httpCode < 200 || $httpCode >= 300) {
                            error_log("Push notification failed for user {$pushUser['users_id']}: " . ($error ?: "HTTP $httpCode"));
                            $errorCount++;
                        } else {
                            error_log("Push notification sent successfully to user {$pushUser['users_id']}");
                            $successCount++;
                        }
                    }
                    
                    error_log("Push notifications sent after reservation creation: $successCount successful, $errorCount failed");
                }
    
            } elseif ($userLevelId == 5 || $userLevelId == 18) {
                // Skip department approvals for user level 18 with department ID 28
                if ($userLevelId == 18 && isset($userLevel['users_department_id']) && (int)$userLevel['users_department_id'] == 28) {
                    error_log("Skipping department approvals for user level 18 with department ID 28");
                } 
                // Only process department approvals if there are venues in the reservation and not skipped
                elseif (!empty($data['venues']) && is_array($data['venues']) && !$isGsdSecretary) {
                    // Log the start of venue check
                    error_log("Checking venues for department approvals (Levels 5/18) - Reservation ID: " . $reservationId);
                    
                    // Get event_type and area_type for all venues in this reservation
                    $venueIds = implode(",", array_map('intval', $data['venues']));
                    $venueSql = "SELECT ven_id, event_type, area_type, ven_name FROM tbl_venue WHERE ven_id IN ($venueIds)";
                    $venueStmt = $this->conn->query($venueSql);
                    
                    if ($venueStmt) {
                        $venues = $venueStmt->fetchAll(PDO::FETCH_ASSOC);
                        $hasBigEvent = false;
                        $hasOpenArea = false;
                        $hasCloseArea = false;
                        
                        // Log all venues being checked
                        error_log("Venues to check (ID - Name - Event Type - Area Type): " . 
                                json_encode(array_map(function($v) { 
                                    return [
                                        'id' => $v['ven_id'],
                                        'name' => $v['ven_name'] ?? 'N/A',
                                        'event_type' => $v['event_type'] ?? 'not set',
                                        'area_type' => $v['area_type'] ?? 'not set'
                                    ]; 
                                }, $venues)));
                        
                        // Check venue types
                        foreach ($venues as $venue) {
                            $venueId = $venue['ven_id'];
                            $eventType = $venue['event_type'] ?? 'not set';
                            $areaType = $venue['area_type'] ?? 'not set';
                            
                            error_log(sprintf("Checking venue (L5/18) - ID: %d, Name: %s, Event Type: %s, Area Type: %s", 
                                $venueId, 
                                $venue['ven_name'] ?? 'N/A',
                                $eventType,
                                $areaType
                            ));
                            
                            if ($eventType === 'Big Event') {
                                $hasBigEvent = true;
                                if ($areaType === 'Open Area') {
                                    $hasOpenArea = true;
                                    error_log(sprintf("FOUND Big Event with Open Area (L5/18) - Venue ID: %d, Name: %s", 
                                        $venueId, $venue['ven_name'] ?? 'N/A'));
                                } elseif ($areaType === 'Close Area') {
                                    $hasCloseArea = true;
                                    error_log(sprintf("FOUND Big Event with Close Area (L5/18) - Venue ID: %d, Name: %s", 
                                        $venueId, $venue['ven_name'] ?? 'N/A'));
                                }
                            } elseif ($eventType === 'Small Event' && $areaType === 'Close Area') {
                                $hasCloseArea = true;
                                error_log(sprintf("FOUND Small Event with Close Area (L5/18) - Venue ID: %d, Name: %s", 
                                    $venueId, $venue['ven_name'] ?? 'N/A'));
                            }
                        }
                        
                        // Insert department approval based on conditions
                        if (($hasBigEvent && $hasOpenArea) || // Big Event AND Open Area
                            ($hasBigEvent && $hasCloseArea) || // Big Event AND Close Area
                            $hasCloseArea) { // Small Event AND Close Area (handled by the same flag)
                            
                            // Get all academic departments except user's own department
                            $departmentsToApprove = [];
                            $userDeptId = isset($userLevel['users_department_id']) ? (int)$userLevel['users_department_id'] : 0;
                            
                            // For Big Event in Open Area - include all academic departments
                            // For user levels 5 and 18, exclude department 29 as they are department heads/deans
                            if ($hasBigEvent && $hasOpenArea) {
                                // Get all academic departments (excluding 48 and 54)
                                $deptQuery = "SELECT departments_id FROM tbl_departments WHERE department_type = 'Academic' AND departments_id NOT IN (48, 54)";
                                $deptStmt = $this->conn->query($deptQuery);
                                
                                if ($deptStmt) {
                                    $departmentsToApprove = $deptStmt->fetchAll(PDO::FETCH_COLUMN);
                                }
                                
                                // For user levels 5 and 18, never include department 29
                                // Also, if user is level 18 with department 28, no approvals needed
                                if ($userLevelId == 18 && isset($userLevel['users_department_id']) && (int)$userLevel['users_department_id'] == 28) {
                                    $departmentsToApprove = [];
                                    error_log("No department approvals needed for user level 18 with department ID 28");
                                }
                                else if (!in_array($userLevelId, [5, 18])) {
                                    // Add department 29 if not already in the list and not the user's department
                                    if (!in_array(29, $departmentsToApprove) && 29 !== $userDeptId) {
                                        $departmentsToApprove[] = 29;
                                    }
                                    
                                    // If no academic departments found and 29 is not the user's department, use 29
                                    if (empty($departmentsToApprove) && 29 !== $userDeptId) {
                                        $departmentsToApprove = [29];
                                    }
                                } else {
                                    error_log("Skipping department 29 for user level $userLevelId (department head/dean)");
                                }
                            } 
                            // For Big Event in Close Area - only department 29, except for user levels 5 and 18
                            elseif ($hasBigEvent && $hasCloseArea) {
                                if (in_array($userLevelId, [5, 18])) {
                                    $departmentsToApprove = [];
                                    error_log("Skipping department 29 for user level $userLevelId (department head/dean)");
                                } elseif (29 !== $userDeptId) {
                                    $departmentsToApprove = [29];
                                } else {
                                    $departmentsToApprove = [];
                                    error_log("Skipping department 29 approval as it's the user's own department");
                                }
                            }
                            // For Small Event in Close Area - no department approvals needed
                            
                            // Include or exclude user's own department based on user level
                            if ((int)$userLevelId === 17) {
                                // For user level 17, ensure the user's own department is included
                                if ($userDeptId > 0 && !in_array((int)$userDeptId, array_map('intval', $departmentsToApprove), true)) {
                                    $departmentsToApprove[] = (int)$userDeptId;
                                }
                            } else {
                                // For other levels, exclude requester's own department from approvals
                                $departmentsToApprove = array_filter($departmentsToApprove, function($deptId) use ($userDeptId) {
                                    return (int)$deptId !== $userDeptId;
                                });
                            }

                            // Deduplicate and normalize department IDs
                            $departmentsToApprove = array_values(array_unique(array_map('intval', $departmentsToApprove)));
                            
                            // Check if venue ID 92 is in the reservation and add additional departments (48 and 54)
                            if (in_array(92, $data['venues'])) {
                                $additionalDepts = [48, 54];
                                foreach ($additionalDepts as $additionalDept) {
                                    if (!in_array($additionalDept, $departmentsToApprove)) {
                                        $departmentsToApprove[] = $additionalDept;
                                        error_log(sprintf("Added additional department %d for venue ID 92 (Levels 5/18)", $additionalDept));
                                    }
                                }
                            }
                            
                            // Insert department approvals if any
                            if (!empty($departmentsToApprove)) {
                                error_log("Inserting department approvals for reservation ID: " . $reservationId . 
                                        ", Departments: " . implode(', ', $departmentsToApprove));
                                
                                foreach ($departmentsToApprove as $deptId) {
                                    $deptApprovalSql = "INSERT INTO tbl_department_approval 
                                                      (department_is_approved, department_approval_department_id, 
                                                      department_user_id, department_updated_at, department_request_reservation_id) 
                                                      VALUES (0, :dept_id, NULL, NULL, :reservation_id)";
                                    $deptApprovalStmt = $this->conn->prepare($deptApprovalSql);
                                    $deptApprovalStmt->bindValue(':dept_id', $deptId, PDO::PARAM_INT);
                                    $deptApprovalStmt->bindValue(':reservation_id', $reservationId, PDO::PARAM_INT);
                                    
                                    if (!$deptApprovalStmt->execute()) {
                                        $error = $deptApprovalStmt->errorInfo();
                                        error_log(sprintf("Failed to create department approval - Department ID: %d, Error: %s", 
                                            $deptId, json_encode($error)));
                                        $this->conn->rollBack();
                                        return ['status' => 'error', 'message' => 'Failed to create department approval record'];
                                    } else {
                                        error_log(sprintf("Successfully created department approval - Department ID: %d, Reservation ID: %d", 
                                            $deptId, $reservationId));
                                    }
                                }
                            } else {
                                error_log("No department approvals needed for this reservation");
                            }
                        } else {
                            error_log("No department approvals needed based on venue types");
                        }
                    } else {
                        error_log("Failed to fetch venue information for reservation ID: " . $reservationId);
                    }
                }
                
                // Status SQL 1 and 2 remain the same
                $statusSql1 = "INSERT INTO tbl_reservation_status 
                              (reservation_reservation_id, reservation_status_status_id, 
                               reservation_active, reservation_users_id, reservation_updated_at) 
                              VALUES (:reservation_id, 1, 0, 114, NOW())";

                $statusSql2 = "INSERT INTO tbl_reservation_status 
                              (reservation_reservation_id, reservation_status_status_id, 
                               reservation_active, reservation_users_id, reservation_updated_at) 
                              VALUES (:reservation_id, 8, 0, 99, NOW())";
    
                $statusStmt1 = $this->conn->prepare($statusSql1);
                $statusStmt1->bindValue(':reservation_id', $reservationId, PDO::PARAM_INT);
                if (!$statusStmt1->execute()) {
                    $this->conn->rollBack();
                    return ['status' => 'error', 'message' => 'Failed to create first reservation status'];
                }
    
                $statusStmt2 = $this->conn->prepare($statusSql2);
                $statusStmt2->bindValue(':reservation_id', $reservationId, PDO::PARAM_INT);
                if (!$statusStmt2->execute()) {
                    $this->conn->rollBack();
                    return ['status' => 'error', 'message' => 'Failed to create second reservation status'];
                }
    
                // Insert notification for waiting for confirmation (keeping existing notification)
                $notificationSql = "INSERT INTO tbl_notification_reservation 
                                  (notification_message, notification_reservation_reservation_id, 
                                   notification_user_id, notification_created_at)
                                  VALUES ('Your reservation is waiting for confirmation', 
                                         :reservation_id, :user_id, NOW())";
                $notificationStmt = $this->conn->prepare($notificationSql);
                $notificationStmt->bindValue(':reservation_id', $reservationId, PDO::PARAM_INT);
                $notificationStmt->bindValue(':user_id', $data['user_id'], PDO::PARAM_INT);
                
                if (!$notificationStmt->execute()) {
                    $this->conn->rollBack();
                    return ['status' => 'error', 'message' => 'Failed to create notification'];
                }
                
                // Audit: Request submitted (user level 5/18 branch)
                try {
                    $desc = ($type === 'venue')
                        ? 'Venue Request submitted'
                        : (($type === 'vehicle')
                            ? 'Vehicle Request submitted'
                            : 'Equipment Request submitted');
                    $action = 'Reservation Request';
                    $created_by = (int)$data['user_id'];

                    $auditSql = "INSERT INTO audit_log (description, action, created_at, created_by) VALUES (:description, :action, NOW(), :created_by)";
                    $auditStmt = $this->conn->prepare($auditSql);
                    $auditStmt->bindParam(':description', $desc, PDO::PARAM_STR);
                    $auditStmt->bindParam(':action', $action, PDO::PARAM_STR);
                    $auditStmt->bindParam(':created_by', $created_by, PDO::PARAM_INT);
                    if (!$auditStmt->execute()) {
                        $err = $auditStmt->errorInfo();
                        error_log('Audit log insert failed (level 5/18 block): ' . json_encode($err));
                    }
                } catch (Exception $e) {
                    // Do not fail the main flow if audit logging fails; just log the error
                    error_log('Audit log insert failed: ' . $e->getMessage());
                }
                
                // Insert notifications directly to tbl_notification_reservation for all department users
                if (!$isGsdSecretary) {
                    $targetDeptId = ($userLevelId == 18) ? 27 : $userLevel['users_department_id'];
                    
                    // Get all users in the target department
                    $deptUsersSql = "SELECT users_id FROM tbl_users WHERE users_department_id = :dept_id AND is_active = 1";
                    $deptUsersStmt = $this->conn->prepare($deptUsersSql);
                    $deptUsersStmt->bindValue(':dept_id', $targetDeptId, PDO::PARAM_INT);
                    
                    if ($deptUsersStmt->execute()) {
                        $deptUsers = $deptUsersStmt->fetchAll(PDO::FETCH_ASSOC);
                        
                        // Insert notification for each user in the department
                        $notifSql = "INSERT INTO tbl_notification_reservation 
                                   (notification_message, notification_reservation_reservation_id, 
                                    notification_user_id, notification_created_at, is_read) 
                                   VALUES ('New Reservation Request', :reservation_id, :user_id, NOW(), 0)";
                        
                        foreach ($deptUsers as $user) {
                            $notifStmt = $this->conn->prepare($notifSql);
                            $notifStmt->bindValue(':reservation_id', $reservationId, PDO::PARAM_INT);
                            $notifStmt->bindValue(':user_id', $user['users_id'], PDO::PARAM_INT);
                            
                            if (!$notifStmt->execute()) {
                                error_log('Failed to insert notification for user ID: ' . $user['users_id']);
                            }
                        }
                    } else {
                        error_log('Failed to fetch department users for notifications');
                    }
                }
    
                // --- SPECIAL NOTIFICATION FOR DEPARTMENT 27, USER LEVEL 1 (ALWAYS) ---
                // Get all users in department 27 with user level 1
                $specialDeptUsersSql = "SELECT users_id FROM tbl_users WHERE users_department_id = 27 AND users_user_level_id = 1 AND is_active = 1";
                $specialDeptUsersStmt = $this->conn->prepare($specialDeptUsersSql);
                
                if ($specialDeptUsersStmt->execute()) {
                    $specialDeptUsers = $specialDeptUsersStmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    // Insert notification for each user in department 27 with level 1
                    $specialNotifSql = "INSERT INTO tbl_notification_reservation 
                                      (notification_message, notification_reservation_reservation_id, 
                                       notification_user_id, notification_created_at, is_read) 
                                      VALUES ('New Reservation Request', :reservation_id, :user_id, NOW(), 0)";
                    
                    foreach ($specialDeptUsers as $user) {
                        $specialNotifStmt = $this->conn->prepare($specialNotifSql);
                        $specialNotifStmt->bindValue(':reservation_id', $reservationId, PDO::PARAM_INT);
                        $specialNotifStmt->bindValue(':user_id', $user['users_id'], PDO::PARAM_INT);
                        
                        if (!$specialNotifStmt->execute()) {
                            error_log('Failed to insert special notification for department 27, level 1 user ID: ' . $user['users_id']);
                        }
                    }
                } else {
                    error_log('Failed to fetch department 27, level 1 users for special notifications');
                }
    
                // --- PUSH NOTIFICATION TO ADMIN LOGIC (for user levels 5, 18) ---
                $pushUserLevel = 1; // Admin user level
                $pushDeptId = null;
    
                if ($userLevelId == 18) {
                    $pushDeptId = 27; // Specific department for VPAA requests
                }

                // If the requester is Secretary from GSD, directly push to Admin in department 27
                try {
                    $roleSql = "SELECT COALESCE(d.departments_name,'') AS dept_name, COALESCE(ul.user_level_name,'') AS level_name
                                FROM tbl_users u
                                LEFT JOIN tbl_departments d ON d.departments_id = u.users_department_id
                                LEFT JOIN tbl_user_level ul ON ul.user_level_id = u.users_user_level_id
                                WHERE u.users_id = :user_id
                                LIMIT 1";
                    $roleStmt = $this->conn->prepare($roleSql);
                    $roleStmt->bindValue(':user_id', $data['user_id'], PDO::PARAM_INT);
                    if ($roleStmt->execute()) {
                        $roleRow = $roleStmt->fetch(PDO::FETCH_ASSOC);
                        $deptName = strtolower(trim($roleRow['dept_name'] ?? ''));
                        $levelName = strtolower(trim($roleRow['level_name'] ?? ''));
                        if ($deptName === 'gsd' && $levelName === 'secretary') {
                            // Ensure push goes to Admins in Department 27
                            $pushDeptId = 27;
                            error_log('Direct admin push to department 27 enabled (GSD Secretary requester).');
                        }
                    }
                } catch (Exception $e) {
                    error_log('Failed to evaluate GSD Secretary direct push: ' . $e->getMessage());
                }
    
                $sqlPushUsers = "SELECT u.users_id
                                    FROM tbl_users u
                                    INNER JOIN tbl_push_subscriptions ps ON u.users_id = ps.user_id
                                    WHERE u.users_user_level_id = :push_user_level
                                    AND ps.is_active = 1";
                
                if ($pushDeptId !== null) {
                    $sqlPushUsers .= " AND u.users_department_id = :push_dept_id";
                }
    
                $stmtPushUsers = $this->conn->prepare($sqlPushUsers);
                $stmtPushUsers->bindValue(':push_user_level', $pushUserLevel, PDO::PARAM_INT);
                if ($pushDeptId !== null) {
                    $stmtPushUsers->bindValue(':push_dept_id', $pushDeptId, PDO::PARAM_INT);
                }
                $stmtPushUsers->execute();
                $pushUsers = $stmtPushUsers->fetchAll(PDO::FETCH_ASSOC);
    
                error_log("Found " . count($pushUsers) . " admin users for push notification (level 5/18 request).");
    
                // Old push notification code removed - now handled by consolidated method
            } else {
                // Default case: set as pending with active 1
                $statusSql = "INSERT INTO tbl_reservation_status 
                              (reservation_reservation_id, reservation_status_status_id, 
                               reservation_active, reservation_users_id, reservation_updated_at) 
                              VALUES (:reservation_id, 1, 1, null, NOW())";
                               
                $statusStmt = $this->conn->prepare($statusSql);
                $statusStmt->bindValue(':reservation_id', $reservationId, PDO::PARAM_INT);
                
                if (!$statusStmt->execute()) {
                    $this->conn->rollBack();
                    return ['status' => 'error', 'message' => 'Failed to create reservation status'];
                }
                
                // --- SPECIAL NOTIFICATION FOR DEPARTMENT 27, USER LEVEL 1 (ALWAYS) ---
                // Get all users in department 27 with user level 1
                $specialDeptUsersSql = "SELECT users_id FROM tbl_users WHERE users_department_id = 27 AND users_user_level_id = 1 AND is_active = 1";
                $specialDeptUsersStmt = $this->conn->prepare($specialDeptUsersSql);
                
                if ($specialDeptUsersStmt->execute()) {
                    $specialDeptUsers = $specialDeptUsersStmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    // Insert notification for each user in department 27 with level 1
                    $specialNotifSql = "INSERT INTO tbl_notification_reservation 
                                      (notification_message, notification_reservation_reservation_id, 
                                       notification_user_id, notification_created_at, is_read) 
                                      VALUES ('New Reservation Request', :reservation_id, :user_id, NOW(), 0)";
                    
                    foreach ($specialDeptUsers as $user) {
                        $specialNotifStmt = $this->conn->prepare($specialNotifSql);
                        $specialNotifStmt->bindValue(':reservation_id', $reservationId, PDO::PARAM_INT);
                        $specialNotifStmt->bindValue(':user_id', $user['users_id'], PDO::PARAM_INT);
                        
                        if (!$specialNotifStmt->execute()) {
                            error_log('Failed to insert special notification for department 27, level 1 user ID: ' . $user['users_id']);
                        }
                    }
                } else {
                    error_log('Failed to fetch department 27, level 1 users for special notifications');
                }
            }
    
            $this->conn->commit();
            
            // Send push notifications after successful reservation creation
            try {
                // Get reservation details for push notifications
                $reservationDetails = [
                    'reservation_user_id' => $data['user_id'],
                    'reservation_title' => $data['title'] ?? $data['destination'] ?? 'New Reservation',
                    'requester_name' => 'User'
                ];
                
                // Get requester name
                $sqlRequester = "SELECT CONCAT(users_fname, ' ', users_mname, ' ', users_lname) AS full_name 
                               FROM tbl_users WHERE users_id = :user_id";
                $stmtRequester = $this->conn->prepare($sqlRequester);
                $stmtRequester->bindParam(':user_id', $data['user_id'], PDO::PARAM_INT);
                $stmtRequester->execute();
                $requesterInfo = $stmtRequester->fetch(PDO::FETCH_ASSOC);
                if ($requesterInfo) {
                    $reservationDetails['requester_name'] = $requesterInfo['full_name'];
                }
                
                // Send push notification to admins based on requester's user level and department
                $this->sendPushNotificationToAdmins($reservationDetails, 'pending', 'New Reservation Request', 'A new reservation request has been submitted and requires your attention.', [
                    'reservation_id' => $reservationId,
                    'type' => 'new_reservation',
                    'action_url' => '/reservation/Admin/viewRequest'
                ]);
                
                // Send push notification to department approval users
                $this->sendPushNotificationToDepartmentApproval($reservationId);
                
                // Send push notification for successful reservation creation to requester
                $this->sendSuccessfulReservationPushNotification($reservationId, $data, $type);
                
            } catch (Exception $e) {
                error_log("Error sending push notifications after reservation creation: " . $e->getMessage());
                // Don't fail the reservation creation if push notifications fail
            }
            
            return ['status' => 'success', 'reservation_id' => $reservationId];

        } catch (Exception $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            return ['status' => 'error', 'message' => $e->getMessage()];
        } finally {
            // Always release locks
            if (!empty($acquired)) {
                foreach ($acquired as $ak) { $this->releaseLock($ak); }
            }
        }
    }
    
    // Send push notification to admins based on requester's user level and department
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
            
            $pushUrl = 'http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']) . '/server/send-push-notification.php';
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

    // Send push notification to department approval users
    private function sendPushNotificationToDepartmentApproval($reservationId) {
        try {
            // Get all department approvals for this reservation
            $sqlDeptApprovals = "SELECT department_approval_department_id 
                               FROM tbl_department_approval 
                               WHERE department_request_reservation_id = :reservation_id 
                               AND department_is_approved = 0";
            $stmtDeptApprovals = $this->conn->prepare($sqlDeptApprovals);
            $stmtDeptApprovals->bindParam(':reservation_id', $reservationId, PDO::PARAM_INT);
            $stmtDeptApprovals->execute();
            $deptApprovals = $stmtDeptApprovals->fetchAll(PDO::FETCH_ASSOC);
            
            if (empty($deptApprovals)) {
                error_log("No department approvals found for reservation ID: " . $reservationId);
                return;
            }
            
            // Get reservation details for notification
            $sqlReservation = "SELECT r.reservation_title, 
                                    CONCAT(u.users_fname, ' ', u.users_mname, ' ', u.users_lname) AS requester_name
                             FROM tbl_reservation r
                             LEFT JOIN tbl_users u ON r.reservation_user_id = u.users_id
                             WHERE r.reservation_id = :reservation_id";
            $stmtReservation = $this->conn->prepare($sqlReservation);
            $stmtReservation->bindParam(':reservation_id', $reservationId, PDO::PARAM_INT);
            $stmtReservation->execute();
            $reservation = $stmtReservation->fetch(PDO::FETCH_ASSOC);
            
            if (!$reservation) {
                error_log("Could not find reservation details for ID: " . $reservationId);
                return;
            }
            
            $allDeptUsers = [];
            
            // Get users from each department that needs approval
            foreach ($deptApprovals as $deptApproval) {
                $deptId = $deptApproval['department_approval_department_id'];
                
                // Get department heads (level 5) and deans (level 16) from the department
                $sqlDeptUsers = "SELECT 
                                    u.users_id,
                                    CONCAT(u.users_fname, ' ', u.users_mname, ' ', u.users_lname) AS full_name,
                                    ps.subscription_id,
                                    ps.endpoint,
                                    ps.p256dh_key,
                                    ps.auth_key,
                                    d.departments_name
                                FROM tbl_users u
                                INNER JOIN tbl_push_subscriptions ps ON u.users_id = ps.user_id
                                LEFT JOIN tbl_departments d ON u.users_department_id = d.departments_id
                                WHERE u.users_department_id = :dept_id 
                                AND u.users_user_level_id IN (5, 16)
                                AND ps.is_active = 1";
                
                $stmtDeptUsers = $this->conn->prepare($sqlDeptUsers);
                $stmtDeptUsers->bindParam(':dept_id', $deptId, PDO::PARAM_INT);
                $stmtDeptUsers->execute();
                $deptUsers = $stmtDeptUsers->fetchAll(PDO::FETCH_ASSOC);
                
                $allDeptUsers = array_merge($allDeptUsers, $deptUsers);
            }
            
            if (empty($allDeptUsers)) {
                error_log("No department users with push subscriptions found for reservation ID: " . $reservationId);
                return;
            }
            
            // Prepare notification data
            $title = "Department Approval Required";
            $body = "A reservation '{$reservation['reservation_title']}' by {$reservation['requester_name']} requires your department's approval.";
            $data = [
                'reservation_id' => $reservationId,
                'type' => 'department_approval',
                'action' => 'view_approval',
                'url' => '/reservation/Department/viewApproval',
                'notification_id' => uniqid('dept_', true),
                'timestamp' => time(),
                'recipient_type' => 'department_approval'
            ];
            
            $pushUrl = 'http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']) . '/server/send-push-notification.php';
            $successCount = 0;
            $errorCount = 0;
            
            // Send push notification to all department users
            foreach ($allDeptUsers as $user) {
                $pushData = [
                    'operation' => 'send',
                    'user_id' => $user['users_id'],
                    'title' => $title,
                    'body' => $body,
                    'data' => $data
                ];
                
                error_log("Sending department approval push notification to user {$user['users_id']} in department {$user['departments_name']}: " . json_encode($pushData));
                
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
                
                error_log("Department approval push notification response for user {$user['users_id']}: HTTP $httpCode, Response: $response");
                
                if ($error || $httpCode < 200 || $httpCode >= 300) {
                    error_log("Push notification failed for department user {$user['users_id']}: " . ($error ?: "HTTP $httpCode"));
                    $errorCount++;
                } else {
                    $successCount++;
                }
            }

            // Also send notifications to admin users (for view request)
            $sqlAdminUsers = "SELECT 
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
            $stmtAdminUsers = $this->conn->prepare($sqlAdminUsers);
            $stmtAdminUsers->execute();
            $adminUsers = $stmtAdminUsers->fetchAll(PDO::FETCH_ASSOC);

            // Send notifications to admin users with different action URL
            if (!empty($adminUsers)) {
                $adminTitle = "New Reservation Request";
                $adminBody = "A reservation '{$reservation['reservation_title']}' by {$reservation['requester_name']} requires admin review.";
                $adminData = [
                    'reservation_id' => $reservationId,
                    'type' => 'reservation_admin_review',
                    'action' => 'view_request',
                    'url' => '/reservation/Admin/viewRequest',
                    'notification_id' => uniqid('admin_', true),
                    'timestamp' => time(),
                    'recipient_type' => 'admin'
                ];

                foreach ($adminUsers as $user) {
                    $pushData = [
                        'operation' => 'send',
                        'user_id' => $user['users_id'],
                        'title' => $adminTitle,
                        'body' => $adminBody,
                        'data' => $adminData
                    ];
                    
                    error_log("Sending admin review push notification to user {$user['users_id']}: " . json_encode($pushData));
                    
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
                    
                    error_log("Admin review push notification response for user {$user['users_id']}: HTTP $httpCode, Response: $response");
                    
                    if ($error || $httpCode < 200 || $httpCode >= 300) {
                        error_log("Push notification failed for admin user {$user['users_id']}: " . ($error ?: "HTTP $httpCode"));
                        $errorCount++;
                    } else {
                        $successCount++;
                    }
                }
            }

            error_log("Department approval push notifications sent: $successCount successful, $errorCount failed");
            error_log("Notified " . count($allDeptUsers) . " department users and " . count($adminUsers) . " admin users");
            
        } catch (Exception $e) {
            error_log("Error sending department approval notifications: " . $e->getMessage());
        }
    }

    // Helper method to send individual push notification
    private function sendPushNotificationHelper($pushUrl, $pushData, $userId, &$successCount, &$errorCount) {
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
        
        if ($error || $httpCode < 200 || $httpCode >= 300) {
            error_log("Push notification failed for user $userId: " . ($error ?: "HTTP $httpCode"));
            $errorCount++;
        } else {
            error_log("Push notification sent successfully to user $userId");
            $successCount++;
        }
    }

    // Send push notification for successful reservation creation
    private function sendSuccessfulReservationPushNotification($reservationId, $data, $type) {
        try {
            // ... (rest of the code remains the same)
            // Get the requester's information
            $userSql = "SELECT u.users_id, u.users_fname, u.users_lname, u.users_department_id,
                              d.departments_name, ul.user_level_name
                        FROM tbl_users u
                        LEFT JOIN tbl_departments d ON d.departments_id = u.users_department_id
                        LEFT JOIN tbl_user_level ul ON ul.user_level_id = u.users_user_level_id
                        WHERE u.users_id = :user_id
                        LIMIT 1";
            $userStmt = $this->conn->prepare($userSql);
            $userStmt->bindValue(':user_id', $data['user_id'], PDO::PARAM_INT);
            
            if (!$userStmt->execute()) {
                error_log('Failed to fetch user information for push notification');
                return;
            }
            
            $userInfo = $userStmt->fetch(PDO::FETCH_ASSOC);
            if (!$userInfo) {
                error_log('User not found for push notification');
                return;
            }
            
            $requesterName = trim($userInfo['users_fname'] . ' ' . $userInfo['users_lname']);
            $department = $userInfo['departments_name'] ?? 'Unknown Department';
            
            // Determine notification recipients based on reservation type and user level
            $recipientsSql = "SELECT DISTINCT u.users_id
                             FROM tbl_users u
                             INNER JOIN tbl_push_subscriptions ps ON u.users_id = ps.user_id
                             WHERE ps.is_active = 1
                             AND u.is_active = 1
                             AND (
                                 -- Send to GSD department (ID 27) admins (level 1)
                                 (u.users_department_id = 27 AND u.users_user_level_id = 1)
                                 OR
                                 -- Send to department heads/deans (levels 5, 6) in requester's department
                                 (u.users_department_id = :user_dept_id AND u.users_user_level_id IN (5, 6))
                             )";
            
            $recipientsStmt = $this->conn->prepare($recipientsSql);
            $recipientsStmt->bindValue(':user_dept_id', $userInfo['users_department_id'] ?? 0, PDO::PARAM_INT);
            $recipientsStmt->execute();
            $recipients = $recipientsStmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (empty($recipients)) {
                error_log('No push notification recipients found for successful reservation');
                return;
            }
            
            // Prepare notification content
            $typeLabel = ucfirst($type);
            $pushTitle = 'Reservation Created Successfully';
            $pushBody = "New {$typeLabel} reservation by {$requesterName} from {$department}";
            $pushData = [
                'reservation_id' => $reservationId,
                'type' => 'reservation_created',
                'reservation_type' => $type,
                'requester_name' => $requesterName,
                'department' => $department
            ];
            
            $pushUrl = 'http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']) . '/server/send-push-notification.php';
            
            $successCount = 0;
            $errorCount = 0;
            
            foreach ($recipients as $recipient) {
                $pushPayload = [
                    'operation' => 'send',
                    'user_id' => $recipient['users_id'],
                    'title' => $pushTitle,
                    'body' => $pushBody,
                    'data' => $pushData
                ];
                
                error_log("Sending successful reservation push notification to user {$recipient['users_id']}: " . json_encode($pushPayload));
                
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $pushUrl);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($pushPayload));
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Content-Type: application/json',
                    'Content-Length: ' . strlen(json_encode($pushPayload))
                ]);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 10);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                
                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $error = curl_error($ch);
                curl_close($ch);
                
                error_log("Successful reservation push notification response for user {$recipient['users_id']}: HTTP $httpCode, Response: $response");
                
                if ($error || $httpCode < 200 || $httpCode >= 300) {
                    error_log("Successful reservation push notification failed for user {$recipient['users_id']}: " . ($error ?: "HTTP $httpCode"));
                    $errorCount++;
                } else {
                    error_log("Successful reservation push notification sent to user {$recipient['users_id']}");
                    $successCount++;
                }
            }
            
            error_log("Successful reservation push notifications sent: $successCount successful, $errorCount failed");
            
        } catch (Exception $e) {
            error_log('Exception in sendSuccessfulReservationPushNotification: ' . $e->getMessage());
        }
    }

    public function commit() {
        if ($this->conn->inTransaction()) {
            return $this->conn->commit();
        }
        return true;  // No transaction to commit
    }
    
    public function rollBack() {
        if ($this->conn->inTransaction()) {
            return $this->conn->rollBack();
        }
        return true;  // No transaction to roll back
    }
}



if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        $input = $_POST;
    }

    $operation = $input['operation'] ?? '';
    $userId = $input['userId'] ?? null;
    $facultyStaff = new FacultyStaff();

    switch ($operation) {

        case "handleCancelReservation":
            $reservationId = $input['reservation_id'] ?? null;
            if ($reservationId === null) {
                echo json_encode(['status' => 'error', 'message' => 'Reservation ID is required']);
                break;
            }
            echo $facultyStaff->handleCancelReservation($reservationId, $userId);
            break;

        case "updateReservationReschedule":
            $reservation_id = $input['reservation_id'] ?? ($_POST['reservation_id'] ?? null);
            $reschedule_start_date = $input['reschedule_start_date'] ?? ($_POST['reschedule_start_date'] ?? null);
            $reschedule_end_date = $input['reschedule_end_date'] ?? ($_POST['reschedule_end_date'] ?? null);
            $user_admin_id = $input['user_admin_id'] ?? ($_POST['user_admin_id'] ?? null);
            if ($reservation_id === null || $reschedule_start_date === null || $reschedule_end_date === null) {
                echo json_encode(['status' => 'error', 'message' => 'reservation_id, reschedule_start_date, and reschedule_end_date are required']);
                break;
            }
            echo $facultyStaff->updateReservationReschedule($reservation_id, $reschedule_start_date, $reschedule_end_date, $user_admin_id);
            break;


        case "updateVenueReschedule":
            $reservation_venue_id = $input['reservation_venue_id'] ?? ($_POST['reservation_venue_id'] ?? null);
            $reservation_change_venue_id = $input['reservation_change_venue_id'] ?? ($_POST['reservation_change_venue_id'] ?? null);
            if ($reservation_venue_id === null) {
                echo json_encode(['status' => 'error', 'message' => 'reservation_venue_id is required']);
                break;
            }
            echo $facultyStaff->updateVenueReschedule($reservation_venue_id, $reservation_change_venue_id);
            break;

        case "updateVehicleReschedule":
            $reservation_vehicle_id = $input['reservation_vehicle_id'] ?? ($_POST['reservation_vehicle_id'] ?? null);
            $reservation_change_vehicle_id = $input['reservation_change_vehicle_id'] ?? ($_POST['reservation_change_vehicle_id'] ?? null);
            if ($reservation_vehicle_id === null) {
                echo json_encode(['status' => 'error', 'message' => 'reservation_vehicle_id is required']);
                break;
            }
            echo $facultyStaff->updateVehicleReschedule($reservation_vehicle_id, $reservation_change_vehicle_id);
            break;
        case 'fetchDeansApproval':
            if (!$reservationId) {
                echo json_encode(['status' => 'error', 'message' => 'Reservation ID is required']);
                break;
            }
            echo $facultyStaff->fetchDeansApproval($reservationId);
            break;
        case 'fetchMyReservation':
            if (!$userId) {
                echo json_encode(['status' => 'error', 'message' => 'User ID is required']);
                break;
            }
            echo $facultyStaff->fetchMyReservation($userId);
            break;
        case 'fetchMyReservationbyId':
            if(!$reservationId = $input['reservationId'] ?? null) {
                echo json_encode(['status' => 'error', 'message' => 'Reservation ID is required']);
                break;
            }
            echo $facultyStaff->fetchMyReservationById($reservationId);
            break;
        case 'fetchStatusById':
            if(!$reservationId = $input['reservationId'] ?? null) {
                echo json_encode(['status' => 'error', 'message' => 'Reservation ID is required']);
                break;
            }
            echo $facultyStaff->fetchStatusById($reservationId);
            break;
        case 'displayedMaintenanceResources':
            if(!$reservationId = $input['reservationId'] ?? null) {
                echo json_encode(['status' => 'error', 'message' => 'Reservation ID is required']);
                break;
            }
            echo $facultyStaff->displayedMaintenanceResources($reservationId);
            break;
        case 'fetchMyActiveReservation':
            if (!$userId) {
                echo json_encode(['status' => 'error', 'message' => 'User ID is required']);
                break;
            }
            echo $facultyStaff->fetchMyActiveReservation($userId);
            break;
        case 'fetchNotification':
            if (!$userId) {
                echo json_encode(['status' => 'error', 'message' => 'User ID is required']);
                break;
            }
            echo $facultyStaff->fetchNotification($userId);
            break;
        case 'updateReadNotification':
            if(!$notificationIds = $input['notificationIds'] ?? null) {
                echo json_encode(['status' => 'error', 'message' => 'Notification IDs are required']);
                break;
            }
            echo $facultyStaff->updateReadNotification($notificationIds, $userId);
            break;
        case 'updateReschedule':
            $reservationId = $input['reservationId'] ?? null;
            // Accept either explicit active (1 or -1) or confirm boolean/string
            $active = null;
            if (isset($input['active'])) {
                $active = (int)$input['active'];
            } elseif (isset($input['confirm'])) {
                $confirm = $input['confirm'];
                // Handle boolean, numeric, or string values
                $truthy = [true, 1, '1', 'true', 'TRUE', 'confirm', 'CONFIRM', 'yes', 'YES'];
                $active = in_array($confirm, $truthy, true) ? 1 : -1;
            }
            if (!$reservationId || !in_array($active, [1, -1], true)) {
                echo json_encode(['status' => 'error', 'message' => 'reservationId and active (1 or -1) or confirm flag are required']);
                break;
            }
            echo $facultyStaff->updateReschedule((int)$reservationId, (int)$active, $userId !== null ? (int)$userId : null);
            break;
            case 'venuereservation':
                try {
                    // Validate required fields for venue
                    if (!isset($input['form_data']['title']) || 
                        !isset($input['form_data']['description']) || 
                        !isset($input['form_data']['start_date']) || 
                        !isset($input['form_data']['end_date']) || 
                        !isset($input['form_data']['participants']) || 
                        !isset($input['form_data']['user_id']) ||
                        !isset($input['form_data']['venues']) ||  // Changed from venue_id to venues
                        !isset($input['form_data']['equipment'])) {
                        throw new Exception('Missing required fields for venue reservation');
                    }
    
                    // Validate venues array
                    if (!is_array($input['form_data']['venues'])) {
                        throw new Exception('Venues must be an array');
                    }
    
                    // Validate equipment array structure
                    if (!is_array($input['form_data']['equipment'])) {
                        throw new Exception('Equipment must be an array');
                    }
    
                    foreach ($input['form_data']['equipment'] as $equipment) {
                        if (!isset($equipment['equipment_id']) || !isset($equipment['quantity'])) {
                            throw new Exception('Each equipment must have equipment_id and quantity');
                        }
                    }
            
                    $input['form_data']['type'] = 'venue';
                    $reservationResult = $facultyStaff->createReservation($input['form_data'], 'venue');
                    echo json_encode($reservationResult);
                    
                } catch (Exception $e) {
                    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
                }
                break;
    
            case 'vehiclereservation':
            case 'vehicleReservation':
                try {
                    // Validate required fields for vehicle
                    if (!isset($input['form_data']['destination']) || 
                        !isset($input['form_data']['purpose']) || 
                        !isset($input['form_data']['start_date']) || 
                        !isset($input['form_data']['end_date']) || 
                        !isset($input['form_data']['user_id']) ||
                        !isset($input['form_data']['vehicles']) ||
                        !isset($input['form_data']['passengers'])) {
                        throw new Exception('Missing required fields for vehicle reservation');
                    }
    
                    // Validate arrays
                    if (!is_array($input['form_data']['vehicles'])) {
                        throw new Exception('Vehicles must be an array');
                    }
    
                    if (!is_array($input['form_data']['passengers'])) {
                        throw new Exception('Passengers must be an array');
                    }
    
                    // Validate equipment array if present
                    if (isset($input['form_data']['equipment'])) {
                        if (!is_array($input['form_data']['equipment'])) {
                            throw new Exception('Equipment must be an array');
                        }
                        foreach ($input['form_data']['equipment'] as $equipment) {
                            if (!isset($equipment['equipment_id']) || !isset($equipment['quantity'])) {
                                throw new Exception('Each equipment must have equipment_id and quantity');
                            }
                        }
                    }
            
                    $input['form_data']['type'] = 'vehicle';
                    $reservationResult = $facultyStaff->createReservation($input['form_data'], 'vehicle');
                    echo json_encode($reservationResult);
                    
                } catch (Exception $e) {
                    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
                }
                break;
    
            case 'equipmentreservation':
                try {
                    // Validate required fields for equipment
                    if (!isset($input['form_data']['title']) || 
                        !isset($input['form_data']['description']) || 
                        !isset($input['form_data']['start_date']) || 
                        !isset($input['form_data']['end_date']) || 
                        !isset($input['form_data']['user_id']) ||
                        !isset($input['form_data']['equipment'])) {
                        throw new Exception('Missing required fields for equipment reservation');
                    }
    
                    // Validate equipment array structure
                    if (!is_array($input['form_data']['equipment'])) {
                        throw new Exception('Equipment must be an array');
                    }
    
                    foreach ($input['form_data']['equipment'] as $equipment) {
                        if (!isset($equipment['equipment_id']) || !isset($equipment['quantity'])) {
                            throw new Exception('Each equipment must have equipment_id and quantity');
                        }
                    }
    
                    $input['form_data']['type'] = 'equipment';
                    $reservationResult = $facultyStaff->createReservation($input['form_data'], 'equipment');
                    echo json_encode($reservationResult);
                    
                } catch (Exception $e) {
                    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
                }
                break;
    
            case "updateNotification":
                if (isset($input['notification_id'])) {
                    $updateResponse = $facultyStaff->updateNotification($input['notification_id']);
                    echo json_encode($updateResponse);
                } else {
                    echo json_encode(['status' => 'error', 'message' => 'Missing notification ID.']);
                }
                break;
            case "deanApproval":
                if (isset($input['dept_id'], $input['reservation_id'], $input['is_approved'])) {
                    $approvalResponse = $reservation->insertApproval(
                        $input['dept_id'], // Changed from dean_id to dept_id
                        $input['reservation_id'],
                        $input['is_approved']
                    );
                    echo json_encode($approvalResponse);
                } else {
                    echo json_encode(['status' => 'error', 'message' => 'Missing required fields for approval.']);
                }
                break;
            default:
                echo json_encode([
                    'status' => 'error', 
                    'message' => 'Invalid operation: ' . $input['operation'],
                    'valid_operations' => ['venuereservation', 'vehicleReservation', 'vehiclereservation', 'equipmentreservation']
                ]);
                break;
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
}
?>