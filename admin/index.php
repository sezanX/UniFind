<?php
// Include authentication functions
require_once '../includes/auth.php';

// Check if user is logged in and is admin
if (!isAdmin()) {
    header('Location: ../login.php');
    exit;
}

// Redirect to admin dashboard
header('Location: dashboard.php');
exit;
?>