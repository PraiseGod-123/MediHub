<?php
session_start();
require_once '../../config/config.php';
require_once '../functions.php';

// Ensure user is logged in and is a customer
require_role('customer');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    $errors = [];

    // Validate input
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $errors[] = "All password fields are required";
    }

    if ($new_password !== $confirm_password) {
        $errors[] = "New passwords do not match";
    }

    if (!validate_password($new_password)) {
        $errors[] = "New password must be at least 8 characters long";
    }

    if (empty($errors)) {
        try {
            // Verify current password
            $stmt = $pdo->prepare("SELECT password FROM users WHERE user_id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $stored_password = $stmt->fetchColumn();

            if (password_verify($current_password, $stored_password)) {
                // Hash new password
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

                // Update password
                $stmt = $pdo->prepare("
                    UPDATE users 
                    SET password = ? 
                    WHERE user_id = ?
                ");

                if ($stmt->execute([$hashed_password, $_SESSION['user_id']])) {
                    $_SESSION['success'] = "Password updated successfully";

                    // Log the password change
                    error_log("Password changed for user ID: " . $_SESSION['user_id']);

                    // Optionally, you could send an email notification here
                } else {
                    $_SESSION['error'] = "Failed to update password";
                }
            } else {
                $_SESSION['error'] = "Current password is incorrect";
            }
        } catch (PDOException $e) {
            error_log($e->getMessage());
            $_SESSION['error'] = "An error occurred updating your password";
        }
    } else {
        $_SESSION['error'] = implode("<br>", $errors);
    }
} else {
    $_SESSION['error'] = "Invalid request";
}

header("Location: ../../customer/profile.php");
exit();
