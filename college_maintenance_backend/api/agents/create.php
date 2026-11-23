<?php
// ==============================================================================
// POST /api/agents/create.php - Register new Service Agent (Admin only)
// ==============================================================================

require_once __DIR__ . '/../../config/helpers.php';

$auth = requireAuth('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'error' => 'Method not allowed. Use POST.'], 405);
}

$input = getJsonInput();

$agentId = strtoupper(trim($input['agent_id'] ?? ''));
$name    = trim($input['name'] ?? '');
$email   = strtolower(trim($input['email'] ?? ''));
$password = (string)($input['password'] ?? '');

if (empty($name) || empty($email) || empty($password)) {
    jsonResponse(['success' => false, 'error' => 'Agent Name, Email, and Password are required.'], 400);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    jsonResponse(['success' => false, 'error' => 'Invalid email address format.'], 400);
}

if (strlen($password) < 6) {
    jsonResponse(['success' => false, 'error' => 'Password must be at least 6 characters.'], 400);
}

$db = Database::getConnection();

// Auto-generate Agent ID if not provided
if (empty($agentId)) {
    $cStmt = $db->query("SELECT MAX(CAST(SUBSTRING(agent_id, 5) AS UNSIGNED)) AS max_id FROM agents WHERE agent_id LIKE 'AGT-%'");
    $maxVal = (int)($cStmt->fetch()['max_id'] ?? 0);
    $agentId = sprintf("AGT-%03d", $maxVal + 1);
} else {
    // Check collision
    $chk = $db->prepare("SELECT agent_id FROM agents WHERE agent_id = :id LIMIT 1");
    $chk->execute([':id' => $agentId]);
    if ($chk->fetch()) {
        jsonResponse(['success' => false, 'error' => "Agent ID '{$agentId}' already exists."], 409);
    }
}

// Check email uniqueness
$eChk = $db->prepare("SELECT agent_id FROM agents WHERE email = :email LIMIT 1");
$eChk->execute([':email' => $email]);
if ($eChk->fetch()) {
    jsonResponse(['success' => false, 'error' => "Email '{$email}' is already registered."], 409);
}

$passwordHash = password_hash($password, PASSWORD_BCRYPT);

$stmt = $db->prepare("
    INSERT INTO agents (agent_id, name, email, password_hash, status)
    VALUES (:id, :name, :email, :hash, 'active')
");
$stmt->execute([
    ':id'    => $agentId,
    ':name'  => $name,
    ':email' => $email,
    ':hash'  => $passwordHash
]);

jsonResponse([
    'success' => true,
    'message' => "Service Agent '{$name}' registered successfully.",
    'agent'   => [
        'agent_id' => $agentId,
        'name'     => $name,
        'email'    => $email,
        'status'   => 'active'
    ]
], 201);
