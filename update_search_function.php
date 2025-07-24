<?php
// Update Search Function Script
// This script updates the searchItems function in functions.php with the fixed version

// Initialize variables
$message = '';
$error = '';
$updated = false;

// Check if the script is being run with the update parameter
$update = isset($_GET['update']) && $_GET['update'] === 'true';

// Function to update the searchItems function
function updateSearchFunction() {
    global $message, $error, $updated;
    
    // Path to functions.php
    $functionsPath = __DIR__ . '/includes/functions.php';
    
    // Check if functions.php exists
    if (!file_exists($functionsPath)) {
        $error .= "<p>Could not find functions.php at {$functionsPath}</p>";
        return;
    }
    
    // Read the contents of functions.php
    $functionsContent = file_get_contents($functionsPath);
    
    // Check if the searchItems function exists
    if (strpos($functionsContent, 'function searchItems') === false) {
        $error .= "<p>Could not find the searchItems function in functions.php</p>";
        return;
    }
    
    // Path to fixed_search_function.php
    $fixedFunctionPath = __DIR__ . '/fixed_search_function.php';
    
    // Check if fixed_search_function.php exists
    if (!file_exists($fixedFunctionPath)) {
        $error .= "<p>Could not find fixed_search_function.php at {$fixedFunctionPath}</p>";
        return;
    }
    
    // Read the contents of fixed_search_function.php
    $fixedFunctionContent = file_get_contents($fixedFunctionPath);
    
    // Extract the fixed searchItems function
    preg_match('/function searchItems\(.*?\}\n\}/s', $fixedFunctionContent, $matches);
    
    if (empty($matches)) {
        $error .= "<p>Could not extract the searchItems function from fixed_search_function.php</p>";
        return;
    }
    
    $fixedFunction = $matches[0];
    
    // Create a backup of functions.php
    $backupPath = __DIR__ . '/includes/functions.php.bak';
    if (!copy($functionsPath, $backupPath)) {
        $error .= "<p>Failed to create a backup of functions.php</p>";
        return;
    }
    
    $message .= "<p>Created a backup of functions.php at {$backupPath}</p>";
    
    // Replace the searchItems function in functions.php
    if (isset($update) && $update) {
        // Find the start and end of the searchItems function
        $pattern = '/function searchItems\(.*?\}\n\}/s';
        
        // Replace the function
        $updatedContent = preg_replace($pattern, $fixedFunction, $functionsContent);
        
        if ($updatedContent === null) {
            $error .= "<p>Failed to replace the searchItems function in functions.php</p>";
            return;
        }
        
        // Write the updated content back to functions.php
        if (file_put_contents($functionsPath, $updatedContent)) {
            $message .= "<p>Successfully updated the searchItems function in functions.php</p>";
            $updated = true;
        } else {
            $error .= "<p>Failed to write the updated content to functions.php</p>";
        }
    } else {
        $message .= "<p>Ready to update the searchItems function in functions.php</p>";
    }
}

// Run the update function
updateSearchFunction();

// HTML output
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update Search Function - UniFind</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
</head>
<body>
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-10">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h3 class="mb-0">Update Search Function</h3>
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
                        
                        <?php if (!$update && empty($error)): ?>
                            <div class="alert alert-warning">
                                <p>This script will update the searchItems function in functions.php with the fixed version.</p>
                                <p>A backup of functions.php will be created before making any changes.</p>
                                <a href="update_search_function.php?update=true" class="btn btn-warning">
                                    <i class="fas fa-wrench me-2"></i>Update Search Function
                                </a>
                            </div>
                        <?php elseif ($update && $updated): ?>
                            <div class="alert alert-success">
                                <h4 class="alert-heading">Success!</h4>
                                <p>The searchItems function has been updated successfully.</p>
                                <p>You can now go back to the home page to see if the item IDs are displayed correctly.</p>
                            </div>
                        <?php endif; ?>
                        
                        <div class="mt-4">
                            <a href="index.php" class="btn btn-primary">
                                <i class="fas fa-home me-2"></i>Return to Home
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