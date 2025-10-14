<?php
// filepath: backend/api/books.php

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

$method = $_SERVER['REQUEST_METHOD'];

try {
    $db = getDB();
    
    if ($method === 'GET') {
        // Get all books
        $result = $db->query("SELECT * FROM books ORDER BY created_at DESC");
        $books = [];
        
        while ($row = $result->fetch_assoc()) {
            $books[] = $row;
        }
        
        echo json_encode($books);
        
    } elseif ($method === 'POST') {
        // Add new book (requires authentication)
        $user = authenticate();
        checkRole($user, ['admin', 'worker']);
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        $title = $data['title'] ?? '';
        $author = $data['author'] ?? '';
        $isbn = $data['isbn'] ?? '';
        $quantity = $data['quantity'] ?? 1;
        
        if (empty($title) || empty($author)) {
            http_response_code(400);
            echo json_encode(['error' => 'Title and author are required']);
            exit;
        }
        
        $stmt = $db->prepare("INSERT INTO books (title, author, isbn, quantity, available) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sssii", $title, $author, $isbn, $quantity, $quantity);
        
        if ($stmt->execute()) {
            echo json_encode([
                'success' => true,
                'id' => $db->insert_id,
                'message' => 'Book added successfully'
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to add book']);
        }
        
    } elseif ($method === 'PUT') {
        // Update book
        $user = authenticate();
        checkRole($user, ['admin', 'worker']);
        
        $data = json_decode(file_get_contents('php://input'), true);
        $id = $data['id'] ?? 0;
        
        if (!$id) {
            http_response_code(400);
            echo json_encode(['error' => 'Book ID is required']);
            exit;
        }
        
        $title = $data['title'] ?? '';
        $author = $data['author'] ?? '';
        $isbn = $data['isbn'] ?? '';
        $quantity = $data['quantity'] ?? 1;
        
        $stmt = $db->prepare("UPDATE books SET title=?, author=?, isbn=?, quantity=? WHERE id=?");
        $stmt->bind_param("sssii", $title, $author, $isbn, $quantity, $id);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Book updated successfully']);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to update book']);
        }
        
    } elseif ($method === 'DELETE') {
        // Delete book
        $user = authenticate();
        checkRole($user, ['admin']);
        
        $data = json_decode(file_get_contents('php://input'), true);
        $id = $data['id'] ?? 0;
        
        if (!$id) {
            http_response_code(400);
            echo json_encode(['error' => 'Book ID is required']);
            exit;
        }
        
        $stmt = $db->prepare("DELETE FROM books WHERE id=?");
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Book deleted successfully']);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to delete book']);
        }
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error', 'details' => $e->getMessage()]);
}
?>