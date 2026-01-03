<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and is an employee
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'employee') {
    header('Location: ../auth/login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$conn = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);

// Check connection
if ($conn->connect_error) {
    die("Connection fail        ed: " . $conn->connect_error);
}

// Fetch employee details
$stmt = $conn->prepare("SELECT * FROM employees WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$employee = $stmt->get_result()->fetch_assoc();

// Get attendance summary for current month
$current_month = date('Y-m');
$stmt = $conn->prepare("
    SELECT 
        COUNT(*) as total_days,
        SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present_days,
        SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent_days,
        SUM(CASE WHEN status = 'half_day' THEN 1 ELSE 0 END) as half_days
    FROM attendance 
    WHERE employee_id = ? AND DATE_FORMAT(date, '%Y-%m') = ?
");
$stmt->bind_param("is", $user_id, $current_month);
$stmt->execute();
$attendance_summary = $stmt->get_result()->fetch_assoc();

// Get leave summary
$stmt = $conn->prepare("
    SELECT 
        leave_type,
        SUM(CASE WHEN status = 'approved' THEN days_count ELSE 0 END) as used_days
    FROM leave_requests 
    WHERE employee_id = ? AND YEAR(from_date) = YEAR(CURDATE())
    GROUP BY leave_type
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$leave_summary = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get recent leave requests
$stmt = $conn->prepare("
    SELECT * FROM leave_requests 
    WHERE employee_id = ? 
    ORDER BY created_at DESC 
    LIMIT 5
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$recent_leaves = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Check today's attendance
$today = date('Y-m-d');
$stmt = $conn->prepare("SELECT * FROM attendance WHERE employee_id = ? AND date = ?");
$stmt->bind_param("is", $user_id, $today);
$stmt->execute();
$today_attendance = $stmt->get_result()->fetch_assoc();

include '../includes/header.php';
?>

<div class="container-fluid mt-4">
    <div class="row">
        <!-- Welcome Section -->
        <div class="col-12 mb-4">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <h3 class="mb-0">Welcome back, <?php echo htmlspecialchars($employee['first_name']); ?>! 👋</h3>
                    <p class="mb-0">Employee ID: <?php echo htmlspecialchars($employee['employee_code']); ?></p>
                </div>
            </div>
        </div>

        <!-- Quick Stats -->
        <div class="col-md-3 mb-4">
            <div class="card text-center">
                <div class="card-body">
                    <h5 class="text-muted">Present Days</h5>
                    <h2 class="text-success"><?php echo $attendance_summary['present_days'] ?? 0; ?></h2>
                    <small class="text-muted">This Month</small>
                </div>
            </div>
        </div>

        <div class="col-md-3 mb-4">
            <div class="card text-center">
                <div class="card-body">
                    <h5 class="text-muted">Absent Days</h5>
                    <h2 class="text-danger"><?php echo $attendance_summary['absent_days'] ?? 0; ?></h2>
                    <small class="text-muted">This Month</small>
                </div>
            </div>
        </div>

        <div class="col-md-3 mb-4">
            <div class="card text-center">
                <div class="card-body">
                    <h5 class="text-muted">Half Days</h5>
                    <h2 class="text-warning"><?php echo $attendance_summary['half_days'] ?? 0; ?></h2>
                    <small class="text-muted">This Month</small>
                </div>
            </div>
        </div>

        <div class="col-md-3 mb-4">
            <div class="card text-center">
                <div class="card-body">
                    <h5 class="text-muted">Leave Balance</h5>
                    <h2 class="text-info">
                        <?php 
                        $total_used = array_sum(array_column($leave_summary, 'used_days'));
                        echo (20 - $total_used); // Assuming 20 days annual leave
                        ?>
                    </h2>
                    <small class="text-muted">Days Remaining</small>
                </div>
            </div>
        </div>

        <!-- Today's Attendance -->
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0">Today's Attendance</h5>
                </div>
                <div class="card-body">
                    <?php if ($today_attendance): ?>
                        <div class="alert alert-success">
                            <strong>✓ Marked as <?php echo ucfirst($today_attendance['status']); ?></strong><br>
                            <small>Check-in: <?php echo date('h:i A', strtotime($today_attendance['check_in'])); ?></small>
                            <?php if ($today_attendance['check_out']): ?>
                                <br><small>Check-out: <?php echo date('h:i A', strtotime($today_attendance['check_out'])); ?></small>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-warning">
                            <strong>⚠ Not marked yet</strong>
                        </div>
                        <a href="attendance.php" class="btn btn-primary">Mark Attendance</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header bg-secondary text-white">
                    <h5 class="mb-0">Quick Actions</h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="attendance.php" class="btn btn-outline-primary">
                            📅 View Attendance
                        </a>
                        <a href="leave-request.php" class="btn btn-outline-success">
                            ✉️ Request Leave
                        </a>
                        <a href="profile.php" class="btn btn-outline-info">
                            👤 Update Profile
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Leave Requests -->
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-dark text-white">
                    <h5 class="mb-0">Recent Leave Requests</h5>
                </div>
                <div class="card-body">
                    <?php if (count($recent_leaves) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Leave Type</th>
                                        <th>From</th>
                                        <th>To</th>
                                        <th>Days</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_leaves as $leave): ?>
                                        <tr>
                                            <td><?php echo ucfirst(str_replace('_', ' ', $leave['leave_type'])); ?></td>
                                            <td><?php echo date('M d, Y', strtotime($leave['from_date'])); ?></td>
                                            <td><?php echo date('M d, Y', strtotime($leave['to_date'])); ?></td>
                                            <td><?php echo $leave['days_count']; ?></td>
                                            <td>
                                                <?php
                                                $badge_class = 'secondary';
                                                if ($leave['status'] == 'approved') $badge_class = 'success';
                                                elseif ($leave['status'] == 'rejected') $badge_class = 'danger';
                                                elseif ($leave['status'] == 'pending') $badge_class = 'warning';
                                                ?>
                                                <span class="badge bg-<?php echo $badge_class; ?>">
                                                    <?php echo ucfirst($leave['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <a href="leave-history.php?id=<?php echo $leave['id']; ?>" class="btn btn-sm btn-outline-primary">View</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-muted text-center">No leave requests found.</p>
                    <?php endif; ?>
                    <div class="text-center mt-3">
                        <a href="leave-history.php" class="btn btn-outline-secondary">View All Requests</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>