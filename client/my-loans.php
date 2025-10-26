<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireRole(['client']);

$user_id = getUserId();

$loans = fetchAll(
    "SELECT l.*, b.title, b.author, b.isbn,
            COALESCE(f.fine_amount, 0) as fine_amount,
            COALESCE(f.is_paid, 1) as fine_paid
     FROM loans l
     JOIN books b ON l.book_id = b.book_id
     LEFT JOIN fines f ON l.loan_id = f.loan_id
     WHERE l.client_id = ?
     ORDER BY l.loan_date DESC",
    [$user_id]
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Loans History</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="container mt-4">
        <h2><i class="bi bi-clock-history"></i> My Loan History</h2>
        
        <div class="card mt-4">
            <div class="card-body">
                <?php if (empty($loans)): ?>
                    <p class="text-muted">No loan history</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Book</th>
                                    <th>Author</th>
                                    <th>Loan Date</th>
                                    <th>Due Date</th>
                                    <th>Return Date</th>
                                    <th>Status</th>
                                    <th>Fine</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($loans as $loan): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($loan['title']) ?></td>
                                        <td><?= htmlspecialchars($loan['author']) ?></td>
                                        <td><?= date('M d, Y', strtotime($loan['loan_date'])) ?></td>
                                        <td><?= date('M d, Y', strtotime($loan['due_date'])) ?></td>
                                        <td>
                                            <?= $loan['return_date'] ? date('M d, Y', strtotime($loan['return_date'])) : '-' ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?= 
                                                $loan['status'] == 'returned' ? 'success' : 
                                                ($loan['status'] == 'overdue' ? 'danger' : 'primary') 
                                            ?>">
                                                <?= ucfirst($loan['status']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($loan['fine_amount'] > 0): ?>
                                                <span class="badge bg-<?= $loan['fine_paid'] ? 'success' : 'danger' ?>">
                                                    $<?= number_format($loan['fine_amount'], 2) ?>
                                                    <?= $loan['fine_paid'] ? '(Paid)' : '(Unpaid)' ?>
                                                </span>
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/script.js"></script>
</body>
</html>