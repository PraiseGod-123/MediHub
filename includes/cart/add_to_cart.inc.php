<?php
session_start();
require_once '../../config/config.php';
require_once '../../includes/functions.php';

if (!is_logged_in()) {
    $_SESSION['login_error'] = "Please login to add items to cart";
    header("Location: ../../public/login_register.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['medicine_id'])) {
    $medicine_id = filter_var($_POST['medicine_id'], FILTER_VALIDATE_INT);
    $quantity = isset($_POST['quantity']) ? filter_var($_POST['quantity'], FILTER_VALIDATE_INT) : 1;

    if (!$medicine_id || !$quantity || $quantity < 1) {
        $_SESSION['cart_error'] = "Invalid item or quantity";
        header("Location: " . $_SERVER['HTTP_REFERER']);
        exit();
    }

    try {
        // Check if medicine exists and is available
        $stmt = $pdo->prepare("
            SELECT m.*, p.pharmacy_id 
            FROM medicines m
            JOIN pharmacy_details p ON m.pharmacy_id = p.pharmacy_id
            WHERE m.medicine_id = ? AND m.status = 'available'
        ");
        $stmt->execute([$medicine_id]);
        $medicine = $stmt->fetch();

        if (!$medicine) {
            $_SESSION['cart_error'] = "Medicine not available";
            header("Location: " . $_SERVER['HTTP_REFERER']);
            exit();
        }

        // Check if medicine requires prescription
        if ($medicine['requires_prescription']) {
            $_SESSION['prescription_required'] = $medicine_id;
            header("Location: ../../customer/upload_prescription.php?medicine_id=" . $medicine_id);
            exit();
        }

        // Check if quantity is available
        if ($quantity > $medicine['stock_quantity']) {
            $_SESSION['cart_error'] = "Requested quantity not available";
            header("Location: " . $_SERVER['HTTP_REFERER']);
            exit();
        }

        // Check if item already exists in cart
        $stmt = $pdo->prepare("
            SELECT cart_id, quantity 
            FROM cart_items 
            WHERE user_id = ? AND medicine_id = ?
        ");
        $stmt->execute([$_SESSION['user_id'], $medicine_id]);
        $existing_item = $stmt->fetch();

        if ($existing_item) {
            // Update quantity if total doesn't exceed stock
            $new_quantity = $existing_item['quantity'] + $quantity;
            if ($new_quantity > $medicine['stock_quantity']) {
                $_SESSION['cart_error'] = "Cannot add more of this item (stock limit reached)";
                header("Location: " . $_SERVER['HTTP_REFERER']);
                exit();
            }

            $stmt = $pdo->prepare("
                UPDATE cart_items 
                SET quantity = ? 
                WHERE cart_id = ?
            ");
            $stmt->execute([$new_quantity, $existing_item['cart_id']]);
        } else {
            // Add new item to cart
            $stmt = $pdo->prepare("
                INSERT INTO cart_items (user_id, medicine_id, quantity)
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$_SESSION['user_id'], $medicine_id, $quantity]);
        }

        $_SESSION['cart_success'] = "Item added to cart successfully";

        // Update cart count in session
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count 
            FROM cart_items 
            WHERE user_id = ?
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $_SESSION['cart_count'] = $stmt->fetch()['count'];
    } catch (PDOException $e) {
        error_log($e->getMessage());
        $_SESSION['cart_error'] = "Error adding item to cart";
    }

    header("Location: " . $_SERVER['HTTP_REFERER']);
    exit();
}

// If accessed directly without POST
header("Location: ../../customer/medicines.php");
exit();
