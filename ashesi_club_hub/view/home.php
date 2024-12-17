<?php
// Start the session at the beginning of the file
session_start();

// Include necessary files
include '../db/config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    // Redirect to login page if not logged in
    header("Location: login.php");
    exit();
}

// Get the user ID from the session
$user_id = $_SESSION['user_id'];

// Function to fetch posts with likes and comments
function fetchPosts($conn)
{
    $query = "SELECT p.*, c.club_name, 
              (SELECT COUNT(*) FROM likes l WHERE l.post_id = p.post_id) AS like_count,
              (SELECT COUNT(*) FROM comments cm WHERE cm.post_id = p.post_id) AS comment_count
              FROM posts p
              JOIN clubs c ON p.club_id = c.club_id
              ORDER BY p.created_at DESC";
    $result = $conn->query($query);
    return $result;
}

// Improved image path validation function
function getValidImagePath($imagePath) {
    $uploadDir = '../../uploads/';
    $placeholderPath = $uploadDir . 'placeholder.jpg';
    
    // If no image path provided, use placeholder
    if (empty($imagePath)) {
        return $placeholderPath;
    }
    
    // Full path to the image
    $fullImagePath = $uploadDir . $imagePath;
    
    // Check if image exists
    return file_exists($fullImagePath) ? $fullImagePath : $placeholderPath;
}

// Handle Like Action
if (isset($_POST['like_post'])) {
    $post_id = $_POST['post_id'];

    // Check if user has already liked the post
    $check_like_query = "SELECT * FROM likes WHERE user_id = ? AND post_id = ?";
    $check_stmt = $conn->prepare($check_like_query);
    $check_stmt->bind_param("ii", $user_id, $post_id);
    $check_stmt->execute();
    $like_result = $check_stmt->get_result();

    if ($like_result->num_rows == 0) {
        // Insert like
        $like_query = "INSERT INTO likes (user_id, post_id) VALUES (?, ?)";
        $like_stmt = $conn->prepare($like_query);
        $like_stmt->bind_param("ii", $user_id, $post_id);
        $like_stmt->execute();
    } else {
        // Remove like (unlike)
        $unlike_query = "DELETE FROM likes WHERE user_id = ? AND post_id = ?";
        $unlike_stmt = $conn->prepare($unlike_query);
        $unlike_stmt->bind_param("ii", $user_id, $post_id);
        $unlike_stmt->execute();
    }

    // Redirect to prevent form resubmission
    header("Location: home.php");
    exit();
}

// Handle Comment Action
if (isset($_POST['add_comment'])) {
    $post_id = $_POST['post_id'];
    $comment_content = trim($_POST['comment_content']);

    if (!empty($comment_content)) {
        $comment_query = "INSERT INTO comments (user_id, post_id, content) VALUES (?, ?, ?)";
        $comment_stmt = $conn->prepare($comment_query);
        $comment_stmt->bind_param("iis", $user_id, $post_id, $comment_content);
        $comment_stmt->execute();

        // Redirect to prevent form resubmission
        header("Location: home.php");
        exit();
    }
}

// Fetch posts
$posts = fetchPosts($conn);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ashesi Club Hub</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/home.css">
</head>

<body>
    <header>
        <img src="../assets/images/home_logo.png" alt="Ashesi Logo" class="logo">
        <nav>
            <ul>
                <li><a href="home.php?id=<?php echo $user_id; ?>">Home</a></li>
                <li><a href="club_discovery.php?id=<?php echo $user_id; ?>">Club Discovery</a></li>
                <li><a href="events.php?id=<?php echo $user_id; ?>">Events</a></li>
                <li><a href="profile.php?id=<?php echo $user_id; ?>">Profile</a></li>
            </ul>
        </nav>
    </header>

    <div class="content">
        <?php 
        // Check if there are any posts
        if ($posts->num_rows > 0):
            while ($post = $posts->fetch_assoc()): 
        ?>
            <div class="post">
                <div class="post-header">
                    <h3><?php echo htmlspecialchars($post['club_name']); ?></h3>
                </div>
                <div class="post-content">
                    <?php 
                    // Use the new image path validation function
                    //var_dump($post['image_path']);
                   // $validImagePath = getValidImagePath($post['image_path']);
                    // var_dump($validImagePath);
                    //$displayImagePath = str_replace('/uploads', 'uploads', $validImagePath);
                    ?>
                    <img src="<?php echo htmlspecialchars("../../" . $post['image_path']); ?>" alt="Club Activity">
                    <p><?php echo htmlspecialchars($post['content']); ?></p>
                </div>
                <div class="post-actions">
                    <form method="POST" style="display:inline;">
                        <input type="hidden" name="post_id" value="<?php echo $post['post_id']; ?>">
                        <button type="submit" name="like_post"
                            style="background:none; border:none; display:flex; align-items:center; cursor:pointer;">
                            <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path
                                    d="M12 21.35L10.55 20.03C5.4 15.36 2 12.27 2 8.5C2 5.41 4.42 3 7.5 3C9.24 3 10.91 3.81 12 5.08C13.09 3.81 14.76 3 16.5 3C19.58 3 22 5.41 22 8.5C22 12.27 18.6 15.36 13.45 20.03L12 21.35Z" />
                            </svg>
                            <?php echo $post['like_count']; ?> likes
                        </button>
                    </form>
                    <button>
                        <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M22 4H2V16H6V22L14 16H22V4Z" />
                        </svg>
                        <?php echo $post['comment_count']; ?> comments
                    </button>
                </div>

                <!-- Comments Section -->
                <div class="post-comments">
                    <?php
                    // Fetch comments for this post
                    $post_id = $post['post_id'];
                    $comments_query = "SELECT c.*, u.full_name 
                                   FROM comments c 
                                   JOIN club_users u ON c.user_id = u.user_id 
                                   WHERE c.post_id = ? 
                                   ORDER BY c.created_at DESC";
                    $comments_stmt = $conn->prepare($comments_query);
                    $comments_stmt->bind_param("i", $post_id);
                    $comments_stmt->execute();
                    $comments_result = $comments_stmt->get_result();

                    while ($comment = $comments_result->fetch_assoc()): ?>
                        <div class="comment">
                            <p><strong><?php echo htmlspecialchars($comment['full_name']); ?>:</strong>
                                <?php echo htmlspecialchars($comment['content']); ?></p>
                        </div>
                    <?php endwhile; ?>

                    <!-- Comment Form -->
                    <form method="POST" class="comment-form">
                        <input type="hidden" name="post_id" value="<?php echo $post_id; ?>">
                        <input type="text" name="comment_content" placeholder="Write a comment..." required>
                        <button type="submit" name="add_comment">Send</button>
                    </form>
                </div>
            </div>
        <?php 
            endwhile; 
        else:
            echo "<p>No posts available.</p>";
        endif;
        ?>
    </div>
</body>

</html>