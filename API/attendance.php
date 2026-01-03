<?php
// api/profile.php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];

switch ($action) {
    case 'get_profile':
        getProfile($conn, $user_id, $user_role);
        break;
    
    case 'update_profile':
        updateProfile($conn, $user_id);
        break;
    
    case 'change_password':
        changePassword($conn, $user_id);
        break;
    
    case 'upload_picture':
        uploadProfilePicture($conn, $user_id);
        break;
    
    case 'update_employee':
        if ($user_role === 'admin') {
            updateEmployee($conn);
        } else {
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        }
        break;
    
    case 'delete_employee':
        if ($user_role === 'admin') {
            deleteEmployee($conn);
        } else {
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        }
        break;
    
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}

function getProfile($conn, $user_id, $user_role) {
    $profile_id = ($user_role === 'admin' && isset($_GET['user_id'])) ? $_GET['user_id'] : $user_id;
    
    $stmt = $conn->prepare("SELECT id, name, email, phone, address, city, state, country, zip_code, department, position, join_date, date_of_birth, profile_picture, role FROM users WHERE id = ?");
    $stmt->bind_param("i", $profile_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $profile = $result->fetch_assoc();
        
        // Don't send sensitive data
        unset($profile['password']);
        
        echo json_encode(['success' => true, 'data' => $profile]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Profile not found']);
    }
    
    $stmt->close();
}

function updateProfile($conn, $user_id) {
    $name = $_POST['name'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $address = $_POST['address'] ?? '';
    $city = $_POST['city'] ?? '';
    $state = $_POST['state'] ?? '';
    $country = $_POST['country'] ?? '';
    $zip_code = $_POST['zip_code'] ?? '';
    $date_of_birth = $_POST['date_of_birth'] ?? null;
    
    // Validate required fields
    if (empty($name)) {
        echo json_encode(['success' => false, 'message' => 'Name is required']);
        return;
    }
    
    $sql = "UPDATE users SET name = ?, phone = ?, address = ?, city = ?, state = ?, country = ?, zip_code = ?";
    $params = [$name, $phone, $address, $city, $state, $country, $zip_code];
    $types = "sssssss";
    
    if ($date_of_birth) {
        $sql .= ", date_of_birth = ?";
        $params[] = $date_of_birth;
        $types .= "s";
    }
    
    $sql .= " WHERE id = ?";
    $params[] = $user_id;
    $types .= "i";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    
    if ($stmt->execute()) {
        // Update session name if changed
        $_SESSION['name'] = $name;
        
        echo json_encode(['success' => true, 'message' => 'Profile updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update profile']);
    }
    
    $stmt->close();
}

function changePassword($conn, $user_id) {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Validate passwords
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        echo json_encode(['success' => false, 'message' => 'All password fields are required']);
        return;
    }
    
    if ($new_password !== $confirm_password) {
        echo json_encode(['success' => false, 'message' => 'New passwords do not match']);
        return;
    }
    
    if (strlen($new_password) < 6) {
        echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters']);
        return;
    }
    
    // Verify current password
    $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
    
    if (!password_verify($current_password, $user['password'])) {
        echo json_encode(['success' => false, 'message' => 'Current password is incorrect']);
        return;
    }
    
    // Update password
    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
    $update = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
    $update->bind_param("si", $hashed_password, $user_id);
    
    if ($update->execute()) {
        echo json_encode(['success' => true, 'message' => 'Password changed successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to change password']);
    }
    
    $update->close();
}

function uploadProfilePicture($conn, $user_id) {
    if (!isset($_FILES['profile_picture']) || $_FILES['profile_picture']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'message' => 'No file uploaded or upload error']);
        return;
    }
    
    $file = $_FILES['profile_picture'];
    $allowed_types = ['image/jpeg', 'image/png', 'image/jpg', 'image/gif'];
    $max_size = 2 * 1024 * 1024; // 2MB
    
    // Validate file type
    if (!in_array($file['type'], $allowed_types)) {
        echo json_encode(['success' => false, 'message' => 'Invalid file type. Only JPG, PNG and GIF are allowed']);
        return;
    }
    
    // Validate file size
    if ($file['size'] > $max_size) {
        echo json_encode(['success' => false, 'message' => 'File size exceeds 2MB limit']);
        return;
    }
    
    // Generate unique filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'profile_' . $user_id . '_' . time() . '.' . $extension;
    $upload_path = '../uploads/profile_pictures/';
    
    // Create directory if not exists
    if (!is_dir($upload_path)) {
        mkdir($upload_path, 0755, true);
    }
    
    $target_file = $upload_path . $filename;
    
    // Delete old profile picture
    $old_pic_stmt = $conn->prepare("SELECT profile_picture FROM users WHERE id = ?");
    $old_pic_stmt->bind_param("i", $user_id);
    $old_pic_stmt->execute();
    $old_result = $old_pic_stmt->get_result();
    $old_data = $old_result->fetch_assoc();
    
    if ($old_data['profile_picture'] && file_exists('../' . $old_data['profile_picture'])) {
        unlink('../' . $old_data['profile_picture']);
    }
    $old_pic_stmt->close();
    
    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $target_file)) {
        $db_path = 'uploads/profile_pictures/' . $filename;
        
        // Update database
        $stmt = $conn->prepare("UPDATE users SET profile_picture = ? WHERE id = ?");
        $stmt->bind_param("si", $db_path, $user_id);
        
        if ($stmt->execute()) {
            echo json_encode([
                'success' => true, 
                'message' => 'Profile picture uploaded successfully',
                'picture_url' => $db_path
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update database']);
        }
        
        $stmt->close();
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to upload file']);
    }
}

function updateEmployee($conn) {
    $employee_id = $_POST['employee_id'] ?? 0;
    $name = $_POST['name'] ?? '';
    $email = $_POST['email'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $department = $_POST['department'] ?? '';
    $position = $_POST['position'] ?? '';
    $join_date = $_POST['join_date'] ?? null;
    $role = $_POST['role'] ?? 'employee';
    
    // Validate required fields
    if (empty($name) || empty($email)) {
        echo json_encode(['success' => false, 'message' => 'Name and email are required']);
        return;
    }
    
    // Check if email already exists for another user
    $check = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
    $check->bind_param("si", $email, $employee_id);
    $check->execute();
    $check_result = $check->get_result();
    
    if ($check_result->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Email already exists']);
        $check->close();
        return;
    }
    $check->close();
    
    $stmt = $conn->prepare("UPDATE users SET name = ?, email = ?, phone = ?, department = ?, position = ?, join_date = ?, role = ? WHERE id = ?");
    $stmt->bind_param("sssssssi", $name, $email, $phone, $department, $position, $join_date, $role, $employee_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Employee updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update employee']);
    }
    
    $stmt->close();
}

function deleteEmployee($conn) {
    $employee_id = $_POST['employee_id'] ?? 0;
    
    // Don't allow deleting yourself
    if ($employee_id == $_SESSION['user_id']) {
        echo json_encode(['success' => false, 'message' => 'Cannot delete your own account']);
        return;
    }
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Delete related records
        $conn->query("DELETE FROM attendance WHERE user_id = $employee_id");
        $conn->query("DELETE FROM leave_requests WHERE user_id = $employee_id");
        $conn->query("DELETE FROM leave_balance WHERE user_id = $employee_id");
        
        // Get profile picture to delete
        $pic_stmt = $conn->prepare("SELECT profile_picture FROM users WHERE id = ?");
        $pic_stmt->bind_param("i", $employee_id);
        $pic_stmt->execute();
        $pic_result = $pic_stmt->get_result();
        $pic_data = $pic_result->fetch_assoc();
        
        if ($pic_data['profile_picture'] && file_exists('../' . $pic_data['profile_picture'])) {
            unlink('../' . $pic_data['profile_picture']);
        }
        $pic_stmt->close();
        
        // Delete user
        $delete = $conn->prepare("DELETE FROM users WHERE id = ?");
        $delete->bind_param("i", $employee_id);
        $delete->execute();
        $delete->close();
        
        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'Employee deleted successfully']);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Failed to delete employee: ' . $e->getMessage()]);
    }
}

$conn->close();
?>
