<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Emerson's Watering Hole Cam</title>
    <link rel="icon" type="image/svg+xml" href="favicon.svg">
    <link rel="stylesheet" href="css/styles.css">
</head>
<body>
    <div class="container">
        <header class="header">
            <div class="header-content">
                <h1>🐾 Emerson's Watering Hole Cam</h1>
                <p class="subtitle">Live wildlife captures from the watering hole in my backyard!</p>
            </div>
            <div class="header-controls">
                <button id="themeToggle" class="btn-icon" title="Toggle dark mode">
                    <span class="theme-icon">🌙</span>
                </button>
                <a href="admin.php" class="btn btn-secondary">Admin</a>
            </div>
        </header>

        <main class="gallery-container">
            <!-- Calendar and Filters -->
            <div class="gallery-controls">
                <div class="controls-section">
                    <h3>Quick Filters</h3>
                    <div class="filter-buttons">
                        <button class="filter-btn" data-filter="today">Today</button>
                        <button class="filter-btn active" data-filter="week">Last 7 Days</button>
                        <button class="filter-btn" data-filter="month">Last 30 Days</button>
                        <button class="filter-btn" data-filter="favorites">⭐ Favorites</button>
                    </div>
                </div>

                <div class="controls-section">
                    <h3>Calendar</h3>
                    <div class="calendar-navigation">
                        <button id="prevMonth" class="btn-small">◀</button>
                        <span id="calendarMonth" class="calendar-month">August 2024</span>
                        <button id="nextMonth" class="btn-small">▶</button>
                    </div>
                    <div id="calendar" class="calendar-grid">
                        <!-- Calendar will be populated here -->
                    </div>
                </div>
            </div>

            <!-- Gallery Display -->
            <div id="loadingIndicator" class="loading">
                <div class="spinner"></div>
                <p>Loading images...</p>
            </div>

            <div id="selectedDateInfo" class="selected-date-info" style="display: none;">
                <p id="selectedDateText"></p>
                <button id="clearDateBtn" class="btn btn-secondary">Show All</button>
            </div>

            <div id="galleryContainer" class="gallery-by-date-container">
                <!-- Date-grouped gallery will be populated here -->
            </div>

            <div id="noImagesMessage" class="no-images" style="display: none;">
                <p>No images available.</p>
            </div>
        </main>

        <!-- Image Lightbox -->
        <div id="lightbox" class="lightbox" style="display: none;">
            <div class="lightbox-content">
                <button class="lightbox-close" id="lightboxClose">&times;</button>
                <img id="lightboxImage" src="" alt="Full size">
                <div class="lightbox-info">
                    <p id="lightboxDate" class="lightbox-date"></p>
                    <p id="lightboxLabel" class="lightbox-label"></p>
                    <p id="lightboxFilename" class="lightbox-filename" style="display: none;"></p>
                </div>
                <div class="lightbox-nav">
                    <button id="lightboxPrev" class="btn btn-pagination">← Previous</button>
                    <button id="lightboxNext" class="btn btn-pagination">Next →</button>
                </div>
            </div>
        </div>
    </div>

    <script src="js/app.js?v=<?= filemtime(__DIR__ . '/js/app.js') ?>"></script>
    <script>
        // Initialize gallery
        initGallery();
    </script>
</body>
</html>
