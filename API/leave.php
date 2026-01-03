<?php
// api/leave.php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];

switch ($action) {
    case 'submit_request':
        submitLeaveRequest($conn, $user_id);
        break;
    
    case 'get_requests':
        getLeaveRequests($conn, $user_id, $user_role);
        break;
    
    case 'get_request_details':
        getRequestDetails($conn, $user_role);
        break;
    
    case 'approve_request':
        if ($user_role === 'admin') {
            approveLeaveRequest($conn, $user_id);
        } else {
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        }
        break;
    
    case 'reject_request':
        if ($user_role === 'admin') {
            rejectLeaveRequest($conn, $user_id);
        } else {
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        }
        break;
    
    case 'cancel_request':
        cancelLeaveRequest($conn, $user_id);
        break;
    
    case 'get_leave_balance':
        getLeaveBalance($conn, $user_id);
        break;
    
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}

function submitLeaveRequest($conn, $user_id) {
    $leave_type = $_POST['leave_type'] ?? '';
    $start_date = $_POST['start_date'] ?? '';
    $end_date = $_POST['end_date'] ?? '';
    $reason = $_POST['reason'] ?? '';
    
    // Validate dates
    if (strtotime($start_date) > strtotime($end_date)) {
        echo json_encode(['success' => false, 'message' => 'End date must be after start date']);
        return;
    }
    
    // Calculate number of days
    $start = new DateTime($start_date);
    $end = new DateTime($end_date);
    $interval = $start->diff($end);
    $days = $interval->days + 1;
    
    // Check leave balance
    $balance_stmt = $conn->prepare("SELECT * FROM leave_balance WHERE user_id = ? AND leave_type = ?");
    $balance_stmt->bind_param("is", $user_id, $leave_type);
    $balance_stmt->execute();
    $balance_result = $balance_stmt->get_result();
    
    if ($balance_result->num_rows > 0) {
        $balance = $balance_result->fetch_assoc();
        if ($balance['available'] < $days) {
            echo json_encode(['success' => false, 'message' => 'Insufficient leave balance. Available: ' . $balance['available'] . ' days']);
            $balance_stmt->close();
            return;
        }
    }
    $balance_stmt->close();
    
    // Insert leave request
    $stmt = $conn->prepare("INSERT INTO leave_requests (user_id, leave_type, start_date, end_date, days, reason, status, request_date) VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())");
    $stmt->bind_param("isssis", $user_id, $leave_type, $start_date, $end_date, $days, $reason);
    
    if ($stmt->execute()) {
        echo json_encode([
            'success' => true, 
            'message' => 'Leave request submitted successfully',
            'request_id' => $conn->insert_id
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to submit leave request']);
    }
    
    $stmt->close();
}

function getLeaveRequests($conn, $user_id, $user_role) {
    $status = $_GET['status'] ?? 'all';
    $filter_user_id = $_GET['user_id'] ?? null;
    
    if ($user_role === 'admin') {
        $sql = "SELECT lr.*, u.name, u.email, u.department 
                FROM leave_requests lr 
                LEFT JOIN users u ON lr.user_id = u.id 
                WHERE 1=1";
        
        if ($filter_user_id) {
            $sql .= " AND lr.user_id = " . intval($filter_user_id);
        }
    } else {
        $sql = "SELECT lr.*, u.name, u.email 
                FROM leave_requests lr 
                LEFT JOIN users u ON lr.user_id = u.id 
                WHERE lr.user_id = $user_id";
    }
    
    if ($status !== 'all') {
        $sql .= " AND lr.status = '" . $conn->real_escape_string($status) . "'";
    }
    
    $sql .= " ORDER BY lr.request_date DESC";
    
    $result = $conn->query($sql);
    
    $requests = [];
    while ($row = $result->fetch_assoc()) {
        $requests[] = $row;
    }
    
    echo json_encode(['success' => true, 'data' => $requests]);
}

function getRequestDetails($conn, $user_role) {
    $request_id = $_GET['request_id'] ?? 0;
    
    $sql = "SELECT lr.*, u.name, u.email, u.department, u.position,
            approver.name as approver_name
            FROM leave_requests lr 
            LEFT JOIN users u ON lr.user_id = u.id
            LEFT JOIN users approver ON lr.approved_by = approver.id
            WHERE lr.id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $request_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $request = $result->fetch_assoc();
        echo json_encode(['success' => true, 'data' => $request]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Request not found']);
    }
    
    $stmt->close();
}

function approveLeaveRequest($conn, $admin_id) {
    $request_id = $_POST['request_id'] ?? 0;
    $comments = $_POST['comments'] ?? '';
    
    // Get request details
    $stmt = $conn->prepare("SELECT * FROM leave_requests WHERE id = ?");
    $stmt->bind_param("i", $request_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Request not found']);
        $stmt->close();
        return;
    }
    
    $request = $result->fetch_assoc();
    $stmt->close();
    
    // Update leave request
    $update = $conn->prepare("UPDATE leave_requests SET status = 'approved', approved_by = ?, approval_date = NOW(), comments = ? WHERE id = ?");
    $update->bind_param("isi", $admin_id, $comments, $request_id);
    
    if ($update->execute()) {
        // Update leave balance
        $balance_update = $conn->prepare("UPDATE leave_balance SET used = used + ?, available = available - ? WHERE user_id = ? AND leave_type = ?");
        $balance_update->bind_param("iiis", $request['days'], $request['days'], $request['user_id'], $request['leave_type']);
        $balance_update->execute();
        $balance_update->close();
        
        echo json_encode(['success' => true, 'message' => 'Leave request approved successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to approve request']);
    }
    
    $update->close();
}

function rejectLeaveRequest($conn, $admin_id) {
    $request_id = $_POST['request_id'] ?? 0;
    $comments = $_POST['comments'] ?? '';
    
    $stmt = $conn->prepare("UPDATE leave_requests SET status = 'rejected', approved_by = ?, approval_date = NOW(), comments = ? WHERE id = ?");
    $stmt->bind_param("isi", $admin_id, $comments, $request_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Leave request rejected']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to reject request']);
    }
    
    $stmt->close();
}

function cancelLeaveRequest($conn, $user_id) {
    $request_id = $_POST['request_id'] ?? 0;
    
    // Check if request belongs to user and is pending
    $stmt = $conn->prepare("SELECT * FROM leave_requests WHERE id = ? AND user_id = ? AND status = 'pending'");
    $stmt->bind_param("ii", $request_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Cannot cancel this request']);
        $stmt->close();
        return;
    }
    
    $stmt->close();
    
    // Update status to cancelled
    $update = $conn->prepare("UPDATE leave_requests SET status = 'cancelled' WHERE id = ?");
    $update->bind_param("i", $request_id);
    
    if ($update->execute()) {
        echo json_encode(['success' => true, 'message' => 'Leave request cancelled']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to cancel request']);
    }
    
    $update->close();
}

function getLeaveBalance($conn, $user_id) {
    $stmt = $conn->prepare("SELECT * FROM leave_balance WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $balance = [];
    while ($row = $result->fetch_assoc()) {
        $balance[] = $row;
    }
    
    echo json_encode(['success' => true, 'data' => $balance]);
    $stmt->close();
}

$conn->close();
?>
