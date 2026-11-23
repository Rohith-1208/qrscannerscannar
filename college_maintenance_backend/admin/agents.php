<?php
// ==============================================================================
// Admin Web Dashboard - Service Agent Management
// ==============================================================================

require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/layout_nav.php';

$adminUser = checkAdminSession();
$db = getAdminDb();

$message = '';
$error = '';

// Handle Agent Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $agentId  = strtoupper(trim($_POST['agent_id'] ?? ''));
        $name     = trim($_POST['name'] ?? '');
        $email    = strtolower(trim($_POST['email'] ?? ''));
        $password = (string)($_POST['password'] ?? '');

        if (empty($name) || empty($email) || empty($password)) {
            $error = 'Name, email, and password are required.';
        } elseif (strlen($password) < 6) {
            $error = 'Password must be at least 6 characters.';
        } else {
            // Auto-generate Agent ID if empty
            if (empty($agentId)) {
                $cStmt = $db->query("SELECT MAX(CAST(SUBSTRING(agent_id, 5) AS UNSIGNED)) AS max_id FROM agents WHERE agent_id LIKE 'AGT-%'");
                $maxVal = (int)($cStmt->fetch()['max_id'] ?? 0);
                $agentId = sprintf("AGT-%03d", $maxVal + 1);
            }

            // Check existence
            $chk = $db->prepare("SELECT agent_id FROM agents WHERE agent_id = :id OR email = :email");
            $chk->execute([':id' => $agentId, ':email' => $email]);
            if ($chk->fetch()) {
                $error = 'Agent ID or Email is already registered.';
            } else {
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $stmt = $db->prepare("INSERT INTO agents (agent_id, name, email, password_hash, status) VALUES (:id, :name, :email, :hash, 'active')");
                $stmt->execute([
                    ':id'    => $agentId,
                    ':name'  => $name,
                    ':email' => $email,
                    ':hash'  => $hash
                ]);
                $message = "Service Agent '{$name}' ({$agentId}) successfully registered.";
            }
        }
    } elseif ($action === 'toggle_status') {
        $agentId = trim($_POST['agent_id'] ?? '');
        $newStatus = trim($_POST['new_status'] ?? 'active');
        $stmt = $db->prepare("UPDATE agents SET status = :status WHERE agent_id = :id");
        $stmt->execute([':status' => $newStatus, ':id' => $agentId]);
        $message = "Agent {$agentId} status updated to {$newStatus}.";
    }
}

// Fetch all agents
$stmt = $db->query("
    SELECT 
        a.agent_id, 
        a.name, 
        a.email, 
        a.status, 
        a.created_at,
        COUNT(r.report_id) AS total_inspections
    FROM agents a
    LEFT JOIN maintenance_reports r ON a.agent_id = r.agent_id
    GROUP BY a.agent_id
    ORDER BY a.agent_id ASC
");
$agents = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Manage Service Agents - College Maintenance</title>
  <link rel="stylesheet" href="../assets/css/style.css">
  <script src="../assets/js/app.js"></script>
</head>
<body>
  <div class="app-container">
    <?php renderAdminSidebar('agents', $adminUser); ?>

    <main class="main-wrapper">
      <header class="topbar">
        <div>
          <h1>Service Agent Management</h1>
        </div>
        <div>
          <button class="btn btn-primary" onclick="openModal('addAgentModal')">
            + Add Service Agent
          </button>
        </div>
      </header>

      <div class="page-content">
        <?php if (!empty($message)): ?>
          <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        <?php if (!empty($error)): ?>
          <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div class="card">
          <div class="card-header">
            <h3>Registered Service Agents (<?= count($agents) ?>)</h3>
          </div>
          <div class="card-body" style="padding: 0;">
            <div class="table-responsive">
              <table class="data-table">
                <thead>
                  <tr>
                    <th>Agent ID</th>
                    <th>Name</th>
                    <th>Email Address</th>
                    <th>Total Inspections</th>
                    <th>Account Status</th>
                    <th>Registered On</th>
                    <th>Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (empty($agents)): ?>
                    <tr>
                      <td colspan="7" style="text-align: center; color: #64748b; padding: 30px;">No service agents registered yet.</td>
                    </tr>
                  <?php else: ?>
                    <?php foreach ($agents as $a): ?>
                      <tr>
                        <td><b style="color: var(--primary);"><?= htmlspecialchars($a['agent_id']) ?></b></td>
                        <td><b><?= htmlspecialchars($a['name']) ?></b></td>
                        <td><?= htmlspecialchars($a['email']) ?></td>
                        <td>
                          <span style="font-weight: 600; color: var(--accent);"><?= $a['total_inspections'] ?> reports</span>
                        </td>
                        <td>
                          <?php if ($a['status'] === 'active'): ?>
                            <span class="badge badge-active">Active</span>
                          <?php else: ?>
                            <span class="badge badge-inactive">Inactive</span>
                          <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($a['created_at']) ?></td>
                        <td>
                          <form method="POST" action="agents.php" style="display: inline;">
                            <input type="hidden" name="action" value="toggle_status">
                            <input type="hidden" name="agent_id" value="<?= htmlspecialchars($a['agent_id']) ?>">
                            <input type="hidden" name="new_status" value="<?= $a['status'] === 'active' ? 'inactive' : 'active' ?>">
                            <button type="submit" class="btn btn-secondary btn-sm" onclick="return confirm('Toggle status for <?= htmlspecialchars($a['agent_id']) ?>?');">
                              <?= $a['status'] === 'active' ? 'Deactivate' : 'Activate' ?>
                            </button>
                          </form>
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

  <!-- MODAL: Add Service Agent -->
  <div id="addAgentModal" class="modal-overlay">
    <div class="modal-content">
      <div class="modal-header">
        <h3>Add Service Agent</h3>
        <button class="modal-close" onclick="closeModal('addAgentModal')">&times;</button>
      </div>
      <form method="POST" action="agents.php">
        <input type="hidden" name="action" value="create">
        <div class="modal-body">
          <div class="form-group">
            <label>Agent ID (Optional - Auto-generated if blank)</label>
            <input type="text" name="agent_id" class="form-control" placeholder="e.g. AGT-002">
          </div>
          <div class="form-group">
            <label>Full Name *</label>
            <input type="text" name="name" class="form-control" required placeholder="Alex Morgan">
          </div>
          <div class="form-group">
            <label>Registered Email Address *</label>
            <input type="email" name="email" class="form-control" required placeholder="alex@college.edu">
          </div>
          <div class="form-group">
            <label>Secure Password *</label>
            <input type="password" name="password" class="form-control" required placeholder="At least 6 characters">
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" onclick="closeModal('addAgentModal')">Cancel</button>
          <button type="submit" class="btn btn-primary">Create Agent Account</button>
        </div>
      </form>
    </div>
  </div>
</body>
</html>
