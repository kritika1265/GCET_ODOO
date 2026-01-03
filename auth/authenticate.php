<?php
require_once '../config/database.php';
require_once '../config/constants.php';
require_once '../includes/functions.php';

// Redirect if already logged in
redirectIfLoggedIn();

// Check if form is submitted
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.php');
    exit();
}

$action = isset($_POST['action']) ? $_POST['action'] : '';

// LOGIN AUTHENTICATION
if ($action === 'login') {
    $email = sanitize($_POST['email']);
    $password = $_POST['password'];
    
    // Validate inputs
    if (empty($email) || empty($password)) {
        setFlashMessage('Please fill in all fields', 'danger');
        header('Location: login.php');
        exit();
    }
    
    if (!validateEmail($email)) {
        setFlashMessage('Please enter a valid email address', 'danger');
        header('Location: login.php');
        exit();
    }
    
    // Check user credentials
    $query = "SELECT u.*, e.id as employee_id, e.first_name, e.last_name 
              FROM users u 
              LEFT JOIN employees e ON u.id = e.user_id 
              WHERE u.email = ? AND u.status = 'active'";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "s", $email);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if ($user = mysqli_fetch_assoc($result)) {
        if (verifyPassword($password, $user['password'])) {
            // Set session variables
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['employee_id'] = $user['employee_id'];
            $_SESSION['name'] = $user['first_name'] . ' ' . $user['last_name'];
            $_SESSION['logged_in_at'] = time();
            
            // Optional: Handle "Remember Me"
            if (isset($_POST['remember']) && $_POST['remember'] === 'on') {
                // Set cookie for 30 days
                setcookie('remember_user', $user['id'], time() + (30 * 24 * 60 * 60), '/');
            }
            
            // Log login activity (optional)
            $log_query = "INSERT INTO login_logs (user_id, login_time, ip_address) VALUES (?, NOW(), ?)";
            $log_stmt = mysqli_prepare($conn, $log_query);
            $ip_address = $_SERVER['REMOTE_ADDR'];
            mysqli_stmt_bind_param($log_stmt, "is", $user['id'], $ip_address);
            @mysqli_stmt_execute($log_stmt); // @ to suppress error if table doesn't exist
            
            // Redirect based on role
            if ($user['role'] === ROLE_ADMIN) {
                header('Location: ../admin/index.php');
            } else {
                header('Location: ../employee/index.php');
            }
            exit();
        } else {
            setFlashMessage('Invalid email or password', 'danger');
            header('Location: login.php');
            exit();
        }
    } else {
        setFlashMessage('Invalid email or password', 'danger');
        header('Location: login.php');
        exit();
    }
}

// REGISTRATION AUTHENTICATION
elseif ($action === 'register') {
    $first_name = sanitize($_POST['first_name']);
    $last_name = sanitize($_POST['last_name']);
    $email = sanitize($_POST['email']);
    $phone = sanitize($_POST['phone']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validate inputs
    if (empty($first_name) || empty($last_name) || empty($email) || empty($password)) {
        setFlashMessage('Please fill in all required fields', 'danger');
        header('Location: register.php');
        exit();
    }
    
    if (!validateEmail($email)) {
        setFlashMessage('Please enter a valid email address', 'danger');
        header('Location: register.php');
        exit();
    }
    
    if (strlen($password) < 6) {
        setFlashMessage('Password must be at least 6 characters long', 'danger');
        header('Location: register.php');
        exit();
    }
    
    if ($password !== $confirm_password) {
        setFlashMessage('Passwords do not match', 'danger');
        header('Location: register.php');
        exit();
    }
    
    // Check if terms accepted
    if (!isset($_POST['terms'])) {
        setFlashMessage('Please accept the terms and conditions', 'danger');
        header('Location: register.php');
        exit();
    }
    
    // Check if email already exists
    $check_query = "SELECT id FROM users WHERE email = ?";
    $stmt = mysqli_prepare($conn, $check_query);
    mysqli_stmt_bind_param($stmt, "s", $email);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if (mysqli_num_rows($result) > 0) {
        setFlashMessage('Email address already registered', 'danger');
        header('Location: register.php');
        exit();
    }
    
    // Start transaction
    mysqli_begin_transaction($conn);
    
    try {
        // Insert user
        $hashed_password = hashPassword($password);
        $insert_user = "INSERT INTO users (email, password, role, status) VALUES (?, ?, 'employee', 'active')";
        $stmt = mysqli_prepare($conn, $insert_user);
        mysqli_stmt_bind_param($stmt, "ss", $email, $hashed_password);
        mysqli_stmt_execute($stmt);
        $user_id = mysqli_insert_id($conn);
        
        // Generate employee ID
        $employee_id = 'EMP' . str_pad($user_id, 4, '0', STR_PAD_LEFT);
        
        // Insert employee record
        $insert_employee = "INSERT INTO employees (user_id, employee_id, first_name, last_name, phone, date_of_joining) 
                           VALUES (?, ?, ?, ?, ?, CURDATE())";
        $stmt = mysqli_prepare($conn, $insert_employee);
        mysqli_stmt_bind_param($stmt, "issss", $user_id, $employee_id, $first_name, $last_name, $phone);
        mysqli_stmt_execute($stmt);
        $emp_id = mysqli_insert_id($conn);
        
        // Create initial leave balance for current year
        $current_year = date('Y');
        $insert_balance = "INSERT INTO leave_balance (employee_id, year) VALUES (?, ?)";
        $stmt = mysqli_prepare($conn, $insert_balance);
        mysqli_stmt_bind_param($stmt, "ii", $emp_id, $current_year);
        mysqli_stmt_execute($stmt);
        
        // Commit transaction
        mysqli_commit($conn);
        
        // Send welcome email (optional - implement later)
        // sendWelcomeEmail($email, $first_name);
        
        setFlashMessage('Registration successful! Please login to continue.', 'success');
        header('Location: login.php');
        exit();
        
    } catch (Exception $e) {
        // Rollback on error
        mysqli_rollback($conn);
        setFlashMessage('Registration failed. Please try again.', 'danger');
        header('Location: register.php');
        exit();
    }
}

// If no valid action, redirect to login
else {
    header('Location: login.php');
    exit();
}
?>