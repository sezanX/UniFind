<?php
$pageTitle = 'Report Item Fix';
require_once 'includes/header.php';

// This page is for non-logged in users to choose between reporting lost or found items
// It will redirect them to login first
?>

<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-primary text-white">
                <h4 class="card-title mb-0">Report an Item</h4>
            </div>
            <div class="card-body p-4">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    You need to be logged in to report lost or found items. Please login or create an account.
                </div>
                
                <div class="row mt-4">
                    <div class="col-md-6 mb-4 mb-md-0">
                        <div class="card h-100 border-danger">
                            <div class="card-body text-center p-4">
                                <i class="fas fa-search-location text-danger fa-4x mb-3"></i>
                                <h5 class="card-title">Lost an Item?</h5>
                                <p class="card-text">Report details about your lost item to help others find it.</p>
                                <a href="login.php?redirect=report_lost.php" class="btn btn-outline-danger mt-3">
                                    <i class="fas fa-sign-in-alt me-2"></i> Login to Report
                                </a>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="card h-100 border-success">
                            <div class="card-body text-center p-4">
                                <i class="fas fa-hand-holding text-success fa-4x mb-3"></i>
                                <h5 class="card-title">Found an Item?</h5>
                                <p class="card-text">Report items you've found to help reunite them with their owners.</p>
                                <a href="login.php?redirect=report_found.php" class="btn btn-outline-success mt-3">
                                    <i class="fas fa-sign-in-alt me-2"></i> Login to Report
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="text-center mt-4">
                    <p>Don't have an account?</p>
                    <a href="signup.php?redirect=report_lost.php" class="btn btn-primary">
                        <i class="fas fa-user-plus me-2"></i> Create an Account
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>