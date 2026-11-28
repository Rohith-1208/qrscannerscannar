const express = require('express');
const router = express.Router();
const { pool } = require('../config/db');
const { requireAuth } = require('../middleware/auth');
const { generateQrSignature } = require('../utils/cryptoHelper');
const config = require('../config/constants');

// GET /api/systems/list
async function listSystems(req, res) {
  try {
    const search = (req.query.search || '').trim();
    const department = (req.query.department || '').trim();
    const status = (req.query.status || '').trim();

    let sql = `
      SELECT 
        s.system_id, 
        s.equipment_name, 
        s.equipment_type, 
        s.department, 
        s.building, 
        s.room_number, 
        s.floor_number,
        s.latitude, 
        s.longitude, 
        s.altitude,
        s.allowed_radius, 
        s.altitude_tolerance,
        s.status, 
        s.created_at,
        q.qr_version,
        q.signature
      FROM systems s
      LEFT JOIN qr_codes q ON s.system_id = q.system_id AND q.is_active = 1
      WHERE 1=1
    `;
    const params = [];

    if (search) {
      sql += ` AND (s.system_id LIKE ? OR s.equipment_name LIKE ? OR s.room_number LIKE ?)`;
      params.push(`%${search}%`, `%${search}%`, `%${search}%`);
    }
    if (department) {
      sql += ` AND s.department = ?`;
      params.push(department);
    }
    if (status) {
      sql += ` AND s.status = ?`;
      params.push(status);
    }

    sql += ` ORDER BY s.system_id ASC`;

    const [systems] = await pool.execute(sql, params);

    // Ensure every system has an active HMAC-SHA256 signature
    for (const sys of systems) {
      if (!sys.signature) {
        const ver = parseInt(sys.qr_version || 1, 10);
        const sig = generateQrSignature(
          sys.system_id,
          parseFloat(sys.latitude),
          parseFloat(sys.longitude),
          parseFloat(sys.altitude || 0),
          parseFloat(sys.allowed_radius),
          ver
        );
        await pool.execute(
          `INSERT INTO qr_codes (system_id, qr_version, signature, is_active) VALUES (?, ?, ?, 1)
           ON DUPLICATE KEY UPDATE signature = VALUES(signature), is_active = 1`,
          [sys.system_id, ver, sig]
        );
        sys.qr_version = ver;
        sys.signature = sig;
      }
    }

    return res.json({
      success: true,
      count: systems.length,
      systems,
    });
  } catch (err) {
    console.error('[List Systems Error]', err);
    return res.status(500).json({ success: false, error: 'Database error fetching equipment list.' });
  }
}

// POST /api/systems/create
async function createSystem(req, res) {
  const connection = await pool.getConnection();
  try {
    const systemId = (req.body.system_id || '').toUpperCase().trim();
    const equipmentName = (req.body.equipment_name || '').trim();
    const equipmentType = (req.body.equipment_type || 'Desktop Computer').trim();
    const department = (req.body.department || '').trim();
    const building = (req.body.building || '').trim();
    const roomNumber = (req.body.room_number || '').trim();
    const floorNumber = (req.body.floor_number || 'Ground Floor').trim();
    const latitude = parseFloat(req.body.latitude);
    const longitude = parseFloat(req.body.longitude);
    const altitude = parseFloat(req.body.altitude !== undefined ? req.body.altitude : 0);
    const allowedRadius = Math.max(1, parseInt(req.body.allowed_radius || config.DEFAULT_ALLOWED_RADIUS_METRES, 10));
    const altitudeTolerance = parseFloat(req.body.altitude_tolerance || 5.0);

    if (!systemId || !equipmentName || !department || !roomNumber) {
      return res.status(400).json({ success: false, error: 'System ID, Equipment Name, Department, and Room Number are required.' });
    }

    if (isNaN(latitude) || isNaN(longitude) || latitude < -90 || latitude > 90 || longitude < -180 || longitude > 180) {
      return res.status(400).json({ success: false, error: 'Valid latitude (-90 to 90) and longitude (-180 to 180) are required.' });
    }

    // Check unique ID
    const [chk] = await connection.execute(`SELECT system_id FROM systems WHERE system_id = ? LIMIT 1`, [systemId]);
    if (chk.length > 0) {
      return res.status(409).json({ success: false, error: `System ID '${systemId}' is already registered.` });
    }

    await connection.beginTransaction();

    // 1. Insert System with Altitude & Floor
    await connection.execute(
      `INSERT INTO systems (system_id, equipment_name, equipment_type, department, building, room_number, floor_number, latitude, longitude, altitude, allowed_radius, altitude_tolerance, status)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'working')`,
      [systemId, equipmentName, equipmentType, department, building, roomNumber, floorNumber, latitude, longitude, altitude, allowedRadius, altitudeTolerance]
    );

    // 2. Generate Version 1 QR with HMAC-SHA256 signature including altitude
    const qrVersion = 1;
    const signature = generateQrSignature(systemId, latitude, longitude, altitude, allowedRadius, qrVersion);

    await connection.execute(
      `INSERT INTO qr_codes (system_id, qr_version, signature, is_active) VALUES (?, ?, ?, 1)`,
      [systemId, qrVersion, signature]
    );

    await connection.commit();

    return res.status(201).json({
      success: true,
      message: `Equipment '${systemId}' registered successfully.`,
      system: {
        system_id: systemId,
        equipment_name: equipmentName,
        equipment_type: equipmentType,
        department,
        building,
        room_number: roomNumber,
        latitude,
        longitude,
        allowed_radius: allowedRadius,
        status: 'working',
        qr: {
          system_id: systemId,
          latitude: parseFloat(latitude.toFixed(6)),
          longitude: parseFloat(longitude.toFixed(6)),
          radius: allowedRadius,
          version: qrVersion,
          signature,
        },
      },
    });
  } catch (err) {
    await connection.rollback();
    console.error('[Create System Error]', err);
    return res.status(500).json({ success: false, error: 'Failed to register equipment: ' + err.message });
  } finally {
    connection.release();
  }
}

// POST or PUT /api/systems/update
async function updateSystem(req, res) {
  const connection = await pool.getConnection();
  try {
    const systemId = (req.body.system_id || '').toUpperCase().trim();
    if (!systemId) {
      return res.status(400).json({ success: false, error: 'System ID is required for update.' });
    }

    const [existingList] = await connection.execute(`SELECT * FROM systems WHERE system_id = ? LIMIT 1`, [systemId]);
    if (existingList.length === 0) {
      return res.status(404).json({ success: false, error: `System '${systemId}' not found.` });
    }
    const existing = existingList[0];

    const equipmentName = (req.body.equipment_name || existing.equipment_name).trim();
    const equipmentType = (req.body.equipment_type || existing.equipment_type).trim();
    const department    = (req.body.department || existing.department).trim();
    const building      = (req.body.building || existing.building).trim();
    const roomNumber    = (req.body.room_number || existing.room_number).trim();
    const floorNumber   = (req.body.floor_number || existing.floor_number || 'Ground Floor').trim();
    const status        = (req.body.status || existing.status).trim();

    const newLat = req.body.latitude !== undefined ? parseFloat(req.body.latitude) : parseFloat(existing.latitude);
    const newLon = req.body.longitude !== undefined ? parseFloat(req.body.longitude) : parseFloat(existing.longitude);
    const newAlt = req.body.altitude !== undefined ? parseFloat(req.body.altitude) : parseFloat(existing.altitude || 0);
    const newRadius = req.body.allowed_radius !== undefined ? Math.max(1, parseInt(req.body.allowed_radius, 10)) : parseInt(existing.allowed_radius, 10);
    const newAltitudeTolerance = req.body.altitude_tolerance !== undefined ? parseFloat(req.body.altitude_tolerance) : parseFloat(existing.altitude_tolerance || 5.0);

    const coordsChanged =
      Math.abs(newLat - parseFloat(existing.latitude)) > 0.000001 ||
      Math.abs(newLon - parseFloat(existing.longitude)) > 0.000001 ||
      Math.abs(newAlt - parseFloat(existing.altitude || 0)) > 0.1 ||
      newRadius !== parseInt(existing.allowed_radius, 10);

    await connection.beginTransaction();

    await connection.execute(
      `UPDATE systems 
       SET equipment_name = ?, equipment_type = ?, department = ?, building = ?, room_number = ?, floor_number = ?, 
           latitude = ?, longitude = ?, altitude = ?, allowed_radius = ?, altitude_tolerance = ?, status = ?, updated_at = NOW()
       WHERE system_id = ?`,
      [equipmentName, equipmentType, department, building, roomNumber, floorNumber, newLat, newLon, newAlt, newRadius, newAltitudeTolerance, status, systemId]
    );

    let newQrData = null;

    if (coordsChanged) {
      const [maxV] = await connection.execute(`SELECT MAX(qr_version) AS max_v FROM qr_codes WHERE system_id = ?`, [systemId]);
      const nextVersion = (parseInt(maxV[0].max_v || 0, 10)) + 1;

      // Invalidate old QR codes
      await connection.execute(`UPDATE qr_codes SET is_active = 0 WHERE system_id = ?`, [systemId]);

      // Generate new HMAC-SHA256 signature with Altitude
      const signature = generateQrSignature(systemId, newLat, newLon, newAlt, newRadius, nextVersion);

      await connection.execute(
        `INSERT INTO qr_codes (system_id, qr_version, signature, is_active) VALUES (?, ?, ?, 1)`,
        [systemId, nextVersion, signature]
      );

      newQrData = {
        system_id: systemId,
        latitude: parseFloat(newLat.toFixed(6)),
        longitude: parseFloat(newLon.toFixed(6)),
        altitude: parseFloat(newAlt.toFixed(2)),
        radius: newRadius,
        version: nextVersion,
        signature,
      };
    }

    await connection.commit();

    return res.json({
      success: true,
      message: 'Equipment details updated successfully.',
      coordinate_change: coordsChanged,
      new_qr: newQrData,
    });
  } catch (err) {
    await connection.rollback();
    console.error('[Update System Error]', err);
    return res.status(500).json({ success: false, error: 'Failed to update system: ' + err.message });
  } finally {
    connection.release();
  }
}

// GET /api/systems/details
async function getSystemDetails(req, res) {
  try {
    const systemId = (req.query.system_id || '').toUpperCase().trim();
    if (!systemId) {
      return res.status(400).json({ success: false, error: 'system_id query parameter is required.' });
    }

    const [systems] = await pool.execute(
      `SELECT s.*, q.qr_version, q.signature
       FROM systems s
       LEFT JOIN qr_codes q ON s.system_id = q.system_id AND q.is_active = 1
       WHERE s.system_id = ?
       LIMIT 1`,
      [systemId]
    );

    if (systems.length === 0) {
      return res.status(404).json({ success: false, error: `System '${systemId}' not found.` });
    }

    const system = systems[0];

    // If signature missing, generate it
    if (!system.signature) {
      const sig = generateQrSignature(
        system.system_id,
        parseFloat(system.latitude),
        parseFloat(system.longitude),
        parseFloat(system.allowed_radius),
        parseInt(system.qr_version || 1, 10)
      );
      await pool.execute(
        `INSERT INTO qr_codes (system_id, qr_version, signature, is_active) VALUES (?, ?, ?, 1)`,
        [system.system_id, parseInt(system.qr_version || 1, 10), sig]
      );
      system.signature = sig;
    }

    const qrPayload = {
      system_id: system.system_id,
      latitude: parseFloat(parseFloat(system.latitude).toFixed(6)),
      longitude: parseFloat(parseFloat(system.longitude).toFixed(6)),
      radius: parseInt(system.allowed_radius, 10),
      version: parseInt(system.qr_version || 1, 10),
      signature: system.signature,
    };

    const [recentReports] = await pool.execute(
      `SELECT r.report_id, r.maintenance_date, r.maintenance_time, r.overall_status, a.name AS agent_name
       FROM maintenance_reports r
       JOIN agents a ON r.agent_id = a.agent_id
       WHERE r.system_id = ?
       ORDER BY r.maintenance_date DESC, r.maintenance_time DESC
       LIMIT 10`,
      [systemId]
    );

    return res.json({
      success: true,
      system,
      qr_payload: qrPayload,
      recent_reports: recentReports,
    });
  } catch (err) {
    console.error('[System Details Error]', err);
    return res.status(500).json({ success: false, error: 'Error fetching system details.' });
  }
}

// Routes
router.get('/list', requireAuth(), listSystems);
router.get('/list.php', requireAuth(), listSystems);

router.post('/create', requireAuth('admin'), createSystem);
router.post('/create.php', requireAuth('admin'), createSystem);

router.post('/update', requireAuth('admin'), updateSystem);
router.post('/update.php', requireAuth('admin'), updateSystem);
router.put('/update', requireAuth('admin'), updateSystem);
router.put('/update.php', requireAuth('admin'), updateSystem);

router.get('/details', requireAuth(), getSystemDetails);
router.get('/details.php', requireAuth(), getSystemDetails);

module.exports = router;
