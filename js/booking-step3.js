/**
 * Booking Step 3 - Menu Selection
 */

document.addEventListener('DOMContentLoaded', function() {
    // Validate session on page load
    if (typeof hallPrice === 'undefined' || typeof guestsCount === 'undefined') {
        // Missing required session data, redirect to appropriate step
        window.location.href = baseUrl + '/booking-step2.php';
        return;
    }
    
    // Handle back button navigation
    window.addEventListener('popstate', function(event) {
        // Allow natural back navigation
        if (event.state && event.state.page) {
            // Browser will handle the navigation
            return;
        }
    });
    
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

    // Make entire menu card clickable (not just the checkbox)
    document.querySelectorAll('.menu-card').forEach(function(card) {
        card.style.cursor = 'pointer';
        card.addEventListener('click', function(e) {
            // Let checkbox, links, and buttons handle their own events
            if (e.target.closest('a, button, .menu-checkbox')) {
                return;
            }
            const checkbox = card.querySelector('.menu-checkbox');
            if (checkbox) {
                checkbox.click();
            }
        });
    });
    
    // Initial calculation
    calculateMenuTotal();

    // Menu search filter
    const searchInput = document.getElementById('menuSearchInput');
    const clearBtn    = document.getElementById('menuSearchClear');
    const noResults   = document.getElementById('menuSearchNoResults');

    if (searchInput) {
        function filterMenus() {
            const term = searchInput.value.trim().toLowerCase();
            const cards = Array.from(document.querySelectorAll('#menusContainer [data-menu-name]'));
            let visibleCount = 0;

            cards.forEach(function(card) {
                const name = (card.getAttribute('data-menu-name') || '').toLowerCase();
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

        searchInput.addEventListener('input', filterMenus);

        if (clearBtn) {
            clearBtn.addEventListener('click', function() {
                searchInput.value = '';
                filterMenus();
                searchInput.focus();
            });
        }
    }
});

// Update menu card selection state (single-selection: uncheck all others)
function updateMenuSelection(checkbox) {
    const menuCard = checkbox.closest('.menu-card');

    if (checkbox.checked) {
        // Enforce single selection: uncheck every other menu checkbox
        document.querySelectorAll('.menu-checkbox').forEach(function(cb) {
            if (cb !== checkbox) {
                cb.checked = false;
                const otherCard = cb.closest('.menu-card');
                if (otherCard) otherCard.classList.remove('selected');
            }
        });
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
        const menuId = parseInt(checkbox.value);
        const pricePerPerson = parseFloat(checkbox.dataset.price);
        // Per-item pricing menus: items have their own prices so base price_per_person is not charged
        const isPerItem = window.menuPerItemPricingIds && window.menuPerItemPricingIds.has(menuId);
        if (!isPerItem) {
            menuTotal += pricePerPerson * guestsCount;
        }
    });

    // Include extra charges from custom menu item selections (e.g. premium items with extra_charge)
    const extraCharges = (typeof window.menuExtraChargesTotal !== 'undefined') ? window.menuExtraChargesTotal : 0;

    // Show/hide the extra charges line in the summary bar
    const extraLine = document.getElementById('menuExtraLine');
    const extraAmount = document.getElementById('menuExtraAmount');
    if (extraLine && extraAmount) {
        if (extraCharges > 0) {
            extraLine.style.display = 'block';
            extraAmount.textContent = formatCurrency(extraCharges * guestsCount);
        } else {
            extraLine.style.display = 'none';
        }
    }

    const rate = (typeof taxRate !== 'undefined') ? taxRate : 0; // taxRate is always PHP-injected; 0 is a safe fallback to avoid breaking the UI
    const subtotal = hallPrice + menuTotal + extraCharges * guestsCount;
    const total = subtotal * (1 + rate / 100);
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
