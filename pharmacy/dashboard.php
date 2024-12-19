<?php
session_start();
require_once '../config/config.php';
require_once '../includes/functions.php';

// Ensure user is logged in and is a pharmacy
require_role('pharmacy');

try {
    // Get pharmacy details
    $stmt = $pdo->prepare("
        SELECT pd.*, u.email, u.status as account_status
        FROM pharmacy_details pd
        JOIN users u ON pd.pharmacy_id = u.user_id
        WHERE pd.pharmacy_id = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $pharmacy = $stmt->fetch();

    // Get today's statistics
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(DISTINCT o.order_id) as total_orders,
            COUNT(DISTINCT CASE WHEN o.status = 'pending' THEN o.order_id END) as pending_orders,
            COUNT(DISTINCT CASE WHEN o.status = 'processing' THEN o.order_id END) as processing_orders,
            COUNT(DISTINCT CASE WHEN o.status = 'completed' THEN o.order_id END) as completed_orders,
            COUNT(DISTINCT CASE WHEN o.status = 'cancelled' THEN o.order_id END) as cancelled_orders,
            SUM(o.total_amount) as total_revenue,
            COUNT(DISTINCT CASE WHEN o.created_at >= NOW() - INTERVAL 24 HOUR THEN o.order_id END) as orders_24h,
            COUNT(DISTINCT CASE WHEN p.status = 'pending' THEN p.prescription_id END) as pending_prescriptions
        FROM orders o
        LEFT JOIN prescriptions p ON o.order_id = p.order_id
        WHERE o.pharmacy_id = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $stats = $stmt->fetch();

    // Get recent orders
    $stmt = $pdo->prepare("
        SELECT o.*, 
               u.first_name, u.last_name,
               COUNT(oi.order_item_id) as item_count,
               p.status as prescription_status
        FROM orders o
        JOIN users u ON o.user_id = u.user_id
        LEFT JOIN order_items oi ON o.order_id = oi.order_id
        LEFT JOIN prescriptions p ON o.order_id = p.order_id
        WHERE o.pharmacy_id = ?
        GROUP BY o.order_id
        ORDER BY o.created_at DESC
        LIMIT 5
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $recent_orders = $stmt->fetchAll();

    // Get low stock alerts
    $stmt = $pdo->prepare("
        SELECT m.*, c.name as category_name
        FROM medicines m
        LEFT JOIN categories c ON m.category_id = c.category_id
        WHERE m.pharmacy_id = ? AND m.stock_quantity <= 10
        ORDER BY m.stock_quantity ASC
        LIMIT 5
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $low_stock = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log($e->getMessage());
    $_SESSION['error'] = "Error loading dashboard data";
}

// Additional CSS for this page
$additional_css = ['pharmacy-dashboard'];

include_once '../includes/header.php';
?>

<div class="dashboard-container animate-fade-in">
    <!-- Welcome Banner -->
    <div class="welcome-banner card">
        <div class="welcome-content">
            <div class="status-indicator <?php echo $pharmacy['account_status']; ?>">
                <i class="fas fa-circle"></i>
                <?php echo ucfirst($pharmacy['account_status']); ?>
            </div>
            <h1>Welcome, <?php echo htmlspecialchars($pharmacy['business_name']); ?>!</h1>
            <p class="license">License: <?php echo htmlspecialchars($pharmacy['license_number']); ?></p>
        </div>
        <div class="quick-actions">
            <button class="btn btn-secondary pulse" onclick="location.href='inventory.php'">
                <i class="fas fa-box-open"></i>
                Manage Inventory
            </button>
            <button class="btn btn-primary" onclick="location.href='orders.php'">
                <i class="fas fa-clipboard-list"></i>
                View All Orders
            </button>
        </div>
    </div>

    <!-- Stats Grid -->
    <div class="stats-grid">
        <div class="stat-card orders animate-slide-up" style="--delay: 0.1s">
            <div class="stat-icon">
                <i class="fas fa-shopping-bag"></i>
            </div>
            <div class="stat-content">
                <div class="stat-value"><?php echo $stats['total_orders']; ?></div>
                <div class="stat-label">Total Orders</div>
                <div class="stat-trend positive">
                    <i class="fas fa-arrow-up"></i>
                    <?php echo $stats['orders_24h']; ?> new in 24h
                </div>
            </div>
        </div>

        <div class="stat-card revenue animate-slide-up" style="--delay: 0.2s">
            <div class="stat-icon">
                <i class="fas fa-chart-line"></i>
            </div>
            <div class="stat-content">
                <div class="stat-value"><?php echo format_currency($stats['total_revenue']); ?></div>
                <div class="stat-label">Total Revenue</div>
                <div class="stat-trend positive">
                    <i class="fas fa-chart-bar"></i>
                    View Analytics
                </div>
            </div>
        </div>

        <div class="stat-card prescriptions animate-slide-up" style="--delay: 0.3s">
            <div class="stat-icon">
                <i class="fas fa-file-medical"></i>
            </div>
            <div class="stat-content">
                <div class="stat-value"><?php echo $stats['pending_prescriptions']; ?></div>
                <div class="stat-label">Pending Prescriptions</div>
                <div class="stat-trend urgent">
                    <i class="fas fa-exclamation-circle"></i>
                    Needs Review
                </div>
            </div>
        </div>

        <div class="stat-card stock animate-slide-up" style="--delay: 0.4s">
            <div class="stat-icon">
                <i class="fas fa-boxes"></i>
            </div>
            <div class="stat-content">
                <div class="stat-value"><?php echo count($low_stock); ?></div>
                <div class="stat-label">Low Stock Alerts</div>
                <div class="stat-trend warning">
                    <i class="fas fa-exclamation-triangle"></i>
                    Needs Attention
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content Grid -->
    <div class="dashboard-grid">
        <!-- Recent Orders -->
        <div class="dashboard-section card orders-section animate-slide-up">
            <div class="section-header">
                <h2>Recent Orders</h2>
                <a href="orders.php" class="btn btn-text">View All <i class="fas fa-arrow-right"></i></a>
            </div>

            <?php if (!empty($recent_orders)): ?>
                <div class="orders-list">
                    <?php foreach ($recent_orders as $order): ?>
                        <div class="order-item">
                            <div class="order-info">
                                <div class="order-header">
                                    <h3>Order #<?php echo $order['order_id']; ?></h3>
                                    <span class="status-badge <?php echo $order['status']; ?>">
                                        <?php echo ucfirst($order['status']); ?>
                                    </span>
                                </div>
                                <div class="order-details">
                                    <span class="customer-name">
                                        <i class="fas fa-user"></i>
                                        <?php echo htmlspecialchars($order['first_name'] . ' ' . $order['last_name']); ?>
                                    </span>
                                    <span class="order-date">
                                        <i class="fas fa-calendar"></i>
                                        <?php echo date('M j, Y, g:i a', strtotime($order['created_at'])); ?>
                                    </span>
                                    <span class="order-items">
                                        <i class="fas fa-shopping-basket"></i>
                                        <?php echo $order['item_count']; ?> items
                                    </span>
                                    <span class="order-total">
                                        <i class="fas fa-money-bill-wave"></i>
                                        <?php echo format_currency($order['total_amount']); ?>
                                    </span>
                                </div>
                            </div>
                            <div class="order-actions">
                                <?php if ($order['prescription_status'] === 'pending'): ?>
                                    <button class="btn btn-outline review-prescription"
                                        onclick="location.href='review_prescription.php?order_id=<?php echo $order['order_id']; ?>'">
                                        <i class="fas fa-file-medical"></i>
                                        Review Prescription
                                    </button>
                                <?php endif; ?>
                                <button class="btn btn-primary view-details"
                                    onclick="location.href='order_details.php?id=<?php echo $order['order_id']; ?>'">
                                    <i class="fas fa-eye"></i>
                                    View Details
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-clipboard-list"></i>
                    <p>No orders yet</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Right Sidebar -->
        <div class="dashboard-sidebar">
            <!-- Order Status Summary -->
            <div class="dashboard-section card status-summary animate-slide-up">
                <h2>Order Status</h2>
                <div class="status-chart">
                    <div class="status-bar">
                        <div class="status-segment pending" style="width: <?php
                                                                            echo ($stats['total_orders'] > 0 ? ($stats['pending_orders'] / $stats['total_orders'] * 100) : 0);
                                                                            ?>%">
                            <span class="status-label">Pending (<?php echo $stats['pending_orders']; ?>)</span>
                        </div>
                        <div class="status-segment processing" style="width: <?php
                                                                                echo ($stats['total_orders'] > 0 ? ($stats['processing_orders'] / $stats['total_orders'] * 100) : 0);
                                                                                ?>%">
                            <span class="status-label">Processing (<?php echo $stats['processing_orders']; ?>)</span>
                        </div>
                        <div class="status-segment completed" style="width: <?php
                                                                            echo ($stats['total_orders'] > 0 ? ($stats['completed_orders'] / $stats['total_orders'] * 100) : 0);
                                                                            ?>%">
                            <span class="status-label">Completed (<?php echo $stats['completed_orders']; ?>)</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Low Stock Alerts -->
            <div class="dashboard-section card stock-alerts animate-slide-up">
                <div class="section-header">
                    <h2>Low Stock Alerts</h2>
                    <a href="inventory.php?filter=low_stock" class="btn btn-text">Manage Stock <i class="fas fa-arrow-right"></i></a>
                </div>

                <?php if (!empty($low_stock)): ?>
                    <div class="stock-list">
                        <?php foreach ($low_stock as $item): ?>
                            <div class="stock-item">
                                <div class="stock-info">
                                    <div class="stock-name">
                                        <h4><?php echo htmlspecialchars($item['name']); ?></h4>
                                        <span class="category-tag">
                                            <?php echo htmlspecialchars($item['category_name']); ?>
                                        </span>
                                    </div>
                                    <div class="stock-quantity <?php echo $item['stock_quantity'] <= 5 ? 'critical' : 'warning'; ?>">
                                        <?php echo $item['stock_quantity']; ?> left
                                    </div>
                                </div>
                                <button class="btn btn-outline update-stock"
                                    onclick="location.href='update_stock.php?id=<?php echo $item['medicine_id']; ?>'">
                                    <i class="fas fa-plus"></i>
                                    Update Stock
                                </button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-check-circle"></i>
                        <p>All stock levels are healthy</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include_once '../includes/footer.php'; ?>