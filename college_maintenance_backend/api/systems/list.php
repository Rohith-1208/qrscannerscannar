<?php
// ==============================================================================
// GET /api/systems/list.php - List registered college systems
// ==============================================================================

require_once __DIR__ . '/../../config/helpers.php';

// Authentication required
$auth = requireAuth();

$db = Database::getConnection();

$search = trim($_GET['search'] ?? '');
$department = trim($_GET['department'] ?? '');
$status = trim($_GET['status'] ?? '');

$sql = "
    SELECT 
        s.system_id, 
        s.equipment_name, 
        s.equipment_type, 
        s.department, 
        s.building, 
        s.room_number, 
        s.latitude, 
        s.longitude, 
        s.allowed_radius, 
        s.status, 
        s.created_at,
        q.qr_version,
        q.signature
    FROM systems s
    LEFT JOIN qr_codes q ON s.system_id = q.system_id AND q.is_active = 1
    WHERE 1=1
";

$params = [];

if (!empty($search)) {
    $sql .= " AND (s.system_id LIKE :search OR s.equipment_name LIKE :search2 OR s.room_number LIKE :search3)";
    $params[':search']  = "%{$search}%";
    $params[':search2'] = "%{$search}%";
    $params[':search3'] = "%{$search}%";
}

if (!empty($department)) {
    $sql .= " AND s.department = :dept";
    $params[':dept'] = $department;
}

if (!empty($status)) {
    $sql .= " AND s.status = :status";
    $params[':status'] = $status;
}

$sql .= " ORDER BY s.system_id ASC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$systems = $stmt->fetchAll();

jsonResponse([
    'success' => true,
    'count'   => count($systems),
    'systems' => $systems
]);
