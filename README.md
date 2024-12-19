medihub/
├── pharmacy/
│ ├── dashboard.php # Main dashboard
│ ├── inventory.php # Inventory management
│ ├── prescription_review.php # Review prescriptions
│ ├── orders.php # Manage orders
│ └── profile.php # Pharmacy profile
│
├── assets/
│ ├── css/
│ │ ├── pharmacy-dashboard.css
│ │ ├── pharmacy-inventory.css
│ │ ├── pharmacy-prescriptions.css
│ │ ├── pharmacy-orders.css
│ │ └── pharmacy-profile.css
│ └── images/
│ ├── products/ # Medicine images
│ └── prescriptions/ # Prescription images
│
├── includes/
│ ├── inventory/
│ │ ├── medicine_form.inc.php # Add/Edit medicine form
│ │ ├── stock_form.inc.php # Update stock form
│ │ ├── add_medicine.inc.php # Process new medicine
│ │ ├── update_medicine.inc.php # Update medicine details
│ │ └── update_stock.inc.php # Update stock levels
│ │
│ ├── prescriptions/
│ │ ├── review_form.inc.php # Prescription review form
│ │ └── update_status.inc.php # Update prescription status
│ │
│ └── orders/
├── update_status.inc.php # Update order status
└── order_details.inc.php # Order details handler

medihub/
├── admin/
│ ├── dashboard.php
│ ├── manage_users.php
│ ├── manage_pharmacies.php
│ └── manage_categories.php
├── assets/
│ ├── css/
│ └── images/
│ ├── products/
│ ├── profiles/
│ └── prescriptions/
├── config/
│ ├── config.php
│ └── database.php
├── customer/
│ ├── dashboard.php
│ ├── medicines.php
│ ├── cart.php
│ ├── orders.php
│ ├── prescriptions.php
│ └── profile.php
├── pharmacy/
│ ├── dashboard.php
│ ├── inventory.php
│ ├── orders.php
│ ├── profile.php
│ └── reports.php
├── includes/
│ ├── header.php
│ ├── footer.php
│ ├── auth.php
│ └── functions.php
└── public/
├── index.php
├── about.php
├── contact.php
└── login_register.php
