const { pool } = require('../config/db');
const { hashToken } = require('../utils/cryptoHelper');

/**
 * Authentication middleware for Express.
 * @param {string|null} expectedType - 'agent' | 'admin' | null
 */
function requireAuth(expectedType = null) {
  return async (req, res, next) => {
    try {
      const authHeader = req.headers['authorization'] || '';
      const match = authHeader.match(/Bearer\s+(\S+)/i);

      if (!match) {
        return res.status(401).json({
          success: false,
          error: 'Unauthorized: Authorization token required.',
        });
      }

      const rawToken = match[1];
      const tokenHash = hashToken(rawToken);

      // Look up token in database
      const [tokens] = await pool.execute(
        `SELECT token_id, user_id, user_type, expires_at 
         FROM auth_tokens 
         WHERE token_hash = ? AND expires_at > NOW() 
         LIMIT 1`,
        [tokenHash]
      );

      if (tokens.length === 0) {
        return res.status(401).json({
          success: false,
          error: 'Session expired or invalid token. Please log in again.',
        });
      }

      const tokenRecord = tokens[0];

      if (expectedType && tokenRecord.user_type !== expectedType) {
        return res.status(403).json({
          success: false,
          error: 'Forbidden: Insufficient privileges.',
        });
      }

      // Fetch user details
      if (tokenRecord.user_type === 'agent') {
        const [agents] = await pool.execute(
          `SELECT agent_id, name, email, status FROM agents WHERE agent_id = ? LIMIT 1`,
          [tokenRecord.user_id]
        );
        if (agents.length === 0 || agents[0].status !== 'active') {
          return res.status(403).json({
            success: false,
            error: 'Agent account is inactive or not found.',
          });
        }
        req.user = agents[0];
        req.userId = tokenRecord.user_id;
        req.userType = 'agent';
      } else {
        const [admins] = await pool.execute(
          `SELECT admin_id, name, email FROM admins WHERE admin_id = ? LIMIT 1`,
          [tokenRecord.user_id]
        );
        if (admins.length === 0) {
          return res.status(403).json({
            success: false,
            error: 'Admin account not found.',
          });
        }
        req.user = admins[0];
        req.userId = tokenRecord.user_id;
        req.userType = 'admin';
      }

      next();
    } catch (err) {
      console.error('[Auth Middleware Error]', err);
      return res.status(500).json({ success: false, error: 'Internal server authentication error.' });
    }
  };
}

module.exports = {
  requireAuth,
};
