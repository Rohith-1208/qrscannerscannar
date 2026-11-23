import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'screens/agent_dashboard_screen.dart';
import 'screens/login_screen.dart';
import 'services/storage_service.dart';
import 'utils/app_theme.dart';
import 'utils/constants.dart';

// ==============================================================================
// Application Entry Point
// ==============================================================================

void main() async {
  WidgetsFlutterBinding.ensureInitialized();

  // Set system UI overlay style
  SystemChrome.setSystemUIOverlayStyle(
    const SystemUiOverlayStyle(
      statusBarColor: Colors.transparent,
      statusBarIconBrightness: Brightness.light,
    ),
  );

  // Initialize persistent storage
  await StorageService.init();

  // Check existing session
  final String? existingToken = StorageService.getToken();
  final bool isLoggedIn = existingToken != null && existingToken.isNotEmpty;

  runApp(CollegeMaintenanceApp(isLoggedIn: isLoggedIn));
}

class CollegeMaintenanceApp extends StatelessWidget {
  final bool isLoggedIn;

  const CollegeMaintenanceApp({super.key, required this.isLoggedIn});

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: AppConstants.appName,
      debugShowCheckedModeBanner: false,
      theme: AppTheme.lightTheme,
      home: isLoggedIn ? const AgentDashboardScreen() : const LoginScreen(),
    );
  }
}
