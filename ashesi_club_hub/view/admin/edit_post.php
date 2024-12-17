<?php
session_start();
include '../../db/config.php';

// Convert to PDO for consistency with other scripts
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

$user_id = $_SESSION['user_id'];
$post_id = isset($_GET['post_id']) ? intval($_GET['post_id']) : 0;

// Fetch post details
try {
    // Check if the user has permission to edit this post
    $stmt = $pdo->prepare("SELECT p.post_id, p.content, p.image_path, p.club_id, c.club_name 
                            FROM posts p
                            JOIN clubs c ON p.club_id = c.club_id
                            LEFT JOIN club_memberships cm ON c.club_id = cm.club_id
                            WHERE p.post_id = :post_id 
                            AND (c.club_head_id = :user_id OR cm.user_id = :user_id OR :role = 1)");
    $stmt->bindParam(':post_id', $post_id, PDO::PARAM_INT);
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->bindParam(':role', $_SESSION['role'], PDO::PARAM_INT);
    $stmt->execute();
    
    $post = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$post) {
        // No post found or user doesn't have permission
        $_SESSION['error'] = "You do not have permission to edit this post.";
        header("Location: manage_posts.php");
        exit();
    }
} catch(PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    $_SESSION['error'] = "An error occurred while fetching the post.";
    header("Location: manage_posts.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Post - Club Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/create_post.css">
</head>
<body>
    <header>
        <img src="../../assets/images/home_logo.png" alt="Ashesi Logo" class="logo">
        <nav>
            <ul>
                <li><a href="admin_dashboard.php?id=<?php echo $user_id; ?>">Dashboard</a></li>
                <li><a href="admin_create_post.php?id=<?php echo $user_id; ?>">Create Post</a></li>
                <li><a href="manage_posts.php?id=<?php echo $user_id; ?>">Manage Posts</a></li>
                <li><a href="create_events.php?id=<?php echo $user_id; ?>">Create Events</a></li>
                <li><a href="manage_events.php?id=<?php echo $user_id; ?>">Manage Events</a></li>
                <?php if ($_SESSION['role'] == 1): ?>
                    <li><a href="manage_users.php?id=<?php echo $user_id; ?>">Manage Users</a></li>
                <?php endif; ?>
                <li><a href="../login_signup.php">Log-out</a></li>
            </ul>
        </nav>
    </header>

    <div class="create-post-container">
        <form action="../../actions/process_edit_post.php" method="POST" enctype="multipart/form-data" class="post-form">
            <h2>Edit Post for <?php echo htmlspecialchars($post['club_name']); ?></h2>

            <!-- Hidden inputs for post and club identification -->
            <input type="hidden" name="post_id" value="<?php echo $post_id; ?>">
            <input type="hidden" name="club_id" value="<?php echo $post['club_id']; ?>">
            <input type="hidden" name="existing_image" value="<?php echo htmlspecialchars($post['image_path'] ?? ''); ?>">

            <div class="image-upload-container">
                <label for="post-image" class="image-upload-label">
                    <span class="upload-icon">+</span>
                    <span class="upload-text">Upload New Image</span>
                    <input type="file" id="post-image" name="post_image" accept="image/*" class="image-upload-input">
                </label>
                
                <!-- Display existing image if it exists -->
                <div id="image-preview" class="image-preview">
                    <?php if (!empty($post['image_path'])): ?>
                        <img src="../../<?php echo htmlspecialchars($post['image_path']); ?>" alt="Existing Post Image">
                        <div class="remove-image-overlay">
                            <span>Current Image</span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="text-input-container">
                <textarea id="post-content" name="post_content" placeholder="Write your post content here..."
                    class="post-textarea" required><?php echo htmlspecialchars($post['content']); ?></textarea>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-post">Update Post</button>
                <button type="button" class="btn btn-cancel" onclick="window.location.href='manage_posts.php'">Cancel</button>
            </div>
        </form>
    </div>

    <script>
        document.getElementById('post-image').addEventListener('change', function (event) {
            const file = event.target.files[0];
            const preview = document.getElementById('image-preview');

            if (file) {
                const reader = new FileReader();
                reader.onload = function (e) {
                    preview.innerHTML = `
                        <img src="${e.target.result}" alt="Image Preview">
                        <div class="remove-image-overlay">
                            <span>New Image</span>
                        </div>
                    `;
                    preview.style.display = 'block';
                }
                reader.readAsDataURL(file);
            }
        });
    </script>
</body>
</html>