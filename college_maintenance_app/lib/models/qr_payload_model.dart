import 'dart:convert';

// ==============================================================================
// Scanned QR Payload Model
// ==============================================================================

class QrPayloadModel {
  final String systemId;
  final double latitude;
  final double longitude;
  final double radius;
  final int version;
  final String signature;

  QrPayloadModel({
    required this.systemId,
    required this.latitude,
    required this.longitude,
    required this.radius,
    required this.version,
    required this.signature,
  });

  /// Parse from raw string scanned from camera
  static QrPayloadModel? fromRawString(String raw) {
    try {
      final dynamic decoded = jsonDecode(raw.trim());
      if (decoded is! Map<String, dynamic>) return null;

      final String sysId = (decoded['system_id'] ?? '').toString().trim();
      final double? lat = double.tryParse(decoded['latitude']?.toString() ?? '');
      final double? lon = double.tryParse(decoded['longitude']?.toString() ?? '');
      final double? rad = double.tryParse(decoded['radius']?.toString() ?? '10');
      final int? ver = int.tryParse(decoded['version']?.toString() ?? '1');
      final String sig = (decoded['signature'] ?? '').toString().trim();

      if (sysId.isEmpty || lat == null || lon == null || rad == null || ver == null || sig.isEmpty) {
        return null;
      }

      return QrPayloadModel(
        systemId: sysId,
        latitude: lat,
        longitude: lon,
        radius: rad,
        version: ver,
        signature: sig,
      );
    } catch (_) {
      return null;
    }
  }

  Map<String, dynamic> toJson() {
    return {
      'system_id': systemId,
      'latitude': latitude,
      'longitude': longitude,
      'radius': radius,
      'version': version,
      'signature': signature,
    };
  }
}
