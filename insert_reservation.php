<?php
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

class Reservation {
    public $conn;

    public function __construct() {
        include 'connection-pdo.php'; 
        $this->conn = $conn;
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

    public function hasConflictRequest($resourceType, $resourceId, $startDate, $endDate) {
        if (!in_array($resourceType, ['venue', 'vehicle', 'driver', 'equipment'])) {
            error_log("Invalid resource type: " . $resourceType);
            return ['status' => false, 'count' => 0];
        }
    
        try {
            $sql = "";
    
            switch ($resourceType) {
                case 'venue':
                    $sql = "SELECT COUNT(*) as conflict_count
                           FROM tbl_reservation r
                           INNER JOIN tbl_reservation_status rs ON r.reservation_id = rs.reservation_reservation_id
                           INNER JOIN tbl_reservation_form_venue rv ON r.reservation_form_venue_id = rv.reservation_form_venue_id
                           INNER JOIN tbl_reservation_venue v ON rv.reservation_form_venue_id = v.reservation_venue_form_venue_id
                           WHERE v.reservation_venue_venue_id = :resource_id
                           AND rs.reservation_status_status_id = 1
                           AND rs.reservation_active IN (0, 1)
                           AND (
                               :start_date <= rv.reservation_form_end_date
                               AND :end_date >= rv.reservation_form_start_date
                           )";
                    break;
    
                case 'vehicle':
                    $sql = "SELECT COUNT(*) as conflict_count
                           FROM tbl_reservation r
                           INNER JOIN tbl_reservation_status rs ON r.reservation_id = rs.reservation_reservation_id
                           INNER JOIN tbl_reservation_form_vehicle rv ON r.reservation_form_vehicle_id = rv.reservation_form_vehicle_id
                           INNER JOIN tbl_reservation_vehicle v ON rv.reservation_form_vehicle_id = v.reservation_vehicle_reservation_form_vehicle_id
                           WHERE v.reservation_vehicle_vehicle_id = :resource_id
                           AND rs.reservation_status_status_id = 1
                           AND rs.reservation_active IN (0, 1)
                           AND (
                               :start_date <= rv.reservation_form_end_date
                               AND :end_date >= rv.reservation_form_start_date
                           )";
                    break;
    
                case 'driver':
                    $sql = "SELECT COUNT(*) as conflict_count
                           FROM tbl_reservation r
                           INNER JOIN tbl_reservation_status rs ON r.reservation_id = rs.reservation_reservation_id
                           INNER JOIN tbl_reservation_form_vehicle rv ON r.reservation_form_vehicle_id = rv.reservation_form_vehicle_id
                           INNER JOIN tbl_reservation_driver d ON rv.reservation_form_vehicle_id = d.reservation_driver_reservation_form_vehicle_id
                           WHERE d.driver_id = :resource_id
                           AND rs.reservation_status_status_id = 1
                           AND rs.reservation_active IN (0, 1)
                           AND (
                               :start_date <= rv.reservation_form_end_date
                               AND :end_date >= rv.reservation_form_start_date
                           )";
                    break;
    
                case 'equipment':
                    $sql = "SELECT 
                                eq.equip_name,
                                eq.equip_quantity AS total_quantity,
                                
                                -- Reserved Quantity (Confirmed - Status 6)
                                COALESCE(SUM(CASE WHEN rs.reservation_status_status_id = :reserved_status THEN e.reservation_equipment_quantity END), 0) AS reserved_quantity,
                
                                -- Pending Quantity (Pending - Status 1)
                                COALESCE(SUM(CASE WHEN rs.reservation_status_status_id = :pending_status THEN e.reservation_equipment_quantity END), 0) AS pending_quantity,
                
                                -- Remaining Quantity Calculation
                                (eq.equip_quantity - 
                                    COALESCE(SUM(CASE WHEN rs.reservation_status_status_id = :reserved_status THEN e.reservation_equipment_quantity END), 0) - 
                                    COALESCE(SUM(CASE WHEN rs.reservation_status_status_id = :pending_status THEN e.reservation_equipment_quantity END), 0)
                                ) AS remaining_quantity
                
                            FROM tbl_equipments eq
                
                            -- LEFT JOIN to ensure equipment shows even if not reserved
                            LEFT JOIN tbl_reservation_equipment e 
                                ON eq.equip_id = e.reservation_equipment_equip_id
                
                            LEFT JOIN tbl_reservation_form_venue rv 
                                ON e.reservation_equipment_form_venue_id = rv.reservation_form_venue_id
                
                            LEFT JOIN tbl_reservation r 
                                ON rv.reservation_form_venue_id = r.reservation_form_venue_id
                
                            LEFT JOIN tbl_reservation_status rs 
                                ON r.reservation_id = rs.reservation_reservation_id 
                
                            -- Filtering for equipment ID and date range
                            WHERE eq.equip_id = :resource_id
                            AND (
                                :start_date <= rv.reservation_form_end_date
                                AND :end_date >= rv.reservation_form_start_date
                            )
                
                            GROUP BY eq.equip_id, eq.equip_name, eq.equip_quantity";
                
                    // For equipment case, bind parameters differently
                    if ($resourceType === 'equipment') {
                        $reservedStatus = 6;
                        $pendingStatus = 1;
                        $stmt = $this->conn->prepare($sql);
                        $stmt->bindValue(':resource_id', $resourceId);
                        $stmt->bindValue(':start_date', $startDate);
                        $stmt->bindValue(':end_date', $endDate);
                        $stmt->bindValue(':reserved_status', $reservedStatus, PDO::PARAM_INT);
                        $stmt->bindValue(':pending_status', $pendingStatus, PDO::PARAM_INT);
                        
                    } else {
                        $stmt = $this->conn->prepare($sql);
                        $stmt->bindParam(':resource_id', $resourceId);
                        $stmt->bindParam(':start_date', $startDate);
                        $stmt->bindParam(':end_date', $endDate);
                    }
                    
                    $stmt->execute();
                    $result = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    // If no result found, equipment is fully available
                    if (!$result) {
                        return ['status' => true, 'count' => 0];
                    }
                    
                    // Get the requested quantity from the parameters
                    $requestedQuantity = isset($data['quantity']) ? intval($data['quantity']) : 0;
                    
                    // Compare requested quantity with remaining quantity
                    $remainingQuantity = intval($result['remaining_quantity']);
                    if ($requestedQuantity <= $remainingQuantity) {
                        return ['status' => true, 'count' => 0];
                    } else {
                        return ['status' => false, 'message' => 'Insufficient quantity for Equipment ID ' . $resourceId . '. Requested: ' . $requestedQuantity . ', Available: ' . $remainingQuantity];
                    }
                    break;
            }
    
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':resource_id', $resourceId);
            $stmt->bindParam(':start_date', $startDate);
            $stmt->bindParam(':end_date', $endDate);
            
            if ($resourceType === 'equipment') {
                $requestedQuantity = isset($data['quantity']) ? $data['quantity'] : 1;
                $stmt->bindParam(':requested_quantity', $requestedQuantity);
            }
            
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($resourceType === 'equipment') {
                $hasConflict = ($result !== false);
                return ['status' => true, 'count' => $hasConflict ? 1 : 0];
            }
            
            $conflictCount = (int)$result['conflict_count'];
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
            
            foreach ($equipments as $equip) {
                $stmt->bindParam(':equipment_id', $equip['equipment_id']);
                $stmt->bindParam(':reservation_id', $reservationId);
                $stmt->bindParam(':quantity', $equip['quantity']);
    
                if (!$stmt->execute()) {
                    if ($this->conn->inTransaction()) {
                        $this->conn->rollBack();
                    }
                    return ['status' => 'error', 'message' => 'Failed to insert equipment: ' . $equip['equipment_id']];
                }
            }
    
            if ($this->conn->inTransaction()) {
                $this->conn->commit();
            }
            return ['status' => 'success', 'message' => 'All equipment inserted successfully.'];
        } catch (PDOException $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
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
    public function insertDriver($reservationId, $driverId = null) {
        try {
            $sql = "INSERT INTO tbl_reservation_driver 
                    (reservation_reservation_id, reservation_driver_user_id, is_accepted_trip) 
                    VALUES (:reservation_id, :driver_id, :is_accepted_trip)";

            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':reservation_id', $reservationId);
            $stmt->bindParam(':driver_id', $driverId);
            
            // If driver_id is null, set is_accepted_trip to 0
            // If driver_id is provided, set is_accepted_trip to null
            $isAcceptedTrip = ($driverId === null) ? 0 : null;
            $stmt->bindParam(':is_accepted_trip', $isAcceptedTrip);

            if ($stmt->execute()) {
                return ['status' => 'success', 'message' => 'Driver assignment processed successfully.'];
            }
            return ['status' => 'error', 'message' => 'Failed to process driver assignment.'];
        } catch (PDOException $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }
    public function insertVehicleForm($data) {
        
        try {
            // Check for vehicle conflicts
            $vehicles = $data['vehicles'] ?? [];

            foreach ($vehicles as $vehicleId) {
                $conflict = $this->hasConflictRequest('vehicle', $vehicleId, $data['start_date'], $data['end_date']);
                if (!$conflict['status'] || $conflict['count'] > 0) {
                    $this->conn->rollBack();
                    return ['status' => 'error', 'message' => 'There must be Active Process base on your selected vehicle or driver'];
                }
            }
            

            // Check for driver conflict if driver is specified
            if (isset($data['driver_id'])) {
                $driverConflict = $this->hasConflictRequest('driver', $data['driver_id'], $data['start_date'], $data['end_date']);
                if (!$driverConflict['status'] || $driverConflict['count'] > 0) {
                    return ['status' => 'error', 'message' => 'Driver is already reserved for this time period.'];
                }
            }

            // Proceed with the reservation
            $userType = $data['user_type'] ?? 'user';
            $userId = $data['user_id'];
            
            $sql = "INSERT INTO tbl_reservation_form_vehicle 
                    (reservation_form_purpose, reservation_form_name, 
                    reservation_form_destination, reservation_form_start_date, 
                    reservation_form_end_date, " . 
                    ($userType === 'user' ? "reservation_form_user_id" : "reservation_form_dean_id") . 
                    ") VALUES (:purpose, :name, :destination, :start_date, 
                    :end_date, :user_id)";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':purpose', $data['purpose']);
            $stmt->bindParam(':name', $data['name']);
            $stmt->bindParam(':destination', $data['destination']);
            $stmt->bindParam(':start_date', $data['start_date']);
            $stmt->bindParam(':end_date', $data['end_date']);
            $stmt->bindParam(':user_id', $userId);
            
            if (!$stmt->execute()) {
                $this->conn->rollBack();
                return ['status' => 'error', 'message' => 'Failed to create vehicle form.'];
            }

            $formId = $this->conn->lastInsertId();
            $this->conn->commit();
            return ['status' => 'success', 'form_id' => $formId];
        } catch (PDOException $e) {
            $this->conn->rollBack();
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }
    public function insertVenueForm($data, $venueId) {
        try {
            $conflict = $this->hasConflictRequest('venue', $venueId, $data['start_date'], $data['end_date']);
            if (!$conflict['status'] || $conflict['count'] > 0) {
                return ['status' => 'error', 'message' => 'There must be Active Process base on your selected venue'];
            }
    
            // Check equipment availability and conflicts
            if (!empty($data['equipment'])) {
                foreach ($data['equipment'] as $equip) {
                    $availableQuantity = $this->getAvailableEquipmentQuantity($equip['equipment_id'], $data['start_date'], $data['end_date']);
                    
                    if ($equip['quantity'] > $availableQuantity) {
                        return ['status' => 'error', 'message' => 'There must be Active Process based on your selected equipment'];
                    }
                }
            }
            
            // Proceed with the reservation
            $userType = $data['user_type'] ?? 'user';
            $userId = $data['user_id'];
            
            $sql = "INSERT INTO tbl_reservation_form_venue 
                    (reservation_form_name, reservation_form_event_title, 
                    reservation_form_description, reservation_participants, 
                    reservation_form_start_date, reservation_form_end_date, " . 
                    ($userType === 'user' ? "reservation_form_user_id" : "reservation_form_dean_id") . 
                    ") VALUES (:name, :event_title, :description, :participants, 
                    :start_date, :end_date, :user_id)";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':name', $data['name']);
            $stmt->bindParam(':event_title', $data['event_title']);
            $stmt->bindParam(':description', $data['description']);
            $stmt->bindParam(':participants', $data['participants']);
            $stmt->bindParam(':start_date', $data['start_date']);
            $stmt->bindParam(':end_date', $data['end_date']);
            $stmt->bindParam(':user_id', $userId);
            
            if (!$stmt->execute()) {
                return ['status' => 'error', 'message' => 'Failed to create venue form.'];
            }
    
            $formId = $this->conn->lastInsertId();
            return ['status' => 'success', 'form_id' => $formId];
        } catch (PDOException $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }
    
    private function getAvailableEquipmentQuantity($equipmentId, $startDate, $endDate) {
        try {
            $sql = "SELECT 
                        eq.equip_name,
                        eq.equip_quantity AS total_quantity,
                        
                        (SELECT COALESCE(SUM(e.reservation_equipment_quantity), 0)
                         FROM tbl_reservation_equipment e
                         LEFT JOIN tbl_reservation_form_venue rv ON e.reservation_equipment_form_venue_id = rv.reservation_form_venue_id
                         LEFT JOIN tbl_reservation r ON rv.reservation_form_venue_id = r.reservation_form_venue_id
                         LEFT JOIN tbl_reservation_status rs ON r.reservation_id = rs.reservation_reservation_id
                         WHERE e.reservation_equipment_equip_id = eq.equip_id
                         AND rs.reservation_status_status_id = 6
                         AND (:start_date <= rv.reservation_form_end_date
                         AND :end_date >= rv.reservation_form_start_date)
                        ) AS reserved_quantity,
                        
                        (SELECT COALESCE(SUM(e.reservation_equipment_quantity), 0)
                         FROM tbl_reservation_equipment e
                         LEFT JOIN tbl_reservation_form_venue rv ON e.reservation_equipment_form_venue_id = rv.reservation_form_venue_id
                         LEFT JOIN tbl_reservation r ON rv.reservation_form_venue_id = r.reservation_form_venue_id
                         LEFT JOIN tbl_reservation_status rs ON r.reservation_id = rs.reservation_reservation_id
                         WHERE e.reservation_equipment_equip_id = eq.equip_id
                         AND rs.reservation_status_status_id = 1
                         AND (:start_date <= rv.reservation_form_end_date
                         AND :end_date >= rv.reservation_form_start_date)
                        ) AS pending_quantity,
                        
                        (eq.equip_quantity - 
                            (SELECT COALESCE(SUM(e.reservation_equipment_quantity), 0)
                             FROM tbl_reservation_equipment e
                             LEFT JOIN tbl_reservation_form_venue rv ON e.reservation_equipment_form_venue_id = rv.reservation_form_venue_id
                             LEFT JOIN tbl_reservation r ON rv.reservation_form_venue_id = r.reservation_form_venue_id
                             LEFT JOIN tbl_reservation_status rs ON r.reservation_id = rs.reservation_reservation_id
                             WHERE e.reservation_equipment_equip_id = eq.equip_id
                             AND rs.reservation_status_status_id = 6
                             AND (:start_date <= rv.reservation_form_end_date
                             AND :end_date >= rv.reservation_form_start_date)
                            ) 
                            - 
                            (SELECT COALESCE(SUM(e.reservation_equipment_quantity), 0)
                             FROM tbl_reservation_equipment e
                             LEFT JOIN tbl_reservation_form_venue rv ON e.reservation_equipment_form_venue_id = rv.reservation_form_venue_id
                             LEFT JOIN tbl_reservation r ON rv.reservation_form_venue_id = r.reservation_form_venue_id
                             LEFT JOIN tbl_reservation_status rs ON r.reservation_id = rs.reservation_reservation_id
                             WHERE e.reservation_equipment_equip_id = eq.equip_id
                             AND rs.reservation_status_status_id = 1
                             AND (:start_date <= rv.reservation_form_end_date
                             AND :end_date >= rv.reservation_form_start_date)
                            )
                        ) AS remaining_quantity
                    FROM tbl_equipments eq
                    WHERE eq.equip_id = :equipment_id";
    
            $stmt = $this->conn->prepare($sql);
            $stmt->bindValue(':equipment_id', $equipmentId);
            $stmt->bindValue(':start_date', $startDate);
            $stmt->bindValue(':end_date', $endDate);
            $stmt->execute();
    
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
            return $result ? (int)$result['remaining_quantity'] : 0;
        } catch (PDOException $e) {
            error_log("Error fetching available equipment quantity: " . $e->getMessage());
            return 0;
        }
    }
    
    

    // Add this new method to check for active transactions
    public function hasActiveTransaction() {
        try {
            return $this->conn->inTransaction();
        } catch (PDOException $e) {
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
                $stmt->bindParam(':passenger_name', $passenger);
                $stmt->bindParam(':reservation_id', $reservationId);

                if (!$stmt->execute()) {
                    $errors[] = 'Failed to insert passenger: ' . $passenger;
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
        try {
            $this->conn->beginTransaction();
        
            // Insert into tbl_reservation
            $sql = "INSERT INTO tbl_reservation 
                    (reservation_title, reservation_description, 
                    reservation_start_date, reservation_end_date,
                    reservation_participants, reservation_user_id,
                    reservation_created_at) 
                    VALUES (:title, :description,
                    :start_date, :end_date, :participants, :user_id,
                    NOW())";
            
            $stmt = $this->conn->prepare($sql);
    
            switch($type) {
                case 'venue':
                    $stmt->bindParam(':title', $data['title']);
                    $stmt->bindParam(':description', $data['description']);
                    $stmt->bindParam(':start_date', $data['start_date']);
                    $stmt->bindParam(':end_date', $data['end_date']);
                    $stmt->bindParam(':participants', $data['participants']);
                    $stmt->bindParam(':user_id', $data['user_id']);
                    break;
                    
                case 'vehicle':
                    $stmt->bindParam(':title', $data['destination']);
                    $stmt->bindParam(':description', $data['purpose']);
                    $stmt->bindValue(':participants', null);
                    $stmt->bindParam(':user_id', $data['user_id']);
                    $stmt->bindParam(':start_date', $data['start_date']);
                    $stmt->bindParam(':end_date', $data['end_date']);
                    break;
                    
                case 'equipment':
                    $stmt->bindParam(':title', $data['title']);
                    $stmt->bindParam(':description', $data['description']);
                    $stmt->bindParam(':start_date', $data['start_date']);
                    $stmt->bindParam(':end_date', $data['end_date']);
                    $stmt->bindParam(':participants', $data['participants']);
                    $stmt->bindParam(':user_id', $data['user_id']);
                    break;
            }
    
            if (!$stmt->execute()) {
                $this->conn->rollBack();
                return ['status' => 'error', 'message' => 'Failed to create reservation record'];
            }
    
            $reservationId = $this->conn->lastInsertId();
    
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

            // Initialize status SQL based on user level
            $statusSql = "";
    
            if ($userLevelId == 3 || $userLevelId == 6 || $userLevelId == 16 || $userLevelId == 17) {
                // If user level is 3 or 6, set reservation as pending with active 1
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

                // Insert notifications in notification_requests for user levels 5 and 6
                $requestNotifSql = "INSERT INTO notification_requests 
                                  (notification_message, notification_department_id, 
                                   notification_user_level_id, notification_create) 
                                  VALUES ('New Reservation Request Pending', :dept_id, :user_level_id, NOW())";

                // Insert for user level 5
                $requestNotifStmt = $this->conn->prepare($requestNotifSql);
                $requestNotifStmt->bindValue(':dept_id', $userLevel['users_department_id'], PDO::PARAM_INT);
                $requestNotifStmt->bindValue(':user_level_id', 5, PDO::PARAM_INT);
                
                if (!$requestNotifStmt->execute()) {
                    $this->conn->rollBack();
                    return ['status' => 'error', 'message' => 'Failed to create notification request for level 5'];
                }

                // Insert for user level 6
                $requestNotifStmt = $this->conn->prepare($requestNotifSql);
                $requestNotifStmt->bindValue(':dept_id', $userLevel['users_department_id'], PDO::PARAM_INT);
                $requestNotifStmt->bindValue(':user_level_id', 6, PDO::PARAM_INT);
                
                if (!$requestNotifStmt->execute()) {
                    $this->conn->rollBack();
                    return ['status' => 'error', 'message' => 'Failed to create notification request for level 6'];
                }

            } elseif ($userLevelId == 5 || $userLevelId == 18) {
                // Status SQL 1 and 2 remain the same
                $statusSql1 = "INSERT INTO tbl_reservation_status 
                              (reservation_reservation_id, reservation_status_status_id, 
                               reservation_active, reservation_users_id, reservation_updated_at) 
                              VALUES (:reservation_id, 1, 0, null, NOW())";

                $statusSql2 = "INSERT INTO tbl_reservation_status 
                              (reservation_reservation_id, reservation_status_status_id, 
                               reservation_active, reservation_users_id, reservation_updated_at) 
                              VALUES (:reservation_id, 3, 1, null, NOW())";

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

                // Insert notification in notification_requests for user level 5
                $requestNotifSql = "INSERT INTO notification_requests 
                                  (notification_message, notification_department_id, 
                                   notification_user_level_id, notification_create) 
                                  VALUES ('New Reservation Request Pending', :dept_id, :user_level_id, NOW())";

                $requestNotifStmt = $this->conn->prepare($requestNotifSql);
                $requestNotifStmt->bindValue(':dept_id', $userLevel['users_department_id'], PDO::PARAM_INT);
                $requestNotifStmt->bindValue(':user_level_id', 5, PDO::PARAM_INT);
                
                if (!$requestNotifStmt->execute()) {
                    $this->conn->rollBack();
                    return ['status' => 'error', 'message' => 'Failed to create notification request'];
                }
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
            }
    
            $this->conn->commit();
            return ['status' => 'success', 'reservation_id' => $reservationId];
    
        } catch (PDOException $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            return ['status' => 'error', 'message' => $e->getMessage()];
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
    $reservation = new Reservation();

    // Debug input
    error_log('Received input: ' . print_r($input, true));

    if (!isset($input['operation'])) {
        echo json_encode(['status' => 'error', 'message' => 'Operation field is required']);
        exit;
    }

    switch(strtolower(trim($input['operation']))) {
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
                $reservationResult = $reservation->createReservation($input['form_data'], 'venue');
                
                if ($reservationResult['status'] !== 'success') {
                    throw new Exception($reservationResult['message']);
                }

                // Insert venues
                $venueResult = $reservation->insertVenue($reservationResult['reservation_id'], $input['form_data']['venues']);
                if ($venueResult['status'] !== 'success') {
                    throw new Exception($venueResult['message']);
                }

                // Insert equipment
                $equipmentResult = $reservation->insertEquipment($reservationResult['reservation_id'], $input['form_data']['equipment']);
                if ($equipmentResult['status'] !== 'success') {
                    throw new Exception($equipmentResult['message']);
                }
        
                echo json_encode($reservationResult);
                
            } catch (Exception $e) {
                echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
            }
            break;

        case 'vehiclereservation':
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
                $reservationResult = $reservation->createReservation($input['form_data'], 'vehicle');
                
                if ($reservationResult['status'] !== 'success') {
                    throw new Exception($reservationResult['message']);
                }

                // Insert vehicles
                $vehicleResult = $reservation->insertVehicles($reservationResult['reservation_id'], $input['form_data']['vehicles']);
                if ($vehicleResult['status'] !== 'success') {
                    throw new Exception($vehicleResult['message']);
                }

                // Insert passengers
                $passengerResult = $reservation->insertPassengers($reservationResult['reservation_id'], $input['form_data']['passengers']);
                if ($passengerResult['status'] !== 'success') {
                    throw new Exception($passengerResult['message']);
                }

                // Get the driver_id from input, it can be null or a specific ID
                $driverId = isset($input['form_data']['driver_id']) ? $input['form_data']['driver_id'] : null;
                
                // Always insert driver record with whatever driver_id was provided (can be null)
                $driverResult = $reservation->insertDriver($reservationResult['reservation_id'], $driverId);
                if ($driverResult['status'] !== 'success') {
                    throw new Exception($driverResult['message']);
                }

                // Insert equipment if specified
                if (isset($input['form_data']['equipment']) && !empty($input['form_data']['equipment'])) {
                    $equipmentResult = $reservation->insertEquipment($reservationResult['reservation_id'], $input['form_data']['equipment']);
                    if ($equipmentResult['status'] !== 'success') {
                        throw new Exception($equipmentResult['message']);
                    }
                }
        
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
                $reservationResult = $reservation->createReservation($input['form_data'], 'equipment');
                
                if ($reservationResult['status'] !== 'success') {
                    throw new Exception($reservationResult['message']);
                }

                // Insert equipment
                $equipmentResult = $reservation->insertEquipment($reservationResult['reservation_id'], $input['form_data']['equipment']);
                if ($equipmentResult['status'] !== 'success') {
                    throw new Exception($equipmentResult['message']);
                }

                echo json_encode($reservationResult);
                
            } catch (Exception $e) {
                echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
            }
            break;

        case "updateNotification":
            if (isset($input['notification_id'])) {
                $updateResponse = $reservation->updateNotification($input['notification_id']);
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
                'valid_operations' => ['venueReservation', 'vehicleReservation', 'equipmentReservation']
            ]);
            break;
    }
}
?>