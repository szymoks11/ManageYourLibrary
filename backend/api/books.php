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

// --- Add below if you do not have auth.php ---
if (!function_exists('validateToken')) {
    function validateToken($token) {
        // For development, accept any token and return a dummy user
        return ['id' => 1, 'username' => 'admin', 'role' => 'admin'];
    }
}
if (!function_exists('checkRole')) {
    function checkRole($user, $roles) {
        if (!in_array($user['role'], $roles)) {
            http_response_code(403);
            echo json_encode(['error' => 'Forbidden: insufficient role']);
            exit;
        }
    }
}
// --- End stub ---

// Debug: log script entry and method
error_log('books.php called, method: ' . ($_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN'));

try {
    // Get DB connection
    $db = getDB();
    if (!$db) {
        error_log('DB connection failed');
        http_response_code(500);
        echo json_encode(['error' => 'Server error: DB connection failed']);
        exit;
    }

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
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    if (!$authHeader) {
        // Try apache_request_headers if available
        if (function_exists('apache_request_headers')) {
            $headers = apache_request_headers();
            if (isset($headers['Authorization'])) {
                $authHeader = $headers['Authorization'];
            }
        }
    }
    if (!$authHeader || !preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
        error_log('Authorization header missing or invalid. Headers: ' . json_encode(getallheaders()));
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized: Missing or invalid Authorization header']);
        exit;
    }

    $token = $matches[1];
    if (!function_exists('validateToken')) {
        error_log('validateToken() not defined');
        http_response_code(500);
        echo json_encode(['error' => 'Server error: validateToken() not defined']);
        exit;
    }
    $user = validateToken($token);
    if (!$user) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid token']);
        exit;
    }

    if (!function_exists('checkRole')) {
        error_log('checkRole() not defined');
        http_response_code(500);
        echo json_encode(['error' => 'Server error: checkRole() not defined']);
        exit;
    }

    // Handle POST requests (Create a new book)
    if ($method === 'POST') {
        checkRole($user, ['admin', 'worker']);

        // Debug log for incoming payload
        error_log('POST /books.php payload: ' . json_encode($input));

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
        if (!$stmt) {
            error_log('DB prepare failed (add book): ' . $db->error);
            http_response_code(500);
            echo json_encode(['error' => 'Server error: DB prepare failed', 'details' => $db->error]);
            exit;
        }
        $stmt->bind_param("sssii", $title, $author, $isbn, $quantity, $quantity);

        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'id' => $db->insert_id, 'message' => 'Book added successfully']);
            exit;
        } else {
            error_log('DB execute failed (add book): ' . $stmt->error);
            http_response_code(500);
            echo json_encode(['error' => 'Server error: Failed to add book', 'details' => $stmt->error]);
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
    echo json_encode(['error' => 'Server error', 'details' => $e->getMessage()]);
    error_log('books.php exception: ' . $e->getMessage() . ' | trace: ' . $e->getTraceAsString());
    exit;
}
?>