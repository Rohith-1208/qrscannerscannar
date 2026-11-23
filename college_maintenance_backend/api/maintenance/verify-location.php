<?php
// ==============================================================================
// POST /api/maintenance/verify-location.php - Server-side Location & QR Pre-check
// ==============================================================================

require_once __DIR__ . '/../../config/helpers.php';

$auth = requireAuth('agent');

$input = getJsonInput();

$systemId       = strtoupper(trim($input['system_id'] ?? ''));
$agentLatitude  = isset($input['agent_latitude']) ? (float)$input['agent_latitude'] : null;
$agentLongitude = isset($input['agent_longitude']) ? (float)$input['agent_longitude'] : null;
$gpsAccuracy    = isset($input['gps_accuracy']) ? (float)$input['gps_accuracy'] : null;
$qrSignature    = trim($input['qr_signature'] ?? '');
$qrVersion      = isset($input['qr_version']) ? (int)$input['qr_version'] : 1;

if (empty($systemId) || $agentLatitude === null || $agentLongitude === null || $gpsAccuracy === null) {
    jsonResponse(['success' => false, 'error' => 'system_id, agent_latitude, agent_longitude, and gps_accuracy are required.'], 400);
}

// 1. Check GPS Accuracy
if ($gpsAccuracy > MAX_ACCEPTABLE_GPS_ACCURACY_METRES) {
    jsonResponse([
        'success'             => false,
        'verified'            => false,
        'accuracy_insufficient' => true,
        'error'               => 'GPS signal accuracy is insufficient. Please move away from obstructions and retry location reading.'
    ], 200);
}

$db = Database::getConnection();

// 2. Fetch System & Active QR
$stmt = $db->prepare("
    SELECT s.*, q.signature, q.qr_version AS active_qr_version
    FROM systems s
    LEFT JOIN qr_codes q ON s.system_id = q.system_id AND q.is_active = 1
    WHERE s.system_id = :id
    LIMIT 1
");
$stmt->execute([':id' => $systemId]);
$system = $stmt->fetch();

if (!$system) {
    jsonResponse(['success' => false, 'verified' => false, 'error' => "System '{$systemId}' not found."], 404);
}

// 3. Verify Signature if provided
if (!empty($qrSignature)) {
    $sigValid = verifyQrSignature(
        $systemId,
        (float)$system['latitude'],
        (float)$system['longitude'],
        (float)$system['allowed_radius'],
        $qrVersion,
        $qrSignature
    );
    if (!$sigValid) {
        jsonResponse(['success' => false, 'verified' => false, 'error' => 'Invalid or forged QR signature.'], 403);
    }
}

// 4. Calculate Distance using Haversine
$regLat = (float)$system['latitude'];
$regLon = (float)$system['longitude'];
$allowedRadius = (float)$system['allowed_radius'];

$distance = calculateHaversineDistance($regLat, $regLon, $agentLatitude, $agentLongitude);

// Check proximity
if ($distance <= $allowedRadius) {
    jsonResponse([
        'success'             => true,
        'verified'            => true,
        'system_id'           => $systemId,
        'equipment_name'      => $system['equipment_name'],
        'room_number'         => $system['room_number'],
        'department'          => $system['department'],
        'equipment_type'      => $system['equipment_type'],
        'allowed_radius'      => $allowedRadius
    ]);
} else {
    // When distance > allowed radius, per Rule 6, mobile UI shows ONLY: "Not in correct location"
    jsonResponse([
        'success'  => false,
        'verified' => false,
        'error'    => 'Not in correct location'
    ], 200);
}
