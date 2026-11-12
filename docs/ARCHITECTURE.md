# Emerson's Watering Hole Cam: Architecture

This document describes the whole system: the camera on the Raspberry Pi, the transfer of photos, and the gallery website. It covers two repositories:

- **`emersons-watering-hole-raspi`** holds the camera code and the Pi deployment files (`deploy/`).
- **`emersons-watering-hole-website`** (this repo) holds the gallery, the admin pages, and the sync code.

Decisions that affect both sides are recorded here. Each repo's README links back to this document.

## System overview

```
Raspberry Pi                                  Web server (shared hosting)
------------                                  ----------------------------
PIR sensor (GPIO 27)                          uploads/YYYY-MM-DD/*.jpg
  + daytime video motion check                        ^
  -> rtsp_server.py saves JPEG                        | rsync over SSH, every 60 s
     to /mnt/usbdrive/img/YYYY-MM-DD/     ----------> | (rrsync restricts the key
                                                      |  to uploads/)
MediaMTX RTSP stream :8554 /cam  ----> Synology NAS   |
                                                      v
                                       cron_sync.php, every 5 min
                                         -> registers new files in cam_images
                                         -> generates thumbnails
                                                      |
                                                      v
                                       index.php + js/app.js (gallery)
                                       admin.php (admin pages)
                                       api.php (JSON endpoints)
```

## Camera (Raspberry Pi)

**Motion capture.** `rtsp_server.py` runs as `emcam-rtsp.service`. A PIR sensor on GPIO 27 triggers a capture. Captures are saved as `motion_YYYYMMDD_HHMMSS.jpg` in a folder named for the date.

**Hybrid detection.** At night, the PIR trigger alone is trusted. During the day, which is sunrise to sunset calculated with `astral` and padded by a configurable offset, a capture needs a matching motion event from a low-resolution video analysis within 1.5 seconds either side of the trigger. This removes most false triggers from sunlight and heat glints on the sensor.

**Fast capture.** The camera stream is decoded into a frame buffer in RAM (`/dev/shm/latest_frame.jpg`). A capture copies that buffer, which avoids waiting for a new RTSP connection. The script falls back to an on-demand grab if the buffer is stale.

**Overlay.** Each saved snapshot has a black bar with the pawprint icon and the time burned into the bottom.

**Stream.** `rtsp_server.py` starts MediaMTX as a subprocess and serves the camera at `rtsp://<pi>:8554/cam`. The Synology NAS pulls that stream. MediaMTX has no separate service.

**Credentials.** The RTSP password is read from `/home/<pi-user>/.config/emcam/rtsp.env` (outside the repo, read-only to other users) through the `EnvironmentFile=` line in `emcam-rtsp.service`.

**Local storage.** Captures go to a USB drive at `/mnt/usbdrive/img/`. The drive must be mounted before the services start (`RequiresMountsFor=`).

## Photo transfer (Pi to web server)

`emcam-sync.timer` runs `emcam-sync.service` every 60 seconds. That service runs `sync_photos.sh`, which runs:

```
rsync -a --remove-source-files -e "ssh -i <key>" /mnt/usbdrive/img/ user@host:
```

- **Remote path.** The server's `authorized_keys` entry for this key forces `rrsync` into `/home/<web-user>/public_html/emersoncam/uploads`, so the destination needs no path.
- **Local cleanup.** A file is deleted from the Pi only after rsync reports a successful transfer. Empty date folders older than one minute are then removed. The minute protects a folder that a capture has just created.
- **Failure mode.** If the server is unreachable, files stay on the USB drive and transfer on the next run.

## Website

### Files

| Path | Purpose |
|---|---|
| `index.php`, `js/app.js`, `css/styles.css` | Public gallery |
| `admin.php` | Admin pages (login required) |
| `api.php` | JSON endpoints used by both pages |
| `includes/sync.php` | Finds new JPEGs, registers them in the database, makes thumbnails |
| `cron_sync.php` | Scheduled sync entry point (command line only) |
| `config.php` | Database settings and secrets. Not in git. `config.example.php` is the template. |
| `uploads/` | Photos from the Pi, in `YYYY-MM-DD/` folders. Not in git. |
| `uploads_archive/` | Archived photos, in the same date folders. Not in git. |
| `thumbnails/` | Generated thumbnails, named `YYYY-MM-DD_<name>_thumb.jpg`. Not in git. |

### Database

MariaDB 10.6. The full structure is in `docs/schema.sql` (tables and indexes only).

- **`cam_images`**: one row per photo.
  - `filename` is unique, so the same file can't be registered twice.
  - `folder_date` (DATE) is the date folder the file is stored in.
  - `captured_date` (DATETIME) is read from the filename.
  - `upload_date` is when the sync registered the photo.
  - `label`, `is_favorite`, and `is_archived` are set by the admin.
  - `display_order` is written as 0 by the sync and not read anywhere else.
  - `created_at` and `updated_at` are set automatically.
- **`admin_settings`**: key-value pairs with a unique `setting_key`. The admin password hash is stored here under `setting_key = 'password_hash'`.

### Sync

Syncing is the process of registering photos that have arrived on disk. Only `cron_sync.php` and the admin "Sync" button run it. Page loads don't.

`cron_sync.php` is run by cron every 5 minutes. It does the following:

1. Opens a lock file, so two runs can't overlap.
2. Exits quietly unless `SYNC_INTERVAL_MINUTES` (default 60) has passed since the last successful run.
3. Scans the last three date folders and registers any JPEG not yet in `cam_images`, then makes its thumbnail.
4. Records the run time only if the sync succeeds, and writes a line to the log.

Logs and state live outside the web root in `/home/<web-user>/emcam_logs/`.

### Gallery

- The gallery loads with **Last 7 Days** selected. Filters are Today, Last 7 Days, Last 30 Days, and Favorites.
- The page requests all the photos for the active filter in one call to `get_all_images` (`days=1`, `7`, or `30`, or `days=0` for favorites). It groups them by date in the browser.
- Clicking a day on the calendar loads that day with `get_images_by_date`.
- Favorites show everything, since the number of favorites is kept small by hand.

### Admin

Admin pages require a login. The session expires after one hour of inactivity. Admin actions include label editing, favorites, archiving, restoring, permanent deletion, and checking for orphaned records.

### Cache busting

`index.php` and `admin.php` load `js/app.js?v=<modification time>`, so browsers fetch the new file after each update.

## Environment

| Component | Version |
|---|---|
| Web server PHP (cPanel, used by the website) | 8.4.27 |
| Command-line PHP (used by cron) | 8.2.35 |
| MariaDB | 10.6.28 |
| phpMyAdmin | 5.2.3 |

Both PHP versions include the `gd` and `pdo_mysql` modules the sync needs.

## Operations

| Task | Where |
|---|---|
| Check that sync is running | `/home/<web-user>/emcam_logs/cron_sync.log`, one line per real run |
| Check PHP errors | `/home/<web-user>/public_html/emersoncam/error_log` |
| Check Pi services | `systemctl status emcam-rtsp.service emcam-sync.timer` |
| Check photo transfers | `journalctl -u emcam-sync.service -n 20` on the Pi |
| Change the sync period | `SYNC_INTERVAL_MINUTES` in `config.php`, no crontab change needed |
| Deploy website code | Patch from the repo, `git am` on the server, `git push` from the server |
| Deploy Pi code | Patch from the repo, `git am` on the Pi, `git push` from the Pi |

## Decisions

Each entry says what was decided, why, and what it costs.

### 1. Scheduled sync via cron

**Decision.** `cron_sync.php` runs every 5 minutes and syncs when `SYNC_INTERVAL_MINUTES` has passed. Page loads only read the database.

**Why.** The sync used to run on every gallery request. It scanned three days of folders with one query per file, which slowed page loads as the archive grew. Two page loads running at once could also insert the same photo twice, which crashed the request.

**Cost.** New photos appear up to one interval after capture. A failed cron run is invisible to visitors, so the log needs checking.

**Rejected.** Sync on page load (slow, and it had the race above). Sync triggered by the Pi over HTTP (adds an upload endpoint that is reachable from the internet).

### 2. Photos transferred by rsync over SSH, one-minute timer

**Decision.** The Pi pushes photos every 60 seconds with `rsync --remove-source-files`. The server key is restricted to the uploads folder by `rrsync`.

**Why.** rsync sends only new files and removes each one from the Pi after it arrives, so the USB drive doesn't fill up. SSH needs no upload endpoint on the server, and the key can't write anywhere else.

**Cost.** If the server is offline for a long time, photos build up on the USB drive. The Pi has no notification for this.

### 3. Date-based folders (`YYYY-MM-DD`)

**Decision.** Photos are stored in one folder per date, in `uploads/`, `uploads_archive/`, and `thumbnails/` (as a filename prefix).

**Why.** The folder name matches how people browse ("which day was that?"), and the database `folder_date` column matches it. Folders stay small enough to browse by hand.

**Cost.** Every file operation has to build the date path.

### 4. Thumbnails made when a photo is registered

**Decision.** `includes/sync.php` makes each thumbnail at the moment the photo is registered, not when it's first viewed.

**Why.** The gallery reads thumbnails without any extra work, and the cost falls on the scheduled job, not on visitors.

**Cost.** The sync takes longer when many photos arrive at once.

**Note.** An earlier draft of this document described on-demand thumbnails. The code does not do that.

### 5. Archive instead of deleting

**Decision.** "Archive" moves a photo to `uploads_archive/` and sets `is_archived = 1`. Only the "delete permanently" action removes the file and its thumbnail.

**Why.** A mistaken delete can be undone with "restore", and the database row keeps its history.

**Cost.** Disk use grows until someone deletes permanently. The orphan check on the admin page finds mismatches between the database and the files.

### 6. Session timeout of one hour

**Decision.** Admin sessions expire after 3600 seconds without activity.

**Why.** A personal admin login shouldn't stay open for days on a shared machine.

**Cost.** The admin has to log in again after an hour of inactivity.

### 7. Hybrid motion detection (PIR at night, PIR plus video in daylight)

**Decision.** At night, the PIR trigger alone saves a photo. In the daytime, a PIR trigger saves a photo only if the video check saw motion within 1.5 seconds either side of it.

**Why.** Sunlight and heat glints on the PIR sensor caused over 400 false triggers a day. The video check is accurate in daylight and useless in darkness, so the two are used at different times.

**Cost.** The Pi runs two video decoders (the stream and the analysis stream). The analysis stream uses software decoding, because many Pi boards can't run two hardware decoders at once.

### 8. No CSRF tokens

**Decision.** Admin requests rely on the session cookie and do not carry a CSRF token.

**Why.** There is one admin user, on a personal site.

**Cost.** A malicious page visited by a logged-in admin could submit an admin action. If the site ever gains more users, this needs a CSRF token.

### 9. Prepared statements and escaped output

**Decision.** Every database query uses a prepared statement. Output that includes user text is escaped with `escapeHtml()` in JavaScript.

**Why.** This prevents SQL injection and cross-site scripting. Checked in the code: the API uses `prepare()` throughout, and the frontend escapes labels and filenames.

**Cost.** Anyone adding a query or an output line has to follow the pattern.

### 10. Admin password stored as a bcrypt hash

**Decision.** The admin password is stored as a bcrypt hash in `admin_settings` and checked with `password_verify()`. The hash uses cost factor 12 (the stored value starts with `$2y$12$`), which was set when the hash was created outside the repo.

**Why.** Cost 12 makes offline guessing of a stolen hash slow. Login still takes well under a second on the server.

**Cost.** Each login does a slow hash check. The code doesn't create hashes, so changing the password means generating a new hash with cost 12 and updating `admin_settings` directly.

### 11. The gallery loads one request, with a 30-day default filter

**Decision.** Gallery filters are Today, Last 7 Days, Last 30 Days, and Favorites. The default is Last 7 Days, and there's no "All" option. Each filter is loaded with a single `get_all_images` call.

**Why.** The old "All" view sent one request per day. On a shared host, that exhausted database connections, and some days failed silently. The archive is now too large to show at once.

**Cost.** Older photos are reached through the calendar, not the filters.

### 12. Favorites are not limited

**Decision.** Favorites show every favorited photo, with no date limit.

**Why.** The number of favorites is kept small by hand.

**Cost.** Favorites will load more slowly if the list grows.

### 13. RTSP password outside the repo

**Decision.** The RTSP password is read from an environment file outside the repo. It is not stored in the code.

**Why.** The repo is public. The old password, `raspberry`, was in the code and in git history. It was rotated, so the value in history no longer grants access.

**Cost.** The Synology's stream settings must match the environment file. Changing the password means updating both places.

## Open items

- A way to report sync health to the admin page would avoid checking the log by hand.
