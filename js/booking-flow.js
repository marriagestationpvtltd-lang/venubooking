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
    
    // Initialize Nepali calendar functionality
    initNepaliCalendar();
    
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

// Nepali Date Picker functionality (Basic implementation)
// This provides a simple BS/AD date conversion
// NOTE: This is an APPROXIMATE conversion for display purposes only
// For accurate Nepali date conversion, integrate a dedicated library like:
// - nepali-date-picker (JavaScript)
// - nepali-date (npm package)
// The current implementation provides a rough estimate to give users
// a sense of the Nepali date, but should not be used for precise calculations
const nepaliMonths = [
    'Baisakh', 'Jestha', 'Ashadh', 'Shrawan', 'Bhadra', 'Ashwin',
    'Kartik', 'Mangsir', 'Poush', 'Magh', 'Falgun', 'Chaitra'
];

// Simple BS to AD conversion (approximate - for display purposes ONLY)
// This is a simplified algorithm and does NOT account for:
// - Variable month lengths in BS calendar (29-32 days)
// - Leap years in BS calendar
// - Precise date boundaries between AD and BS
function convertADtoBS(adDate) {
    const ad = new Date(adDate);
    const year = ad.getFullYear();
    const month = ad.getMonth() + 1;
    const day = ad.getDate();
    
    // Approximate conversion (add 56-57 years for BS)
    let bsYear = year + 56;
    let bsMonth = month + 8;
    let bsDay = day + 15;
    
    // Adjust for overflow
    if (bsMonth > 12) {
        bsMonth -= 12;
        bsYear += 1;
    }
    
    if (bsDay > 30) {
        bsDay -= 30;
        bsMonth += 1;
        if (bsMonth > 12) {
            bsMonth -= 12;
            bsYear += 1;
        }
    }
    
    return {
        year: bsYear,
        month: bsMonth,
        day: bsDay,
        monthName: nepaliMonths[bsMonth - 1]
    };
}

function displayNepaliDate(adDate) {
    if (!adDate) return '';
    
    const bs = convertADtoBS(adDate);
    return `${bs.day} ${bs.monthName} ${bs.year} BS`;
}

// Initialize Nepali calendar toggle
function initNepaliCalendar() {
    const eventDateInput = document.getElementById('event_date');
    const nepaliDateDisplay = document.getElementById('nepaliDateDisplay');
    const toggleCalendar = document.getElementById('toggleCalendar');
    
    if (!eventDateInput || !nepaliDateDisplay || !toggleCalendar) return;
    
    // Update Nepali date display when English date changes
    eventDateInput.addEventListener('change', function() {
        if (this.value) {
            const nepaliDate = displayNepaliDate(this.value);
            nepaliDateDisplay.textContent = nepaliDate;
        }
    });
    
    // Toggle calendar button (for future enhancement with full Nepali picker)
    toggleCalendar.addEventListener('click', function() {
        // For now, just show info message
        if (typeof showSuccess === 'function') {
            showSuccess('Nepali calendar picker will be available soon! Currently showing approximate BS date.');
        } else {
            alert('Nepali calendar picker will be available soon! Currently showing approximate BS date.');
        }
    });
    
    // Show initial Nepali date if date is already set
    if (eventDateInput.value) {
        const nepaliDate = displayNepaliDate(eventDateInput.value);
        nepaliDateDisplay.textContent = nepaliDate;
    }
}
