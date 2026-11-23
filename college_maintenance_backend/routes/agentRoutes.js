const express = require('express');
const router = express.Router();
const bcrypt = require('bcryptjs');
const { pool } = require('../config/db');
const { requireAuth } = require('../middleware/auth');

// GET /api/agents/list
async function listAgents(req, res) {
  try {
    const [agents] = await pool.execute(`
      SELECT 
        a.agent_id, 
        a.name, 
        a.email, 
        a.status, 
        a.created_at,
        COUNT(r.report_id) AS total_reports
      FROM agents a
      LEFT JOIN maintenance_reports r ON a.agent_id = r.agent_id
      GROUP BY a.agent_id
      ORDER BY a.created_at DESC
    `);

    return res.json({
      success: true,
      count: agents.length,
      agents,
    });
  } catch (err) {
    console.error('[List Agents Error]', err);
    return res.status(500).json({ success: false, error: 'Database error listing agents.' });
  }
}

// POST /api/agents/create
async function createAgent(req, res) {
  try {
    let agentId = (req.body.agent_id || '').toUpperCase().trim();
    const name = (req.body.name || '').trim();
    const email = (req.body.email || '').toLowerCase().trim();
    const password = String(req.body.password || '');

    if (!name || !email || !password) {
      return res.status(400).json({ success: false, error: 'Agent Name, Email, and Password are required.' });
    }

    if (password.length < 6) {
      return res.status(400).json({ success: false, error: 'Password must be at least 6 characters.' });
    }

    // Auto-generate Agent ID if not provided
    if (!agentId) {
      const [maxId] = await pool.execute(
        `SELECT MAX(CAST(SUBSTRING(agent_id, 5) AS UNSIGNED)) AS max_val FROM agents WHERE agent_id LIKE 'AGT-%'`
      );
      const val = parseInt(maxId[0].max_val || 0, 10);
      agentId = `AGT-${String(val + 1).padStart(3, '0')}`;
    } else {
      const [chk] = await pool.execute(`SELECT agent_id FROM agents WHERE agent_id = ? LIMIT 1`, [agentId]);
      if (chk.length > 0) {
        return res.status(409).json({ success: false, error: `Agent ID '${agentId}' already exists.` });
      }
    }

    // Check unique email
    const [eChk] = await pool.execute(`SELECT agent_id FROM agents WHERE email = ? LIMIT 1`, [email]);
    if (eChk.length > 0) {
      return res.status(409).json({ success: false, error: `Email '${email}' is already registered.` });
    }

    const passwordHash = await bcrypt.hash(password, 10);

    await pool.execute(
      `INSERT INTO agents (agent_id, name, email, password_hash, status) VALUES (?, ?, ?, ?, 'active')`,
      [agentId, name, email, passwordHash]
    );

    return res.status(201).json({
      success: true,
      message: `Service Agent '${name}' registered successfully.`,
      agent: {
        agent_id: agentId,
        name,
        email,
        status: 'active',
      },
    });
  } catch (err) {
    console.error('[Create Agent Error]', err);
    return res.status(500).json({ success: false, error: 'Failed to create agent account.' });
  }
}

// POST or PUT /api/agents/update
async function updateAgent(req, res) {
  try {
    const agentId = (req.body.agent_id || '').toUpperCase().trim();
    if (!agentId) {
      return res.status(400).json({ success: false, error: 'agent_id is required.' });
    }

    const [agents] = await pool.execute(`SELECT * FROM agents WHERE agent_id = ? LIMIT 1`, [agentId]);
    if (agents.length === 0) {
      return res.status(404).json({ success: false, error: `Agent '${agentId}' not found.` });
    }
    const agent = agents[0];

    const name = (req.body.name || agent.name).trim();
    const status = (req.body.status || agent.status).trim();
    const password = (req.body.password || '').trim();

    let sql = `UPDATE agents SET name = ?, status = ?`;
    const params = [name, status];

    if (password) {
      if (password.length < 6) {
        return res.status(400).json({ success: false, error: 'Password must be at least 6 characters.' });
      }
      const hash = await bcrypt.hash(password, 10);
      sql += `, password_hash = ?`;
      params.push(hash);
    }

    sql += ` WHERE agent_id = ?`;
    params.push(agentId);

    await pool.execute(sql, params);

    return res.json({
      success: true,
      message: `Agent '${agentId}' updated successfully.`,
    });
  } catch (err) {
    console.error('[Update Agent Error]', err);
    return res.status(500).json({ success: false, error: 'Failed to update agent.' });
  }
}

// Routes
router.get('/list', requireAuth('admin'), listAgents);
router.get('/list.php', requireAuth('admin'), listAgents);

router.post('/create', requireAuth('admin'), createAgent);
router.post('/create.php', requireAuth('admin'), createAgent);

router.post('/update', requireAuth('admin'), updateAgent);
router.post('/update.php', requireAuth('admin'), updateAgent);
router.put('/update', requireAuth('admin'), updateAgent);
router.put('/update.php', requireAuth('admin'), updateAgent);

module.exports = router;
