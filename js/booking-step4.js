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
