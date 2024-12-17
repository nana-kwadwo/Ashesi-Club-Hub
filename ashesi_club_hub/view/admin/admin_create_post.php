<?php
session_start();
include '../../db/config.php';

// Check if user is logged in and has admin privileges
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] > 2) {
    header("Location: ../../login_signup.php");
    exit();
}

$user_id = $_SESSION['user_id'];
// Query to find clubs where the user is the club head
$clubs_query = "SELECT club_id, club_name FROM clubs WHERE club_head_id = ?";
$clubs_stmt = $conn->prepare($clubs_query);
$clubs_stmt->bind_param("i", $user_id);
$clubs_stmt->execute();
$clubs_result = $clubs_stmt->get_result();

// If no clubs found, redirect or show an error
if ($clubs_result->num_rows == 0) {
    $_SESSION['error'] = "You are not a head of any clubs.";
    header("Location: admin_dashboard.php?id=$user_id");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Post - Club Admin</title>
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
                <li><a href="../../login_signup.php">Log-out</a></li>
            </ul>
        </nav>
    </header>

    <div class="create-post-container">
        <?php
        // Display error messages
        if (isset($_SESSION['error'])) {
            echo "<div class='error-message'>" . htmlspecialchars($_SESSION['error']) . "</div>";
            unset($_SESSION['error']);
        }

        // Fetch and display club information
        $current_club = $clubs_result->fetch_assoc();
        ?>
        <form action="../../actions/process_post.php" method="POST" enctype="multipart/form-data" class="post-form">
            <h2>Create Post for <?php echo htmlspecialchars($current_club['club_name']); ?></h2>

            <!-- Hidden input for club_id -->
            <input type="hidden" name="club_id" value="<?php echo $current_club['club_id']; ?>">

            <div class="image-upload-container">
                <label for="post-image" class="image-upload-label">
                    <span class="upload-icon">+</span>
                    <span class="upload-text">Upload Image</span>
                    <input type="file" id="post-image" name="post_image" accept="image/*" class="image-upload-input">
                </label>
                <div id="image-preview" class="image-preview"></div>
            </div>

            <div class="text-input-container">
                <textarea id="post-content" name="post_content" placeholder="Write your post content here..."
                    class="post-textarea" required></textarea>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-post">Post</button>
                <button type="button" class="btn btn-cancel" onclick="window.location.href='admin_dashboard.php?id=<?php echo $user_id; ?>'">Cancel</button>
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
                    preview.innerHTML = `<img src="${e.target.result}" alt="Image Preview">`;
                    preview.style.display = 'block';
                }
                reader.readAsDataURL(file);
            }
        });
    </script>
</body>

</html>
<?php
$clubs_stmt->close();
$conn->close();
?>