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

// Handle club join functionality
if (isset($_POST['join_club'])) {
    $club_id = $_POST['club_id'];

    // Check if user is already a member
    $check_membership = $conn->prepare("SELECT * FROM club_memberships WHERE user_id = ? AND club_id = ?");
    $check_membership->bind_param("ii", $user_id, $club_id);
    $check_membership->execute();
    $membership_result = $check_membership->get_result();

    if ($membership_result->num_rows == 0) {
        // Insert new membership
        $join_club = $conn->prepare("INSERT INTO club_memberships (user_id, club_id) VALUES (?, ?)");
        $join_club->bind_param("ii", $user_id, $club_id);

        if ($join_club->execute()) {
            // Store success message in session for SweetAlert
            $_SESSION['alert'] = [
                'type' => 'success',
                'message' => 'Successfully joined the club!'
            ];
        } else {
            // Store error message in session for SweetAlert
            $_SESSION['alert'] = [
                'type' => 'error',
                'message' => 'Error joining the club. Please try again.'
            ];
        }
    } else {
        // Store already a member message in session for SweetAlert
        $_SESSION['alert'] = [
            'type' => 'info',
            'message' => 'You are already a member of this club.'
        ];
    }

    // Redirect to prevent form resubmission
    header("Location: club_discovery.php");
    exit();
}

// Fetch ALL clubs from the database (removed status filter)
$clubs_query = "SELECT * FROM clubs";
$clubs_result = $conn->query($clubs_query);

// Check if there are any clubs
if (!$clubs_result) {
    die("Error fetching clubs: " . $conn->error);
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ashesi Club Discovery</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/club_discovery.css">
    <!-- SweetAlert2 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
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


    <div class="clubs-container">
        <?php
        // Check if there are any clubs
        if ($clubs_result->num_rows > 0) {
            while ($club = $clubs_result->fetch_assoc()):
                // Check if user is already a member
                $membership_check = $conn->prepare("SELECT * FROM club_memberships WHERE user_id = ? AND club_id = ?");
                $membership_check->bind_param("ii", $user_id, $club['club_id']);
                $membership_check->execute();
                $is_member = $membership_check->get_result()->num_rows > 0;
                ?>
                <div class="club-card">
                    <h3><?php echo htmlspecialchars($club['club_name']); ?></h3>
                    <p><?php echo htmlspecialchars($club['description'] ?? 'No description available'); ?></p>
                    <div class="club-actions">
                        <button class="btn btn-learn">Learn More</button>
                        <form method="POST" style="width: 100%;">
                            <input type="hidden" name="club_id" value="<?php echo $club['club_id']; ?>">
                            <button type="submit" name="join_club" class="btn btn-join" <?php echo $is_member ? 'disabled' : ''; ?>>
                                <?php echo $is_member ? 'Joined' : 'Join Club'; ?>
                            </button>
                        </form>
                    </div>
                </div>
            <?php
            endwhile;
        } else {
            // Display a message if no clubs exist
            echo "<p style='width: 100%; text-align: center;'>No clubs are currently available.</p>";
        }
        ?>
    </div>

    <!-- SweetAlert2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>

    <script>
        // Check for any alert messages from the session
        <?php if (isset($_SESSION['alert'])): ?>
            Swal.fire({
                icon: '<?php echo $_SESSION['alert']['type']; ?>',
                title: '<?php echo $_SESSION['alert']['message']; ?>',
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 3000,
                timerProgressBar: true
            });

            <?php
            // Clear the alert from the session
            unset($_SESSION['alert']);
            ?>
        <?php endif; ?>
    </script>
</body>

</html>