<?php
session_start();
// Database connection
include '../../db/config.php';

// Convert mysqli connection to PDO
try {
    $pdo = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    error_log("PDO Connection Error: " . $e->getMessage());
    die("Database connection failed");
}

// Check if user is logged in and has proper permissions
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    // Redirect to login if not authenticated
    header("Location: ../login_signup.php");
    exit();
}

// Handle post deletion if POST request is received
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_post_id'])) {
    try {
        $post_id = $_POST['delete_post_id'];
        $user_id = $_SESSION['user_id'];
        $role = $_SESSION['role'];

        // Start a transaction
        $pdo->beginTransaction();

        // Check if user has permission to delete the post
        if ($role == 1) {
            // Super admin can delete any post
            $stmt = $pdo->prepare("DELETE FROM posts WHERE post_id = :post_id");
            $stmt->bindParam(':post_id', $post_id, PDO::PARAM_INT);
        } else {
            // Club admin can only delete posts from their own clubs
            $stmt = $pdo->prepare("
                DELETE p FROM posts p
                JOIN clubs c ON p.club_id = c.club_id
                WHERE p.post_id = :post_id 
                AND (c.club_head_id = :user_id OR EXISTS (
                    SELECT 1 FROM club_memberships cm 
                    WHERE cm.club_id = c.club_id AND cm.user_id = :user_id
                ))
            ");
            $stmt->bindParam(':post_id', $post_id, PDO::PARAM_INT);
            $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        }

        $result = $stmt->execute();

        if ($result && $stmt->rowCount() > 0) {
            $pdo->commit();
            $_SESSION['success'] = "Post deleted successfully.";
            header("Location: manage_posts.php");
            exit();
        } else {
            $pdo->rollBack();
            $_SESSION['error'] = "You do not have permission to delete this post.";
            header("Location: manage_posts.php");
            exit();
        }
    } catch(PDOException $e) {
        // Roll back the transaction in case of error
        $pdo->rollBack();
        error_log("Database error: " . $e->getMessage());
        $_SESSION['error'] = "An error occurred while deleting the post.";
        header("Location: manage_posts.php");
        exit();
    }
}

try {
    // Fetch posts for the user's club (assuming club admin is viewing their club's posts)
    $user_id = $_SESSION['user_id'];
    $role = $_SESSION['role'];

    // For super admin, fetch all posts
    if ($role == 1) {
        $stmt = $pdo->prepare("SELECT p.post_id, p.content, p.image_path, c.club_name, cu.full_name 
                                FROM posts p
                                JOIN clubs c ON p.club_id = c.club_id
                                JOIN club_users cu ON p.user_id = cu.user_id
                                ORDER BY p.created_at DESC");
        $stmt->execute();
    } else {
        // For club admin, fetch posts for clubs where the user is a member or admin
        $stmt = $pdo->prepare("SELECT DISTINCT p.post_id, p.content, p.image_path, c.club_name, cu.full_name 
                                FROM posts p
                                JOIN clubs c ON p.club_id = c.club_id
                                JOIN club_users cu ON p.user_id = cu.user_id
                                LEFT JOIN club_memberships cm ON c.club_id = cm.club_id
                                LEFT JOIN clubs club_admin ON club_admin.club_head_id = :user_id
                                WHERE cm.user_id = :user_id OR club_admin.club_id = p.club_id
                                ORDER BY p.created_at DESC");
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->execute();
    }
    
    $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    // Log error and show user-friendly message
    error_log("Database error: " . $e->getMessage());
    $posts = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Posts - Club Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/manage_posts.css">
</head>
<body>
    <header>
        <img src="../../assets/images/home_logo.png" alt="Ashesi Logo" class="logo">
        <nav>
            <ul>
                <li><a href="admin_dashboard.php">Dashboard</a></li>
                <li><a href="admin_create_post.php">Create Post</a></li>
                <li><a href="manage_posts.php">Manage Posts</a></li>
                <li><a href="create_events.php">Create Events</a></li>
                <li><a href="manage_events.php">Manage Events</a></li>
                <?php if ($_SESSION['role'] == 1): ?>
                    <li><a href="manage_users.php">Manage Users</a></li>
                <?php endif; ?>
                <li><a href="../login_signup.php">Log-out</a></li>
            </ul>
        </nav>
    </header>

    <div class="manage-posts-container">
        <?php
        // Display success or error messages
        if (isset($_SESSION['success'])) {
            echo "<div class='alert alert-success'>" . htmlspecialchars($_SESSION['success']) . "</div>";
            unset($_SESSION['success']);
        }
        if (isset($_SESSION['error'])) {
            echo "<div class='alert alert-error'>" . htmlspecialchars($_SESSION['error']) . "</div>";
            unset($_SESSION['error']);
        }
        ?>

        <h2 class="manage-posts-header">Manage Posts</h2>

        <div class="posts-grid">
            <?php if (empty($posts)): ?>
                <div class="no-posts">
                    No posts available. <a href="admin_create_post.php">Create your first post</a>
                </div>
            <?php else: ?>
                <?php foreach ($posts as $post): ?>
                    <div class="post-card">
                        <?php 
                        // Corrected image path handling
                        if (!empty($post['image_path'])) {
                            $imagePath = '../../' . $post['image_path'];
                            echo "<img src='" . htmlspecialchars($imagePath) . "' alt='Post Image' class='post-image'>";
                        }
                        ?>
                        <div class="post-content">
                            <p class="post-text"><?php echo htmlspecialchars($post['content']); ?></p>
                            <div class="post-metadata">
                                <span>Club: <?php echo htmlspecialchars($post['club_name']); ?></span>
                                <span>Posted by: <?php echo htmlspecialchars($post['full_name']); ?></span>
                            </div>
                            <div class="post-actions">
                                <button class="btn btn-edit" onclick="editPost(<?php echo $post['post_id']; ?>)">Edit</button>
                                <form method="POST" onsubmit="return confirmDelete();" style="display:inline;">
                                    <input type="hidden" name="delete_post_id" value="<?php echo $post['post_id']; ?>">
                                    <button type="submit" class="btn btn-delete">Delete</button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function editPost(postId) {
            window.location.href = `edit_post.php?post_id=${postId}`;
        }

        function confirmDelete() {
            return confirm('Are you sure you want to delete this post?');
        }
    </script>
</body>
</html>
<?php
$pdo = null; // Close PDO connection
?>