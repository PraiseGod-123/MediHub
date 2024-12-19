<?php
// includes/auth/login.inc.php
session_start();
require_once '../../config/config.php';
require_once '../../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['login'])) {
    $email = sanitize_input($_POST['email']);
    $password = $_POST['password'];

    try {
        // For debugging - log the values we're checking
        error_log("Login attempt - Email: " . $email);

        // Check if user exists and is active
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        // For debugging - log if user was found
        error_log("User found: " . ($user ? 'Yes' : 'No'));
        if ($user) {
            error_log("User status: " . $user['status']);
        }

        // Debug password verification
        if ($user) {
            $password_verify_result = password_verify($password, $user['password']);
            error_log("Password verification result: " . ($password_verify_result ? 'True' : 'False'));
        }

        if ($user && password_verify($password, $user['password'])) {
            // Check user status
            if ($user['status'] !== 'active' && $user['role'] === 'pharmacy') {
                $_SESSION['login_error'] = "Your pharmacy account is pending approval.";
                header("Location: ../../public/login_register.php");
                exit();
            }

            // Set session variables
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['name'] = $user['first_name'] . ' ' . $user['last_name'];

            // Update last login
            $update = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE user_id = ?");
            $update->execute([$user['user_id']]);

            // For debugging - log successful login
            error_log("Successful login - User ID: " . $user['user_id'] . ", Role: " . $user['role']);

            // Redirect based on role
            switch ($user['role']) {
                case 'admin':
                    header("Location: ../../admin/dashboard.php");
                    break;
                case 'pharmacy':
                    header("Location: ../../pharmacy/dashboard.php");
                    break;
                case 'customer':
                    header("Location: ../../customer/dashboard.php");
                    break;
                default:
                    // If role is not recognized, destroy session and redirect to login
                    session_destroy();
                    $_SESSION['login_error'] = "Invalid user role";
                    header("Location: ../../public/login_register.php");
                    break;
            }
            exit();
        } else {
            $_SESSION['login_error'] = "Invalid email or password";
            error_log("Login failed - Invalid credentials");
        }
    } catch (PDOException $e) {
        error_log("Login error: " . $e->getMessage());
        $_SESSION['login_error'] = "An error occurred. Please try again.";
    }

    header("Location: ../../public/login_register.php");
    exit();
}

// If someone tries to access this file directly without POST request
header("Location: ../../public/login_register.php");
exit();
