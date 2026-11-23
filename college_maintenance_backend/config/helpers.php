<?php
// ==============================================================================
// Utility & Security Functions
// ==============================================================================

require_once __DIR__ . '/database.php';

/**
 * Generate HMAC-SHA256 signature for a QR payload.
 */
function generateQrSignature(string $systemId, float $latitude, float $longitude, float $radius, int $version): string {
    // Format lat/lon with standard 6 decimal places for deterministic hashing
    $normalizedLat = number_format($latitude, 6, '.', '');
    $normalizedLon = number_format($longitude, 6, '.', '');
    $normalizedRadius = number_format($radius, 2, '.', '');
    
    $payloadString = "{$systemId}|{$normalizedLat}|{$normalizedLon}|{$normalizedRadius}|{$version}";
    return hash_hmac('sha256', $payloadString, QR_HMAC_SECRET_KEY);
}

/**
 * Verify HMAC-SHA256 signature for a QR payload.
 */
function verifyQrSignature(string $systemId, float $latitude, float $longitude, float $radius, int $version, string $signature): bool {
    $expected = generateQrSignature($systemId, $latitude, $longitude, $radius, $version);
    return hash_equals(strtolower($expected), strtolower($signature));
}

/**
 * Calculate distance in metres between two coordinates using the Haversine formula.
 * Earth radius = 6,371,000 metres.
 */
function calculateHaversineDistance(float $lat1, float $lon1, float $lat2, float $lon2): float {
    $earthRadius = 6371000.0; // Metres

    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);

    $a = sin($dLat / 2) * sin($dLat / 2) +
         cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
         sin($dLon / 2) * sin($dLon / 2);

    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

    return round($earthRadius * $c, 2);
}

/**
 * Parse JSON body from php://input safely.
 */
function getJsonInput(): array {
    $raw = file_get_contents('php://input');
    if (empty($raw)) {
        return [];
    }
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        jsonResponse(['success' => false, 'error' => 'Invalid JSON input payload'], 400);
    }
    return $decoded;
}

/**
 * Generate an API session token for an authenticated user.
 */
function createAuthToken(string $userId, string $userType): string {
    $db = Database::getConnection();
    
    // Generate 64-character random hex token
    $rawToken = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $rawToken);
    
    $expiry = date('Y-m-d H:i:s', strtotime('+' . TOKEN_EXPIRY_DAYS . ' days'));
    
    $stmt = $db->prepare("
        INSERT INTO auth_tokens (user_id, user_type, token_hash, expires_at)
        VALUES (:user_id, :user_type, :token_hash, :expires_at)
    ");
    $stmt->execute([
        ':user_id'    => $userId,
        ':user_type'  => $userType,
        ':token_hash' => $tokenHash,
        ':expires_at' => $expiry
    ]);
    
    return $rawToken;
}

/**
 * Authenticate incoming request via Authorization: Bearer <token>.
 * Returns array with ['user_id' => ..., 'user_type' => ..., 'user' => ...]
 */
function requireAuth(?string $expectedType = null): array {
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (empty($authHeader) && function_exists('apache_request_headers')) {
        $headers = apache_request_headers();
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    }
    
    if (empty($authHeader) || !preg_match('/Bearer\s+(\S+)/i', $authHeader, $matches)) {
        jsonResponse(['success' => false, 'error' => 'Unauthorized. Authorization token required.'], 401);
    }
    
    $rawToken = $matches[1];
    $tokenHash = hash('sha256', $rawToken);
    
    $db = Database::getConnection();
    $stmt = $db->prepare("
        SELECT token_id, user_id, user_type, expires_at
        FROM auth_tokens
        WHERE token_hash = :hash AND expires_at > NOW()
        LIMIT 1
    ");
    $stmt->execute([':hash' => $tokenHash]);
    $tokenRecord = $stmt->fetch();
    
    if (!$tokenRecord) {
        jsonResponse(['success' => false, 'error' => 'Session expired or invalid token. Please log in again.'], 401);
    }
    
    if ($expectedType !== null && $tokenRecord['user_type'] !== $expectedType) {
        jsonResponse(['success' => false, 'error' => 'Forbidden: insufficient privileges.'], 403);
    }
    
    // Fetch user details
    if ($tokenRecord['user_type'] === 'agent') {
        $uStmt = $db->prepare("SELECT agent_id, name, email, status FROM agents WHERE agent_id = :id LIMIT 1");
        $uStmt->execute([':id' => $tokenRecord['user_id']]);
        $user = $uStmt->fetch();
        if (!$user || $user['status'] !== 'active') {
            jsonResponse(['success' => false, 'error' => 'Agent account is inactive or not found.'], 403);
        }
    } else {
        $uStmt = $db->prepare("SELECT admin_id, name, email FROM admins WHERE admin_id = :id LIMIT 1");
        $uStmt->execute([':id' => $tokenRecord['user_id']]);
        $user = $uStmt->fetch();
        if (!$user) {
            jsonResponse(['success' => false, 'error' => 'Admin account not found.'], 403);
        }
    }
    
    return [
        'user_id'   => $tokenRecord['user_id'],
        'user_type' => $tokenRecord['user_type'],
        'user'      => $user
    ];
}
