<?php
// ==============================================================================
// POST /api/auth/logout.php - Invalidate Session Token
// ==============================================================================

require_once __DIR__ . '/../../config/helpers.php';

$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
if (empty($authHeader) && function_exists('apache_request_headers')) {
    $headers = apache_request_headers();
    $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
}

if (!empty($authHeader) && preg_match('/Bearer\s+(\S+)/i', $authHeader, $matches)) {
    $tokenHash = hash('sha256', $matches[1]);
    $db = Database::getConnection();
    $stmt = $db->prepare("DELETE FROM auth_tokens WHERE token_hash = :hash");
    $stmt->execute([':hash' => $tokenHash]);
}

jsonResponse([
    'success' => true,
    'message' => 'Logged out successfully'
]);
