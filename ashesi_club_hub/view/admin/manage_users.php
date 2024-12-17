<?php
session_start();
include '../../db/config.php';

// Authentication check
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login_signup.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch the club that the current user is head of
$club_query = "SELECT club_id, club_name FROM clubs WHERE club_head_id = ?";
$stmt = $conn->prepare($club_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$club_result = $stmt->get_result();
$club = $club_result->fetch_assoc();

// If the user is a club head, fetch club members
$members = [];
$no_members = false;

if ($club) {
    $members_query = "
        SELECT cu.user_id, cu.full_name, cu.email, cu.role,
               GROUP_CONCAT(DISTINCT c.club_name SEPARATOR ', ') AS clubs,
               cm.joined_at
        FROM club_users cu
        JOIN club_memberships cm ON cu.user_id = cm.user_id
        JOIN clubs c ON cm.club_id = c.club_id
        WHERE c.club_id = ?
        GROUP BY cu.user_id
    ";
    $stmt = $conn->prepare($members_query);
    $stmt->bind_param("i", $club['club_id']);
    $stmt->execute();
    $members_result = $stmt->get_result();
    $members = $members_result->fetch_all(MYSQLI_ASSOC);

    // Check if there are no members
    $no_members = empty($members);
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Club Members</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/manage_users.css">
    <!-- SweetAlert2 for better notifications -->
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

    <div class="manage-users-container">
        <?php if (!$club): ?>
            <h1>Error: You are not a club head</h1>
            <p>Only club heads can manage members.</p>
        <?php else: ?>
            <h1>Manage Members for <?php echo htmlspecialchars($club['club_name']); ?></h1>

            <?php if ($no_members): ?>
                <div class="no-members-message">
                    <p>You have no members in your club.</p>
                    <p>Invite students to join or promote your club to attract more members!</p>
                </div>
            <?php else: ?>
                <div class="users-grid">
                    <?php foreach ($members as $member): ?>
                        <div class="user-card">
                            <div class="user-header">
                                <h2><?php echo htmlspecialchars($member['full_name']); ?></h2>
                                <span class="user-role"><?php echo ucfirst($member['role'] == 3 ? 'Student' : 'Admin'); ?></span>
                            </div>
                            <div class="user-details">
                                <div class="user-meta">
                                    <div class="user-email">
                                        <strong>Email:</strong>
                                        <?php echo htmlspecialchars($member['email']); ?>
                                    </div>
                                    <div class="user-joined-date">
                                        <strong>Joined Club:</strong>
                                        <?php echo date('F d, Y', strtotime($member['joined_at'])); ?>
                                    </div>
                                </div>
                            </div>
                            <div class="user-actions">
                                <button class="btn btn-delete" onclick="confirmRemove(<?php echo $member['user_id']; ?>)">
                                    Remove from Club
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <!-- SweetAlert2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
    <script>
        function confirmRemove(userId) {
            Swal.fire({
                title: 'Remove Member',
                text: 'Are you sure you want to remove this member from the club?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Yes, remove member'
            }).then((result) => {
                if (result.isConfirmed) {
                    // AJAX call to remove member
                    const formData = new FormData();
                    formData.append('user_id', userId);

                    fetch('../actions/remove_member.php', {
                        method: 'POST',
                        body: formData
                    })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                Swal.fire('Removed!', 'Member has been removed from the club.', 'success')
                                    .then(() => {
                                        location.reload();
                                    });
                            } else {
                                Swal.fire('Error', data.message || 'Failed to remove member', 'error');
                            }
                        })
                        .catch(error => {
                            Swal.fire('Error', 'Something went wrong', 'error');
                        });
                }
            });
        }
    </script>
</body>

</html>