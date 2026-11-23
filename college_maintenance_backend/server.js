const express = require('express');
const cors = require('cors');
const path = require('path');
const config = require('./config/constants');
const { testConnection } = require('./config/db');

// Route modules
const authRoutes = require('./routes/authRoutes');
const systemRoutes = require('./routes/systemRoutes');
const qrRoutes = require('./routes/qrRoutes');
const agentRoutes = require('./routes/agentRoutes');
const maintenanceRoutes = require('./routes/maintenanceRoutes');

const app = express();

// Global Middleware
app.use(cors({ origin: '*', methods: ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'] }));
app.use(express.json());
app.use(express.urlencoded({ extended: true }));

// Serve static frontend assets for Admin Web Dashboard
app.use(express.static(path.join(__dirname, 'public')));
app.use('/assets', express.static(path.join(__dirname, 'assets')));

// API Routes
app.use('/api/auth', authRoutes);
app.use('/api/systems', systemRoutes);
app.use('/api/qr', qrRoutes);
app.use('/api/agents', agentRoutes);
app.use('/api/maintenance', maintenanceRoutes);

// Health check endpoint
app.get('/api/health', (req, res) => {
  res.json({
    status: 'online',
    app: config.APP_NAME,
    version: config.APP_VERSION,
    timestamp: new Date().toISOString(),
  });
});

// Admin Dashboard SPA fallback
app.get(['/', '/admin', '/admin/login'], (req, res) => {
  res.sendFile(path.join(__dirname, 'public', 'login.html'));
});
app.get('/admin/dashboard', (req, res) => {
  res.sendFile(path.join(__dirname, 'public', 'dashboard.html'));
});
app.get('/admin/systems', (req, res) => {
  res.sendFile(path.join(__dirname, 'public', 'systems.html'));
});
app.get('/admin/agents', (req, res) => {
  res.sendFile(path.join(__dirname, 'public', 'agents.html'));
});
app.get('/admin/reports', (req, res) => {
  res.sendFile(path.join(__dirname, 'public', 'reports.html'));
});
app.get('/admin/qr-print', (req, res) => {
  res.sendFile(path.join(__dirname, 'public', 'qr_print.html'));
});

// Error handling middleware
app.use((err, req, res, next) => {
  console.error('[Global Error Handler]', err);
  res.status(500).json({ success: false, error: 'Internal Server Error' });
});

// Start Server
const PORT = config.PORT;
app.listen(PORT, async () => {
  console.log(`=======================================================`);
  console.log(`🚀 ${config.APP_NAME} Server (Node.js/Express)`);
  console.log(`📡 Listening on http://localhost:${PORT}`);
  console.log(`🌐 Admin Dashboard: http://localhost:${PORT}/admin`);
  console.log(`=======================================================`);
  
  // Test MySQL connection
  await testConnection();
});
