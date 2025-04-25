<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Origin: http://localhost:3000'); // Change this to match your frontend
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);

    // Set timezone to Asia/Manila
    date_default_timezone_set('Asia/Manila');

    // Validate data
    if (!isset($data['name']) || !isset($data['value']) || !isset($data['expires'])) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid request data']);
        exit;
    }

    $name = $data['name'];
    $value = $data['value'];
    $expires = strtotime($data['expires']);

    setcookie($name, $value, [
        'expires' => $expires,
        'path' => '/',
        'domain' => $_SERVER['HTTP_HOST'],
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Strict'
    ]);

    echo json_encode([
        'status' => 'success',
        'expires' => date('Y-m-d H:i:s', $expires),
        'timezone' => 'Asia/Manila'
    ]);
}
?>
