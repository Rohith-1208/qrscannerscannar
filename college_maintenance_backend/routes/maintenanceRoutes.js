const express = require('express');
const router = express.Router();
const crypto = require('crypto');
const { pool } = require('../config/db');
const { requireAuth } = require('../middleware/auth');
const { calculateHaversineDistance } = require('../utils/haversine');
const { verifyQrSignature } = require('../utils/cryptoHelper');
const config = require('../config/constants');

// GET /api/maintenance/checklist-questions
async function getChecklistQuestions(req, res) {
  try {
    const equipmentType = (req.query.equipment_type || 'Desktop Computer').trim();

    const [questions] = await pool.execute(
      `SELECT q.question_id, q.question_text, q.sort_order, q.is_required
       FROM checklist_questions q
       JOIN checklist_templates t ON q.template_id = t.template_id
       WHERE t.equipment_type = ? AND t.status = 'active'
       ORDER BY q.sort_order ASC`,
      [equipmentType]
    );

    if (questions.length > 0) {
      return res.json({ success: true, equipment_type: equipmentType, questions });
    }

    // Fallback to template 1
    const [fallback] = await pool.execute(
      `SELECT question_id, question_text, sort_order, is_required
       FROM checklist_questions
       WHERE template_id = 1
       ORDER BY sort_order ASC`
    );

    return res.json({ success: true, equipment_type: equipmentType, questions: fallback });
  } catch (err) {
    console.error('[Checklist Questions Error]', err);
    return res.status(500).json({ success: false, error: 'Database error fetching checklist.' });
  }
}

// POST /api/maintenance/verify-location
async function verifyLocation(req, res) {
  try {
    const systemId = (req.body.system_id || '').toUpperCase().trim();
    const agentLatitude = parseFloat(req.body.agent_latitude);
    const agentLongitude = parseFloat(req.body.agent_longitude);
    const gpsAccuracy = parseFloat(req.body.gps_accuracy);
    const qrSignature = (req.body.qr_signature || '').trim();
    const qrVersion = parseInt(req.body.qr_version || 1, 10);

    if (!systemId || isNaN(agentLatitude) || isNaN(agentLongitude) || isNaN(gpsAccuracy)) {
      return res.status(400).json({ success: false, error: 'Missing coordinates or system_id.' });
    }

    // 1. Check GPS Accuracy (Rule 7)
    if (gpsAccuracy > config.MAX_ACCEPTABLE_GPS_ACCURACY_METRES) {
      return res.json({
        success: false,
        verified: false,
        accuracy_insufficient: true,
        error: 'GPS signal accuracy is insufficient. Please acquire a reliable satellite lock and retry.',
      });
    }

    // 2. Fetch System & Active QR
    const [systems] = await pool.execute(
      `SELECT s.*, q.signature, q.qr_version AS active_qr_version
       FROM systems s
       LEFT JOIN qr_codes q ON s.system_id = q.system_id AND q.is_active = 1
       WHERE s.system_id = ?
       LIMIT 1`,
      [systemId]
    );

    if (systems.length === 0) {
      return res.status(404).json({ success: false, verified: false, error: `System '${systemId}' not found.` });
    }
    const system = systems[0];

    // 3. Verify Signature if provided
    if (qrSignature) {
      const sigValid = verifyQrSignature(
        systemId,
        parseFloat(system.latitude),
        parseFloat(system.longitude),
        parseFloat(system.allowed_radius),
        qrVersion,
        qrSignature
      );
      if (!sigValid) {
        return res.status(403).json({ success: false, verified: false, error: 'Invalid or forged QR signature.' });
      }
    }

    // 4. Calculate Distance
    const regLat = parseFloat(system.latitude);
    const regLon = parseFloat(system.longitude);
    const allowedRadius = parseFloat(system.allowed_radius);

    const distance = calculateHaversineDistance(regLat, regLon, agentLatitude, agentLongitude);

    if (distance <= allowedRadius) {
      return res.json({
        success: true,
        verified: true,
        system_id: systemId,
        equipment_name: system.equipment_name,
        room_number: system.room_number,
        department: system.department,
        equipment_type: system.equipment_type,
        allowed_radius: allowedRadius,
      });
    } else {
      // Distance > allowed radius: per Rule 6, mobile UI shows ONLY: "Not in correct location"
      return res.json({
        success: false,
        verified: false,
        error: 'Not in correct location',
      });
    }
  } catch (err) {
    console.error('[Verify Location Error]', err);
    return res.status(500).json({ success: false, error: 'Internal server error verifying location.' });
  }
}

// POST /api/maintenance/submit-report
async function submitReport(req, res) {
  const connection = await pool.getConnection();
  try {
    const agentId = req.user.agent_id;
    const systemId = (req.body.system_id || '').toUpperCase().trim();
    const agentLatitude = parseFloat(req.body.agent_latitude);
    const agentLongitude = parseFloat(req.body.agent_longitude);
    const gpsAccuracy = parseFloat(req.body.gps_accuracy);
    const qrSignature = (req.body.qr_signature || '').trim();
    const qrVersion = parseInt(req.body.qr_version || 1, 10);
    const answers = req.body.answers || [];
    const notes = (req.body.notes || '').trim();

    if (!systemId || isNaN(agentLatitude) || isNaN(agentLongitude) || isNaN(gpsAccuracy)) {
      return res.status(400).json({ success: false, error: 'System ID, GPS coordinates, and accuracy are required.' });
    }

    if (!Array.isArray(answers) || answers.length === 0) {
      return res.status(400).json({ success: false, error: 'Maintenance checklist answers cannot be empty.' });
    }

    // 2. Fetch System
    const [systems] = await connection.execute(
      `SELECT s.*, q.signature, q.qr_version AS active_qr_version
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

    // 3. Security: Independent Server-Side QR Signature Verification
    if (qrSignature) {
      const sigValid = verifyQrSignature(
        systemId,
        parseFloat(system.latitude),
        parseFloat(system.longitude),
        parseFloat(system.allowed_radius),
        qrVersion,
        qrSignature
      );
      if (!sigValid) {
        return res.status(403).json({ success: false, error: 'Server rejection: Invalid digital signature on QR payload.' });
      }
    }

    // 4. Security: Independent Server-Side Haversine Verification
    if (gpsAccuracy > config.MAX_ACCEPTABLE_GPS_ACCURACY_METRES) {
      return res.status(400).json({ success: false, error: 'Server rejection: GPS accuracy is insufficient for verified submission.' });
    }

    const regLat = parseFloat(system.latitude);
    const regLon = parseFloat(system.longitude);
    const allowedRadius = parseFloat(system.allowed_radius);

    const calculatedDistance = calculateHaversineDistance(regLat, regLon, agentLatitude, agentLongitude);

    if (calculatedDistance > allowedRadius) {
      return res.status(403).json({ success: false, error: 'Not in correct location' });
    }

    // 5. Validate Checklist Answers
    const [mandatoryQuestions] = await connection.execute(
      `SELECT q.question_id, q.question_text, q.is_required
       FROM checklist_questions q
       JOIN checklist_templates t ON q.template_id = t.template_id
       WHERE t.equipment_type = ? AND t.status = 'active'`,
      [system.equipment_type]
    );

    const questionsList = mandatoryQuestions.length > 0 ? mandatoryQuestions : (
      await connection.execute(`SELECT question_id, question_text, is_required FROM checklist_questions WHERE template_id = 1`)
    )[0];

    const answerMap = {};
    for (const a of answers) {
      if (a.question_id) answerMap[parseInt(a.question_id, 10)] = a;
    }

    let hasIssues = false;
    const validatedAnswers = [];

    for (const mq of questionsList) {
      const qid = parseInt(mq.question_id, 10);
      if (!answerMap[qid]) {
        return res.status(400).json({ success: false, error: `Missing answer for mandatory question: '${mq.question_text}'.` });
      }

      const entry = answerMap[qid];
      const ansVal = (entry.answer || '').toUpperCase().trim();
      const issueDesc = (entry.issue_description || '').trim();

      if (ansVal !== 'YES' && ansVal !== 'NO') {
        return res.status(400).json({ success: false, error: `Answer must be 'YES' or 'NO' for question ID ${qid}.` });
      }

      if (ansVal === 'NO') {
        hasIssues = true;
        if (!issueDesc) {
          return res.status(400).json({ success: false, error: `Issue description is required for question: '${mq.question_text}' when answered NO.` });
        }
      }

      validatedAnswers.push({
        question_id: qid,
        answer: ansVal,
        issue_description: ansVal === 'NO' ? issueDesc : null,
      });
    }

    const overallStatus = hasIssues ? 'Issues identified' : 'No issues reported';
    const systemNewStatus = hasIssues ? 'issue_identified' : 'working';

    // 6. Generate Unique Report ID: REP-YYYYMMDD-XXXXX
    const now = new Date();
    const yyyy = now.getFullYear();
    const mm = String(now.getMonth() + 1).padStart(2, '0');
    const dd = String(now.getDate()).padStart(2, '0');
    const randomHex = crypto.randomBytes(3).toString('hex').toUpperCase().substring(0, 5);
    const reportId = `REP-${yyyy}${mm}${dd}-${randomHex}`;

    const currentDate = `${yyyy}-${mm}-${dd}`;
    const currentTime = now.toTimeString().split(' ')[0];

    await connection.beginTransaction();

    // 1. Insert Report
    await connection.execute(
      `INSERT INTO maintenance_reports (report_id, system_id, agent_id, maintenance_date, maintenance_time, overall_status, notes)
       VALUES (?, ?, ?, ?, ?, ?, ?)`,
      [reportId, systemId, agentId, currentDate, currentTime, overallStatus, notes]
    );

    // 2. Insert Geo Verification
    await connection.execute(
      `INSERT INTO location_verifications (report_id, registered_latitude, registered_longitude, agent_latitude, agent_longitude, calculated_distance, gps_accuracy, verification_status)
       VALUES (?, ?, ?, ?, ?, ?, ?, 'verified')`,
      [reportId, regLat, regLon, agentLatitude, agentLongitude, calculatedDistance, gpsAccuracy]
    );

    // 3. Insert Answers
    for (const va of validatedAnswers) {
      await connection.execute(
        `INSERT INTO maintenance_answers (report_id, question_id, answer, issue_description)
         VALUES (?, ?, ?, ?)`,
        [reportId, va.question_id, va.answer, va.issue_description]
      );
    }

    // 4. Update Equipment Status
    await connection.execute(
      `UPDATE systems SET status = ?, updated_at = NOW() WHERE system_id = ?`,
      [systemNewStatus, systemId]
    );

    await connection.commit();

    return res.status(201).json({
      success: true,
      message: 'Maintenance report successfully submitted and saved.',
      report_id: reportId,
      system_id: systemId,
      equipment_name: system.equipment_name,
      department: system.department,
      room_number: system.room_number,
      agent_id: agentId,
      agent_name: req.user.name,
      maintenance_date: currentDate,
      maintenance_time: currentTime,
      overall_status: overallStatus,
      verification_status: 'Verified (Within 10m)',
    });
  } catch (err) {
    await connection.rollback();
    console.error('[Submit Report Error]', err);
    return res.status(500).json({ success: false, error: 'Database error saving report: ' + err.message });
  } finally {
    connection.release();
  }
}

// GET /api/maintenance/agent-reports
async function getAgentReports(req, res) {
  try {
    const agentId = req.user.agent_id;

    const [reports] = await pool.execute(
      `SELECT 
        r.report_id,
        r.system_id,
        s.equipment_name,
        s.department,
        s.room_number,
        r.maintenance_date,
        r.maintenance_time,
        r.overall_status,
        r.created_at,
        v.verification_status,
        v.calculated_distance
       FROM maintenance_reports r
       JOIN systems s ON r.system_id = s.system_id
       LEFT JOIN location_verifications v ON r.report_id = v.report_id
       WHERE r.agent_id = ?
       ORDER BY r.maintenance_date DESC, r.maintenance_time DESC`,
      [agentId]
    );

    return res.json({
      success: true,
      count: reports.length,
      reports,
    });
  } catch (err) {
    console.error('[Agent Reports Error]', err);
    return res.status(500).json({ success: false, error: 'Database error fetching reports.' });
  }
}

// GET /api/maintenance/report-details
async function getReportDetails(req, res) {
  try {
    const reportId = (req.query.report_id || '').trim();
    if (!reportId) {
      return res.status(400).json({ success: false, error: 'report_id is required.' });
    }

    const [reports] = await pool.execute(
      `SELECT 
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
       WHERE r.report_id = ?
       LIMIT 1`,
      [reportId]
    );

    if (reports.length === 0) {
      return res.status(404).json({ success: false, error: `Report '${reportId}' not found.` });
    }
    const report = reports[0];

    // Agents can only access their own reports
    if (req.userType === 'agent' && req.userId !== report.agent_id) {
      return res.status(403).json({ success: false, error: 'Access denied: You can only view your own maintenance reports.' });
    }

    const [answers] = await pool.execute(
      `SELECT 
        a.answer_id,
        a.question_id,
        q.question_text,
        a.answer,
        a.issue_description
       FROM maintenance_answers a
       JOIN checklist_questions q ON a.question_id = q.question_id
       WHERE a.report_id = ?
       ORDER BY q.sort_order ASC`,
      [reportId]
    );

    return res.json({
      success: true,
      report,
      answers,
    });
  } catch (err) {
    console.error('[Report Details Error]', err);
    return res.status(500).json({ success: false, error: 'Error fetching report details.' });
  }
}

// GET /api/maintenance/all-reports (Admin only)
async function getAllReports(req, res) {
  try {
    const fromDate   = (req.query.from_date || '').trim();
    const toDate     = (req.query.to_date || '').trim();
    const department = (req.query.department || '').trim();
    const systemId   = (req.query.system_id || '').trim();
    const agentId    = (req.query.agent_id || '').trim();
    const status     = (req.query.status || '').trim();

    let sql = `
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
    `;
    const params = [];

    if (fromDate) {
      sql += ` AND r.maintenance_date >= ?`;
      params.push(fromDate);
    }
    if (toDate) {
      sql += ` AND r.maintenance_date <= ?`;
      params.push(toDate);
    }
    if (department) {
      sql += ` AND s.department = ?`;
      params.push(department);
    }
    if (systemId) {
      sql += ` AND (r.system_id LIKE ? OR s.equipment_name LIKE ?)`;
      params.push(`%${systemId}%`, `%${systemId}%`);
    }
    if (agentId) {
      sql += ` AND (r.agent_id = ? OR a.name LIKE ?)`;
      params.push(agentId, `%${agentId}%`);
    }
    if (status) {
      sql += ` AND r.overall_status = ?`;
      params.push(status);
    }

    sql += ` ORDER BY r.maintenance_date DESC, r.maintenance_time DESC`;

    const [reports] = await pool.execute(sql, params);

    return res.json({
      success: true,
      count: reports.length,
      reports,
    });
  } catch (err) {
    console.error('[All Reports Error]', err);
    return res.status(500).json({ success: false, error: 'Database error fetching reports.' });
  }
}

// Routes
router.get('/checklist-questions', requireAuth(), getChecklistQuestions);
router.get('/checklist-questions.php', requireAuth(), getChecklistQuestions);

router.post('/verify-location', requireAuth('agent'), verifyLocation);
router.post('/verify-location.php', requireAuth('agent'), verifyLocation);

router.post('/submit-report', requireAuth('agent'), submitReport);
router.post('/submit-report.php', requireAuth('agent'), submitReport);

router.get('/agent-reports', requireAuth('agent'), getAgentReports);
router.get('/agent-reports.php', requireAuth('agent'), getAgentReports);

router.get('/report-details', requireAuth(), getReportDetails);
router.get('/report-details.php', requireAuth(), getReportDetails);

router.get('/all-reports', requireAuth('admin'), getAllReports);
router.get('/all-reports.php', requireAuth('admin'), getAllReports);

module.exports = router;
