<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container-fluid">
        <a class="navbar-brand" href="index.php">
            <i class="bi bi-shield-lock"></i> Admin Panel
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link" href="index.php">
                        <i class="bi bi-speedometer2"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="manage-books.php">
                        <i class="bi bi-book"></i> Manage Books
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="manage-users.php">
                        <i class="bi bi-people"></i> Manage Users
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="manage-genres.php">
                        <i class="bi bi-tags"></i> Genres
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="view-loans.php">
                        <i class="bi bi-list-check"></i> All Loans
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="reports.php">
                        <i class="bi bi-graph-up"></i> Reports
                    </a>
                </li>
            </ul>
            <ul class="navbar-nav">
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                        <i class="bi bi-person-circle"></i> <?= getUserName() ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><span class="dropdown-item-text text-muted">Role: Administrator</span></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="../logout.php">
                            <i class="bi bi-box-arrow-right"></i> Logout
                        </a></li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>