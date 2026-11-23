import 'package:flutter/material.dart';
import '../models/maintenance_report_model.dart';
import '../services/api_service.dart';
import '../utils/app_theme.dart';
import 'report_detail_screen.dart';

// ==============================================================================
// Screen 4.7: My Maintenance Reports History
// ==============================================================================

class MyReportsScreen extends StatefulWidget {
  const MyReportsScreen({super.key});

  @override
  State<MyReportsScreen> createState() => _MyReportsScreenState();
}

class _MyReportsScreenState extends State<MyReportsScreen> {
  List<MaintenanceReportModel> _reports = [];
  bool _isLoading = true;
  String? _errorMessage;

  @override
  void initState() {
    super.initState();
    _loadReports();
  }

  Future<void> _loadReports() async {
    setState(() {
      _isLoading = true;
      _errorMessage = null;
    });

    try {
      final List<MaintenanceReportModel> fetched = await ApiService.getAgentReports();
      if (mounted) {
        setState(() {
          _reports = fetched;
          _isLoading = false;
        });
      }
    } catch (e) {
      if (mounted) {
        setState(() {
          _errorMessage = e.toString();
          _isLoading = false;
        });
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('My Maintenance Reports'),
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator(color: AppTheme.primaryBlue))
          : _errorMessage != null
              ? Center(
                  child: Padding(
                    padding: const EdgeInsets.all(24),
                    child: Text(_errorMessage!, style: const TextStyle(color: AppTheme.dangerRed)),
                  ),
                )
              : RefreshIndicator(
              onRefresh: _loadReports,
              child: _reports.isEmpty
                  ? Center(
                      child: SingleChildScrollView(
                        physics: const AlwaysScrollableScrollPhysics(),
                        padding: const EdgeInsets.all(24),
                        child: Column(
                          mainAxisAlignment: MainAxisAlignment.center,
                          children: [
                            Icon(Icons.assignment_late_outlined, size: 56, color: Colors.grey.shade400),
                            const SizedBox(height: 16),
                            const Text(
                              'No Reports Logged Yet',
                              style: TextStyle(fontSize: 18, fontWeight: FontWeight.w700, color: AppTheme.textDark),
                            ),
                            const SizedBox(height: 8),
                            const Text(
                              'Scan an equipment QR code from the dashboard to submit your first inspection report.',
                              textAlign: TextAlign.center,
                              style: TextStyle(fontSize: 13, color: AppTheme.textMuted),
                            ),
                          ],
                        ),
                      ),
                    )
                  : ListView.separated(
                      padding: const EdgeInsets.all(16),
                      itemCount: _reports.length,
                      separatorBuilder: (_, __) => const SizedBox(height: 12),
                      itemBuilder: (context, index) {
                        final report = _reports[index];
                        final bool hasIssues = report.overallStatus == 'Issues identified';

                        return Card(
                          elevation: 1,
                          child: InkWell(
                            borderRadius: BorderRadius.circular(14),
                            onTap: () {
                              Navigator.push(
                                context,
                                MaterialPageRoute(
                                  builder: (_) => ReportDetailScreen(reportId: report.reportId),
                                ),
                              );
                            },
                            child: Padding(
                              padding: const EdgeInsets.all(16),
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Row(
                                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                                    children: [
                                      Text(
                                        report.reportId,
                                        style: const TextStyle(
                                          fontWeight: FontWeight.w700,
                                          fontSize: 14,
                                          color: AppTheme.primaryBlue,
                                        ),
                                      ),
                                      Container(
                                        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                                        decoration: BoxDecoration(
                                          color: hasIssues ? const Color(0xFFFEF2F2) : const Color(0xFFECFDF5),
                                          borderRadius: BorderRadius.circular(12),
                                        ),
                                        child: Text(
                                          hasIssues ? 'Issues Identified' : 'No Issues',
                                          style: TextStyle(
                                            fontSize: 11,
                                            fontWeight: FontWeight.w700,
                                            color: hasIssues ? AppTheme.dangerRed : AppTheme.successGreen,
                                          ),
                                        ),
                                      ),
                                    ],
                                  ),
                                  const SizedBox(height: 8),
                                  Text(
                                    '${report.systemId} - ${report.equipmentName}',
                                    style: const TextStyle(
                                      fontSize: 15,
                                      fontWeight: FontWeight.w600,
                                      color: AppTheme.textDark,
                                    ),
                                  ),
                                  const SizedBox(height: 4),
                                  Text(
                                    '${report.department} (Room ${report.roomNumber})',
                                    style: const TextStyle(fontSize: 12, color: AppTheme.textMuted),
                                  ),
                                  const Divider(height: 20),
                                  Row(
                                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                                    children: [
                                      Row(
                                        children: [
                                          const Icon(Icons.access_time_rounded, size: 14, color: AppTheme.textMuted),
                                          const SizedBox(width: 4),
                                          Text(
                                            '${report.maintenanceDate}  ${report.maintenanceTime}',
                                            style: const TextStyle(fontSize: 12, color: AppTheme.textMuted),
                                          ),
                                        ],
                                      ),
                                      const Row(
                                        children: [
                                          Text(
                                            'Details',
                                            style: TextStyle(fontSize: 12, fontWeight: FontWeight.w600, color: AppTheme.accentBlue),
                                          ),
                                          Icon(Icons.chevron_right, size: 16, color: AppTheme.accentBlue),
                                        ],
                                      ),
                                    ],
                                  ),
                                ],
                              ),
                            ),
                          ),
                        );
                      },
                    ),
            ),
    );
  }
}
