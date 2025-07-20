<?php
session_start();

require_once 'db.php';

/**
 * Register a new user
 * @param array $userData User data including username, full_name, email, phone, department, password
 * @return array Status and message
 */
function registerUser($userData) {
    global $conn;
    
    // Validate input
    if (empty($userData['username']) || empty($userData['full_name']) || 
        empty($userData['email']) || empty($userData['phone']) || 
        empty($userData['department']) || empty($userData['password']) || 
        empty($userData['confirm_password'])) {
        return ['status' => false, 'message' => 'All fields are required'];
    }
    
    // Check if passwords match
    if ($userData['password'] !== $userData['confirm_password']) {
        return ['status' => false, 'message' => 'Passwords do not match'];
    }
    
    // Check if username already exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1");
    $stmt->bind_param("ss", $userData['username'], $userData['email']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        return ['status' => false, 'message' => 'Username or email already exists'];
    }
    
    // Hash password
    $hashedPassword = password_hash($userData['password'], PASSWORD_DEFAULT);
    
    // Insert user
    $stmt = $conn->prepare("INSERT INTO users (username, full_name, email, phone, department, password, role, created_at) VALUES (?, ?, ?, ?, ?, ?, 'user', NOW())");
    $stmt->bind_param("ssssss", 
        $userData['username'], 
        $userData['full_name'], 
        $userData['email'], 
        $userData['phone'], 
        $userData['department'], 
        $hashedPassword
    );
    
    if ($stmt->execute()) {
        return ['status' => true, 'message' => 'Registration successful! You can now login.'];
    } else {
        return ['status' => false, 'message' => 'Registration failed: ' . $conn->error];
    }
}

/**
 * Login a user
 * @param string $username Username
 * @param string $password Password
 * @return array Status and message
 */
function loginUser($username, $password) {
    global $conn;
    
    // Validate input
    if (empty($username) || empty($password)) {
        return ['status' => false, 'message' => 'Username and password are required'];
    }
    
    // Get user
    $stmt = $conn->prepare("SELECT id, username, full_name, email, phone, department, password, role FROM users WHERE username = ? LIMIT 1");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        return ['status' => false, 'message' => 'Invalid username or password'];
    }
    
    $user = $result->fetch_assoc();
    
    // Verify password
    if (password_verify($password, $user['password'])) {
        // Set session
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['phone'] = $user['phone'];
        $_SESSION['department'] = $user['department'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['is_admin'] = ($user['role'] == 'admin') ? 1 : 0; // Keep for backward compatibility
        $_SESSION['logged_in'] = true;
        
        // Add more detailed logging
        error_log('Login successful for user: ' . $user['username']);
        error_log('User role from DB: ' . $user['role']);
        error_log('Session role set to: ' . $_SESSION['role']);
        error_log('Session is_admin set to: ' . $_SESSION['is_admin']);
        
        return ['status' => true, 'message' => 'Login successful', 'is_admin' => ($user['role'] == 'admin')];
    } else {
        return ['status' => false, 'message' => 'Invalid username or password'];
    }
}

/**
 * Check if user is logged in
 * @return bool True if logged in, false otherwise
 */
function isLoggedIn() {
    return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
}

/**
 * Check if user is admin
 * @return bool True if admin, false otherwise
 */
function isAdmin() {
    return isLoggedIn() && isset($_SESSION['role']) && $_SESSION['role'] == 'admin';
}

/**
 * Logout user
 */
function logoutUser() {
    // Unset all session variables
    $_SESSION = [];
    
    // Destroy the session
    session_destroy();
}

/**
 * Get current user data
 * @return array|null User data or null if not logged in
 */
function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    
    global $conn;
    $userId = $_SESSION['user_id'];
    
    $stmt = $conn->prepare("SELECT u.*, d.name as department_name 
                           FROM users u 
                           LEFT JOIN departments d ON u.department = d.id 
                           WHERE u.id = ? LIMIT 1");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        return null;
    }
    
    return $result->fetch_assoc();
}
?>