<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireRole(['client']);

$search = $_GET['search'] ?? '';
$books = [];

if ($search) {
    $books = fetchAll(
        "SELECT b.*, g.genre_name 
         FROM books b
         LEFT JOIN genres g ON b.genre_id = g.genre_id
         WHERE b.title LIKE ? OR b.author LIKE ? OR b.isbn LIKE ?
         ORDER BY b.title",
        ["%$search%", "%$search%", "%$search%"]
    );
} else {
    $books = fetchAll(
        "SELECT b.*, g.genre_name 
         FROM books b
         LEFT JOIN genres g ON b.genre_id = g.genre_id
         ORDER BY b.title
         LIMIT 50"
    );
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Search Books</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="container mt-4">
        <h2><i class="bi bi-search"></i> Search Books</h2>
        
        <!-- Search Form -->
        <form method="GET" class="mb-4">
            <div class="input-group">
                <input type="text" class="form-control" name="search" 
                       placeholder="Search by title, author, or ISBN..." 
                       value="<?= htmlspecialchars($search) ?>">
                <button class="btn btn-primary" type="submit">
                    <i class="bi bi-search"></i> Search
                </button>
            </div>
        </form>
        
        <!-- Results -->
        <div class="row">
            <?php if (empty($books)): ?>
                <div class="col-12">
                    <div class="alert alert-info">No books found</div>
                </div>
            <?php else: ?>
                <?php foreach ($books as $book): ?>
                    <div class="col-md-6 col-lg-4 mb-4">
                        <div class="card h-100">
                            <div class="card-body">
                                <h5 class="card-title"><?= htmlspecialchars($book['title']) ?></h5>
                                <p class="card-text">
                                    <strong>Author:</strong> <?= htmlspecialchars($book['author']) ?><br>
                                    <strong>ISBN:</strong> <?= htmlspecialchars($book['isbn']) ?><br>
                                    <strong>Genre:</strong> <?= htmlspecialchars($book['genre_name'] ?? 'N/A') ?><br>
                                    <strong>Publisher:</strong> <?= htmlspecialchars($book['publisher']) ?><br>
                                    <strong>Year:</strong> <?= $book['publication_year'] ?><br>
                                    <strong>Location:</strong> <?= htmlspecialchars($book['shelf_location']) ?>
                                </p>
                                <div class="mt-3">
                                    <?php if ($book['available_copies'] > 0): ?>
                                        <span class="badge bg-success">
                                            <?= $book['available_copies'] ?> Available
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Not Available</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>