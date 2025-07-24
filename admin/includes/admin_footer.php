</main>
        
        <footer class="py-4">
            <div class="container-fluid">
                <div class="d-flex align-items-center justify-content-between small">
                    <div class="text-muted">Copyright &copy; UniFind 2025 </div>
                    <div>
                        <a href="#">Privacy Policy</a>
                        &middot;
                <a href="#">Terms &amp; Conditions</a>
                    </div>
                </div>
            </div>
        </footer>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        // Sidebar Toggler
        const sidebarToggle = document.getElementById('sidebarToggle');
        if (sidebarToggle) {
            sidebarToggle.addEventListener('click', function (event) {
                event.preventDefault();
                document.getElementById('adminSidebar').classList.toggle('show');
            });
        }

    // Initialize Bootstrap Tooltips
        const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
    const tooltipList = [...tooltipTriggerList].map(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl));

    // Initialize Bootstrap Popovers
        const popoverTriggerList = document.querySelectorAll('[data-bs-toggle="popover"]');
        const popoverList = [...popoverTriggerList].map(popoverTriggerEl => new bootstrap.Popover(popoverTriggerEl));

    // Auto-close alerts
        window.setTimeout(function() {
            $(".alert").fadeTo(500, 0).slideUp(500, function(){
                $(this).remove();
            });
        }, 5000); // 5 seconds
    });

// Confirm delete function
    function confirmDelete(url, itemName) {
        if (confirm(`Are you sure you want to delete "${itemName}"? This action cannot be undone.`)) {
            window.location.href = url;
    }
    }
</script>
</body>
</html>