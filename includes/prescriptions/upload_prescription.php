<?php
session_start();
require_once '../../config/config.php';
require_once '../functions.php';

// Ensure user is logged in and is a customer
require_role('customer');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['prescription']) && isset($_POST['order_id'])) {
    $file = $_FILES['prescription'];
    $order_id = filter_var($_POST['order_id'], FILTER_VALIDATE_INT);

    if (!$order_id) {
        $_SESSION['error'] = "Invalid order ID";
        header("Location: ../../customer/prescriptions.php");
        exit();
    }

    // Validate file
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'];
    $max_size = 5 * 1024 * 1024; // 5MB

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $_SESSION['error'] = "Error uploading file";
        header("Location: ../../customer/prescriptions.php");
        exit();
    }

    if (!in_array($file['type'], $allowed_types)) {
        $_SESSION['error'] = "Invalid file type. Please upload an image (JPEG, PNG, GIF) or PDF";
        header("Location: ../../customer/prescriptions.php");
        exit();
    }

    if ($file['size'] > $max_size) {
        $_SESSION['error'] = "File size must be less than 5MB";
        header("Location: ../../customer/prescriptions.php");
        exit();
    }

    // Create upload directory if it doesn't exist
    $upload_dir = '../../assets/images/prescriptions/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    // Generate unique filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'prescription_' . $order_id . '_' . time() . '.' . $extension;
    $filepath = $upload_dir . $filename;

    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO prescriptions (user_id, order_id, image, status, uploaded_at)
                VALUES (?, ?, ?, 'pending', NOW())
            ");

            if ($stmt->execute([$_SESSION['user_id'], $order_id, $filename])) {
                // Update order status
                $stmt = $pdo->prepare("
                    UPDATE orders 
                    SET status = 'pending' 
                    WHERE order_id = ? AND user_id = ?
                ");
                $stmt->execute([$order_id, $_SESSION['user_id']]);

                $_SESSION['success'] = "Prescription uploaded successfully";
            } else {
                $_SESSION['error'] = "Failed to save prescription details";
                // Remove the file if database insert failed
                unlink($filepath);
            }
        } catch (PDOException $e) {
            error_log($e->getMessage());
            $_SESSION['error'] = "Error saving prescription";
            // Remove the file if database error occurred
            unlink($filepath);
        }
    } else {
        $_SESSION['error'] = "Failed to save prescription file";
    }
} else {
    $_SESSION['error'] = "Invalid request";
}

header("Location: ../../customer/prescriptions.php");
exit();
