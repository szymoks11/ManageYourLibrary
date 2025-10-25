<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireRole(['admin']);

// Get comprehensive statistics
$stats = fetchOne("
    SELECT 
        (SELECT COUNT(*) FROM books) as total_books,
        (SELECT SUM(total_copies) FROM books) as total_copies,
        (SELECT SUM(available_copies) FROM books) as available_copies,
        (SELECT COUNT(*) FROM users WHERE role = 'client') as total_clients,
        (SELECT COUNT(*) FROM users WHERE role = 'worker') as total_workers,
        (SELECT COUNT(*) FROM loans WHERE status IN ('active', 'overdue')) as active_loans,
        (SELECT COUNT(*) FROM loans WHERE status = 'overdue') as overdue_loans,
        (SELECT COALESCE(SUM(fine_amount), 0) FROM fines WHERE is_paid = 0) as unpaid_fines,
        (SELECT COUNT(*) FROM loans WHERE DATE(loan_date) = CURDATE()) as today_loans,
        (SELECT COUNT(*) FROM loans WHERE status = 'returned' AND DATE(return_date) = CURDATE()) as today_returns
");

// Most popular books
$popular_books = fetchAll("
    SELECT b.title, b.author, COUNT(l.loan_id) as loan_count
    FROM books b
    LEFT JOIN loans l ON b.book_id = l.book_id
    GROUP BY b.book_id
    ORDER BY loan_count DESC
    LIMIT 5
");

// Most active clients
$active_clients = fetchAll("
    SELECT u.full_name, u.email, COUNT(l.loan_id) as loan_count
    FROM users u
    LEFT JOIN loans l ON u.user_id = l.client_id
    WHERE u.role = 'client'
    GROUP BY u.user_id
    ORDER BY loan_count DESC
    LIMIT 5
");

// Recent activity
$recent_activity = fetchAll("
    SELECT l.loan_id, l.loan_date, l.return_date, l.status,
           b.title, c.full_name as client_name, w.full_name as worker_name
    FROM loans l
    JOIN books b ON l.book_id = b.book_id
    JOIN users c ON l.client_id = c.user_id
    JOIN users w ON l.worker_id = w.user_id
    ORDER BY l.loan_date DESC
    LIMIT 10
");

// Books by genre
$genre_stats = fetchAll("
    SELECT g.genre_name, COUNT(b.book_id) as book_count, 
           SUM(b.total_copies) as total_copies
    FROM genres g
    LEFT JOIN books b ON g.genre_id = b.genre_id
    GROUP BY g.genre_id
    ORDER BY book_count DESC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .stat-card {
            transition: all 0.3s;
            cursor: pointer;
        }
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        .stat-icon {
            font-size: 3rem;
            opacity: 0.3;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="container-fluid mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2><i class="bi bi-speedometer2"></i> Admin Dashboard</h2>
                <p class="text-muted">Complete overview of library operations</p>
            </div>
            <div>
                <span class="text-muted">Last updated: <?= date('M d, Y g:i A') ?></span>
            </div>
        </div>
        
        <!-- Statistics Cards Row 1 -->
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="card text-white bg-primary stat-card h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-title text-uppercase mb-0">Total Books</h6>
                                <h2 class="mt-2 mb-0"><?= $stats['total_books'] ?></h2>
                                <small><?= $stats['total_copies'] ?> copies</small>
                            </div>
                            <i class="bi bi-book-fill stat-icon"></i>
                        </div>
                    </div>
                    <div class="card-footer bg-transparent border-0">
                        <a href="manage-books.php" class="text-white text-decoration-none">
                            View all <i class="bi bi-arrow-right"></i>
                        </a>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="card text-white bg-success stat-card h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-title text-uppercase mb-0">Available</h6>
                                <h2 class="mt-2 mb-0"><?= $stats['available_copies'] ?></h2>
                                <small>Books ready to loan</small>
                            </div>
                            <i class="bi bi-check-circle-fill stat-icon"></i>
                        </div>
                    </div>
                    <div class="card-footer bg-transparent border-0">
                        <small>
                            <?= round(($stats['available_copies'] / $stats['total_copies']) * 100, 1) ?>% available
                        </small>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="card text-white bg-info stat-card h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-title text-uppercase mb-0">Total Users</h6>
                                <h2 class="mt-2 mb-0"><?= $stats['total_clients'] + $stats['total_workers'] ?></h2>
                                <small><?= $stats['total_clients'] ?> clients, <?= $stats['total_workers'] ?> workers</small>
                            </div>
                            <i class="bi bi-people-fill stat-icon"></i>
                        </div>
                    </div>
                    <div class="card-footer bg-transparent border-0">
                        <a href="manage-users.php" class="text-white text-decoration-none">
                            Manage users <i class="bi bi-arrow-right"></i>
                        </a>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="card text-white bg-warning stat-card h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-title text-uppercase mb-0">Active Loans</h6>
                                <h2 class="mt-2 mb-0"><?= $stats['active_loans'] ?></h2>
                                <small><?= $stats['overdue_loans'] ?> overdue</small>
                            </div>
                            <i class="bi bi-clipboard-check-fill stat-icon"></i>
                        </div>
                    </div>
                    <div class="card-footer bg-transparent border-0">
                        <a href="view-loans.php" class="text-white text-decoration-none">
                            View loans <i class="bi bi-arrow-right"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Statistics Cards Row 2 -->
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="card border-danger stat-card">
                    <div class="card-body text-center">
                        <i class="bi bi-exclamation-triangle-fill text-danger" style="font-size: 2rem;"></i>
                        <h3 class="mt-2"><?= $stats['overdue_loans'] ?></h3>
                        <p class="mb-0">Overdue Loans</p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="card border-success stat-card">
                    <div class="card-body text-center">
                        <i class="bi bi-cash-coin text-success" style="font-size: 2rem;"></i>
                        <h3 class="mt-2">$<?= number_format($stats['unpaid_fines'], 2) ?></h3>
                        <p class="mb-0">Unpaid Fines</p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="card border-primary stat-card">
                    <div class="card-body text-center">
                        <i class="bi bi-calendar-plus text-primary" style="font-size: 2rem;"></i>
                        <h3 class="mt-2"><?= $stats['today_loans'] ?></h3>
                        <p class="mb-0">Loans Today</p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="card border-info stat-card">
                    <div class="card-body text-center">
                        <i class="bi bi-arrow-return-left text-info" style="font-size: 2rem;"></i>
                        <h3 class="mt-2"><?= $stats['today_returns'] ?></h3>
                        <p class="mb-0">Returns Today</p>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row">
            <!-- Popular Books -->
            <div class="col-md-6 mb-4">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="bi bi-star-fill"></i> Most Popular Books</h5>
                    </div>
                    <div class="card-body">
                        <div class="list-group list-group-flush">
                            <?php foreach ($popular_books as $index => $book): ?>
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <span class="badge bg-primary rounded-pill me-2"><?= $index + 1 ?></span>
                                        <strong><?= htmlspecialchars($book['title']) ?></strong>
                                        <br><small class="text-muted"><?= htmlspecialchars($book['author']) ?></small>
                                    </div>
                                    <span class="badge bg-success rounded-pill"><?= $book['loan_count'] ?> loans</span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Active Clients -->
            <div class="col-md-6 mb-4">
                <div class="card">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0"><i class="bi bi-person-badge"></i> Most Active Clients</h5>
                    </div>
                    <div class="card-body">
                        <div class="list-group list-group-flush">
                            <?php foreach ($active_clients as $index => $client): ?>
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <span class="badge bg-info rounded-pill me-2"><?= $index + 1 ?></span>
                                        <strong><?= htmlspecialchars($client['full_name']) ?></strong>
                                        <br><small class="text-muted"><?= htmlspecialchars($client['email']) ?></small>
                                    </div>
                                    <span class="badge bg-primary rounded-pill"><?= $client['loan_count'] ?> loans</span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row">
            <!-- Genre Distribution Chart -->
            <div class="col-md-6 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-pie-chart-fill"></i> Books by Genre</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="genreChart"></canvas>
                    </div>
                </div>
            </div>
            
            <!-- Recent Activity -->
            <div class="col-md-6 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-clock-history"></i> Recent Activity</h5>
                    </div>
                    <div class="card-body" style="max-height: 400px; overflow-y: auto;">
                        <div class="list-group list-group-flush">
                            <?php foreach ($recent_activity as $activity): ?>
                                <div class="list-group-item">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <h6 class="mb-1"><?= htmlspecialchars($activity['title']) ?></h6>
                                            <small class="text-muted">
                                                <i class="bi bi-person"></i> <?= htmlspecialchars($activity['client_name']) ?>
                                                | <i class="bi bi-person-workspace"></i> <?= htmlspecialchars($activity['worker_name']) ?>
                                            </small>
                                        </div>
                                        <div class="text-end">
                                            <small class="text-muted"><?= date('M d, g:i A', strtotime($activity['loan_date'])) ?></small><br>
                                            <span class="badge bg-<?= 
                                                $activity['status'] == 'returned' ? 'success' : 
                                                ($activity['status'] == 'overdue' ? 'danger' : 'primary') 
                                            ?>">
                                                <?= ucfirst($activity['status']) ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Genre Distribution Chart
        const genreData = {
            labels: [
                <?php foreach ($genre_stats as $g): ?>
                    '<?= addslashes($g['genre_name']) ?>',
                <?php endforeach; ?>
            ],
            datasets: [{
                label: 'Books',
                data: [
                    <?php foreach ($genre_stats as $g): ?>
                        <?= $g['book_count'] ?>,
                    <?php endforeach; ?>
                ],
                backgroundColor: [
                    '#0d6efd', '#6610f2', '#6f42c1', '#d63384', '#dc3545',
                    '#fd7e14', '#ffc107', '#198754', '#20c997', '#0dcaf0'
                ]
            }]
        };

        const genreChart = new Chart(document.getElementById('genreChart'), {
            type: 'doughnut',
            data: genreData,
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'right',
                    }
                }
            }
        });
    </script>
</body>
</html>