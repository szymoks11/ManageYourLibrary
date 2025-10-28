<?php
require_once 'includes/auth.php';

// Clear all session data
session_start();
session_unset();
session_destroy();

// Redirect to login page
header("Location: login.php");
exit();
?>