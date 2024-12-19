<?php
if (!defined('BASE_URL')) {
    require_once '../../config/config.php';
    require_once '../functions.php';
}

// Get medicine details if editing
$medicine_id = isset($_GET['id']) ? filter_var($_GET['id'], FILTER_VALIDATE_INT) : null;
$medicine = null;

if ($medicine_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT m.*, c.name as category_name
            FROM medicines m
            LEFT JOIN categories c ON m.category_id = c.category_id
            WHERE m.medicine_id = ? AND m.pharmacy_id = ?
        ");
        $stmt->execute([$medicine_id, $_SESSION['user_id']]);
        $medicine = $stmt->fetch();
    } catch (PDOException $e) {
        error_log($e->getMessage());
    }
}

// Get categories for dropdown
try {
    $stmt = $pdo->prepare("SELECT * FROM categories ORDER BY name");
    $stmt->execute();
    $categories = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log($e->getMessage());
    $categories = [];
}
?>

<div class="medicine-form">
    <div class="modal-header">
        <h2><?php echo $medicine ? "Edit Medicine" : "Add New Medicine"; ?></h2>
        <button type="button" class="modal-close" onclick="closeModal()">&times;</button>
    </div>

    <form action="../includes/inventory/<?php echo $medicine ? 'update_medicine.inc.php' : 'add_medicine.inc.php'; ?>"
        method="POST"
        enctype="multipart/form-data"
        class="form-grid">

        <?php if ($medicine): ?>
            <input type="hidden" name="medicine_id" value="<?php echo $medicine['medicine_id']; ?>">
        <?php endif; ?>

        <!-- Basic Information -->
        <div class="form-section">
            <h3>Basic Information</h3>

            <div class="form-group">
                <label for="name">Medicine Name *</label>
                <input type="text"
                    id="name"
                    name="name"
                    value="<?php echo $medicine ? htmlspecialchars($medicine['name']) : ''; ?>"
                    required>
            </div>

            <div class="form-group">
                <label for="category">Category *</label>
                <select name="category_id" id="category" required>
                    <option value="">Select Category</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?php echo $category['category_id']; ?>"
                            <?php echo ($medicine && $medicine['category_id'] == $category['category_id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($category['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="description">Description</label>
                <textarea id="description"
                    name="description"
                    rows="4"><?php echo $medicine ? htmlspecialchars($medicine['description']) : ''; ?></textarea>
            </div>
        </div>

        <!-- Pricing and Stock -->
        <div class="form-section">
            <h3>Pricing and Stock</h3>

            <div class="form-row">
                <div class="form-group">
                    <label for="price">Price (₦) *</label>
                    <input type="number"
                        id="price"
                        name="price"
                        min="0"
                        step="0.01"
                        value="<?php echo $medicine ? $medicine['price'] : ''; ?>"
                        required>
                </div>

                <div class="form-group">
                    <label for="stock">Initial Stock *</label>
                    <input type="number"
                        id="stock"
                        name="stock_quantity"
                        min="0"
                        value="<?php echo $medicine ? $medicine['stock_quantity'] : ''; ?>"
                        <?php echo $medicine ? 'disabled' : 'required'; ?>>
                    <?php if ($medicine): ?>
                        <small class="help-text">Use the Update Stock feature to modify stock levels</small>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Additional Details -->
        <div class="form-section">
            <h3>Additional Details</h3>

            <div class="form-group">
                <label class="checkbox-label">
                    <input type="checkbox"
                        name="requires_prescription"
                        value="1"
                        <?php echo ($medicine && $medicine['requires_prescription']) ? 'checked' : ''; ?>>
                    Requires Prescription
                </label>
            </div>

            <div class="form-group">
                <label for="status">Status *</label>
                <select name="status" id="status" required>
                    <option value="available" <?php echo ($medicine && $medicine['status'] == 'available') ? 'selected' : ''; ?>>
                        Available
                    </option>
                    <option value="out_of_stock" <?php echo ($medicine && $medicine['status'] == 'out_of_stock') ? 'selected' : ''; ?>>
                        Out of Stock
                    </option>
                    <option value="discontinued" <?php echo ($medicine && $medicine['status'] == 'discontinued') ? 'selected' : ''; ?>>
                        Discontinued
                    </option>
                </select>
            </div>

            <div class="form-group">
                <label for="image">Medicine Image</label>
                <?php if ($medicine && $medicine['image']): ?>
                    <div class="current-image">
                        <img src="<?php echo BASE_URL; ?>/assets/images/products/<?php echo htmlspecialchars($medicine['image']); ?>"
                            alt="Current medicine image">
                        <small>Current image will be replaced if you upload a new one</small>
                    </div>
                <?php endif; ?>
                <input type="file"
                    id="image"
                    name="image"
                    accept="image/*">
                <small class="help-text">Recommended size: 600x600px. Max size: 2MB</small>
            </div>
        </div>

        <div class="form-actions">
            <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
            <button type="submit" class="btn btn-primary">
                <?php echo $medicine ? 'Update Medicine' : 'Add Medicine'; ?>
            </button>
        </div>
    </form>
</div>

<style>
    .medicine-form {
        max-width: 800px;
        margin: 0 auto;
    }

    .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 2rem;
    }

    .modal-close {
        background: none;
        border: none;
        font-size: 1.5rem;
        color: var(--grey-600);
        cursor: pointer;
    }

    .form-grid {
        display: grid;
        gap: 2rem;
    }

    .form-section {
        background: var(--grey-100);
        padding: 1.5rem;
        border-radius: 8px;
    }

    .form-section h3 {
        margin: 0 0 1.5rem 0;
        color: var(--grey-800);
        font-size: 1.1rem;
    }

    .form-row {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1rem;
    }

    .form-group {
        margin-bottom: 1.5rem;
    }

    .form-group:last-child {
        margin-bottom: 0;
    }

    .form-group label {
        display: block;
        margin-bottom: 0.5rem;
        color: var(--grey-700);
        font-weight: 500;
    }

    .form-group input[type="text"],
    .form-group input[type="number"],
    .form-group select,
    .form-group textarea {
        width: 100%;
        padding: 0.75rem;
        border: 1px solid var(--grey-300);
        border-radius: 8px;
        font-size: 1rem;
        transition: all var(--transition-speed);
    }

    .form-group input:focus,
    .form-group select:focus,
    .form-group textarea:focus {
        outline: none;
        border-color: var(--primary-color);
        box-shadow: 0 0 0 3px var(--primary-light);
    }

    .checkbox-label {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        cursor: pointer;
    }

    .help-text {
        display: block;
        margin-top: 0.5rem;
        color: var(--grey-600);
        font-size: 0.9rem;
    }

    .current-image {
        margin-bottom: 1rem;
    }

    .current-image img {
        max-width: 150px;
        height: auto;
        border-radius: 8px;
        margin-bottom: 0.5rem;
    }

    .form-actions {
        display: flex;
        justify-content: flex-end;
        gap: 1rem;
        margin-top: 2rem;
    }

    /* Responsive Design */
    @media (max-width: 768px) {
        .form-section {
            padding: 1rem;
        }

        .form-row {
            grid-template-columns: 1fr;
        }

        .form-actions {
            flex-direction: column;
        }

        .btn {
            width: 100%;
        }
    }
</style>