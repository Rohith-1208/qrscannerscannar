<?php
// ==============================================================================
// POST /api/qr/generate.php - Generate or regenerate secure QR payload
// ==============================================================================

require_once __DIR__ . '/../../config/helpers.php';

$auth = requireAuth('admin');

$input = getJsonInput();
$systemId = strtoupper(trim($input['system_id'] ?? ''));
$forceRegenerate = !empty($input['regenerate']);

if (empty($systemId)) {
    jsonResponse(['success' => false, 'error' => 'System ID is required.'], 400);
}

$db = Database::getConnection();

$stmt = $db->prepare("SELECT * FROM systems WHERE system_id = :id LIMIT 1");
$stmt->execute([':id' => $systemId]);
$system = $stmt->fetch();

if (!$system) {
    jsonResponse(['success' => false, 'error' => "System '{$systemId}' not found."], 404);
}

// Check active QR
$qrStmt = $db->prepare("SELECT * FROM qr_codes WHERE system_id = :id AND is_active = 1 LIMIT 1");
$qrStmt->execute([':id' => $systemId]);
$activeQr = $qrStmt->fetch();

if (!$activeQr || $forceRegenerate) {
    // Determine next version
    $maxVStmt = $db->prepare("SELECT MAX(qr_version) AS max_v FROM qr_codes WHERE system_id = :id");
    $maxVStmt->execute([':id' => $systemId]);
    $nextVersion = ((int)($maxVStmt->fetch()['max_v'] ?? 0)) + 1;

    // Deactivate previous
    $db->prepare("UPDATE qr_codes SET is_active = 0 WHERE system_id = :id")->execute([':id' => $systemId]);

    // Compute signature
    $sig = generateQrSignature(
        $systemId,
        (float)$system['latitude'],
        (float)$system['longitude'],
        (float)$system['allowed_radius'],
        $nextVersion
    );

    $ins = $db->prepare("INSERT INTO qr_codes (system_id, qr_version, signature, is_active) VALUES (:id, :v, :s, 1)");
    $ins->execute([
        ':id' => $systemId,
        ':v'  => $nextVersion,
        ':s'  => $sig
    ]);

    $activeQr = [
        'qr_version' => $nextVersion,
        'signature'  => $sig
    ];
}

$payload = [
    'system_id' => $system['system_id'],
    'latitude'  => (float)number_format((float)$system['latitude'], 6, '.', ''),
    'longitude' => (float)number_format((float)$system['longitude'], 6, '.', ''),
    'radius'    => (int)$system['allowed_radius'],
    'version'   => (int)$activeQr['qr_version'],
    'signature' => $activeQr['signature']
];

jsonResponse([
    'success'      => true,
    'system_id'    => $systemId,
    'qr_payload'   => $payload,
    'json_string'  => json_encode($payload, JSON_UNESCAPED_SLASHES)
]);
