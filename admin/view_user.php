<?php
$pageTitle = 'View User';
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

// Check if user ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: manage_users.php');
    exit;
}

$userId = $_GET['id'];

// Get user details
$user = null;
$sql = "SELECT u.*, d.name as department_name 
        FROM users u 
        LEFT JOIN departments d ON u.department = d.id 
        WHERE u.id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('Location: manage_users.php');
    exit;
}

$user = $result->fetch_assoc();

// Get user's lost items
$lostItems = [];
$sql = "SELECT l.*, c.name as category_name, d.name as department_name 
        FROM lost_items l 
        JOIN categories c ON l.category_id = c.id 
        JOIN users u ON l.user_id = u.id 
        JOIN departments d ON u.department = d.id 
        WHERE l.user_id = ? 
        ORDER BY l.date_reported DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $lostItems[] = $row;
}

// Get user's found items
$foundItems = [];
$sql = "SELECT f.*, c.name as category_name, d.name as department_name 
        FROM found_items f 
        JOIN categories c ON f.category_id = c.id 
        JOIN users u ON f.user_id = u.id 
        JOIN departments d ON u.department = d.id 
        WHERE f.user_id = ? 
        ORDER BY f.date_reported DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $foundItems[] = $row;
}

// Get user's matches
$matches = [];
$sql = "SELECT m.*, 
        l.title as lost_title, l.date_lost, l.location as lost_location, 
        f.title as found_title, f.date_found, f.location as found_location, 
        c.name as category_name 
        FROM matches m 
        JOIN lost_items l ON m.lost_item_id = l.id 
        JOIN found_items f ON m.found_item_id = f.id 
        JOIN categories c ON l.category_id = c.id 
        WHERE l.user_id = ? OR f.user_id = ? 
        ORDER BY m.created_at DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $userId, $userId);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $matches[] = $row;
}

// Get user's contact messages
$contactMessages = [];
$sql = "SELECT cm.*, 
        CASE 
            WHEN cm.lost_item_id IS NOT NULL THEN l.title 
            ELSE f.title 
        END as item_title, 
        CASE 
            WHEN cm.lost_item_id IS NOT NULL THEN 'lost' 
            ELSE 'found' 
        END as item_type, 
        u.full_name as recipient_name 
        FROM contact_messages cm 
        LEFT JOIN lost_items l ON cm.lost_item_id = l.id 
        LEFT JOIN found_items f ON cm.found_item_id = f.id 
        LEFT JOIN users u ON (l.user_id = u.id OR f.user_id = u.id) 
        WHERE cm.sender_id = ? 
        ORDER BY cm.created_at DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $contactMessages[] = $row;
}

// Include admin header
include 'includes/admin_header.php';
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0 text-gray-800">User Profile: <?php echo htmlspecialchars($user['full_name']); ?></h1>
        <a href="manage_users.php" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-1"></i> Back to Users
        </a>
    </div>
    
    <div class="row">
        <div class="col-lg-4">
            <!-- User Information Card -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white py-3">
                    <h5 class="card-title mb-0">User Information</h5>
                </div>
                <div class="card-body">
                    <div class="text-center mb-4">
                        <div class="avatar-circle mx-auto mb-3">
                            <span class="initials"><?php echo substr($user['full_name'], 0, 1); ?></span>
                        </div>
                        <h4 class="mb-0"><?php echo htmlspecialchars($user['full_name']); ?></h4>
                        <p class="text-muted">
                            <?php echo $user['role'] == 'admin' ? '<span class="badge bg-danger">Administrator</span>' : '<span class="badge bg-primary">Regular User</span>'; ?>
                        </p>
                    </div>
                    
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item px-0 py-2 d-flex justify-content-between">
                            <span><i class="fas fa-user me-2 text-primary"></i> Username</span>
                            <span class="text-muted"><?php echo htmlspecialchars($user['username']); ?></span>
                        </li>
                        <li class="list-group-item px-0 py-2 d-flex justify-content-between">
                            <span><i class="fas fa-envelope me-2 text-primary"></i> Email</span>
                            <span class="text-muted">
                                <a href="mailto:<?php echo $user['email']; ?>" class="text-decoration-none">
                                    <?php echo htmlspecialchars($user['email']); ?>
                                </a>
                            </span>
                        </li>
                        <li class="list-group-item px-0 py-2 d-flex justify-content-between">
                            <span><i class="fas fa-phone me-2 text-primary"></i> Phone</span>
                            <span class="text-muted">
                                <a href="tel:<?php echo $user['phone']; ?>" class="text-decoration-none">
                                    <?php echo htmlspecialchars($user['phone']); ?>
                                </a>
                            </span>
                        </li>
                        <!-- WhatsApp field removed as it's not in the database schema -->
                        <li class="list-group-item px-0 py-2 d-flex justify-content-between">
                            <span><i class="fas fa-building me-2 text-primary"></i> Department</span>
                            <span class="text-muted"><?php echo htmlspecialchars($user['department_name'] ?? 'Not Assigned'); ?></span>
                        </li>
                        <li class="list-group-item px-0 py-2 d-flex justify-content-between">
                            <span><i class="fas fa-calendar-alt me-2 text-primary"></i> Joined</span>
                            <span class="text-muted"><?php echo formatDate($user['created_at']); ?></span>
                        </li>
                        <li class="list-group-item px-0 py-2 d-flex justify-content-between">
                            <span><i class="fas fa-clock me-2 text-primary"></i> Last Updated</span>
                            <span class="text-muted"><?php echo formatDate($user['updated_at']); ?></span>
                        </li>
                    </ul>
                </div>
                <div class="card-footer bg-white">
                    <div class="d-grid gap-2">
                        <a href="manage_users.php?toggle_admin=<?php echo $user['id']; ?>" class="btn btn-outline-<?php echo $user['role'] == 'admin' ? 'warning' : 'success'; ?>">
                            <i class="fas <?php echo $user['role'] == 'admin' ? 'fa-user' : 'fa-user-shield'; ?> me-1"></i>
                            <?php echo $user['role'] == 'admin' ? 'Remove Admin Rights' : 'Make Administrator'; ?>
                        </a>
                        <a href="javascript:void(0)" onclick="confirmDelete('manage_users.php?delete=<?php echo $user['id']; ?>', '<?php echo htmlspecialchars($user['full_name']); ?>')" class="btn btn-outline-danger">
                            <i class="fas fa-trash-alt me-1"></i> Delete User
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- User Statistics Card -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white py-3">
                    <h5 class="card-title mb-0">User Statistics</h5>
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-6 mb-3">
                            <div class="stat-item">
                                <h2 class="text-danger"><?php echo count($lostItems); ?></h2>
                                <p class="mb-0">Lost Items</p>
                            </div>
                        </div>
                        <div class="col-6 mb-3">
                            <div class="stat-item">
                                <h2 class="text-success"><?php echo count($foundItems); ?></h2>
                                <p class="mb-0">Found Items</p>
                            </div>
                        </div>
                        <div class="col-6 mb-3">
                            <div class="stat-item">
                                <h2 class="text-info"><?php echo count($matches); ?></h2>
                                <p class="mb-0">Matches</p>
                            </div>
                        </div>
                        <div class="col-6 mb-3">
                            <div class="stat-item">
                                <h2 class="text-primary"><?php echo count($contactMessages); ?></h2>
                                <p class="mb-0">Messages</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-3">
                        <canvas id="userActivityChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-8">
            <!-- User's Items Tabs -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white py-3">
                    <ul class="nav nav-tabs card-header-tabs" id="userItemsTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="lost-items-tab" data-bs-toggle="tab" data-bs-target="#lost-items" type="button" role="tab" aria-controls="lost-items" aria-selected="true">
                                <i class="fas fa-search me-1"></i> Lost Items (<?php echo count($lostItems); ?>)
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="found-items-tab" data-bs-toggle="tab" data-bs-target="#found-items" type="button" role="tab" aria-controls="found-items" aria-selected="false">
                                <i class="fas fa-hand-holding me-1"></i> Found Items (<?php echo count($foundItems); ?>)
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="matches-tab" data-bs-toggle="tab" data-bs-target="#matches" type="button" role="tab" aria-controls="matches" aria-selected="false">
                                <i class="fas fa-exchange-alt me-1"></i> Matches (<?php echo count($matches); ?>)
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="messages-tab" data-bs-toggle="tab" data-bs-target="#messages" type="button" role="tab" aria-controls="messages" aria-selected="false">
                                <i class="fas fa-envelope me-1"></i> Messages (<?php echo count($contactMessages); ?>)
                            </button>
                        </li>
                    </ul>
                </div>
                <div class="card-body p-0">
                    <div class="tab-content" id="userItemsTabsContent">
                        <!-- Lost Items Tab -->
                        <div class="tab-pane fade show active" id="lost-items" role="tabpanel" aria-labelledby="lost-items-tab">
                            <?php if (empty($lostItems)): ?>
                                <div class="p-4 text-center">
                                    <p class="text-muted mb-0">No lost items reported by this user.</p>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Title</th>
                                                <th>Category</th>
                                                <th>Department</th>
                                                <th>Date Lost</th>
                                                <th>Status</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($lostItems as $item): ?>
                                                <tr>
                                                    <td>
                                                        <div class="d-flex align-items-center">
                                                            <?php if (!empty($item['image'])): ?>
                                                                <img src="../uploads/<?php echo $item['image']; ?>" alt="Lost Item" class="img-thumbnail me-2" style="width: 40px; height: 40px; object-fit: cover;">
                                                            <?php else: ?>
                                                                <div class="bg-light d-flex align-items-center justify-content-center me-2" style="width: 40px; height: 40px;">
                                                                    <i class="fas fa-search text-muted"></i>
                                                                </div>
                                                            <?php endif; ?>
                                                            <a href="../lost_item.php?id=<?php echo $item['id']; ?>" target="_blank" class="text-decoration-none">
                                                                <?php echo htmlspecialchars($item['title']); ?>
                                                            </a>
                                                        </div>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($item['category_name']); ?></td>
                                                    <td><?php echo htmlspecialchars($item['department_name']); ?></td>
                                                    <td><?php echo formatDate($item['date_lost']); ?></td>
                                                    <td>
                                                        <span class="badge bg-<?php 
                                                            echo $item['status'] === 'active' ? 'primary' : 
                                                                ($item['status'] === 'pending_match' ? 'warning' : 
                                                                    ($item['status'] === 'matched' ? 'info' : 'success')); 
                                                        ?>">
                                                            <?php echo ucfirst(str_replace('_', ' ', $item['status'])); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <div class="btn-group">
                                                            <a href="../lost_item.php?id=<?php echo $item['id']; ?>" target="_blank" class="btn btn-sm btn-outline-primary" title="View Item">
                                                                <i class="fas fa-eye"></i>
                                                            </a>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Found Items Tab -->
                        <div class="tab-pane fade" id="found-items" role="tabpanel" aria-labelledby="found-items-tab">
                            <?php if (empty($foundItems)): ?>
                                <div class="p-4 text-center">
                                    <p class="text-muted mb-0">No found items reported by this user.</p>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Title</th>
                                                <th>Category</th>
                                                <th>Department</th>
                                                <th>Date Found</th>
                                                <th>Status</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($foundItems as $item): ?>
                                                <tr>
                                                    <td>
                                                        <div class="d-flex align-items-center">
                                                            <?php if (!empty($item['image'])): ?>
                                                                <img src="../uploads/<?php echo $item['image']; ?>" alt="Found Item" class="img-thumbnail me-2" style="width: 40px; height: 40px; object-fit: cover;">
                                                            <?php else: ?>
                                                                <div class="bg-light d-flex align-items-center justify-content-center me-2" style="width: 40px; height: 40px;">
                                                                    <i class="fas fa-hand-holding text-muted"></i>
                                                                </div>
                                                            <?php endif; ?>
                                                            <a href="../found_item.php?id=<?php echo $item['id']; ?>" target="_blank" class="text-decoration-none">
                                                                <?php echo htmlspecialchars($item['title']); ?>
                                                            </a>
                                                        </div>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($item['category_name']); ?></td>
                                                    <td><?php echo htmlspecialchars($item['department_name']); ?></td>
                                                    <td><?php echo formatDate($item['date_found']); ?></td>
                                                    <td>
                                                        <span class="badge bg-<?php 
                                                            echo $item['status'] === 'active' ? 'primary' : 
                                                                ($item['status'] === 'pending_match' ? 'warning' : 
                                                                    ($item['status'] === 'matched' ? 'info' : 'success')); 
                                                        ?>">
                                                            <?php echo ucfirst(str_replace('_', ' ', $item['status'])); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <div class="btn-group">
                                                            <a href="../found_item.php?id=<?php echo $item['id']; ?>" target="_blank" class="btn btn-sm btn-outline-primary" title="View Item">
                                                                <i class="fas fa-eye"></i>
                                                            </a>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Matches Tab -->
                        <div class="tab-pane fade" id="matches" role="tabpanel" aria-labelledby="matches-tab">
                            <?php if (empty($matches)): ?>
                                <div class="p-4 text-center">
                                    <p class="text-muted mb-0">No matches found for this user.</p>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Lost Item</th>
                                                <th>Found Item</th>
                                                <th>Category</th>
                                                <th>Status</th>
                                                <th>Date Matched</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($matches as $match): ?>
                                                <tr>
                                                    <td>
                                                        <a href="../lost_item.php?id=<?php echo $match['lost_item_id']; ?>" target="_blank" class="text-decoration-none">
                                                            <?php echo htmlspecialchars($match['lost_title']); ?>
                                                        </a>
                                                    </td>
                                                    <td>
                                                        <a href="../found_item.php?id=<?php echo $match['found_item_id']; ?>" target="_blank" class="text-decoration-none">
                                                            <?php echo htmlspecialchars($match['found_title']); ?>
                                                        </a>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($match['category_name']); ?></td>
                                                    <td>
                                                        <span class="badge bg-<?php 
                                                            echo $match['status'] === 'pending_review' ? 'warning' : 
                                                                ($match['status'] === 'confirmed' ? 'info' : 
                                                                    ($match['status'] === 'completed' ? 'success' : 'danger')); 
                                                        ?>">
                                                            <?php echo ucfirst(str_replace('_', ' ', $match['status'])); ?>
                                                        </span>
                                                    </td>
                                                    <td><?php echo formatDate($match['created_at']); ?></td>
                                                    <td>
                                                        <div class="btn-group">
                                                            <a href="review_match.php?id=<?php echo $match['id']; ?>" class="btn btn-sm btn-outline-primary" title="Review Match">
                                                                <i class="fas fa-eye"></i>
                                                            </a>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Messages Tab -->
                        <div class="tab-pane fade" id="messages" role="tabpanel" aria-labelledby="messages-tab">
                            <?php if (empty($contactMessages)): ?>
                                <div class="p-4 text-center">
                                    <p class="text-muted mb-0">No contact messages sent by this user.</p>
                                </div>
                            <?php else: ?>
                                <div class="list-group list-group-flush">
                                    <?php foreach ($contactMessages as $message): ?>
                                        <div class="list-group-item p-3">
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <h6 class="mb-0">
                                                    Message to: <?php echo htmlspecialchars($message['recipient_name']); ?>
                                                    <small class="text-muted">
                                                        (<?php echo ucfirst($message['item_type']); ?> Item: <?php echo htmlspecialchars($message['item_title']); ?>)
                                                    </small>
                                                </h6>
                                                <small class="text-muted"><?php echo formatDate($message['created_at']); ?></small>
                                            </div>
                                            <p class="mb-2"><?php echo nl2br(htmlspecialchars($message['message'])); ?></p>
                                            <?php if (!empty($message['image'])): ?>
                                                <div class="mt-2">
                                                    <a href="../uploads/<?php echo $message['image']; ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                                        <i class="fas fa-image me-1"></i> View Attached Image
                                                    </a>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.avatar-circle {
    width: 100px;
    height: 100px;
    background-color: #f8f9fa;
    border-radius: 50%;
    display: flex;
    justify-content: center;
    align-items: center;
    border: 2px solid #e9ecef;
}

.initials {
    font-size: 40px;
    line-height: 1;
    color: #6c757d;
    font-weight: bold;
    text-transform: uppercase;
}
</style>

<script>
// Chart for user activity
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('userActivityChart').getContext('2d');
    
    // Prepare data for chart
    const lostItems = <?php echo count($lostItems); ?>;
    const foundItems = <?php echo count($foundItems); ?>;
    const matches = <?php echo count($matches); ?>;
    const messages = <?php echo count($contactMessages); ?>;
    
    const userChart = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['Lost Items', 'Found Items', 'Matches', 'Messages'],
            datasets: [{
                data: [lostItems, foundItems, matches, messages],
                backgroundColor: [
                    'rgba(220, 53, 69, 0.7)',
                    'rgba(40, 167, 69, 0.7)',
                    'rgba(13, 202, 240, 0.7)',
                    'rgba(13, 110, 253, 0.7)'
                ],
                borderColor: [
                    'rgba(220, 53, 69, 1)',
                    'rgba(40, 167, 69, 1)',
                    'rgba(13, 202, 240, 1)',
                    'rgba(13, 110, 253, 1)'
                ],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                },
                title: {
                    display: true,
                    text: 'User Activity Distribution'
                }
            }
        }
    });
});
</script>

<?php 
$conn->close();
include 'includes/admin_footer.php';
?>