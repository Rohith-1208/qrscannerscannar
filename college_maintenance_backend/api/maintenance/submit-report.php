<?php
// ==============================================================================
// POST /api/maintenance/submit-report.php - Submit Maintenance Checklist & Report
// ==============================================================================

require_once __DIR__ . '/../../config/helpers.php';

// Only authenticated agents can submit maintenance reports
$auth = requireAuth('agent');
$agentId = $auth['user_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'error' => 'Method not allowed. Use POST.'], 405);
}

$input = getJsonInput();

$systemId       = strtoupper(trim($input['system_id'] ?? ''));
$agentLatitude  = isset($input['agent_latitude']) ? (float)$input['agent_latitude'] : null;
$agentLongitude = isset($input['agent_longitude']) ? (float)$input['agent_longitude'] : null;
$gpsAccuracy    = isset($input['gps_accuracy']) ? (float)$input['gps_accuracy'] : null;
$qrSignature    = trim($input['qr_signature'] ?? '');
$qrVersion      = isset($input['qr_version']) ? (int)$input['qr_version'] : 1;
$answers        = $input['answers'] ?? [];
$notes          = trim($input['notes'] ?? '');

// 1. Basic validation
if (empty($systemId) || $agentLatitude === null || $agentLongitude === null || $gpsAccuracy === null) {
    jsonResponse(['success' => false, 'error' => 'System ID, GPS coordinates, and accuracy are required.'], 400);
}

if (!is_array($answers) || empty($answers)) {
    jsonResponse(['success' => false, 'error' => 'Maintenance checklist answers cannot be empty.'], 400);
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
    jsonResponse(['success' => false, 'error' => "System '{$systemId}' not found."], 404);
}

// 3. Security: Independent Server-Side QR Signature Verification
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
        jsonResponse(['success' => false, 'error' => 'Server rejection: Invalid digital signature on QR payload.'], 403);
    }
}

// 4. Security: Independent Server-Side Haversine Verification
if ($gpsAccuracy > MAX_ACCEPTABLE_GPS_ACCURACY_METRES) {
    jsonResponse(['success' => false, 'error' => 'Server rejection: GPS accuracy is insufficient for verified submission.'], 400);
}

$regLat = (float)$system['latitude'];
$regLon = (float)$system['longitude'];
$allowedRadius = (float)$system['allowed_radius'];

$calculatedDistance = calculateHaversineDistance($regLat, $regLon, $agentLatitude, $agentLongitude);

if ($calculatedDistance > $allowedRadius) {
    // Distance check failed on server
    jsonResponse(['success' => false, 'error' => 'Not in correct location'], 403);
}

// 5. Validate Checklist Answers
$qStmt = $db->prepare("
    SELECT q.question_id, q.question_text, q.is_required
    FROM checklist_questions q
    JOIN checklist_templates t ON q.template_id = t.template_id
    WHERE t.equipment_type = :type AND t.status = 'active'
");
$qStmt->execute([':type' => $system['equipment_type']]);
$mandatoryQuestions = $qStmt->fetchAll();

if (empty($mandatoryQuestions)) {
    $mandatoryQuestions = $db->query("SELECT question_id, question_text, is_required FROM checklist_questions WHERE template_id = 1")->fetchAll();
}

$submittedAnswerMap = [];
foreach ($answers as $ans) {
    if (isset($ans['question_id'])) {
        $submittedAnswerMap[(int)$ans['question_id']] = $ans;
    }
}

$hasIssues = false;
$validatedAnswers = [];

foreach ($mandatoryQuestions as $mq) {
    $qid = (int)$mq['question_id'];
    if (!isset($submittedAnswerMap[$qid])) {
        jsonResponse(['success' => false, 'error' => "Missing answer for mandatory question: '{$mq['question_text']}'."], 400);
    }

    $entry = $submittedAnswerMap[$qid];
    $ansVal = strtoupper(trim($entry['answer'] ?? ''));
    $issueDesc = trim($entry['issue_description'] ?? '');

    if ($ansVal !== 'YES' && $ansVal !== 'NO') {
        jsonResponse(['success' => false, 'error' => "Answer must be 'YES' or 'NO' for question ID {$qid}."], 400);
    }

    // Rule: If answer is NO, issue description is required
    if ($ansVal === 'NO') {
        $hasIssues = true;
        if (empty($issueDesc)) {
            jsonResponse(['success' => false, 'error' => "Issue description is required for question: '{$mq['question_text']}' when answered NO."], 400);
        }
    }

    $validatedAnswers[] = [
        'question_id'       => $qid,
        'answer'            => $ansVal,
        'issue_description' => $ansVal === 'NO' ? $issueDesc : null
    ];
}

$overallStatus = $hasIssues ? 'Issues identified' : 'No issues reported';
$systemNewStatus = $hasIssues ? 'issue_identified' : 'working';

// 6. Generate Unique Report ID: REP-YYYYMMDD-XXXX
$todayStr = date('Ymd');
$randomPart = strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));
$reportId = "REP-{$todayStr}-{$randomPart}";

$currentTime = date('H:i:s');
$currentDate = date('Y-m-d');

try {
    $db->beginTransaction();

    // Insert Maintenance Report
    $insReport = $db->prepare("
        INSERT INTO maintenance_reports (report_id, system_id, agent_id, maintenance_date, maintenance_time, overall_status, notes)
        VALUES (:rep_id, :sys_id, :agt_id, :m_date, :m_time, :status, :notes)
    ");
    $insReport->execute([
        ':rep_id'  => $reportId,
        ':sys_id'  => $systemId,
        ':agt_id'  => $agentId,
        ':m_date'  => $currentDate,
        ':m_time'  => $currentTime,
        ':status'  => $overallStatus,
        ':notes'   => $notes
    ]);

    // Insert Location Verification Record
    $insGeo = $db->prepare("
        INSERT INTO location_verifications (
            report_id, registered_latitude, registered_longitude, 
            agent_latitude, agent_longitude, calculated_distance, gps_accuracy, verification_status
        ) VALUES (
            :rep_id, :reg_lat, :reg_lon, 
            :agt_lat, :agt_lon, :dist, :acc, 'verified'
        )
    ");
    $insGeo->execute([
        ':rep_id'  => $reportId,
        ':reg_lat' => $regLat,
        ':reg_lon' => $regLon,
        ':agt_lat' => $agentLatitude,
        ':agt_lon' => $agentLongitude,
        ':dist'    => $calculatedDistance,
        ':acc'     => $gpsAccuracy
    ]);

    // Insert Answers
    $insAns = $db->prepare("
        INSERT INTO maintenance_answers (report_id, question_id, answer, issue_description)
        VALUES (:rep_id, :qid, :ans, :desc)
    ");
    foreach ($validatedAnswers as $va) {
        $insAns->execute([
            ':rep_id' => $reportId,
            ':qid'    => $va['question_id'],
            ':ans'    => $va['answer'],
            ':desc'   => $va['issue_description']
        ]);
    }

    // Update Equipment status
    $upSys = $db->prepare("UPDATE systems SET status = :status, updated_at = NOW() WHERE system_id = :id");
    $upSys->execute([
        ':status' => $systemNewStatus,
        ':id'     => $systemId
    ]);

    $db->commit();

    jsonResponse([
        'success'        => true,
        'message'        => 'Maintenance report successfully submitted and saved.',
        'report_id'      => $reportId,
        'system_id'      => $systemId,
        'equipment_name' => $system['equipment_name'],
        'department'     => $system['department'],
        'room_number'    => $system['room_number'],
        'agent_id'       => $agentId,
        'agent_name'     => $auth['user']['name'],
        'maintenance_date' => $currentDate,
        'maintenance_time' => $currentTime,
        'overall_status' => $overallStatus,
        'verification_status' => 'Verified (Within 10m)'
    ], 201);

} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    error_log("Report submission failed: " . $e->getMessage());
    jsonResponse(['success' => false, 'error' => 'Database error while saving report: ' . $e->getMessage()], 500);
}
