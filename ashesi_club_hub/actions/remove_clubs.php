<?php
session_start();
include '../db/config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}

$user_id = $_SESSION['user_id'];
$club_id = $_POST['club_id'];

// Prepare and execute the deletion query
$query = "DELETE FROM club_memberships WHERE user_id = ? AND club_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $user_id, $club_id);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to leave club']);
}

$stmt->close();
$conn->close();
?>