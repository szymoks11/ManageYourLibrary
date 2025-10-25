<?php 
require_once 'includes/auth.php';

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
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
    <div class="container vh-100 d-flex align-items-center justify-content-center">
        <div class="text-center">
            <h1 class="display-3 mb-4">📚 Library Management System</h1>
            <p class="lead mb-4">Welcome to our digital library</p>
            <div class="d-grid gap-2 d-md-flex justify-content-md-center">
                <a href="login.php" class="btn btn-primary btn-lg px-5">Login</a>
                <a href="register.php" class="btn btn-outline-primary btn-lg px-5">Register</a>
            </div>
        </div>
    </div>
</body>
</html>