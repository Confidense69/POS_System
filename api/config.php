<?php
header('Content-Type:  Application/json');
header('Access-Control-Allow-Origin: *');
$username = 'root';
$password = '';
try {
    $pdo = new PDO('mysql:host=localhost;dbname=pos_accounting', $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    
    echo json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]);
}



?>