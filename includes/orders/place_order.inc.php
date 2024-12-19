<?php
session_start();
require_once '../../config/config.php';
require_once '../functions.php';

// Ensure user is logged in and is a customer
require_role('customer');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order'])) {
    // Get and sanitize input
    $name = sanitize_input($_POST['name']);
    $phone = sanitize_input($_POST['phone']);
    $email = sanitize_input($_POST['email']);
    $address = sanitize_input($_POST['address']);
    $notes = sanitize_input($_POST['notes']);

    // Validate input
    if (empty($name) || empty($phone) || empty($email) || empty($address)) {
        $_SESSION['error'] = "Please fill in all required fields";
        header("Location: ../../customer/checkout.php");
        exit();
    }

    try {
        // Start transaction
        $pdo->beginTransaction();

        // Get cart items grouped by pharmacy
        $stmt = $pdo->prepare("
            SELECT ci.*, m.name, m.price, m.requires_prescription, m.pharmacy_id,
                   m.stock_quantity
            FROM cart_items ci
            JOIN medicines m ON ci.medicine_id = m.medicine_id
            WHERE ci.user_id = ?
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $cart_items = $stmt->fetchAll();

        if (empty($cart_items)) {
            throw new Exception("Your cart is empty");
        }

        // Group items by pharmacy
        $orders_by_pharmacy = [];
        foreach ($cart_items as $item) {
            // Verify stock availability
            if ($item['quantity'] > $item['stock_quantity']) {
                throw new Exception("Insufficient stock for {$item['name']}");
            }

            if (!isset($orders_by_pharmacy[$item['pharmacy_id']])) {
                $orders_by_pharmacy[$item['pharmacy_id']] = [
                    'items' => [],
                    'total' => 0,
                    'requires_prescription' => false
                ];
            }
            $orders_by_pharmacy[$item['pharmacy_id']]['items'][] = $item;
            $orders_by_pharmacy[$item['pharmacy_id']]['total'] += $item['price'] * $item['quantity'];

            if ($item['requires_prescription']) {
                $orders_by_pharmacy[$item['pharmacy_id']]['requires_prescription'] = true;
            }
        }

        // Create orders for each pharmacy
        $orders_requiring_prescription = [];

        foreach ($orders_by_pharmacy as $pharmacy_id => $order_data) {
            // Create order
            $stmt = $pdo->prepare("
                INSERT INTO orders (
                    user_id,
                    pharmacy_id,
                    total_amount,
                    status,
                    created_at,
                    updated_at
                ) VALUES (?, ?, ?, 'pending', NOW(), NOW())
            ");
            $stmt->execute([
                $_SESSION['user_id'],
                $pharmacy_id,
                $order_data['total']
            ]);

            $order_id = $pdo->lastInsertId();

            // Add order items
            $stmt = $pdo->prepare("
                INSERT INTO order_items (
                    order_id,
                    medicine_id,
                    quantity,
                    price_per_unit
                ) VALUES (?, ?, ?, ?)
            ");

            foreach ($order_data['items'] as $item) {
                // Insert order item
                $stmt->execute([
                    $order_id,
                    $item['medicine_id'],
                    $item['quantity'],
                    $item['price']
                ]);

                // Update stock quantity
                $update_stock = $pdo->prepare("
                    UPDATE medicines 
                    SET stock_quantity = stock_quantity - ?,
                        status = CASE 
                            WHEN (stock_quantity - ?) = 0 THEN 'out_of_stock'
                            ELSE status 
                        END
                    WHERE medicine_id = ?
                ");
                $update_stock->execute([
                    $item['quantity'],
                    $item['quantity'],
                    $item['medicine_id']
                ]);
            }

            // If order requires prescription, add to list
            if ($order_data['requires_prescription']) {
                $orders_requiring_prescription[] = $order_id;
            }
        }

        // Clear the cart
        $stmt = $pdo->prepare("DELETE FROM cart_items WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);

        // Update user delivery information if changed
        $stmt = $pdo->prepare("
            UPDATE users 
            SET phone = ?,
                address = ?
            WHERE user_id = ?
        ");
        $stmt->execute([$phone, $address, $_SESSION['user_id']]);

        $pdo->commit();

        // Store orders requiring prescription in session
        if (!empty($orders_requiring_prescription)) {
            $_SESSION['orders_requiring_prescription'] = $orders_requiring_prescription;
            $_SESSION['success'] = "Orders placed successfully. Please upload required prescriptions.";
            header("Location: ../../customer/upload_prescription.php?order_id=" . $orders_requiring_prescription[0]);
            exit();
        }

        $_SESSION['success'] = "Orders placed successfully!";
        header("Location: ../../customer/orders.php");
        exit();
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log($e->getMessage());
        $_SESSION['error'] = "Error placing order: " . $e->getMessage();
        header("Location: ../../customer/checkout.php");
        exit();
    }
} else {
    header("Location: ../../customer/cart.php");
    exit();
}
