<?php 
require_once 'includes/auth.php';

// Redirect if already logged in
if (isLoggedIn()) {
    $role = getUserRole();
    header("Location: $role/index.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Library Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
    <div class="landing-hero">
        <div class="container text-center">
            <div class="animate-fadeInUp">
                <i class="bi bi-book-half display-1 mb-4"></i>
                <h1 class="gradient-text">Library Management System</h1>
                <p class="lead">Your Complete Digital Library Solution</p>
                <div class="d-flex gap-3 justify-content-center mt-5">
                    <a href="login.php" class="btn btn-light btn-lg px-5 hover-lift">
                        <i class="bi bi-box-arrow-in-right"></i> Login
                    </a>
                    <a href="register.php" class="btn btn-outline-light btn-lg px-5 hover-lift">
                        <i class="bi bi-person-plus"></i> Register
                    </a>
                </div>
                
                <div class="row mt-5 pt-5">
                    <div class="col-md-4 mb-4">
                        <div class="glass-card p-4 hover-lift">
                            <i class="bi bi-search display-4 mb-3"></i>
                            <h4>Easy Search</h4>
                            <p>Find books quickly and easily</p>
                        </div>
                    </div>
                    <div class="col-md-4 mb-4">
                        <div class="glass-card p-4 hover-lift">
                            <i class="bi bi-clock-history display-4 mb-3"></i>
                            <h4>Track Loans</h4>
                            <p>Manage your borrowed books</p>
                        </div>
                    </div>
                    <div class="col-md-4 mb-4">
                        <div class="glass-card p-4 hover-lift">
                            <i class="bi bi-graph-up display-4 mb-3"></i>
                            <h4>Analytics</h4>
                            <p>View detailed reports</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>