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

    // ...existing code...
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
