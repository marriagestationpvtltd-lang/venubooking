/**
 * Admin Booking Form - Nepali Calendar Support
 */

document.addEventListener('DOMContentLoaded', function() {
    // Initialize Nepali calendar for event_date field in admin
    const eventDateInput = document.getElementById('event_date');
    
    if (!eventDateInput) return;
    
    // Check if nepali date utils are available
    if (typeof window.nepaliDateUtils === 'undefined') {
        console.warn('Nepali date picker library not loaded');
        return;
    }
    
    // Add calendar toggle button next to date input
    const dateFieldGroup = eventDateInput.closest('.mb-3') || eventDateInput.parentElement;
    
    // Create toggle button if it doesn't exist
    let toggleButton = dateFieldGroup.querySelector('.calendar-toggle-btn');
    if (!toggleButton) {
        toggleButton = document.createElement('button');
        toggleButton.type = 'button';
        toggleButton.className = 'btn btn-sm btn-success calendar-toggle-btn mt-2';
        toggleButton.innerHTML = '<i class="fas fa-calendar"></i> Current: Nepali (BS) | Click to toggle';
        toggleButton.style.display = 'block';
        
        // Insert after the input
        if (eventDateInput.nextSibling) {
            dateFieldGroup.insertBefore(toggleButton, eventDateInput.nextSibling);
        } else {
            dateFieldGroup.appendChild(toggleButton);
        }
    }
    
    // Create display element for Nepali date
    let nepaliDisplay = dateFieldGroup.querySelector('.nepali-date-display-admin');
    if (!nepaliDisplay) {
        nepaliDisplay = document.createElement('small');
        nepaliDisplay.className = 'form-text text-muted nepali-date-display-admin';
        dateFieldGroup.appendChild(nepaliDisplay);
    }
    
    let isNepaliMode = true; // Start with Nepali calendar as default
    let nepaliPicker = null;
    
    // Update Nepali date display
    function updateNepaliDisplay() {
        if (eventDateInput.value) {
            try {
                const adDate = new Date(eventDateInput.value);
                if (!isNaN(adDate)) {
                    const bs = window.nepaliDateUtils.adToBS(
                        adDate.getFullYear(),
                        adDate.getMonth() + 1,
                        adDate.getDate()
                    );
                    if (bs) {
                        const bsDateStr = window.nepaliDateUtils.formatBSDate(bs.year, bs.month, bs.day);
                        nepaliDisplay.textContent = bsDateStr + ' (BS)';
                        nepaliDisplay.style.display = 'block';
                    }
                }
            } catch (error) {
                console.error('Error converting date:', error);
            }
        } else {
            nepaliDisplay.textContent = '';
            nepaliDisplay.style.display = 'none';
        }
    }
    
    // Initialize with Nepali calendar as default
    function initializeNepaliAsDefault() {
        // Change input to text and make readonly
        eventDateInput.setAttribute('type', 'text');
        eventDateInput.setAttribute('readonly', 'readonly');
        eventDateInput.setAttribute('placeholder', 'Select Nepali Date (BS)');
        
        // Initialize Nepali picker
        nepaliPicker = new window.NepaliDatePicker(eventDateInput, {
            closeOnSelect: true, // Close calendar after date is selected (like English calendar)
            onChange: function(adDate, bsDate) {
                updateNepaliDisplay();
            }
        });
    }
    
    // Initial display
    updateNepaliDisplay();
    
    // Initialize Nepali calendar as default
    initializeNepaliAsDefault();
    
    // Listen for date changes
    eventDateInput.addEventListener('change', updateNepaliDisplay);
    
    // Toggle calendar mode
    toggleButton.addEventListener('click', function() {
        isNepaliMode = !isNepaliMode;
        
        if (isNepaliMode) {
            // Switch to Nepali mode
            toggleButton.innerHTML = '<i class="fas fa-calendar"></i> Current: Nepali (BS) | Click to toggle';
            toggleButton.classList.remove('btn-outline-success');
            toggleButton.classList.add('btn-success');
            
            // Change input to text and make readonly
            eventDateInput.setAttribute('type', 'text');
            eventDateInput.setAttribute('readonly', 'readonly');
            eventDateInput.setAttribute('placeholder', 'Select Nepali Date (BS)');
            
            // Initialize Nepali picker
            if (!nepaliPicker) {
                nepaliPicker = new window.NepaliDatePicker(eventDateInput, {
                    closeOnSelect: true, // Close calendar after date is selected (like English calendar)
                    onChange: function(adDate, bsDate) {
                        updateNepaliDisplay();
                    }
                });
            }
            
            if (typeof showSuccess === 'function') {
                showSuccess('Switched to Nepali (BS) Calendar');
            } else {
                alert('Switched to Nepali (BS) Calendar');
            }
        } else {
            // Switch to English mode
            toggleButton.innerHTML = '<i class="fas fa-calendar-alt"></i> Current: English (AD) | Click to toggle';
            toggleButton.classList.remove('btn-success');
            toggleButton.classList.add('btn-outline-success');
            
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
            } else {
                alert('Switched to English (AD) Calendar');
            }
        }
    });
});
