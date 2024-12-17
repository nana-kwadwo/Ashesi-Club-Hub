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

// Fetch events with club information
$events_query = "
    SELECT 
        e.event_id, 
        e.event_title, 
        e.event_date, 
        e.total_slots, 
        e.current_slots, 
        c.club_name, 
        c.logo_path,
        (SELECT COUNT(*) FROM event_registrations er WHERE er.event_id = e.event_id AND er.user_id = ?) AS user_registered
    FROM 
        events e
    JOIN 
        clubs c ON e.club_id = c.club_id
    WHERE 
        e.status IN ('upcoming', 'ongoing')
    ORDER BY 
        e.event_date ASC
";

// Prepare and execute the query
$stmt = $conn->prepare($events_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ashesi Events</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/events.css">
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

    <div class="events-container">
        <?php 
        // Check if there are any events
        if ($result->num_rows > 0) {
            // Loop through events
            while ($event = $result->fetch_assoc()) {
                // Convert event date to a more readable format
                $event_date = date('F j, Y | g:i A', strtotime($event['event_date']));
                
                // Determine RSVP button state
                $rsvp_class = $event['user_registered'] > 0 ? 'active' : '';
                $rsvp_text = $event['user_registered'] > 0 ? 'Leave' : 'RSVP';
        ?>
            <div class="event-card" data-event-id="<?php echo $event['event_id']; ?>">
                <div class="event-header">
                    <span class="event-title"><?php echo htmlspecialchars($event['event_title']); ?></span>
                    <span class="event-date"><?php echo $event_date; ?></span>
                </div>

                <div class="event-club">
                    <img src="<?php echo !empty($event['logo_path']) ? htmlspecialchars("../" . $event['logo_path']) : 'https://via.placeholder.com/40'; ?>" alt="<?php echo htmlspecialchars($event['club_name']); ?>">
                    <span><?php echo htmlspecialchars($event['club_name']); ?></span>
                </div>

                <div class="event-details">
                    <span class="event-slots"><?php echo $event['current_slots'] . '/' . $event['total_slots']; ?> Slots Available</span>
                    <button class="btn-rsvp <?php echo $rsvp_class; ?>" onclick="toggleRSVP(this, <?php echo $event['event_id']; ?>)">
                        <?php echo $rsvp_text; ?>
                    </button>
                </div>
            </div>
        <?php 
            }
        } else {
            // Display message if no events are found
            echo '<p>No upcoming events at the moment.</p>';
        }
        ?>
    </div>

    <script>
    function toggleRSVP(button, eventId) {
        // Send AJAX request to handle RSVP
        fetch('../actions/process_rsvp.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `event_id=${eventId}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Toggle button state
                button.classList.toggle('active');
                button.textContent = button.classList.contains('active') ? 'Leave' : 'RSVP';
                
                // Update slots
                const slotsElement = button.closest('.event-details').querySelector('.event-slots');
                slotsElement.textContent = data.current_slots + '/' + data.total_slots + ' Slots Available';
            } else {
                // Show error message
                alert(data.message || 'Failed to process RSVP');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while processing your request');
        });
    }
    </script>
</body>

</html>

<?php
// Close the database connection
$stmt->close();
$conn->close();
?>