<?php
session_start();
require_once '../config/config.php';
require_once '../includes/functions.php';

// Ensure user is logged in and is a customer
require_role('customer');

// Get cart items
try {
    $stmt = $pdo->prepare("
        SELECT ci.*, m.name, m.price, m.image, m.requires_prescription,
               p.business_name as pharmacy_name
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

    foreach ($cart_items as $item) {
        if (!isset($grouped_items[$item['pharmacy_name']])) {
            $grouped_items[$item['pharmacy_name']] = [
                'items' => [],
                'subtotal' => 0
            ];
        }
        $item['subtotal'] = $item['price'] * $item['quantity'];
        $grouped_items[$item['pharmacy_name']]['items'][] = $item;
        $grouped_items[$item['pharmacy_name']]['subtotal'] += $item['subtotal'];
        $total += $item['subtotal'];
    }
} catch (PDOException $e) {
    error_log($e->getMessage());
    $cart_items = [];
    $grouped_items = [];
    $total = 0;
}

// Additional CSS for this page
$additional_css = ['customer-cart'];

include_once '../includes/header.php';
?>

<div class="cart-container animate-fade-in">
    <div class="cart-content">
        <?php if (!empty($grouped_items)): ?>
            <h1>Shopping Cart</h1>

            <?php foreach ($grouped_items as $pharmacy => $group): ?>
                <div class="pharmacy-group card animate-slide-up">
                    <div class="pharmacy-header">
                        <h2>
                            <i class="fas fa-store"></i>
                            <?php echo htmlspecialchars($pharmacy); ?>
                        </h2>
                        <span class="subtotal">
                            Subtotal: <?php echo format_currency($group['subtotal']); ?>
                        </span>
                    </div>

                    <div class="cart-items">
                        <?php foreach ($group['items'] as $item): ?>
                            <div class="cart-item">
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
                                    <h3><?php echo htmlspecialchars($item['name']); ?></h3>
                                    <?php if ($item['requires_prescription']): ?>
                                        <span class="prescription-badge">
                                            <i class="fas fa-file-medical"></i> Requires Prescription
                                        </span>
                                    <?php endif; ?>
                                    <div class="price"><?php echo format_currency($item['price']); ?></div>
                                </div>

                                <div class="item-actions">
                                    <form action="../includes/cart/update_cart.inc.php" method="POST"
                                        class="quantity-form" data-item-id="<?php echo $item['cart_id']; ?>">
                                        <button type="button" class="btn-quantity" data-action="decrease">
                                            <i class="fas fa-minus"></i>
                                        </button>
                                        <input type="number" name="quantity" value="<?php echo $item['quantity']; ?>"
                                            min="1" max="99" class="quantity-input">
                                        <button type="button" class="btn-quantity" data-action="increase">
                                            <i class="fas fa-plus"></i>
                                        </button>
                                    </form>

                                    <form action="../includes/cart/remove_from_cart.inc.php" method="POST"
                                        class="remove-form">
                                        <input type="hidden" name="cart_id" value="<?php echo $item['cart_id']; ?>">
                                        <button type="submit" class="btn-remove" name="remove">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>

            <!-- Cart Summary -->
            <div class="cart-summary card animate-slide-up">
                <div class="summary-details">
                    <div class="summary-row">
                        <span>Total Items:</span>
                        <span><?php echo array_sum(array_map(function ($group) {
                                    return array_sum(array_column($group['items'], 'quantity'));
                                }, $grouped_items)); ?></span>
                    </div>
                    <div class="summary-row">
                        <span>Total Amount:</span>
                        <span class="total-amount"><?php echo format_currency($total); ?></span>
                    </div>
                </div>

                <div class="summary-actions">
                    <a href="medicines.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Continue Shopping
                    </a>
                    <a href="checkout.php" class="btn btn-primary">
                        Proceed to Checkout <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
            </div>

        <?php else: ?>
            <div class="empty-cart animate-slide-up">
                <i class="fas fa-shopping-cart"></i>
                <h2>Your cart is empty</h2>
                <p>Browse our medicines and add items to your cart</p>
                <a href="medicines.php" class="btn btn-primary">
                    <i class="fas fa-pills"></i> Browse Medicines
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Handle quantity updates
        document.querySelectorAll('.quantity-form').forEach(form => {
            const input = form.querySelector('.quantity-input');
            const itemId = form.dataset.itemId;

            form.querySelectorAll('.btn-quantity').forEach(btn => {
                btn.addEventListener('click', function() {
                    let value = parseInt(input.value);
                    if (this.dataset.action === 'increase') {
                        value = Math.min(99, value + 1);
                    } else {
                        value = Math.max(1, value - 1);
                    }
                    input.value = value;

                    // Update cart via AJAX
                    updateCartQuantity(itemId, value);
                });
            });

            input.addEventListener('change', function() {
                let value = parseInt(this.value);
                value = Math.max(1, Math.min(99, value));
                this.value = value;
                updateCartQuantity(itemId, value);
            });
        });

        // AJAX function to update cart
        function updateCartQuantity(itemId, quantity) {
            const formData = new FormData();
            formData.append('cart_id', itemId);
            formData.append('quantity', quantity);

            fetch('../includes/cart/update_cart.inc.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Update subtotals and total
                        location.reload(); // Temporary solution - ideally update prices via JS
                    } else {
                        alert('Error updating cart. Please try again.');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error updating cart. Please try again.');
                });
        }

        // Smooth removal animation
        document.querySelectorAll('.remove-form').forEach(form => {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                const cartItem = this.closest('.cart-item');
                cartItem.style.animation = 'slideOutRight 0.3s ease-out';

                setTimeout(() => {
                    this.submit();
                }, 300);
            });
        });
    });