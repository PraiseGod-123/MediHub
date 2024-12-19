<?php
// includes/auth/register.inc.php
session_start();
require_once '../../config/config.php';
require_once '../../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['register'])) {
    // Get and sanitize input
    $first_name = sanitize_input($_POST['first_name']);
    $last_name = sanitize_input($_POST['last_name']);
    $email = sanitize_input($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $role = sanitize_input($_POST['role']);

    $errors = [];

    // Validate input
    if (empty($first_name) || empty($last_name)) {
        $errors[] = "Name fields are required";
    }

    if (!validate_email($email)) {
        $errors[] = "Invalid email format";
    }

    if (!validate_password($password)) {
        $errors[] = "Password must be at least 8 characters long";
    }

    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match";
    }

    if (!in_array($role, ['customer', 'pharmacy'])) {
        $errors[] = "Invalid role selected";
    }

    // Check if email already exists
    try {
        $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->rowCount() > 0) {
            $errors[] = "Email already registered";
        }
    } catch (PDOException $e) {
        error_log($e->getMessage());
        $errors[] = "An error occurred. Please try again.";
    }

    // If there are no errors, proceed with registration
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            // Hash password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            // Insert user
            $stmt = $pdo->prepare("
                INSERT INTO users (first_name, last_name, email, password, role, status)
                VALUES (?, ?, ?, ?, ?, ?)
            ");

            // Set initial status
            $status = ($role === 'pharmacy') ? 'pending' : 'active';

            $stmt->execute([
                $first_name,
                $last_name,
                $email,
                $hashed_password,
                $role,
                $status
            ]);

            $user_id = $pdo->lastInsertId();

            // If registering as pharmacy, add pharmacy details
            if ($role === 'pharmacy') {
                $stmt = $pdo->prepare("
                    INSERT INTO pharmacy_details (pharmacy_id, business_name, license_number)
                    VALUES (?, ?, ?)
                ");

                // Temporary values - these will be updated later
                $stmt->execute([
                    $user_id,
                    $first_name . "'s Pharmacy", // Default business name
                    'PENDING-' . generate_random_string(8) // Temporary license number
                ]);
            }

            $pdo->commit();

            // Set success message
            if ($role === 'pharmacy') {
                $_SESSION['login_success'] = "Registration successful. Please wait for admin approval.";
            } else {
                // Auto-login for customers
                $_SESSION['user_id'] = $user_id;
                $_SESSION['email'] = $email;
                $_SESSION['role'] = $role;
                $_SESSION['name'] = $first_name . ' ' . $last_name;

                header("Location: ../../customer/dashboard.php");
                exit();
            }
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log($e->getMessage());
            $errors[] = "Registration failed. Please try again.";
        }
    }

    if (!empty($errors)) {
        $_SESSION['register_error'] = implode("<br>", $errors);
    }
}

header("Location: ../../public/login_register.php");
exit();
