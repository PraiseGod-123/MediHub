<?php
session_start();
require_once '../config/config.php';
require_once '../includes/functions.php';

// Ensure user is logged in and is a customer
require_role('customer');

try {
    // Get user details
    $stmt = $pdo->prepare("
        SELECT * FROM users 
        WHERE user_id = ? AND role = 'customer'
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();

    if (!$user) {
        $_SESSION['error'] = "User not found";
        header("Location: dashboard.php");
        exit();
    }

    // Get order statistics
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_orders,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_orders,
            SUM(total_amount) as total_spent
        FROM orders 
        WHERE user_id = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $stats = $stmt->fetch();
} catch (PDOException $e) {
    error_log($e->getMessage());
    $_SESSION['error'] = "Error retrieving user information";
    header("Location: dashboard.php");
    exit();
}

// Additional CSS for this page
$additional_css = ['customer-profile'];

include_once '../includes/header.php';
?>

<div class="profile-container animate-fade-in">
    <!-- Profile Header -->
    <div class="profile-header card animate-slide-up">
        <div class="profile-avatar">
            <?php if ($user['profile_image']): ?>
                <img src="<?php echo BASE_URL; ?>/assets/images/profiles/<?php echo htmlspecialchars($user['profile_image']); ?>"
                    alt="Profile Image">
            <?php else: ?>
                <div class="avatar-placeholder">
                    <?php echo strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)); ?>
                </div>
            <?php endif; ?>
            <button class="change-avatar-btn" onclick="document.getElementById('avatarUpload').click()">
                <i class="fas fa-camera"></i>
            </button>
            <form id="avatarForm" action="../includes/profile/update_avatar.inc.php" method="POST" enctype="multipart/form-data" style="display: none;">
                <input type="file" id="avatarUpload" name="avatar" accept="image/*" onchange="this.form.submit()">
            </form>
        </div>

        <div class="profile-info">
            <h1><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h1>
            <p class="email"><?php echo htmlspecialchars($user['email']); ?></p>
            <p class="join-date">Member since <?php echo date('F Y', strtotime($user['created_at'])); ?></p>
        </div>

        <div class="profile-stats">
            <div class="stat-card">
                <div class="stat-value"><?php echo $stats['total_orders']; ?></div>
                <div class="stat-label">Total Orders</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $stats['completed_orders']; ?></div>
                <div class="stat-label">Completed</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo format_currency($stats['total_spent']); ?></div>
                <div class="stat-label">Total Spent</div>
            </div>
        </div>
    </div>

    <!-- Profile Settings -->
    <div class="profile-settings card animate-slide-up">
        <h2>Profile Settings</h2>
        <form action="../includes/profile/update_profile.inc.php" method="POST" class="settings-form">
            <div class="form-row">
                <div class="form-group">
                    <label for="first_name">First Name</label>
                    <input type="text" id="first_name" name="first_name"
                        value="<?php echo htmlspecialchars($user['first_name']); ?>" required>
                </div>

                <div class="form-group">
                    <label for="last_name">Last Name</label>
                    <input type="text" id="last_name" name="last_name"
                        value="<?php echo htmlspecialchars($user['last_name']); ?>" required>
                </div>
            </div>

            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email"
                    value="<?php echo htmlspecialchars($user['email']); ?>" required>
            </div>

            <div class="form-group">
                <label for="phone">Phone Number</label>
                <input type="tel" id="phone" name="phone"
                    value="<?php echo htmlspecialchars($user['phone']); ?>">
            </div>

            <div class="form-group">
                <label for="address">Delivery Address</label>
                <textarea id="address" name="address" rows="3"><?php echo htmlspecialchars($user['address']); ?></textarea>
            </div>

            <button type="submit" name="update_profile" class="btn btn-primary">
                <i class="fas fa-save"></i> Save Changes
            </button>
        </form>
    </div>

    <!-- Security Settings -->
    <div class="security-settings card animate-slide-up">
        <h2>Security Settings</h2>
        <form action="../includes/profile/change_password.inc.php" method="POST" class="password-form">
            <div class="form-group">
                <label for="current_password">Current Password</label>
                <input type="password" id="current_password" name="current_password" required>
            </div>

            <div class="form-group">
                <label for="new_password">New Password</label>
                <input type="password" id="new_password" name="new_password" required>
            </div>

            <div class="form-group">
                <label for="confirm_password">Confirm New Password</label>
                <input type="password" id="confirm_password" name="confirm_password" required>
            </div>

            <button type="submit" name="change_password" class="btn btn-primary">
                <i class="fas fa-lock"></i> Change Password
            </button>
        </form>
    </div>

    <!-- Account Deactivation -->
    <div class="danger-zone card animate-slide-up">
        <h2>Danger Zone</h2>
        <p class="warning-text">
            Once you deactivate your account, all your personal information will be anonymized and your account will be disabled.
            This action cannot be undone.
        </p>
        <button type="button" class="btn btn-danger" onclick="confirmDeactivation()">
            <i class="fas fa-user-slash"></i> Deactivate Account
        </button>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Password validation
        const passwordForm = document.querySelector('.password-form');
        if (passwordForm) {
            passwordForm.addEventListener('submit', function(e) {
                const newPassword = document.getElementById('new_password').value;
                const confirmPassword = document.getElementById('confirm_password').value;

                if (newPassword !== confirmPassword) {
                    e.preventDefault();
                    alert('New passwords do not match!');
                    return false;
                }

                if (newPassword.length < 8) {
                    e.preventDefault();
                    alert('Password must be at least 8 characters long!');
                    return false;
                }
            });
        }

        // Profile image preview
        const avatarUpload = document.getElementById('avatarUpload');
        if (avatarUpload) {
            avatarUpload.addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (file) {
                    if (file.size > 5 * 1024 * 1024) { // 5MB
                        alert('File size must be less than 5MB');
                        this.value = '';
                        return;
                    }

                    const allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
                    if (!allowedTypes.includes(file.type)) {
                        alert('Please upload an image file (JPEG, PNG, or GIF)');
                        this.value = '';
                        return;
                    }
                }
            });
        }
    });

    // Account deactivation confirmation
    function confirmDeactivation() {
        const confirmed = confirm(
            'Are you sure you want to deactivate your account?\n\n' +
            'This action will:\n' +
            '- Anonymize your personal information\n' +
            '- Disable your login access\n' +
            '- Cannot be undone\n\n' +
            'Do you wish to proceed?'
        );

        if (confirmed) {
            window.location.href = '../includes/profile/deactivate_account.inc.php';
        }
    }
</script>

<?php include_once '../includes/footer.php'; ?>