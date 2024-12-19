<?php
session_start();
require_once '../config/config.php';
require_once '../includes/functions.php';

// Ensure user is logged in and is a pharmacy
require_role('pharmacy');

// Get filter parameters
$status = isset($_GET['status']) ? sanitize_input($_GET['status']) : '';
$date_range = isset($_GET['date_range']) ? sanitize_input($_GET['date_range']) : '';

// Build query
$query = "
    SELECT p.*, 
           o.order_id, o.total_amount,
           m.name as medicine_name, m.price,
           u.first_name, u.last_name, u.email
    FROM prescriptions p
    JOIN orders o ON p.order_id = o.order_id
    JOIN order_items oi ON o.order_id = oi.order_id
    JOIN medicines m ON oi.medicine_id = m.medicine_id
    JOIN users u ON p.user_id = u.user_id
    WHERE o.pharmacy_id = ?
";

$params = [$_SESSION['user_id']];

if ($status) {
    $query .= " AND p.status = ?";
    $params[] = $status;
}

if ($date_range) {
    switch ($date_range) {
        case 'today':
            $query .= " AND DATE(p.uploaded_at) = CURDATE()";
            break;
        case 'week':
            $query .= " AND p.uploaded_at >= DATE_SUB(NOW(), INTERVAL 1 WEEK)";
            break;
        case 'month':
            $query .= " AND p.uploaded_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
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
            COUNT(*) as total_prescriptions,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
            SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected
        FROM prescriptions p
        JOIN orders o ON p.order_id = o.order_id
        WHERE o.pharmacy_id = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $stats = $stmt->fetch();
} catch (PDOException $e) {
    error_log($e->getMessage());
    $prescriptions = [];
    $stats = ['total_prescriptions' => 0, 'pending' => 0, 'approved' => 0, 'rejected' => 0];
}

// Additional CSS for this page
$additional_css = ['pharmacy-prescriptions'];

include_once '../includes/header.php';
?>

<div class="prescriptions-container animate-fade-in">
    <!-- Header Section -->
    <div class="prescriptions-header">
        <div class="header-content">
            <h1>Prescription Reviews</h1>
            <?php if ($stats['pending'] > 0): ?>
                <div class="pending-alert animate-pulse">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo $stats['pending']; ?> prescriptions need review
                </div>
            <?php endif; ?>
        </div>

        <!-- Stats Section -->
        <div class="stats-grid">
            <div class="stat-card total animate-slide-up" style="--delay: 0.1s">
                <div class="stat-icon">
                    <i class="fas fa-file-medical"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo $stats['total_prescriptions']; ?></div>
                    <div class="stat-label">Total Prescriptions</div>
                </div>
            </div>

            <div class="stat-card pending animate-slide-up" style="--delay: 0.2s">
                <div class="stat-icon">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo $stats['pending']; ?></div>
                    <div class="stat-label">Pending Review</div>
                </div>
            </div>

            <div class="stat-card approved animate-slide-up" style="--delay: 0.3s">
                <div class="stat-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo $stats['approved']; ?></div>
                    <div class="stat-label">Approved</div>
                </div>
            </div>

            <div class="stat-card rejected animate-slide-up" style="--delay: 0.4s">
                <div class="stat-icon">
                    <i class="fas fa-times-circle"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo $stats['rejected']; ?></div>
                    <div class="stat-label">Rejected</div>
                </div>
            </div>
        </div>

        <!-- Filters Section -->
        <div class="filters-section card animate-slide-up">
            <form action="" method="GET" class="filters-form">
                <div class="form-group">
                    <label for="status">Prescription Status</label>
                    <select name="status" id="status" class="form-control">
                        <option value="">All Prescriptions</option>
                        <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>Pending Review</option>
                        <option value="approved" <?php echo $status === 'approved' ? 'selected' : ''; ?>>Approved</option>
                        <option value="rejected" <?php echo $status === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="date_range">Time Period</label>
                    <select name="date_range" id="date_range" class="form-control">
                        <option value="">All Time</option>
                        <option value="today" <?php echo $date_range === 'today' ? 'selected' : ''; ?>>Today</option>
                        <option value="week" <?php echo $date_range === 'week' ? 'selected' : ''; ?>>Past Week</option>
                        <option value="month" <?php echo $date_range === 'month' ? 'selected' : ''; ?>>Past Month</option>
                    </select>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Apply Filters</button>
                    <a href="prescription_review.php" class="btn btn-secondary">Clear Filters</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Prescriptions List -->
    <div class="prescriptions-list">
        <?php if (!empty($prescriptions)): ?>
            <?php foreach ($prescriptions as $prescription): ?>
                <div class="prescription-card card animate-slide-up">
                    <div class="prescription-header">
                        <div class="header-content">
                            <div class="order-info">
                                <h3>Order #<?php echo $prescription['order_id']; ?></h3>
                                <span class="timestamp">
                                    <i class="fas fa-clock"></i>
                                    <?php echo date('M j, Y, g:i a', strtotime($prescription['uploaded_at'])); ?>
                                </span>
                            </div>
                            <div class="status-badge <?php echo $prescription['status']; ?>">
                                <?php echo ucfirst($prescription['status']); ?>
                            </div>
                        </div>
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
                            <div class="customer-info">
                                <h4>Customer Information</h4>
                                <div class="detail-row">
                                    <span class="label">Name:</span>
                                    <span class="value">
                                        <?php echo htmlspecialchars($prescription['first_name'] . ' ' . $prescription['last_name']); ?>
                                    </span>
                                </div>
                                <div class="detail-row">
                                    <span class="label">Email:</span>
                                    <span class="value"><?php echo htmlspecialchars($prescription['email']); ?></span>
                                </div>
                            </div>

                            <div class="order-details">
                                <h4>Order Details</h4>
                                <div class="detail-row">
                                    <span class="label">Medicine:</span>
                                    <span class="value"><?php echo htmlspecialchars($prescription['medicine_name']); ?></span>
                                </div>
                                <div class="detail-row">
                                    <span class="label">Amount:</span>
                                    <span class="value"><?php echo format_currency($prescription['total_amount']); ?></span>
                                </div>
                            </div>

                            <?php if ($prescription['status'] === 'pending'): ?>
                                <div class="review-actions">
                                    <button class="btn btn-success" onclick="approvePrescription(<?php echo $prescription['prescription_id']; ?>)">
                                        <i class="fas fa-check"></i> Approve
                                    </button>
                                    <button class="btn btn-danger" onclick="openRejectModal(<?php echo $prescription['prescription_id']; ?>)">
                                        <i class="fas fa-times"></i> Reject
                                    </button>
                                </div>
                            <?php elseif ($prescription['status'] === 'rejected'): ?>
                                <div class="rejection-reason">
                                    <h4>Rejection Reason</h4>
                                    <p><?php echo htmlspecialchars($prescription['rejection_reason'] ?? 'No reason provided'); ?></p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-file-medical"></i>
                <h2>No prescriptions found</h2>
                <p>There are no prescriptions matching your filters</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Image Modal -->
<div id="imageModal" class="modal">
    <span class="modal-close">&times;</span>
    <img class="modal-content" id="modalImage">
</div>

<!-- Rejection Modal -->
<div id="rejectModal" class="modal">
    <div class="modal-content">
        <h2>Reject Prescription</h2>
        <form id="rejectForm" action="../includes/prescriptions/update_status.inc.php" method="POST">
            <input type="hidden" name="prescription_id" id="reject_prescription_id">
            <input type="hidden" name="action" value="reject">

            <div class="form-group">
                <label for="rejection_reason">Reason for Rejection</label>
                <textarea name="rejection_reason" id="rejection_reason" rows="4" required
                    placeholder="Please provide a reason for rejecting this prescription..."></textarea>
            </div>

            <div class="modal-actions">
                <button type="submit" class="btn btn-danger">Confirm Rejection</button>
                <button type="button" class="btn btn-secondary" onclick="closeRejectModal()">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
    // Image Modal
    function openImageModal(src) {
        const modal = document.getElementById('imageModal');
        const modalImg = document.getElementById('modalImage');
        modal.style.display = "block";
        modalImg.src = src;
    }

    // Approve Prescription
    function approvePrescription(prescriptionId) {
        if (confirm('Are you sure you want to approve this prescription?')) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '../includes/prescriptions/update_status.inc.php';

            const prescriptionInput = document.createElement('input');
            prescriptionInput.type = 'hidden';
            prescriptionInput.name = 'prescription_id';
            prescriptionInput.value = prescriptionId;

            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action';
            actionInput.value = 'approve';

            form.appendChild(prescriptionInput);
            form.appendChild(actionInput);
            document.body.appendChild(form);
            form.submit();
        }
    }

    // Reject Modal
    function openRejectModal(prescriptionId) {
        const modal = document.getElementById('rejectModal');
        document.getElementById('reject_prescription_id').value = prescriptionId;
        modal.style.display = 'block';
    }

    function closeRejectModal() {
        const modal = document.getElementById('rejectModal');
        modal.style.display = 'none';
        document.getElementById('rejectForm').reset();
    }

    // Close modals when clicking outside
    window.onclick = function(event) {
        const imageModal = document.getElementById('imageModal');
        const rejectModal = document.getElementById('rejectModal');

        if (event.target === imageModal) {
            imageModal.style.display = "none";
        }
        if (event.target === rejectModal) {
            rejectModal.style.display = "none";
        }
    }

    document.querySelector('.modal-close').onclick = function() {
        document.getElementById('imageModal').style.display = "none";
    }
</script>

<?php include_once '../includes/footer.php'; ?>