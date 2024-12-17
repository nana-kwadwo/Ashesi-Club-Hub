<?php

include "../db/config.php";

session_start();

$response = [
    'error' => null,
    'success' => false
];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Enable error reporting for debugging
    ini_set('display_errors', 1);
    error_reporting(E_ALL);

    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    // Basic validation
    if (empty($email)) {
        $response['error'] = "Email is required";
    }
    if (empty($password)) {
        $response['error'] = "Password is required";
    }

    if ($response['error'] === null) {
        try {
            // Prepare statement to prevent SQL injection
            $stmt = $conn->prepare("SELECT * FROM club_users WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                $row = $result->fetch_assoc();
                
                // Verify password
                if (password_verify($password, $row['password'])) {
                    // Set session variables
                    $_SESSION['email'] = $row['email'];
                    $_SESSION['role'] = $row['role'];
                    $_SESSION['user_id'] = $row['user_id'];
                    $_SESSION['full_name'] = $row['full_name'];

                    // Role-based redirection
                    switch($row['role']) {
                        case 1: // Super Admin
                            header("Location: ../view/super_admin/super_dashboard.php?id=" . $row['user_id']);
                            break;
                        case 2: // Admin
                            header("Location: ../view/admin/admin_dashboard.php?id=" . $row['user_id']);
                            break;
                        case 3: // Regular User
                            header("Location: ../view/home.php?id=" . $row['user_id']);
                            break;
                        default:
                            // Fallback redirect
                            header("Location: ../view/home.php?id=" . $row['user_id']);
                    }
                    exit();
                } else {
                    // Incorrect password
                    header("Location: ../view/login_signup.php?error=invalid_credentials");
                    exit();
                }
            } else {
                // Email not found
                header("Location: ../view/login_signup.php?error=email_not_found");
                exit();
            }
        } catch (Exception $e) {
            // Handle any database errors
            header("Location: ../view/login_signup.php?error=database_error");
            exit();
        }
    }

    // Close connection
    $conn->close();
}
?>