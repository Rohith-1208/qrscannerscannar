import 'dart:convert';
import 'dart:io';
import 'package:http/http.dart' as http;
import '../models/checklist_question_model.dart';
import '../models/maintenance_report_model.dart';
import '../models/qr_payload_model.dart';
import 'storage_service.dart';

// ==============================================================================
// PHP REST API Client Service
// ==============================================================================

class ApiService {
  static String get _baseUrl {
    String url = StorageService.getServerUrl().trim();
    if (url.endsWith('/')) {
      url = url.substring(0, url.length - 1);
    }
    return url;
  }

  static Map<String, String> _headers({bool withAuth = true}) {
    final Map<String, String> headers = {
      'Content-Type': 'application/json; charset=UTF-8',
      'Accept': 'application/json',
    };
    if (withAuth) {
      final String? token = StorageService.getToken();
      if (token != null && token.isNotEmpty) {
        headers['Authorization'] = 'Bearer $token';
      }
    }
    return headers;
  }

  // 1. Agent Login
  static Future<Map<String, dynamic>> login(String login, String password) async {
    try {
      final Uri uri = Uri.parse('$_baseUrl/api/auth/agent-login.php');
      final http.Response res = await http.post(
        uri,
        headers: _headers(withAuth: false),
        body: jsonEncode({'login': login, 'password': password}),
      ).timeout(const Duration(seconds: 15));

      final dynamic data = jsonDecode(res.body);
      return data is Map<String, dynamic> ? data : {'success': false, 'error': 'Malformed server response'};
    } catch (e) {
      return {'success': false, 'error': _formatNetworkError(e)};
    }
  }

  // 2. Validate Scanned QR Code
  static Future<Map<String, dynamic>> verifyQr(QrPayloadModel payload) async {
    try {
      final Uri uri = Uri.parse('$_baseUrl/api/qr/verify.php');
      final http.Response res = await http.post(
        uri,
        headers: _headers(),
        body: jsonEncode(payload.toJson()),
      ).timeout(const Duration(seconds: 12));

      final dynamic data = jsonDecode(res.body);
      return data is Map<String, dynamic> ? data : {'success': false, 'error': 'Invalid response'};
    } catch (e) {
      return {'success': false, 'error': _formatNetworkError(e)};
    }
  }

  // 3. Pre-check Location & Distance with Backend
  static Future<Map<String, dynamic>> verifyLocation({
    required String systemId,
    required double agentLatitude,
    required double agentLongitude,
    required double gpsAccuracy,
    required String qrSignature,
    required int qrVersion,
  }) async {
    try {
      final Uri uri = Uri.parse('$_baseUrl/api/maintenance/verify-location.php');
      final http.Response res = await http.post(
        uri,
        headers: _headers(),
        body: jsonEncode({
          'system_id': systemId,
          'agent_latitude': agentLatitude,
          'agent_longitude': agentLongitude,
          'gps_accuracy': gpsAccuracy,
          'qr_signature': qrSignature,
          'qr_version': qrVersion,
        }),
      ).timeout(const Duration(seconds: 12));

      final dynamic data = jsonDecode(res.body);
      return data is Map<String, dynamic> ? data : {'success': false, 'error': 'Invalid response'};
    } catch (e) {
      return {'success': false, 'error': _formatNetworkError(e)};
    }
  }

  // 4. Fetch Checklist Questions
  static Future<List<ChecklistQuestionModel>> getChecklistQuestions(String equipmentType) async {
    try {
      final Uri uri = Uri.parse('$_baseUrl/api/maintenance/checklist-questions.php?equipment_type=${Uri.encodeComponent(equipmentType)}');
      final http.Response res = await http.get(uri, headers: _headers()).timeout(const Duration(seconds: 12));

      final dynamic data = jsonDecode(res.body);
      if (data is Map<String, dynamic> && data['success'] == true && data['questions'] is List) {
        return (data['questions'] as List)
            .map((q) => ChecklistQuestionModel.fromJson(q))
            .toList();
      }
      return [];
    } catch (_) {
      return [];
    }
  }

  // 5. Submit Complete Maintenance Report
  static Future<Map<String, dynamic>> submitReport({
    required String systemId,
    required double agentLatitude,
    required double agentLongitude,
    required double gpsAccuracy,
    required String qrSignature,
    required int qrVersion,
    required List<ChecklistQuestionModel> questions,
    String notes = '',
  }) async {
    try {
      final Uri uri = Uri.parse('$_baseUrl/api/maintenance/submit-report.php');
      final List<Map<String, dynamic>> answersPayload =
          questions.map((q) => q.toSubmissionJson()).toList();

      final http.Response res = await http.post(
        uri,
        headers: _headers(),
        body: jsonEncode({
          'system_id': systemId,
          'agent_latitude': agentLatitude,
          'agent_longitude': agentLongitude,
          'gps_accuracy': gpsAccuracy,
          'qr_signature': qrSignature,
          'qr_version': qrVersion,
          'answers': answersPayload,
          'notes': notes,
        }),
      ).timeout(const Duration(seconds: 20));

      final dynamic data = jsonDecode(res.body);
      return data is Map<String, dynamic> ? data : {'success': false, 'error': 'Invalid response'};
    } catch (e) {
      return {'success': false, 'error': _formatNetworkError(e)};
    }
  }

  // 6. Fetch Logged-in Agent's Reports
  static Future<List<MaintenanceReportModel>> getAgentReports() async {
    try {
      final Uri uri = Uri.parse('$_baseUrl/api/maintenance/agent-reports.php');
      final http.Response res = await http.get(uri, headers: _headers()).timeout(const Duration(seconds: 15));

      final dynamic data = jsonDecode(res.body);
      if (data is Map<String, dynamic> && data['success'] == true && data['reports'] is List) {
        return (data['reports'] as List)
            .map((r) => MaintenanceReportModel.fromJson(r))
            .toList();
      }
      return [];
    } catch (_) {
      return [];
    }
  }

  // 7. Fetch Report Details
  static Future<Map<String, dynamic>?> getReportDetails(String reportId) async {
    try {
      final Uri uri = Uri.parse('$_baseUrl/api/maintenance/report-details.php?report_id=${Uri.encodeComponent(reportId)}');
      final http.Response res = await http.get(uri, headers: _headers()).timeout(const Duration(seconds: 15));

      final dynamic data = jsonDecode(res.body);
      if (data is Map<String, dynamic> && data['success'] == true) {
        return data;
      }
      return null;
    } catch (_) {
      return null;
    }
  }

  // 8. Logout
  static Future<void> logout() async {
    try {
      final Uri uri = Uri.parse('$_baseUrl/api/auth/logout.php');
      await http.post(uri, headers: _headers()).timeout(const Duration(seconds: 5));
    } catch (_) {}
    await StorageService.clearSession();
  }

  static String _formatNetworkError(dynamic e) {
    if (e is SocketException) {
      return 'Cannot connect to backend server ($_baseUrl). Please verify server address and connection.';
    }
    return e.toString();
  }
}
