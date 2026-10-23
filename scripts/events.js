/**
 * StudentHub: Asynchronous Event Management (Practical 13)
 * Implements Fetch API CRUD without full page reload.
 *
 * Operations:
 *  - loadEvents()      : Asynchronous GET from /api/events.php with search & filters
 *  - createEvent()     : Asynchronous POST via Fetch API & FormData (with poster)
 *  - updateEvent()     : Asynchronous PUT/POST via Fetch API & FormData
 *  - deleteEvent()     : Asynchronous DELETE via Fetch API with DOM removal
 *  - Live search with 300ms debounce
 *  - Loading states, empty states, and toast notifications
 */

// Cache of loaded events
let currentEvents = [];
let searchDebounceTimer = null;

/**
 * Escapes HTML characters to prevent XSS injection.
 *
 * @param {string|null|undefined} text
 * @returns {string} Safe HTML string
 */
function escapeHtml(text) {
    if (text === null || text === undefined) return '';
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return String(text).replace(/[&<>"']/g, function(m) { return map[m]; });
}

/**
 * Formats a YYYY-MM-DD date string to a human-readable format.
 *
 * @param {string} dateStr
 * @returns {string} Formatted date (e.g., "Thu, Nov 15, 2026")
 */
function formatDate(dateStr) {
    if (!dateStr) return 'N/A';
    try {
        const parts = dateStr.split('-');
        if (parts.length === 3) {
            const date = new Date(parts[0], parts[1] - 1, parts[2]);
            return date.toLocaleDateString('en-US', {
                weekday: 'short',
                year: 'numeric',
                month: 'short',
                day: 'numeric'
            });
        }
    } catch (e) {}
    return dateStr;
}

/**
 * Formats HH:MM[:SS] to 12-hour AM/PM format.
 *
 * @param {string} timeStr
 * @returns {string}
 */
function formatTime(timeStr) {
    if (!timeStr) return 'TBA';
    try {
        const parts = timeStr.split(':');
        let hours = parseInt(parts[0], 10);
        const minutes = parts[1] || '00';
        const ampm = hours >= 12 ? 'PM' : 'AM';
        hours = hours % 12;
        hours = hours ? hours : 12; // '0' should be '12'
        return hours + ':' + minutes + ' ' + ampm;
    } catch (e) {
        return timeStr;
    }
}

/**
 * Displays feedback notifications at the top of the main area.
 *
 * @param {string} message Text message
 * @param {'success'|'danger'|'warning'|'info'} type Banner style
 */
function showNotification(message, type = 'success') {
    const container = document.getElementById('ajax-feedback');
    if (!container) return;

    const isSuccess = type === 'success';
    const icon = isSuccess ? '&#10004;' : '&#9888;';
    const bg   = isSuccess ? 'var(--success-bg)' : 'var(--danger-bg)';
    const text = isSuccess ? 'var(--success)' : 'var(--danger)';
    const border = isSuccess ? '#86efac' : '#fca5a5';

    container.innerHTML = `
        <div style="background:${bg}; color:${text}; padding:12px 16px; border:1px solid ${border}; border-radius:var(--radius-sm); margin-bottom:14px; font-weight:600; display:flex; justify-content:space-between; align-items:center; animation: fadeIn 0.2s var(--ease);">
            <div>${icon} ${escapeHtml(message)}</div>
            <button type="button" onclick="this.parentElement.remove()" style="background:transparent; border:none; color:inherit; font-size:18px; cursor:pointer; padding:0 4px; box-shadow:none;">&times;</button>
        </div>
    `;

    // Auto-dismiss success notification after 5 seconds
    if (isSuccess) {
        setTimeout(() => {
            if (container.firstElementChild) {
                container.firstElementChild.style.transition = 'opacity 0.4s ease';
                container.firstElementChild.style.opacity = '0';
                setTimeout(() => { container.innerHTML = ''; }, 400);
            }
        }, 5000);
    }
}

/**
 * Shows or hides the table loading skeleton.
 *
 * @param {boolean} isLoading
 */
function setTableLoading(isLoading) {
    const tbody = document.getElementById('events-table-body');
    if (!tbody) return;

    if (isLoading) {
        tbody.innerHTML = `
            <tr id="loading-row">
                <td colspan="6" style="text-align:center; padding: 40px 16px; color:var(--text-muted);">
                    <div class="loading-spinner" style="margin: 0 auto 12px; width:32px; height:32px; border:3px solid var(--border); border-top-color:var(--primary); border-radius:50%; animation: spin 0.75s linear infinite;"></div>
                    <b style="color:var(--text); font-size:14px;">Loading campus events...</b>
                    <p style="margin:4px 0 0; font-size:12px; color:var(--text-muted);">Fetching real-time records via PHP JSON API</p>
                </td>
            </tr>
        `;
    }
}

/**
 * Renders the array of event objects into table rows.
 *
 * @param {Array<Object>} events
 */
function renderEventsTable(events) {
    const tbody = document.getElementById('events-table-body');
    const countEl = document.getElementById('event-count-label');
    if (!tbody) return;

    tbody.innerHTML = '';

    if (!events || events.length === 0) {
        tbody.innerHTML = `
            <tr id="empty-state-row">
                <td colspan="6" style="text-align:center; padding: 38px 16px; color:var(--text-muted);">
                    <div style="font-size: 2.2rem; margin-bottom: 8px;">&#128197;</div>
                    <b style="font-size: 1.05rem; color:var(--text);">No events found matching your criteria.</b>
                    <p style="margin: 6px 0 14px; font-size: 13px;">Try adjusting your search keywords or clearing active category/status filters.</p>
                    <button type="button" class="btn-outline" onclick="resetFilters()" style="font-size: 12.5px;">
                        Reset Filters
                    </button>
                </td>
            </tr>
        `;
        if (countEl) countEl.innerHTML = `Showing <b>0</b> event(s) in system.`;
        return;
    }

    if (countEl) {
        countEl.innerHTML = `Showing <b>${events.length}</b> event(s) in system.`;
    }

    events.forEach(evt => {
        const tr = document.createElement('tr');
        tr.id = `event-row-${evt.event_id}`;
        tr.style.transition = 'all 0.3s ease';

        const evtId   = parseInt(evt.event_id, 10);
        const name    = escapeHtml(evt.event_name);
        const cat     = escapeHtml(evt.category);
        const dateStr = formatDate(evt.event_date);
        const timeStr = formatTime(evt.event_time);
        const venue   = escapeHtml(evt.venue);
        const status  = (evt.status || 'Open').toLowerCase();

        // Poster cell
        let posterHtml = '';
        if (evt.poster) {
            const posterSrc = `../uploads/posters/${encodeURIComponent(evt.poster)}`;
            posterHtml = `
                <img
                    src="${posterSrc}"
                    alt="${name} Poster"
                    class="poster-thumb"
                    onclick="openDetailModal(${evtId})"
                    title="Click to view details"
                    onerror="this.onerror=null; this.parentElement.innerHTML='<div class=\\'poster-placeholder\\'>No<br>Poster</div>';"
                >
            `;
        } else {
            posterHtml = `<div class="poster-placeholder" title="No poster uploaded">No<br>Poster</div>`;
        }

        // Status badge
        const statusBadge = (status === 'open')
            ? `<span class="status-badge-open">&#9679; Open</span>`
            : `<span class="status-badge-closed">&#9679; Closed</span>`;

        // Action buttons (Role-based: only admin gets Edit & Delete)
        let actionButtons = `
            <button
                type="button"
                class="btn-outline"
                onclick="openDetailModal(${evtId})"
                title="View event details"
            >View</button>
        `;

        if (typeof IS_ADMIN !== 'undefined' && IS_ADMIN) {
            actionButtons += `
                <button
                    type="button"
                    onclick="openEditModal(${evtId})"
                    title="Edit event"
                >Edit</button>
                <button
                    type="button"
                    class="btn-danger"
                    onclick="openDeleteModal(${evtId}, '${name.replace(/'/g, "\\'")}')"
                    title="Delete event"
                >Delete</button>
            `;
        }

        tr.innerHTML = `
            <td align="center" style="vertical-align: middle;">${posterHtml}</td>
            <td>
                <b style="font-size: 14.5px; color:var(--text);">${name}</b>
                <div style="margin-top: 4px;">
                    <span class="cat-badge">${cat}</span>
                    <span style="font-size: 11px; color:var(--text-muted); margin-left:6px;">ID #${evtId}</span>
                </div>
            </td>
            <td>
                <div style="font-weight: 600;">${dateStr}</div>
                <div style="font-size: 12px; color:var(--text-muted); margin-top: 2px;">
                    &#128338; ${timeStr}
                </div>
            </td>
            <td>
                <div style="font-size: 13.5px;">&#128205; ${venue}</div>
            </td>
            <td align="center">${statusBadge}</td>
            <td align="center">
                <div class="action-btns">${actionButtons}</div>
            </td>
        `;

        tbody.appendChild(tr);
    });
}

/**
 * Loads events from PHP JSON API using Fetch API.
 *
 * @param {boolean} showLoading Whether to show the loading skeleton
 */
async function loadEvents(showLoading = true) {
    const searchInput = document.getElementById('search-input');
    const categorySelect = document.getElementById('category-filter');
    const statusSelect = document.getElementById('status-filter');

    const search   = searchInput ? searchInput.value.trim() : '';
    const category = categorySelect ? categorySelect.value.trim() : '';
    const status   = statusSelect ? statusSelect.value.trim() : '';

    const params = new URLSearchParams();
    if (search) params.append('search', search);
    if (category) params.append('category', category);
    if (status) params.append('status', status);

    const apiUrl = `../api/events.php?${params.toString()}`;

    if (showLoading) {
        setTableLoading(true);
    }

    try {
        const response = await fetch(apiUrl, {
            method: 'GET',
            headers: {
                'Accept': 'application/json'
            }
        });

        if (!response.ok && response.status === 401) {
            showNotification('Session expired. Please sign in again.', 'danger');
            window.location.href = 'login.php?error=unauthorized';
            return;
        }

        const data = await response.json();

        if (data.success) {
            currentEvents = Array.isArray(data.data) ? data.data : [];
            renderEventsTable(currentEvents);
        } else {
            showNotification(data.message || 'Failed to retrieve events.', 'danger');
            renderEventsTable([]);
        }
    } catch (error) {
        console.error('Fetch error:', error);
        showNotification('Network failure: Unable to communicate with the server. Please check your connection.', 'danger');
        renderEventsTable([]);
    }
}

/**
 * Resets search input and filter dropdowns and re-fetches events.
 */
function resetFilters() {
    const searchInput = document.getElementById('search-input');
    const categorySelect = document.getElementById('category-filter');
    const statusSelect = document.getElementById('status-filter');

    if (searchInput) searchInput.value = '';
    if (categorySelect) categorySelect.value = '';
    if (statusSelect) statusSelect.value = '';

    loadEvents();
}

/**
 * Debounced search event handler.
 */
function handleSearchInput() {
    clearTimeout(searchDebounceTimer);
    searchDebounceTimer = setTimeout(() => {
        loadEvents(false);
    }, 300);
}

// =========================================================================
// MODAL CONTROLS & EVENT POPULATION
// =========================================================================

function openModal(id) {
    const el = document.getElementById(id);
    if (el) el.classList.add('active');
}

function closeModal(id) {
    const el = document.getElementById(id);
    if (el) el.classList.remove('active');
}

function handleOverlayClick(event, id) {
    if (event.target && event.target.id === id) {
        closeModal(id);
    }
}

// Close on Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal-overlay.active').forEach(m => m.classList.remove('active'));
    }
});

/**
 * Opens Create Event Modal with fresh fields.
 */
function openCreateModal() {
    const form = document.getElementById('createEventForm');
    if (form) form.reset();
    const prev = document.getElementById('create_preview');
    if (prev) prev.style.display = 'none';
    const errBox = document.getElementById('create-modal-error');
    if (errBox) { errBox.style.display = 'none'; errBox.textContent = ''; }
    openModal('createModal');
}

/**
 * Opens Edit Event Modal populated with data.
 *
 * @param {number|Object} param Event ID or Event Object
 */
function openEditModal(param) {
    if (typeof param === 'object' && param !== null) {
        populateAndShowEditModal(param);
        return;
    }
    const eventId = parseInt(param, 10);
    const evt = currentEvents.find(e => parseInt(e.event_id, 10) === eventId);
    if (!evt) {
        fetchSingleEventAndPopulateEdit(eventId);
        return;
    }
    populateAndShowEditModal(evt);
}

async function fetchSingleEventAndPopulateEdit(eventId) {
    try {
        const res = await fetch(`../api/events.php?id=${encodeURIComponent(eventId)}`);
        const json = await res.json();
        if (json.success && json.data) {
            populateAndShowEditModal(json.data);
        } else {
            showNotification(json.message || 'Event not found.', 'danger');
        }
    } catch (e) {
        showNotification('Unable to fetch event details.', 'danger');
    }
}

function populateAndShowEditModal(evt) {
    document.getElementById('edit_event_id').value = evt.event_id;
    document.getElementById('edit_title').value    = evt.event_name || '';
    document.getElementById('edit_category').value = evt.category || 'Technical';
    document.getElementById('edit_status').value   = evt.status || 'Open';
    document.getElementById('edit_date').value     = evt.event_date || '';
    document.getElementById('edit_venue').value    = evt.venue || '';
    document.getElementById('edit_description').value = evt.description || '';

    let timeVal = '10:00';
    if (evt.event_time) {
        timeVal = evt.event_time.substring(0, 5);
    }
    document.getElementById('edit_time').value = timeVal;

    const labelEl = document.getElementById('current_poster_label');
    if (evt.poster) {
        labelEl.textContent = evt.poster;
    } else {
        labelEl.textContent = 'None (no poster uploaded)';
    }

    document.getElementById('edit_poster').value = '';
    document.getElementById('edit_preview').style.display = 'none';

    const errBox = document.getElementById('edit-modal-error');
    if (errBox) { errBox.style.display = 'none'; errBox.textContent = ''; }

    openModal('editModal');
}

/**
 * Opens Delete Confirmation Modal.
 *
 * @param {number} id Event ID
 * @param {string} title Event Title
 */
function openDeleteModal(id, title) {
    document.getElementById('delete_event_id').value = id;
    document.getElementById('delete_event_title').textContent = title;
    document.getElementById('delete_event_id_label').textContent = 'Event ID: #' + id;

    const errBox = document.getElementById('delete-modal-error');
    if (errBox) { errBox.style.display = 'none'; errBox.textContent = ''; }

    openModal('deleteModal');
}

/**
 * Opens View Event Details Modal.
 *
 * @param {number|Object} param Event ID or Event Object
 */
function openDetailModal(param) {
    if (typeof param === 'object' && param !== null) {
        populateDetailModal(param);
        return;
    }
    const eventId = parseInt(param, 10);
    const evt = currentEvents.find(e => parseInt(e.event_id, 10) === eventId);
    if (!evt) {
        fetch(`../api/events.php?id=${encodeURIComponent(eventId)}`)
            .then(r => r.json())
            .then(res => {
                if (res.success && res.data) populateDetailModal(res.data);
            });
        return;
    }
    populateDetailModal(evt);
}

function populateDetailModal(evt) {
    document.getElementById('detail_title').textContent       = evt.event_name;
    document.getElementById('detail_category').textContent    = evt.category;
    document.getElementById('detail_venue').textContent       = evt.venue;
    document.getElementById('detail_description').textContent = evt.description || 'No detailed description provided.';

    const statusEl = document.getElementById('detail_status');
    if ((evt.status || '').toLowerCase() === 'open') {
        statusEl.innerHTML = '<span class="status-badge-open">&#9679; Open for Registration</span>';
    } else {
        statusEl.innerHTML = '<span class="status-badge-closed">&#9679; Closed</span>';
    }

    const scheduleStr = `${formatDate(evt.event_date)} at ${formatTime(evt.event_time)}`;
    document.getElementById('detail_schedule').textContent = scheduleStr;

    let ts = 'Created: ' + (evt.created_at || 'N/A');
    if (evt.updated_at) {
        ts += ' | Last Updated: ' + evt.updated_at;
    }
    document.getElementById('detail_timestamps').textContent = ts;

    const posterWrap = document.getElementById('detail_poster_wrap');
    const posterImg  = document.getElementById('detail_poster');
    if (evt.poster) {
        posterImg.src = `../uploads/posters/${encodeURIComponent(evt.poster)}`;
        posterWrap.style.display = 'block';
    } else {
        posterWrap.style.display = 'none';
        posterImg.src = '';
    }

    openModal('detailModal');
}

// Live Image Preview with client-side type & size verification
function previewImage(input, previewBoxId, imgId) {
    const previewBox = document.getElementById(previewBoxId);
    const previewImg = document.getElementById(imgId);

    if (input.files && input.files[0]) {
        const file = input.files[0];
        const maxBytes = 2 * 1024 * 1024; // 2 MB

        if (file.size > maxBytes) {
            alert('Selected file (' + (file.size / 1024 / 1024).toFixed(2) + ' MB) exceeds the 2 MB maximum upload limit.');
            input.value = '';
            previewBox.style.display = 'none';
            return;
        }

        const allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
        if (!allowedTypes.includes(file.type)) {
            alert('Invalid file type (' + file.type + '). Please choose a JPG, PNG, or WebP image.');
            input.value = '';
            previewBox.style.display = 'none';
            return;
        }

        const reader = new FileReader();
        reader.onload = function(e) {
            previewImg.src = e.target.result;
            previewBox.style.display = 'block';
        };
        reader.readAsDataURL(file);
    } else {
        previewBox.style.display = 'none';
    }
}

// =========================================================================
// FETCH API ASYNCHRONOUS FORM SUBMISSIONS (CREATE, UPDATE, DELETE)
// =========================================================================

/**
 * Submits new event form asynchronously using Fetch API and FormData.
 *
 * @param {HTMLFormElement} form
 */
async function submitCreateEvent(form) {
    const submitBtn = document.getElementById('create_submit_btn');
    const errorBox  = document.getElementById('create-modal-error');

    if (errorBox) {
        errorBox.style.display = 'none';
        errorBox.textContent = '';
    }

    // Client-side quick checks
    const title = form.querySelector('input[name="event_name"]').value.trim();
    if (title.length < 3) {
        if (errorBox) {
            errorBox.style.display = 'block';
            errorBox.textContent = 'Event title must be at least 3 characters.';
        }
        return;
    }

    const formData = new FormData(form);

    // Disable button to prevent duplicate submissions
    if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.innerHTML = '&#8987; Creating...';
    }

    try {
        const response = await fetch('../api/events.php', {
            method: 'POST',
            headers: {
                'X-CSRF-Token': typeof CSRF_TOKEN !== 'undefined' ? CSRF_TOKEN : '',
                'Accept': 'application/json'
            },
            body: formData
        });

        const result = await response.json();

        if (response.ok && result.success) {
            closeModal('createModal');
            form.reset();
            const prev = document.getElementById('create_preview');
            if (prev) prev.style.display = 'none';

            showNotification(result.message || 'Event created successfully!', 'success');
            await loadEvents(false);
        } else {
            if (errorBox) {
                errorBox.style.display = 'block';
                errorBox.textContent = result.message || 'Failed to create event.';
            } else {
                showNotification(result.message || 'Failed to create event.', 'danger');
            }
        }
    } catch (err) {
        console.error('Create error:', err);
        if (errorBox) {
            errorBox.style.display = 'block';
            errorBox.textContent = 'Network error: Failed to connect to server.';
        } else {
            showNotification('Network failure: Unable to create event.', 'danger');
        }
    } finally {
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.textContent = 'Create Event';
        }
    }
}

/**
 * Submits edit event form asynchronously using Fetch API and FormData.
 *
 * @param {HTMLFormElement} form
 */
async function submitEditEvent(form) {
    const submitBtn = document.getElementById('edit_submit_btn');
    const errorBox  = document.getElementById('edit-modal-error');

    if (errorBox) {
        errorBox.style.display = 'none';
        errorBox.textContent = '';
    }

    const title = form.querySelector('input[name="event_name"]').value.trim();
    if (title.length < 3) {
        if (errorBox) {
            errorBox.style.display = 'block';
            errorBox.textContent = 'Event title must be at least 3 characters.';
        }
        return;
    }

    const formData = new FormData(form);
    formData.append('_method', 'PUT');
    formData.append('action', 'update');

    if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.innerHTML = '&#8987; Saving...';
    }

    try {
        const response = await fetch('../api/events.php', {
            method: 'POST', // Sent as POST with _method=PUT to enable multipart/form-data with poster
            headers: {
                'X-CSRF-Token': typeof CSRF_TOKEN !== 'undefined' ? CSRF_TOKEN : '',
                'Accept': 'application/json'
            },
            body: formData
        });

        const result = await response.json();

        if (response.ok && result.success) {
            closeModal('editModal');
            showNotification(result.message || 'Event updated successfully!', 'success');
            await loadEvents(false);
        } else {
            if (errorBox) {
                errorBox.style.display = 'block';
                errorBox.textContent = result.message || 'Failed to update event.';
            } else {
                showNotification(result.message || 'Failed to update event.', 'danger');
            }
        }
    } catch (err) {
        console.error('Edit error:', err);
        if (errorBox) {
            errorBox.style.display = 'block';
            errorBox.textContent = 'Network error: Failed to connect to server.';
        } else {
            showNotification('Network failure: Unable to update event.', 'danger');
        }
    } finally {
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.textContent = 'Save Changes';
        }
    }
}

/**
 * Submits event deletion asynchronously using Fetch API without page reload.
 *
 * @param {HTMLFormElement} form
 */
async function submitDeleteEvent(form) {
    const deleteBtn = document.getElementById('delete_submit_btn');
    const errorBox  = document.getElementById('delete-modal-error');
    const eventId   = document.getElementById('delete_event_id').value;

    if (errorBox) {
        errorBox.style.display = 'none';
        errorBox.textContent = '';
    }

    if (!eventId) {
        if (errorBox) {
            errorBox.style.display = 'block';
            errorBox.textContent = 'Invalid event identifier.';
        }
        return;
    }

    const formData = new FormData(form);
    formData.append('_method', 'DELETE');
    formData.append('action', 'delete');

    if (deleteBtn) {
        deleteBtn.disabled = true;
        deleteBtn.innerHTML = '&#8987; Deleting...';
    }

    try {
        const response = await fetch('../api/events.php', {
            method: 'POST', // Handled as DELETE via _method override
            headers: {
                'X-CSRF-Token': typeof CSRF_TOKEN !== 'undefined' ? CSRF_TOKEN : '',
                'Accept': 'application/json'
            },
            body: formData
        });

        const result = await response.json();

        if (response.ok && result.success) {
            closeModal('deleteModal');

            // Animate removal of table row in the UI immediately
            const targetRow = document.getElementById(`event-row-${eventId}`);
            if (targetRow) {
                targetRow.style.opacity = '0';
                targetRow.style.transform = 'translateX(20px)';
                setTimeout(() => {
                    targetRow.remove();
                    // Update cache
                    currentEvents = currentEvents.filter(e => parseInt(e.event_id, 10) !== parseInt(eventId, 10));
                    const countEl = document.getElementById('event-count-label');
                    if (countEl) {
                        countEl.innerHTML = `Showing <b>${currentEvents.length}</b> event(s) in system.`;
                    }
                    if (currentEvents.length === 0) {
                        renderEventsTable([]);
                    }
                }, 300);
            } else {
                await loadEvents(false);
            }

            showNotification(result.message || 'Event deleted successfully.', 'success');
        } else {
            if (errorBox) {
                errorBox.style.display = 'block';
                errorBox.textContent = result.message || 'Failed to delete event.';
            } else {
                showNotification(result.message || 'Failed to delete event.', 'danger');
            }
        }
    } catch (err) {
        console.error('Delete error:', err);
        if (errorBox) {
            errorBox.style.display = 'block';
            errorBox.textContent = 'Network error: Failed to connect to server.';
        } else {
            showNotification('Network failure: Unable to delete event.', 'danger');
        }
    } finally {
        if (deleteBtn) {
            deleteBtn.disabled = false;
            deleteBtn.textContent = 'Permanently Delete';
        }
    }
}

// =========================================================================
// INITIALIZATION ON DOM READY
// =========================================================================

document.addEventListener('DOMContentLoaded', function() {
    // 1. Initial asynchronous load of events
    loadEvents(true);

    // 2. Attach live search listener with debounce
    const searchInput = document.getElementById('search-input');
    if (searchInput) {
        searchInput.addEventListener('input', handleSearchInput);
        searchInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                clearTimeout(searchDebounceTimer);
                loadEvents(false);
            }
        });
    }

    // 3. Attach category & status filter change listeners
    const categorySelect = document.getElementById('category-filter');
    if (categorySelect) {
        categorySelect.addEventListener('change', () => loadEvents(false));
    }

    const statusSelect = document.getElementById('status-filter');
    if (statusSelect) {
        statusSelect.addEventListener('change', () => loadEvents(false));
    }

    // 4. Attach filter form submission preventer
    const filterForm = document.getElementById('filterForm');
    if (filterForm) {
        filterForm.addEventListener('submit', function(e) {
            e.preventDefault();
            loadEvents(false);
        });
    }

    // 5. Intercept Create Modal form
    const createForm = document.getElementById('createEventForm');
    if (createForm) {
        createForm.addEventListener('submit', function(e) {
            e.preventDefault();
            submitCreateEvent(this);
        });
    }

    // 6. Intercept Edit Modal form
    const editForm = document.getElementById('editEventForm');
    if (editForm) {
        editForm.addEventListener('submit', function(e) {
            e.preventDefault();
            submitEditEvent(this);
        });
    }

    // 7. Intercept Delete Modal form
    const deleteForm = document.getElementById('deleteEventForm');
    if (deleteForm) {
        deleteForm.addEventListener('submit', function(e) {
            e.preventDefault();
            submitDeleteEvent(this);
        });
    }
});
