/**
 * Booking Step 4 - Additional Services Selection
 */

document.addEventListener('DOMContentLoaded', function() {
    // Validate session on page load
    if (typeof baseTotal === 'undefined') {
        // Missing required session data, redirect to appropriate step
        window.location.href = baseUrl + '/booking-step3.php';
        return;
    }
    
    // Handle back button navigation
    window.addEventListener('popstate', function(event) {
        // Allow natural back navigation
        if (event.state && event.state.page) {
            return;
        }
    });
    
    const serviceCheckboxes = document.querySelectorAll('.service-checkbox');
    
    if (serviceCheckboxes) {
        serviceCheckboxes.forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                calculateServicesTotal();
            });
        });
    }

    // Service search filter
    const searchInput = document.getElementById('serviceSearchInput');
    const clearBtn    = document.getElementById('serviceSearchClear');
    const noResults   = document.getElementById('serviceSearchNoResults');

    if (searchInput) {
        let debounceTimer = null;

        function filterServices() {
            const term = searchInput.value.trim().toLowerCase();

            // Desktop service cards: col-md-6[data-service-name] inside .d-none.d-md-block
            const desktopCards = Array.from(document.querySelectorAll('.d-none.d-md-block [data-service-name]'));
            // Mobile service cards: .service-card[data-service-name] inside .d-md-none
            const mobileCards  = Array.from(document.querySelectorAll('.d-md-none [data-service-name]'));

            desktopCards.forEach(function(card) {
                const name = (card.getAttribute('data-service-name') || '').toLowerCase();
                card.style.display = name.includes(term) ? '' : 'none';
            });

            mobileCards.forEach(function(card) {
                const name = (card.getAttribute('data-service-name') || '').toLowerCase();
                card.style.display = name.includes(term) ? '' : 'none';
            });

            // Show/hide each category section based on whether any of its services are visible
            const categorySections = Array.from(document.querySelectorAll('.service-category-section'));
            let totalVisible = 0;

            categorySections.forEach(function(section) {
                const visibleDesktop = section.querySelectorAll('.d-none.d-md-block [data-service-name]');
                const anyDesktop = Array.from(visibleDesktop).some(c => c.style.display !== 'none');

                const visibleMobile = section.querySelectorAll('.d-md-none [data-service-name]');
                const anyMobile = Array.from(visibleMobile).some(c => c.style.display !== 'none');

                const anyVisible = anyDesktop || anyMobile;
                section.style.display = anyVisible ? '' : 'none';
                if (anyVisible) totalVisible++;
            });

            if (noResults) {
                noResults.style.display = totalVisible === 0 ? 'block' : 'none';
            }
            if (clearBtn) {
                clearBtn.style.display = term.length > 0 ? 'inline-block' : 'none';
            }
        }

        searchInput.addEventListener('input', function() {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(filterServices, 200);
        });

        if (clearBtn) {
            clearBtn.addEventListener('click', function() {
                searchInput.value = '';
                filterServices();
                searchInput.focus();
            });
        }
    }
});

// Calculate total with selected services
function calculateServicesTotal() {
    const checkboxes = document.querySelectorAll('.service-checkbox:checked');
    let servicesTotal = 0;
    
    checkboxes.forEach(checkbox => {
        const price = parseFloat(checkbox.dataset.price);
        servicesTotal += price;
    });
    
    const total = baseTotal + servicesTotal;
    updateTotalCost(total);
}
