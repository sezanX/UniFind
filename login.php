<?php
$pageTitle = 'Login';
require_once 'includes/auth.php';

// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$error = '';
$success = '';

// Process login form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    $result = loginUser($username, $password);
    
    if ($result['status']) {
        // Redirect based on role or redirect parameter
        if (isAdmin()) {
            // Log session data for debugging
            error_log('Admin login successful. Session data: ' . print_r($_SESSION, true));
            header('Location: admin/dashboard.php');
        } else {
            // Check if there's a redirect parameter
            if (isset($_GET['redirect']) && !empty($_GET['redirect'])) {
                $redirect = filter_var($_GET['redirect'], FILTER_SANITIZE_URL);
                // Only allow redirects to local pages
                if (strpos($redirect, 'http') === false) {
                    header('Location: ' . $redirect);
                } else {
                    header('Location: index.php');
                }
            } else {
                header('Location: index.php');
            }
        }
        exit;
    } else {
        $error = $result['message'];
    }
}

// Check for success message from registration
if (isset($_GET['registered']) && $_GET['registered'] === 'true') {
    $success = 'Sign Up successful! You can now login.';
}

include 'includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-6 col-lg-5">
        <div class="card shadow-sm">
            <div class="card-header bg-white py-3">
                <h4 class="card-title mb-0 text-center">Login to UniFind</h4>
            </div>
            <div class="card-body p-4">
                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
                    </div>
                <?php endif; ?>
                
                <form method="post" action="" class="needs-validation" novalidate>
                    <div class="mb-3">
                        <label for="username" class="form-label">Username</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-user"></i></span>
                            <input type="text" class="form-control" id="username" name="username" required>
                        </div>
                        <div class="invalid-feedback">Please enter your username</div>
                    </div>
                    
                    <div class="mb-4">
                        <label for="password" class="form-label">Password</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-lock"></i></span>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>
                        <div class="invalid-feedback">Please enter your password</div>
                    </div>
                    
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">Login</button>
                    </div>
                </form>
            </div>
            <div class="card-footer bg-white py-3 text-center">
                <p class="mb-0">Don't have an account? <a href="signup.php" class="text-decoration-none">Sign Up</a></p>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>