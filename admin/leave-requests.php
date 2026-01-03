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

// Handle approve/reject actions
if (isset($_POST['action']) && isset($_POST['leave_id'])) {
    $leave_id = (int)$_POST['leave_id'];
    $action = $_POST['action'];
    $admin_remarks = isset($_POST['admin_remarks']) ? trim($_POST['admin_remarks']) : '';
    
    if ($action === 'approve' || $action === 'reject') {
        $status = $action === 'approve' ? 'approved' : 'rejected';
        $stmt = $conn->prepare("UPDATE leave_requests SET status = ?, admin_remarks = ?, reviewed_by = ?, reviewed_at = NOW() WHERE id = ?");
        $stmt->bind_param("ssii", $status, $admin_remarks, $_SESSION['user_id'], $leave_id);
        
        if ($stmt->execute()) {
            $message = "Leave request " . $status . " successfully!";
            $message_type = "success";
        } else {
            $message = "Error processing leave request.";
            $message_type = "danger";
        }
        $stmt->close();
    }
}

// Get filter parameters
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build query
$query = "SELECT lr.*, u.full_name, u.email, u.department 
          FROM leave_requests lr 
          JOIN users u ON lr.user_id = u.id 
          WHERE 1=1";

if ($status_filter !== 'all') {
    $query .= " AND lr.status = '$status_filter'";
}

if ($search) {
    $query .= " AND u.full_name LIKE '%$search%'";
}

$query .= " ORDER BY lr.created_at DESC";
$leave_requests = $conn->query($query);

// Count statistics
$stats = $conn->query("SELECT 
    COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending,
    COUNT(CASE WHEN status = 'approved' THEN 1 END) as approved,
    COUNT(CASE WHEN status = 'rejected' THEN 1 END) as rejected
    FROM leave_requests")->fetch_assoc();

include '../includes/header.php';
?>

<div class="container-fluid mt-4">
    <div class="row mb-4">
        <div class="col-md-8">
            <h2>Leave Requests Management</h2>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card bg-warning text-white">
                <div class="card-body">
                    <h6>Pending Requests</h6>
                    <h3><?php echo $stats['pending']; ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h6>Approved</h6>
                    <h3><?php echo $stats['approved']; ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-danger text-white">
                <div class="card-body">
                    <h6>Rejected</h6>
                    <h3><?php echo $stats['rejected']; ?></h3>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <label for="status" class="form-label">Filter by Status</label>
                    <select name="status" id="status" class="form-select">
                        <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All</option>
                        <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="approved" <?php echo $status_filter === 'approved' ? 'selected' : ''; ?>>Approved</option>
                        <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                    </select>
                </div>
                
                <div class="col-md-6">
                    <label for="search" class="form-label">Search Employee</label>
                    <input type="text" name="search" id="search" class="form-control" placeholder="Employee name" value="<?php echo htmlspecialchars($search); ?>">
                </div>
                
                <div class="col-md-2">
                    <label class="form-label">&nbsp;</label>
                    <button type="submit" class="btn btn-primary w-100">Filter</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Leave Requests Table -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Employee</th>
                            <th>Department</th>
                            <th>Leave Type</th>
                            <th>From</th>
                            <th>To</th>
                            <th>Days</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($leave_requests->num_rows > 0): ?>
                            <?php while($leave = $leave_requests->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo $leave['id']; ?></td>
                                    <td><?php echo htmlspecialchars($leave['full_name']); ?></td>
                                    <td><?php echo htmlspecialchars($leave['department']); ?></td>
                                    <td><?php echo htmlspecialchars($leave['leave_type']); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($leave['start_date'])); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($leave['end_date'])); ?></td>
                                    <td><?php echo $leave['days']; ?></td>
                                    <td>
                                        <?php
                                        $badge_class = '';
                                        switch($leave['status']) {
                                            case 'pending': $badge_class = 'warning'; break;
                                            case 'approved': $badge_class = 'success'; break;
                                            case 'rejected': $badge_class = 'danger'; break;
                                        }
                                        ?>
                                        <span class="badge bg-<?php echo $badge_class; ?>">
                                            <?php echo ucfirst($leave['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-info view-btn" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#viewLeaveModal"
                                                data-id="<?php echo $leave['id']; ?>"
                                                data-employee="<?php echo htmlspecialchars($leave['full_name']); ?>"
                                                data-email="<?php echo htmlspecialchars($leave['email']); ?>"
                                                data-department="<?php echo htmlspecialchars($leave['department']); ?>"
                                                data-type="<?php echo htmlspecialchars($leave['leave_type']); ?>"
                                                data-start="<?php echo date('M d, Y', strtotime($leave['start_date'])); ?>"
                                                data-end="<?php echo date('M d, Y', strtotime($leave['end_date'])); ?>"
                                                data-days="<?php echo $leave['days']; ?>"
                                                data-reason="<?php echo htmlspecialchars($leave['reason']); ?>"
                                                data-status="<?php echo $leave['status']; ?>"
                                                data-remarks="<?php echo htmlspecialchars($leave['admin_remarks']); ?>">
                                            View
                                        </button>
                                        
                                        <?php if ($leave['status'] === 'pending'): ?>
                                            <button class="btn btn-sm btn-success approve-btn" 
                                                    data-id="<?php echo $leave['id']; ?>"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#actionModal">
                                                Approve
                                            </button>
                                            <button class="btn btn-sm btn-danger reject-btn" 
                                                    data-id="<?php echo $leave['id']; ?>"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#actionModal">
                                                Reject
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="9" class="text-center">No leave requests found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- View Leave Modal -->
<div class="modal fade" id="viewLeaveModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Leave Request Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <strong>Employee:</strong> <span id="view_employee"></span>
                </div>
                <div class="mb-3">
                    <strong>Email:</strong> <span id="view_email"></span>
                </div>
                <div class="mb-3">
                    <strong>Department:</strong> <span id="view_department"></span>
                </div>
                <div class="mb-3">
                    <strong>Leave Type:</strong> <span id="view_type"></span>
                </div>
                <div class="mb-3">
                    <strong>Duration:</strong> <span id="view_start"></span> to <span id="view_end"></span>
                </div>
                <div class="mb-3">
                    <strong>Total Days:</strong> <span id="view_days"></span>
                </div>
                <div class="mb-3">
                    <strong>Reason:</strong><br>
                    <span id="view_reason"></span>
                </div>
                <div class="mb-3">
                    <strong>Status:</strong> <span id="view_status"></span>
                </div>
                <div class="mb-3" id="view_remarks_div" style="display:none;">
                    <strong>Admin Remarks:</strong><br>
                    <span id="view_remarks"></span>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Action Modal (Approve/Reject) -->
<div class="modal fade" id="actionModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="actionModalTitle">Approve Leave Request</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="leave_id" id="action_leave_id">
                    <input type="hidden" name="action" id="action_type">
                    
                    <div class="mb-3">
                        <label for="admin_remarks" class="form-label">Remarks (Optional)</label>
                        <textarea class="form-control" name="admin_remarks" id="admin_remarks" rows="3"></textarea>
                    </div>
                    
                    <p id="action_confirm_text"></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="action_submit_btn">Confirm</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// View button functionality
document.querySelectorAll('.view-btn').forEach(button => {
    button.addEventListener('click', function() {
        document.getElementById('view_employee').textContent = this.dataset.employee;
        document.getElementById('view_email').textContent = this.dataset.email;
        document.getElementById('view_department').textContent = this.dataset.department;
        document.getElementById('view_type').textContent = this.dataset.type;
        document.getElementById('view_start').textContent = this.dataset.start;
        document.getElementById('view_end').textContent = this.dataset.end;
        document.getElementById('view_days').textContent = this.dataset.days;
        document.getElementById('view_reason').textContent = this.dataset.reason;
        document.getElementById('view_status').textContent = this.dataset.status.toUpperCase();
        
        if (this.dataset.remarks) {
            document.getElementById('view_remarks').textContent = this.dataset.remarks;
            document.getElementById('view_remarks_div').style.display = 'block';
        } else {
            document.getElementById('view_remarks_div').style.display = 'none';
        }
    });
});

// Approve button functionality
document.querySelectorAll('.approve-btn').forEach(button => {
    button.addEventListener('click', function() {
        document.getElementById('actionModalTitle').textContent = 'Approve Leave Request';
        document.getElementById('action_leave_id').value = this.dataset.id;
        document.getElementById('action_type').value = 'approve';
        document.getElementById('action_confirm_text').textContent = 'Are you sure you want to approve this leave request?';
        document.getElementById('action_submit_btn').className = 'btn btn-success';
        document.getElementById('action_submit_btn').textContent = 'Approve';
    });
});

// Reject button functionality
document.querySelectorAll('.reject-btn').forEach(button => {
    button.addEventListener('click', function() {
        document.getElementById('actionModalTitle').textContent = 'Reject Leave Request';
        document.getElementById('action_leave_id').value = this.dataset.id;
        document.getElementById('action_type').value = 'reject';
        document.getElementById('action_confirm_text').textContent = 'Are you sure you want to reject this leave request?';
        document.getElementById('action_submit_btn').className = 'btn btn-danger';
        document.getElementById('action_submit_btn').textContent = 'Reject';
    });
});
</script>

<?php 
$conn->close();
include '../includes/footer.php'; 
?>
