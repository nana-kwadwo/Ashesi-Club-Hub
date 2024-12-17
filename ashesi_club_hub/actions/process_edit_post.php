<?php
session_start();
include '../db/config.php';

// Convert to PDO for consistency
try {
    $pdo = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    error_log("PDO Connection Error: " . $e->getMessage());
    die("Database connection failed");
}

// Check if user is logged in and has admin privileges
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] > 2) {
    $_SESSION['error'] = "Unauthorized access.";
    header("Location: ../../login_signup.php");
    exit();
}

// Validation and processing
try {
    $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
    $club_id = isset($_POST['club_id']) ? intval($_POST['club_id']) : 0;
    $post_content = trim($_POST['post_content']);
    $existing_image = $_POST['existing_image'] ?? '';
    $user_id = $_SESSION['user_id'];

    // Validate inputs
    if (empty($post_content)) {
        throw new Exception("Post content cannot be empty.");
    }

    // Handle image upload
    $image_path = $existing_image;
    if (!empty($_FILES['post_image']['name'])) {
        // Validate file upload
        if ($_FILES['post_image']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception("File upload error. Please try again.");
        }

        // Define upload directory
        $upload_dir = '../../uploads/';
        
        // Ensure directory exists
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        // Validate file type
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $file_type = mime_content_type($_FILES['post_image']['tmp_name']);
        if (!in_array($file_type, $allowed_types)) {
            throw new Exception("Invalid file type. Only images are allowed.");
        }

        // Generate unique filename
        $file_extension = pathinfo($_FILES['post_image']['name'], PATHINFO_EXTENSION);
        $filename = 'post_' . uniqid() . '.' . $file_extension;
        $upload_path = $upload_dir . $filename;

        // Move uploaded file
        if (move_uploaded_file($_FILES['post_image']['tmp_name'], $upload_path)) {
            $image_path = str_replace('../../', '', $upload_path);
            
            // If there was an existing image, remove it
            if (!empty($existing_image)) {
                $old_image_path = '../../' . $existing_image;
                if (file_exists($old_image_path)) {
                    unlink($old_image_path);
                }
            }
        } else {
            throw new Exception("Failed to upload image.");
        }
    }

    // Check if user has permission to edit this post
    $check_stmt = $pdo->prepare("SELECT 1 FROM posts p
                                  JOIN clubs c ON p.club_id = c.club_id
                                  LEFT JOIN club_memberships cm ON c.club_id = cm.club_id
                                  WHERE p.post_id = :post_id 
                                  AND (c.club_head_id = :user_id OR cm.user_id = :user_id OR :role = 1)");
    $check_stmt->bindParam(':post_id', $post_id, PDO::PARAM_INT);
    $check_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $check_stmt->bindParam(':role', $_SESSION['role'], PDO::PARAM_INT);
    $check_stmt->execute();

    if ($check_stmt->rowCount() == 0) {
        throw new Exception("You do not have permission to edit this post.");
    }

    // Begin transaction
    $pdo->beginTransaction();

    // Update post in database
    $update_stmt = $pdo->prepare("UPDATE posts 
                                   SET content = :content, 
                                       image_path = :image_path 
                                   WHERE post_id = :post_id");
    $update_stmt->bindParam(':content', $post_content, PDO::PARAM_STR);
    $update_stmt->bindParam(':image_path', $image_path, PDO::PARAM_STR);
    $update_stmt->bindParam(':post_id', $post_id, PDO::PARAM_INT);
    $update_stmt->execute();

    // Log the edit action
    $log_stmt = $pdo->prepare("INSERT INTO admin_actions_log 
                                (admin_id, action_type, target_table, target_id, details) 
                                VALUES (:admin_id, 'update', 'posts', :post_id, 'Updated post content and image')");
    $log_stmt->bindParam(':admin_id', $user_id, PDO::PARAM_INT);
    $log_stmt->bindParam(':post_id', $post_id, PDO::PARAM_INT);
    $log_stmt->execute();

    // Commit transaction
    $pdo->commit();

    // Redirect with success message
    $_SESSION['success'] = "Post updated successfully.";
    header("Location: ../view/admin/manage_posts.php");
    exit();

} catch (Exception $e) {
    // Rollback transaction if it was started
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    // Handle errors
    error_log("Post Edit Error: " . $e->getMessage());
    $_SESSION['error'] = $e->getMessage();
    header("Location: ../admin/view/edit_post.php?post_id=" . $post_id);
    exit();
}