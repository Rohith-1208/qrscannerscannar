<?php
// ==============================================================================
// GET /api/agents/list.php - List all service agents (Admin only)
// ==============================================================================

require_once __DIR__ . '/../../config/helpers.php';

$auth = requireAuth('admin');

$db = Database::getConnection();

$stmt = $db->prepare("
    SELECT 
        a.agent_id, 
        a.name, 
        a.email, 
        a.status, 
        a.created_at,
        COUNT(r.report_id) AS total_reports
    FROM agents a
    LEFT JOIN maintenance_reports r ON a.agent_id = r.agent_id
    GROUP BY a.agent_id
    ORDER BY a.created_at DESC
");
$stmt->execute();
$agents = $stmt->fetchAll();

jsonResponse([
    'success' => true,
    'count'   => count($agents),
    'agents'  => $agents
]);
