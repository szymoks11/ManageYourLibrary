<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireRole(['worker', 'admin']);

// Get statistics
$stats = fetchOne("
    SELECT 
        COUNT(CASE WHEN status = 'active' THEN 1 END) as active_loans,
        COUNT(CASE WHEN status = 'overdue' THEN 1 END) as overdue_loans,
        COUNT(CASE WHEN status = 'returned' AND DATE(return_date) = CURDATE() THEN 1 END) as today_returns,
        (SELECT COUNT(*) FROM books WHERE available_copies > 0) as available_books
    FROM loans
");

// Recent activity (loans processed by this worker today)
$recent_loans = fetchAll("
    SELECT l.*, b.title, b.author, c.full_name as client_name
    FROM loans l
    JOIN books b ON l.book_id = b.book_id
    JOIN users c ON l.client_id = c.user_id
    WHERE l.worker_id = ? AND DATE(l.loan_date) = CURDATE()
    ORDER BY l.loan_date DESC
    LIMIT 10
", [getUserId()]);

// Overdue loans
$overdue_loans = fetchAll("
    SELECT l.*, b.title, b.author, c.full_name as client_name, c.phone, c.email,
           DATEDIFF(CURDATE(), l.due_date) as days_overdue
    FROM loans l
    JOIN books b ON l.book_id = b.book_id
    JOIN users c ON l.client_id = c.user_id
    WHERE l.status IN ('active', 'overdue') AND l.due_date < CURDATE()
    ORDER BY l.due_date ASC
    LIMIT 10
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Worker Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        .stat-card {
            transition: transform 0.2s;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="container mt-4">
        <h2><i class="bi bi-speedometer2"></i> Worker Dashboard</h2>
        <p class="text-muted">Welcome back, <?= getUserName() ?>!</p>
        
        <!-- Statistics Cards -->
        <div class="row mt-4">
            <div class="col-md-3">
                <div class="card text-white bg-primary stat-card">
                    <div class="card-body text-center">
                        <i class="bi bi-book-fill" style="font-size: 2rem;"></i>
                        <h3 class="mt-2"><?= $stats['active_loans'] ?></h3>
                        <p class="mb-0">Active Loans</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-white bg-danger stat-card">
                    <div class="card-body text-center">
                        <i class="bi bi-exclamation-triangle-fill" style="font-size: 2rem;"></i>
                        <h3 class="mt-2"><?= $stats['overdue_loans'] ?></h3>
                        <p class="mb-0">Overdue Loans</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-white bg-success stat-card">
                    <div class="card-body text-center">
                        <i class="bi bi-arrow-return-left" style="font-size: 2rem;"></i>
                        <h3 class="mt-2"><?= $stats['today_returns'] ?></h3>
                        <p class="mb-0">Returns Today</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-white bg-info stat-card">
                    <div class="card-body text-center">
                        <i class="bi bi-collection-fill" style="font-size: 2rem;"></i>
                        <h3 class="mt-2"><?= $stats['available_books'] ?></h3>
                        <p class="mb-0">Available Books</p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="card mt-4">
            <div class="card-header bg-dark text-white">
                <h5 class="mb-0"><i class="bi bi-lightning-fill"></i> Quick Actions</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <a href="loan-book.php" class="btn btn-primary btn-lg w-100">
                            <i class="bi bi-plus-circle"></i> Loan a Book
                        </a>
                    </div>
                    <div class="col-md-4 mb-3">
                        <a href="return-book.php" class="btn btn-success btn-lg w-100">
                            <i class="bi bi-arrow-return-left"></i> Return a Book
                        </a>
                    </div>
                    <div class="col-md-4 mb-3">
                        <a href="active-loans.php" class="btn btn-info btn-lg w-100">
                            <i class="bi bi-list-check"></i> View All Loans
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row mt-4">
            <!-- Recent Activity -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-clock-history"></i> Today's Activity</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($recent_loans)): ?>
                            <p class="text-muted">No loans processed today</p>
                        <?php else: ?>
                            <div class="list-group">
                                <?php foreach ($recent_loans as $loan): ?>
                                    <div class="list-group-item">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <h6 class="mb-1"><?= htmlspecialchars($loan['title']) ?></h6>
                                                <small class="text-muted">
                                                    <i class="bi bi-person"></i> <?= htmlspecialchars($loan['client_name']) ?>
                                                </small>
                                            </div>
                                            <small class="text-muted">
                                                <?= date('g:i A', strtotime($loan['loan_date'])) ?>
                                            </small>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Overdue Loans -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-danger text-white">
                        <h5 class="mb-0"><i class="bi bi-exclamation-triangle"></i> Overdue Loans</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($overdue_loans)): ?>
                            <p class="text-muted">No overdue loans</p>
                        <?php else: ?>
                            <div class="list-group">
                                <?php foreach ($overdue_loans as $loan): ?>
                                    <div class="list-group-item">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <h6 class="mb-1"><?= htmlspecialchars($loan['title']) ?></h6>
                                                <small class="text-muted">
                                                    <i class="bi bi-person"></i> <?= htmlspecialchars($loan['client_name']) ?><br>
                                                    <i class="bi bi-telephone"></i> <?= htmlspecialchars($loan['phone']) ?>
                                                </small>
                                            </div>
                                            <span class="badge bg-danger">
                                                <?= $loan['days_overdue'] ?> days overdue
                                            </span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/script.js"></script>
</body>
</html>