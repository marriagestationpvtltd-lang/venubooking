/**
 * Booking Flow JavaScript
 */

// Validate booking form on index page
document.addEventListener('DOMContentLoaded', function() {
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
