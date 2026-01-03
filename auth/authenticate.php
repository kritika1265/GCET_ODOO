<?php
session_start();
require_once '../config/database.php';
require_once '../config/constants.php';

// Redirect if already logged in
if(isset($_SESSION['role'])) {
    if($_SESSION['role'] === 'admin') {
        header("Location: ../admin/index.php");
        exit;
    } else {
        header("Location: ../employee/index.php");
        exit;
    }
}

// If page opened without POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.php');
    exit();
}

$action = isset($_POST['action']) ? $_POST['action'] : '';

/* ---------------------- FLASH MESSAGE ---------------------- */
function flash($msg,$type){
    $_SESSION['flash'] = [
        "message"=>$msg,
        "type"=>$type
    ];
}

/* ---------------------- LOGIN ---------------------- */
if ($action === 'login') {

    // sanitize email
    $email = trim(htmlspecialchars($_POST['email']));
    $password = $_POST['password'];

    if(empty($email) || empty($password)){
        flash("Please fill in all fields","danger");
        header("Location: login.php");
        exit;
    }

    if(!filter_var($email, FILTER_VALIDATE_EMAIL)){
        flash("Please enter a valid email address","danger");
        header("Location: login.php");
        exit;
    }

    $query = "SELECT u.*, e.id AS employee_id, e.first_name, e.last_name 
              FROM users u 
              LEFT JOIN employees e ON u.id = e.user_id 
              WHERE u.email = ? AND u.status = 'active'";

    $stmt = mysqli_prepare($conn,$query);
    mysqli_stmt_bind_param($stmt,"s",$email);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if($user = mysqli_fetch_assoc($result)){

        // Verify password (assuming password_hash used earlier)
        if(password_verify($password,$user['password'])){

            $_SESSION['user_id'] = $user['id'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['employee_id'] = $user['employee_id'];
            $_SESSION['name'] = $user['first_name'] . ' ' . $user['last_name'];
            $_SESSION['logged_in_at'] = time();

            // Remember me
            if(isset($_POST['remember'])){
                setcookie("remember_user",$user['id'],time()+60*60*24*30,'/');
            }

            // Redirect
            if($user['role'] === ROLE_ADMIN){
                header("Location: ../admin/index.php");
            } else {
                header("Location: ../employee/index.php");
            }
            exit;

        } else {
            flash("Invalid email or password","danger");
            header("Location: login.php");
            exit;
        }

    } else {
        flash("Invalid email or password","danger");
        header("Location: login.php");
        exit;
    }
}


/* ---------------------- REGISTER ---------------------- */
elseif ($action === 'register') {

    $first_name = trim(htmlspecialchars($_POST['first_name']));
    $last_name = trim(htmlspecialchars($_POST['last_name']));
    $email = trim(htmlspecialchars($_POST['email']));
    $phone = trim(htmlspecialchars($_POST['phone']));
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    if(empty($first_name) || empty($last_name) || empty($email) || empty($password)){
        flash("Please fill in all required fields","danger");
        header("Location: register.php");
        exit;
    }

    if(!filter_var($email,FILTER_VALIDATE_EMAIL)){
        flash("Please enter a valid email","danger");
        header("Location: register.php");
        exit;
    }

    if(strlen($password) < 6){
        flash("Password must be at least 6 characters","danger");
        header("Location: register.php");
        exit;
    }

    if($password !== $confirm_password){
        flash("Passwords do not match","danger");
        header("Location: register.php");
        exit;
    }

    if(!isset($_POST['terms'])){
        flash("Please accept terms and conditions","danger");
        header("Location: register.php");
        exit;
    }

    // Check email already exists
    $check = mysqli_prepare($conn,"SELECT id FROM users WHERE email = ?");
    mysqli_stmt_bind_param($check,"s",$email);
    mysqli_stmt_execute($check);
    $res = mysqli_stmt_get_result($check);

    if(mysqli_num_rows($res) > 0){
        flash("Email already registered","danger");
        header("Location: register.php");
        exit;
    }

    mysqli_begin_transaction($conn);

    try {

        $hashed = password_hash($password,PASSWORD_BCRYPT);

        // Insert user
        $insert_user = mysqli_prepare($conn,
            "INSERT INTO users (email,password,role,status) VALUES (?,?,'employee','active')");
        mysqli_stmt_bind_param($insert_user,"ss",$email,$hashed);
        mysqli_stmt_execute($insert_user);

        $user_id = mysqli_insert_id($conn);

        // Employee ID
        $emp_code = "EMP".str_pad($user_id,4,'0',STR_PAD_LEFT);

        // Insert employee
        $emp = mysqli_prepare($conn,
        "INSERT INTO employees(user_id,employee_id,first_name,last_name,phone,date_of_joining)
         VALUES (?,?,?,?,?,CURDATE())");

        mysqli_stmt_bind_param($emp,"issss",$user_id,$emp_code,$first_name,$last_name,$phone);
        mysqli_stmt_execute($emp);

        $emp_table_id = mysqli_insert_id($conn);

        // Leave balance
        $year = date("Y");
        $leave = mysqli_prepare($conn,
        "INSERT INTO leave_balance(employee_id,year) VALUES (?,?)");
        mysqli_stmt_bind_param($leave,"ii",$emp_table_id,$year);
        mysqli_stmt_execute($leave);

        mysqli_commit($conn);

        flash("Registration successful! Please login.","success");
        header("Location: login.php");
        exit;

    } catch (Exception $e){
        mysqli_rollback($conn);
        flash("Registration failed. Try again.","danger");
        header("Location: register.php");
        exit;
    }

}

/* ---------------------- INVALID ACTION ---------------------- */
else {
    header("Location: login.php");
    exit;
}
?>
