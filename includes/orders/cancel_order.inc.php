<?php
session_start();
require_once '../../config/config.php';
require_once '../functions.php';

require_role('customer');

if (isset($_GET['order_id'])) {
    $order_id = filter_var($_GET['order_id'], FILTER_VALIDATE_INT);

    try {
        $pdo->beginTransaction();

        // Update order status to cancelled
        $stmt = $pdo->prepare("
            UPDATE orders 
            SET status = 'cancelled',
                updated_at = NOW()
            WHERE order_id = ? 
            AND user_id = ? 
            AND status = 'pending'
        ");

        $result = $stmt->execute([$order_id, $_SESSION['user_id']]);

        if ($stmt->rowCount() > 0) {
            // If order was cancelled successfully, restore stock
            $stmt = $pdo->prepare("
                UPDATE medicines m
                INNER JOIN order_items oi ON m.medicine_id = oi.medicine_id
                SET m.stock_quantity = m.stock_quantity + oi.quantity
                WHERE oi.order_id = ?
            ");
            $stmt->execute([$order_id]);

            $pdo->commit();
            $_SESSION['success'] = "Order cancelled successfully";
        } else {
            throw new Exception("Unable to cancel order. It may already be cancelled or processed.");
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error'] = $e->getMessage();
    }

    header("Location: ../../customer/orders.php");
    exit();
}

$_SESSION['error'] = "Invalid request";
header("Location: ../../customer/orders.php");
exit();
