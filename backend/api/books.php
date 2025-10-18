<?php

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit(0);
}

// Log PHP errors but don't display them in responses
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once '../config.php';

try {
    // Get DB connection
    $db = getDB();

    // Read raw body once
    $rawBody = file_get_contents('php://input');
    $jsonBody = $rawBody ? json_decode($rawBody, true) : null;

    // Determine HTTP method
    $method = $_SERVER['REQUEST_METHOD'];
    if ($method === 'POST' && is_array($jsonBody) && !empty($jsonBody['_method'])) {
        $method = strtoupper($jsonBody['_method']);
    }

    // Normalize input: prefer JSON body, then $_POST
    $input = is_array($jsonBody) ? $jsonBody : $_POST;

    // Handle GET requests
    if ($method === 'GET') {
        $search = isset($_GET['search']) ? trim($_GET['search']) : '';

        if ($search !== '') {
            $like = '%' . $db->real_escape_string($search) . '%';
            $stmt = $db->prepare("SELECT * FROM books WHERE title LIKE ? OR author LIKE ? OR isbn LIKE ? ORDER BY created_at DESC");
            if (!$stmt) throw new Exception('DB prepare failed: ' . $db->error);
            $stmt->bind_param('sss', $like, $like, $like);
            $stmt->execute();
            $result = $stmt->get_result();
        } else {
            $result = $db->query("SELECT * FROM books ORDER BY created_at DESC");
            if (!$result) throw new Exception('DB query failed: ' . $db->error);
        }

        $books = [];
        while ($row = $result->fetch_assoc()) {
            $books[] = $row;
        }

        echo json_encode($books);
        exit;
    }

    // Authenticate user for POST, PUT, DELETE
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (!$authHeader || !preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }

    $token = $matches[1];
    $user = validateToken($token); // Implement validateToken() to decode and verify JWT
    if (!$user) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid token']);
        exit;
    }

    // Handle POST requests (Create a new book)
    if ($method === 'POST') {
        checkRole($user, ['admin', 'worker']);

        $title = trim($input['title'] ?? '');
        $author = trim($input['author'] ?? '');
        $isbn = trim($input['isbn'] ?? '');
        $quantity = isset($input['quantity']) ? (int)$input['quantity'] : 1;

        if ($title === '' || $author === '') {
            http_response_code(400);
            echo json_encode(['error' => 'Title and author are required']);
            exit;
        }

        $stmt = $db->prepare("INSERT INTO books (title, author, isbn, quantity, available, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
        if (!$stmt) throw new Exception('DB prepare failed: ' . $db->error);
        $stmt->bind_param("sssii", $title, $author, $isbn, $quantity, $quantity);

        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'id' => $db->insert_id, 'message' => 'Book added successfully']);
            exit;
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to add book']);
            exit;
        }
    }

    // Handle PUT requests (Update a book)
    if ($method === 'PUT') {
        checkRole($user, ['admin', 'worker']);

        $id = isset($input['id']) ? (int)$input['id'] : (isset($_GET['id']) ? (int)$_GET['id'] : 0);
        if (!$id) {
            http_response_code(400);
            echo json_encode(['error' => 'Book ID is required']);
            exit;
        }

        $title = trim($input['title'] ?? '');
        $author = trim($input['author'] ?? '');
        $isbn = trim($input['isbn'] ?? '');
        $quantity = isset($input['quantity']) ? (int)$input['quantity'] : 1;
        $available = isset($input['available']) ? (int)$input['available'] : $quantity;

        if ($available < 0) $available = 0;
        if ($available > $quantity) $available = $quantity;

        $stmt = $db->prepare("UPDATE books SET title=?, author=?, isbn=?, quantity=?, available=? WHERE id=?");
        if (!$stmt) throw new Exception('DB prepare failed: ' . $db->error);
        $stmt->bind_param("sssiii", $title, $author, $isbn, $quantity, $available, $id);

        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Book updated successfully']);
            exit;
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to update book']);
            exit;
        }
    }

    // Handle DELETE requests (Delete a book)
    if ($method === 'DELETE') {
        checkRole($user, ['admin']);

        $id = isset($input['id']) ? (int)$input['id'] : (isset($_GET['id']) ? (int)$_GET['id'] : 0);
        if (!$id) {
            http_response_code(400);
            echo json_encode(['error' => 'Book ID is required']);
            exit;
        }

        $stmt = $db->prepare("DELETE FROM books WHERE id=?");
        if (!$stmt) throw new Exception('DB prepare failed: ' . $db->error);
        $stmt->bind_param("i", $id);

        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Book deleted successfully']);
            exit;
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to delete book']);
            exit;
        }
    }

    // If method not handled
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error']);
    error_log('books.php exception: ' . $e->getMessage() . ' | trace: ' . $e->getTraceAsString());
    exit;
}