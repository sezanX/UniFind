<?php
// Check for empty titles in the database
require_once 'includes/db.php';

// Initialize variables
$message = '';
$error = '';

// Check lost items with empty titles
$lostItemsQuery = "SELECT id, title FROM lost_items WHERE title = '' OR title IS NULL";
$lostResult = $conn->query($lostItemsQuery);

if ($lostResult) {
    $lostEmptyCount = $lostResult->num_rows;
    $message .= "<p>Found {$lostEmptyCount} lost items with empty titles</p>";
    
    if ($lostEmptyCount > 0) {
        $message .= "<p><strong>Lost Items with Empty Titles:</strong></p>";
        $message .= "<ul>";
        while ($row = $lostResult->fetch_assoc()) {
            $message .= "<li>ID: {$row['id']} - Title: '{$row['title']}'</li>";
        }
        $message .= "</ul>";
    }
} else {
    $error .= "<p>Error checking lost items: {$conn->error}</p>";
}

// Check found items with empty titles
$foundItemsQuery = "SELECT id, title FROM found_items WHERE title = '' OR title IS NULL";
$foundResult = $conn->query($foundItemsQuery);

if ($foundResult) {
    $foundEmptyCount = $foundResult->num_rows;
    $message .= "<p>Found {$foundEmptyCount} found items with empty titles</p>";
    
    if ($foundEmptyCount > 0) {
        $message .= "<p><strong>Found Items with Empty Titles:</strong></p>";
        $message .= "<ul>";
        while ($row = $foundResult->fetch_assoc()) {
            $message .= "<li>ID: {$row['id']} - Title: '{$row['title']}'</li>";
        }
        $message .= "</ul>";
    }
} else {
    $error .= "<p>Error checking found items: {$conn->error}</p>";
}

// HTML output
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Check Empty Titles - UniFind</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
</head>
<body>
    <div class="container py-4">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0">Check Empty Titles</h4>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($message)): ?>
                            <div class="alert alert-info">
                                <?php echo $message; ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($error)): ?>
                            <div class="alert alert-danger">
                                <?php echo $error; ?>
                            </div>
                        <?php endif; ?>
                        
                        <div class="mt-3">
                            <a href="fix_item_titles.php?update=true" class="btn btn-primary">Fix Empty Titles</a>
                            <a href="index.php" class="btn btn-secondary ms-2">Back to Home</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>