<?php
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

class Dashboard {
    private $conn;

    public function __construct() {
        include 'connection-pdo.php'; 
        $this->conn = $conn;
    }

    public function getTotals() {
        try {
            // Query for approved reservations (status_id = 3)
            $reservationQuery = "
                SELECT COUNT(DISTINCT r.reservation_id) AS total 
                FROM tbl_reservation r
                JOIN tbl_reservation_status rs ON r.reservation_id = rs.reservation_status_reservation_id
                WHERE rs.reservation_status_status_reservation_id = 3
            ";

            // Query for pending reservations (status_id = 2)
            $pendingRequestQuery = "
                SELECT COUNT(DISTINCT r.reservation_id) AS total 
                FROM tbl_reservation r
                JOIN tbl_reservation_status rs ON r.reservation_id = rs.reservation_status_reservation_id
                WHERE rs.reservation_status_status_reservation_id = 2
            ";

            // Query for vehicles
            $vehicleQuery = "SELECT COUNT(*) AS total FROM tbl_vehicle WHERE is_active = 1";

            // Query for venues
            $venueQuery = "SELECT COUNT(*) AS total FROM tbl_venue WHERE is_active = 1";

            // Query for equipment
            $equipmentQuery = "SELECT COUNT(*) AS total FROM tbl_equipments WHERE is_active = 1";

            // Query for users (combining all user tables)
            $userQuery = "
                SELECT 
                    (SELECT COUNT(*) FROM tbl_admin WHERE is_active = 1) +
                    (SELECT COUNT(*) FROM tbl_dept WHERE is_active = 1) +
                    (SELECT COUNT(*) FROM tbl_driver WHERE is_active = 1) +
                    (SELECT COUNT(*) FROM tbl_users WHERE is_active = 1) +
                    (SELECT COUNT(*) FROM tbl_personel WHERE is_active = 1) +
                    (SELECT COUNT(*) FROM tbl_super_admin WHERE is_active = 1) AS total
            ";

            // Execute all queries
            $queries = [
                'reservations' => $reservationQuery,
                'pending_requests' => $pendingRequestQuery,
                'vehicles' => $vehicleQuery,
                'venues' => $venueQuery,
                'equipments' => $equipmentQuery,
                'users' => $userQuery
            ];

            $totals = [];
            foreach ($queries as $key => $query) {
                $stmt = $this->conn->query($query);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                $totals[$key] = (int)$result['total'];
            }

            return json_encode([
                'status' => 'success',
                'data' => $totals
            ]);

        } catch (PDOException $e) {
            return json_encode([
                'status' => 'error',
                'message' => $e->getMessage()
            ]);
        }
    }

    public function fetchRequest() {
        try {
            // Query to fetch pending requests (reservation_status_status_reservation_id = 1)
            $query = "
                SELECT 
                    r.reservation_id,
                    r.reservation_date,
                    v.reservation_form_name AS venue_name,
                    vh.reservation_form_name AS vehicle_name
                FROM tbl_reservation r
                JOIN tbl_reservation_status rs ON r.reservation_id = rs.reservation_status_reservation_id
                LEFT JOIN tbl_approval a ON r.reservation_approval_id = a.approval_id
                LEFT JOIN tbl_reservation_form_venue v ON a.approval_form_venue_id = v.reservation_form_venue_id
                LEFT JOIN tbl_reservation_form_vehicle vh ON a.approval_form_vehicle_id = vh.reservation_form_vehicle_id
                WHERE rs.reservation_status_status_reservation_id = 1
            ";

            $stmt = $this->conn->query($query);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Format the results
            $formattedResults = [];
            foreach ($results as $row) {
                $formattedResults[] = [
                    'reservation_id' => $row['reservation_id'],
                    'reservation_date' => $row['reservation_date'],
                    'reservation_form_name' => $row['venue_name'] ?? $row['vehicle_name']
                ];
            }

            return json_encode([
                'status' => 'success',
                'data' => $formattedResults
            ]);

        } catch (PDOException $e) {
            return json_encode([
                'status' => 'error',
                'message' => $e->getMessage()
            ]);
        }
    }

    public function fetchCompletedTask() {
        try {
            // Query to fetch completed tasks for personnel (checklist_status_status_checklist_id = 2)
            $query = "
                SELECT 
                    p.jo_personel_fname,
                    p.jo_personel_mname,
                    p.jo_personel_lname,
                    sc.status_checklist_name
                FROM tbl_checklist_master cm
                JOIN tbl_checklist_status cs ON cm.checklist_id = cs.checklist_checklist_id
                JOIN tbl_status_checklist sc ON cs.checklist_status_status_checklist_id = sc.status_checklist_id
                JOIN tbl_personel p ON cm.checklist_personnel_id = p.jo_personel_id
                WHERE cs.checklist_status_status_checklist_id = 2
            ";

            $stmt = $this->conn->query($query);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Format the results
            $formattedResults = [];
            foreach ($results as $row) {
                $formattedResults[] = [
                    'personnel_full_name' => trim($row['jo_personel_fname'] . ' ' . $row['jo_personel_mname'] . ' ' . $row['jo_personel_lname']),
                    'status_name' => $row['status_checklist_name']
                ];
            }

            return json_encode([
                'status' => 'success',
                'data' => $formattedResults
            ]);

        } catch (PDOException $e) {
            return json_encode([
                'status' => 'error',
                'message' => $e->getMessage()
            ]);
        }
    }

    public function fetchReservedAndDeclinedReservations() {
        try {
            // Query to fetch reserved and declined reservations (status_id = 2 for reserved, status_id = 6 for declined)
            // Left join with tbl_status_master to get the status names
            $query = "
                SELECT 
                    r.reservation_id,
                    rs.reservation_status_status_id,
                    sm.status_master_name AS status_name,
                    rs.reservation_active,
                    rs.reservation_updated_at
                FROM tbl_reservation r
                JOIN tbl_reservation_status rs ON r.reservation_id = rs.reservation_reservation_id
                LEFT JOIN tbl_status_master sm ON rs.reservation_status_status_id = sm.status_master_id
                WHERE rs.reservation_status_status_id IN (2, 6)
            ";

            $stmt = $this->conn->query($query);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Format the results
            $formattedResults = [];
            foreach ($results as $row) {
                $formattedResults[] = [
                    'reservation_id' => $row['reservation_id'],
                    'status_id' => $row['reservation_status_status_id'],
                    'status_name' => $row['status_name'],
                    'reservation_active' => $row['reservation_active'],
                    'reservation_updated_at' => $row['reservation_updated_at']
                ];
            }

            return json_encode([
                'status' => 'success',
                'data' => $formattedResults
            ]);

        } catch (PDOException $e) {
            return json_encode([
                'status' => 'error',
                'message' => $e->getMessage()
            ]);
        }
    }
}

// Handle requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' || $_SERVER['REQUEST_METHOD'] === 'GET') {
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);

    if (isset($data['operation'])) {
        $dashboard = new Dashboard();

        switch ($data['operation']) {
            case 'getTotals':
                echo $dashboard->getTotals();
                break;

            case 'fetchRequest':
                echo $dashboard->fetchRequest();
                break;

            case 'fetchCompletedTask':
                echo $dashboard->fetchCompletedTask();
                break;
            
            case 'fetchReservedAndDeclinedReservations':
                echo $dashboard->fetchReservedAndDeclinedReservations();
                break;

            default:
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Invalid operation'
                ]);
                break;
        }
    } else {
        echo json_encode([
            'status' => 'error',
            'message' => 'Operation not specified'
        ]);
    }
}
?>