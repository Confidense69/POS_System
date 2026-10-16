<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
$username = 'root';
$password = '';
try {
    $pdo = new PDO('mysql:host=localhost;dbname=pos_accounting;charset=utf8mb4', $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    
    echo json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]);
    exit;
}



?>