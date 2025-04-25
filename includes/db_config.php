<?php
define('DB_HOST', 'localhost');
define('DB_USER', 'root'); // replace with your actual database user
define('DB_PASS', ''); // replace with your actual database password
define('DB_NAME', 'dbgsd'); // replace with your actual database name

try {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
} catch (Exception $e) {
    error_log("Database connection error: " . $e->getMessage());
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Database connection error: ' . $e->getMessage()]);
    exit();
}
?>
