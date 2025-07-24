<?php
// Debug Title Display Script
// This script helps diagnose issues with title display in item detail pages

// Include database connection
require_once 'includes/db.php';

// Initialize variables
$message = '';
$error = '';

// Function to check item titles
function checkItemTitles() {
    global $conn, $message, $error;
    
    // Check lost items
    $lostQuery = "SELECT id, title FROM lost_items ORDER BY id DESC LIMIT 10";
    $lostResult = $conn->query($lostQuery);
    
    if ($lostResult) {
        $message .= "<h5>Recent Lost Items:</h5>";
        $message .= "<table class='table table-striped'>";
        $message .= "<thead><tr><th>ID</th><th>Title</th><th>Title Type</th><th>Title Length</th><th>Raw Title</th></tr></thead>";
        $message .= "<tbody>";
        
        if ($lostResult->num_rows > 0) {
            while ($item = $lostResult->fetch_assoc()) {
                $titleType = gettype($item['title']);
                $titleLength = strlen($item['title']);
                $rawTitle = bin2hex($item['title']); // Convert to hex to see any hidden characters
                
                $message .= "<tr>";
                $message .= "<td>{$item['id']}</td>";
                $message .= "<td>" . htmlspecialchars($item['title']) . "</td>";
                $message .= "<td>{$titleType}</td>";
                $message .= "<td>{$titleLength}</td>";
                $message .= "<td>{$rawTitle}</td>";
                $message .= "</tr>";
            }
        } else {
            $message .= "<tr><td colspan='5'>No lost items found</td></tr>";
        }
        
        $message .= "</tbody></table>";
    } else {
        $error .= "<p>Error querying lost items: {$conn->error}</p>";
    }
    
    // Check found items
    $foundQuery = "SELECT id, title FROM found_items ORDER BY id DESC LIMIT 10";
    $foundResult = $conn->query($foundQuery);
    
    if ($foundResult) {
        $message .= "<h5>Recent Found Items:</h5>";
        $message .= "<table class='table table-striped'>";
        $message .= "<thead><tr><th>ID</th><th>Title</th><th>Title Type</th><th>Title Length</th><th>Raw Title</th></tr></thead>";
        $message .= "<tbody>";
        
        if ($foundResult->num_rows > 0) {
            while ($item = $foundResult->fetch_assoc()) {
                $titleType = gettype($item['title']);
                $titleLength = strlen($item['title']);
                $rawTitle = bin2hex($item['title']); // Convert to hex to see any hidden characters
                
                $message .= "<tr>";
                $message .= "<td>{$item['id']}</td>";
                $message .= "<td>" . htmlspecialchars($item['title']) . "</td>";
                $message .= "<td>{$titleType}</td>";
                $message .= "<td>{$titleLength}</td>";
                $message .= "<td>{$rawTitle}</td>";
                $message .= "</tr>";
            }
        } else {
            $message .= "<tr><td colspan='5'>No found items found</td></tr>";
        }
        
        $message .= "</tbody></table>";
    } else {
        $error .= "<p>Error querying found items: {$conn->error}</p>";
    }
    
    // Check if there are any items with title '0'
    $zeroTitleQuery = "SELECT 'lost' as type, id, title FROM lost_items WHERE title = '0' UNION SELECT 'found' as type, id, title FROM found_items WHERE title = '0'";
    $zeroTitleResult = $conn->query($zeroTitleQuery);
    
    if ($zeroTitleResult) {
        $message .= "<h5>Items with Title '0':</h5>";
        $message .= "<table class='table table-striped'>";
        $message .= "<thead><tr><th>Type</th><th>ID</th><th>Title</th><th>Title Type</th><th>Title Length</th><th>Raw Title</th></tr></thead>";
        $message .= "<tbody>";
        
        if ($zeroTitleResult->num_rows > 0) {
            while ($item = $zeroTitleResult->fetch_assoc()) {
                $titleType = gettype($item['title']);
                $titleLength = strlen($item['title']);
                $rawTitle = bin2hex($item['title']); // Convert to hex to see any hidden characters
                
                $message .= "<tr>";
                $message .= "<td>{$item['type']}</td>";
                $message .= "<td>{$item['id']}</td>";
                $message .= "<td>" . htmlspecialchars($item['title']) . "</td>";
                $message .= "<td>{$titleType}</td>";
                $message .= "<td>{$titleLength}</td>";
                $message .= "<td>{$rawTitle}</td>";
                $message .= "</tr>";
            }
        } else {
            $message .= "<tr><td colspan='6'>No items with title '0' found</td></tr>";
        }
        
        $message .= "</tbody></table>";
    } else {
        $error .= "<p>Error querying items with title '0': {$conn->error}</p>";
    }
    
    // Check if there are any items with numeric titles
    $numericTitleQuery = "SELECT 'lost' as type, id, title FROM lost_items WHERE title REGEXP '^[0-9]+$' UNION SELECT 'found' as type, id, title FROM found_items WHERE title REGEXP '^[0-9]+$'";
    $numericTitleResult = $conn->query($numericTitleQuery);
    
    if ($numericTitleResult) {
        $message .= "<h5>Items with Numeric Titles:</h5>";
        $message .= "<table class='table table-striped'>";
        $message .= "<thead><tr><th>Type</th><th>ID</th><th>Title</th><th>Title Type</th><th>Title Length</th><th>Raw Title</th></tr></thead>";
        $message .= "<tbody>";
        
        if ($numericTitleResult->num_rows > 0) {
            while ($item = $numericTitleResult->fetch_assoc()) {
                $titleType = gettype($item['title']);
                $titleLength = strlen($item['title']);
                $rawTitle = bin2hex($item['title']); // Convert to hex to see any hidden characters
                
                $message .= "<tr>";
                $message .= "<td>{$item['type']}</td>";
                $message .= "<td>{$item['id']}</td>";
                $message .= "<td>" . htmlspecialchars($item['title']) . "</td>";
                $message .= "<td>{$titleType}</td>";
                $message .= "<td>{$titleLength}</td>";
                $message .= "<td>{$rawTitle}</td>";
                $message .= "</tr>";
            }
        } else {
            $message .= "<tr><td colspan='6'>No items with numeric titles found</td></tr>";
        }
        
        $message .= "</tbody></table>";
    } else {
        $error .= "<p>Error querying items with numeric titles: {$conn->error}</p>";
    }
    
    // Fix items with title '0'
    if (isset($_GET['fix']) && $_GET['fix'] === 'true') {
        $fixLostQuery = "UPDATE lost_items SET title = CONCAT('Lost item #', id) WHERE title = '0'";
        $fixFoundQuery = "UPDATE found_items SET title = CONCAT('Found item #', id) WHERE title = '0'";
        
        if ($conn->query($fixLostQuery) && $conn->query($fixFoundQuery)) {
            $affectedLost = $conn->affected_rows;
            $message .= "<div class='alert alert-success'>Successfully fixed {$affectedLost} lost items with title '0'</div>";
            
            if ($conn->query($fixFoundQuery)) {
                $affectedFound = $conn->affected_rows;
                $message .= "<div class='alert alert-success'>Successfully fixed {$affectedFound} found items with title '0'</div>";
            } else {
                $error .= "<p>Error fixing found items: {$conn->error}</p>";
            }
        } else {
            $error .= "<p>Error fixing lost items: {$conn->error}</p>";
        }
    }
}

// Run the check function
checkItemTitles();

// HTML output
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Debug Title Display - UniFind</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
</head>
<body>
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-12">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h3 class="mb-0">Debug Title Display</h3>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($error)): ?>
                            <div class="alert alert-danger">
                                <h4 class="alert-heading">Errors:</h4>
                                <?php echo $error; ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($message)): ?>
                            <div class="alert alert-info">
                                <h4 class="alert-heading">Information:</h4>
                                <?php echo $message; ?>
                            </div>
                        <?php endif; ?>
                        
                        <div class="mt-4">
                            <a href="debug_title_display.php?fix=true" class="btn btn-primary">
                                <i class="fas fa-wrench me-2"></i>Fix Items with Title '0'
                            </a>
                            <a href="index.php" class="btn btn-secondary ms-2">
                                <i class="fas fa-home me-2"></i>Back to Home
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>