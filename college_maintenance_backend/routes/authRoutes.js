const express = require('express');
const router = express.Router();
const bcrypt = require('bcryptjs');
const { pool } = require('../config/db');
const { generateAuthToken, hashToken } = require('../utils/cryptoHelper');
const config = require('../config/constants');

// Handler: Agent Login
async function handleAgentLogin(req, res) {
  try {
    const login = (req.body.login || req.body.email || req.body.agent_id || '').trim();
    const password = String(req.body.password || '');

    if (!login || !password) {
      return res.status(400).json({ success: false, error: 'Agent ID/Email and password are required.' });
    }

    const [agents] = await pool.execute(
      `SELECT agent_id, name, email, password_hash, status 
       FROM agents 
       WHERE (agent_id = ? OR email = ?) 
       LIMIT 1`,
      [login, login]
    );

    if (agents.length === 0) {
      return res.status(401).json({ success: false, error: 'Invalid credentials. Please verify your Agent ID and password.' });
    }

    const agent = agents[0];
    const isMatch = await bcrypt.compare(password, agent.password_hash);
    if (!isMatch) {
      return res.status(401).json({ success: false, error: 'Invalid credentials. Please verify your Agent ID and password.' });
    }

    if (agent.status !== 'active') {
      return res.status(403).json({ success: false, error: 'Account is currently inactive. Contact your college system administrator.' });
    }

    // Generate Session Token
    const { rawToken, tokenHash } = generateAuthToken();
    const expiry = new Date();
    expiry.setDate(expiry.getDate() + config.TOKEN_EXPIRY_DAYS);

    await pool.execute(
      `INSERT INTO auth_tokens (user_id, user_type, token_hash, expires_at) VALUES (?, 'agent', ?, ?)`,
      [agent.agent_id, tokenHash, expiry]
    );

    return res.json({
      success: true,
      message: 'Login successful',
      token: rawToken,
      agent: {
        agent_id: agent.agent_id,
        name: agent.name,
        email: agent.email,
        status: agent.status,
      },
    });
  } catch (err) {
    console.error('[Agent Login Error]', err);
    return res.status(500).json({ success: false, error: 'Internal server error during authentication.' });
  }
}

// Handler: Admin Login
async function handleAdminLogin(req, res) {
  try {
    const email = (req.body.email || '').trim();
    const password = String(req.body.password || '');

    if (!email || !password) {
      return res.status(400).json({ success: false, error: 'Admin email and password are required.' });
    }

    const [admins] = await pool.execute(
      `SELECT admin_id, name, email, password_hash FROM admins WHERE email = ? LIMIT 1`,
      [email]
    );

    if (admins.length === 0) {
      return res.status(401).json({ success: false, error: 'Invalid email or password.' });
    }

    const admin = admins[0];
    const isMatch = await bcrypt.compare(password, admin.password_hash);
    if (!isMatch) {
      return res.status(401).json({ success: false, error: 'Invalid email or password.' });
    }

    // Generate Session Token
    const { rawToken, tokenHash } = generateAuthToken();
    const expiry = new Date();
    expiry.setDate(expiry.getDate() + config.TOKEN_EXPIRY_DAYS);

    await pool.execute(
      `INSERT INTO auth_tokens (user_id, user_type, token_hash, expires_at) VALUES (?, 'admin', ?, ?)`,
      [String(admin.admin_id), tokenHash, expiry]
    );

    return res.json({
      success: true,
      message: 'Admin authentication successful',
      token: rawToken,
      admin: {
        admin_id: admin.admin_id,
        name: admin.name,
        email: admin.email,
      },
    });
  } catch (err) {
    console.error('[Admin Login Error]', err);
    return res.status(500).json({ success: false, error: 'Internal server error.' });
  }
}

// Handler: Logout
async function handleLogout(req, res) {
  try {
    const authHeader = req.headers['authorization'] || '';
    const match = authHeader.match(/Bearer\s+(\S+)/i);
    if (match) {
      const tokenHash = hashToken(match[1]);
      await pool.execute(`DELETE FROM auth_tokens WHERE token_hash = ?`, [tokenHash]);
    }
    return res.json({ success: true, message: 'Logged out successfully' });
  } catch (err) {
    console.error('[Logout Error]', err);
    return res.status(500).json({ success: false, error: 'Logout failed.' });
  }
}

// Support both standard route and .php extension route for 100% backward compatibility
router.post('/agent-login', handleAgentLogin);
router.post('/agent-login.php', handleAgentLogin);

router.post('/admin-login', handleAdminLogin);
router.post('/admin-login.php', handleAdminLogin);

router.post('/logout', handleLogout);
router.post('/logout.php', handleLogout);

module.exports = router;
