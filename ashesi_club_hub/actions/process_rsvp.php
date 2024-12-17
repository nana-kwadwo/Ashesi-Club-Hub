<?php
session_start();
include '../db/config.php';

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false, 
        'message' => 'Please log in to RSVP'
    ]);
    exit();
}

$user_id = $_SESSION['user_id'];
$event_id = $_POST['event_id'] ?? null;

if (!$event_id) {
    echo json_encode([
        'success' => false, 
        'message' => 'Invalid event'
    ]);
    exit();
}

// Check if user is already registered for this event
$check_query = "SELECT * FROM event_registrations WHERE user_id = ? AND event_id = ?";
$check_stmt = $conn->prepare($check_query);
$check_stmt->bind_param("ii", $user_id, $event_id);
$check_stmt->execute();
$existing_registration = $check_stmt->get_result()->fetch_assoc();

$response = [];

if ($existing_registration) {
    // User is already registered, so remove registration
    $delete_query = "DELETE FROM event_registrations WHERE user_id = ? AND event_id = ?";
    $delete_stmt = $conn->prepare($delete_query);
    $delete_stmt->bind_param("ii", $user_id, $event_id);
    
    // Update event current slots
    $update_slots_query = "UPDATE events SET current_slots = current_slots - 1 WHERE event_id = ?";
    $update_slots_stmt = $conn->prepare($update_slots_query);
    $update_slots_stmt->bind_param("i", $event_id);
    
    if ($delete_stmt->execute() && $update_slots_stmt->execute()) {
        // Fetch updated event details
        $event_query = "SELECT current_slots, total_slots FROM events WHERE event_id = ?";
        $event_stmt = $conn->prepare($event_query);
        $event_stmt->bind_param("i", $event_id);
        $event_stmt->execute();
        $event_result = $event_stmt->get_result()->fetch_assoc();
        
        $response = [
            'success' => true, 
            'message' => 'Successfully unregistered from the event',
            'current_slots' => $event_result['current_slots'],
            'total_slots' => $event_result['total_slots']
        ];
    } else {
        $response = [
            'success' => false, 
            'message' => 'Failed to remove registration'
        ];
    }
} else {
    // Check if event has available slots
    $check_slots_query = "SELECT current_slots, total_slots FROM events WHERE event_id = ?";
    $check_slots_stmt = $conn->prepare($check_slots_query);
    $check_slots_stmt->bind_param("i", $event_id);
    $check_slots_stmt->execute();
    $event_details = $check_slots_stmt->get_result()->fetch_assoc();
    
    if ($event_details['current_slots'] >= $event_details['total_slots']) {
        $response = [
            'success' => false, 
            'message' => 'Event is full'
        ];
    } else {
        // Add registration
        $insert_query = "INSERT INTO event_registrations (user_id, event_id) VALUES (?, ?)";
        $insert_stmt = $conn->prepare($insert_query);
        $insert_stmt->bind_param("ii", $user_id, $event_id);
        
        // Update event current slots
        $update_slots_query = "UPDATE events SET current_slots = current_slots + 1 WHERE event_id = ?";
        $update_slots_stmt = $conn->prepare($update_slots_query);
        $update_slots_stmt->bind_param("i", $event_id);
        
        if ($insert_stmt->execute() && $update_slots_stmt->execute()) {
            // Fetch updated event details
            $event_query = "SELECT current_slots, total_slots FROM events WHERE event_id = ?";
            $event_stmt = $conn->prepare($event_query);
            $event_stmt->bind_param("i", $event_id);
            $event_stmt->execute();
            $event_result = $event_stmt->get_result()->fetch_assoc();
            
            $response = [
                'success' => true, 
                'message' => 'Successfully registered for the event',
                'current_slots' => $event_result['current_slots'],
                'total_slots' => $event_result['total_slots']
            ];
        } else {
            $response = [
                'success' => false, 
                'message' => 'Failed to register for the event'
            ];
        }
    }
}

// Send JSON response
header('Content-Type: application/json');
echo json_encode($response);

// Close connections
$conn->close();
?>