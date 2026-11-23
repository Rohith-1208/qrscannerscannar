const mysql = require('mysql2/promise');
const config = require('./constants');

// Create MySQL Connection Pool
const pool = mysql.createPool({
  host: config.DB_HOST,
  port: config.DB_PORT,
  user: config.DB_USER,
  password: config.DB_PASS,
  database: config.DB_NAME,
  waitForConnections: true,
  connectionLimit: 15,
  queueLimit: 0,
  enableKeepAlive: true,
  keepAliveInitialDelay: 10000,
});

// Helper to test connectivity
async function testConnection() {
  try {
    const connection = await pool.getConnection();
    console.log(`[Database] Connected successfully to MySQL database '${config.DB_NAME}' on ${config.DB_HOST}:${config.DB_PORT}`);
    connection.release();
    return true;
  } catch (err) {
    console.error(`[Database Error] Failed to connect to MySQL: ${err.message}`);
    return false;
  }
}

module.exports = {
  pool,
  testConnection,
};
