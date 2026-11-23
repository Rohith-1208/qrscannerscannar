<?php
// ==============================================================================
// POST /api/auth/admin-login.php - College Administrator Login
// ==============================================================================

require_once __DIR__ . '/../../config/helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'error' => 'Method not allowed. Use POST.'], 405);
}

$input = getJsonInput();

$email = trim($input['email'] ?? '');
$password = (string)($input['password'] ?? '');

if (empty($email) || empty($password)) {
    jsonResponse(['success' => false, 'error' => 'Admin email and password are required.'], 400);
}

$db = Database::getConnection();

$stmt = $db->prepare("SELECT admin_id, name, email, password_hash FROM admins WHERE email = :email LIMIT 1");
$stmt->execute([':email' => $email]);
$admin = $stmt->fetch();

if (!$admin || !password_verify($password, $admin['password_hash'])) {
    jsonResponse(['success' => false, 'error' => 'Invalid email or password.'], 401);
}

// Generate token
$token = createAuthToken((string)$admin['admin_id'], 'admin');

jsonResponse([
    'success' => true,
    'message' => 'Admin authentication successful',
    'token'   => $token,
    'admin'   => [
        'admin_id' => $admin['admin_id'],
        'name'     => $admin['name'],
        'email'    => $admin['email']
    ]
]);
