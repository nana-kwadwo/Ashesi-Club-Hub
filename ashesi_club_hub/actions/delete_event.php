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
    // Sanitize and validate event ID
    $event_id = filter_input(INPUT_POST, 'event_id', FILTER_VALIDATE_INT);

    // Validate input
    if (!$event_id) {
        echo json_encode(['success' => false, 'message' => 'Invalid event ID']);
        exit();
    }

    // First, verify the user has permission to delete this event
    $check_permission_query = "
        SELECT e.event_id, e.event_title
        FROM events e
        JOIN clubs c ON e.club_id = c.club_id
        WHERE e.event_id = ? AND c.club_head_id = ?
    ";
    $stmt = $conn->prepare($check_permission_query);
    $stmt->bind_param("ii", $event_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    // If no matching event found or user is not the club head
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Not authorized to delete this event']);
        exit();
    }

    // Get the event title for logging
    $event_row = $result->fetch_assoc();
    $event_title = $event_row['event_title'];

    // Begin transaction for atomic delete operation
    $conn->begin_transaction();

    try {
        // First, delete related event registrations
        $delete_registrations_query = "DELETE FROM event_registrations WHERE event_id = ?";
        $reg_stmt = $conn->prepare($delete_registrations_query);
        $reg_stmt->bind_param("i", $event_id);
        $reg_stmt->execute();

        // Then delete the event
        $delete_event_query = "DELETE FROM events WHERE event_id = ?";
        $event_stmt = $conn->prepare($delete_event_query);
        $event_stmt->bind_param("i", $event_id);
        $event_stmt->execute();

        // Log the admin action
        $log_query = "
            INSERT INTO admin_actions_log 
            (admin_id, action_type, target_table, target_id, details) 
            VALUES (?, 'delete', 'events', ?, ?)
        ";
        $log_stmt = $conn->prepare($log_query);
        $details = "Deleted event: $event_title";
        $log_stmt->bind_param("iis", $user_id, $event_id, $details);
        $log_stmt->execute();

        // Commit the transaction
        $conn->commit();

        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        // Rollback the transaction in case of any error
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Failed to delete event: ' . $e->getMessage()]);
    }
    exit();
}
?>