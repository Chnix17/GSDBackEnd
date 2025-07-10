<?php
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

class Vehicle {
    private $conn;

    public function __construct() {
        include 'connection-pdo.php';
        $this->conn = $conn;
    }

    public function fetchActiveTrips($driverId) {
        try {
            $stmt = $this->conn->prepare("
                SELECT
                    r.reservation_id,
                    r.reservation_title,
                    r.reservation_description,
                    r.reservation_start_date,
                    r.reservation_end_date,
                    r.reservation_participants,
                    r.reservation_user_id,
                    r.reservation_created_at,
                    rd.reservation_driver_id,
                    rd.is_accepted_trip,
                    rd.driver_name,
                    rv.reservation_vehicle_id,
                    v.vehicle_id,
                    v.vehicle_license,
                    v.year,
                    v.status_availability_id,
                    v.vehicle_pic,
                    v.is_active AS vehicle_active,
                    vm.vehicle_model_name,
                    vmake.vehicle_make_name,
                    vcat.vehicle_category_name
                FROM tbl_reservation_driver rd
                JOIN tbl_reservation_vehicle rv
                    ON rv.reservation_vehicle_id = rd.reservation_vehicle_id
                JOIN tbl_reservation r
                    ON r.reservation_id = rv.reservation_reservation_id
                JOIN tbl_reservation_status rs
                    ON rs.reservation_reservation_id = r.reservation_id
                JOIN tbl_vehicle v
                    ON v.vehicle_id = rv.reservation_vehicle_vehicle_id
                JOIN tbl_vehicle_model vm
                    ON vm.vehicle_model_id = v.vehicle_model_id
                JOIN tbl_vehicle_make vmake
                    ON vmake.vehicle_make_id = vm.vehicle_model_vehicle_make_id
                JOIN tbl_vehicle_category vcat
                    ON vcat.vehicle_category_id = vm.vehicle_category_id
                WHERE rd.reservation_driver_user_id = :driverId
                  AND rs.reservation_status_status_id = 6
                  AND rs.reservation_active = 1
                ORDER BY r.reservation_start_date ASC
            ");

            $stmt->bindParam(':driverId', $driverId);
            $stmt->execute();
            $trips = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'status' => 'success',
                'data' => $trips
            ], JSON_UNESCAPED_UNICODE);

        } catch (PDOException $e) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Database error: ' . $e->getMessage()
            ]);
        }
    }

    public function fetchInactiveTrips($driverId) {
        try {
            $stmt = $this->conn->prepare("
                SELECT
                    r.reservation_id,
                    r.reservation_title,
                    r.reservation_description,
                    r.reservation_start_date,
                    r.reservation_end_date,
                    r.reservation_participants,
                    r.reservation_user_id,
                    r.reservation_created_at,
                    rd.reservation_driver_id,
                    rd.is_accepted_trip,
                    rd.driver_name,
                    rv.reservation_vehicle_id,
                    v.vehicle_id,
                    v.vehicle_license,
                    v.year,
                    v.status_availability_id,
                    v.vehicle_pic,
                    v.is_active AS vehicle_active,
                    vm.vehicle_model_name,
                    vmake.vehicle_make_name,
                    vcat.vehicle_category_name
                FROM tbl_reservation_driver rd
                JOIN tbl_reservation_vehicle rv
                    ON rv.reservation_vehicle_id = rd.reservation_vehicle_id
                JOIN tbl_reservation r
                    ON r.reservation_id = rv.reservation_reservation_id
                JOIN tbl_reservation_status rs
                    ON rs.reservation_reservation_id = r.reservation_id
                JOIN tbl_vehicle v
                    ON v.vehicle_id = rv.reservation_vehicle_vehicle_id
                JOIN tbl_vehicle_model vm
                    ON vm.vehicle_model_id = v.vehicle_model_id
                JOIN tbl_vehicle_make vmake
                    ON vmake.vehicle_make_id = vm.vehicle_model_vehicle_make_id
                JOIN tbl_vehicle_category vcat
                    ON vcat.vehicle_category_id = vm.vehicle_category_id
                WHERE rd.reservation_driver_user_id = :driverId
                  AND rs.reservation_status_status_id != 6
                  AND rs.reservation_active = 1
                ORDER BY r.reservation_start_date DESC
            ");

            $stmt->bindParam(':driverId', $driverId);
            $stmt->execute();
            $trips = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'status' => 'success',
                'data' => $trips
            ], JSON_UNESCAPED_UNICODE);

        } catch (PDOException $e) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Database error: ' . $e->getMessage()
            ]);
        }
    }
}

// Handle incoming requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $vehicle = new Vehicle();
    $data = json_decode(file_get_contents('php://input'), true);

    if (isset($data['operation'])) {
        switch ($data['operation']) {
            case 'fetchActiveTrips':
                if (isset($data['driver_id'])) {
                    $vehicle->fetchActiveTrips($data['driver_id']);
                } else {
                    echo json_encode(['status' => 'error', 'message' => 'Missing driver_id parameter.']);
                }
                break;

            case 'fetchInactiveTrips':
                if (isset($data['driver_id'])) {
                    $vehicle->fetchInactiveTrips($data['driver_id']);
                } else {
                    echo json_encode(['status' => 'error', 'message' => 'Missing driver_id parameter.']);
                }
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
