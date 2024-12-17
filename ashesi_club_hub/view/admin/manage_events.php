<?php
session_start();
include '../../db/config.php';

// Authentication check
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login_signup.php");
    exit();
}

$user_id = isset($_GET['id']) ? intval($_GET['id']) : $_SESSION['user_id'];

// Handle event update via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    try {
        switch($_POST['action']) {
            case 'update_event':
                // Validate input
                if (!isset($_POST['event_id'])) {
                    throw new Exception('Invalid event ID');
                }

                // Prepare update query
                $update_query = "UPDATE events SET 
                    event_title = ?, 
                    description = ?, 
                    event_date = ?, 
                    current_slots = ?, 
                    total_slots = ?, 
                    status = ? 
                    WHERE event_id = ?";
                
                $stmt = $conn->prepare($update_query);
                $stmt->bind_param(
                    "ssssssi", 
                    $_POST['event_title'], 
                    $_POST['event_description'], 
                    $_POST['event_date'], 
                    $_POST['current_slots'], 
                    $_POST['total_slots'], 
                    $_POST['status'], 
                    $_POST['event_id']
                );

                if ($stmt->execute()) {
                    echo json_encode(['success' => true, 'message' => 'Event updated successfully']);
                } else {
                    throw new Exception('Failed to update event');
                }
                break;

            case 'delete_event':
                // Validate input
                if (!isset($_POST['event_id'])) {
                    throw new Exception('Invalid event ID');
                }

                // Prepare delete query
                $delete_query = "DELETE FROM events WHERE event_id = ?";
                
                $stmt = $conn->prepare($delete_query);
                $stmt->bind_param("i", $_POST['event_id']);

                if ($stmt->execute()) {
                    echo json_encode(['success' => true, 'message' => 'Event deleted successfully']);
                } else {
                    throw new Exception('Failed to delete event');
                }
                break;

            default:
                throw new Exception('Invalid action');
        }
    } catch (Exception $e) {
        echo json_encode([
            'success' => false, 
            'message' => $e->getMessage()
        ]);
    }
    exit();
}

// Fetch events for the user's club
$events_query = "
    SELECT e.*, c.club_name 
    FROM events e
    JOIN clubs c ON e.club_id = c.club_id
    WHERE c.club_head_id = ?
    ORDER BY e.event_date DESC
";
$stmt = $conn->prepare($events_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$events = $result->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Events - Club Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/manage_events.css">
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

    <div class="manage-events-container">
        <h1>Manage Events</h1>

        <div class="events-grid">
            <?php foreach ($events as $event): ?>
                <div class="event-card" id="event-card-<?php echo $event['event_id']; ?>">
                    <div class="event-header">
                        <h2 class="event-title"><?php echo htmlspecialchars($event['event_title']); ?></h2>
                        <span class="event-club"><?php echo htmlspecialchars($event['club_name']); ?></span>
                    </div>
                    <div class="event-details">
                        <p class="event-description"><?php echo htmlspecialchars($event['description']); ?></p>
                        <div class="event-meta">
                            <div class="event-date">
                                <strong>Date:</strong>
                                <?php echo date('F d, Y h:i A', strtotime($event['event_date'])); ?>
                            </div>
                            <div class="event-slots">
                                <strong>Slots:</strong>
                                <?php echo $event['current_slots'] . ' / ' . $event['total_slots']; ?>
                            </div>
                            <div class="event-status <?php echo strtolower($event['status']); ?>">
                                <?php echo ucfirst($event['status']); ?>
                            </div>
                        </div>
                    </div>
                    <div class="event-actions">
                        <button class="btn btn-edit" onclick="editEvent(<?php echo $event['event_id']; ?>)">Edit</button>
                        <button class="btn btn-delete" onclick="confirmDelete(<?php echo $event['event_id']; ?>)">Delete</button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- SweetAlert2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
    <script>
    function editEvent(eventId) {
        // Fetch current event details
        const eventCard = document.getElementById(`event-card-${eventId}`);
        const title = eventCard.querySelector('.event-title').textContent;
        const description = eventCard.querySelector('.event-description').textContent;
        const dateElement = eventCard.querySelector('.event-date');
        const currentDate = dateElement.textContent.replace('Date: ', '');
        const slotsElement = eventCard.querySelector('.event-slots');
        const [currentSlots, totalSlots] = slotsElement.textContent.replace('Slots: ', '').split(' / ');
        const statusElement = eventCard.querySelector('.event-status');
        const currentStatus = statusElement.textContent.toLowerCase();

        Swal.fire({
            title: 'Edit Event',
            html: `
                <input type="text" id="swal-input-title" class="swal2-input" placeholder="Event Title" value="${title}">
                <textarea id="swal-input-description" class="swal2-input" placeholder="Event Description">${description}</textarea>
                <input type="datetime-local" id="swal-input-date" class="swal2-input" value="${formatDateForInput(currentDate)}">
                <div class="swal2-input-group">
                    <input type="number" id="swal-input-current-slots" class="swal2-input" placeholder="Current Slots" value="${currentSlots}">
                    <input type="number" id="swal-input-total-slots" class="swal2-input" placeholder="Total Slots" value="${totalSlots}">
                </div>
                <select id="swal-input-status" class="swal2-input">
                    <option value="upcoming" ${currentStatus === 'upcoming' ? 'selected' : ''}>Upcoming</option>
                    <option value="ongoing" ${currentStatus === 'ongoing' ? 'selected' : ''}>Ongoing</option>
                    <option value="completed" ${currentStatus === 'completed' ? 'selected' : ''}>Completed</option>
                    <option value="cancelled" ${currentStatus === 'cancelled' ? 'selected' : ''}>Cancelled</option>
                </select>
            `,
            focusConfirm: false,
            preConfirm: () => {
                const title = document.getElementById('swal-input-title').value;
                const description = document.getElementById('swal-input-description').value;
                const eventDate = document.getElementById('swal-input-date').value;
                const currentSlots = document.getElementById('swal-input-current-slots').value;
                const totalSlots = document.getElementById('swal-input-total-slots').value;
                const status = document.getElementById('swal-input-status').value;

                // Client-side validation
                if (!title || !description || !eventDate || !currentSlots || !totalSlots) {
                    Swal.showValidationMessage('Please fill in all fields');
                    return false;
                }

                return { 
                    title, 
                    description, 
                    eventDate, 
                    currentSlots, 
                    totalSlots, 
                    status 
                };
            },
            showCancelButton: true,
            confirmButtonText: 'Save Changes',
        }).then((result) => {
            if (result.isConfirmed) {
                // AJAX call to update event
                const formData = new FormData();
                formData.append('action', 'update_event');
                formData.append('event_id', eventId);
                formData.append('event_title', result.value.title);
                formData.append('event_description', result.value.description);
                formData.append('event_date', formatDateForDatabase(result.value.eventDate));
                formData.append('current_slots', result.value.currentSlots);
                formData.append('total_slots', result.value.totalSlots);
                formData.append('status', result.value.status);

                fetch('manage_events.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire('Updated!', 'Event has been updated.', 'success')
                        .then(() => {
                            location.reload();
                        });
                    } else {
                        Swal.fire('Error', data.message || 'Failed to update event', 'error');
                    }
                })
                .catch(error => {
                    Swal.fire('Error', 'Something went wrong', 'error');
                    console.error('Error:', error);
                });
            }
        });
    }

    function formatDateForInput(dateString) {
        const date = new Date(dateString);
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        const hours = String(date.getHours()).padStart(2, '0');
        const minutes = String(date.getMinutes()).padStart(2, '0');
        return `${year}-${month}-${day}T${hours}:${minutes}`;
    }

    function formatDateForDatabase(dateString) {
        return dateString.replace('T', ' ') + ':00';
    }

    function confirmDelete(eventId) {
        Swal.fire({
            title: 'Are you sure?',
            text: 'You won\'t be able to revert this!',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Yes, delete it!'
        }).then((result) => {
            if (result.isConfirmed) {
                // AJAX call to delete event
                const formData = new FormData();
                formData.append('action', 'delete_event');
                formData.append('event_id', eventId);

                fetch('manage_events.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire('Deleted!', 'Event has been deleted.', 'success')
                        .then(() => {
                            const eventCard = document.getElementById(`event-card-${eventId}`);
                            if (eventCard) {
                                eventCard.remove();
                            } else {
                                location.reload();
                            }
                        });
                    } else {
                        Swal.fire('Error', data.message || 'Failed to delete event', 'error');
                    }
                })
                .catch(error => {
                    Swal.fire('Error', 'Something went wrong', 'error');
                    console.error('Error:', error);
                });
            }
        });
    }
    </script>
</body>
</html>