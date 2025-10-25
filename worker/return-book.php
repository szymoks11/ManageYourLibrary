<?php
require_once '../includes/auth.php';
require_once '../config/database.php';
requireRole(['worker', 'admin']);

$success = '';
$error = '';
$loan_details = null;

// Handle return
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_return'])) {
    $loan_id = $_POST['loan_id'] ?? '';
    
    $database = new Database();
    $db = $database->getConnection();
    
    try {
        $stmt = $db->prepare("CALL return_book(?)");
        $stmt->execute([$loan_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $success = $result['message'];
        if ($result['fine_amount'] > 0) {
            $success .= " Fine charged: $" . number_format($result['fine_amount'], 2);
        }
        
        $loan_details = null;
    } catch (PDOException $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Search for loan
if (isset($_GET['search']) || isset($_POST['search_loan'])) {
    $search = $_GET['search'] ?? $_POST['search'] ?? '';
    
    $database = new Database();
    $db = $database->getConnection();
    
    // Search by loan ID or book ISBN or client name
    $query = "SELECT l.*, b.title, b.author, b.isbn, c.full_name as client_name, c.email, c.phone,
                     DATEDIFF(CURDATE(), l.due_date) as days_overdue
              FROM loans l
              JOIN books b ON l.book_id = b.book_id
              JOIN users c ON l.client_id = c.user_id
              WHERE l.status IN ('active', 'overdue')
              AND (l.loan_id = ? OR b.isbn LIKE ? OR c.full_name LIKE ?)
              LIMIT 1";
    
    $stmt = $db->prepare($query);
    $stmt->execute([$search, "%$search%", "%$search%"]);
    $loan_details = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$loan_details) {
        $error = "No active loan found with this search term.";
    }
}

// Get all active loans for dropdown
$database = new Database();
$db = $database->getConnection();
$active_loans_query = "SELECT l.loan_id, l.due_date, b.title, b.isbn, c.full_name as client_name,
                              DATEDIFF(CURDATE(), l.due_date) as days_overdue
                       FROM loans l
                       JOIN books b ON l.book_id = b.book_id
                       JOIN users c ON l.client_id = c.user_id
                       WHERE l.status IN ('active', 'overdue')
                       ORDER BY l.due_date ASC
                       LIMIT 100";
$active_loans = $db->query($active_loans_query)->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Return a Book</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="container mt-4">
        <div class="row">
            <div class="col-md-10 mx-auto">
                <div class="card">
                    <div class="card-header bg-success text-white">
                        <h4 class="mb-0"><i class="bi bi-arrow-return-left"></i> Return a Book</h4>
                    </div>
                    <div class="card-body">
                        <?php if ($success): ?>
                            <div class="alert alert-success alert-dismissible fade show">
                                <i class="bi bi-check-circle"></i> <?= $success ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($error): ?>
                            <div class="alert alert-danger alert-dismissible fade show">
                                <i class="bi bi-exclamation-triangle"></i> <?= $error ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Search Form -->
                        <div class="card mb-4">
                            <div class="card-body">
                                <h5 class="card-title">Search for Loan</h5>
                                <form method="POST" class="row g-3">
                                    <div class="col-md-8">
                                        <input type="text" class="form-control" name="search" 
                                               placeholder="Enter Loan ID, ISBN, or Client Name..." required>
                                    </div>
                                    <div class="col-md-4">
                                        <button type="submit" name="search_loan" class="btn btn-primary w-100">
                                            <i class="bi bi-search"></i> Search
                                        </button>
                                    </div>
                                </form>
                                
                                <hr class="my-3">
                                
                                <h5 class="card-title">Or Select from Active Loans</h5>
                                <form method="POST">
                                    <div class="row g-3">
                                        <div class="col-md-8">
                                            <select class="form-select" name="search" id="loanSelect">
                                                <option value="">Choose an active loan...</option>
                                                <?php foreach ($active_loans as $al): ?>
                                                    <option value="<?= $al['loan_id'] ?>">
                                                        Loan #<?= $al['loan_id'] ?> - 
                                                        <?= htmlspecialchars($al['title']) ?> - 
                                                        <?= htmlspecialchars($al['client_name']) ?>
                                                        <?php if ($al['days_overdue'] > 0): ?>
                                                            [OVERDUE: <?= $al['days_overdue'] ?> days]
                                                        <?php endif; ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <button type="submit" name="search_loan" class="btn btn-info w-100">
                                                <i class="bi bi-arrow-right"></i> Select
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                        
                        <!-- Loan Details & Return Confirmation -->
                        <?php if ($loan_details): ?>
                            <div class="card border-primary">
                                <div class="card-header bg-primary text-white">
                                    <h5 class="mb-0">Loan Details</h5>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <h6><i class="bi bi-book"></i> Book Information</h6>
                                            <table class="table table-sm">
                                                <tr>
                                                    <th>Title:</th>
                                                    <td><?= htmlspecialchars($loan_details['title']) ?></td>
                                                </tr>
                                                <tr>
                                                    <th>Author:</th>
                                                    <td><?= htmlspecialchars($loan_details['author']) ?></td>
                                                </tr>
                                                <tr>
                                                    <th>ISBN:</th>
                                                    <td><?= htmlspecialchars($loan_details['isbn']) ?></td>
                                                </tr>
                                            </table>
                                        </div>
                                        <div class="col-md-6">
                                            <h6><i class="bi bi-person"></i> Client Information</h6>
                                            <table class="table table-sm">
                                                <tr>
                                                    <th>Name:</th>
                                                    <td><?= htmlspecialchars($loan_details['client_name']) ?></td>
                                                </tr>
                                                <tr>
                                                    <th>Email:</th>
                                                    <td><?= htmlspecialchars($loan_details['email']) ?></td>
                                                </tr>
                                                <tr>
                                                    <th>Phone:</th>
                                                    <td><?= htmlspecialchars($loan_details['phone']) ?></td>
                                                </tr>
                                            </table>
                                        </div>
                                    </div>
                                    
                                    <hr>
                                    
                                    <div class="row">
                                        <div class="col-md-4">
                                            <strong>Loan Date:</strong><br>
                                            <?= date('F d, Y', strtotime($loan_details['loan_date'])) ?>
                                        </div>
                                        <div class="col-md-4">
                                            <strong>Due Date:</strong><br>
                                            <?= date('F d, Y', strtotime($loan_details['due_date'])) ?>
                                            <?php if ($loan_details['days_overdue'] > 0): ?>
                                                <span class="badge bg-danger">
                                                    <?= $loan_details['days_overdue'] ?> days overdue
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="col-md-4">
                                            <strong>Return Date:</strong><br>
                                            <?= date('F d, Y') ?> (Today)
                                        </div>
                                    </div>
                                    
                                    <?php if ($loan_details['days_overdue'] > 0): ?>
                                        <div class="alert alert-warning mt-3">
                                            <i class="bi bi-exclamation-triangle"></i>
                                            <strong>Fine Notice:</strong> This book is overdue by 
                                            <?= $loan_details['days_overdue'] ?> days.
                                            Estimated fine: $<?= number_format($loan_details['days_overdue'] * 0.50, 2) ?>
                                            (at $0.50 per day)
                                        </div>
                                    <?php endif; ?>
                                    
                                    <form method="POST" class="mt-4">
                                        <input type="hidden" name="loan_id" value="<?= $loan_details['loan_id'] ?>">
                                        <div class="d-grid gap-2">
                                            <button type="submit" name="confirm_return" class="btn btn-success btn-lg">
                                                <i class="bi bi-check-circle"></i> Confirm Return
                                            </button>
                                            <a href="return-book.php" class="btn btn-secondary">
                                                <i class="bi bi-x-circle"></i> Cancel
                                            </a>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Recent Returns -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-clock-history"></i> Recent Returns (Today)</h5>
                    </div>
                    <div class="card-body">
                        <?php
                        $recent_returns = fetchAll("
                            SELECT l.*, b.title, c.full_name as client_name,
                                   COALESCE(f.fine_amount, 0) as fine_amount
                            FROM loans l
                            JOIN books b ON l.book_id = b.book_id
                            JOIN users c ON l.client_id = c.user_id
                            LEFT JOIN fines f ON l.loan_id = f.loan_id
                            WHERE l.status = 'returned' 
                            AND DATE(l.return_date) = CURDATE()
                            ORDER BY l.return_date DESC
                            LIMIT 10
                        ");
                        ?>
                        
                        <?php if (empty($recent_returns)): ?>
                            <p class="text-muted">No returns processed today</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-sm table-hover">
                                    <thead>
                                        <tr>
                                            <th>Time</th>
                                            <th>Loan ID</th>
                                            <th>Client</th>
                                            <th>Book</th>
                                            <th>Fine</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recent_returns as $return): ?>
                                            <tr>
                                                <td><?= date('g:i A', strtotime($return['return_date'])) ?></td>
                                                <td>#<?= $return['loan_id'] ?></td>
                                                <td><?= htmlspecialchars($return['client_name']) ?></td>
                                                <td><?= htmlspecialchars($return['title']) ?></td>
                                                <td>
                                                    <?php if ($return['fine_amount'] > 0): ?>
                                                        <span class="badge bg-warning">
                                                            $<?= number_format($return['fine_amount'], 2) ?>
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
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
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#loanSelect').select2({
                theme: 'bootstrap-5',
                width: '100%',
                placeholder: 'Choose an active loan...'
            });
        });
    </script>
</body>
</html>