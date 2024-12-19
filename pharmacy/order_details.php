<?php
session_start();
require_once '../config/config.php';
require_once '../includes/functions.php';

// Ensure user is logged in and is a pharmacy
require_role('pharmacy');

$order_id = isset($_GET['id']) ? filter_var($_GET['id'], FILTER_VALIDATE_INT) : null;

if (!$order_id) {
    $_SESSION['error'] = "Invalid order ID";
    header("Location: orders.php");
    exit();
}

try {
    // Get order details with customer information
    $stmt = $pdo->prepare("
        SELECT o.*, u.first_name, u.last_name, u.email, u.phone, u.address,
               p.status as prescription_status, p.image as prescription_image,
               p.prescription_id, p.reviewed_at, p.rejection_reason
        FROM orders o
        JOIN users u ON o.user_id = u.user_id
        LEFT JOIN prescriptions p ON o.order_id = p.order_id
        WHERE o.order_id = ? AND o.pharmacy_id = ?
    ");
    $stmt->execute([$order_id, $_SESSION['user_id']]);
    $order = $stmt->fetch();

    if (!$order) {
        $_SESSION['error'] = "Order not found";
        header("Location: orders.php");
        exit();
    }

    // Get order items
    $stmt = $pdo->prepare("
        SELECT oi.*, m.name as medicine_name, m.image as medicine_image,
               m.requires_prescription
        FROM order_items oi
        JOIN medicines m ON oi.medicine_id = m.medicine_id
        WHERE oi.order_id = ?
    ");
    $stmt->execute([$order_id]);
    $order_items = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log($e->getMessage());
    $_SESSION['error'] = "Error retrieving order details";
    header("Location: orders.php");
    exit();
}

// Additional CSS for this page
$additional_css = ['pharmacy-order-details'];

include_once '../includes/header.php';
?>

<div class="order-details-container animate-fade-in">
    <!-- Status Bar -->
    <div class="status-bar card">
        <div class="status-content">
            <div class="status-info">
                <h1>Order #<?php echo $order_id; ?></h1>
                <span class="timestamp">
                    <i class="fas fa-clock"></i>
                    <?php echo date('F j, Y, g:i a', strtotime($order['created_at'])); ?>
                </span>
            </div>
            <div class="status-badge <?php echo $order['status']; ?>">
                <?php echo ucfirst($order['status']); ?>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="action-buttons">
            <?php if ($order['status'] === 'pending'): ?>
                <button onclick="updateOrderStatus(<?php echo $order_id; ?>, 'confirmed')" class="btn btn-success">
                    <i class="fas fa-check"></i> Accept Order
                </button>
                <button onclick="updateOrderStatus(<?php echo $order_id; ?>, 'cancelled')" class="btn btn-danger">
                    <i class="fas fa-times"></i> Cancel Order
                </button>
            <?php elseif ($order['status'] === 'confirmed'): ?>
                <button onclick="updateOrderStatus(<?php echo $order_id; ?>, 'ready')" class="btn btn-primary">
                    <i class="fas fa-box"></i> Mark as Ready
                </button>
            <?php elseif ($order['status'] === 'ready'): ?>
                <button onclick="updateOrderStatus(<?php echo $order_id; ?>, 'completed')" class="btn btn-success">
                    <i class="fas fa-check-circle"></i> Complete Order
                </button>
            <?php endif; ?>
        </div>
    </div>

    <div class="order-grid">
        <!-- Customer Information -->
        <div class="order-section card">
            <h2><i class="fas fa-user"></i> Customer Information</h2>
            <div class="info-grid">
                <div class="info-item">
                    <span class="label">Name:</span>
                    <span class="value"><?php echo htmlspecialchars($order['first_name'] . ' ' . $order['last_name']); ?></span>
                </div>
                <div class="info-item">
                    <span class="label">Email:</span>
                    <span class="value"><?php echo htmlspecialchars($order['email']); ?></span>
                </div>
                <div class="info-item">
                    <span class="label">Phone:</span>
                    <span class="value"><?php echo htmlspecialchars($order['phone'] ?? 'Not provided'); ?></span>
                </div>
                <div class="info-item full-width">
                    <span class="label">Delivery Address:</span>
                    <span class="value"><?php echo nl2br(htmlspecialchars($order['address'])); ?></span>
                </div>
            </div>
        </div>

        <!-- Order Items -->
        <div class="order-section card">
            <h2><i class="fas fa-pills"></i> Order Items</h2>
            <div class="items-list">
                <?php foreach ($order_items as $item): ?>
                    <div class="order-item">
                        <div class="item-image">
                            <?php if ($item['medicine_image']): ?>
                                <img src="<?php echo BASE_URL; ?>/assets/images/products/<?php echo htmlspecialchars($item['medicine_image']); ?>"
                                    alt="<?php echo htmlspecialchars($item['medicine_name']); ?>">
                            <?php else: ?>
                                <div class="placeholder-image">
                                    <i class="fas fa-pills"></i>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="item-details">
                            <h3><?php echo htmlspecialchars($item['medicine_name']); ?></h3>
                            <?php if ($item['requires_prescription']): ?>
                                <span class="prescription-badge">
                                    <i class="fas fa-file-medical"></i> Prescription Required
                                </span>
                            <?php endif; ?>
                            <div class="item-meta">
                                <span class="quantity">
                                    <i class="fas fa-box"></i>
                                    Quantity: <?php echo $item['quantity']; ?>
                                </span>
                                <span class="price">
                                    <i class="fas fa-tag"></i>
                                    <?php echo format_currency($item['price_per_unit']); ?> each
                                </span>
                            </div>
                            <div class="item-total">
                                Total: <?php echo format_currency($item['quantity'] * $item['price_per_unit']); ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="order-total">
                <span>Total Amount:</span>
                <span class="total-amount"><?php echo format_currency($order['total_amount']); ?></span>
            </div>
        </div>

        <!-- Prescription Section (if applicable) -->
        <?php if ($order['prescription_id']): ?>
            <div class="order-section card">
                <h2><i class="fas fa-file-medical"></i> Prescription</h2>
                <div class="prescription-content">
                    <div class="prescription-image">
                        <img src="<?php echo BASE_URL; ?>/assets/images/prescriptions/<?php echo htmlspecialchars($order['prescription_image']); ?>"
                            alt="Prescription"
                            onclick="viewPrescription(this.src)">
                    </div>
                    <div class="prescription-info">
                        <div class="status-badge <?php echo $order['prescription_status']; ?>">
                            <?php echo ucfirst($order['prescription_status']); ?>
                        </div>
                        <?php if ($order['reviewed_at']): ?>
                            <div class="review-info">
                                Reviewed on <?php echo date('F j, Y, g:i a', strtotime($order['reviewed_at'])); ?>
                            </div>
                        <?php endif; ?>
                        <?php if ($order['rejection_reason']): ?>
                            <div class="rejection-reason">
                                <h4>Rejection Reason:</h4>
                                <p><?php echo htmlspecialchars($order['rejection_reason']); ?></p>
                            </div>
                        <?php endif; ?>

                        <?php if ($order['prescription_status'] === 'pending'): ?>
                            <div class="prescription-actions">
                                <button onclick="approvePrescription(<?php echo $order['prescription_id']; ?>)"
                                    class="btn btn-success">
                                    <i class="fas fa-check"></i> Approve Prescription
                                </button>
                                <button onclick="showRejectForm(<?php echo $order['prescription_id']; ?>)"
                                    class="btn btn-danger">
                                    <i class="fas fa-times"></i> Reject Prescription
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Prescription View Modal -->
<div id="prescriptionModal" class="modal">
    <span class="modal-close">&times;</span>
    <img class="modal-content" id="modalImage">
</div>

<!-- Reject Prescription Modal -->
<div id="rejectModal" class="modal">
    <div class="modal-content">
        <h2>Reject Prescription</h2>
        <form id="rejectForm" action="../includes/prescriptions/reject_prescription.inc.php" method="POST">
            <input type="hidden" name="prescription_id" id="reject_prescription_id">
            <div class="form-group">
                <label for="rejection_reason">Reason for Rejection</label>
                <textarea name="rejection_reason" id="rejection_reason" rows="4" required></textarea>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" onclick="closeRejectModal()">Cancel</button>
                <button type="submit" class="btn btn-danger">Confirm Rejection</button>
            </div>
        </form>
    </div>
</div>

<script>
    function updateOrderStatus(orderId, status) {
        if (confirm(`Are you sure you want to mark this order as ${status}?`)) {
            window.location.href = `../includes/orders/update_status.inc.php?order_id=${orderId}&status=${status}`;
        }
    }

    function viewPrescription(src) {
        const modal = document.getElementById('prescriptionModal');
        const modalImg = document.getElementById('modalImage');
        modal.style.display = "block";
        modalImg.src = src;
    }

    function showRejectForm(prescriptionId) {
        document.getElementById('reject_prescription_id').value = prescriptionId;
        document.getElementById('rejectModal').style.display = 'block';
    }

    function closeRejectModal() {
        document.getElementById('rejectModal').style.display = 'none';
        document.getElementById('rejectForm').reset();
    }

    // Close modals when clicking outside
    window.onclick = function(event) {
        const prescriptionModal = document.getElementById('prescriptionModal');
        const rejectModal = document.getElementById('rejectModal');

        if (event.target === prescriptionModal) {
            prescriptionModal.style.display = "none";
        }
        if (event.target === rejectModal) {
            rejectModal.style.display = "none";
        }
    }

    // Close modals with × button
    document.querySelectorAll('.modal-close').forEach(button => {
        button.onclick = function() {
            this.closest('.modal').style.display = "none";
        }
    });
</script>

<?php include_once '../includes/footer.php'; ?>