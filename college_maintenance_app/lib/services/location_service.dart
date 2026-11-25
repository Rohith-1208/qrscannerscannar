import 'package:geolocator/geolocator.dart';
import '../utils/constants.dart';

// ==============================================================================
// GPS Location Service
// ==============================================================================

class LocationResult {
  final bool isSuccess;
  final Position? position;
  final String? errorMessage;
  final bool isAccuracyInsufficient;

  LocationResult({
    required this.isSuccess,
    this.position,
    this.errorMessage,
    this.isAccuracyInsufficient = false,
  });
}

class LocationService {
  /// Request GPS permissions and obtain high-accuracy device coordinates.
  static Future<LocationResult> getCurrentLocation() async {
    // 1. Check if location services are enabled on device
    final bool serviceEnabled = await Geolocator.isLocationServiceEnabled();
    if (!serviceEnabled) {
      return LocationResult(
        isSuccess: false,
        errorMessage: 'Location services are disabled on your device. Please enable GPS in device settings.',
      );
    }

    // 2. Check and request location permissions
    LocationPermission permission = await Geolocator.checkPermission();
    if (permission == LocationPermission.denied) {
      permission = await Geolocator.requestPermission();
      if (permission == LocationPermission.denied) {
        return LocationResult(
          isSuccess: false,
          errorMessage: 'Location permissions were denied by user.',
        );
      }
    }

    if (permission == LocationPermission.deniedForever) {
      return LocationResult(
        isSuccess: false,
        errorMessage: 'Location permissions are permanently denied. Please enable them in app settings.',
      );
    }

    // 3. Obtain reliable current GPS position
    try {
      Position? position;
      try {
        position = await Geolocator.getCurrentPosition(
          locationSettings: const LocationSettings(
            accuracy: LocationAccuracy.high,
            timeLimit: Duration(seconds: 8),
          ),
        );
      } catch (_) {
        // Fallback to last known position if fresh fix times out
        position = await Geolocator.getLastKnownPosition();
      }

      position ??= await Geolocator.getLastKnownPosition();

      if (position == null) {
        return LocationResult(
          isSuccess: false,
          errorMessage: 'Unable to acquire GPS signal. Please ensure Location is enabled.',
        );
      }

      return LocationResult(
        isSuccess: true,
        position: position,
      );
    } catch (e) {
      return LocationResult(
        isSuccess: false,
        errorMessage: 'Unable to acquire GPS signal: $e',
      );
    }
  }
}
