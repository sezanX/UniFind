<?php
$pageTitle = 'Manage Users';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Redirect if not admin
if (!isAdmin()) {
    header('Location: ../login.php');
    exit;
}

// Get database connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}

// Process user actions
$message = '';
$messageType = '';

// Delete user
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $userId = $_GET['delete'];
    
    // Don't allow deleting self
    if ($userId == $_SESSION['user_id']) {
        $message = 'You cannot delete your own account!';
        $messageType = 'danger';
    } else {
        // Check if user exists
        $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            // Delete user's reports and related data first
            
            // 1. Get lost items by this user
            $lostItems = [];
            $stmt = $conn->prepare("SELECT id, image FROM lost_items WHERE user_id = ?");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $lostItems[] = $row;
            }
            
            // 2. Get found items by this user
            $foundItems = [];
            $stmt = $conn->prepare("SELECT id, image FROM found_items WHERE user_id = ?");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $foundItems[] = $row;
            }
            
            // 3. Delete matches related to user's items
            foreach ($lostItems as $item) {
                $stmt = $conn->prepare("DELETE FROM matches WHERE lost_item_id = ?");
                $stmt->bind_param("i", $item['id']);
                $stmt->execute();
            }
            
            foreach ($foundItems as $item) {
                $stmt = $conn->prepare("DELETE FROM matches WHERE found_item_id = ?");
                $stmt->bind_param("i", $item['id']);
                $stmt->execute();
            }
            
            // 4. Delete lost items and their images
            foreach ($lostItems as $item) {
                if (!empty($item['image'])) {
                    $imagePath = "../uploads/" . $item['image'];
                    if (file_exists($imagePath)) {
                        unlink($imagePath);
                    }
                }
                
                $stmt = $conn->prepare("DELETE FROM lost_items WHERE id = ?");
                $stmt->bind_param("i", $item['id']);
                $stmt->execute();
            }
            
            // 5. Delete found items and their images
            foreach ($foundItems as $item) {
                if (!empty($item['image'])) {
                    $imagePath = "../uploads/" . $item['image'];
                    if (file_exists($imagePath)) {
                        unlink($imagePath);
                    }
                }
                
                $stmt = $conn->prepare("DELETE FROM found_items WHERE id = ?");
                $stmt->bind_param("i", $item['id']);
                $stmt->execute();
            }
            
            // 6. Delete contact messages
            $stmt = $conn->prepare("DELETE FROM contact_messages WHERE user_id = ?");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            
            // 7. Finally delete the user
            $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            
            if ($stmt->affected_rows > 0) {
                $message = 'User deleted successfully!';
                $messageType = 'success';
            } else {
                $message = 'Failed to delete user!';
                $messageType = 'danger';
            }
        } else {
            $message = 'User not found!';
            $messageType = 'danger';
        }
    }
}

// Toggle admin status
if (isset($_GET['toggle_admin']) && is_numeric($_GET['toggle_admin'])) {
    $userId = $_GET['toggle_admin'];
    
    // Don't allow changing own admin status
    if ($userId == $_SESSION['user_id']) {
        $message = 'You cannot change your own admin status!';
        $messageType = 'danger';
    } else {
        // Get current admin status
        $stmt = $conn->prepare("SELECT role FROM users WHERE id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            $newRole = ($user['role'] == 'admin') ? 'user' : 'admin';
            
            $stmt = $conn->prepare("UPDATE users SET role = ? WHERE id = ?");
            $stmt->bind_param("si", $newRole, $userId);
            $stmt->execute();
            
            if ($stmt->affected_rows > 0) {
                $message = 'User admin status updated successfully!';
                $messageType = 'success';
            } else {
                $message = 'Failed to update user admin status!';
                $messageType = 'danger';
            }
        } else {
            $message = 'User not found!';
            $messageType = 'danger';
        }
    }
}

// Get all users with pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Search functionality
$search = isset($_GET['search']) ? $_GET['search'] : '';
$searchCondition = '';
$searchParams = [];

if (!empty($search)) {
    $searchCondition = "WHERE username LIKE ? OR full_name LIKE ? OR email LIKE ? OR phone LIKE ?";
    $searchTerm = "%$search%";
    $searchParams = [$searchTerm, $searchTerm, $searchTerm, $searchTerm];
}

// Count total users for pagination
$totalUsers = 0;
$countSql = "SELECT COUNT(*) as total FROM users $searchCondition";

if (!empty($searchParams)) {
    $stmt = $conn->prepare($countSql);
    $stmt->bind_param(str_repeat("s", count($searchParams)), ...$searchParams);
    $stmt->execute();
    $countResult = $stmt->get_result();
    $totalUsers = $countResult->fetch_assoc()['total'];
} else {
    $countResult = $conn->query($countSql);
    $totalUsers = $countResult->fetch_assoc()['total'];
}

$totalPages = ceil($totalUsers / $limit);

// Get users for current page
$users = [];
$sql = "SELECT u.*, d.name as department_name, 
        (SELECT COUNT(*) FROM lost_items WHERE user_id = u.id) as lost_count,
        (SELECT COUNT(*) FROM found_items WHERE user_id = u.id) as found_count
        FROM users u 
        LEFT JOIN departments d ON u.department = d.id 
        $searchCondition 
        ORDER BY u.created_at DESC 
        LIMIT ? OFFSET ?";

if (!empty($searchParams)) {
    $stmt = $conn->prepare($sql);
    $types = str_repeat("s", count($searchParams)) . "ii";
    $params = array_merge($searchParams, [$limit, $offset]);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $limit, $offset);
    $stmt->execute();
    $result = $stmt->get_result();
}

while ($row = $result->fetch_assoc()) {
    $users[] = $row;
}

// Include admin header
include 'includes/admin_header.php';
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0 text-gray-800">Manage Users</h1>
        <a href="add_user.php" class="btn btn-primary">
            <i class="fas fa-user-plus me-2"></i>Add New User
        </a>
    </div>
    
    <?php if (!empty($message)): ?>
        <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
            <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white py-3">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <h5 class="card-title mb-0">All Users</h5>
                </div>
                <div class="col-md-6">
                    <form action="" method="GET" class="d-flex">
                        <input type="text" name="search" class="form-control" placeholder="Search users..." value="<?php echo htmlspecialchars($search); ?>">
                        <button type="submit" class="btn btn-primary ms-2"><i class="fas fa-search"></i></button>
                    </form>
                </div>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Username</th>
                            <th>Full Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Department</th>
                            <th>Reports</th>
                            <th>Role</th>
                            <th>Joined</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($users)): ?>
                            <tr>
                                <td colspan="10" class="text-center py-3">
                                    <?php echo empty($search) ? 'No users found' : 'No users matching "' . htmlspecialchars($search) . '"'; ?>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td><?php echo $user['id']; ?></td>
                                    <td><?php echo htmlspecialchars($user['username']); ?></td>
                                    <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td><?php echo htmlspecialchars($user['phone']); ?></td>
                                    <td><?php echo htmlspecialchars($user['department_name'] ?? 'N/A'); ?></td>
                                    <td>
                                        <span class="badge bg-danger"><?php echo $user['lost_count']; ?> Lost</span>
                                        <span class="badge bg-success"><?php echo $user['found_count']; ?> Found</span>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo $user['role'] == 'admin' ? 'primary' : 'secondary'; ?>">
                                            <?php echo $user['role'] == 'admin' ? 'Admin' : 'User'; ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                                    <td>
                                        <div class="btn-group">
                                            <a href="view_user.php?id=<?php echo $user['id']; ?>" class="btn btn-sm btn-outline-primary" title="View User">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="edit_user.php?id=<?php echo $user['id']; ?>" class="btn btn-sm btn-outline-secondary" title="Edit User">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                                <a href="manage_users.php?toggle_admin=<?php echo $user['id']; ?>" class="btn btn-sm btn-outline-<?php echo $user['role'] == 'admin' ? 'warning' : 'info'; ?>" title="<?php echo $user['role'] == 'admin' ? 'Remove Admin' : 'Make Admin'; ?>">
                                                    <i class="fas fa-<?php echo $user['role'] == 'admin' ? 'user' : 'user-shield'; ?>"></i>
                                                </a>
                                                <a href="javascript:void(0)" onclick="confirmDelete('manage_users.php?delete=<?php echo $user['id']; ?>', '<?php echo htmlspecialchars($user['username']); ?>')" class="btn btn-sm btn-outline-danger" title="Delete User">
                                                    <i class="fas fa-trash-alt"></i>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php if ($totalPages > 1): ?>
            <div class="card-footer bg-white">
                <nav>
                    <ul class="pagination justify-content-center mb-0">
                        <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $page - 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                        </li>
                        
                        <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                            <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                        
                        <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $page + 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>">
                                <i class="fas fa-chevron-right"></i>
                            </a>
                        </li>
                    </ul>
                </nav>
            </div>
        <?php endif; ?>
    </div>
    
    <div class="row">
        <div class="col-12 mb-4">
            <div class="card shadow-sm">
                <div class="card-header bg-white py-3">
                    <h5 class="card-title mb-0">User Statistics</h5>
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-md-4 mb-3">
                            <div class="stat-item">
                                <h2 class="text-primary"><?php echo $totalUsers; ?></h2>
                                <p class="mb-0">Total Users</p>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="stat-item">
                                <h2 class="text-info">
                                    <?php 
                                    $adminCount = 0;
                                    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM users WHERE role = 'admin'");
                                    $stmt->execute();
                                    $result = $stmt->get_result();
                                    if ($row = $result->fetch_assoc()) {
                                        $adminCount = $row['count'];
                                    }
                                    echo $adminCount;
                                    ?>
                                </h2>
                                <p class="mb-0">Admins</p>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="stat-item">
                                <h2 class="text-success">
                                    <?php 
                                    $newUsers = 0;
                                    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
                                    $stmt->execute();
                                    $result = $stmt->get_result();
                                    if ($row = $result->fetch_assoc()) {
                                        $newUsers = $row['count'];
                                    }
                                    echo $newUsers;
                                    ?>
                                </h2>
                                <p class="mb-0">New This Week</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php 
// Close the database connection before including footer
$conn->close();

// Include admin footer
include 'includes/admin_footer.php';
?>