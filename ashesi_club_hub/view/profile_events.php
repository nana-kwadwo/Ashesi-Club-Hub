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

// Fetch events the user has registered for
$events_query = "SELECT e.event_id, e.event_title, e.event_date, c.club_name 
                 FROM events e 
                 JOIN event_registrations er ON e.event_id = er.event_id 
                 JOIN clubs c ON e.club_id = c.club_id 
                 WHERE er.user_id = ?
                 ORDER BY e.event_date ASC";
$events_stmt = $conn->prepare($events_query);
$events_stmt->bind_param("i", $user_id);
$events_stmt->execute();
$events_result = $events_stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Events</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/profile_events.css">
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

    <div class="my-events-container">
        <div class="profile-header">
            <button class="btn-join" onclick="goBack()">←</button>
            <div class="profile-info">
                <div class="profile-name"><?php echo $full_name; ?></div>
                <div class="profile-email"><?php echo $email; ?></div>
            </div>
        </div>

        <div class="event-list">
            <?php if ($events_result->num_rows > 0): ?>
                <?php while ($event = $events_result->fetch_assoc()): ?>
                    <div class="event-item" data-event-id="<?php echo $event['event_id']; ?>">
                        <div class="event-header">
                            <span class="event-title"><?php echo htmlspecialchars($event['event_title']); ?></span>
                            <span class="event-date"><?php echo date('F j, Y | g:00 A', strtotime($event['event_date'])); ?></span>
                        </div>

                        <div class="event-details">
                            <div class="event-club">
                                <img src="https://via.placeholder.com/40" alt="<?php echo htmlspecialchars($event['club_name']); ?>">
                                <span><?php echo htmlspecialchars($event['club_name']); ?></span>
                            </div>
                            <button class="btn-leave" onclick="leaveEvent(this)">Leave Event</button>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="no-events-message">
                    <p>You have no events</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function leaveEvent(button) {
            const eventItem = button.closest('.event-item');
            const eventId = eventItem.getAttribute('data-event-id');

            // AJAX call to remove event registration
            fetch('../actions/remove_event.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'event_id=' + eventId
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    eventItem.remove();
                    
                    // Check if no events remain
                    const eventList = document.querySelector('.event-list');
                    if (eventList.children.length === 0) {
                        eventList.innerHTML = '<div class="no-events-message"><p>You have no events</p></div>';
                    }
                } else {
                    alert('Failed to leave event');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred');
            });
        }

        function goBack() {
            window.history.back();
        }
    </script>
</body>

</html>

<?php
// Close the database connection
$events_stmt->close();
$conn->close();
?>