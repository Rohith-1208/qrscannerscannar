<?php
// ==============================================================================
// Admin Web Dashboard - Printable QR Sticker Layout
// ==============================================================================

require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/../config/helpers.php';

$adminUser = checkAdminSession();
$db = getAdminDb();

$systemId = strtoupper(trim($_GET['system_id'] ?? ''));
if (empty($systemId)) {
    die('System ID required');
}

$stmt = $db->prepare("
    SELECT s.*, q.qr_version, q.signature
    FROM systems s
    LEFT JOIN qr_codes q ON s.system_id = q.system_id AND q.is_active = 1
    WHERE s.system_id = :id
    LIMIT 1
");
$stmt->execute([':id' => $systemId]);
$system = $stmt->fetch();

if (!$system) {
    die('System not found');
}

$qrPayload = [
    'system_id' => $system['system_id'],
    'latitude'  => (float)number_format((float)$system['latitude'], 6, '.', ''),
    'longitude' => (float)number_format((float)$system['longitude'], 6, '.', ''),
    'radius'    => (int)$system['allowed_radius'],
    'version'   => (int)($system['qr_version'] ?? 1),
    'signature' => $system['signature'] ?? ''
];
$jsonString = json_encode($qrPayload, JSON_UNESCAPED_SLASHES);
$qrImgUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=' . urlencode($jsonString);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Print QR Tag - <?= htmlspecialchars($system['system_id']) ?></title>
  <style>
    body {
      font-family: Arial, sans-serif;
      background: #f1f5f9;
      margin: 0;
      padding: 20px;
      display: flex;
      flex-direction: column;
      align-items: center;
    }
    .print-actions {
      margin-bottom: 20px;
    }
    .btn {
      padding: 10px 20px;
      background: #1e3a8a;
      color: #fff;
      border: none;
      border-radius: 6px;
      font-size: 14px;
      font-weight: bold;
      cursor: pointer;
    }
    .sticker-card {
      width: 380px;
      background: #ffffff;
      border: 2px solid #0f172a;
      border-radius: 12px;
      padding: 24px;
      text-align: center;
      box-shadow: 0 4px 10px rgba(0,0,0,0.1);
    }
    .college-header {
      border-bottom: 2px solid #0f172a;
      padding-bottom: 10px;
      margin-bottom: 16px;
    }
    .college-title {
      font-size: 16px;
      font-weight: 800;
      text-transform: uppercase;
      color: #1e3a8a;
      letter-spacing: 0.05em;
    }
    .sub-title {
      font-size: 11px;
      font-weight: 600;
      color: #64748b;
      margin-top: 2px;
    }
    .qr-box {
      margin: 14px 0;
    }
    .qr-box img {
      width: 200px;
      height: 200px;
    }
    .system-id {
      font-size: 24px;
      font-weight: 900;
      color: #0f172a;
      letter-spacing: 0.1em;
      margin-bottom: 4px;
    }
    .system-name {
      font-size: 14px;
      font-weight: 600;
      color: #334155;
    }
    .location-details {
      font-size: 12px;
      color: #475569;
      margin-top: 6px;
    }
    .geo-warning {
      margin-top: 14px;
      border-top: 1px dashed #cbd5e1;
      padding-top: 10px;
      font-size: 10px;
      color: #64748b;
      font-weight: 600;
      text-transform: uppercase;
    }
    @media print {
      body {
        background: #fff;
        padding: 0;
      }
      .print-actions {
        display: none;
      }
      .sticker-card {
        box-shadow: none;
        border: 2px solid #000;
      }
    }
  </style>
</head>
<body>
  <div class="print-actions">
    <button class="btn" onclick="window.print()">&#x1F5B6; Print Equipment Sticker</button>
  </div>

  <div class="sticker-card">
    <div class="college-header">
      <div class="college-title">College Equipment Tag</div>
      <div class="sub-title">Geo-Verified Maintenance System</div>
    </div>

    <div class="system-id"><?= htmlspecialchars($system['system_id']) ?></div>
    <div class="system-name"><?= htmlspecialchars($system['equipment_name']) ?></div>
    <div class="location-details">
      <b>Dept:</b> <?= htmlspecialchars($system['department']) ?><br>
      <b>Location:</b> <?= htmlspecialchars($system['building']) ?> - <?= htmlspecialchars($system['room_number']) ?>
    </div>

    <div class="qr-box">
      <img src="<?= $qrImgUrl ?>" alt="QR Code">
    </div>

    <div class="geo-warning">
      &#x25C9; Geo-Verified Equipment &bull; 10m Permitted GPS Radius<br>
      Tamper-Proof HMAC-SHA256 Signed Tag (v<?= (int)$system['qr_version'] ?>)
    </div>
  </div>
</body>
</html>
