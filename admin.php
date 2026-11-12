<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Emerson's Watering Hole Cam</title>
    <link rel="icon" type="image/svg+xml" href="favicon.svg">
    <link rel="stylesheet" href="css/styles.css">
</head>
<body>
    <div class="container">
        <header class="header">
            <div class="header-content">
                <h1>🐾 Emerson's Watering Hole Cam</h1>
                <p class="subtitle">Admin Dashboard</p>
            </div>
            <div class="header-controls">
                <button id="themeToggle" class="btn-icon" title="Toggle dark mode">
                    <span class="theme-icon">🌙</span>
                </button>
                <a href="index.php" class="btn btn-secondary" id="backBtn" style="display: none;">← Back to Gallery</a>
                <button class="btn btn-danger" id="logoutBtn" style="display: none;">Logout</button>
            </div>
        </header>

        <main>
            <!-- LOGIN FORM -->
            <div id="loginSection" class="login-container">
                <div class="login-box">
                    <h2>Admin Login</h2>
                    <p class="login-subtitle">Enter password to manage images</p>
                    <form id="loginForm" onsubmit="handleLogin(event)">
                        <div class="form-group">
                            <label for="password">Password:</label>
                            <input 
                                type="password" 
                                id="password" 
                                name="password" 
                                placeholder="Enter admin password"
                                autocomplete="current-password"
                                required
                            >
                        </div>
                        <button type="submit" class="btn btn-primary btn-large">Login</button>
                        <div id="loginError" class="error-message" style="display: none;"></div>
                    </form>
                    <div class="login-help">
                        <p><strong>Forgot password?</strong> See <a href="#" onclick="showPasswordHelp(); return false;">password reset instructions</a></p>
                    </div>
                </div>
            </div>

            <!-- ADMIN DASHBOARD (hidden until logged in) -->
            <div id="dashboardSection" style="display: none;">
                <div class="admin-toolbar">
                    <div class="toolbar-section">
                        <h3>View Options</h3>
                        <div class="tab-buttons">
                            <button class="tab-btn active" data-tab="active">
                                Active Images
                            </button>
                            <button class="tab-btn" data-tab="archived">
                                Archived Images
                            </button>
                        </div>
                        <div class="view-filters">
                            <label>
                                <input type="radio" name="viewType" value="all" checked> All
                            </label>
                            <label>
                                <input type="radio" name="viewType" value="favorites"> ⭐ Favorites Only
                            </label>
                        </div>
                    </div>

                    <div class="toolbar-section">
                        <h3>Sync Options</h3>
                        <button class="btn btn-info btn-small" onclick="syncRecent()">Sync Recent (3 days)</button>
                        <button class="btn btn-info btn-small" onclick="syncAll()">Sync All</button>
                        <span id="syncStatus" class="sync-status"></span>
                    </div>

                    <div class="toolbar-section" id="archivedToolsSection" style="display: none;">
                        <h3>Archived Image Management</h3>
                        <p style="color: var(--text-secondary); font-size: 0.9rem; margin-bottom: 1rem;">
                            Check for and remove broken database records for archived images.
                        </p>
                        <button class="btn btn-info btn-small" onclick="checkOrphanedImages()">Check & Clean Orphaned Records</button>
                        <span id="orphanedStatus" class="sync-status"></span>
                    </div>

                    <div class="toolbar-section">
                        <h3>Select & Perform Actions</h3>
                        <p style="color: var(--text-secondary); font-size: 0.9rem; margin-bottom: 1rem;">
                            💡 Click "Select All" for a date, or click a checkbox then Shift+click another to select a range.
                        </p>
                        <div class="bulk-actions" style="margin-top: 1rem; border-top: 1px solid var(--border-color); padding-top: 1rem;">
                            <span style="color: var(--text-secondary); font-weight: 500;">Perform on selected:</span>
                            <button class="btn btn-warning btn-small" id="bulkLabelBtn" onclick="showBulkLabelDialog()" disabled>Label</button>
                            <button class="btn btn-success btn-small" id="bulkFavoriteBtn" onclick="showBulkFavoriteDialog()" disabled>⭐ Favorite</button>
                            <button class="btn btn-danger" id="bulkArchiveBtn" disabled>Archive</button>
                            <button class="btn btn-info" id="bulkRestoreBtn" style="display: none;" disabled>Restore</button>
                            <button class="btn btn-danger" id="bulkDeleteBtn" style="display: none;" disabled>🗑️ Delete Permanently</button>
                        </div>
                    </div>
                </div>

                <div id="loadingIndicator" class="loading" style="display: none;">
                    <div class="spinner"></div>
                    <p>Loading images...</p>
                </div>

                <div id="adminDatesList" class="admin-dates-list">
                    <!-- Date groups will be populated here -->
                </div>

                <div id="noImagesMessage" class="no-images" style="display: none;">
                    <p>No images available.</p>
                </div>
            </div>
        </main>
    </div>

    <!-- PASSWORD HELP MODAL -->
    <div id="passwordHelpModal" class="modal" style="display: none;">
        <div class="modal-content">
            <span class="modal-close" onclick="hidePasswordHelp()">&times;</span>
            <h2>Reset Admin Password</h2>
            <p>To reset the admin password, use phpMyAdmin in cPanel:</p>
            <ol>
                <li>Log in to cPanel</li>
                <li>Open <strong>phpMyAdmin</strong></li>
                <li>Select your database</li>
                <li>Click the <strong>admin_settings</strong> table</li>
                <li>Find the row with <code>setting_key = 'password_hash'</code></li>
                <li>Click <strong>Edit</strong></li>
                <li>Use an <strong>online PHP password hash generator</strong> (search "bcrypt generator")</li>
                <li>Generate a hash for your new password</li>
                <li>Paste the hash into the <code>setting_value</code> field</li>
                <li>Click <strong>Go</strong> to save</li>
            </ol>
            <button class="btn btn-primary" onclick="hidePasswordHelp()">Close</button>
        </div>
    </div>

    <!-- BULK LABEL MODAL -->
    <div id="bulkLabelModal" class="modal" style="display: none;">
        <div class="modal-content">
            <span class="modal-close" onclick="hideBulkLabelDialog()">&times;</span>
            <h2>Add Label to Selected Images</h2>
            <div class="form-group">
                <label for="bulkLabelInput">Label:</label>
                <input 
                    type="text" 
                    id="bulkLabelInput" 
                    placeholder="e.g., Deer, Raccoon, etc."
                    maxlength="255"
                >
            </div>
            <div class="modal-buttons">
                <button class="btn btn-primary" onclick="submitBulkLabel()">Apply Label</button>
                <button class="btn btn-secondary" onclick="hideBulkLabelDialog()">Cancel</button>
            </div>
        </div>
    </div>

    <!-- BULK FAVORITE MODAL -->
    <div id="bulkFavoriteModal" class="modal" style="display: none;">
        <div class="modal-content">
            <span class="modal-close" onclick="hideBulkFavoriteDialog()">&times;</span>
            <h2>Mark as Favorite</h2>
            <p>Mark the selected images as favorites?</p>
            <div class="modal-buttons">
                <button class="btn btn-success" onclick="submitBulkFavorite(true)">✓ Mark Favorite</button>
                <button class="btn btn-warning" onclick="submitBulkFavorite(false)">✗ Remove from Favorites</button>
                <button class="btn btn-secondary" onclick="hideBulkFavoriteDialog()">Cancel</button>
            </div>
        </div>
    </div>

    <!-- IMAGE EDIT MODAL -->
    <div id="editImageModal" class="modal" style="display: none;">
        <div class="modal-content">
            <span class="modal-close" onclick="hideEditImageDialog()">&times;</span>
            <div class="edit-image-container">
                <div class="edit-image-preview">
                    <img id="editImageImg" src="" alt="Image preview">
                </div>
                <div class="edit-image-form">
                    <h3 id="editImageFilename"></h3>
                    <p id="editImageDate" class="image-date"></p>
                    
                    <div class="form-group">
                        <label for="editLabelInput">Label:</label>
                        <input 
                            type="text" 
                            id="editLabelInput" 
                            placeholder="Add a label for this image"
                            maxlength="255"
                        >
                    </div>

                    <div class="form-group">
                        <label>
                            <input type="checkbox" id="editFavoriteCheckbox">
                            ⭐ Mark as Favorite
                        </label>
                    </div>
                    
                    <div class="modal-buttons">
                        <button class="btn btn-primary" onclick="saveEditImage()">Save Changes</button>
                        <button class="btn btn-danger" id="editArchiveBtn" onclick="archiveFromEdit()">Archive</button>
                        <button class="btn btn-secondary" onclick="hideEditImageDialog()">Close</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="js/app.js"></script>
    <script>
        // Initialize admin
        initAdmin();
    </script>
</body>
</html>
