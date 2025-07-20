<?php
require_once 'includes/auth.php';

// Logout user
logoutUser();

// Redirect to login page
header('Location: login.php');
exit;
?>