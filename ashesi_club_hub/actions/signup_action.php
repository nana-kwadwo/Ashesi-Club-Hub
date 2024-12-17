<?php
// Enable error reporting for debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

include "../db/config.php";

$response = [
    'error' => null,
    'success' => false
];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Collect form data
    $full_name = $_POST['full_name'] ?? '';
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmpassword = $_POST['confirmpassword'] ?? '';

    // Validation
    if (empty($full_name)) {
        header("Location: ../view/login_signup.php?error=full_name_required");
        exit();
    }
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        header("Location: ../view/login_signup.php?error=invalid_email");
        exit();
    }
    if (empty($password) || strlen($password) < 6) {
        header("Location: ../view/login_signup.php?error=password_too_short");
        exit();
    }
    if ($password !== $confirmpassword) {
        header("Location: ../view/login_signup.php?error=passwords_do_not_match");
        exit();
    }

    try {
        // Check if email already exists
        $check_stmt = $conn->prepare("SELECT user_id FROM club_users WHERE email = ?");
        $check_stmt->bind_param("s", $email);
        $check_stmt->execute();
        $check_stmt->store_result();

        if ($check_stmt->num_rows > 0) {
            header("Location: ../view/login_signup.php?error=email_already_exists");
            exit();
        }

        // Hash the password
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $role = 3; // Default role for new users
        $timestamp = date("Y-m-d H:i:s");

        // Prepare insert statement
        $insert_stmt = $conn->prepare("INSERT INTO club_users(full_name, email, password, role, created_at) VALUES (?, ?, ?, ?, ?)");
        $insert_stmt->bind_param("sssis", $full_name, $email, $hash, $role, $timestamp);

        if ($insert_stmt->execute()) {
            // Successful registration
            header("Location: ../view/login_signup.php?msg=registration_successful");
            exit();
        } else {
            // Registration failed
            header("Location: ../view/login_signup.php?error=registration_failed");
            exit();
        }
    } catch (Exception $e) {
        // Handle any errors
        header("Location: ../view/login_signup.php?error=unexpected_error");
        exit();
    }

    // Close connections
    $check_stmt->close();
    $insert_stmt->close();
    $conn->close();
}
?>