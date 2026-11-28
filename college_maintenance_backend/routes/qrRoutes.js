const express = require('express');
const router = express.Router();
const { pool } = require('../config/db');
const { requireAuth } = require('../middleware/auth');
const { generateQrSignature, verifyQrSignature } = require('../utils/cryptoHelper');

// POST /api/qr/generate
async function generateQr(req, res) {
  try {
    const systemId = (req.body.system_id || '').toUpperCase().trim();
    const forceRegenerate = Boolean(req.body.regenerate);

    if (!systemId) {
      return res.status(400).json({ success: false, error: 'System ID is required.' });
    }

    const [systems] = await pool.execute(`SELECT * FROM systems WHERE system_id = ? LIMIT 1`, [systemId]);
    if (systems.length === 0) {
      return res.status(404).json({ success: false, error: `System '${systemId}' not found.` });
    }
    const system = systems[0];

    const [activeQrs] = await pool.execute(
      `SELECT * FROM qr_codes WHERE system_id = ? AND is_active = 1 LIMIT 1`,
      [systemId]
    );

    let activeQr = activeQrs.length > 0 ? activeQrs[0] : null;

    if (!activeQr || forceRegenerate) {
      const [maxV] = await pool.execute(`SELECT MAX(qr_version) AS max_v FROM qr_codes WHERE system_id = ?`, [systemId]);
      const nextVersion = (parseInt(maxV[0].max_v || 0, 10)) + 1;

      await pool.execute(`UPDATE qr_codes SET is_active = 0 WHERE system_id = ?`, [systemId]);

      const sig = generateQrSignature(
        systemId,
        parseFloat(system.latitude),
        parseFloat(system.longitude),
        parseFloat(system.altitude || 0),
        parseFloat(system.allowed_radius),
        nextVersion
      );

      await pool.execute(
        `INSERT INTO qr_codes (system_id, qr_version, signature, is_active) VALUES (?, ?, ?, 1)`,
        [systemId, nextVersion, sig]
      );

      activeQr = {
        qr_version: nextVersion,
        signature: sig,
      };
    }

    const payload = {
      system_id: system.system_id,
      latitude: parseFloat(parseFloat(system.latitude).toFixed(6)),
      longitude: parseFloat(parseFloat(system.longitude).toFixed(6)),
      altitude: parseFloat(parseFloat(system.altitude || 0).toFixed(2)),
      radius: parseInt(system.allowed_radius, 10),
      version: parseInt(activeQr.qr_version, 10),
      signature: activeQr.signature,
    };

    return res.json({
      success: true,
      system_id: systemId,
      qr_payload: payload,
      json_string: JSON.stringify(payload),
    });
  } catch (err) {
    console.error('[Generate QR Error]', err);
    return res.status(500).json({ success: false, error: 'Failed to generate QR.' });
  }
}

// POST /api/qr/verify
async function verifyQr(req, res) {
  try {
    const systemId = (req.body.system_id || '').toUpperCase().trim();
    const latitude = parseFloat(req.body.latitude);
    const longitude = parseFloat(req.body.longitude);
    const altitude = parseFloat(req.body.altitude !== undefined ? req.body.altitude : 0);
    const radius = parseFloat(req.body.radius);
    const version = parseInt(req.body.version, 10);
    const signature = (req.body.signature || '').trim();

    if (!systemId || isNaN(latitude) || isNaN(longitude) || isNaN(radius) || isNaN(version) || !signature) {
      return res.status(400).json({
        success: false,
        valid: false,
        error: 'Malformed QR payload. Missing required fields.',
      });
    }

    // 1. Verify digital signature using backend secret
    const isSignatureValid = verifyQrSignature(systemId, latitude, longitude, altitude, radius, version, signature);
    if (!isSignatureValid) {
      return res.status(403).json({
        success: false,
        valid: false,
        error: 'Invalid digital signature. QR code is forged or tampered with.',
      });
    }

    // 2. Verify system exists in database
    const [systems] = await pool.execute(`SELECT * FROM systems WHERE system_id = ? LIMIT 1`, [systemId]);
    if (systems.length === 0) {
      return res.status(404).json({
        success: false,
        valid: false,
        error: `System '${systemId}' does not exist in college records.`,
      });
    }
    const system = systems[0];

    if (system.status === 'disabled') {
      return res.status(403).json({
        success: false,
        valid: false,
        error: `System '${systemId}' is marked as disabled.`,
      });
    }

    // 3. Verify active QR version
    const [activeQrs] = await pool.execute(
      `SELECT * FROM qr_codes WHERE system_id = ? AND is_active = 1 LIMIT 1`,
      [systemId]
    );

    if (activeQrs.length === 0 || parseInt(activeQrs[0].qr_version, 10) !== version) {
      return res.status(409).json({
        success: false,
        valid: false,
        error: 'Expired QR Code. A newer QR code has been issued for this equipment.',
      });
    }

    return res.json({
      success: true,
      valid: true,
      message: 'QR payload authenticated and valid.',
      system: {
        system_id: system.system_id,
        equipment_name: system.equipment_name,
        equipment_type: system.equipment_type,
        department: system.department,
        building: system.building,
        room_number: system.room_number,
        latitude: parseFloat(system.latitude),
        longitude: parseFloat(system.longitude),
        allowed_radius: parseFloat(system.allowed_radius),
        status: system.status,
      },
    });
  } catch (err) {
    console.error('[Verify QR Error]', err);
    return res.status(500).json({ success: false, valid: false, error: 'Internal error verifying QR code.' });
  }
}

// Routes
router.post('/generate', requireAuth('admin'), generateQr);
router.post('/generate.php', requireAuth('admin'), generateQr);

router.post('/verify', requireAuth(), verifyQr);
router.post('/verify.php', requireAuth(), verifyQr);

module.exports = router;
