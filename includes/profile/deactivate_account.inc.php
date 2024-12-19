<?php
session_start();
require_once '../../config/config.php';
require_once '../functions.php';

// Ensure user is logged in and is a customer
require_role('customer');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $pdo->beginTransaction();

        // Get user details before anonymizing
        $stmt = $pdo->prepare("SELECT email FROM users WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user_email = $stmt->fetchColumn();

        // Generate anonymous identifier
        $anonymous_id = 'DEACTIVATED_' . time() . '_' . substr(md5(rand()), 0, 8);

        // Anonymize user data
        $stmt = $pdo->prepare("
            UPDATE users 
            SET 
                email = ?,
                first_name = 'Deactivated',
                last_name = 'User',
                phone = NULL,
                address = NULL,
                profile_image = NULL,
                status = 'inactive',
                password = ?
            WHERE user_id = ? AND role = 'customer'
        ");

        // Create a random password hash that can't be used to login
        $random_password = password_hash(random_bytes(32), PASSWORD_DEFAULT);

        $stmt->execute([
            $anonymous_id . '@deactivated.local',
            $random_password,
            $_SESSION['user_id']
        ]);

        // Log the deactivation
        error_log("Account deactivated for user ID: " . $_SESSION['user_id'] . " (Original email: " . $user_email . ")");

        // You might want to send a confirmation email here
        // sendDeactivationEmail($user_email);

        $pdo->commit();

        // Destroy session
        session_destroy();

        // Set a temporary session message for the login page
        session_start();
        $_SESSION['login_message'] = "Your account has been successfully deactivated. We're sorry to see you go!";
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log($e->getMessage());

        session_start();
        $_SESSION['error'] = "An error occurred while deactivating your account";
        header("Location: ../../customer/profile.php");
        exit();
    }
}

// Redirect to login page
header("Location: ../../public/login_register.php");
exit();
