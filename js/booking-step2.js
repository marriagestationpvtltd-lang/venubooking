/**
 * Booking Step 2 - Venue and Hall Selection
 */

let currentVenueId = null;

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
            console.error('Error:', error);
            showError('An error occurred while loading halls');
        });
}

// Display halls
function displayHalls(halls, venueName) {
    const venuesContainer = document.getElementById('venuesContainer');
    const hallsSection = document.getElementById('hallsSection');
    const hallsContainer = document.getElementById('hallsContainer');
    const venueNameElement = document.getElementById('venueName');
    
    // Hide venues, show halls section
    if (venuesContainer) venuesContainer.style.display = 'none';
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
        const hallCard = `
            <div class="col-md-6 col-lg-4">
                <div class="hall-card card h-100">
                    ${hall.image ? `<img src="${baseUrl}/uploads/${escapeHtml(hall.image)}" class="card-img-top hall-image" alt="${escapeHtml(hall.name)}">` : ''}
                    <div class="card-body">
                        <h5 class="card-title">${escapeHtml(hall.name)}</h5>
                        <div class="mb-3">
                            <span class="capacity-badge">
                                <i class="fas fa-users"></i> ${escapeHtml(hall.capacity)} pax
                            </span>
                            <span class="badge bg-info ms-2">${escapeHtml(hall.indoor_outdoor)}</span>
                        </div>
                        <p class="card-text text-muted">${escapeHtml(hall.description || '')}</p>
                        ${hall.features ? `<p class="small"><strong>Features:</strong> ${escapeHtml(hall.features)}</p>` : ''}
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <span class="text-muted">Base Price:</span>
                            <h5 class="text-success mb-0">${formatCurrency(hall.base_price)}</h5>
                        </div>
                        ${hall.available ? 
                            `<button class="btn btn-success w-100 select-hall-btn" 
                                     data-hall-id="${parseInt(hall.id)}" 
                                     data-hall-name="${escapeHtml(hall.name)}" 
                                     data-venue-name="${escapeHtml(venueName)}" 
                                     data-base-price="${parseFloat(hall.base_price)}" 
                                     data-capacity="${parseInt(hall.capacity)}">
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
            const hallId = parseInt(this.getAttribute('data-hall-id'));
            const hallName = this.getAttribute('data-hall-name');
            const venueName = this.getAttribute('data-venue-name');
            const basePrice = parseFloat(this.getAttribute('data-base-price'));
            const capacity = parseInt(this.getAttribute('data-capacity'));
            selectHall(hallId, hallName, venueName, basePrice, capacity);
        });
    });
    
    // Scroll to halls section
    hallsSection.scrollIntoView({ behavior: 'smooth' });
}

// Show venues (back button)
function showVenues() {
    const venuesContainer = document.getElementById('venuesContainer');
    const hallsSection = document.getElementById('hallsSection');
    
    if (venuesContainer) venuesContainer.style.display = 'flex';
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
        console.error('Error:', error);
        showError('An error occurred while selecting the hall');
    });
}
