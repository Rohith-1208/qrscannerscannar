<?php
// ==============================================================================
// POST /api/systems/create.php - Register new college equipment
// ==============================================================================

require_once __DIR__ . '/../../config/helpers.php';

// Only admins can register new equipment
$auth = requireAuth('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'error' => 'Method not allowed. Use POST.'], 405);
}

$input = getJsonInput();

$systemId = strtoupper(trim($input['system_id'] ?? ''));
$equipmentName = trim($input['equipment_name'] ?? '');
$equipmentType = trim($input['equipment_type'] ?? 'Desktop Computer');
$department = trim($input['department'] ?? '');
$building = trim($input['building'] ?? '');
$roomNumber = trim($input['room_number'] ?? '');
$latitude = isset($input['latitude']) ? (float)$input['latitude'] : null;
$longitude = isset($input['longitude']) ? (float)$input['longitude'] : null;
$allowedRadius = isset($input['allowed_radius']) ? max(1, (int)$input['allowed_radius']) : (int)DEFAULT_ALLOWED_RADIUS_METRES;

// Input Validation
if (empty($systemId) || empty($equipmentName) || empty($department) || empty($roomNumber)) {
    jsonResponse(['success' => false, 'error' => 'System ID, Equipment Name, Department, and Room Number are required.'], 400);
}

if ($latitude === null || $longitude === null || $latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
    jsonResponse(['success' => false, 'error' => 'Valid latitude (-90 to 90) and longitude (-180 to 180) are required.'], 400);
}

$db = Database::getConnection();

// Check unique system_id
$checkStmt = $db->prepare("SELECT system_id FROM systems WHERE system_id = :id LIMIT 1");
$checkStmt->execute([':id' => $systemId]);
if ($checkStmt->fetch()) {
    jsonResponse(['success' => false, 'error' => "System ID '{$systemId}' is already registered."], 409);
}

try {
    $db->beginTransaction();

    // 1. Insert System Record
    $stmt = $db->prepare("
        INSERT INTO systems (system_id, equipment_name, equipment_type, department, building, room_number, latitude, longitude, allowed_radius, status)
        VALUES (:id, :name, :type, :dept, :building, :room, :lat, :lon, :radius, 'working')
    ");
    $stmt->execute([
        ':id'       => $systemId,
        ':name'     => $equipmentName,
        ':type'     => $equipmentType,
        ':dept'     => $department,
        ':building' => $building,
        ':room'     => $roomNumber,
        ':lat'      => $latitude,
        ':lon'      => $longitude,
        ':radius'   => $allowedRadius
    ]);

    // 2. Generate Version 1 QR with HMAC-SHA256 signature
    $qrVersion = 1;
    $signature = generateQrSignature($systemId, $latitude, $longitude, $allowedRadius, $qrVersion);

    $qrStmt = $db->prepare("
        INSERT INTO qr_codes (system_id, qr_version, signature, is_active)
        VALUES (:id, :version, :sig, 1)
    ");
    $qrStmt->execute([
        ':id'      => $systemId,
        ':version' => $qrVersion,
        ':sig'     => $signature
    ]);

    $db->commit();

    jsonResponse([
        'success' => true,
        'message' => "Equipment '{$systemId}' registered successfully.",
        'system'  => [
            'system_id'      => $systemId,
            'equipment_name' => $equipmentName,
            'equipment_type' => $equipmentType,
            'department'     => $department,
            'building'       => $building,
            'room_number'    => $roomNumber,
            'latitude'       => $latitude,
            'longitude'      => $longitude,
            'allowed_radius' => $allowedRadius,
            'status'         => 'working',
            'qr' => [
                'system_id' => $systemId,
                'latitude'  => (float)number_format($latitude, 6, '.', ''),
                'longitude' => (float)number_format($longitude, 6, '.', ''),
                'radius'    => $allowedRadius,
                'version'   => $qrVersion,
                'signature' => $signature
            ]
        ]
    ], 201);

} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    error_log("System registration error: " . $e->getMessage());
    jsonResponse(['success' => false, 'error' => 'Failed to register equipment: ' . $e->getMessage()], 500);
}
