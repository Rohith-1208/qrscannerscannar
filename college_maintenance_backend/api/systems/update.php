<?php
// ==============================================================================
// POST or PUT /api/systems/update.php - Update equipment & coordinates
// ==============================================================================

require_once __DIR__ . '/../../config/helpers.php';

// Only admins can update systems
$auth = requireAuth('admin');

$input = getJsonInput();

$systemId = strtoupper(trim($input['system_id'] ?? ''));
if (empty($systemId)) {
    jsonResponse(['success' => false, 'error' => 'System ID is required for update.'], 400);
}

$db = Database::getConnection();

// Fetch existing system
$stmt = $db->prepare("SELECT * FROM systems WHERE system_id = :id LIMIT 1");
$stmt->execute([':id' => $systemId]);
$existing = $stmt->fetch();

if (!$existing) {
    jsonResponse(['success' => false, 'error' => "System '{$systemId}' not found."], 404);
}

// Editable fields
$equipmentName = trim($input['equipment_name'] ?? $existing['equipment_name']);
$equipmentType = trim($input['equipment_type'] ?? $existing['equipment_type']);
$department    = trim($input['department'] ?? $existing['department']);
$building      = trim($input['building'] ?? $existing['building']);
$roomNumber    = trim($input['room_number'] ?? $existing['room_number']);
$status        = trim($input['status'] ?? $existing['status']);

$newLat = isset($input['latitude']) ? (float)$input['latitude'] : (float)$existing['latitude'];
$newLon = isset($input['longitude']) ? (float)$input['longitude'] : (float)$existing['longitude'];
$newRadius = isset($input['allowed_radius']) ? max(1, (int)$input['allowed_radius']) : (int)$existing['allowed_radius'];

// Check if coordinates or radius changed (requires generating new QR version and invalidating old)
$coordsChanged = (
    abs($newLat - (float)$existing['latitude']) > 0.000001 ||
    abs($newLon - (float)$existing['longitude']) > 0.000001 ||
    $newRadius !== (int)$existing['allowed_radius']
);

try {
    $db->beginTransaction();

    // 1. Update systems table
    $upStmt = $db->prepare("
        UPDATE systems 
        SET equipment_name = :name,
            equipment_type = :type,
            department = :dept,
            building = :building,
            room_number = :room,
            latitude = :lat,
            longitude = :lon,
            allowed_radius = :radius,
            status = :status,
            updated_at = NOW()
        WHERE system_id = :id
    ");
    $upStmt->execute([
        ':name'     => $equipmentName,
        ':type'     => $equipmentType,
        ':dept'     => $department,
        ':building' => $building,
        ':room'     => $roomNumber,
        ':lat'      => $newLat,
        ':lon'      => $newLon,
        ':radius'   => $newRadius,
        ':status'   => $status,
        ':id'       => $systemId
    ]);

    $newQrData = null;

    if ($coordsChanged) {
        // Find highest existing QR version
        $vStmt = $db->prepare("SELECT MAX(qr_version) AS max_v FROM qr_codes WHERE system_id = :id");
        $vStmt->execute([':id' => $systemId]);
        $maxV = (int)($vStmt->fetch()['max_v'] ?? 0);
        $nextVersion = $maxV + 1;

        // Invalidate old active QR codes
        $db->prepare("UPDATE qr_codes SET is_active = 0 WHERE system_id = :id")->execute([':id' => $systemId]);

        // Generate new signature
        $signature = generateQrSignature($systemId, $newLat, $newLon, $newRadius, $nextVersion);

        // Insert new active QR record
        $insQr = $db->prepare("
            INSERT INTO qr_codes (system_id, qr_version, signature, is_active)
            VALUES (:id, :version, :sig, 1)
        ");
        $insQr->execute([
            ':id'      => $systemId,
            ':version' => $nextVersion,
            ':sig'     => $signature
        ]);

        $newQrData = [
            'system_id' => $systemId,
            'latitude'  => (float)number_format($newLat, 6, '.', ''),
            'longitude' => (float)number_format($newLon, 6, '.', ''),
            'radius'    => $newRadius,
            'version'   => $nextVersion,
            'signature' => $signature
        ];
    }

    $db->commit();

    jsonResponse([
        'success'           => true,
        'message'           => 'Equipment details updated successfully.',
        'coordinate_change' => $coordsChanged,
        'new_qr'            => $newQrData
    ]);

} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    error_log("Update error: " . $e->getMessage());
    jsonResponse(['success' => false, 'error' => 'Failed to update system: ' . $e->getMessage()], 500);
}
