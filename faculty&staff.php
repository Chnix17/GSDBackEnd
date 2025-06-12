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

    public function fetchMyReservation($userId) {
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
                            venue.ven_operating_hours, ':',
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
                        if (count($venueParts) >= 5) {
                            $venues[] = [
                                'venue_id' => $venueParts[0],
                                'venue_name' => $venueParts[1],
                                'occupancy' => $venueParts[2],
                                'operating_hours' => $venueParts[3],
                                'picture' => $venueParts[4]
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

    public function updateReadNotification($notificationIds) {
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
                return json_encode([
                    'status' => 'success',
                    'message' => 'Notifications marked as read',
                    'updated_count' => $stmt->rowCount()
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
            echo $facultyStaff->updateReadNotification($notificationIds);
            break;
        default:
            echo json_encode(['status' => 'error', 'message' => 'Invalid operation']);
            break;
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
}
?>