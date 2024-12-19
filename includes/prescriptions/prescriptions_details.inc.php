<?php
// Ensure this isn't accessed directly
if (!defined('BASE_URL')) {
    http_response_code(403);
    exit('No direct script access allowed');
}

// Get prescription details if ID is provided
$prescription_id = isset($_GET['id']) ? filter_var($_GET['id'], FILTER_VALIDATE_INT) : null;

if ($prescription_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT p.*, 
                   o.order_id, o.total_amount,
                   m.name as medicine_name, m.price,
                   u.first_name, u.last_name, u.email, u.phone
            FROM prescriptions p
            JOIN orders o ON p.order_id = o.order_id
            JOIN order_items oi ON o.order_id = oi.order_id
            JOIN medicines m ON oi.medicine_id = m.medicine_id
            JOIN users u ON p.user_id = u.user_id
            WHERE p.prescription_id = ? AND o.pharmacy_id = ?
        ");
        $stmt->execute([$prescription_id, $_SESSION['user_id']]);
        $prescription = $stmt->fetch();
    } catch (PDOException $e) {
        error_log($e->getMessage());
        echo "Error loading prescription details";
        exit();
    }
}
?>

<div class="prescription-details-modal">
    <div class="modal-header">
        <h2><?php echo $prescription ? "Prescription Review #" . $prescription_id : "Prescription Details"; ?></h2>
        <button type="button" class="close-modal" onclick="closeModal()">&times;</button>
    </div>

    <?php if ($prescription): ?>
        <div class="modal-body">
            <!-- Prescription Image -->
            <div class="prescription-image-large">
                <img src="<?php echo BASE_URL; ?>/assets/images/prescriptions/<?php echo htmlspecialchars($prescription['image']); ?>"
                    alt="Prescription">
            </div>

            <!-- Customer Information -->
            <div class="info-section">
                <h3>Customer Information</h3>
                <div class="info-grid">
                    <div class="info-item">
                        <span class="label">Name:</span>
                        <span class="value"><?php echo htmlspecialchars($prescription['first_name'] . ' ' . $prescription['last_name']); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="label">Email:</span>
                        <span class="value"><?php echo htmlspecialchars($prescription['email']); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="label">Phone:</span>
                        <span class="value"><?php echo htmlspecialchars($prescription['phone'] ?? 'Not provided'); ?></span>
                    </div>
                </div>
            </div>

            <!-- Order Information -->
            <div class="info-section">
                <h3>Order Information</h3>
                <div class="info-grid">
                    <div class="info-item">
                        <span class="label">Order ID:</span>
                        <span class="value">#<?php echo $prescription['order_id']; ?></span>
                    </div>
                    <div class="info-item">
                        <span class="label">Medicine:</span>
                        <span class="value"><?php echo htmlspecialchars($prescription['medicine_name']); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="label">Amount:</span>
                        <span class="value"><?php echo format_currency($prescription['total_amount']); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="label">Submitted:</span>
                        <span class="value"><?php echo date('M j, Y, g:i a', strtotime($prescription['uploaded_at'])); ?></span>
                    </div>
                </div>
            </div>

            <?php if ($prescription['status'] === 'pending'): ?>
                <!-- Review Actions -->
                <div class="review-actions">
                    <button class="btn btn-success" onclick="approvePrescription(<?php echo $prescription_id; ?>)">
                        <i class="fas fa-check"></i> Approve Prescription
                    </button>
                    <button class="btn btn-danger" onclick="openRejectModal(<?php echo $prescription_id; ?>)">
                        <i class="fas fa-times"></i> Reject Prescription
                    </button>
                </div>
            <?php else: ?>
                <!-- Status Information -->
                <div class="status-info <?php echo $prescription['status']; ?>">
                    <div class="status-header">
                        <i class="fas fa-<?php echo $prescription['status'] === 'approved' ? 'check-circle' : 'times-circle'; ?>"></i>
                        <h3><?php echo ucfirst($prescription['status']); ?></h3>
                    </div>
                    <?php if ($prescription['status'] === 'rejected' && $prescription['rejection_reason']): ?>
                        <p class="rejection-reason"><?php echo htmlspecialchars($prescription['rejection_reason']); ?></p>
                    <?php endif; ?>
                    <p class="review-timestamp">
                        <?php echo date('M j, Y, g:i a', strtotime($prescription['reviewed_at'])); ?>
                    </p>
                </div>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="modal-body">
            <p class="error-message">Prescription not found or access denied.</p>
        </div>
    <?php endif; ?>
</div>

<style>
    .prescription-details-modal {
        color: var(--grey-900);
    }

    .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1.5rem;
    }

    .modal-header h2 {
        margin: 0;
        font-size: 1.5rem;
    }

    .close-modal {
        background: none;
        border: none;
        font-size: 1.5rem;
        color: var(--grey-600);
        cursor: pointer;
        padding: 0.5rem;
    }

    .close-modal:hover {
        color: var(--grey-900);
    }

    .prescription-image-large {
        margin-bottom: 1.5rem;
        border-radius: 8px;
        overflow: hidden;
    }

    .prescription-image-large img {
        width: 100%;
        height: auto;
        display: block;
    }

    .info-section {
        background: var(--grey-100);
        padding: 1.5rem;
        border-radius: 8px;
        margin-bottom: 1.5rem;
    }

    .info-section h3 {
        margin: 0 0 1rem 0;
        font-size: 1.1rem;
        color: var(--grey-800);
    }

    .info-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1rem;
    }

    .info-item {
        display: flex;
        flex-direction: column;
        gap: 0.25rem;
    }

    .info-item .label {
        font-size: 0.9rem;
        color: var(--grey-600);
    }

    .info-item .value {
        font-weight: 500;
        color: var(--grey-900);
    }

    .status-info {
        text-align: center;
        padding: 2rem;
        border-radius: 8px;
    }

    .status-info.approved {
        background: #E8F5E9;
    }

    .status-info.rejected {
        background: #FFEBEE;
    }

    .status-header {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        margin-bottom: 1rem;
    }

    .status-header i {
        font-size: 2rem;
    }

    .status-info.approved i {
        color: #2E7D32;
    }

    .status-info.rejected i {
        color: #C62828;
    }

    .rejection-reason {
        margin: 1rem 0;
        color: var(--grey-700);
    }

    .review-timestamp {
        font-size: 0.9rem;
        color: var(--grey-600);
        margin: 0;
    }

    .review-actions {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1rem;
        margin-top: 2rem;
    }

    @media (max-width: 768px) {
        .info-grid {
            grid-template-columns: 1fr;
        }

        .review-actions {
            grid-template-columns: 1fr;
        }
    }
</style>