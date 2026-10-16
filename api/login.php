<?php
require 'config.php';

$data = json_decode(file_get_contents('php://input'), true);

$username = trim($data["username"] ?? '');
$password = $data["password"] ?? ''; 



if ($username === '' || $password === '') {
    echo json_encode(['success' => false, 'message' => 'Username and password are required.']);
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM users WHERE username = :username AND is_active = 1");
$stmt->execute([':username' => $username]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if ($user && password_verify($password, $user['password_hash'])){
    session_start();
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['role'] = $user['role'];

    echo json_encode([
        'success' => true,
        'role' => $user['role'],
        'full_name' => $user['full_name']
    ]);

    }else{
        echo json_encode([
            'success' => false,
            'message' => 'Incorrect password or username.'
            ]);

}