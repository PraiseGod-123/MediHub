<?php
session_start();
require_once '../config/config.php';
require_once '../includes/functions.php';

// Ensure user is logged in and is a customer
require_role('customer');

// Get filter parameters
$status = isset($_GET['status']) ? sanitize_input($_GET['status']) : '';
$date_range = isset($_GET['date_range']) ? sanitize_input($_GET['date_range']) : '';

// Build query
$query = "
    SELECT o.*, 
           p.business_name as pharmacy_name,
           (SELECT COUNT(*) FROM order_items WHERE order_id = o.order_id) as item_count
    FROM orders o
    LEFT JOIN pharmacy_details p ON o.pharmacy_id = p.pharmacy_id
    WHERE o.user_id = ?
";

$params = [$_SESSION['user_id']];

if ($status) {
    $query .= " AND o.status = ?";
    $params[] = $status;
}

if ($date_range) {
    switch ($date_range) {
        case '7days':
            $query .= " AND o.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
            break;
        case '30days':
            $query .= " AND o.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
            break;
        case '6months':
            $query .= " AND o.created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)";
            break;
    }
}

$query .= " ORDER BY o.created_at DESC";

try {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $orders = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log($e->getMessage());
    $orders = [];
}

// Additional CSS for this page
$additional_css = ['customer-orders'];

include_once '../includes/header.php';
?>

<div class="orders-container animate-fade-in">
    <div class="orders-header">
        <h1>My Orders</h1>

        <!-- Filters -->
        <div class="filters-section card">
            <form action="" method="GET" class="filters-form">
                <div class="form-group">
                    <label for="status">Order Status</label>
                    <select name="status" id="status" class="form-control">
                        <option value="">All Orders</option>
                        <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="confirmed" <?php echo $status === 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                        <option value="processing" <?php echo $status === 'processing' ? 'selected' : ''; ?>>Processing</option>
                        <option value="ready" <?php echo $status === 'ready' ? 'selected' : ''; ?>>Ready</option>
                        <option value="completed" <?php echo $status === 'completed' ? 'selected' : ''; ?>>Completed</option>
                        <option value="cancelled" <?php echo $status === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="date_range">Time Period</label>
                    <select name="date_range" id="date_range" class="form-control">
                        <option value="">All Time</option>
                        <option value="7days" <?php echo $date_range === '7days' ? 'selected' : ''; ?>>Last 7 Days</option>
                        <option value="30days" <?php echo $date_range === '30days' ? 'selected' : ''; ?>>Last 30 Days</option>
                        <option value="6months" <?php echo $date_range === '6months' ? 'selected' : ''; ?>>Last 6 Months</option>
                    </select>
                </div>

                <button type="submit" class="btn btn-primary">Apply Filters</button>
                <a href="orders.php" class="btn btn-secondary">Clear Filters</a>
            </form>
        </div>
    </div>

    <!-- Orders List -->
    <div class="orders-list">
        <?php if (!empty($orders)): ?>
            <?php foreach ($orders as $order): ?>
                <div class="order-card card animate-slide-up">
                    <div class="order-header">
                        <div class="order-info">
                            <h3>Order #<?php echo $order['order_id']; ?></h3>
                            <span class="order-date">
                                <i class="fas fa-calendar"></i>
                                <?php echo date('M j, Y, g:i a', strtotime($order['created_at'])); ?>
                            </span>
                        </div>
                        <span class="status-badge <?php echo $order['status']; ?>">
                            <?php echo ucfirst($order['status']); ?>
                        </span>
                    </div>

                    <div class="order-details">
                        <div class="detail-row">
                            <span class="detail-label">
                                <i class="fas fa-store"></i> Pharmacy
                            </span>
                            <span class="detail-value"><?php echo htmlspecialchars($order['pharmacy_name']); ?></span>
                        </div>

                        <div class="detail-row">
                            <span class="detail-label">
                                <i class="fas fa-shopping-basket"></i> Items
                            </span>
                            <span class="detail-value"><?php echo $order['item_count']; ?> items</span>
                        </div>

                        <div class="detail-row">
                            <span class="detail-label">
                                <i class="fas fa-money-bill-wave"></i> Total Amount
                            </span>
                            <span class="detail-value total-amount"><?php echo format_currency($order['total_amount']); ?></span>
                        </div>
                    </div>

                    <div class="order-timeline">
                        <?php
                        $statuses = ['pending', 'confirmed', 'processing', 'ready', 'completed'];
                        $current_status_index = array_search($order['status'], $statuses);
                        ?>
                        <div class="timeline-wrapper">
                            <?php foreach ($statuses as $index => $timeline_status): ?>
                                <div class="timeline-item <?php
                                                            echo $index <= $current_status_index ? 'completed' : '';
                                                            echo $index === $current_status_index ? 'current' : '';
                                                            ?>">
                                    <div class="timeline-icon">
                                        <i class="fas fa-<?php
                                                            switch ($timeline_status) {
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
                                    <div class="timeline-label"><?php echo ucfirst($timeline_status); ?></div>
                                </div>
                                <?php if ($index < count($statuses) - 1): ?>
                                    <div class="timeline-connector <?php
                                                                    echo $index < $current_status_index ? 'completed' : '';
                                                                    ?>"></div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="order-footer">
                        <a href="order_details.php?id=<?php echo $order['order_id']; ?>" class="btn btn-primary">
                            <i class="fas fa-eye"></i> View Details
                        </a>
                        <?php if ($order['status'] === 'pending'): ?>
                            <form action="../includes/orders/cancel_order.inc.php" method="POST" class="cancel-form"
                                onsubmit="return confirm('Are you sure you want to cancel this order?');">
                                <input type="hidden" name="order_id" value="<?php echo $order['order_id']; ?>">
                                <button type="submit" class="btn btn-danger">
                                    <i class="fas fa-times"></i> Cancel Order
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="no-orders card animate-slide-up">
                <i class="fas fa-shopping-bag"></i>
                <h2>No orders found</h2>
                <p>You haven't placed any orders yet</p>
                <a href="medicines.php" class="btn btn-primary">
                    <i class="fas fa-pills"></i> Browse Medicines
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include_once '../includes/footer.php'; ?>