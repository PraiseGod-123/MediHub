<?php
session_start();
require_once '../../config/config.php';
require_once '../functions.php';

// Ensure user is logged in and is a pharmacy
require_role('pharmacy');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['medicine_id'])) {
    // Get and sanitize input
    $medicine_id = filter_var($_POST['medicine_id'], FILTER_VALIDATE_INT);
    $name = sanitize_input($_POST['name']);
    $category_id = filter_var($_POST['category_id'], FILTER_VALIDATE_INT);
    $description = sanitize_input($_POST['description']);
    $price = filter_var($_POST['price'], FILTER_VALIDATE_FLOAT);
    $requires_prescription = isset($_POST['requires_prescription']) ? 1 : 0;
    $status = sanitize_input($_POST['status']);

    // Validate input
    $errors = [];

    if (!$medicine_id) {
        $errors[] = "Invalid medicine ID";
    }

    if (empty($name)) {
        $errors[] = "Medicine name is required";
    }

    if (!$category_id) {
        $errors[] = "Please select a valid category";
    }

    if (!$price || $price <= 0) {
        $errors[] = "Please enter a valid price";
    }

    if (!in_array($status, ['available', 'out_of_stock', 'discontinued'])) {
        $errors[] = "Invalid status selected";
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            // Get current medicine details
            $stmt = $pdo->prepare("
                SELECT image 
                FROM medicines 
                WHERE medicine_id = ? AND pharmacy_id = ?
            ");
            $stmt->execute([$medicine_id, $_SESSION['user_id']]);
            $current_medicine = $stmt->fetch();

            if (!$current_medicine) {
                throw new Exception("Medicine not found or access denied");
            }

            // Handle image upload
            $image_filename = $current_medicine['image'];
            if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
                $max_size = 2 * 1024 * 1024; // 2MB

                if (!in_array($_FILES['image']['type'], $allowed_types)) {
                    throw new Exception("Invalid image type. Please upload JPEG, PNG, or GIF");
                }

                if ($_FILES['image']['size'] > $max_size) {
                    throw new Exception("Image size must be less than 2MB");
                }

                // Create directory if it doesn't exist
                $upload_dir = UPLOAD_PATH . 'products/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }

                // Delete old image if exists
                if ($image_filename && file_exists($upload_dir . $image_filename)) {
                    unlink($upload_dir . $image_filename);
                }

                $image_filename = uniqid('med_') . '_' . time() . '.' .
                    pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);

                $upload_path = $upload_dir . $image_filename;

                if (!move_uploaded_file($_FILES['image']['tmp_name'], $upload_path)) {
                    throw new Exception("Failed to upload image");
                }
            }

            // Check if name exists for another medicine
            $stmt = $pdo->prepare("
                SELECT COUNT(*) 
                FROM medicines 
                WHERE pharmacy_id = ? AND LOWER(name) = LOWER(?) AND medicine_id != ?
            ");
            $stmt->execute([$_SESSION['user_id'], $name, $medicine_id]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception("Another medicine with this name already exists");
            }

            // Update medicine record
            $stmt = $pdo->prepare("
                UPDATE medicines 
                SET name = ?,
                    category_id = ?,
                    description = ?,
                    price = ?,
                    requires_prescription = ?,
                    status = ?,
                    image = ?
                WHERE medicine_id = ? AND pharmacy_id = ?
            ");

            $stmt->execute([
                $name,
                $category_id,
                $description,
                $price,
                $requires_prescription,
                $status,
                $image_filename,
                $medicine_id,
                $_SESSION['user_id']
            ]);

            $pdo->commit();
            $_SESSION['success'] = "Medicine updated successfully";

            // Log the action
            error_log("Medicine {$medicine_id} updated by pharmacy {$_SESSION['user_id']}");
        } catch (Exception $e) {
            $pdo->rollBack();

            // Delete newly uploaded image if exists and there was an error
            if (
                isset($image_filename) && $image_filename !== $current_medicine['image'] &&
                file_exists(UPLOAD_PATH . 'products/' . $image_filename)
            ) {
                unlink(UPLOAD_PATH . 'products/' . $image_filename);
            }

            error_log($e->getMessage());
            $_SESSION['error'] = "Error updating medicine: " . $e->getMessage();
        }
    } else {
        $_SESSION['error'] = implode("<br>", $errors);
    }
}

header("Location: ../../pharmacy/inventory.php");
exit();
