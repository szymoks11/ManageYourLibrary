<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Suppress error display but log them
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once '../config.php';

try {
    $user = authenticate();
    checkRole($user, ['admin']); // Only admins can manage users

    $method = $_SERVER['REQUEST_METHOD'];
    $db = getDB();

    // Function to generate a unique member code
    function generateMemberCode($db) {
        $result = $db->query("SELECT COUNT(*) AS total FROM users");
        $row = $result->fetch_assoc();
        $next = $row['total'] + 1;
        return sprintf("LIB%05d", $next); // e.g., LIB00012
    }

    // Handle GET requests (Fetch all users)
    if ($method === 'GET') {
        $result = $db->query("SELECT id, first_name, last_name, username, member_code, role, created_at FROM users ORDER BY created_at DESC");
        if (!$result) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to fetch users']);
            exit;
        }

        $users = $result->fetch_all(MYSQLI_ASSOC);
        echo json_encode($users);
        exit;
    }

    // Handle POST requests (Create a new user)
    if ($method === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);

        if (empty($data['first_name']) || empty($data['last_name']) || empty($data['username']) || empty($data['password']) || empty($data['role'])) {
            http_response_code(400);
            echo json_encode(['error' => 'First name, last name, username, password, and role are required']);
            exit;
        }

        // Check if the username already exists
        $stmt = $db->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->bind_param("s", $data['username']);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            http_response_code(400);
            echo json_encode(['error' => 'Username already exists']);
            exit;
        }

        // Generate a unique member code
        $member_code = generateMemberCode($db);

        $hashedPassword = password_hash($data['password'], PASSWORD_DEFAULT);
        $stmt = $db->prepare("INSERT INTO users (first_name, last_name, username, password, role, member_code, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
        $stmt->bind_param("ssssss", $data['first_name'], $data['last_name'], $data['username'], $hashedPassword, $data['role'], $member_code);

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
        exit;
    }

    // Handle PUT requests (Update a user)
    if ($method === 'PUT') {
        $data = json_decode(file_get_contents('php://input'), true);
        $id = $data['id'] ?? 0;

        if (!$id) {
            http_response_code(400);
            echo json_encode(['error' => 'User ID is required']);
            exit;
        }

        // Prevent modifying your own account
        if ($id == $user['id']) {
            http_response_code(400);
            echo json_encode(['error' => 'Cannot modify your own account']);
            exit;
        }

        if (!empty($data['password'])) {
            $hashedPassword = password_hash($data['password'], PASSWORD_DEFAULT);
            $stmt = $db->prepare("UPDATE users SET first_name=?, last_name=?, username=?, password=?, role=? WHERE id=?");
            $stmt->bind_param("sssssi", $data['first_name'], $data['last_name'], $data['username'], $hashedPassword, $data['role'], $id);
        } else {
            $stmt = $db->prepare("UPDATE users SET first_name=?, last_name=?, username=?, role=? WHERE id=?");
            $stmt->bind_param("ssssi", $data['first_name'], $data['last_name'], $data['username'], $data['role'], $id);
        }

        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'User updated successfully']);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to update user']);
        }
        exit;
    }

    // Handle DELETE requests (Delete a user)
    if ($method === 'DELETE') {
        $data = json_decode(file_get_contents('php://input'), true);
        $id = $data['id'] ?? 0;

        if (!$id) {
            http_response_code(400);
            echo json_encode(['error' => 'User ID is required']);
            exit;
        }

        // Prevent deleting your own account
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
        exit;
    }

    // If method is not handled
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error', 'details' => $e->getMessage()]);
    error_log('users.php exception: ' . $e->getMessage());
    exit;
}