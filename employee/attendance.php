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

// Get current month and year
$current_month = isset($_GET['month']) ? $_GET['month'] : date('Y-m');
$month_start = $current_month . '-01';
$month_end = date('Y-m-t', strtotime($month_start));

// Fetch attendance records for the month
$stmt = $conn->prepare("
    SELECT * FROM attendance 
    WHERE employee_id = ? AND date BETWEEN ? AND ?
    ORDER BY date DESC
");
$stmt->bind_param("iss", $user_id, $month_start, $month_end);
$stmt->execute();
$attendance_records = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Calculate statistics
$stats = [
    'present' => 0,
    'absent' => 0,
    'half_day' => 0,
    'late' => 0
];

foreach ($attendance_records as $record) {
    if (isset($stats[$record['status']])) {
        $stats[$record['status']]++;
    }
}

// Check today's attendance
$today = date('Y-m-d');
$stmt = $conn->prepare("SELECT * FROM attendance WHERE employee_id = ? AND date = ?");
$stmt->bind_param("is", $user_id, $today);
$stmt->execute();
$today_attendance = $stmt->get_result()->fetch_assoc();

include '../includes/header.php';
?>

<div class="container mt-4">
    <div class="row mb-4">
        <div class="col-md-8">
            <h2>Attendance Management</h2>
        </div>
        <div class="col-md-4 text-end">
            <form method="GET" class="d-inline-flex">
                <input type="month" class="form-control me-2" name="month" 
                       value="<?php echo $current_month; ?>" 
                       onchange="this.form.submit()">
            </form>
        </div>
    </div>

    <!-- Today's Attendance Card -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-primary">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Today's Attendance - <?php echo date('F d, Y'); ?></h5>
                </div>
                <div class="card-body">
                    <?php if ($today_attendance): ?>
                        <div class="row">
                            <div class="col-md-3">
                                <strong>Status:</strong>
                                <span class="badge bg-success fs-6"><?php echo ucfirst($today_attendance['status']); ?></span>
                            </div>
                            <div class="col-md-3">
                                <strong>Check-in:</strong>
                                <?php echo date('h:i A', strtotime($today_attendance['check_in'])); ?>
                            </div>
                            <div class="col-md-3">
                                <strong>Check-out:</strong>
                                <?php echo $today_attendance['check_out'] ? date('h:i A', strtotime($today_attendance['check_out'])) : 'Not yet'; ?>
                            </div>
                            <div class="col-md-3">
                                <?php if (!$today_attendance['check_out']): ?>
                                    <button class="btn btn-danger btn-sm" onclick="markCheckout()">Check Out</button>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php if ($today_attendance['notes']): ?>
                            <div class="mt-2">
                                <strong>Notes:</strong> <?php echo htmlspecialchars($today_attendance['notes']); ?>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="text-center">
                            <p class="text-muted mb-3">You haven't marked attendance for today yet.</p>
                            <button class="btn btn-success btn-lg" onclick="markCheckin()">
                                <i class="bi bi-check-circle"></i> Check In Now
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Monthly Statistics -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card text-center border-success">
                <div class="card-body">
                    <h3 class="text-success"><?php echo $stats['present']; ?></h3>
                    <p class="mb-0">Present Days</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center border-danger">
                <div class="card-body">
                    <h3 class="text-danger"><?php echo $stats['absent']; ?></h3>
                    <p class="mb-0">Absent Days</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center border-warning">
                <div class="card-body">
                    <h3 class="text-warning"><?php echo $stats['half_day']; ?></h3>
                    <p class="mb-0">Half Days</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center border-info">
                <div class="card-body">
                    <h3 class="text-info"><?php echo $stats['late']; ?></h3>
                    <p class="mb-0">Late Arrivals</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Attendance Records Table -->
    <div class="card">
        <div class="card-header bg-dark text-white">
            <h5 class="mb-0">Attendance History - <?php echo date('F Y', strtotime($current_month)); ?></h5>
        </div>
        <div class="card-body">
            <?php if (count($attendance_records) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Day</th>
                                <th>Check-in</th>
                                <th>Check-out</th>
                                <th>Working Hours</th>
                                <th>Status</th>
                                <th>Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($attendance_records as $record): ?>
                                <tr>
                                    <td><?php echo date('M d, Y', strtotime($record['date'])); ?></td>
                                    <td><?php echo date('l', strtotime($record['date'])); ?></td>
                                    <td><?php echo $record['check_in'] ? date('h:i A', strtotime($record['check_in'])) : '-'; ?></td>
                                    <td><?php echo $record['check_out'] ? date('h:i A', strtotime($record['check_out'])) : '-'; ?></td>
                                    <td>
                                        <?php
                                        if ($record['check_in'] && $record['check_out']) {
                                            $checkin = new DateTime($record['check_in']);
                                            $checkout = new DateTime($record['check_out']);
                                            $interval = $checkin->diff($checkout);
                                            echo $interval->format('%h hrs %i mins');
                                        } else {
                                            echo '-';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <?php
                                        $badge_class = 'secondary';
                                        if ($record['status'] == 'present') $badge_class = 'success';
                                        elseif ($record['status'] == 'absent') $badge_class = 'danger';
                                        elseif ($record['status'] == 'half_day') $badge_class = 'warning';
                                        elseif ($record['status'] == 'late') $badge_class = 'info';
                                        ?>
                                        <span class="badge bg-<?php echo $badge_class; ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $record['status'])); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($record['notes'] ?? '-'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="text-center text-muted">No attendance records found for this month.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Weekly View -->
    <div class="card mt-4">
        <div class="card-header bg-info text-white">
            <h5 class="mb-0">This Week at a Glance</h5>
        </div>
        <div class="card-body">
            <div class="row text-center">
                <?php
                // Get current week dates
                $week_start = date('Y-m-d', strtotime('monday this week'));
                for ($i = 0; $i < 7; $i++) {
                    $current_date = date('Y-m-d', strtotime($week_start . ' +' . $i . ' days'));
                    $day_name = date('D', strtotime($current_date));
                    
                    // Find attendance for this day
                    $day_attendance = null;
                    foreach ($attendance_records as $record) {
                        if ($record['date'] == $current_date) {
                            $day_attendance = $record;
                            break;
                        }
                    }
                    
                    $status_icon = '⚪';
                    $status_color = 'text-muted';
                    if ($day_attendance) {
                        if ($day_attendance['status'] == 'present') {
                            $status_icon = '✅';
                            $status_color = 'text-success';
                        } elseif ($day_attendance['status'] == 'absent') {
                            $status_icon = '❌';
                            $status_color = 'text-danger';
                        } elseif ($day_attendance['status'] == 'half_day') {
                            $status_icon = '🕐';
                            $status_color = 'text-warning';
                        }
                    }
                ?>
                    <div class="col">
                        <div class="border rounded p-2">
                            <div class="<?php echo $status_color; ?>" style="font-size: 32px;">
                                <?php echo $status_icon; ?>
                            </div>
                            <small><strong><?php echo $day_name; ?></strong></small><br>
                            <small><?php echo date('M d', strtotime($current_date)); ?></small>
                        </div>
                    </div>
                <?php } ?>
            </div>
        </div>
    </div>
</div>

<script>
function markCheckin() {
    if (confirm('Mark check-in for today?')) {
        fetch('../api/attendance.php?action=checkin', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Check-in marked successfully!');
                location.reload();
            } else {
                alert(data.message || 'Error marking attendance');
            }
        })
        .catch(error => {
            alert('An error occurred. Please try again.');
        });
    }
}

function markCheckout() {
    if (confirm('Mark check-out for today?')) {
        fetch('../api/attendance.php?action=checkout', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Check-out marked successfully!');
                location.reload();
            } else {
                alert(data.message || 'Error marking checkout');
            }
        })
        .catch(error => {
            alert('An error occurred. Please try again.');
        });
    }
}
</script>

<?php include '../includes/footer.php'; ?>
