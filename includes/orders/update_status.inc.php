<?php
session_start();
require_once '../../config/config.php';
require_once '../functions.php';

// Ensure user is logged in and is a pharmacy
require_role('pharmacy');

if (isset($_GET['order_id']) && isset($_GET['status'])) {
    $order_id = filter_var($_GET['order_id'], FILTER_VALIDATE_INT);
    $new_status = sanitize_input($_GET['status']);

    // Valid status transitions
    $valid_statuses = ['confirmed', 'ready', 'completed', 'cancelled'];

    if (!$order_id || !in_array($new_status, $valid_statuses)) {
        $_SESSION['error'] = "Invalid request parameters";
        header("Location: ../../pharmacy/orders.php");
        exit();
    }

    try {
        $pdo->beginTransaction();

        // Get current order status and verify pharmacy ownership
        $stmt = $pdo->prepare("
            SELECT o.status as current_status, 
                   o.pharmacy_id,
                   p.prescription_id,
                   p.status as prescription_status
            FROM orders o
            LEFT JOIN prescriptions p ON o.order_id = p.order_id
            WHERE o.order_id = ?
        ");
        $stmt->execute([$order_id]);
        $order = $stmt->fetch();

        // Verify ownership
        if (!$order || $order['pharmacy_id'] != $_SESSION['user_id']) {
            throw new Exception("Order not found or access denied");
        }

        // Validate status transition
        $valid_transition = false;
        switch ($order['current_status']) {
            case 'pending':
                // Can only move to confirmed if prescription is approved (if required)
                if ($new_status === 'confirmed') {
                    if ($order['prescription_id'] && $order['prescription_status'] !== 'approved') {
                        throw new Exception("Cannot confirm order until prescription is approved");
                    }
                    $valid_transition = true;
                } elseif ($new_status === 'cancelled') {
                    $valid_transition = true;
                }
                break;
            case 'confirmed':
                // Can move to ready
                $valid_transition = ($new_status === 'ready');
                break;
            case 'ready':
                // Can move to completed
                $valid_transition = ($new_status === 'completed');
                break;
        }

        if (!$valid_transition) {
            throw new Exception("Invalid status transition");
        }

        // Update order status
        $stmt = $pdo->prepare("
            UPDATE orders 
            SET status = ?,
                updated_at = NOW()
            WHERE order_id = ? AND pharmacy_id = ?
        ");

        $stmt->execute([$new_status, $order_id, $_SESSION['user_id']]);

        // If cancelling, restore stock
        if ($new_status === 'cancelled') {
            $stmt = $pdo->prepare("
        UPDATE medicines m
        INNER JOIN order_items oi ON m.medicine_id = oi.medicine_id
        SET m.stock_quantity = m.stock_quantity + oi.quantity
        WHERE oi.order_id = ?
    ");
            $stmt->execute([$order_id]);
        }

        $pdo->commit();

        // Set appropriate success message
        switch ($new_status) {
            case 'confirmed':
                $_SESSION['success'] = "Order has been accepted";
                break;
            case 'ready':
                $_SESSION['success'] = "Order marked as ready for pickup/delivery";
                break;
            case 'completed':
                $_SESSION['success'] = "Order completed successfully";
                break;
            case 'cancelled':
                $_SESSION['success'] = "Order cancelled and stock restored";
                break;
        }

        // Log the status change
        error_log("Order {$order_id} status updated from {$order['current_status']} to {$new_status} by pharmacy {$_SESSION['user_id']}");
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log($e->getMessage());
        $_SESSION['error'] = $e->getMessage();
    }

    // Redirect back to appropriate page
    $redirect = isset($_SERVER['HTTP_REFERER']) && strpos($_SERVER['HTTP_REFERER'], 'order_details.php') !== false
        ? "../../pharmacy/order_details.php?id=" . $order_id
        : "../../pharmacy/orders.php";

    header("Location: " . $redirect);
    exit();
}

// Invalid request
$_SESSION['error'] = "Invalid request";
header("Location: ../../pharmacy/orders.php");
exit();
