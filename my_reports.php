<?php
$pageTitle = 'My Reports';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

// Redirect if not logged in
if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

// Get current user
$currentUser = getCurrentUser();

// Get user's reported items
$userItems = getUserItems($currentUser['id']);

// Handle item deletion if requested
if (isset($_POST['delete_item']) && isset($_POST['item_id']) && isset($_POST['item_type'])) {
    $itemId = (int)$_POST['item_id'];
    $itemType = $_POST['item_type'];
    
    if ($itemType === 'lost' || $itemType === 'found') {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if ($conn->connect_error) {
            die('Connection failed: ' . $conn->connect_error);
        }
        
        // Get the item to check if it belongs to the user and to get the image filename
        $tableName = $itemType . '_items';
        $checkSql = "SELECT image FROM $tableName WHERE id = ? AND user_id = ?";
        $checkStmt = $conn->prepare($checkSql);
        $checkStmt->bind_param('ii', $itemId, $currentUser['id']);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        
        if ($checkResult->num_rows > 0) {
            $item = $checkResult->fetch_assoc();
            
            // Delete the item from the database
            $deleteSql = "DELETE FROM $tableName WHERE id = ? AND user_id = ?";
            $deleteStmt = $conn->prepare($deleteSql);
            $deleteStmt->bind_param('ii', $itemId, $currentUser['id']);
            $deleteStmt->execute();
            
            if ($deleteStmt->affected_rows > 0) {
                // Delete the image file if it exists
                if (!empty($item['image']) && file_exists(__DIR__ . '/uploads/' . $item['image'])) {
                    unlink(__DIR__ . '/uploads/' . $item['image']);
                }
                
                // Redirect to prevent form resubmission
                header('Location: my_reports.php?deleted=1');
                exit;
            }
        }
        
        $conn->close();
    }
}

include 'includes/header.php';
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-clipboard-list me-2"></i>My Reports</h2>
        <div>
            <a href="report_lost.php" class="btn btn-outline-danger me-2">
                <i class="fas fa-search me-1"></i> Report Lost Item
            </a>
            <a href="report_found.php" class="btn btn-outline-success">
                <i class="fas fa-hand-holding me-1"></i> Report Found Item
            </a>
        </div>
    </div>
    
    <?php if (isset($_GET['deleted']) && $_GET['deleted'] == 1): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i> Item has been deleted successfully.
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    
    <ul class="nav nav-tabs mb-4" id="myReportsTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="all-tab" data-bs-toggle="tab" data-bs-target="#all" type="button" role="tab" aria-controls="all" aria-selected="true">
                All Reports <span class="badge bg-secondary ms-1"><?php echo count($userItems['lost_items']) + count($userItems['found_items']); ?></span>
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="lost-tab" data-bs-toggle="tab" data-bs-target="#lost" type="button" role="tab" aria-controls="lost" aria-selected="false">
                Lost Items <span class="badge bg-danger ms-1"><?php echo count($userItems['lost_items']); ?></span>
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="found-tab" data-bs-toggle="tab" data-bs-target="#found" type="button" role="tab" aria-controls="found" aria-selected="false">
                Found Items <span class="badge bg-success ms-1"><?php echo count($userItems['found_items']); ?></span>
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="matched-tab" data-bs-toggle="tab" data-bs-target="#matched" type="button" role="tab" aria-controls="matched" aria-selected="false">
                Matched Items <span class="badge bg-info ms-1"><?php echo count($userItems['matched_items']); ?></span>
            </button>
        </li>
    </ul>
    
    <div class="tab-content" id="myReportsTabsContent">
        <!-- All Reports Tab -->
        <div class="tab-pane fade show active" id="all" role="tabpanel" aria-labelledby="all-tab">
            <?php if (empty($userItems['lost_items']) && empty($userItems['found_items'])): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i> You haven't reported any items yet.
                </div>
            <?php else: ?>
                <div class="row">
                    <?php foreach (array_merge($userItems['lost_items'], $userItems['found_items']) as $item): ?>
                        <div class="col-md-6 col-lg-4 mb-4">
                            <div class="card item-card h-100">
                                <?php if (!empty($item['image']) && file_exists(__DIR__ . '/uploads/' . $item['image'])): ?>
                                    <img src="uploads/<?php echo htmlspecialchars($item['image']); ?>" class="card-img-top" alt="<?php echo htmlspecialchars($item['title']); ?>">
                                <?php else: ?>
                                    <div class="card-img-top d-flex align-items-center justify-content-center bg-light" style="height: 200px;">
                                        <i class="fas fa-image fa-3x text-muted"></i>
                                    </div>
                                <?php endif; ?>
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <h5 class="card-title mb-0"><?php echo htmlspecialchars($item['title']); ?></h5>
                                        <span class="badge badge-<?php echo $item['type']; ?>"><?php echo ucfirst($item['type']); ?></span>
                                    </div>
                                    <p class="card-text text-muted small mb-2">
                                        <i class="fas fa-tag me-1"></i> <?php echo htmlspecialchars($item['category_name']); ?>
                                    </p>
                                    <p class="card-text text-muted small mb-2">
                                        <i class="fas fa-map-marker-alt me-1"></i> <?php echo htmlspecialchars($item['location']); ?>
                                    </p>
                                    <p class="card-text text-muted small mb-2">
                                        <i class="fas fa-calendar-alt me-1"></i> 
                                        <?php echo $item['type'] === 'lost' ? 'Lost on: ' . formatDate($item['date_lost']) : 'Found on: ' . formatDate($item['date_found']); ?>
                                    </p>
                                    <p class="card-text text-muted small mb-3">
                                        <i class="fas fa-clock me-1"></i> Status: 
                                        <span class="badge bg-<?php 
                                            echo $item['status'] === 'active' ? 'success' : 
                                                ($item['status'] === 'pending_match' ? 'warning' : 
                                                    ($item['status'] === 'matched' ? 'info' : 'secondary')); 
                                        ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $item['status'])); ?>
                                        </span>
                                    </p>
                                </div>
                                <div class="card-footer bg-white d-flex justify-content-between">
                                    <a href="<?php echo $item['type']; ?>_item.php?id=<?php echo $item['id']; ?>" class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-eye me-1"></i> View
                                    </a>
                                    <div>
                                        <a href="edit_<?php echo $item['type']; ?>.php?id=<?php echo $item['id']; ?>" class="btn btn-sm btn-outline-secondary me-1">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteModal<?php echo $item['type'] . $item['id']; ?>">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Delete Modal for each item -->
                        <div class="modal fade" id="deleteModal<?php echo $item['type'] . $item['id']; ?>" tabindex="-1" aria-labelledby="deleteModalLabel<?php echo $item['type'] . $item['id']; ?>" aria-hidden="true">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title" id="deleteModalLabel<?php echo $item['type'] . $item['id']; ?>">Confirm Deletion</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body">
                                        Are you sure you want to delete this <?php echo $item['type']; ?> item report? This action cannot be undone.
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                        <form method="post" action="">
                                            <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
                                            <input type="hidden" name="item_type" value="<?php echo $item['type']; ?>">
                                            <button type="submit" name="delete_item" class="btn btn-danger">Delete</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Lost Items Tab -->
        <div class="tab-pane fade" id="lost" role="tabpanel" aria-labelledby="lost-tab">
            <?php if (empty($userItems['lost_items'])): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i> You haven't reported any lost items yet.
                </div>
            <?php else: ?>
                <div class="row">
                    <?php foreach ($userItems['lost_items'] as $item): ?>
                        <div class="col-md-6 col-lg-4 mb-4">
                            <div class="card item-card h-100">
                                <?php if (!empty($item['image']) && file_exists(__DIR__ . '/uploads/' . $item['image'])): ?>
                                    <img src="uploads/<?php echo htmlspecialchars($item['image']); ?>" class="card-img-top" alt="<?php echo htmlspecialchars($item['title']); ?>">
                                <?php else: ?>
                                    <div class="card-img-top d-flex align-items-center justify-content-center bg-light" style="height: 200px;">
                                        <i class="fas fa-image fa-3x text-muted"></i>
                                    </div>
                                <?php endif; ?>
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <h5 class="card-title mb-0"><?php echo htmlspecialchars($item['title']); ?></h5>
                                        <span class="badge badge-lost">Lost</span>
                                    </div>
                                    <p class="card-text text-muted small mb-2">
                                        <i class="fas fa-tag me-1"></i> <?php echo htmlspecialchars($item['category_name']); ?>
                                    </p>
                                    <p class="card-text text-muted small mb-2">
                                        <i class="fas fa-map-marker-alt me-1"></i> <?php echo htmlspecialchars($item['location']); ?>
                                    </p>
                                    <p class="card-text text-muted small mb-2">
                                        <i class="fas fa-calendar-alt me-1"></i> Lost on: <?php echo formatDate($item['date_lost']); ?>
                                    </p>
                                    <p class="card-text text-muted small mb-3">
                                        <i class="fas fa-clock me-1"></i> Status: 
                                        <span class="badge bg-<?php 
                                            echo $item['status'] === 'active' ? 'success' : 
                                                ($item['status'] === 'pending_match' ? 'warning' : 
                                                    ($item['status'] === 'matched' ? 'info' : 'secondary')); 
                                        ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $item['status'])); ?>
                                        </span>
                                    </p>
                                </div>
                                <div class="card-footer bg-white d-flex justify-content-between">
                                    <a href="lost_item.php?id=<?php echo $item['id']; ?>" class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-eye me-1"></i> View
                                    </a>
                                    <div>
                                        <a href="edit_lost.php?id=<?php echo $item['id']; ?>" class="btn btn-sm btn-outline-secondary me-1">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteModallost<?php echo $item['id']; ?>">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Delete Modal for each lost item -->
                        <div class="modal fade" id="deleteModallost<?php echo $item['id']; ?>" tabindex="-1" aria-labelledby="deleteModalLabellost<?php echo $item['id']; ?>" aria-hidden="true">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title" id="deleteModalLabellost<?php echo $item['id']; ?>">Confirm Deletion</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body">
                                        Are you sure you want to delete this lost item report? This action cannot be undone.
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                        <form method="post" action="">
                                            <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
                                            <input type="hidden" name="item_type" value="lost">
                                            <button type="submit" name="delete_item" class="btn btn-danger">Delete</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Found Items Tab -->
        <div class="tab-pane fade" id="found" role="tabpanel" aria-labelledby="found-tab">
            <?php if (empty($userItems['found_items'])): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i> You haven't reported any found items yet.
                </div>
            <?php else: ?>
                <div class="row">
                    <?php foreach ($userItems['found_items'] as $item): ?>
                        <div class="col-md-6 col-lg-4 mb-4">
                            <div class="card item-card h-100">
                                <?php if (!empty($item['image']) && file_exists(__DIR__ . '/uploads/' . $item['image'])): ?>
                                    <img src="uploads/<?php echo htmlspecialchars($item['image']); ?>" class="card-img-top" alt="<?php echo htmlspecialchars($item['title']); ?>">
                                <?php else: ?>
                                    <div class="card-img-top d-flex align-items-center justify-content-center bg-light" style="height: 200px;">
                                        <i class="fas fa-image fa-3x text-muted"></i>
                                    </div>
                                <?php endif; ?>
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <h5 class="card-title mb-0"><?php echo htmlspecialchars($item['title']); ?></h5>
                                        <span class="badge badge-found">Found</span>
                                    </div>
                                    <p class="card-text text-muted small mb-2">
                                        <i class="fas fa-tag me-1"></i> <?php echo htmlspecialchars($item['category_name']); ?>
                                    </p>
                                    <p class="card-text text-muted small mb-2">
                                        <i class="fas fa-map-marker-alt me-1"></i> <?php echo htmlspecialchars($item['location']); ?>
                                    </p>
                                    <p class="card-text text-muted small mb-2">
                                        <i class="fas fa-calendar-alt me-1"></i> Found on: <?php echo formatDate($item['date_found']); ?>
                                    </p>
                                    <p class="card-text text-muted small mb-3">
                                        <i class="fas fa-clock me-1"></i> Status: 
                                        <span class="badge bg-<?php 
                                            echo $item['status'] === 'active' ? 'success' : 
                                                ($item['status'] === 'pending_match' ? 'warning' : 
                                                    ($item['status'] === 'matched' ? 'info' : 'secondary')); 
                                        ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $item['status'])); ?>
                                        </span>
                                    </p>
                                </div>
                                <div class="card-footer bg-white d-flex justify-content-between">
                                    <a href="found_item.php?id=<?php echo $item['id']; ?>" class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-eye me-1"></i> View
                                    </a>
                                    <div>
                                        <a href="edit_found.php?id=<?php echo $item['id']; ?>" class="btn btn-sm btn-outline-secondary me-1">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteModalfound<?php echo $item['id']; ?>">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Delete Modal for each found item -->
                        <div class="modal fade" id="deleteModalfound<?php echo $item['id']; ?>" tabindex="-1" aria-labelledby="deleteModalLabelfound<?php echo $item['id']; ?>" aria-hidden="true">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title" id="deleteModalLabelfound<?php echo $item['id']; ?>">Confirm Deletion</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body">
                                        Are you sure you want to delete this found item report? This action cannot be undone.
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                        <form method="post" action="">
                                            <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
                                            <input type="hidden" name="item_type" value="found">
                                            <button type="submit" name="delete_item" class="btn btn-danger">Delete</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Matched Items Tab -->
        <div class="tab-pane fade" id="matched" role="tabpanel" aria-labelledby="matched-tab">
            <?php if (empty($userItems['matched_items'])): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i> You don't have any matched items yet.
                </div>
            <?php else: ?>
                <div class="row">
                    <?php foreach ($userItems['matched_items'] as $match): ?>
                        <div class="col-md-6 mb-4">
                            <div class="card shadow-sm">
                                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                                    <h5 class="card-title mb-0">Match #<?php echo $match['id']; ?></h5>
                                    <span class="badge bg-info">Matched</span>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-6 mb-3 mb-md-0">
                                            <div class="card h-100">
                                                <div class="card-header bg-light py-2">
                                                    <h6 class="card-title mb-0 d-flex align-items-center">
                                                        <span class="badge badge-lost me-2">Lost</span>
                                                        <?php echo htmlspecialchars($match['lost_title']); ?>
                                                    </h6>
                                                </div>
                                                <div class="card-body">
                                                    <p class="card-text small mb-1">
                                                        <i class="fas fa-calendar-alt me-1"></i> Lost on: <?php echo formatDate($match['date_lost']); ?>
                                                    </p>
                                                    <p class="card-text small mb-1">
                                                        <i class="fas fa-map-marker-alt me-1"></i> <?php echo htmlspecialchars($match['lost_location']); ?>
                                                    </p>
                                                    <p class="card-text small">
                                                        <i class="fas fa-tag me-1"></i> <?php echo htmlspecialchars($match['category_name']); ?>
                                                    </p>
                                                </div>
                                                <div class="card-footer bg-white">
                                                    <a href="lost_item.php?id=<?php echo $match['lost_item_id']; ?>" class="btn btn-sm btn-outline-primary w-100">
                                                        <i class="fas fa-eye me-1"></i> View Details
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="card h-100">
                                                <div class="card-header bg-light py-2">
                                                    <h6 class="card-title mb-0 d-flex align-items-center">
                                                        <span class="badge badge-found me-2">Found</span>
                                                        <?php echo htmlspecialchars($match['found_title']); ?>
                                                    </h6>
                                                </div>
                                                <div class="card-body">
                                                    <p class="card-text small mb-1">
                                                        <i class="fas fa-calendar-alt me-1"></i> Found on: <?php echo formatDate($match['date_found']); ?>
                                                    </p>
                                                    <p class="card-text small mb-1">
                                                        <i class="fas fa-map-marker-alt me-1"></i> <?php echo htmlspecialchars($match['found_location']); ?>
                                                    </p>
                                                    <p class="card-text small">
                                                        <i class="fas fa-user me-1"></i> Found by: <?php echo htmlspecialchars($match['finder_name']); ?>
                                                    </p>
                                                </div>
                                                <div class="card-footer bg-white">
                                                    <a href="found_item.php?id=<?php echo $match['found_item_id']; ?>" class="btn btn-sm btn-outline-primary w-100">
                                                        <i class="fas fa-eye me-1"></i> View Details
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="mt-3">
                                        <p class="mb-1"><strong>Match Status:</strong> 
                                            <span class="badge bg-<?php 
                                                echo $match['status'] === 'pending_review' ? 'warning' : 
                                                    ($match['status'] === 'confirmed' ? 'success' : 
                                                        ($match['status'] === 'returned' ? 'info' : 'secondary')); 
                                            ?>">
                                                <?php echo ucfirst(str_replace('_', ' ', $match['status'])); ?>
                                            </span>
                                        </p>
                                        <p class="small text-muted mb-0">Matched on: <?php echo formatDate($match['created_at']); ?></p>
                                    </div>
                                </div>
                                <?php if ($match['status'] === 'confirmed'): ?>
                                <div class="card-footer bg-white">
                                    <a href="contact_finder.php?item_id=<?php echo $match['found_item_id']; ?>&type=found" class="btn btn-primary w-100">
                                        <i class="fas fa-paper-plane me-2"></i>Contact Finder
                                    </a>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>