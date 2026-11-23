<?php
// ==============================================================================
// GET /api/maintenance/agent-reports.php - Agent's personal report history
// ==============================================================================

require_once __DIR__ . '/../../config/helpers.php';

$auth = requireAuth('agent');
$agentId = $auth['user_id'];

$db = Database::getConnection();

$stmt = $db->prepare("
    SELECT 
        r.report_id,
        r.system_id,
        s.equipment_name,
        s.department,
        s.room_number,
        r.maintenance_date,
        r.maintenance_time,
        r.overall_status,
        r.created_at,
        v.verification_status,
        v.calculated_distance
    FROM maintenance_reports r
    JOIN systems s ON r.system_id = s.system_id
    LEFT JOIN location_verifications v ON r.report_id = v.report_id
    WHERE r.agent_id = :agent_id
    ORDER BY r.maintenance_date DESC, r.maintenance_time DESC
");
$stmt->execute([':agent_id' => $agentId]);
$reports = $stmt->fetchAll();

jsonResponse([
    'success' => true,
    'count'   => count($reports),
    'reports' => $reports
]);
