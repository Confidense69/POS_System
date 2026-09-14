<?php
require 'config.php';

if (isset($_GET['barcode'])) {
   $stmt = $pdo->prepare("SELECT * FROM products WHERE barcode = :barcode");
   $stmt->execute([':barcode' => $_GET['barcode']]);
   $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
}else{
    $rows = $pdo->query('SELECT * FROM products')->fetchAll(PDO::FETCH_ASSOC);
    
}
echo json_encode($rows);



?>