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
    checkRole($user, ['worker', 'admin']);
    
    $method = $_SERVER['REQUEST_METHOD'];
    $db = getDB();

    if ($method === 'GET') {
        $sql = "SELECT l.*, b.title, b.author, u.username 
                FROM loans l 
                JOIN books b ON l.book_id = b.id 
                JOIN users u ON l.user_id = u.id 
                ORDER BY l.borrowed_date DESC";
        $result = $db->query($sql);
        $loans = $result->fetch_all(MYSQLI_ASSOC);
        echo json_encode($loans);
        
    } elseif ($method === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        
        $stmt = $db->prepare("SELECT available FROM books WHERE id=?");
        $stmt->bind_param("i", $data['book_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        $book = $result->fetch_assoc();
        
        if (!$book || $book['available'] < 1) {
            http_response_code(400);
            echo json_encode(['error' => 'Book not available']);
            exit;
        }
        
        $stmt = $db->prepare("INSERT INTO loans (book_id, user_id, due_date) VALUES (?, ?, ?)");
        $stmt->bind_param("iis", $data['book_id'], $data['user_id'], $data['due_date']);
        $stmt->execute();
        
        $stmt = $db->prepare("UPDATE books SET available = available - 1 WHERE id=?");
        $stmt->bind_param("i", $data['book_id']);
        $stmt->execute();
        
        echo json_encode(['success' => true, 'id' => $db->insert_id]);
        
    } elseif ($method === 'PUT') {
        $data = json_decode(file_get_contents('php://input'), true);
        $id = $data['id'] ?? 0;
        
        if (!$id) {
            http_response_code(400);
            echo json_encode(['error' => 'Loan ID is required']);
            exit;
        }
        
        $stmt = $db->prepare("SELECT book_id, returned_date FROM loans WHERE id=?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $loan = $result->fetch_assoc();
        
        if (!$loan) {
            http_response_code(404);
            echo json_encode(['error' => 'Loan not found']);
            exit;
        }
        
        if ($loan['returned_date']) {
            http_response_code(400);
            echo json_encode(['error' => 'Book already returned']);
            exit;
        }
        
        $stmt = $db->prepare("UPDATE loans SET returned_date = NOW() WHERE id=?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        
        $stmt = $db->prepare("UPDATE books SET available = available + 1 WHERE id=?");
        $stmt->bind_param("i", $loan['book_id']);
        $stmt->execute();
        
        echo json_encode(['success' => true]);
        
    } elseif ($method === 'DELETE') {
        checkRole($user, ['admin']); // Only admin can delete
        
        $data = json_decode(file_get_contents('php://input'), true);
        $id = $data['id'] ?? 0;
        
        if (!$id) {
            http_response_code(400);
            echo json_encode(['error' => 'Loan ID is required']);
            exit;
        }
        
        // Get loan to restore book availability if not returned
        $stmt = $db->prepare("SELECT book_id, returned_date FROM loans WHERE id=?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $loan = $result->fetch_assoc();
        
        if ($loan && !$loan['returned_date']) {
            // Restore book availability
            $stmt = $db->prepare("UPDATE books SET available = available + 1 WHERE id=?");
            $stmt->bind_param("i", $loan['book_id']);
            $stmt->execute();
        }
        
        $stmt = $db->prepare("DELETE FROM loans WHERE id=?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        
        echo json_encode(['success' => true]);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error', 'details' => $e->getMessage()]);
}
?>