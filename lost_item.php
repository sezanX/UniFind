<?php
$pageTitle = 'Lost Item Details';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

// Check if ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: index.php');
    exit;
}

$itemId = (int)$_GET['id'];

// Get item details
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}

$sql = "SELECT l.*, c.name as category_name, u.full_name, u.email, u.phone, u.department, d.name as department_name 
        FROM lost_items l 
        JOIN categories c ON l.category_id = c.id 
        JOIN users u ON l.user_id = u.id 
        JOIN departments d ON u.department = d.id 
        WHERE l.id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $itemId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('Location: index.php');
    exit;
}

$item = $result->fetch_assoc();

// Check for potential matches
$potentialMatches = [];
if ($item['status'] === 'active') {
    $potentialMatches = findPotentialMatches($item);
}

// Handle match confirmation
if (isset($_POST['confirm_match']) && isLoggedIn()) {
    $foundItemId = (int)$_POST['found_item_id'];
    
    // Create match
    $matchCreated = createMatch($itemId, $foundItemId);
    
    if ($matchCreated) {
        // Update status of both items
        $updateSql = "UPDATE lost_items SET status = 'pending_match' WHERE id = ?";
        $updateStmt = $conn->prepare($updateSql);
        $updateStmt->bind_param('i', $itemId);
        $updateStmt->execute();
        
        $updateFoundSql = "UPDATE found_items SET status = 'pending_match' WHERE id = ?";
        $updateFoundStmt = $conn->prepare($updateFoundSql);
        $updateFoundStmt->bind_param('i', $foundItemId);
        $updateFoundStmt->execute();
        
        // Redirect to prevent form resubmission
        header('Location: lost_item.php?id=' . $itemId . '&matched=1');
        exit;
    }
}

// Check if user is the owner of this item
$isOwner = isLoggedIn() && getCurrentUser()['id'] === $item['user_id'];

// Handle item deletion
if (isset($_POST['delete_item']) && $isOwner) {
    $deleteSql = "DELETE FROM lost_items WHERE id = ? AND user_id = ?";
    $deleteStmt = $conn->prepare($deleteSql);
    $deleteStmt->bind_param('ii', $itemId, getCurrentUser()['id']);
    $deleteStmt->execute();
    
    if ($deleteStmt->affected_rows > 0) {
        // Delete the image file if it exists
        if (!empty($item['image']) && file_exists(__DIR__ . '/uploads/' . $item['image'])) {
            unlink(__DIR__ . '/uploads/' . $item['image']);
        }
        
        header('Location: my_reports.php?deleted=1');
        exit;
    }
}

include 'includes/header.php';
?>

<div class="container py-4">
    <?php if (isset($_GET['matched']) && $_GET['matched'] == 1): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i> Match request sent successfully! An admin will review and confirm the match.
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    
    <div class="row">
        <div class="col-md-8">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                    <li class="breadcrumb-item"><a href="search.php?type=lost">Lost Items</a></li>
                    <li class="breadcrumb-item active" aria-current="page"><?php echo htmlspecialchars($item['title']); ?></li>
                </ol>
            </nav>
            
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h4 class="card-title mb-0"><?php echo htmlspecialchars($item['title']); ?></h4>
                    <span class="badge badge-lost">Lost</span>
                </div>
                
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-4">
                            <?php if (!empty($item['image']) && file_exists(__DIR__ . '/uploads/' . $item['image'])): ?>
                                <img src="uploads/<?php echo htmlspecialchars($item['image']); ?>" class="img-fluid rounded" alt="<?php echo htmlspecialchars($item['title']); ?>">
                            <?php else: ?>
                                <div class="no-image-placeholder d-flex align-items-center justify-content-center bg-light rounded" style="height: 300px;">
                                    <i class="fas fa-image fa-4x text-muted"></i>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="col-md-6">
                            <h5 class="border-bottom pb-2 mb-3">Item Details</h5>
                            <ul class="list-unstyled">
                                <li class="mb-2">
                                    <strong><i class="fas fa-tag me-2"></i>Category:</strong> 
                                    <?php echo htmlspecialchars($item['category_name']); ?>
                                </li>
                                <li class="mb-2">
                                    <strong><i class="fas fa-calendar-alt me-2"></i>Date Lost:</strong> 
                                    <?php echo formatDate($item['date_lost']); ?>
                                    <small class="text-muted">(<?php echo timeElapsed($item['date_lost']); ?>)</small>
                                </li>
                                <li class="mb-2">
                                    <strong><i class="fas fa-map-marker-alt me-2"></i>Location:</strong> 
                                    <?php echo htmlspecialchars($item['location']); ?>
                                </li>
                                <li class="mb-2">
                                    <strong><i class="fas fa-university me-2"></i>Department:</strong> 
                                    <?php echo htmlspecialchars($item['department_name']); ?>
                                </li>
                                <li class="mb-2">
                                    <strong><i class="fas fa-clock me-2"></i>Status:</strong> 
                                    <span class="badge bg-<?php 
                                        echo $item['status'] === 'active' ? 'success' : 
                                            ($item['status'] === 'pending_match' ? 'warning' : 
                                                ($item['status'] === 'matched' ? 'info' : 'secondary')); 
                                    ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $item['status'])); ?>
                                    </span>
                                </li>
                                <li class="mb-2">
                                    <strong><i class="fas fa-calendar-plus me-2"></i>Reported:</strong> 
                                    <?php echo formatDate($item['date_reported']); ?>
                                </li>
                            </ul>
                        </div>
                    </div>
                    
                    <div class="mt-4">
                        <h5 class="border-bottom pb-2 mb-3">Description</h5>
                        <p><?php echo nl2br(htmlspecialchars($item['description'])); ?></p>
                    </div>
                    
                    <?php if ($isOwner): ?>
                        <div class="mt-4 d-flex">
                            <a href="edit_lost.php?id=<?php echo $item['id']; ?>" class="btn btn-outline-primary me-2">
                                <i class="fas fa-edit me-1"></i> Edit
                            </a>
                            <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteModal">
                                <i class="fas fa-trash-alt me-1"></i> Delete
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php if (!empty($potentialMatches)): ?>
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-white py-3">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-exchange-alt me-2"></i>Potential Matches
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <?php foreach ($potentialMatches as $match): ?>
                                <div class="col-md-6 mb-3">
                                    <div class="card h-100">
                                        <div class="card-body">
                                            <h6 class="card-title"><?php echo htmlspecialchars($match['title']); ?></h6>
                                            <p class="card-text small">
                                                <i class="fas fa-calendar-alt me-1"></i> Found on: <?php echo formatDate($match['date_found']); ?><br>
                                                <i class="fas fa-map-marker-alt me-1"></i> <?php echo htmlspecialchars($match['location']); ?>
                                            </p>
                                            <?php if (isLoggedIn()): ?>
                                                <form method="post" action="">
                                                    <input type="hidden" name="found_item_id" value="<?php echo $match['id']; ?>">
                                                    <button type="submit" name="confirm_match" class="btn btn-sm btn-success w-100">
                                                        <i class="fas fa-check-circle me-1"></i> This is my item
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <a href="login.php" class="btn btn-sm btn-outline-primary w-100">Login to claim</a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="col-md-4">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white py-3">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-user me-2"></i>Contact Information
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (isLoggedIn()): ?>
                        <ul class="list-unstyled contact-info">
                            <li class="mb-3">
                                <i class="fas fa-user-circle fa-fw me-2"></i>
                                <strong>Name:</strong> <?php echo htmlspecialchars($item['full_name']); ?>
                            </li>
                            <li class="mb-3">
                                <i class="fas fa-phone fa-fw me-2"></i>
                                <strong>Phone:</strong> 
                                <a href="tel:<?php echo htmlspecialchars($item['phone']); ?>">
                                    <?php echo htmlspecialchars($item['phone']); ?>
                                </a>
                            </li>
                            <?php if (!empty($item['whatsapp'])): ?>
                            <li class="mb-3">
                                <i class="fab fa-whatsapp fa-fw me-2"></i>
                                <strong>WhatsApp:</strong> 
                                <a href="https://wa.me/<?php echo preg_replace('/[^0-9]/', '', $item['whatsapp']); ?>" target="_blank">
                                    <?php echo htmlspecialchars($item['whatsapp']); ?>
                                </a>
                            </li>
                            <?php endif; ?>
                            <li class="mb-3">
                                <i class="fas fa-envelope fa-fw me-2"></i>
                                <strong>Email:</strong> 
                                <a href="mailto:<?php echo htmlspecialchars($item['email']); ?>">
                                    <?php echo htmlspecialchars($item['email']); ?>
                                </a>
                            </li>
                        </ul>
                        
                        <div class="mt-4">
                            <a href="contact_finder.php?item_id=<?php echo $item['id']; ?>&type=lost" class="btn btn-primary w-100">
                                <i class="fas fa-paper-plane me-2"></i>Contact with Message
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info mb-0">
                            <i class="fas fa-info-circle me-2"></i>
                            Please <a href="login.php">login</a> to view contact information and send messages to the person who reported this item.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="card shadow-sm">
                <div class="card-header bg-white py-3">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-share-alt me-2"></i>Share This Item
                    </h5>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-around">
                        <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo urlencode('http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']); ?>" target="_blank" class="btn btn-outline-primary">
                            <i class="fab fa-facebook-f"></i>
                        </a>
                        <a href="https://twitter.com/intent/tweet?url=<?php echo urlencode('http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']); ?>&text=<?php echo urlencode('Help find this lost item: ' . $item['title']); ?>" target="_blank" class="btn btn-outline-info">
                            <i class="fab fa-twitter"></i>
                        </a>
                        <a href="https://api.whatsapp.com/send?text=<?php echo urlencode('Help find this lost item: ' . $item['title'] . ' - ' . 'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']); ?>" target="_blank" class="btn btn-outline-success">
                            <i class="fab fa-whatsapp"></i>
                        </a>
                        <button class="btn btn-outline-secondary" onclick="copyToClipboard()">
                            <i class="fas fa-link"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<?php if ($isOwner): ?>
<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteModalLabel">Confirm Deletion</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                Are you sure you want to delete this lost item report? This action cannot be undone.
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form method="post" action="">
                    <button type="submit" name="delete_item" class="btn btn-danger">Delete</button>
                </form>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
function copyToClipboard() {
    const el = document.createElement('textarea');
    el.value = window.location.href;
    document.body.appendChild(el);
    el.select();
    document.execCommand('copy');
    document.body.removeChild(el);
    
    // Show a tooltip or alert
    alert('Link copied to clipboard!');
}
</script>

<?php include 'includes/footer.php'; ?>
<?php $conn->close(); ?>