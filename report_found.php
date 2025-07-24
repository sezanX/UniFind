<?php
$pageTitle = 'Report Found Item';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

// Redirect if not logged in
if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$error = '';
$success = '';
$potentialMatches = [];

// Get categories for dropdown
$categories = getCategories();
// Get current user
$user = getCurrentUser();

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate input
    if (empty($_POST['title']) || empty($_POST['category_id']) || empty($_POST['date_found']) || 
        empty($_POST['location']) || empty($_POST['description']) || empty($_POST['phone'])) {
        $error = 'All fields marked with * are required';
    } else {
        // Handle image upload
        $imageUploadResult = ['status' => true, 'filename' => null];
        if (!empty($_FILES['image']['name'])) {
            $imageUploadResult = uploadImage($_FILES['image'], 'found');
            if (!$imageUploadResult['status']) {
                $error = $imageUploadResult['message'];
            }
        }
        
        // If no errors, insert into database
        if (empty($error)) {
            global $conn;
            $stmt = $conn->prepare("INSERT INTO found_items (user_id, title, category_id, date_found, location, description, image, phone, whatsapp, email, status, date_reported) 
                                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', NOW())");
            
            // CORRECTED LINE:
            $stmt->bind_param("isisssssss", 
                $_SESSION['user_id'],
                $_POST['title'],
                $_POST['category_id'],
                $_POST['date_found'],
                $_POST['location'],
                $_POST['description'],
                $imageUploadResult['filename'],
                $_POST['phone'],
                $_POST['whatsapp'],
                $_POST['email']
            );

            if ($stmt->execute()) {
                $foundItemId = $conn->insert_id;
                // Check for potential matches
                $foundItem = [
                    'category_id' => $_POST['category_id'],
                    'date_found' => $_POST['date_found'],
                    'location' => $_POST['location']
                ];
                
                $potentialMatches = findPotentialMatchesForFound($foundItem);
                
                if (empty($potentialMatches)) {
                    $success = 'Your found item has been reported successfully!';
                }
            } else {
                $error = 'Failed to report found item: ' . $conn->error;
            }
        }
    }
}

include 'includes/header.php';
?>

<div class="row">
    <div class="col-md-8 mx-auto">
        <?php if (!empty($potentialMatches)): ?>
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-warning-subtle py-3">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-lightbulb me-2"></i>Potential Matches Found!
                    </h5>
                </div>
                <div class="card-body">
                    <p>We found some lost items that might match what you found. These people might be looking for this item:</p>
                    
                    <div class="row">
                        <?php foreach ($potentialMatches as $match): ?>
                            <div class="col-md-6 mb-3">
                                <div class="card h-100">
                                    <?php if (!empty($match['image']) && file_exists(__DIR__ . '/uploads/' . $match['image'])): ?>
                                        <img src="uploads/<?php echo htmlspecialchars($match['image']); ?>" class="card-img-top" alt="<?php echo htmlspecialchars($match['title']); ?>" style="height: 150px; object-fit: cover;">
                                    <?php endif; ?>
                                    <div class="card-body">
                                        <h5 class="card-title"><?php echo htmlspecialchars($match['title']); ?></h5>
                                        <p class="card-text small">
                                            <i class="fas fa-map-marker-alt me-1"></i> <?php echo htmlspecialchars($match['location']); ?><br>
                                            <i class="fas fa-calendar-alt me-1"></i> Lost on: <?php echo formatDate($match['date_lost']); ?>
                                        </p>
                                        <a href="lost_item.php?id=<?php echo $match['id']; ?>" class="btn btn-sm btn-primary">View Details</a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
            </div>
        <?php endif; ?>
        
        <div class="card shadow-sm">
            <div class="card-header bg-white py-3">
                <h4 class="card-title mb-0">
                    <i class="fas fa-hand-holding text-success me-2"></i>Report Found Item
                </h4>
            </div>
            <div class="card-body p-4">
                <form method="post" action="" enctype="multipart/form-data" class="needs-validation" novalidate>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="title" class="form-label">Item Title *</label>
                            <input type="text" class="form-control" id="title" name="title" required>
                            <div class="invalid-feedback">Please provide a title for the item</div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="category_id" class="form-label">Category *</label>
                            <select class="form-select" id="category_id" name="category_id" required>
                                <option value="" selected disabled>Select category</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo $category['id']; ?>"><?php echo htmlspecialchars($category['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">Please select a category</div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="date_found" class="form-label">Date Found *</label>
                            <input type="date" class="form-control date-picker" id="date_found" name="date_found" max="<?php echo date('Y-m-d'); ?>" required>
                            <div class="invalid-feedback">Please select the date when the item was found</div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="location" class="form-label">Location Found *</label>
                            <input type="text" class="form-control" id="location" name="location" placeholder="e.g., Library, Cafeteria, Room 101" required>
                            <div class="invalid-feedback">Please provide the location where the item was found</div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Description *</label>
                        <textarea class="form-control" id="description" name="description" rows="4" placeholder="Provide detailed description of the item including color, brand, identifying marks, etc." required></textarea>
                        <div class="invalid-feedback">Please provide a description of the item</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="image" class="form-label">Image (Optional)</label>
                        <input type="file" class="form-control custom-file-input" id="image" name="image" accept="image/*">
                        <div class="form-text">Upload an image of the item (max 5MB, JPG/PNG/GIF only)</div>
                        <div class="image-preview mt-2">
                            <div class="placeholder">Image preview will appear here</div>
                        </div>
                    </div>
                    
                    <h5 class="mt-4 mb-3">Contact Information</h5>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="phone" class="form-label">Phone Number *</label>
                            <input type="tel" class="form-control" id="phone" name="phone" value="<?php echo htmlspecialchars($user['phone']); ?>" required>
                            <div class="invalid-feedback">Please provide your phone number</div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="whatsapp" class="form-label">WhatsApp Number (Optional)</label>
                            <input type="tel" class="form-control" id="whatsapp" name="whatsapp">
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label for="email" class="form-label">Email (Optional)</label>
                        <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>">
                    </div>
                    
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <a href="index.php" class="btn btn-outline-secondary me-md-2">Cancel</a>
                        <button type="submit" class="btn btn-primary">Submit Report</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
