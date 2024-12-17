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

// Fetch clubs the user has joined
$clubs_query = "SELECT c.club_id, c.club_name, c.logo_path, c.description 
                FROM clubs c
                JOIN club_memberships cm ON c.club_id = cm.club_id
                WHERE cm.user_id = ?
                ORDER BY c.club_name ASC";
$clubs_stmt = $conn->prepare($clubs_query);
$clubs_stmt->bind_param("i", $user_id);
$clubs_stmt->execute();
$clubs_result = $clubs_stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Clubs</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/profile_clubs.css">
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

    <div class="my-clubs-container">
        <div class="profile-header">
            <div class="profile-info">
                <div class="profile-name"><?php echo $full_name; ?></div>
                <div class="profile-email"><?php echo $email; ?></div>
            </div>
        </div>

        <div class="club-list">
            <?php if ($clubs_result->num_rows > 0): ?>
                <?php while ($club = $clubs_result->fetch_assoc()): ?>
                    <div class="club-item" data-club-id="<?php echo $club['club_id']; ?>">
                        <div class="club-details">
                            <img src="<?php echo !empty($club['logo_path']) ? htmlspecialchars("../../" . $club['logo_path']) : 'https://via.placeholder.com/50'; ?>"
                                alt="<?php echo htmlspecialchars($club['club_name']); ?>" class="club-logo">
                            <span class="club-name"><?php echo htmlspecialchars($club['club_name']); ?></span>
                        </div>
                        <button class="btn-leave" onclick="leaveClub(this)">Leave Club</button>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="no-clubs-message">
                    <p>You have not joined any clubs</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function leaveClub(button) {
            const clubItem = button.closest('.club-item');
            const clubId = clubItem.getAttribute('data-club-id');

            // AJAX call to remove club membership
            fetch('../actions/remove_clubs.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'club_id=' + clubId
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        clubItem.remove();

                        // Check if no clubs remain
                        const clubList = document.querySelector('.club-list');
                        if (clubList.children.length === 0) {
                            clubList.innerHTML = '<div class="no-clubs-message"><p>You have not joined any clubs</p></div>';
                        }
                    } else {
                        alert('Failed to leave club');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred');
                });
        }
    </script>
</body>

</html>

<?php
// Close the database connection
$clubs_stmt->close();
$conn->close();
?>