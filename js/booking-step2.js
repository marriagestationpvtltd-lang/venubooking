/**
 * Booking Step 2 - Venue and Hall Selection
 */

let currentVenueId = null;

// Validate session on page load
document.addEventListener('DOMContentLoaded', function() {
    // Check if we have required session data
    if (typeof bookingData === 'undefined' || !bookingData) {
        // No session data, redirect to start
        const redirectUrl = (typeof baseUrl !== 'undefined' && baseUrl) ? baseUrl + '/index.php' : '/index.php';
        window.location.href = redirectUrl;
        return;
    }
    
    // Handle back button navigation
    window.addEventListener('popstate', function(event) {
        // Redirect to start if session is lost
        if (typeof bookingData === 'undefined' || !bookingData) {
            const redirectUrl = (typeof baseUrl !== 'undefined' && baseUrl) ? baseUrl + '/index.php' : '/index.php';
            window.location.href = redirectUrl;
        }
    });
});

// Escape HTML to prevent XSS - safer implementation
function escapeHtml(text) {
    if (text === null || text === undefined) return '';
    return String(text)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

// Venue name search filter
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('venueSearchInput');
    const clearBtn    = document.getElementById('venueSearchClear');
    const noResults   = document.getElementById('venueSearchNoResults');

    if (!searchInput) return;

    // Cache venue cards once — they do not change after page load
    const cards = Array.from(document.querySelectorAll('#venuesContainer [data-venue-name]'));

    function filterVenues() {
        const term = searchInput.value.trim().toLowerCase();
        let visibleCount = 0;

        cards.forEach(function(card) {
            const name = (card.getAttribute('data-venue-name') || '').toLowerCase();
            const show = name.includes(term);
            card.style.display = show ? '' : 'none';
            if (show) visibleCount++;
        });

        if (noResults) {
            noResults.style.display = visibleCount === 0 ? 'block' : 'none';
        }
        if (clearBtn) {
            clearBtn.style.display = term.length > 0 ? 'inline-block' : 'none';
        }
    }

    searchInput.addEventListener('input', filterVenues);

    if (clearBtn) {
        clearBtn.addEventListener('click', function() {
            searchInput.value = '';
            filterVenues();
            searchInput.focus();
        });
    }
});

// Show halls for selected venue
function showHalls(venueId, venueName) {
    currentVenueId = venueId;
    
    showLoading();
    
    // Fetch halls for the venue (no shift needed – time slots drive availability)
    const params = new URLSearchParams({
        venue_id: venueId,
        date: bookingData.event_date,
        guests: bookingData.guests
    });
    
    fetch(baseUrl + '/api/get-halls.php?' + params)
        .then(response => response.json())
        .then(data => {
            hideLoading();
            
            if (data.success) {
                displayHalls(data.halls, venueName);
            } else {
                showError(data.message || 'Failed to load halls');
            }
        })
        .catch(error => {
            hideLoading();
            // Log error for debugging but show user-friendly message
            if (typeof logError === 'function') {
                logError('Hall loading failed', error);
            }
            showError('An error occurred while loading halls');
        });
}

// Display halls
function displayHalls(halls, venueName) {
    const venuesContainer = document.getElementById('venuesContainer');
    const hallsSection = document.getElementById('hallsSection');
    const hallsContainer = document.getElementById('hallsContainer');
    const venueNameElement = document.getElementById('venueName');
    const searchWrapper = document.getElementById('venueSearchWrapper');
    const searchNoResults = document.getElementById('venueSearchNoResults');
    
    // Hide venues and search, show halls section
    if (venuesContainer) venuesContainer.style.display = 'none';
    if (searchWrapper) searchWrapper.style.display = 'none';
    if (searchNoResults) searchNoResults.style.display = 'none';
    if (hallsSection) hallsSection.style.display = 'block';
    if (venueNameElement) venueNameElement.textContent = venueName;
    
    if (!halls || halls.length === 0) {
        hallsContainer.innerHTML = `
            <div class="col-12">
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i> No halls available for the selected capacity and date.
                </div>
            </div>
        `;
        return;
    }
    
    hallsContainer.innerHTML = '';
    
    // Ensure the 360° pano modal exists in the DOM (inject once)
    ensurePanoModal();

    // Build all hall cards HTML first for better performance
    let hallsHtml = '';
    
    halls.forEach(hall => {
        // Build image/carousel HTML
        let imageHtml = '';
        const images = Array.isArray(hall.image_urls) && hall.image_urls.length > 0 ? hall.image_urls : (hall.image_url ? [hall.image_url] : []);
        if (images.length > 1) {
            const carouselId = 'hallCarousel' + parseInt(hall.id, 10);
            const items = images.map((url, idx) =>
                `<div class="carousel-item ${idx === 0 ? 'active' : ''}">
                    <img src="${escapeHtml(url)}" class="d-block w-100 hall-image" alt="${escapeHtml(hall.name)}">
                </div>`
            ).join('');
            imageHtml = `
                <div id="${carouselId}" class="carousel slide" data-bs-ride="carousel">
                    <div class="carousel-inner">${items}</div>
                    <button class="carousel-control-prev" type="button" data-bs-target="#${carouselId}" data-bs-slide="prev">
                        <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                        <span class="visually-hidden">Previous</span>
                    </button>
                    <button class="carousel-control-next" type="button" data-bs-target="#${carouselId}" data-bs-slide="next">
                        <span class="carousel-control-next-icon" aria-hidden="true"></span>
                        <span class="visually-hidden">Next</span>
                    </button>
                    <div class="carousel-indicators-counter position-absolute top-0 end-0 p-2">
                        <span class="badge bg-dark bg-opacity-75"><i class="fas fa-images"></i> ${images.length}</span>
                    </div>
                </div>`;
        } else if (images.length === 1) {
            imageHtml = `<img src="${escapeHtml(images[0])}" class="card-img-top hall-image" alt="${escapeHtml(hall.name)}">`;
        }

        const hallCard = `
            <div class="col-md-6 col-lg-4" data-hall-name="${escapeHtml(hall.name || '')}">
                <div class="hall-card card h-100">
                    ${imageHtml}
                    <div class="card-body">
                        <h5 class="card-title">${escapeHtml(hall.name)}</h5>
                        <div class="mb-3">
                            <span class="capacity-badge">
                                <i class="fas fa-users"></i> ${parseInt(hall.capacity, 10) || 0} pax
                            </span>
                            <span class="badge bg-info ms-2">${escapeHtml(hall.indoor_outdoor)}</span>
                            ${hall.pano_image_url ? `<span class="badge bg-primary ms-2"><i class="fas fa-street-view"></i> 360°</span>` : ''}
                        </div>
                        <p class="card-text text-muted">${escapeHtml(hall.description || '')}</p>
                        ${hall.features ? `<p class="small"><strong>Features:</strong> ${escapeHtml(hall.features)}</p>` : ''}
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <span class="text-muted">Base Price:</span>
                            <h5 class="text-success mb-0">${formatCurrency(parseFloat(hall.base_price) || 0)}</h5>
                        </div>
                        ${hall.pano_image_url ? `
                        <button class="btn btn-outline-primary w-100 mb-2 view-pano-btn"
                                data-pano-url="${escapeHtml(hall.pano_image_url)}"
                                data-hall-name="${escapeHtml(hall.name || '')}">
                            <i class="fas fa-street-view"></i> View 360° Panorama
                        </button>` : ''}
                        ${hall.has_time_slots ? 
                            `<button class="btn btn-success w-100 select-hall-btn" 
                                     data-hall-id="${parseInt(hall.id, 10) || 0}" 
                                     data-hall-name="${hall.name || ''}" 
                                     data-venue-name="${venueName || ''}" 
                                     data-base-price="${parseFloat(hall.base_price) || 0}" 
                                     data-capacity="${parseInt(hall.capacity, 10) || 0}">
                                <i class="fas fa-clock"></i> View Available Times
                            </button>` :
                            `<button class="btn btn-secondary w-100" disabled title="No time slots have been configured for this hall yet.">
                                <i class="fas fa-exclamation-circle"></i> No Time Slots Available
                            </button>`
                        }
                    </div>
                </div>
            </div>
        `;
        hallsHtml += hallCard;
    });
    
    // Set innerHTML once for better performance
    hallsContainer.innerHTML = hallsHtml;
    
    // Add event listeners to select buttons using event delegation
    hallsContainer.querySelectorAll('.select-hall-btn').forEach(button => {
        button.addEventListener('click', function() {
            const hallId = parseInt(this.getAttribute('data-hall-id'), 10);
            const hallName = this.getAttribute('data-hall-name') || '';
            const venueName = this.getAttribute('data-venue-name') || '';
            const basePrice = parseFloat(this.getAttribute('data-base-price'));
            const capacity = parseInt(this.getAttribute('data-capacity'), 10);
            
            // Validate numeric values are valid and positive
            if (isNaN(hallId) || hallId <= 0 || isNaN(basePrice) || basePrice < 0 || isNaN(capacity) || capacity <= 0) {
                showError('Invalid hall data. Please try again.');
                return;
            }
            
            // Show time slot selector before confirming hall selection
            openTimeSlotModal(hallId, hallName, venueName, basePrice, capacity);
        });
    });

    // Add event listeners to 360° panorama view buttons
    hallsContainer.querySelectorAll('.view-pano-btn').forEach(button => {
        button.addEventListener('click', function() {
            const panoUrl = this.getAttribute('data-pano-url');
            const hallName = this.getAttribute('data-hall-name') || '';
            openPanoViewer(panoUrl, hallName);
        });
    });

    // Reset and re-initialise hall search input
    initHallSearch();
    
    // Scroll to halls section
    hallsSection.scrollIntoView({ behavior: 'smooth' });
}

// Initialise (or re-initialise) the hall name search filter
function initHallSearch() {
    const searchInput = document.getElementById('hallSearchInput');
    const clearBtn    = document.getElementById('hallSearchClear');
    const noResults   = document.getElementById('hallSearchNoResults');
    const hallsContainer = document.getElementById('hallsContainer');

    if (!searchInput || !hallsContainer) return;

    // Abort any previously attached listeners on this element
    if (searchInput._hallSearchAbort) {
        searchInput._hallSearchAbort.abort();
    }
    const controller = new AbortController();
    searchInput._hallSearchAbort = controller;
    const signal = controller.signal;

    // Clear any previous search value
    searchInput.value = '';
    if (clearBtn) clearBtn.style.display = 'none';
    if (noResults) noResults.style.display = 'none';

    function filterHalls() {
        const term = searchInput.value.trim().toLowerCase();
        const cards = Array.from(hallsContainer.querySelectorAll('[data-hall-name]'));
        let visibleCount = 0;

        cards.forEach(function(card) {
            const name = (card.getAttribute('data-hall-name') || '').toLowerCase();
            const show = name.includes(term);
            card.style.display = show ? '' : 'none';
            if (show) visibleCount++;
        });

        if (noResults) {
            noResults.style.display = visibleCount === 0 ? 'block' : 'none';
        }
        if (clearBtn) {
            clearBtn.style.display = term.length > 0 ? 'inline-block' : 'none';
        }
    }

    searchInput.addEventListener('input', filterHalls, { signal });

    if (clearBtn) {
        if (clearBtn._hallClearAbort) {
            clearBtn._hallClearAbort.abort();
        }
        const clearController = new AbortController();
        clearBtn._hallClearAbort = clearController;
        clearBtn.addEventListener('click', function() {
            searchInput.value = '';
            filterHalls();
            searchInput.focus();
        }, { signal: clearController.signal });
    }
}

// Show venues (back button)
function showVenues() {
    const venuesContainer = document.getElementById('venuesContainer');
    const hallsSection = document.getElementById('hallsSection');
    const searchWrapper = document.getElementById('venueSearchWrapper');
    
    if (venuesContainer) venuesContainer.style.display = 'flex';
    if (searchWrapper) searchWrapper.style.display = 'block';
    if (hallsSection) hallsSection.style.display = 'none';
    
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

// Select hall (with optional time slot) and proceed to next step
function selectHall(hallId, hallName, venueName, basePrice, capacity, slotId) {
    // Save selected hall to session
    const hallData = {
        id: hallId,
        name: hallName,
        venue_name: venueName,
        base_price: basePrice,
        capacity: capacity
    };

    if (slotId !== undefined && slotId !== null) {
        hallData.slot_id = slotId;
    }
    
    // Show loading indicator
    showLoading();
    
    // Update session via AJAX
    fetch(baseUrl + '/api/select-hall.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(hallData)
    })
    .then(response => response.json())
    .then(data => {
        hideLoading();
        
        if (data.success) {
            // Update total cost
            updateTotalCost(basePrice);
            
            // Redirect to next step
            window.location.href = baseUrl + '/booking-step3.php';
        } else {
            showError(data.message || 'Failed to select hall');
        }
    })
    .catch(error => {
        hideLoading();
        // Log error for debugging
        if (typeof logError === 'function') {
            logError('Hall selection failed', error);
        }
        showError('An error occurred while selecting the hall');
    });
}

// ── Time Slot Modal ─────────────────────────────────────────────────────────

let _pendingHall = null;   // stores hall data while user picks a slot
let _selectedSlot = null;  // the slot the user has chosen

function openTimeSlotModal(hallId, hallName, venueName, basePrice, capacity) {
    _pendingHall = { hallId, hallName, venueName, basePrice, capacity };
    _selectedSlot = null;

    // Populate modal header
    const nameEl = document.getElementById('tsModalHallName');
    if (nameEl) nameEl.textContent = hallName;

    const dateEl = document.getElementById('tsModalDate');
    if (dateEl) dateEl.textContent = bookingData.event_date || '';

    // Disable confirm button until a slot is selected
    const confirmBtn = document.getElementById('confirmSlotBtn');
    if (confirmBtn) confirmBtn.disabled = true;

    // Show loading spinner inside modal body
    const container = document.getElementById('timeSlotsContainer');
    if (container) {
        container.innerHTML = `
            <div class="text-center py-4">
                <div class="spinner-border text-success" role="status">
                    <span class="visually-hidden">Loading…</span>
                </div>
                <p class="mt-2 text-muted">Loading available time slots…</p>
            </div>`;
    }

    // Show the modal
    const modalEl = document.getElementById('timeSlotModal');
    if (!modalEl) { showError('Time slot modal not found.'); return; }
    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    modal.show();

    // Fetch available slots
    const params = new URLSearchParams({
        hall_id: hallId,
        date: bookingData.event_date
    });

    fetch(baseUrl + '/api/get-time-slots.php?' + params)
        .then(r => r.json())
        .then(data => {
            if (!data.success) {
                container.innerHTML = `<div class="alert alert-danger"><i class="fas fa-exclamation-circle me-1"></i>${escapeHtml(data.message || 'Failed to load time slots.')}</div>`;
                return;
            }
            renderTimeSlots(data.slots, container);
        })
        .catch(() => {
            container.innerHTML = `<div class="alert alert-danger"><i class="fas fa-exclamation-circle me-1"></i>An error occurred while loading time slots.</div>`;
        });
}

function renderTimeSlots(slots, container) {
    if (!slots || slots.length === 0) {
        container.innerHTML = `<div class="alert alert-warning"><i class="fas fa-clock me-1"></i>No time slots have been configured for this hall. Please contact us or choose another hall.</div>`;
        return;
    }

    let html = '<div class="row g-3">';
    slots.forEach(slot => {
        const available = slot.available;
        const priceLabel = slot.price_override !== null
            ? formatCurrency(slot.price_override)
            : formatCurrency(_pendingHall ? _pendingHall.basePrice : 0);

        html += `
            <div class="col-12 col-md-6">
                <div class="card h-100 time-slot-card ${available ? 'border-success' : 'border-secondary opacity-50'}"
                     data-slot-id="${parseInt(slot.id, 10)}"
                     data-slot-name="${escapeHtml(slot.slot_name)}"
                     data-start="${escapeHtml(slot.start_time)}"
                     data-end="${escapeHtml(slot.end_time)}"
                     data-price="${slot.price_override !== null ? parseFloat(slot.price_override) : ''}"
                     style="${available ? 'cursor:pointer;' : 'cursor:not-allowed;'}">
                    <div class="card-body d-flex flex-column justify-content-between">
                        <div>
                            <h6 class="card-title mb-1 ${available ? 'text-success' : 'text-muted'}">
                                <i class="fas fa-clock me-1"></i>${escapeHtml(slot.slot_name)}
                            </h6>
                            <p class="text-muted mb-2 small">
                                ${escapeHtml(slot.start_time_display)} – ${escapeHtml(slot.end_time_display)}
                            </p>
                            <p class="mb-0 fw-semibold small">${priceLabel}</p>
                        </div>
                        <div class="mt-2">
                            ${available
                                ? `<span class="badge bg-success"><i class="fas fa-check-circle me-1"></i>Available</span>`
                                : `<span class="badge bg-secondary"><i class="fas fa-ban me-1"></i>Already Booked</span>`
                            }
                        </div>
                    </div>
                </div>
            </div>`;
    });
    html += '</div>';
    container.innerHTML = html;

    // Attach click listeners only to available slots
    container.querySelectorAll('.time-slot-card').forEach(card => {
        const slotId = parseInt(card.getAttribute('data-slot-id'), 10);
        const slotPrice = card.getAttribute('data-price');
        if (!slots.find(s => s.id === slotId && s.available)) return;

        card.addEventListener('click', function() {
            // Deselect all
            container.querySelectorAll('.time-slot-card.selected-slot').forEach(c => {
                c.classList.remove('selected-slot', 'border-warning', 'shadow');
                c.classList.add('border-success');
            });
            // Highlight selected
            this.classList.add('selected-slot', 'border-warning', 'shadow');
            this.classList.remove('border-success');

            _selectedSlot = {
                id: slotId,
                name: this.getAttribute('data-slot-name'),
                start: this.getAttribute('data-start'),
                end: this.getAttribute('data-end'),
                price: slotPrice !== '' ? parseFloat(slotPrice) : null
            };

            const confirmBtn = document.getElementById('confirmSlotBtn');
            if (confirmBtn) confirmBtn.disabled = false;
        });
    });
}

// Wire up confirm button (once, at DOMContentLoaded)
document.addEventListener('DOMContentLoaded', function() {
    const confirmBtn = document.getElementById('confirmSlotBtn');
    if (!confirmBtn) return;

    confirmBtn.addEventListener('click', function() {
        if (!_selectedSlot || !_pendingHall) return;

        const modal = bootstrap.Modal.getInstance(document.getElementById('timeSlotModal'));
        if (modal) modal.hide();

        // Use slot price override if present, else hall base price
        const effectivePrice = (_selectedSlot.price !== null) ? _selectedSlot.price : _pendingHall.basePrice;

        // Update the summary bar with selected slot info
        const slotDisplay = document.getElementById('selectedSlotDisplay');
        if (slotDisplay) {
            slotDisplay.textContent = ' | \u23F0 ' + _selectedSlot.name + ' (' + _selectedSlot.start.substring(0,5) + ' – ' + _selectedSlot.end.substring(0,5) + ')';
            slotDisplay.style.display = '';
        }

        selectHall(
            _pendingHall.hallId,
            _pendingHall.hallName,
            _pendingHall.venueName,
            effectivePrice,
            _pendingHall.capacity,
            _selectedSlot.id
        );
    });
});

// Ensure the 360° pano viewer modal exists in the DOM (inject once)
function ensurePanoModal() {
    if (!document.getElementById('panoViewerModal')) {
        const modalHtml = `
        <div class="modal fade" id="panoViewerModal" tabindex="-1" aria-labelledby="panoViewerModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-xl modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="panoViewerModalLabel">
                            <i class="fas fa-street-view text-primary"></i> <span id="panoViewerHallName"></span> — 360° View
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body p-0">
                        <div id="panoViewerContainer" style="width:100%;height:480px;"></div>
                    </div>
                </div>
            </div>
        </div>`;
        document.body.insertAdjacentHTML('beforeend', modalHtml);

        // Destroy Pannellum viewer when modal closes to free resources
        document.getElementById('panoViewerModal').addEventListener('hidden.bs.modal', function() {
            if (window._panoViewerInstance) {
                window._panoViewerInstance.destroy();
                window._panoViewerInstance = null;
            }
        });
    }
}

// Render a plain-image fallback inside the pano container
function showPanoFallback(containerId, panoUrl) {
    var container = document.getElementById(containerId);
    if (!container) return;
    container.style.cssText = 'width:100%;height:480px;background:#000;overflow:hidden;display:flex;align-items:center;justify-content:center;';
    container.innerHTML = '';
    var img = document.createElement('img');
    img.src = panoUrl;
    img.alt = '360\u00b0 panoramic photo';
    img.style.cssText = 'max-width:100%;max-height:100%;object-fit:contain;';
    img.onerror = function () {
        img.style.display = 'none';
        var msg = document.createElement('div');
        msg.style.cssText = 'color:#fff;text-align:center;';
        var icon = document.createElement('i');
        icon.className = 'fas fa-image fa-3x';
        icon.style.cssText = 'opacity:.5;display:block;margin-bottom:8px;';
        var text = document.createTextNode('Image could not be loaded.');
        msg.appendChild(icon);
        msg.appendChild(text);
        container.appendChild(msg);
    };
    container.appendChild(img);
}

// Open the 360° panoramic viewer modal for a hall
function openPanoViewer(panoUrl, hallName) {
    ensurePanoModal();
    const modalEl = document.getElementById('panoViewerModal');
    if (!modalEl) return;

    // Set hall name in modal title
    const nameEl = document.getElementById('panoViewerHallName');
    if (nameEl) nameEl.textContent = hallName;

    // Show the modal
    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    modal.show();

    // Initialise Pannellum (or fallback) after the modal is fully visible
    modalEl.addEventListener('shown.bs.modal', function initViewer() {
        // Destroy any existing instance first
        if (window._panoViewerInstance) {
            window._panoViewerInstance.destroy();
            window._panoViewerInstance = null;
        }

        const container = document.getElementById('panoViewerContainer');
        if (container) container.innerHTML = '';

        if (typeof pannellum === 'undefined') {
            showPanoFallback('panoViewerContainer', panoUrl);
            return;
        }

        try {
            window._panoViewerInstance = pannellum.viewer('panoViewerContainer', {
                type: 'equirectangular',
                panorama: panoUrl,
                autoLoad: true,
                autoRotate: -2,
                autoRotateInactivityDelay: 3000,
                showControls: true,
                showZoomCtrl: true,
                showFullscreenCtrl: true,
                compass: false,
                keyboardZoom: false
            });

            window._panoViewerInstance.on('error', function() {
                if (window._panoViewerInstance) {
                    window._panoViewerInstance.destroy();
                    window._panoViewerInstance = null;
                }
                showPanoFallback('panoViewerContainer', panoUrl);
            });
        } catch (e) {
            window._panoViewerInstance = null;
            console.error('Pannellum initialization failed:', e);
            showPanoFallback('panoViewerContainer', panoUrl);
        }
    }, { once: true });
}

// ── Custom / Own Venue entry ────────────────────────────────────────────────

document.addEventListener('DOMContentLoaded', function () {
    const btn = document.getElementById('useCustomVenueBtn');
    if (!btn) return;

    btn.addEventListener('click', function () {
        const venueNameInput = document.getElementById('customVenueName');
        const hallNameInput  = document.getElementById('customHallName');
        const errorEl        = document.getElementById('customVenueNameError');

        const venueName = venueNameInput ? venueNameInput.value.trim() : '';
        const hallName  = hallNameInput  ? hallNameInput.value.trim()  : '';

        // Validate required field
        if (!venueName) {
            if (venueNameInput) venueNameInput.classList.add('is-invalid');
            return;
        }
        if (venueNameInput) venueNameInput.classList.remove('is-invalid');

        showLoading();

        fetch(baseUrl + '/api/set-custom-venue.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ venue_name: venueName, hall_name: hallName })
        })
        .then(response => response.json())
        .then(data => {
            hideLoading();
            if (data.success) {
                window.location.href = baseUrl + '/booking-step3.php';
            } else {
                showError(data.message || 'Failed to save venue. Please try again.');
            }
        })
        .catch(error => {
            hideLoading();
            if (typeof logError === 'function') {
                logError('Custom venue save failed', error);
            }
            showError('An error occurred. Please try again.');
        });
    });
});
