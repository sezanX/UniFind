<?php
$pageTitle = 'Search Items';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

// Get categories and departments for filters
$categories = getCategories();
$departments = getDepartments();

// Initialize filters
$filters = [
    'type' => $_GET['type'] ?? 'all',
    'category' => $_GET['category'] ?? '',
    'department' => $_GET['department'] ?? '',
    'date_from' => $_GET['date_from'] ?? '',
    'date_to' => $_GET['date_to'] ?? '',
    'keyword' => $_GET['keyword'] ?? ''
];

// Handle date shortcuts
if (isset($_GET['date']) && $_GET['date'] === 'today') {
    $filters['date_from'] = date('Y-m-d');
    $filters['date_to'] = date('Y-m-d');
} elseif (isset($_GET['date']) && $_GET['date'] === 'week') {
    $filters['date_from'] = date('Y-m-d', strtotime('-7 days'));
    $filters['date_to'] = date('Y-m-d');
}

// Search items
$searchResults = searchItems($filters);

include 'includes/header.php';
?>

<div class="row">
    <div class="col-lg-3 mb-4">
        <div class="card shadow-sm search-filters">
            <div class="card-header bg-white py-3">
                <h5 class="card-title mb-0">
                    <i class="fas fa-filter me-2"></i>Search Filters
                </h5>
            </div>
            <div class="card-body">
                <form method="get" action="" id="search-form">
                    <div class="mb-3">
                        <label for="type" class="form-label">Item Type</label>
                        <select class="form-select" id="type" name="type">
                            <option value="all" <?php echo $filters['type'] === 'all' ? 'selected' : ''; ?>>All Items</option>
                            <option value="lost" <?php echo $filters['type'] === 'lost' ? 'selected' : ''; ?>>Lost Items</option>
                            <option value="found" <?php echo $filters['type'] === 'found' ? 'selected' : ''; ?>>Found Items</option>
                            <option value="matched" <?php echo $filters['type'] === 'matched' ? 'selected' : ''; ?>>Matched Items</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="category" class="form-label">Category</label>
                        <select class="form-select" id="category" name="category">
                            <option value="">All Categories</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo $category['id']; ?>" <?php echo $filters['category'] == $category['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($category['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="department" class="form-label">Department</label>
                        <select class="form-select" id="department" name="department">
                            <option value="">All Departments</option>
                            <?php foreach ($departments as $department): ?>
                                <option value="<?php echo $department['id']; ?>" <?php echo $filters['department'] == $department['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($department['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="date_from" class="form-label">Date From</label>
                        <input type="date" class="form-control date-picker" id="date_from" name="date_from" value="<?php echo $filters['date_from']; ?>">
                    </div>
                    
                    <div class="mb-3">
                        <label for="date_to" class="form-label">Date To</label>
                        <input type="date" class="form-control date-picker" id="date_to" name="date_to" value="<?php echo $filters['date_to']; ?>">
                    </div>
                    
                    <div class="mb-3">
                        <label for="keyword" class="form-label">Keyword</label>
                        <input type="text" class="form-control" id="keyword" name="keyword" value="<?php echo htmlspecialchars($filters['keyword']); ?>" placeholder="Search by title, description, location...">
                    </div>
                    
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search me-2"></i>Search
                        </button>
                        <a href="search.php" class="btn btn-outline-secondary">
                            <i class="fas fa-redo me-2"></i>Reset Filters
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-lg-9">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="mb-0">Search Results</h2>
            <div class="btn-group" role="group">
                <button type="button" id="view-grid" class="btn btn-outline-primary active">
                    <i class="fas fa-th-large"></i>
                </button>
                <button type="button" id="view-list" class="btn btn-outline-primary">
                    <i class="fas fa-list"></i>
                </button>
            </div>
        </div>
        
        <?php if (empty($searchResults)): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i> No items found matching your search criteria.
            </div>
        <?php else: ?>
            <div id="items-container" class="row grid-view">
                <?php foreach ($searchResults as $item): ?>
                    <div class="col-md-6 col-lg-4 mb-4">
                        <div class="card item-card h-100">
                            <?php if (!empty($item['image']) && file_exists(__DIR__ . '/uploads/' . $item['image'])): ?>
                                <img src="uploads/<?php echo htmlspecialchars($item['image']); ?>" class="card-img-top" alt="<?php echo htmlspecialchars($item['title']); ?>">
                            <?php else: ?>
                                <div class="card-img-top d-flex align-items-center justify-content-center bg-light">
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
                                <p class="card-text text-muted small mb-2">
                                    <i class="fas fa-university me-1"></i> <?php echo htmlspecialchars($item['department_name']); ?>
                                </p>
                                <p class="card-text"><?php echo substr(htmlspecialchars($item['description']), 0, 100) . (strlen($item['description']) > 100 ? '...' : ''); ?></p>
                            </div>
                            <div class="card-footer bg-white">
                                <a href="<?php echo $item['type']; ?>_item.php?id=<?php echo $item['id']; ?>" class="btn btn-sm btn-outline-primary w-100">View Details</a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>