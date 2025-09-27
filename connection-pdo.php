<?php
// Railway backend connecting to InfinityFree database
$host = $_ENV['DB_HOST'] ?? 'sql312.infinityfree.com';
$database = $_ENV['DB_NAME'] ?? 'if0_40037383_dbgsd';
$username = $_ENV['DB_USER'] ?? 'if0_40037383';
$password = $_ENV['DB_PASS'] ?? '';
$port = $_ENV['DB_PORT'] ?? 3306;

try {
    $dsn = "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4";
    $pdo = new PDO($dsn, $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "Successfully connected to InfinityFree database!";
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
?>