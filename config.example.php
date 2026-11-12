<?php
/**
 * Emerson's Watering Hole Cam - Configuration
 * Edit the database credentials below with your cPanel database info
 */

// ===== DATABASE CONFIGURATION =====
// Get your database credentials from cPanel -> MySQL Databases
$db_host = 'localhost';         // Usually localhost
$db_name = 'db_name';           // Replace with your actual database name
$db_user = 'db_username';       // Replace with your cPanel username
$db_pass = 'password';          // Replace with your database password

// ===== FILE PATHS =====
$uploads_dir = __DIR__ . '/uploads';         // Where raspi uploads images
$archive_dir = __DIR__ . '/uploads_archive'; // Where deleted images are moved
$thumb_dir   = __DIR__ . '/thumbnails';      // Cache for thumbnails

// ===== SITE SETTINGS =====
define('IMAGES_PER_PAGE', 50);
define('THUMB_WIDTH', 300);
define('THUMB_HEIGHT', 225);
define('MAX_LABEL_LENGTH', 255);

// ===== DATABASE CONNECTION =====
try {
    $pdo = new PDO(
        "mysql:host=$db_host;dbname=$db_name;charset=utf8mb4",
        $db_user,
        $db_pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException $e) {
    die('Database connection failed. Please check config.php. Error: ' . htmlspecialchars($e->getMessage()));
}

// ===== ENSURE DIRECTORIES EXIST =====
if (!is_dir($uploads_dir)) mkdir($uploads_dir, 0755, true);
if (!is_dir($archive_dir)) mkdir($archive_dir, 0755, true);
if (!is_dir($thumb_dir)) mkdir($thumb_dir, 0755, true);

// ===== SESSION CONFIGURATION =====
session_start();
define('SESSION_TIMEOUT', 3600); // 1 hour

// ===== UTILITY FUNCTIONS =====

/**
 * Check admin authentication
 */
function is_admin_authenticated() {
    if (!isset($_SESSION['admin_logged_in'])) {
        return false;
    }
    
    // Check session timeout
    if (isset($_SESSION['last_activity']) && time() - $_SESSION['last_activity'] > SESSION_TIMEOUT) {
        session_destroy();
        return false;
    }
    
    $_SESSION['last_activity'] = time();
    return true;
}

/**
 * Redirect to login if not authenticated (for admin pages)
 */
function require_admin_login() {
    if (!is_admin_authenticated()) {
        header('Location: admin.php?login=required');
        exit;
    }
}

/**
 * Extract date from filename in format motion_YYYYMMDD_HHMMSS.jpg
 * Supports both old IMG_ and new motion_ formats for backwards compatibility
 */
function extract_date_from_filename($filename) {
    if (preg_match('/(IMG_|motion_)(\d{8})_(\d{6})/', $filename, $matches)) {
        $date_str = $matches[2] . ' ' . $matches[3];
        return DateTime::createFromFormat('Ymd His', $date_str);
    }
    return null;
}

/**
 * Extract folder date from path in format YYYY-MM-DD
 */
function extract_folder_date($folder_path) {
    if (preg_match('/(\d{4})-(\d{2})-(\d{2})/', basename($folder_path), $matches)) {
        return $matches[1] . '-' . $matches[2] . '-' . $matches[3];
    }
    return null;
}

/**
 * Generate thumbnail for image
 */
function generate_thumbnail($source_path, $thumb_path) {
    if (file_exists($thumb_path)) {
        return true;
    }
    
    if (!file_exists($source_path)) {
        return false;
    }
    
    // Use PHP's GD library
    $image = imagecreatefromjpeg($source_path);
    if (!$image) {
        return false;
    }
    
    $orig_width = imagesx($image);
    $orig_height = imagesy($image);
    
    // Calculate aspect ratio
    $ratio = min(THUMB_WIDTH / $orig_width, THUMB_HEIGHT / $orig_height);
    
    $new_width = round($orig_width * $ratio);
    $new_height = round($orig_height * $ratio);
    
    // Create thumbnail
    $thumb = imagecreatetruecolor($new_width, $new_height);
    imagecopyresampled($thumb, $image, 0, 0, 0, 0, $new_width, $new_height, $orig_width, $orig_height);
    
    imagejpeg($thumb, $thumb_path, 85);
    imagedestroy($image);
    imagedestroy($thumb);
    
    return true;
}

/**
 * Sync images from filesystem with database
 * Discovers new images uploaded by raspi and adds them to DB
 * Supports recursive YYYY-MM-DD folder structure
 */
function sync_images_from_filesystem($pdo, $uploads_dir, $days_back = 3) {
    global $thumb_dir;
    
    if (!is_dir($uploads_dir)) {
        return false;
    }
    
    $synced_count = 0;
    
    if ($days_back === 0) {
        // Sync all folders
        $folders_to_scan = get_all_date_folders($uploads_dir);
    } else {
        // Sync only recent days
        $folders_to_scan = get_recent_date_folders($uploads_dir, $days_back);
    }
    
    foreach ($folders_to_scan as $folder_path) {
        $folder_date = extract_folder_date($folder_path);
        if (!$folder_date) {
            continue; // Skip invalid folder names
        }
        
        if (!is_dir($folder_path)) {
            continue;
        }
        
        $files = array_filter(scandir($folder_path), function($file) {
            return strtolower(pathinfo($file, PATHINFO_EXTENSION)) === 'jpg';
        });
        
        foreach ($files as $file) {
            $filename = basename($file);
            
            // Check if already in database
            $stmt = $pdo->prepare('SELECT id FROM cam_images WHERE filename = ?');
            $stmt->execute([$filename]);
            if ($stmt->fetch()) {
                continue; // Already in DB
            }
            
            // Extract capture date from filename
            $captured_date = extract_date_from_filename($filename);
            if (!$captured_date) {
                continue; // Skip files with invalid names
            }
            
            $upload_date = new DateTime();
            
            // Insert into database with folder_date
            $stmt = $pdo->prepare('
                INSERT INTO cam_images (filename, folder_date, upload_date, captured_date, display_order)
                VALUES (?, ?, ?, ?, ?)
            ');
            
            $stmt->execute([
                $filename,
                $folder_date,
                $upload_date->format('Y-m-d H:i:s'),
                $captured_date->format('Y-m-d H:i:s'),
                0
            ]);
            
            $synced_count++;
            
            // Generate thumbnail
            $image_path = $folder_path . '/' . $filename;
            $thumb_path = $thumb_dir . '/' . $folder_date . '_' . pathinfo($filename, PATHINFO_FILENAME) . '_thumb.jpg';
            @mkdir(dirname($thumb_path), 0755, true);
            generate_thumbnail($image_path, $thumb_path);
        }
    }
    
    return $synced_count;
}

/**
 * Get all date folders (YYYY-MM-DD)
 */
function get_all_date_folders($uploads_dir) {
    $folders = [];
    $items = @scandir($uploads_dir);
    
    if (!$items) return $folders;
    
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        $path = $uploads_dir . '/' . $item;
        if (is_dir($path) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $item)) {
            $folders[] = $path;
        }
    }
    
    rsort($folders); // Most recent first
    return $folders;
}

/**
 * Get date folders from last N days
 */
function get_recent_date_folders($uploads_dir, $days = 3) {
    $folders = [];
    
    for ($i = 0; $i < $days; $i++) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $path = $uploads_dir . '/' . $date;
        if (is_dir($path)) {
            $folders[] = $path;
        }
    }
    
    return $folders;
}

/**
 * Format date for display
 */
function format_display_date($date_str) {
    $date = new DateTime($date_str);
    return $date->format('F j, Y \\a\\t g:ia');
}

/**
 * Get thumbnail path for an image (with optional folder_date for date-based structure)
 */
function get_thumbnail_path($filename, $folder_date = null) {
    $base = pathinfo($filename, PATHINFO_FILENAME) . '_thumb.jpg';
    if ($folder_date) {
        return 'thumbnails/' . $folder_date . '_' . $base;
    }
    return 'thumbnails/' . $base;
}

/**
 * Safely move image to archive (preserving date folder structure)
 */
function archive_image($pdo, $filename, $uploads_dir, $archive_dir) {
    // Get folder_date from database
    $stmt = $pdo->prepare('SELECT folder_date FROM cam_images WHERE filename = ?');
    $stmt->execute([$filename]);
    $row = $stmt->fetch();
    
    if (!$row) {
        return false; // Image not found
    }
    
    $folder_date = $row['folder_date'];
    $source = $uploads_dir . '/' . $folder_date . '/' . $filename;
    $archive_dest_dir = $archive_dir . '/' . $folder_date;
    $dest = $archive_dest_dir . '/' . $filename;
    
    // Mark as archived in DB
    $stmt = $pdo->prepare('UPDATE cam_images SET is_archived = 1 WHERE filename = ?');
    $stmt->execute([$filename]);
    
    // Move file if it exists
    if (file_exists($source)) {
        @mkdir($archive_dest_dir, 0755, true);
        return rename($source, $dest);
    }
    
    return true;
}

/**
 * Restore image from archive (preserving date folder structure)
 */
function restore_image($pdo, $filename, $uploads_dir, $archive_dir) {
    // Get folder_date from database
    $stmt = $pdo->prepare('SELECT folder_date FROM cam_images WHERE filename = ?');
    $stmt->execute([$filename]);
    $row = $stmt->fetch();
    
    if (!$row) {
        return false; // Image not found
    }
    
    $folder_date = $row['folder_date'];
    $source = $archive_dir . '/' . $folder_date . '/' . $filename;
    $uploads_dest_dir = $uploads_dir . '/' . $folder_date;
    $dest = $uploads_dest_dir . '/' . $filename;
    
    // Mark as not archived in DB
    $stmt = $pdo->prepare('UPDATE cam_images SET is_archived = 0 WHERE filename = ?');
    $stmt->execute([$filename]);
    
    // Move file if it exists
    if (file_exists($source)) {
        @mkdir($uploads_dest_dir, 0755, true);
        return rename($source, $dest);
    }
    
    return true;
}

?>
