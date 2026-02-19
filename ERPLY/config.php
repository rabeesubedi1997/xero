<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'erply_sync');

// Erply API configuration
define('ERPLY_CLIENT_CODE', '606950');
define('ERPLY_USERNAME', 'support@retailcare.com.au');
define('ERPLY_PASSWORD', 'NF7c8XUFv0!C');
define('ERPLY_API_URL', 'https://606950.erply.com/api/');

// Connect to database
$connect = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($connect->connect_error) {
    die("Database connection failed: " . $connect->connect_error);
}

// Set charset
$connect->set_charset("utf8mb4");

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
