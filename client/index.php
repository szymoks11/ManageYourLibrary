<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireRole(['client']);

$user_id = getUserId();

// Get user's active loans
$active_loans = fetchAll(
    "SELECT l.*, b.title, b.author, b.isbn, 
            DATEDIFF(l.due_date, CURDATE()) as days_remaining
     FROM loans l
     JOIN books b ON l.book_id = b.book_id
     WHERE l.client_id = ? AND l.status IN ('active', 'overdue')
     ORDER BY l.due_date",
    [$user_id]
);

// Get user's unpaid fines
$fines = fetchAll(
    "SELECT f.*, b.title 
     FROM fines f
     JOIN loans l ON f.loan_id = l.loan_id
     JOIN books b ON l.book_id = b.book_id
     WHERE f.client_id = ? AND f.is_paid = 0",
    [$user_id]
);

// Get statistics
$stats = fetchOne(
    "SELECT 
        COUNT(CASE WHEN status IN ('active', 'overdue') THEN 1 END) as active_loans,
        COUNT(CASE WHEN status = 'returned' THEN 1 END) as total_returned,
        COALESCE(SUM(CASE WHEN f.is_paid = 0 THEN f.fine_amount ELSE 0 END), 0) as total_fines
     FROM loans l
     LEFT JOIN fines f ON l.loan_id = f.loan_id
     WHERE l.client_id = ?",
    [$user_id]
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Client Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="container mt-4">
        <h2>Welcome, <?= getUserName() ?>!</h2>
        
        <!-- Statistics Cards -->
        <div class="row mt-4">
            <div class="col-md-4">
                <div class="card text-white bg-primary">
                    <div class="card-body">
                        <h5 class="card-title"><i class="bi bi-book"></i> Active Loans</h5>
                        <h2><?= $stats['active_loans'] ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card text-white bg-success">
                    <div class="card-body">
                        <h5 class="card-title"><i class="bi bi-check-circle"></i> Books Returned</h5>
                        <h2><?= $stats['total_returned'] ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card text-white bg-danger">
                    <div class="card-body">
                        <h5 class="card-title"><i class="bi bi-cash"></i> Unpaid Fines</h5>
                        <h2>$<?= number_format($stats['total_fines'], 2) ?></h2>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Active Loans -->
        <div class="card mt-4">
            <div class="card-header">
                <h5>My Active Loans</h5>
            </div>
            <div class="card-body">
                <?php if (empty($active_loans)): ?>
                    <p class="text-muted">No active loans</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Book Title</th>
                                    <th>Author</th>
                                    <th>Loan Date</th>
                                    <th>Due Date</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($active_loans as $loan): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($loan['title']) ?></td>
                                        <td><?= htmlspecialchars($loan['author']) ?></td>
                                        <td><?= date('M d, Y', strtotime($loan['loan_date'])) ?></td>
                                        <td>
                                            <?= date('M d, Y', strtotime($loan['due_date'])) ?>
                                            <?php if ($loan['days_remaining'] < 0): ?>
                                                <span class="badge bg-danger">Overdue</span>
                                            <?php elseif ($loan['days_remaining'] <= 3): ?>
                                                <span class="badge bg-warning">Due Soon</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?= $loan['status'] == 'overdue' ? 'danger' : 'success' ?>">
                                                <?= ucfirst($loan['status']) ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Unpaid Fines -->
        <?php if (!empty($fines)): ?>
        <div class="card mt-4">
            <div class="card-header bg-danger text-white">
                <h5>Unpaid Fines</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Book</th>
                                <th>Reason</th>
                                <th>Amount</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($fines as $fine): ?>
                                <tr>
                                    <td><?= htmlspecialchars($fine['title']) ?></td>
                                    <td><?= htmlspecialchars($fine['reason']) ?></td>
                                    <td>$<?= number_format($fine['fine_amount'], 2) ?></td>
                                    <td><?= date('M d, Y', strtotime($fine['created_at'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/script.js"></script>
</body>
</html>