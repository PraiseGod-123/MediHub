<?php
session_start();
require_once '../../config/config.php';
require_once '../functions.php';

// Ensure user is logged in and is a pharmacy
require_role('pharmacy');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['prescription_id'], $_POST['action'])) {
    $prescription_id = filter_var($_POST['prescription_id'], FILTER_VALIDATE_INT);
    $action = sanitize_input($_POST['action']);
    $rejection_reason = isset($_POST['rejection_reason']) ? sanitize_input($_POST['rejection_reason']) : null;

    if (!$prescription_id) {
        $_SESSION['error'] = "Invalid prescription ID";
        header("Location: ../../pharmacy/prescription_review.php");
        exit();
    }

    try {
        // Verify the prescription belongs to an order for this pharmacy
        $stmt = $pdo->prepare("
            SELECT p.*, o.pharmacy_id
            FROM prescriptions p
            JOIN orders o ON p.order_id = o.order_id
            WHERE p.prescription_id = ? AND o.pharmacy_id = ?
        ");
        $stmt->execute([$prescription_id, $_SESSION['user_id']]);
        $prescription = $stmt->fetch();

        if (!$prescription) {
            $_SESSION['error'] = "Prescription not found";
            header("Location: ../../pharmacy/prescription_review.php");
            exit();
        }

        // Begin transaction
        $pdo->beginTransaction();

        if ($action === 'approve') {
            // Update prescription status
            $stmt = $pdo->prepare("
                UPDATE prescriptions 
                SET status = 'approved', 
                    reviewed_at = NOW(),
                    rejection_reason = NULL
                WHERE prescription_id = ?
            ");
            $stmt->execute([$prescription_id]);

            // Update order status
            $stmt = $pdo->prepare("
                UPDATE orders 
                SET status = 'processing'
                WHERE order_id = ?
            ");
            $stmt->execute([$prescription['order_id']]);

            $_SESSION['success'] = "Prescription approved successfully";
        } elseif ($action === 'reject') {
            if (empty($rejection_reason)) {
                throw new Exception("Rejection reason is required");
            }

            // Update prescription status
            $stmt = $pdo->prepare("
                UPDATE prescriptions 
                SET status = 'rejected', 
                    reviewed_at = NOW(),
                    rejection_reason = ?
                WHERE prescription_id = ?
            ");
            $stmt->execute([$rejection_reason, $prescription_id]);

            // Update order status
            $stmt = $pdo->prepare("
                UPDATE orders 
                SET status = 'cancelled'
                WHERE order_id = ?
            ");
            $stmt->execute([$prescription['order_id']]);

            // Optional: Send email notification to customer
            // send_prescription_rejection_email($prescription['user_id'], $rejection_reason);

            $_SESSION['success'] = "Prescription rejected successfully";
        } else {
            throw new Exception("Invalid action");
        }

        $pdo->commit();

        // Log the action
        error_log("Prescription {$prescription_id} {$action}ed by pharmacy {$_SESSION['user_id']}");
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log($e->getMessage());
        $_SESSION['error'] = "Error updating prescription status: " . $e->getMessage();
    }
} else {
    $_SESSION['error'] = "Invalid request";
}

header("Location: ../../pharmacy/prescription_review.php");
exit();
