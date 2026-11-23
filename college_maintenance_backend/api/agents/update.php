<?php
// ==============================================================================
// POST /api/agents/update.php - Update Agent Profile / Status / Password
// ==============================================================================

require_once __DIR__ . '/../../config/helpers.php';

$auth = requireAuth('admin');

$input = getJsonInput();

$agentId = strtoupper(trim($input['agent_id'] ?? ''));
if (empty($agentId)) {
    jsonResponse(['success' => false, 'error' => 'agent_id is required.'], 400);
}

$db = Database::getConnection();

$stmt = $db->prepare("SELECT * FROM agents WHERE agent_id = :id LIMIT 1");
$stmt->execute([':id' => $agentId]);
$agent = $stmt->fetch();

if (!$agent) {
    jsonResponse(['success' => false, 'error' => "Agent '{$agentId}' not found."], 404);
}

$name   = trim($input['name'] ?? $agent['name']);
$status = trim($input['status'] ?? $agent['status']);
$password = trim($input['password'] ?? '');

$updates = [
    'name'   => $name,
    'status' => $status
];

$sql = "UPDATE agents SET name = :name, status = :status";
$params = [
    ':name'   => $name,
    ':status' => $status,
    ':id'     => $agentId
];

if (!empty($password)) {
    if (strlen($password) < 6) {
        jsonResponse(['success' => false, 'error' => 'Password must be at least 6 characters.'], 400);
    }
    $sql .= ", password_hash = :hash";
    $params[':hash'] = password_hash($password, PASSWORD_BCRYPT);
}

$sql .= " WHERE agent_id = :id";

$upStmt = $db->prepare($sql);
$upStmt->execute($params);

jsonResponse([
    'success' => true,
    'message' => "Agent '{$agentId}' updated successfully."
]);
