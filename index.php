<?php
$pageTitle = 'Home';
require_once 'includes/header.php';

// Get item statistics
$stats = getItemStats();

// Get recent activity
$recent_activity = getRecentActivity(5);

// Get statistics for dashboard
$statistics = getStatistics();

// Get recent items
$recentItems = searchItems(['type' => 'all', 'limit' => 6]);
?>

<!-- Hero Section -->
<section class="bg-primary text-white text-center py-5 rounded shadow-sm mb-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <h1 class="display-4 fw-bold mb-3">UniFind</h1>
                <p class="lead mb-4">Lost and Found Management System for Northern University of Business and Technology Khulna</p>
                <div class="d-grid gap-2 d-sm-flex justify-content-sm-center">
                    <?php if (!isLoggedIn()): ?>
                        <a href="login.php" class="btn btn-light btn-lg px-4 me-sm-3">
                            <i class="fas fa-sign-in-alt me-2"></i>Login
                        </a>
                        <a href="signup.php" class="btn btn-outline-light btn-lg px-4">
                            <i class="fas fa-user-plus me-2"></i>Sign Up
                        </a>
                    <?php else: ?>
                        <a href="report_lost.php" class="btn btn-light btn-lg px-4 me-sm-3">
                            <i class="fas fa-exclamation-circle me-2"></i>Report Lost Item
                        </a>
                        <a href="report_found.php" class="btn btn-outline-light btn-lg px-4">
                            <i class="fas fa-hand-holding me-2"></i>Report Found Item
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Stats Section -->
<section class="mb-5">
    <div class="container">
        <h2 class="fw-bold mb-4">Item Statistics</h2>
        <div class="row">
            <div class="col-md-3 mb-4">
                <a href="lost_item.php" class="text-decoration-none">
                    <div class="stats-card lost">
                        <div class="icon text-danger">
                            <i class="fas fa-search"></i>
                        </div>
                        <div class="count text-danger"><?php echo $statistics['total_lost']; ?></div>
                        <div class="label">Total Lost Items</div>
                    </div>
                </a>
            </div>
            <div class="col-md-3 mb-4">
                <a href="found_item.php" class="text-decoration-none">
                    <div class="stats-card found">
                        <div class="icon text-success">
                            <i class="fas fa-hand-holding"></i>
                        </div>
                        <div class="count text-success"><?php echo $statistics['total_found']; ?></div>
                        <div class="label">Total Found Items</div>
                    </div>
                </a>
            </div>
            <div class="col-md-3 mb-4">
                <a href="#" class="text-decoration-none">
                    <div class="stats-card matched">
                        <div class="icon text-warning">
                            <i class="fas fa-exchange-alt"></i>
                        </div>
                        <div class="count text-warning"><?php echo $statistics['total_matched']; ?></div>
                        <div class="label">Matched Items</div>
                    </div>
                </a>
            </div>
            
            <!-- Registered users section removed as requested -->
        </div>
        
        <div class="row mt-3">
            <div class="col-md-4 mb-4">
                <a href="lost_item.php?date=today" class="text-decoration-none">
                    <div class="card shadow-sm h-100">
                        <div class="card-body text-center">
                            <h5 class="card-title">Lost Today</h5>
                            <h2 class="display-4 text-danger"><?php echo $stats['lost_today']; ?></h2>
                            <p class="card-text text-muted">Items reported lost in the last 24 hours</p>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col-md-4 mb-4">
                <a href="found_item.php?date=today" class="text-decoration-none">
                    <div class="card shadow-sm h-100">
                        <div class="card-body text-center">
                            <h5 class="card-title">Found Today</h5>
                            <h2 class="display-4 text-success"><?php echo $stats['found_today']; ?></h2>
                            <p class="card-text text-muted">Items reported found in the last 24 hours</p>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col-md-4 mb-4">
                <a href="lost_item.php?date=week" class="text-decoration-none">
                    <div class="card shadow-sm h-100">
                        <div class="card-body text-center">
                            <h5 class="card-title">This Week</h5>
                            <h2 class="display-4 text-primary"><?php echo $stats['lost_week'] + $stats['found_week']; ?></h2>
                            <p class="card-text text-muted">Total items reported in the last 7 days</p>
                        </div>
                    </div>
                </a>
            </div>
        </div>
    </div>
</section>

<!-- Recent Items Section -->
<section class="mb-5 py-4 bg-light rounded shadow-sm">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="fw-bold">Recent Items</h2>
            <div>
                <a href="lost_item.php" class="btn btn-outline-danger me-2">All Lost Items</a>
                <a href="found_item.php" class="btn btn-outline-success">All Found Items</a>
            </div>
        </div>
        
        <?php if (empty($recentItems)): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i> No items have been reported yet.
            </div>
        <?php else: ?>
            <div class="row">
                <?php foreach ($recentItems as $item): ?>
                    <div class="col-md-6 col-lg-4 mb-4">
                        <div class="card item-card h-100 shadow-sm hover-shadow">
                            <div class="position-absolute top-0 end-0 m-2">
                                <span class="badge bg-<?php echo $item['type'] === 'lost' ? 'danger' : 'success'; ?> rounded-pill px-3 py-2">
                                    <i class="fas <?php echo $item['type'] === 'lost' ? 'fa-search' : 'fa-hand-holding'; ?> me-1"></i>
                                    <?php echo ucfirst($item['type']); ?>
                                </span>
                            </div>
                            <?php if (!empty($item['image']) && file_exists(__DIR__ . '/uploads/' . $item['image'])): ?>
                                <div class="card-img-container">
                                    <img src="uploads/<?php echo htmlspecialchars($item['image']); ?>" class="card-img-top" alt="<?php echo htmlspecialchars($item['title']); ?>">
                                </div>
                            <?php else: ?>
                                <div class="card-img-top d-flex align-items-center justify-content-center bg-light py-5">
                                    <i class="fas fa-image fa-3x text-muted"></i>
                                </div>
                            <?php endif; ?>
                            <div class="card-body">
                                <h5 class="card-title fw-bold"><?php echo htmlspecialchars($item['title']); ?></h5>
                                <p class="card-text text-muted small mb-2">
                                    <i class="fas fa-tag me-1"></i> <?php echo htmlspecialchars($item['category_name']); ?>
                                </p>
                                <p class="card-text text-muted small mb-2">
                                    <i class="fas fa-map-marker-alt me-1"></i> <?php echo htmlspecialchars($item['location']); ?>
                                </p>
                                <p class="card-text text-muted small mb-3">
                                    <i class="fas fa-calendar-alt me-1"></i> 
                                    <?php echo $item['type'] === 'lost' ? 'Lost on: ' . formatDate($item['date_lost']) : 'Found on: ' . formatDate($item['date_found']); ?>
                                </p>
                                <p class="card-text"><?php echo substr(htmlspecialchars($item['description']), 0, 100) . (strlen($item['description']) > 100 ? '...' : ''); ?></p>
                            </div>
                            <div class="card-footer bg-white border-0">
                                <a href="<?php echo $item['type']; ?>_item.php?id=<?php echo $item['id']; ?>" class="btn btn-primary w-100">
                                    <i class="fas fa-eye me-1"></i> View Details
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <div class="text-center mt-4">
            <a href="search.php" class="btn btn-lg btn-outline-primary">
                <i class="fas fa-search me-1"></i> Search All Items
            </a>
        </div>
    </div>
</section>

<!-- Recent Activity Section -->
<section class="mb-5">
    <div class="container">
        <h2 class="fw-bold mb-4">Recent Activity</h2>
        <div class="row">
            <div class="col-lg-8">
                <div class="card shadow-sm">
                    <div class="card-header bg-white">
                        <ul class="nav nav-tabs card-header-tabs" id="activityTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="all-tab" data-bs-toggle="tab" data-bs-target="#all" type="button" role="tab" aria-controls="all" aria-selected="true">All Activity</button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="lost-tab" data-bs-toggle="tab" data-bs-target="#lost" type="button" role="tab" aria-controls="lost" aria-selected="false">Lost Items</button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="found-tab" data-bs-toggle="tab" data-bs-target="#found" type="button" role="tab" aria-controls="found" aria-selected="false">Found Items</button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="matched-tab" data-bs-toggle="tab" data-bs-target="#matched" type="button" role="tab" aria-controls="matched" aria-selected="false">Matches</button>
                            </li>
                        </ul>
                    </div>
                    <div class="card-body">
                        <div class="tab-content" id="activityTabsContent">
                            <div class="tab-pane fade show active" id="all" role="tabpanel" aria-labelledby="all-tab">
                                <div class="activity-timeline">
                                    <?php if (!empty($recent_activity)): ?>
                                        <?php foreach ($recent_activity as $activity): ?>
                                            <div class="activity-item d-flex pb-3 mb-3 border-bottom">
                                                <div class="activity-icon me-3">
                                                    <?php if ($activity['type'] == 'lost'): ?>
                                                        <div class="icon-circle bg-danger-light text-danger">
                                                            <i class="fas fa-search"></i>
                                                        </div>
                                                    <?php elseif ($activity['type'] == 'found'): ?>
                                                        <div class="icon-circle bg-success-light text-success">
                                                            <i class="fas fa-hand-holding"></i>
                                                        </div>
                                                    <?php else: ?>
                                                        <div class="icon-circle bg-warning-light text-warning">
                                                            <i class="fas fa-exchange-alt"></i>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="activity-content">
                                                    <div class="d-flex justify-content-between">
                                                        <h6 class="mb-1"><?php echo htmlspecialchars($activity['title']); ?></h6>
                                                        <small class="text-muted"><?php echo timeElapsed($activity['date']); ?></small>
                                                    </div>
                                                    <p class="mb-1 text-muted small"><?php echo $activity['message']; ?></p>
                                                    <?php if (isset($activity['link'])): ?>
                                                        <a href="<?php echo $activity['link']; ?>" class="btn btn-sm btn-outline-primary">View Details</a>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <div class="text-center py-4">
                                            <i class="fas fa-info-circle fa-2x text-muted mb-3"></i>
                                            <p>No recent activity to display.</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="tab-pane fade" id="lost" role="tabpanel" aria-labelledby="lost-tab">
                                <div class="activity-timeline">
                                    <?php if (!empty($recent_activity)): ?>
                                        <?php foreach ($recent_activity as $activity): ?>
                                            <?php if ($activity['type'] == 'lost'): ?>
                                            <div class="activity-item d-flex pb-3 mb-3 border-bottom">
                                                <div class="activity-icon me-3">
                                                    <div class="icon-circle bg-danger-light text-danger">
                                                        <i class="fas fa-search"></i>
                                                    </div>
                                                </div>
                                                <div class="activity-content">
                                                    <div class="d-flex justify-content-between">
                                                        <h6 class="mb-1"><?php echo htmlspecialchars($activity['title']); ?></h6>
                                                        <small class="text-muted"><?php echo timeElapsed($activity['date']); ?></small>
                                                    </div>
                                                    <p class="mb-1 text-muted small"><?php echo $activity['message']; ?></p>
                                                    <?php if (isset($activity['link'])): ?>
                                                        <a href="<?php echo $activity['link']; ?>" class="btn btn-sm btn-outline-primary">View Details</a>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <div class="text-center py-4">
                                            <i class="fas fa-search fa-2x text-muted mb-3"></i>
                                            <p>No lost items reported recently.</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="tab-pane fade" id="found" role="tabpanel" aria-labelledby="found-tab">
                                <div class="activity-timeline">
                                    <?php if (!empty($recent_activity)): ?>
                                        <?php foreach ($recent_activity as $activity): ?>
                                            <?php if ($activity['type'] == 'found'): ?>
                                            <div class="activity-item d-flex pb-3 mb-3 border-bottom">
                                                <div class="activity-icon me-3">
                                                    <div class="icon-circle bg-success-light text-success">
                                                        <i class="fas fa-hand-holding"></i>
                                                    </div>
                                                </div>
                                                <div class="activity-content">
                                                    <div class="d-flex justify-content-between">
                                                        <h6 class="mb-1"><?php echo htmlspecialchars($activity['title']); ?></h6>
                                                        <small class="text-muted"><?php echo timeElapsed($activity['date']); ?></small>
                                                    </div>
                                                    <p class="mb-1 text-muted small"><?php echo $activity['message']; ?></p>
                                                    <?php if (isset($activity['link'])): ?>
                                                        <a href="<?php echo $activity['link']; ?>" class="btn btn-sm btn-outline-primary">View Details</a>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <div class="text-center py-4">
                                            <i class="fas fa-hand-holding fa-2x text-muted mb-3"></i>
                                            <p>No found items reported recently.</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="tab-pane fade" id="matched" role="tabpanel" aria-labelledby="matched-tab">
                                <div class="activity-timeline">
                                    <?php if (!empty($recent_activity)): ?>
                                        <?php foreach ($recent_activity as $activity): ?>
                                            <?php if ($activity['type'] == 'matched'): ?>
                                            <div class="activity-item d-flex pb-3 mb-3 border-bottom">
                                                <div class="activity-icon me-3">
                                                    <div class="icon-circle bg-warning-light text-warning">
                                                        <i class="fas fa-exchange-alt"></i>
                                                    </div>
                                                </div>
                                                <div class="activity-content">
                                                    <div class="d-flex justify-content-between">
                                                        <h6 class="mb-1"><?php echo htmlspecialchars($activity['title']); ?></h6>
                                                        <small class="text-muted"><?php echo timeElapsed($activity['date']); ?></small>
                                                    </div>
                                                    <p class="mb-1 text-muted small"><?php echo $activity['message']; ?></p>
                                                    <?php if (isset($activity['link'])): ?>
                                                        <a href="<?php echo $activity['link']; ?>" class="btn btn-sm btn-outline-primary">View Details</a>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <div class="text-center py-4">
                                            <i class="fas fa-exchange-alt fa-2x text-muted mb-3"></i>
                                            <p>No matches made recently.</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4">
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="card-title mb-0">How It Works</h5>
                    </div>
                    <div class="card-body">
                        <div class="how-it-works-step d-flex align-items-center mb-3">
                            <div class="step-icon me-3 bg-danger-light text-danger rounded-circle">
                                <i class="fas fa-search"></i>
                            </div>
                            <div>
                                <h6 class="mb-1">Report Lost Item</h6>
                                <p class="small text-muted mb-0">Fill out a simple form with details about your lost item.</p>
                            </div>
                        </div>
                        <div class="how-it-works-step d-flex align-items-center mb-3">
                            <div class="step-icon me-3 bg-success-light text-success rounded-circle">
                                <i class="fas fa-hand-holding"></i>
                            </div>
                            <div>
                                <h6 class="mb-1">Report Found Item</h6>
                                <p class="small text-muted mb-0">Report items you've found to help reunite them with owners.</p>
                            </div>
                        </div>
                        <div class="how-it-works-step d-flex align-items-center">
                            <div class="step-icon me-3 bg-warning-light text-warning rounded-circle">
                                <i class="fas fa-exchange-alt"></i>
                            </div>
                            <div>
                                <h6 class="mb-1">Get Matched</h6>
                                <p class="small text-muted mb-0">Our system automatically matches lost and found items.</p>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer bg-white text-center">
                        <a href="about.php" class="btn btn-outline-primary">Learn More</a>
                    </div>
                </div>
                
                <div class="card shadow-sm">
                    <div class="card-header bg-success text-white">
                        <h5 class="card-title mb-0">Report an Item</h5>
                    </div>
                    <div class="card-body">
                        <p>Have you lost or found something on campus?</p>
                        <div class="d-grid gap-2">
                            <a href="report_lost.php" class="btn btn-danger">
                                <i class="fas fa-search me-2"></i> Report Lost Item
                            </a>
                            <a href="report_found.php" class="btn btn-success">
                                <i class="fas fa-hand-holding me-2"></i> Report Found Item
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- How It Works Section -->
<section class="mb-5 bg-primary bg-gradient text-white p-5 rounded shadow">
    <div class="container">
        <div class="row align-items-center mb-4">
            <div class="col-md-6">
                <h2 class="fw-bold mb-3">How UniFind Works</h2>
                <p class="lead">Our platform makes it easy to report, find, and recover lost items on campus.</p>
            </div>
            <div class="col-md-6 text-md-end">
                <a href="about.php" class="btn btn-light btn-lg">Learn More About Us</a>
            </div>
        </div>
        
        <div class="row g-4">
            <div class="col-md-4">
                <div class="card bg-white text-dark h-100 border-0 shadow-sm hover-lift">
                    <div class="card-body text-center p-4">
                        <div class="icon-circle bg-danger-light text-danger mx-auto mb-4">
                            <i class="fas fa-search fa-2x"></i>
                        </div>
                        <h4 class="card-title">Report Lost Item</h4>
                        <p class="card-text">Lost something? Fill out a simple form with details about your lost item including when and where you last saw it.</p>
                        <a href="report_lost.php" class="btn btn-outline-danger mt-3">
                            <i class="fas fa-arrow-right me-2"></i> Report Lost Item
                        </a>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card bg-white text-dark h-100 border-0 shadow-sm hover-lift">
                    <div class="card-body text-center p-4">
                        <div class="icon-circle bg-success-light text-success mx-auto mb-4">
                            <i class="fas fa-hand-holding fa-2x"></i>
                        </div>
                        <h4 class="card-title">Report Found Item</h4>
                        <p class="card-text">Found something? Report it with details and photos so we can help reunite it with its rightful owner quickly.</p>
                        <a href="report_found.php" class="btn btn-outline-success mt-3">
                            <i class="fas fa-arrow-right me-2"></i> Report Found Item
                        </a>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card bg-white text-dark h-100 border-0 shadow-sm hover-lift">
                    <div class="card-body text-center p-4">
                        <div class="icon-circle bg-warning-light text-warning mx-auto mb-4">
                            <i class="fas fa-exchange-alt fa-2x"></i>
                        </div>
                        <h4 class="card-title">Get Matched</h4>
                        <p class="card-text">Our intelligent system automatically matches lost and found items based on descriptions, locations, and dates.</p>
                        <a href="search.php" class="btn btn-outline-warning mt-3">
                            <i class="fas fa-arrow-right me-2"></i> Search Items
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="text-center mt-5">
            <p class="lead mb-0">Join thousands of campus members who have successfully recovered their lost items!</p>
        </div>
    </div>
</section>

<?php include 'includes/footer.php'; ?>