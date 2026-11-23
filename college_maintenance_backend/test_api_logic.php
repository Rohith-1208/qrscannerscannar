<?php
// ==============================================================================
// PHP Logic & Cryptographic Verification Test Suite
// ==============================================================================

require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/config/helpers.php';

echo "=== Running PHP Backend Verification Tests ===\n";

// 1. Test HMAC-SHA256 Signature Generation & Verification
$systemId = 'SYS-001';
$lat = 13.075100;
$lon = 80.117000;
$radius = 10.0;
$version = 1;

$sig = generateQrSignature($systemId, $lat, $lon, $radius, $version);
echo "[PASS] Generated Signature: " . substr($sig, 0, 16) . "...\n";

// Verify valid signature
$isValid = verifyQrSignature($systemId, $lat, $lon, $radius, $version, $sig);
assert($isValid === true, "Signature verification should pass for identical parameters");
echo "[PASS] Valid signature successfully verified\n";

// Verify tampered signature fails
$isTampered = verifyQrSignature($systemId, 13.075101, $lon, $radius, $version, $sig); // 0.000001 lat change
assert($isTampered === false, "Tampered latitude must fail signature verification");
echo "[PASS] Tampered coordinate rejection verified\n";

$isVersionTampered = verifyQrSignature($systemId, $lat, $lon, $radius, 2, $sig); // version changed to 2
assert($isVersionTampered === false, "Tampered version must fail signature verification");
echo "[PASS] Tampered version rejection verified\n";

// 2. Test PHP Haversine Calculation
$d0 = calculateHaversineDistance($lat, $lon, $lat, $lon);
assert($d0 < 0.01, "Same point distance must be 0");
echo "[PASS] Haversine same-point distance: {$d0}m\n";

// 5m proximity test (13.075145, 80.117000)
$d5 = calculateHaversineDistance($lat, $lon, 13.075145, $lon);
echo "[PASS] Haversine ~5m distance: {$d5}m\n";
assert($d5 <= 10.0, "Should be within 10m allowed radius");

// 50m distance test (13.075550, 80.117000)
$d50 = calculateHaversineDistance($lat, $lon, 13.075550, $lon);
echo "[PASS] Haversine ~50m distance: {$d50}m\n";
assert($d50 > 10.0, "Should exceed 10m allowed radius");

echo "=== ALL PHP BACKEND TESTS PASSED SUCCESSFULLY ===\n";
