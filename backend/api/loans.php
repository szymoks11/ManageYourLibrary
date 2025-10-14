<?php
// filepath: backend/api/loans.php
require_once '../config.php';

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
    
    if ($book['available'] < 1) {
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
    
    echo json_encode(['success' => true]);
    
} elseif ($method === 'PUT') {
    $id = $_GET['id'] ?? 0;
    
    $stmt = $db->prepare("SELECT book_id FROM loans WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $loan = $result->fetch_assoc();
    
    $stmt = $db->prepare("UPDATE loans SET returned_date = NOW() WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    
    $stmt = $db->prepare("UPDATE books SET available = available + 1 WHERE id=?");
    $stmt->bind_param("i", $loan['book_id']);
    $stmt->execute();
    
    echo json_encode(['success' => true]);
}
?>