/**
 * HRMS Custom JavaScript
 * Handles dynamic interactions and AJAX requests
 */

$(document).ready(function() {
    // Initialize tooltips if Bootstrap 5 is used
    if (typeof bootstrap !== 'undefined') {
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    }

    // Mobile sidebar toggle
    $('.sidebar-toggle').on('click', function() {
        $('.sidebar').toggleClass('active');
    });

    // Close sidebar when clicking outside on mobile
    $(document).on('click', function(e) {
        if ($(window).width() <= 768) {
            if (!$(e.target).closest('.sidebar, .sidebar-toggle').length) {
                $('.sidebar').removeClass('active');
            }
        }
    });

    // Auto-hide alerts after 5 seconds
    setTimeout(function() {
        $('.alert').fadeOut('slow');
    }, 5000);

    // Form validation
    $('form').on('submit', function(e) {
        var isValid = true;
        $(this).find('[required]').each(function() {
            if ($(this).val() === '') {
                isValid = false;
                $(this).addClass('is-invalid');
            } else {
                $(this).removeClass('is-invalid');
            }
        });

        if (!isValid) {
            e.preventDefault();
            showAlert('Please fill in all required fields', 'danger');
        }
    });

    // Remove invalid class on input
    $('input, select, textarea').on('input change', function() {
        $(this).removeClass('is-invalid');
    });
});

/**
 * Show alert message
 */
function showAlert(message, type = 'info') {
    var alertHtml = `
        <div class="alert alert-${type} alert-dismissible fade show" role="alert">
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    `;
    
    $('.content-wrapper').prepend(alertHtml);
    
    setTimeout(function() {
        $('.alert').fadeOut('slow', function() {
            $(this).remove();
        });
    }, 5000);
}

/**
 * Confirm action dialog
 */
function confirmAction(message, callback) {
    if (confirm(message)) {
        callback();
    }
}

/**
 * ATTENDANCE FUNCTIONS
 */

// Mark attendance (clock in/out)
function markAttendance(action) {
    $.ajax({
        url: '../api/attendance.php',
        type: 'POST',
        data: {
            action: action
        },
        dataType: 'json',
        beforeSend: function() {
            $('.btn-attendance').prop('disabled', true);
            $('.btn-attendance').html('<span class="spinner-border spinner-border-sm"></span> Processing...');
        },
        success: function(response) {
            if (response.success) {
                showAlert(response.message, 'success');
                setTimeout(function() {
                    location.reload();
                }, 1500);
            } else {
                showAlert(response.message, 'danger');
                $('.btn-attendance').prop('disabled', false);
                $('.btn-attendance').html(action === 'clock_in' ? 'Clock In' : 'Clock Out');
            }
        },
        error: function() {
            showAlert('An error occurred. Please try again.', 'danger');
            $('.btn-attendance').prop('disabled', false);
            $('.btn-attendance').html(action === 'clock_in' ? 'Clock In' : 'Clock Out');
        }
    });
}

// Load attendance history
function loadAttendanceHistory(period = 'week') {
    $.ajax({
        url: '../api/attendance.php',
        type: 'GET',
        data: {
            action: 'get_history',
            period: period
        },
        dataType: 'json',
        beforeSend: function() {
            $('#attendance-table-body').html('<tr><td colspan="5" class="text-center"><div class="spinner"></div></td></tr>');
        },
        success: function(response) {
            if (response.success) {
                displayAttendanceHistory(response.data);
            } else {
                $('#attendance-table-body').html('<tr><td colspan="5" class="text-center text-danger">' + response.message + '</td></tr>');
            }
        },
        error: function() {
            $('#attendance-table-body').html('<tr><td colspan="5" class="text-center text-danger">Failed to load attendance history</td></tr>');
        }
    });
}

// Display attendance history in table
function displayAttendanceHistory(data) {
    var html = '';
    
    if (data.length === 0) {
        html = '<tr><td colspan="5" class="text-center">No attendance records found</td></tr>';
    } else {
        data.forEach(function(record) {
            var statusBadge = '';
            if (record.status === 'Present') {
                statusBadge = '<span class="badge badge-success">Present</span>';
            } else if (record.status === 'Absent') {
                statusBadge = '<span class="badge badge-danger">Absent</span>';
            } else if (record.status === 'Leave') {
                statusBadge = '<span class="badge badge-warning">Leave</span>';
            } else if (record.status === 'Half Day') {
                statusBadge = '<span class="badge badge-info">Half Day</span>';
            }
            
            html += `
                <tr>
                    <td>${record.date}</td>
                    <td>${record.clock_in || '-'}</td>
                    <td>${record.clock_out || '-'}</td>
                    <td>${record.total_hours || '-'}</td>
                    <td>${statusBadge}</td>
                </tr>
            `;
        });
    }
    
    $('#attendance-table-body').html(html);
}

/**
 * LEAVE REQUEST FUNCTIONS
 */

// Submit leave request
$('#leave-request-form').on('submit', function(e) {
    e.preventDefault();
    
    var formData = $(this).serialize();
    
    $.ajax({
        url: '../api/leave.php',
        type: 'POST',
        data: formData + '&action=submit_leave',
        dataType: 'json',
        beforeSend: function() {
            $('#submit-leave-btn').prop('disabled', true);
            $('#submit-leave-btn').html('<span class="spinner-border spinner-border-sm"></span> Submitting...');
        },
        success: function(response) {
            if (response.success) {
                showAlert(response.message, 'success');
                $('#leave-request-form')[0].reset();
                setTimeout(function() {
                    window.location.href = 'leave-history.php';
                }, 1500);
            } else {
                showAlert(response.message, 'danger');
            }
            $('#submit-leave-btn').prop('disabled', false);
            $('#submit-leave-btn').html('Submit Request');
        },
        error: function() {
            showAlert('An error occurred. Please try again.', 'danger');
            $('#submit-leave-btn').prop('disabled', false);
            $('#submit-leave-btn').html('Submit Request');
        }
    });
});

// Calculate leave days
$('#leave_from, #leave_to').on('change', function() {
    var fromDate = new Date($('#leave_from').val());
    var toDate = new Date($('#leave_to').val());
    
    if (fromDate && toDate && toDate >= fromDate) {
        var timeDiff = toDate.getTime() - fromDate.getTime();
        var dayDiff = Math.ceil(timeDiff / (1000 * 3600 * 24)) + 1;
        $('#leave-days-display').text(dayDiff + ' day(s)');
    } else {
        $('#leave-days-display').text('0 day(s)');
    }
});

// Approve/Reject leave request (Admin)
function updateLeaveStatus(leaveId, status) {
    var action = status === 'Approved' ? 'approve' : 'reject';
    var message = 'Are you sure you want to ' + action + ' this leave request?';
    
    confirmAction(message, function() {
        $.ajax({
            url: '../api/leave.php',
            type: 'POST',
            data: {
                action: 'update_status',
                leave_id: leaveId,
                status: status
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    showAlert(response.message, 'success');
                    setTimeout(function() {
                        location.reload();
                    }, 1500);
                } else {
                    showAlert(response.message, 'danger');
                }
            },
            error: function() {
                showAlert('An error occurred. Please try again.', 'danger');
            }
        });
    });
}

// Load leave history
function loadLeaveHistory() {
    $.ajax({
        url: '../api/leave.php',
        type: 'GET',
        data: {
            action: 'get_history'
        },
        dataType: 'json',
        beforeSend: function() {
            $('#leave-table-body').html('<tr><td colspan="6" class="text-center"><div class="spinner"></div></td></tr>');
        },
        success: function(response) {
            if (response.success) {
                displayLeaveHistory(response.data);
            } else {
                $('#leave-table-body').html('<tr><td colspan="6" class="text-center text-danger">' + response.message + '</td></tr>');
            }
        },
        error: function() {
            $('#leave-table-body').html('<tr><td colspan="6" class="text-center text-danger">Failed to load leave history</td></tr>');
        }
    });
}

// Display leave history in table
function displayLeaveHistory(data) {
    var html = '';
    
    if (data.length === 0) {
        html = '<tr><td colspan="6" class="text-center">No leave requests found</td></tr>';
    } else {
        data.forEach(function(leave) {
            var statusBadge = '';
            if (leave.status === 'Pending') {
                statusBadge = '<span class="badge badge-warning">Pending</span>';
            } else if (leave.status === 'Approved') {
                statusBadge = '<span class="badge badge-success">Approved</span>';
            } else if (leave.status === 'Rejected') {
                statusBadge = '<span class="badge badge-danger">Rejected</span>';
            }
            
            html += `
                <tr>
                    <td>${leave.leave_type}</td>
                    <td>${leave.from_date}</td>
                    <td>${leave.to_date}</td>
                    <td>${leave.days}</td>
                    <td>${leave.reason}</td>
                    <td>${statusBadge}</td>
                </tr>
            `;
        });
    }
    
    $('#leave-table-body').html(html);
}

/**
 * PROFILE FUNCTIONS
 */

// Profile picture upload preview
$('#profile-picture-input').on('change', function(e) {
    var file = e.target.files[0];
    if (file) {
        var reader = new FileReader();
        reader.onload = function(e) {
            $('#profile-picture-preview').attr('src', e.target.result);
        }
        reader.readAsDataURL(file);
    }
});

// Update profile
$('#profile-update-form').on('submit', function(e) {
    e.preventDefault();
    
    var formData = new FormData(this);
    formData.append('action', 'update_profile');
    
    $.ajax({
        url: '../api/profile.php',
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        dataType: 'json',
        beforeSend: function() {
            $('#update-profile-btn').prop('disabled', true);
            $('#update-profile-btn').html('<span class="spinner-border spinner-border-sm"></span> Updating...');
        },
        success: function(response) {
            if (response.success) {
                showAlert(response.message, 'success');
                setTimeout(function() {
                    location.reload();
                }, 1500);
            } else {
                showAlert(response.message, 'danger');
            }
            $('#update-profile-btn').prop('disabled', false);
            $('#update-profile-btn').html('Update Profile');
        },
        error: function() {
            showAlert('An error occurred. Please try again.', 'danger');
            $('#update-profile-btn').prop('disabled', false);
            $('#update-profile-btn').html('Update Profile');
        }
    });
});

/**
 * EMPLOYEE MANAGEMENT (Admin)
 */

// Delete employee
function deleteEmployee(employeeId) {
    confirmAction('Are you sure you want to delete this employee? This action cannot be undone.', function() {
        $.ajax({
            url: '../api/employee.php',
            type: 'POST',
            data: {
                action: 'delete',
                employee_id: employeeId
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    showAlert(response.message, 'success');
                    setTimeout(function() {
                        location.reload();
                    }, 1500);
                } else {
                    showAlert(response.message, 'danger');
                }
            },
            error: function() {
                showAlert('An error occurred. Please try again.', 'danger');
            }
        });
    });
}

// Toggle employee status
function toggleEmployeeStatus(employeeId, currentStatus) {
    var newStatus = currentStatus === 'Active' ? 'Inactive' : 'Active';
    var message = 'Are you sure you want to change this employee status to ' + newStatus + '?';
    
    confirmAction(message, function() {
        $.ajax({
            url: '../api/employee.php',
            type: 'POST',
            data: {
                action: 'toggle_status',
                employee_id: employeeId,
                status: newStatus
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    showAlert(response.message, 'success');
                    setTimeout(function() {
                        location.reload();
                    }, 1500);
                } else {
                    showAlert(response.message, 'danger');
                }
            },
            error: function() {
                showAlert('An error occurred. Please try again.', 'danger');
            }
        });
    });
}

/**
 * DATA TABLES ENHANCEMENT
 */

// Search functionality for tables
$('#table-search').on('keyup', function() {
    var value = $(this).val().toLowerCase();
    $('#data-table tbody tr').filter(function() {
        $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1);
    });
});

// Sort table columns
$('.sortable').on('click', function() {
    var table = $(this).parents('table').eq(0);
    var rows = table.find('tbody tr').toArray().sort(comparer($(this).index()));
    this.asc = !this.asc;
    if (!this.asc) {
        rows = rows.reverse();
    }
    for (var i = 0; i < rows.length; i++) {
        table.find('tbody').append(rows[i]);
    }
});

function comparer(index) {
    return function(a, b) {
        var valA = getCellValue(a, index);
        var valB = getCellValue(b, index);
        return $.isNumeric(valA) && $.isNumeric(valB) ? valA - valB : valA.localeCompare(valB);
    }
}

function getCellValue(row, index) {
    return $(row).children('td').eq(index).text();
}

/**
 * DASHBOARD STATS ANIMATION
 */
function animateValue(element, start, end, duration) {
    var range = end - start;
    var current = start;
    var increment = end > start ? 1 : -1;
    var stepTime = Math.abs(Math.floor(duration / range));
    
    var timer = setInterval(function() {
        current += increment;
        $(element).text(current);
        if (current == end) {
            clearInterval(timer);
        }
    }, stepTime);
}

// Animate dashboard statistics on page load
$('.stat-value h2').each(function() {
    var finalValue = parseInt($(this).text());
    if (!isNaN(finalValue)) {
        $(this).text('0');
        animateValue(this, 0, finalValue, 1000);
    }
});

/**
 * DATE AND TIME UTILITIES
 */

// Format date
function formatDate(date) {
    var d = new Date(date);
    var month = '' + (d.getMonth() + 1);
    var day = '' + d.getDate();
    var year = d.getFullYear();

    if (month.length < 2) month = '0' + month;
    if (day.length < 2) day = '0' + day;

    return [year, month, day].join('-');
}

// Get current time
function getCurrentTime() {
    var now = new Date();
    var hours = now.getHours();
    var minutes = now.getMinutes();
    var seconds = now.getSeconds();
    
    hours = hours < 10 ? '0' + hours : hours;
    minutes = minutes < 10 ? '0' + minutes : minutes;
    seconds = seconds < 10 ? '0' + seconds : seconds;
    
    return hours + ':' + minutes + ':' + seconds;
}

// Update live clock if element exists
setInterval(function() {
    if ($('#live-clock').length) {
        $('#live-clock').text(getCurrentTime());
    }
}, 1000);

/**
 * EXPORT FUNCTIONS
 */

// Export table to CSV
function exportTableToCSV(filename) {
    var csv = [];
    var rows = document.querySelectorAll('#data-table tr');
    
    for (var i = 0; i < rows.length; i++) {
        var row = [];
        var cols = rows[i].querySelectorAll('td, th');
        
        for (var j = 0; j < cols.length; j++) {
            row.push(cols[j].innerText);
        }
        
        csv.push(row.join(','));
    }
    
    downloadCSV(csv.join('\n'), filename);
}

function downloadCSV(csv, filename) {
    var csvFile = new Blob([csv], { type: 'text/csv' });
    var downloadLink = document.createElement('a');
    downloadLink.download = filename;
    downloadLink.href = window.URL.createObjectURL(csvFile);
    downloadLink.style.display = 'none';
    document.body.appendChild(downloadLink);
    downloadLink.click();
    document.body.removeChild(downloadLink);
}

// Print table
function printTable() {
    window.print();
}

/**
 * PASSWORD STRENGTH CHECKER
 */
$('#password').on('keyup', function() {
    var password = $(this).val();
    var strength = 0;
    
    if (password.length >= 8) strength++;
    if (password.match(/[a-z]+/)) strength++;
    if (password.match(/[A-Z]+/)) strength++;
    if (password.match(/[0-9]+/)) strength++;
    if (password.match(/[$@#&!]+/)) strength++;
    
    var strengthText = ['Very Weak', 'Weak', 'Fair', 'Good', 'Strong'];
    var strengthColor = ['danger', 'danger', 'warning', 'info', 'success'];
    
    if (password.length > 0) {
        $('#password-strength').html('<span class="badge badge-' + strengthColor[strength - 1] + '">' + strengthText[strength - 1] + '</span>');
    } else {
        $('#password-strength').html('');
    }
});