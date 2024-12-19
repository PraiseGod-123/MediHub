<?php
session_start();
require_once '../../config/config.php';
require_once '../functions.php';

// Ensure user is logged in and is a customer
require_role('customer');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['avatar'])) {
    $file = $_FILES['avatar'];
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
    $max_size = 5 * 1024 * 1024; // 5MB

    // Validate file
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $_SESSION['error'] = "Error uploading file";
        header("Location: ../../customer/profile.php");
        exit();
    }

    if (!in_array($file['type'], $allowed_types)) {
        $_SESSION['error'] = "Invalid file type. Please upload a JPEG, PNG, or GIF";
        header("Location: ../../customer/profile.php");
        exit();
    }

    if ($file['size'] > $max_size) {
        $_SESSION['error'] = "File size must be less than 5MB";
        header("Location: ../../customer/profile.php");
        exit();
    }

    // Process upload
    $upload_dir = '../../assets/images/profiles/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    // Delete old avatar if exists
    try {
        $stmt = $pdo->prepare("SELECT profile_image FROM users WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $old_avatar = $stmt->fetchColumn();

        if ($old_avatar && file_exists($upload_dir . $old_avatar)) {
            unlink($upload_dir . $old_avatar);
        }
    } catch (PDOException $e) {
        error_log($e->getMessage());
    }

    // Generate unique filename
    $filename = uniqid('avatar_') . '_' . time() . '.' . pathinfo($file['name'], PATHINFO_EXTENSION);
    $filepath = $upload_dir . $filename;

    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        try {
            $stmt = $pdo->prepare("
                UPDATE users 
                SET profile_image = ? 
                WHERE user_id = ?
            ");

            if ($stmt->execute([$filename, $_SESSION['user_id']])) {
                $_SESSION['success'] = "Profile picture updated successfully";
            } else {
                $_SESSION['error'] = "Failed to update profile picture";
            }
        } catch (PDOException $e) {
            error_log($e->getMessage());
            $_SESSION['error'] = "Database error updating profile picture";
        }
    } else {
        $_SESSION['error'] = "Failed to save uploaded file";
    }
} else {
    $_SESSION['error'] = "Invalid request";
}

header("Location: ../../customer/profile.php");
exit();
