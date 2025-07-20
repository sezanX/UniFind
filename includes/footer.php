</main>

    <?php
    require_once 'includes/functions.php';
    $item_stats = getItemStats();
    ?>
    <!-- Footer -->
    <footer class="bg-light py-4 mt-5 border-top">
        <div class="container">
            <div class="row">
                <div class="col-md-4">
                    <h5 class="mb-3"><i class="fas fa-search-location me-2"></i>UniFind</h5>
                    <p class="text-muted">Lost and Found Management System for Northern University of Business and Technology Khulna.</p>
                    <p class="text-muted small mb-0">
                        <strong>Statistics:</strong><br>
                        Lost Items: <?php echo $item_stats['total_lost']; ?> | 
                        Found Items: <?php echo $item_stats['total_found']; ?> | 
                        Matched: <?php echo $item_stats['total_matched']; ?>
                    </p>
                </div>
                <div class="col-md-3">
                    <h5 class="mb-3">Quick Links</h5>
                    <ul class="list-unstyled">
                        <li><a href="index.php" class="text-decoration-none">Home</a></li>
                        <li><a href="lost_item.php" class="text-decoration-none">Lost Items</a></li>
                <li><a href="found_item.php" class="text-decoration-none">Found Items</a></li>
                        <li><a href="about.php" class="text-decoration-none">About Us</a></li>
                    </ul>
                </div>
                <div class="col-md-2">
                    <h5 class="mb-3">Account</h5>
                    <ul class="list-unstyled">
                        <?php if (isLoggedIn()): ?>
                            <li><a href="profile.php" class="text-decoration-none">My Profile</a></li>
                            <li><a href="my_reports.php" class="text-decoration-none">My Reports</a></li>
                            <li><a href="report_lost.php" class="text-decoration-none">Report Lost</a></li>
                            <li><a href="report_found.php" class="text-decoration-none">Report Found</a></li>
                            <li><a href="logout.php" class="text-decoration-none">Logout</a></li>
                        <?php else: ?>
                            <li><a href="login.php" class="text-decoration-none">Login</a></li>
                            <li><a href="signup.php" class="text-decoration-none">Sign Up</a></li>
                            <li><a href="report_item.php" class="text-decoration-none">Report Item</a></li>
                        <?php endif; ?>
                    </ul>
                </div>
                <div class="col-md-3">
                    <h5 class="mb-3">Contact</h5>
                    <ul class="list-unstyled">
                        <li><i class="fas fa-map-marker-alt me-2"></i> Northern University of Business and Technology Khulna</li>
                        <li><i class="fas fa-envelope me-2"></i> info@nubtkhulna.ac.bd</li>
                        <li><i class="fas fa-phone me-2"></i> +880 1XXX-XXXXXX</li>
                        <li><i class="fas fa-clock me-2"></i> Mon-Fri: 9:00 AM - 5:00 PM</li>
                    </ul>
                </div>
            </div>
            <hr>
            <div class="row">
                <div class="col-md-6">
                    <p class="mb-0">&copy; <?php echo date('Y'); ?> UniFind. All rights reserved.</p>
                    <p class="text-muted small">Developed with <i class="fas fa-heart text-danger"></i> by NUBT Students</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <div class="mb-2">
                        <a href="#" class="text-decoration-none me-3"><i class="fab fa-facebook-f"></i></a>
                        <a href="#" class="text-decoration-none me-3"><i class="fab fa-twitter"></i></a>
                        <a href="#" class="text-decoration-none me-3"><i class="fab fa-instagram"></i></a>
                        <a href="#" class="text-decoration-none"><i class="fab fa-linkedin-in"></i></a>
                    </div>
                    <p class="text-muted small mb-0">Last updated: <?php echo date('F Y'); ?></p>
                </div>
            </div>
        </div>
    </footer>

    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- Custom JS -->
    <script src="assets/js/script.js"></script>
    
    <!-- Initialize Bootstrap tooltips and popovers -->
    <script>
        // Initialize tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl)
        })
        
        // Initialize popovers
        var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'))
        var popoverList = popoverTriggerList.map(function (popoverTriggerEl) {
            return new bootstrap.Popover(popoverTriggerEl)
        })
    </script>
</body>
</html>