const crypto = require('crypto');
const config = require('../config/constants');

/**
 * Generate HMAC-SHA256 signature for a QR payload.
 * Formats lat/lon with 6 decimal places for deterministic cross-platform hashing.
 */
function generateQrSignature(systemId, latitude, longitude, radius, version) {
  const normLat = parseFloat(latitude).toFixed(6);
  const normLon = parseFloat(longitude).toFixed(6);
  const normRadius = parseFloat(radius).toFixed(2);

  const payloadString = `${systemId}|${normLat}|${normLon}|${normRadius}|${version}`;
  return crypto.createHmac('sha256', config.QR_HMAC_SECRET_KEY).update(payloadString).digest('hex');
}

/**
 * Timing-safe verification of HMAC-SHA256 signature.
 */
function verifyQrSignature(systemId, latitude, longitude, radius, version, signature) {
  const expected = generateQrSignature(systemId, latitude, longitude, radius, version);
  try {
    const expectedBuf = Buffer.from(expected.toLowerCase(), 'utf8');
    const actualBuf = Buffer.from(String(signature).toLowerCase(), 'utf8');
    if (expectedBuf.length !== actualBuf.length) return false;
    return crypto.timingSafeEqual(expectedBuf, actualBuf);
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
