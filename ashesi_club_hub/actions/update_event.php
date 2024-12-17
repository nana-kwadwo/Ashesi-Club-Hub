<?php
session_start();
include '../db/config.php';

// Authentication check
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize and validate inputs
    $event_id = filter_input(INPUT_POST, 'event_id', FILTER_VALIDATE_INT);
    $event_title = trim($_POST['event_title']);
    $event_description = trim($_POST['event_description']);
    $event_date = $_POST['event_date'];
    $current_slots = filter_input(INPUT_POST, 'current_slots', FILTER_VALIDATE_INT);
    $total_slots = filter_input(INPUT_POST, 'total_slots', FILTER_VALIDATE_INT);
    $status = $_POST['status'];

    // Validate inputs
    if (!$event_id || !$event_title || !$event_description || !$event_date || 
        $current_slots === false || $total_slots === false || 
        $current_slots > $total_slots) {
        echo json_encode(['success' => false, 'message' => 'Invalid input data']);
        exit();
    }

    // First, verify the user has permission to edit this event
    $check_permission_query = "
        SELECT e.event_id 
        FROM events e
        JOIN clubs c ON e.club_id = c.club_id
        WHERE e.event_id = ? AND c.club_head_id = ?
    ";
    $stmt = $conn->prepare($check_permission_query);
    $stmt->bind_param("ii", $event_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Not authorized to edit this event']);
        exit();
    }

    // Prepare update query
    $update_query = "
        UPDATE events 
        SET event_title = ?, 
            description = ?, 
            event_date = ?, 
            current_slots = ?, 
            total_slots = ?, 
            status = ?
        WHERE event_id = ?
    ";
    $stmt = $conn->prepare($update_query);
    $stmt->bind_param(
        "sssiisi", 
        $event_title, 
        $event_description, 
        $event_date, 
        $current_slots, 
        $total_slots, 
        $status, 
        $event_id
    );

    // Execute update and log action
    if ($stmt->execute()) {
        // Log the admin action
        $log_query = "
            INSERT INTO admin_actions_log 
            (admin_id, action_type, target_table, target_id, details) 
            VALUES (?, 'update', 'events', ?, ?)
        ";
        $log_stmt = $conn->prepare($log_query);
        $details = "Updated event: $event_title";
        $log_stmt->bind_param("iis", $user_id, $event_id, $details);
        $log_stmt->execute();

        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update event']);
    }
    exit();
}
?>