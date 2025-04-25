<?php
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
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

    public function getReservationDetailsById($reservationId) {
        try {
            $sql = "
                SELECT 
                    r.reservation_id,
                    sm.status_master_name,
                    r.reservation_name,
                    r.reservation_event_title,
                    r.reservation_description,
                    r.reservation_start_date,
                    r.reservation_end_date,
                    r.date_created,
                    u.users_name,
                    u.users_contact_number
                FROM 
                    tbl_reservations r
                INNER JOIN 
                    tbl_users u ON r.reservations_users_id = u.users_id
                INNER JOIN 
                    tbl_status_master sm ON r.reservation_status_master_id = sm.status_master_id
                WHERE 
                    r.reservation_id = :reservation_id;
            ";

            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':reservation_id', $reservationId);
            $stmt->execute();
            $reservationDetails = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$reservationDetails) {
                return json_encode(['status' => 'error', 'message' => 'Reservation not found']);
            }

            return json_encode(['status' => 'success', 'data' => $reservationDetails]);
        } catch (PDOException $e) {
            return json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
        }
    }

    public function __destruct() {
        unset($this->conn);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid JSON input']);
        exit;
    }

    $operation = $data['operation'] ?? '';
    $reservationId = $data['reservation_id'] ?? null;

    $user = new User();
    
    if ($operation === "getReservationDetailsById") {
        if ($reservationId !== null) {
            echo $user->getReservationDetailsById($reservationId);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Reservation ID is required']);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Invalid operation']);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
}
?>
