<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database connection and authentication functions
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Check if user is logged in and is admin
if (!isAdmin()) {
    header('Location: ../login.php');
    exit;
}

// Get current user info
$currentUser = null;
if (isset($_SESSION['user_id'])) {
    $userId = $_SESSION['user_id'];
    // Use existing connection if available, otherwise create a new one
    if (!isset($conn) || !$conn) {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    }
    if (!$conn->connect_error) {
        $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $currentUser = $result->fetch_assoc();
        }
        $stmt->close();
        // Don't close connection here as it's needed in the main page
    }
}

// Set default page title if not set
if (!isset($pageTitle)) {
    $pageTitle = 'Admin Panel';
}

// Get current page for active menu highlighting
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - UniFind Admin</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="assets/css/admin.css">
    
    <style>
        .admin-sidebar {
            min-height: 100vh;
            background-color: #343a40;
            color: #fff;
            position: fixed;
            left: 0;
            top: 0;
            width: 250px;
            z-index: 100;
            transition: all 0.3s;
        }
        
        .admin-sidebar .navbar-brand {
            padding: 1.5rem 1rem;
            font-size: 1.5rem;
            font-weight: bold;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .admin-sidebar .nav-link {
            color: rgba(255, 255, 255, 0.7);
            padding: 0.8rem 1rem;
            transition: all 0.3s;
        }
        
        .admin-sidebar .nav-link:hover,
        .admin-sidebar .nav-link.active {
            color: #fff;
            background-color: rgba(255, 255, 255, 0.1);
        }
        
        .admin-sidebar .nav-link i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
        }
        
        .admin-content {
            margin-left: 250px;
            padding: 20px;
            transition: all 0.3s;
        }
        
        .admin-topbar {
            background-color: #fff;
            border-bottom: 1px solid #e3e6f0;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
            position: fixed;
            top: 0;
            right: 0;
            left: 250px;
            z-index: 99;
            height: 70px;
            padding: 0 1.5rem;
            transition: all 0.3s;
        }
        
        .admin-main {
            margin-top: 90px;
            padding: 1.5rem;
            min-height: calc(100vh - 70px);
        }
        
        .sidebar-toggler {
            display: none;
        }
        
        .stats-card .stats-icon {
            opacity: 0.4;
        }
        
        .stats-card .card-footer {
            background: rgba(0, 0, 0, 0.1);
            border-top: none;
        }
        
        @media (max-width: 992px) {
            .admin-sidebar {
                margin-left: -250px;
            }
            
            .admin-sidebar.show {
                margin-left: 0;
            }
            
            .admin-content {
                margin-left: 0;
            }
            
            .admin-topbar {
                left: 0;
            }
            
            .sidebar-toggler {
                display: block;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <nav class="admin-sidebar" id="adminSidebar">
        <div class="navbar-brand d-flex align-items-center">
            <i class="fas fa-search-location me-2"></i>
            <span>UniFind Admin</span>
        </div>
        
        <div class="mt-4">
            <div class="user-info text-center mb-4">
                <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($currentUser['full_name']); ?>&background=random" 
                     class="rounded-circle mb-2" width="80" height="80" alt="Admin">
                <h6 class="mb-0"><?php echo htmlspecialchars($currentUser['full_name']); ?></h6>
                <small class="text-muted">Administrator</small>
            </div>
            
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link <?php echo ($currentPage == 'dashboard.php') ? 'active' : ''; ?>" href="dashboard.php">
                        <i class="fas fa-tachometer-alt"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo ($currentPage == 'manage_users.php') ? 'active' : ''; ?>" href="manage_users.php">
                        <i class="fas fa-users"></i> Manage Users
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo (strpos($currentPage, 'manage_items.php') !== false) ? 'active' : ''; ?>" href="manage_items.php">
                        <i class="fas fa-clipboard-list"></i> Manage Items
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo ($currentPage == 'manage_matches.php') ? 'active' : ''; ?>" href="manage_matches.php">
                        <i class="fas fa-exchange-alt"></i> Manage Matches
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo ($currentPage == 'manage_categories.php') ? 'active' : ''; ?>" href="manage_categories.php">
                        <i class="fas fa-tags"></i> Categories
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo ($currentPage == 'manage_departments.php') ? 'active' : ''; ?>" href="manage_departments.php">
                        <i class="fas fa-building"></i> Departments
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo ($currentPage == 'backup.php') ? 'active' : ''; ?>" href="backup.php">
                        <i class="fas fa-database"></i> Backup & Export
                    </a>
                </li>
                <li class="nav-item mt-3">
                    <a class="nav-link" href="../index.php" target="_blank">
                        <i class="fas fa-home"></i> View Site
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="../logout.php">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </li>
            </ul>
        </div>
    </nav>
    
    <!-- Content Wrapper -->
    <div class="admin-content">
        <!-- Top Navbar -->
        <nav class="admin-topbar d-flex justify-content-between align-items-center">
            <button class="btn sidebar-toggler" id="sidebarToggle">
                <i class="fas fa-bars"></i>
            </button>
            
            <div class="d-flex align-items-center">
                <div class="dropdown">
                    <a class="nav-link dropdown-toggle" href="#" role="button" id="userDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                        <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($currentUser['full_name']); ?>&background=random" 
                             class="rounded-circle me-2" width="32" height="32" alt="Admin">
                        <span class="d-none d-lg-inline"><?php echo htmlspecialchars($currentUser['full_name']); ?></span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                        <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user-circle me-2"></i> Profile</a></li>
                        <li><a class="dropdown-item" href="settings.php"><i class="fas fa-cog me-2"></i> Settings</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="../logout.php"><i class="fas fa-sign-out-alt me-2"></i> Logout</a></li>
                    </ul>
                </div>
            </div>
        </nav>
        
        <!-- Main Content -->
        <main class="admin-main">