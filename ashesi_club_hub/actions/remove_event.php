<?php
session_start();
include '../db/config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}

$user_id = $_SESSION['user_id'];
$event_id = $_POST['event_id'];

// Prepare and execute the deletion query
$query = "DELETE FROM event_registrations WHERE user_id = ? AND event_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $user_id, $event_id);

if ($stmt->execute()) {
    // Update current slots in events table
    $update_query = "UPDATE events SET current_slots = current_slots - 1 WHERE event_id = ?";
    $update_stmt = $conn->prepare($update_query);
    $update_stmt->bind_param("i", $event_id);
    $update_stmt->execute();

    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to leave event']);
}

$stmt->close();
$conn->close();
?>