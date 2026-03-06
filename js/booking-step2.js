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
    
    // Fetch halls for the venue
    const params = new URLSearchParams({
        venue_id: venueId,
        date: bookingData.event_date,
        shift: bookingData.shift,
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
                        </div>
                        <p class="card-text text-muted">${escapeHtml(hall.description || '')}</p>
                        ${hall.features ? `<p class="small"><strong>Features:</strong> ${escapeHtml(hall.features)}</p>` : ''}
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <span class="text-muted">Base Price:</span>
                            <h5 class="text-success mb-0">${formatCurrency(parseFloat(hall.base_price) || 0)}</h5>
                        </div>
                        ${hall.available ? 
                            `<button class="btn btn-success w-100 select-hall-btn" 
                                     data-hall-id="${parseInt(hall.id, 10) || 0}" 
                                     data-hall-name="${hall.name || ''}" 
                                     data-venue-name="${venueName || ''}" 
                                     data-base-price="${parseFloat(hall.base_price) || 0}" 
                                     data-capacity="${parseInt(hall.capacity, 10) || 0}">
                                <i class="fas fa-check"></i> Select This Hall
                            </button>` :
                            `<button class="btn btn-secondary w-100" disabled>
                                <i class="fas fa-times"></i> Not Available
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
            
            selectHall(hallId, hallName, venueName, basePrice, capacity);
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

// Select hall and proceed to next step
function selectHall(hallId, hallName, venueName, basePrice, capacity) {
    // Save selected hall to session
    const hallData = {
        id: hallId,
        name: hallName,
        venue_name: venueName,
        base_price: basePrice,
        capacity: capacity
    };
    
    // Show loading indicator
    showLoading();
    
    // Update session via AJAX or form submission
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
