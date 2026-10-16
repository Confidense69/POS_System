<?php
require_once 'config.php';
session_start();

$data = json_decode(file_get_contents('php://input'), true);

$username = trim($data["username"] ?? '');
$password = $data["password"] ?? ''; 

if (empty($username) || empty($password)) {
    echo json_encode(['success' => false, 'message' => 'Username and password are required.']);
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM users WHERE username = :username AND is_active = 1");
$stmt->execute([':username' => $username]);
$user = $stmt->fetch();

if ($user && password_verify($password, $user['password_hash'])) {
    $_SESSION['user_id']   = $user['id'];
    $_SESSION['username']  = $user['username'];
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['role']      = $user['role'];

    $redirectUrl = match($user['role']) {
        'admin'   => 'pages/admin-dashboard.php',
        'manager' => 'pages/manager-dashboard.php',
        'cashier' => 'pages/cashier-dashboard.php',
        default   => 'index.html'
    };

    echo json_encode([
        'success'      => true,
        'role'         => $user['role'],
        'full_name'    => $user['full_name'],
        'redirect_url' => $redirectUrl
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Incorrect credentials or account deactivated.']);
}
?>






