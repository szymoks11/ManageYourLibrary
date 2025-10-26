<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireRole(['worker', 'admin']);

$search = $_GET['search'] ?? '';
$genre = $_GET['genre'] ?? '';

// Get all genres for filter
$genres = fetchAll("SELECT * FROM genres ORDER BY genre_name");

// Build search query
$query = "SELECT b.*, g.genre_name,
          (SELECT COUNT(*) FROM loans WHERE book_id = b.book_id) as total_loans
          FROM books b
          LEFT JOIN genres g ON b.genre_id = g.genre_id
          WHERE 1=1";
$params = [];

if ($search) {
    $query .= " AND (b.title LIKE ? OR b.author LIKE ? OR b.isbn LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($genre) {
    $query .= " AND b.genre_id = ?";
    $params[] = $genre;
}

$query .= " ORDER BY b.title LIMIT 100";

$books = fetchAll($query, $params);
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
        <div class="card mt-3">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Search</label>
                        <input type="text" class="form-control" name="search" 
                               placeholder="Title, Author, or ISBN..." 
                               value="<?= htmlspecialchars($search) ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Genre</label>
                        <select class="form-select" name="genre">
                            <option value="">All Genres</option>
                            <?php foreach ($genres as $g): ?>
                                <option value="<?= $g['genre_id'] ?>" 
                                        <?= $genre == $g['genre_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($g['genre_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">&nbsp;</label>
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-search"></i> Search
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Results -->
        <div class="mt-4">
            <h5>Found <?= count($books) ?> book(s)</h5>
            
            <div class="row mt-3">
                <?php foreach ($books as $book): ?>
                    <div class="col-md-6 col-lg-4 mb-3">
                        <div class="card h-100">
                            <div class="card-header">
                                <h6 class="mb-0"><?= htmlspecialchars($book['title']) ?></h6>
                            </div>
                            <div class="card-body">
                                <p class="mb-2"><strong>Author:</strong> <?= htmlspecialchars($book['author']) ?></p>
                                <p class="mb-2"><strong>ISBN:</strong> <?= htmlspecialchars($book['isbn']) ?></p>
                                <p class="mb-2"><strong>Genre:</strong> <?= htmlspecialchars($book['genre_name'] ?? 'N/A') ?></p>
                                <p class="mb-2"><strong>Location:</strong> <?= htmlspecialchars($book['shelf_location']) ?></p>
                                <hr>
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <span class="badge bg-<?= $book['available_copies'] > 0 ? 'success' : 'danger' ?>">
                                            <?= $book['available_copies'] ?> / <?= $book['total_copies'] ?> Available
                                        </span>
                                    </div>
                                    <?php if ($book['available_copies'] > 0): ?>
                                        <a href="loan-book.php?book_id=<?= $book['book_id'] ?>" 
                                           class="btn btn-sm btn-primary">
                                            <i class="bi bi-plus-circle"></i> Loan
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
                
                <?php if (empty($books)): ?>
                    <div class="col-12">
                        <div class="alert alert-info">No books found</div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/script.js"></script>
</body>
</html>