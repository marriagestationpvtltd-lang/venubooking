/**
 * Booking Step 3 - Menu Selection
 */

document.addEventListener('DOMContentLoaded', function() {
    const menuForm = document.getElementById('menuForm');
    const menuCheckboxes = document.querySelectorAll('.menu-checkbox');
    
    if (menuCheckboxes) {
        menuCheckboxes.forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                updateMenuSelection(this);
                calculateMenuTotal();
            });
        });
    }
    
    // Initial calculation
    calculateMenuTotal();
});

// Update menu card selection state
function updateMenuSelection(checkbox) {
    const menuCard = checkbox.closest('.menu-card');
    
    if (checkbox.checked) {
        menuCard.classList.add('selected');
    } else {
        menuCard.classList.remove('selected');
    }
}

// Calculate total with selected menus
function calculateMenuTotal() {
    const checkboxes = document.querySelectorAll('.menu-checkbox:checked');
    let menuTotal = 0;
    
    checkboxes.forEach(checkbox => {
        const pricePerPerson = parseFloat(checkbox.dataset.price);
        menuTotal += pricePerPerson * guestsCount;
    });
    
    const total = hallPrice + menuTotal;
    updateTotalCost(total);
}

// Show menu customization modal
function showMenuCustomizationModal() {
    const modal = new bootstrap.Modal(document.getElementById('menuCustomizationModal'));
    modal.show();
}

// Design menu
function designMenu() {
    // Close modal
    const modal = bootstrap.Modal.getInstance(document.getElementById('menuCustomizationModal'));
    modal.hide();
    
    // Show info message
    showSuccess('Menu customization feature will be available soon!', () => {
        continueBooking();
    });
}

// Continue booking
function continueBooking() {
    const form = document.getElementById('menuForm');
    if (form) {
        form.submit();
    }
}
