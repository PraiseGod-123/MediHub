<?php
// Start session at the very beginning
session_start();

// Define BASE_URL if not already defined in config
if (!defined('BASE_URL')) {
    define('BASE_URL', ''); // Set this to your actual base URL
}

// Check if already logged in and properly handle session variables
if (isset($_SESSION['user_id']) && isset($_SESSION['role'])) {
    $redirect_path = '';

    switch ($_SESSION['role']) {
        case 'admin':
            $redirect_path = '../admin/dashboard.php';
            break;
        case 'pharmacy':
            $redirect_path = '../pharmacy/dashboard.php';
            break;
        case 'customer':
            $redirect_path = '../customer/dashboard.php';
            break;
        default:
            // If role is not recognized, destroy session and continue to login page
            session_destroy();
            session_start();
    }

    if (!empty($redirect_path)) {
        header("Location: " . $redirect_path);
        exit();
    }
}

// Get any error or success messages
$login_error = isset($_SESSION['login_error']) ? $_SESSION['login_error'] : '';
$register_error = isset($_SESSION['register_error']) ? $_SESSION['register_error'] : '';
$register_success = isset($_SESSION['register_success']) ? $_SESSION['register_success'] : '';

// Clear messages after displaying
unset($_SESSION['login_error'], $_SESSION['register_error'], $_SESSION['register_success']);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MediHub - Login/Register</title>
    <link rel="stylesheet" href="../assets/css/login_register.css">
</head>

<body>
    <div class="container" id="container">
        <!-- Registration Form -->
        <div class="form-container sign-up">
            <form action="../includes/auth/register.inc.php" method="POST">
                <h1>Create Account</h1>
                <?php if ($register_error): ?>
                    <div class="error-message"><?php echo htmlspecialchars($register_error); ?></div>
                <?php endif; ?>
                <?php if ($register_success): ?>
                    <div class="success-message"><?php echo htmlspecialchars($register_success); ?></div>
                <?php endif; ?>

                <input type="text" name="first_name" placeholder="First Name" required>
                <input type="text" name="last_name" placeholder="Last Name" required>
                <input type="email" name="email" placeholder="Email" required>
                <input type="password" name="password" placeholder="Password" required>
                <input type="password" name="confirm_password" placeholder="Confirm Password" required>
                <select name="role" required>
                    <option value="customer">Customer</option>
                    <option value="pharmacy">Pharmacy Owner</option>
                </select>
                <button type="submit" name="register">Sign Up</button>
            </form>
        </div>

        <!-- Login Form -->
        <div class="form-container log-in">
            <form action="../includes/auth/login.inc.php" method="POST">
                <h1>Log In</h1>
                <?php if ($login_error): ?>
                    <div class="error-message"><?php echo htmlspecialchars($login_error); ?></div>
                <?php endif; ?>

                <input type="email" name="email" placeholder="Email" required>
                <input type="password" name="password" placeholder="Password" required>
                <a href="#">Forgot Password?</a>
                <button type="submit" name="login">Log In</button>
            </form>
        </div>

        <!-- Toggle Container -->
        <div class="toggle-container">
            <div class="toggle">
                <div class="toggle-panel toggle-left">
                    <h1>Welcome Back!</h1>
                    <p>Login with your personal details to access your account</p>
                    <button class="hidden" id="login">Log In</button>
                </div>
                <div class="toggle-panel toggle-right">
                    <h1>Hello!</h1>
                    <p>Register with your details to access all our features</p>
                    <button class="hidden" id="register">Sign Up</button>
                </div>
            </div>
        </div>
    </div>

    <script src="../assets/js/login_register.js"></script>
</body>

</html>