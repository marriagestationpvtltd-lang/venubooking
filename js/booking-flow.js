/**
 * Booking Flow JavaScript
 */

// Fields saved to localStorage draft for the step-1 booking form
var BOOKING_DRAFT_FIELDS = ['city_id', 'shift', 'event_date', 'guests', 'event_type'];

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
    
    // Restore any previously saved draft before calendar initialization
    // so that the Nepali date picker can pick up the restored date value
    restoreBookingFormDraft();
    
    // Initialize Nepali calendar functionality
    initNepaliCalendar();
    
    const bookingForm = document.getElementById('bookingForm');
    
    if (bookingForm) {
        // Save draft whenever any booking field changes
        BOOKING_DRAFT_FIELDS.forEach(function(id) {
            var el = document.getElementById(id);
            if (el) {
                el.addEventListener('change', saveBookingFormDraft);
                el.addEventListener('input', saveBookingFormDraft);
            }
        });

        // Set minimum date for event_date using Nepal timezone
        const eventDateInput = document.getElementById('event_date');
        if (eventDateInput) {
            // Check if nepaliDateUtils is available for timezone handling
            if (typeof window.nepaliDateUtils !== 'undefined' && window.nepaliDateUtils.getTodayInNepal) {
                const todayInNepal = window.nepaliDateUtils.getTodayInNepal();
                const tomorrow = new Date(Date.UTC(todayInNepal.year, todayInNepal.month - 1, todayInNepal.day + 1));
                const minDate = tomorrow.toISOString().split('T')[0];
                eventDateInput.setAttribute('min', minDate);
            } else {
                // Fallback to client time if utils not available
                const tomorrow = new Date();
                tomorrow.setDate(tomorrow.getDate() + 1);
                const minDate = tomorrow.toISOString().split('T')[0];
                eventDateInput.setAttribute('min', minDate);
            }
        }
        
        // Helper: mark a field as invalid (shows its inline error message)
        function markFieldInvalid(el) {
            el.classList.add('is-invalid');
            el.classList.remove('is-valid');
        }

        // Helper: mark a field as valid (hides its inline error message)
        function markFieldValid(el) {
            el.classList.remove('is-invalid');
            el.classList.add('is-valid');
        }

        // Real-time error clearing: update validation state when the user
        // interacts with a field.
        function addRealTimeClear(el, eventType) {
            el.addEventListener(eventType, function() {
                if (this.value) {
                    markFieldValid(this);
                } else {
                    // Field was cleared — remove all validation styling
                    this.classList.remove('is-invalid', 'is-valid');
                }
            });
        }

        const cityEl      = document.getElementById('city_id');
        const shiftEl     = document.getElementById('shift');
        const startTimeEl = document.getElementById('start_time');
        const endTimeEl   = document.getElementById('end_time');
        const eventDateEl = document.getElementById('event_date');
        const guestsInput = document.getElementById('guests');
        const eventTypeEl = document.getElementById('event_type');

        // Attach real-time clearing listeners
        if (cityEl)      addRealTimeClear(cityEl,      'change');
        if (shiftEl)     addRealTimeClear(shiftEl,     'change');
        if (startTimeEl) addRealTimeClear(startTimeEl, 'change');
        if (endTimeEl)   addRealTimeClear(endTimeEl,   'change');
        if (eventDateEl) addRealTimeClear(eventDateEl, 'change');
        if (eventTypeEl) addRealTimeClear(eventTypeEl, 'change');
        if (guestsInput) {
            guestsInput.addEventListener('input', function() {
                const value = parseInt(this.value);
                if (!this.value) {
                    // Field cleared — remove all validation styling
                    this.classList.remove('is-invalid', 'is-valid');
                } else if (value && value >= 10) {
                    markFieldValid(this);
                } else {
                    markFieldInvalid(this);
                }
            });
        }

        // Form validation on submit — mark each field individually so that
        // filled fields do NOT show an error and empty/invalid ones do.
        bookingForm.addEventListener('submit', function(event) {
            let isValid = true;

            // City
            if (cityEl) {
                if (!cityEl.value) { markFieldInvalid(cityEl); isValid = false; }
                else { markFieldValid(cityEl); }
            }

            // Shift
            if (shiftEl) {
                if (!shiftEl.value) { markFieldInvalid(shiftEl); isValid = false; }
                else { markFieldValid(shiftEl); }
            }

            // Start time
            if (startTimeEl) {
                if (!startTimeEl.value) { markFieldInvalid(startTimeEl); isValid = false; }
                else { markFieldValid(startTimeEl); }
            }

            // End time
            if (endTimeEl) {
                if (!endTimeEl.value) { markFieldInvalid(endTimeEl); isValid = false; }
                else { markFieldValid(endTimeEl); }
            }

            // Event date
            if (eventDateEl) {
                const eventDate = eventDateEl.value;
                if (!eventDate) {
                    markFieldInvalid(eventDateEl); isValid = false;
                } else {
                    const selectedDate = new Date(eventDate);

                    // Use Nepal timezone for validation
                    let todayInNepal;
                    if (typeof window.nepaliDateUtils !== 'undefined' && window.nepaliDateUtils.getTodayInNepal) {
                        const nepalToday = window.nepaliDateUtils.getTodayInNepal();
                        todayInNepal = new Date(Date.UTC(nepalToday.year, nepalToday.month - 1, nepalToday.day));
                    } else {
                        // Fallback to client time
                        todayInNepal = new Date();
                        todayInNepal.setHours(0, 0, 0, 0);
                    }

                    if (selectedDate <= todayInNepal) {
                        markFieldInvalid(eventDateEl); isValid = false;
                    } else {
                        markFieldValid(eventDateEl);
                    }
                }
            }

            // Guests
            if (guestsInput) {
                const guests = parseInt(guestsInput.value);
                if (!guests || guests < 10) { markFieldInvalid(guestsInput); isValid = false; }
                else { markFieldValid(guestsInput); }
            }

            // Event type
            if (eventTypeEl) {
                if (!eventTypeEl.value) { markFieldInvalid(eventTypeEl); isValid = false; }
                else { markFieldValid(eventTypeEl); }
            }

            if (!isValid) {
                event.preventDefault();
                return false;
            }
        });
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

// localStorage draft helpers - persist booking form data across browser sessions
function saveBookingFormDraft() {
    var draft = {};
    BOOKING_DRAFT_FIELDS.forEach(function(id) {
        var el = document.getElementById(id);
        if (el) draft[id] = el.value;
    });
    try {
        localStorage.setItem('bookingDraft', JSON.stringify(draft));
    } catch (e) {
        // localStorage not available
    }
}

function restoreBookingFormDraft() {
    try {
        var raw = localStorage.getItem('bookingDraft');
        if (!raw) return;
        var draft = JSON.parse(raw);
        BOOKING_DRAFT_FIELDS.forEach(function(id) {
            var el = document.getElementById(id);
            if (el && draft[id]) el.value = draft[id];
        });
    } catch (e) {
        // localStorage not available or parse error
    }
}

function clearBookingDraft() {
    try {
        localStorage.removeItem('bookingDraft');
    } catch (e) {
        // localStorage not available
    }
}

// Update total cost display
function updateTotalCost(total) {
    const totalCostElement = document.getElementById('totalCost');
    if (totalCostElement) {
        totalCostElement.textContent = formatCurrency(total);
    }
}

// Nepali Date Picker functionality
// This provides accurate BS/AD date conversion using the nepali-date-picker.js library

// Initialize Nepali calendar toggle
function initNepaliCalendar() {
    const eventDateInput = document.getElementById('event_date');
    const nepaliDateDisplay = document.getElementById('nepaliDateDisplay');
    const toggleCalendar = document.getElementById('toggleCalendar');
    const calendarTypeLabel = document.getElementById('calendarType');
    
    if (!eventDateInput || !nepaliDateDisplay || !toggleCalendar) return;
    
    // Check if nepali date utils are available
    if (typeof window.nepaliDateUtils === 'undefined') {
        console.warn('Nepali date picker library not loaded');
        return;
    }
    
    let isNepaliMode = true; // Start with Nepali calendar as default
    let nepaliPicker = null;
    let standardDateInput = eventDateInput;
    
    // Update Nepali date display when English date changes
    function updateNepaliDisplay() {
        if (eventDateInput.value) {
            try {
                // Parse YYYY-MM-DD as local date components to avoid UTC-midnight timezone issues.
                // Using a regex match ensures the value is valid before passing to adToBS.
                const dateMatch = eventDateInput.value.match(/^(\d{4})-(\d{2})-(\d{2})$/);
                if (dateMatch) {
                    const bs = window.nepaliDateUtils.adToBS(
                        parseInt(dateMatch[1], 10),
                        parseInt(dateMatch[2], 10),
                        parseInt(dateMatch[3], 10)
                    );
                    if (bs) {
                        const bsDateStr = window.nepaliDateUtils.formatBSDate(bs.year, bs.month, bs.day);
                        nepaliDateDisplay.textContent = bsDateStr + ' (BS)';
                        nepaliDateDisplay.classList.add('active');
                    }
                }
            } catch (error) {
                console.error('Error converting date:', error);
            }
        } else {
            nepaliDateDisplay.textContent = '';
            nepaliDateDisplay.classList.remove('active');
        }
    }
    
    // Initialize with Nepali calendar as default
    function initializeNepaliAsDefault() {
        // Change button label to show current mode (BS)
        // This helps users understand which calendar they are currently using
        // Previously showed "AD" which was confusing as it suggested the target, not current state
        calendarTypeLabel.textContent = 'BS';
        
        // Remove type="date" to prevent browser date picker
        eventDateInput.removeAttribute('type');
        eventDateInput.setAttribute('type', 'text');
        eventDateInput.setAttribute('readonly', 'readonly');
        eventDateInput.setAttribute('placeholder', 'Select Nepali Date (BS)');
        
        // Initialize Nepali picker
        nepaliPicker = new window.NepaliDatePicker(eventDateInput, {
            closeOnSelect: true, // Close calendar after date is selected (like English calendar)
            onChange: function(adDate, bsDate) {
                updateNepaliDisplay();
            },
            onMonthChange: function(bsYear, bsMonth) {
                loadBookingCountsForMonth(bsYear, bsMonth);
            }
        });

        // Load booking counts for the currently displayed month
        if (nepaliPicker.currentBSDate) {
            loadBookingCountsForMonth(nepaliPicker.currentBSDate.year, nepaliPicker.currentBSDate.month);
        }
    }

    /**
     * Fetch booking counts for a Nepali (BS) month and update the date picker.
     * Converts the BS month boundaries to AD dates, calls the API, then
     * passes the result to the picker so it can render count badges.
     */
    function loadBookingCountsForMonth(bsYear, bsMonth) {
        if (typeof window.nepaliDateUtils === 'undefined') return;

        var utils = window.nepaliDateUtils;
        var daysInMonth = utils.getDaysInBSMonth(bsYear, bsMonth);
        var firstDay = utils.bsToAD(bsYear, bsMonth, 1);
        var lastDay  = utils.bsToAD(bsYear, bsMonth, daysInMonth);

        if (!firstDay || !lastDay) return;

        var startDate = firstDay.year + '-' +
            String(firstDay.month).padStart(2, '0') + '-' +
            String(firstDay.day).padStart(2, '0');
        var endDate = lastDay.year + '-' +
            String(lastDay.month).padStart(2, '0') + '-' +
            String(lastDay.day).padStart(2, '0');

        var apiUrl = (typeof baseUrl !== 'undefined' ? baseUrl : '') +
            '/api/get-booking-counts.php?start=' + startDate + '&end=' + endDate;

        fetch(apiUrl)
            .then(function(response) { return response.json(); })
            .then(function(data) {
                if (data.success && nepaliPicker) {
                    nepaliPicker.setBookingCounts(data.counts);
                }
            })
            .catch(function(err) {
                // Non-critical: silently ignore network errors
            });
    }
    
    // Initial display update and setup
    updateNepaliDisplay();
    
    // Initialize Nepali calendar as default
    initializeNepaliAsDefault();
    
    // Listen for date changes
    eventDateInput.addEventListener('change', updateNepaliDisplay);
    
    // Toggle between AD and BS calendar
    toggleCalendar.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        isNepaliMode = !isNepaliMode;
        
        if (isNepaliMode) {
            // Switch to Nepali calendar
            calendarTypeLabel.textContent = 'BS'; // Show current mode
            
            // Remove type="date" to prevent browser date picker
            eventDateInput.removeAttribute('type');
            eventDateInput.setAttribute('type', 'text');
            eventDateInput.setAttribute('readonly', 'readonly');
            eventDateInput.setAttribute('placeholder', 'Select Nepali Date (BS)');
            
            // Initialize Nepali picker
            if (!nepaliPicker) {
                nepaliPicker = new window.NepaliDatePicker(eventDateInput, {
                    closeOnSelect: true, // Close calendar after date is selected (like English calendar)
                    onChange: function(adDate, bsDate) {
                        updateNepaliDisplay();
                    },
                    onMonthChange: function(bsYear, bsMonth) {
                        loadBookingCountsForMonth(bsYear, bsMonth);
                    }
                });

                // Load counts for the initial month shown
                if (nepaliPicker.currentBSDate) {
                    loadBookingCountsForMonth(nepaliPicker.currentBSDate.year, nepaliPicker.currentBSDate.month);
                }
            }
            
            // Show current BS date in display
            if (eventDateInput.value) {
                // Parse YYYY-MM-DD as local date components to avoid UTC-midnight timezone issues.
                const dateMatch = eventDateInput.value.match(/^(\d{4})-(\d{2})-(\d{2})$/);
                if (dateMatch) {
                    const bs = window.nepaliDateUtils.adToBS(
                        parseInt(dateMatch[1], 10),
                        parseInt(dateMatch[2], 10),
                        parseInt(dateMatch[3], 10)
                    );
                    if (bs) {
                        eventDateInput.setAttribute('data-bs-date', 
                            `${bs.year}-${String(bs.month).padStart(2, '0')}-${String(bs.day).padStart(2, '0')}`);
                    }
                }
            }
            
            if (typeof showSuccess === 'function') {
                showSuccess('Switched to Nepali (BS) Calendar');
            }
        } else {
            // Switch to English calendar
            calendarTypeLabel.textContent = 'AD'; // Show current mode
            
            // Restore standard date input
            eventDateInput.removeAttribute('readonly');
            eventDateInput.setAttribute('type', 'date');
            eventDateInput.setAttribute('placeholder', '');
            
            // Destroy Nepali picker
            if (nepaliPicker) {
                nepaliPicker.destroy();
                nepaliPicker = null;
            }
            
            if (typeof showSuccess === 'function') {
                showSuccess('Switched to English (AD) Calendar');
            }
        }
    });
}
