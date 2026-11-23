const assert = require('assert');
const { generateQrSignature, verifyQrSignature } = require('./utils/cryptoHelper');
const { calculateHaversineDistance } = require('./utils/haversine');

console.log('=== Running Node.js / Express Backend Verification Tests ===');

// 1. Test HMAC-SHA256 Signature Generation & Verification
const systemId = 'SYS-001';
const lat = 13.075100;
const lon = 80.117000;
const radius = 10.0;
const version = 1;

const sig = generateQrSignature(systemId, lat, lon, radius, version);
console.log(`[PASS] Generated Signature: ${sig.substring(0, 16)}...`);

// Verify identical signature passes
const isValid = verifyQrSignature(systemId, lat, lon, radius, version, sig);
assert.strictEqual(isValid, true, 'Signature verification should pass for identical parameters');
console.log('[PASS] Valid signature successfully verified');

// Verify tampered coordinate fails
const isTampered = verifyQrSignature(systemId, 13.075101, lon, radius, version, sig);
assert.strictEqual(isTampered, false, 'Tampered latitude must fail signature verification');
console.log('[PASS] Tampered coordinate rejection verified');

// Verify tampered version fails
const isVersionTampered = verifyQrSignature(systemId, lat, lon, radius, 2, sig);
assert.strictEqual(isVersionTampered, false, 'Tampered version must fail signature verification');
console.log('[PASS] Tampered version rejection verified');

// 2. Test Haversine Calculation
const d0 = calculateHaversineDistance(lat, lon, lat, lon);
assert(d0 < 0.01, 'Same point distance must be 0');
console.log(`[PASS] Haversine same-point distance: ${d0}m`);

// 5m proximity test (13.075145, 80.117000)
const d5 = calculateHaversineDistance(lat, lon, 13.075145, lon);
console.log(`[PASS] Haversine ~5m distance: ${d5}m`);
assert(d5 <= 10.0, 'Should be within 10m allowed radius');

// 50m distance test (13.075550, 80.117000)
const d50 = calculateHaversineDistance(lat, lon, 13.075550, lon);
console.log(`[PASS] Haversine ~50m distance: ${d50}m`);
assert(d50 > 10.0, 'Should exceed 10m allowed radius');

console.log('=== ALL NODE.JS BACKEND TESTS PASSED SUCCESSFULLY ===');
