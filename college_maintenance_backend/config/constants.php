<?php
// ==============================================================================
// Application Constants & Security Configuration
// ==============================================================================

// Enforce strict typing & error reporting in dev
error_reporting(E_ALL);
ini_set('display_errors', 0); // Hide raw errors from public outputs in production
ini_set('log_errors', 1);

// Application Identity
define('APP_NAME', 'College System Maintenance Management');
define('APP_VERSION', '1.0.0');

// Database Credentials (Default XAMPP setup: root with no password)
define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_NAME', getenv('DB_NAME') ?: 'college_maintenance_db');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') !== false ? getenv('DB_PASS') : '');
define('DB_CHARSET', 'utf8mb4');

// Cryptographic Secret for QR HMAC-SHA256 Signatures
// IMPORTANT: Never expose this key in client-side code or public repos
define('QR_HMAC_SECRET_KEY', getenv('QR_HMAC_SECRET') ?: 'c0ll3g3_m41nt_s3cur3_hm4c_s1gn1ng_k3y_#2026!');

// Business Logic Rules
define('DEFAULT_ALLOWED_RADIUS_METRES', 10.0);
define('MAX_ACCEPTABLE_GPS_ACCURACY_METRES', 25.0); // GPS reading must be within 25m accuracy to be reliable
define('TOKEN_EXPIRY_DAYS', 30);

// Helper function to output standard JSON response and terminate
function jsonResponse(array $data, int $statusCode = 200): void {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=UTF-8');
    // Allow restricted CORS (or allow local dev hosts)
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
    
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

// Global CORS preflight handler
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
    exit;
}
