<?php
/**
 * Image sync helpers: find new JPGs in the uploads folder, add them to the
 * database, and generate thumbnails.
 * Used by api.php (admin sync button) and cron_sync.php (scheduled sync).
 */

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
