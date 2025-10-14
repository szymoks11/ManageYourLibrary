<?php
// filepath: backend/api/books.php
require_once '../config.php';

$user = authenticate();
$method = $_SERVER['REQUEST_METHOD'];
$db = getDB();

if ($method === 'GET') {
    $search = $_GET['search'] ?? '';
    $sql = "SELECT * FROM books WHERE title LIKE ? OR author LIKE ? ORDER BY title";
    $stmt = $db->prepare($sql);
    $searchTerm = "%$search%";
    $stmt->bind_param("ss", $searchTerm, $searchTerm);
    $stmt->execute();
    $result = $stmt->get_result();
    $books = $result->fetch_all(MYSQLI_ASSOC);
    echo json_encode($books);
    
} elseif ($method === 'POST') {
    checkRole($user, ['worker', 'admin']);
    $data = json_decode(file_get_contents('php://input'), true);
    
    $stmt = $db->prepare("INSERT INTO books (title, author, isbn, quantity, available) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sssii", $data['title'], $data['author'], $data['isbn'], $data['quantity'], $data['quantity']);
    $stmt->execute();
    echo json_encode(['id' => $db->insert_id]);
    
} elseif ($method === 'PUT') {
    checkRole($user, ['worker', 'admin']);
    $id = $_GET['id'] ?? 0;
    $data = json_decode(file_get_contents('php://input'), true);
    
    $stmt = $db->prepare("UPDATE books SET title=?, author=?, isbn=?, quantity=?, available=? WHERE id=?");
    $stmt->bind_param("sssiii", $data['title'], $data['author'], $data['isbn'], $data['quantity'], $data['available'], $id);
    $stmt->execute();
    echo json_encode(['success' => true]);
    
} elseif ($method === 'DELETE') {
    checkRole($user, ['worker', 'admin']);
    $id = $_GET['id'] ?? 0;
    
    $stmt = $db->prepare("DELETE FROM books WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    echo json_encode(['success' => true]);
}
?>