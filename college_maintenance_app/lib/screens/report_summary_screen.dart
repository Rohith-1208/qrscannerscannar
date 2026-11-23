import 'package:flutter/material.dart';
import '../utils/app_theme.dart';
import 'agent_dashboard_screen.dart';

// ==============================================================================
// Screen 4.6: Maintenance Report Submission Confirmation
// ==============================================================================

class ReportSummaryScreen extends StatelessWidget {
  final Map<String, dynamic> reportData;

  const ReportSummaryScreen({super.key, required this.reportData});

  @override
  Widget build(BuildContext context) {
    final String reportId = reportData['report_id'] ?? 'REP-UNKNOWN';
    final String systemId = reportData['system_id'] ?? '';
    final String equipmentName = reportData['equipment_name'] ?? '';
    final String department = reportData['department'] ?? '';
    final String roomNumber = reportData['room_number'] ?? '';
    final String agentName = reportData['agent_name'] ?? '';
    final String agentId = reportData['agent_id'] ?? '';
    final String mDate = reportData['maintenance_date'] ?? '';
    final String mTime = reportData['maintenance_time'] ?? '';
    final String overallStatus = reportData['overall_status'] ?? 'No issues reported';
    final bool hasIssues = overallStatus == 'Issues identified';

    return PopScope(
      canPop: false,
      onPopInvokedWithResult: (didPop, result) {
        if (didPop) return;
        Navigator.pushAndRemoveUntil(
          context,
          MaterialPageRoute(builder: (_) => const AgentDashboardScreen()),
          (r) => false,
        );
      },
      child: Scaffold(
        appBar: AppBar(
          title: const Text('Report Confirmation'),
          automaticallyImplyLeading: false,
        ),
        body: SafeArea(
          child: SingleChildScrollView(
            padding: const EdgeInsets.all(24),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                // Success Icon
                Center(
                  child: Container(
                    width: 76,
                    height: 76,
                    decoration: BoxDecoration(
                      color: const Color(0xFFECFDF5),
                      shape: BoxShape.circle,
                      border: Border.all(color: const Color(0xFFA7F3D0), width: 2),
                    ),
                    child: const Icon(
                      Icons.check_circle_rounded,
                      color: AppTheme.successGreen,
                      size: 46,
                    ),
                  ),
                ),
                const SizedBox(height: 18),
                const Text(
                  'Report Successfully Saved',
                  textAlign: TextAlign.center,
                  style: TextStyle(
                    fontSize: 22,
                    fontWeight: FontWeight.w800,
                    color: AppTheme.textDark,
                  ),
                ),
                const SizedBox(height: 6),
                const Text(
                  'Saved securely to college MySQL database',
                  textAlign: TextAlign.center,
                  style: TextStyle(fontSize: 13, color: AppTheme.textMuted),
                ),
                const SizedBox(height: 24),

                // Report Summary Details Card
                Card(
                  child: Padding(
                    padding: const EdgeInsets.all(20),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        _summaryRow('Report ID', reportId, isBold: true, valueColor: AppTheme.primaryBlue),
                        const Divider(height: 20),
                        _summaryRow('System ID', systemId, isBold: true),
                        const Divider(height: 20),
                        _summaryRow('Equipment Name', equipmentName),
                        const Divider(height: 20),
                        _summaryRow('Department', '$department (Room $roomNumber)'),
                        const Divider(height: 20),
                        _summaryRow('Service Agent', '$agentName ($agentId)'),
                        const Divider(height: 20),
                        _summaryRow('Date & Time', '$mDate at $mTime'),
                        const Divider(height: 20),
                        _summaryRow(
                          'GPS Verification',
                          'Verified (Within 10m Radius)',
                          valueColor: AppTheme.successGreen,
                          isBold: true,
                        ),
                        const Divider(height: 20),
                        _summaryRow(
                          'Overall Status',
                          overallStatus,
                          valueColor: hasIssues ? AppTheme.dangerRed : AppTheme.successGreen,
                          isBold: true,
                        ),
                      ],
                    ),
                  ),
                ),
                const SizedBox(height: 32),

                // Return to Dashboard Button
                ElevatedButton(
                  style: ElevatedButton.styleFrom(
                    backgroundColor: AppTheme.primaryBlue,
                    padding: const EdgeInsets.symmetric(vertical: 16),
                  ),
                  onPressed: () {
                    Navigator.pushAndRemoveUntil(
                      context,
                      MaterialPageRoute(builder: (_) => const AgentDashboardScreen()),
                      (r) => false,
                    );
                  },
                  child: const Text(
                    'Return to Dashboard',
                    style: TextStyle(fontSize: 16, fontWeight: FontWeight.w700),
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }

  Widget _summaryRow(String label, String value, {bool isBold = false, Color? valueColor}) {
    return Row(
      mainAxisAlignment: MainAxisAlignment.spaceBetween,
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          label,
          style: const TextStyle(fontSize: 13, color: AppTheme.textMuted, fontWeight: FontWeight.w500),
        ),
        const SizedBox(width: 14),
        Expanded(
          child: Text(
            value,
            textAlign: TextAlign.end,
            style: TextStyle(
              fontSize: 13,
              fontWeight: isBold ? FontWeight.w700 : FontWeight.w500,
              color: valueColor ?? AppTheme.textDark,
            ),
          ),
        ),
      ],
    );
  }
}
