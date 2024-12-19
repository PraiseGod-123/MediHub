<?php
session_start();
require_once '../config/config.php';
require_once '../includes/functions.php';

// Ensure user is logged in and is a pharmacy
require_role('pharmacy');

// Get filter and search parameters
$category_id = isset($_GET['category']) ? filter_var($_GET['category'], FILTER_VALIDATE_INT) : null;
$search = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';
$status = isset($_GET['status']) ? sanitize_input($_GET['status']) : '';
$sort = isset($_GET['sort']) ? sanitize_input($_GET['sort']) : 'name_asc';

try {
    // Get categories for filter
    $stmt = $pdo->prepare("SELECT * FROM categories ORDER BY name");
    $stmt->execute();
    $categories = $stmt->fetchAll();

    // Build query for medicines
    $query = "
        SELECT m.*, c.name as category_name,
               COUNT(DISTINCT o.order_id) as total_orders,
               COALESCE(SUM(oi.quantity), 0) as total_sold
        FROM medicines m
        LEFT JOIN categories c ON m.category_id = c.category_id
        LEFT JOIN order_items oi ON m.medicine_id = oi.medicine_id
        LEFT JOIN orders o ON oi.order_id = o.order_id AND o.status = 'completed'
        WHERE m.pharmacy_id = ?
    ";
    $params = [$_SESSION['user_id']];

    if ($category_id) {
        $query .= " AND m.category_id = ?";
        $params[] = $category_id;
    }

    if ($search) {
        $query .= " AND (m.name LIKE ? OR m.description LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }

    if ($status) {
        $query .= " AND m.status = ?";
        $params[] = $status;
    }

    $query .= " GROUP BY m.medicine_id";

    // Add sorting
    switch ($sort) {
        case 'price_asc':
            $query .= " ORDER BY m.price ASC";
            break;
        case 'price_desc':
            $query .= " ORDER BY m.price DESC";
            break;
        case 'stock_asc':
            $query .= " ORDER BY m.stock_quantity ASC";
            break;
        case 'stock_desc':
            $query .= " ORDER BY m.stock_quantity DESC";
            break;
        case 'name_desc':
            $query .= " ORDER BY m.name DESC";
            break;
        case 'name_asc':
        default:
            $query .= " ORDER BY m.name ASC";
    }

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $medicines = $stmt->fetchAll();

    // Get summary statistics
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_medicines,
            SUM(CASE WHEN stock_quantity <= 10 THEN 1 ELSE 0 END) as low_stock,
            SUM(CASE WHEN status = 'out_of_stock' THEN 1 ELSE 0 END) as out_of_stock,
            SUM(stock_quantity) as total_stock
        FROM medicines 
        WHERE pharmacy_id = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $stats = $stmt->fetch();
} catch (PDOException $e) {
    error_log($e->getMessage());
    $_SESSION['error'] = "Error retrieving inventory data";
}

// Additional CSS for this page
$additional_css = ['pharmacy-inventory'];

include_once '../includes/header.php';
?>

<div class="inventory-container animate-fade-in">
    <!-- Header Section -->
    <div class="inventory-header">
        <div class="header-content">
            <h1>Inventory Management</h1>
            <button class="btn btn-primary" onclick="openAddMedicineModal()">
                <i class="fas fa-plus"></i> Add New Medicine
            </button>
        </div>

        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card total animate-slide-up" style="--delay: 0.1s">
                <div class="stat-icon">
                    <i class="fas fa-pills"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo $stats['total_medicines']; ?></div>
                    <div class="stat-label">Total Medicines</div>
                </div>
            </div>

            <div class="stat-card stock animate-slide-up" style="--delay: 0.2s">
                <div class="stat-icon">
                    <i class="fas fa-boxes"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo $stats['total_stock']; ?></div>
                    <div class="stat-label">Total Stock</div>
                </div>
            </div>

            <div class="stat-card warning animate-slide-up" style="--delay: 0.3s">
                <div class="stat-icon">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo $stats['low_stock']; ?></div>
                    <div class="stat-label">Low Stock</div>
                </div>
            </div>

            <div class="stat-card danger animate-slide-up" style="--delay: 0.4s">
                <div class="stat-icon">
                    <i class="fas fa-times-circle"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo $stats['out_of_stock']; ?></div>
                    <div class="stat-label">Out of Stock</div>
                </div>
            </div>
        </div>

        <!-- Filters Section -->
        <div class="filters-section card animate-slide-up">
            <form action="" method="GET" class="filters-form">
                <div class="form-group">
                    <label for="search">Search Medicines</label>
                    <div class="search-input">
                        <i class="fas fa-search"></i>
                        <input type="text" id="search" name="search"
                            value="<?php echo htmlspecialchars($search); ?>"
                            placeholder="Search by name or description...">
                    </div>
                </div>

                <div class="form-group">
                    <label for="category">Category</label>
                    <select name="category" id="category" class="form-control">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?php echo $category['category_id']; ?>"
                                <?php echo $category_id == $category['category_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($category['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="status">Status</label>
                    <select name="status" id="status" class="form-control">
                        <option value="">All Status</option>
                        <option value="available" <?php echo $status === 'available' ? 'selected' : ''; ?>>Available</option>
                        <option value="out_of_stock" <?php echo $status === 'out_of_stock' ? 'selected' : ''; ?>>Out of Stock</option>
                        <option value="discontinued" <?php echo $status === 'discontinued' ? 'selected' : ''; ?>>Discontinued</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="sort">Sort By</label>
                    <select name="sort" id="sort" class="form-control">
                        <option value="name_asc" <?php echo $sort === 'name_asc' ? 'selected' : ''; ?>>Name (A-Z)</option>
                        <option value="name_desc" <?php echo $sort === 'name_desc' ? 'selected' : ''; ?>>Name (Z-A)</option>
                        <option value="price_asc" <?php echo $sort === 'price_asc' ? 'selected' : ''; ?>>Price (Low to High)</option>
                        <option value="price_desc" <?php echo $sort === 'price_desc' ? 'selected' : ''; ?>>Price (High to Low)</option>
                        <option value="stock_asc" <?php echo $sort === 'stock_asc' ? 'selected' : ''; ?>>Stock (Low to High)</option>
                        <option value="stock_desc" <?php echo $sort === 'stock_desc' ? 'selected' : ''; ?>>Stock (High to Low)</option>
                    </select>
                </div>

                <button type="submit" class="btn btn-primary">Apply Filters</button>
                <a href="inventory.php" class="btn btn-secondary">Clear Filters</a>
            </form>
        </div>
    </div>

    <!-- Inventory Grid -->
    <div class="inventory-grid">
        <?php if (!empty($medicines)): ?>
            <?php foreach ($medicines as $medicine): ?>
                <div class="medicine-card animate-slide-up">
                    <div class="medicine-image">
                        <?php if ($medicine['image']): ?>
                            <img src="<?php echo BASE_URL; ?>/assets/images/products/<?php echo htmlspecialchars($medicine['image']); ?>"
                                alt="<?php echo htmlspecialchars($medicine['name']); ?>">
                        <?php else: ?>
                            <div class="placeholder-image">
                                <i class="fas fa-pills"></i>
                            </div>
                        <?php endif; ?>
                        <div class="medicine-status <?php echo $medicine['status']; ?>">
                            <?php echo ucfirst($medicine['status']); ?>
                        </div>
                    </div>

                    <div class="medicine-details">
                        <h3><?php echo htmlspecialchars($medicine['name']); ?></h3>

                        <div class="medicine-meta">
                            <span class="category">
                                <i class="fas fa-tag"></i>
                                <?php echo htmlspecialchars($medicine['category_name']); ?>
                            </span>
                            <?php if ($medicine['requires_prescription']): ?>
                                <span class="prescription-required">
                                    <i class="fas fa-file-medical"></i>
                                    Prescription Required
                                </span>
                            <?php endif; ?>
                        </div>

                        <div class="medicine-stats">
                            <div class="stat-item">
                                <span class="stat-label">Price:</span>
                                <span class="stat-value"><?php echo format_currency($medicine['price']); ?></span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-label">In Stock:</span>
                                <span class="stat-value <?php echo $medicine['stock_quantity'] <= 10 ? 'warning' : ''; ?>">
                                    <?php echo $medicine['stock_quantity']; ?> units
                                </span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-label">Total Sold:</span>
                                <span class="stat-value"><?php echo $medicine['total_sold']; ?> units</span>
                            </div>
                        </div>

                        <div class="medicine-actions">
                            <button class="btn btn-outline" onclick="openUpdateStockModal(<?php echo $medicine['medicine_id']; ?>)">
                                <i class="fas fa-plus"></i> Update Stock
                            </button>
                            <button class="btn btn-primary" onclick="openEditMedicineModal(<?php echo $medicine['medicine_id']; ?>)">
                                <i class="fas fa-edit"></i> Edit Details
                            </button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-box-open"></i>
                <h2>No medicines found</h2>
                <p>Add medicines to your inventory or adjust your filters</p>
                <button class="btn btn-primary" onclick="openAddMedicineModal()">
                    <i class="fas fa-plus"></i> Add New Medicine
                </button>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Add/Edit Medicine Modal -->
<div id="medicineModal" class="modal">
    <div class="modal-content">
        <!-- Modal content will be loaded dynamically -->
    </div>
</div>

<!-- Update Stock Modal -->
<div id="stockModal" class="modal">
    <div class="modal-content">
        <!-- Modal content will be loaded dynamically -->
    </div>
</div>

<script>
    // Modal functionality
    function openAddMedicineModal() {
        const modal = document.getElementById('medicineModal');
        fetch('../includes/inventory/medicine_form.inc.php')
            .then(response => response.text())
            .then(html => {
                modal.querySelector('.modal-content').innerHTML = html;
                modal.style.display = 'block';
            });
    }

    function openEditMedicineModal(medicineId) {
        const modal = document.getElementById('medicineModal');
        fetch(`../includes/inventory/medicine_form.inc.php?id=${medicineId}`)
            .then(response => response.text())
            .then(html => {
                modal.querySelector('.modal-content').innerHTML = html;
                modal.style.display = 'block';
            });
    }

    function openUpdateStockModal(medicineId) {
        const modal = document.getElementById('stockModal');
        fetch(`../includes/inventory/stock_form.inc.php?id=${medicineId}`)
            .then(response => response.text())
            .then(html => {
                modal.querySelector('.modal-content').innerHTML = html;
                modal.style.display = 'block';
            });
    }

    // Close modal when clicking outside
    window.onclick = function(event) {
        const modals = document.getElementsByClassName('modal');
        for (let modal of modals) {
            if (event.target == modal) {
                modal.style.display = 'none';
            }
        }
    }
</script>

<?php include_once '../includes/footer.php'; ?>