<?php
// ==============================================================================
// Admin Web Dashboard - Maintenance Report Management
// ==============================================================================

require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/layout_nav.php';

$adminUser = checkAdminSession();
$db = getAdminDb();

// Filters
$fromDate   = trim($_GET['from_date'] ?? '');
$toDate     = trim($_GET['to_date'] ?? '');
$department = trim($_GET['department'] ?? '');
$systemId   = trim($_GET['system_id'] ?? '');
$agentId    = trim($_GET['agent_id'] ?? '');
$status     = trim($_GET['status'] ?? '');
$viewReportId = trim($_GET['report_id'] ?? '');

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

// If viewing a specific report details
$selectedReport = null;
$selectedAnswers = [];
if (!empty($viewReportId)) {
    $detStmt = $db->prepare("
        SELECT r.*, s.equipment_name, s.department, s.room_number, s.allowed_radius, a.name AS agent_name, a.email AS agent_email,
               v.registered_latitude, v.registered_longitude, v.agent_latitude, v.agent_longitude, v.calculated_distance, v.gps_accuracy
        FROM maintenance_reports r
        JOIN systems s ON r.system_id = s.system_id
        JOIN agents a ON r.agent_id = a.agent_id
        LEFT JOIN location_verifications v ON r.report_id = v.report_id
        WHERE r.report_id = :id
        LIMIT 1
    ");
    $detStmt->execute([':id' => $viewReportId]);
    $selectedReport = $detStmt->fetch();

    if ($selectedReport) {
        $aStmt = $db->prepare("
            SELECT a.*, q.question_text
            FROM maintenance_answers a
            JOIN checklist_questions q ON a.question_id = q.question_id
            WHERE a.report_id = :id
            ORDER BY q.sort_order ASC
        ");
        $aStmt->execute([':id' => $viewReportId]);
        $selectedAnswers = $aStmt->fetchAll();
    }
}

// Dropdown lists
$deptList = $db->query("SELECT DISTINCT department FROM systems ORDER BY department ASC")->fetchAll(PDO::FETCH_COLUMN);
$agentList = $db->query("SELECT agent_id, name FROM agents ORDER BY name ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Maintenance Reports - College Maintenance</title>
  <link rel="stylesheet" href="../assets/css/style.css">
  <script src="../assets/js/app.js"></script>
</head>
<body>
  <div class="app-container">
    <?php renderAdminSidebar('reports', $adminUser); ?>

    <main class="main-wrapper">
      <header class="topbar">
        <div>
          <h1>Maintenance Reports Audit</h1>
        </div>
        <div class="topbar-meta">
          <span><?= count($reports) ?> Filtered Records</span>
        </div>
      </header>

      <div class="page-content">
        <!-- Filter Bar -->
        <div class="card" style="margin-bottom: 20px;">
          <div class="card-body" style="padding: 16px 24px;">
            <form method="GET" class="filter-bar">
              <input type="text" name="system_id" class="search-input" placeholder="System ID / Name..." value="<?= htmlspecialchars($systemId) ?>">
              
              <select name="department" class="select-input">
                <option value="">All Departments</option>
                <?php foreach ($deptList as $d): ?>
                  <option value="<?= htmlspecialchars($d) ?>" <?= $department === $d ? 'selected' : '' ?>><?= htmlspecialchars($d) ?></option>
                <?php endforeach; ?>
              </select>

              <select name="agent_id" class="select-input">
                <option value="">All Technicians</option>
                <?php foreach ($agentList as $ag): ?>
                  <option value="<?= htmlspecialchars($ag['agent_id']) ?>" <?= $agentId === $ag['agent_id'] ? 'selected' : '' ?>><?= htmlspecialchars($ag['name']) ?></option>
                <?php endforeach; ?>
              </select>

              <select name="status" class="select-input">
                <option value="">All Statuses</option>
                <option value="No issues reported" <?= $status === 'No issues reported' ? 'selected' : '' ?>>No issues reported</option>
                <option value="Issues identified" <?= $status === 'Issues identified' ? 'selected' : '' ?>>Issues identified</option>
              </select>

              <input type="date" name="from_date" class="date-input" value="<?= htmlspecialchars($fromDate) ?>" title="From Date">
              <input type="date" name="to_date" class="date-input" value="<?= htmlspecialchars($toDate) ?>" title="To Date">

              <button type="submit" class="btn btn-secondary btn-sm">Filter</button>
              <a href="reports.php" class="btn btn-secondary btn-sm">Reset</a>
            </form>
          </div>
        </div>

        <!-- Reports Table -->
        <div class="card">
          <div class="card-header">
            <h3>Verified Inspection Reports</h3>
          </div>
          <div class="card-body" style="padding: 0;">
            <div class="table-responsive">
              <table class="data-table">
                <thead>
                  <tr>
                    <th>Report ID</th>
                    <th>Equipment</th>
                    <th>Location</th>
                    <th>Technician</th>
                    <th>Date & Time</th>
                    <th>GPS Proximity</th>
                    <th>Overall Status</th>
                    <th>Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (empty($reports)): ?>
                    <tr>
                      <td colspan="8" style="text-align: center; color: #64748b; padding: 30px;">No maintenance reports matched your filters.</td>
                    </tr>
                  <?php else: ?>
                    <?php foreach ($reports as $r): ?>
                      <tr>
                        <td><b style="color: var(--accent);"><?= htmlspecialchars($r['report_id']) ?></b></td>
                        <td>
                          <b><?= htmlspecialchars($r['system_id']) ?></b><br>
                          <small style="color: #64748b;"><?= htmlspecialchars($r['equipment_name']) ?></small>
                        </td>
                        <td><?= htmlspecialchars($r['department']) ?> (<?= htmlspecialchars($r['room_number']) ?>)</td>
                        <td><?= htmlspecialchars($r['agent_name']) ?></td>
                        <td><?= htmlspecialchars($r['maintenance_date']) ?> <small style="color: #64748b;"><?= htmlspecialchars($r['maintenance_time']) ?></small></td>
                        <td>
                          <span class="badge badge-verified">
                            Verified (<?= number_format((float)($r['calculated_distance'] ?? 0), 1) ?>m)
                          </span>
                        </td>
                        <td>
                          <?php if ($r['overall_status'] === 'No issues reported'): ?>
                            <span class="badge badge-working">No Issues</span>
                          <?php else: ?>
                            <span class="badge badge-issues">Issues Identified</span>
                          <?php endif; ?>
                        </td>
                        <td>
                          <a href="reports.php?report_id=<?= urlencode($r['report_id']) ?>" class="btn btn-secondary btn-sm">
                            Inspect &rarr;
                          </a>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>

      </div>
    </main>
  </div>

  <!-- MODAL: View Full Report Details -->
  <?php if ($selectedReport): ?>
  <div id="reportDetailModal" class="modal-overlay active">
    <div class="modal-content" style="max-width: 700px;">
      <div class="modal-header">
        <div>
          <h3>Maintenance Report: <?= htmlspecialchars($selectedReport['report_id']) ?></h3>
          <p style="font-size: 13px; color: #64748b; margin-top: 2px;">
            <?= htmlspecialchars($selectedReport['system_id']) ?> - <?= htmlspecialchars($selectedReport['equipment_name']) ?>
          </p>
        </div>
        <a href="reports.php" class="modal-close" style="text-decoration: none;">&times;</a>
      </div>
      <div class="modal-body">
        <!-- Location Evidence Box -->
        <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 16px; margin-bottom: 20px;">
          <h4 style="font-size: 13px; text-transform: uppercase; letter-spacing: 0.05em; color: #1e3a8a; margin-bottom: 10px;">
            &#x1F4CD; Physical Location Verification Evidence
          </h4>
          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; font-size: 13px;">
            <div><b>Department:</b> <?= htmlspecialchars($selectedReport['department']) ?> (<?= htmlspecialchars($selectedReport['room_number']) ?>)</div>
            <div><b>Inspecting Agent:</b> <?= htmlspecialchars($selectedReport['agent_name']) ?> (<?= htmlspecialchars($selectedReport['agent_id']) ?>)</div>
            <div><b>Registered Coordinates:</b> <?= number_format((float)$selectedReport['registered_latitude'], 6) ?>, <?= number_format((float)$selectedReport['registered_longitude'], 6) ?></div>
            <div><b>Agent GPS Coordinates:</b> <?= number_format((float)$selectedReport['agent_latitude'], 6) ?>, <?= number_format((float)$selectedReport['agent_longitude'], 6) ?></div>
            <div><b>Calculated Haversine Distance:</b> <span style="color: var(--success); font-weight: 700;"><?= number_format((float)$selectedReport['calculated_distance'], 2) ?> metres</span> (&le; <?= $selectedReport['allowed_radius'] ?>m permitted)</div>
            <div><b>GPS Device Accuracy:</b> &plusmn;<?= number_format((float)$selectedReport['gps_accuracy'], 1) ?> metres</div>
            <div><b>Inspection Timestamp:</b> <?= htmlspecialchars($selectedReport['maintenance_date']) ?> at <?= htmlspecialchars($selectedReport['maintenance_time']) ?></div>
            <div><b>Verification Result:</b> <span class="badge badge-verified">GEO-VERIFIED</span></div>
          </div>
        </div>

        <!-- Checklist Questions & Answers -->
        <h4 style="font-size: 14px; font-weight: 700; margin-bottom: 12px;">Equipment Inspection Checklist</h4>
        <div style="border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden; margin-bottom: 16px;">
          <table style="width: 100%; border-collapse: collapse; font-size: 13px;">
            <thead>
              <tr style="background: #f1f5f9; border-bottom: 1px solid #e2e8f0;">
                <th style="padding: 10px 14px; text-align: left;">Inspection Question</th>
                <th style="padding: 10px 14px; text-align: center; width: 100px;">Answer</th>
                <th style="padding: 10px 14px; text-align: left;">Issue Description</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($selectedAnswers as $ans): ?>
                <tr style="border-bottom: 1px solid #f1f5f9;">
                  <td style="padding: 10px 14px;"><?= htmlspecialchars($ans['question_text']) ?></td>
                  <td style="padding: 10px 14px; text-align: center;">
                    <?php if ($ans['answer'] === 'YES'): ?>
                      <span class="badge badge-working">YES</span>
                    <?php else: ?>
                      <span class="badge badge-issues">NO</span>
                    <?php endif; ?>
                  </td>
                  <td style="padding: 10px 14px; color: <?= $ans['answer'] === 'NO' ? '#dc2626' : '#64748b' ?>;">
                    <?= !empty($ans['issue_description']) ? htmlspecialchars($ans['issue_description']) : 'None reported' ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <?php if (!empty($selectedReport['notes'])): ?>
          <div style="font-size: 13px; margin-top: 10px;">
            <b>Agent Notes:</b> <?= htmlspecialchars($selectedReport['notes']) ?>
          </div>
        <?php endif; ?>
      </div>
      <div class="modal-footer">
        <a href="reports.php" class="btn btn-secondary">Close</a>
      </div>
    </div>
  </div>
  <?php endif; ?>
</body>
</html>
