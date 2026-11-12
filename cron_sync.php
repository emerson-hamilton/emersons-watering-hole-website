<?php
/**
 * Scheduled image sync. Run from cron, never from a web request.
 *
 * Cron runs this script every 5 minutes. It only does work when
 * SYNC_INTERVAL_MINUTES has passed since the last successful sync, so the
 * sync period is set in config.php rather than in the crontab.
 *
 * Crontab line (server), every 5 minutes: run the minute field as "every 5":
 *   every 5 min  /usr/local/bin/php /home/<web-user>/public_html/emersoncam/cron_sync.php
 */

// Refuse to run from a browser
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

// Cron starts in an unpredictable directory, so set our own
chdir(__DIR__);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/sync.php';

function cron_log($message) {
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
    file_put_contents(SYNC_LOG_PATH, $line, FILE_APPEND | LOCK_EX);
}

// Prevent two runs from overlapping
$lock = fopen(SYNC_LOCK_PATH, 'c');
if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
    cron_log('Skipped: previous sync still running.');
    exit(0);
}

// Stay quiet until the interval has passed, so the log only records real runs
$last_run = is_file(SYNC_STATE_PATH) ? (int) trim((string) file_get_contents(SYNC_STATE_PATH)) : 0;
if (time() - $last_run < SYNC_INTERVAL_MINUTES * 60) {
    exit(0);
}

$started = time();
$synced = sync_images_from_filesystem($pdo, $uploads_dir, 3);

if ($synced === false) {
    cron_log('ERROR: uploads directory not found: ' . $uploads_dir);
    exit(1);
}

// Record the run only after it succeeds, so a failed run is retried on the next tick
file_put_contents(SYNC_STATE_PATH, (string) $started, LOCK_EX);
cron_log("Synced $synced new image(s) in " . (time() - $started) . 's.');
