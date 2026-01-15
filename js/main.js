/**
 * Main JavaScript Functions
 */

// Global settings loaded from API
let appSettings = {
    currency: 'NPR',
    tax_rate: 13
};

// Load settings from API
async function loadSettings() {
    try {
        const apiUrl = (typeof baseUrl !== 'undefined' ? baseUrl : '') + '/api/get-settings.php';
        const response = await fetch(apiUrl);
        const data = await response.json();
        if (data.success && data.settings) {
            appSettings = data.settings;
        }
    } catch (error) {
        console.error('Failed to load settings:', error);
        // Keep default values if loading fails
        // Log to server if logging endpoint exists
        if (typeof logError === 'function') {
            logError('Settings load failed', error);
        }
    }
}

// Format currency using dynamic settings
function formatCurrency(amount) {
    return appSettings.currency + ' ' + parseFloat(amount).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
}

// Client-side error logging (optional - can be implemented with server endpoint)
function logError(message, error) {
    // This is a placeholder for production error logging
    // In production, you can send errors to a logging endpoint:
    // fetch('/api/log-error.php', {
    //     method: 'POST',
    //     headers: { 'Content-Type': 'application/json' },
    //     body: JSON.stringify({ message, error: error?.toString() })
    // });
    
    // For now, just log to console in development
    if (typeof console !== 'undefined' && console.error) {
        console.error(message, error);
    }
}

// Show loading spinner
function showLoading() {
    Swal.fire({
        title: 'Please wait...',
        html: '<div class="loading-spinner"></div>',
        showConfirmButton: false,
        allowOutsideClick: false
    });
}

// Hide loading spinner
function hideLoading() {
    Swal.close();
}

// Show success message
function showSuccess(message, callback) {
    Swal.fire({
        icon: 'success',
        title: 'Success!',
        text: message,
        confirmButtonColor: '#4CAF50'
    }).then((result) => {
        if (callback && typeof callback === 'function') {
            callback();
        }
    });
}

// Show error message
function showError(message) {
    Swal.fire({
        icon: 'error',
        title: 'Error!',
        text: message,
        confirmButtonColor: '#4CAF50'
    });
}

// Show confirmation dialog
function showConfirm(title, message, callback) {
    Swal.fire({
        title: title,
        text: message,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#4CAF50',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Yes',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed && callback && typeof callback === 'function') {
            callback();
        }
    });
}

// Validate form
function validateForm(formId) {
    const form = document.getElementById(formId);
    if (!form) return false;
    
    let isValid = true;
    const inputs = form.querySelectorAll('[required]');
    
    inputs.forEach(input => {
        if (!input.value.trim()) {
            input.classList.add('is-invalid');
            isValid = false;
        } else {
            input.classList.remove('is-invalid');
            input.classList.add('is-valid');
        }
    });
    
    return isValid;
}

// Validate email
function validateEmail(email) {
    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return re.test(email);
}

// Validate phone
function validatePhone(phone) {
    const re = /^[+]?[\d\s()-]{10,}$/;
    return re.test(phone);
}

// Smooth scroll
function smoothScroll(target) {
    const element = document.querySelector(target);
    if (element) {
        element.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
}

// Initialize tooltips
document.addEventListener('DOMContentLoaded', function() {
    // Load settings first
    loadSettings();
    
    // Bootstrap tooltip initialization
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Form validation on submit
    const forms = document.querySelectorAll('form[data-validate="true"]');
    forms.forEach(form => {
        form.addEventListener('submit', function(event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        });
    });
    
    // Auto-hide alerts
    const alerts = document.querySelectorAll('.alert:not(.alert-permanent)');
    alerts.forEach(alert => {
        setTimeout(() => {
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        }, 5000);
    });
});

// Debounce function for search/filter
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// AJAX helper function
function ajax(url, method, data, successCallback, errorCallback) {
    fetch(url, {
        method: method,
        headers: {
            'Content-Type': 'application/json',
        },
        body: method !== 'GET' ? JSON.stringify(data) : undefined
    })
    .then(response => response.json())
    .then(data => {
        if (successCallback && typeof successCallback === 'function') {
            successCallback(data);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        if (errorCallback && typeof errorCallback === 'function') {
            errorCallback(error);
        } else {
            showError('An error occurred. Please try again.');
        }
        // Log to server if available
        if (typeof logError === 'function') {
            logError('AJAX request failed', error);
        }
    });
}

// Image preview
function previewImage(input, previewElementId) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const preview = document.getElementById(previewElementId);
            if (preview) {
                preview.src = e.target.result;
                preview.style.display = 'block';
            }
        };
        reader.readAsDataURL(input.files[0]);
    }
}

// Copy to clipboard
function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(() => {
        showSuccess('Copied to clipboard!');
    }).catch(err => {
        showError('Failed to copy to clipboard');
    });
}

// Export to CSV
function exportTableToCSV(tableId, filename) {
    const table = document.getElementById(tableId);
    if (!table) return;
    
    let csv = [];
    const rows = table.querySelectorAll('tr');
    
    for (let i = 0; i < rows.length; i++) {
        const row = [], cols = rows[i].querySelectorAll('td, th');
        
        for (let j = 0; j < cols.length; j++) {
            row.push(cols[j].innerText);
        }
        
        csv.push(row.join(','));
    }
    
    downloadCSV(csv.join('\n'), filename);
}

function downloadCSV(csv, filename) {
    const csvFile = new Blob([csv], { type: 'text/csv' });
    const downloadLink = document.createElement('a');
    
    downloadLink.download = filename;
    downloadLink.href = window.URL.createObjectURL(csvFile);
    downloadLink.style.display = 'none';
    
    document.body.appendChild(downloadLink);
    downloadLink.click();
    document.body.removeChild(downloadLink);
}
