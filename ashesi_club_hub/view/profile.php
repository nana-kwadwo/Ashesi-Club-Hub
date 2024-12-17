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

// Fetch user details from the database
$query = "SELECT full_name, email FROM club_users WHERE user_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $user = $result->fetch_assoc();
    $full_name = htmlspecialchars($user['full_name']);
    $email = htmlspecialchars($user['email']);
} else {
    // Fallback if user not found
    $full_name = "User";
    $email = "user@ashesi.edu.gh";
}
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ashesi Profile</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/profile.css">

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

    <div class="profile-container">
        <div class="profile-header">
            <div class="profile-name"><?php echo $full_name; ?></div>
            <div class="profile-email"><?php echo $email; ?></div>
        </div>

        <div class="profile-menu">
            <div class="profile-item" onclick="navigateToClubs()">
                My Clubs
                <span class="profile-item-icon">→</span>
            </div>

            <div class="profile-item" onclick="navigateToEvents()">
                My Events
                <span class="profile-item-icon">→</span>
            </div>

            <div class="profile-item logout-item" onclick="logout()">
                Logout
                <span class="profile-item-icon">→</span>
            </div>
        </div>

        <script>
            function navigateToClubs() {
                window.location.href = 'profile_clubs.php?id=<?php echo $user_id; ?>';
            }

            function navigateToEvents() {
                window.location.href = 'profile_events.php?id=<?php echo $user_id; ?>';
            }

            function logout() {
                window.location.href = 'login_signup.php';
            }
        </script>
</body>

</html>