// ==============================================================================
// Application Constants
// ==============================================================================

class AppConstants {
  static const String appName = 'College SysMaint';
  static const String appVersion = '1.0.0';

  // Default Backend URL:
  // - Default set to local Wi-Fi IP so physical Android phones connect immediately
  // - For Android Emulator: Change via Settings gear to http://10.0.2.2:8000
  static const String defaultBaseUrl = 'http://192.168.29.35:8000';

  // Business Rules
  static const double permittedRadiusMetres = 10.0;
  static const double maxAcceptableGpsAccuracyMetres = 25.0;

  // Storage Keys
  static const String keyAuthToken = 'auth_token';
  static const String keyAgentData = 'agent_data';
  static const String keyServerUrl = 'custom_server_url';
}
