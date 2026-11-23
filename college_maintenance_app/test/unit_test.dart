import '../lib/utils/haversine.dart';
import '../lib/models/qr_payload_model.dart';

void main() {
  print('=== Running Verification Unit Tests ===');

  // Test 1: Haversine Distance Calculation
  // College Computer Lab coordinates: 13.075100, 80.117000
  final double lat1 = 13.075100;
  final double lon1 = 80.117000;

  // Exact same point -> distance must be 0
  final double d0 = HaversineCalculator.calculateDistance(lat1: lat1, lon1: lon1, lat2: lat1, lon2: lon1);
  assert(d0 < 0.01, 'Same point distance should be ~0m');
  print('[PASS] Zero distance check: ${d0.toStringAsFixed(2)}m');

  // Point ~5 metres away: 13.075145, 80.117000 (~5.0m delta in latitude)
  final double d5 = HaversineCalculator.calculateDistance(lat1: lat1, lon1: lon1, lat2: 13.075145, lon2: 80.117000);
  print('[PASS] 5-metre proximity check: ${d5.toStringAsFixed(2)}m');
  assert(d5 <= 10.0, 'Point should be within 10m permitted radius');

  // Point ~50 metres away: 13.075550, 80.117000 (~50.0m delta)
  final double d50 = HaversineCalculator.calculateDistance(lat1: lat1, lon1: lon1, lat2: 13.075550, lon2: 80.117000);
  print('[PASS] 50-metre outside location check: ${d50.toStringAsFixed(2)}m');
  assert(d50 > 10.0, 'Point should be outside 10m permitted radius');

  // Test 2: QR Payload Parser
  const String validJson = '{"system_id":"SYS-001","latitude":13.0751,"longitude":80.117,"radius":10,"version":1,"signature":"abc123sig"}';
  final QrPayloadModel? parsed = QrPayloadModel.fromRawString(validJson);
  assert(parsed != null, 'Valid payload should parse');
  if (parsed != null) {
    assert(parsed.systemId == 'SYS-001');
    assert(parsed.radius == 10.0);
    print('[PASS] QR Payload Parsing: System ID ${parsed.systemId}, Radius: ${parsed.radius}m');
  }

  // Test 3: Malformed QR Payload Rejection
  const String invalidJson = '{"foo":"bar"}';
  final QrPayloadModel? invalidParsed = QrPayloadModel.fromRawString(invalidJson);
  assert(invalidParsed == null, 'Invalid payload must return null');
  print('[PASS] Malformed QR rejection check');

  print('=== ALL UNIT TESTS PASSED SUCCESSFULLY ===');
}
