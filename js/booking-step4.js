/**
 * Booking Step 4 - Additional Services Selection
 */

document.addEventListener('DOMContentLoaded', function() {
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
