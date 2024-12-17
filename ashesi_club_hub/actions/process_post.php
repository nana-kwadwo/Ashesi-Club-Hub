<?php
session_start();
include '../db/config.php';

// Enable error reporting for debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Check if user is logged in and has admin privileges
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] > 2) {
    header("Location: ../login_signup.php");
    exit();
}

// Check if the form was submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $user_id = $_SESSION['user_id'];
    $club_id = filter_input(INPUT_POST, 'club_id', FILTER_VALIDATE_INT);
    $post_content = trim(filter_input(INPUT_POST, 'post_content', FILTER_SANITIZE_STRING));

    // Validate inputs
    if (empty($club_id) || empty($post_content)) {
        $_SESSION['error'] = "Please fill in all required fields.";
        header("Location: ../view/admin/admin_create_post.php?id=$user_id");
        exit();
    }


    // File upload handling for post image
    $imagePath = null;
    if (isset($_FILES['post_image']) && $_FILES['post_image']['error'] == 0) {
        $uploadDir = '../../uploads/';
        
        // Ensure upload directory exists
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        // Generate unique filename
        $fileName = uniqid() . '_' . basename($_FILES['post_image']['name']);
        $targetFilePath = $uploadDir . $fileName;
        
        // Validate file type 
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif','image/jpg'];
        $fileType = mime_content_type($_FILES['post_image']['tmp_name']);
        
        if (in_array($fileType, $allowedTypes)) {
            // Move uploaded file
            if (move_uploaded_file($_FILES['post_image']['tmp_name'], $targetFilePath)) {
                $imagePath = str_replace('../../', '', $targetFilePath);
            } else {
                $_SESSION['error'] = "Failed to upload image.";
                header("Location: ../view/admin/admin_create_post.php?id=$user_id");
                exit();
            }
        } else {
            $_SESSION['error'] = "Invalid file type. Only JPEG, PNG, and GIF are allowed.";
            header("Location: ../view/admin/admin_create_post.php?id=$user_id");
            exit();
        }
    }

    // Prepare and execute the insert query
    $insert_query = "INSERT INTO posts (club_id, user_id, content, image_path) VALUES (?, ?, ?, ?)";
    $insert_stmt = $conn->prepare($insert_query);
    $insert_stmt->bind_param("iiss", $club_id, $user_id, $post_content, $imagePath);

    try {
        // Begin transaction
        $conn->begin_transaction();
        
        if ($insert_stmt->execute()) {
            $post_id = $conn->insert_id;
            
            // Log the admin action
            $log_stmt = $conn->prepare("
                INSERT INTO admin_actions_log 
                (admin_id, action_type, target_table, target_id, details) 
                VALUES (?, 'create', 'posts', ?, ?)
            ");
            $log_details = "Created post in club_id: $club_id";
            $log_stmt->bind_param("iis", $user_id, $post_id, $log_details);
            
            if ($log_stmt->execute()) {
                // Commit transaction
                $conn->commit();
                
                // Set session variable for success
                $_SESSION['post_success'] = true;
                
                // Redirect to admin dashboard
                header("Location: ../view/admin/admin_dashboard.php?id=$user_id");
                exit();
            } else {
                throw new Exception("Error logging admin action: " . $log_stmt->error);
            }
        } else {
            throw new Exception("Error creating post: " . $insert_stmt->error);
        }
    } catch (Exception $e) {
        // Rollback transaction
        $conn->rollback();
        
        // Set error message
        $_SESSION['error'] = $e->getMessage();
        error_log($e->getMessage());
        
        // Redirect back to post creation page
        header("Location: ../view/admin/admin_create_post.php?id=$user_id");
        exit();
    }
} else {
    // If accessed directly without POST method
    header("Location: ../view/admin/admin_create_post.php?id=$user_id");
    exit();
}
?>