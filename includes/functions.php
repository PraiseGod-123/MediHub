<?php
// includes/functions.php

/**
 * Sanitize user input
 * @param string $data
 * @return string
 */
function sanitize_input($data)
{
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

/**
 * Validate email
 * @param string $email
 * @return bool
 */
function validate_email($email)
{
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

/**
 * Validate password strength
 * @param string $password
 * @return bool
 */
function validate_password($password)
{
    // At least 8 characters
    return strlen($password) >= 8;
}

/**
 * Check if user is logged in
 * @return bool
 */
function is_logged_in()
{
    return isset($_SESSION['user_id']);
}

/**
 * Check if user has specific role
 * @param string $role
 * @return bool
 */
function has_role($role)
{
    return isset($_SESSION['role']) && $_SESSION['role'] === $role;
}

/**
 * Redirect if not logged in
 * @return void
 */
function require_login()
{
    if (!is_logged_in()) {
        header("Location: " . BASE_URL . "/public/login_register.php");
        exit();
    }
}

/**
 * Redirect if not authorized for role
 * @param string $required_role
 * @return void
 */
function require_role($required_role)
{
    require_login();
    if (!has_role($required_role)) {
        header("Location: " . BASE_URL . "/public/unauthorized.php");
        exit();
    }
}

/**
 * Handle file upload
 * @param array $file
 * @param string $destination
 * @return string|false
 */
function handle_file_upload($file, $destination)
{
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
    $max_size = 5 * 1024 * 1024; // 5MB

    // Validate file
    if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        return false;
    }

    if (!in_array($file['type'], $allowed_types)) {
        return false;
    }

    if ($file['size'] > $max_size) {
        return false;
    }

    // Generate unique filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid() . '.' . $extension;
    $filepath = $destination . '/' . $filename;

    // Move file
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        return $filename;
    }

    return false;
}

/**
 * Display error message
 * @param string $message
 * @return string
 */
function display_error($message)
{
    return "<div class='error-message'>{$message}</div>";
}

/**
 * Display success message
 * @param string $message
 * @return string
 */
function display_success($message)
{
    return "<div class='success-message'>{$message}</div>";
}

/**
 * Format currency
 * @param float $amount
 * @return string
 */
function format_currency($amount)
{
    return '₦' . number_format($amount, 2);
}

/**
 * Get user details
 * @param int $user_id
 * @return array|false
 */
function get_user_details($user_id)
{
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
        $stmt->execute([$user_id]);
        return $stmt->fetch();
    } catch (PDOException $e) {
        error_log($e->getMessage());
        return false;
    }
}

/**
 * Generate random string
 * @param int $length
 * @return string
 */
function generate_random_string($length = 10)
{
    return bin2hex(random_bytes($length));
}
