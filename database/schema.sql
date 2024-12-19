-- Create database
CREATE DATABASE IF NOT EXISTS medihub;
USE medihub;

-- Users table for all user types (customers, pharmacies, admins)
CREATE TABLE users (
user_id INT PRIMARY KEY AUTO_INCREMENT,
email VARCHAR(255) UNIQUE NOT NULL,
password VARCHAR(255) NOT NULL,
first_name VARCHAR(100) NOT NULL,
last_name VARCHAR(100) NOT NULL,
role ENUM('customer', 'pharmacy', 'admin') NOT NULL,
profile_image VARCHAR(255),
phone VARCHAR(20),
address TEXT,
created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
status ENUM('active', 'inactive', 'pending') DEFAULT 'active',
last_login TIMESTAMP NULL
);

-- Pharmacy details (extends users table for pharmacy-specific info)
CREATE TABLE pharmacy_details (
pharmacy_id INT PRIMARY KEY,
business_name VARCHAR(255) NOT NULL,
license_number VARCHAR(100) UNIQUE NOT NULL,
business_phone VARCHAR(20),
business_address TEXT,
operating_hours TEXT,
FOREIGN KEY (pharmacy_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- Categories for medicines
CREATE TABLE categories (
category_id INT PRIMARY KEY AUTO_INCREMENT,
name VARCHAR(100) NOT NULL,
description TEXT,
created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Medicines/Products
CREATE TABLE medicines (
medicine_id INT PRIMARY KEY AUTO_INCREMENT,
pharmacy_id INT,
category_id INT,
name VARCHAR(255) NOT NULL,
description TEXT,
price DECIMAL(10,2) NOT NULL,
stock_quantity INT NOT NULL,
requires_prescription BOOLEAN DEFAULT FALSE,
image VARCHAR(255),
status ENUM('available', 'out_of_stock', 'discontinued') DEFAULT 'available',
created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
FOREIGN KEY (pharmacy_id) REFERENCES pharmacy_details(pharmacy_id) ON DELETE CASCADE,
FOREIGN KEY (category_id) REFERENCES categories(category_id) ON DELETE SET NULL
);

-- Shopping Cart
CREATE TABLE cart_items (
cart_id INT PRIMARY KEY AUTO_INCREMENT,
user_id INT,
medicine_id INT,
quantity INT NOT NULL,
added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
FOREIGN KEY (medicine_id) REFERENCES medicines(medicine_id) ON DELETE CASCADE
);

-- Orders
CREATE TABLE orders (
order_id INT PRIMARY KEY AUTO_INCREMENT,
user_id INT,
pharmacy_id INT,
total_amount DECIMAL(10,2) NOT NULL,
status ENUM('pending', 'confirmed', 'processing', 'ready', 'completed', 'cancelled') DEFAULT 'pending',
created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
updated_at TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL,
FOREIGN KEY (pharmacy_id) REFERENCES pharmacy_details(pharmacy_id) ON DELETE SET NULL
);

-- Order Items
CREATE TABLE order_items (
order_item_id INT PRIMARY KEY AUTO_INCREMENT,
order_id INT,
medicine_id INT,
quantity INT NOT NULL,
price_per_unit DECIMAL(10,2) NOT NULL,
FOREIGN KEY (order_id) REFERENCES orders(order_id) ON DELETE CASCADE,
FOREIGN KEY (medicine_id) REFERENCES medicines(medicine_id) ON DELETE SET NULL
);

-- Prescriptions
CREATE TABLE prescriptions (
prescription_id INT PRIMARY KEY AUTO_INCREMENT,
user_id INT,
order_id INT,
image VARCHAR(255) NOT NULL,
status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
FOREIGN KEY (order_id) REFERENCES orders(order_id) ON DELETE CASCADE
);

-- Add stock history table
CREATE TABLE stock_history (
    history_id INT PRIMARY KEY AUTO_INCREMENT,
    medicine_id INT,
    operation ENUM('add', 'subtract') NOT NULL,
    quantity INT NOT NULL,
    reason VARCHAR(100) NOT NULL,
    notes TEXT,
    updated_by INT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (medicine_id) REFERENCES medicines(medicine_id) ON DELETE CASCADE,
    FOREIGN KEY (updated_by) REFERENCES users(user_id) ON DELETE SET NULL
);

-- Reviews
CREATE TABLE reviews (
review_id INT PRIMARY KEY AUTO_INCREMENT,
user_id INT,
medicine_id INT,
rating INT CHECK (rating >= 1 AND rating <= 5),
    comment TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL,
    FOREIGN KEY (medicine_id) REFERENCES medicines(medicine_id) ON DELETE CASCADE
    );

    -- Add indexes for better performance
    CREATE INDEX idx_medicines_pharmacy ON medicines(pharmacy_id);
    CREATE INDEX idx_medicines_category ON medicines(category_id);
    CREATE INDEX idx_orders_user ON orders(user_id);
    CREATE INDEX idx_orders_pharmacy ON orders(pharmacy_id);
    CREATE INDEX idx_cart_user ON cart_items(user_id);
    CREATE INDEX idx_reviews_medicine ON reviews(medicine_id);

    -- Insert default admin user (password: admin123)
    INSERT INTO users (email, password, first_name, last_name, role, status)
    VALUES ('admin@medihub.com', '$2y$10$8WjSGZQsQO1yF1c5byBzwehwjHPKUyZdS5nUAxgE3sEJ3Pf7OKU.K' , 'Admin' , 'User' , 'admin' , 'active' );

    -- Insert some default categories
    INSERT INTO categories (name, description) VALUES
    ('Pain Relief', 'Medications for pain management' ),
    ('Antibiotics', 'Medicines that fight bacterial infections' ),
    ('Vitamins', 'Nutritional supplements' ),
    ('First Aid', 'Basic medical supplies' ),
    ('Chronic Care', 'Medications for chronic conditions' );