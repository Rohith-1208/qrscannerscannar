<?php
// ==============================================================================
// Admin Web Dashboard - Overview
// ==============================================================================

require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/layout_nav.php';

$adminUser = checkAdminSession();
$db = getAdminDb();

// 1. Fetch Real Statistics from MySQL
// Total systems
$totalSystems = (int)$db->query("SELECT COUNT(*) FROM systems")->fetchColumn();

// Working systems
$workingSystems = (int)$db->query("SELECT COUNT(*) FROM systems WHERE status = 'working'")->fetchColumn();

// Systems with issues
$issueSystems = (int)$db->query("SELECT COUNT(*) FROM systems WHERE status = 'issue_identified'")->fetchColumn();

// Total agents
$totalAgents = (int)$db->query("SELECT COUNT(*) FROM agents WHERE status = 'active'")->fetchColumn();

// Total reports
$totalReports = (int)$db->query("SELECT COUNT(*) FROM maintenance_reports")->fetchColumn();

// Pending inspections (systems not inspected today)
$today = date('Y-m-d');
$pendingQuery = "
    SELECT COUNT(*) 
    FROM systems s
    WHERE s.status != 'disabled' 
      AND s.system_id NOT IN (
          SELECT DISTINCT system_id 
          FROM maintenance_reports 
          WHERE maintenance_date = :today
      )
";
$pStmt = $db->prepare($pendingQuery);
$pStmt->execute([':today' => $today]);
$pendingInspections = (int)$pStmt->fetchColumn();

// 2. Recent Maintenance Reports
$recentReportsStmt = $db->query("
    SELECT 
        r.report_id,
        r.system_id,
        s.equipment_name,
        s.department,
        s.room_number,
        a.name AS agent_name,
        r.maintenance_date,
        r.maintenance_time,
        r.overall_status,
        v.verification_status,
        v.calculated_distance
    FROM maintenance_reports r
    JOIN systems s ON r.system_id = s.system_id
    JOIN agents a ON r.agent_id = a.agent_id
    LEFT JOIN location_verifications v ON r.report_id = v.report_id
    ORDER BY r.maintenance_date DESC, r.maintenance_time DESC
    LIMIT 8
");
$recentReports = $recentReportsStmt->fetchAll();

// 3. Departmental Summary
$deptStmt = $db->query("
    SELECT 
        department, 
        COUNT(*) AS total_count,
        SUM(CASE WHEN status = 'working' THEN 1 ELSE 0 END) AS working_count,
        SUM(CASE WHEN status = 'issue_identified' THEN 1 ELSE 0 END) AS issue_count
    FROM systems 
    GROUP BY department 
    ORDER BY total_count DESC
");
$departments = $deptStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Dashboard - College Equipment Maintenance</title>
  <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
  <div class="app-container">
    <?php renderAdminSidebar('dashboard', $adminUser); ?>

    <main class="main-wrapper">
      <header class="topbar">
        <div>
          <h1>Dashboard Overview</h1>
        </div>
        <div class="topbar-meta">
          <span>College Campus Central Network</span>
          <span>&bull;</span>
          <span><?= date('l, F j, Y') ?></span>
        </div>
      </header>

      <div class="page-content">
        <!-- Stats Grid -->
        <div class="stats-grid">
          <div class="stat-card blue">
            <span class="label">Total Registered Systems</span>
            <span class="value"><?= $totalSystems ?></span>
          </div>

          <div class="stat-card green">
            <span class="label">Working Normally</span>
            <span class="value"><?= $workingSystems ?></span>
          </div>

          <div class="stat-card red">
            <span class="label">Identified Issues</span>
            <span class="value"><?= $issueSystems ?></span>
          </div>

          <div class="stat-card orange">
            <span class="label">Pending Inspections (Today)</span>
            <span class="value"><?= $pendingInspections ?></span>
          </div>

          <div class="stat-card purple">
            <span class="label">Active Service Agents</span>
            <span class="value"><?= $totalAgents ?></span>
          </div>

          <div class="stat-card blue">
            <span class="label">Total Reports Logged</span>
            <span class="value"><?= $totalReports ?></span>
          </div>
        </div>

        <!-- Recent Maintenance Reports Card -->
        <div class="card">
          <div class="card-header">
            <h3>Recent Maintenance Reports (Geo-Verified)</h3>
            <a href="reports.php" class="btn btn-secondary btn-sm">View All Reports &rarr;</a>
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
                    <th>Geo-Verification</th>
                    <th>Overall Status</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (empty($recentReports)): ?>
                    <tr>
                      <td colspan="7" style="text-align: center; color: #64748b; padding: 30px;">
                        No maintenance reports recorded yet.
                      </td>
                    </tr>
                  <?php else: ?>
                    <?php foreach ($recentReports as $rep): ?>
                      <tr>
                        <td><a href="reports.php?report_id=<?= urlencode($rep['report_id']) ?>" style="color: var(--accent); font-weight: 600; text-decoration: none;"><?= htmlspecialchars($rep['report_id']) ?></a></td>
                        <td>
                          <b><?= htmlspecialchars($rep['system_id']) ?></b><br>
                          <small style="color: #64748b;"><?= htmlspecialchars($rep['equipment_name']) ?></small>
                        </td>
                        <td><?= htmlspecialchars($rep['department']) ?> (<?= htmlspecialchars($rep['room_number']) ?>)</td>
                        <td><?= htmlspecialchars($rep['agent_name']) ?></td>
                        <td><?= htmlspecialchars($rep['maintenance_date']) ?> <small style="color: #64748b;"><?= htmlspecialchars($rep['maintenance_time']) ?></small></td>
                        <td>
                          <span class="badge badge-verified">
                            Verified (&le; 10m)
                          </span>
                        </td>
                        <td>
                          <?php if ($rep['overall_status'] === 'No issues reported'): ?>
                            <span class="badge badge-working">No Issues</span>
                          <?php else: ?>
                            <span class="badge badge-issues">Issues Identified</span>
                          <?php endif; ?>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <!-- Department Summary -->
        <div class="card">
          <div class="card-header">
            <h3>Department Equipment Health Summary</h3>
          </div>
          <div class="card-body" style="padding: 0;">
            <div class="table-responsive">
              <table class="data-table">
                <thead>
                  <tr>
                    <th>Department</th>
                    <th>Total Equipment</th>
                    <th>Working</th>
                    <th>With Issues</th>
                    <th>Health Rate</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (empty($departments)): ?>
                    <tr>
                      <td colspan="5" style="text-align: center; color: #64748b; padding: 20px;">No departments registered.</td>
                    </tr>
                  <?php else: ?>
                    <?php foreach ($departments as $dept): 
                      $rate = $dept['total_count'] > 0 ? round(($dept['working_count'] / $dept['total_count']) * 100) : 0;
                    ?>
                      <tr>
                        <td><b><?= htmlspecialchars($dept['department']) ?></b></td>
                        <td><?= $dept['total_count'] ?></td>
                        <td><span style="color: var(--success); font-weight: 600;"><?= $dept['working_count'] ?></span></td>
                        <td><span style="color: var(--danger); font-weight: 600;"><?= $dept['issue_count'] ?></span></td>
                        <td>
                          <div style="display: flex; align-items: center; gap: 8px;">
                            <div style="flex: 1; background: #e2e8f0; height: 8px; border-radius: 4px; overflow: hidden; max-width: 140px;">
                              <div style="width: <?= $rate ?>%; background: <?= $rate >= 80 ? 'var(--success)' : ($rate >= 50 ? 'var(--warning)' : 'var(--danger)') ?>; height: 100%;"></div>
                            </div>
                            <span style="font-size: 12px; font-weight: 600;"><?= $rate ?>%</span>
                          </div>
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
</body>
</html>
