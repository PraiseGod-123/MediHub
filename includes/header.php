<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Get the current page for active navigation
$current_page = basename($_SERVER['PHP_SELF'], '.php');

// Function to check if a nav item is active
function isActive($page)
{
    global $current_page;
    return $current_page === $page ? 'active' : '';
}

// Get user role for conditional navigation
$user_role = $_SESSION['role'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MediHub - <?php echo ucfirst($current_page); ?></title>

    <!-- Base Styles -->
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/base.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/notifications.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    <!-- Additional Page-Specific CSS -->
    <?php if (isset($additional_css)): ?>
        <?php foreach ($additional_css as $css): ?>
            <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/<?php echo $css; ?>.css">
        <?php endforeach; ?>
    <?php endif; ?>
</head>

<body>
    <header class="header">
        <div class="container header-container">
            <a href="<?php echo BASE_URL; ?>" class="logo">
                <img src="<?php echo BASE_URL; ?>/assets/images/logo.svg" alt="MediHub Logo">
                MediHub
            </a>

            <nav>
                <button class="menu-toggle" aria-label="Toggle menu">
                    <i class="fas fa-bars"></i>
                </button>

                <div class="nav-menu">
                    <?php if ($user_role === 'admin'): ?>
                        <!-- Admin Navigation -->
                        <a href="<?php echo BASE_URL; ?>/admin/dashboard.php" class="nav-link <?php echo isActive('dashboard'); ?>">
                            <i class="fas fa-chart-line"></i> Dashboard
                        </a>
                        <a href="<?php echo BASE_URL; ?>/admin/manage_users.php" class="nav-link <?php echo isActive('manage_users'); ?>">
                            <i class="fas fa-users"></i> Users
                        </a>
                        <a href="<?php echo BASE_URL; ?>/admin/manage_pharmacies.php" class="nav-link <?php echo isActive('manage_pharmacies'); ?>">
                            <i class="fas fa-clinic-medical"></i> Pharmacies
                        </a>
                        <a href="<?php echo BASE_URL; ?>/admin/manage_categories.php" class="nav-link <?php echo isActive('manage_categories'); ?>">
                            <i class="fas fa-tags"></i> Categories
                        </a>

                    <?php elseif ($user_role === 'pharmacy'): ?>
                        <!-- Pharmacy Navigation -->
                        <a href="<?php echo BASE_URL; ?>/pharmacy/dashboard.php" class="nav-link <?php echo isActive('dashboard'); ?>">
                            <i class="fas fa-chart-line"></i> Dashboard
                        </a>
                        <a href="<?php echo BASE_URL; ?>/pharmacy/inventory.php" class="nav-link <?php echo isActive('inventory'); ?>">
                            <i class="fas fa-boxes"></i> Inventory
                        </a>
                        <a href="<?php echo BASE_URL; ?>/pharmacy/orders.php" class="nav-link <?php echo isActive('orders'); ?>">
                            <i class="fas fa-shopping-bag"></i> Orders
                        </a>
                        <a href="<?php echo BASE_URL; ?>/pharmacy/profile.php" class="nav-link <?php echo isActive('profile'); ?>">
                            <i class="fas fa-store"></i> Profile
                        </a>

                    <?php elseif ($user_role === 'customer'): ?>
                        <!-- Customer Navigation -->
                        <a href="<?php echo BASE_URL; ?>/customer/dashboard.php" class="nav-link <?php echo isActive('dashboard'); ?>">
                            <i class="fas fa-home"></i> Home
                        </a>
                        <a href="<?php echo BASE_URL; ?>/customer/medicines.php" class="nav-link <?php echo isActive('medicines'); ?>">
                            <i class="fas fa-pills"></i> Medicines
                        </a>
                        <a href="<?php echo BASE_URL; ?>/customer/cart.php" class="nav-link <?php echo isActive('cart'); ?>">
                            <i class="fas fa-shopping-cart"></i> Cart
                            <?php if (isset($_SESSION['cart_count']) && $_SESSION['cart_count'] > 0): ?>
                                <span class="cart-badge"><?php echo $_SESSION['cart_count']; ?></span>
                            <?php endif; ?>
                        </a>
                        <a href="<?php echo BASE_URL; ?>/customer/orders.php" class="nav-link <?php echo isActive('orders'); ?>">
                            <i class="fas fa-clipboard-list"></i> Orders
                        </a>
                        <a href="<?php echo BASE_URL; ?>/customer/profile.php" class="nav-link <?php echo isActive('profile'); ?>">
                            <i class="fas fa-user"></i> Profile
                        </a>

                    <?php else: ?>
                        <!-- Public Navigation -->
                        <a href="<?php echo BASE_URL; ?>/public/about.php" class="nav-link <?php echo isActive('about'); ?>">About</a>
                        <a href="<?php echo BASE_URL; ?>/public/contact.php" class="nav-link <?php echo isActive('contact'); ?>">Contact</a>
                        <a href="<?php echo BASE_URL; ?>/public/login_register.php" class="btn btn-primary">Login / Register</a>
                    <?php endif; ?>

                    <?php if (isset($_SESSION['user_id'])): ?>
                        <div class="user-menu">
                            <button type="button" class="btn btn-secondary dropdown-toggle">
                                <i class="fas fa-user-circle"></i>
                                <?php echo htmlspecialchars($_SESSION['name']); ?>
                            </button>
                            <div class="dropdown-menu">
                                <a href="<?php echo BASE_URL; ?>/customer/profile.php" class="dropdown-item">
                                    <i class="fas fa-user"></i> Profile
                                </a>
                                <a href="<?php echo BASE_URL; ?>/includes/auth/logout.inc.php" class="dropdown-item">
                                    <i class="fas fa-sign-out-alt"></i> Logout
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </nav>
        </div>
    </header>

    <main>
        <div class="container">
            <?php
            // Display notifications
            $notification_types = [
                'success' => ['cart_success', 'login_success', 'register_success', 'order_success'],
                'error' => ['cart_error', 'login_error', 'register_error', 'order_error'],
                'info' => ['info_message']
            ];

            foreach ($notification_types as $type => $messages) {
                foreach ($messages as $message_key) {
                    if (isset($_SESSION[$message_key])) {
                        $icon = $type === 'success' ? 'check-circle' : ($type === 'error' ? 'exclamation-circle' : 'info-circle');
            ?>
                        <div class="notification <?php echo $type; ?>" role="alert">
                            <i class="fas fa-<?php echo $icon; ?> notification-icon"></i>
                            <div class="notification-content">
                                <div class="notification-title">
                                    <?php echo ucfirst($type); ?>
                                </div>
                                <div class="notification-message">
                                    <?php echo htmlspecialchars($_SESSION[$message_key]); ?>
                                </div>
                            </div>
                            <button type="button" class="notification-close" aria-label="Close">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
            <?php
                        unset($_SESSION[$message_key]);
                    }
                }
            }
            ?>