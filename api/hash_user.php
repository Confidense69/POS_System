<?php
require 'config.php';

$users = ['admin', 'manager', 'cashier'];

$stmt = $pdo->prepare('UPDATE users set password_hash = :hash WHERE username = :name');

foreach( $users as $username ){
    $hash = password_hash('password123', PASSWORD_DEFAULT);
    $stmt->execute([
        ':hash' => $hash,
        ':name' => $username
    ]);
}

echo "if this pops out it worked";
?>