<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../config/database.php';
requireRole(['admin']);

$success = '';
$error = '';

// Handle Add/Edit/Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $database = new Database();
    $db = $database->getConnection();
    
    if (isset($_POST['add_book'])) {
        try {
            $query = "INSERT INTO books (isbn, title, author, publisher, publication_year, 
                      genre_id, total_copies, available_copies, shelf_location, description) 
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $db->prepare($query);
            $stmt->execute([
                $_POST['isbn'],
                $_POST['title'],
                $_POST['author'],
                $_POST['publisher'],
                $_POST['publication_year'],
                $_POST['genre_id'] ?: null,
                $_POST['total_copies'],
                $_POST['total_copies'], // available_copies = total_copies initially
                $_POST['shelf_location'],
                $_POST['description']
            ]);
            $success = "Book added successfully!";
        } catch (PDOException $e) {
            $error = "Error: " . $e->getMessage();
        }
    }
    
    if (isset($_POST['edit_book'])) {
        try {
            $query = "UPDATE books SET isbn=?, title=?, author=?, publisher=?, 
                      publication_year=?, genre_id=?, total_copies=?, shelf_location=?, 
                      description=? WHERE book_id=?";
            $stmt = $db->prepare($query);
            $stmt->execute([
                $_POST['isbn'],
                $_POST['title'],
                $_POST['author'],
                $_POST['publisher'],
                $_POST['publication_year'],
                $_POST['genre_id'] ?: null,
                $_POST['total_copies'],
                $_POST['shelf_location'],
                $_POST['description'],
                $_POST['book_id']
            ]);
            $success = "Book updated successfully!";
        } catch (PDOException $e) {
            $error = "Error: " . $e->getMessage();
        }
    }
    
    if (isset($_POST['delete_book'])) {
        try {
            $query = "DELETE FROM books WHERE book_id = ?";
            $stmt = $db->prepare($query);
            $stmt->execute([$_POST['book_id']]);
            $success = "Book deleted successfully!";
        } catch (PDOException $e) {
            $error = "Cannot delete book. It may have active loans.";
        }
    }
}

// Get all books
$books = fetchAll("
    SELECT b.*, g.genre_name,
           (SELECT COUNT(*) FROM loans WHERE book_id = b.book_id) as total_loans
    FROM books b
    LEFT JOIN genres g ON b.genre_id = g.genre_id
    ORDER BY b.title
");

// Get all genres for dropdown
$genres = fetchAll("SELECT * FROM genres ORDER BY genre_name");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Books</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="container-fluid mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="bi bi-book"></i> Manage Books</h2>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addBookModal">
                <i class="bi bi-plus-circle"></i> Add New Book
            </button>
        </div>
        
        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <?= $success ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <?= $error ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <!-- Books Table -->
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table id="booksTable" class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>ISBN</th>
                                <th>Title</th>
                                <th>Author</th>
                                <th>Genre</th>
                                <th>Publisher</th>
                                <th>Year</th>
                                <th>Copies</th>
                                <th>Available</th>
                                <th>Location</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($books as $book): ?>
                                <tr>
                                    <td><?= $book['book_id'] ?></td>
                                    <td><?= htmlspecialchars($book['isbn']) ?></td>
                                    <td><strong><?= htmlspecialchars($book['title']) ?></strong></td>
                                    <td><?= htmlspecialchars($book['author']) ?></td>
                                    <td><?= htmlspecialchars($book['genre_name'] ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars($book['publisher']) ?></td>
                                    <td><?= $book['publication_year'] ?></td>
                                    <td><?= $book['total_copies'] ?></td>
                                    <td>
                                        <span class="badge bg-<?= $book['available_copies'] > 0 ? 'success' : 'danger' ?>">
                                            <?= $book['available_copies'] ?>
                                        </span>
                                    </td>
                                    <td><?= htmlspecialchars($book['shelf_location']) ?></td>
                                    <td>
                                        <button class="btn btn-sm btn-info" onclick='editBook(<?= json_encode($book) ?>)'>
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <button class="btn btn-sm btn-danger" onclick="deleteBook(<?= $book['book_id'] ?>, '<?= addslashes($book['title']) ?>')">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Add Book Modal -->
<div class="modal fade" id="addBookModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="manage-books.php" id="addBookForm">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-plus-circle"></i> Add New Book</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">ISBN *</label>
                            <input type="text" class="form-control" name="isbn" id="add_isbn" maxlength="13" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Title *</label>
                            <input type="text" class="form-control" name="title" id="add_title" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Author *</label>
                            <input type="text" class="form-control" name="author" id="add_author" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Publisher</label>
                            <input type="text" class="form-control" name="publisher" id="add_publisher">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Publication Year</label>
                            <input type="number" class="form-control" name="publication_year" id="add_year" min="1000" max="<?= date('Y') ?>">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Genre</label>
                            <select class="form-select" name="genre_id" id="add_genre_id">
                                <option value="">Select Genre</option>
                                <?php foreach ($genres as $genre): ?>
                                    <option value="<?= $genre['genre_id'] ?>"><?= htmlspecialchars($genre['genre_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Total Copies *</label>
                            <input type="number" class="form-control" name="total_copies" id="add_copies" min="1" value="1" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Shelf Location</label>
                            <input type="text" class="form-control" name="shelf_location" id="add_location" placeholder="e.g., A-101">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" id="add_description" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_book" value="1" class="btn btn-primary" id="submitAddBook">
                        <i class="bi bi-save"></i> Add Book
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
    
    <!-- Edit Book Modal -->
    <div class="modal fade" id="editBookModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="book_id" id="edit_book_id">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="bi bi-pencil"></i> Edit Book</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">ISBN *</label>
                                <input type="text" class="form-control" name="isbn" id="edit_isbn" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Title *</label>
                                <input type="text" class="form-control" name="title" id="edit_title" required>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Author *</label>
                                <input type="text" class="form-control" name="author" id="edit_author" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Publisher</label>
                                <input type="text" class="form-control" name="publisher" id="edit_publisher">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Publication Year</label>
                                <input type="number" class="form-control" name="publication_year" id="edit_year">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Genre</label>
                                <select class="form-select" name="genre_id" id="edit_genre">
                                    <option value="">Select Genre</option>
                                    <?php foreach ($genres as $genre): ?>
                                        <option value="<?= $genre['genre_id'] ?>"><?= htmlspecialchars($genre['genre_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Total Copies *</label>
                                <input type="number" class="form-control" name="total_copies" id="edit_copies" required>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Shelf Location</label>
                                <input type="text" class="form-control" name="shelf_location" id="edit_location">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" id="edit_description" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="edit_book" class="btn btn-primary">
                            <i class="bi bi-save"></i> Update Book
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteBookModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="book_id" id="delete_book_id">
                    <div class="modal-header bg-danger text-white">
                        <h5 class="modal-title"><i class="bi bi-trash"></i> Confirm Delete</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p>Are you sure you want to delete this book?</p>
                        <p><strong id="delete_book_title"></strong></p>
                        <div class="alert alert-warning">
                            <i class="bi bi-exclamation-triangle"></i>
                            This action cannot be undone!
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="delete_book" class="btn btn-danger">
                            <i class="bi bi-trash"></i> Delete Book
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    
    <script>
    $(document).ready(function() {
        // Initialize DataTable
        $('#booksTable').DataTable({
            pageLength: 25,
            order: [[2, 'asc']]
        });
        
        // Remove any previous event handlers that might block submission
        $('#addBookForm').off('submit');
        
        // Simple form validation without preventing submission
        $('#submitAddBook').on('click', function(e) {
            var isbn = $('#add_isbn').val().trim();
            var title = $('#add_title').val().trim();
            var author = $('#add_author').val().trim();
            
            if (!isbn || !title || !author) {
                e.preventDefault();
                alert('Please fill in all required fields (ISBN, Title, and Author)');
                return false;
            }
            
            // If validation passes, the form will submit normally
        });
    });
    
    function editBook(book) {
        $('#edit_book_id').val(book.book_id);
        $('#edit_isbn').val(book.isbn);
        $('#edit_title').val(book.title);
        $('#edit_author').val(book.author);
        $('#edit_publisher').val(book.publisher);
        $('#edit_year').val(book.publication_year);
        $('#edit_genre').val(book.genre_id);
        $('#edit_copies').val(book.total_copies);
        $('#edit_location').val(book.shelf_location);
        $('#edit_description').val(book.description);
        
        new bootstrap.Modal(document.getElementById('editBookModal')).show();
    }
    
    function deleteBook(id, title) {
        $('#delete_book_id').val(id);
        $('#delete_book_title').text(title);
        new bootstrap.Modal(document.getElementById('deleteBookModal')).show();
    }
</script>
</body>
</html>