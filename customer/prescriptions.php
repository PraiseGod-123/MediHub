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
    SELECT p.*, o.order_id, o.total_amount, o.status as order_status,
           m.name as medicine_name, m.price,
           ph.business_name as pharmacy_name
    FROM prescriptions p
    JOIN orders o ON p.order_id = o.order_id
    JOIN order_items oi ON o.order_id = oi.order_id
    JOIN medicines m ON oi.medicine_id = m.medicine_id
    JOIN pharmacy_details ph ON o.pharmacy_id = ph.pharmacy_id
    WHERE p.user_id = ?
";

$params = [$_SESSION['user_id']];

if ($status) {
    $query .= " AND p.status = ?";
    $params[] = $status;
}

if ($date_range) {
    switch ($date_range) {
        case '7days':
            $query .= " AND p.uploaded_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
            break;
        case '30days':
            $query .= " AND p.uploaded_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
            break;
        case '6months':
            $query .= " AND p.uploaded_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)";
            break;
    }
}

$query .= " ORDER BY p.uploaded_at DESC";

try {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $prescriptions = $stmt->fetchAll();

    // Get statistics
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
            SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected
        FROM prescriptions 
        WHERE user_id = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $stats = $stmt->fetch();
} catch (PDOException $e) {
    error_log($e->getMessage());
    $prescriptions = [];
    $stats = ['total' => 0, 'pending' => 0, 'approved' => 0, 'rejected' => 0];
}

// Additional CSS for this page
$additional_css = ['customer-prescriptions'];

include_once '../includes/header.php';
?>

<div class="prescriptions-container animate-fade-in">
    <div class="prescriptions-header">
        <h1>My Prescriptions</h1>

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card animate-slide-up" style="--delay: 0.1s">
                <div class="stat-icon total">
                    <i class="fas fa-file-medical"></i>
                </div>
                <div class="stat-details">
                    <h3><?php echo $stats['total']; ?></h3>
                    <p>Total Prescriptions</p>
                </div>
            </div>

            <div class="stat-card animate-slide-up" style="--delay: 0.2s">
                <div class="stat-icon pending">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-details">
                    <h3><?php echo $stats['pending']; ?></h3>
                    <p>Pending Review</p>
                </div>
            </div>

            <div class="stat-card animate-slide-up" style="--delay: 0.3s">
                <div class="stat-icon approved">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-details">
                    <h3><?php echo $stats['approved']; ?></h3>
                    <p>Approved</p>
                </div>
            </div>

            <div class="stat-card animate-slide-up" style="--delay: 0.4s">
                <div class="stat-icon rejected">
                    <i class="fas fa-times-circle"></i>
                </div>
                <div class="stat-details">
                    <h3><?php echo $stats['rejected']; ?></h3>
                    <p>Rejected</p>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="filters-section card animate-slide-up">
            <form action="" method="GET" class="filters-form">
                <div class="form-group">
                    <label for="status">Prescription Status</label>
                    <select name="status" id="status" class="form-control">
                        <option value="">All Prescriptions</option>
                        <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="approved" <?php echo $status === 'approved' ? 'selected' : ''; ?>>Approved</option>
                        <option value="rejected" <?php echo $status === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
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
                <a href="prescriptions.php" class="btn btn-secondary">Clear Filters</a>
            </form>
        </div>
    </div>

    <!-- Prescriptions List -->
    <div class="prescriptions-list">
        <?php if (!empty($prescriptions)): ?>
            <?php foreach ($prescriptions as $prescription): ?>
                <div class="prescription-card card animate-slide-up">
                    <div class="prescription-header">
                        <div class="prescription-info">
                            <h3>Order #<?php echo $prescription['order_id']; ?></h3>
                            <span class="prescription-date">
                                <i class="fas fa-calendar"></i>
                                <?php echo date('M j, Y, g:i a', strtotime($prescription['uploaded_at'])); ?>
                            </span>
                        </div>
                        <span class="status-badge <?php echo $prescription['status']; ?>">
                            <?php echo ucfirst($prescription['status']); ?>
                        </span>
                    </div>

                    <div class="prescription-content">
                        <div class="prescription-image">
                            <img src="<?php echo BASE_URL; ?>/assets/images/prescriptions/<?php echo htmlspecialchars($prescription['image']); ?>"
                                alt="Prescription"
                                onclick="openImageModal(this.src)">
                            <div class="image-overlay">
                                <i class="fas fa-search-plus"></i>
                                Click to view
                            </div>
                        </div>

                        <div class="prescription-details">
                            <div class="detail-row">
                                <span class="detail-label">
                                    <i class="fas fa-pills"></i> Medicine
                                </span>
                                <span class="detail-value"><?php echo htmlspecialchars($prescription['medicine_name']); ?></span>
                            </div>

                            <div class="detail-row">
                                <span class="detail-label">
                                    <i class="fas fa-store"></i> Pharmacy
                                </span>
                                <span class="detail-value"><?php echo htmlspecialchars($prescription['pharmacy_name']); ?></span>
                            </div>

                            <div class="detail-row">
                                <span class="detail-label">
                                    <i class="fas fa-money-bill-wave"></i> Order Amount
                                </span>
                                <span class="detail-value"><?php echo format_currency($prescription['total_amount']); ?></span>
                            </div>

                            <div class="detail-row">
                                <span class="detail-label">
                                    <i class="fas fa-shopping-cart"></i> Order Status
                                </span>
                                <span class="status-badge small <?php echo $prescription['order_status']; ?>">
                                    <?php echo ucfirst($prescription['order_status']); ?>
                                </span>
                            </div>
                        </div>
                    </div>

                    <?php if ($prescription['status'] === 'rejected'): ?>
                        <div class="prescription-footer">
                            <div class="rejection-reason">
                                <i class="fas fa-exclamation-circle"></i>
                                <p>Your prescription was rejected. Please upload a new prescription or contact the pharmacy for more information.</p>
                            </div>
                            <a href="upload_prescription.php?order_id=<?php echo $prescription['order_id']; ?>" class="btn btn-primary">
                                <i class="fas fa-upload"></i> Upload New Prescription
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="no-prescriptions card animate-slide-up">
                <i class="fas fa-file-medical"></i>
                <h2>No prescriptions found</h2>
                <p>You haven't uploaded any prescriptions yet</p>
                <a href="medicines.php" class="btn btn-primary">
                    <i class="fas fa-pills"></i> Browse Medicines
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Image Modal -->
<div id="imageModal" class="modal">
    <span class="modal-close">&times;</span>
    <img class="modal-content" id="modalImage">
</div>

<script>
    // Image Modal functionality
    function openImageModal(src) {
        const modal = document.getElementById('imageModal');
        const modalImg = document.getElementById('modalImage');
        modal.style.display = "block";
        modalImg.src = src;
    }

    const modal = document.getElementById('imageModal');
    const span = document.getElementsByClassName("modal-close")[0];

    span.onclick = function() {
        modal.style.display = "none";
    }

    modal.onclick = function(event) {
        if (event.target == modal) {
            modal.style.display = "none";
        }
    }
</script>

<?php include_once '../includes/footer.php'; ?>