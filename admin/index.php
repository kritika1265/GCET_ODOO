<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit();
}

$user_name = $_SESSION['user_name'];

// Fetch dashboard statistics
$conn = getDBConnection();

// Total employees
$result = $conn->query("SELECT COUNT(*) as total FROM users WHERE role = 'employee'");
$total_employees = $result->fetch_assoc()['total'];

// Today's attendance
$today = date('Y-m-d');
$result = $conn->query("SELECT COUNT(DISTINCT user_id) as present FROM attendance WHERE date = '$today' AND status = 'present'");
$present_today = $result->fetch_assoc()['present'];

// Pending leave requests
$result = $conn->query("SELECT COUNT(*) as pending FROM leave_requests WHERE status = 'pending'");
$pending_leaves = $result->fetch_assoc()['pending'];

// Recent leave requests
$recent_leaves = $conn->query("SELECT lr.*, u.full_name, u.email FROM leave_requests lr 
                               JOIN users u ON lr.user_id = u.id 
                               WHERE lr.status = 'pending' 
                               ORDER BY lr.created_at DESC LIMIT 5");

$conn->close();

include '../includes/header.php';
?>

<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-12">
            <h2 class="mb-4">Admin Dashboard</h2>
            <p class="text-muted">Welcome back, <?php echo htmlspecialchars($user_name); ?>!</p>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-4 mb-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <h5 class="card-title">Total Employees</h5>
                    <h2 class="display-4"><?php echo $total_employees; ?></h2>
                    <a href="employees.php" class="btn btn-light btn-sm mt-2">View All</a>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h5 class="card-title">Present Today</h5>
                    <h2 class="display-4"><?php echo $present_today; ?></h2>
                    <a href="attendance.php" class="btn btn-light btn-sm mt-2">View Attendance</a>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="card bg-warning text-white">
                <div class="card-body">
                    <h5 class="card-title">Pending Leave Requests</h5>
                    <h2 class="display-4"><?php echo $pending_leaves; ?></h2>
                    <a href="leave-requests.php" class="btn btn-light btn-sm mt-2">Review Requests</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Leave Requests -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Recent Leave Requests</h5>
                </div>
                <div class="card-body">
                    <?php if ($recent_leaves->num_rows > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Employee</th>
                                        <th>Leave Type</th>
                                        <th>From</th>
                                        <th>To</th>
                                        <th>Days</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while($leave = $recent_leaves->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($leave['full_name']); ?></td>
                                            <td><?php echo htmlspecialchars($leave['leave_type']); ?></td>
                                            <td><?php echo date('M d, Y', strtotime($leave['start_date'])); ?></td>
                                            <td><?php echo date('M d, Y', strtotime($leave['end_date'])); ?></td>
                                            <td><?php echo $leave['days']; ?></td>
                                            <td><span class="badge bg-warning">Pending</span></td>
                                            <td>
                                                <a href="leave-requests.php?id=<?php echo $leave['id']; ?>" class="btn btn-sm btn-primary">Review</a>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-muted">No pending leave requests.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
