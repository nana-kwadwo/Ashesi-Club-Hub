<?php
session_start();
// Database connection
include '../../db/config.php';

// Check if user is logged in and has superadmin privileges
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] != 1) {
    header("Location: ../../login_signup.php");
    exit();
}

// Handle Club Creation
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Sanitize and validate inputs
    $clubName = trim($_POST['club_name']);
    $description = trim($_POST['description'] ?? '');
    
    // Careful handling of club head
    $clubHeadId = null;
    if (!empty($_POST['club_head']) && is_numeric($_POST['club_head'])) {
        $clubHeadId = intval($_POST['club_head']);
    }

    $status = $_POST['status'] ?? 'pending';
    var_dump($_FILES);
    // File upload handling for logo
    $logoPath = null;
    if (isset($_FILES['logo']) && $_FILES['logo']['error'] == 0) {
        $uploadDir = '../../../uploads/';
        
        // Ensure upload directory exists
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 777, true);
        }

        // chmod for FOLDER

        // Generate unique filename
        $fileName = uniqid() . '_' . basename($_FILES['logo']['name']);
        $targetFilePath = $uploadDir . $fileName;
        
        // Validate file type (optional: add more strict validation if needed)
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
        $fileType = mime_content_type($_FILES['logo']['tmp_name']);
        
        if (in_array($fileType, $allowedTypes)) {
            // Move uploaded file
            if (move_uploaded_file($_FILES['logo']['tmp_name'], $targetFilePath)) {
                $logoPath = str_replace('../../../', '', $targetFilePath);
                // var_dump($logoPath);
               
            } else {
                $_SESSION['error'] = "Failed to upload logo.";
            }
        } else {
            $_SESSION['error'] = "Invalid file type. Only JPEG , PNG, and GIF are allowed.";
        }
    }

    try {
        // Comprehensive prepared statement to handle all scenarios
        $stmt = $conn->prepare("
            INSERT INTO clubs 
            (club_name, description, logo_path, club_head_id, status, created_by) 
            VALUES (?, NULLIF(?, ''), NULLIF(?, ''), NULLIF(?, 0), ?, ?)
        ");

        // Bind parameters with appropriate types and null handling
        $stmt->bind_param(
            "sssisi", 
            $clubName, 
            $description, 
            $logoPath, 
            $clubHeadId, 
            $status, 
            $_SESSION['user_id']
        );
        
        // Begin transaction
        $conn->begin_transaction();
        
        if ($stmt->execute()) {
            $newClubId = $stmt->insert_id;
            
            // Log the admin action
            $logStmt = $conn->prepare("
                INSERT INTO admin_actions_log 
                (admin_id, action_type, target_table, target_id, details) 
                VALUES (?, 'create', 'clubs', ?, 'Created new club')
            ");
            $logStmt->bind_param("ii", $_SESSION['user_id'], $newClubId);
            
            if ($logStmt->execute()) {
                // Commit transaction
                $conn->commit();
                
                // Set success message
                $_SESSION['success'] = "Club created successfully!";
                
                // Redirect to superadmin dashboard
                header("Location: super_dashboard.php");
                exit();
            } else {
                throw new Exception("Error logging admin action: " . $logStmt->error);
            }
        } else {
            throw new Exception("Error creating club: " . $stmt->error);
        }
    } catch (Exception $e) {
        // Rollback transaction
        $conn->rollback();
        
        // Set error message
        $_SESSION['error'] = $e->getMessage();
        error_log($e->getMessage());
    }
}

// Fetch potential club heads (users who can be club heads)
try {
    $usersQuery = "SELECT user_id, full_name, email FROM club_users WHERE role IN (2, 1)";
    $usersResult = $conn->query($usersQuery);
} catch (Exception $e) {
    error_log("Users Fetch Error: " . $e->getMessage());
    $usersResult = false;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create New Club</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/superadmin_dashboard.css">
    <!-- SweetAlert2 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <!-- SweetAlert2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
    <style>
        .form-container {
            background-color: var(--white);
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
            padding: 40px;
            max-width: 600px;
            margin: 0 auto;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 10px;
            color: var(--primary-blue);
        }
        .form-group input, 
        .form-group textarea, 
        .form-group select {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 1rem;
        }
    </style>
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
        <h1>Create New Club</h1>

        <div class="form-container">
            <form action="" method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="club_name">Club Name *</label>
                    <input type="text" id="club_name" name="club_name" required placeholder="Enter club name" maxlength="100">
                </div>

                <div class="form-group">
                    <label for="description">Club Description</label>
                    <textarea id="description" name="description" rows="4" placeholder="Describe the club's purpose and activities" maxlength="1000"></textarea>
                </div>

                <div class="form-group">
                    <label for="club_head">Club Head</label>
                    <select id="club_head" name="club_head">
                        <option value="">Select Club Head (Optional)</option>
                        <?php if ($usersResult && $usersResult->num_rows > 0): ?>
                            <?php while ($user = $usersResult->fetch_assoc()): ?>
                                <option value="<?php echo $user['user_id']; ?>">
                                    <?php echo htmlspecialchars($user['full_name'] . ' (' . $user['email'] . ')'); ?>
                                </option>
                            <?php endwhile; ?>
                        <?php endif; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="status">Club Status</label>
                    <select id="status" name="status">
                        <option value="pending">Pending</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="logo">Club Logo (Optional)</label>
                    <input type="file" id="logo" name="logo" accept="image/jpeg,image/png,image/gif">
                </div>

                <div class="action-buttons">
                    <button type="submit" class="btn">Create Club</button>
                    <button type="button" class="btn btn-logout" onclick="window.location.href='super_dashboard.php'">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Success and error message handling
        <?php if(isset($_SESSION['error'])): ?>
            Swal.fire({
                icon: 'error',
                title: 'Oops...',
                text: '<?php echo htmlspecialchars($_SESSION['error']); ?>',
                showConfirmButton: true
            });
            
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <?php if(isset($_SESSION['success'])): ?>
            Swal.fire({
                icon: 'success',
                title: 'Success!',
                text: '<?php echo htmlspecialchars($_SESSION['success']); ?>',
                showConfirmButton: true
            });
            
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>
    </script>
</body>
</html>
<?php
// Close database connection
if (isset($conn)) $conn->close();
?>