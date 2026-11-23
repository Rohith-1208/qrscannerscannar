<?php
// ==============================================================================
// GET /api/maintenance/report-details.php - Complete report details
// ==============================================================================

require_once __DIR__ . '/../../config/helpers.php';

$auth = requireAuth();

$reportId = trim($_GET['report_id'] ?? '');
if (empty($reportId)) {
    jsonResponse(['success' => false, 'error' => 'report_id is required.'], 400);
}

$db = Database::getConnection();

// Fetch report header with system, agent, and geo-verification
$stmt = $db->prepare("
    SELECT 
        r.*,
        s.equipment_name,
        s.equipment_type,
        s.department,
        s.building,
        s.room_number,
        s.allowed_radius,
        a.name AS agent_name,
        a.email AS agent_email,
        v.registered_latitude,
        v.registered_longitude,
        v.agent_latitude,
        v.agent_longitude,
        v.calculated_distance,
        v.gps_accuracy,
        v.verification_status,
        v.verified_at
    FROM maintenance_reports r
    JOIN systems s ON r.system_id = s.system_id
    JOIN agents a ON r.agent_id = a.agent_id
    LEFT JOIN location_verifications v ON r.report_id = v.report_id
    WHERE r.report_id = :id
    LIMIT 1
");
$stmt->execute([':id' => $reportId]);
$report = $stmt->fetch();

if (!$report) {
    jsonResponse(['success' => false, 'error' => "Report '{$reportId}' not found."], 404);
}

// Security: Agents must only be able to access their own reports
if ($auth['user_type'] === 'agent' && $auth['user_id'] !== $report['agent_id']) {
    jsonResponse(['success' => false, 'error' => 'Access denied: You can only view your own maintenance reports.'], 403);
}

// Fetch answers and questions
$ansStmt = $db->prepare("
    SELECT 
        a.answer_id,
        a.question_id,
        q.question_text,
        a.answer,
        a.issue_description
    FROM maintenance_answers a
    JOIN checklist_questions q ON a.question_id = q.question_id
    WHERE a.report_id = :id
    ORDER BY q.sort_order ASC
");
$ansStmt->execute([':id' => $reportId]);
$answers = $ansStmt->fetchAll();

jsonResponse([
    'success' => true,
    'report'  => $report,
    'answers' => $answers
]);
