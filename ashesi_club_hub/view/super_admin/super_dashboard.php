<?php
session_start();
// Database connection
include '../../db/config.php';

// Check if user is logged in and has superadmin privileges
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] != 1) {
    header("Location: ../../login_signup.php");
    exit();
}

// Handle Logout
if (isset($_GET['logout'])) {
    // Unset all session variables
    $_SESSION = array();

    // Destroy the session
    session_destroy();

    // Redirect to login page
    header("Location: ../../login_signup.php");
    exit();
}

// Handle Club Deletion
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $clubId = intval($_GET['delete']);
    try {
        // Start a transaction
        $conn->begin_transaction();

        // Delete related records first
        $conn->query("DELETE FROM club_memberships WHERE club_id = $clubId");
        $conn->query("DELETE FROM events WHERE club_id = $clubId");
        $conn->query("DELETE FROM posts WHERE club_id = $clubId");

        // Prepare delete statement for the club
        $deleteStmt = $conn->prepare("DELETE FROM clubs WHERE club_id = ?");
        $deleteStmt->bind_param("i", $clubId);
        $deleteStmt->execute();

        if ($deleteStmt->affected_rows > 0) {
            // Log the admin action
            $adminId = $_SESSION['user_id'];
            $logStmt = $conn->prepare("INSERT INTO admin_actions_log (admin_id, action_type, target_table, target_id, details) VALUES (?, 'delete', 'clubs', ?, 'Deleted club')");
            $logStmt->bind_param("ii", $adminId, $clubId);
            $logStmt->execute();

            // Commit the transaction
            $conn->commit();

            $_SESSION['success'] = "Club deleted successfully!";
        } else {
            // Rollback the transaction if club deletion fails
            $conn->rollback();
            $_SESSION['error'] = "Club not found or could not be deleted.";
        }
        $deleteStmt->close();
        
        // Redirect to prevent form resubmission
        header("Location: super_dashboard.php");
        exit();
    } catch (Exception $e) {
        // Rollback the transaction in case of error
        $conn->rollback();
        $_SESSION['error'] = "Error deleting club: " . $e->getMessage();
        header("Location: super_dashboard.php");
        exit();
    }
}

try {
    // Fetch all clubs with their head's full name
    $clubsQuery = "SELECT clubs.club_id, clubs.club_name, clubs.status, 
                   club_users.full_name AS head_name 
                   FROM clubs 
                   LEFT JOIN club_users ON clubs.club_head_id = club_users.user_id 
                   ORDER BY clubs.club_name";
    $clubsResult = $conn->query($clubsQuery);
} catch (Exception $e) {
    error_log("Clubs Fetch Error: " . $e->getMessage());
    $clubsResult = false;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Superadmin Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/superadmin_dashboard.css">
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
                <li style="color:white;">Welcome, <?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Superadmin'); ?></li>
            </ul>
        </nav>
    </header>

    <div class="dashboard-container">
        <h1>Superadmin Dashboard</h1>

        <div class="action-buttons">
            <button class="btn" onclick="window.location.href='create_club.php'">Create New Club</button>
        </div>

        <div class="clubs-container">
            <?php if ($clubsResult && $clubsResult->num_rows > 0): ?>
                <?php while ($club = $clubsResult->fetch_assoc()): ?>
                    <div class="club-card">
                        <h3><?php echo htmlspecialchars($club['club_name']); ?></h3>
                        <p>Club Head: <?php echo htmlspecialchars($club['head_name'] ?? 'Not Assigned'); ?></p>
                        <p>Status: <span class="club-status <?php echo strtolower($club['status']); ?>">
                            <?php echo htmlspecialchars(ucfirst($club['status'])); ?>
                        </span></p>
                        <div class="club-actions">
                        <a href="edit_club.php?club_id=<?php echo $club['club_id']; ?>" class="btn btn-edit">Edit</a>
                            <button class="btn btn-delete" onclick="confirmDelete(<?php echo $club['club_id']; ?>)">Delete</button>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <p class="no-clubs">No clubs found. Create your first club!</p>
            <?php endif; ?>
        </div>

        <div class="logout-container">
            <a href="../login_signup.php" class="btn btn-logout">Logout</a>
        </div>
    </div>

    <script>
        function confirmDelete(clubId) {
            Swal.fire({
                title: 'Are you sure?',
                text: 'You will not be able to recover this club and all its associated data!',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#8B0000',
                cancelButtonColor: '#4C0000',
                confirmButtonText: 'Yes, delete it!'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = 'super_dashboard.php?delete=' + clubId;
                }
            });
        }

        // Success and error message handling
        <?php if(isset($_SESSION['success'])): ?>
            Swal.fire({
                icon: 'success',
                title: 'Success!',
                text: '<?php echo htmlspecialchars($_SESSION['success']); ?>',
                showConfirmButton: false,
                timer: 3000
            });
            
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <?php if(isset($_SESSION['error'])): ?>
            Swal.fire({
                icon: 'error',
                title: 'Oops...',
                text: '<?php echo htmlspecialchars($_SESSION['error']); ?>',
                showConfirmButton: true
            });
            
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>
    </script>
</body>
</html>
<?php
// Close database connection
if (isset($conn)) $conn->close();
?>