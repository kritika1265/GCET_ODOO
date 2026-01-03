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

// Handle cancellation
if (isset($_GET['cancel']) && is_numeric($_GET['cancel'])) {
    $leave_id = (int)$_GET['cancel'];
    
    // Check if leave belongs to user and is pending
    $stmt = $conn->prepare("
        SELECT * FROM leave_requests 
        WHERE id = ? AND employee_id = ? AND status = 'pending'
    ");
    $stmt->bind_param("ii", $leave_id, $user_id);
    $stmt->execute();
    $leave = $stmt->get_result()->fetch_assoc();
    
    if ($leave) {
        $stmt = $conn->prepare("
            UPDATE leave_requests 
            SET status = 'cancelled', updated_at = NOW() 
            WHERE id = ?
        ");
        $stmt->bind_param("i", $leave_id);
        $stmt->execute();
        
        header('Location: leave-history.php?msg=cancelled');
        exit();
    }
}

// Filter options
$filter_status = isset($_GET['status']) ? $_GET['status'] : 'all';
$filter_year = isset($_GET['year']) ? $_GET['year'] : date('Y');

// Build query
$query = "SELECT * FROM leave_requests WHERE employee_id = ?";
$params = [$user_id];
$types = "i";

if ($filter_status !== 'all') {
    $query .= " AND status = ?";
    $params[] = $filter_status;
    $types .= "s";
}

if ($filter_year !== 'all') {
    $query .= " AND YEAR(from_date) = ?";
    $params[] = $filter_year;
    $types .= "i";
}

$query .= " ORDER BY created_at DESC";

$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$leave_requests = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get available years
$stmt = $conn->prepare("
    SELECT DISTINCT YEAR(from_date) as year 
    FROM leave_requests 
    WHERE employee_id = ? 
    ORDER BY year DESC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$available_years = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

include '../includes/header.php';
?>

<div class="container mt-4">
    <?php if (isset($_GET['msg']) && $_GET['msg'] == 'cancelled'): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            Leave request cancelled successfully!
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row mb-4">
        <div class="col-md-6">
            <h2>Leave History</h2>
        </div>
        <div class="col-md-6 text-end">
            <a href="leave-request.php" class="btn btn-primary">
                <i class="bi bi-plus-circle"></i> New Leave Request
            </a>
        </div>
    </div>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Status</label>
                    <select class="form-select" name="status" onchange="this.form.submit()">
                        <option value="all" <?php echo $filter_status == 'all' ? 'selected' : ''; ?>>All Status</option>
                        <option value="pending" <?php echo $filter_status == 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="approved" <?php echo $filter_status == 'approved' ? 'selected' : ''; ?>>Approved</option>
                        <option value="rejected" <?php echo $filter_status == 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                        <option value="cancelled" <?php echo $filter_status == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                    </select>
                </div>
                
                <div class="col-md-4">
                    <label class="form-label">Year</label>
                    <select class="form-select" name="year" onchange="this.form.submit()">
                        <option value="all" <?php echo $filter_year == 'all' ? 'selected' : ''; ?>>All Years</option>
                        <?php foreach ($available_years as $year_data): ?>
                            <option value="<?php echo $year_data['year']; ?>" 
                                    <?php echo $filter_year == $year_data['year'] ? 'selected' : ''; ?>>
                                <?php echo $year_data['year']; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-4 d-flex align-items-end">
                    <a href="leave-history.php" class="btn btn-secondary">Reset Filters</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Leave Requests Table -->
    <div class="card">
        <div class="card-header bg-dark text-white">
            <h5 class="mb-0">Your Leave Requests</h5>
        </div>
        <div class="card-body">
            <?php if (count($leave_requests) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Request ID</th>
                                <th>Leave Type</th>
                                <th>From</th>
                                <th>To</th>
                                <th>Days</th>
                                <th>Status</th>
                                <th>Applied On</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($leave_requests as $request): ?>
                                <tr>
                                    <td>#<?php echo $request['id']; ?></td>
                                    <td class="text-capitalize">
                                        <?php echo str_replace('_', ' ', $request['leave_type']); ?>
                                    </td>
                                    <td><?php echo date('M d, Y', strtotime($request['from_date'])); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($request['to_date'])); ?></td>
                                    <td><?php echo $request['days_count']; ?></td>
                                    <td>
                                        <?php
                                        $badge_class = 'secondary';
                                        $status_icon = '';
                                        switch ($request['status']) {
                                            case 'approved':
                                                $badge_class = 'success';
                                                $status_icon = '✓';
                                                break;
                                            case 'rejected':
                                                $badge_class = 'danger';
                                                $status_icon = '✗';
                                                break;
                                            case 'pending':
                                                $badge_class = 'warning';
                                                $status_icon = '⏱';
                                                break;
                                            case 'cancelled':
                                                $badge_class = 'secondary';
                                                $status_icon = '⊘';
                                                break;
                                        }
                                        ?>
                                        <span class="badge bg-<?php echo $badge_class; ?>">
                                            <?php echo $status_icon . ' ' . ucfirst($request['status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M d, Y', strtotime($request['created_at'])); ?></td>
                                    <td>
                                        <button class="btn btn-sm btn-info" 
                                                onclick="viewDetails(<?php echo htmlspecialchars(json_encode($request)); ?>)">
                                            View
                                        </button>
                                        <?php if ($request['status'] == 'pending'): ?>
                                            <a href="?cancel=<?php echo $request['id']; ?>" 
                                               class="btn btn-sm btn-danger"
                                               onclick="return confirm('Are you sure you want to cancel this leave request?')">
                                                Cancel
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center py-5">
                    <img src="../assets/img/no-data.svg" alt="No data" style="width: 200px; opacity: 0.5;" 
                         onerror="this.style.display='none'">
                    <p class="text-muted mt-3">No leave requests found.</p>
                    <a href="leave-request.php" class="btn btn-primary">Request Your First Leave</a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="row mt-4">
        <?php
        $summary = [
            'total' => 0,
            'pending' => 0,
            'approved' => 0,
            'rejected' => 0
        ];
        
        foreach ($leave_requests as $req) {
            $summary['total']++;
            if (isset($summary[$req['status']])) {
                $summary[$req['status']]++;
            }
        }
        ?>
        
        <div class="col-md-3">
            <div class="card text-center border-primary">
                <div class="card-body">
                    <h3 class="text-primary"><?php echo $summary['total']; ?></h3>
                    <p class="mb-0">Total Requests</p>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card text-center border-warning">
                <div class="card-body">
                    <h3 class="text-warning"><?php echo $summary['pending']; ?></h3>
                    <p class="mb-0">Pending</p>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card text-center border-success">
                <div class="card-body">
                    <h3 class="text-success"><?php echo $summary['approved']; ?></h3>
                    <p class="mb-0">Approved</p>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card text-center border-danger">
                <div class="card-body">
                    <h3 class="text-danger"><?php echo $summary['rejected']; ?></h3>
                    <p class="mb-0">Rejected</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Leave Details Modal -->
<div class="modal fade" id="leaveDetailsModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Leave Request Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="leaveDetailsContent">
                <!-- Content will be loaded via JavaScript -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
function viewDetails(leave) {
    const statusBadge = {
        'pending': '<span class="badge bg-warning">⏱ Pending</span>',
        'approved': '<span class="badge bg-success">✓ Approved</span>',
        'rejected': '<span class="badge bg-danger">✗ Rejected</span>',
        'cancelled': '<span class="badge bg-secondary">⊘ Cancelled</span>'
    };
    
    const leaveType = leave.leave_type.replace('_', ' ');
    
    let content = `
        <div class="mb-3">
            <strong>Request ID:</strong> #${leave.id}
        </div>
        <div class="mb-3">
            <strong>Leave Type:</strong> 
            <span class="text-capitalize">${leaveType}</span>
        </div>
        <div class="mb-3">
            <strong>Duration:</strong><br>
            From: ${new Date(leave.from_date).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' })}<br>
            To: ${new Date(leave.to_date).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' })}<br>
            Total Days: ${leave.days_count}
        </div>
        <div class="mb-3">
            <strong>Status:</strong> ${statusBadge[leave.status]}
        </div>
        <div class="mb-3">
            <strong>Reason:</strong><br>
            <p class="text-muted">${leave.reason}</p>
        </div>
        <div class="mb-3">
            <strong>Applied On:</strong> 
            ${new Date(leave.created_at).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit' })}
        </div>
    `;
    
    if (leave.admin_remarks) {
        content += `
            <div class="mb-3">
                <strong>Admin Remarks:</strong><br>
                <p class="text-muted">${leave.admin_remarks}</p>
            </div>
        `;
    }
    
    if (leave.approved_by) {
        content += `
            <div class="mb-3">
                <strong>Processed By:</strong> Admin ID #${leave.approved_by}<br>
                <strong>Processed On:</strong> ${new Date(leave.updated_at).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' })}
            </div>
        `;
    }
    
    document.getElementById('leaveDetailsContent').innerHTML = content;
    
    const modal = new bootstrap.Modal(document.getElementById('leaveDetailsModal'));
    modal.show();
}
</script>

<?php include '../includes/footer.php'; ?>
