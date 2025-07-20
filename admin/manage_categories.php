<?php
$pageTitle = 'Manage Categories';
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

// Initialize variables
$message = '';
$messageType = '';
$categoryName = '';
$categoryId = 0;
$isEditing = false;

// Process category actions
// Add new category
if (isset($_POST['add_category'])) {
    $categoryName = trim($_POST['category_name']);
    
    if (empty($categoryName)) {
        $message = 'Category name cannot be empty!';
        $messageType = 'danger';
    } else {
        // Check if category already exists
        $stmt = $conn->prepare("SELECT id FROM categories WHERE name = ?");
        $stmt->bind_param("s", $categoryName);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $message = 'Category already exists!';
            $messageType = 'danger';
        } else {
            // Insert new category
            $stmt = $conn->prepare("INSERT INTO categories (name) VALUES (?)");
            $stmt->bind_param("s", $categoryName);
            $stmt->execute();
            
            if ($stmt->affected_rows > 0) {
                $message = 'Category added successfully!';
                $messageType = 'success';
                $categoryName = ''; // Clear the form
            } else {
                $message = 'Failed to add category!';
                $messageType = 'danger';
            }
        }
    }
}

// Edit category
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $categoryId = $_GET['edit'];
    $isEditing = true;
    
    // Get category details
    $stmt = $conn->prepare("SELECT * FROM categories WHERE id = ?");
    $stmt->bind_param("i", $categoryId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $category = $result->fetch_assoc();
        $categoryName = $category['name'];
    } else {
        $message = 'Category not found!';
        $messageType = 'danger';
        $isEditing = false;
    }
}

// Update category
if (isset($_POST['update_category'])) {
    $categoryId = $_POST['category_id'];
    $categoryName = trim($_POST['category_name']);
    
    if (empty($categoryName)) {
        $message = 'Category name cannot be empty!';
        $messageType = 'danger';
    } else {
        // Check if category already exists with the same name but different ID
        $stmt = $conn->prepare("SELECT id FROM categories WHERE name = ? AND id != ?");
        $stmt->bind_param("si", $categoryName, $categoryId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $message = 'Category with this name already exists!';
            $messageType = 'danger';
        } else {
            // Update category
            $stmt = $conn->prepare("UPDATE categories SET name = ? WHERE id = ?");
            $stmt->bind_param("si", $categoryName, $categoryId);
            $stmt->execute();
            
            if ($stmt->affected_rows > 0 || $conn->affected_rows > 0) {
                $message = 'Category updated successfully!';
                $messageType = 'success';
                $isEditing = false;
                $categoryName = ''; // Clear the form
                $categoryId = 0;
            } else {
                $message = 'No changes made or failed to update category!';
                $messageType = 'warning';
            }
        }
    }
}

// Delete category
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $categoryId = $_GET['delete'];
    
    // Check if category is being used in lost or found items
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM lost_items WHERE category_id = ?");
    $stmt->bind_param("i", $categoryId);
    $stmt->execute();
    $result = $stmt->get_result();
    $lostItemsCount = $result->fetch_assoc()['count'];
    
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM found_items WHERE category_id = ?");
    $stmt->bind_param("i", $categoryId);
    $stmt->execute();
    $result = $stmt->get_result();
    $foundItemsCount = $result->fetch_assoc()['count'];
    
    if ($lostItemsCount > 0 || $foundItemsCount > 0) {
        $message = 'Cannot delete category! It is being used in ' . $lostItemsCount . ' lost items and ' . $foundItemsCount . ' found items.';
        $messageType = 'danger';
    } else {
        // Delete category
        $stmt = $conn->prepare("DELETE FROM categories WHERE id = ?");
        $stmt->bind_param("i", $categoryId);
        $stmt->execute();
        
        if ($stmt->affected_rows > 0) {
            $message = 'Category deleted successfully!';
            $messageType = 'success';
        } else {
            $message = 'Failed to delete category!';
            $messageType = 'danger';
        }
    }
}

// Get all categories with item counts
$categories = [];
$sql = "SELECT c.id, c.name, 
        (SELECT COUNT(*) FROM lost_items WHERE category_id = c.id) as lost_count, 
        (SELECT COUNT(*) FROM found_items WHERE category_id = c.id) as found_count 
        FROM categories c 
        ORDER BY c.name";
$result = $conn->query($sql);

while ($row = $result->fetch_assoc()) {
    $categories[] = $row;
}

// Include admin header
include 'includes/admin_header.php';
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0 text-gray-800">Manage Categories</h1>
    </div>
    
    <?php if (!empty($message)): ?>
        <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
            <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    
    <div class="row">
        <div class="col-md-4">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white py-3">
                    <h5 class="card-title mb-0"><?php echo $isEditing ? 'Edit Category' : 'Add New Category'; ?></h5>
                </div>
                <div class="card-body">
                    <form action="" method="POST">
                        <?php if ($isEditing): ?>
                            <input type="hidden" name="category_id" value="<?php echo $categoryId; ?>">
                        <?php endif; ?>
                        
                        <div class="mb-3">
                            <label for="category_name" class="form-label">Category Name</label>
                            <input type="text" class="form-control" id="category_name" name="category_name" value="<?php echo htmlspecialchars($categoryName); ?>" required>
                        </div>
                        
                        <div class="d-grid gap-2">
                            <?php if ($isEditing): ?>
                                <button type="submit" name="update_category" class="btn btn-primary">
                                    <i class="fas fa-save me-1"></i> Update Category
                                </button>
                                <a href="manage_categories.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-times me-1"></i> Cancel
                                </a>
                            <?php else: ?>
                                <button type="submit" name="add_category" class="btn btn-success">
                                    <i class="fas fa-plus me-1"></i> Add Category
                                </button>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
            
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white py-3">
                    <h5 class="card-title mb-0">Category Statistics</h5>
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-md-4 mb-3">
                            <div class="stat-item">
                                <h2 class="text-primary"><?php echo count($categories); ?></h2>
                                <p class="mb-0">Total Categories</p>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="stat-item">
                                <h2 class="text-danger">
                                    <?php 
                                    $totalLostItems = 0;
                                    foreach ($categories as $category) {
                                        $totalLostItems += $category['lost_count'];
                                    }
                                    echo $totalLostItems;
                                    ?>
                                </h2>
                                <p class="mb-0">Lost Items</p>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="stat-item">
                                <h2 class="text-success">
                                    <?php 
                                    $totalFoundItems = 0;
                                    foreach ($categories as $category) {
                                        $totalFoundItems += $category['found_count'];
                                    }
                                    echo $totalFoundItems;
                                    ?>
                                </h2>
                                <p class="mb-0">Found Items</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-3">
                        <canvas id="categoryDistributionChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-8">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white py-3">
                    <h5 class="card-title mb-0">All Categories</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>ID</th>
                                    <th>Category Name</th>
                                    <th>Lost Items</th>
                                    <th>Found Items</th>
                                    <th>Total Items</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($categories)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-3">No categories found</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($categories as $category): ?>
                                        <tr>
                                            <td><?php echo $category['id']; ?></td>
                                            <td><?php echo htmlspecialchars($category['name']); ?></td>
                                            <td>
                                                <span class="badge bg-danger"><?php echo $category['lost_count']; ?></span>
                                            </td>
                                            <td>
                                                <span class="badge bg-success"><?php echo $category['found_count']; ?></span>
                                            </td>
                                            <td>
                                                <span class="badge bg-primary"><?php echo $category['lost_count'] + $category['found_count']; ?></span>
                                            </td>
                                            <td>
                                                <div class="btn-group">
                                                    <a href="?edit=<?php echo $category['id']; ?>" class="btn btn-sm btn-outline-primary" title="Edit Category">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <?php if ($category['lost_count'] == 0 && $category['found_count'] == 0): ?>
                                                        <a href="javascript:void(0)" onclick="confirmDelete('manage_categories.php?delete=<?php echo $category['id']; ?>', '<?php echo htmlspecialchars($category['name']); ?>')" class="btn btn-sm btn-outline-danger" title="Delete Category">
                                                            <i class="fas fa-trash-alt"></i>
                                                        </a>
                                                    <?php else: ?>
                                                        <button class="btn btn-sm btn-outline-danger" disabled title="Cannot delete: Category is in use">
                                                            <i class="fas fa-trash-alt"></i>
                                                        </button>
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
            </div>
        </div>
    </div>
</div>

<script>
// Chart for category distribution
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('categoryDistributionChart').getContext('2d');
    
    // Prepare data for chart
    const categories = <?php echo json_encode(array_column($categories, 'name')); ?>;
    const lostCounts = <?php echo json_encode(array_column($categories, 'lost_count')); ?>;
    const foundCounts = <?php echo json_encode(array_column($categories, 'found_count')); ?>;
    
    const categoryChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: categories,
            datasets: [
                {
                    label: 'Lost Items',
                    data: lostCounts,
                    backgroundColor: 'rgba(220, 53, 69, 0.7)',
                    borderColor: 'rgba(220, 53, 69, 1)',
                    borderWidth: 1
                },
                {
                    label: 'Found Items',
                    data: foundCounts,
                    backgroundColor: 'rgba(40, 167, 69, 0.7)',
                    borderColor: 'rgba(40, 167, 69, 1)',
                    borderWidth: 1
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        precision: 0
                    }
                }
            },
            plugins: {
                legend: {
                    position: 'top',
                },
                title: {
                    display: true,
                    text: 'Items by Category'
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