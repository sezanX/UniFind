<?php
$pageTitle = 'Review Match';
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

// Check if match ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: manage_matches.php');
    exit;
}

$matchId = $_GET['id'];
$message = '';
$messageType = '';

// Process match actions
if (isset($_POST['action'])) {
    $action = $_POST['action'];
    $status = '';
    $lostItemStatus = '';
    $foundItemStatus = '';
    
    switch ($action) {
        case 'confirm':
            $status = 'confirmed';
            $lostItemStatus = 'matched';
            $foundItemStatus = 'matched';
            break;
        case 'complete':
            $status = 'completed';
            $lostItemStatus = 'returned';
            $foundItemStatus = 'returned';
            break;
        case 'reject':
            $status = 'rejected';
            $lostItemStatus = 'active';
            $foundItemStatus = 'active';
            break;
        default:
            $status = 'pending_review';
            $lostItemStatus = 'pending_match';
            $foundItemStatus = 'pending_match';
    }
    
    // Update match status
    $stmt = $conn->prepare("UPDATE matches SET status = ?, admin_notes = ?, updated_at = NOW() WHERE id = ?");
    $adminNotes = isset($_POST['admin_notes']) ? $_POST['admin_notes'] : '';
    $stmt->bind_param("ssi", $status, $adminNotes, $matchId);
    $stmt->execute();
    
    // Get match details
    $stmt = $conn->prepare("SELECT * FROM matches WHERE id = ?");
    $stmt->bind_param("i", $matchId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $match = $result->fetch_assoc();
        
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
}

// Get match details with related information
$match = null;
$sql = "SELECT m.*, 
        l.title as lost_title, l.description as lost_description, l.date_lost, l.location as lost_location, 
        l.image as lost_image, l.category_id as category_id, l.department as department, 
        l.status as lost_status, l.created_at as lost_created_at, 
        f.title as found_title, f.description as found_description, f.date_found, f.location as found_location, 
        f.image as found_image, f.status as found_status, f.created_at as found_created_at, 
        ul.full_name as owner_name, ul.email as owner_email, ul.phone as owner_phone, ul.whatsapp as owner_whatsapp, 
        ul.department as owner_department, ul.id as owner_id, 
        uf.full_name as finder_name, uf.email as finder_email, uf.phone as finder_phone, uf.whatsapp as finder_whatsapp, 
        uf.department as finder_department, uf.id as finder_id, 
        c.name as category_name, 
        dl.name as lost_department_name, 
        df.name as found_department_name 
        FROM matches m 
        JOIN lost_items l ON m.lost_item_id = l.id 
        JOIN found_items f ON m.found_item_id = f.id 
        JOIN users ul ON l.user_id = ul.id 
        JOIN users uf ON f.user_id = uf.id 
        JOIN categories c ON l.category_id = c.id 
        JOIN departments dl ON ul.department = dl.id 
        JOIN departments df ON uf.department = df.id 
        WHERE m.id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $matchId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('Location: manage_matches.php');
    exit;
}

$match = $result->fetch_assoc();

// Get contact messages related to this match
$contactMessages = [];
$sql = "SELECT cm.*, u.full_name as sender_name, u.email as sender_email 
        FROM contact_messages cm 
        JOIN users u ON cm.sender_id = u.id 
        WHERE (cm.lost_item_id = ? OR cm.found_item_id = ?) 
        ORDER BY cm.created_at DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $match['lost_item_id'], $match['found_item_id']);
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
        <h1 class="h3 mb-0 text-gray-800">Review Match #<?php echo $matchId; ?></h1>
        <a href="manage_matches.php" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-1"></i> Back to Matches
        </a>
    </div>
    
    <?php if (!empty($message)): ?>
        <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
            <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    
    <div class="row">
        <div class="col-lg-8">
            <!-- Match Status Card -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Match Status</h5>
                    <span class="badge bg-<?php 
                        echo $match['status'] === 'pending_review' ? 'warning' : 
                            ($match['status'] === 'confirmed' ? 'info' : 
                                ($match['status'] === 'completed' ? 'success' : 'danger')); 
                    ?> fs-6">
                        <?php echo ucfirst(str_replace('_', ' ', $match['status'])); ?>
                    </span>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Match Created:</strong> <?php echo formatDate($match['created_at']); ?></p>
                            <p><strong>Last Updated:</strong> <?php echo formatDate($match['updated_at']); ?></p>
                            <p><strong>Match Type:</strong> System-generated</p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Category:</strong> <?php echo htmlspecialchars($match['category_name']); ?></p>
                            <p><strong>Department:</strong> <?php echo htmlspecialchars($match['lost_department_name']); ?></p>
                            <p><strong>Time Difference:</strong> 
                                <?php 
                                $dateLost = new DateTime($match['date_lost']);
                                $dateFound = new DateTime($match['date_found']);
                                $interval = $dateLost->diff($dateFound);
                                echo $interval->format('%a days');
                                ?>
                            </p>
                        </div>
                    </div>
                    
                    <form action="" method="POST" class="mt-3">
                        <div class="mb-3">
                            <label for="admin_notes" class="form-label">Admin Notes</label>
                            <textarea class="form-control" id="admin_notes" name="admin_notes" rows="3"><?php echo htmlspecialchars($match['admin_notes'] ?? ''); ?></textarea>
                        </div>
                        
                        <div class="d-flex justify-content-between">
                            <button type="submit" name="action" value="confirm" class="btn btn-info">
                                <i class="fas fa-check me-1"></i> Confirm Match
                            </button>
                            <button type="submit" name="action" value="complete" class="btn btn-success">
                                <i class="fas fa-check-double me-1"></i> Mark as Completed
                            </button>
                            <button type="submit" name="action" value="reject" class="btn btn-danger">
                                <i class="fas fa-times me-1"></i> Reject Match
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Items Comparison -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white py-3">
                    <h5 class="card-title mb-0">Items Comparison</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="card h-100">
                                <div class="card-header bg-light">
                                    <h6 class="mb-0 text-primary">Lost Item</h6>
                                </div>
                                <div class="card-body">
                                    <div class="text-center mb-3">
                                        <?php if (!empty($match['lost_image'])): ?>
                                            <img src="../uploads/<?php echo $match['lost_image']; ?>" alt="Lost Item" class="img-fluid rounded" style="max-height: 200px;">
                                        <?php else: ?>
                                            <div class="bg-light d-flex align-items-center justify-content-center rounded" style="height: 200px;">
                                                <i class="fas fa-search fa-3x text-muted"></i>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <h5 class="card-title">
                                        <a href="../lost_item.php?id=<?php echo $match['lost_item_id']; ?>" target="_blank" class="text-decoration-none">
                                            <?php echo htmlspecialchars($match['lost_title']); ?>
                                        </a>
                                    </h5>
                                    
                                    <p class="card-text"><?php echo nl2br(htmlspecialchars($match['lost_description'])); ?></p>
                                    
                                    <ul class="list-group list-group-flush mt-3">
                                        <li class="list-group-item px-0">
                                            <i class="fas fa-map-marker-alt me-2 text-primary"></i>
                                            <?php echo htmlspecialchars($match['lost_location']); ?>
                                        </li>
                                        <li class="list-group-item px-0">
                                            <i class="fas fa-calendar-alt me-2 text-primary"></i>
                                            <?php echo formatDate($match['date_lost']); ?>
                                        </li>
                                        <li class="list-group-item px-0">
                                            <i class="fas fa-building me-2 text-primary"></i>
                                            <?php echo htmlspecialchars($match['lost_department_name']); ?>
                                        </li>
                                        <li class="list-group-item px-0">
                                            <i class="fas fa-clock me-2 text-primary"></i>
                                            Reported <?php echo timeElapsed($match['lost_created_at']); ?>
                                        </li>
                                        <li class="list-group-item px-0">
                                            <i class="fas fa-tag me-2 text-primary"></i>
                                            <span class="badge bg-<?php 
                                                echo $match['lost_status'] === 'active' ? 'primary' : 
                                                    ($match['lost_status'] === 'pending_match' ? 'warning' : 
                                                        ($match['lost_status'] === 'matched' ? 'info' : 'success')); 
                                            ?>">
                                                <?php echo ucfirst(str_replace('_', ' ', $match['lost_status'])); ?>
                                            </span>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="card h-100">
                                <div class="card-header bg-light">
                                    <h6 class="mb-0 text-success">Found Item</h6>
                                </div>
                                <div class="card-body">
                                    <div class="text-center mb-3">
                                        <?php if (!empty($match['found_image'])): ?>
                                            <img src="../uploads/<?php echo $match['found_image']; ?>" alt="Found Item" class="img-fluid rounded" style="max-height: 200px;">
                                        <?php else: ?>
                                            <div class="bg-light d-flex align-items-center justify-content-center rounded" style="height: 200px;">
                                                <i class="fas fa-hand-holding fa-3x text-muted"></i>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <h5 class="card-title">
                                        <a href="../found_item.php?id=<?php echo $match['found_item_id']; ?>" target="_blank" class="text-decoration-none">
                                            <?php echo htmlspecialchars($match['found_title']); ?>
                                        </a>
                                    </h5>
                                    
                                    <p class="card-text"><?php echo nl2br(htmlspecialchars($match['found_description'])); ?></p>
                                    
                                    <ul class="list-group list-group-flush mt-3">
                                        <li class="list-group-item px-0">
                                            <i class="fas fa-map-marker-alt me-2 text-success"></i>
                                            <?php echo htmlspecialchars($match['found_location']); ?>
                                        </li>
                                        <li class="list-group-item px-0">
                                            <i class="fas fa-calendar-alt me-2 text-success"></i>
                                            <?php echo formatDate($match['date_found']); ?>
                                        </li>
                                        <li class="list-group-item px-0">
                                            <i class="fas fa-building me-2 text-success"></i>
                                            <?php echo htmlspecialchars($match['found_department_name']); ?>
                                        </li>
                                        <li class="list-group-item px-0">
                                            <i class="fas fa-clock me-2 text-success"></i>
                                            Reported <?php echo timeElapsed($match['found_created_at']); ?>
                                        </li>
                                        <li class="list-group-item px-0">
                                            <i class="fas fa-tag me-2 text-success"></i>
                                            <span class="badge bg-<?php 
                                                echo $match['found_status'] === 'active' ? 'primary' : 
                                                    ($match['found_status'] === 'pending_match' ? 'warning' : 
                                                        ($match['found_status'] === 'matched' ? 'info' : 'success')); 
                                            ?>">
                                                <?php echo ucfirst(str_replace('_', ' ', $match['found_status'])); ?>
                                            </span>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Contact Messages -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white py-3">
                    <h5 class="card-title mb-0">Contact Messages</h5>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($contactMessages)): ?>
                        <div class="p-4 text-center">
                            <p class="text-muted mb-0">No contact messages found for this match.</p>
                        </div>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($contactMessages as $message): ?>
                                <div class="list-group-item p-3">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <h6 class="mb-0"><?php echo htmlspecialchars($message['sender_name']); ?> <small class="text-muted">(<?php echo htmlspecialchars($message['sender_email']); ?>)</small></h6>
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
        
        <div class="col-lg-4">
            <!-- Owner Information -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white py-3">
                    <h5 class="card-title mb-0">Owner Information</h5>
                </div>
                <div class="card-body">
                    <div class="text-center mb-3">
                        <div class="avatar-circle mx-auto">
                            <span class="initials"><?php echo substr($match['owner_name'], 0, 1); ?></span>
                        </div>
                        <h5 class="mt-3 mb-1">
                            <a href="view_user.php?id=<?php echo $match['owner_id']; ?>" class="text-decoration-none">
                                <?php echo htmlspecialchars($match['owner_name']); ?>
                            </a>
                        </h5>
                        <p class="text-muted">Item Owner</p>
                    </div>
                    
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item px-0 py-2">
                            <i class="fas fa-envelope me-2 text-primary"></i>
                            <a href="mailto:<?php echo $match['owner_email']; ?>" class="text-decoration-none">
                                <?php echo htmlspecialchars($match['owner_email']); ?>
                            </a>
                        </li>
                        <li class="list-group-item px-0 py-2">
                            <i class="fas fa-phone me-2 text-primary"></i>
                            <a href="tel:<?php echo $match['owner_phone']; ?>" class="text-decoration-none">
                                <?php echo htmlspecialchars($match['owner_phone']); ?>
                            </a>
                        </li>
                        <?php if (!empty($match['owner_whatsapp'])): ?>
                            <li class="list-group-item px-0 py-2">
                                <i class="fab fa-whatsapp me-2 text-primary"></i>
                                <a href="https://wa.me/<?php echo $match['owner_whatsapp']; ?>" target="_blank" class="text-decoration-none">
                                    <?php echo htmlspecialchars($match['owner_whatsapp']); ?>
                                </a>
                            </li>
                        <?php endif; ?>
                        <li class="list-group-item px-0 py-2">
                            <i class="fas fa-building me-2 text-primary"></i>
                            <?php 
                            $stmt = $conn->prepare("SELECT name FROM departments WHERE id = ?");
                            $stmt->bind_param("i", $match['owner_department']);
                            $stmt->execute();
                            $result = $stmt->get_result();
                            if ($row = $result->fetch_assoc()) {
                                echo htmlspecialchars($row['name']);
                            }
                            ?>
                        </li>
                    </ul>
                </div>
            </div>
            
            <!-- Finder Information -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white py-3">
                    <h5 class="card-title mb-0">Finder Information</h5>
                </div>
                <div class="card-body">
                    <div class="text-center mb-3">
                        <div class="avatar-circle mx-auto">
                            <span class="initials"><?php echo substr($match['finder_name'], 0, 1); ?></span>
                        </div>
                        <h5 class="mt-3 mb-1">
                            <a href="view_user.php?id=<?php echo $match['finder_id']; ?>" class="text-decoration-none">
                                <?php echo htmlspecialchars($match['finder_name']); ?>
                            </a>
                        </h5>
                        <p class="text-muted">Item Finder</p>
                    </div>
                    
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item px-0 py-2">
                            <i class="fas fa-envelope me-2 text-success"></i>
                            <a href="mailto:<?php echo $match['finder_email']; ?>" class="text-decoration-none">
                                <?php echo htmlspecialchars($match['finder_email']); ?>
                            </a>
                        </li>
                        <li class="list-group-item px-0 py-2">
                            <i class="fas fa-phone me-2 text-success"></i>
                            <a href="tel:<?php echo $match['finder_phone']; ?>" class="text-decoration-none">
                                <?php echo htmlspecialchars($match['finder_phone']); ?>
                            </a>
                        </li>
                        <?php if (!empty($match['finder_whatsapp'])): ?>
                            <li class="list-group-item px-0 py-2">
                                <i class="fab fa-whatsapp me-2 text-success"></i>
                                <a href="https://wa.me/<?php echo $match['finder_whatsapp']; ?>" target="_blank" class="text-decoration-none">
                                    <?php echo htmlspecialchars($match['finder_whatsapp']); ?>
                                </a>
                            </li>
                        <?php endif; ?>
                        <li class="list-group-item px-0 py-2">
                            <i class="fas fa-building me-2 text-success"></i>
                            <?php 
                            $stmt = $conn->prepare("SELECT name FROM departments WHERE id = ?");
                            $stmt->bind_param("i", $match['finder_department']);
                            $stmt->execute();
                            $result = $stmt->get_result();
                            if ($row = $result->fetch_assoc()) {
                                echo htmlspecialchars($row['name']);
                            }
                            ?>
                        </li>
                    </ul>
                </div>
            </div>
            
            <!-- Actions -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white py-3">
                    <h5 class="card-title mb-0">Actions</h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="../lost_item.php?id=<?php echo $match['lost_item_id']; ?>" target="_blank" class="btn btn-outline-primary">
                            <i class="fas fa-search me-1"></i> View Lost Item
                        </a>
                        <a href="../found_item.php?id=<?php echo $match['found_item_id']; ?>" target="_blank" class="btn btn-outline-success">
                            <i class="fas fa-hand-holding me-1"></i> View Found Item
                        </a>
                        <a href="javascript:void(0)" onclick="confirmDelete('manage_matches.php?delete=<?php echo $matchId; ?>', 'Match #<?php echo $matchId; ?>')" class="btn btn-outline-danger">
                            <i class="fas fa-trash-alt me-1"></i> Delete Match
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.avatar-circle {
    width: 80px;
    height: 80px;
    background-color: #f8f9fa;
    border-radius: 50%;
    display: flex;
    justify-content: center;
    align-items: center;
    border: 2px solid #e9ecef;
}

.initials {
    font-size: 32px;
    line-height: 1;
    color: #6c757d;
    font-weight: bold;
    text-transform: uppercase;
}
</style>

<?php 
$conn->close();
include 'includes/admin_footer.php';
?>