<?php
// ==============================================================================
// POST /api/qr/verify.php - Server-side validation of scanned QR payload
// ==============================================================================

require_once __DIR__ . '/../../config/helpers.php';

// Requires authenticated agent or admin
$auth = requireAuth();

$input = getJsonInput();

$systemId  = strtoupper(trim($input['system_id'] ?? ''));
$latitude  = isset($input['latitude']) ? (float)$input['latitude'] : null;
$longitude = isset($input['longitude']) ? (float)$input['longitude'] : null;
$radius    = isset($input['radius']) ? (float)$input['radius'] : null;
$version   = isset($input['version']) ? (int)$input['version'] : null;
$signature = trim($input['signature'] ?? '');

if (empty($systemId) || $latitude === null || $longitude === null || $radius === null || $version === null || empty($signature)) {
    jsonResponse([
        'success'  => false,
        'valid'    => false,
        'error'    => 'Malformed QR payload. Missing required fields.'
    ], 400);
}

// 1. Verify digital signature using backend secret
$isSignatureValid = verifyQrSignature($systemId, $latitude, $longitude, $radius, $version, $signature);
if (!$isSignatureValid) {
    jsonResponse([
        'success'  => false,
        'valid'    => false,
        'error'    => 'Invalid digital signature. QR code is forged or tampered with.'
    ], 403);
}

$db = Database::getConnection();

// 2. Verify system exists in database
$stmt = $db->prepare("SELECT * FROM systems WHERE system_id = :id LIMIT 1");
$stmt->execute([':id' => $systemId]);
$system = $stmt->fetch();

if (!$system) {
    jsonResponse([
        'success' => false,
        'valid'   => false,
        'error'   => "System '{$systemId}' does not exist in college records."
    ], 404);
}

if ($system['status'] === 'disabled') {
    jsonResponse([
        'success' => false,
        'valid'   => false,
        'error'   => "System '{$systemId}' is marked as disabled."
    ], 403);
}

// 3. Verify active QR version
$qrStmt = $db->prepare("SELECT * FROM qr_codes WHERE system_id = :id AND is_active = 1 LIMIT 1");
$qrStmt->execute([':id' => $systemId]);
$activeQr = $qrStmt->fetch();

if (!$activeQr || (int)$activeQr['qr_version'] !== $version) {
    jsonResponse([
        'success' => false,
        'valid'   => false,
        'error'   => 'Expired QR Code. A newer QR code has been issued for this equipment.'
    ], 409);
}

jsonResponse([
    'success' => true,
    'valid'   => true,
    'message' => 'QR payload authenticated and valid.',
    'system'  => [
        'system_id'      => $system['system_id'],
        'equipment_name' => $system['equipment_name'],
        'equipment_type' => $system['equipment_type'],
        'department'     => $system['department'],
        'building'       => $system['building'],
        'room_number'    => $system['room_number'],
        'latitude'       => (float)$system['latitude'],
        'longitude'      => (float)$system['longitude'],
        'allowed_radius' => (float)$system['allowed_radius'],
        'status'         => $system['status']
    ]
]);
