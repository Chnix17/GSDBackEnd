<?php
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

class Department_Dean {
    private $conn;

    public function __construct() {
        include 'connection-pdo.php';
        $this->conn = $conn;
    }
    
    /**
     * Fetch department approval data by reservation ID
     * @param int $reservationId The ID of the reservation
     * @return string JSON encoded response with department approval data
     */
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

    public function fetchApprovalByDept(int $departmentId, int $userLevelId, int $currentUserId): string
{
    if (!$departmentId || !$userLevelId) {
        return json_encode([
            'status'  => 'error',
            'message' => 'Department ID and User Level ID are required'
        ]);
    }

    try {
        $sql = "
            SELECT 
                r.reservation_id,
                r.reservation_created_at,
                r.reservation_title,
                r.reservation_description,
                r.reservation_start_date,
                r.reservation_end_date,
                r.reservation_participants,
                r.reservation_user_id,
                r.additional_note,
                rs.reservation_status_status_id    AS status_id,
                rs.reservation_active              AS active,
                u_req.users_user_level_id          AS user_level_id,
                CONCAT_WS(' ',
                    u_req.users_fname,
                    u_req.users_mname,
                    u_req.users_lname
                )                                    AS requester_name,
                dep.departments_name               AS department_name,
                ul.user_level_name                  AS user_level_name,
                da.department_approval_id,
                da.department_is_approved,
                da.department_user_id,
                da.department_updated_at,

                -- Venues
                GROUP_CONCAT(DISTINCT
                    CONCAT_WS(':',
                        v.reservation_venue_venue_id,
                        venue.ven_name,
                        venue.ven_occupancy,
                        IFNULL(venue.ven_pic,'')
                    )
                ) AS venue_data,

                -- Vehicles
                GROUP_CONCAT(DISTINCT
                    CONCAT_WS(':',
                        ve.reservation_vehicle_vehicle_id,
                        ve.reservation_vehicle_id,
                        vm.vehicle_license,
                        vmm.vehicle_model_name
                    )
                ) AS vehicle_data,

                -- Equipment
                GROUP_CONCAT(DISTINCT
                    CONCAT_WS(':',
                        e.reservation_equipment_equip_id,
                        equip.equip_name,
                        e.reservation_equipment_quantity
                    )
                ) AS equipment_data,

                -- Passengers
                GROUP_CONCAT(DISTINCT
                    CONCAT_WS(':',
                        p.reservation_passenger_id,
                        p.reservation_passenger_name
                    )
                ) AS passenger_data

            FROM tbl_department_approval da
            INNER JOIN tbl_reservation r 
                ON da.department_request_reservation_id = r.reservation_id
            LEFT JOIN tbl_users u_req 
                ON r.reservation_user_id = u_req.users_id
            LEFT JOIN tbl_departments dep 
                ON u_req.users_department_id = dep.departments_id
            LEFT JOIN tbl_reservation_status rs 
                ON r.reservation_id = rs.reservation_reservation_id
            LEFT JOIN tbl_reservation_venue v 
                ON r.reservation_id = v.reservation_reservation_id
            LEFT JOIN tbl_venue venue 
                ON v.reservation_venue_venue_id = venue.ven_id
            LEFT JOIN tbl_reservation_vehicle ve 
                ON r.reservation_id = ve.reservation_reservation_id
            LEFT JOIN tbl_vehicle vm 
                ON ve.reservation_vehicle_vehicle_id = vm.vehicle_id
            LEFT JOIN tbl_user_level ul 
                ON u_req.users_user_level_id = ul.user_level_id
            LEFT JOIN tbl_vehicle_model vmm 
                ON vm.vehicle_model_id = vmm.vehicle_model_id
            LEFT JOIN tbl_reservation_equipment e 
                ON r.reservation_id = e.reservation_reservation_id
            LEFT JOIN tbl_equipments equip 
                ON e.reservation_equipment_equip_id = equip.equip_id
            LEFT JOIN tbl_reservation_passenger p 
                ON r.reservation_id = p.reservation_reservation_id

            WHERE
                da.department_approval_department_id = :department_id
                AND da.department_is_approved = 0
                AND (
                    :user_level_id <> 6 OR r.reservation_user_id <> :current_user_id
                )
                
            GROUP BY r.reservation_id, da.department_approval_id
        ";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            'department_id'   => $departmentId,
            'user_level_id'   => $userLevelId,
            'current_user_id' => $currentUserId
        ]);

        $result = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            // Decode concatenated fields into structured arrays...
            $venues     = [];
            $vehicles   = [];
            $equipment  = [];
            $drivers    = [];
            $passengers = [];

            if (!empty($row['venue_data'])) {
                foreach (explode(',', $row['venue_data']) as $str) {
                    list($vid,$vname,$occ,$hours,$pic) = explode(':', $str) + [null,null,null,null,null];
                    $venues[] = [
                        'venue_id'        => $vid,
                        'venue_name'      => $vname,
                        'occupancy'       => $occ,
                        'operating_hours' => $hours,
                        'picture'         => $pic
                    ];
                }
            }

            if (!empty($row['vehicle_data'])) {
                foreach (explode(',', $row['vehicle_data']) as $str) {
                    list($vehicle_vehicle_id, $reservation_vehicle_id, $license, $model) = explode(':', $str) + [null,null,null,null];
                    $vehicles[] = [
                        'vehicle_vehicle_id'      => $vehicle_vehicle_id,
                        'reservation_vehicle_id'  => $reservation_vehicle_id,
                        'license'                 => $license,
                        'model'                   => $model
                    ];
                }
            }

            if (!empty($row['equipment_data'])) {
                foreach (explode(',', $row['equipment_data']) as $str) {
                    list($eid,$ename,$qty) = explode(':', $str) + [null,null,null];
                    $equipment[] = [
                        'equipment_id' => $eid,
                        'name'         => $ename,
                        'quantity'     => $qty
                    ];
                }
            }

            // Fetch all drivers for this reservation (with fallback to driver_name if user_id is null)
            $driverStmt = $this->conn->prepare(
                "SELECT rd.reservation_driver_id, rd.reservation_driver_user_id, rd.reservation_vehicle_id, rd.is_accepted_trip, rd.driver_name, rd.created_at, rd.updated_at, u.users_fname, u.users_mname, u.users_lname, u.users_suffix
                 FROM tbl_reservation_driver rd
                 JOIN tbl_reservation_vehicle rv ON rd.reservation_vehicle_id = rv.reservation_vehicle_id
                 LEFT JOIN tbl_users u ON rd.reservation_driver_user_id = u.users_id
                 WHERE rv.reservation_reservation_id = :reservation_id"
            );
            $driverStmt->execute([':reservation_id' => $row['reservation_id']]);
            while ($driverRow = $driverStmt->fetch(PDO::FETCH_ASSOC)) {
                if (empty($driverRow['reservation_driver_user_id'])) {
                    $driverName = $driverRow['driver_name'];
                } else {
                    $driverName = trim(
                        $driverRow['users_fname'] . ' ' .
                        ($driverRow['users_mname'] ? $driverRow['users_mname'] . ' ' : '') .
                        $driverRow['users_lname'] .
                        ($driverRow['users_suffix'] ? ' ' . $driverRow['users_suffix'] : '')
                    );
                }
                $drivers[] = [
                    'reservation_driver_id' => $driverRow['reservation_driver_id'],
                    'driver_id' => $driverRow['reservation_driver_user_id'],
                    'name' => $driverName,
                    'created_at' => $driverRow['created_at'],
                    'updated_at' => $driverRow['updated_at'],
                    'reservation_vehicle_id' => $driverRow['reservation_vehicle_id'],
                    'assigned_vehicle' => null // You can add vehicle details if needed
                ];
            }

            if (!empty($row['passenger_data'])) {
                foreach (explode(',', $row['passenger_data']) as $str) {
                    list($pid,$pname) = explode(':', $str) + [null,null];
                    $passengers[] = [
                        'passenger_id' => $pid,
                        'name'         => $pname
                    ];
                }
            }

            $result[] = [
                'reservation_id'           => $row['reservation_id'],
                'reservation_created_at'   => $row['reservation_created_at'],
                'reservation_title'        => $row['reservation_title'],
                'reservation_description'  => $row['reservation_description'],
                'reservation_start_date'   => $row['reservation_start_date'],
                'reservation_end_date'     => $row['reservation_end_date'],
                'reservation_participants' => $row['reservation_participants'],
                'reservation_user_id'      => $row['reservation_user_id'],
                'additional_note'          => $row['additional_note'],
                'user_level_id'            => $row['user_level_id'],
                'status_id'                => $row['status_id'],
                'active'                   => $row['active'],
                'requester_name'           => $row['requester_name'],
                'department_name'          => $row['department_name'],
                'user_level_name'          => $row['user_level_name'],
                'department_approval_id'   => $row['department_approval_id'],
                'department_is_approved'   => $row['department_is_approved'],
                'department_user_id'       => $row['department_user_id'],
                'department_updated_at'    => $row['department_updated_at'],
                'venues'                   => $venues,
                'vehicles'                 => $vehicles,
                'equipment'                => $equipment,
                'drivers'                  => $drivers,
                'passengers'               => $passengers
            ];
        }

        return json_encode([
            'status' => 'success',
            'data'   => $result
        ]);
    } catch (PDOException $e) {
        error_log("Database error: " . $e->getMessage());
        return json_encode([
            'status'  => 'error',
            'message' => 'Database error: ' . $e->getMessage()
        ]);
    }
}


    

    

   

    public function uploadClassroomJSON($school_year_name, $semesterName, $semesterStart, $semesterEnd, $csvData) {
        if (!is_array($csvData)) {
            return json_encode(['status' => 'error', 'message' => 'Invalid or missing csv_data']);
        }

        $dayMap = [
            'mon' => 'Monday', 'tue' => 'Tuesday', 'wed' => 'Wednesday',
            'thu' => 'Thursday', 'fri' => 'Friday', 'sat' => 'Saturday', 'sun' => 'Sunday'
        ];

        // --- School Year ---
        // ✅ FIXED VERSION
            $stmt = $this->conn->prepare("SELECT school_year_id FROM tbl_school_year WHERE school_year_name = ?");
            $stmt->execute([$school_year_name]);
            $sy = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$sy) {
                $insert = $this->conn->prepare("INSERT INTO tbl_school_year (school_year_name) VALUES (?)");
                $insert->execute([$school_year_name]);
                $schoolYearId = $this->conn->lastInsertId();
            } else {
                $schoolYearId = $sy['school_year_id'];
            }


        // --- Semester ---
        $stmt = $this->conn->prepare("SELECT semester_id FROM tbl_semester WHERE semester_name = ? AND semester_start = ? AND semester_end = ? AND school_year_id = ?");
        $stmt->execute([$semesterName, $semesterStart, $semesterEnd, $schoolYearId]);
        $semester = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$semester) {
            $insert = $this->conn->prepare("INSERT INTO tbl_semester (semester_name, semester_start, semester_end, is_active, school_year_id) VALUES (?, ?, ?, 1, ?)");
            $insert->execute([$semesterName, $semesterStart, $semesterEnd, $schoolYearId]);
            $semesterId = $this->conn->lastInsertId();
        } else {
            $semesterId = $semester['semester_id'];
        }

        $rowCount = 0;
        $skippedRows = [];

        foreach ($csvData as $row) {
            $sectionName = trim($row['section_name'] ?? '');
            $venueName = trim($row['venue_name'] ?? '');
            $dayAbbrev = trim($row['day'] ?? '');
            $startTime = trim($row['start_time'] ?? '');
            $endTime = trim($row['end_time'] ?? '');

            if (!$sectionName || !$venueName || !$dayAbbrev || !$startTime || !$endTime) {
                $skippedRows[] = array_merge($row, ['reason' => 'Missing required fields']);
                continue;
            }

            $dayKey = strtolower(substr($dayAbbrev, 0, 3));
            $dayOfWeek = $dayMap[$dayKey] ?? null;

            if (!$dayOfWeek) {
                $skippedRows[] = array_merge($row, ['reason' => 'Invalid day format']);
                continue;
            }

            // SECTION
            $stmt = $this->conn->prepare("SELECT section_id FROM tbl_section WHERE section_name = ?");
            $stmt->execute([$sectionName]);
            $section = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$section) {
                $insert = $this->conn->prepare("INSERT INTO tbl_section (section_name) VALUES (?)");
                $insert->execute([$sectionName]);
                $sectionId = $this->conn->lastInsertId();
            } else {
                $sectionId = $section['section_id'];
            }

            // VENUE
            $stmt = $this->conn->prepare("SELECT ven_id FROM tbl_venue WHERE LOWER(ven_name) = ?");
            $stmt->execute([strtolower($venueName)]);
            $venue = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$venue) {
                $skippedRows[] = array_merge($row, ['reason' => 'Venue not found']);
                continue;
            }
            $venueId = $venue['ven_id'];

            // INSERT schedule
            $insertSchedule = $this->conn->prepare("INSERT INTO tbl_class_venue_schedule (semester_id, section_id, ven_id, day_of_week, start_time, end_time) VALUES (?, ?, ?, ?, ?, ?)");
            $insertSchedule->execute([$semesterId, $sectionId, $venueId, $dayOfWeek, $startTime, $endTime]);

            $rowCount++;
        }

        return json_encode([
            'status' => 'success',
            'message' => "$rowCount rows inserted.",
            'skipped_count' => count($skippedRows),
            'skipped' => $skippedRows
        ]);
    }

    public function fetchSchoolYear() {
        try {
            $stmt = $this->conn->prepare("SELECT `school_year_id`, `school_year_name`, `created_at` FROM `tbl_school_year` WHERE 1");
            $stmt->execute();
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
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

    public function fetchSemester($school_year_id) {
        try {
            $stmt = $this->conn->prepare("SELECT `semester_id`, `school_year_id`, `semester_name`, `semester_start`, `semester_end`, `created_at`, `is_active` FROM `tbl_semester` WHERE school_year_id = ?");
            $stmt->execute([$school_year_id]);
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
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

    public function fetchVenueScheduled($semester_id) {
        try {
            $query = "SELECT DISTINCT v.ven_id,v.ven_name, cvs.schedule_id
                     FROM tbl_class_venue_schedule cvs
                     INNER JOIN tbl_venue v ON cvs.ven_id = v.ven_id
                     WHERE cvs.semester_id = ?";
            
            $stmt = $this->conn->prepare($query);
            $stmt->execute([$semester_id]);
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
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
    public function fetchVenueByVenId($ven_id) {
        try {            $query = "SELECT cvs.day_of_week, cvs.start_time, cvs.end_time,
                            s.semester_start, s.semester_end,
                            sec.section_name
                     FROM tbl_class_venue_schedule cvs
                     INNER JOIN tbl_semester s ON cvs.semester_id = s.semester_id
                     INNER JOIN tbl_section sec ON cvs.section_id = sec.section_id
                     WHERE cvs.ven_id = ?
                     ORDER BY cvs.day_of_week, cvs.start_time";
            
            $stmt = $this->conn->prepare($query);
            $stmt->execute([$ven_id]);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return json_encode([
                'status' => 'success',
                'data' => $results
            ]);
            
        } catch (PDOException $e) {
            return json_encode([
                'status' => 'error',
                'message' => 'Database error: ' . $e->getMessage()
            ]);
        }
    }

    public function fetchRequestReservation() {
    try {
        $sql = "
            SELECT 
                r.reservation_id, 
                r.reservation_created_at, 
                r.reservation_title,
                r.reservation_description,
                r.reservation_start_date,
                r.reservation_end_date,
                r.reservation_participants,
                r.reservation_user_id AS requester_id,
                CONCAT(u.users_fname, ' ', u.users_mname, ' ', u.users_lname) AS requester_name,
                d.departments_name,
                rs.reservation_status_status_id AS status_id,
                rs.reservation_active AS active,
                sm.status_master_name AS reservation_status
            FROM 
                tbl_reservation r
            INNER JOIN 
                tbl_users u ON r.reservation_user_id = u.users_id
            INNER JOIN 
                tbl_departments d ON u.users_department_id = d.departments_id
            LEFT JOIN 
                tbl_reservation_status rs ON r.reservation_id = rs.reservation_reservation_id
            LEFT JOIN 
                tbl_status_master sm ON rs.reservation_status_status_id = sm.status_master_id
            WHERE
                rs.reservation_status_status_id = 7 AND rs.reservation_active = 1
            GROUP BY 
                r.reservation_id
            ORDER BY 
                r.reservation_created_at DESC;
        ";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute();

        $reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return json_encode(['status' => 'success', 'data' => $reservations]);

    } catch (PDOException $e) {
        return json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}


    public function fetchVenueScheduledCheck() {
    try {
        $query = "SELECT 
                    v.ven_id,
                    v.ven_name, 
                    sct.section_id,
                    sct.section_name,
                    cvs.day_of_week, 
                    cvs.start_time, 
                    cvs.end_time,
                    cvs.semester_id,
                    sem.semester_name
                  FROM tbl_class_venue_schedule cvs
                  INNER JOIN tbl_venue v ON cvs.ven_id = v.ven_id
                  INNER JOIN tbl_section sct ON cvs.section_id = sct.section_id
                  INNER JOIN tbl_semester sem ON cvs.semester_id = sem.semester_id
                  ORDER BY sem.semester_id, v.ven_name, sct.section_name, cvs.day_of_week, cvs.start_time";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return json_encode([
            'status' => 'success',
            'data' => $result
        ]);
    } catch (PDOException $e) {
        return json_encode([
            'status' => 'error',
            'message' => 'Database error: ' . $e->getMessage()
        ]);
    } catch (Exception $e) {
        return json_encode([
            'status' => 'error',
            'message' => 'Error: ' . $e->getMessage()
        ]);
    }
}

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
                rs.reservation_status_status_id AS status_id,
                rs.reservation_active AS active,
                ul.user_level_name,
                dep.departments_name,
                CONCAT(u_req.users_fname, ' ', u_req.users_mname, ' ', u_req.users_lname) AS requester_name,
                dep.departments_name AS department_name,
                -- Only venue names
                GROUP_CONCAT(DISTINCT venue.ven_name SEPARATOR '|') AS venue_names
            FROM 
                tbl_reservation r
            LEFT JOIN tbl_reservation_status rs ON r.reservation_id = rs.reservation_reservation_id
            LEFT JOIN tbl_users u_req ON r.reservation_user_id = u_req.users_id
            LEFT JOIN tbl_user_level ul ON u_req.users_user_level_id = ul.user_level_id
            LEFT JOIN tbl_departments dep ON u_req.users_department_id = dep.departments_id
            LEFT JOIN tbl_reservation_venue v ON r.reservation_id = v.reservation_reservation_id
            LEFT JOIN tbl_venue venue ON v.reservation_venue_venue_id = venue.ven_id
            WHERE r.reservation_id = :reservation_id
            GROUP BY r.reservation_id
        ";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':reservation_id', $reservationId, PDO::PARAM_INT);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            // Prepare response
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
                'reservation_user_id' => $row['reservation_user_id'],
                'requester_name' => $row['requester_name'],
                'department_name' => $row['department_name'],
                'user_level_name' => $row['user_level_name'],
                'venues' => []
            ];

            // VENUE NAMES ONLY
            if (!empty($row['venue_names'])) {
                $names = explode('|', $row['venue_names']);
                foreach ($names as $name) {
                    if (trim($name) !== '') {
                        $response['venues'][] = [
                            'venue_name' => $name
                        ];
                    }
                }
            }

            return json_encode(['status' => 'success', 'data' => $response]);
        } else {
            return json_encode(['status' => 'error', 'message' => 'Reservation not found']);
        }

    } catch (PDOException $e) {
        return json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}

public function handleReviewUpdated($reservationId, $userId, $isAvailable) {
    try {
        $this->conn->beginTransaction();

        // Step 1: Deactivate old status with ID 7
        $sql = "UPDATE tbl_reservation_status 
                SET reservation_active = 0
                WHERE reservation_reservation_id = :reservation_id 
                AND reservation_status_status_id = 7";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':reservation_id', $reservationId, PDO::PARAM_INT);
        $stmt->execute();

        // Step 2: Determine new status ID
        $statusId = $isAvailable ? 8 : 9;

        // Step 3: Insert new status record
        $sqlInsert = "
            INSERT INTO tbl_reservation_status (
                reservation_status_status_id,
                reservation_reservation_id,
                reservation_active,
                reservation_users_id,
                reservation_updated_at
            ) VALUES (
                :status_id,
                :reservation_id,
                1,
                :user_id,
                NOW()
            )
        ";

        $stmtInsert = $this->conn->prepare($sqlInsert);
        $stmtInsert->bindParam(':status_id', $statusId, PDO::PARAM_INT);
        $stmtInsert->bindParam(':reservation_id', $reservationId, PDO::PARAM_INT);
        $stmtInsert->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmtInsert->execute();

        // Step 4: Insert notification
        $sqlNotif = "
            INSERT INTO notification_requests (
                notification_message,
                notification_department_id,
                notification_user_level_id,
                notification_create
            ) VALUES (
                :message,
                :department_id,
                :user_level_id,
                NOW()
            )
        ";

        $stmtNotif = $this->conn->prepare($sqlNotif);

        // Customize message
        if ($isAvailable) {
            $message = "Venue has been approved and available by registrar.";
        } else {
            $message = "Venue is NOT AVAILABLE for the requested time.";
        }

        // You can change these IDs based on your system
        $departmentId = 27;
        $userLevelId = 1;

        $stmtNotif->bindParam(':message', $message, PDO::PARAM_STR);
        $stmtNotif->bindParam(':department_id', $departmentId, PDO::PARAM_INT);
        $stmtNotif->bindParam(':user_level_id', $userLevelId, PDO::PARAM_INT);
        $stmtNotif->execute();

        // Finalize transaction
        $this->conn->commit();

        return json_encode([
            'status' => 'success',
            'message' => $message,
            'reservation_id' => $reservationId,
            'status_id' => $statusId
        ]);

    } catch (PDOException $e) {
        $this->conn->rollBack();
        return json_encode([
            'status' => 'error',
            'message' => 'Database error: ' . $e->getMessage()
        ]);
    } catch (Exception $e) {
        $this->conn->rollBack();
        return json_encode([
            'status' => 'error',
            'message' => 'General error: ' . $e->getMessage()
        ]);
    }
}

public function fetchApprovalNotification($departmentId, $userLevelId) {
        try {
            if ($departmentId === null || $userLevelId === null) {
                return json_encode(['status' => 'error', 'message' => 'Missing department ID or user level ID']);
            }
    
            $sql = "SELECT 
                        notification_id, 
                        notification_message, 
                        notification_department_id, 
                        notification_user_level_id, 
                        notification_create 
                    FROM notification_requests 
                    WHERE notification_department_id = :department_id
                      AND notification_user_level_id = :user_level_id
                    ORDER BY notification_create DESC";
    
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([
                ':department_id' => $departmentId,
                ':user_level_id' => $userLevelId
            ]);
    
            $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return json_encode(['status' => 'success', 'data' => $notifications]);
    
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
            
            // Determine the requester (reservation owner) and insert user notification
            $reqStmt = $this->conn->prepare("SELECT reservation_user_id FROM tbl_reservation WHERE reservation_id = :reservation_id");
            $reqStmt->bindParam(':reservation_id', $reservationId, PDO::PARAM_INT);
            $reqStmt->execute();
            $reqRow = $reqStmt->fetch(PDO::FETCH_ASSOC);
            if (!$reqRow || empty($reqRow['reservation_user_id'])) {
                throw new Exception("Requester not found for reservation");
            }
            $notificationUserId = (int)$reqRow['reservation_user_id'];

            $sqlNotification = "INSERT INTO tbl_notification_reservation 
                             (notification_message, notification_reservation_reservation_id, notification_user_id, notification_created_at) 
                             VALUES (:message, :reservation_id, :notification_user_id, NOW())";

            $stmtNotification = $this->conn->prepare($sqlNotification);
            $stmtNotification->bindParam(':message', $notificationMessage, PDO::PARAM_STR);
            $stmtNotification->bindParam(':reservation_id', $reservationId, PDO::PARAM_INT);
            $stmtNotification->bindParam(':notification_user_id', $notificationUserId, PDO::PARAM_INT);
            $stmtNotification->execute();
            
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

    public function sendApprovalPushNotification($reservationId, $isAccepted, $userId, $data = []) {
        try {
            // Make a POST request to the push notification service
            $pushNotificationUrl = 'http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']) . '/server/send-push-notification.php';
            
            $title = 'Department Approval Update';
            $body = $isAccepted ? 'Your reservation has been approved by the department.' : 'Your reservation has been declined by the department.';
            
            $notificationData = array_merge([
                'reservation_id' => $reservationId,
                'status' => $isAccepted ? 'approved' : 'declined',
                'type' => 'department_approval'
            ], $data);
            
            $postData = json_encode([
                'operation' => 'send',
                'user_id' => $userId,
                'title' => $title,
                'body' => $body,
                'data' => $notificationData
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
            
            $result = file_get_contents($pushNotificationUrl, false, $context);
            
            if ($result === false) {
                error_log("Failed to send department approval push notification to user {$userId}");
                return false;
            }
            
            $response = json_decode($result, true);
            if ($response && isset($response['status']) && $response['status'] === 'success') {
                error_log("Department approval push notification sent successfully to user {$userId}");
                return true;
            } else {
                error_log("Department approval push notification failed for user {$userId}: " . ($response['message'] ?? 'Unknown error'));
                return false;
            }
            
        } catch (Exception $e) {
            error_log("Exception in sendApprovalPushNotification: " . $e->getMessage());
            return false;
        }
    }
}

// Handle JSON POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $json = file_get_contents("php://input");
    $data = json_decode($json, true);

    if (!$data || !isset($data['operation'])) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid JSON or missing operation']);
        exit();
    }

    $operation = $data['operation'];
    $user = new Department_Dean();

    switch ($operation) {

        case "handleApproval":
            $reservationId = $data['reservation_id'] ?? null;
            $isAccepted = $data['is_accepted'] ?? false;
            $userId = $data['user_id'] ?? null;
            $notificationMessage = $data['notification_message'] ?? '';
            $notificationUserId = $data['notification_user_id'] ?? null;
            if ($reservationId === null) {
                echo json_encode(['status' => 'error', 'message' => 'Reservation ID is required']);
                break;
            }
            if ($userId === null) {
                echo json_encode(['status' => 'error', 'message' => 'User ID is required']);
                break;
            }
            echo $user->handleApproval($reservationId, $isAccepted, $userId, $notificationMessage, $notificationUserId);
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
            
        case "fetchApprovalByDept":
            // Extract parameters from the JSON payload
            $departmentId    = $data['json']['department_id']    ?? null;
            $userLevelId     = $data['json']['user_level_id']     ?? null;
            $currentUserId   = $data['json']['current_user_id']   ?? null;

            // Validate presence
            if ($departmentId === null || $userLevelId === null || $currentUserId === null) {
                echo json_encode([
                    'status'  => 'error',
                    'message' => 'Department ID, User Level ID, and Current User ID are required'
                ]);
                break;
            }

            // Call your service method
            echo $user->fetchApprovalByDept(
                (int)$departmentId,
                (int)$userLevelId,
                (int)$currentUserId
            );
            break;

        case "handleReviewUpdated":
            $reservationId = $data['reservation_id'] ?? null;
            $userId = $data['user_id'] ?? null;
            $isAvailable = isset($data['is_available']) ? (bool)$data['is_available'] : null;

            if ($reservationId !== null && $userId !== null && $isAvailable !== null) {
                echo $user->handleReviewUpdated($reservationId, $userId, $isAvailable);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Missing parameters']);
            }
            break;

        case "fetchRequestById":
            $reservationId = $data['reservation_id'] ?? null;
            if ($reservationId !== null) {
                echo $user->fetchRequestById($reservationId);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Missing reservation_id parameter']);
            }
            break;
        case "fetchVenueScheduledCheck":
            echo $user->fetchVenueScheduledCheck();
            break;

        case "fetchRequestReservation":
            echo $user->fetchRequestReservation();
            break;
        case 'uploadClassroomCSV':
            $school_year_name = $data['school_year_name'] ?? '';
            $semesterName = $data['semester_name'] ?? '';
            $semesterStart = $data['semester_start'] ?? '';
            $semesterEnd = $data['semester_end'] ?? '';
            $csvData = $data['csv_data'] ?? [];

            if ($school_year_name && $semesterName && $semesterStart && $semesterEnd && is_array($csvData)) {
                echo $user->uploadClassroomJSON($school_year_name, $semesterName, $semesterStart, $semesterEnd, $csvData);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Missing or invalid parameters']);
            }
            break;
        case 'fetchSchoolYear':
            echo $user->fetchSchoolYear();
            break;
        case 'fetchSemester':
            $school_year_id = $data['school_year_id'] ?? null;
            if ($school_year_id !== null) {
                echo $user->fetchSemester($school_year_id);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Missing school_year_id parameter']);
            }
            break;
        case 'fetchVenueScheduled':
            $semester_id = $data['semester_id'] ?? null;
            if ($semester_id !== null) {
                echo $user->fetchVenueScheduled($semester_id);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Missing semester_id parameter']);
            }
            break;
        case 'fetchVenueByVenId':
            $ven_id = $data['ven_id'] ?? null;
            if ($ven_id !== null) {
                echo $user->fetchVenueByVenId($ven_id);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Missing ven_id parameter']);
            }
            break;

        default:
            echo json_encode(['status' => 'error', 'message' => 'Invalid operation']);
            break;
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
}
?>
