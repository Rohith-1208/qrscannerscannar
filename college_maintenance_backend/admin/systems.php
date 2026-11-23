<?php
// ==============================================================================
// Admin Web Dashboard - Equipment & QR Management
// ==============================================================================

require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/layout_nav.php';
require_once __DIR__ . '/../config/helpers.php';

$adminUser = checkAdminSession();
$db = getAdminDb();

$message = '';
$error = '';

// Handle Actions (Create / Update / Regenerate QR)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $systemId      = strtoupper(trim($_POST['system_id'] ?? ''));
        $equipmentName = trim($_POST['equipment_name'] ?? '');
        $equipmentType = trim($_POST['equipment_type'] ?? 'Desktop Computer');
        $department    = trim($_POST['department'] ?? '');
        $building      = trim($_POST['building'] ?? '');
        $roomNumber    = trim($_POST['room_number'] ?? '');
        $latitude      = (float)($_POST['latitude'] ?? 0);
        $longitude     = (float)($_POST['longitude'] ?? 0);
        $allowedRadius = max(1, (int)($_POST['allowed_radius'] ?? 10));

        if (empty($systemId) || empty($equipmentName) || empty($department) || empty($roomNumber)) {
            $error = 'System ID, Equipment Name, Department, and Room Number are required.';
        } elseif ($latitude == 0 || $longitude == 0) {
            $error = 'Valid Latitude and Longitude coordinates are required.';
        } else {
            // Check existence
            $chk = $db->prepare("SELECT system_id FROM systems WHERE system_id = :id");
            $chk->execute([':id' => $systemId]);
            if ($chk->fetch()) {
                $error = "System ID '{$systemId}' already exists.";
            } else {
                try {
                    $db->beginTransaction();
                    $stmt = $db->prepare("
                        INSERT INTO systems (system_id, equipment_name, equipment_type, department, building, room_number, latitude, longitude, allowed_radius, status)
                        VALUES (:id, :name, :type, :dept, :bld, :room, :lat, :lon, :radius, 'working')
                    ");
                    $stmt->execute([
                        ':id'     => $systemId,
                        ':name'   => $equipmentName,
                        ':type'   => $equipmentType,
                        ':dept'   => $department,
                        ':bld'    => $building,
                        ':room'   => $roomNumber,
                        ':lat'    => $latitude,
                        ':lon'    => $longitude,
                        ':radius' => $allowedRadius
                    ]);

                    // Generate V1 QR Code
                    $sig = generateQrSignature($systemId, $latitude, $longitude, $allowedRadius, 1);
                    $qrStmt = $db->prepare("INSERT INTO qr_codes (system_id, qr_version, signature, is_active) VALUES (:id, 1, :sig, 1)");
                    $qrStmt->execute([':id' => $systemId, ':sig' => $sig]);

                    $db->commit();
                    $message = "Equipment '{$systemId}' and its secure QR code were successfully registered.";
                } catch (Exception $e) {
                    if ($db->inTransaction()) $db->rollBack();
                    $error = 'Database error: ' . $e->getMessage();
                }
            }
        }
    } elseif ($action === 'regenerate_qr') {
        $systemId = strtoupper(trim($_POST['system_id'] ?? ''));
        $stmt = $db->prepare("SELECT * FROM systems WHERE system_id = :id");
        $stmt->execute([':id' => $systemId]);
        $sys = $stmt->fetch();
        if ($sys) {
            $vStmt = $db->prepare("SELECT MAX(qr_version) AS max_v FROM qr_codes WHERE system_id = :id");
            $vStmt->execute([':id' => $systemId]);
            $newV = ((int)($vStmt->fetch()['max_v'] ?? 0)) + 1;

            $db->prepare("UPDATE qr_codes SET is_active = 0 WHERE system_id = :id")->execute([':id' => $systemId]);
            $sig = generateQrSignature($systemId, (float)$sys['latitude'], (float)$sys['longitude'], (float)$sys['allowed_radius'], $newV);
            $db->prepare("INSERT INTO qr_codes (system_id, qr_version, signature, is_active) VALUES (:id, :v, :sig, 1)")
               ->execute([':id' => $systemId, ':v' => $newV, ':sig' => $sig]);
            $message = "New QR version {$newV} generated for {$systemId}. Previous version invalidated.";
        }
    }
}

// Fetch all systems with active QR
$search = trim($_GET['search'] ?? '');
$filterDept = trim($_GET['department'] ?? '');

$query = "
    SELECT s.*, q.qr_version, q.signature
    FROM systems s
    LEFT JOIN qr_codes q ON s.system_id = q.system_id AND q.is_active = 1
    WHERE 1=1
";
$params = [];
if (!empty($search)) {
    $query .= " AND (s.system_id LIKE :search OR s.equipment_name LIKE :search2 OR s.room_number LIKE :search3)";
    $params[':search']  = "%{$search}%";
    $params[':search2'] = "%{$search}%";
    $params[':search3'] = "%{$search}%";
}
if (!empty($filterDept)) {
    $query .= " AND s.department = :dept";
    $params[':dept'] = $filterDept;
}
$query .= " ORDER BY s.system_id ASC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$systems = $stmt->fetchAll();

// Distinct departments for filter
$deptList = $db->query("SELECT DISTINCT department FROM systems ORDER BY department ASC")->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Manage Equipment & QR Codes - College Maintenance</title>
  <link rel="stylesheet" href="../assets/css/style.css">
  <script src="../assets/js/app.js"></script>
</head>
<body>
  <div class="app-container">
    <?php renderAdminSidebar('systems', $adminUser); ?>

    <main class="main-wrapper">
      <header class="topbar">
        <div>
          <h1>College Equipment & Secure QR Codes</h1>
        </div>
        <div>
          <button class="btn btn-primary" onclick="openModal('registerSystemModal')">
            + Register New Equipment
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

        <!-- Search & Filter Bar -->
        <div class="card" style="margin-bottom: 20px;">
          <div class="card-body" style="padding: 16px 24px;">
            <form method="GET" class="filter-bar">
              <input type="text" name="search" class="search-input" placeholder="Search System ID, Name, Room..." value="<?= htmlspecialchars($search) ?>" style="min-width: 280px;">
              
              <select name="department" class="select-input">
                <option value="">All Departments</option>
                <?php foreach ($deptList as $d): ?>
                  <option value="<?= htmlspecialchars($d) ?>" <?= $filterDept === $d ? 'selected' : '' ?>><?= htmlspecialchars($d) ?></option>
                <?php endforeach; ?>
              </select>

              <button type="submit" class="btn btn-secondary btn-sm">Filter</button>
              <?php if (!empty($search) || !empty($filterDept)): ?>
                <a href="systems.php" class="btn btn-secondary btn-sm">Clear</a>
              <?php endif; ?>
            </form>
          </div>
        </div>

        <!-- Systems Table -->
        <div class="card">
          <div class="card-header">
            <h3>Registered Equipment Inventory (<?= count($systems) ?>)</h3>
          </div>
          <div class="card-body" style="padding: 0;">
            <div class="table-responsive">
              <table class="data-table">
                <thead>
                  <tr>
                    <th>System ID</th>
                    <th>Equipment Name</th>
                    <th>Department & Location</th>
                    <th>Registered Coordinates</th>
                    <th>Permitted Radius</th>
                    <th>Status</th>
                    <th>QR Version</th>
                    <th>Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (empty($systems)): ?>
                    <tr>
                      <td colspan="8" style="text-align: center; color: #64748b; padding: 30px;">No equipment records found matching criteria.</td>
                    </tr>
                  <?php else: ?>
                    <?php foreach ($systems as $s): 
                      $qrPayload = [
                        'system_id' => $s['system_id'],
                        'latitude'  => (float)number_format((float)$s['latitude'], 6, '.', ''),
                        'longitude' => (float)number_format((float)$s['longitude'], 6, '.', ''),
                        'radius'    => (int)$s['allowed_radius'],
                        'version'   => (int)($s['qr_version'] ?? 1),
                        'signature' => $s['signature'] ?? ''
                      ];
                      $jsonPayload = json_encode($qrPayload, JSON_UNESCAPED_SLASHES);
                    ?>
                      <tr>
                        <td><b style="color: var(--primary);"><?= htmlspecialchars($s['system_id']) ?></b></td>
                        <td>
                          <b><?= htmlspecialchars($s['equipment_name']) ?></b><br>
                          <small style="color: #64748b;"><?= htmlspecialchars($s['equipment_type']) ?></small>
                        </td>
                        <td>
                          <?= htmlspecialchars($s['department']) ?><br>
                          <small style="color: #64748b;"><?= htmlspecialchars($s['building']) ?> - <?= htmlspecialchars($s['room_number']) ?></small>
                        </td>
                        <td>
                          <span style="font-family: monospace; font-size: 12px; background: #f1f5f9; padding: 2px 6px; border-radius: 4px;">
                            <?= number_format((float)$s['latitude'], 6) ?>, <?= number_format((float)$s['longitude'], 6) ?>
                          </span>
                        </td>
                        <td><?= (int)$s['allowed_radius'] ?> metres</td>
                        <td>
                          <?php if ($s['status'] === 'working'): ?>
                            <span class="badge badge-working">Working</span>
                          <?php elseif ($s['status'] === 'issue_identified'): ?>
                            <span class="badge badge-issues">Issues Identified</span>
                          <?php elseif ($s['status'] === 'disabled'): ?>
                            <span class="badge badge-inactive">Disabled</span>
                          <?php else: ?>
                            <span class="badge badge-maintenance"><?= htmlspecialchars($s['status']) ?></span>
                          <?php endif; ?>
                        </td>
                        <td>
                          <span class="badge" style="background: #eff6ff; color: #1d4ed8; font-weight: 700;">
                            v<?= (int)($s['qr_version'] ?? 1) ?>
                          </span>
                        </td>
                        <td>
                          <div style="display: flex; gap: 6px;">
                            <button class="btn btn-secondary btn-sm" onclick='showQrModal(<?= json_encode($s['system_id']) ?>, <?= json_encode($jsonPayload) ?>)'>
                              View QR
                            </button>
                            <a href="qr_print.php?system_id=<?= urlencode($s['system_id']) ?>" target="_blank" class="btn btn-secondary btn-sm" title="Print QR Sticker">
                              Print
                            </a>
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

  <!-- MODAL: Register Equipment -->
  <div id="registerSystemModal" class="modal-overlay">
    <div class="modal-content">
      <div class="modal-header">
        <h3>Register New College Equipment</h3>
        <button class="modal-close" onclick="closeModal('registerSystemModal')">&times;</button>
      </div>
      <form method="POST" action="systems.php">
        <input type="hidden" name="action" value="create">
        <div class="modal-body">
          <div class="form-grid-2">
            <div class="form-group">
              <label>System ID * (e.g. SYS-011)</label>
              <input type="text" name="system_id" class="form-control" required placeholder="SYS-011" uppercase>
            </div>
            <div class="form-group">
              <label>Equipment Name *</label>
              <input type="text" name="equipment_name" class="form-control" required placeholder="Desktop Workstation 11">
            </div>
          </div>

          <div class="form-grid-2">
            <div class="form-group">
              <label>Equipment Type</label>
              <select name="equipment_type" class="form-control">
                <option value="Desktop Computer">Desktop Computer</option>
                <option value="Server Workstation">Server Workstation</option>
                <option value="Laboratory Instrument">Laboratory Instrument</option>
                <option value="Network Switch/Router">Network Switch/Router</option>
              </select>
            </div>
            <div class="form-group">
              <label>Department *</label>
              <input type="text" name="department" class="form-control" required placeholder="Computer Science & Engineering">
            </div>
          </div>

          <div class="form-grid-2">
            <div class="form-group">
              <label>Building Name</label>
              <input type="text" name="building" class="form-control" placeholder="Tech Block A">
            </div>
            <div class="form-group">
              <label>Room / Lab Number *</label>
              <input type="text" name="room_number" class="form-control" required placeholder="Lab 101">
            </div>
          </div>

          <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 14px; margin-bottom: 16px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
              <span style="font-size: 13px; font-weight: 600; color: #1e3a8a;">Equipment GPS Location (Geo-Fence)</span>
              <button type="button" class="btn btn-secondary btn-sm" onclick="captureDeviceCoordinates('regLat', 'regLon', 'gpsStatusSpan')">
                &#x25CE; Auto-Get Device GPS
              </button>
            </div>
            <div id="gpsStatusSpan" style="font-size: 12px; margin-bottom: 8px;"></div>
            <div class="form-grid-2">
              <div class="form-group" style="margin-bottom: 0;">
                <label>Latitude *</label>
                <input type="number" step="0.00000001" id="regLat" name="latitude" class="form-control" required placeholder="13.07510000">
              </div>
              <div class="form-group" style="margin-bottom: 0;">
                <label>Longitude *</label>
                <input type="number" step="0.00000001" id="regLon" name="longitude" class="form-control" required placeholder="80.11700000">
              </div>
            </div>
          </div>

          <div class="form-group">
            <label>Permitted GPS Radius (Metres)</label>
            <input type="number" name="allowed_radius" class="form-control" value="10" min="1" max="100" required>
            <small style="color: #64748b;">Technicians must be within this distance to perform maintenance (Default: 10m).</small>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" onclick="closeModal('registerSystemModal')">Cancel</button>
          <button type="submit" class="btn btn-primary">Save & Generate Secure QR</button>
        </div>
      </form>
    </div>
  </div>

  <!-- MODAL: View QR Code -->
  <div id="viewQrModal" class="modal-overlay">
    <div class="modal-content" style="max-width: 440px;">
      <div class="modal-header">
        <h3 id="qrModalTitle">Equipment QR Code</h3>
        <button class="modal-close" onclick="closeModal('viewQrModal')">&times;</button>
      </div>
      <div class="modal-body">
        <div id="qrCodeContainer"></div>
      </div>
      <div class="modal-footer" style="justify-content: space-between;">
        <form method="POST" action="systems.php" style="margin: 0;">
          <input type="hidden" name="action" value="regenerate_qr">
          <input type="hidden" id="regenSystemId" name="system_id" value="">
          <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Regenerating this QR will invalidate all physical stickers printed with older versions. Proceed?');">
            Regenerate QR
          </button>
        </form>
        <button type="button" class="btn btn-secondary" onclick="closeModal('viewQrModal')">Close</button>
      </div>
    </div>
  </div>

  <script>
    function showQrModal(systemId, jsonPayload) {
      document.getElementById('qrModalTitle').innerText = 'QR Code: ' + systemId;
      document.getElementById('regenSystemId').value = systemId;
      renderQRCodeOnCanvas('qrCodeContainer', jsonPayload);
      openModal('viewQrModal');
    }
  </script>
</body>
</html>
