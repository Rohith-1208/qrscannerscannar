<?php
// ==============================================================================
// GET /api/maintenance/checklist-questions.php - Get checklist questions
// ==============================================================================

require_once __DIR__ . '/../../config/helpers.php';

$auth = requireAuth();

$equipmentType = trim($_GET['equipment_type'] ?? 'Desktop Computer');

$db = Database::getConnection();

$stmt = $db->prepare("
    SELECT q.question_id, q.question_text, q.sort_order, q.is_required
    FROM checklist_questions q
    JOIN checklist_templates t ON q.template_id = t.template_id
    WHERE t.equipment_type = :type AND t.status = 'active'
    ORDER BY q.sort_order ASC
");
$stmt->execute([':type' => $equipmentType]);
$questions = $stmt->fetchAll();

// Fallback to template 1 if specific type doesn't have custom questions
if (empty($questions)) {
    $fb = $db->query("
        SELECT question_id, question_text, sort_order, is_required
        FROM checklist_questions
        WHERE template_id = 1
        ORDER BY sort_order ASC
    ");
    $questions = $fb->fetchAll();
}

jsonResponse([
    'success'        => true,
    'equipment_type' => $equipmentType,
    'questions'      => $questions
]);
