/**
 * Admin Panel JavaScript
 */

$(document).ready(function() {
    // Initialize DataTables
    if ($.fn.DataTable) {
        $('.data-table').DataTable({
            pageLength: 25,
            responsive: true,
            language: {
                search: "_INPUT_",
                searchPlaceholder: "Search..."
            }
        });
    }
    
    // Initialize Select2
    if ($.fn.select2) {
        $('.select2').select2({
            theme: 'bootstrap-5',
            width: '100%'
        });
    }
    
    // Sidebar toggle for mobile
    $('#sidebarCollapse').on('click', function() {
        $('#sidebar').toggleClass('active');
    });
    
    // Delete confirmation
    $('.btn-delete').on('click', function(e) {
        e.preventDefault();
        const url = $(this).attr('href');
        
        Swal.fire({
            title: 'Are you sure?',
            text: "You won't be able to revert this!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Yes, delete it!'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = url;
            }
        });
    });
    
    // Auto-dismiss alerts
    setTimeout(function() {
        $('.alert:not(.alert-permanent)').fadeOut('slow');
    }, 5000);
    
    // Form validation
    if ($.fn.validate) {
        $('form.validate-form').validate({
            errorClass: 'text-danger',
            errorElement: 'small',
            highlight: function(element) {
                $(element).addClass('is-invalid');
            },
            unhighlight: function(element) {
                $(element).removeClass('is-invalid');
            }
        });
    }
    
    // Confirm action buttons
    $('.btn-confirm').on('click', function(e) {
        const message = $(this).data('message') || 'Are you sure you want to proceed?';
        if (!confirm(message)) {
            e.preventDefault();
        }
    });
    
    // Image preview
    $('input[type="file"].image-upload').on('change', function() {
        const file = this.files[0];
        if (file) {
            const reader = new FileReader();
            const preview = $(this).closest('.form-group').find('.image-preview');
            
            reader.onload = function(e) {
                if (preview.length) {
                    preview.attr('src', e.target.result).show();
                } else {
                    $(this).after('<img src="' + e.target.result + '" class="image-preview mt-2" style="max-width: 200px; max-height: 200px;">');
                }
            };
            
            reader.readAsDataURL(file);
        }
    });
    
    // Number formatting
    $('.format-currency').each(function() {
        const value = parseFloat($(this).text());
        if (!isNaN(value)) {
            $(this).text(value.toLocaleString('en-US', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            }));
        }
    });
    
    // Tooltip initialization
    if ($.fn.tooltip) {
        $('[data-bs-toggle="tooltip"]').tooltip();
    }
    
    // Popover initialization
    if ($.fn.popover) {
        $('[data-bs-toggle="popover"]').popover();
    }
});

/**
 * Show loading overlay
 */
function showLoading(message = 'Loading...') {
    Swal.fire({
        title: message,
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
}

/**
 * Hide loading overlay
 */
function hideLoading() {
    Swal.close();
}

/**
 * Show success message
 */
function showSuccess(message, title = 'Success') {
    Swal.fire({
        icon: 'success',
        title: title,
        text: message,
        confirmButtonColor: '#4CAF50'
    });
}

/**
 * Show error message
 */
function showError(message, title = 'Error') {
    Swal.fire({
        icon: 'error',
        title: title,
        text: message,
        confirmButtonColor: '#d33'
    });
}

/**
 * Confirm dialog
 */
function confirmAction(message, callback) {
    Swal.fire({
        title: 'Are you sure?',
        text: message,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#4CAF50',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Yes, proceed!'
    }).then((result) => {
        if (result.isConfirmed && typeof callback === 'function') {
            callback();
        }
    });
}
