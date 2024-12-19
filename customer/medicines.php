<?php
session_start();
require_once '../config/config.php';
require_once '../includes/functions.php';

// Ensure user is logged in and is a customer
require_role('customer');

// Get categories for filter
try {
    $stmt = $pdo->prepare("SELECT * FROM categories ORDER BY name");
    $stmt->execute();
    $categories = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log($e->getMessage());
    $categories = [];
}

// Handle filters
$category_id = isset($_GET['category']) ? filter_var($_GET['category'], FILTER_VALIDATE_INT) : null;
$search = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';
$pharmacy_id = isset($_GET['pharmacy']) ? filter_var($_GET['pharmacy'], FILTER_VALIDATE_INT) : null;
$sort = isset($_GET['sort']) ? sanitize_input($_GET['sort']) : 'name_asc';

// Build query
$query = "
    SELECT m.*, c.name as category_name, p.business_name as pharmacy_name 
    FROM medicines m
    LEFT JOIN categories c ON m.category_id = c.category_id
    LEFT JOIN pharmacy_details p ON m.pharmacy_id = p.pharmacy_id
    WHERE m.status = 'available'
";

$params = [];

if ($category_id) {
    $query .= " AND m.category_id = ?";
    $params[] = $category_id;
}

if ($search) {
    $query .= " AND (m.name LIKE ? OR m.description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($pharmacy_id) {
    $query .= " AND m.pharmacy_id = ?";
    $params[] = $pharmacy_id;
}

// Add sorting
switch ($sort) {
    case 'price_asc':
        $query .= " ORDER BY m.price ASC";
        break;
    case 'price_desc':
        $query .= " ORDER BY m.price DESC";
        break;
    case 'name_desc':
        $query .= " ORDER BY m.name DESC";
        break;
    case 'name_asc':
    default:
        $query .= " ORDER BY m.name ASC";
}

try {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $medicines = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log($e->getMessage());
    $medicines = [];
}

// Additional CSS for this page
$additional_css = ['customer-medicines'];

include_once '../includes/header.php';
?>

<div class="medicines-container">
    <!-- Filters Section -->
    <aside class="filters-sidebar animate-slide-right">
        <div class="filters-section">
            <h3>Search Medicines</h3>
            <form action="" method="GET" class="search-form">
                <div class="form-group">
                    <input type="text" name="search" class="form-control"
                        placeholder="Search medicines..."
                        value="<?php echo htmlspecialchars($search); ?>">
                </div>

                <div class="form-group">
                    <label>Category</label>
                    <select name="category" class="form-control">
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
                    <label>Sort By</label>
                    <select name="sort" class="form-control">
                        <option value="name_asc" <?php echo $sort === 'name_asc' ? 'selected' : ''; ?>>Name (A-Z)</option>
                        <option value="name_desc" <?php echo $sort === 'name_desc' ? 'selected' : ''; ?>>Name (Z-A)</option>
                        <option value="price_asc" <?php echo $sort === 'price_asc' ? 'selected' : ''; ?>>Price (Low to High)</option>
                        <option value="price_desc" <?php echo $sort === 'price_desc' ? 'selected' : ''; ?>>Price (High to Low)</option>
                    </select>
                </div>

                <button type="submit" class="btn btn-primary">Apply Filters</button>
                <a href="medicines.php" class="btn btn-secondary">Clear Filters</a>
            </form>
        </div>
    </aside>

    <!-- Medicines Grid -->
    <main class="medicines-grid">
        <?php if (!empty($medicines)): ?>
            <?php foreach ($medicines as $index => $medicine): ?>
                <div class="medicine-card animate-slide-up" style="animation-delay: <?php echo $index * 0.1; ?>s">
                    <?php if ($medicine['image']): ?>
                        <img src="<?php echo BASE_URL; ?>/assets/images/products/<?php echo htmlspecialchars($medicine['image']); ?>"
                            alt="<?php echo htmlspecialchars($medicine['name']); ?>"
                            class="medicine-image">
                    <?php else: ?>
                        <div class="medicine-image placeholder">
                            <i class="fas fa-pills"></i>
                        </div>
                    <?php endif; ?>

                    <div class="medicine-details">
                        <h3><?php echo htmlspecialchars($medicine['name']); ?></h3>
                        <p class="pharmacy-name">
                            <i class="fas fa-store"></i>
                            <?php echo htmlspecialchars($medicine['pharmacy_name']); ?>
                        </p>
                        <p class="category">
                            <i class="fas fa-tag"></i>
                            <?php echo htmlspecialchars($medicine['category_name']); ?>
                        </p>
                        <p class="description"><?php echo htmlspecialchars(substr($medicine['description'], 0, 100)); ?>...</p>
                        <div class="medicine-footer">
                            <span class="price"><?php echo format_currency($medicine['price']); ?></span>
                            <form action="../includes/cart/add_to_cart.inc.php" method="POST" class="add-to-cart-form">
                                <input type="hidden" name="medicine_id" value="<?php echo $medicine['medicine_id']; ?>">
                                <?php if ($medicine['requires_prescription']): ?>
                                    <button type="submit" class="btn btn-primary" name="add_to_cart">
                                        <i class="fas fa-file-medical"></i> Upload Prescription
                                    </button>
                                <?php else: ?>
                                    <button type="submit" class="btn btn-primary" name="add_to_cart">
                                        <i class="fas fa-cart-plus"></i> Add to Cart
                                    </button>
                                <?php endif; ?>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="no-results">
                <i class="fas fa-search"></i>
                <h2>No medicines found</h2>
                <p>Try adjusting your filters or search terms</p>
            </div>
        <?php endif; ?>
    </main>
</div>

<?php include_once '../includes/footer.php'; ?>