require('dotenv').config();

module.exports = {
  APP_NAME: 'College System Maintenance Management',
  APP_VERSION: '1.0.0',
  PORT: process.env.PORT || 8000,
  
  // Database Configuration
  DB_HOST: process.env.DB_HOST || '127.0.0.1',
  DB_PORT: parseInt(process.env.DB_PORT || '3306', 10),
  DB_NAME: process.env.DB_NAME || 'college_maintenance_db',
  DB_USER: process.env.DB_USER || 'root',
  DB_PASS: process.env.DB_PASS || '',

  // Cryptographic Secret for QR HMAC-SHA256 Signatures
  QR_HMAC_SECRET_KEY: process.env.QR_HMAC_SECRET || 'c0ll3g3_m41nt_s3cur3_hm4c_s1gn1ng_k3y_#2026!',

  // Business Rules
  DEFAULT_ALLOWED_RADIUS_METRES: parseFloat(process.env.DEFAULT_ALLOWED_RADIUS_METRES || '50.0'),
  MAX_ACCEPTABLE_GPS_ACCURACY_METRES: parseFloat(process.env.MAX_ACCEPTABLE_GPS_ACCURACY_METRES || '100.0'),
  TOKEN_EXPIRY_DAYS: parseInt(process.env.TOKEN_EXPIRY_DAYS || '30', 10),
};
