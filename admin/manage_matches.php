<?php
$pageTitle = 'Manage Matches';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Redirect if not admin
if (!isAdmin()) {
    header('Location: ../login.php');
    exit;
}

// Use the global database connection from includes/db.php
global $conn;

// Process match actions
$message = '';
$messageType = '';

// Delete match
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $matchId = $_GET['delete'];
    
    // Get match details first
    $stmt = $conn->prepare("SELECT * FROM matches WHERE id = ?");
    $stmt->bind_param("i", $matchId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $match = $result->fetch_assoc();
        
        // Update lost item status
        $stmt = $conn->prepare("UPDATE lost_items SET status = 'active' WHERE id = ?");
        $stmt->bind_param("i", $match['lost_item_id']);
        $stmt->execute();
        
        // Update found item status
        $stmt = $conn->prepare("UPDATE found_items SET status = 'active' WHERE id = ?");
        $stmt->bind_param("i", $match['found_item_id']);
        $stmt->execute();
        
        // Delete the match
        $stmt = $conn->prepare("DELETE FROM matches WHERE id = ?");
        $stmt->bind_param("i", $matchId);
        $stmt->execute();
        
        if ($stmt->affected_rows > 0) {
            $message = 'Match deleted successfully!';
            $messageType = 'success';
        } else {
            $message = 'Failed to delete match!';
            $messageType = 'danger';
        }
    } else {
        $message = 'Match not found!';
        $messageType = 'danger';
    }
}

// Update match status
if (isset($_GET['status']) && isset($_GET['id']) && is_numeric($_GET['id'])) {
    $matchId = $_GET['id'];
    $status = $_GET['status'];
    
    if (in_array($status, ['pending_review', 'confirmed', 'completed', 'rejected'])) {
        // Get match details first
        $stmt = $conn->prepare("SELECT * FROM matches WHERE id = ?");
        $stmt->bind_param("i", $matchId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $match = $result->fetch_assoc();
            
            // Update match status
            $stmt = $conn->prepare("UPDATE matches SET status = ? WHERE id = ?");
            $stmt->bind_param("si", $status, $matchId);
            $stmt->execute();
            
            // Update item statuses based on match status
            $lostItemStatus = 'pending_match';
            $foundItemStatus = 'pending_match';
            
            if ($status === 'confirmed') {
                $lostItemStatus = 'matched';
                $foundItemStatus = 'matched';
            } else if ($status === 'completed') {
                $lostItemStatus = 'returned';
                $foundItemStatus = 'returned';
            } else if ($status === 'rejected') {
                $lostItemStatus = 'active';
                $foundItemStatus = 'active';
            }
            
            // Update lost item status
            $stmt = $conn->prepare("UPDATE lost_items SET status = ? WHERE id = ?");
            $stmt->bind_param("si", $lostItemStatus, $match['lost_item_id']);
            $stmt->execute();
            
            // Update found item status
            $stmt = $conn->prepare("UPDATE found_items SET status = ? WHERE id = ?");
            $stmt->bind_param("si", $foundItemStatus, $match['found_item_id']);
            $stmt->execute();
            
            $message = 'Match status updated successfully!';
            $messageType = 'success';
        } else {
            $message = 'Match not found!';
            $messageType = 'danger';
        }
    } else {
        $message = 'Invalid status!';
        $messageType = 'danger';
    }
}

// Get all matches with pagination and filtering
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Filter functionality
$statusFilter = isset($_GET['status_filter']) ? $_GET['status_filter'] : '';
$categoryFilter = isset($_GET['category']) ? (int)$_GET['category'] : 0;
$dateFilter = isset($_GET['date_filter']) ? $_GET['date_filter'] : '';

$conditions = [];
$params = [];
$types = '';

if (!empty($statusFilter)) {
    $conditions[] = "m.status = ?";
    $params[] = $statusFilter;
    $types .= 's';
}

if ($categoryFilter > 0) {
    $conditions[] = "l.category_id = ?";
    $params[] = $categoryFilter;
    $types .= 'i';
}

if (!empty($dateFilter)) {
    switch ($dateFilter) {
        case 'today':
            $conditions[] = "DATE(m.created_at) = CURDATE()";
            break;
        case 'yesterday':
            $conditions[] = "DATE(m.created_at) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)";
            break;
        case 'this_week':
            $conditions[] = "m.created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
            break;
        case 'this_month':
            $conditions[] = "MONTH(m.created_at) = MONTH(CURDATE()) AND YEAR(m.created_at) = YEAR(CURDATE())";
            break;
    }
}

$whereClause = !empty($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";

// Count total matches for pagination
$countSql = "SELECT COUNT(*) as total FROM matches m 
            LEFT JOIN lost_items l ON m.lost_item_id = l.id 
            LEFT JOIN found_items f ON m.found_item_id = f.id 
            $whereClause";

$totalMatches = 0;
if (!empty($params)) {
    $stmt = $conn->prepare($countSql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $countResult = $stmt->get_result();
    $totalMatches = $countResult->fetch_assoc()['total'];
} else {
    $countResult = $conn->query($countSql);
    $totalMatches = $countResult->fetch_assoc()['total'];
}

$totalPages = ceil($totalMatches / $limit);

// Get matches for current page
$matches = [];
$sql = "SELECT m.*, 
        l.title as lost_title, l.date_lost, l.location as lost_location, l.image as lost_image, 
        f.title as found_title, f.date_found, f.location as found_location, f.image as found_image, 
        ul.full_name as owner_name, ul.id as owner_id, 
        uf.full_name as finder_name, uf.id as finder_id, 
        c.name as category_name, c.id as category_id 
        FROM matches m 
        JOIN lost_items l ON m.lost_item_id = l.id 
        JOIN found_items f ON m.found_item_id = f.id 
        JOIN users ul ON l.user_id = ul.id 
        JOIN users uf ON f.user_id = uf.id 
        JOIN categories c ON l.category_id = c.id 
        $whereClause 
        ORDER BY m.created_at DESC 
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
    $matches[] = $row;
}

// Get categories for filter dropdown
$categories = [];
$sql = "SELECT * FROM categories ORDER BY name";
$result = $conn->query($sql);
while ($row = $result->fetch_assoc()) {
    $categories[] = $row;
}

// Include admin header
include 'includes/admin_header.php';
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0 text-gray-800">Manage Matches</h1>
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
                <div class="col-md-3">
                    <label for="status_filter" class="form-label">Status</label>
                    <select name="status_filter" id="status_filter" class="form-select">
                        <option value="">All Statuses</option>
                        <option value="pending_review" <?php echo $statusFilter === 'pending_review' ? 'selected' : ''; ?>>Pending Review</option>
                        <option value="confirmed" <?php echo $statusFilter === 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                        <option value="completed" <?php echo $statusFilter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                        <option value="rejected" <?php echo $statusFilter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                    </select>
                </div>
                
                <div class="col-md-3">
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
                
                <div class="col-md-3">
                    <label for="date_filter" class="form-label">Date</label>
                    <select name="date_filter" id="date_filter" class="form-select">
                        <option value="">All Time</option>
                        <option value="today" <?php echo $dateFilter === 'today' ? 'selected' : ''; ?>>Today</option>
                        <option value="yesterday" <?php echo $dateFilter === 'yesterday' ? 'selected' : ''; ?>>Yesterday</option>
                        <option value="this_week" <?php echo $dateFilter === 'this_week' ? 'selected' : ''; ?>>This Week</option>
                        <option value="this_month" <?php echo $dateFilter === 'this_month' ? 'selected' : ''; ?>>This Month</option>
                    </select>
                </div>
                
                <div class="col-md-3">
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
                            <th>Lost Item</th>
                            <th>Found Item</th>
                            <th>Category</th>
                            <th>Owner</th>
                            <th>Finder</th>
                            <th>Status</th>
                            <th>Date Matched</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($matches)): ?>
                            <tr>
                                <td colspan="9" class="text-center py-3">
                                    <?php echo empty($statusFilter) && $categoryFilter == 0 && empty($dateFilter) ? 
                                        'No matches found' : 
                                        'No matches matching your filters'; ?>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($matches as $match): ?>
                                <tr>
                                    <td><?php echo $match['id']; ?></td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <?php if (!empty($match['lost_image'])): ?>
                                                <img src="../uploads/<?php echo $match['lost_image']; ?>" alt="Lost Item" class="img-thumbnail me-2" style="width: 40px; height: 40px; object-fit: cover;">
                                            <?php else: ?>
                                                <div class="bg-light d-flex align-items-center justify-content-center me-2" style="width: 40px; height: 40px;">
                                                    <i class="fas fa-search text-muted"></i>
                                                </div>
                                            <?php endif; ?>
                                            <div>
                                                <a href="../lost_item.php?id=<?php echo $match['lost_item_id']; ?>" target="_blank" class="text-decoration-none">
                                                    <?php echo htmlspecialchars($match['lost_title']); ?>
                                                </a>
                                                <small class="d-block text-muted">
                                                    <i class="fas fa-map-marker-alt me-1"></i><?php echo htmlspecialchars($match['lost_location']); ?>
                                                </small>
                                                <small class="d-block text-muted">
                                                    <i class="fas fa-calendar-alt me-1"></i><?php echo formatDate($match['date_lost']); ?>
                                                </small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <?php if (!empty($match['found_image'])): ?>
                                                <img src="../uploads/<?php echo $match['found_image']; ?>" alt="Found Item" class="img-thumbnail me-2" style="width: 40px; height: 40px; object-fit: cover;">
                                            <?php else: ?>
                                                <div class="bg-light d-flex align-items-center justify-content-center me-2" style="width: 40px; height: 40px;">
                                                    <i class="fas fa-hand-holding text-muted"></i>
                                                </div>
                                            <?php endif; ?>
                                            <div>
                                                <a href="../found_item.php?id=<?php echo $match['found_item_id']; ?>" target="_blank" class="text-decoration-none">
                                                    <?php echo htmlspecialchars($match['found_title']); ?>
                                                </a>
                                                <small class="d-block text-muted">
                                                    <i class="fas fa-map-marker-alt me-1"></i><?php echo htmlspecialchars($match['found_location']); ?>
                                                </small>
                                                <small class="d-block text-muted">
                                                    <i class="fas fa-calendar-alt me-1"></i><?php echo formatDate($match['date_found']); ?>
                                                </small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary"><?php echo htmlspecialchars($match['category_name']); ?></span>
                                    </td>
                                    <td>
                                        <a href="view_user.php?id=<?php echo $match['owner_id']; ?>" class="text-decoration-none">
                                            <?php echo htmlspecialchars($match['owner_name']); ?>
                                        </a>
                                    </td>
                                    <td>
                                        <a href="view_user.php?id=<?php echo $match['finder_id']; ?>" class="text-decoration-none">
                                            <?php echo htmlspecialchars($match['finder_name']); ?>
                                        </a>
                                    </td>
                                    <td>
                                        <div class="dropdown">
                                            <button class="btn btn-sm dropdown-toggle status-dropdown" type="button" id="statusDropdown<?php echo $match['id']; ?>" data-bs-toggle="dropdown" aria-expanded="false">
                                                <span class="badge bg-<?php 
                                                    echo $match['status'] === 'pending_review' ? 'warning' : 
                                                        ($match['status'] === 'confirmed' ? 'info' : 
                                                            ($match['status'] === 'completed' ? 'success' : 'danger')); 
                                                ?>">
                                                    <?php echo ucfirst(str_replace('_', ' ', $match['status'])); ?>
                                                </span>
                                            </button>
                                            <ul class="dropdown-menu" aria-labelledby="statusDropdown<?php echo $match['id']; ?>">
                                                <li><a class="dropdown-item" href="?id=<?php echo $match['id']; ?>&status=pending_review<?php echo !empty($statusFilter) ? '&status_filter=' . $statusFilter : ''; ?><?php echo $categoryFilter > 0 ? '&category=' . $categoryFilter : ''; ?><?php echo !empty($dateFilter) ? '&date_filter=' . $dateFilter : ''; ?><?php echo $page > 1 ? '&page=' . $page : ''; ?>">Pending Review</a></li>
                                                <li><a class="dropdown-item" href="?id=<?php echo $match['id']; ?>&status=confirmed<?php echo !empty($statusFilter) ? '&status_filter=' . $statusFilter : ''; ?><?php echo $categoryFilter > 0 ? '&category=' . $categoryFilter : ''; ?><?php echo !empty($dateFilter) ? '&date_filter=' . $dateFilter : ''; ?><?php echo $page > 1 ? '&page=' . $page : ''; ?>">Confirm Match</a></li>
                                                <li><a class="dropdown-item" href="?id=<?php echo $match['id']; ?>&status=completed<?php echo !empty($statusFilter) ? '&status_filter=' . $statusFilter : ''; ?><?php echo $categoryFilter > 0 ? '&category=' . $categoryFilter : ''; ?><?php echo !empty($dateFilter) ? '&date_filter=' . $dateFilter : ''; ?><?php echo $page > 1 ? '&page=' . $page : ''; ?>">Mark as Completed</a></li>
                                                <li><a class="dropdown-item" href="?id=<?php echo $match['id']; ?>&status=rejected<?php echo !empty($statusFilter) ? '&status_filter=' . $statusFilter : ''; ?><?php echo $categoryFilter > 0 ? '&category=' . $categoryFilter : ''; ?><?php echo !empty($dateFilter) ? '&date_filter=' . $dateFilter : ''; ?><?php echo $page > 1 ? '&page=' . $page : ''; ?>">Reject Match</a></li>
                                            </ul>
                                        </div>
                                    </td>
                                    <td><?php echo formatDate($match['created_at']); ?></td>
                                    <td>
                                        <div class="btn-group">
                                            <a href="review_match.php?id=<?php echo $match['id']; ?>" class="btn btn-sm btn-outline-primary" title="Review Match">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="javascript:void(0)" onclick="confirmDelete('manage_matches.php?delete=<?php echo $match['id']; ?><?php echo !empty($statusFilter) ? '&status_filter=' . $statusFilter : ''; ?><?php echo $categoryFilter > 0 ? '&category=' . $categoryFilter : ''; ?><?php echo !empty($dateFilter) ? '&date_filter=' . $dateFilter : ''; ?><?php echo $page > 1 ? '&page=' . $page : ''; ?>', 'Match #<?php echo $match['id']; ?>')" class="btn btn-sm btn-outline-danger" title="Delete Match">
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
                            <a class="page-link" href="?page=<?php echo $page - 1; ?><?php echo !empty($statusFilter) ? '&status_filter=' . $statusFilter : ''; ?><?php echo $categoryFilter > 0 ? '&category=' . $categoryFilter : ''; ?><?php echo !empty($dateFilter) ? '&date_filter=' . $dateFilter : ''; ?>">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                        </li>
                        
                        <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                            <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?><?php echo !empty($statusFilter) ? '&status_filter=' . $statusFilter : ''; ?><?php echo $categoryFilter > 0 ? '&category=' . $categoryFilter : ''; ?><?php echo !empty($dateFilter) ? '&date_filter=' . $dateFilter : ''; ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                        
                        <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $page + 1; ?><?php echo !empty($statusFilter) ? '&status_filter=' . $statusFilter : ''; ?><?php echo $categoryFilter > 0 ? '&category=' . $categoryFilter : ''; ?><?php echo !empty($dateFilter) ? '&date_filter=' . $dateFilter : ''; ?>">
                                <i class="fas fa-chevron-right"></i>
                            </a>
                        </li>
                    </ul>
                </nav>
            </div>
        <?php endif; ?>
    </div>
    
    <div class="row">
        <div class="col-md-6 mb-4">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-white py-3">
                    <h5 class="card-title mb-0">Match Statistics</h5>
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-md-3 mb-3">
                            <div class="stat-item">
                                <h2 class="text-primary"><?php echo $totalMatches; ?></h2>
                                <p class="mb-0">Total Matches</p>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="stat-item">
                                <h2 class="text-warning">
                                    <?php 
                                    $pendingCount = 0;
                                    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM matches WHERE status = 'pending_review'");
                                    $stmt->execute();
                                    $result = $stmt->get_result();
                                    if ($row = $result->fetch_assoc()) {
                                        $pendingCount = $row['count'];
                                    }
                                    echo $pendingCount;
                                    ?>
                                </h2>
                                <p class="mb-0">Pending</p>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="stat-item">
                                <h2 class="text-info">
                                    <?php 
                                    $confirmedCount = 0;
                                    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM matches WHERE status = 'confirmed'");
                                    $stmt->execute();
                                    $result = $stmt->get_result();
                                    if ($row = $result->fetch_assoc()) {
                                        $confirmedCount = $row['count'];
                                    }
                                    echo $confirmedCount;
                                    ?>
                                </h2>
                                <p class="mb-0">Confirmed</p>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="stat-item">
                                <h2 class="text-success">
                                    <?php 
                                    $completedCount = 0;
                                    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM matches WHERE status = 'completed'");
                                    $stmt->execute();
                                    $result = $stmt->get_result();
                                    if ($row = $result->fetch_assoc()) {
                                        $completedCount = $row['count'];
                                    }
                                    echo $completedCount;
                                    ?>
                                </h2>
                                <p class="mb-0">Completed</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-6 mb-4">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-white py-3">
                    <h5 class="card-title mb-0">Recent Activity</h5>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        <?php 
                        $recentActivity = [];
                        $sql = "SELECT m.id, m.status, m.created_at, m.updated_at, 
                                l.title as lost_title, f.title as found_title, 
                                ul.full_name as owner_name, uf.full_name as finder_name 
                                FROM matches m 
                                JOIN lost_items l ON m.lost_item_id = l.id 
                                JOIN found_items f ON m.found_item_id = f.id 
                                JOIN users ul ON l.user_id = ul.id 
                                JOIN users uf ON f.user_id = uf.id 
                                ORDER BY m.updated_at DESC LIMIT 5";
                        $result = $conn->query($sql);
                        while ($row = $result->fetch_assoc()) {
                            $recentActivity[] = $row;
                        }
                        
                        if (empty($recentActivity)): 
                        ?>
                            <div class="list-group-item py-3 text-center">
                                <p class="mb-0 text-muted">No recent activity</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($recentActivity as $activity): ?>
                                <div class="list-group-item py-3">
                                    <div class="d-flex w-100 justify-content-between">
                                        <h6 class="mb-1">
                                            <a href="review_match.php?id=<?php echo $activity['id']; ?>" class="text-decoration-none">
                                                Match #<?php echo $activity['id']; ?>
                                            </a>
                                        </h6>
                                        <small class="text-muted"><?php echo timeElapsed($activity['updated_at']); ?></small>
                                    </div>
                                    <p class="mb-1">
                                        <span class="badge bg-<?php 
                                            echo $activity['status'] === 'pending_review' ? 'warning' : 
                                                ($activity['status'] === 'confirmed' ? 'info' : 
                                                    ($activity['status'] === 'completed' ? 'success' : 'danger')); 
                                        ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $activity['status'])); ?>
                                        </span>
                                        <span class="ms-2">
                                            <?php echo htmlspecialchars($activity['lost_title']); ?> (Lost) &
                                            <?php echo htmlspecialchars($activity['found_title']); ?> (Found)
                                        </span>
                                    </p>
                                    <small>
                                        Owner: <?php echo htmlspecialchars($activity['owner_name']); ?> | 
                                        Finder: <?php echo htmlspecialchars($activity['finder_name']); ?>
                                    </small>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php 
$conn->close();
include 'includes/admin_footer.php';
?>