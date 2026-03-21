    </div>
    
    <!-- jQuery -->
    <script src="<?php echo BASE_URL; ?>/admin/vendor/jquery/jquery.min.js"></script>
    
    <!-- Bootstrap 5 JS -->
    <script src="<?php echo BASE_URL; ?>/admin/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    
    <!-- DataTables -->
    <script src="<?php echo BASE_URL; ?>/admin/vendor/datatables/js/jquery.dataTables.min.js"></script>
    <script src="<?php echo BASE_URL; ?>/admin/vendor/datatables/js/dataTables.bootstrap5.min.js"></script>
    
    <!-- Chart.js -->
    <script src="<?php echo BASE_URL; ?>/admin/vendor/chartjs/chart.umd.min.js"></script>
    
    <!-- SweetAlert2 -->
    <script src="<?php echo BASE_URL; ?>/admin/vendor/sweetalert2/sweetalert2.all.min.js"></script>
    
    <script>
        // Sidebar toggle for mobile
        document.getElementById('sidebarToggle')?.addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('show');
            document.getElementById('sidebarOverlay').classList.toggle('show');
        });
        
        // Close sidebar when clicking overlay
        document.getElementById('sidebarOverlay')?.addEventListener('click', function() {
            document.getElementById('sidebar').classList.remove('show');
            document.getElementById('sidebarOverlay').classList.remove('show');
        });
        
        // Initialize DataTables
        $(document).ready(function() {
            $('.datatable').DataTable({
                pageLength: 25,
                order: [[0, 'desc']]
            });
        });
        
        // Confirm delete
        function confirmDelete(url, message = 'Are you sure you want to delete this item?') {
            Swal.fire({
                title: 'Confirm Delete',
                text: message,
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
        }
        
        // Show success message
        function showSuccess(message) {
            Swal.fire({
                icon: 'success',
                title: 'Success!',
                text: message,
                confirmButtonColor: '#4CAF50'
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
    </script>
    
    <!-- Nepali Date Picker -->
    <script src="<?php echo BASE_URL; ?>/js/nepali-date-picker.js"></script>
    
    <?php if (isset($extra_js)) echo $extra_js; ?>
</body>
</html>
