<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'employee') {
    header('Location: ../auth/login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$conn = getDBConnection();
$success_message = '';
$error_message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = sanitizeInput($_POST['first_name']);
    $last_name = sanitizeInput($_POST['last_name']);
    $email = sanitizeInput($_POST['email']);
    $phone = sanitizeInput($_POST['phone']);
    $address = sanitizeInput($_POST['address']);
    $date_of_birth = sanitizeInput($_POST['date_of_birth']);
    $emergency_contact = sanitizeInput($_POST['emergency_contact']);
    $emergency_phone = sanitizeInput($_POST['emergency_phone']);
    
    // Handle profile picture upload
    $profile_picture = null;
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === 0) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/jpg'];
        $file_type = $_FILES['profile_picture']['type'];
        
        if (in_array($file_type, $allowed_types)) {
            $upload_dir = '../uploads/profile_pictures/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $file_extension = pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION);
            $new_filename = 'profile_' . $user_id . '_' . time() . '.' . $file_extension;
            $upload_path = $upload_dir . $new_filename;
            
            if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $upload_path)) {
                $profile_picture = $new_filename;
            }
        }
    }
    
    // Update profile
    if ($profile_picture) {
        $stmt = $conn->prepare("
            UPDATE employees 
            SET first_name = ?, last_name = ?, email = ?, phone = ?, 
                address = ?, date_of_birth = ?, emergency_contact = ?, 
                emergency_phone = ?, profile_picture = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->bind_param("sssssssssi", $first_name, $last_name, $email, $phone, 
                         $address, $date_of_birth, $emergency_contact, 
                         $emergency_phone, $profile_picture, $user_id);
    } else {
        $stmt = $conn->prepare("
            UPDATE employees 
            SET first_name = ?, last_name = ?, email = ?, phone = ?, 
                address = ?, date_of_birth = ?, emergency_contact = ?, 
                emergency_phone = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->bind_param("ssssssssi", $first_name, $last_name, $email, $phone, 
                         $address, $date_of_birth, $emergency_contact, 
                         $emergency_phone, $user_id);
    }
    
    if ($stmt->execute()) {
        $success_message = "Profile updated successfully!";
    } else {
        $error_message = "Error updating profile. Please try again.";
    }
}

// Fetch current employee data
$stmt = $conn->prepare("SELECT * FROM employees WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$employee = $stmt->get_result()->fetch_assoc();

include '../includes/header.php';
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-md-3 mb-4">
            <!-- Profile Sidebar -->
            <div class="card text-center">
                <div class="card-body">
                    <?php if ($employee['profile_picture']): ?>
                        <img src="../uploads/profile_pictures/<?php echo htmlspecialchars($employee['profile_picture']); ?>" 
                             class="rounded-circle mb-3" 
                             style="width: 150px; height: 150px; object-fit: cover;" 
                             alt="Profile Picture">
                    <?php else: ?>
                        <div class="rounded-circle bg-secondary text-white d-inline-flex align-items-center justify-content-center mb-3" 
                             style="width: 150px; height: 150px; font-size: 48px;">
                            <?php echo strtoupper(substr($employee['first_name'], 0, 1)); ?>
                        </div>
                    <?php endif; ?>
                    
                    <h4><?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?></h4>
                    <p class="text-muted mb-1">Employee ID: <?php echo htmlspecialchars($employee['employee_code']); ?></p>
                    <p class="text-muted"><?php echo htmlspecialchars($employee['department'] ?? 'N/A'); ?></p>
                    
                    <hr>
                    
                    <div class="text-start">
                        <p class="mb-2"><strong>Join Date:</strong><br>
                        <?php echo date('M d, Y', strtotime($employee['join_date'])); ?></p>
                        <p class="mb-2"><strong>Status:</strong><br>
                        <span class="badge bg-success"><?php echo ucfirst($employee['status']); ?></span></p>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-9">
            <!-- Profile Form -->
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Edit Profile</h5>
                </div>
                <div class="card-body">
                    <?php if ($success_message): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <?php echo $success_message; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($error_message): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <?php echo $error_message; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <form method="POST" enctype="multipart/form-data">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">First Name *</label>
                                <input type="text" class="form-control" name="first_name" 
                                       value="<?php echo htmlspecialchars($employee['first_name']); ?>" required>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Last Name *</label>
                                <input type="text" class="form-control" name="last_name" 
                                       value="<?php echo htmlspecialchars($employee['last_name']); ?>" required>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Email *</label>
                                <input type="email" class="form-control" name="email" 
                                       value="<?php echo htmlspecialchars($employee['email']); ?>" required>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Phone *</label>
                                <input type="tel" class="form-control" name="phone" 
                                       value="<?php echo htmlspecialchars($employee['phone']); ?>" required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Date of Birth</label>
                            <input type="date" class="form-control" name="date_of_birth" 
                                   value="<?php echo htmlspecialchars($employee['date_of_birth']); ?>">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Address</label>
                            <textarea class="form-control" name="address" rows="3"><?php echo htmlspecialchars($employee['address']); ?></textarea>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Emergency Contact Name</label>
                                <input type="text" class="form-control" name="emergency_contact" 
                                       value="<?php echo htmlspecialchars($employee['emergency_contact']); ?>">
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Emergency Contact Phone</label>
                                <input type="tel" class="form-control" name="emergency_phone" 
                                       value="<?php echo htmlspecialchars($employee['emergency_phone']); ?>">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Profile Picture</label>
                            <input type="file" class="form-control" name="profile_picture" accept="image/*">
                            <small class="text-muted">Accepted formats: JPG, PNG (Max 2MB)</small>
                        </div>

                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                            <a href="index.php" class="btn btn-secondary">Cancel</a>
                            <button type="submit" class="btn btn-primary">Save Changes</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Password Change Section -->
            <div class="card mt-4">
                <div class="card-header bg-warning">
                    <h5 class="mb-0">Change Password</h5>
                </div>
                <div class="card-body">
                    <form id="passwordForm">
                        <div class="mb-3">
                            <label class="form-label">Current Password</label>
                            <input type="password" class="form-control" name="current_password" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">New Password</label>
                            <input type="password" class="form-control" name="new_password" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Confirm New Password</label>
                            <input type="password" class="form-control" name="confirm_password" required>
                        </div>
                        <button type="submit" class="btn btn-warning">Update Password</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('passwordForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    
    fetch('../api/profile.php?action=change_password', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Password updated successfully!');
            this.reset();
        } else {
            alert(data.message || 'Error updating password');
        }
    })
    .catch(error => {
        alert('An error occurred. Please try again.');
    });
});
</script>

<?php include '../includes/footer.php'; ?>