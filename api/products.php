<?php
require 'config.php';
session_start();

// Same auth rule as pos_system_api.php: no session, no data.
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Not authenticated']);
    exit;
}

if (isset($_GET['barcode'])) {
   $stmt = $pdo->prepare("SELECT * FROM products WHERE barcode = :barcode AND is_active = 1");
   $stmt->execute([':barcode' => $_GET['barcode']]);
   $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
}else{
    $rows = $pdo->query('SELECT * FROM products WHERE is_active = 1')->fetchAll(PDO::FETCH_ASSOC);
    
}
echo json_encode($rows);
