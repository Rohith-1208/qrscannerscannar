import 'dart:math' as math;

// ==============================================================================
// Haversine Distance Calculator (Metres)
// ==============================================================================

class HaversineCalculator {
  static const double earthRadiusMetres = 6371000.0;

  /// Calculate geographical distance in metres between two coordinate pairs.
  static double calculateDistance({
    required double lat1,
    required double lon1,
    required double lat2,
    required double lon2,
  }) {
    final double phi1 = _degreesToRadians(lat1);
    final double phi2 = _degreesToRadians(lat2);
    final double deltaPhi = _degreesToRadians(lat2 - lat1);
    final double deltaLambda = _degreesToRadians(lon2 - lon1);

    final double a = math.sin(deltaPhi / 2) * math.sin(deltaPhi / 2) +
        math.cos(phi1) *
            math.cos(phi2) *
            math.sin(deltaLambda / 2) *
            math.sin(deltaLambda / 2);

    final double c = 2 * math.atan2(math.sqrt(a), math.sqrt(1 - a));

    return earthRadiusMetres * c;
  }

  /// Calculate 3D Euclidean + Haversine distance in metres taking altitude into account.
  static double calculateDistance3D({
    required double lat1,
    required double lon1,
    required double alt1,
    required double lat2,
    required double lon2,
    required double alt2,
  }) {
    final double horizDist = calculateDistance(
      lat1: lat1,
      lon1: lon1,
      lat2: lat2,
      lon2: lon2,
    );
    final double altDiff = (alt1 - alt2).abs();
    return math.sqrt(horizDist * horizDist + altDiff * altDiff);
  }

  static double _degreesToRadians(double degrees) {
    return degrees * math.pi / 180.0;
  }
}
