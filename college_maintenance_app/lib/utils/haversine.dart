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

  static double _degreesToRadians(double degrees) {
    return degrees * math.pi / 180.0;
  }
}
