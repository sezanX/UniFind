<?php
require_once 'includes/db.php';

// Check if admin user exists
$stmt = $conn->prepare("SELECT id, username, role FROM users WHERE role = 'admin'");
$stmt->execute();
$result = $stmt->get_result();

echo "<h2>Admin Users in Database:</h2>";
if ($result->num_rows > 0) {
    echo "<ul>";
    while ($row = $result->fetch_assoc()) {
        echo "<li>ID: {$row['id']}, Username: {$row['username']}, Role: {$row['role']}</li>";
    }
    echo "</ul>";
} else {
    echo "<p>No admin users found in the database.</p>";
}

echo "<h2>Session Information:</h2>";
echo "<pre>";
session_start();
print_r($_SESSION);
echo "</pre>";
?>