<?php
session_start();
require_once '../config/config.php';
require_once '../includes/functions.php';

// Ensure user is logged in and is a customer
require_role('customer');

// Get user's orders
try {
    $stmt = $pdo->prepare("
        SELECT o.*, p.business_name as pharmacy_name
        FROM orders o
        LEFT JOIN pharmacy_details p ON o.pharmacy_id = p.pharmacy_id
        WHERE o.user_id = ?
        ORDER BY o.created_at DESC
        LIMIT 5
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $recent_orders = $stmt->fetchAll();

    // Get order counts by status
    $stmt = $pdo->prepare("
        SELECT status, COUNT(*) as count
        FROM orders
        WHERE user_id = ?
        GROUP BY status
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $order_stats = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
} catch (PDOException $e) {
    error_log($e->getMessage());
    $error_message = "An error occurred loading your dashboard.";
}

// Additional CSS for this page
$additional_css = ['customer-dashboard'];

include_once '../includes/header.php';
?>

<div class="dashboard-container animate-fade-in">
    <!-- Welcome Section -->
    <section class="welcome-section card mb-4">
        <div class="welcome-content">
            <h1>Welcome back, <?php echo htmlspecialchars($_SESSION['name']); ?>!</h1>
            <p class="text-muted">Here's an overview of your recent activity</p>
        </div>
        <div class="quick-actions">
            <a href="medicines.php" class="btn btn-primary">
                <i class="fas fa-pills"></i> Browse Medicines
            </a>
            <a href="orders.php" class="btn btn-secondary">
                <i class="fas fa-clipboard-list"></i> View All Orders
            </a>
        </div>
    </section>

    <!-- Stats Grid -->
    <div class="stats-grid mb-4">
        <div class="stat-card animate-slide-up" style="animation-delay: 0.1s">
            <div class="stat-icon pending">
                <i class="fas fa-clock"></i>
            </div>
            <div class="stat-details">
                <h3><?php echo $order_stats['pending'] ?? 0; ?></h3>
                <p>Pending Orders</p>
            </div>
        </div>

        <div class="stat-card animate-slide-up" style="animation-delay: 0.2s">
            <div class="stat-icon processing">
                <i class="fas fa-spinner"></i>
            </div>
            <div class="stat-details">
                <h3><?php echo $order_stats['processing'] ?? 0; ?></h3>
                <p>Processing</p>
            </div>
        </div>

        <div class="stat-card animate-slide-up" style="animation-delay: 0.3s">
            <div class="stat-icon completed">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="stat-details">
                <h3><?php echo $order_stats['completed'] ?? 0; ?></h3>
                <p>Completed Orders</p>
            </div>
        </div>

        <div class="stat-card animate-slide-up" style="animation-delay: 0.4s">
            <div class="stat-icon cancelled">
                <i class="fas fa-times-circle"></i>
            </div>
            <div class="stat-details">
                <h3><?php echo $order_stats['cancelled'] ?? 0; ?></h3>
                <p>Cancelled Orders</p>
            </div>
        </div>
    </div>

    <!-- Recent Orders -->
    <section class="recent-orders card animate-slide-up" style="animation-delay: 0.5s">
        <div class="section-header">
            <h2>Recent Orders</h2>
            <a href="orders.php" class="btn btn-secondary btn-sm">View All</a>
        </div>

        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Order ID</th>
                        <th>Pharmacy</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($recent_orders)): ?>
                        <?php foreach ($recent_orders as $order): ?>
                            <tr>
                                <td>#<?php echo $order['order_id']; ?></td>
                                <td><?php echo htmlspecialchars($order['pharmacy_name']); ?></td>
                                <td><?php echo format_currency($order['total_amount']); ?></td>
                                <td>
                                    <span class="status-badge <?php echo $order['status']; ?>">
                                        <?php echo ucfirst($order['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo date('M j, Y', strtotime($order['created_at'])); ?></td>
                                <td>
                                    <a href="order_details.php?id=<?php echo $order['order_id']; ?>" class="btn btn-primary btn-sm">
                                        View Details
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center">No orders found</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>

<?php include_once '../includes/footer.php'; ?>