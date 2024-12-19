<?php
session_start();
require_once '../../config/config.php';
require_once '../../includes/functions.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cart_id'], $_POST['quantity'])) {
    $cart_id = filter_var($_POST['cart_id'], FILTER_VALIDATE_INT);
    $quantity = filter_var($_POST['quantity'], FILTER_VALIDATE_INT);

    if (!$cart_id || !$quantity || $quantity < 1 || $quantity > 99) {
        echo json_encode(['success' => false, 'message' => 'Invalid quantity']);
        exit();
    }

    try {
        // Verify cart item belongs to user
        $stmt = $pdo->prepare("
            SELECT ci.*, m.stock_quantity 
            FROM cart_items ci
            JOIN medicines m ON ci.medicine_id = m.medicine_id
            WHERE ci.cart_id = ? AND ci.user_id = ?
        ");
        $stmt->execute([$cart_id, $_SESSION['user_id']]);
        $cart_item = $stmt->fetch();

        if (!$cart_item) {
            echo json_encode(['success' => false, 'message' => 'Cart item not found']);
            exit();
        }

        // Check if requested quantity is available
        if ($quantity > $cart_item['stock_quantity']) {
            echo json_encode([
                'success' => false,
                'message' => 'Requested quantity exceeds available stock',
                'available_quantity' => $cart_item['stock_quantity']
            ]);
            exit();
        }

        // Update quantity
        $stmt = $pdo->prepare("
            UPDATE cart_items 
            SET quantity = ?
            WHERE cart_id = ? AND user_id = ?
        ");
        $stmt->execute([$quantity, $cart_id, $_SESSION['user_id']]);

        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        error_log($e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}
