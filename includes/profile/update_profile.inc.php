<?php
session_start();
require_once '../../config/config.php';
require_once '../functions.php';

// Ensure user is logged in and is a customer
require_role('customer');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $first_name = sanitize_input($_POST['first_name']);
    $last_name = sanitize_input($_POST['last_name']);
    $email = sanitize_input($_POST['email']);
    $phone = sanitize_input($_POST['phone']);
    $address = sanitize_input($_POST['address']);

    // Validate input
    $errors = [];

    if (empty($first_name) || empty($last_name)) {
        $errors[] = "Name fields cannot be empty";
    }

    if (!validate_email($email)) {
        $errors[] = "Invalid email format";
    }

    // Check if email already exists (excluding current user)
    try {
        $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
        $stmt->execute([$email, $_SESSION['user_id']]);
        if ($stmt->rowCount() > 0) {
            $errors[] = "Email is already registered to another account";
        }
    } catch (PDOException $e) {
        error_log($e->getMessage());
        $errors[] = "An error occurred checking email availability";
    }

    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                UPDATE users 
                SET first_name = ?, 
                    last_name = ?, 
                    email = ?, 
                    phone = ?, 
                    address = ?
                WHERE user_id = ? AND role = 'customer'
            ");

            $result = $stmt->execute([
                $first_name,
                $last_name,
                $email,
                $phone,
                $address,
                $_SESSION['user_id']
            ]);

            if ($result) {
                // Update session variables
                $_SESSION['name'] = $first_name . ' ' . $last_name;
                $_SESSION['email'] = $email;

                $_SESSION['success'] = "Profile updated successfully";
            } else {
                $_SESSION['error'] = "Failed to update profile";
            }
        } catch (PDOException $e) {
            error_log($e->getMessage());
            $_SESSION['error'] = "An error occurred updating your profile";
        }
    } else {
        $_SESSION['error'] = implode("<br>", $errors);
    }
} else {
    $_SESSION['error'] = "Invalid request";
}

header("Location: ../../customer/profile.php");
exit();
