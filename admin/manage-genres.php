<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../config/database.php';
requireRole(['admin']);
$success = '';
$error = '';

// Handle Add/Edit/Delete Genre
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $database = new Database();
    $db = $database->getConnection();
    
    if (isset($_POST['add_genre'])) {
        try {
            $query = "INSERT INTO genres (genre_name, description) VALUES (?, ?)";
            $stmt = $db->prepare($query);
            $stmt->execute([$_POST['genre_name'], $_POST['description']]);
            $success = "Genre added successfully!";
        } catch (PDOException $e) {
            $error = "Error: " . $e->getMessage();
        }
    }
    
    if (isset($_POST['edit_genre'])) {
        try {
            $query = "UPDATE genres SET genre_name = ?, description = ? WHERE genre_id = ?";
            $stmt = $db->prepare($query);
            $stmt->execute([$_POST['genre_name'], $_POST['description'], $_POST['genre_id']]);
            $success = "Genre updated successfully!";
        } catch (PDOException $e) {
            $error = "Error: " . $e->getMessage();
        }
    }
    
    if (isset($_POST['delete_genre'])) {
        try {
            $query = "DELETE FROM genres WHERE genre_id = ?";
            $stmt = $db->prepare($query);
            $stmt->execute([$_POST['genre_id']]);
            $success = "Genre deleted successfully!";
        } catch (PDOException $e) {
            $error = "Cannot delete genre. Books may be assigned to it.";
        }
    }
}

// Get all genres with book counts
$genres = fetchAll("
    SELECT g.*, COUNT(b.book_id) as book_count
    FROM genres g
    LEFT JOIN books b ON g.genre_id = b.genre_id
    GROUP BY g.genre_id
    ORDER BY g.genre_name
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Genres</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="container mt-4">
        <div class="row">
            <div class="col-md-8 mx-auto">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2><i class="bi bi-tags"></i> Manage Genres</h2>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addGenreModal">
                        <i class="bi bi-plus-circle"></i> Add Genre
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
                
                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Genre Name</th>
                                        <th>Description</th>
                                        <th>Books</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($genres as $genre): ?>
                                        <tr>
                                            <td><strong><?= htmlspecialchars($genre['genre_name']) ?></strong></td>
                                            <td><?= htmlspecialchars($genre['description']) ?></td>
                                            <td><span class="badge bg-primary"><?= $genre['book_count'] ?></span></td>
                                            <td>
                                                <button class="btn btn-sm btn-info" onclick='editGenre(<?= json_encode($genre) ?>)'>
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                                <?php if ($genre['book_count'] == 0): ?>
                                                    <button class="btn btn-sm btn-danger" onclick="deleteGenre(<?= $genre['genre_id'] ?>, '<?= addslashes($genre['genre_name']) ?>')">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Add Genre Modal -->
    <div class="modal fade" id="addGenreModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">Add New Genre</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Genre Name *</label>
                            <input type="text" class="form-control" name="genre_name" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="add_genre" class="btn btn-primary">Add Genre</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Edit Genre Modal -->
    <div class="modal fade" id="editGenreModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="genre_id" id="edit_genre_id">
                    <div class="modal-header">
                        <h5 class="modal-title">Edit Genre</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Genre Name *</label>
                            <input type="text" class="form-control" name="genre_name" id="edit_genre_name" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" id="edit_genre_desc" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="edit_genre" class="btn btn-primary">Update Genre</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Delete Genre Modal -->
    <div class="modal fade" id="deleteGenreModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="genre_id" id="delete_genre_id">
                    <div class="modal-header bg-danger text-white">
                        <h5 class="modal-title">Confirm Delete</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p>Are you sure you want to delete the genre: <strong id="delete_genre_name"></strong>?</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="delete_genre" class="btn btn-danger">Delete</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function editGenre(genre) {
            document.getElementById('edit_genre_id').value = genre.genre_id;
            document.getElementById('edit_genre_name').value = genre.genre_name;
            document.getElementById('edit_genre_desc').value = genre.description;
            new bootstrap.Modal(document.getElementById('editGenreModal')).show();
        }
        
        function deleteGenre(id, name) {
            document.getElementById('delete_genre_id').value = id;
            document.getElementById('delete_genre_name').textContent = name;
            new bootstrap.Modal(document.getElementById('deleteGenreModal')).show();
        }
    </script>
</body>
</html>