<?php
// includes/functions.php

/**
 * Sanitize user input to prevent XSS attacks
 */
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

/**
 * Validate email format
 */
function validate_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

/**
 * Hash password securely
 */
function hash_password($password) {
    return password_hash($password, PASSWORD_BCRYPT);
}

/**
 * Verify password against hash
 */
function verify_password($password, $hash) {
    return password_verify($password, $hash);
}

/**
 * Check if user is logged in
 */
function is_logged_in() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Check if user has admin role
 */
function is_admin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === ROLE_ADMIN;
}

/**
 * Check if user has employee role
 */
function is_employee() {
    return isset($_SESSION['role']) && $_SESSION['role'] === ROLE_EMPLOYEE;
}

/**
 * Redirect to a specific page
 */
function redirect($page) {
    header("Location: $page");
    exit();
}

/**
 * Set success message in session
 */
function set_success_message($message) {
    $_SESSION['success_message'] = $message;
}

/**
 * Set error message in session
 */
function set_error_message($message) {
    $_SESSION['error_message'] = $message;
}

/**
 * Format date for display
 */
function format_date($date, $format = 'd M Y') {
    return date($format, strtotime($date));
}

/**
 * Format time for display
 */
function format_time($time, $format = 'h:i A') {
    return date($format, strtotime($time));
}

/**
 * Format datetime for display
 */
function format_datetime($datetime, $format = 'd M Y h:i A') {
    return date($format, strtotime($datetime));
}

/**
 * Get user full name from database
 */
function get_user_name($conn, $user_id) {
    $stmt = $conn->prepare("SELECT CONCAT(first_name, ' ', last_name) as full_name FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        return $row['full_name'];
    }
    return 'Unknown User';
}

/**
 * Get user details from database
 */
function get_user_details($conn, $user_id) {
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    return $result->fetch_assoc();
}

/**
 * Calculate leave days between two dates (excluding weekends)
 */
function calculate_leave_days($start_date, $end_date) {
    $start = new DateTime($start_date);
    $end = new DateTime($end_date);
    $end = $end->modify('+1 day'); // Include end date
    
    $interval = new DateInterval('P1D');
    $daterange = new DatePeriod($start, $interval, $end);
    
    $days = 0;
    foreach($daterange as $date){
        // Skip weekends (Saturday = 6, Sunday = 0)
        if($date->format('N') < 6) {
            $days++;
        }
    }
    
    return $days;
}

/**
 * Get leave status badge HTML
 */
function get_leave_status_badge($status) {
    $badge_class = '';
    $status_text = '';
    
    switch($status) {
        case LEAVE_STATUS_PENDING:
            $badge_class = 'bg-warning';
            $status_text = 'Pending';
            break;
        case LEAVE_STATUS_APPROVED:
            $badge_class = 'bg-success';
            $status_text = 'Approved';
            break;
        case LEAVE_STATUS_REJECTED:
            $badge_class = 'bg-danger';
            $status_text = 'Rejected';
            break;
        default:
            $badge_class = 'bg-secondary';
            $status_text = 'Unknown';
    }
    
    return "<span class='badge $badge_class'>$status_text</span>";
}

/**
 * Get attendance status badge HTML
 */
function get_attendance_status_badge($status) {
    $badge_class = '';
    $status_text = '';
    
    switch($status) {
        case ATTENDANCE_PRESENT:
            $badge_class = 'bg-success';
            $status_text = 'Present';
            break;
        case ATTENDANCE_ABSENT:
            $badge_class = 'bg-danger';
            $status_text = 'Absent';
            break;
        case ATTENDANCE_HALFDAY:
            $badge_class = 'bg-warning';
            $status_text = 'Half Day';
            break;
        case ATTENDANCE_LEAVE:
            $badge_class = 'bg-info';
            $status_text = 'On Leave';
            break;
        default:
            $badge_class = 'bg-secondary';
            $status_text = 'Not Marked';
    }
    
    return "<span class='badge $badge_class'>$status_text</span>";
}

/**
 * Upload profile picture
 */
function upload_profile_picture($file, $user_id) {
    $target_dir = "../uploads/profile_pictures/";
    
    // Create directory if it doesn't exist
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0777, true);
    }
    
    // Get file extension
    $imageFileType = strtolower(pathinfo($file["name"], PATHINFO_EXTENSION));
    
    // Generate unique filename
    $new_filename = "user_" . $user_id . "_" . time() . "." . $imageFileType;
    $target_file = $target_dir . $new_filename;
    
    // Check if image file is actual image
    $check = getimagesize($file["tmp_name"]);
    if($check === false) {
        return ["success" => false, "message" => "File is not an image."];
    }
    
    // Check file size (limit to 5MB)
    if ($file["size"] > 5000000) {
        return ["success" => false, "message" => "File is too large. Maximum size is 5MB."];
    }
    
    // Allow certain file formats
    if($imageFileType != "jpg" && $imageFileType != "png" && $imageFileType != "jpeg" && $imageFileType != "gif" ) {
        return ["success" => false, "message" => "Only JPG, JPEG, PNG & GIF files are allowed."];
    }
    
    // Upload file
    if (move_uploaded_file($file["tmp_name"], $target_file)) {
        return ["success" => true, "filename" => $new_filename];
    } else {
        return ["success" => false, "message" => "Error uploading file."];
    }
}

/**
 * Get profile picture URL
 */
function get_profile_picture($filename) {
    if (!empty($filename) && file_exists("../uploads/profile_pictures/" . $filename)) {
        return "/hrms/uploads/profile_pictures/" . $filename;
    }
    return "/hrms/assets/img/default-avatar.png";
}

/**
 * Generate CSRF token
 */
function generate_csrf_token() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token
 */
function verify_csrf_token($token) {
    if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        return false;
    }
    return true;
}

/**
 * Log activity
 */
function log_activity($conn, $user_id, $action, $details = '') {
    $stmt = $conn->prepare("INSERT INTO activity_logs (user_id, action, details, ip_address, created_at) VALUES (?, ?, ?, ?, NOW())");
    $ip_address = $_SERVER['REMOTE_ADDR'];
    $stmt->bind_param("isss", $user_id, $action, $details, $ip_address);
    $stmt->execute();
}

/**
 * Check if attendance is already marked for today
 */
function is_attendance_marked_today($conn, $user_id) {
    $today = date('Y-m-d');
    $stmt = $conn->prepare("SELECT id FROM attendance WHERE user_id = ? AND attendance_date = ?");
    $stmt->bind_param("is", $user_id, $today);
    $stmt->execute();
    $result = $stmt->get_result();
    
    return $result->num_rows > 0;
}

/**
 * Get remaining leave balance
 */
function get_leave_balance($conn, $user_id) {
    // Get total allocated leaves (you can set this per user or have a default)
    $total_leaves = 20; // Default annual leave
    
    // Get used leaves (approved leaves only)
    $stmt = $conn->prepare("
        SELECT COALESCE(SUM(DATEDIFF(end_date, start_date) + 1), 0) as used_leaves 
        FROM leave_requests 
        WHERE user_id = ? 
        AND status = ? 
        AND YEAR(start_date) = YEAR(CURDATE())
    ");
    $approved_status = LEAVE_STATUS_APPROVED;
    $stmt->bind_param("is", $user_id, $approved_status);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    $used_leaves = $row['used_leaves'];
    $remaining_leaves = $total_leaves - $used_leaves;
    
    return [
        'total' => $total_leaves,
        'used' => $used_leaves,
        'remaining' => $remaining_leaves
    ];
}

/**
 * Send email notification (configure with your SMTP settings)
 */
function send_email($to, $subject, $message) {
    // Basic PHP mail function - you should configure SMTP for production
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: HRMS <noreply@hrms.com>" . "\r\n";
    
    return mail($to, $subject, $message, $headers);
}
?>
