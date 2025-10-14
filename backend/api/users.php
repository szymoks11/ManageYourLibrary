<?php
// filepath: backend/api/users.php
require_once '../config.php';

$user = authenticate();
checkRole($user, ['admin']);
$method = $_SERVER['REQUEST_METHOD'];
$db = getDB();

if ($method === 'GET') {
    $result = $db->query("SELECT id, username, role, created_at FROM users ORDER BY created_at DESC");
    $users = $result->fetch_all(MYSQLI_ASSOC);
    echo json_encode($users);
    
} elseif ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $hashedPassword = password_hash($data['password'], PASSWORD_DEFAULT);
    
    $stmt = $db->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $data['username'], $hashedPassword, $data['role']);
    $stmt->execute();
    echo json_encode(['id' => $db->insert_id]);
    
} elseif ($method === 'DELETE') {
    $id = $_GET['id'] ?? 0;
    
    $stmt = $db->prepare("DELETE FROM users WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    echo json_encode(['success' => true]);
}
?>