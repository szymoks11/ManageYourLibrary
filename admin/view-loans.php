<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireRole(['admin']);
$filter = $_GET['filter'] ?? 'all';

$where = "1=1";
if ($filter == 'active') {
    $where = "l.status = 'active'";
} elseif ($filter == 'overdue') {
    $where = "l.status = 'overdue'";
} elseif ($filter == 'returned') {
    $where = "l.status = 'returned'";
}

$loans = fetchAll("
    SELECT l.*, b.title, b.author, c.full_name as client_name, 
           w.full_name as worker_name,
           DATEDIFF(COALESCE(l.return_date, CURDATE()), l.due_date) as days_diff
    FROM loans l
    JOIN books b ON l.book_id = b.book_id
    JOIN users c ON l.client_id = c.user_id
    JOIN users w ON l.worker_id = w.user_id
    WHERE $where
    ORDER BY l.loan_date DESC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Loans</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="container-fluid mt-4">
        <h2><i class="bi bi-list-check"></i> All Loans</h2>
        
        <div class="btn-group mt-3 mb-4">
            <a href="?filter=all" class="btn btn-<?= $filter == 'all' ? 'primary' : 'outline-primary' ?>">All</a>
            <a href="?filter=active" class="btn btn-<?= $filter == 'active' ? 'success' : 'outline-success' ?>">Active</a>
            <a href="?filter=overdue" class="btn btn-<?= $filter == 'overdue' ? 'danger' : 'outline-danger' ?>">Overdue</a>
            <a href="?filter=returned" class="btn btn-<?= $filter == 'returned' ? 'info' : 'outline-info' ?>">Returned</a>
        </div>
        
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table id="loansTable" class="table table-striped table-sm">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Book</th>
                                <th>Client</th>
                                <th>Worker</th>
                                <th>Loan Date</th>
                                <th>Due Date</th>
                                <th>Return Date</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($loans as $loan): ?>
                                <tr>
                                    <td><?= $loan['loan_id'] ?></td>
                                    <td><small><?= htmlspecialchars($loan['title']) ?></small></td>
                                    <td><small><?= htmlspecialchars($loan['client_name']) ?></small></td>
                                    <td><small><?= htmlspecialchars($loan['worker_name']) ?></small></td>
                                    <td><?= date('M d, Y', strtotime($loan['loan_date'])) ?></td>
                                    <td><?= date('M d, Y', strtotime($loan['due_date'])) ?></td>
                                    <td><?= $loan['return_date'] ? date('M d, Y', strtotime($loan['return_date'])) : '-' ?></td>
                                    <td>
                                        <span class="badge bg-<?= 
                                            $loan['status'] == 'returned' ? 'success' : 
                                            ($loan['status'] == 'overdue' ? 'danger' : 'primary') 
                                        ?>">
                                            <?= ucfirst($loan['status']) ?>
                                        </span>
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
    <script src="../assets/js/script.js"></script>
    <script>
        $(document).ready(function() {
            $('#loansTable').DataTable({
                pageLength: 50,
                order: [[0, 'desc']]
            });
        });
    </script>
</body>
</html>