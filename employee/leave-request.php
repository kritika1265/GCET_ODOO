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
    $leave_type = sanitizeInput($_POST['leave_type']);
    $from_date = sanitizeInput($_POST['from_date']);
    $to_date = sanitizeInput($_POST['to_date']);
    $reason = sanitizeInput($_POST['reason']);
    
    // Calculate number of days
    $date1 = new DateTime($from_date);
    $date2 = new DateTime($to_date);
    $interval = $date1->diff($date2);
    $days_count = $interval->days + 1;
    
    // Validate dates
    if ($from_date > $to_date) {
        $error_message = "End date must be after start date.";
    } elseif ($from_date < date('Y-m-d')) {
        $error_message = "Cannot request leave for past dates.";
    } else {
        // Check for overlapping leave requests
        $stmt = $conn->prepare("
            SELECT * FROM leave_requests 
            WHERE employee_id = ? 
            AND status != 'rejected'
            AND (
                (from_date <= ? AND to_date >= ?) OR
                (from_date <= ? AND to_date >= ?) OR
                (from_date >= ? AND to_date <= ?)
            )
        ");
        $stmt->bind_param("issssss", $user_id, $from_date, $from_date, $to_date, $to_date, $from_date, $to_date);
        $stmt->execute();
        $overlapping = $stmt->get_result()->fetch_assoc();
        
        if ($overlapping) {
            $error_message = "You already have a leave request for overlapping dates.";
        } else {
            // Insert leave request
            $stmt = $conn->prepare("
                INSERT INTO leave_requests 
                (employee_id, leave_type, from_date, to_date, days_count, reason, status, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())
            ");
            $stmt->bind_param("isssis", $user_id, $leave_type, $from_date, $to_date, $days_count, $reason);
            
            if ($stmt->execute()) {
                $success_message = "Leave request submitted successfully! Request ID: " . $conn->insert_id;
            } else {
                $error_message = "Error submitting leave request. Please try again.";
            }
        }
    }
}

// Get leave balance
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
$leave_balance = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Leave allocations (you can adjust these)
$leave_allocations = [
    'sick_leave' => 10,
    'casual_leave' => 12,
    'annual_leave' => 20,
    'unpaid_leave' => 999
];

$balance_summary = [];
foreach ($leave_allocations as $type => $allocation) {
    $used = 0;
    foreach ($leave_balance as $lb) {
        if ($lb['leave_type'] == $type) {
            $used = $lb['used_days'];
            break;
        }
    }
    $balance_summary[$type] = [
        'allocated' => $allocation,
        'used' => $used,
        'remaining' => $allocation - $used
    ];
}

include '../includes/header.php';
?>

<div class="container mt-4">
    <div class="row">
        <!-- Leave Request Form -->
        <div class="col-md-8">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Request Leave</h5>
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

                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label">Leave Type *</label>
                            <select class="form-select" name="leave_type" id="leaveType" required>
                                <option value="">-- Select Leave Type --</option>
                                <option value="sick_leave">Sick Leave</option>
                                <option value="casual_leave">Casual Leave</option>
                                <option value="annual_leave">Annual Leave</option>
                                <option value="unpaid_leave">Unpaid Leave</option>
                            </select>
                            <small class="text-muted" id="leaveBalance"></small>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">From Date *</label>
                                <input type="date" class="form-control" name="from_date" 
                                       id="fromDate" min="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">To Date *</label>
                                <input type="date" class="form-control" name="to_date" 
                                       id="toDate" min="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Number of Days</label>
                            <input type="text" class="form-control" id="daysCount" readonly>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Reason *</label>
                            <textarea class="form-control" name="reason" rows="4" 
                                      placeholder="Please provide a reason for your leave request..." required></textarea>
                        </div>

                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                            <a href="index.php" class="btn btn-secondary">Cancel</a>
                            <button type="submit" class="btn btn-primary">Submit Request</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Leave Guidelines -->
            <div class="card mt-4">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0">Leave Guidelines</h5>
                </div>
                <div class="card-body">
                    <ul class="mb-0">
                        <li><strong>Sick Leave:</strong> For medical reasons. May require medical certificate for 3+ days.</li>
                        <li><strong>Casual Leave:</strong> For personal matters, short-term absences.</li>
                        <li><strong>Annual Leave:</strong> Planned vacation or extended time off.</li>
                        <li><strong>Unpaid Leave:</strong> When other leave types are exhausted.</li>
                    </ul>
                    <hr>
                    <p class="mb-0"><strong>Note:</strong> Leave requests should be submitted at least 3 days in advance for approval consideration. Emergency leaves will be reviewed on a case-by-case basis.</p>
                </div>
            </div>
        </div>

        <!-- Leave Balance Summary -->
        <div class="col-md-4">
            <div class="card">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0">Leave Balance</h5>
                </div>
                <div class="card-body">
                    <?php foreach ($balance_summary as $type => $balance): ?>
                        <?php if ($balance['allocated'] < 100): // Don't show unpaid leave in balance ?>
                            <div class="mb-3 pb-3 border-bottom">
                                <h6 class="text-capitalize"><?php echo str_replace('_', ' ', $type); ?></h6>
                                <div class="d-flex justify-content-between">
                                    <span>Allocated:</span>
                                    <strong><?php echo $balance['allocated']; ?> days</strong>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span>Used:</span>
                                    <strong class="text-danger"><?php echo $balance['used']; ?> days</strong>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span>Remaining:</span>
                                    <strong class="text-success"><?php echo $balance['remaining']; ?> days</strong>
                                </div>
                                <div class="progress mt-2" style="height: 20px;">
                                    <?php 
                                    $percentage = ($balance['used'] / $balance['allocated']) * 100;
                                    $bar_color = $percentage < 50 ? 'success' : ($percentage < 80 ? 'warning' : 'danger');
                                    ?>
                                    <div class="progress-bar bg-<?php echo $bar_color; ?>" 
                                         style="width: <?php echo $percentage; ?>%">
                                        <?php echo round($percentage); ?>%
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Quick Links -->
            <div class="card mt-3">
                <div class="card-header bg-secondary text-white">
                    <h5 class="mb-0">Quick Links</h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="leave-history.php" class="btn btn-outline-primary">
                            📋 View Leave History
                        </a>
                        <a href="attendance.php" class="btn btn-outline-info">
                            📅 View Attendance
                        </a>
                        <a href="index.php" class="btn btn-outline-secondary">
                            🏠 Dashboard
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Leave balance data
const leaveBalance = <?php echo json_encode($balance_summary); ?>;

// Update leave balance display
document.getElementById('leaveType').addEventListener('change', function() {
    const selectedType = this.value;
    const balanceText = document.getElementById('leaveBalance');
    
    if (selectedType && leaveBalance[selectedType]) {
        const balance = leaveBalance[selectedType];
        balanceText.textContent = `Available: ${balance.remaining} days (Used: ${balance.used}/${balance.allocated})`;
        balanceText.className = balance.remaining > 0 ? 'text-success' : 'text-danger';
    } else {
        balanceText.textContent = '';
    }
});

// Calculate days
function calculateDays() {
    const fromDate = document.getElementById('fromDate').value;
    const toDate = document.getElementById('toDate').value;
    
    if (fromDate && toDate) {
        const date1 = new Date(fromDate);
        const date2 = new Date(toDate);
        const diffTime = Math.abs(date2 - date1);
        const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1;
        
        document.getElementById('daysCount').value = diffDays + ' day(s)';
    }
}

document.getElementById('fromDate').addEventListener('change', calculateDays);
document.getElementById('toDate').addEventListener('change', calculateDays);

// Update min date for toDate
document.getElementById('fromDate').addEventListener('change', function() {
    document.getElementById('toDate').min = this.value;
});
</script>

<?php include '../includes/footer.php'; ?>
