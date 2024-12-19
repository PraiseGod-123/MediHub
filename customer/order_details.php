<?php
session_start();
require_once '../config/config.php';
require_once '../includes/functions.php';

// Ensure user is logged in and is a customer
require_role('customer');

$order_id = isset($_GET['id']) ? filter_var($_GET['id'], FILTER_VALIDATE_INT) : null;

if (!$order_id) {
    $_SESSION['error'] = "Invalid order ID";
    header("Location: orders.php");
    exit();
}

try {
    // Get order details with pharmacy information
    $stmt = $pdo->prepare("
        SELECT o.*, p.business_name as pharmacy_name,
               p.business_phone, p.business_address,
               pr.status as prescription_status,
               pr.image as prescription_image,
               pr.reviewed_at, pr.rejection_reason
        FROM orders o
        JOIN pharmacy_details p ON o.pharmacy_id = p.pharmacy_id
        LEFT JOIN prescriptions pr ON o.order_id = pr.order_id
        WHERE o.order_id = ? AND o.user_id = ?
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
$additional_css = ['customer-order-details'];

include_once '../includes/header.php';
?>

<div class="order-details-container animate-fade-in">
    <!-- Order Header -->
    <div class="order-header card">
        <div class="header-content">
            <div class="order-title">
                <h1>Order #<?php echo $order_id; ?></h1>
                <span class="order-date">
                    <i class="fas fa-calendar"></i>
                    <?php echo date('F j, Y, g:i a', strtotime($order['created_at'])); ?>
                </span>
            </div>
            <div class="status-badge <?php echo $order['status']; ?>">
                <?php echo ucfirst($order['status']); ?>
            </div>
        </div>

        <!-- Order Timeline -->
        <div class="order-timeline">
            <?php
            $statuses = ['pending', 'confirmed', 'processing', 'ready', 'completed'];
            $current_status_index = array_search($order['status'], $statuses);
            ?>
            <?php foreach ($statuses as $index => $status): ?>
                <div class="timeline-item <?php
                                            echo $index <= $current_status_index ? 'completed' : '';
                                            echo $index === $current_status_index ? 'current' : '';
                                            ?>">
                    <div class="timeline-icon">
                        <i class="fas fa-<?php
                                            switch ($status) {
                                                case 'pending':
                                                    echo 'clock';
                                                    break;
                                                case 'confirmed':
                                                    echo 'check';
                                                    break;
                                                case 'processing':
                                                    echo 'cog';
                                                    break;
                                                case 'ready':
                                                    echo 'box';
                                                    break;
                                                case 'completed':
                                                    echo 'flag-checkered';
                                                    break;
                                            }
                                            ?>"></i>
                    </div>
                    <div class="timeline-content">
                        <h3><?php echo ucfirst($status); ?></h3>
                    </div>
                </div>
                <?php if ($index < count($statuses) - 1): ?>
                    <div class="timeline-connector <?php echo $index < $current_status_index ? 'completed' : ''; ?>"></div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>

        <?php if ($order['status'] === 'pending'): ?>
            <div class="order-actions">
                <button class="btn btn-danger" onclick="confirmCancelOrder(<?php echo $order_id; ?>)">
                    <i class="fas fa-times"></i> Cancel Order
                </button>
            </div>
        <?php endif; ?>
    </div>

    <div class="order-content">
        <!-- Pharmacy Information -->
        <div class="pharmacy-info card">
            <h2>Pharmacy Information</h2>
            <div class="info-grid">
                <div class="info-item">
                    <span class="label">Name:</span>
                    <span class="value"><?php echo htmlspecialchars($order['pharmacy_name']); ?></span>
                </div>
                <div class="info-item">
                    <span class="label">Phone:</span>
                    <span class="value"><?php echo htmlspecialchars($order['business_phone']); ?></span>
                </div>
                <div class="info-item full-width">
                    <span class="label">Address:</span>
                    <span class="value"><?php echo htmlspecialchars($order['business_address']); ?></span>
                </div>
            </div>
        </div>

        <!-- Prescription Section (if applicable) -->
        <?php if ($order['prescription_status']): ?>
            <div class="prescription-details card">
                <h2>Prescription Details</h2>
                <div class="prescription-content">
                    <div class="prescription-image">
                        <img src="<?php echo BASE_URL; ?>/assets/images/prescriptions/<?php echo htmlspecialchars($order['prescription_image']); ?>"
                            alt="Prescription"
                            onclick="openImageModal(this.src)">
                    </div>
                    <div class="prescription-info">
                        <div class="status-badge <?php echo $order['prescription_status']; ?>">
                            <?php echo ucfirst($order['prescription_status']); ?>
                        </div>
                        <?php if ($order['reviewed_at']): ?>
                            <p class="review-date">
                                Reviewed on <?php echo date('F j, Y, g:i a', strtotime($order['reviewed_at'])); ?>
                            </p>
                        <?php endif; ?>
                        <?php if ($order['rejection_reason']): ?>
                            <div class="rejection-reason">
                                <h3>Rejection Reason:</h3>
                                <p><?php echo htmlspecialchars($order['rejection_reason']); ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Order Items -->
        <div class="order-items card">
            <h2>Order Items</h2>
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
                                <span class="prescription-required">
                                    <i class="fas fa-file-medical"></i> Prescription Required
                                </span>
                            <?php endif; ?>
                            <div class="item-meta">
                                <span class="quantity">Quantity: <?php echo $item['quantity']; ?></span>
                                <span class="price"><?php echo format_currency($item['price_per_unit']); ?> each</span>
                                <span class="subtotal">
                                    Subtotal: <?php echo format_currency($item['quantity'] * $item['price_per_unit']); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Order Summary -->
        <div class="order-summary card">
            <h2>Order Summary</h2>
            <div class="summary-details">
                <div class="summary-row">
                    <span class="label">Subtotal:</span>
                    <span class="value"><?php echo format_currency($order['total_amount']); ?></span>
                </div>
                <div class="summary-row">
                    <span class="label">Delivery Fee:</span>
                    <span class="value">₦0.00</span>
                </div>
                <div class="summary-row total">
                    <span class="label">Total Amount:</span>
                    <span class="value"><?php echo format_currency($order['total_amount']); ?></span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Image Modal -->
<div id="imageModal" class="modal">
    <span class="modal-close">&times;</span>
    <img class="modal-content" id="modalImage">
</div>

<script>
    function confirmCancelOrder(orderId) {
        if (confirm('Are you sure you want to cancel this order?')) {
            window.location.href = '../includes/orders/cancel_order.inc.php?order_id=' + orderId;
        }
    }

    function openImageModal(src) {
        const modal = document.getElementById('imageModal');
        const modalImg = document.getElementById('modalImage');
        modal.style.display = "block";
        modalImg.src = src;
    }

    // Close modal
    document.querySelector('.modal-close').onclick = function() {
        document.getElementById('imageModal').style.display = "none";
    }

    window.onclick = function(event) {
        const modal = document.getElementById('imageModal');
        if (event.target == modal) {
            modal.style.display = "none";
        }
    }
</script>

<?php include_once '../includes/footer.php'; ?>