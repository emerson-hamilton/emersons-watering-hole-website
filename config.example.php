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

// ===== SYNC SETTINGS (scheduled sync via cron_sync.php) =====
define('SYNC_INTERVAL_MINUTES', 60);  // how often new photos are added to the gallery
// Keep these outside the web root. The log directory must exist.
define('SYNC_LOG_PATH',   '/home/<web-user>/emcam_logs/cron_sync.log');
define('SYNC_STATE_PATH', '/home/<web-user>/emcam_logs/cron_sync.last');
define('SYNC_LOCK_PATH',  '/home/<web-user>/emcam_logs/cron_sync.lock');

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
