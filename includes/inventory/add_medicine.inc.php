<?php
session_start();
require_once '../../config/config.php';
require_once '../functions.php';

// Ensure user is logged in and is a pharmacy
require_role('pharmacy');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Debug log
    error_log("Medicine addition attempt started");

    // Get and sanitize input
    $name = sanitize_input($_POST['name']);
    $category_id = filter_var($_POST['category_id'], FILTER_VALIDATE_INT);
    $description = sanitize_input($_POST['description']);
    $price = filter_var($_POST['price'], FILTER_VALIDATE_FLOAT);
    $stock_quantity = filter_var($_POST['stock_quantity'], FILTER_VALIDATE_INT);
    $requires_prescription = isset($_POST['requires_prescription']) ? 1 : 0;
    $status = sanitize_input($_POST['status']);

    // Debug log the received values
    error_log("Received values - Name: $name, Category: $category_id, Price: $price, Stock: $stock_quantity");

    // Validate input
    $errors = [];

    if (empty($name)) {
        $errors[] = "Medicine name is required";
    }

    if (!$category_id) {
        $errors[] = "Please select a valid category";
    }

    if (!$price || $price <= 0) {
        $errors[] = "Please enter a valid price";
    }

    if ($stock_quantity === false || $stock_quantity < 0) {
        $errors[] = "Please enter a valid stock quantity";
    }

    if (!in_array($status, ['available', 'out_of_stock', 'discontinued'])) {
        $errors[] = "Invalid status selected";
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            error_log("Starting transaction for medicine addition");

            // Handle image upload
            $image_filename = null;
            if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                error_log("Processing image upload");

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

                $image_filename = uniqid('med_') . '_' . time() . '.' .
                    pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);

                $upload_path = $upload_dir . $image_filename;

                if (!move_uploaded_file($_FILES['image']['tmp_name'], $upload_path)) {
                    error_log("Failed to upload image to: " . $upload_path);
                    throw new Exception("Failed to upload image");
                }

                error_log("Image uploaded successfully: " . $image_filename);
            }

            // Insert medicine record
            $stmt = $pdo->prepare("
                INSERT INTO medicines (
                    pharmacy_id,
                    category_id,
                    name,
                    description,
                    price,
                    stock_quantity,
                    requires_prescription,
                    status,
                    image
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            error_log("Executing medicine insert with pharmacy_id: " . $_SESSION['user_id']);

            $result = $stmt->execute([
                $_SESSION['user_id'],
                $category_id,
                $name,
                $description,
                $price,
                $stock_quantity,
                $requires_prescription,
                $status,
                $image_filename
            ]);

            if (!$result) {
                error_log("Failed to insert medicine. Error: " . json_encode($stmt->errorInfo()));
                throw new Exception("Failed to insert medicine record");
            }

            $medicine_id = $pdo->lastInsertId();
            error_log("Medicine inserted with ID: " . $medicine_id);

            // Record initial stock in history if stock_quantity > 0
            if ($stock_quantity > 0) {
                error_log("Recording initial stock history");

                $stmt = $pdo->prepare("
                    INSERT INTO stock_history (
                        medicine_id,
                        operation,
                        quantity,
                        reason,
                        notes,
                        updated_by
                    ) VALUES (?, 'add', ?, 'initial_stock', 'Initial stock on medicine creation', ?)
                ");

                $result = $stmt->execute([$medicine_id, $stock_quantity, $_SESSION['user_id']]);

                if (!$result) {
                    error_log("Failed to insert stock history. Error: " . json_encode($stmt->errorInfo()));
                    throw new Exception("Failed to record initial stock");
                }
            }

            $pdo->commit();
            error_log("Medicine addition completed successfully");
            $_SESSION['success'] = "Medicine added successfully";
        } catch (Exception $e) {
            $pdo->rollBack();
            error_log("Error in medicine addition: " . $e->getMessage());

            // Delete uploaded image if exists
            if ($image_filename && file_exists(UPLOAD_PATH . 'products/' . $image_filename)) {
                unlink(UPLOAD_PATH . 'products/' . $image_filename);
            }

            $_SESSION['error'] = "Error adding medicine: " . $e->getMessage();
        }
    } else {
        error_log("Validation errors in medicine addition: " . implode(", ", $errors));
        $_SESSION['error'] = implode("<br>", $errors);
    }
} else {
    error_log("Invalid request method for medicine addition");
    $_SESSION['error'] = "Invalid request";
}

header("Location: ../../pharmacy/inventory.php");
exit();
