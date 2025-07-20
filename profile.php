<?php
$pageTitle = 'My Profile';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

// Redirect if not logged in
if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

// Get database connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}

// Get current user ID from session
$userId = $_SESSION['user_id'];
$errors = [];
$success = '';

// Get user details
$user = null;
$sql = "SELECT * FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('Location: logout.php');
    exit;
}

$user = $result->fetch_assoc();

// Get all departments for dropdown
$departments = [];
$sql = "SELECT * FROM departments ORDER BY name ASC";
$result = $conn->query($sql);
while ($row = $result->fetch_assoc()) {
    $departments[] = $row;
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate input
    $fullName = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    // WhatsApp field is not in the database schema, so we'll ignore it
    $departmentId = $_POST['department'];
    $currentPassword = trim($_POST['current_password']);
    $newPassword = trim($_POST['new_password']);
    $confirmPassword = trim($_POST['confirm_password']);
    
    // Basic validation
    if (empty($fullName)) {
        $errors[] = "Full name is required";
    }
    
    if (empty($email)) {
        $errors[] = "Email is required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
    } else {
        // Check if email exists (excluding current user)
        $sql = "SELECT id FROM users WHERE email = ? AND id != ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $email, $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $errors[] = "Email already exists";
        }
    }
    
    if (empty($phone)) {
        $errors[] = "Phone number is required";
    }
    
    // Password validation
    if (!empty($newPassword) || !empty($currentPassword)) {
        // Verify current password
        if (empty($currentPassword)) {
            $errors[] = "Current password is required to change password";
        } elseif (!password_verify($currentPassword, $user['password'])) {
            $errors[] = "Current password is incorrect";
        }
        
        // Validate new password
        if (!empty($newPassword)) {
            if (strlen($newPassword) < 8) {
                $errors[] = "New password must be at least 8 characters long";
            } elseif ($newPassword !== $confirmPassword) {
                $errors[] = "New passwords do not match";
            }
        }
    }
    
    // If no errors, update user
    if (empty($errors)) {
        if (!empty($newPassword) && !empty($currentPassword)) {
            // Update with new password
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            $sql = "UPDATE users SET 
                    full_name = ?, 
                    email = ?, 
                    phone = ?, 
                    department = ?, 
                    password = ?, 
                    updated_at = NOW() 
                    WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssssi", $fullName, $email, $phone, $departmentId, $hashedPassword, $userId);
        } else {
            // Update without changing password
            $sql = "UPDATE users SET 
                    full_name = ?, 
                    email = ?, 
                    phone = ?, 
                    department = ?, 
                    updated_at = NOW() 
                    WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssssi", $fullName, $email, $phone, $departmentId, $userId);
        }
        
        if ($stmt->execute()) {
            $success = "Profile updated successfully";
            
            // Refresh user data
            $sql = "SELECT * FROM users WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();
        } else {
            $errors[] = "Error updating profile: " . $conn->error;
        }
    }
}

// Include header
include 'includes/header.php';
?>

<div class="container py-5">
    <div class="row">
        <div class="col-lg-4 mb-4">
            <!-- Profile Card -->
            <div class="card shadow-sm">
                <div class="card-body text-center">
                    <div class="avatar-circle mx-auto mb-3">
                        <span class="initials"><?php echo substr($user['full_name'], 0, 1); ?></span>
                    </div>
                    <h4 class="mb-1"><?php echo htmlspecialchars($user['full_name']); ?></h4>
                    <p class="text-muted mb-3"><?php echo htmlspecialchars($user['username']); ?></p>
                    
                    <?php if ($user['role'] === 'admin'): ?>
                        <div class="mb-3">
                            <span class="badge bg-danger">Administrator</span>
                        </div>
                    <?php endif; ?>
                    
                    <div class="d-grid gap-2">
                        <a href="my_reports.php" class="btn btn-outline-primary">
                            <i class="fas fa-clipboard-list me-1"></i> My Reports
                        </a>
                        <?php if ($user['role'] === 'admin'): ?>
                            <a href="admin/dashboard.php" class="btn btn-outline-danger">
                                <i class="fas fa-tachometer-alt me-1"></i> Admin Dashboard
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Contact Information -->
            <div class="card shadow-sm mt-4">
                <div class="card-header bg-white">
                    <h5 class="card-title mb-0">Contact Information</h5>
                </div>
                <div class="card-body">
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item px-0 py-2 d-flex justify-content-between">
                            <span><i class="fas fa-envelope me-2 text-primary"></i> Email</span>
                            <span class="text-muted"><?php echo htmlspecialchars($user['email']); ?></span>
                        </li>
                        <li class="list-group-item px-0 py-2 d-flex justify-content-between">
                            <span><i class="fas fa-phone me-2 text-primary"></i> Phone</span>
                            <span class="text-muted"><?php echo htmlspecialchars($user['phone']); ?></span>
                        </li>
                        <!-- WhatsApp field removed as it's not in the database schema -->
                        <li class="list-group-item px-0 py-2 d-flex justify-content-between">
                            <span><i class="fas fa-calendar-alt me-2 text-primary"></i> Joined</span>
                            <span class="text-muted"><?php echo formatDate($user['created_at']); ?></span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
        
        <div class="col-lg-8">
            <!-- Edit Profile Form -->
            <div class="card shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="card-title mb-0">Edit Profile</h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <strong>Error!</strong>
                            <ul class="mb-0">
                                <?php foreach ($errors as $error): ?>
                                    <li><?php echo $error; ?></li>
                                <?php endforeach; ?>
                            </ul>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($success)): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <strong>Success!</strong> <?php echo $success; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>
                    
                    <form method="post" action="" class="needs-validation" novalidate>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="username" class="form-label">Username</label>
                                <input type="text" class="form-control" id="username" value="<?php echo htmlspecialchars($user['username']); ?>" readonly>
                                <div class="form-text">Username cannot be changed.</div>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="full_name" class="form-label">Full Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="full_name" name="full_name" value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
                                <div class="invalid-feedback">Please enter your full name.</div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="email" class="form-label">Email Address <span class="text-danger">*</span></label>
                                <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                                <div class="invalid-feedback">Please enter a valid email address.</div>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="phone" class="form-label">Phone Number <span class="text-danger">*</span></label>
                                <input type="tel" class="form-control" id="phone" name="phone" value="<?php echo htmlspecialchars($user['phone']); ?>" required>
                                <div class="invalid-feedback">Please enter your phone number.</div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-12 mb-3">
                                <label for="department" class="form-label">Department <span class="text-danger">*</span></label>
                                <select class="form-select" id="department" name="department" required>
                                    <option value="">Select Department</option>
                                    <?php foreach ($departments as $department): ?>
                                        <option value="<?php echo $department['id']; ?>" <?php echo $user['department'] == $department['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($department['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="invalid-feedback">Please select your department.</div>
                            </div>
                        </div>
                        
                        <hr class="my-4">
                        <h5 class="mb-3">Change Password</h5>
                        <p class="text-muted mb-3">Leave these fields empty if you don't want to change your password.</p>
                        
                        <div class="row">
                            <div class="col-md-12 mb-3">
                                <label for="current_password" class="form-label">Current Password</label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="current_password" name="current_password">
                                    <button class="btn btn-outline-secondary toggle-password" type="button" data-target="current_password">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                                <div class="form-text">Required to change your password.</div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="new_password" class="form-label">New Password</label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="new_password" name="new_password">
                                    <button class="btn btn-outline-secondary toggle-password" type="button" data-target="new_password">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                                <div class="form-text">Minimum 8 characters.</div>
                                <div class="password-strength mt-2 d-none">
                                    <div class="progress" style="height: 5px;">
                                        <div class="progress-bar" role="progressbar" style="width: 0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
                                    </div>
                                    <small class="text-muted strength-text">Password strength: <span>Very Weak</span></small>
                                </div>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="confirm_password" class="form-label">Confirm New Password</label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password">
                                    <button class="btn btn-outline-secondary toggle-password" type="button" data-target="confirm_password">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                                <div class="password-match mt-2 d-none">
                                    <small class="text-danger">Passwords do not match</small>
                                </div>
                            </div>
                        </div>
                        
                        <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-4">
                            <button type="reset" class="btn btn-outline-secondary me-md-2">Reset</button>
                            <button type="submit" class="btn btn-primary">Save Changes</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.avatar-circle {
    width: 100px;
    height: 100px;
    background-color: var(--bs-primary);
    border-radius: 50%;
    display: flex;
    justify-content: center;
    align-items: center;
    margin: 0 auto;
}

.initials {
    font-size: 40px;
    line-height: 1;
    color: white;
    font-weight: bold;
    text-transform: uppercase;
}
</style>

<?php include 'includes/footer.php'; ?>