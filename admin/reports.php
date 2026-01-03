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

// Get report parameters
$report_type = isset($_GET['report_type']) ? $_GET['report_type'] : '';
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01'); // First day of current month
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$department = isset($_GET['department']) ? trim($_GET['department']) : '';

$report_data = [];
$report_title = '';

// Generate report based on type
if ($report_type) {
    switch ($report_type) {
        case 'attendance_summary':
            $report_title = 'Attendance Summary Report';
            $query = "SELECT u.full_name, u.department, u.email,
                      COUNT(CASE WHEN a.status = 'present' THEN 1 END) as present_days,
                      COUNT(CASE WHEN a.status = 'absent' THEN 1 END) as absent_days,
                      COUNT(CASE WHEN a.status = 'late' THEN 1 END) as late_days,
                      COUNT(CASE WHEN a.status = 'half_day' THEN 1 END) as half_days,
                      COUNT(*) as total_days
                      FROM users u
                      LEFT JOIN attendance a ON u.id = a.user_id AND a.date BETWEEN '$start_date' AND '$end_date'
                      WHERE u.role = 'employee'";
            
            if ($department) {
                $query .= " AND u.department = '$department'";
            }
            
            $query .= " GROUP BY u.id ORDER BY u.full_name";
            $report_data = $conn->query($query);
            break;
            
        case 'leave_summary':
            $report_title = 'Leave Summary Report';
            $query = "SELECT u.full_name, u.department, u.email,
                      COUNT(CASE WHEN lr.status = 'approved' THEN 1 END) as approved_leaves,
                      COUNT(CASE WHEN lr.status = 'rejected' THEN 1 END) as rejected_leaves,
                      COUNT(CASE WHEN lr.status = 'pending' THEN 1 END) as pending_leaves,
                      SUM(CASE WHEN lr.status = 'approved' THEN lr.days ELSE 0 END) as total_leave_days
                      FROM users u
                      LEFT JOIN leave_requests lr ON u.id = lr.user_id AND lr.start_date BETWEEN '$start_date' AND '$end_date'
                      WHERE u.role = 'employee'";
            
            if ($department) {
                $query .= " AND u.department = '$department'";
            }
            
            $query .= " GROUP BY u.id ORDER BY u.full_name";
            $report_data = $conn->query($query);
            break;
            
        case 'department_summary':
            $report_title = 'Department-wise Summary';
            $query = "SELECT u.department,
                      COUNT(DISTINCT u.id) as total_employees,
                      COUNT(CASE WHEN a.status = 'present' THEN 1 END) as present_count,
                      COUNT(CASE WHEN a.status = 'absent' THEN 1 END) as absent_count,
                      ROUND(COUNT(CASE WHEN a.status = 'present' THEN 1 END) * 100.0 / NULLIF(COUNT(a.id), 0), 2) as attendance_percentage
                      FROM users u
                      LEFT JOIN attendance a ON u.id = a.user_id AND a.date BETWEEN '$start_date' AND '$end_date'
                      WHERE u.role = 'employee'";
            
            if ($department) {
                $query .= " AND u.department = '$department'";
            }
            
            $query .= " GROUP BY u.department ORDER BY u.department";
            $report_data = $conn->query($query);
            break;
            
        case 'employee_performance':
            $report_title = 'Employee Performance Report';
            $query = "SELECT u.full_name, u.department, u.email, u.position,
                      COUNT(CASE WHEN a.status = 'present' THEN 1 END) as present_days,
                      COUNT(CASE WHEN a.status = 'late' THEN 1 END) as late_days,
                      SUM(CASE WHEN lr.status = 'approved' THEN lr.days ELSE 0 END) as leave_days,
                      ROUND(COUNT(CASE WHEN a.status = 'present' THEN 1 END) * 100.0 / NULLIF(COUNT(a.id), 0), 2) as attendance_rate
                      FROM users u
                      LEFT JOIN attendance a ON u.id = a.user_id AND a.date BETWEEN '$start_date' AND '$end_date'
                      LEFT JOIN leave_requests lr ON u.id = lr.user_id AND lr.start_date BETWEEN '$start_date' AND '$end_date'
                      WHERE u.role = 'employee'";
            
            if ($department) {
                $query .= " AND u.department = '$department'";
            }
            
            $query .= " GROUP BY u.id ORDER BY attendance_rate DESC, u.full_name";
            $report_data = $conn->query($query);
            break;
    }
}

// Get list of departments for filter
$departments = $conn->query("SELECT DISTINCT department FROM users WHERE role = 'employee' ORDER BY department");

include '../includes/header.php';
?>

<div class="container-fluid mt-4">
    <div class="row mb-4">
        <div class="col-md-8">
            <h2>Reports & Analytics</h2>
        </div>
        <div class="col-md-4 text-end">
            <?php if ($report_type && $report_data && $report_data->num_rows > 0): ?>
                <button onclick="window.print()" class="btn btn-secondary">
                    <i class="bi bi-printer"></i> Print Report
                </button>
                <button onclick="exportToCSV()" class="btn btn-success">
                    <i class="bi bi-download"></i> Export CSV
                </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Report Generator -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">Generate Report</h5>
        </div>
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label for="report_type" class="form-label">Report Type *</label>
                    <select name="report_type" id="report_type" class="form-select" required>
                        <option value="">Select Report</option>
                        <option value="attendance_summary" <?php echo $report_type === 'attendance_summary' ? 'selected' : ''; ?>>Attendance Summary</option>
                        <option value="leave_summary" <?php echo $report_type === 'leave_summary' ? 'selected' : ''; ?>>Leave Summary</option>
                        <option value="department_summary" <?php echo $report_type === 'department_summary' ? 'selected' : ''; ?>>Department Summary</option>
                        <option value="employee_performance" <?php echo $report_type === 'employee_performance' ? 'selected' : ''; ?>>Employee Performance</option>
                    </select>
                </div>
                
                <div class="col-md-3">
                    <label for="start_date" class="form-label">Start Date *</label>
                    <input type="date" name="start_date" id="start_date" class="form-control" value="<?php echo $start_date; ?>" required>
                </div>
                
                <div class="col-md-3">
                    <label for="end_date" class="form-label">End Date *</label>
                    <input type="date" name="end_date" id="end_date" class="form-control" value="<?php echo $end_date; ?>" required>
                </div>
                
                <div class="col-md-2">
                    <label for="department" class="form-label">Department</label>
                    <select name="department" id="department" class="form-select">
                        <option value="">All Departments</option>
                        <?php while($dept = $departments->fetch_assoc()): ?>
                            <option value="<?php echo htmlspecialchars($dept['department']); ?>" 
                                    <?php echo $department === $dept['department'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($dept['department']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <div class="col-md-1">
                    <label class="form-label">&nbsp;</label>
                    <button type="submit" class="btn btn-primary w-100">Generate</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Report Display -->
    <?php if ($report_type && $report_data): ?>
        <div class="card" id="reportCard">
            <div class="card-header">
                <h5 class="mb-0"><?php echo $report_title; ?></h5>
                <small class="text-muted">
                    Period: <?php echo date('M d, Y', strtotime($start_date)); ?> to <?php echo date('M d, Y', strtotime($end_date)); ?>
                    <?php if ($department): ?>
                        | Department: <?php echo htmlspecialchars($department); ?>
                    <?php endif; ?>
                </small>
            </div>
            <div class="card-body">
                <?php if ($report_data->num_rows > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover" id="reportTable">
                            <thead>
                                <tr>
                                    <?php if ($report_type === 'attendance_summary'): ?>
                                        <th>Employee Name</th>
                                        <th>Department</th>
                                        <th>Email</th>
                                        <th>Present Days</th>
                                        <th>Absent Days</th>
                                        <th>Late Days</th>
                                        <th>Half Days</th>
                                        <th>Total Days</th>
                                    <?php elseif ($report_type === 'leave_summary'): ?>
                                        <th>Employee Name</th>
                                        <th>Department</th>
                                        <th>Email</th>
                                        <th>Approved Leaves</th>
                                        <th>Rejected Leaves</th>
                                        <th>Pending Leaves</th>
                                        <th>Total Leave Days</th>
                                    <?php elseif ($report_type === 'department_summary'): ?>
                                        <th>Department</th>
                                        <th>Total Employees</th>
                                        <th>Present Count</th>
                                        <th>Absent Count</th>
                                        <th>Attendance %</th>
                                    <?php elseif ($report_type === 'employee_performance'): ?>
                                        <th>Employee Name</th>
                                        <th>Department</th>
                                        <th>Position</th>
                                        <th>Present Days</th>
                                        <th>Late Days</th>
                                        <th>Leave Days</th>
                                        <th>Attendance Rate</th>
                                    <?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while($row = $report_data->fetch_assoc()): ?>
                                    <tr>
                                        <?php if ($report_type === 'attendance_summary'): ?>
                                            <td><?php echo htmlspecialchars($row['full_name']); ?></td>
                                            <td><?php echo htmlspecialchars($row['department']); ?></td>
                                            <td><?php echo htmlspecialchars($row['email']); ?></td>
                                            <td><?php echo $row['present_days']; ?></td>
                                            <td><?php echo $row['absent_days']; ?></td>
                                            <td><?php echo $row['late_days']; ?></td>
                                            <td><?php echo $row['half_days']; ?></td>
                                            <td><?php echo $row['total_days']; ?></td>
                                        <?php elseif ($report_type === 'leave_summary'): ?>
                                            <td><?php echo htmlspecialchars($row['full_name']); ?></td>
                                            <td><?php echo htmlspecialchars($row['department']); ?></td>
                                            <td><?php echo htmlspecialchars($row['email']); ?></td>
                                            <td><?php echo $row['approved_leaves']; ?></td>
                                            <td><?php echo $row['rejected_leaves']; ?></td>
                                            <td><?php echo $row['pending_leaves']; ?></td>
                                            <td><?php echo $row['total_leave_days']; ?></td>
                                        <?php elseif ($report_type === 'department_summary'): ?>
                                            <td><?php echo htmlspecialchars($row['department']); ?></td>
                                            <td><?php echo $row['total_employees']; ?></td>
                                            <td><?php echo $row['present_count']; ?></td>
                                            <td><?php echo $row['absent_count']; ?></td>
                                            <td><?php echo $row['attendance_percentage'] ?? '0'; ?>%</td>
                                        <?php elseif ($report_type === 'employee_performance'): ?>
                                            <td><?php echo htmlspecialchars($row['full_name']); ?></td>
                                            <td><?php echo htmlspecialchars($row['department']); ?></td>
                                            <td><?php echo htmlspecialchars($row['position']); ?></td>
                                            <td><?php echo $row['present_days']; ?></td>
                                            <td><?php echo $row['late_days']; ?></td>
                                            <td><?php echo $row['leave_days']; ?></td>
                                            <td><?php echo $row['attendance_rate'] ?? '0'; ?>%</td>
                                        <?php endif; ?>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-muted text-center">No data available for the selected criteria.</p>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
function exportToCSV() {
    const table = document.getElementById('reportTable');
    let csv = [];
    
    // Get headers
    const headers = [];
    table.querySelectorAll('thead th').forEach(th => {
        headers.push(th.textContent);
    });
    csv.push(headers.join(','));
    
    // Get data rows
    table.querySelectorAll('tbody tr').forEach(tr => {
        const row = [];
        tr.querySelectorAll('td').forEach(td => {
            row.push('"' + td.textContent.replace(/"/g, '""') + '"');
        });
        csv.push(row.join(','));
    });
    
    // Download
    const csvContent = csv.join('\n');
    const blob = new Blob([csvContent], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'report_<?php echo date('Y-m-d'); ?>.csv';
    a.click();
    window.URL.revokeObjectURL(url);
}
</script>

<style>
@media print {
    .btn, .card-header .btn, form { display: none !important; }
    .card { border: none; box-shadow: none; }
}
</style>

<?php 
$conn->close();
include '../includes/footer.php'; 
?>
