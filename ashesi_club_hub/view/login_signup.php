<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ashesi Club Hub - Login/Signup</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/login_signup.css">
</head>
<body>
    <div class="container">
        <img src="../assets/images/logo.png" alt="Ashesi Logo" class="ashesi-logo">

        <div class="site-title">Ashesi Club Hub</div>

        <?php
        // Display error messages
        if (isset($_GET['error'])) {
            $error_messages = [
                'invalid_credentials' => 'Invalid email or password',
                'email_not_found' => 'Email not registered',
                'invalid_email' => 'Please enter a valid email',
                'password_too_short' => 'Password must be at least 6 characters',
                'passwords_do_not_match' => 'Passwords do not match',
                'email_already_exists' => 'Email is already registered',
                'registration_failed' => 'Registration failed. Please try again.',
                'unexpected_error' => 'An unexpected error occurred'
            ];
            
            $error = $_GET['error'];
            echo "<div class='error-message'>" . 
                 (isset($error_messages[$error]) ? $error_messages[$error] : 'An error occurred') . 
                 "</div>";
        }

        // Display success messages
        if (isset($_GET['msg'])) {
            $success_messages = [
                'registration_successful' => 'Registration successful! Please log in.'
            ];
            
            $msg = $_GET['msg'];
            echo "<div class='success-message'>" . 
                 (isset($success_messages[$msg]) ? $success_messages[$msg] : 'Success!') . 
                 "</div>";
        }
        ?>

        <div class="form-toggle">
            <button id="login-tab" class="active">Login</button>
            <button id="signup-tab">Sign Up</button>
        </div>

        <form id="login-form" method="post" action="../actions/login_action.php">
            <div class="form-group">
                <label for="login-email">Email</label>
                <input type="email" id="login-email" name="email" required>
            </div>
            <div class="form-group">
                <label for="login-password">Password</label>
                <input type="password" id="login-password" name="password" required>
            </div>
            <button type="submit" class="submit-btn">Login</button>
        </form>

        <form id="signup-form" method="post" action="../actions/signup_action.php">
            <div class="form-group">
                <label for="signup-name">Full Name</label>
                <input type="text" id="signup-name" name="full_name" required>
            </div>
            <div class="form-group">
                <label for="signup-email">Ashesi Email</label>
                <input type="email" id="signup-email" name="email" required>
            </div>
            <div class="form-group">
                <label for="signup-password">Password</label>
                <input type="password" id="signup-password" name="password" required>
            </div>
            <div class="form-group">
                <label for="signup-confirm-password">Confirm Password</label>
                <input type="password" id="signup-confirm-password" name="confirmpassword" required>
            </div>
            <button type="submit" class="submit-btn">Create Account</button>
        </form>
    </div>

    <script>
        // Tab switching functionality
        const loginTab = document.getElementById('login-tab');
        const signupTab = document.getElementById('signup-tab');
        const loginForm = document.getElementById('login-form');
        const signupForm = document.getElementById('signup-form');

        // Initially hide signup form
        signupForm.style.display = 'none';

        loginTab.addEventListener('click', () => {
            loginTab.classList.add('active');
            signupTab.classList.remove('active');
            loginForm.style.display = 'block';
            signupForm.style.display = 'none';
        });

        signupTab.addEventListener('click', () => {
            signupTab.classList.add('active');
            loginTab.classList.remove('active');
            signupForm.style.display = 'block';
            loginForm.style.display = 'none';
        });
    </script>
</body>
</html>