<?php
// ==============================================================================
// GET /api/maintenance/all-reports.php - Admin all maintenance reports with filters
// ==============================================================================

require_once __DIR__ . '/../../config/helpers.php';

$auth = requireAuth('admin');

$db = Database::getConnection();

$fromDate   = trim($_GET['from_date'] ?? '');
$toDate     = trim($_GET['to_date'] ?? '');
$department = trim($_GET['department'] ?? '');
$systemId   = trim($_GET['system_id'] ?? '');
$agentId    = trim($_GET['agent_id'] ?? '');
$status     = trim($_GET['status'] ?? '');

$sql = "
    SELECT 
        r.report_id,
        r.system_id,
        s.equipment_name,
        s.department,
        s.room_number,
        r.agent_id,
        a.name AS agent_name,
        r.maintenance_date,
        r.maintenance_time,
        r.overall_status,
        v.verification_status,
        v.calculated_distance,
        v.gps_accuracy,
        r.created_at
    FROM maintenance_reports r
    JOIN systems s ON r.system_id = s.system_id
    JOIN agents a ON r.agent_id = a.agent_id
    LEFT JOIN location_verifications v ON r.report_id = v.report_id
    WHERE 1=1
";

$params = [];

if (!empty($fromDate)) {
    $sql .= " AND r.maintenance_date >= :from_date";
    $params[':from_date'] = $fromDate;
}

if (!empty($toDate)) {
    $sql .= " AND r.maintenance_date <= :to_date";
    $params[':to_date'] = $toDate;
}

if (!empty($department)) {
    $sql .= " AND s.department = :dept";
    $params[':dept'] = $department;
}

if (!empty($systemId)) {
    $sql .= " AND (r.system_id LIKE :sys OR s.equipment_name LIKE :sys2)";
    $params[':sys']  = "%{$systemId}%";
    $params[':sys2'] = "%{$systemId}%";
}

if (!empty($agentId)) {
    $sql .= " AND (r.agent_id = :agt OR a.name LIKE :agt2)";
    $params[':agt']  = $agentId;
    $params[':agt2'] = "%{$agentId}%";
}

if (!empty($status)) {
    $sql .= " AND r.overall_status = :status";
    $params[':status'] = $status;
}

$sql .= " ORDER BY r.maintenance_date DESC, r.maintenance_time DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$reports = $stmt->fetchAll();

jsonResponse([
    'success' => true,
    'count'   => count($reports),
    'reports' => $reports
]);
