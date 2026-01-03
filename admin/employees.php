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

// Handle employee deletion
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $employee_id = (int)$_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM users WHERE id = ? AND role = 'employee'");
    $stmt->bind_param("i", $employee_id);
    
    if ($stmt->execute()) {
        $message = "Employee deleted successfully!";
        $message_type = "success";
    } else {
        $message = "Error deleting employee.";
        $message_type = "danger";
    }
    $stmt->close();
}

// Handle add/edit employee
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $department = trim($_POST['department']);
    $position = trim($_POST['position']);
    $join_date = $_POST['join_date'];
    $employee_id = isset($_POST['employee_id']) ? (int)$_POST['employee_id'] : 0;
    
    if ($employee_id > 0) {
        // Update existing employee
        $stmt = $conn->prepare("UPDATE users SET full_name=?, email=?, phone=?, department=?, position=?, join_date=? WHERE id=? AND role='employee'");
        $stmt->bind_param("ssssssi", $full_name, $email, $phone, $department, $position, $join_date, $employee_id);
        
        if ($stmt->execute()) {
            $message = "Employee updated successfully!";
            $message_type = "success";
        } else {
            $message = "Error updating employee.";
            $message_type = "danger";
        }
    } else {
        // Add new employee
        $password = password_hash('password123', PASSWORD_DEFAULT); // Default password
        $role = 'employee';
        
        $stmt = $conn->prepare("INSERT INTO users (full_name, email, password, phone, department, position, join_date, role) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssssss", $full_name, $email, $password, $phone, $department, $position, $join_date, $role);
        
        if ($stmt->execute()) {
            $message = "Employee added successfully! Default password: password123";
            $message_type = "success";
        } else {
            $message = "Error adding employee. Email might already exist.";
            $message_type = "danger";
        }
    }
    $stmt->close();
}

// Fetch all employees
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$query = "SELECT * FROM users WHERE role = 'employee'";
if ($search) {
    $query .= " AND (full_name LIKE '%$search%' OR email LIKE '%$search%' OR department LIKE '%$search%')";
}
$query .= " ORDER BY full_name ASC";
$employees = $conn->query($query);

include '../includes/header.php';
?>

<div class="container-fluid mt-4">
    <div class="row mb-4">
        <div class="col-md-6">
            <h2>Manage Employees</h2>
        </div>
        <div class="col-md-6 text-end">
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addEmployeeModal">
                <i class="bi bi-plus-circle"></i> Add Employee
            </button>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Search Bar -->
    <div class="row mb-3">
        <div class="col-md-6">
            <form method="GET" class="d-flex">
                <input type="text" name="search" class="form-control me-2" placeholder="Search by name, email, or department" value="<?php echo htmlspecialchars($search); ?>">
                <button type="submit" class="btn btn-secondary">Search</button>
            </form>
        </div>
    </div>

    <!-- Employees Table -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Department</th>
                            <th>Position</th>
                            <th>Join Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($employees->num_rows > 0): ?>
                            <?php while($emp = $employees->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo $emp['id']; ?></td>
                                    <td><?php echo htmlspecialchars($emp['full_name']); ?></td>
                                    <td><?php echo htmlspecialchars($emp['email']); ?></td>
                                    <td><?php echo htmlspecialchars($emp['phone']); ?></td>
                                    <td><?php echo htmlspecialchars($emp['department']); ?></td>
                                    <td><?php echo htmlspecialchars($emp['position']); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($emp['join_date'])); ?></td>
                                    <td>
                                        <button class="btn btn-sm btn-info edit-btn" 
                                                data-id="<?php echo $emp['id']; ?>"
                                                data-name="<?php echo htmlspecialchars($emp['full_name']); ?>"
                                                data-email="<?php echo htmlspecialchars($emp['email']); ?>"
                                                data-phone="<?php echo htmlspecialchars($emp['phone']); ?>"
                                                data-department="<?php echo htmlspecialchars($emp['department']); ?>"
                                                data-position="<?php echo htmlspecialchars($emp['position']); ?>"
                                                data-joindate="<?php echo $emp['join_date']; ?>"
                                                data-bs-toggle="modal" 
                                                data-bs-target="#addEmployeeModal">
                                            Edit
                                        </button>
                                        <a href="?delete=<?php echo $emp['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this employee?')">Delete</a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-center">No employees found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add/Edit Employee Modal -->
<div class="modal fade" id="addEmployeeModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">Add Employee</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="employee_id" id="employee_id">
                    
                    <div class="mb-3">
                        <label for="full_name" class="form-label">Full Name *</label>
                        <input type="text" class="form-control" id="full_name" name="full_name" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="email" class="form-label">Email *</label>
                        <input type="email" class="form-control" id="email" name="email" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="phone" class="form-label">Phone</label>
                        <input type="text" class="form-control" id="phone" name="phone">
                    </div>
                    
                    <div class="mb-3">
                        <label for="department" class="form-label">Department *</label>
                        <input type="text" class="form-control" id="department" name="department" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="position" class="form-label">Position *</label>
                        <input type="text" class="form-control" id="position" name="position" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="join_date" class="form-label">Join Date *</label>
                        <input type="date" class="form-control" id="join_date" name="join_date" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Employee</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Edit button functionality
document.querySelectorAll('.edit-btn').forEach(button => {
    button.addEventListener('click', function() {
        document.getElementById('modalTitle').textContent = 'Edit Employee';
        document.getElementById('employee_id').value = this.dataset.id;
        document.getElementById('full_name').value = this.dataset.name;
        document.getElementById('email').value = this.dataset.email;
        document.getElementById('phone').value = this.dataset.phone;
        document.getElementById('department').value = this.dataset.department;
        document.getElementById('position').value = this.dataset.position;
        document.getElementById('join_date').value = this.dataset.joindate;
    });
});

// Reset form when adding new employee
document.getElementById('addEmployeeModal').addEventListener('hidden.bs.modal', function () {
    document.getElementById('modalTitle').textContent = 'Add Employee';
    document.getElementById('employee_id').value = '';
    document.querySelector('#addEmployeeModal form').reset();
});
</script>

<?php 
$conn->close();
include '../includes/footer.php'; 
?>
