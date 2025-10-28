<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../config/database.php';
requireRole(['worker', 'admin']);

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $book_id = $_POST['book_id'] ?? '';
    $client_id = $_POST['client_id'] ?? '';
    $loan_days = $_POST['loan_days'] ?? 14;
    $worker_id = getUserId();
    
    $database = new Database();
    $db = $database->getConnection();
    
    try {
        // Call stored procedure to loan book
        $stmt = $db->prepare("CALL loan_book(?, ?, ?, ?)");
        $stmt->execute([$book_id, $client_id, $worker_id, $loan_days]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $_SESSION['success_message'] = "Book loaned successfully! Loan ID: " . $result['loan_id'];
        header("Location: loan-book.php");
        exit();
    } catch (PDOException $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Get success message from session
if (isset($_SESSION['success_message'])) {
    $success = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

// Get all available books
$database = new Database();
$db = $database->getConnection();
$books_query = "SELECT book_id, title, author, isbn, available_copies 
                FROM books 
                WHERE available_copies > 0 
                ORDER BY title";
$books_stmt = $db->query($books_query);
$books = $books_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all active clients
$clients_query = "SELECT user_id, username, full_name, email, phone 
                  FROM users 
                  WHERE role = 'client' AND is_active = 1 
                  ORDER BY full_name";
$clients_stmt = $db->query($clients_query);
$clients = $clients_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get recent loans
$recent = fetchAll("
    SELECT l.*, b.title, c.full_name as client_name
    FROM loans l
    JOIN books b ON l.book_id = b.book_id
    JOIN users c ON l.client_id = c.user_id
    WHERE l.worker_id = ? AND DATE(l.loan_date) = CURDATE()
    ORDER BY l.loan_date DESC
    LIMIT 5
", [getUserId()]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Loan a Book</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
    <style>
        .select2-container--bootstrap-5 .select2-selection {
            min-height: 38px;
            padding: 0.375rem 0.75rem;
            font-size: 1rem;
            border: 1px solid #dee2e6;
        }
        .select2-container--bootstrap-5 .select2-selection--single .select2-selection__rendered {
            line-height: 1.5;
            padding-left: 0;
        }
        .select2-container--bootstrap-5 .select2-dropdown {
            border-color: #dee2e6;
        }
        .select2-container--bootstrap-5 .select2-search--dropdown .select2-search__field {
            border: 1px solid #dee2e6;
            padding: 0.375rem 0.75rem;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="container mt-4">
        <div class="row">
            <div class="col-md-8 mx-auto">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0"><i class="bi bi-plus-circle"></i> Loan a Book</h4>
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
                        
                        <form method="POST" id="loanForm">
                            <div class="mb-3">
                                <label for="client_id" class="form-label">
                                    <i class="bi bi-person"></i> Select Client *
                                </label>
                                <select class="form-select" id="client_id" name="client_id" required>
                                    <option value="">Type to search for a client...</option>
                                    <?php foreach ($clients as $client): ?>
                                        <option value="<?= $client['user_id'] ?>" 
                                                data-email="<?= htmlspecialchars($client['email']) ?>"
                                                data-phone="<?= htmlspecialchars($client['phone']) ?>">
                                            <?= htmlspecialchars($client['full_name']) ?> 
                                            (<?= htmlspecialchars($client['username']) ?>)
                                            <?php if ($client['email']): ?>
                                                - <?= htmlspecialchars($client['email']) ?>
                                            <?php endif; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="text-muted">
                                    <i class="bi bi-info-circle"></i> Type name, username or email to search
                                </small>
                                <div id="clientInfo" class="mt-2"></div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="book_id" class="form-label">
                                    <i class="bi bi-book"></i> Select Book *
                                </label>
                                <select class="form-select" id="book_id" name="book_id" required>
                                    <option value="">Type to search for a book...</option>
                                    <?php foreach ($books as $book): ?>
                                        <option value="<?= $book['book_id'] ?>" 
                                                data-available="<?= $book['available_copies'] ?>"
                                                data-author="<?= htmlspecialchars($book['author']) ?>">
                                            <?= htmlspecialchars($book['title']) ?> - 
                                            <?= htmlspecialchars($book['author']) ?> 
                                            (ISBN: <?= htmlspecialchars($book['isbn']) ?>) 
                                            [<?= $book['available_copies'] ?> available]
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="text-muted">
                                    <i class="bi bi-info-circle"></i> Type title, author or ISBN to search
                                </small>
                                <div id="bookInfo" class="mt-2"></div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="loan_days" class="form-label">
                                    <i class="bi bi-calendar"></i> Loan Duration (Days) *
                                </label>
                                <select class="form-select" id="loan_days" name="loan_days">
                                    <option value="7">7 Days (1 Week)</option>
                                    <option value="14" selected>14 Days (2 Weeks)</option>
                                    <option value="21">21 Days (3 Weeks)</option>
                                    <option value="30">30 Days (1 Month)</option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <div class="card bg-light">
                                    <div class="card-body">
                                        <h6><i class="bi bi-info-circle"></i> Loan Summary</h6>
                                        <table class="table table-sm mb-0">
                                            <tr>
                                                <td><strong>Loan Date:</strong></td>
                                                <td><?= date('F d, Y') ?></td>
                                            </tr>
                                            <tr>
                                                <td><strong>Due Date:</strong></td>
                                                <td id="dueDate"><?= date('F d, Y', strtotime('+14 days')) ?></td>
                                            </tr>
                                            <tr>
                                                <td><strong>Worker:</strong></td>
                                                <td><?= getUserName() ?></td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="bi bi-check-circle"></i> Process Loan
                                </button>
                                <a href="index.php" class="btn btn-secondary">
                                    <i class="bi bi-x-circle"></i> Cancel
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Recent Loans -->
                <?php if (!empty($recent)): ?>
                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-clock-history"></i> Recent Loans (Today)</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Time</th>
                                        <th>Client</th>
                                        <th>Book</th>
                                        <th>Due Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent as $loan): ?>
                                        <tr>
                                            <td><?= date('g:i A', strtotime($loan['loan_date'])) ?></td>
                                            <td><?= htmlspecialchars($loan['client_name']) ?></td>
                                            <td><?= htmlspecialchars($loan['title']) ?></td>
                                            <td><?= date('M d, Y', strtotime($loan['due_date'])) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        $(document).ready(function() {
            // Initialize Select2 for Client dropdown with search
            $('#client_id').select2({
                theme: 'bootstrap-5',
                width: '100%',
                placeholder: 'Type to search for a client...',
                allowClear: true,
                minimumInputLength: 0,
                matcher: function(params, data) {
                    // If there are no search terms, return all data
                    if ($.trim(params.term) === '') {
                        return data;
                    }
                    
                    // Do not display the item if there is no 'text' property
                    if (typeof data.text === 'undefined') {
                        return null;
                    }
                    
                    // Search in text
                    if (data.text.toLowerCase().indexOf(params.term.toLowerCase()) > -1) {
                        return data;
                    }
                    
                    // Return null if the term should not be displayed
                    return null;
                }
            });
            
            // Initialize Select2 for Book dropdown with search
            $('#book_id').select2({
                theme: 'bootstrap-5',
                width: '100%',
                placeholder: 'Type to search for a book...',
                allowClear: true,
                minimumInputLength: 0,
                matcher: function(params, data) {
                    // If there are no search terms, return all data
                    if ($.trim(params.term) === '') {
                        return data;
                    }
                    
                    // Do not display the item if there is no 'text' property
                    if (typeof data.text === 'undefined') {
                        return null;
                    }
                    
                    // Search in text
                    if (data.text.toLowerCase().indexOf(params.term.toLowerCase()) > -1) {
                        return data;
                    }
                    
                    // Return null if the term should not be displayed
                    return null;
                }
            });
            
            // Show client info when selected
            $('#client_id').on('change', function() {
                const selected = $(this).find(':selected');
                const email = selected.data('email');
                const phone = selected.data('phone');
                
                if (selected.val()) {
                    let info = '<div class="alert alert-info"><i class="bi bi-person-check"></i> ';
                    info += '<strong>Selected Client:</strong> ' + selected.text() + '<br>';
                    if (email) info += '<i class="bi bi-envelope"></i> ' + email + '<br>';
                    if (phone) info += '<i class="bi bi-telephone"></i> ' + phone;
                    info += '</div>';
                    $('#clientInfo').html(info);
                } else {
                    $('#clientInfo').html('');
                }
            });
            
            // Update due date when loan days change
            $('#loan_days').on('change', function() {
                const days = parseInt($(this).val());
                const dueDate = new Date();
                dueDate.setDate(dueDate.getDate() + days);
                
                const options = { year: 'numeric', month: 'long', day: 'numeric' };
                $('#dueDate').text(dueDate.toLocaleDateString('en-US', options));
            });
            
            // Show book availability info
            $('#book_id').on('change', function() {
                const selected = $(this).find(':selected');
                const available = selected.data('available');
                const author = selected.data('author');
                
                if (selected.val()) {
                    let info = '<div class="alert alert-success">';
                    info += '<i class="bi bi-book-fill"></i> ';
                    info += '<strong>Author:</strong> ' + author + '<br>';
                    info += '<i class="bi bi-check-circle"></i> ';
                    info += '<strong>' + available + '</strong> copies available';
                    info += '</div>';
                    $('#bookInfo').html(info);
                } else {
                    $('#bookInfo').html('');
                }
            });
            
            // Form validation
            $('#loanForm').on('submit', function(e) {
                const clientId = $('#client_id').val();
                const bookId = $('#book_id').val();
                
                if (!clientId || !bookId) {
                    e.preventDefault();
                    alert('Please select both a client and a book');
                    return false;
                }
            });
        });
    </script>
</body>
</html>