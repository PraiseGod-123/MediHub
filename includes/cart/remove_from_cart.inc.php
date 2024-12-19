<?php
session_start();
require_once '../../config/config.php';
require_once '../../includes/functions.php';

if (!is_logged_in()) {
    header("Location: ../../public/login_register.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cart_id'])) {
    $cart_id = filter_var($_POST['cart_id'], FILTER_VALIDATE_INT);

    if (!$cart_id) {
        $_SESSION['cart_error'] = "Invalid cart item";
        header("Location: ../../customer/cart.php");
        exit();
    }

    try {
        // Verify cart item belongs to user and delete it
        $stmt = $pdo->prepare("
            DELETE FROM cart_items 
            WHERE cart_id = ? AND user_id = ?
        ");
        $result = $stmt->execute([$cart_id, $_SESSION['user_id']]);

        if ($result) {
            $_SESSION['cart_success'] = "Item removed from cart";
        } else {
            $_SESSION['cart_error'] = "Error removing item from cart";
        }
    } catch (PDOException $e) {
        error_log($e->getMessage());
        $_SESSION['cart_error'] = "Error removing item from cart";
    }

    header("Location: ../../customer/cart.php");
    exit();
}
