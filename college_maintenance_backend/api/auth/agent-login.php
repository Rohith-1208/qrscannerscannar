<?php
// ==============================================================================
// POST /api/auth/agent-login.php - Service Agent Login
// ==============================================================================

require_once __DIR__ . '/../../config/helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'error' => 'Method not allowed. Use POST.'], 405);
}

$input = getJsonInput();

$login = trim($input['login'] ?? $input['email'] ?? $input['agent_id'] ?? '');
$password = (string)($input['password'] ?? '');

if (empty($login) || empty($password)) {
    jsonResponse(['success' => false, 'error' => 'Agent ID/Email and password are required.'], 400);
}

$db = Database::getConnection();

// Agent can log in with either Agent ID or registered Email
$stmt = $db->prepare("
    SELECT agent_id, name, email, password_hash, status 
    FROM agents 
    WHERE (agent_id = :login OR email = :email) 
    LIMIT 1
");
$stmt->execute([
    ':login' => $login,
    ':email' => $login
]);
$agent = $stmt->fetch();

if (!$agent || !password_verify($password, $agent['password_hash'])) {
    jsonResponse(['success' => false, 'error' => 'Invalid credentials. Please verify your Agent ID and password.'], 401);
}

if ($agent['status'] !== 'active') {
    jsonResponse(['success' => false, 'error' => 'Account is currently inactive. Contact your college system administrator.'], 403);
}

// Generate secure API session token
$token = createAuthToken($agent['agent_id'], 'agent');

jsonResponse([
    'success' => true,
    'message' => 'Login successful',
    'token'   => $token,
    'agent'   => [
        'agent_id' => $agent['agent_id'],
        'name'     => $agent['name'],
        'email'    => $agent['email'],
        'status'   => $agent['status']
    ]
]);
