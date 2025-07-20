<?php
require_once 'db.php';

/**
 * Upload an image file
 * @param array $file The $_FILES array element
 * @param string $itemType 'lost' or 'found'
 * @return array Status, message and filename if successful
 */
function uploadImage($file, $itemType) {
    // Check if file was uploaded without errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errorMessages = [
            UPLOAD_ERR_INI_SIZE => 'The uploaded file exceeds the upload_max_filesize directive in php.ini',
            UPLOAD_ERR_FORM_SIZE => 'The uploaded file exceeds the MAX_FILE_SIZE directive in the HTML form',
            UPLOAD_ERR_PARTIAL => 'The uploaded file was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload'
        ];
        $errorMessage = isset($errorMessages[$file['error']]) ? $errorMessages[$file['error']] : 'Unknown upload error';
        return ['status' => false, 'message' => $errorMessage];
    }
    
    // Check file type
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/jpg'];
    if (!in_array($file['type'], $allowedTypes)) {
        return ['status' => false, 'message' => 'Only JPG, JPEG, PNG & GIF files are allowed'];
    }
    
    // Check file size (max 5MB)
    $maxSize = 5 * 1024 * 1024; // 5MB in bytes
    if ($file['size'] > $maxSize) {
        return ['status' => false, 'message' => 'File size should be less than 5MB'];
    }
    
    // Create upload directory if it doesn't exist
    $uploadDir = dirname(__DIR__) . '/uploads/' . $itemType . '/';
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    
    // Generate unique filename
    $filename = uniqid() . '_' . basename($file['name']);
    $targetFile = $uploadDir . $filename;
    
    // Upload file
    if (move_uploaded_file($file['tmp_name'], $targetFile)) {
        return [
            'status' => true, 
            'message' => 'File uploaded successfully', 
            'filename' => $itemType . '/' . $filename
        ];
    } else {
        return ['status' => false, 'message' => 'Failed to upload file'];
    }
}

/**
 * Format date to a readable format
 * @param string $date Date in Y-m-d format
 * @param bool $includeTime Whether to include time
 * @return string Formatted date
 */
function formatDate($date, $includeTime = false) {
    if (empty($date)) return 'N/A';
    
    $format = $includeTime ? 'F j, Y \a\t g:i a' : 'F j, Y';
    return date($format, strtotime($date));
}

/**
 * Get time elapsed since a date
 * @param string $datetime Date and time in Y-m-d H:i:s format
 * @return string Time elapsed
 */
function timeElapsed($datetime) {
    $now = new DateTime();
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);
    
    if ($diff->y > 0) {
        return $diff->y . ' year' . ($diff->y > 1 ? 's' : '') . ' ago';
    } elseif ($diff->m > 0) {
        return $diff->m . ' month' . ($diff->m > 1 ? 's' : '') . ' ago';
    } elseif ($diff->d > 0) {
        return $diff->d . ' day' . ($diff->d > 1 ? 's' : '') . ' ago';
    } elseif ($diff->h > 0) {
        return $diff->h . ' hour' . ($diff->h > 1 ? 's' : '') . ' ago';
    } elseif ($diff->i > 0) {
        return $diff->i . ' minute' . ($diff->i > 1 ? 's' : '') . ' ago';
    } else {
        return 'Just now';
    }
}

/**
 * Get all categories
 * @return array Categories
 */
function getCategories() {
    global $conn;
    $categories = [];
    
    $result = $conn->query("SELECT * FROM categories ORDER BY name ASC");
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $categories[] = $row;
        }
    }
    
    return $categories;
}

/**
 * Get all departments
 * @return array Departments
 */
function getDepartments() {
    global $conn;
    $departments = [];
    
    $result = $conn->query("SELECT * FROM departments ORDER BY name ASC");
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $departments[] = $row;
        }
    }
    
    return $departments;
}

/**
 * Get item statistics
 * @return array Statistics
 */
function getItemStats() {
    global $conn;
    $stats = [
        'lost_today' => 0,
        'found_today' => 0,
        'lost_week' => 0,
        'found_week' => 0,
        'matched' => 0,
        'total_lost' => 0,
        'total_found' => 0,
        'total_matched' => 0
    ];
    
    // Lost today
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM lost_items WHERE DATE(date_reported) = CURDATE()");
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $stats['lost_today'] = $row['count'];
    }
    
    // Found today
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM found_items WHERE DATE(date_reported) = CURDATE()");
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $stats['found_today'] = $row['count'];
    }
    
    // Lost this week
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM lost_items WHERE date_reported >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)");
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $stats['lost_week'] = $row['count'];
    }
    
    // Found this week
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM found_items WHERE date_reported >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)");
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $stats['found_week'] = $row['count'];
    }
    
    // Matched items
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM matches WHERE status = 'matched'");
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $stats['matched'] = $row['count'];
    }

    // Total lost items
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM lost_items");
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $stats['total_lost'] = $row['count'];
    }

    // Total found items
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM found_items");
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $stats['total_found'] = $row['count'];
    }

    // Total matched items
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM matches WHERE status = 'matched'");
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $stats['total_matched'] = $row['count'];
    }
    
    return $stats;
}

/**
 * Get all items reported by a specific user (both lost and found)
 * @param int $userId The ID of the user
 * @return array An array containing 'lost_items' and 'found_items'
 */
function getUserItems($userId) {
    global $conn;
    $items = ['lost_items' => [], 'found_items' => [], 'matched_items' => []];

    // Get lost items for the user
    $sqlLost = "SELECT li.*, c.name as category_name, d.name as department_name
                FROM lost_items li
                LEFT JOIN categories c ON li.category_id = c.id
                LEFT JOIN users u ON li.user_id = u.id
                LEFT JOIN departments d ON u.department = d.id
                WHERE li.user_id = ? ORDER BY li.date_reported DESC";
    $stmtLost = $conn->prepare($sqlLost);
    $stmtLost->bind_param('i', $userId);
    $stmtLost->execute();
    $resultLost = $stmtLost->get_result();
    while ($row = $resultLost->fetch_assoc()) {
        $row['type'] = 'lost';
        $items['lost_items'][] = $row;
    }

    // Get found items for the user
    $sqlFound = "SELECT fi.*, c.name as category_name, d.name as department_name
                 FROM found_items fi
                 LEFT JOIN categories c ON fi.category_id = c.id
                 LEFT JOIN users u ON fi.user_id = u.id
                 LEFT JOIN departments d ON u.department = d.id
                 WHERE fi.user_id = ? ORDER BY fi.date_reported DESC";
    $stmtFound = $conn->prepare($sqlFound);
    $stmtFound->bind_param('i', $userId);
    $stmtFound->execute();
    $resultFound = $stmtFound->get_result();
    while ($row = $resultFound->fetch_assoc()) {
        $row['type'] = 'found';
        $items['found_items'][] = $row;
    }

    // Get matched items for the user
    $sqlMatched = "SELECT m.*, 
                   li.title as lost_title, li.image as lost_image, li.date_reported as lost_date_reported, 
                   fi.title as found_title, fi.image as found_image, fi.date_found as found_date_found, 
                   'matched' as type
                   FROM matches m
                   JOIN lost_items li ON m.lost_item_id = li.id
                   JOIN found_items fi ON m.found_item_id = fi.id
                   WHERE li.user_id = ? OR fi.user_id = ? ORDER BY m.created_at DESC";
    $stmtMatched = $conn->prepare($sqlMatched);
    $stmtMatched->bind_param('ii', $userId, $userId);
    $stmtMatched->execute();
    $resultMatched = $stmtMatched->get_result();
    while ($row = $resultMatched->fetch_assoc()) {
        $items['matched_items'][] = $row;
    }

    return $items;
}

/**
 * Check for potential matches for a lost item
 * @param array $lostItem Lost item data
 * @return array Potential matches
 */
function findPotentialMatches($lostItem) {
    global $conn;
    $matches = [];
    
    // Find potential matches based on category, date and location
    $stmt = $conn->prepare("SELECT f.*, c.name as category_name, u.full_name as finder_name 
                           FROM found_items f 
                           JOIN categories c ON f.category_id = c.id 
                           JOIN users u ON f.user_id = u.id 
                           WHERE f.category_id = ? 
                           AND f.status = 'active' 
                           AND ABS(DATEDIFF(f.date_found, ?)) <= 7 
                           AND (f.location LIKE CONCAT('%', ?, '%') OR ? LIKE CONCAT('%', f.location, '%'))
                           ORDER BY f.date_found DESC");
    
    $stmt->bind_param("isss", 
        $lostItem['category_id'], 
        $lostItem['date_lost'], 
        $lostItem['location'], 
        $lostItem['location']
    );
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $matches[] = $row;
        }
    }
    
    return $matches;
}

/**
 * Check for potential matches for a found item
 * @param array $foundItem Found item data
 * @return array Potential matches
 */
function findPotentialMatchesForFound($foundItem) {
    global $conn;
    $matches = [];
    
    // Find potential matches based on category, date and location
    $stmt = $conn->prepare("SELECT l.*, c.name as category_name, u.full_name as owner_name 
                           FROM lost_items l 
                           JOIN categories c ON l.category_id = c.id 
                           JOIN users u ON l.user_id = u.id 
                           WHERE l.category_id = ? 
                           AND l.status = 'active' 
                           AND ABS(DATEDIFF(l.date_lost, ?)) <= 7 
                           AND (l.location LIKE CONCAT('%', ?, '%') OR ? LIKE CONCAT('%', l.location, '%'))
                           ORDER BY l.date_lost DESC");
    
    $stmt->bind_param("isss", 
        $foundItem['category_id'], 
        $foundItem['date_found'], 
        $foundItem['location'], 
        $foundItem['location']
    );
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $matches[] = $row;
        }
    }
    
    return $matches;
}

/**
 * Create a match between lost and found items
 * @param int $lostItemId Lost item ID
 * @param int $foundItemId Found item ID
 * @return array Status and message
 */
function createMatch($lostItemId, $foundItemId) {
    global $conn;
    
    // Check if items exist and are active
    $stmt = $conn->prepare("SELECT * FROM lost_items WHERE id = ? AND status = 'active'");
    $stmt->bind_param("i", $lostItemId);
    $stmt->execute();
    $lostResult = $stmt->get_result();
    
    $stmt = $conn->prepare("SELECT * FROM found_items WHERE id = ? AND status = 'active'");
    $stmt->bind_param("i", $foundItemId);
    $stmt->execute();
    $foundResult = $stmt->get_result();
    
    if ($lostResult->num_rows === 0 || $foundResult->num_rows === 0) {
        return ['status' => false, 'message' => 'One or both items are not available for matching'];
    }
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Update lost item status
        $stmt = $conn->prepare("UPDATE lost_items SET status = 'pending_match' WHERE id = ?");
        $stmt->bind_param("i", $lostItemId);
        $stmt->execute();
        
        // Update found item status
        $stmt = $conn->prepare("UPDATE found_items SET status = 'pending_match' WHERE id = ?");
        $stmt->bind_param("i", $foundItemId);
        $stmt->execute();
        
        // Create match record
        $stmt = $conn->prepare("INSERT INTO matches (lost_item_id, found_item_id, status, created_at) VALUES (?, ?, 'pending_review', NOW())");
        $stmt->bind_param("ii", $lostItemId, $foundItemId);
        $stmt->execute();
        
        // Commit transaction
        $conn->commit();
        
        return ['status' => true, 'message' => 'Match created successfully! An admin will review and confirm the match.'];
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        return ['status' => false, 'message' => 'Failed to create match: ' . $e->getMessage()];
    }
}

/**
 * Get recent activity (lost, found, and matches)
 * @param int $limit Number of activities to return
 * @return array Recent activities
 */
function getRecentActivity($limit = 5) {
    global $conn;
    $activities = [];

    // Get recent lost items
    $stmt = $conn->prepare("SELECT l.id, l.title, l.date_reported as date, l.location, 'lost' as type FROM lost_items l WHERE l.status = 'active' ORDER BY l.date_reported DESC LIMIT ?");
    $stmt->bind_param("i", $limit);
    $stmt->execute();
    $lostResult = $stmt->get_result();
    while ($row = $lostResult->fetch_assoc()) {
        $row['message'] = 'Lost ' . htmlspecialchars($row['title']) . ' in ' . htmlspecialchars($row['location']);
        $row['link'] = 'lost_item.php?id=' . $row['id'];
        $activities[] = $row;
    }

    // Get recent found items
    $stmt = $conn->prepare("SELECT f.id, f.title, f.date_found as date, f.location, 'found' as type FROM found_items f WHERE f.status = 'active' ORDER BY f.date_found DESC LIMIT ?");
    $stmt->bind_param("i", $limit);
    $stmt->execute();
    $foundResult = $stmt->get_result();
    while ($row = $foundResult->fetch_assoc()) {
        $row['message'] = 'Found ' . htmlspecialchars($row['title']) . ' in ' . htmlspecialchars($row['location']);
        $row['link'] = 'found_item.php?id=' . $row['id'];
        $activities[] = $row;
    }

    // Get recent matches
    $stmt = $conn->prepare("SELECT m.id, m.created_at as date, 'match' as type, l.title as lost_title, f.title as found_title FROM matches m JOIN lost_items l ON m.lost_item_id = l.id JOIN found_items f ON m.found_item_id = f.id ORDER BY m.created_at DESC LIMIT ?");
    $stmt->bind_param("i", $limit);
    $stmt->execute();
    $matchResult = $stmt->get_result();
    while ($row = $matchResult->fetch_assoc()) {
        $row['message'] = 'Matched ' . htmlspecialchars($row['lost_title']) . ' with ' . htmlspecialchars($row['found_title']);
        $row['link'] = 'admin/review_match.php?id=' . $row['id']; // Assuming an admin page for matches
        $activities[] = $row;
    }

    // Sort all activities by date in descending order
    usort($activities, function($a, $b) {
        return strtotime($b['date']) - strtotime($a['date']);
    });

    // Limit the total number of activities
    return array_slice($activities, 0, $limit);
}

/**
 * Get user's reports (lost and found)
 * @param int $userId User ID
 * @return array User's reports
 */
function getUserReports($userId) {
    global $conn;
    $reports = [
        'lost' => [],
        'found' => [],
        'matched' => []
    ];
    
    // Get user's lost items
    $stmt = $conn->prepare("SELECT l.*, c.name as category_name 
                           FROM lost_items l 
                           JOIN categories c ON l.category_id = c.id 
                           WHERE l.user_id = ? 
                           ORDER BY l.date_reported DESC");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $lostResult = $stmt->get_result();
    
    while ($row = $lostResult->fetch_assoc()) {
        if ($row['status'] === 'matched' || $row['status'] === 'returned') {
            $reports['matched'][] = array_merge($row, ['type' => 'lost']);
        } else {
            $reports['lost'][] = $row;
        }
    }
    
    // Get user's found items
    $stmt = $conn->prepare("SELECT f.*, c.name as category_name 
                           FROM found_items f 
                           JOIN categories c ON f.category_id = c.id 
                           WHERE f.user_id = ? 
                           ORDER BY f.date_reported DESC");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $foundResult = $stmt->get_result();
    
    while ($row = $foundResult->fetch_assoc()) {
        if ($row['status'] === 'matched' || $row['status'] === 'returned') {
            $reports['matched'][] = array_merge($row, ['type' => 'found']);
        } else {
            $reports['found'][] = $row;
        }
    }
    
    // Sort matched items by date
    usort($reports['matched'], function($a, $b) {
        return strtotime($b['date_reported']) - strtotime($a['date_reported']);
    });
    
    return $reports;
}

/**
 * Search items
 * @param array $filters Search filters
 * @return array Search results
 */
function searchItems($filters) {
    global $conn;
    $items = [];
    
    // Build query
    $lostQuery = "SELECT l.*, 'lost' as type, c.name as category_name, u.full_name as owner_name, d.name as department_name 
                 FROM lost_items l 
                 JOIN categories c ON l.category_id = c.id 
                 JOIN users u ON l.user_id = u.id 
                 JOIN departments d ON u.department = d.id 
                 WHERE 1=1";
                 
    $foundQuery = "SELECT f.*, 'found' as type, c.name as category_name, u.full_name as finder_name, d.name as department_name 
                  FROM found_items f 
                  JOIN categories c ON f.category_id = c.id 
                  JOIN users u ON f.user_id = u.id 
                  JOIN departments d ON u.department = d.id 
                  WHERE 1=1";
    
    $params = [];
    $types = "";
    
    // Add filters
    if (!empty($filters['type']) && $filters['type'] !== 'all') {
        if ($filters['type'] === 'matched') {
            $lostQuery .= " AND l.status = 'matched'";
            $foundQuery .= " AND f.status = 'matched'";
        } else if ($filters['type'] === 'lost') {
            $foundQuery = ""; // Only search lost items
        } else if ($filters['type'] === 'found') {
            $lostQuery = ""; // Only search found items
        }
    }
    
    if (!empty($filters['category'])) {
        $lostQuery .= " AND l.category_id = ?";
        $foundQuery .= " AND f.category_id = ?";
        $params[] = $filters['category'];
        $types .= "i";
    }
    
    if (!empty($filters['department'])) {
        $lostQuery .= " AND l.department_id = ?";
        $foundQuery .= " AND f.department_id = ?";
        $params[] = $filters['department'];
        $types .= "i";
    }
    
    if (!empty($filters['date_from'])) {
        $lostQuery .= " AND l.date_lost >= ?";
        $foundQuery .= " AND f.date_found >= ?";
        $params[] = $filters['date_from'];
        $types .= "s";
    }
    
    if (!empty($filters['date_to'])) {
        $lostQuery .= " AND l.date_lost <= ?";
        $foundQuery .= " AND f.date_found <= ?";
        $params[] = $filters['date_to'];
        $types .= "s";
    }
    
    if (!empty($filters['keyword'])) {
        $keyword = "%{$filters['keyword']}%";
        $lostQuery .= " AND (l.title LIKE ? OR l.description LIKE ? OR l.location LIKE ?)";
        $foundQuery .= " AND (f.title LIKE ? OR f.description LIKE ? OR f.location LIKE ?)";
        $params[] = $keyword;
        $params[] = $keyword;
        $params[] = $keyword;
        $types .= "sss";
    }
    
    // Execute lost items query if needed
    if ($lostQuery !== "") {
        $lostQuery .= " ORDER BY l.date_reported DESC";
        $stmt = $conn->prepare($lostQuery);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $lostResult = $stmt->get_result();
        
        while ($row = $lostResult->fetch_assoc()) {
            $items[] = $row;
        }
    }
    
    // Execute found items query if needed
    if ($foundQuery !== "") {
        $foundQuery .= " ORDER BY f.date_reported DESC";
        $stmt = $conn->prepare($foundQuery);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $foundResult = $stmt->get_result();
        
        while ($row = $foundResult->fetch_assoc()) {
            $items[] = $row;
        }
    }
    
    // Sort by date_reported
    usort($items, function($a, $b) {
        return strtotime($b['date_reported']) - strtotime($a['date_reported']);
    });
    
    return $items;
}

/**
 * Truncate text to a specific length
 * @param string $text Text to truncate
 * @param int $length Maximum length
 * @param string $append String to append if truncated
 * @return string Truncated text
 */
function truncateText($text, $length = 100, $append = '...') {
    if (strlen($text) <= $length) {
        return $text;
    }
    
    $text = substr($text, 0, $length);
    $text = substr($text, 0, strrpos($text, ' '));
    return $text . $append;
}

/**
 * Generate a random string
 * @param int $length Length of the string
 * @return string Random string
 */
function generateRandomString($length = 10) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $charactersLength = strlen($characters);
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, $charactersLength - 1)];
    }
    return $randomString;
}

/**
 * Delete an image file
 * @param string $filename Filename to delete
 * @return bool True if deleted, false otherwise
 */
function deleteImage($filename) {
    if (empty($filename)) return true;
    
    $filepath = '../uploads/' . $filename;
    if (file_exists($filepath)) {
        return unlink($filepath);
    }
    return false;
}

/**
 * Get status badge HTML
 * @param string $status Item status
 * @return string HTML for status badge
 */
function getStatusBadge($status) {
    $badgeClass = '';
    $statusText = str_replace('_', ' ', $status);
    
    switch ($status) {
        case 'active':
            $badgeClass = 'bg-primary';
            break;
        case 'pending_match':
            $badgeClass = 'bg-warning';
            break;
        case 'matched':
            $badgeClass = 'bg-info';
            break;
        case 'completed':
            $badgeClass = 'bg-success';
            break;
        case 'claimed':
            $badgeClass = 'bg-success';
            break;
        case 'returned':
            $badgeClass = 'bg-success';
            break;
        case 'pending_review':
            $badgeClass = 'bg-warning';
            break;
        case 'confirmed':
            $badgeClass = 'bg-info';
            break;
        case 'rejected':
            $badgeClass = 'bg-danger';
            break;
        default:
            $badgeClass = 'bg-secondary';
    }
    
    return '<span class="badge ' . $badgeClass . '">' . ucwords($statusText) . '</span>';
}

/**
 * Get pagination HTML
 * @param int $currentPage Current page number
 * @param int $totalPages Total number of pages
 * @param string $url Base URL for pagination links
 * @return string HTML for pagination
 */
function getPagination($currentPage, $totalPages, $url) {
    if ($totalPages <= 1) return '';
    
    $html = '<nav aria-label="Page navigation"><ul class="pagination justify-content-center">';
    
    // Previous button
    if ($currentPage > 1) {
        $html .= '<li class="page-item"><a class="page-link" href="' . $url . '&page=' . ($currentPage - 1) . '" aria-label="Previous"><span aria-hidden="true">&laquo;</span></a></li>';
    } else {
        $html .= '<li class="page-item disabled"><a class="page-link" href="#" aria-label="Previous"><span aria-hidden="true">&laquo;</span></a></li>';
    }
    
    // Page numbers
    $startPage = max(1, $currentPage - 2);
    $endPage = min($totalPages, $startPage + 4);
    
    if ($endPage - $startPage < 4) {
        $startPage = max(1, $endPage - 4);
    }
    
    for ($i = $startPage; $i <= $endPage; $i++) {
        if ($i == $currentPage) {
            $html .= '<li class="page-item active"><a class="page-link" href="#">' . $i . '</a></li>';
        } else {
            $html .= '<li class="page-item"><a class="page-link" href="' . $url . '&page=' . $i . '">' . $i . '</a></li>';
        }
    }
    
    // Next button
    if ($currentPage < $totalPages) {
        $html .= '<li class="page-item"><a class="page-link" href="' . $url . '&page=' . ($currentPage + 1) . '" aria-label="Next"><span aria-hidden="true">&raquo;</span></a></li>';
    } else {
        $html .= '<li class="page-item disabled"><a class="page-link" href="#" aria-label="Next"><span aria-hidden="true">&raquo;</span></a></li>';
    }
    
    $html .= '</ul></nav>';
    
    return $html;
}

/**
 * Get statistics for dashboard
 * @return array Statistics
 */
function getStatistics() {
    global $conn;
    
    $stats = [
        'total_users' => 0,
        'total_lost' => 0,
        'total_found' => 0,
        'total_matched' => 0,
        'total_completed_matches' => 0
    ];
    
    // Get total users
    $result = $conn->query("SELECT COUNT(*) as count FROM users");
    $stats['total_users'] = $result->fetch_assoc()['count'];
    
    // Get total lost items
    $result = $conn->query("SELECT COUNT(*) as count FROM lost_items");
    $stats['total_lost'] = $result->fetch_assoc()['count'];
    
    // Get total found items
    $result = $conn->query("SELECT COUNT(*) as count FROM found_items");
    $stats['total_found'] = $result->fetch_assoc()['count'];
    
    // Get total matches
    $result = $conn->query("SELECT COUNT(*) as count FROM matches");
    $stats['total_matched'] = $result->fetch_assoc()['count'];
    
    // Get total completed matches
    $result = $conn->query("SELECT COUNT(*) as count FROM matches WHERE status = 'completed'");
    $stats['total_completed_matches'] = $result->fetch_assoc()['count'];
    
    return $stats;
}

/**
 * Get chart data for dashboard
 * @param string $type Chart type (lost, found, matches)
 * @param int $months Number of months to include
 * @return array Chart data
 */
function getChartData($type, $months = 6) {
    global $conn;
    
    $data = [
        'labels' => [],
        'values' => []
    ];
    
    // Generate labels for last X months
    $currentMonth = date('n');
    $currentYear = date('Y');
    
    for ($i = $months - 1; $i >= 0; $i--) {
        $month = ($currentMonth - $i) % 12;
        if ($month <= 0) $month += 12;
        $year = $currentYear;
        if ($currentMonth - $i <= 0) $year--;
        
        $data['labels'][] = date('M Y', mktime(0, 0, 0, $month, 1, $year));
        
        // Get start and end dates for the month
        $startDate = date('Y-m-d', mktime(0, 0, 0, $month, 1, $year));
        $endDate = date('Y-m-t', mktime(0, 0, 0, $month, 1, $year));
        
        // Get count based on type
        switch ($type) {
            case 'lost':
                $sql = "SELECT COUNT(*) as count FROM lost_items WHERE date_reported BETWEEN ? AND ?"; 
                break;
            case 'found':
                $sql = "SELECT COUNT(*) as count FROM found_items WHERE date_reported BETWEEN ? AND ?"; 
                break;
            case 'matches':
                $sql = "SELECT COUNT(*) as count FROM matches WHERE created_at BETWEEN ? AND ?"; 
                break;
            default:
                $sql = "SELECT 0 as count";
        }
        
        if ($type != 'default') {
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ss", $startDate, $endDate);
            $stmt->execute();
            $result = $stmt->get_result();
            $data['values'][] = $result->fetch_assoc()['count'];
        } else {
            $data['values'][] = 0;
        }
    }
    
    return $data;
}

/**
 * Get category distribution data for charts
 * @param string $type Item type (lost, found, all)
 * @return array Category distribution data
 */
function getCategoryDistribution($type = 'all') {
    global $conn;
    
    $data = [
        'labels' => [],
        'values' => []
    ];
    
    // Get categories
    $result = $conn->query("SELECT id, name FROM categories ORDER BY name ASC");
    $categories = [];
    while ($row = $result->fetch_assoc()) {
        $categories[$row['id']] = $row['name'];
    }
    
    // Get counts for each category
    foreach ($categories as $id => $name) {
        $data['labels'][] = $name;
        
        switch ($type) {
            case 'lost':
                $sql = "SELECT COUNT(*) as count FROM lost_items WHERE category_id = ?"; 
                break;
            case 'found':
                $sql = "SELECT COUNT(*) as count FROM found_items WHERE category_id = ?"; 
                break;
            case 'all':
            default:
                $sql = "SELECT 
                        (SELECT COUNT(*) FROM lost_items WHERE category_id = ?) + 
                        (SELECT COUNT(*) FROM found_items WHERE category_id = ?) as count";
                break;
        }
        
        $stmt = $conn->prepare($sql);
        if ($type == 'all') {
            $stmt->bind_param("ii", $id, $id);
        } else {
            $stmt->bind_param("i", $id);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        $data['values'][] = $result->fetch_assoc()['count'];
    }
    
    return $data;
}

/**
 * Get department distribution data for charts
 * @param string $type Data type (users, items)
 * @return array Department distribution data
 */
function getDepartmentDistribution($type = 'items') {
    global $conn;
    
    $data = [
        'labels' => [],
        'values' => []
    ];
    
    // Get departments
    $result = $conn->query("SELECT id, name FROM departments ORDER BY name ASC");
    $departments = [];
    while ($row = $result->fetch_assoc()) {
        $departments[$row['id']] = $row['name'];
    }
    
    // Get counts for each department
    foreach ($departments as $id => $name) {
        $data['labels'][] = $name;
        
        switch ($type) {
            case 'users':
                $sql = "SELECT COUNT(*) as count FROM users WHERE department = ?"; 
                break;
            case 'items':
            default:
                $sql = "SELECT 
                        (SELECT COUNT(*) FROM lost_items l JOIN users u ON l.user_id = u.id WHERE u.department = ?) + 
                        (SELECT COUNT(*) FROM found_items f JOIN users u ON f.user_id = u.id WHERE u.department = ?) as count";
                break;
        }
        
        $stmt = $conn->prepare($sql);
        if ($type == 'items') {
            $stmt->bind_param("ii", $id, $id);
        } else {
            $stmt->bind_param("i", $id);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        $data['values'][] = $result->fetch_assoc()['count'];
    }
    
    return $data;
}

/**
 * Get recent activity for dashboard
 * @param int $limit Maximum number of items to return
 * @return array Recent activity
 */
function getRecentActivityList($limit = 10) {
    global $conn;
    
    $activity = [];
    
    // Get recent lost items
    $stmt = $conn->prepare("SELECT l.id, l.title, l.date_reported as created_at, 'lost' as type, u.full_name as user_name 
                           FROM lost_items l 
                           JOIN users u ON l.user_id = u.id 
                           ORDER BY l.date_reported DESC 
                           LIMIT ?");
    $stmt->bind_param("i", $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $activity[] = $row;
    }
    
    // Get recent found items
    $stmt = $conn->prepare("SELECT f.id, f.title, f.date_reported as created_at, 'found' as type, u.full_name as user_name 
                           FROM found_items f 
                           JOIN users u ON f.user_id = u.id 
                           ORDER BY f.date_reported DESC 
                           LIMIT ?");
    $stmt->bind_param("i", $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $activity[] = $row;
    }
    
    // Get recent matches
    $stmt = $conn->prepare("SELECT m.id, l.title as lost_title, f.title as found_title, m.created_at, 'match' as type, 
                           m.status as match_status 
                           FROM matches m 
                           JOIN lost_items l ON m.lost_item_id = l.id 
                           JOIN found_items f ON m.found_item_id = f.id 
                           ORDER BY m.created_at DESC 
                           LIMIT ?");
    $stmt->bind_param("i", $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $activity[] = $row;
    }
    
    // Sort by created_at
    usort($activity, function($a, $b) {
        return strtotime($b['created_at']) - strtotime($a['created_at']);
    });
    
    // Limit to specified number
    return array_slice($activity, 0, $limit);
}
?>