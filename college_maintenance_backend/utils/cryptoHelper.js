const crypto = require('crypto');
const config = require('../config/constants');

/**
 * Generate HMAC-SHA256 signature for a QR payload including 3D Altitude.
 * Formats lat/lon with 6 decimal places, altitude and radius with 2 decimal places.
 */
function generateQrSignature(systemId, latitude, longitude, altitude = 0, radius, version) {
  const normLat = parseFloat(latitude).toFixed(6);
  const normLon = parseFloat(longitude).toFixed(6);
  const normAlt = parseFloat(altitude || 0).toFixed(2);
  const normRadius = parseFloat(radius).toFixed(2);

  const payloadString = `${systemId}|${normLat}|${normLon}|${normAlt}|${normRadius}|${version}`;
  return crypto.createHmac('sha256', config.QR_HMAC_SECRET_KEY).update(payloadString).digest('hex');
}

/**
 * Timing-safe verification of HMAC-SHA256 signature with 3D Altitude support.
 */
function verifyQrSignature(systemId, latitude, longitude, altitude = 0, radius, version, signature) {
  const expectedWithAlt = generateQrSignature(systemId, latitude, longitude, altitude, radius, version);
  const legacyStr = `${systemId}|${parseFloat(latitude).toFixed(6)}|${parseFloat(longitude).toFixed(6)}|${parseFloat(radius).toFixed(2)}|${version}`;
  const legacyExpected = crypto.createHmac('sha256', config.QR_HMAC_SECRET_KEY).update(legacyStr).digest('hex');

  try {
    const actualBuf = Buffer.from(String(signature).toLowerCase(), 'utf8');
    const expBuf = Buffer.from(expectedWithAlt.toLowerCase(), 'utf8');
    if (expBuf.length === actualBuf.length && crypto.timingSafeEqual(expBuf, actualBuf)) {
      return true;
    }
    const legBuf = Buffer.from(legacyExpected.toLowerCase(), 'utf8');
    if (legBuf.length === actualBuf.length && crypto.timingSafeEqual(legBuf, actualBuf)) {
      return true;
    }
    return false;
  } catch (_) {
    return false;
  }
}

/**
 * Generate random 64-character hex session token and its SHA-256 hash.
 */
function generateAuthToken() {
  const rawToken = crypto.randomBytes(32).toString('hex');
  const tokenHash = crypto.createHash('sha256').update(rawToken).digest('hex');
  return { rawToken, tokenHash };
}

/**
 * Hash raw token to look up in database.
 */
function hashToken(rawToken) {
  return crypto.createHash('sha256').update(rawToken).digest('hex');
}

module.exports = {
  generateQrSignature,
  verifyQrSignature,
  generateAuthToken,
  hashToken,
};
