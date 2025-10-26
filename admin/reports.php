<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireRole(['admin']);
// Date range filter
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-t');

// Loans statistics
$loan_stats = fetchOne("
    SELECT 
        COUNT(*) as total_loans,
        COUNT(CASE WHEN status = 'returned' THEN 1 END) as returned,
        COUNT(CASE WHEN status = 'active' THEN 1 END) as active,
        COUNT(CASE WHEN status = 'overdue' THEN 1 END) as overdue,
        AVG(DATEDIFF(COALESCE(return_date, CURDATE()), loan_date)) as avg_loan_duration
    FROM loans
    WHERE DATE(loan_date) BETWEEN ? AND ?
", [$start_date, $end_date]);

// Financial statistics
$finance_stats = fetchOne("
    SELECT 
        COUNT(*) as total_fines,
        SUM(fine_amount) as total_fine_amount,
        SUM(CASE WHEN is_paid = 1 THEN fine_amount ELSE 0 END) as paid_fines,
        SUM(CASE WHEN is_paid = 0 THEN fine_amount ELSE 0 END) as unpaid_fines
    FROM fines
    WHERE DATE(created_at) BETWEEN ? AND ?
", [$start_date, $end_date]);

// Top books
$top_books = fetchAll("
    SELECT b.title, b.author, COUNT(l.loan_id) as loan_count
    FROM books b
    JOIN loans l ON b.book_id = l.book_id
    WHERE DATE(l.loan_date) BETWEEN ? AND ?
    GROUP BY b.book_id
    ORDER BY loan_count DESC
    LIMIT 10
", [$start_date, $end_date]);

// Top clients
$top_clients = fetchAll("
    SELECT u.full_name, u.email, COUNT(l.loan_id) as loan_count
    FROM users u
    JOIN loans l ON u.user_id = l.client_id
    WHERE DATE(l.loan_date) BETWEEN ? AND ?
    GROUP BY u.user_id
    ORDER BY loan_count DESC
    LIMIT 10
", [$start_date, $end_date]);

// Worker performance
$worker_performance = fetchAll("
    SELECT u.full_name, 
           COUNT(l.loan_id) as loans_processed,
           COUNT(CASE WHEN l.status = 'returned' THEN 1 END) as returns_processed
    FROM users u
    JOIN loans l ON u.user_id = l.worker_id
    WHERE u.role = 'worker' AND DATE(l.loan_date) BETWEEN ? AND ?
    GROUP BY u.user_id
    ORDER BY loans_processed DESC
", [$start_date, $end_date]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="container-fluid mt-4">
        <h2><i class="bi bi-graph-up"></i> Library Reports</h2>
        
        <!-- Date Filter -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label">Start Date</label>
                        <input type="date" class="form-control" name="start_date" value="<?= $start_date ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">End Date</label>
                        <input type="date" class="form-control" name="end_date" value="<?= $end_date ?>">
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-filter"></i> Filter
                        </button>
                    </div>
                    
                </form>
            </div>
        </div>
        
        <!-- Loan Statistics -->
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="card text-white bg-primary">
                    <div class="card-body text-center">
                        <h3><?= $loan_stats['total_loans'] ?></h3>
                        <p class="mb-0">Total Loans</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-white bg-success">
                    <div class="card-body text-center">
                        <h3><?= $loan_stats['returned'] ?></h3>
                        <p class="mb-0">Returned</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-white bg-warning">
                    <div class="card-body text-center">
                        <h3><?= $loan_stats['active'] ?></h3>
                        <p class="mb-0">Active</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-white bg-danger">
                    <div class="card-body text-center">
                        <h3><?= $loan_stats['overdue'] ?></h3>
                        <p class="mb-0">Overdue</p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Financial Statistics -->
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="card border-info">
                    <div class="card-body text-center">
                        <h4>$<?= number_format($finance_stats['total_fine_amount'] ?? 0, 2) ?></h4>
                        <small>Total Fines</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-success">
                    <div class="card-body text-center">
                        <h4>$<?= number_format($finance_stats['paid_fines'] ?? 0, 2) ?></h4>
                        <small>Paid Fines</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-danger">
                    <div class="card-body text-center">
                        <h4>$<?= number_format($finance_stats['unpaid_fines'] ?? 0, 2) ?></h4>
                        <small>Unpaid Fines</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-primary">
                    <div class="card-body text-center">
                        <h4><?= round($loan_stats['avg_loan_duration'] ?? 0, 1) ?></h4>
                        <small>Avg Loan Duration (days)</small>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row">
            <!-- Top Books -->
            <div class="col-md-6 mb-4">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">Top 10 Most Borrowed Books</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Title</th>
                                        <th>Author</th>
                                        <th>Loans</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($top_books as $index => $book): ?>
                                        <tr>
                                            <td><?= $index + 1 ?></td>
                                            <td><?= htmlspecialchars($book['title']) ?></td>
                                            <td><?= htmlspecialchars($book['author']) ?></td>
                                            <td><span class="badge bg-primary"><?= $book['loan_count'] ?></span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Top Clients -->
            <div class="col-md-6 mb-4">
                <div class="card">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0">Top 10 Most Active Clients</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Loans</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($top_clients as $index => $client): ?>
                                        <tr>
                                            <td><?= $index + 1 ?></td>
                                            <td><?= htmlspecialchars($client['full_name']) ?></td>
                                            <td><small><?= htmlspecialchars($client['email']) ?></small></td>
                                            <td><span class="badge bg-info"><?= $client['loan_count'] ?></span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Worker Performance -->
        <div class="card mb-4">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0">Worker Performance</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Worker Name</th>
                                <th>Loans Processed</th>
                                <th>Returns Processed</th>
                                <th>Total Transactions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($worker_performance as $worker): ?>
                                <tr>
                                    <td><?= htmlspecialchars($worker['full_name']) ?></td>
                                    <td><?= $worker['loans_processed'] ?></td>
                                    <td><?= $worker['returns_processed'] ?></td>
                                    <td><strong><?= $worker['loans_processed'] + $worker['returns_processed'] ?></strong></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/script.js"></script>
</body>
</html>