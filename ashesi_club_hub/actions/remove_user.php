<?php
session_start();
include '../../db/config.php';

// Response array to send back JSON
$response = [
    'success' => false,
    'message' => ''
];

// Authentication check
if (!isset($_SESSION['user_id'])) {
    $response['message'] = 'Unauthorized access';
    echo json_encode($response);
    exit();
}

$current_user_id = $_SESSION['user_id'];
$user_to_remove_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;

if ($user_to_remove_id <= 0) {
    $response['message'] = 'Invalid user ID';
    echo json_encode($response);
    exit();
}

try {
    // First, verify that the current user is a club head
    $verify_club_head_query = "
        SELECT club_id 
        FROM clubs 
        WHERE club_head_id = ?
    ";
    $stmt = $conn->prepare($verify_club_head_query);
    $stmt->bind_param("i", $current_user_id);
    $stmt->execute();
    $club_result = $stmt->get_result();
    
    if ($club_result->num_rows == 0) {
        $response['message'] = 'You are not authorized to remove members';
        echo json_encode($response);
        exit();
    }
    
    $club = $club_result->fetch_assoc();
    $club_id = $club['club_id'];

    // Check if the user to remove is actually a member of this club
    $check_membership_query = "
        SELECT membership_id 
        FROM club_memberships 
        WHERE user_id = ? AND club_id = ?
    ";
    $stmt = $conn->prepare($check_membership_query);
    $stmt->bind_param("ii", $user_to_remove_id, $club_id);
    $stmt->execute();
    $membership_result = $stmt->get_result();
    
    if ($membership_result->num_rows == 0) {
        $response['message'] = 'User is not a member of this club';
        echo json_encode($response);
        exit();
    }

    // Remove the user from club memberships
    $remove_member_query = "
        DELETE FROM club_memberships 
        WHERE user_id = ? AND club_id = ?
    ";
    $stmt = $conn->prepare($remove_member_query);
    $stmt->bind_param("ii", $user_to_remove_id, $club_id);
    
    if ($stmt->execute()) {
        // Log the admin action
        $log_query = "
            INSERT INTO admin_actions_log 
            (admin_id, action_type, target_table, target_id, details) 
            VALUES (?, 'delete', 'club_memberships', ?, ?)
        ";
        $stmt = $conn->prepare($log_query);
        $details = "Removed user ID $user_to_remove_id from club ID $club_id";
        $stmt->bind_param("iis", $current_user_id, $user_to_remove_id, $details);
        $stmt->execute();

        $response['success'] = true;
        $response['message'] = 'Member successfully removed from the club';
    } else {
        $response['message'] = 'Failed to remove member';
    }
} catch (Exception $e) {
    $response['message'] = 'An error occurred: ' . $e->getMessage();
} finally {
    echo json_encode($response);
    exit();
}