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

// Get filter parameters
$selected_date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$view_type = isset($_GET['view']) ? $_GET['view'] : 'daily';
$search_employee = isset($_GET['employee']) ? trim($_GET['employee']) : '';

// Calculate date range based on view type
if ($view_type === 'weekly') {
    $start_date = date('Y-m-d', strtotime('monday this week', strtotime($selected_date)));
    $end_date = date('Y-m-d', strtotime('sunday this week', strtotime($selected_date)));
} else {
    $start_date = $selected_date;
    $end_date = $selected_date;
}

// Fetch attendance records
$query = "SELECT a.*, u.full_name, u.email, u.department 
          FROM attendance a 
          JOIN users u ON a.user_id = u.id 
          WHERE a.date BETWEEN '$start_date' AND '$end_date'";

if ($search_employee) {
    $query .= " AND u.full_name LIKE '%$search_employee%'";
}

$query .= " ORDER BY a.date DESC, u.full_name ASC";
$attendance_records = $conn->query($query);

// Calculate statistics
$stats_query = "SELECT 
                COUNT(CASE WHEN status = 'present' THEN 1 END) as present_count,
                COUNT(CASE WHEN status = 'absent' THEN 1 END) as absent_count,
                COUNT(CASE WHEN status = 'late' THEN 1 END) as late_count,
                COUNT(CASE WHEN status = 'half_day' THEN 1 END) as half_day_count
                FROM attendance 
                WHERE date BETWEEN '$start_date' AND '$end_date'";
$stats = $conn->query($stats_query)->fetch_assoc();

include '../includes/header.php';
?>

<div class="container-fluid mt-4">
    <div class="row mb-4">
        <div class="col-md-8">
            <h2>Attendance Management</h2>
        </div>
    </div>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label for="view" class="form-label">View Type</label>
                    <select name="view" id="view" class="form-select">
                        <option value="daily" <?php echo $view_type === 'daily' ? 'selected' : ''; ?>>Daily</option>
                        <option value="weekly" <?php echo $view_type === 'weekly' ? 'selected' : ''; ?>>Weekly</option>
                    </select>
                </div>
                
                <div class="col-md-3">
                    <label for="date" class="form-label">Date</label>
                    <input type="date" name="date" id="date" class="form-control" value="<?php echo $selected_date; ?>">
                </div>
                
                <div class="col-md-4">
                    <label for="employee" class="form-label">Search Employee</label>
                    <input type="text" name="employee" id="employee" class="form-control" placeholder="Employee name" value="<?php echo htmlspecialchars($search_employee); ?>">
                </div>
                
                <div class="col-md-2">
                    <label class="form-label">&nbsp;</label>
                    <button type="submit" class="btn btn-primary w-100">Filter</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h6>Present</h6>
                    <h3><?php echo $stats['present_count']; ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-danger text-white">
                <div class="card-body">
                    <h6>Absent</h6>
                    <h3><?php echo $stats['absent_count']; ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-white">
                <div class="card-body">
                    <h6>Late</h6>
                    <h3><?php echo $stats['late_count']; ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <h6>Half Day</h6>
                    <h3><?php echo $stats['half_day_count']; ?></h3>
                </div>
            </div>
        </div>
    </div>

    <!-- Attendance Table -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">
                Attendance Records 
                <?php if ($view_type === 'weekly'): ?>
                    (Week: <?php echo date('M d', strtotime($start_date)); ?> - <?php echo date('M d, Y', strtotime($end_date)); ?>)
                <?php else: ?>
                    (<?php echo date('F d, Y', strtotime($selected_date)); ?>)
                <?php endif; ?>
            </h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Employee</th>
                            <th>Department</th>
                            <th>Check In</th>
                            <th>Check Out</th>
                            <th>Status</th>
                            <th>Remarks</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($attendance_records->num_rows > 0): ?>
                            <?php while($record = $attendance_records->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo date('M d, Y', strtotime($record['date'])); ?></td>
                                    <td><?php echo htmlspecialchars($record['full_name']); ?></td>
                                    <td><?php echo htmlspecialchars($record['department']); ?></td>
                                    <td><?php echo $record['check_in_time'] ? date('h:i A', strtotime($record['check_in_time'])) : '-'; ?></td>
                                    <td><?php echo $record['check_out_time'] ? date('h:i A', strtotime($record['check_out_time'])) : '-'; ?></td>
                                    <td>
                                        <?php
                                        $status_class = '';
                                        switch($record['status']) {
                                            case 'present': $status_class = 'success'; break;
                                            case 'absent': $status_class = 'danger'; break;
                                            case 'late': $status_class = 'warning'; break;
                                            case 'half_day': $status_class = 'info'; break;
                                        }
                                        ?>
                                        <span class="badge bg-<?php echo $status_class; ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $record['status'])); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($record['remarks']); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center">No attendance records found for the selected period.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php 
$conn->close();
include '../includes/footer.php'; 
?>
