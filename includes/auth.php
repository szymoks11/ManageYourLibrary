<?php
session_start();

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header("Location: ../login.php");
        exit();
    }
}

function requireRole($roles) {
    requireLogin();
    if (!in_array($_SESSION['role'], $roles)) {
        header("Location: ../index.php");
        exit();
    }
}

function getUserRole() {
    return $_SESSION['role'] ?? null;
}

function getUserId() {
    return $_SESSION['user_id'] ?? null;
}

function getUserName() {
    return $_SESSION['full_name'] ?? null;
}
?>