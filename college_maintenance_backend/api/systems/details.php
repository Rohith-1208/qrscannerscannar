<?php
// ==============================================================================
// GET /api/systems/details.php - Fetch equipment details & maintenance history
// ==============================================================================

require_once __DIR__ . '/../../config/helpers.php';

$auth = requireAuth();

$systemId = strtoupper(trim($_GET['system_id'] ?? ''));
if (empty($systemId)) {
    jsonResponse(['success' => false, 'error' => 'system_id query parameter is required.'], 400);
}

$db = Database::getConnection();

// System info
$stmt = $db->prepare("
    SELECT s.*, q.qr_version, q.signature
    FROM systems s
    LEFT JOIN qr_codes q ON s.system_id = q.system_id AND q.is_active = 1
    WHERE s.system_id = :id
    LIMIT 1
");
$stmt->execute([':id' => $systemId]);
$system = $stmt->fetch();

if (!$system) {
    jsonResponse(['success' => false, 'error' => "System '{$systemId}' not found."], 404);
}

// Generate canonical QR payload object
$qrPayload = [
    'system_id' => $system['system_id'],
    'latitude'  => (float)number_format((float)$system['latitude'], 6, '.', ''),
    'longitude' => (float)number_format((float)$system['longitude'], 6, '.', ''),
    'radius'    => (int)$system['allowed_radius'],
    'version'   => (int)($system['qr_version'] ?? 1),
    'signature' => $system['signature'] ?? ''
];

// If signature is missing or inactive, regenerate it
if (empty($system['signature'])) {
    $sig = generateQrSignature(
        $system['system_id'], 
        (float)$system['latitude'], 
        (float)$system['longitude'], 
        (float)$system['allowed_radius'], 
        (int)$system['qr_version']
    );
    $ins = $db->prepare("INSERT INTO qr_codes (system_id, qr_version, signature, is_active) VALUES (:id, :v, :s, 1)");
    $ins->execute([
        ':id' => $system['system_id'],
        ':v'  => (int)$system['qr_version'],
        ':s'  => $sig
    ]);
    $qrPayload['signature'] = $sig;
    $system['signature'] = $sig;
}

// Recent reports for this system
$repStmt = $db->prepare("
    SELECT r.report_id, r.maintenance_date, r.maintenance_time, r.overall_status, a.name AS agent_name
    FROM maintenance_reports r
    JOIN agents a ON r.agent_id = a.agent_id
    WHERE r.system_id = :id
    ORDER BY r.maintenance_date DESC, r.maintenance_time DESC
    LIMIT 10
");
$repStmt->execute([':id' => $systemId]);
$recentReports = $repStmt->fetchAll();

jsonResponse([
    'success'        => true,
    'system'         => $system,
    'qr_payload'     => $qrPayload,
    'recent_reports' => $recentReports
]);
