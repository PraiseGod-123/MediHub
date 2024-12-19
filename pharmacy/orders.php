<?php
session_start();
require_once '../config/config.php';
require_once '../includes/functions.php';

// Ensure user is logged in and is a pharmacy
require_role('pharmacy');

// Get filter parameters
$status = isset($_GET['status']) ? sanitize_input($_GET['status']) : '';
$search = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';
$date_range = isset($_GET['date_range']) ? sanitize_input($_GET['date_range']) : '';

// Build query
$query = "
    SELECT o.*, 
           u.first_name, u.last_name, u.email, u.phone,
           (SELECT COUNT(*) FROM order_items WHERE order_id = o.order_id) as item_count,
           p.status as prescription_status,
           p.prescription_id
    FROM orders o
    JOIN users u ON o.user_id = u.user_id
    LEFT JOIN prescriptions p ON o.order_id = p.order_id
    WHERE o.pharmacy_id = ?
";

$params = [$_SESSION['user_id']];

if ($status) {
    $query .= " AND o.status = ?";
    $params[] = $status;
}

if ($search) {
    $query .= " AND (o.order_id LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?)";
    $search_term = "%$search%";
    array_push($params, $search_term, $search_term, $search_term, $search_term);
}

if ($date_range) {
    switch ($date_range) {
        case 'today':
            $query .= " AND DATE(o.created_at) = CURDATE()";
            break;
        case 'week':
            $query .= " AND o.created_at >= DATE_SUB(NOW(), INTERVAL 1 WEEK)";
            break;
        case 'month':
            $query .= " AND o.created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
            break;
    }
}

$query .= " ORDER BY o.created_at DESC";

try {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $orders = $stmt->fetchAll();

    // Get statistics
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_orders,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_orders,
            SUM(CASE WHEN status = 'processing' THEN 1 ELSE 0 END) as processing_orders,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_orders,
            SUM(total_amount) as total_revenue
        FROM orders 
        WHERE pharmacy_id = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $stats = $stmt->fetch();
} catch (PDOException $e) {
    error_log($e->getMessage());
    $_SESSION['error'] = "Error retrieving orders";
    $orders = [];
    $stats = [
        'total_orders' => 0,
        'pending_orders' => 0,
        'processing_orders' => 0,
        'completed_orders' => 0,
        'total_revenue' => 0
    ];
}

// Additional CSS for this page
$additional_css = ['pharmacy-orders'];

include_once '../includes/header.php';
?>

<div class="orders-container animate-fade-in">
    <div class="orders-header">
        <div class="header-content">
            <h1>
                <i class="fas fa-shopping-bag"></i>
                Manage Orders
            </h1>
            <?php if ($stats['pending_orders'] > 0): ?>
                <div class="pending-alert animate-pulse">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo $stats['pending_orders']; ?> orders need attention
                </div>
            <?php endif; ?>
        </div>

        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card total animate-slide-up">
                <div class="stat-icon">
                    <i class="fas fa-shopping-bag"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo $stats['total_orders']; ?></div>
                    <div class="stat-label">Total Orders</div>
                </div>
            </div>

            <div class="stat-card pending animate-slide-up">
                <div class="stat-icon">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo $stats['pending_orders']; ?></div>
                    <div class="stat-label">Pending Orders</div>
                </div>
            </div>

            <div class="stat-card processing animate-slide-up">
                <div class="stat-icon">
                    <i class="fas fa-cog"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo $stats['processing_orders']; ?></div>
                    <div class="stat-label">Processing</div>
                </div>
            </div>

            <div class="stat-card completed animate-slide-up">
                <div class="stat-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo format_currency($stats['total_revenue']); ?></div>
                    <div class="stat-label">Total Revenue</div>
                </div>
            </div>
        </div>

        <!-- Filters Section -->
        <div class="filters-section">
            <form action="" method="GET" class="filters-form">
                <div class="form-group">
                    <label>Search Orders</label>
                    <div class="search-input">
                        <i class="fas fa-search"></i>
                        <input type="text" name="search" class="form-control"
                            value="<?php echo htmlspecialchars($search); ?>"
                            placeholder="Search by order ID, customer name...">
                    </div>
                </div>

                <div class="form-group">
                    <label>Order Status</label>
                    <select name="status" class="form-control">
                        <option value="">All Orders</option>
                        <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="processing" <?php echo $status === 'processing' ? 'selected' : ''; ?>>Processing</option>
                        <option value="completed" <?php echo $status === 'completed' ? 'selected' : ''; ?>>Completed</option>
                        <option value="cancelled" <?php echo $status === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Time Period</label>
                    <select name="date_range" class="form-control">
                        <option value="">All Time</option>
                        <option value="today" <?php echo $date_range === 'today' ? 'selected' : ''; ?>>Today</option>
                        <option value="week" <?php echo $date_range === 'week' ? 'selected' : ''; ?>>Past Week</option>
                        <option value="month" <?php echo $date_range === 'month' ? 'selected' : ''; ?>>Past Month</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>&nbsp;</label>
                    <div>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-filter"></i> Apply Filters
                        </button>
                        <a href="orders.php" class="btn btn-secondary">
                            <i class="fas fa-redo"></i> Reset
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Orders List -->
    <div class="orders-list">
        <?php if (!empty($orders)): ?>
            <?php foreach ($orders as $order): ?>
                <div class="order-card animate-slide-up">
                    <div class="order-header">
                        <div class="order-info">
                            <h3>Order #<?php echo $order['order_id']; ?></h3>
                            <span class="order-date">
                                <i class="fas fa-calendar"></i>
                                <?php echo date('M j, Y, g:i a', strtotime($order['created_at'])); ?>
                            </span>
                        </div>
                        <div class="status-badge <?php echo $order['status']; ?>">
                            <?php echo ucfirst($order['status']); ?>
                        </div>
                    </div>

                    <div class="order-details">
                        <div class="detail-row">
                            <span class="detail-label">
                                <i class="fas fa-user"></i> Customer
                            </span>
                            <span class="detail-value">
                                <?php echo htmlspecialchars($order['first_name'] . ' ' . $order['last_name']); ?>
                            </span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">
                                <i class="fas fa-shopping-cart"></i> Items
                            </span>
                            <span class="detail-value"><?php echo $order['item_count']; ?> items</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">
                                <i class="fas fa-money-bill"></i> Total
                            </span>
                            <span class="detail-value"><?php echo format_currency($order['total_amount']); ?></span>
                        </div>
                        <?php if ($order['prescription_status']): ?>
                            <div class="detail-row">
                                <span class="detail-label">
                                    <i class="fas fa-file-medical"></i> Prescription
                                </span>
                                <span class="detail-value">
                                    Status: <?php echo ucfirst($order['prescription_status']); ?>
                                </span>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="order-actions">
                        <a href="order_details.php?id=<?php echo $order['order_id']; ?>" class="btn btn-primary">
                            <i class="fas fa-eye"></i> View Details
                        </a>
                        <?php if ($order['status'] === 'pending' && $order['prescription_status']): ?>
                            <a href="prescription_review.php?id=<?php echo $order['prescription_id']; ?>" class="btn btn-warning">
                                <i class="fas fa-file-medical"></i> Review Prescription
                            </a>
                        <?php endif; ?>
                        <?php if ($order['status'] === 'pending'): ?>
                            <button onclick="updateOrderStatus(<?php echo $order['order_id']; ?>, 'processing')" class="btn btn-success">
                                <i class="fas fa-check"></i> Accept Order
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-shopping-bag"></i>
                <h2>No Orders Found</h2>
                <p>There are no orders matching your current filters.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
    function updateOrderStatus(orderId, status) {
        if (confirm('Are you sure you want to update this order status?')) {
            window.location.href = `../includes/orders/update_status.inc.php?order_id=${orderId}&status=${status}`;
        }
    }
</script>

<?php include_once '../includes/footer.php'; ?>