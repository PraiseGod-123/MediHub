<?php
session_start();
require_once '../../config/config.php';
require_once '../functions.php';

// Ensure user is logged in and is a pharmacy
require_role('pharmacy');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $medicine_id = filter_var($_POST['medicine_id'], FILTER_VALIDATE_INT);
    $quantity = filter_var($_POST['quantity'], FILTER_VALIDATE_INT);
    $operation = sanitize_input($_POST['operation']); // 'add' or 'subtract'
    $reason = sanitize_input($_POST['reason']);
    $notes = sanitize_input($_POST['notes']);

    if (!$medicine_id || !$quantity || $quantity < 1) {
        $_SESSION['error'] = "Invalid quantity specified";
        header("Location: ../../pharmacy/inventory.php");
        exit();
    }

    try {
        $pdo->beginTransaction();

        // Get current stock and verify pharmacy ownership
        $stmt = $pdo->prepare("
            SELECT medicine_id, name, stock_quantity 
            FROM medicines 
            WHERE medicine_id = ? AND pharmacy_id = ?
            FOR UPDATE
        ");
        $stmt->execute([$medicine_id, $_SESSION['user_id']]);
        $medicine = $stmt->fetch();

        if (!$medicine) {
            throw new Exception("Medicine not found or access denied");
        }

        // Calculate new stock level
        if ($operation === 'add') {
            $new_stock = $medicine['stock_quantity'] + $quantity;
        } else {
            if ($quantity > $medicine['stock_quantity']) {
                throw new Exception("Cannot remove more units than current stock");
            }
            $new_stock = $medicine['stock_quantity'] - $quantity;
        }

        // Update stock level
        $stmt = $pdo->prepare("
            UPDATE medicines 
            SET stock_quantity = ?,
                status = CASE 
                    WHEN ? = 0 THEN 'out_of_stock'
                    ELSE 'available'
                END
            WHERE medicine_id = ?
        ");
        $stmt->execute([$new_stock, $new_stock, $medicine_id]);

        // Record stock update in history
        $stmt = $pdo->prepare("
            INSERT INTO stock_history (
                medicine_id,
                operation,
                quantity,
                reason,
                notes,
                updated_by
            ) VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $medicine_id,
            $operation,
            $quantity,
            $reason,
            $notes,
            $_SESSION['user_id']
        ]);

        $pdo->commit();

        $_SESSION['success'] = sprintf(
            "Stock %s by %d units. New stock level: %d",
            $operation === 'add' ? 'increased' : 'decreased',
            $quantity,
            $new_stock
        );

        // Log the stock update
        error_log(sprintf(
            "Stock updated for medicine %d (%s): %s %d units by pharmacy %d. New stock: %d",
            $medicine_id,
            $medicine['name'],
            $operation,
            $quantity,
            $_SESSION['user_id'],
            $new_stock
        ));
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log($e->getMessage());
        $_SESSION['error'] = $e->getMessage();
    }

    header("Location: ../../pharmacy/inventory.php");
    exit();
}

$_SESSION['error'] = "Invalid request";
header("Location: ../../pharmacy/inventory.php");
exit();
