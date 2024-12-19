<?php
session_start();
require_once '../config/config.php';
require_once '../includes/functions.php';

// Ensure user is logged in and is a customer
require_role('customer');

// Get medicine ID from query string
$medicine_id = isset($_GET['medicine_id']) ? filter_var($_GET['medicine_id'], FILTER_VALIDATE_INT) : 0;

if (!$medicine_id) {
    $_SESSION['error'] = "Invalid medicine ID";
    header("Location: medicines.php");
    exit();
}

try {
    // Get medicine details
    $stmt = $pdo->prepare("
        SELECT m.*, p.business_name
        FROM medicines m
        JOIN pharmacy_details p ON m.pharmacy_id = p.pharmacy_id
        WHERE m.medicine_id = ? AND m.requires_prescription = 1
    ");
    $stmt->execute([$medicine_id]);
    $medicine = $stmt->fetch();

    if (!$medicine) {
        $_SESSION['error'] = "Medicine not found or prescription not required";
        header("Location: medicines.php");
        exit();
    }

    // Create order if it doesn't exist
    $stmt = $pdo->prepare("
        SELECT order_id 
        FROM orders 
        WHERE user_id = ? AND pharmacy_id = ? AND status = 'pending'
        ORDER BY created_at DESC 
        LIMIT 1
    ");
    $stmt->execute([$_SESSION['user_id'], $medicine['pharmacy_id']]);
    $existing_order = $stmt->fetch();

    if ($existing_order) {
        $order_id = $existing_order['order_id'];
    } else {
        // Create new order
        $stmt = $pdo->prepare("
            INSERT INTO orders (user_id, pharmacy_id, total_amount, status, created_at)
            VALUES (?, ?, ?, 'pending', NOW())
        ");
        $stmt->execute([$_SESSION['user_id'], $medicine['pharmacy_id'], $medicine['price']]);
        $order_id = $pdo->lastInsertId();

        // Add order item
        $stmt = $pdo->prepare("
            INSERT INTO order_items (order_id, medicine_id, quantity, price_per_unit)
            VALUES (?, ?, 1, ?)
        ");
        $stmt->execute([$order_id, $medicine_id, $medicine['price']]);
    }
} catch (PDOException $e) {
    error_log($e->getMessage());
    $_SESSION['error'] = "Error processing request";
    header("Location: medicines.php");
    exit();
}

// Additional CSS for this page
$additional_css = ['customer-prescriptions'];

include_once '../includes/header.php';
?>

<div class="prescription-upload-container animate-fade-in">
    <div class="upload-header">
        <a href="medicines.php" class="back-link">
            <i class="fas fa-arrow-left"></i> Back to Medicines
        </a>
        <h1>Upload Prescription</h1>
    </div>

    <!-- Medicine Details -->
    <div class="medicine-details card animate-slide-up">
        <div class="medicine-info">
            <h2>Medicine Information</h2>
            <div class="detail-row">
                <span class="detail-label">Name:</span>
                <span class="detail-value"><?php echo htmlspecialchars($medicine['name']); ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Pharmacy:</span>
                <span class="detail-value"><?php echo htmlspecialchars($medicine['business_name']); ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Price:</span>
                <span class="detail-value"><?php echo format_currency($medicine['price']); ?></span>
            </div>
        </div>
    </div>

    <!-- Upload Form -->
    <div class="upload-form card animate-slide-up">
        <h2>Upload Your Prescription</h2>
        <p class="upload-instructions">
            Please upload a clear image or scan of your prescription.
            Accepted formats: JPEG, PNG, GIF, PDF (Max size: 5MB)
        </p>

        <form action="../includes/prescriptions/upload_prescription.inc.php"
            method="POST"
            enctype="multipart/form-data"
            class="prescription-form">

            <input type="hidden" name="order_id" value="<?php echo $order_id; ?>">

            <div class="upload-area" id="uploadArea">
                <div class="upload-prompt">
                    <i class="fas fa-cloud-upload-alt"></i>
                    <p>Drag and drop your prescription here or click to browse</p>
                </div>
                <input type="file"
                    name="prescription"
                    id="prescriptionFile"
                    accept=".jpg,.jpeg,.png,.gif,.pdf"
                    required>
            </div>

            <div class="preview-area" id="previewArea" style="display: none;">
                <img id="imagePreview" src="" alt="Prescription preview">
                <button type="button" class="btn btn-secondary" id="changeFile">
                    <i class="fas fa-sync-alt"></i> Change File
                </button>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-upload"></i> Upload Prescription
                </button>
            </div>
        </form>
    </div>

    <!-- Guidelines -->
    <div class="guidelines card animate-slide-up">
        <h2>Prescription Guidelines</h2>
        <div class="guidelines-content">
            <div class="guideline-item">
                <i class="fas fa-check-circle"></i>
                <div class="guideline-text">
                    <h3>Clear and Legible</h3>
                    <p>Ensure the prescription is clearly visible and all text is readable</p>
                </div>
            </div>
            <div class="guideline-item">
                <i class="fas fa-calendar-check"></i>
                <div class="guideline-text">
                    <h3>Valid Date</h3>
                    <p>Prescription must be current and not expired</p>
                </div>
            </div>
            <div class="guideline-item">
                <i class="fas fa-user-md"></i>
                <div class="guideline-text">
                    <h3>Doctor's Information</h3>
                    <p>Doctor's name, signature, and contact details must be visible</p>
                </div>
            </div>
            <div class="guideline-item">
                <i class="fas fa-file-medical"></i>
                <div class="guideline-text">
                    <h3>Complete Information</h3>
                    <p>Include all pages of the prescription if multiple pages exist</p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const uploadArea = document.getElementById('uploadArea');
        const previewArea = document.getElementById('previewArea');
        const imagePreview = document.getElementById('imagePreview');
        const fileInput = document.getElementById('prescriptionFile');
        const changeFileBtn = document.getElementById('changeFile');

        // Handle drag and drop
        uploadArea.addEventListener('dragover', function(e) {
            e.preventDefault();
            uploadArea.classList.add('dragover');
        });

        uploadArea.addEventListener('dragleave', function() {
            uploadArea.classList.remove('dragover');
        });

        uploadArea.addEventListener('drop', function(e) {
            e.preventDefault();
            uploadArea.classList.remove('dragover');
            fileInput.files = e.dataTransfer.files;
            handleFileSelect(e.dataTransfer.files[0]);
        });

        // Handle file selection
        fileInput.addEventListener('change', function() {
            if (this.files && this.files[0]) {
                handleFileSelect(this.files[0]);
            }
        });

        // Change file button
        changeFileBtn.addEventListener('click', function() {
            fileInput.value = '';
            uploadArea.style.display = 'flex';
            previewArea.style.display = 'none';
        });

        function handleFileSelect(file) {
            if (file) {
                // Validate file type and size
                const validTypes = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'];
                const maxSize = 5 * 1024 * 1024; // 5MB

                if (!validTypes.includes(file.type)) {
                    alert('Please upload an image (JPEG, PNG, GIF) or PDF file');
                    return;
                }

                if (file.size > maxSize) {
                    alert('File size must be less than 5MB');
                    return;
                }

                // Show preview for images
                if (file.type.startsWith('image/')) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        imagePreview.src = e.target.result;
                        uploadArea.style.display = 'none';
                        previewArea.style.display = 'block';
                    };
                    reader.readAsDataURL(file);
                } else {
                    // For PDFs, show an icon
                    imagePreview.src = '../assets/images/pdf-icon.png';
                    uploadArea.style.display = 'none';
                    previewArea.style.display = 'block';
                }
            }
        }
    });
</script>

<?php include_once '../includes/footer.php'; ?>