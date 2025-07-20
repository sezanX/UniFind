<?php
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

// Determine which type of items to display
$type = isset($_GET['type']) && in_array($_GET['type'], ['lost', 'found']) ? $_GET['type'] : 'lost';
$pageTitle = ucfirst($type) . ' Items';

// Process item actions
$message = '';
$messageType = '';

// Delete item
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $itemId = $_GET['delete'];
    $table = $type . '_items';
    
    // Get item details first (for image deletion)
    $stmt = $conn->prepare("SELECT image FROM $table WHERE id = ?");
    $stmt->bind_param("i", $itemId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $item = $result->fetch_assoc();
        
        // Delete related matches
        if ($type === 'lost') {
            $stmt = $conn->prepare("DELETE FROM matches WHERE lost_item_id = ?");
        } else {
            $stmt = $conn->prepare("DELETE FROM matches WHERE found_item_id = ?");
        }
        $stmt->bind_param("i", $itemId);
        $stmt->execute();
        
        // Delete the item
        $stmt = $conn->prepare("DELETE FROM $table WHERE id = ?");
        $stmt->bind_param("i", $itemId);
        $stmt->execute();
        
        if ($stmt->affected_rows > 0) {
            // Delete the image file if it exists
            if (!empty($item['image'])) {
                $imagePath = "../uploads/" . $item['image'];
                if (file_exists($imagePath)) {
                    unlink($imagePath);
                }
            }
            
            $message = ucfirst($type) . ' item deleted successfully!';
            $messageType = 'success';
        } else {
            $message = 'Failed to delete ' . $type . ' item!';
            $messageType = 'danger';
        }
    } else {
        $message = ucfirst($type) . ' item not found!';
        $messageType = 'danger';
    }
}

// Update item status
if (isset($_GET['status']) && isset($_GET['id']) && is_numeric($_GET['id'])) {
    $itemId = $_GET['id'];
    $status = $_GET['status'];
    $table = $type . '_items';
    
    if (in_array($status, ['active', 'pending_match', 'matched', 'returned', 'archived'])) {
        $stmt = $conn->prepare("UPDATE $table SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $status, $itemId);
        $stmt->execute();
        
        if ($stmt->affected_rows > 0) {
            $message = ucfirst($type) . ' item status updated successfully!';
            $messageType = 'success';
        } else {
            $message = 'Failed to update ' . $type . ' item status!';
            $messageType = 'danger';
        }
    } else {
        $message = 'Invalid status!';
        $messageType = 'danger';
    }
}

// Get all items with pagination and filtering
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Search and filter functionality
$search = isset($_GET['search']) ? $_GET['search'] : '';
$categoryFilter = isset($_GET['category']) ? (int)$_GET['category'] : 0;
$departmentFilter = isset($_GET['department']) ? (int)$_GET['department'] : 0;
$statusFilter = isset($_GET['status_filter']) ? $_GET['status_filter'] : '';
$dateFilter = isset($_GET['date_filter']) ? $_GET['date_filter'] : '';

$conditions = [];
$params = [];
$types = '';

if (!empty($search)) {
    $conditions[] = "(i.title LIKE ? OR i.description LIKE ? OR i.location LIKE ?)"; 
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $types .= 'sss';
}

if ($categoryFilter > 0) {
    $conditions[] = "i.category_id = ?";
    $params[] = $categoryFilter;
    $types .= 'i';
}

if ($departmentFilter > 0) {
    $conditions[] = "u.department = ?";
    $params[] = $departmentFilter;
    $types .= 'i';
}

if (!empty($statusFilter)) {
    $conditions[] = "i.status = ?";
    $params[] = $statusFilter;
    $types .= 's';
}

if (!empty($dateFilter)) {
    switch ($dateFilter) {
        case 'today':
            $conditions[] = "DATE(i.date_reported) = CURDATE()";
            break;
        case 'yesterday':
            $conditions[] = "DATE(i.date_reported) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)";
            break;
        case 'this_week':
            $conditions[] = "i.date_reported >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
            break;
        case 'this_month':
            $conditions[] = "MONTH(i.date_reported) = MONTH(CURDATE()) AND YEAR(i.date_reported) = YEAR(CURDATE())";
            break;
    }
}

$whereClause = !empty($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";

// Count total items for pagination
$table = $type . '_items';
$countSql = "SELECT COUNT(*) as total FROM $table i 
            LEFT JOIN users u ON i.user_id = u.id 
            $whereClause";

$totalItems = 0;
if (!empty($params)) {
    $stmt = $conn->prepare($countSql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $countResult = $stmt->get_result();
    $totalItems = $countResult->fetch_assoc()['total'];
} else {
    $countResult = $conn->query($countSql);
    $totalItems = $countResult->fetch_assoc()['total'];
}

$totalPages = ceil($totalItems / $limit);

// Get items for current page
$items = [];
$sql = "SELECT i.*, c.name as category_name, u.full_name, u.email, u.phone, d.name as department_name 
        FROM $table i 
        LEFT JOIN categories c ON i.category_id = c.id 
        LEFT JOIN users u ON i.user_id = u.id 
        LEFT JOIN departments d ON u.department = d.id 
        $whereClause 
        ORDER BY i.date_reported DESC 
        LIMIT ? OFFSET ?";

if (!empty($params)) {
    $stmt = $conn->prepare($sql);
    $types .= 'ii';
    $params[] = $limit;
    $params[] = $offset;
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
    $items[] = $row;
}

// Get categories for filter dropdown
$categories = [];
$sql = "SELECT * FROM categories ORDER BY name";
$result = $conn->query($sql);
while ($row = $result->fetch_assoc()) {
    $categories[] = $row;
}

// Get departments for filter dropdown
$departments = [];
$sql = "SELECT * FROM departments ORDER BY name";
$result = $conn->query($sql);
while ($row = $result->fetch_assoc()) {
    $departments[] = $row;
}

// Include admin header
include 'includes/admin_header.php';
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0 text-gray-800">Manage <?php echo ucfirst($type); ?> Items</h1>
        <div>
            <a href="manage_items.php?type=lost" class="btn btn-<?php echo $type === 'lost' ? 'danger' : 'outline-danger'; ?> me-2">
                <i class="fas fa-search me-1"></i> Lost Items
            </a>
            <a href="manage_items.php?type=found" class="btn btn-<?php echo $type === 'found' ? 'success' : 'outline-success'; ?>">
                <i class="fas fa-hand-holding me-1"></i> Found Items
            </a>
        </div>
    </div>
    
    <?php if (!empty($message)): ?>
        <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
            <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white py-3">
            <form action="" method="GET" class="row g-3 align-items-end">
                <input type="hidden" name="type" value="<?php echo $type; ?>">
                
                <div class="col-md-3">
                    <label for="search" class="form-label">Search</label>
                    <input type="text" name="search" id="search" class="form-control" placeholder="Search items..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                
                <div class="col-md-2">
                    <label for="category" class="form-label">Category</label>
                    <select name="category" id="category" class="form-select">
                        <option value="0">All Categories</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?php echo $category['id']; ?>" <?php echo $categoryFilter == $category['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($category['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-2">
                    <label for="department" class="form-label">Department</label>
                    <select name="department" id="department" class="form-select">
                        <option value="0">All Departments</option>
                        <?php foreach ($departments as $department): ?>
                            <option value="<?php echo $department['id']; ?>" <?php echo $departmentFilter == $department['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($department['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-2">
                    <label for="status_filter" class="form-label">Status</label>
                    <select name="status_filter" id="status_filter" class="form-select">
                        <option value="">All Statuses</option>
                        <option value="active" <?php echo $statusFilter === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="pending_match" <?php echo $statusFilter === 'pending_match' ? 'selected' : ''; ?>>Pending Match</option>
                        <option value="matched" <?php echo $statusFilter === 'matched' ? 'selected' : ''; ?>>Matched</option>
                        <option value="returned" <?php echo $statusFilter === 'returned' ? 'selected' : ''; ?>>Returned</option>
                        <option value="archived" <?php echo $statusFilter === 'archived' ? 'selected' : ''; ?>>Archived</option>
                    </select>
                </div>
                
                <div class="col-md-2">
                    <label for="date_filter" class="form-label">Date</label>
                    <select name="date_filter" id="date_filter" class="form-select">
                        <option value="">All Time</option>
                        <option value="today" <?php echo $dateFilter === 'today' ? 'selected' : ''; ?>>Today</option>
                        <option value="yesterday" <?php echo $dateFilter === 'yesterday' ? 'selected' : ''; ?>>Yesterday</option>
                        <option value="this_week" <?php echo $dateFilter === 'this_week' ? 'selected' : ''; ?>>This Week</option>
                        <option value="this_month" <?php echo $dateFilter === 'this_month' ? 'selected' : ''; ?>>This Month</option>
                    </select>
                </div>
                
                <div class="col-md-1">
                    <button type="submit" class="btn btn-primary w-100">Filter</button>
                </div>
            </form>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Image</th>
                            <th>Title</th>
                            <th>Category</th>
                            <th>Date <?php echo $type === 'lost' ? 'Lost' : 'Found'; ?></th>
                            <th>Location</th>
                            <th>Reported By</th>
                            <th>Status</th>
                            <th>Date Reported</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($items)): ?>
                            <tr>
                                <td colspan="10" class="text-center py-3">
                                    <?php echo empty($search) && $categoryFilter == 0 && $departmentFilter == 0 && empty($statusFilter) && empty($dateFilter) ? 
                                        'No ' . $type . ' items found' : 
                                        'No ' . $type . ' items matching your filters'; ?>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($items as $item): ?>
                                <tr>
                                    <td><?php echo $item['id']; ?></td>
                                    <td>
                                        <?php if (!empty($item['image'])): ?>
                                            <img src="../uploads/<?php echo $item['image']; ?>" alt="Item Image" class="img-thumbnail" style="width: 50px; height: 50px; object-fit: cover;">
                                        <?php else: ?>
                                            <div class="bg-light d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
                                                <i class="fas fa-image text-muted"></i>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($item['title']); ?></td>
                                    <td><?php echo htmlspecialchars($item['category_name']); ?></td>
                                    <td><?php echo formatDate($item[$type === 'lost' ? 'date_lost' : 'date_found']); ?></td>
                                    <td><?php echo htmlspecialchars($item['location']); ?></td>
                                    <td>
                                        <a href="view_user.php?id=<?php echo $item['user_id']; ?>" class="text-decoration-none">
                                            <?php echo htmlspecialchars($item['full_name']); ?>
                                        </a>
                                        <small class="d-block text-muted"><?php echo htmlspecialchars($item['department_name'] ?? 'N/A'); ?></small>
                                    </td>
                                    <td>
                                        <div class="dropdown">
                                            <button class="btn btn-sm dropdown-toggle status-dropdown" type="button" id="statusDropdown<?php echo $item['id']; ?>" data-bs-toggle="dropdown" aria-expanded="false">
                                                <span class="badge bg-<?php 
                                                    echo $item['status'] === 'active' ? 'success' : 
                                                        ($item['status'] === 'pending_match' ? 'warning' : 
                                                            ($item['status'] === 'matched' ? 'info' : 
                                                                ($item['status'] === 'returned' ? 'primary' : 'secondary'))); 
                                                ?>">
                                                    <?php echo ucfirst(str_replace('_', ' ', $item['status'])); ?>
                                                </span>
                                            </button>
                                            <ul class="dropdown-menu" aria-labelledby="statusDropdown<?php echo $item['id']; ?>">
                                                <li><a class="dropdown-item" href="?type=<?php echo $type; ?>&id=<?php echo $item['id']; ?>&status=active<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo $categoryFilter > 0 ? '&category=' . $categoryFilter : ''; ?><?php echo $departmentFilter > 0 ? '&department=' . $departmentFilter : ''; ?><?php echo !empty($statusFilter) ? '&status_filter=' . $statusFilter : ''; ?><?php echo !empty($dateFilter) ? '&date_filter=' . $dateFilter : ''; ?><?php echo $page > 1 ? '&page=' . $page : ''; ?>">Active</a></li>
                                                <li><a class="dropdown-item" href="?type=<?php echo $type; ?>&id=<?php echo $item['id']; ?>&status=pending_match<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo $categoryFilter > 0 ? '&category=' . $categoryFilter : ''; ?><?php echo $departmentFilter > 0 ? '&department=' . $departmentFilter : ''; ?><?php echo !empty($statusFilter) ? '&status_filter=' . $statusFilter : ''; ?><?php echo !empty($dateFilter) ? '&date_filter=' . $dateFilter : ''; ?><?php echo $page > 1 ? '&page=' . $page : ''; ?>">Pending Match</a></li>
                                                <li><a class="dropdown-item" href="?type=<?php echo $type; ?>&id=<?php echo $item['id']; ?>&status=matched<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo $categoryFilter > 0 ? '&category=' . $categoryFilter : ''; ?><?php echo $departmentFilter > 0 ? '&department=' . $departmentFilter : ''; ?><?php echo !empty($statusFilter) ? '&status_filter=' . $statusFilter : ''; ?><?php echo !empty($dateFilter) ? '&date_filter=' . $dateFilter : ''; ?><?php echo $page > 1 ? '&page=' . $page : ''; ?>">Matched</a></li>
                                                <li><a class="dropdown-item" href="?type=<?php echo $type; ?>&id=<?php echo $item['id']; ?>&status=returned<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo $categoryFilter > 0 ? '&category=' . $categoryFilter : ''; ?><?php echo $departmentFilter > 0 ? '&department=' . $departmentFilter : ''; ?><?php echo !empty($statusFilter) ? '&status_filter=' . $statusFilter : ''; ?><?php echo !empty($dateFilter) ? '&date_filter=' . $dateFilter : ''; ?><?php echo $page > 1 ? '&page=' . $page : ''; ?>">Returned</a></li>
                                                <li><a class="dropdown-item" href="?type=<?php echo $type; ?>&id=<?php echo $item['id']; ?>&status=archived<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo $categoryFilter > 0 ? '&category=' . $categoryFilter : ''; ?><?php echo $departmentFilter > 0 ? '&department=' . $departmentFilter : ''; ?><?php echo !empty($statusFilter) ? '&status_filter=' . $statusFilter : ''; ?><?php echo !empty($dateFilter) ? '&date_filter=' . $dateFilter : ''; ?><?php echo $page > 1 ? '&page=' . $page : ''; ?>">Archived</a></li>
                                            </ul>
                                        </div>
                                    </td>
                                    <td><?php echo formatDate($item['date_reported']); ?></td>
                                    <td>
                                        <div class="btn-group">
                                            <a href="../<?php echo $type; ?>_item.php?id=<?php echo $item['id']; ?>" class="btn btn-sm btn-outline-primary" title="View Item" target="_blank">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="edit_item.php?type=<?php echo $type; ?>&id=<?php echo $item['id']; ?>" class="btn btn-sm btn-outline-secondary" title="Edit Item">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="javascript:void(0)" onclick="confirmDelete('manage_items.php?type=<?php echo $type; ?>&delete=<?php echo $item['id']; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo $categoryFilter > 0 ? '&category=' . $categoryFilter : ''; ?><?php echo $departmentFilter > 0 ? '&department=' . $departmentFilter : ''; ?><?php echo !empty($statusFilter) ? '&status_filter=' . $statusFilter : ''; ?><?php echo !empty($dateFilter) ? '&date_filter=' . $dateFilter : ''; ?><?php echo $page > 1 ? '&page=' . $page : ''; ?>', '<?php echo htmlspecialchars($item['title']); ?>')" class="btn btn-sm btn-outline-danger" title="Delete Item">
                                                <i class="fas fa-trash-alt"></i>
                                            </a>
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
                            <a class="page-link" href="?type=<?php echo $type; ?>&page=<?php echo $page - 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo $categoryFilter > 0 ? '&category=' . $categoryFilter : ''; ?><?php echo $departmentFilter > 0 ? '&department=' . $departmentFilter : ''; ?><?php echo !empty($statusFilter) ? '&status_filter=' . $statusFilter : ''; ?><?php echo !empty($dateFilter) ? '&date_filter=' . $dateFilter : ''; ?>">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                        </li>
                        
                        <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                            <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                <a class="page-link" href="?type=<?php echo $type; ?>&page=<?php echo $i; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo $categoryFilter > 0 ? '&category=' . $categoryFilter : ''; ?><?php echo $departmentFilter > 0 ? '&department=' . $departmentFilter : ''; ?><?php echo !empty($statusFilter) ? '&status_filter=' . $statusFilter : ''; ?><?php echo !empty($dateFilter) ? '&date_filter=' . $dateFilter : ''; ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                        
                        <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?type=<?php echo $type; ?>&page=<?php echo $page + 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo $categoryFilter > 0 ? '&category=' . $categoryFilter : ''; ?><?php echo $departmentFilter > 0 ? '&department=' . $departmentFilter : ''; ?><?php echo !empty($statusFilter) ? '&status_filter=' . $statusFilter : ''; ?><?php echo !empty($dateFilter) ? '&date_filter=' . $dateFilter : ''; ?>">
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
                    <h5 class="card-title mb-0"><?php echo ucfirst($type); ?> Item Statistics</h5>
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-md-4 mb-3">
                            <div class="stat-item">
                                <h2 class="text-<?php echo $type === 'lost' ? 'danger' : 'success'; ?>"><?php echo $totalItems; ?></h2>
                                <p class="mb-0">Total <?php echo ucfirst($type); ?> Items</p>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="stat-item">
                                <h2 class="text-warning">
                                    <?php 
                                    $pendingCount = 0;
                                    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM {$type}_items WHERE status = 'pending_match'");
                                    $stmt->execute();
                                    $result = $stmt->get_result();
                                    if ($row = $result->fetch_assoc()) {
                                        $pendingCount = $row['count'];
                                    }
                                    echo $pendingCount;
                                    ?>
                                </h2>
                                <p class="mb-0">Pending Matches</p>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="stat-item">
                                <h2 class="text-info">
                                    <?php 
                                    $matchedCount = 0;
                                    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM {$type}_items WHERE status = 'matched'");
                                    $stmt->execute();
                                    $result = $stmt->get_result();
                                    if ($row = $result->fetch_assoc()) {
                                        $matchedCount = $row['count'];
                                    }
                                    echo $matchedCount;
                                    ?>
                                </h2>
                                <p class="mb-0">Matched</p>
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