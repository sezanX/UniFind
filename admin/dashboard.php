<?php
$pageTitle = 'Admin Dashboard';
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

// Get statistics
$stats = [
    'total_users' => 0,
    'total_lost' => 0,
    'total_found' => 0,
    'total_matched' => 0,
    'pending_matches' => 0,
    'today_lost' => 0,
    'today_found' => 0,
    'this_week_lost' => 0,
    'this_week_found' => 0
];

// Total users
$sql = "SELECT COUNT(*) as count FROM users";
$result = $conn->query($sql);
if ($result && $row = $result->fetch_assoc()) {
    $stats['total_users'] = $row['count'];
}

// Total lost items
$sql = "SELECT COUNT(*) as count FROM lost_items";
$result = $conn->query($sql);
if ($result && $row = $result->fetch_assoc()) {
    $stats['total_lost'] = $row['count'];
}

// Total found items
$sql = "SELECT COUNT(*) as count FROM found_items";
$result = $conn->query($sql);
if ($result && $row = $result->fetch_assoc()) {
    $stats['total_found'] = $row['count'];
}

// Total matched items
$sql = "SELECT COUNT(*) as count FROM matches";
$result = $conn->query($sql);
if ($result && $row = $result->fetch_assoc()) {
    $stats['total_matched'] = $row['count'];
}

// Pending matches
$sql = "SELECT COUNT(*) as count FROM matches WHERE status = 'pending_review'";
$result = $conn->query($sql);
if ($result && $row = $result->fetch_assoc()) {
    $stats['pending_matches'] = $row['count'];
}

// Today's lost items
$sql = "SELECT COUNT(*) as count FROM lost_items WHERE DATE(date_reported) = CURDATE()";
$result = $conn->query($sql);
if ($result && $row = $result->fetch_assoc()) {
    $stats['today_lost'] = $row['count'];
}

// Today's found items
$sql = "SELECT COUNT(*) as count FROM found_items WHERE DATE(date_reported) = CURDATE()";
$result = $conn->query($sql);
if ($result && $row = $result->fetch_assoc()) {
    $stats['today_found'] = $row['count'];
}

// This week's lost items
$sql = "SELECT COUNT(*) as count FROM lost_items WHERE date_reported >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
$result = $conn->query($sql);
if ($result && $row = $result->fetch_assoc()) {
    $stats['this_week_lost'] = $row['count'];
}

// This week's found items
$sql = "SELECT COUNT(*) as count FROM found_items WHERE date_reported >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
$result = $conn->query($sql);
if ($result && $row = $result->fetch_assoc()) {
    $stats['this_week_found'] = $row['count'];
}

// Get recent lost items
$recentLostItems = [];
$sql = "SELECT l.*, c.name as category_name, u.full_name 
        FROM lost_items l 
        JOIN categories c ON l.category_id = c.id 
        JOIN users u ON l.user_id = u.id 
        ORDER BY l.date_reported DESC LIMIT 5";
$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $recentLostItems[] = $row;
    }
}

// Get recent found items
$recentFoundItems = [];
$sql = "SELECT f.*, c.name as category_name, u.full_name 
        FROM found_items f 
        JOIN categories c ON f.category_id = c.id 
        JOIN users u ON f.user_id = u.id 
        ORDER BY f.date_reported DESC LIMIT 5";
$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $recentFoundItems[] = $row;
    }
}

// Get pending matches
$pendingMatches = [];
$sql = "SELECT m.*, 
        l.title as lost_title, l.date_lost, l.location as lost_location, 
        f.title as found_title, f.date_found, f.location as found_location, 
        ul.full_name as owner_name, uf.full_name as finder_name, 
        c.name as category_name 
        FROM matches m 
        JOIN lost_items l ON m.lost_item_id = l.id 
        JOIN found_items f ON m.found_item_id = f.id 
        JOIN users ul ON l.user_id = ul.id 
        JOIN users uf ON f.user_id = uf.id 
        JOIN categories c ON l.category_id = c.id 
        WHERE m.status = 'pending_review' 
        ORDER BY m.created_at DESC LIMIT 5";
$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $pendingMatches[] = $row;
    }
}





// Include admin header
include 'includes/admin_header.php';
?>

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-lg-3 col-md-6 mb-4">
            <div class="card stats-card bg-primary text-white h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title">Total Users</h6>
                            <h2 class="mb-0"><?php echo $stats['total_users']; ?></h2>
                        </div>
                        <div class="stats-icon">
                            <i class="fas fa-users fa-2x"></i>
                        </div>
                    </div>
                </div>
                <div class="card-footer d-flex justify-content-between align-items-center">
                    <span>View Details</span>
                    <a href="manage_users.php" class="text-white"><i class="fas fa-arrow-right"></i></a>
                </div>
            </div>
        </div>
        
        <div class="col-lg-3 col-md-6 mb-4">
            <div class="card stats-card bg-danger text-white h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title">Lost Items</h6>
                            <h2 class="mb-0"><?php echo $stats['total_lost']; ?></h2>
                        </div>
                        <div class="stats-icon">
                            <i class="fas fa-search fa-2x"></i>
                        </div>
                    </div>
                </div>
                <div class="card-footer d-flex justify-content-between align-items-center">
                    <span>View Details</span>
                    <a href="manage_items.php?type=lost" class="text-white"><i class="fas fa-arrow-right"></i></a>
                </div>
            </div>
        </div>
        
        <div class="col-lg-3 col-md-6 mb-4">
            <div class="card stats-card bg-success text-white h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title">Found Items</h6>
                            <h2 class="mb-0"><?php echo $stats['total_found']; ?></h2>
                        </div>
                        <div class="stats-icon">
                            <i class="fas fa-hand-holding fa-2x"></i>
                        </div>
                    </div>
                </div>
                <div class="card-footer d-flex justify-content-between align-items-center">
                    <span>View Details</span>
                    <a href="manage_items.php?type=found" class="text-white"><i class="fas fa-arrow-right"></i></a>
                </div>
            </div>
        </div>
        
        <div class="col-lg-3 col-md-6 mb-4">
            <div class="card stats-card bg-info text-white h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title">Matched Items</h6>
                            <h2 class="mb-0"><?php echo $stats['total_matched']; ?></h2>
                        </div>
                        <div class="stats-icon">
                            <i class="fas fa-exchange-alt fa-2x"></i>
                        </div>
                    </div>
                </div>
                <div class="card-footer d-flex justify-content-between align-items-center">
                    <span>View Details</span>
                    <a href="manage_matches.php" class="text-white"><i class="fas fa-arrow-right"></i></a>
                </div>
            </div>
        </div>
    </div>
    

    
    <div class="row">
        <div class="col-lg-6 mb-4">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Recent Lost Items</h5>
                    <a href="manage_items.php?type=lost" class="btn btn-sm btn-outline-primary">View All</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Title</th>
                                    <th>Category</th>
                                    <th>Date Lost</th>
                                    <th>Reported By</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($recentLostItems)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-3">No lost items found</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($recentLostItems as $item): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($item['title']); ?></td>
                                            <td><?php echo htmlspecialchars($item['category_name']); ?></td>
                                            <td><?php echo formatDate($item['date_lost']); ?></td>
                                            <td><?php echo htmlspecialchars($item['full_name']); ?></td>
                                            <td>
                                                <span class="badge bg-<?php 
                                                    echo $item['status'] === 'active' ? 'success' : 
                                                        ($item['status'] === 'pending_match' ? 'warning' : 
                                                            ($item['status'] === 'matched' ? 'info' : 'secondary')); 
                                                ?>">
                                                    <?php echo ucfirst(str_replace('_', ' ', $item['status'])); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <a href="../lost_item.php?id=<?php echo $item['id']; ?>" class="btn btn-sm btn-outline-primary" target="_blank">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-6 mb-4">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Recent Found Items</h5>
                    <a href="manage_items.php?type=found" class="btn btn-sm btn-outline-primary">View All</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Title</th>
                                    <th>Category</th>
                                    <th>Date Found</th>
                                    <th>Reported By</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($recentFoundItems)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-3">No found items found</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($recentFoundItems as $item): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($item['title']); ?></td>
                                            <td><?php echo htmlspecialchars($item['category_name']); ?></td>
                                            <td><?php echo formatDate($item['date_found']); ?></td>
                                            <td><?php echo htmlspecialchars($item['full_name']); ?></td>
                                            <td>
                                                <span class="badge bg-<?php 
                                                    echo $item['status'] === 'active' ? 'success' : 
                                                        ($item['status'] === 'pending_match' ? 'warning' : 
                                                            ($item['status'] === 'matched' ? 'info' : 'secondary')); 
                                                ?>">
                                                    <?php echo ucfirst(str_replace('_', ' ', $item['status'])); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <a href="../found_item.php?id=<?php echo $item['id']; ?>" class="btn btn-sm btn-outline-primary" target="_blank">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <div class="col-12 mb-4">
            <div class="card shadow-sm">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-bell me-2"></i>Pending Matches
                        <?php if ($stats['pending_matches'] > 0): ?>
                            <span class="badge bg-warning ms-2"><?php echo $stats['pending_matches']; ?></span>
                        <?php endif; ?>
                    </h5>
                    <a href="manage_matches.php" class="btn btn-sm btn-outline-primary">View All</a>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($pendingMatches)): ?>
                        <div class="alert alert-info m-3 mb-0">
                            <i class="fas fa-info-circle me-2"></i> No pending matches to review.
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Match ID</th>
                                        <th>Lost Item</th>
                                        <th>Found Item</th>
                                        <th>Category</th>
                                        <th>Owner</th>
                                        <th>Finder</th>
                                        <th>Date Matched</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pendingMatches as $match): ?>
                                        <tr>
                                            <td>#<?php echo $match['id']; ?></td>
                                            <td>
                                                <a href="../lost_item.php?id=<?php echo $match['lost_item_id']; ?>" target="_blank">
                                                    <?php echo htmlspecialchars($match['lost_title']); ?>
                                                </a>
                                            </td>
                                            <td>
                                                <a href="../found_item.php?id=<?php echo $match['found_item_id']; ?>" target="_blank">
                                                    <?php echo htmlspecialchars($match['found_title']); ?>
                                                </a>
                                            </td>
                                            <td><?php echo htmlspecialchars($match['category_name']); ?></td>
                                            <td><?php echo htmlspecialchars($match['owner_name']); ?></td>
                                            <td><?php echo htmlspecialchars($match['finder_name']); ?></td>
                                            <td><?php echo formatDate($match['created_at']); ?></td>
                                            <td>
                                                <a href="review_match.php?id=<?php echo $match['id']; ?>" class="btn btn-sm btn-primary">
                                                    <i class="fas fa-check-circle me-1"></i> Review
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <div class="col-md-6 mb-4">
            <div class="card shadow-sm">
                <div class="card-header bg-white py-3">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-database me-2"></i>Database Backup
                    </h5>
                </div>
                <div class="card-body">
                    <p>Create a backup of the entire database for safekeeping.</p>
                    <a href="backup.php" class="btn btn-primary">
                        <i class="fas fa-download me-2"></i>Generate Backup
                    </a>
                </div>
            </div>
        </div>
        
        <div class="col-md-6 mb-4">
            <div class="card shadow-sm">
                <div class="card-header bg-white py-3">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-file-export me-2"></i>Export Data
                    </h5>
                </div>
                <div class="card-body">
                    <p>Export data to CSV format for reporting and analysis.</p>
                    <div class="d-flex gap-2">
                        <a href="export.php?type=lost" class="btn btn-outline-danger">
                            <i class="fas fa-file-csv me-1"></i> Lost Items
                        </a>
                        <a href="export.php?type=found" class="btn btn-outline-success">
                            <i class="fas fa-file-csv me-1"></i> Found Items
                        </a>
                        <a href="export.php?type=matches" class="btn btn-outline-info">
                            <i class="fas fa-file-csv me-1"></i> Matches
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
</script>

<?php 
$conn->close();
include 'includes/admin_footer.php';
?>