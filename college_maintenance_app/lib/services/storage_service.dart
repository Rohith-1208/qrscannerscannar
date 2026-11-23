import 'dart:convert';
import 'package:shared_preferences/shared_preferences.dart';
import '../models/agent_model.dart';
import '../utils/constants.dart';

// ==============================================================================
// Persistent Storage Service (SharedPreferences)
// ==============================================================================

class StorageService {
  static SharedPreferences? _prefs;

  static Future<void> init() async {
    _prefs ??= await SharedPreferences.getInstance();
  }

  static String? getToken() {
    return _prefs?.getString(AppConstants.keyAuthToken);
  }

  static Future<bool> setToken(String token) async {
    return await _prefs?.setString(AppConstants.keyAuthToken, token) ?? false;
  }

  static AgentModel? getAgent() {
    final String? raw = _prefs?.getString(AppConstants.keyAgentData);
    if (raw == null) return null;
    try {
      return AgentModel.fromJson(jsonDecode(raw));
    } catch (_) {
      return null;
    }
  }

  static Future<bool> setAgent(AgentModel agent) async {
    return await _prefs?.setString(AppConstants.keyAgentData, jsonEncode(agent.toJson())) ?? false;
  }

  static String getServerUrl() {
    return _prefs?.getString(AppConstants.keyServerUrl) ?? AppConstants.defaultBaseUrl;
  }

  static Future<bool> setServerUrl(String url) async {
    return await _prefs?.setString(AppConstants.keyServerUrl, url) ?? false;
  }

  static Future<void> clearSession() async {
    await _prefs?.remove(AppConstants.keyAuthToken);
    await _prefs?.remove(AppConstants.keyAgentData);
  }
}
