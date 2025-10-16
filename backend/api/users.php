<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

error_reporting(0);
ini_set('display_errors', 0);

require_once '../config.php';

try {
    $user = authenticate();
    checkRole($user, ['admin']); // tylko admin może zarządzać użytkownikami

    $method = $_SERVER['REQUEST_METHOD'];
    $db = getDB();

    // --- Funkcja do generowania unikalnego kodu członka ---
    function generateMemberCode($db) {
        $result = $db->query("SELECT COUNT(*) AS total FROM users");
        $row = $result->fetch_assoc();
        $next = $row['total'] + 1;
        return sprintf("LIB%05d", $next); // np. LIB00012
    }

    if ($method === 'GET') {
        $result = $db->query("SELECT id, first_name, last_name, member_code, role, created_at FROM users ORDER BY created_at DESC");
        $users = $result->fetch_all(MYSQLI_ASSOC);
        echo json_encode($users);

    } elseif ($method === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);

        if (empty($data['first_name']) || empty($data['last_name']) || empty($data['password']) || empty($data['role'])) {
            http_response_code(400);
            echo json_encode(['error' => 'First name, last name, password and role are required']);
            exit;
        }

        // Wygeneruj unikalny member_code
        $member_code = generateMemberCode($db);

        $hashedPassword = password_hash($data['password'], PASSWORD_DEFAULT);
        $stmt = $db->prepare("INSERT INTO users (first_name, last_name, password, role, member_code) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sssss", $data['first_name'], $data['last_name'], $hashedPassword, $data['role'], $member_code);

        if ($stmt->execute()) {
            echo json_encode([
                'success' => true,
                'id' => $db->insert_id,
                'member_code' => $member_code,
                'message' => 'User created successfully'
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to create user']);
        }

    } elseif ($method === 'PUT') {
        $data = json_decode(file_get_contents('php://input'), true);
        $id = $data['id'] ?? 0;

        if (!$id) {
            http_response_code(400);
            echo json_encode(['error' => 'User ID is required']);
            exit;
        }

        // Nie można edytować własnego konta (bezpieczeństwo)
        if ($id == $user['id']) {
            http_response_code(400);
            echo json_encode(['error' => 'Cannot modify your own account']);
            exit;
        }

        if (!empty($data['password'])) {
            $hashedPassword = password_hash($data['password'], PASSWORD_DEFAULT);
            $stmt = $db->prepare("UPDATE users SET first_name=?, last_name=?, password=?, role=? WHERE id=?");
            $stmt->bind_param("ssssi", $data['first_name'], $data['last_name'], $hashedPassword, $data['role'], $id);
        } else {
            $stmt = $db->prepare("UPDATE users SET first_name=?, last_name=?, role=? WHERE id=?");
            $stmt->bind_param("sssi", $data['first_name'], $data['last_name'], $data['role'], $id);
        }

        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'User updated successfully']);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to update user']);
        }

    } elseif ($method === 'DELETE') {
        $data = json_decode(file_get_contents('php://input'), true);
        $id = $data['id'] ?? 0;

        if (!$id) {
            http_response_code(400);
            echo json_encode(['error' => 'User ID is required']);
            exit;
        }

        if ($id == $user['id']) {
            http_response_code(400);
            echo json_encode(['error' => 'Cannot delete your own account']);
            exit;
        }

        $stmt = $db->prepare("DELETE FROM users WHERE id=?");
        $stmt->bind_param("i", $id);

        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'User deleted successfully']);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to delete user']);
        }
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error', 'details' => $e->getMessage()]);
}
?>
