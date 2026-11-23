<?php
// ==============================================================================
// Admin Session Guard
// ==============================================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';

function checkAdminSession(): array {
    if (empty($_SESSION['admin_user'])) {
        header('Location: login.php');
        exit;
    }
    return $_SESSION['admin_user'];
}

function getAdminDb(): PDO {
    return Database::getConnection();
}
