<?php
session_start();
// Database connection
include '../../db/config.php';

// Check if user is logged in and has admin privileges
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] > 2) {
    header("Location: ../../login_signup.php");
    exit();
}

$user_id = $_SESSION['user_id'];
try {
    // Get the club ID for the logged-in admin
    $userId = $_SESSION['user_id'];
    
    // Prepare club query using MySQLi - updated to match clubs table
    $clubQuery = "SELECT club_id, club_name FROM clubs WHERE club_head_id = ?";
    $clubStmt = $conn->prepare($clubQuery);
    $clubStmt->bind_param("i", $userId);
    $clubStmt->execute();
    $clubResult = $clubStmt->get_result();
    $clubDetails = $clubResult->fetch_assoc();

    if (!$clubDetails) {
        throw new Exception("No club found for this admin");
    }

    $clubId = $clubDetails['club_id'];
    $clubName = $clubDetails['club_name'];

    // Total Members Query - using club_memberships table
    $membersQuery = "SELECT COUNT(*) as total_members 
                     FROM club_memberships 
                     WHERE club_id = ?";
    $membersStmt = $conn->prepare($membersQuery);
    $membersStmt->bind_param("i", $clubId);
    $membersStmt->execute();
    $membersResult = $membersStmt->get_result();
    $totalMembers = $membersResult->fetch_assoc()['total_members'];

    // Recent Likes Query - using likes and posts tables
    $likesQuery = "SELECT COUNT(*) as recent_likes 
                   FROM likes l
                   JOIN posts p ON l.post_id = p.post_id
                   WHERE p.club_id = ? 
                   AND l.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
    $likesStmt = $conn->prepare($likesQuery);
    $likesStmt->bind_param("i", $clubId);
    $likesStmt->execute();
    $likesResult = $likesStmt->get_result();
    $recentLikes = $likesResult->fetch_assoc()['recent_likes'];

    // Recent Comments Query - using comments and posts tables
    $commentsQuery = "SELECT COUNT(*) as recent_comments 
                      FROM comments c
                      JOIN posts p ON c.post_id = p.post_id
                      WHERE p.club_id = ? 
                      AND c.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
    $commentsStmt = $conn->prepare($commentsQuery);
    $commentsStmt->bind_param("i", $clubId);
    $commentsStmt->execute();
    $commentsResult = $commentsStmt->get_result();
    $recentComments = $commentsResult->fetch_assoc()['recent_comments'];

    // Upcoming Event Attendees Query - using event_registrations and events tables
    $eventsQuery = "SELECT COUNT(*) as upcoming_attendees 
                    FROM event_registrations er
                    JOIN events e ON er.event_id = e.event_id
                    WHERE e.club_id = ? 
                    AND e.status = 'upcoming'";
    $eventsStmt = $conn->prepare($eventsQuery);
    $eventsStmt->bind_param("i", $clubId);
    $eventsStmt->execute();
    $eventsResult = $eventsStmt->get_result();
    $upcomingEventAttendees = $eventsResult->fetch_assoc()['upcoming_attendees'];

} catch (Exception $e) {
    // Log error and set default values
    error_log("Dashboard Error: " . $e->getMessage());
    $totalMembers = 0;
    $recentLikes = 0;
    $recentComments = 0;
    $upcomingEventAttendees = 0;
    $clubName = "Club";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($clubName); ?> Admin Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/admin_dashboard.css">
    <!-- SweetAlert2 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <!-- SweetAlert2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
</head>
<body>
    <header>
        <img src="../../assets/images/home_logo.png" alt="Ashesi Logo" class="logo">
        <nav>
            <ul>
                <li style="color:white;">Welcome, <?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Admin'); ?></li>
            </ul>
        </nav>
    </header>

    <div class="dashboard-container">
        <h1><?php echo htmlspecialchars($clubName); ?> Club Dashboard</h1>

        <div class="action-buttons">
            <button class="btn"><a href="admin_create_post.php?id=<?php echo $user_id;?>" style="color: white;">Create Post</a></button>
            <button class="btn"><a href="manage_posts.php?id=<?php echo $user_id;?>" style="color: white;">Manage Posts</a></button>
            <button class="btn"><a href="create_events.php?id=<?php echo $user_id;?>" style="color: white;">Create Events</a></button>
            <button class="btn"><a href="manage_events.php?id=<?php echo $user_id;?>" style="color: white;">Manage Events</a></button>
            <button class="btn"><a href="manage_users.php?id=<?php echo $user_id;?>" style="color: white;">Manage Members</a></button>
        </div>

        <div class="analytics-container">
            <div class="club-card">
                <h3>Total Members</h3>
                <div class="analytics-number"><?php echo number_format($totalMembers); ?></div>
            </div>

            <div class="club-card">
                <h3>Recent Likes</h3>
                <div class="analytics-number"><?php echo number_format($recentLikes); ?></div>
            </div>

            <div class="club-card">
                <h3>Recent Comments</h3>
                <div class="analytics-number"><?php echo number_format($recentComments); ?></div>
            </div>

            <div class="club-card full-width">
                <h3>Upcoming Event Attendees</h3>
                <div class="analytics-number"><?php echo number_format($upcomingEventAttendees); ?></div>
            </div>

            <div>
                <button class="btn btn-logout">
                    <a href="../login_signup.php" style="color:white">Logout</a>
                </button>
            </div>
        </div>
    </div>

    <script>
        // Check for post success and show SweetAlert
        <?php if(isset($_SESSION['post_success']) && $_SESSION['post_success'] === true): ?>
            Swal.fire({
                icon: 'success',
                title: 'Post Created Successfully!',
                text: 'Your post has been published.',
                showConfirmButton: false,
                timer: 3000
            });
            
            <?php 
            // Clear the session variable 
            unset($_SESSION['post_success']); 
            ?>
        <?php endif; ?>

        // Check for other potential success messages
        <?php if(isset($_SESSION['success'])): ?>
            Swal.fire({
                icon: 'success',
                title: 'Success!',
                text: '<?php echo htmlspecialchars($_SESSION['success']); ?>',
                showConfirmButton: false,
                timer: 3000
            });
            
            <?php 
            // Clear the session variable 
            unset($_SESSION['success']); 
            ?>
        <?php endif; ?>

        // Check for error messages
        <?php if(isset($_SESSION['error'])): ?>
            Swal.fire({
                icon: 'error',
                title: 'Oops...',
                text: '<?php echo htmlspecialchars($_SESSION['error']); ?>',
                showConfirmButton: true
            });
            
            <?php 
            // Clear the session variable 
            unset($_SESSION['error']); 
            ?>
        <?php endif; ?>
    </script>
</body>
</html>
<?php
// Close database connections
if (isset($clubStmt)) $clubStmt->close();
if (isset($membersStmt)) $membersStmt->close();
if (isset($likesStmt)) $likesStmt->close();
if (isset($commentsStmt)) $commentsStmt->close();
if (isset($eventsStmt)) $eventsStmt->close();
if (isset($conn)) $conn->close();
?>