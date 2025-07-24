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
    <title><?php echo htmlspecialchars($pageTitle); ?> - UniFind Admin</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        :root {
            --sidebar-width: 260px;
            --sidebar-bg: linear-gradient(180deg, #1d2327, #101418);
            --sidebar-color: #adb5bd;
            --sidebar-hover-color: #ffffff;
            --sidebar-active-bg: rgba(255, 255, 255, 0.05);
            --sidebar-active-border: #0d6efd;
            --content-bg: #f8f9fa;
            --topbar-height: 70px;
            --topbar-bg: #ffffff;
            --topbar-shadow: 0 2px 4px rgba(0,0,0,0.05);
            --font-family: 'Inter', sans-serif;
        }

        body {
            font-family: var(--font-family);
            background-color: var(--content-bg);
            color: #343a40;
        }

        .admin-sidebar {
            width: var(--sidebar-width);
            height: 100vh; /* Changed from min-height */
            position: fixed;
            top: 0;
            left: 0;
            z-index: 1030;
            background: var(--sidebar-bg);
            color: var(--sidebar-color);
            display: flex; /* Added */
            flex-direction: column; /* Added */
    transition: margin-left 0.3s ease-in-out;
        }

        .admin-sidebar .sidebar-header {
            padding: 1.25rem 1.5rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            display: flex;
            align-items: center;
            flex-shrink: 0; /* Added */
        }
        
        .admin-sidebar .sidebar-header .logo-icon {
            font-size: 1.8rem;
            margin-right: 0.75rem;
            color: var(--sidebar-active-border);
        }

        .admin-sidebar .sidebar-header .logo-text {
    font-size: 1.25rem;
            font-weight: 600;
            color: #fff;
        }

.admin-sidebar .user-info {
            padding: 1.5rem;
            text-align: center;
            flex-shrink: 0; /* Added */
}
        
        /* New wrapper for scrollable navigation */
        .sidebar-nav-wrapper {
            flex-grow: 1;
            overflow-y: auto;
        }

        /* Custom scrollbar styling */
        .sidebar-nav-wrapper::-webkit-scrollbar {
            width: 8px;
        }
        .sidebar-nav-wrapper::-webkit-scrollbar-track {
            background: rgba(0, 0, 0, 0.1);
        }
        .sidebar-nav-wrapper::-webkit-scrollbar-thumb {
            background-color: #6c757d;
            border-radius: 4px;
        }
        .sidebar-nav-wrapper::-webkit-scrollbar-thumb:hover {
            background-color: #adb5bd;
        }

        .admin-sidebar .nav-link {
            color: var(--sidebar-color);
            font-weight: 500;
    padding: 0.9rem 1.5rem;
            display: flex;
            align-items: center;
            border-left: 3px solid transparent;
            transition: all 0.2s ease-in-out;
        }

        .admin-sidebar .nav-link:hover {
            color: var(--sidebar-hover-color);
    background-color: var(--sidebar-active-bg);
        }

        .admin-sidebar .nav-link.active {
            color: var(--sidebar-hover-color);
            background-color: var(--sidebar-active-bg);
            border-left-color: var(--sidebar-active-border);
        }
        
        .admin-sidebar .nav-link i.nav-icon {
    width: 24px;
            margin-right: 0.75rem;
            font-size: 1.1rem;
            text-align: center;
        }
        
        .sidebar-footer {
            width: 100%;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            flex-shrink: 0; /* Added */
        }
        
        .admin-content-wrapper {
            margin-left: var(--sidebar-width);
    padding-top: var(--topbar-height);
            transition: margin-left 0.3s ease-in-out;
        }
        
        /* Other styles remain the same */
        .admin-topbar { height: var(--topbar-height); background-color: var(--topbar-bg); box-shadow: var(--topbar-shadow); position: fixed; top: 0; right: 0; left: var(--sidebar-width); z-index: 1020; padding: 0 2rem; display: flex; align-items: center; justify-content: space-between; transition: left 0.3s ease-in-out; }
        .sidebar-toggler { font-size: 1.5rem; color: #6c757d; cursor: pointer; display: none; }
        .admin-main { padding: 2rem; min-height: calc(100vh - var(--topbar-height)); }
        @media (max-width: 991.98px) { .admin-sidebar { margin-left: calc(-1 * var(--sidebar-width)); } .admin-sidebar.show { margin-left: 0; } .admin-content-wrapper { margin-left: 0; } .admin-topbar { left: 0; } .sidebar-toggler { display: block; } }
        .user-dropdown .dropdown-menu { border: none; box-shadow: 0 0.5rem 1rem rgba(0,0,0,.15); min-width: 220px; }
        .user-dropdown .dropdown-item { padding: 0.75rem 1.5rem; font-weight: 500; }
        .user-dropdown .dropdown-item i { width: 20px; margin-right: 0.5rem; color: #6c757d; }
        .user-dropdown .dropdown-header { padding: 0.75rem 1.5rem; font-weight: 600; }
    </style>
</head>
<body>
    <div class="d-flex">
        <nav class="admin-sidebar" id="adminSidebar">
            <div class="sidebar-header">
                <i class="fas fa-search-location logo-icon"></i>
                <span class="logo-text">UniFind Admin</span>
            </div>

            <div class="user-info">
                <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($currentUser['full_name']); ?>&background=0D6EFD&color=fff&size=80"
                     class="rounded-circle mb-2" alt="Admin">
                <h6 class="mb-0 text-white fw-bold"><?php echo htmlspecialchars($currentUser['full_name']); ?></h6>
                <small class="text-warning">Administrator</small>
            </div>

            <div class="sidebar-nav-wrapper">
                <ul class="nav flex-column">
                    <li class="nav-item">
            <a class="nav-link <?php echo ($currentPage == 'dashboard.php') ? 'active' : ''; ?>" href="dashboard.php">
                            <i class="fas fa-tachometer-alt nav-icon"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
            <a class="nav-link <?php echo ($currentPage == 'manage_users.php') ? 'active' : ''; ?>" href="manage_users.php">
                            <i class="fas fa-users nav-icon"></i> Manage Users
                        </a>
                    </li>
                    <li class="nav-item">
            <a class="nav-link <?php echo (strpos($currentPage, 'manage_items.php') !== false) ? 'active' : ''; ?>" href="manage_items.php">
                            <i class="fas fa-clipboard-list nav-icon"></i> Manage Items
                        </a>
                    </li>
                    <li class="nav-item">
                <a class="nav-link <?php echo ($currentPage == 'manage_matches.php') ? 'active' : ''; ?>" href="manage_matches.php">
                            <i class="fas fa-exchange-alt nav-icon"></i> Manage Matches
                        </a>
                    </li>
                    <li class="nav-item">
                 <a class="nav-link <?php echo ($currentPage == 'manage_categories.php') ? 'active' : ''; ?>" href="manage_categories.php">
                            <i class="fas fa-tags nav-icon"></i> Categories
                        </a>
                    </li>
                    <li class="nav-item">
                <a class="nav-link <?php echo ($currentPage == 'manage_departments.php') ? 'active' : ''; ?>" href="manage_departments.php">
                            <i class="fas fa-building nav-icon"></i> Departments
                        </a>
                    </li>
                    <li class="nav-item">
                <a class="nav-link <?php echo ($currentPage == 'backup.php') ? 'active' : ''; ?>" href="#">
                            <i class="fas fa-database nav-icon"></i> Backup & Export
                        </a>
                    </li>
                </ul>
            </div>
            
            <div class="sidebar-footer">
                <ul class="nav flex-column">
                     <li class="nav-item">
                 <a class="nav-link" href="../index.php" target="_blank">
                            <i class="fas fa-home nav-icon"></i> View Site
                        </a>
                    </li>
                    <li class="nav-item">
                <a class="nav-link" href="../logout.php">
                            <i class="fas fa-sign-out-alt nav-icon"></i> Logout
                        </a>
                    </li>
                </ul>
            </div>
        </nav>

        <div class="admin-content-wrapper">
            <nav class="admin-topbar">
                <i class="fas fa-bars sidebar-toggler" id="sidebarToggle"></i>
                
                <div class="ms-auto">
                <div class="dropdown user-dropdown">
                        <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" role="button" id="userDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            <span class="d-none d-lg-inline me-2"><?php echo htmlspecialchars($currentUser['full_name']); ?></span>
                            <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($currentUser['full_name']); ?>&background=random&size=32"
                                 class="rounded-circle" alt="Admin">
                        </a>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                            <li class="dropdown-header">
                                Welcome, <?php echo htmlspecialchars(explode(' ', $currentUser['full_name'])[0]); ?>!
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="../profile.php"><i class="fas fa-user-circle"></i> Profile</a></li>
                            <li><a class="dropdown-item" href="../index.php"><i class="fas fa-home"></i> View Site</a></li>
                            <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                        </ul>
                    </div>
                </div>
            </nav>

                        <main class="admin-main">