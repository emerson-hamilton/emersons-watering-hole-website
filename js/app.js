/**
 * Emerson's Watering Hole Cam - Main Application JavaScript (Updated)
 * Now with calendar, date grouping, favorites, and enhanced features
 */

// ===== STATE MANAGEMENT =====

let currentState = {
    currentYear: new Date().getFullYear(),
    currentMonth: new Date().getMonth() + 1,
    selectedDate: null,
    allDates: [],
    allImages: [],
    currentFilter: 'all', // 'all', 'today', 'week', 'month', 'favorites'
    authenticated: false,
    showArchived: false,
    showArchiveFolder: false,
    selectedImages: new Set(),
    currentLightboxIndex: 0,
    adminViewType: 'all', // 'all' or 'favorites'
};

// ===== INITIALIZATION =====

function initGallery() {
    setupThemeToggle();
    setupGalleryEventListeners();
    loadCalendarData();
    loadGalleryDates();
}

function initAdmin() {
    setupThemeToggle();
    checkAdminAuth();
    setupAdminEventListeners();
}

// ===== THEME MANAGEMENT =====

function setupThemeToggle() {
    const themeToggle = document.getElementById('themeToggle');
    const savedTheme = localStorage.getItem('theme') || 'light';
    
    if (savedTheme === 'dark') {
        document.body.classList.add('dark-theme');
        updateThemeIcon();
    }
    
    if (themeToggle) {
        themeToggle.addEventListener('click', toggleTheme);
    }
}

function toggleTheme() {
    const isDark = document.body.classList.toggle('dark-theme');
    localStorage.setItem('theme', isDark ? 'dark' : 'light');
    updateThemeIcon();
}

function updateThemeIcon() {
    const icon = document.querySelector('.theme-icon');
    if (icon) {
        icon.textContent = document.body.classList.contains('dark-theme') ? '☀️' : '🌙';
    }
}

// ===== CALENDAR FUNCTIONS =====

function setupGalleryEventListeners() {
    // Calendar navigation
    document.getElementById('prevMonth')?.addEventListener('click', () => {
        currentState.currentMonth--;
        if (currentState.currentMonth < 1) {
            currentState.currentMonth = 12;
            currentState.currentYear--;
        }
        loadCalendarData();
    });

    document.getElementById('nextMonth')?.addEventListener('click', () => {
        currentState.currentMonth++;
        if (currentState.currentMonth > 12) {
            currentState.currentMonth = 1;
            currentState.currentYear++;
        }
        loadCalendarData();
    });

    // Filter buttons
    document.querySelectorAll('.filter-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            // Close lightbox when changing filters
            closeLightbox();
            
            document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            currentState.currentFilter = btn.dataset.filter;
            currentState.selectedDate = null;
            loadGalleryDates();
            updateSelectedDateInfo();
        });
    });

    // Clear date button
    document.getElementById('clearDateBtn')?.addEventListener('click', () => {
        closeLightbox();
        currentState.selectedDate = null;
        document.querySelectorAll('.calendar-day.selected').forEach(d => d.classList.remove('selected'));
        loadGalleryDates();
        updateSelectedDateInfo();
    });

    // Lightbox
    document.getElementById('lightboxClose')?.addEventListener('click', closeLightbox);
    document.getElementById('lightboxPrev')?.addEventListener('click', previousImage);
    document.getElementById('lightboxNext')?.addEventListener('click', nextImage);
    document.getElementById('lightbox')?.addEventListener('click', (e) => {
        if (e.target.id === 'lightbox') closeLightbox();
    });

    // Keyboard navigation
    document.addEventListener('keydown', (e) => {
        if (document.getElementById('lightbox')?.style.display !== 'flex') return;
        if (e.key === 'Escape') closeLightbox();
        if (e.key === 'ArrowLeft') previousImage();
        if (e.key === 'ArrowRight') nextImage();
    });
}

function loadCalendarData() {
    const loading = document.getElementById('loadingIndicator');
    if (loading) loading.style.display = 'block';

    fetch(`api.php?action=get_calendar_data&year=${currentState.currentYear}&month=${currentState.currentMonth}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                renderCalendar(data.year, data.month, data.calendar);
            }
        })
        .catch(error => console.error('Error loading calendar:', error))
        .finally(() => {
            if (loading) loading.style.display = 'none';
        });
}

function renderCalendar(year, month, calendarData) {
    const calendar = document.getElementById('calendar');
    if (!calendar) return;

    // Update month label
    const monthLabel = document.getElementById('calendarMonth');
    if (monthLabel) {
        const monthName = new Date(year, month - 1).toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
        monthLabel.textContent = monthName;
    }

    calendar.innerHTML = '';

    // Add day headers
    const dayHeaders = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
    dayHeaders.forEach(day => {
        const header = document.createElement('div');
        header.className = 'calendar-day-header';
        header.textContent = day;
        calendar.appendChild(header);
    });

    // Get first day of month and number of days
    const firstDay = new Date(year, month - 1, 1).getDay();
    const daysInMonth = new Date(year, month, 0).getDate();
    const daysInPrevMonth = new Date(year, month - 1, 0).getDate();

    // Add previous month's days
    for (let i = firstDay - 1; i >= 0; i--) {
        const day = daysInPrevMonth - i;
        const dayEl = createCalendarDay(day, month - 1, year, null, true);
        calendar.appendChild(dayEl);
    }

    // Add current month's days
    for (let day = 1; day <= daysInMonth; day++) {
        const dateStr = `${year}-${String(month).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
        const data = calendarData[day] || null;
        const dayEl = createCalendarDay(day, month, year, data, false);
        calendar.appendChild(dayEl);
    }

    // Add next month's days
    const totalCells = calendar.children.length - 7; // Subtract day headers
    const remainingCells = 42 - totalCells; // 6 rows * 7 days
    for (let day = 1; day <= remainingCells; day++) {
        const dayEl = createCalendarDay(day, month + 1, year, null, true);
        calendar.appendChild(dayEl);
    }
}

function createCalendarDay(day, month, year, data, isOtherMonth) {
    const dayEl = document.createElement('div');
    dayEl.className = 'calendar-day';

    const dateStr = `${year}-${String(month).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
    dayEl.dataset.date = dateStr;

    if (isOtherMonth) {
        dayEl.classList.add('other-month');
    } else if (data) {
        dayEl.classList.add('has-images');
        dayEl.innerHTML = `<span class="calendar-day-number">${day}</span><span class="calendar-day-count">${data.count}</span>`;
        dayEl.onclick = () => selectDate(dateStr);
    } else {
        dayEl.textContent = day;
    }

    if (currentState.selectedDate === dateStr) {
        dayEl.classList.add('selected');
    }

    return dayEl;
}

function selectDate(date) {
    currentState.selectedDate = date;
    currentState.currentFilter = 'all'; // Reset filter when selecting date
    document.querySelectorAll('.filter-btn').forEach(b => {
        if (b.dataset.filter === 'all') b.classList.add('active');
        else b.classList.remove('active');
    });
    document.querySelectorAll('.calendar-day.selected').forEach(d => d.classList.remove('selected'));
    document.querySelector(`[data-date="${date}"]`)?.classList.add('selected');
    loadGalleryDates();
    updateSelectedDateInfo();
}

function updateSelectedDateInfo() {
    const info = document.getElementById('selectedDateInfo');
    if (!info) return;

    if (currentState.selectedDate) {
        const date = new Date(currentState.selectedDate + 'T00:00:00');
        const dateStr = date.toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
        document.getElementById('selectedDateText').textContent = `Showing photos from ${dateStr}`;
        info.style.display = 'flex';
    } else {
        info.style.display = 'none';
    }
}

// ===== GALLERY FUNCTIONS =====

function loadGalleryDates() {
    const loading = document.getElementById('loadingIndicator');
    if (loading) loading.style.display = 'block';

    if (currentState.selectedDate) {
        loadImagesByDate(currentState.selectedDate);
    } else {
        loadAllDateGroups();
    }
}

function loadAllDateGroups() {
    fetch(`api.php?action=get_images&view=${currentState.currentFilter === 'favorites' ? 'favorites' : 'all'}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Filter dates based on selected filter
                currentState.allDates = filterDatesByRange(data.dates, currentState.currentFilter);
                renderDateGroups();
            }
        })
        .catch(error => console.error('Error loading dates:', error))
        .finally(() => {
            document.getElementById('loadingIndicator').style.display = 'none';
        });
}

/**
 * Filter dates by range (Today, Last 7 Days, Last 30 Days)
 */
function filterDatesByRange(dates, filter) {
    if (filter === 'all' || filter === 'favorites') {
        return dates; // No filtering
    }

    const today = new Date();
    today.setHours(0, 0, 0, 0);

    let startDate = new Date(today);

    switch(filter) {
        case 'today':
            // Only today
            startDate = new Date(today);
            break;
        case 'week':
            // Last 7 days
            startDate.setDate(today.getDate() - 6);
            break;
        case 'month':
            // Last 30 days
            startDate.setDate(today.getDate() - 29);
            break;
        default:
            return dates;
    }

    return dates.filter(dateInfo => {
        const dateObj = new Date(dateInfo.folder_date + 'T00:00:00');
        dateObj.setHours(0, 0, 0, 0);
        return dateObj >= startDate && dateObj <= today;
    });
}

function loadImagesByDate(date) {
    fetch(`api.php?action=get_images_by_date&date=${date}&view=${currentState.currentFilter === 'favorites' ? 'favorites' : 'all'}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                renderSingleDateGallery(data);
            }
        })
        .catch(error => console.error('Error loading images:', error))
        .finally(() => {
            document.getElementById('loadingIndicator').style.display = 'none';
        });
}

function renderDateGroups() {
    const container = document.getElementById('galleryContainer');
    if (!container) return;

    if (currentState.allDates.length === 0) {
        document.getElementById('noImagesMessage').style.display = 'block';
        container.innerHTML = '';
        return;
    }

    document.getElementById('noImagesMessage').style.display = 'none';
    container.innerHTML = '';
    
    // Flatten all images into one array for lightbox indexing
    currentState.allImages = [];

    // Fetch all images for all dates
    const fetchPromises = currentState.allDates.map(dateInfo => {
        return fetch(`api.php?action=get_images_by_date&date=${dateInfo.folder_date}&view=${currentState.currentFilter === 'favorites' ? 'favorites' : 'all'}`)
            .then(response => response.json())
            .then(data => ({
                dateInfo: dateInfo,
                images: data.success ? data.images : []
            }));
    });

    // Wait for ALL fetches to complete, then render in correct order
    Promise.all(fetchPromises)
        .then(results => {
            // Build the global image array in the correct order
            results.forEach(result => {
                if (result.images.length > 0) {
                    currentState.allImages.push(...result.images);
                }
            });

            // Now render all date groups with correct global indices
            let globalIndex = 0;
            results.forEach(result => {
                if (result.images.length === 0) return;

                const dateInfo = result.dateInfo;
                const group = document.createElement('div');
                group.className = 'date-group';

                const date = new Date(dateInfo.folder_date + 'T00:00:00');
                const dateStr = date.toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });

                const header = document.createElement('div');
                header.className = 'date-group-header';
                header.innerHTML = `
                    <h2>${dateStr}</h2>
                    <span class="date-group-count">${result.images.length} photo${result.images.length !== 1 ? 's' : ''}</span>
                `;
                group.appendChild(header);

                const gallery = document.createElement('div');
                gallery.className = 'date-group-gallery';
                gallery.innerHTML = result.images.map((img, localIndex) => {
                    const currentGlobalIndex = globalIndex + localIndex;
                    return `
                    <div class="gallery-item" onclick="openLightbox(${currentGlobalIndex})">
                        <img 
                            src="${escapeHtml(img.thumbnail)}" 
                            alt="Image" 
                            class="gallery-item-image"
                            loading="lazy"
                        >
                        <div class="gallery-item-info">
                            <div class="gallery-item-header">
                                <div class="gallery-item-date">${formatDate(img.captured_date)}</div>
                                <button class="gallery-item-favorite" onclick="event.stopPropagation(); toggleFavorite(${img.id}, event)" title="Add to favorites">
                                    ${img.is_favorite ? '⭐' : '☆'}
                                </button>
                            </div>
                            <div class="gallery-item-label ${img.label ? '' : 'empty'}">
                                ${img.label ? escapeHtml(img.label) : 'No label'}
                            </div>
                        </div>
                    </div>
                `;
                }).join('');

                group.appendChild(gallery);
                container.appendChild(group);

                globalIndex += result.images.length;
            });
        })
        .catch(error => console.error('Error loading images:', error));
}

function renderSingleDateGallery(data) {
    const container = document.getElementById('galleryContainer');
    if (!container) return;

    if (data.images.length === 0) {
        document.getElementById('noImagesMessage').style.display = 'block';
        container.innerHTML = '';
        return;
    }

    document.getElementById('noImagesMessage').style.display = 'none';
    container.innerHTML = '';

    // For single date view, just load all images into allImages array
    currentState.allImages = data.images;

    const group = document.createElement('div');
    group.className = 'date-group';

    const date = new Date(data.date + 'T00:00:00');
    const dateStr = date.toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });

    const header = document.createElement('div');
    header.className = 'date-group-header';
    header.innerHTML = `
        <h2>${dateStr}</h2>
        <span class="date-group-count">${data.images.length} photo${data.images.length !== 1 ? 's' : ''}</span>
    `;
    group.appendChild(header);

    const gallery = document.createElement('div');
    gallery.className = 'date-group-gallery';
    gallery.innerHTML = data.images.map((img, index) => `
        <div class="gallery-item" onclick="openLightbox(${index})">
            <img 
                src="${escapeHtml(img.thumbnail)}" 
                alt="Image" 
                class="gallery-item-image"
                loading="lazy"
            >
            <div class="gallery-item-info">
                <div class="gallery-item-header">
                    <div class="gallery-item-date">${formatDate(img.captured_date)}</div>
                    <button class="gallery-item-favorite" onclick="event.stopPropagation(); toggleFavorite(${img.id}, event)" title="Add to favorites">
                        ${img.is_favorite ? '⭐' : '☆'}
                    </button>
                </div>
                <div class="gallery-item-label ${img.label ? '' : 'empty'}">
                    ${img.label ? escapeHtml(img.label) : 'No label'}
                </div>
            </div>
        </div>
    `).join('');
    
    group.appendChild(gallery);
    container.appendChild(group);
}

// ===== LIGHTBOX FUNCTIONS =====

function openLightbox(index) {
    // Validate index is within bounds of current filtered image set
    if (index < 0 || index >= currentState.allImages.length) {
        return;
    }

    currentState.currentLightboxIndex = index;
    const img = currentState.allImages[index];
    
    const lightbox = document.getElementById('lightbox');
    const lightboxImage = document.getElementById('lightboxImage');
    const lightboxDate = document.getElementById('lightboxDate');
    const lightboxLabel = document.getElementById('lightboxLabel');
    const lightboxFilename = document.getElementById('lightboxFilename');
    const lightboxPrev = document.getElementById('lightboxPrev');
    const lightboxNext = document.getElementById('lightboxNext');

    if (!lightbox) return;

    lightboxImage.src = `uploads/${img.folder_date}/${encodeURIComponent(img.filename)}`;
    
    // Show position in filtered set (e.g., "3 of 47")
    const position = `${index + 1} of ${currentState.allImages.length}`;
    lightboxDate.textContent = formatDate(img.captured_date) + ` [${position}]`;
    lightboxLabel.textContent = img.label || 'No label';
    
    // Show filename in admin mode
    if (lightboxFilename) {
        if (currentState.authenticated) {
            lightboxFilename.textContent = `📄 ${escapeHtml(img.filename)}`;
            lightboxFilename.style.display = 'block';
        } else {
            lightboxFilename.style.display = 'none';
        }
    }

    // Disable prev/next buttons at boundaries
    lightboxPrev.disabled = index === 0;
    lightboxNext.disabled = index === currentState.allImages.length - 1;

    lightbox.style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function closeLightbox() {
    const lightbox = document.getElementById('lightbox');
    if (lightbox) {
        lightbox.style.display = 'none';
        document.body.style.overflow = 'auto';
    }
}

function previousImage() {
    if (currentState.currentLightboxIndex > 0) {
        openLightbox(currentState.currentLightboxIndex - 1);
    }
}

function nextImage() {
    if (currentState.currentLightboxIndex < currentState.allImages.length - 1) {
        openLightbox(currentState.currentLightboxIndex + 1);
    }
}

function toggleFavorite(imageId, event) {
    if (!event) return;
    
    const btn = event.target.closest('.gallery-item-favorite');
    const isFav = btn.textContent === '⭐';
    
    fetch('api.php?action=toggle_favorite', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: `id=${imageId}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            btn.textContent = data.is_favorite ? '⭐' : '☆';
            // Update in our state
            const img = currentState.allImages.find(i => i.id === imageId);
            if (img) img.is_favorite = data.is_favorite;
        }
    });
}

// ===== ADMIN FUNCTIONS =====

function setupAdminEventListeners() {
    // Tab switching
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.addEventListener('click', (e) => {
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            e.target.classList.add('active');
            currentState.showArchived = e.target.dataset.tab === 'archived';
            
            // Show/hide archived tools section
            const archivedToolsSection = document.getElementById('archivedToolsSection');
            if (archivedToolsSection) {
                archivedToolsSection.style.display = currentState.showArchived ? 'block' : 'none';
            }
            
            // Update bulk action button labels
            updateBulkActionButtonLabels();
            
            loadAdminDates();
        });
    });

    // View type switching (all vs favorites)
    document.querySelectorAll('input[name="viewType"]').forEach(radio => {
        radio.addEventListener('change', () => {
            currentState.adminViewType = radio.value;
            loadAdminDates();
        });
    });

    // Bulk action buttons
    document.getElementById('bulkArchiveBtn')?.addEventListener('click', () => {
        bulkArchive();
    });

    document.getElementById('bulkRestoreBtn')?.addEventListener('click', bulkRestore);
    
    document.getElementById('bulkDeleteBtn')?.addEventListener('click', () => {
        const ids = Array.from(currentState.selectedImages);
        if (ids.length === 0) {
            alert('Please select images first');
            return;
        }
        
        if (!confirm(`Permanently delete ${ids.length} archived image(s)? This cannot be undone.`)) return;
        
        bulkDeleteArchivedPermanently(ids);
    });
    document.getElementById('bulkLabelBtn')?.addEventListener('click', showBulkLabelDialog);
    document.getElementById('bulkFavoriteBtn')?.addEventListener('click', showBulkFavoriteDialog);

    // Logout
    document.getElementById('logoutBtn')?.addEventListener('click', logout);

    // Monitor bulk action button visibility
    updateBulkActionsVisibility();
}

function checkAdminAuth() {
    fetch('api.php?action=check_auth')
        .then(response => response.json())
        .then(data => {
            if (data.authenticated) {
                currentState.authenticated = true;
                showAdminDashboard();
                loadAdminDates();
            } else {
                showAdminLogin();
            }
        });
}

function handleLogin(event) {
    event.preventDefault();
    const password = document.getElementById('password').value;
    const errorDiv = document.getElementById('loginError');

    fetch('api.php?action=admin_login', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: `password=${encodeURIComponent(password)}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            currentState.authenticated = true;
            document.getElementById('loginForm').reset();
            showAdminDashboard();
            loadAdminDates();
        } else {
            errorDiv.textContent = data.error || 'Login failed';
            errorDiv.style.display = 'block';
        }
    })
    .catch(error => {
        console.error('Login error:', error);
        errorDiv.textContent = 'An error occurred. Please try again.';
        errorDiv.style.display = 'block';
    });
}

function logout() {
    fetch('api.php?action=admin_logout', { method: 'POST' })
        .then(() => {
            currentState.authenticated = false;
            showAdminLogin();
            currentState.selectedImages.clear();
        });
}

function showAdminLogin() {
    document.getElementById('loginSection').style.display = 'block';
    document.getElementById('dashboardSection').style.display = 'none';
    document.getElementById('backBtn').style.display = 'none';
    document.getElementById('logoutBtn').style.display = 'none';
}

function showAdminDashboard() {
    document.getElementById('loginSection').style.display = 'none';
    document.getElementById('dashboardSection').style.display = 'block';
    document.getElementById('backBtn').style.display = 'inline-block';
    document.getElementById('logoutBtn').style.display = 'block';
}

function loadAdminDates() {
    const loading = document.getElementById('loadingIndicator');
    if (loading) loading.style.display = 'block';

    fetch(`api.php?action=get_admin_images_by_date&archived=${currentState.showArchived ? 1 : 0}&view=${currentState.adminViewType}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                renderAdminDateGroups(data.dates);
            }
        })
        .catch(error => console.error('Error loading admin dates:', error))
        .finally(() => {
            if (loading) loading.style.display = 'none';
        });
}

function renderAdminDateGroups(dates) {
    const container = document.getElementById('adminDatesList');
    if (!container) return;

    if (dates.length === 0) {
        document.getElementById('noImagesMessage').style.display = 'block';
        container.innerHTML = '';
        return;
    }

    document.getElementById('noImagesMessage').style.display = 'none';
    container.innerHTML = '';
    currentState.selectedImages.clear();

    dates.forEach(dateInfo => {
        const group = document.createElement('div');
        group.className = 'admin-date-group';

        const date = new Date(dateInfo.folder_date + 'T00:00:00');
        const dateStr = date.toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });

        const header = document.createElement('div');
        header.className = 'admin-date-header';
        header.innerHTML = `
            <div>
                <h3>${dateStr}</h3>
                <span class="date-group-count">${dateInfo.count} photo${dateInfo.count !== 1 ? 's' : ''}</span>
            </div>
            <button class="btn btn-secondary btn-small" onclick="selectAllForDate('${dateInfo.folder_date}')">
                Select All
            </button>
        `;
        group.appendChild(header);

        const gallery = document.createElement('div');
        gallery.className = 'admin-gallery-grid';
        gallery.dataset.date = dateInfo.folder_date;
        group.appendChild(gallery);

        // Fetch images for this date
        fetch(`api.php?action=get_admin_images&date=${dateInfo.folder_date}&archived=${currentState.showArchived ? 1 : 0}&view=${currentState.adminViewType}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    renderAdminImages(data.images, gallery);
                }
            });

        container.appendChild(group);
    });
}

function selectAllForDate(folderDate) {
    // Get all checkboxes for this date group
    const dateGroup = document.querySelector(`[data-date="${folderDate}"]`);
    if (!dateGroup) return;

    const checkboxes = dateGroup.querySelectorAll('.admin-gallery-item-checkbox');
    
    // Toggle select all (if any are unchecked, check all; if all are checked, uncheck all)
    const someUnchecked = Array.from(checkboxes).some(cb => !cb.checked);
    
    checkboxes.forEach(checkbox => {
        checkbox.checked = someUnchecked;
        updateItemSelection(checkbox);
    });
}

let lastSelectedCheckbox = null;

function renderAdminImages(images, container) {
    container.innerHTML = images.map((img) => `
        <div class="admin-gallery-item" id="item-${img.id}">
            <input 
                type="checkbox" 
                class="admin-gallery-item-checkbox"
                data-id="${img.id}"
                data-filename="${escapeHtml(img.filename)}"
                data-date="${img.captured_date}"
                data-label="${escapeHtml(img.label || '')}"
                data-favorite="${img.is_favorite ? '1' : '0'}"
                onclick="handleCheckboxClick(event)"
            >
            <button 
                class="admin-gallery-item-favorite-btn ${img.is_favorite ? 'is-favorite' : ''}"
                onclick="adminToggleFavorite(${img.id}, event)"
                title="${img.is_favorite ? 'Remove from favorites' : 'Add to favorites'}"
            >
                ${img.is_favorite ? '⭐' : '☆'}
            </button>
            <img 
                src="${escapeHtml(img.thumbnail)}" 
                alt="Thumbnail"
                class="admin-gallery-item-image"
                onclick="openEditImageDialog(${img.id})"
                style="cursor: pointer;"
            >
            <div class="admin-gallery-item-info" onclick="openEditImageDialog(${img.id})" style="cursor: pointer;">
                <div class="admin-gallery-item-label" title="${escapeHtml(img.label || '')}">
                    ${img.label ? escapeHtml(img.label) : '<em>No label</em>'}
                </div>
            </div>
        </div>
    `).join('');
}

function handleCheckboxClick(event) {
    const checkbox = event.target;
    if (checkbox.type !== 'checkbox') return;
    
    const id = parseInt(checkbox.dataset.id);

    // Handle shift+click range selection
    if (event.shiftKey && lastSelectedCheckbox !== null) {
        const lastId = parseInt(lastSelectedCheckbox.dataset.id);
        selectRange(lastId, id);
    } else {
        // Regular single selection
        updateItemSelection(checkbox);
    }

    lastSelectedCheckbox = checkbox;
}

function selectRange(startId, endId) {
    // Get all checkboxes in order
    const checkboxes = Array.from(document.querySelectorAll('.admin-gallery-item-checkbox'));
    
    // Find indices of start and end
    const startIndex = checkboxes.findIndex(cb => parseInt(cb.dataset.id) === startId);
    const endIndex = checkboxes.findIndex(cb => parseInt(cb.dataset.id) === endId);
    
    if (startIndex === -1 || endIndex === -1) return;
    
    // Select all checkboxes between start and end (inclusive)
    const min = Math.min(startIndex, endIndex);
    const max = Math.max(startIndex, endIndex);
    
    for (let i = min; i <= max; i++) {
        checkboxes[i].checked = true;
        updateItemSelection(checkboxes[i]);
    }
}

function updateItemSelection(checkbox) {
    const id = parseInt(checkbox.dataset.id);
    const item = document.getElementById(`item-${id}`);

    if (checkbox.checked) {
        currentState.selectedImages.add(id);
        item.classList.add('selected');
    } else {
        currentState.selectedImages.delete(id);
        item.classList.remove('selected');
    }

    updateBulkActionsVisibility();
}

function updateBulkActionsVisibility() {
    const hasSelection = currentState.selectedImages.size > 0;
    const bulkLabelBtn = document.getElementById('bulkLabelBtn');
    const bulkFavoriteBtn = document.getElementById('bulkFavoriteBtn');
    const bulkArchiveBtn = document.getElementById('bulkArchiveBtn');
    const bulkRestoreBtn = document.getElementById('bulkRestoreBtn');
    const bulkDeleteBtn = document.getElementById('bulkDeleteBtn');

    if (bulkLabelBtn) bulkLabelBtn.disabled = !hasSelection;
    if (bulkFavoriteBtn) bulkFavoriteBtn.disabled = !hasSelection;
    if (bulkArchiveBtn) bulkArchiveBtn.disabled = !hasSelection;
    if (bulkRestoreBtn) bulkRestoreBtn.disabled = !hasSelection;
    if (bulkDeleteBtn) bulkDeleteBtn.disabled = !hasSelection;

    // Show/hide buttons based on active or archived view
    if (bulkArchiveBtn) bulkArchiveBtn.style.display = currentState.showArchived ? 'none' : 'inline-block';
    if (bulkRestoreBtn) bulkRestoreBtn.style.display = currentState.showArchived ? 'inline-block' : 'none';
    if (bulkDeleteBtn) bulkDeleteBtn.style.display = currentState.showArchived ? 'inline-block' : 'none';
}

function updateBulkActionButtonLabels() {
    updateBulkActionsVisibility();
}

// ===== DIALOG FUNCTIONS =====

function openEditImageDialog(imageId) {
    // Try to find the image in currentState.allImages first
    let img = currentState.allImages.find(i => i.id === parseInt(imageId));
    
    // Fall back to finding the DOM element if not in allImages
    if (!img) {
        const domElement = document.getElementById(`item-${imageId}`);
        if (!domElement) {
            console.error('Image element not found:', imageId);
            return;
        }
        
        // Extract data from DOM elements as fallback
        const checkbox = domElement.querySelector('.admin-gallery-item-checkbox');
        const thumbnailImg = domElement.querySelector('.admin-gallery-item-image');
        
        img = {
            id: parseInt(imageId),
            filename: checkbox?.dataset?.filename || 'Unknown',
            thumbnail: thumbnailImg?.src || '',
            captured_date: checkbox?.dataset?.date || new Date().toISOString(),
            label: checkbox?.dataset?.label || '',
            is_favorite: parseInt(checkbox?.dataset?.favorite || 0)
        };
    }

    // Populate dialog with image data
    document.getElementById('editImageFilename').textContent = img.filename;
    document.getElementById('editImageDate').textContent = formatDate(img.captured_date);
    document.getElementById('editImageImg').src = img.thumbnail;
    document.getElementById('editLabelInput').value = img.label || '';
    document.getElementById('editFavoriteCheckbox').checked = img.is_favorite === 1;
    document.getElementById('editImageModal').dataset.imageId = imageId;

    const archiveBtn = document.getElementById('editArchiveBtn');
    if (archiveBtn) {
        archiveBtn.textContent = currentState.showArchived ? 'Delete Permanently' : 'Archive';
        archiveBtn.onclick = () => {
            if (currentState.showArchived) {
                deleteImagePermanently(imageId);
            } else {
                archiveFromEdit(imageId);
            }
        };
    }

    document.getElementById('editImageModal').style.display = 'flex';
}

function hideEditImageDialog() {
    document.getElementById('editImageModal').style.display = 'none';
}

function saveEditImage() {
    const imageId = document.getElementById('editImageModal').dataset.imageId;
    const label = document.getElementById('editLabelInput').value;
    const isFavorite = document.getElementById('editFavoriteCheckbox').checked;

    // Update label
    fetch('api.php?action=update_label', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: `id=${imageId}&label=${encodeURIComponent(label)}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Update favorite
            return fetch('api.php?action=toggle_favorite', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: `id=${imageId}`
            });
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            hideEditImageDialog();
            loadAdminDates();
        }
    });
}

function archiveFromEdit(imageId) {
    fetch('api.php?action=archive_image', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: `id=${imageId}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            hideEditImageDialog();
            loadAdminDates();
        }
    });
}

function showPasswordHelp() {
    document.getElementById('passwordHelpModal').style.display = 'flex';
}

function hidePasswordHelp() {
    document.getElementById('passwordHelpModal').style.display = 'none';
}

function showBulkLabelDialog() {
    if (currentState.selectedImages.size === 0) {
        alert('Please select images first');
        return;
    }
    document.getElementById('bulkLabelInput').value = '';
    document.getElementById('bulkLabelModal').style.display = 'flex';
    document.getElementById('bulkLabelInput').focus();
}

function hideBulkLabelDialog() {
    document.getElementById('bulkLabelModal').style.display = 'none';
}

function submitBulkLabel() {
    const label = document.getElementById('bulkLabelInput').value;
    const ids = Array.from(currentState.selectedImages);

    if (ids.length === 0) return;

    fetch('api.php?action=bulk_label', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ ids, label })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            hideBulkLabelDialog();
            loadAdminDates();
        }
    });
}

function showBulkFavoriteDialog() {
    if (currentState.selectedImages.size === 0) {
        alert('Please select images first');
        return;
    }
    document.getElementById('bulkFavoriteModal').style.display = 'flex';
}

function hideBulkFavoriteDialog() {
    document.getElementById('bulkFavoriteModal').style.display = 'none';
}

function submitBulkFavorite(isFavorite) {
    const ids = Array.from(currentState.selectedImages);

    if (ids.length === 0) return;

    fetch('api.php?action=bulk_favorite', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ ids, favorite: isFavorite })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            hideBulkFavoriteDialog();
            loadAdminDates();
        }
    });
}

function bulkArchive() {
    const ids = Array.from(currentState.selectedImages);
    if (ids.length === 0) {
        alert('Please select images first');
        return;
    }

    if (!confirm(`Archive ${ids.length} image(s)? They can be restored later.`)) return;

    fetch('api.php?action=bulk_archive', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ ids })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            loadAdminDates();
        }
    });
}

function bulkDeleteArchivedPermanently(ids) {
    let deleted = 0;
    const status = document.getElementById('orphanedStatus') || document.getElementById('syncStatus');
    if (status) status.textContent = 'Deleting...';

    const deleteNext = (index) => {
        if (index >= ids.length) {
            if (status) status.textContent = `Permanently deleted ${deleted} images`;
            setTimeout(() => {
                if (status) status.textContent = '';
            }, 3000);
            loadAdminDates();
            return;
        }

        fetch('api.php?action=delete_archived_image', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: `id=${ids[index]}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                deleted++;
            }
            deleteNext(index + 1);
        });
    };

    deleteNext(0);
}

function bulkRestore() {
    const ids = Array.from(currentState.selectedImages);
    if (ids.length === 0) {
        alert('Please select images first');
        return;
    }

    if (!confirm(`Restore ${ids.length} image(s)?`)) return;

    fetch('api.php?action=bulk_restore', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ ids })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            loadAdminDates();
        }
    });
}

function syncRecent() {
    syncImages(false);
}

function syncAll() {
    if (!confirm('Sync all images? This may take a while.')) return;
    syncImages(true);
}

function syncImages(syncAll) {
    const status = document.getElementById('syncStatus');
    if (status) status.textContent = 'Syncing...';

    fetch('api.php?action=sync_images', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: `sync_all=${syncAll ? 1 : 0}`
    })
    .then(response => response.json())
    .then(data => {
        if (status) {
            status.textContent = `Synced ${data.synced_count} images`;
            setTimeout(() => {
                status.textContent = '';
            }, 3000);
        }
        loadAdminDates();
    });
}

function adminToggleFavorite(imageId, event) {
    event.preventDefault();
    event.stopPropagation();
    
    const btn = event.target;
    fetch('api.php?action=toggle_favorite', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: `id=${imageId}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            btn.textContent = data.is_favorite ? '⭐' : '☆';
            btn.classList.toggle('is-favorite');
        }
    });
}

function checkOrphanedImages() {
    const status = document.getElementById('orphanedStatus');
    if (status) status.textContent = 'Checking for broken records...';

    fetch('api.php?action=check_orphaned_images')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                if (data.orphaned_count === 0) {
                    if (status) {
                        status.textContent = 'All archived images are intact!';
                        setTimeout(() => status.textContent = '', 3000);
                    }
                } else {
                    showOrphanedImagesDialog(data.orphaned);
                }
            }
        })
        .catch(error => {
            console.error('Error checking orphaned images:', error);
            if (status) status.textContent = 'Error checking images';
        });
}

function showOrphanedImagesDialog(orphaned) {
    let html = `
        <div class="modal" id="orphanedModal" style="display: flex;">
            <div class="modal-content">
                <span class="modal-close" onclick="document.getElementById('orphanedModal').style.display = 'none'">&times;</span>
                <h2>Broken Records & Orphaned Thumbnails</h2>
                <p>Found ${orphaned.length} issue(s) in archived images:</p>
                <div style="max-height: 400px; overflow-y: auto; border: 1px solid var(--border-color); border-radius: 0.5rem; padding: 1rem; margin-bottom: 1rem; background-color: var(--bg-secondary);">
    `;

    orphaned.forEach((item, index) => {
        let issueText = '';
        let dataAttr = '';
        
        if (item.type === 'broken_record') {
            // DB record exists but files are missing
            issueText = item.issues.map(issue => {
                if (issue === 'missing_file') return '❌ Missing image file';
                if (issue === 'missing_thumbnail') return '❌ Missing thumbnail';
                return issue;
            }).join(', ');
            dataAttr = `data-type="record" data-id="${item.id}"`;
        } else if (item.type === 'orphaned_thumbnail') {
            // Thumbnail exists but no DB record
            issueText = '❌ Orphaned thumbnail (no database record)';
            dataAttr = `data-type="thumbnail" data-thumbnail="${escapeHtml(item.thumbnail_name)}"`;
        }

        html += `
            <div style="padding: 0.5rem 0; border-bottom: 1px solid var(--border-color);">
                <input type="checkbox" class="orphaned-checkbox" ${dataAttr} checked>
                <span style="font-family: monospace; font-size: 0.85rem;">${escapeHtml(item.filename)}</span>
                <br/>
                <span style="color: var(--text-secondary); font-size: 0.85rem;">${issueText}</span>
            </div>
        `;
    });

    html += `
                </div>
                <div class="modal-buttons">
                    <button class="btn btn-danger" onclick="deleteSelectedOrphaned()">Delete Checked Items</button>
                    <button class="btn btn-secondary" onclick="document.getElementById('orphanedModal').style.display = 'none'">Cancel</button>
                </div>
            </div>
        </div>
    `;

    document.body.insertAdjacentHTML('beforeend', html);
}

function deleteSelectedOrphaned() {
    const checkboxes = document.querySelectorAll('.orphaned-checkbox:checked');
    const ids = [];
    const thumbnails = [];
    
    checkboxes.forEach(cb => {
        if (cb.dataset.type === 'record') {
            ids.push(parseInt(cb.dataset.id));
        } else if (cb.dataset.type === 'thumbnail') {
            thumbnails.push(cb.dataset.thumbnail);
        }
    });

    if (ids.length === 0 && thumbnails.length === 0) {
        alert('Please select items to delete');
        return;
    }

    const itemCount = ids.length + thumbnails.length;
    if (!confirm(`Delete ${itemCount} item(s)? This includes ${ids.length} database record(s) and ${thumbnails.length} thumbnail file(s).`)) return;

    fetch('api.php?action=cleanup_orphaned_images', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ ids, thumbnails })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            document.getElementById('orphanedModal').remove();
            const status = document.getElementById('orphanedStatus');
            if (status) {
                const msg = `Deleted ${data.deleted_records} record(s) and ${data.deleted_thumbnails} thumbnail(s)`;
                status.textContent = msg;
                setTimeout(() => status.textContent = '', 3000);
            }
            loadAdminDates();
        }
    });
}

// ===== UTILITY FUNCTIONS =====

function formatDate(dateString) {
    const options = { 
        year: 'numeric', 
        month: 'long', 
        day: 'numeric', 
        hour: '2-digit', 
        minute: '2-digit'
    };
    return new Date(dateString).toLocaleDateString('en-US', options);
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
