<?php
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

class Reservation {
    private $conn;

    public function __construct() {
        include 'connection-pdo.php';
        $this->conn = $conn;
    }


    public function fetchAvailability($itemType, $itemId, $inputQuantities = [], $startDateTime = null, $endDateTime = null) {
        try {
            $itemIds = is_array($itemId) ? $itemId : [$itemId];
            $placeholders = implode(',', array_fill(0, count($itemIds), '?'));
    
            if ($itemType === 'venue') {
                // First get original venue reservations
                $sql1 = "
                    SELECT
                        v.ven_id,
                        v.ven_name,
                        v.ven_occupancy,
                        r.reservation_id,
                        r.reservation_title,
                        r.reservation_user_id,
                        ul.user_level_name AS user_level_name,
                        d.departments_name AS department_name,
                        r.reservation_start_date,
                        r.reservation_end_date,
                        r.reschedule_start_date,
                        r.reschedule_end_date,
                        latest_status.reservation_status_status_id AS reservation_status_status_id,
                        latest_status.reservation_active AS reservation_active,
                        CASE WHEN active_resched.max_reschedule_status_id IS NULL THEN 0 ELSE 1 END AS has_active_reschedule,
                        rv.reservation_change_venue_id,
                        'original' AS venue_type
                    FROM tbl_venue v
                    INNER JOIN tbl_reservation_venue rv
                        ON v.ven_id = rv.reservation_venue_venue_id
                    INNER JOIN tbl_reservation r
                        ON rv.reservation_reservation_id = r.reservation_id
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
                    ) latest_status
                        ON latest_status.reservation_reservation_id = r.reservation_id
                    LEFT JOIN (
                        SELECT reservation_reservation_id, MAX(reservation_status_id) AS max_reschedule_status_id
                        FROM tbl_reservation_status
                        WHERE reservation_status_status_id IN (10, 11, 14) AND reservation_active IN (0, 1)
                        GROUP BY reservation_reservation_id
                    ) active_resched ON active_resched.reservation_reservation_id = r.reservation_id
                    LEFT JOIN tbl_users u ON r.reservation_user_id = u.users_id
                    LEFT JOIN tbl_user_level ul ON u.users_user_level_id = ul.user_level_id
                    LEFT JOIN tbl_departments d ON u.users_department_id = d.departments_id
                    WHERE v.ven_id IN ($placeholders)
                      AND r.reservation_id NOT IN (
                          SELECT DISTINCT reservation_reservation_id 
                          FROM tbl_reservation_status 
                          WHERE reservation_status_status_id IN (2, 5, 4)
                      )
                      AND NOT (latest_status.reservation_status_status_id = 6 AND active_resched.max_reschedule_status_id IS NOT NULL)
                    
                    UNION ALL
                    
                    SELECT
                        v.ven_id,
                        v.ven_name,
                        v.ven_occupancy,
                        r.reservation_id,
                        r.reservation_title,
                        r.reservation_user_id,
                        ul.user_level_name AS user_level_name,
                        d.departments_name AS department_name,
                        r.reschedule_start_date AS reservation_start_date,
                        r.reschedule_end_date AS reservation_end_date,
                        r.reschedule_start_date,
                        r.reschedule_end_date,
                        latest_status.reservation_status_status_id AS reservation_status_status_id,
                        latest_status.reservation_active AS reservation_active,
                        CASE WHEN active_resched.max_reschedule_status_id IS NULL THEN 0 ELSE 1 END AS has_active_reschedule,
                        rv.reservation_change_venue_id,
                        'reschedule_original' AS venue_type
                    FROM tbl_venue v
                    INNER JOIN tbl_reservation_venue rv
                        ON v.ven_id = rv.reservation_venue_venue_id
                    INNER JOIN tbl_reservation r
                        ON rv.reservation_reservation_id = r.reservation_id
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
                    ) latest_status
                        ON latest_status.reservation_reservation_id = r.reservation_id
                    LEFT JOIN (
                        SELECT reservation_reservation_id, MAX(reservation_status_id) AS max_reschedule_status_id
                        FROM tbl_reservation_status
                        WHERE reservation_status_status_id IN (10, 11, 14) AND reservation_active IN (0, 1)
                        GROUP BY reservation_reservation_id
                    ) active_resched ON active_resched.reservation_reservation_id = r.reservation_id
                    LEFT JOIN tbl_users u ON r.reservation_user_id = u.users_id
                    LEFT JOIN tbl_user_level ul ON u.users_user_level_id = ul.user_level_id
                    LEFT JOIN tbl_departments d ON u.users_department_id = d.departments_id
                    WHERE v.ven_id IN ($placeholders)
                      AND latest_status.reservation_status_status_id IN (6, 10, 11, 14)
                      AND rv.reservation_change_venue_id IS NULL
                      AND r.reschedule_start_date IS NOT NULL
                      AND r.reschedule_end_date IS NOT NULL
                      AND r.reservation_id NOT IN (
                          SELECT DISTINCT reservation_reservation_id 
                          FROM tbl_reservation_status 
                          WHERE reservation_status_status_id IN (2, 5, 4)
                      )
                    
                    UNION ALL
                    
                    SELECT
                        cv.ven_id,
                        cv.ven_name,
                        cv.ven_occupancy,
                        r.reservation_id,
                        r.reservation_title,
                        r.reservation_user_id,
                        ul.user_level_name AS user_level_name,
                        d.departments_name AS department_name,
                        CASE 
                            WHEN latest_status.reservation_status_status_id = 14 
                                 AND r.reschedule_start_date IS NOT NULL 
                            THEN r.reschedule_start_date 
                            ELSE r.reservation_start_date 
                        END AS reservation_start_date,
                        CASE 
                            WHEN latest_status.reservation_status_status_id = 14 
                                 AND r.reschedule_end_date IS NOT NULL 
                            THEN r.reschedule_end_date 
                            ELSE r.reservation_end_date 
                        END AS reservation_end_date,
                        r.reschedule_start_date,
                        r.reschedule_end_date,
                        latest_status.reservation_status_status_id AS reservation_status_status_id,
                        latest_status.reservation_active AS reservation_active,
                        CASE WHEN active_resched.max_reschedule_status_id IS NULL THEN 0 ELSE 1 END AS has_active_reschedule,
                        rv.reservation_change_venue_id,
                        'change' AS venue_type
                    FROM tbl_venue cv
                    INNER JOIN tbl_reservation_venue rv
                        ON cv.ven_id = rv.reservation_change_venue_id
                    INNER JOIN tbl_reservation r
                        ON rv.reservation_reservation_id = r.reservation_id
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
                    ) latest_status
                        ON latest_status.reservation_reservation_id = r.reservation_id
                    LEFT JOIN (
                        SELECT reservation_reservation_id, MAX(reservation_status_id) AS max_reschedule_status_id
                        FROM tbl_reservation_status
                        WHERE reservation_status_status_id IN (10, 11, 14) AND reservation_active IN (0, 1)
                        GROUP BY reservation_reservation_id
                    ) active_resched ON active_resched.reservation_reservation_id = r.reservation_id
                    LEFT JOIN tbl_users u ON r.reservation_user_id = u.users_id
                    LEFT JOIN tbl_user_level ul ON u.users_user_level_id = ul.user_level_id
                    LEFT JOIN tbl_departments d ON u.users_department_id = d.departments_id
                    WHERE cv.ven_id IN ($placeholders)
                      AND rv.reservation_change_venue_id IS NOT NULL
                      AND latest_status.reservation_status_status_id IN (6, 10, 11, 14)
                      AND r.reservation_id NOT IN (
                          SELECT DISTINCT reservation_reservation_id 
                          FROM tbl_reservation_status 
                          WHERE reservation_status_status_id IN (2, 5, 4)
                      )
                ";
                
                // Add date range filtering if provided
                if ($startDateTime && $endDateTime) {
                    $dateFilter = " AND (
                        (
                            (CASE
                                WHEN latest_status.reservation_status_status_id IN (10, 11, 14)
                                     AND r.reschedule_start_date IS NOT NULL AND r.reschedule_end_date IS NOT NULL
                                THEN r.reschedule_start_date ELSE r.reservation_start_date END)
                            BETWEEN :startDateTime AND :endDateTime
                        )
                        OR (
                            (CASE
                                WHEN latest_status.reservation_status_status_id IN (10, 11, 14)
                                     AND r.reschedule_start_date IS NOT NULL AND r.reschedule_end_date IS NOT NULL
                                THEN r.reschedule_end_date ELSE r.reservation_end_date END)
                            BETWEEN :startDateTime AND :endDateTime
                        )
                        OR (
                            :startDateTime BETWEEN 
                                (CASE
                                    WHEN latest_status.reservation_status_status_id IN (10, 11, 14)
                                         AND r.reschedule_start_date IS NOT NULL AND r.reschedule_end_date IS NOT NULL
                                    THEN r.reschedule_start_date ELSE r.reservation_start_date END)
                                AND 
                                (CASE
                                    WHEN latest_status.reservation_status_status_id IN (10, 11, 14)
                                         AND r.reschedule_start_date IS NOT NULL AND r.reschedule_end_date IS NOT NULL
                                    THEN r.reschedule_end_date ELSE r.reservation_end_date END)
                        )
                        OR (
                            :endDateTime BETWEEN 
                                (CASE
                                    WHEN latest_status.reservation_status_status_id IN (10, 11, 14)
                                         AND r.reschedule_start_date IS NOT NULL AND r.reschedule_end_date IS NOT NULL
                                    THEN r.reschedule_start_date ELSE r.reservation_start_date END)
                                AND 
                                (CASE
                                    WHEN latest_status.reservation_status_status_id IN (10, 11, 14)
                                         AND r.reschedule_start_date IS NOT NULL AND r.reschedule_end_date IS NOT NULL
                                    THEN r.reschedule_end_date ELSE r.reservation_end_date END)
                        )
                    )";
                    
                    // Insert date filter before UNION ALL
                    $sql1 = str_replace('WHERE cv.ven_id IN', $dateFilter . ' WHERE cv.ven_id IN', 
                             str_replace('WHERE v.ven_id IN', $dateFilter . ' WHERE v.ven_id IN', $sql1));
                }
                
                $stmt = $this->conn->prepare($sql1);
                
                if ($startDateTime && $endDateTime) {
                    $params = array_merge($itemIds, [$startDateTime, $endDateTime, $startDateTime, $endDateTime], 
                                         $itemIds, [$startDateTime, $endDateTime, $startDateTime, $endDateTime],
                                         $itemIds, [$startDateTime, $endDateTime, $startDateTime, $endDateTime]);
                    $stmt->execute($params);
                } else {
                    $params = array_merge($itemIds, $itemIds, $itemIds);
                    $stmt->execute($params);
                }
    
                $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Filter results to handle rescheduled reservations properly
                $filteredResults = [];
                $processedReservations = [];
                
                foreach ($results as $result) {
                    $reservationId = $result['reservation_id'];
                    $statusId = (int)$result['reservation_status_status_id'];
                    $reservationActive = (int)$result['reservation_active'];
                    $venueType = $result['venue_type'];
                    
                    // Handle the logic for displaying reservations based on status and venue type
                    if ($statusId === 10 || $statusId === 11 || $statusId === 14) {
                        // Special handling for status 11 - always display 2 entries
                        if ($statusId === 11) {
                            // Check if change_venue_id exists
                            if (!empty($result['reservation_change_venue_id'])) {
                                // Display both original and change venues
                                if ($venueType === 'original') {
                                    // Original venue uses original dates
                                    // Keep original dates as they are
                                    
                                    // Clean up helper fields
                                    unset($result['reschedule_start_date']);
                                    unset($result['reschedule_end_date']);
                                    unset($result['venue_type']);
                                    unset($result['has_active_reschedule']);
                                    unset($result['reservation_change_venue_id']);
                                    
                                    // Set availability based on status
                                    $result['is_available'] = false;
                                    
                                    // Set reservation status text
                                    $result['reservation_status'] = 'Rescheduled';
                                    
                                    $filteredResults[] = $result;
                                } else if ($venueType === 'change') {
                                    // Change venue uses reschedule dates
                                    if (!empty($result['reschedule_start_date']) && !empty($result['reschedule_end_date'])) {
                                        $result['reservation_start_date'] = $result['reschedule_start_date'];
                                        $result['reservation_end_date'] = $result['reschedule_end_date'];
                                    }
                                    
                                    // Clean up helper fields
                                    unset($result['reschedule_start_date']);
                                    unset($result['reschedule_end_date']);
                                    unset($result['venue_type']);
                                    unset($result['has_active_reschedule']);
                                    unset($result['reservation_change_venue_id']);
                                    
                                    // Set availability based on status
                                    $result['is_available'] = false;
                                    
                                    // Set reservation status text
                                    $result['reservation_status'] = 'Rescheduled';
                                    
                                    $filteredResults[] = $result;
                                }
                            } else {
                                // No change venue - display both original and reschedule dates
                                if ($venueType === 'original') {
                                    // Original venue with original dates
                                    // Keep original dates as they are
                                    
                                    // Clean up helper fields
                                    unset($result['reschedule_start_date']);
                                    unset($result['reschedule_end_date']);
                                    unset($result['venue_type']);
                                    unset($result['has_active_reschedule']);
                                    unset($result['reservation_change_venue_id']);
                                    
                                    // Set availability based on status
                                    $result['is_available'] = false;
                                    
                                    // Set reservation status text
                                    $result['reservation_status'] = 'Rescheduled';
                                    
                                    $filteredResults[] = $result;
                                } else if ($venueType === 'reschedule_original') {
                                    // Same venue with reschedule dates (already set in query)
                                    // Reschedule dates are already set as reservation dates
                                    
                                    // Clean up helper fields
                                    unset($result['reschedule_start_date']);
                                    unset($result['reschedule_end_date']);
                                    unset($result['venue_type']);
                                    unset($result['has_active_reschedule']);
                                    unset($result['reservation_change_venue_id']);
                                    
                                    // Set availability based on status
                                    $result['is_available'] = false;
                                    
                                    // Set reservation status text
                                    $result['reservation_status'] = 'Rescheduled';
                                    
                                    $filteredResults[] = $result;
                                }
                            }
                        }
                        // Special handling for status 14 with active=1
                        else if ($statusId === 14 && $reservationActive === 1) {
                            // Check if change_venue_id exists
                            if (!empty($result['reservation_change_venue_id'])) {
                                // Only display one entry (prioritize change venue)
                                if ($venueType === 'change' && !in_array($reservationId, $processedReservations)) {
                                    // For status 14 change venues, reschedule dates are already set in the query
                                    // No need to modify dates as they're already correct
                                    
                                    // Clean up helper fields
                                    unset($result['reschedule_start_date']);
                                    unset($result['reschedule_end_date']);
                                    unset($result['venue_type']);
                                    unset($result['has_active_reschedule']);
                                    unset($result['reservation_change_venue_id']);
                                    
                                    // Set availability based on status
                                    $result['is_available'] = false;
                                    
                                    // Set reservation status text
                                    $result['reservation_status'] = 'Rescheduled';
                                    
                                    $filteredResults[] = $result;
                                    $processedReservations[] = $reservationId;
                                }
                            } else {
                                // Display reschedule dates when change_venue_id is null
                                if (!in_array($reservationId, $processedReservations)) {
                                    if ($venueType === 'reschedule_original') {
                                        // For status 14 original venues, reschedule dates are already set as reservation dates
                                        // No need to modify dates
                                    } else if ($venueType === 'original') {
                                        // For status 14 original venues, use reschedule dates
                                        if (!empty($result['reschedule_start_date']) && !empty($result['reschedule_end_date'])) {
                                            $result['reservation_start_date'] = $result['reschedule_start_date'];
                                            $result['reservation_end_date'] = $result['reschedule_end_date'];
                                        }
                                    }
                                    
                                    // Clean up helper fields
                                    unset($result['reschedule_start_date']);
                                    unset($result['reschedule_end_date']);
                                    unset($result['venue_type']);
                                    unset($result['has_active_reschedule']);
                                    unset($result['reservation_change_venue_id']);
                                    
                                    // Set availability based on status
                                    $result['is_available'] = false;
                                    
                                    // Set reservation status text
                                    $result['reservation_status'] = 'Rescheduled';
                                    
                                    $filteredResults[] = $result;
                                    
                                    // Mark as processed only after handling all venue types for this reservation
                                    if ($venueType === 'reschedule_original' || ($venueType === 'original' && empty($result['reservation_change_venue_id']))) {
                                        $processedReservations[] = $reservationId;
                                    }
                                }
                            }
                        } else {
                            // For other statuses (10, 11, or 14 with active=0), handle based on venue type
                            if ($venueType === 'change') {
                                // For status 14 change venues, reschedule dates are already set in the query
                                // For status 10 and 11 change venues, use rescheduled dates
                                if (($statusId === 10 || $statusId === 11) && !empty($result['reschedule_start_date']) && !empty($result['reschedule_end_date'])) {
                                    $result['reservation_start_date'] = $result['reschedule_start_date'];
                                    $result['reservation_end_date'] = $result['reschedule_end_date'];
                                }
                            } else if ($venueType === 'original') {
                                // For status 14 and 11 original venues, use reschedule dates
                                if (($statusId === 14 || $statusId === 11) && !empty($result['reschedule_start_date']) && !empty($result['reschedule_end_date'])) {
                                    $result['reservation_start_date'] = $result['reschedule_start_date'];
                                    $result['reservation_end_date'] = $result['reschedule_end_date'];
                                }
                                // For status 10 original venues, keep original dates (don't use reschedule dates)
                            } else if ($venueType === 'reschedule_original') {
                                // This venue type already has reschedule dates as reservation dates
                                // No need to modify dates
                            }
                            
                            // Clean up helper fields
                            unset($result['reschedule_start_date']);
                            unset($result['reschedule_end_date']);
                            unset($result['venue_type']);
                            unset($result['has_active_reschedule']);
                            unset($result['reservation_change_venue_id']);
                            
                            // Set availability based on status
                            $result['is_available'] = false;
                            
                            // Set reservation status text
                            $result['reservation_status'] = 'Rescheduled';
                            
                            $filteredResults[] = $result;
                        }
                    } else {
                        // For all other reservations, show as normal
                        // Clean up helper fields
                        unset($result['reschedule_start_date']);
                        unset($result['reschedule_end_date']);
                        unset($result['venue_type']);
                        unset($result['has_active_reschedule']);
                        unset($result['reservation_change_venue_id']);
                        
                        // Set availability based on status
                        $result['is_available'] = ($statusId !== 6);
                        
                        // Set reservation status text
                        switch ($statusId) {
                            case 1:
                                $result['reservation_status'] = 'Pending';
                                break;
                            case 6:
                                $result['reservation_status'] = 'Reserved';
                                break;
                            case 8:
                                $result['reservation_status'] = 'Pending Department Approval';
                                break;
                            case 10:
                            case 11:
                            case 14:
                                $result['reservation_status'] = 'Rescheduled';
                                break;
                            default:
                                $result['reservation_status'] = 'Available';
                        }
                        
                        $filteredResults[] = $result;
                    }
                }
                
                $results = $filteredResults;
    
            } elseif ($itemType === 'vehicle') {
                // First get original vehicle reservations
                $sql2 = "
                    SELECT
                        v.vehicle_id,
                        v.vehicle_license,
                        vm.vehicle_make_name,
                        vmd.vehicle_model_name,
                        r.reservation_id,
                        r.reservation_title,
                        r.reservation_user_id,
                        ul.user_level_name AS user_level_name,
                        d.departments_name AS department_name,
                        r.reservation_start_date,
                        r.reservation_end_date,
                        r.reschedule_start_date,
                        r.reschedule_end_date,
                        latest_status.reservation_status_status_id,
                        latest_status.reservation_active,
                        CASE WHEN active_resched.max_reschedule_status_id IS NULL THEN 0 ELSE 1 END AS has_active_reschedule,
                        rv.reservation_change_vehicle_id,
                        'original' AS vehicle_type,
                        (
                            SELECT COUNT(DISTINCT u2.users_id)
                            FROM tbl_users u2
                            WHERE u2.users_user_level_id = 19
                                AND u2.is_active = 1
                        ) AS available_drivers_count
                    FROM tbl_vehicle v
                    LEFT JOIN tbl_vehicle_model vmd
                        ON v.vehicle_model_id = vmd.vehicle_model_id
                    LEFT JOIN tbl_vehicle_make vm
                        ON vmd.vehicle_model_vehicle_make_id = vm.vehicle_make_id
                    INNER JOIN tbl_reservation_vehicle rv
                        ON v.vehicle_id = rv.reservation_vehicle_vehicle_id
                    INNER JOIN tbl_reservation r
                        ON rv.reservation_reservation_id = r.reservation_id
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
                    ) latest_status
                        ON latest_status.reservation_reservation_id = r.reservation_id
                    LEFT JOIN (
                        SELECT reservation_reservation_id, MAX(reservation_status_id) AS max_reschedule_status_id
                        FROM tbl_reservation_status
                        WHERE reservation_status_status_id IN (10, 11, 14) AND reservation_active IN (0, 1)
                        GROUP BY reservation_reservation_id
                    ) active_resched ON active_resched.reservation_reservation_id = r.reservation_id
                    LEFT JOIN tbl_users u ON r.reservation_user_id = u.users_id
                    LEFT JOIN tbl_user_level ul ON u.users_user_level_id = ul.user_level_id
                    LEFT JOIN tbl_departments d ON u.users_department_id = d.departments_id
                    WHERE v.vehicle_id IN ($placeholders)
                      AND r.reservation_id NOT IN (
                          SELECT DISTINCT reservation_reservation_id 
                          FROM tbl_reservation_status 
                          WHERE reservation_status_status_id IN (2, 5, 4)
                      )
                      AND NOT (latest_status.reservation_status_status_id = 6 AND active_resched.max_reschedule_status_id IS NOT NULL)
                    
                    UNION ALL
                    
                    SELECT
                        v.vehicle_id,
                        v.vehicle_license,
                        vm.vehicle_make_name,
                        vmd.vehicle_model_name,
                        r.reservation_id,
                        r.reservation_title,
                        r.reservation_user_id,
                        ul.user_level_name AS user_level_name,
                        d.departments_name AS department_name,
                        r.reschedule_start_date AS reservation_start_date,
                        r.reschedule_end_date AS reservation_end_date,
                        r.reschedule_start_date,
                        r.reschedule_end_date,
                        latest_status.reservation_status_status_id,
                        latest_status.reservation_active,
                        CASE WHEN active_resched.max_reschedule_status_id IS NULL THEN 0 ELSE 1 END AS has_active_reschedule,
                        rv.reservation_change_vehicle_id,
                        'reschedule_original' AS vehicle_type,
                        (
                            SELECT COUNT(DISTINCT u2.users_id)
                            FROM tbl_users u2
                            WHERE u2.users_user_level_id = 19
                                AND u2.is_active = 1
                        ) AS available_drivers_count
                    FROM tbl_vehicle v
                    LEFT JOIN tbl_vehicle_model vmd
                        ON v.vehicle_model_id = vmd.vehicle_model_id
                    LEFT JOIN tbl_vehicle_make vm
                        ON vmd.vehicle_model_vehicle_make_id = vm.vehicle_make_id
                    INNER JOIN tbl_reservation_vehicle rv
                        ON v.vehicle_id = rv.reservation_vehicle_vehicle_id
                    INNER JOIN tbl_reservation r
                        ON rv.reservation_reservation_id = r.reservation_id
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
                    ) latest_status
                        ON latest_status.reservation_reservation_id = r.reservation_id
                    LEFT JOIN (
                        SELECT reservation_reservation_id, MAX(reservation_status_id) AS max_reschedule_status_id
                        FROM tbl_reservation_status
                        WHERE reservation_status_status_id IN (10, 11, 14) AND reservation_active IN (0, 1)
                        GROUP BY reservation_reservation_id
                    ) active_resched ON active_resched.reservation_reservation_id = r.reservation_id
                    LEFT JOIN tbl_users u ON r.reservation_user_id = u.users_id
                    LEFT JOIN tbl_user_level ul ON u.users_user_level_id = ul.user_level_id
                    LEFT JOIN tbl_departments d ON u.users_department_id = d.departments_id
                    WHERE v.vehicle_id IN ($placeholders)
                      AND latest_status.reservation_status_status_id IN (6, 10, 11, 14)
                      AND rv.reservation_change_vehicle_id IS NULL
                      AND r.reschedule_start_date IS NOT NULL
                      AND r.reschedule_end_date IS NOT NULL
                      AND r.reservation_id NOT IN (
                          SELECT DISTINCT reservation_reservation_id 
                          FROM tbl_reservation_status 
                          WHERE reservation_status_status_id IN (2, 5, 4)
                      )
                    
                    UNION ALL
                    
                    SELECT
                        cv.vehicle_id,
                        cv.vehicle_license,
                        cvmk.vehicle_make_name,
                        cvmd.vehicle_model_name,
                        r.reservation_id,
                        r.reservation_title,
                        r.reservation_user_id,
                        ul.user_level_name AS user_level_name,
                        d.departments_name AS department_name,
                        CASE 
                            WHEN latest_status.reservation_status_status_id = 14 
                                 AND r.reschedule_start_date IS NOT NULL 
                            THEN r.reschedule_start_date 
                            ELSE r.reservation_start_date 
                        END AS reservation_start_date,
                        CASE 
                            WHEN latest_status.reservation_status_status_id = 14 
                                 AND r.reschedule_end_date IS NOT NULL 
                            THEN r.reschedule_end_date 
                            ELSE r.reservation_end_date 
                        END AS reservation_end_date,
                        r.reschedule_start_date,
                        r.reschedule_end_date,
                        latest_status.reservation_status_status_id,
                        latest_status.reservation_active,
                        CASE WHEN active_resched.max_reschedule_status_id IS NULL THEN 0 ELSE 1 END AS has_active_reschedule,
                        rv.reservation_change_vehicle_id,
                        'change' AS vehicle_type,
                        (
                            SELECT COUNT(DISTINCT u2.users_id)
                            FROM tbl_users u2
                            WHERE u2.users_user_level_id = 19
                                AND u2.is_active = 1
                        ) AS available_drivers_count
                    FROM tbl_vehicle cv
                    LEFT JOIN tbl_vehicle_model cvmd
                        ON cv.vehicle_model_id = cvmd.vehicle_model_id
                    LEFT JOIN tbl_vehicle_make cvmk
                        ON cvmd.vehicle_model_vehicle_make_id = cvmk.vehicle_make_id
                    INNER JOIN tbl_reservation_vehicle rv
                        ON cv.vehicle_id = rv.reservation_change_vehicle_id
                    INNER JOIN tbl_reservation r
                        ON rv.reservation_reservation_id = r.reservation_id
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
                    ) latest_status
                        ON latest_status.reservation_reservation_id = r.reservation_id
                    LEFT JOIN (
                        SELECT reservation_reservation_id, MAX(reservation_status_id) AS max_reschedule_status_id
                        FROM tbl_reservation_status
                        WHERE reservation_status_status_id IN (10, 11, 14) AND reservation_active IN (0, 1)
                        GROUP BY reservation_reservation_id
                    ) active_resched ON active_resched.reservation_reservation_id = r.reservation_id
                    LEFT JOIN tbl_users u ON r.reservation_user_id = u.users_id
                    LEFT JOIN tbl_user_level ul ON u.users_user_level_id = ul.user_level_id
                    LEFT JOIN tbl_departments d ON u.users_department_id = d.departments_id
                    WHERE cv.vehicle_id IN ($placeholders)
                      AND rv.reservation_change_vehicle_id IS NOT NULL
                      AND latest_status.reservation_status_status_id IN (6, 10, 11, 14)
                      AND r.reservation_id NOT IN (
                          SELECT DISTINCT reservation_reservation_id 
                          FROM tbl_reservation_status 
                          WHERE reservation_status_status_id IN (2, 5, 4)
                      )
                ";
                
                $stmt = $this->conn->prepare($sql2);
                $params = array_merge($itemIds, $itemIds, $itemIds);
                $stmt->execute($params);
    
                $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Filter results to handle rescheduled reservations properly
                $filteredResults = [];
                $processedReservations = [];
                
                foreach ($results as $result) {
                    $reservationId = $result['reservation_id'];
                    $statusId = (int)$result['reservation_status_status_id'];
                    $reservationActive = (int)$result['reservation_active'];
                    $vehicleType = $result['vehicle_type'];
                    
                    // Handle the logic for displaying reservations based on status and vehicle type
                    if ($statusId === 10 || $statusId === 11 || $statusId === 14) {
                        // Special handling for status 11 - always display 2 entries
                        if ($statusId === 11) {
                            // Check if change_vehicle_id exists
                            if (!empty($result['reservation_change_vehicle_id'])) {
                                // Display both original and change vehicles
                                if ($vehicleType === 'original') {
                                    // Original vehicle uses original dates
                                    // Keep original dates as they are
                                    
                                    // Clean up helper fields
                                    unset($result['reschedule_start_date']);
                                    unset($result['reschedule_end_date']);
                                    unset($result['vehicle_type']);
                                    unset($result['has_active_reschedule']);
                                    unset($result['reservation_change_vehicle_id']);
                                    
                                    // Set availability based on status
                                    $result['is_available'] = false;
                                    
                                    // Set reservation status text
                                    $result['reservation_status'] = 'Rescheduled';
                                    
                                    $filteredResults[] = $result;
                                } else if ($vehicleType === 'change') {
                                    // Change vehicle uses reschedule dates
                                    if (!empty($result['reschedule_start_date']) && !empty($result['reschedule_end_date'])) {
                                        $result['reservation_start_date'] = $result['reschedule_start_date'];
                                        $result['reservation_end_date'] = $result['reschedule_end_date'];
                                    }
                                    
                                    // Clean up helper fields
                                    unset($result['reschedule_start_date']);
                                    unset($result['reschedule_end_date']);
                                    unset($result['vehicle_type']);
                                    unset($result['has_active_reschedule']);
                                    unset($result['reservation_change_vehicle_id']);
                                    
                                    // Set availability based on status
                                    $result['is_available'] = false;
                                    
                                    // Set reservation status text
                                    $result['reservation_status'] = 'Rescheduled';
                                    
                                    $filteredResults[] = $result;
                                }
                            } else {
                                // No change vehicle - display both original and reschedule dates
                                if ($vehicleType === 'original') {
                                    // Original vehicle with original dates
                                    // Keep original dates as they are
                                    
                                    // Clean up helper fields
                                    unset($result['reschedule_start_date']);
                                    unset($result['reschedule_end_date']);
                                    unset($result['vehicle_type']);
                                    unset($result['has_active_reschedule']);
                                    unset($result['reservation_change_vehicle_id']);
                                    
                                    // Set availability based on status
                                    $result['is_available'] = false;
                                    
                                    // Set reservation status text
                                    $result['reservation_status'] = 'Rescheduled';
                                    
                                    $filteredResults[] = $result;
                                } else if ($vehicleType === 'reschedule_original') {
                                    // Same vehicle with reschedule dates (already set in query)
                                    // Reschedule dates are already set as reservation dates
                                    
                                    // Clean up helper fields
                                    unset($result['reschedule_start_date']);
                                    unset($result['reschedule_end_date']);
                                    unset($result['vehicle_type']);
                                    unset($result['has_active_reschedule']);
                                    unset($result['reservation_change_vehicle_id']);
                                    
                                    // Set availability based on status
                                    $result['is_available'] = false;
                                    
                                    // Set reservation status text
                                    $result['reservation_status'] = 'Rescheduled';
                                    
                                    $filteredResults[] = $result;
                                }
                            }
                        }
                        // Special handling for status 14 with active=1
                        else if ($statusId === 14 && $reservationActive === 1) {
                            // Check if change_vehicle_id exists
                            if (!empty($result['reservation_change_vehicle_id'])) {
                                // Only display one entry (prioritize change vehicle)
                                if ($vehicleType === 'change' && !in_array($reservationId, $processedReservations)) {
                                    // For status 14 change vehicles, reschedule dates are already set in the query
                                    // No need to modify dates as they're already correct
                                    
                                    // Clean up helper fields
                                    unset($result['reschedule_start_date']);
                                    unset($result['reschedule_end_date']);
                                    unset($result['vehicle_type']);
                                    unset($result['has_active_reschedule']);
                                    unset($result['reservation_change_vehicle_id']);
                                    
                                    // Set availability based on status
                                    $result['is_available'] = false;
                                    
                                    // Set reservation status text
                                    $result['reservation_status'] = 'Rescheduled';
                                    
                                    $filteredResults[] = $result;
                                    $processedReservations[] = $reservationId;
                                }
                            } else {
                                // Display reschedule dates when change_vehicle_id is null
                                if (!in_array($reservationId, $processedReservations)) {
                                    if ($vehicleType === 'reschedule_original') {
                                        // For status 14 original vehicles, reschedule dates are already set as reservation dates
                                        // No need to modify dates
                                    } else if ($vehicleType === 'original') {
                                        // For status 14 original vehicles, use reschedule dates
                                        if (!empty($result['reschedule_start_date']) && !empty($result['reschedule_end_date'])) {
                                            $result['reservation_start_date'] = $result['reschedule_start_date'];
                                            $result['reservation_end_date'] = $result['reschedule_end_date'];
                                        }
                                    }
                                    
                                    // Clean up helper fields
                                    unset($result['reschedule_start_date']);
                                    unset($result['reschedule_end_date']);
                                    unset($result['vehicle_type']);
                                    unset($result['has_active_reschedule']);
                                    unset($result['reservation_change_vehicle_id']);
                                    
                                    // Set availability based on status
                                    $result['is_available'] = false;
                                    
                                    // Set reservation status text
                                    $result['reservation_status'] = 'Rescheduled';
                                    
                                    $filteredResults[] = $result;
                                    
                                    // Mark as processed only after handling all vehicle types for this reservation
                                    if ($vehicleType === 'reschedule_original' || ($vehicleType === 'original' && empty($result['reservation_change_vehicle_id']))) {
                                        $processedReservations[] = $reservationId;
                                    }
                                }
                            }
                        } else {
                            // For other statuses (10, 11, or 14 with active=0), handle based on vehicle type
                            if ($vehicleType === 'change') {
                                // For status 14 change vehicles, reschedule dates are already set in the query
                                // For status 10 and 11 change vehicles, use rescheduled dates
                                if (($statusId === 10 || $statusId === 11) && !empty($result['reschedule_start_date']) && !empty($result['reschedule_end_date'])) {
                                    $result['reservation_start_date'] = $result['reschedule_start_date'];
                                    $result['reservation_end_date'] = $result['reschedule_end_date'];
                                }
                            } else if ($vehicleType === 'original') {
                                // For status 14 and 11 original vehicles, use reschedule dates
                                if (($statusId === 14 || $statusId === 11) && !empty($result['reschedule_start_date']) && !empty($result['reschedule_end_date'])) {
                                    $result['reservation_start_date'] = $result['reschedule_start_date'];
                                    $result['reservation_end_date'] = $result['reschedule_end_date'];
                                }
                                // For status 10 original vehicles, keep original dates (don't use reschedule dates)
                            } else if ($vehicleType === 'reschedule_original') {
                                // This vehicle type already has reschedule dates as reservation dates
                                // No need to modify dates
                            }
                            
                            // Clean up helper fields
                            unset($result['reschedule_start_date']);
                            unset($result['reschedule_end_date']);
                            unset($result['vehicle_type']);
                            unset($result['has_active_reschedule']);
                            unset($result['reservation_change_vehicle_id']);
                            
                            // Set availability based on status
                            $result['is_available'] = false;
                            
                            // Set reservation status text
                            $result['reservation_status'] = 'Rescheduled';
                            
                            $filteredResults[] = $result;
                        }
                    } else {
                        // For all other reservations, show as normal
                        // Clean up helper fields
                        unset($result['reschedule_start_date']);
                        unset($result['reschedule_end_date']);
                        unset($result['vehicle_type']);
                        unset($result['has_active_reschedule']);
                        unset($result['reservation_change_vehicle_id']);
                        
                        // Set availability based on status
                        $result['is_available'] = ($statusId !== 6);
                        
                        // Set reservation status text
                        switch ($statusId) {
                            case 1:
                                $result['reservation_status'] = 'Pending';
                                break;
                            case 6:
                                $result['reservation_status'] = 'Reserved';
                                break;
                            case 8:
                                $result['reservation_status'] = 'Pending Department Approval';
                                break;
                            case 10:
                            case 14:
                                $result['reservation_status'] = 'Rescheduled';
                                break;
                            default:
                                $result['reservation_status'] = 'Available';
                        }
                        
                        $filteredResults[] = $result;
                    }
                }
                
                $results = $filteredResults;
            } elseif ($itemType === 'equipment') {
                // Remove the complex status filtering from the WHERE clause
                $sql = "
                    SELECT
                        e.equip_id,
                        e.equip_name,
                        e.equip_type,
                        tec.equipments_category_name AS category_name,
                        CASE
                            WHEN e.equip_type = 'Bulk' THEN COALESCE(eq.quantity, 0)
                            ELSE COALESCE(eu.unit_count, 0)
                        END AS current_quantity,
                        COALESCE(re.reservation_equipment_quantity, 0) AS reserved_quantity,
                        r.reservation_user_id,
                        ul.user_level_name AS user_level_name,
                        d.departments_name AS department_name,
                        r.reservation_start_date,
                        r.reservation_end_date,
                        r.reschedule_start_date,
                        r.reschedule_end_date,
                        latest_status.reservation_status_status_id,
                        latest_status.reservation_active,
                        CASE WHEN active_resched.max_reschedule_status_id IS NULL THEN 0 ELSE 1 END AS has_active_reschedule,
                        r.reservation_id,
                        e.equip_created_at,
                        e.equipments_category_id
                    FROM tbl_equipments e
                    LEFT JOIN tbl_equipment_category tec ON e.equipments_category_id = tec.equipments_category_id
                    LEFT JOIN (
                        SELECT
                            equip_id,
                            quantity
                            FROM tbl_equipment_quantity
                            WHERE (equip_id, last_updated) IN (
                                SELECT equip_id, MAX(last_updated)
                                FROM tbl_equipment_quantity
                                GROUP BY equip_id
                            )
                    ) AS eq ON e.equip_id = eq.equip_id AND e.equip_type = 'Bulk'
                    LEFT JOIN (
                        SELECT
                            equip_id,
                            COUNT(*) AS unit_count
                        FROM tbl_equipment_unit
                        WHERE is_active = 1
                        GROUP BY equip_id
                    ) AS eu ON e.equip_id = eu.equip_id AND e.equip_type != 'Bulk'
                    LEFT JOIN tbl_reservation_equipment re ON e.equip_id = re.reservation_equipment_equip_id
                    LEFT JOIN tbl_reservation r ON re.reservation_reservation_id = r.reservation_id
                    LEFT JOIN tbl_users u ON r.reservation_user_id = u.users_id
                    LEFT JOIN tbl_user_level ul ON u.users_user_level_id = ul.user_level_id
                    LEFT JOIN tbl_departments d ON u.users_department_id = d.departments_id
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
                        WHERE reservation_status_status_id IN (10, 11, 14) AND reservation_active IN (0, 1)
                        GROUP BY reservation_reservation_id
                    ) active_resched ON active_resched.reservation_reservation_id = r.reservation_id
                    WHERE e.equip_id IN ($placeholders)
                      AND r.reservation_id NOT IN (
                          SELECT DISTINCT reservation_reservation_id 
                          FROM tbl_reservation_status 
                          WHERE reservation_status_status_id IN (2, 5, 4)
                      )
                ";
                $stmt = $this->conn->prepare($sql);
                $stmt->execute($itemIds);
                $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
                foreach ($results as &$result) {
                    $eid = $result['equip_id'];
                    $result['inputted_quantity'] = isset($inputQuantities[$eid])
                        ? (int)$inputQuantities[$eid]
                        : 0;
                    $result['is_available'] = ($result['current_quantity'] - $result['reserved_quantity']) > 0;
                    $result['availability_status'] = ($result['current_quantity'] - $result['reserved_quantity']) > 0 ? 'Available' : 'Unavailable';
                    $result['total_available'] = $result['current_quantity'] - $result['reserved_quantity'];
                    
                    // Use reschedule dates when an active reschedule exists (status 10 or 14, active 1)
                    if (((int)$result['reservation_status_status_id'] === 10 && (int)$result['reservation_active'] === 1)
                        || ((int)$result['reservation_status_status_id'] === 14 && (int)$result['reservation_active'] === 1)
                        || (!empty($result['has_active_reschedule']) && (int)$result['has_active_reschedule'] === 1)) {
                        if (!empty($result['reschedule_start_date']) && !empty($result['reschedule_end_date'])) {
                            $result['reservation_start_date'] = $result['reschedule_start_date'];
                            $result['reservation_end_date'] = $result['reschedule_end_date'];
                        }
                    }
                    // Do not expose reschedule_* keys; keep only reservation_* naming
                    if (array_key_exists('reschedule_start_date', $result)) {
                        unset($result['reschedule_start_date']);
                    }
                    if (array_key_exists('reschedule_end_date', $result)) {
                        unset($result['reschedule_end_date']);
                    }
                    if (array_key_exists('has_active_reschedule', $result)) {
                        unset($result['has_active_reschedule']);
                    }
                    
                    // Set reservation status text
                    switch ($result['reservation_status_status_id']) {
                        case 1:
                            $result['reservation_status'] = 'Pending';
                            break;
                        case 6:
                            $result['reservation_status'] = 'Reserved';
                            break;
                        case 10:
                            $result['reservation_status'] = 'Rescheduled';
                            break;
                        case 11:
                            $result['reservation_status'] = 'Rescheduled';
                            break;
                        case 14:
                            $result['reservation_status'] = 'Rescheduled';
                            break;
                        default:
                            $result['reservation_status'] = 'Available';
                    }
                }
            }
    
            return json_encode([
                'status' => 'success',
                'data' => $results
            ]);
        } catch (PDOException $e) {
            error_log("Database error in fetchAvailability: " . $e->getMessage());
            return json_encode([
                'status' => 'error',
                'message' => 'Database error: ' . $e->getMessage()
            ]);
        } catch (Exception $e) {
            error_log("General error in fetchAvailability: " . $e->getMessage());
            return json_encode([
                'status' => 'error',
                'message' => 'An unexpected error occurred: ' . $e->getMessage()
            ]);
        }
    } 
    public function fetchAvailableDrivers() {
        try {
            $sql = "
                SELECT 
                    u.users_id,
                    u.users_fname,
                    u.users_mname,
                    u.users_lname,
                    u.users_suffix,

                    u.title_id,
                    r.reservation_id,
                    r.reservation_title,
                    r.reservation_start_date,
                    r.reservation_end_date,
                    r.reschedule_start_date,
                    r.reschedule_end_date,
                    latest_status.reservation_status_status_id,
                    latest_status.reservation_active,
                    CASE 
                        WHEN latest_status.reservation_status_status_id = 6 AND latest_status.reservation_active = 1 THEN 'Reserved'
                        WHEN latest_status.reservation_status_status_id IN (10, 14) AND latest_status.reservation_active = 1 THEN 'Rescheduled'
                        ELSE 'Available'
                    END AS availability_status,
                    CASE 
                        WHEN latest_status.reservation_status_status_id IN (6, 10, 14) AND latest_status.reservation_active = 1 THEN 0
                        ELSE 1
                    END AS is_available
                FROM 
                    tbl_users u
                LEFT JOIN tbl_reservation_driver rd ON u.users_id = rd.reservation_driver_user_id
                LEFT JOIN tbl_reservation_vehicle rv ON rd.reservation_vehicle_id = rv.reservation_vehicle_id
                LEFT JOIN tbl_reservation r ON rv.reservation_reservation_id = r.reservation_id
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
                WHERE 
                    u.users_user_level_id = 19
                    AND u.is_active = 1
                    AND (r.reservation_id IS NULL OR r.reservation_id NOT IN (
                        SELECT DISTINCT reservation_reservation_id 
                        FROM tbl_reservation_status 
                        WHERE reservation_status_status_id IN (2, 5)
                    ))
                ORDER BY 
                    u.users_lname, u.users_fname, r.reservation_start_date DESC
            ";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute();
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Group drivers by user_id to handle multiple reservations
            $drivers = [];
            foreach ($results as $row) {
                $userId = $row['users_id'];
                
                if (!isset($drivers[$userId])) {
                    // Initialize driver data
                    $drivers[$userId] = [
                        'users_id' => $row['users_id'],
                        'users_fname' => $row['users_fname'],
                        'users_mname' => $row['users_mname'],
                        'users_lname' => $row['users_lname'],
                        'users_suffix' => $row['users_suffix'],
                        'title_id' => $row['title_id'],
                        'availability_status' => $row['availability_status'],
                        'is_available' => $row['is_available'],
                        'reservations' => []
                    ];
                }

                // Add reservation data if exists and matches criteria
                if ($row['reservation_id'] && $row['reservation_status_status_id'] && $row['reservation_active'] == 1) {
                    // Use rescheduled dates if reservation is rescheduled
                    $startDate = $row['reservation_status_status_id'] == 10 || $row['reservation_status_status_id'] == 14 ? 
                        ($row['reschedule_start_date'] ?? $row['reservation_start_date']) : 
                        $row['reservation_start_date'];
                    
                    $endDate = $row['reservation_status_status_id'] == 10 || $row['reservation_status_status_id'] == 14 ? 
                        ($row['reschedule_end_date'] ?? $row['reservation_end_date']) : 
                        $row['reservation_end_date'];
                    
                    $drivers[$userId]['reservations'][] = [
                        'reservation_id' => $row['reservation_id'],
                        'reservation_title' => $row['reservation_title'],
                        'reservation_start_date' => $startDate,
                        'reservation_end_date' => $endDate,
                        'reservation_status_status_id' => $row['reservation_status_status_id'],
                        'reservation_active' => $row['reservation_active']
                    ];
                }
            }

            return json_encode(['status' => 'success', 'data' => array_values($drivers)]);

        } catch (PDOException $e) {
            return json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
        }
    }

    public function doubleCheckAvailability($startDateTime, $endDateTime, $reservationId = null) {
        try {
            // Initialize the result array with empty arrays for each resource type
            $result = [
                'reservation_users' => [],
                'unavailable_vehicles' => [],
                'unavailable_venues' => [],
                'unavailable_equipment' => [],
                'unavailable_drivers' => []
            ];
    
            // Build the exclusion clause for the reservation ID
            $excludeReservationClause = '';
            $excludeParams = [];
            if ($reservationId !== null) {
                $excludeReservationClause = 'AND r.reservation_id != :excludeReservationId';
                $excludeParams[':excludeReservationId'] = $reservationId;
            }
    
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
                    WHERE reservation_status_status_id IN (10, 11, 14) AND reservation_active IN (0, 1)
                    GROUP BY reservation_reservation_id
                ) active_resched ON active_resched.reservation_reservation_id = r.reservation_id
                LEFT JOIN tbl_user_level ul ON u.users_user_level_id = ul.user_level_id
                LEFT JOIN tbl_departments d ON u.users_department_id = d.departments_id
                WHERE (
                    latest_status.reservation_status_status_id IN (6, 8, 10, 11, 14)
                    AND latest_status.reservation_active IN (0, 1)
                )
                AND (
                    (CASE
                        WHEN (
                                (latest_status.reservation_status_status_id = 10 AND latest_status.reservation_active = 1)
                                OR (
                                    latest_status.reservation_status_status_id IN (6, 8) AND latest_status.reservation_active = 1
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
                                    latest_status.reservation_status_status_id IN (6, 8) AND latest_status.reservation_active = 1
                                    AND active_resched.max_reschedule_status_id IS NOT NULL
                                )
                             )
                             AND r.reschedule_start_date IS NOT NULL AND r.reschedule_end_date IS NOT NULL
                        THEN r.reschedule_end_date ELSE r.reservation_end_date END) >= :startDateTime
                )
                {$excludeReservationClause}
            ";
            $stmt = $this->conn->prepare($userQuery);
            $params = array_merge([
                ':startDateTime' => $startDateTime,
                ':endDateTime' => $endDateTime
            ], $excludeParams);
            $stmt->execute($params);
            $result['reservation_users'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
            // --- Unavailable Vehicles: pick effective vehicle when rescheduled, apply latest-status and reschedule dates ---
            $vehicleQuery = "
                SELECT DISTINCT
                    rv.reservation_vehicle_id,
                    CASE 
                        WHEN (
                            (latest_status.reservation_status_status_id IN (10, 11, 14) AND latest_status.reservation_active = 1)
                            OR (
                                latest_status.reservation_status_status_id IN (6, 8) AND latest_status.reservation_active = 1
                                AND active_resched.max_reschedule_status_id IS NOT NULL
                            )
                        ) AND rv.reservation_change_vehicle_id IS NOT NULL
                        THEN rv.reservation_change_vehicle_id
                        ELSE v.vehicle_id
                    END AS vehicle_id,
                    CASE 
                        WHEN (
                            (latest_status.reservation_status_status_id IN (10, 11, 14) AND latest_status.reservation_active = 1)
                            OR (
                                latest_status.reservation_status_status_id IN (6, 8) AND latest_status.reservation_active = 1
                                AND active_resched.max_reschedule_status_id IS NOT NULL
                            )
                        ) AND rv.reservation_change_vehicle_id IS NOT NULL
                        THEN cv.vehicle_license
                        ELSE v.vehicle_license
                    END AS vehicle_license,
                    CASE 
                        WHEN (
                            (latest_status.reservation_status_status_id IN (10, 11, 14) AND latest_status.reservation_active = 1)
                            OR (
                                latest_status.reservation_status_status_id IN (6, 8) AND latest_status.reservation_active = 1
                                AND active_resched.max_reschedule_status_id IS NOT NULL
                            )
                        ) AND rv.reservation_change_vehicle_id IS NOT NULL
                        THEN cvmk.vehicle_make_name
                        ELSE vm.vehicle_make_name
                    END AS vehicle_make_name,
                    CASE 
                        WHEN (
                            (latest_status.reservation_status_status_id IN (10, 11, 14) AND latest_status.reservation_active = 1)
                            OR (
                                latest_status.reservation_status_status_id IN (6, 8) AND latest_status.reservation_active = 1
                                AND active_resched.max_reschedule_status_id IS NOT NULL
                            )
                        ) AND rv.reservation_change_vehicle_id IS NOT NULL
                        THEN cvmd.vehicle_model_name
                        ELSE vmd.vehicle_model_name
                    END AS vehicle_model_name,
                    r.reservation_id,
                    r.reservation_title,
                    CONCAT(u.users_fname, ' ', u.users_mname, ' ', u.users_lname) AS reserved_by
                FROM tbl_reservation r
                INNER JOIN tbl_reservation_vehicle rv ON r.reservation_id = rv.reservation_reservation_id
                INNER JOIN tbl_vehicle v ON rv.reservation_vehicle_vehicle_id = v.vehicle_id
                INNER JOIN tbl_vehicle_model vmd ON v.vehicle_model_id = vmd.vehicle_model_id
                INNER JOIN tbl_vehicle_make vm ON vmd.vehicle_model_vehicle_make_id = vm.vehicle_make_id
                INNER JOIN tbl_users u ON r.reservation_user_id = u.users_id
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
                    WHERE reservation_status_status_id IN (10, 11, 14) AND reservation_active IN (0, 1)
                    GROUP BY reservation_reservation_id
                ) active_resched ON active_resched.reservation_reservation_id = r.reservation_id
                WHERE r.reservation_id NOT IN (
                    SELECT DISTINCT reservation_reservation_id 
                    FROM tbl_reservation_status 
                    WHERE reservation_status_status_id IN (2, 5, 4)
                )
                AND NOT (
                    latest_status.reservation_status_status_id = 6 
                    AND active_resched.max_reschedule_status_id IS NOT NULL
                )
                AND (
                    (CASE
                        WHEN (
                                (latest_status.reservation_status_status_id IN (10, 11, 14) AND latest_status.reservation_active = 1)
                                OR (
                                    latest_status.reservation_status_status_id IN (6, 8) AND latest_status.reservation_active = 1
                                    AND active_resched.max_reschedule_status_id IS NOT NULL
                                )
                             )
                             AND r.reschedule_start_date IS NOT NULL AND r.reschedule_end_date IS NOT NULL
                        THEN r.reschedule_start_date ELSE r.reservation_start_date END) <= :endDateTime
                    AND
                    (CASE
                        WHEN (
                                (latest_status.reservation_status_status_id IN (10, 11, 14) AND latest_status.reservation_active = 1)
                                OR (
                                    latest_status.reservation_status_status_id IN (6, 8) AND latest_status.reservation_active = 1
                                    AND active_resched.max_reschedule_status_id IS NOT NULL
                                )
                             )
                             AND r.reschedule_start_date IS NOT NULL AND r.reschedule_end_date IS NOT NULL
                        THEN r.reschedule_end_date ELSE r.reservation_end_date END) >= :startDateTime
                )
                {$excludeReservationClause}
            ";
            $stmt = $this->conn->prepare($vehicleQuery);
            $stmt->execute($params);
            $result['unavailable_vehicles'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
            // --- Unavailable Venues: pick effective venue when rescheduled, apply latest-status and reschedule dates ---
            $venueQuery = "
                SELECT DISTINCT
                    rv.reservation_venue_id,
                    CASE 
                        WHEN (
                            (latest_status.reservation_status_status_id IN (10, 11, 14) AND latest_status.reservation_active = 1)
                            OR (
                                latest_status.reservation_status_status_id IN (6, 8) AND latest_status.reservation_active = 1
                                AND active_resched.max_reschedule_status_id IS NOT NULL
                            )
                        ) AND rv.reservation_change_venue_id IS NOT NULL
                        THEN rv.reservation_change_venue_id
                        ELSE v.ven_id
                    END AS ven_id,
                    CASE 
                        WHEN (
                            (latest_status.reservation_status_status_id IN (10, 11, 14) AND latest_status.reservation_active = 1)
                            OR (
                                latest_status.reservation_status_status_id IN (6, 8) AND latest_status.reservation_active = 1
                                AND active_resched.max_reschedule_status_id IS NOT NULL
                            )
                        ) AND rv.reservation_change_venue_id IS NOT NULL
                        THEN change_venue.ven_name
                        ELSE v.ven_name
                    END AS ven_name,
                    r.reservation_id,
                    r.reservation_title,
                    CONCAT(u.users_fname, ' ', u.users_mname, ' ', u.users_lname) AS reserved_by
                FROM tbl_reservation r
                INNER JOIN tbl_reservation_venue rv ON r.reservation_id = rv.reservation_reservation_id
                INNER JOIN tbl_venue v ON rv.reservation_venue_venue_id = v.ven_id
                INNER JOIN tbl_users u ON r.reservation_user_id = u.users_id
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
                    WHERE reservation_status_status_id IN (10, 11, 14) AND reservation_active IN (0, 1)
                    GROUP BY reservation_reservation_id
                ) active_resched ON active_resched.reservation_reservation_id = r.reservation_id
                WHERE r.reservation_id NOT IN (
                    SELECT DISTINCT reservation_reservation_id 
                    FROM tbl_reservation_status 
                    WHERE reservation_status_status_id IN (2, 5, 4)
                )
                AND NOT (
                    latest_status.reservation_status_status_id = 6 
                    AND active_resched.max_reschedule_status_id IS NOT NULL
                )
                AND (
                    (CASE
                        WHEN (
                                (latest_status.reservation_status_status_id IN (10, 11, 14) AND latest_status.reservation_active = 1)
                                OR (
                                    latest_status.reservation_status_status_id IN (6, 8) AND latest_status.reservation_active = 1
                                    AND active_resched.max_reschedule_status_id IS NOT NULL
                                )
                             )
                             AND r.reschedule_start_date IS NOT NULL AND r.reschedule_end_date IS NOT NULL
                        THEN r.reschedule_start_date ELSE r.reservation_start_date END) <= :endDateTime
                    AND
                    (CASE
                        WHEN (
                                (latest_status.reservation_status_status_id IN (10, 11, 14) AND latest_status.reservation_active = 1)
                                OR (
                                    latest_status.reservation_status_status_id IN (6, 8) AND latest_status.reservation_active = 1
                                    AND active_resched.max_reschedule_status_id IS NOT NULL
                                )
                             )
                             AND r.reschedule_start_date IS NOT NULL AND r.reschedule_end_date IS NOT NULL
                        THEN r.reschedule_end_date ELSE r.reservation_end_date END) >= :startDateTime
                )
                {$excludeReservationClause}
            ";
            $stmt = $this->conn->prepare($venueQuery);
            $stmt->execute($params);
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
                    COALESCE(SUM(re.reservation_equipment_quantity), 0) AS reserved_quantity,
                    GROUP_CONCAT(
                        DISTINCT CONCAT(r.reservation_id, ':', r.reservation_title, ':', 
                        CONCAT(u.users_fname, ' ', u.users_mname, ' ', u.users_lname))
                        SEPARATOR '|'
                    ) AS reservations_info
                FROM tbl_equipments e
                INNER JOIN EquipmentTotalQuantities etq ON e.equip_id = etq.equip_id
                LEFT JOIN tbl_reservation_equipment re ON e.equip_id = re.reservation_equipment_equip_id
                LEFT JOIN tbl_reservation r ON r.reservation_id = re.reservation_reservation_id
                LEFT JOIN tbl_users u ON r.reservation_user_id = u.users_id
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
                    WHERE reservation_status_status_id IN (10, 11, 14) AND reservation_active IN (0, 1)
                    GROUP BY reservation_reservation_id
                ) active_resched ON active_resched.reservation_reservation_id = r.reservation_id
                WHERE (r.reservation_id IS NULL OR r.reservation_id NOT IN (
                    SELECT DISTINCT reservation_reservation_id 
                    FROM tbl_reservation_status 
                    WHERE reservation_status_status_id IN (2, 5, 4)
                ))
                AND (r.reservation_id IS NULL OR (
                    latest_status.reservation_active IN (0, 1)
                ))
                AND (r.reservation_id IS NULL OR (
                    (CASE
                        WHEN (
                                (latest_status.reservation_status_status_id IN (10, 11, 14) AND latest_status.reservation_active = 1)
                                OR (
                                    latest_status.reservation_status_status_id IN (6, 8) AND latest_status.reservation_active = 1
                                    AND active_resched.max_reschedule_status_id IS NOT NULL
                                )
                             )
                             AND r.reschedule_start_date IS NOT NULL AND r.reschedule_end_date IS NOT NULL
                        THEN r.reschedule_start_date ELSE r.reservation_start_date END) <= :endDateTime
                    AND
                    (CASE
                        WHEN (
                                (latest_status.reservation_status_status_id IN (10, 11, 14) AND latest_status.reservation_active = 1)
                                OR (
                                    latest_status.reservation_status_status_id IN (6, 8) AND latest_status.reservation_active = 1
                                    AND active_resched.max_reschedule_status_id IS NOT NULL
                                )
                             )
                             AND r.reschedule_start_date IS NOT NULL AND r.reschedule_end_date IS NOT NULL
                        THEN r.reschedule_end_date ELSE r.reservation_end_date END) >= :startDateTime
                ))
                " . ($reservationId !== null ? "AND (r.reservation_id IS NULL OR r.reservation_id != :excludeReservationId)" : "") . "
                GROUP BY e.equip_id, e.equip_name, etq.total_quantity
                HAVING COALESCE(SUM(re.reservation_equipment_quantity), 0) > 0
            ";
            $stmt = $this->conn->prepare($equipmentQuery);
            $stmt->execute($params);
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
                    WHERE reservation_status_status_id IN (10, 11, 14) AND reservation_active IN (0, 1)
                    GROUP BY reservation_reservation_id
                ) active_resched ON active_resched.reservation_reservation_id = r.reservation_id
                WHERE r.reservation_id NOT IN (
                    SELECT DISTINCT reservation_reservation_id 
                    FROM tbl_reservation_status 
                    WHERE reservation_status_status_id IN (1, 2, 4, 5)
                )
                AND (
                    latest_status.reservation_status_status_id IN (6, 8, 10, 11, 14)
                    AND latest_status.reservation_active IN (0, 1)
                )
                AND (
                    (CASE
                        WHEN (
                                (latest_status.reservation_status_status_id = 10 AND latest_status.reservation_active = 1)
                                OR (
                                    latest_status.reservation_status_status_id IN (6, 8) AND latest_status.reservation_active = 1
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
                                    latest_status.reservation_status_status_id IN (6, 8) AND latest_status.reservation_active = 1
                                    AND active_resched.max_reschedule_status_id IS NOT NULL
                                )
                             )
                             AND r.reschedule_start_date IS NOT NULL AND r.reschedule_end_date IS NOT NULL
                        THEN r.reschedule_end_date ELSE r.reservation_end_date END) >= :startDateTime
                )
                {$excludeReservationClause}
            ";
            $stmt = $this->conn->prepare($sqlReservedDrivers);
            $stmt->execute($params);
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

    public function fetchEquipments($startDateTime = null, $endDateTime = null) {
        header('Content-Type: application/json');
    
        try {
            $mainQuery = "
                SELECT
                    e.equip_id,
                    e.equip_name,
                    e.equip_type,
                    e.equip_created_at,
                    tec.equipments_category_name AS category_name,
                    e.equipments_category_id,
                    CASE
                        WHEN e.equip_type = 'Bulk' THEN COALESCE(eq.quantity, 0)
                        ELSE COALESCE(eu.unit_count, 0)
                    END AS total_on_hand,
                    COALESCE(r.reserved_qty, 0) AS total_reserved,
                    (
                        CASE
                            WHEN e.equip_type = 'Bulk' THEN COALESCE(eq.quantity, 0)
                            ELSE COALESCE(eu.unit_count, 0)
                        END
                        - COALESCE(r.reserved_qty, 0)
                    ) AS total_available
                FROM tbl_equipments e
                LEFT JOIN tbl_equipment_category tec
                    ON e.equipments_category_id = tec.equipments_category_id
                LEFT JOIN (
                    SELECT
                        equip_id,
                        quantity
                    FROM tbl_equipment_quantity
                    WHERE (equip_id, last_updated) IN (
                        SELECT equip_id, MAX(last_updated)
                        FROM tbl_equipment_quantity
                        GROUP BY equip_id
                    )
                ) AS eq ON e.equip_id = eq.equip_id AND e.equip_type = 'Bulk'
                LEFT JOIN (
                    SELECT
                        equip_id,
                        COUNT(*) AS unit_count
                    FROM tbl_equipment_unit
                    WHERE is_active = 1 AND status_availability_id != 2
                    GROUP BY equip_id
                ) AS eu ON e.equip_id = eu.equip_id AND e.equip_type != 'Bulk'
                LEFT JOIN (
                    SELECT
                        re.reservation_equipment_equip_id AS equip_id,
                        SUM(re.reservation_equipment_quantity) AS reserved_qty
                    FROM tbl_reservation_equipment re
                    JOIN tbl_reservation r
                        ON r.reservation_id = re.reservation_reservation_id
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
                        WHERE reservation_status_status_id IN (10, 14) AND reservation_active = 1
                        GROUP BY reservation_reservation_id
                    ) active_resched ON active_resched.reservation_reservation_id = r.reservation_id
                    WHERE (
                        ls.reservation_status_status_id IN (1, 6, 8, 10, 14)
                        AND (
                            (ls.reservation_status_status_id = 6 AND ls.reservation_active = 1)
                            OR (ls.reservation_status_status_id IN (1, 8, 10, 14) AND (ls.reservation_active = 0 OR ls.reservation_active = 1))
                        )
                    )
                    AND (
                        (CASE
                            WHEN (
                                    (ls.reservation_status_status_id IN (10, 14) AND ls.reservation_active = 1)
                                    OR (
                                        ls.reservation_status_status_id = 6 AND ls.reservation_active = 1
                                        AND active_resched.max_reschedule_status_id IS NOT NULL
                                    )
                                 )
                                 AND r.reschedule_start_date IS NOT NULL AND r.reschedule_end_date IS NOT NULL
                            THEN r.reschedule_start_date ELSE r.reservation_start_date END) <= :end
                        AND (CASE
                            WHEN (
                                    (ls.reservation_status_status_id IN (10, 14) AND ls.reservation_active = 1)
                                    OR (
                                        ls.reservation_status_status_id = 6 AND ls.reservation_active = 1
                                        AND active_resched.max_reschedule_status_id IS NOT NULL
                                    )
                                 )
                                 AND r.reschedule_start_date IS NOT NULL AND r.reschedule_end_date IS NOT NULL
                            THEN r.reschedule_end_date ELSE r.reservation_end_date END) >= :start
                    )
                    GROUP BY re.reservation_equipment_equip_id
                ) AS r ON e.equip_id = r.equip_id
                WHERE e.is_active = 1
                ORDER BY e.equip_name";
    
            $mainStmt = $this->conn->prepare($mainQuery);
    
            // Always bind these, even if null (SQL will handle them accordingly)
            $mainStmt->bindParam(':start', $startDateTime);
            $mainStmt->bindParam(':end', $endDateTime);
    
            $mainStmt->execute();
            $equipments = $mainStmt->fetchAll(PDO::FETCH_ASSOC);
    
            $finalResults = [];
            foreach ($equipments as $equip) {
                $totalOnHand = (int)$equip['total_on_hand'];
                $reserved = (int)$equip['total_reserved'];
                $available = (int)$equip['total_available'];
    
                if ($available > 0 || $reserved > 0) {
                    $equip['current_quantity'] = $totalOnHand;
                    $equip['reserved_quantity'] = $reserved;
                    $equip['available_quantity'] = $available;
                    $equip['is_available'] = ($available > 0);
                    $equip['availability_status'] = $available > 0 ? 'Available' : 'Unavailable';
                    $finalResults[] = $equip;
                }
            }
    
            http_response_code(200);
            echo json_encode([
                'status'    => 'success',
                'data'      => $finalResults,
                'timestamp' => date('Y-m-d H:i:s')
            ], JSON_UNESCAPED_SLASHES);
    
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode([
                'status'    => 'error',
                'message'   => 'Database error: ' . $e->getMessage(),
                'timestamp' => date('Y-m-d H:i:s')
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'status'    => 'error',
                'message'   => 'General error: ' . $e->getMessage(),
                'timestamp' => date('Y-m-d H:i:s')
            ]);
        }
    }

    public function fetchRequestById($reservationId) {
        try {
            // First, get all status history for this reservation
            $statusHistorySql = "
                SELECT 
                    rs.reservation_status_id,
                    rs.reservation_status_status_id AS status_id,
                    sm.status_master_name AS status_name,
                    rs.reservation_active,
                    rs.reservation_updated_at,
                    rs.reservation_users_id,
                    CONCAT_WS(' ', u.users_fname, u.users_mname, u.users_lname) AS updated_by_name
                FROM tbl_reservation_status rs
                JOIN tbl_status_master sm ON rs.reservation_status_status_id = sm.status_master_id
                LEFT JOIN tbl_users u ON rs.reservation_users_id = u.users_id
                WHERE rs.reservation_reservation_id = :reservation_id
                ORDER BY rs.reservation_status_id DESC, rs.reservation_updated_at DESC
            ";
            
            $statusStmt = $this->conn->prepare($statusHistorySql);
            $statusStmt->bindParam(':reservation_id', $reservationId, PDO::PARAM_INT);
            $statusStmt->execute();
            $statusHistory = $statusStmt->fetchAll(PDO::FETCH_ASSOC);

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
                        CONCAT_WS(0x1F,
                            COALESCE(v.reservation_venue_id, ''),
                            COALESCE(v.reservation_venue_venue_id, ''),
                            COALESCE(venue.ven_name, ''),
                            COALESCE(venue.ven_occupancy, ''),
                            COALESCE(venue.ven_pic, ''),
                            COALESCE(v.reservation_change_venue_id, ''),
                            COALESCE(change_venue.ven_name, '')
                        )
                        SEPARATOR 0x1E
                    ) as venue_data,

                    -- Vehicle details with complete information
                    GROUP_CONCAT(DISTINCT
                        CONCAT_WS(0x1F,
                            COALESCE(ve.reservation_vehicle_id, ''),
                            COALESCE(ve.reservation_vehicle_vehicle_id, ''),
                            COALESCE(vm.vehicle_license, ''),
                            COALESCE(vmm.vehicle_model_name, ''),
                            COALESCE(vm.year, ''),
                            COALESCE(vma.vehicle_make_name, ''),
                            COALESCE(vc.vehicle_category_name, ''),
                            COALESCE(vm.vehicle_pic, ''),
                            COALESCE(ve.reservation_change_vehicle_id, ''),
                            COALESCE(change_vm.vehicle_license, ''),
                            COALESCE(change_vmm.vehicle_model_name, ''),
                            COALESCE(change_vm.year, ''),
                            COALESCE(change_vma.vehicle_make_name, ''),
                            COALESCE(change_vc.vehicle_category_name, ''),
                            COALESCE(change_vm.vehicle_pic, '')
                        )
                        SEPARATOR 0x1E
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
                LEFT JOIN tbl_venue change_venue ON v.reservation_change_venue_id = change_venue.ven_id

                LEFT JOIN tbl_reservation_vehicle ve ON r.reservation_id = ve.reservation_reservation_id
                -- Original vehicle joins with complete details
                LEFT JOIN tbl_vehicle vm ON ve.reservation_vehicle_vehicle_id = vm.vehicle_id
                LEFT JOIN tbl_vehicle_model vmm ON vm.vehicle_model_id = vmm.vehicle_model_id
                LEFT JOIN tbl_vehicle_make vma ON vmm.vehicle_model_vehicle_make_id = vma.vehicle_make_id
                LEFT JOIN tbl_vehicle_category vc ON vmm.vehicle_category_id = vc.vehicle_category_id
                -- Change vehicle joins with complete details
                LEFT JOIN tbl_vehicle change_vm ON ve.reservation_change_vehicle_id = change_vm.vehicle_id
                LEFT JOIN tbl_vehicle_model change_vmm ON change_vm.vehicle_model_id = change_vmm.vehicle_model_id
                LEFT JOIN tbl_vehicle_make change_vma ON change_vmm.vehicle_model_vehicle_make_id = change_vma.vehicle_make_id
                LEFT JOIN tbl_vehicle_category change_vc ON change_vmm.vehicle_category_id = change_vc.vehicle_category_id

                LEFT JOIN tbl_reservation_equipment e ON r.reservation_id = e.reservation_reservation_id
                LEFT JOIN tbl_equipments equip ON e.reservation_equipment_equip_id = equip.equip_id

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
                    'reschedule_start_date' => $row['reschedule_start_date'],
                    'reschedule_end_date' => $row['reschedule_end_date'],
                    'reservation_participants' => $row['reservation_participants'],
                    'additional_note' => $row['additional_note'],
                    'status_name' => $row['status_name'],  // Keep for backward compatibility
                    'status_id' => $row['status_id'],      // Keep for backward compatibility
                    'active' => $row['active'],            // Keep for backward compatibility
                    'reservation_user_id' => $row['reservation_user_id'],
                    'requester_name' => $row['requester_name'],
                    'department_name' => $row['department_name'],
                    'user_level_name' => $row['user_level_name'],
                    'status_history' => $statusHistory     // Add full status history
                ];

                // VENUES
                if (!empty($row['venue_data'])) {
                    foreach (explode(chr(30), $row['venue_data']) as $venueStr) {
                        if ($venueStr === '' || $venueStr === null) { continue; }
                        $venueParts = explode(chr(31), $venueStr);
                        // Ensure at least base fields exist and not all empty
                        if (count($venueParts) >= 5 && trim(implode('', $venueParts)) !== '') {
                            $venueItem = [
                                'reservation_venue_id' => $venueParts[0] ?? '',
                                'venue_id' => $venueParts[1] ?? '',
                                'venue_name' => $venueParts[2] ?? '',
                                'occupancy' => $venueParts[3] ?? '',
                                'picture' => $venueParts[4] ?? ''
                            ];
                            if (count($venueParts) >= 7) {
                                $venueItem['change_venue_id'] = $venueParts[5] ?? '';
                                $venueItem['change_venue_name'] = $venueParts[6] ?? '';
                            }
                            $venues[] = $venueItem;
                        }
                    }
                }

                // VEHICLES - Updated to include all vehicle details
                if (!empty($row['vehicle_data'])) {
                    foreach (explode(chr(30), $row['vehicle_data']) as $vehicleStr) {
                        if ($vehicleStr === '' || $vehicleStr === null) { continue; }
                        $vehicleParts = explode(chr(31), $vehicleStr);

                        // Skip entries where all parts are empty
                        if (trim(implode('', $vehicleParts)) === '') { continue; }

                        if (count($vehicleParts) >= 8) {
                            $vehicleItem = [
                                'reservation_vehicle_id' => $vehicleParts[0] ?? '',
                                'vehicle_id' => $vehicleParts[1] ?? '',
                                'license' => $vehicleParts[2] ?? '',
                                'model' => $vehicleParts[3] ?? '',
                                'year' => $vehicleParts[4] ?? '',
                                'make' => $vehicleParts[5] ?? '',
                                'category' => $vehicleParts[6] ?? '',
                                'picture' => $vehicleParts[7] ?? ''
                            ];
                            
                            // If change vehicle data is present (15 parts total), include it as well
                            if (count($vehicleParts) >= 15) {
                                $vehicleItem['change_vehicle_id'] = $vehicleParts[8] ?? '';
                                $vehicleItem['change_vehicle_license'] = $vehicleParts[9] ?? '';
                                $vehicleItem['change_vehicle_model'] = $vehicleParts[10] ?? '';
                                $vehicleItem['change_vehicle_year'] = $vehicleParts[11] ?? '';
                                $vehicleItem['change_vehicle_make'] = $vehicleParts[12] ?? '';
                                $vehicleItem['change_vehicle_category'] = $vehicleParts[13] ?? '';
                                $vehicleItem['change_vehicle_picture'] = $vehicleParts[14] ?? '';
                            }

                            // Only push if at least one primary field has a value
                            if (
                                $vehicleItem['reservation_vehicle_id'] !== '' ||
                                $vehicleItem['vehicle_id'] !== '' ||
                                $vehicleItem['license'] !== '' ||
                                $vehicleItem['model'] !== ''
                            ) {
                                $vehicles[] = $vehicleItem;
                            }
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

                // DRIVERS: fetch all drivers for this reservation and their details
                $drivers = [];
                $driverStmt = $this->conn->prepare(
                    "SELECT rd.reservation_driver_id, rd.reservation_driver_user_id, rd.reservation_vehicle_id, rv.reservation_vehicle_vehicle_id, rd.is_accepted_trip, rd.driver_name, rd.created_at, rd.updated_at, u.users_fname, u.users_mname, u.users_lname, u.users_birthdate, u.users_suffix, u.users_email, u.users_school_id, u.users_contact_number, u.users_user_level_id, u.users_pic, u.is_active, u.title_id
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
                        'reservation_vehicle_vehicle_id' => $driverRow['reservation_vehicle_vehicle_id'],
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

    public function fetchAvailableVenues($startDateTime = null, $endDateTime = null, $excludeIds = null) {
        header('Content-Type: application/json');
        
        try {
            // Log the input parameters
            error_log("fetchAvailableVenues called with:");
            error_log("- startDateTime: " . ($startDateTime ?? 'null'));
            error_log("- endDateTime: " . ($endDateTime ?? 'null'));
            error_log("- excludeIds: " . (is_array($excludeIds) ? implode(',', $excludeIds) : ($excludeIds ?? 'null')));
            error_log("- excludeIds type: " . gettype($excludeIds));
            
            // Step 1: Get all available venues (not status 2 - unavailable)
            $sql = "
                SELECT 
                    v.ven_id, 
                    v.ven_name, 
                    v.ven_occupancy, 
                    v.ven_created_at, 
                    v.ven_updated_at, 
                    v.status_availability_id,
                    sa.status_availability_name,
                    v.ven_pic
                FROM tbl_venue v
                INNER JOIN tbl_status_availability sa ON v.status_availability_id = sa.status_availability_id
                WHERE v.status_availability_id NOT IN (2)
                ORDER BY v.ven_name";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->execute();
            $allVenues = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            error_log("Total venues found (before exclusions): " . count($allVenues));
            
            // Step 2: Filter out excluded venue IDs
            $filteredVenues = $allVenues;
            if (!empty($excludeIds)) {
                if (is_array($excludeIds)) {
                    $filteredVenues = array_filter($allVenues, function($venue) use ($excludeIds) {
                        return !in_array($venue['ven_id'], $excludeIds);
                    });
                    error_log("Excluded venue IDs: " . implode(',', $excludeIds));
                } else {
                    $filteredVenues = array_filter($allVenues, function($venue) use ($excludeIds) {
                        return $venue['ven_id'] != $excludeIds;
                    });
                    error_log("Excluded venue ID: " . $excludeIds);
                }
                error_log("Venues after exclusion filter: " . count($filteredVenues));
            }
            
            // Step 3: Apply date-based reservation filtering if dates are provided
            if ($startDateTime && $endDateTime && !empty($filteredVenues)) {
                error_log("Applying reservation date filtering...");
                
                // Get venue IDs that are currently reserved/occupied during the specified time
                $reservedVenuesSql = "
                    SELECT DISTINCT 
                        CASE 
                            WHEN (
                                (ls.reservation_status_status_id IN (10, 14) AND ls.reservation_active = 1)
                                OR (active_resched.max_reschedule_status_id IS NOT NULL AND ls.reservation_status_status_id = 6 AND ls.reservation_active = 1)
                            ) AND rv.reservation_change_venue_id IS NOT NULL
                            THEN rv.reservation_change_venue_id
                            ELSE rv.reservation_venue_venue_id
                        END AS venue_id_in_use
                    FROM tbl_reservation_venue rv
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
                    ) ls ON ls.reservation_reservation_id = r.reservation_id
                    LEFT JOIN (
                        SELECT reservation_reservation_id, MAX(reservation_status_id) AS max_reschedule_status_id
                        FROM tbl_reservation_status
                        WHERE reservation_status_status_id IN (10, 14) AND reservation_active = 1
                        GROUP BY reservation_reservation_id
                    ) active_resched ON active_resched.reservation_reservation_id = r.reservation_id
                    WHERE 
                        ls.reservation_status_status_id IN (1, 6, 8, 10, 14)
                        AND r.reservation_id NOT IN (
                            SELECT DISTINCT reservation_reservation_id 
                            FROM tbl_reservation_status 
                            WHERE reservation_status_status_id IN (2, 5)
                        )
                        AND (
                            (CASE
                                WHEN (
                                        (ls.reservation_status_status_id IN (10, 14) AND ls.reservation_active = 1)
                                        OR (
                                            ls.reservation_status_status_id = 6 AND ls.reservation_active = 1
                                            AND active_resched.max_reschedule_status_id IS NOT NULL
                                        )
                                     )
                                     AND r.reschedule_start_date IS NOT NULL AND r.reschedule_end_date IS NOT NULL
                                THEN r.reschedule_start_date ELSE r.reservation_start_date END) <= ?
                            AND (CASE
                                WHEN (
                                        (ls.reservation_status_status_id IN (10, 14) AND ls.reservation_active = 1)
                                        OR (
                                            ls.reservation_status_status_id = 6 AND ls.reservation_active = 1
                                            AND active_resched.max_reschedule_status_id IS NOT NULL
                                        )
                                     )
                                     AND r.reschedule_start_date IS NOT NULL AND r.reschedule_end_date IS NOT NULL
                                THEN r.reschedule_end_date ELSE r.reservation_end_date END) >= ?
                        )
                    
                    UNION
                    
                    SELECT DISTINCT 
                        CASE 
                            WHEN rv.reservation_change_venue_id IS NOT NULL
                            THEN rv.reservation_change_venue_id
                            ELSE rv.reservation_venue_venue_id
                        END AS venue_id_in_use
                    FROM tbl_reservation_venue rv
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
                    ) ls ON ls.reservation_reservation_id = r.reservation_id
                    LEFT JOIN (
                        SELECT reservation_reservation_id, MAX(reservation_status_id) AS max_reschedule_status_id
                        FROM tbl_reservation_status
                        WHERE reservation_status_status_id IN (10, 14) AND reservation_active = 1
                        GROUP BY reservation_reservation_id
                    ) active_resched ON active_resched.reservation_reservation_id = r.reservation_id
                    WHERE 
                        ls.reservation_status_status_id IN (6, 10, 14)
                        AND ls.reservation_active = 1
                        AND r.reservation_id NOT IN (
                            SELECT DISTINCT reservation_reservation_id 
                            FROM tbl_reservation_status 
                            WHERE reservation_status_status_id IN (2, 5)
                        )
                        AND (
                            (CASE
                                WHEN (
                                        (ls.reservation_status_status_id IN (10, 14) AND ls.reservation_active = 1)
                                        OR (
                                            ls.reservation_status_status_id = 6 AND ls.reservation_active = 1
                                            AND active_resched.max_reschedule_status_id IS NOT NULL
                                        )
                                     )
                                     AND r.reschedule_start_date IS NOT NULL AND r.reschedule_end_date IS NOT NULL
                                THEN r.reschedule_start_date ELSE r.reservation_start_date END) <= ?
                            AND (CASE
                                WHEN (
                                        (ls.reservation_status_status_id IN (10, 14) AND ls.reservation_active = 1)
                                        OR (
                                            ls.reservation_status_status_id = 6 AND ls.reservation_active = 1
                                            AND active_resched.max_reschedule_status_id IS NOT NULL
                                        )
                                     )
                                     AND r.reschedule_start_date IS NOT NULL AND r.reschedule_end_date IS NOT NULL
                                THEN r.reschedule_end_date ELSE r.reservation_end_date END) >= ?
                        )";
                
                $reservedStmt = $this->conn->prepare($reservedVenuesSql);
                $reservedStmt->execute([$endDateTime, $startDateTime, $endDateTime, $startDateTime]);
                $reservedVenues = $reservedStmt->fetchAll(PDO::FETCH_COLUMN);
                
                error_log("Reserved venue IDs during specified time: " . (empty($reservedVenues) ? 'none' : implode(',', $reservedVenues)));
                
                // Filter out reserved venues
                $finalVenues = array_filter($filteredVenues, function($venue) use ($reservedVenues) {
                    return !in_array($venue['ven_id'], $reservedVenues);
                });
                
                error_log("Final available venues after reservation filtering: " . count($finalVenues));
            } else {
                $finalVenues = $filteredVenues;
                if (!$startDateTime || !$endDateTime) {
                    error_log("No date filtering applied - missing start or end date");
                }
            }
            
            // Re-index array to ensure proper JSON encoding
            $finalVenues = array_values($finalVenues);
            
            error_log("Final venue count returned: " . count($finalVenues));
            if (!empty($finalVenues)) {
                $venueIds = array_column($finalVenues, 'ven_id');
                error_log("Final venue IDs: " . implode(',', $venueIds));
            }
            
            http_response_code(200);
            echo json_encode([
                'status' => 'success',
                'data' => $finalVenues,
                'count' => count($finalVenues),
                'filters_applied' => [
                    'excluded_ids' => !empty($excludeIds),
                    'date_filtering' => ($startDateTime && $endDateTime)
                ],
                'timestamp' => date('Y-m-d H:i:s')
            ], JSON_UNESCAPED_SLASHES);
            
        } catch (PDOException $e) {
            error_log("Database error in fetchAvailableVenues: " . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'status' => 'error',
                'message' => 'Database error: ' . $e->getMessage(),
                'timestamp' => date('Y-m-d H:i:s')
            ]);
        } catch (Exception $e) {
            error_log("General error in fetchAvailableVenues: " . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'status' => 'error',
                'message' => 'General error: ' . $e->getMessage(),
                'timestamp' => date('Y-m-d H:i:s')
            ]);
        }
    }

    public function fetchAvailableVehicles($startDateTime = null, $endDateTime = null, $excludeIds = null) {
        header('Content-Type: application/json');
        
        try {
            // Log the input parameters
            error_log("fetchAvailableVehicles called with:");
            error_log("- startDateTime: " . ($startDateTime ?? 'null'));
            error_log("- endDateTime: " . ($endDateTime ?? 'null'));
            error_log("- excludeIds: " . (is_array($excludeIds) ? implode(',', $excludeIds) : ($excludeIds ?? 'null')));
            error_log("- excludeIds type: " . gettype($excludeIds));
            
            // Step 1: Get all available vehicles (not status 2 - unavailable)
            $sql = "
                SELECT 
                    vh.vehicle_id, 
                    CONCAT(vm.vehicle_make_name, ' ', vmo.vehicle_model_name, ' ', vc.vehicle_category_name, ' - ', vh.vehicle_license) AS vehicle_name,
                    vh.vehicle_license,
                    vh.year,
                    vh.created_at AS vehicle_created_at, 
                    vh.updated_at AS vehicle_updated_at, 
                    vh.status_availability_id,
                    sa.status_availability_name,
                    vh.vehicle_pic
                FROM tbl_vehicle vh
                INNER JOIN tbl_status_availability sa ON vh.status_availability_id = sa.status_availability_id
                INNER JOIN tbl_vehicle_model vmo ON vh.vehicle_model_id = vmo.vehicle_model_id
                INNER JOIN tbl_vehicle_make vm ON vmo.vehicle_model_vehicle_make_id = vm.vehicle_make_id
                INNER JOIN tbl_vehicle_category vc ON vmo.vehicle_category_id = vc.vehicle_category_id
                WHERE vh.status_availability_id NOT IN (2) AND vh.is_active = 1
                ORDER BY vehicle_name";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->execute();
            $allVehicles = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            error_log("Total vehicles found (before exclusions): " . count($allVehicles));
            
            // Step 2: Filter out excluded vehicle IDs
            $filteredVehicles = $allVehicles;
            if (!empty($excludeIds)) {
                if (is_array($excludeIds)) {
                    $filteredVehicles = array_filter($allVehicles, function($vehicle) use ($excludeIds) {
                        return !in_array($vehicle['vehicle_id'], $excludeIds);
                    });
                    error_log("Excluded vehicle IDs: " . implode(',', $excludeIds));
                } else {
                    $filteredVehicles = array_filter($allVehicles, function($vehicle) use ($excludeIds) {
                        return $vehicle['vehicle_id'] != $excludeIds;
                    });
                    error_log("Excluded vehicle ID: " . $excludeIds);
                }
                error_log("Vehicles after exclusion filter: " . count($filteredVehicles));
            }
            
            // Step 3: Apply date-based reservation filtering if dates are provided
            if ($startDateTime && $endDateTime && !empty($filteredVehicles)) {
                error_log("Applying reservation date filtering...");
                
                // Get vehicle IDs that are currently reserved/occupied during the specified time
                $reservedVehiclesSql = "
                    SELECT DISTINCT 
                        CASE 
                            WHEN (
                                (ls.reservation_status_status_id IN (10, 14) AND ls.reservation_active = 1)
                                OR (active_resched.max_reschedule_status_id IS NOT NULL AND ls.reservation_status_status_id = 6 AND ls.reservation_active = 1)
                            ) AND rv.reservation_change_vehicle_id IS NOT NULL
                            THEN rv.reservation_change_vehicle_id
                            ELSE rv.reservation_vehicle_vehicle_id
                        END AS vehicle_id_in_use
                    FROM tbl_reservation_vehicle rv
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
                    ) ls ON ls.reservation_reservation_id = r.reservation_id
                    LEFT JOIN (
                        SELECT reservation_reservation_id, MAX(reservation_status_id) AS max_reschedule_status_id
                        FROM tbl_reservation_status
                        WHERE reservation_status_status_id IN (10, 14) AND reservation_active = 1
                        GROUP BY reservation_reservation_id
                    ) active_resched ON active_resched.reservation_reservation_id = r.reservation_id
                    WHERE 
                        ls.reservation_status_status_id IN (1, 6, 8, 10, 14)
                        AND r.reservation_id NOT IN (
                            SELECT DISTINCT reservation_reservation_id 
                            FROM tbl_reservation_status 
                            WHERE reservation_status_status_id IN (2, 5)
                        )
                        AND (
                            (CASE
                                WHEN (
                                        (ls.reservation_status_status_id IN (10, 14) AND ls.reservation_active = 1)
                                        OR (
                                            ls.reservation_status_status_id = 6 AND ls.reservation_active = 1
                                            AND active_resched.max_reschedule_status_id IS NOT NULL
                                        )
                                     )
                                     AND r.reschedule_start_date IS NOT NULL AND r.reschedule_end_date IS NOT NULL
                                THEN r.reschedule_start_date ELSE r.reservation_start_date END) <= ?
                            AND (CASE
                                WHEN (
                                        (ls.reservation_status_status_id IN (10, 14) AND ls.reservation_active = 1)
                                        OR (
                                            ls.reservation_status_status_id = 6 AND ls.reservation_active = 1
                                            AND active_resched.max_reschedule_status_id IS NOT NULL
                                        )
                                     )
                                     AND r.reschedule_start_date IS NOT NULL AND r.reschedule_end_date IS NOT NULL
                                THEN r.reschedule_end_date ELSE r.reservation_end_date END) >= ?
                        )
                    
                    UNION
                    
                    SELECT DISTINCT 
                        CASE 
                            WHEN rv.reservation_change_vehicle_id IS NOT NULL
                            THEN rv.reservation_change_vehicle_id
                            ELSE rv.reservation_vehicle_vehicle_id
                        END AS vehicle_id_in_use
                    FROM tbl_reservation_vehicle rv
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
                    ) ls ON ls.reservation_reservation_id = r.reservation_id
                    LEFT JOIN (
                        SELECT reservation_reservation_id, MAX(reservation_status_id) AS max_reschedule_status_id
                        FROM tbl_reservation_status
                        WHERE reservation_status_status_id IN (10, 14) AND reservation_active = 1
                        GROUP BY reservation_reservation_id
                    ) active_resched ON active_resched.reservation_reservation_id = r.reservation_id
                    WHERE 
                        ls.reservation_status_status_id IN (6, 10, 14)
                        AND ls.reservation_active = 1
                        AND r.reservation_id NOT IN (
                            SELECT DISTINCT reservation_reservation_id 
                            FROM tbl_reservation_status 
                            WHERE reservation_status_status_id IN (2, 5)
                        )
                        AND (
                            (CASE
                                WHEN (
                                        (ls.reservation_status_status_id IN (10, 14) AND ls.reservation_active = 1)
                                        OR (
                                            ls.reservation_status_status_id = 6 AND ls.reservation_active = 1
                                            AND active_resched.max_reschedule_status_id IS NOT NULL
                                        )
                                     )
                                     AND r.reschedule_start_date IS NOT NULL AND r.reschedule_end_date IS NOT NULL
                                THEN r.reschedule_start_date ELSE r.reservation_start_date END) <= ?
                            AND (CASE
                                WHEN (
                                        (ls.reservation_status_status_id IN (10, 14) AND ls.reservation_active = 1)
                                        OR (
                                            ls.reservation_status_status_id = 6 AND ls.reservation_active = 1
                                            AND active_resched.max_reschedule_status_id IS NOT NULL
                                        )
                                     )
                                     AND r.reschedule_start_date IS NOT NULL AND r.reschedule_end_date IS NOT NULL
                                THEN r.reschedule_end_date ELSE r.reservation_end_date END) >= ?
                        )";
                
                $reservedStmt = $this->conn->prepare($reservedVehiclesSql);
                $reservedStmt->execute([$endDateTime, $startDateTime, $endDateTime, $startDateTime]);
                $reservedVehicles = $reservedStmt->fetchAll(PDO::FETCH_COLUMN);
                
                error_log("Reserved vehicle IDs during specified time: " . (empty($reservedVehicles) ? 'none' : implode(',', $reservedVehicles)));
                
                // Filter out reserved vehicles
                $finalVehicles = array_filter($filteredVehicles, function($vehicle) use ($reservedVehicles) {
                    return !in_array($vehicle['vehicle_id'], $reservedVehicles);
                });
                
                error_log("Final available vehicles after reservation filtering: " . count($finalVehicles));
            } else {
                $finalVehicles = $filteredVehicles;
                if (!$startDateTime || !$endDateTime) {
                    error_log("No date filtering applied - missing start or end date");
                }
            }
            
            // Re-index array to ensure proper JSON encoding
            $finalVehicles = array_values($finalVehicles);
            
            error_log("Final vehicle count returned: " . count($finalVehicles));
            if (!empty($finalVehicles)) {
                $vehicleIds = array_column($finalVehicles, 'vehicle_id');
                error_log("Final vehicle IDs: " . implode(',', $vehicleIds));
            }
            
            http_response_code(200);
            echo json_encode([
                'status' => 'success',
                'data' => $finalVehicles,
                'count' => count($finalVehicles),
                'filters_applied' => [
                    'excluded_ids' => !empty($excludeIds),
                    'date_filtering' => ($startDateTime && $endDateTime)
                ],
                'timestamp' => date('Y-m-d H:i:s')
            ], JSON_UNESCAPED_SLASHES);
            
        } catch (PDOException $e) {
            error_log("Database error in fetchAvailableVehicles: " . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'status' => 'error',
                'message' => 'Database error: ' . $e->getMessage(),
                'timestamp' => date('Y-m-d H:i:s')
            ]);
        } catch (Exception $e) {
            error_log("General error in fetchAvailableVehicles: " . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'status' => 'error',
                'message' => 'General error: ' . $e->getMessage(),
                'timestamp' => date('Y-m-d H:i:s')
            ]);
        }
    }

    public function updateVenueReschedule($reservation_venue_id, $reservation_change_venue_id) {
        try {
            if ($reservation_venue_id === null || $reservation_change_venue_id === null) {
                return json_encode(['status' => 'error', 'message' => 'Missing required parameters']);
            }
    
            $sql = "UPDATE tbl_reservation_venue
                    SET reservation_change_venue_id = :reservation_change_venue_id
                    WHERE reservation_venue_id = :reservation_venue_id";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindValue(':reservation_change_venue_id', (int)$reservation_change_venue_id, PDO::PARAM_INT);
            $stmt->bindValue(':reservation_venue_id', (int)$reservation_venue_id, PDO::PARAM_INT);
    
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
            if ($reservation_vehicle_id === null || $reservation_change_vehicle_id === null) {
                return json_encode(['status' => 'error', 'message' => 'Missing required parameters']);
            }
    
            $sql = "UPDATE tbl_reservation_vehicle
                    SET reservation_change_vehicle_id = :reservation_change_vehicle_id
                    WHERE reservation_vehicle_id = :reservation_vehicle_id";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindValue(':reservation_change_vehicle_id', (int)$reservation_change_vehicle_id, PDO::PARAM_INT);
            $stmt->bindValue(':reservation_vehicle_id', (int)$reservation_vehicle_id, PDO::PARAM_INT);
    
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
                               AND reservation_status_status_id = 8";
            $updateStmt = $this->conn->prepare($updateStatusSql);
            $updateStmt->bindValue(':reservation_id', (int)$reservation_id, PDO::PARAM_INT);
            if ($user_admin_id === null || $user_admin_id === '') {
                $updateStmt->bindValue(':user_admin_id', null, PDO::PARAM_NULL);
            } else {
                $updateStmt->bindValue(':user_admin_id', (int)$user_admin_id, PDO::PARAM_INT);
            }
            $updateStmt->execute();
    
            // 3) Insert a new status row with reservation_active = 0 and fixed status id 14
            $ins = $this->conn->prepare("INSERT INTO tbl_reservation_status
                (reservation_status_status_id, reservation_reservation_id, reservation_active, reservation_updated_at, reservation_users_id)
                VALUES (:status_id, :reservation_id, 0, NOW(), :user_admin_id)");
            $ins->bindValue(':status_id', 10, PDO::PARAM_INT);
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
                    CONCAT_WS(' ', u.users_fname, u.users_mname, u.users_lname) AS requester_name,
                    d.departments_name,
                    rs.reservation_status_status_id AS status_id,
                    rs.reservation_active AS active,
                    sm.status_master_name AS reservation_status
                FROM 
                    tbl_reservation r
                LEFT JOIN 
                    tbl_users u ON r.reservation_user_id = u.users_id
                LEFT JOIN 
                    tbl_departments d ON u.users_department_id = d.departments_id
                LEFT JOIN 
                    tbl_reservation_status rs ON r.reservation_id = rs.reservation_reservation_id
                LEFT JOIN 
                    tbl_status_master sm ON rs.reservation_status_status_id = sm.status_master_id
                WHERE
                    (
                        -- Show status 1 and 8 only if no status 11 exists and no reserved status (6) exists
                        (
                            rs.reservation_status_status_id IN (1, 8) 
                            AND rs.reservation_active IN (0, 1)
                            AND NOT EXISTS (
                                SELECT 1 
                                FROM tbl_reservation_status rs_check_11 
                                WHERE rs_check_11.reservation_reservation_id = r.reservation_id 
                                AND rs_check_11.reservation_status_status_id = 11
                            )
                            AND NOT EXISTS (
                                SELECT 1 
                                FROM tbl_reservation_status rs_check_6 
                                WHERE rs_check_6.reservation_reservation_id = r.reservation_id 
                                AND rs_check_6.reservation_status_status_id = 6
                            )
                        )
                        OR 
                        -- Show status 11 (Change Request) regardless of whether status 6 exists
                        (
                            rs.reservation_status_status_id = 11 
                            AND rs.reservation_active = 0
                        )
                    )
                    AND NOT EXISTS (
                        SELECT 1
                        FROM tbl_reservation_status rs3
                        WHERE rs3.reservation_reservation_id = r.reservation_id
                        AND rs3.reservation_status_status_id = 2
                        AND rs3.reservation_active = 1
                    )
                    AND NOT EXISTS (
                        SELECT 1
                        FROM tbl_reservation_status rs4
                        WHERE rs4.reservation_reservation_id = r.reservation_id
                        AND rs4.reservation_status_status_id = 14
                        AND rs4.reservation_active = 1
                    )
    
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

    public function updateChangeReschedule($reservationId, $isAccepted, $userId) {
        try {
            $this->conn->beginTransaction();

            if ($isAccepted) {
                // ACCEPTED: Update reservation_active to 1 and insert status 14 with active 1
                $sqlUpdate = "
                    UPDATE tbl_reservation_status 
                    SET reservation_active = 1 
                    WHERE reservation_reservation_id = :reservation_id 
                    AND reservation_status_status_id = 11";
                
                $stmtUpdate = $this->conn->prepare($sqlUpdate);
                $stmtUpdate->bindParam(':reservation_id', $reservationId, PDO::PARAM_INT);
                $stmtUpdate->execute();

                // Insert new status: status_id 14 with active 1
                $sqlInsert14 = "
                    INSERT INTO tbl_reservation_status 
                    (reservation_reservation_id, reservation_status_status_id, reservation_active, reservation_updated_at, reservation_users_id) 
                    VALUES (:reservation_id, 14, 1, NOW(), :user_id)";
                
                $stmtInsert14 = $this->conn->prepare($sqlInsert14);
                $stmtInsert14->bindParam(':reservation_id', $reservationId, PDO::PARAM_INT);
                $stmtInsert14->bindParam(':user_id', $userId, PDO::PARAM_INT);
                $stmtInsert14->execute();

                $statusText = 'accepted';
                $newStatusId = 14;
            } else {
                // REJECTED: Update reservation_active to 1 and insert status 2 with active 1
                $sqlUpdate = "
                    UPDATE tbl_reservation_status 
                    SET reservation_active = 1 
                    WHERE reservation_reservation_id = :reservation_id 
                    AND reservation_status_status_id = 11";
                
                $stmtUpdate = $this->conn->prepare($sqlUpdate);
                $stmtUpdate->bindParam(':reservation_id', $reservationId, PDO::PARAM_INT);
                $stmtUpdate->execute();

                // Insert new status: status_id 2 with active 1
                $sqlInsert2 = "
                    INSERT INTO tbl_reservation_status 
                    (reservation_reservation_id, reservation_status_status_id, reservation_active, reservation_updated_at, reservation_users_id) 
                    VALUES (:reservation_id, 2, 1, NOW(), :user_id)";
                
                $stmtInsert2 = $this->conn->prepare($sqlInsert2);
                $stmtInsert2->bindParam(':reservation_id', $reservationId, PDO::PARAM_INT);
                $stmtInsert2->bindParam(':user_id', $userId, PDO::PARAM_INT);
                $stmtInsert2->execute();

                $statusText = 'rejected';
                $newStatusId = 2;
            }

            $this->conn->commit();

            // Add audit log entry
            try {
                $auditSql = "INSERT INTO audit_log (description, action, created_at, created_by) 
                           VALUES (:description, :action, NOW(), :created_by)";
                $auditStmt = $this->conn->prepare($auditSql);
                $description = "Reschedule request {$statusText} for reservation ID {$reservationId}";
                $action = "RESCHEDULE_" . strtoupper($statusText);
                $auditStmt->execute([
                    ':description' => $description,
                    ':action' => $action,
                    ':created_by' => $userId
                ]);
            } catch (Exception $e) {
                error_log("Audit log error in updateChangeReschedule: " . $e->getMessage());
            }

            return json_encode([
                'status' => 'success',
                'message' => "Reschedule request {$statusText} successfully",
                'reservation_id' => $reservationId,
                'new_status_id' => $newStatusId,
                'is_accepted' => $isAccepted
            ]);

        } catch (PDOException $e) {
            $this->conn->rollBack();
            error_log("Database error in updateChangeReschedule: " . $e->getMessage());
            return json_encode([
                'status' => 'error',
                'message' => 'Database error: ' . $e->getMessage()
            ]);
        } catch (Exception $e) {
            $this->conn->rollBack();
            error_log("General error in updateChangeReschedule: " . $e->getMessage());
            return json_encode([
                'status' => 'error',
                'message' => 'Error processing reschedule request: ' . $e->getMessage()
            ]);
        }
    }

    

    

    
}

// Handle incoming requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $reservation = new Reservation();
    $data = json_decode(file_get_contents('php://input'), true);

    if (isset($data['operation'])) {
        switch ($data['operation']) {
            case "fetchAvailableDrivers":
                echo $reservation->fetchAvailableDrivers();
                break;

            case "updateChangeReschedule":
                $reservationId = $data['reservation_id'] ?? ($_POST['reservation_id'] ?? null);
                $isAccepted = $data['is_accepted'] ?? ($_POST['is_accepted'] ?? false);
                $userId = $data['user_id'] ?? ($_POST['user_id'] ?? null);
                
                if (!$reservationId || $userId === null) {
                    echo json_encode(['status' => 'error', 'message' => 'Reservation ID and User ID are required']);
                    break;
                }
                
                echo $reservation->updateChangeReschedule($reservationId, $isAccepted, $userId);
                break;

            case "fetchRequestReservation":
                echo $reservation->fetchRequestReservation(); 
                break;
    
            case "updateReservationReschedule":
                $reservation_id = $data['reservation_id'] ?? ($_POST['reservation_id'] ?? null);
                $reschedule_start_date = $data['reschedule_start_date'] ?? ($_POST['reschedule_start_date'] ?? null);
                $reschedule_end_date = $data['reschedule_end_date'] ?? ($_POST['reschedule_end_date'] ?? null);
                $user_admin_id = $data['user_admin_id'] ?? ($_POST['user_admin_id'] ?? null);
                if ($reservation_id === null || $reschedule_start_date === null || $reschedule_end_date === null) {
                    echo json_encode(['status' => 'error', 'message' => 'reservation_id, reschedule_start_date, and reschedule_end_date are required']);
                    break;
                }
                echo $reservation->updateReservationReschedule($reservation_id, $reschedule_start_date, $reschedule_end_date, $user_admin_id);
                break;

            case "updateVenueReschedule":
                $reservation_venue_id = $data['reservation_venue_id'] ?? ($_POST['reservation_venue_id'] ?? null);
                $reservation_change_venue_id = $data['reservation_change_venue_id'] ?? ($_POST['reservation_change_venue_id'] ?? null);
                if ($reservation_venue_id === null || $reservation_change_venue_id === null) {
                    echo json_encode(['status' => 'error', 'message' => 'reservation_venue_id and reservation_change_venue_id are required']);
                    break;
                }
                echo $reservation->updateVenueReschedule($reservation_venue_id, $reservation_change_venue_id);
                break;
    
            case "updateVehicleReschedule":
                $reservation_vehicle_id = $data['reservation_vehicle_id'] ?? ($_POST['reservation_vehicle_id'] ?? null);
                $reservation_change_vehicle_id = $data['reservation_change_vehicle_id'] ?? ($_POST['reservation_change_vehicle_id'] ?? null);
                if ($reservation_vehicle_id === null || $reservation_change_vehicle_id === null) {
                    echo json_encode(['status' => 'error', 'message' => 'reservation_vehicle_id and reservation_change_vehicle_id are required']);
                    break;
                }
                echo $reservation->updateVehicleReschedule($reservation_vehicle_id, $reservation_change_vehicle_id);
                break;

            case "fetchAvailableVenues":
                // Accept both camelCase and snake_case for flexibility
                $startDateTime = $data['startDateTime']
                    ?? $data['start_datetime']
                    ?? ($_POST['startDateTime'] ?? ($_POST['start_datetime'] ?? null));
                $endDateTime = $data['endDateTime']
                    ?? $data['end_datetime']
                    ?? ($_POST['endDateTime'] ?? ($_POST['end_datetime'] ?? null));
                
                // Handle exclude IDs - check multiple possible field names
                $excludeIds = $data['excludeIds']
                    ?? $data['exclude_ids']
                    ?? $data['excludedIds']
                    ?? $data['excluded_ids']
                    ?? $data['excludedids']
                    ?? ($_POST['excludeIds'] ?? ($_POST['exclude_ids'] ?? ($_POST['excludedIds'] ?? ($_POST['excluded_ids'] ?? ($_POST['excludedids'] ?? null)))));
                
                // Log what we received for debugging
                error_log("Case fetchAvailableVenues - Raw input for excludeIds:");
                error_log("- input data: " . json_encode($data));
                error_log("- excludeIds value: " . ($excludeIds === null ? 'NULL' : (is_array($excludeIds) ? implode(',', $excludeIds) : $excludeIds)));
                error_log("- excludeIds type: " . gettype($excludeIds));
                
                // Handle string conversion to array if needed
                if (!empty($excludeIds) && is_string($excludeIds)) {
                    // Remove any brackets and split by comma
                    $excludeIds = trim($excludeIds, '[]');
                    if (!empty($excludeIds)) {
                        $excludeIds = array_map('trim', explode(',', $excludeIds));
                        $excludeIds = array_filter($excludeIds, function($id) {
                            return !empty($id) && is_numeric($id);
                        });
                        $excludeIds = array_map('intval', $excludeIds);
                    } else {
                        $excludeIds = null;
                    }
                }
                
                // Final validation
                if ($startDateTime === null || $endDateTime === null) {
                    echo json_encode(['status' => 'error', 'message' => 'startDateTime and endDateTime are required']);
                    break;
                }
                
                // Method echoes JSON and sets headers internally
                $reservation->fetchAvailableVenues($startDateTime, $endDateTime, $excludeIds);
                break;

            case "fetchAvailableVehicles":
                    // Accept both camelCase and snake_case for flexibility
                    $startDateTime = $data['startDateTime']
                        ?? $data['start_datetime']
                        ?? ($_POST['startDateTime'] ?? ($_POST['start_datetime'] ?? null));
                    $endDateTime = $data['endDateTime']
                        ?? $data['end_datetime']
                        ?? ($_POST['endDateTime'] ?? ($_POST['end_datetime'] ?? null));
                    
                    // Handle exclude IDs - check multiple possible field names
                    $excludeIds = $data['excludeIds']
                        ?? $data['exclude_ids']
                        ?? $data['excludedIds']
                        ?? $data['excluded_ids']
                        ?? $data['excludedids']
                        ?? ($_POST['excludeIds'] ?? ($_POST['exclude_ids'] ?? ($_POST['excludedIds'] ?? ($_POST['excluded_ids'] ?? ($_POST['excludedids'] ?? null)))));
                    
                    // Log what we received for debugging
                    error_log("Case fetchAvailableVehicles - Raw input for excludeIds:");
                    error_log("- input data: " . json_encode($data));
                    error_log("- excludeIds value: " . ($excludeIds === null ? 'NULL' : (is_array($excludeIds) ? implode(',', $excludeIds) : $excludeIds)));
                    error_log("- excludeIds type: " . gettype($excludeIds));
                    
                    // Handle string conversion to array if needed
                    if (!empty($excludeIds) && is_string($excludeIds)) {
                        // Remove any brackets and split by comma
                        $excludeIds = trim($excludeIds, '[]');
                        if (!empty($excludeIds)) {
                            $excludeIds = array_map('trim', explode(',', $excludeIds));
                            $excludeIds = array_filter($excludeIds, function($id) {
                                return !empty($id) && is_numeric($id);
                            });
                            $excludeIds = array_map('intval', $excludeIds);
                        } else {
                            $excludeIds = null;
                        }
                    }
                    
                    // Final validation
                    if ($startDateTime === null || $endDateTime === null) {
                        echo json_encode(['status' => 'error', 'message' => 'startDateTime and endDateTime are required']);
                        break;
                    }
                    
                    // Method echoes JSON and sets headers internally
                    $reservation->fetchAvailableVehicles($startDateTime, $endDateTime, $excludeIds);
                    break;
            case "fetchRequestById":
                $reservationId = $data['reservation_id'] ?? null;
                if ($reservationId === null) {
                    echo json_encode(['status' => 'error', 'message' => 'Reservation ID is required']);
                    break;
                }
                echo $reservation->fetchRequestById($reservationId); 
                break;

            case "fetchEquipments":
                $startDateTime = $data['startDateTime'] ?? null;
                $endDateTime = $data['endDateTime'] ?? null;
                $reservation->fetchEquipments($startDateTime, $endDateTime);
                break;

            case "doubleCheckAvailability":
                $startDateTime = $data['start_datetime'] ?? null;
                $endDateTime = $data['end_datetime'] ?? null;
                $reservationId = $data['reservation_id'] ?? null;
                if ($startDateTime === null || $endDateTime === null) {
                    echo json_encode(['status' => 'error', 'message' => 'Start and end datetime are required']);
                    break;
                }
                echo $reservation->doubleCheckAvailability($startDateTime, $endDateTime, $reservationId);
                break;
            case "fetchAvailability":
                $itemType = $data['itemType'] ?? '';
                $itemId = $data['itemId'] ?? [];
                $quantities = $data['quantity'] ?? [];
                
                // Initialize empty array for quantities if not dealing with equipment
                $inputQuantities = [];
                if ($itemType === 'equipment' && !empty($quantities)) {
                    // Only create the combined array if we have quantities
                    $inputQuantities = array_combine($itemId, $quantities);
                }        
                echo $reservation->fetchAvailability($itemType, $itemId, $inputQuantities);
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
