<?php
/**
 * Emerson's Watering Hole Cam - API Endpoints (Updated)
 * Handles all AJAX requests from frontend
 * Now with date grouping, favorites, and calendar support
 */

require_once 'config.php';
require_once __DIR__ . '/includes/sync.php';

header('Content-Type: application/json');

// Get action from request
$action = $_GET['action'] ?? $_POST['action'] ?? null;

if (!$action) {
    http_response_code(400);
    die(json_encode(['error' => 'No action specified']));
}

// ===== PUBLIC ENDPOINTS =====

if ($action === 'get_images') {
    handle_get_images();
}
elseif ($action === 'get_images_by_date') {
    handle_get_images_by_date();
}
elseif ($action === 'get_calendar_data') {
    handle_get_calendar_data();
}
elseif ($action === 'admin_login') {
    handle_admin_login();
}
elseif ($action === 'admin_logout') {
    handle_admin_logout();
}
elseif ($action === 'check_auth') {
    handle_check_auth();
}
// ===== ADMIN ENDPOINTS (require authentication) =====
elseif ($action === 'get_admin_images') {
    require_admin_login();
    handle_get_admin_images();
}
elseif ($action === 'get_admin_images_by_date') {
    require_admin_login();
    handle_get_admin_images_by_date();
}
elseif ($action === 'update_label') {
    require_admin_login();
    handle_update_label();
}
elseif ($action === 'toggle_favorite') {
    require_admin_login();
    handle_toggle_favorite();
}
elseif ($action === 'archive_image') {
    require_admin_login();
    handle_archive_image();
}
elseif ($action === 'restore_image') {
    require_admin_login();
    handle_restore_image();
}
elseif ($action === 'bulk_archive') {
    require_admin_login();
    handle_bulk_archive();
}
elseif ($action === 'bulk_restore') {
    require_admin_login();
    handle_bulk_restore();
}
elseif ($action === 'bulk_label') {
    require_admin_login();
    handle_bulk_label();
}
elseif ($action === 'bulk_favorite') {
    require_admin_login();
    handle_bulk_favorite();
}
elseif ($action === 'sync_images') {
    require_admin_login();
    handle_sync_images();
}
elseif ($action === 'delete_archived_image') {
    require_admin_login();
    handle_delete_archived_image();
}
elseif ($action === 'check_orphaned_images') {
    require_admin_login();
    handle_check_orphaned_images();
}
elseif ($action === 'cleanup_orphaned_images') {
    require_admin_login();
    handle_cleanup_orphaned_images();
}
else {
    http_response_code(404);
    die(json_encode(['error' => 'Unknown action']));
}

// ===== ENDPOINT HANDLERS =====

/**
 * Public: Get images grouped by date for date picker
 */
function handle_get_images() {
    global $pdo, $uploads_dir;
    
    
    $view = $_GET['view'] ?? 'all'; // 'all' or 'favorites'
    
    // Get list of dates with image counts
    $where = 'WHERE is_archived = 0';
    if ($view === 'favorites') {
        $where .= ' AND is_favorite = 1';
    }
    
    $stmt = $pdo->prepare("
        SELECT folder_date, COUNT(*) as count
        FROM cam_images
        $where
        GROUP BY folder_date
        ORDER BY folder_date DESC
    ");
    $stmt->execute();
    $dates = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'dates' => $dates,
        'view' => $view
    ]);
}

/**
 * Public: Get images for specific date
 */
function handle_get_images_by_date() {
    global $pdo;
    
    $date = $_GET['date'] ?? null;
    $view = $_GET['view'] ?? 'all'; // 'all' or 'favorites'
    
    if (!$date || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid date format']);
        return;
    }
    
    $where = 'WHERE folder_date = ? AND is_archived = 0';
    $params = [$date];
    
    if ($view === 'favorites') {
        $where .= ' AND is_favorite = 1';
    }
    
    $stmt = $pdo->prepare("
        SELECT id, filename, folder_date, captured_date, label, is_favorite
        FROM cam_images
        $where
        ORDER BY captured_date DESC
    ");
    $stmt->execute($params);
    $images = $stmt->fetchAll();
    
    // Add thumbnail and check if file exists
    foreach ($images as &$img) {
        $img['thumbnail'] = get_thumbnail_path($img['filename'], $img['folder_date']);
    }
    
    echo json_encode([
        'success' => true,
        'date' => $date,
        'images' => $images,
        'count' => count($images),
        'view' => $view
    ]);
}

/**
 * Public: Get calendar data (which dates have images)
 */
function handle_get_calendar_data() {
    global $pdo;
    
    $year = (int)($_GET['year'] ?? date('Y'));
    $month = (int)($_GET['month'] ?? date('m'));
    
    // Get all dates in this month that have images
    $start_date = "$year-" . str_pad($month, 2, '0', STR_PAD_LEFT) . "-01";
    $end_date = date('Y-m-t', strtotime($start_date));
    
    $stmt = $pdo->prepare("
        SELECT folder_date, COUNT(*) as count, SUM(is_favorite) as favorites
        FROM cam_images
        WHERE is_archived = 0 AND folder_date BETWEEN ? AND ?
        GROUP BY folder_date
    ");
    $stmt->execute([$start_date, $end_date]);
    $dates = $stmt->fetchAll();
    
    // Convert to day-based format for calendar
    $calendar = [];
    foreach ($dates as $row) {
        $day = (int)date('j', strtotime($row['folder_date']));
        $calendar[$day] = [
            'date' => $row['folder_date'],
            'count' => $row['count'],
            'favorites' => $row['favorites'] ?? 0
        ];
    }
    
    echo json_encode([
        'success' => true,
        'year' => $year,
        'month' => $month,
        'calendar' => $calendar
    ]);
}

/**
 * Admin: Get images grouped by date
 */
function handle_get_admin_images_by_date() {
    global $pdo;
    
    $show_archived = isset($_GET['archived']) ? (bool)$_GET['archived'] : false;
    $view = $_GET['view'] ?? 'all'; // 'all' or 'favorites'
    
    // Get list of dates with image counts
    $where = 'WHERE is_archived = ?';
    $params = [$show_archived ? 1 : 0];
    
    if ($view === 'favorites') {
        $where .= ' AND is_favorite = 1';
    }
    
    $stmt = $pdo->prepare("
        SELECT folder_date, COUNT(*) as count, SUM(is_favorite) as favorites
        FROM cam_images
        $where
        GROUP BY folder_date
        ORDER BY folder_date DESC
    ");
    $stmt->execute($params);
    $dates = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'dates' => $dates,
        'show_archived' => $show_archived,
        'view' => $view
    ]);
}

/**
 * Admin: Get admin images for specific date
 */
function handle_get_admin_images() {
    global $pdo;
    
    $date = $_GET['date'] ?? null;
    $show_archived = isset($_GET['archived']) ? (bool)$_GET['archived'] : false;
    $view = $_GET['view'] ?? 'all'; // 'all' or 'favorites'
    
    if (!$date || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid date format']);
        return;
    }
    
    $where = 'WHERE folder_date = ? AND is_archived = ?';
    $params = [$date, $show_archived ? 1 : 0];
    
    if ($view === 'favorites') {
        $where .= ' AND is_favorite = 1';
    }
    
    $stmt = $pdo->prepare("
        SELECT id, filename, folder_date, captured_date, label, is_favorite, is_archived
        FROM cam_images
        $where
        ORDER BY captured_date DESC
    ");
    $stmt->execute($params);
    $images = $stmt->fetchAll();
    
    // Add thumbnail
    foreach ($images as &$img) {
        $img['thumbnail'] = get_thumbnail_path($img['filename'], $img['folder_date']);
    }
    
    echo json_encode([
        'success' => true,
        'date' => $date,
        'images' => $images,
        'count' => count($images),
        'show_archived' => $show_archived,
        'view' => $view
    ]);
}

/**
 * Admin: Update image label
 */
function handle_update_label() {
    global $pdo;
    
    $id = (int)($_POST['id'] ?? 0);
    $label = substr($_POST['label'] ?? '', 0, MAX_LABEL_LENGTH);
    
    if (!$id) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid image ID']);
        return;
    }
    
    $stmt = $pdo->prepare('UPDATE cam_images SET label = ? WHERE id = ?');
    $stmt->execute([$label, $id]);
    
    echo json_encode(['success' => true, 'label' => $label]);
}

/**
 * Admin: Toggle favorite status
 */
function handle_toggle_favorite() {
    global $pdo;
    
    $id = (int)($_POST['id'] ?? 0);
    
    if (!$id) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid image ID']);
        return;
    }
    
    // Get current favorite status
    $stmt = $pdo->prepare('SELECT is_favorite FROM cam_images WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    
    if (!$row) {
        http_response_code(404);
        echo json_encode(['error' => 'Image not found']);
        return;
    }
    
    // Toggle it
    $new_favorite = $row['is_favorite'] ? 0 : 1;
    
    $stmt = $pdo->prepare('UPDATE cam_images SET is_favorite = ? WHERE id = ?');
    $stmt->execute([$new_favorite, $id]);
    
    echo json_encode(['success' => true, 'is_favorite' => $new_favorite]);
}

/**
 * Admin: Archive single image
 */
function handle_archive_image() {
    global $pdo, $uploads_dir, $archive_dir;
    
    $id = (int)($_POST['id'] ?? 0);
    
    if (!$id) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid image ID']);
        return;
    }
    
    // Get filename
    $stmt = $pdo->prepare('SELECT filename FROM cam_images WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    
    if (!$row) {
        http_response_code(404);
        echo json_encode(['error' => 'Image not found']);
        return;
    }
    
    if (archive_image($pdo, $row['filename'], $uploads_dir, $archive_dir)) {
        echo json_encode(['success' => true]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to archive image']);
    }
}

/**
 * Admin: Restore image from archive
 */
function handle_restore_image() {
    global $pdo, $uploads_dir, $archive_dir;
    
    $id = (int)($_POST['id'] ?? 0);
    
    if (!$id) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid image ID']);
        return;
    }
    
    // Get filename
    $stmt = $pdo->prepare('SELECT filename FROM cam_images WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    
    if (!$row) {
        http_response_code(404);
        echo json_encode(['error' => 'Image not found']);
        return;
    }
    
    if (restore_image($pdo, $row['filename'], $uploads_dir, $archive_dir)) {
        echo json_encode(['success' => true]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to restore image']);
    }
}

/**
 * Admin: Bulk archive images
 */
function handle_bulk_archive() {
    global $pdo, $uploads_dir, $archive_dir;
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['ids']) || !is_array($input['ids'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid data']);
        return;
    }
    
    $errors = [];
    foreach ($input['ids'] as $id) {
        $stmt = $pdo->prepare('SELECT filename FROM cam_images WHERE id = ?');
        $stmt->execute([(int)$id]);
        $row = $stmt->fetch();
        
        if ($row) {
            if (!archive_image($pdo, $row['filename'], $uploads_dir, $archive_dir)) {
                $errors[] = $row['filename'];
            }
        }
    }
    
    if (empty($errors)) {
        echo json_encode(['success' => true]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'errors' => $errors]);
    }
}

/**
 * Admin: Bulk restore images
 */
function handle_bulk_restore() {
    global $pdo, $uploads_dir, $archive_dir;
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['ids']) || !is_array($input['ids'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid data']);
        return;
    }
    
    $errors = [];
    foreach ($input['ids'] as $id) {
        $stmt = $pdo->prepare('SELECT filename FROM cam_images WHERE id = ?');
        $stmt->execute([(int)$id]);
        $row = $stmt->fetch();
        
        if ($row) {
            if (!restore_image($pdo, $row['filename'], $uploads_dir, $archive_dir)) {
                $errors[] = $row['filename'];
            }
        }
    }
    
    if (empty($errors)) {
        echo json_encode(['success' => true]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'errors' => $errors]);
    }
}

/**
 * Admin: Bulk update labels
 */
function handle_bulk_label() {
    global $pdo;
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['ids']) || !is_array($input['ids']) || !isset($input['label'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid data']);
        return;
    }
    
    $label = substr($input['label'], 0, MAX_LABEL_LENGTH);
    $stmt = $pdo->prepare('UPDATE cam_images SET label = ? WHERE id = ?');
    
    foreach ($input['ids'] as $id) {
        $stmt->execute([$label, (int)$id]);
    }
    
    echo json_encode(['success' => true]);
}

/**
 * Admin: Bulk toggle favorite
 */
function handle_bulk_favorite() {
    global $pdo;
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['ids']) || !is_array($input['ids']) || !isset($input['favorite'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid data']);
        return;
    }
    
    $favorite = $input['favorite'] ? 1 : 0;
    $stmt = $pdo->prepare('UPDATE cam_images SET is_favorite = ? WHERE id = ?');
    
    foreach ($input['ids'] as $id) {
        $stmt->execute([$favorite, (int)$id]);
    }
    
    echo json_encode(['success' => true]);
}

/**
 * Admin: Sync images (with option to sync all or recent)
 */
function handle_sync_images() {
    global $pdo, $uploads_dir;
    
    $sync_all = isset($_POST['sync_all']) ? (bool)$_POST['sync_all'] : false;
    $days_back = $sync_all ? 0 : 3;
    
    $synced = sync_images_from_filesystem($pdo, $uploads_dir, $days_back);
    
    echo json_encode([
        'success' => true,
        'synced_count' => $synced,
        'sync_type' => $sync_all ? 'full' : 'recent'
    ]);
}

/**
 * Admin: Login
 */
function handle_admin_login() {
    global $pdo;
    
    $password = $_POST['password'] ?? '';
    
    // Get stored password hash
    $stmt = $pdo->prepare('SELECT setting_value FROM admin_settings WHERE setting_key = ?');
    $stmt->execute(['password_hash']);
    $row = $stmt->fetch();
    
    if (!$row) {
        http_response_code(500);
        echo json_encode(['error' => 'Authentication system not configured']);
        return;
    }
    
    // Verify password
    if (password_verify($password, $row['setting_value'])) {
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['last_activity'] = time();
        echo json_encode(['success' => true]);
    } else {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid password']);
    }
}

/**
 * Admin: Logout
 */
function handle_admin_logout() {
    session_destroy();
    echo json_encode(['success' => true]);
}

/**
 * Check if currently authenticated
 */
function handle_check_auth() {
    $authenticated = is_admin_authenticated();
    echo json_encode(['authenticated' => $authenticated]);
}

/**
 * Admin: Permanently delete archived image and its file
 */
function handle_delete_archived_image() {
    global $pdo, $archive_dir, $thumb_dir;
    
    $id = (int)($_POST['id'] ?? 0);
    
    if (!$id) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid image ID']);
        return;
    }
    
    // Get image details
    $stmt = $pdo->prepare('SELECT filename, folder_date FROM cam_images WHERE id = ? AND is_archived = 1');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    
    if (!$row) {
        http_response_code(404);
        echo json_encode(['error' => 'Archived image not found']);
        return;
    }
    
    // Delete the physical file
    $file_path = $archive_dir . '/' . $row['folder_date'] . '/' . $row['filename'];
    if (file_exists($file_path)) {
        @unlink($file_path);
    }
    
    // Delete the thumbnail file
    $thumb_path = $thumb_dir . '/' . $row['folder_date'] . '_' . pathinfo($row['filename'], PATHINFO_FILENAME) . '_thumb.jpg';
    if (file_exists($thumb_path)) {
        @unlink($thumb_path);
    }
    
    // Delete from database
    $stmt = $pdo->prepare('DELETE FROM cam_images WHERE id = ?');
    $stmt->execute([$id]);
    
    echo json_encode(['success' => true]);
}

/**
 * Admin: Check for orphaned/broken archived images and orphaned thumbnails
 */
function handle_check_orphaned_images() {
    global $pdo, $archive_dir, $thumb_dir;
    
    // Get all archived images
    $stmt = $pdo->prepare('
        SELECT id, filename, folder_date FROM cam_images WHERE is_archived = 1 ORDER BY folder_date DESC
    ');
    $stmt->execute();
    $archived = $stmt->fetchAll();
    
    $orphaned = [];
    $found_files = []; // Track thumbnails we've seen
    
    // Check archived images for missing files/thumbnails
    foreach ($archived as $img) {
        $issues = [];
        
        // Check if image file exists
        $file_path = $archive_dir . '/' . $img['folder_date'] . '/' . $img['filename'];
        if (!file_exists($file_path)) {
            $issues[] = 'missing_file';
        }
        
        // Check if thumbnail exists
        $thumb_path = $thumb_dir . '/' . $img['folder_date'] . '_' . pathinfo($img['filename'], PATHINFO_FILENAME) . '_thumb.jpg';
        $thumb_name = $img['folder_date'] . '_' . pathinfo($img['filename'], PATHINFO_FILENAME) . '_thumb.jpg';
        $found_files[] = $thumb_name; // Track this
        
        if (!file_exists($thumb_path)) {
            $issues[] = 'missing_thumbnail';
        }
        
        if (!empty($issues)) {
            $orphaned[] = [
                'id' => $img['id'],
                'filename' => $img['filename'],
                'folder_date' => $img['folder_date'],
                'issues' => $issues,
                'type' => 'broken_record'
            ];
        }
    }
    
    // Scan for orphaned thumbnails (thumbnail exists but no DB record)
    if (is_dir($thumb_dir)) {
        $thumbs = scandir($thumb_dir);
        foreach ($thumbs as $thumb) {
            if (strpos($thumb, '_thumb.jpg') === false) continue;
            if ($thumb === '.' || $thumb === '..') continue;
            
            // Extract folder_date and original filename from thumbnail name
            // Format: YYYY-MM-DD_[original_filename]_thumb.jpg
            $pattern = '/^(\d{4}-\d{2}-\d{2})_(.+)_thumb\.jpg$/';
            if (!preg_match($pattern, $thumb, $matches)) continue;
            
            $folder_date = $matches[1];
            $filename_without_ext = $matches[2];
            // Add .jpg back since DB stores filenames with extension
            $original_filename = $filename_without_ext . '.jpg';
            
            // Check if a record exists for this thumbnail (active OR archived)
            $stmt = $pdo->prepare('
                SELECT id FROM cam_images 
                WHERE filename = ? AND folder_date = ?
            ');
            $stmt->execute([$original_filename, $folder_date]);
            
            if (!$stmt->fetch()) {
                // Orphaned thumbnail - no DB record at all (neither active nor archived)
                $orphaned[] = [
                    'id' => null,
                    'filename' => $thumb,  // Show actual thumbnail filename, not reconstructed original
                    'folder_date' => $folder_date,
                    'issues' => ['orphaned_thumbnail'],
                    'type' => 'orphaned_thumbnail',
                    'thumbnail_name' => $thumb
                ];
            }
        }
    }
    
    echo json_encode([
        'success' => true,
        'orphaned_count' => count($orphaned),
        'orphaned' => $orphaned
    ]);
}

/**
 * Admin: Cleanup orphaned images (delete database records and orphaned thumbnail files)
 */
function handle_cleanup_orphaned_images() {
    global $pdo, $thumb_dir;
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['ids']) || !is_array($input['ids'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid data']);
        return;
    }
    
    $deleted_records = 0;
    $deleted_thumbnails = 0;
    
    // Delete database records
    foreach ($input['ids'] as $id) {
        if ($id === null) continue; // Skip null IDs (orphaned thumbnails have no ID)
        
        $stmt = $pdo->prepare('DELETE FROM cam_images WHERE id = ? AND is_archived = 1');
        $stmt->execute([(int)$id]);
        if ($stmt->rowCount() > 0) {
            $deleted_records++;
        }
    }
    
    // Delete orphaned thumbnail files
    if (isset($input['thumbnails']) && is_array($input['thumbnails'])) {
        foreach ($input['thumbnails'] as $thumb_name) {
            $thumb_path = $thumb_dir . '/' . $thumb_name;
            if (file_exists($thumb_path) && @unlink($thumb_path)) {
                $deleted_thumbnails++;
            }
        }
    }
    
    echo json_encode([
        'success' => true,
        'deleted_records' => $deleted_records,
        'deleted_thumbnails' => $deleted_thumbnails
    ]);
}

?>
