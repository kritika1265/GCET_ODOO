<?php
// api/attendance.php
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
    case 'mark_attendance':
        markAttendance($conn, $user_id);
        break;
    
    case 'get_attendance':
        getAttendance($conn, $user_id, $user_role);
        break;
    
    case 'get_attendance_report':
        getAttendanceReport($conn, $user_role);
        break;
    
    case 'update_attendance':
        if ($user_role === 'admin') {
            updateAttendance($conn);
        } else {
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        }
        break;
    
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}

function markAttendance($conn, $user_id) {
    $date = date('Y-m-d');
    $time = date('H:i:s');
    $type = $_POST['type'] ?? 'check_in'; // check_in or check_out
    
    // Check if already marked attendance today
    $stmt = $conn->prepare("SELECT id, check_in, check_out FROM attendance WHERE user_id = ? AND date = ?");
    $stmt->bind_param("is", $user_id, $date);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        
        if ($type === 'check_out' && $row['check_in'] && !$row['check_out']) {
            // Update check_out time
            $update = $conn->prepare("UPDATE attendance SET check_out = ?, status = 'present' WHERE id = ?");
            $update->bind_param("si", $time, $row['id']);
            
            if ($update->execute()) {
                echo json_encode(['success' => true, 'message' => 'Check-out marked successfully', 'time' => $time]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to mark check-out']);
            }
            $update->close();
        } else {
            echo json_encode(['success' => false, 'message' => 'Attendance already marked for today']);
        }
    } else {
        // Insert new check_in
        if ($type === 'check_in') {
            $insert = $conn->prepare("INSERT INTO attendance (user_id, date, check_in, status) VALUES (?, ?, ?, 'present')");
            $insert->bind_param("iss", $user_id, $date, $time);
            
            if ($insert->execute()) {
                echo json_encode(['success' => true, 'message' => 'Check-in marked successfully', 'time' => $time]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to mark check-in']);
            }
            $insert->close();
        } else {
            echo json_encode(['success' => false, 'message' => 'Please check-in first']);
        }
    }
    
    $stmt->close();
}

function getAttendance($conn, $user_id, $user_role) {
    $filter_user_id = $user_role === 'admin' ? ($_GET['user_id'] ?? $user_id) : $user_id;
    $start_date = $_GET['start_date'] ?? date('Y-m-01');
    $end_date = $_GET['end_date'] ?? date('Y-m-d');
    
    $sql = "SELECT a.*, u.name, u.email 
            FROM attendance a 
            LEFT JOIN users u ON a.user_id = u.id 
            WHERE a.user_id = ? AND a.date BETWEEN ? AND ? 
            ORDER BY a.date DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iss", $filter_user_id, $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $attendance = [];
    while ($row = $result->fetch_assoc()) {
        $attendance[] = $row;
    }
    
    // Get today's status
    $today = date('Y-m-d');
    $today_stmt = $conn->prepare("SELECT * FROM attendance WHERE user_id = ? AND date = ?");
    $today_stmt->bind_param("is", $user_id, $today);
    $today_stmt->execute();
    $today_result = $today_stmt->get_result();
    $today_attendance = $today_result->fetch_assoc();
    
    echo json_encode([
        'success' => true, 
        'data' => $attendance,
        'today' => $today_attendance
    ]);
    
    $stmt->close();
    $today_stmt->close();
}

function getAttendanceReport($conn, $user_role) {
    if ($user_role !== 'admin') {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        return;
    }
    
    $start_date = $_GET['start_date'] ?? date('Y-m-01');
    $end_date = $_GET['end_date'] ?? date('Y-m-d');
    
    $sql = "SELECT u.id, u.name, u.email, u.department,
            COUNT(CASE WHEN a.status = 'present' THEN 1 END) as present_days,
            COUNT(CASE WHEN a.status = 'absent' THEN 1 END) as absent_days,
            COUNT(CASE WHEN a.status = 'half_day' THEN 1 END) as half_days
            FROM users u
            LEFT JOIN attendance a ON u.id = a.user_id AND a.date BETWEEN ? AND ?
            WHERE u.role = 'employee'
            GROUP BY u.id
            ORDER BY u.name";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $report = [];
    while ($row = $result->fetch_assoc()) {
        $report[] = $row;
    }
    
    echo json_encode(['success' => true, 'data' => $report]);
    $stmt->close();
}

function updateAttendance($conn) {
    $attendance_id = $_POST['attendance_id'] ?? 0;
    $status = $_POST['status'] ?? 'present';
    $check_in = $_POST['check_in'] ?? null;
    $check_out = $_POST['check_out'] ?? null;
    
    $sql = "UPDATE attendance SET status = ?";
    $params = [$status];
    $types = "s";
    
    if ($check_in) {
        $sql .= ", check_in = ?";
        $params[] = $check_in;
        $types .= "s";
    }
    
    if ($check_out) {
        $sql .= ", check_out = ?";
        $params[] = $check_out;
        $types .= "s";
    }
    
    $sql .= " WHERE id = ?";
    $params[] = $attendance_id;
    $types .= "i";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Attendance updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update attendance']);
    }
    
    $stmt->close();
}

$conn->close();
?>
