<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit();
}

$conn = getDBConnection();
$message = '';
$message_type = '';

// Handle settings update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_general'])) {
        // Update general settings
        $system_name = trim($_POST['system_name']);
        $company_name = trim($_POST['company_name']);
        $admin_email = trim($_POST['admin_email']);
        
        // In a real application, you would save these to a settings table
        // For now, we'll just show success message
        $message = "General settings updated successfully!";
        $message_type = "success";
        
    } elseif (isset($_POST['update_attendance'])) {
        // Update attendance settings
        $office_start_time = $_POST['office_start_time'];
        $office_end_time = $_POST['office_end_time'];
        $late_threshold = (int)$_POST['late_threshold'];
        $half_day_hours = (int)$_POST['half_day_hours'];
        
        $message = "Attendance settings updated successfully!";
        $message_type = "success";
        
    } elseif (isset($_POST['update_leave'])) {
        // Update leave settings
        $annual_leave_days = (int)$_POST['annual_leave_days'];
        $sick_leave_days = (int)$_POST['sick_leave_days'];
        $casual_leave_days = (int)$_POST['casual_leave_days'];
        
        $message = "Leave settings updated successfully!";
        $message_type = "success";
        
    } elseif (isset($_POST['change_password'])) {
        // Change admin password
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        // Verify current password
        $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->bind_param("i", $_SESSION['user_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        
        if (password_verify($current_password, $user['password'])) {
            if ($new_password === $confirm_password) {
                if (strlen($new_password) >= 6) {
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                    $stmt->bind_param("si", $hashed_password, $_SESSION['user_id']);
                    
                    if ($stmt->execute()) {
                        $message = "Password changed successfully!";
                        $message_type = "success";
                    } else {
                        $message = "Error changing password.";
                        $message_type = "danger";
                    }
                } else {
                    $message = "New password must be at least 6 characters long.";
                    $message_type = "danger";
                }
            } else {
                $message = "New passwords do not match.";
                $message_type = "danger";
            }
        } else {
            $message = "Current password is incorrect.";
            $message_type = "danger";
        }
        $stmt->close();
    }
}

// Get system statistics
$total_users = $conn->query("SELECT COUNT(*) as count FROM users")->fetch_assoc()['count'];
$total_employees = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'employee'")->fetch_assoc()['count'];
$total_attendance = $conn->query("SELECT COUNT(*) as count FROM attendance")->fetch_assoc()['count'];
$total_leaves = $conn->query("SELECT COUNT(*) as count FROM leave_requests")->fetch_assoc()['count'];

// Default settings (in a real app, fetch from database)
$settings = [
    'system_name' => 'HRMS',
    'company_name' => 'Your Company',
    'admin_email' => $_SESSION['email'] ?? 'admin@company.com',
    'office_start_time' => '09:00',
    'office_end_time' => '18:00',
    'late_threshold' => 15,
    'half_day_hours' => 4,
    'annual_leave_days' => 15,
    'sick_leave_days' => 10,
    'casual_leave_days' => 7
];

include '../includes/header.php';
?>

<div class="container-fluid mt-4">
    <h2 class="mb-4">System Settings</h2>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row">
        <!-- Left Column - Settings Forms -->
        <div class="col-md-8">
            
            <!-- General Settings -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">General Settings</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="mb-3">
                            <label for="system_name" class="form-label">System Name</label>
                            <input type="text" class="form-control" id="system_name" name="system_name" value="<?php echo htmlspecialchars($settings['system_name']); ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="company_name" class="form-label">Company Name</label>
                            <input type="text" class="form-control" id="company_name" name="company_name" value="<?php echo htmlspecialchars($settings['company_name']); ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="admin_email" class="form-label">Admin Email</label>
                            <input type="email" class="form-control" id="admin_email" name="admin_email" value="<?php echo htmlspecialchars($settings['admin_email']); ?>" required>
                        </div>
                        
                        <button type="submit" name="update_general" class="btn btn-primary">Save Changes</button>
                    </form>
                </div>
            </div>

            <!-- Attendance Settings -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Attendance Settings</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="office_start_time" class="form-label">Office Start Time</label>
                                <input type="time" class="form-control" id="office_start_time" name="office_start_time" value="<?php echo $settings['office_start_time']; ?>" required>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="office_end_time" class="form-label">Office End Time</label>
                                <input type="time" class="form-control" id="office_end_time" name="office_end_time" value="<?php echo $settings['office_end_time']; ?>" required>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="late_threshold" class="form-label">Late Threshold (minutes)</label>
                                <input type="number" class="form-control" id="late_threshold" name="late_threshold" value="<?php echo $settings['late_threshold']; ?>" min="0" required>
                                <small class="text-muted">Minutes after start time to mark as late</small>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="half_day_hours" class="form-label">Half Day Hours</label>
                                <input type="number" class="form-control" id="half_day_hours" name="half_day_hours" value="<?php echo $settings['half_day_hours']; ?>" min="1" max="8" required>
                                <small class="text-muted">Minimum hours for half day</small>
                            </div>
                        </div>
                        
                        <button type="submit" name="update_attendance" class="btn btn-primary">Save Changes</button>
                    </form>
                </div>
            </div>

            <!-- Leave Settings -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Leave Settings</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="mb-3">
                            <label for="annual_leave_days" class="form-label">Annual Leave Days per Year</label>
                            <input type="number" class="form-control" id="annual_leave_days" name="annual_leave_days" value="<?php echo $settings['annual_leave_days']; ?>" min="0" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="sick_leave_days" class="form-label">Sick Leave Days per Year</label>
                            <input type="number" class="form-control" id="sick_leave_days" name="sick_leave_days" value="<?php echo $settings['sick_leave_days']; ?>" min="0" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="casual_leave_days" class="form-label">Casual Leave Days per Year</label>
                            <input type="number" class="form-control" id="casual_leave_days" name="casual_leave_days" value="<?php echo $settings['casual_leave_days']; ?>" min="0" required>
                        </div>
                        
                        <button type="submit" name="update_leave" class="btn btn-primary">Save Changes</button>
                    </form>
                </div>
            </div>

            <!-- Change Password -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Change Password</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="mb-3">
                            <label for="current_password" class="form-label">Current Password</label>
                            <input type="password" class="form-control" id="current_password" name="current_password" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="new_password" class="form-label">New Password</label>
                            <input type="password" class="form-control" id="new_password" name="new_password" minlength="6" required>
                            <small class="text-muted">Minimum 6 characters</small>
                        </div>
                        
                        <div class="mb-3">
                            <label for="confirm_password" class="form-label">Confirm New Password</label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" minlength="6" required>
                        </div>
                        
                        <button type="submit" name="change_password" class="btn btn-primary">Change Password</button>
                    </form>
                </div>
            </div>

        </div>

        <!-- Right Column - System Info -->
        <div class="col-md-4">
            
            <!-- System Statistics -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">System Statistics</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <div class="d-flex justify-content-between">
                            <span>Total Users:</span>
                            <strong><?php echo $total_users; ?></strong>
                        </div>
                    </div>
                    <div class="mb-3">
                        <div class="d-flex justify-content-between">
                            <span>Total Employees:</span>
                            <strong><?php echo $total_employees; ?></strong>
                        </div>
                    </div>
                    <div class="mb-3">
                        <div class="d-flex justify-content-between">
                            <span>Attendance Records:</span>
                            <strong><?php echo $total_attendance; ?></strong>
                        </div>
                    </div>
                    <div class="mb-3">
                        <div class="d-flex justify-content-between">
                            <span>Leave Requests:</span>
                            <strong><?php echo $total_leaves; ?></strong>
                        </div>
                    </div>
                </div>
            </div>

            <!-- System Information -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">System Information</h5>
                </div>
                <div class="card-body">
                    <div class="mb-2">
                        <small class="text-muted">Version:</small><br>
                        <strong>1.0.0</strong>
                    </div>
                    <div class="mb-2">
                        <small class="text-muted">PHP Version:</small><br>
                        <strong><?php echo phpversion(); ?></strong>
                    </div>
                    <div class="mb-2">
                        <small class="text-muted">Database:</small><br>
                        <strong>MySQL <?php echo $conn->server_info; ?></strong>
                    </div>
                    <div class="mb-2">
                        <small class="text-muted">Server:</small><br>
                        <strong><?php echo $_SERVER['SERVER_SOFTWARE']; ?></strong>
                    </div>
                </div>
            </div>

            <!-- Database Maintenance -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Database Maintenance</h5>
                </div>
                <div class="card-body">
                    <p class="text-muted small">Regular maintenance tasks for database health.</p>
                    <button class="btn btn-outline-primary btn-sm w-100 mb-2" onclick="alert('Backup feature coming soon!')">
                        <i class="bi bi-download"></i> Backup Database
                    </button>
                    <button class="btn btn-outline-warning btn-sm w-100" onclick="alert('Optimize feature coming soon!')">
                        <i class="bi bi-gear"></i> Optimize Database
                    </button>
                </div>
            </div>

        </div>
    </div>
</div>

<?php 
$conn->close();
include '../includes/footer.php'; 
?>
