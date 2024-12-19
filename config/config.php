<?php
// config/config.php

// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'praisegod.osiagor');
define('DB_PASS', 'Amara@2004');
define('DB_NAME', 'webtech_fall2024_praisegod_osiagor');

// Path constants
define('BASE_URL', 'http://169.254.169.254/~praisegod.osiagor/medihub');
define('UPLOAD_PATH', $_SERVER['DOCUMENT_ROOT'] . '/medihub/assets/images/');

// Create database connection
try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
} catch (PDOException $e) {
    error_log("Connection failed: " . $e->getMessage());
    die("Sorry, there was a problem connecting to the database.");
}

// Session configuration
session_start();
ini_set('session.use_only_cookies', 1);
ini_set('session.use_strict_mode', 1);

// Set session cookie parameters
session_set_cookie_params([
    'lifetime' => 3600,
    'path' => '/',
    'domain' => '',
    'secure' => true,
    'httponly' => true,
    'samesite' => 'Strict'
]);
