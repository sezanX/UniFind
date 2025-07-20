<?php
$pageTitle = 'Manage Departments';
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
$departmentName = '';
$departmentId = 0;
$isEditing = false;

// Process department actions
// Add new department
if (isset($_POST['add_department'])) {
    $departmentName = trim($_POST['department_name']);
    
    if (empty($departmentName)) {
        $message = 'Department name cannot be empty!';
        $messageType = 'danger';
    } else {
        // Check if department already exists
        $stmt = $conn->prepare("SELECT id FROM departments WHERE name = ?");
        $stmt->bind_param("s", $departmentName);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $message = 'Department already exists!';
            $messageType = 'danger';
        } else {
            // Insert new department
            $stmt = $conn->prepare("INSERT INTO departments (name) VALUES (?)");
            $stmt->bind_param("s", $departmentName);
            $stmt->execute();
            
            if ($stmt->affected_rows > 0) {
                $message = 'Department added successfully!';
                $messageType = 'success';
                $departmentName = ''; // Clear the form
            } else {
                $message = 'Failed to add department!';
                $messageType = 'danger';
            }
        }
    }
}

// Edit department
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $departmentId = $_GET['edit'];
    $isEditing = true;
    
    // Get department details
    $stmt = $conn->prepare("SELECT * FROM departments WHERE id = ?");
    $stmt->bind_param("i", $departmentId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $department = $result->fetch_assoc();
        $departmentName = $department['name'];
    } else {
        $message = 'Department not found!';
        $messageType = 'danger';
        $isEditing = false;
    }
}

// Update department
if (isset($_POST['update_department'])) {
    $departmentId = $_POST['department_id'];
    $departmentName = trim($_POST['department_name']);
    
    if (empty($departmentName)) {
        $message = 'Department name cannot be empty!';
        $messageType = 'danger';
    } else {
        // Check if department already exists with the same name but different ID
        $stmt = $conn->prepare("SELECT id FROM departments WHERE name = ? AND id != ?");
        $stmt->bind_param("si", $departmentName, $departmentId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $message = 'Department with this name already exists!';
            $messageType = 'danger';
        } else {
            // Update department
            $stmt = $conn->prepare("UPDATE departments SET name = ? WHERE id = ?");
            $stmt->bind_param("si", $departmentName, $departmentId);
            $stmt->execute();
            
            if ($stmt->affected_rows > 0 || $conn->affected_rows > 0) {
                $message = 'Department updated successfully!';
                $messageType = 'success';
                $isEditing = false;
                $departmentName = ''; // Clear the form
                $departmentId = 0;
            } else {
                $message = 'No changes made or failed to update department!';
                $messageType = 'warning';
            }
        }
    }
}

// Delete department
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $departmentId = $_GET['delete'];
    
    // Check if department is being used in users, lost or found items
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM users WHERE department = ?");
    $stmt->bind_param("i", $departmentId);
    $stmt->execute();
    $result = $stmt->get_result();
    $usersCount = $result->fetch_assoc()['count'];
    
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM lost_items l JOIN users u ON l.user_id = u.id WHERE u.department = ?");
    $stmt->bind_param("i", $departmentId);
    $stmt->execute();
    $result = $stmt->get_result();
    $lostItemsCount = $result->fetch_assoc()['count'];
    
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM found_items f JOIN users u ON f.user_id = u.id WHERE u.department = ?");
    $stmt->bind_param("i", $departmentId);
    $stmt->execute();
    $result = $stmt->get_result();
    $foundItemsCount = $result->fetch_assoc()['count'];
    
    if ($usersCount > 0 || $lostItemsCount > 0 || $foundItemsCount > 0) {
        $message = 'Cannot delete department! It is being used by ' . $usersCount . ' users, ' . $lostItemsCount . ' lost items, and ' . $foundItemsCount . ' found items.';
        $messageType = 'danger';
    } else {
        // Delete department
        $stmt = $conn->prepare("DELETE FROM departments WHERE id = ?");
        $stmt->bind_param("i", $departmentId);
        $stmt->execute();
        
        if ($stmt->affected_rows > 0) {
            $message = 'Department deleted successfully!';
            $messageType = 'success';
        } else {
            $message = 'Failed to delete department!';
            $messageType = 'danger';
        }
    }
}

// Get all departments with counts
$departments = [];
$sql = "SELECT d.id, d.name, 
        (SELECT COUNT(*) FROM users WHERE department = d.id) as users_count, 
        (SELECT COUNT(*) FROM lost_items l JOIN users u ON l.user_id = u.id WHERE u.department = d.id) as lost_count, 
        (SELECT COUNT(*) FROM found_items f JOIN users u ON f.user_id = u.id WHERE u.department = d.id) as found_count 
        FROM departments d 
        ORDER BY d.name";
$result = $conn->query($sql);

while ($row = $result->fetch_assoc()) {
    $departments[] = $row;
}

// Include admin header
include 'includes/admin_header.php';
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0 text-gray-800">Manage Departments</h1>
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
                    <h5 class="card-title mb-0"><?php echo $isEditing ? 'Edit Department' : 'Add New Department'; ?></h5>
                </div>
                <div class="card-body">
                    <form action="" method="POST">
                        <?php if ($isEditing): ?>
                            <input type="hidden" name="department_id" value="<?php echo $departmentId; ?>">
                        <?php endif; ?>
                        
                        <div class="mb-3">
                            <label for="department_name" class="form-label">Department Name</label>
                            <input type="text" class="form-control" id="department_name" name="department_name" value="<?php echo htmlspecialchars($departmentName); ?>" required>
                        </div>
                        
                        <div class="d-grid gap-2">
                            <?php if ($isEditing): ?>
                                <button type="submit" name="update_department" class="btn btn-primary">
                                    <i class="fas fa-save me-1"></i> Update Department
                                </button>
                                <a href="manage_departments.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-times me-1"></i> Cancel
                                </a>
                            <?php else: ?>
                                <button type="submit" name="add_department" class="btn btn-success">
                                    <i class="fas fa-plus me-1"></i> Add Department
                                </button>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
            
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white py-3">
                    <h5 class="card-title mb-0">Department Statistics</h5>
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-md-4 mb-3">
                            <div class="stat-item">
                                <h2 class="text-primary"><?php echo count($departments); ?></h2>
                                <p class="mb-0">Total Departments</p>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="stat-item">
                                <h2 class="text-info">
                                    <?php 
                                    $totalUsers = 0;
                                    foreach ($departments as $department) {
                                        $totalUsers += $department['users_count'];
                                    }
                                    echo $totalUsers;
                                    ?>
                                </h2>
                                <p class="mb-0">Users</p>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="stat-item">
                                <h2 class="text-success">
                                    <?php 
                                    $totalItems = 0;
                                    foreach ($departments as $department) {
                                        $totalItems += $department['lost_count'] + $department['found_count'];
                                    }
                                    echo $totalItems;
                                    ?>
                                </h2>
                                <p class="mb-0">Total Items</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-3">
                        <canvas id="departmentDistributionChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-8">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white py-3">
                    <h5 class="card-title mb-0">All Departments</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>ID</th>
                                    <th>Department Name</th>
                                    <th>Users</th>
                                    <th>Lost Items</th>
                                    <th>Found Items</th>
                                    <th>Total Items</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($departments)): ?>
                                    <tr>
                                        <td colspan="7" class="text-center py-3">No departments found</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($departments as $department): ?>
                                        <tr>
                                            <td><?php echo $department['id']; ?></td>
                                            <td><?php echo htmlspecialchars($department['name']); ?></td>
                                            <td>
                                                <span class="badge bg-info"><?php echo $department['users_count']; ?></span>
                                            </td>
                                            <td>
                                                <span class="badge bg-danger"><?php echo $department['lost_count']; ?></span>
                                            </td>
                                            <td>
                                                <span class="badge bg-success"><?php echo $department['found_count']; ?></span>
                                            </td>
                                            <td>
                                                <span class="badge bg-primary"><?php echo $department['lost_count'] + $department['found_count']; ?></span>
                                            </td>
                                            <td>
                                                <div class="btn-group">
                                                    <a href="?edit=<?php echo $department['id']; ?>" class="btn btn-sm btn-outline-primary" title="Edit Department">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <?php if ($department['users_count'] == 0 && $department['lost_count'] == 0 && $department['found_count'] == 0): ?>
                                                        <a href="javascript:void(0)" onclick="confirmDelete('manage_departments.php?delete=<?php echo $department['id']; ?>', '<?php echo htmlspecialchars($department['name']); ?>')" class="btn btn-sm btn-outline-danger" title="Delete Department">
                                                            <i class="fas fa-trash-alt"></i>
                                                        </a>
                                                    <?php else: ?>
                                                        <button class="btn btn-sm btn-outline-danger" disabled title="Cannot delete: Department is in use">
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
// Chart for department distribution
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('departmentDistributionChart').getContext('2d');
    
    // Prepare data for chart
    const departments = <?php echo json_encode(array_column($departments, 'name')); ?>;
    const userCounts = <?php echo json_encode(array_column($departments, 'users_count')); ?>;
    const lostCounts = <?php echo json_encode(array_column($departments, 'lost_count')); ?>;
    const foundCounts = <?php echo json_encode(array_column($departments, 'found_count')); ?>;
    
    const departmentChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: departments,
            datasets: [
                {
                    label: 'Users',
                    data: userCounts,
                    backgroundColor: 'rgba(13, 110, 253, 0.7)',
                    borderColor: 'rgba(13, 110, 253, 1)',
                    borderWidth: 1
                },
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
                    text: 'Department Distribution'
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