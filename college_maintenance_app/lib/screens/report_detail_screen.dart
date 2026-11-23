import 'package:flutter/material.dart';
import '../services/api_service.dart';
import '../utils/app_theme.dart';

// ==============================================================================
// Screen 4.7: Detailed Inspection Report Viewer
// ==============================================================================

class ReportDetailScreen extends StatefulWidget {
  final String reportId;

  const ReportDetailScreen({super.key, required this.reportId});

  @override
  State<ReportDetailScreen> createState() => _ReportDetailScreenState();
}

class _ReportDetailScreenState extends State<ReportDetailScreen> {
  Map<String, dynamic>? _reportData;
  List<dynamic> _answers = [];
  bool _isLoading = true;
  String? _errorMessage;

  @override
  void initState() {
    super.initState();
    _fetchDetails();
  }

  Future<void> _fetchDetails() async {
    setState(() {
      _isLoading = true;
      _errorMessage = null;
    });

    final res = await ApiService.getReportDetails(widget.reportId);

    if (mounted) {
      if (res != null && res['success'] == true) {
        setState(() {
          _reportData = res['report'];
          _answers = res['answers'] ?? [];
          _isLoading = false;
        });
      } else {
        setState(() {
          _errorMessage = res?['error'] ?? 'Failed to load report details.';
          _isLoading = false;
        });
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text(widget.reportId),
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
              : SafeArea(
                  child: SingleChildScrollView(
                    padding: const EdgeInsets.all(18),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        // Equipment & Overall Status Card
                        Card(
                          child: Padding(
                            padding: const EdgeInsets.all(16),
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Row(
                                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                                  children: [
                                    Text(
                                      _reportData?['system_id'] ?? '',
                                      style: const TextStyle(
                                        fontSize: 18,
                                        fontWeight: FontWeight.w800,
                                        color: AppTheme.primaryBlue,
                                      ),
                                    ),
                                    Container(
                                      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                                      decoration: BoxDecoration(
                                        color: _reportData?['overall_status'] == 'Issues identified'
                                            ? const Color(0xFFFEF2F2)
                                            : const Color(0xFFECFDF5),
                                        borderRadius: BorderRadius.circular(20),
                                      ),
                                      child: Text(
                                        _reportData?['overall_status'] ?? '',
                                        style: TextStyle(
                                          fontSize: 12,
                                          fontWeight: FontWeight.w700,
                                          color: _reportData?['overall_status'] == 'Issues identified'
                                              ? AppTheme.dangerRed
                                              : AppTheme.successGreen,
                                        ),
                                      ),
                                    ),
                                  ],
                                ),
                                const SizedBox(height: 6),
                                Text(
                                  _reportData?['equipment_name'] ?? '',
                                  style: const TextStyle(fontSize: 15, fontWeight: FontWeight.w600, color: AppTheme.textDark),
                                ),
                                const SizedBox(height: 4),
                                Text(
                                  '${_reportData?['department'] ?? ''} • Room ${_reportData?['room_number'] ?? ''}',
                                  style: const TextStyle(fontSize: 12, color: AppTheme.textMuted),
                                ),
                                const Divider(height: 20),
                                Row(
                                  children: [
                                    const Icon(Icons.person_outline, size: 16, color: AppTheme.textMuted),
                                    const SizedBox(width: 6),
                                    Text(
                                      'Technician: ${_reportData?['agent_name'] ?? ''}',
                                      style: const TextStyle(fontSize: 12, color: AppTheme.textMuted),
                                    ),
                                    const Spacer(),
                                    Text(
                                      '${_reportData?['maintenance_date']} ${_reportData?['maintenance_time']}',
                                      style: const TextStyle(fontSize: 12, color: AppTheme.textMuted),
                                    ),
                                  ],
                                ),
                              ],
                            ),
                          ),
                        ),
                        const SizedBox(height: 16),

                        // Geo-Verification Evidence Card
                        Card(
                          color: const Color(0xFFF8FAFC),
                          child: Padding(
                            padding: const EdgeInsets.all(16),
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                const Row(
                                  children: [
                                    Icon(Icons.verified, size: 16, color: AppTheme.successGreen),
                                    SizedBox(width: 8),
                                    Text(
                                      'Geo-Verification Evidence',
                                      style: TextStyle(
                                        fontSize: 13,
                                        fontWeight: FontWeight.w700,
                                        color: AppTheme.primaryBlue,
                                      ),
                                    ),
                                  ],
                                ),
                                const SizedBox(height: 10),
                                Text(
                                  'Physical Distance: ${double.tryParse(_reportData?['calculated_distance']?.toString() ?? '0')?.toStringAsFixed(1) ?? '0.0'} metres (Permitted: ${_reportData?['allowed_radius'] ?? 10}m)',
                                  style: const TextStyle(fontSize: 12, color: AppTheme.textDark),
                                ),
                                const SizedBox(height: 4),
                                Text(
                                  'GPS Accuracy: ±${double.tryParse(_reportData?['gps_accuracy']?.toString() ?? '0')?.toStringAsFixed(1) ?? '0.0'}m',
                                  style: const TextStyle(fontSize: 12, color: AppTheme.textMuted),
                                ),
                              ],
                            ),
                          ),
                        ),
                        const SizedBox(height: 20),

                        // Answers & Checklist Items
                        const Text(
                          'Inspected Checklist Answers',
                          style: TextStyle(fontSize: 15, fontWeight: FontWeight.w700, color: AppTheme.textDark),
                        ),
                        const SizedBox(height: 10),

                        ListView.separated(
                          shrinkWrap: true,
                          physics: const NeverScrollableScrollPhysics(),
                          itemCount: _answers.length,
                          separatorBuilder: (_, __) => const SizedBox(height: 10),
                          itemBuilder: (context, index) {
                            final ans = _answers[index];
                            final bool isYes = ans['answer'] == 'YES';

                            return Card(
                              child: Padding(
                                padding: const EdgeInsets.all(14),
                                child: Column(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    Row(
                                      crossAxisAlignment: CrossAxisAlignment.start,
                                      children: [
                                        Expanded(
                                          child: Text(
                                            ans['question_text'] ?? '',
                                            style: const TextStyle(
                                              fontSize: 13,
                                              fontWeight: FontWeight.w600,
                                              color: AppTheme.textDark,
                                            ),
                                          ),
                                        ),
                                        const SizedBox(width: 8),
                                        Container(
                                          padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                                          decoration: BoxDecoration(
                                            color: isYes ? const Color(0xFFECFDF5) : const Color(0xFFFEF2F2),
                                            borderRadius: BorderRadius.circular(6),
                                          ),
                                          child: Text(
                                            ans['answer'] ?? '',
                                            style: TextStyle(
                                              fontSize: 11,
                                              fontWeight: FontWeight.w800,
                                              color: isYes ? AppTheme.successGreen : AppTheme.dangerRed,
                                            ),
                                          ),
                                        ),
                                      ],
                                    ),
                                    if (!isYes && (ans['issue_description']?.toString().isNotEmpty ?? false)) ...[
                                      const SizedBox(height: 8),
                                      Container(
                                        padding: const EdgeInsets.all(10),
                                        decoration: BoxDecoration(
                                          color: const Color(0xFFFFF5F5),
                                          borderRadius: BorderRadius.circular(8),
                                          border: Border.all(color: const Color(0xFFFECACA)),
                                        ),
                                        child: Row(
                                          crossAxisAlignment: CrossAxisAlignment.start,
                                          children: [
                                            const Icon(Icons.error_outline, size: 14, color: AppTheme.dangerRed),
                                            const SizedBox(width: 6),
                                            Expanded(
                                              child: Text(
                                                ans['issue_description'],
                                                style: const TextStyle(fontSize: 12, color: AppTheme.dangerRed),
                                              ),
                                            ),
                                          ],
                                        ),
                                      ),
                                    ],
                                  ],
                                ),
                              ),
                            );
                          },
                        ),
                      ],
                    ),
                  ),
                ),
    );
  }
}
