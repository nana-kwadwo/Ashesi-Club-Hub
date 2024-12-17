<?php
session_start();
include '../../db/config.php';

// Authentication check
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login_signup.php");
    exit();
}

$user_id = isset($_GET['id']) ? intval($_GET['id']) : $_SESSION['user_id'];

// Check if user is a club head
$club_query = "SELECT club_id, club_name FROM clubs WHERE club_head_id = ?";
$stmt = $conn->prepare($club_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    die("You are not authorized to create events");
}

$club_row = $result->fetch_assoc();
$club_id = $club_row['club_id'];
$club_name = $club_row['club_name'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Event - <?php echo htmlspecialchars($club_name); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/create_event.css">
    <!-- SweetAlert2 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
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
                <li><a href="manage_users.php?id=<?php echo $user_id; ?>">Manage Users</a></li>
                <li><a href="../login_signup.php">Log-out</a></li>
            </ul>
        </nav>
    </header>

    <div class="create-event-container">
        <form action="../../actions/process_event.php?id=<?php echo $user_id; ?>" method="POST" class="event-form" id="eventForm">
            <h2>Create New Event for <?php echo htmlspecialchars($club_name); ?></h2>

            <div class="form-group">
                <label for="event-title">Event Title</label>
                <input type="text" id="event-title" name="event_title" placeholder="Enter event title" required>
            </div>

            <div class="form-group">
                <label for="event-description">Event Description</label>
                <textarea id="event-description" name="event_description"
                    placeholder="Provide a detailed description of the event" required></textarea>
            </div>

            <div class="form-group">
                <label for="event-date">Event Date and Time</label>
                <input type="datetime-local" id="event-date" name="event_date" required>
            </div>

            <div class="form-group">
                <label for="total-slots">Total Available Slots</label>
                <input type="number" id="total-slots" name="total_slots" min="1"
                    placeholder="Maximum number of participants" required>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-save">Save Event</button>
                <button type="button" class="btn btn-cancel">Cancel</button>
            </div>
        </form>
    </div>

    <!-- SweetAlert2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
    <script>
        document.querySelector('.btn-cancel').addEventListener('click', function () {
            window.location.href = 'admin_dashboard.php?id=<?php echo $user_id; ?>'; 
        });

        <?php 
        // Check for success or error messages
        if (isset($_GET['error'])) {
            echo "
            Swal.fire({
                icon: 'error',
                title: 'Oops...',
                text: 'Failed to create event. Please try again.'
            });";
        }
        ?>
    </script>
</body>
</html>