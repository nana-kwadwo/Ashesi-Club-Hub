<?php
session_start();
include '../db/config.php';

// Check if user is logged in and has appropriate permissions
if (!isset($_SESSION['user_id'])) {
    die("Unauthorized access");
}

// Get the user ID from URL or session
$user_id = isset($_GET['id']) ? intval($_GET['id']) : $_SESSION['user_id'];

// First, find the club associated with this user
$club_query = "SELECT club_id FROM clubs WHERE club_head_id = ?";
$stmt = $conn->prepare($club_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    die("No club found for this user");
}

$club_row = $result->fetch_assoc();
$club_id = $club_row['club_id'];

// Process event creation
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Sanitize and validate inputs
    $event_title = trim($_POST['event_title']);
    $event_description = trim($_POST['event_description']);
    $event_date = $_POST['event_date'];
    $total_slots = intval($_POST['total_slots']);

    // Prepare SQL to insert event
    $insert_query = "INSERT INTO events 
        (club_id, event_title, description, event_date, total_slots, current_slots, created_by) 
        VALUES (?, ?, ?, ?, ?, 0, ?)";
    
    $stmt = $conn->prepare($insert_query);
    $stmt->bind_param("isssii", 
        $club_id, 
        $event_title, 
        $event_description, 
        $event_date, 
        $total_slots, 
        $user_id
    );

    if ($stmt->execute()) {
        // Event created successfully
        $_SESSION['event_created'] = true;
        header("Location: ../view/admin/admin_dashboard.php?id=$user_id&status=event_created");
        exit();
    } else {
        // Error handling
        $_SESSION['error'] = "Failed to create event: " . $stmt->error;
        header("Location: ../view/admin/create_events.php?id=$user_id&error=true");
        exit();
    }
}
?>