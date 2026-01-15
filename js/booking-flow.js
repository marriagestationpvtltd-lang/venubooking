/**
 * Booking Flow JavaScript
 */

// Handle browser back button navigation
function handleBrowserBackButton() {
    // Save current page state to history
    if (window.history && window.history.pushState) {
        // Add state to indicate booking flow
        const currentPath = window.location.pathname;
        const state = { page: currentPath, timestamp: Date.now() };
        
        // Only push state if it's a booking page
        if (currentPath.includes('booking-step') || currentPath.includes('confirmation')) {
            window.history.replaceState(state, '', currentPath);
        }
    }
    
    // Listen for back button navigation
    window.addEventListener('popstate', function(event) {
        // Check if we have session data
        const hasBookingData = sessionStorage.getItem('bookingData');
        
        // If navigating back in booking flow, redirect to appropriate step
        if (window.location.pathname.includes('booking-step') && !hasBookingData) {
            // Session expired or lost, redirect to start
            window.location.href = baseUrl + '/index.php';
        }
    });
    
    // Prevent navigation away without warning on booking pages
    if (window.location.pathname.includes('booking-step')) {
        window.addEventListener('beforeunload', function(e) {
            // Only show warning if we have unsaved data
            const hasBookingData = sessionStorage.getItem('bookingData');
            if (hasBookingData) {
                e.preventDefault();
                e.returnValue = 'You have an incomplete booking. Are you sure you want to leave?';
                return e.returnValue;
            }
        });
    }
}

// Validate booking form on index page
document.addEventListener('DOMContentLoaded', function() {
    // Initialize back button handling
    handleBrowserBackButton();
    
    const bookingForm = document.getElementById('bookingForm');
    
    if (bookingForm) {
        // Set minimum date for event_date
        const eventDateInput = document.getElementById('event_date');
        if (eventDateInput) {
            const tomorrow = new Date();
            tomorrow.setDate(tomorrow.getDate() + 1);
            const minDate = tomorrow.toISOString().split('T')[0];
            eventDateInput.setAttribute('min', minDate);
        }
        
        // Form validation
        bookingForm.addEventListener('submit', function(event) {
            const shift = document.getElementById('shift').value;
            const eventDate = document.getElementById('event_date').value;
            const guests = parseInt(document.getElementById('guests').value);
            const eventType = document.getElementById('event_type').value;
            
            let errors = [];
            
            if (!shift) {
                errors.push('Please select a shift');
            }
            
            if (!eventDate) {
                errors.push('Please select an event date');
            } else {
                const selectedDate = new Date(eventDate);
                const today = new Date();
                today.setHours(0, 0, 0, 0);
                
                if (selectedDate <= today) {
                    errors.push('Event date must be in the future');
                }
            }
            
            if (!guests || guests < 10) {
                errors.push('Minimum 10 guests required');
            }
            
            if (!eventType) {
                errors.push('Please select an event type');
            }
            
            if (errors.length > 0) {
                event.preventDefault();
                showError(errors.join('\n'));
                return false;
            }
        });
        
        // Real-time validation
        const guestsInput = document.getElementById('guests');
        if (guestsInput) {
            guestsInput.addEventListener('input', function() {
                const value = parseInt(this.value);
                if (value && value < 10) {
                    this.classList.add('is-invalid');
                } else if (value) {
                    this.classList.remove('is-invalid');
                    this.classList.add('is-valid');
                }
            });
        }
    }
});

// Session storage helpers
function saveBookingData(data) {
    sessionStorage.setItem('bookingData', JSON.stringify(data));
}

function getBookingData() {
    const data = sessionStorage.getItem('bookingData');
    return data ? JSON.parse(data) : null;
}

function clearBookingData() {
    sessionStorage.removeItem('bookingData');
    sessionStorage.removeItem('selectedHall');
    sessionStorage.removeItem('selectedMenus');
    sessionStorage.removeItem('selectedServices');
}

// Update total cost display
function updateTotalCost(total) {
    const totalCostElement = document.getElementById('totalCost');
    if (totalCostElement) {
        totalCostElement.textContent = formatCurrency(total);
    }
}
