<?php
session_start();
require_once '../config/config.php';
require_once '../includes/functions.php';

// Ensure user is logged in and is a customer
require_role('customer');

try {
    // Get cart items with full details
    $stmt = $pdo->prepare("
        SELECT ci.*, m.name, m.price, m.image, m.requires_prescription,
               p.business_name as pharmacy_name, p.pharmacy_id
        FROM cart_items ci
        JOIN medicines m ON ci.medicine_id = m.medicine_id
        JOIN pharmacy_details p ON m.pharmacy_id = p.pharmacy_id
        WHERE ci.user_id = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $cart_items = $stmt->fetchAll();

    // Group items by pharmacy
    $grouped_items = [];
    $total = 0;
    $has_prescription_items = false;

    foreach ($cart_items as $item) {
        if (!isset($grouped_items[$item['pharmacy_id']])) {
            $grouped_items[$item['pharmacy_id']] = [
                'pharmacy_name' => $item['pharmacy_name'],
                'items' => [],
                'subtotal' => 0,
                'requires_prescription' => false
            ];
        }
        $item['subtotal'] = $item['price'] * $item['quantity'];
        $grouped_items[$item['pharmacy_id']]['items'][] = $item;
        $grouped_items[$item['pharmacy_id']]['subtotal'] += $item['subtotal'];
        $total += $item['subtotal'];

        if ($item['requires_prescription']) {
            $grouped_items[$item['pharmacy_id']]['requires_prescription'] = true;
            $has_prescription_items = true;
        }
    }

    // Get user details for the form
    $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
} catch (PDOException $e) {
    error_log($e->getMessage());
    $_SESSION['error'] = "Error loading checkout information";
    header("Location: cart.php");
    exit();
}

// Additional CSS for this page
$additional_css = ['customer-checkout'];

include_once '../includes/header.php';
?>

<div class="checkout-container animate-fade-in">
    <?php if (!empty($grouped_items)): ?>
        <div class="checkout-content">
            <!-- Order Summary -->
            <div class="order-summary card animate-slide-up">
                <h2>Order Summary</h2>

                <?php foreach ($grouped_items as $pharmacy_id => $group): ?>
                    <div class="pharmacy-group">
                        <div class="pharmacy-header">
                            <h3>
                                <i class="fas fa-store"></i>
                                <?php echo htmlspecialchars($group['pharmacy_name']); ?>
                            </h3>
                            <?php if ($group['requires_prescription']): ?>
                                <span class="prescription-badge">
                                    <i class="fas fa-file-medical"></i>
                                    Prescription Required
                                </span>
                            <?php endif; ?>
                        </div>

                        <div class="items-list">
                            <?php foreach ($group['items'] as $item): ?>
                                <div class="checkout-item">
                                    <div class="item-image">
                                        <?php if ($item['image']): ?>
                                            <img src="<?php echo BASE_URL; ?>/assets/images/products/<?php echo htmlspecialchars($item['image']); ?>"
                                                alt="<?php echo htmlspecialchars($item['name']); ?>">
                                        <?php else: ?>
                                            <div class="placeholder-image">
                                                <i class="fas fa-pills"></i>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="item-details">
                                        <h4><?php echo htmlspecialchars($item['name']); ?></h4>
                                        <div class="item-meta">
                                            <span class="quantity">Quantity: <?php echo $item['quantity']; ?></span>
                                            <span class="price"><?php echo format_currency($item['price']); ?> each</span>
                                        </div>
                                        <div class="item-subtotal">
                                            <?php echo format_currency($item['subtotal']); ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="pharmacy-subtotal">
                            <span>Subtotal:</span>
                            <span><?php echo format_currency($group['subtotal']); ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>

                <div class="order-total">
                    <span>Total Amount:</span>
                    <span class="total-amount"><?php echo format_currency($total); ?></span>
                </div>
            </div>

            <!-- Checkout Form -->
            <div class="checkout-form card animate-slide-up">
                <h2>Delivery Information</h2>

                <form action="../includes/orders/place_order.inc.php" method="POST" id="checkoutForm">
                    <div class="form-group">
                        <label for="name">Full Name *</label>
                        <input type="text" id="name" name="name" required
                            value="<?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>">
                    </div>

                    <div class="form-group">
                        <label for="phone">Phone Number *</label>
                        <input type="tel" id="phone" name="phone" required
                            value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                    </div>

                    <div class="form-group">
                        <label for="email">Email *</label>
                        <input type="email" id="email" name="email" required
                            value="<?php echo htmlspecialchars($user['email']); ?>">
                    </div>

                    <div class="form-group">
                        <label for="address">Delivery Address *</label>
                        <textarea id="address" name="address" rows="3" required><?php echo htmlspecialchars($user['address'] ?? ''); ?></textarea>
                    </div>

                    <div class="form-group">
                        <label for="notes">Order Notes</label>
                        <textarea id="notes" name="notes" rows="2"
                            placeholder="Any special instructions for your order..."></textarea>
                    </div>

                    <?php if ($has_prescription_items): ?>
                        <div class="prescription-notice">
                            <i class="fas fa-exclamation-circle"></i>
                            <p>Some items in your order require a prescription. You will be prompted to upload prescriptions after placing the order.</p>
                        </div>
                    <?php endif; ?>

                    <div class="checkout-actions">
                        <a href="cart.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back to Cart
                        </a>
                        <button type="submit" class="btn btn-primary" name="place_order">
                            Place Order <i class="fas fa-arrow-right"></i>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    <?php else: ?>
        <div class="empty-cart animate-slide-up">
            <i class="fas fa-shopping-cart"></i>
            <h2>Your cart is empty</h2>
            <p>Add some medicines to your cart before checking out</p>
            <a href="medicines.php" class="btn btn-primary">
                <i class="fas fa-pills"></i> Browse Medicines
            </a>
        </div>
    <?php endif; ?>
</div>

<script>
    document.getElementById('checkoutForm')?.addEventListener('submit', function(e) {
        const requiredFields = ['name', 'phone', 'email', 'address'];
        let isValid = true;

        requiredFields.forEach(field => {
            const input = document.getElementById(field);
            if (!input.value.trim()) {
                isValid = false;
                input.classList.add('error');
            } else {
                input.classList.remove('error');
            }
        });

        if (!isValid) {
            e.preventDefault();
            alert('Please fill in all required fields');
        }
    });
</script>

<?php include_once '../includes/footer.php'; ?>