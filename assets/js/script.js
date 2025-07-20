/**
 * UniFind - Lost and Found Management System
 * Main JavaScript File
 */

$(document).ready(function() {
    // Initialize tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // Initialize popovers
    const popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
    popoverTriggerList.map(function (popoverTriggerEl) {
        return new bootstrap.Popover(popoverTriggerEl);
    });
    
    // Initialize tabs
    const tabElms = document.querySelectorAll('button[data-bs-toggle="tab"]');
    tabElms.forEach(tabElm => {
        tabElm.addEventListener('shown.bs.tab', event => {
            // Store the active tab in local storage
            localStorage.setItem('activeTab', event.target.id);
        });
    });
    
    // Restore active tab from local storage
    const activeTab = localStorage.getItem('activeTab');
    if (activeTab) {
        const tab = document.querySelector('#' + activeTab);
        if (tab) {
            new bootstrap.Tab(tab).show();
        }
    }
    
    // Add animation to cards with hover-lift class
    $('.hover-lift').hover(
        function() { $(this).addClass('shadow'); },
        function() { $(this).removeClass('shadow'); }
    );

    // Image preview for file uploads
    $('.custom-file-input').on('change', function() {
        const file = this.files[0];
        const fileType = file.type;
        const validImageTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/jpg'];
        const previewElement = $(this).closest('.form-group').find('.image-preview');
        
        if ($.inArray(fileType, validImageTypes) < 0) {
            // Invalid file type
            $(this).val('');
            previewElement.html('<div class="placeholder">Please select a valid image file (JPG, JPEG, PNG, GIF)</div>');
            return;
        }
        
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                previewElement.html('<img src="' + e.target.result + '" alt="Preview">');
            };
            reader.readAsDataURL(file);
        }
    });

    // Password strength meter
    $('#password').on('input', function() {
        const password = $(this).val();
        const meter = $('#password-strength-meter');
        const text = $('#password-strength-text');
        
        // Reset
        meter.removeClass('bg-danger bg-warning bg-info bg-success');
        
        // Check strength
        if (password.length === 0) {
            meter.css('width', '0%');
            text.text('');
            return;
        }
        
        let strength = 0;
        let message = '';
        
        // Length check
        if (password.length >= 8) {
            strength += 25;
        }
        
        // Lowercase check
        if (password.match(/[a-z]/)) {
            strength += 25;
        }
        
        // Uppercase check
        if (password.match(/[A-Z]/)) {
            strength += 25;
        }
        
        // Number/special char check
        if (password.match(/[0-9]/) || password.match(/[^a-zA-Z0-9]/)) {
            strength += 25;
        }
        
        // Update meter
        meter.css('width', strength + '%');
        
        // Update text and color
        if (strength <= 25) {
            meter.addClass('bg-danger');
            message = 'Weak';
        } else if (strength <= 50) {
            meter.addClass('bg-warning');
            message = 'Fair';
        } else if (strength <= 75) {
            meter.addClass('bg-info');
            message = 'Good';
        } else {
            meter.addClass('bg-success');
            message = 'Strong';
        }
        
        text.text(message);
    });

    // Password confirmation check
    $('#confirm_password').on('input', function() {
        const password = $('#password').val();
        const confirmPassword = $(this).val();
        const feedback = $('#password-match-feedback');
        
        if (confirmPassword.length === 0) {
            feedback.text('');
            return;
        }
        
        if (password === confirmPassword) {
            feedback.text('Passwords match').removeClass('text-danger').addClass('text-success');
        } else {
            feedback.text('Passwords do not match').removeClass('text-success').addClass('text-danger');
        }
    });

    // Form validation
    (function() {
        'use strict';
        
        // Fetch all forms we want to apply validation to
        const forms = document.querySelectorAll('.needs-validation');
        
        // Loop over them and prevent submission
        Array.prototype.slice.call(forms).forEach(function(form) {
            form.addEventListener('submit', function(event) {
                if (!form.checkValidity()) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                
                form.classList.add('was-validated');
            }, false);
        });
    })();

    // Date range picker initialization
    if ($('.date-picker').length) {
        $('.date-picker').datepicker({
            format: 'yyyy-mm-dd',
            autoclose: true,
            todayHighlight: true
        });
    }

    // Search filters toggle
    $('#toggle-filters').on('click', function() {
        $('#search-filters').slideToggle();
        $(this).find('i').toggleClass('fa-chevron-down fa-chevron-up');
    });

    // Item grid/list view toggle
    $('#view-grid').on('click', function() {
        $(this).addClass('active');
        $('#view-list').removeClass('active');
        $('#items-container').removeClass('list-view').addClass('grid-view');
    });
    
    $('#view-list').on('click', function() {
        $(this).addClass('active');
        $('#view-grid').removeClass('active');
        $('#items-container').removeClass('grid-view').addClass('list-view');
    });

    // Match confirmation modal
    $('#confirm-match-modal').on('show.bs.modal', function(event) {
        const button = $(event.relatedTarget);
        const lostItemId = button.data('lost-id');
        const foundItemId = button.data('found-id');
        const modal = $(this);
        
        modal.find('#lost-item-id').val(lostItemId);
        modal.find('#found-item-id').val(foundItemId);
    });

    // Delete confirmation modal
    $('#delete-confirm-modal').on('show.bs.modal', function(event) {
        const button = $(event.relatedTarget);
        const itemId = button.data('item-id');
        const itemType = button.data('item-type');
        const modal = $(this);
        
        modal.find('#delete-item-id').val(itemId);
        modal.find('#delete-item-type').val(itemType);
    });

    // Admin dashboard charts (if on admin page)
    if ($('#itemsChart').length) {
        const ctx = document.getElementById('itemsChart').getContext('2d');
        const itemsChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: ['Lost', 'Found', 'Matched', 'Returned'],
                datasets: [{
                    label: 'Items',
                    data: [
                        parseInt($('#lost-count').text()),
                        parseInt($('#found-count').text()),
                        parseInt($('#matched-count').text()),
                        parseInt($('#returned-count').text())
                    ],
                    backgroundColor: [
                        'rgba(231, 74, 59, 0.7)',
                        'rgba(28, 200, 138, 0.7)',
                        'rgba(246, 194, 62, 0.7)',
                        'rgba(78, 115, 223, 0.7)'
                    ],
                    borderColor: [
                        'rgba(231, 74, 59, 1)',
                        'rgba(28, 200, 138, 1)',
                        'rgba(246, 194, 62, 1)',
                        'rgba(78, 115, 223, 1)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                scales: {
                    y: {
                        beginAtZero: true,
                        precision: 0
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });
    }

    // Export data functionality (admin)
    $('#export-data').on('click', function() {
        const dataType = $('#export-type').val();
        const format = $('#export-format').val();
        
        window.location.href = 'export.php?type=' + dataType + '&format=' + format;
    });

    // Backup database functionality (admin)
    $('#backup-db').on('click', function() {
        $(this).prop('disabled', true).html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Backing up...');
        
        $.ajax({
            url: 'backup_db.php',
            type: 'POST',
            success: function(response) {
                const data = JSON.parse(response);
                if (data.status) {
                    showAlert('success', data.message);
                } else {
                    showAlert('danger', data.message);
                }
            },
            error: function() {
                showAlert('danger', 'An error occurred during backup');
            },
            complete: function() {
                $('#backup-db').prop('disabled', false).html('Backup Database');
            }
        });
    });

    // Helper function to show alerts
    function showAlert(type, message) {
        const alertHtml = `
            <div class="alert alert-${type} alert-dismissible fade show" role="alert">
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        `;
        
        $('#alert-container').html(alertHtml);
        
        // Auto dismiss after 5 seconds
        setTimeout(function() {
            $('.alert').alert('close');
        }, 5000);
    }
});