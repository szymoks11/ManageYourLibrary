<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireRole(['worker', 'admin']);

// Get filter
$filter = $_GET['filter'] ?? 'all';

// Build query based on filter
$where = "l.status IN ('active', 'overdue')";
if ($filter == 'overdue') {
    $where .= " AND l.due_date < CURDATE()";
} elseif ($filter == 'due_soon') {
    $where .= " AND l.due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 3 DAY)";
}

$loans = fetchAll("
    SELECT l.*, b.title, b.author, b.isbn, 
           c.full_name as client_name, c.email, c.phone,
           w.full_name as worker_name,
           DATEDIFF(l.due_date, CURDATE()) as days_remaining,
           DATEDIFF(CURDATE(), l.due_date) as days_overdue
    FROM loans l
    JOIN books b ON l.book_id = b.book_id
    JOIN users c ON l.client_id = c.user_id
    JOIN users w ON l.worker_id = w.user_id
    WHERE $where
    ORDER BY l.due_date ASC
");

$stats = fetchOne("
    SELECT 
        COUNT(*) as total_active,
        COUNT(CASE WHEN due_date < CURDATE() THEN 1 END) as total_overdue,
        COUNT(CASE WHEN due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 3 DAY) THEN 1 END) as due_soon
    FROM loans
    WHERE status IN ('active', 'overdue')
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Active Loans</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="container-fluid mt-4">
        <h2><i class="bi bi-list-check"></i> Active Loans</h2>
        
        <!-- Filter Buttons -->
        <div class="btn-group mt-3 mb-4" role="group">
            <a href="?filter=all" class="btn btn-<?= $filter == 'all' ? 'primary' : 'outline-primary' ?>">
                All Active (<?= $stats['total_active'] ?>)
            </a>
            <a href="?filter=overdue" class="btn btn-<?= $filter == 'overdue' ? 'danger' : 'outline-danger' ?>">
                Overdue (<?= $stats['total_overdue'] ?>)
            </a>
            <a href="?filter=due_soon" class="btn btn-<?= $filter == 'due_soon' ? 'warning' : 'outline-warning' ?>">
                Due Soon (<?= $stats['due_soon'] ?>)
            </a>
        </div>
        
        <!-- Loans Table -->
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table id="loansTable" class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>Loan ID</th>
                                <th>Book</th>
                                <th>Client</th>
                                <th>Contact</th>
                                <th>Loan Date</th>
                                <th>Due Date</th>
                                <th>Status</th>
                                <th>Worker</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($loans as $loan): ?>
                                <tr class="<?= $loan['days_overdue'] > 0 ? 'table-danger' : 
                                            ($loan['days_remaining'] <= 3 ? 'table-warning' : '') ?>">
                                    <td>#<?= $loan['loan_id'] ?></td>
                                    <td>
                                        <strong><?= htmlspecialchars($loan['title']) ?></strong><br>
                                        <small class="text-muted">
                                            <?= htmlspecialchars($loan['author']) ?><br>
                                            ISBN: <?= htmlspecialchars($loan['isbn']) ?>
                                        </small>
                                    </td>
                                    <td><?= htmlspecialchars($loan['client_name']) ?></td>
                                    <td>
                                        <small>
                                            <i class="bi bi-envelope"></i> <?= htmlspecialchars($loan['email']) ?><br>
                                            <i class="bi bi-telephone"></i> <?= htmlspecialchars($loan['phone']) ?>
                                        </small>
                                    </td>
                                    <td><?= date('M d, Y', strtotime($loan['loan_date'])) ?></td>
                                    <td>
                                        <?= date('M d, Y', strtotime($loan['due_date'])) ?>
                                        <?php if ($loan['days_overdue'] > 0): ?>
                                            <br><span class="badge bg-danger">
                                                <?= $loan['days_overdue'] ?> days overdue
                                            </span>
                                        <?php elseif ($loan['days_remaining'] <= 3 && $loan['days_remaining'] >= 0): ?>
                                            <br><span class="badge bg-warning">
                                                Due in <?= $loan['days_remaining'] ?> days
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?= $loan['status'] == 'overdue' ? 'danger' : 'success' ?>">
                                            <?= ucfirst($loan['status']) ?>
                                        </span>
                                    </td>
                                    <td><small><?= htmlspecialchars($loan['worker_name']) ?></small></td>
                                    <td>
                                        <a href="return-book.php?search=<?= $loan['loan_id'] ?>" 
                                           class="btn btn-sm btn-success">
                                            <i class="bi bi-arrow-return-left"></i> Return
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#loansTable').DataTable({
                order: [[5, 'asc']], // Sort by due date
                pageLength: 25,
                language: {
                    search: "Search loans:"
                }
            });
        });
    </script>
    <script src="../assets/js/script.js"></script>
</body>
</html>