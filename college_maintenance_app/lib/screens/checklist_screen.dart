import 'package:flutter/material.dart';
import 'package:geolocator/geolocator.dart';
import '../models/checklist_question_model.dart';
import '../services/api_service.dart';
import '../utils/app_theme.dart';
import 'report_summary_screen.dart';

// ==============================================================================
// Screen 4.5: Equipment Maintenance Checklist
// ==============================================================================

class ChecklistScreen extends StatefulWidget {
  final String systemId;
  final String equipmentName;
  final String equipmentType;
  final String department;
  final String roomNumber;
  final Position agentPosition;
  final String qrSignature;
  final int qrVersion;

  const ChecklistScreen({
    super.key,
    required this.systemId,
    required this.equipmentName,
    required this.equipmentType,
    required this.department,
    required this.roomNumber,
    required this.agentPosition,
    required this.qrSignature,
    required this.qrVersion,
  });

  @override
  State<ChecklistScreen> createState() => _ChecklistScreenState();
}

class _ChecklistScreenState extends State<ChecklistScreen> {
  List<ChecklistQuestionModel> _questions = [];
  bool _isLoading = true;
  bool _isSubmitting = false;
  String? _submissionError;
  final TextEditingController _notesController = TextEditingController();

  // Baseline 7 questions fallback if offline
  final List<String> _defaultDesktopQuestions = [
    'Is the computer switching ON?',
    'Is the monitor working properly?',
    'Is the keyboard working?',
    'Is the mouse working?',
    'Is the internet connection working?',
    'Is the operating system functioning properly?',
    'Are all required software applications working?',
  ];

  @override
  void initState() {
    super.initState();
    _fetchQuestions();
  }

  @override
  void dispose() {
    _notesController.dispose();
    super.dispose();
  }

  Future<void> _fetchQuestions() async {
    setState(() => _isLoading = true);

    List<ChecklistQuestionModel> fetched =
        await ApiService.getChecklistQuestions(widget.equipmentType);

    if (fetched.isEmpty) {
      // Use standard baseline desktop questions
      fetched = List.generate(
        _defaultDesktopQuestions.length,
        (i) => ChecklistQuestionModel(
          questionId: i + 1,
          questionText: _defaultDesktopQuestions[i],
          sortOrder: i + 1,
          isRequired: true,
        ),
      );
    }

    if (mounted) {
      setState(() {
        _questions = fetched;
        _isLoading = false;
      });
    }
  }

  String? _validateChecklist() {
    for (int i = 0; i < _questions.length; i++) {
      final q = _questions[i];
      if (q.isRequired && (q.selectedAnswer == null || q.selectedAnswer!.isEmpty)) {
        return 'Please answer question #${i + 1}: "${q.questionText}"';
      }
      if (q.selectedAnswer == 'NO' && q.issueDescription.trim().isEmpty) {
        return 'Please describe the issue for question #${i + 1}: "${q.questionText}"';
      }
    }
    return null;
  }

  Future<void> _handleSubmitReport() async {
    final String? validationError = _validateChecklist();
    if (validationError != null) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(validationError),
          backgroundColor: AppTheme.dangerRed,
          behavior: SnackBarBehavior.floating,
        ),
      );
      return;
    }

    // Confirmation dialog before final submission
    final bool? confirmed = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Submit Maintenance Report?'),
        content: Text(
          'You are about to log an official maintenance report for ${widget.systemId} (${widget.equipmentName}). '
          'Your physical coordinates and answers will be saved in college records.',
          style: const TextStyle(fontSize: 13),
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('Review Answers')),
          ElevatedButton(onPressed: () => Navigator.pop(ctx, true), child: const Text('Confirm & Submit')),
        ],
      ),
    );

    if (confirmed != true) return;

    setState(() {
      _isSubmitting = true;
      _submissionError = null;
    });

    final Map<String, dynamic> result = await ApiService.submitReport(
      systemId: widget.systemId,
      agentLatitude: widget.agentPosition.latitude,
      agentLongitude: widget.agentPosition.longitude,
      agentAltitude: widget.agentPosition.altitude,
      gpsAccuracy: widget.agentPosition.accuracy,
      qrSignature: widget.qrSignature,
      qrVersion: widget.qrVersion,
      questions: _questions,
      notes: _notesController.text.trim(),
    );

    if (!mounted) return;

    setState(() => _isSubmitting = false);

    if (result['success'] == true) {
      // Navigate to Report Summary Confirmation Screen
      Navigator.pushReplacement(
        context,
        MaterialPageRoute(
          builder: (_) => ReportSummaryScreen(reportData: result),
        ),
      );
    } else {
      setState(() {
        _submissionError = result['error'] ?? 'Failed to submit report. Please retry.';
      });
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(_submissionError!),
          backgroundColor: AppTheme.dangerRed,
        ),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text('${widget.systemId} Checklist'),
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator(color: AppTheme.primaryBlue))
          : SafeArea(
              child: SingleChildScrollView(
                padding: const EdgeInsets.all(18),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    // System Info Header Card
                    Card(
                      elevation: 1,
                      child: Padding(
                        padding: const EdgeInsets.all(16),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Row(
                              mainAxisAlignment: MainAxisAlignment.spaceBetween,
                              children: [
                                Text(
                                  widget.systemId,
                                  style: const TextStyle(
                                    fontSize: 18,
                                    fontWeight: FontWeight.w800,
                                    color: AppTheme.primaryBlue,
                                  ),
                                ),
                                Container(
                                  padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                                  decoration: BoxDecoration(
                                    color: const Color(0xFFECFDF5),
                                    borderRadius: BorderRadius.circular(20),
                                  ),
                                  child: const Row(
                                    children: [
                                      Icon(Icons.verified, size: 14, color: AppTheme.successGreen),
                                      SizedBox(width: 4),
                                      Text(
                                        'Geo-Verified',
                                        style: TextStyle(
                                          color: AppTheme.successGreen,
                                          fontSize: 11,
                                          fontWeight: FontWeight.w700,
                                        ),
                                      ),
                                    ],
                                  ),
                                ),
                              ],
                            ),
                            const SizedBox(height: 6),
                            Text(
                              widget.equipmentName,
                              style: const TextStyle(fontSize: 15, fontWeight: FontWeight.w600, color: AppTheme.textDark),
                            ),
                            const SizedBox(height: 4),
                            Text(
                              '${widget.department} • Room: ${widget.roomNumber}',
                              style: const TextStyle(fontSize: 12, color: AppTheme.textMuted),
                            ),
                          ],
                        ),
                      ),
                    ),
                    const SizedBox(height: 16),

                    if (_submissionError != null) ...[
                      Container(
                        padding: const EdgeInsets.all(12),
                        decoration: BoxDecoration(
                          color: const Color(0xFFFEF2F2),
                          borderRadius: BorderRadius.circular(10),
                          border: Border.all(color: const Color(0xFFFECACA)),
                        ),
                        child: Text(
                          _submissionError!,
                          style: const TextStyle(color: AppTheme.dangerRed, fontSize: 13),
                        ),
                      ),
                      const SizedBox(height: 16),
                    ],

                    // Checklist Section Title
                    const Text(
                      'Functional Inspection Items',
                      style: TextStyle(
                        fontSize: 16,
                        fontWeight: FontWeight.w700,
                        color: AppTheme.textDark,
                      ),
                    ),
                    const SizedBox(height: 8),
                    const Text(
                      'Answer all mandatory questions. Selecting NO requires describing the issue.',
                      style: TextStyle(fontSize: 12, color: AppTheme.textMuted),
                    ),
                    const SizedBox(height: 16),

                    // Questions List
                    ListView.separated(
                      shrinkWrap: true,
                      physics: const NeverScrollableScrollPhysics(),
                      itemCount: _questions.length,
                      separatorBuilder: (_, __) => const SizedBox(height: 14),
                      itemBuilder: (context, index) {
                        final question = _questions[index];
                        final bool isNo = question.selectedAnswer == 'NO';

                        return Card(
                          shape: RoundedRectangleBorder(
                            borderRadius: BorderRadius.circular(14),
                            side: BorderSide(
                              color: isNo ? const Color(0xFFFCA5A5) : const Color(0xFFE2E8F0),
                              width: isNo ? 1.5 : 1,
                            ),
                          ),
                          child: Padding(
                            padding: const EdgeInsets.all(16),
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Row(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    CircleAvatar(
                                      radius: 12,
                                      backgroundColor: AppTheme.primaryBlue.withValues(alpha: 0.1),
                                      child: Text(
                                        '${index + 1}',
                                        style: const TextStyle(
                                          fontSize: 12,
                                          fontWeight: FontWeight.w700,
                                          color: AppTheme.primaryBlue,
                                        ),
                                      ),
                                    ),
                                    const SizedBox(width: 10),
                                    Expanded(
                                      child: Text(
                                        question.questionText,
                                        style: const TextStyle(
                                          fontSize: 14,
                                          fontWeight: FontWeight.w600,
                                          color: AppTheme.textDark,
                                          height: 1.3,
                                        ),
                                      ),
                                    ),
                                  ],
                                ),
                                const SizedBox(height: 14),

                                // YES / NO Radio Selectors
                                Row(
                                  children: [
                                    Expanded(
                                      child: OutlinedButton(
                                        style: OutlinedButton.styleFrom(
                                          backgroundColor: question.selectedAnswer == 'YES'
                                              ? const Color(0xFFECFDF5)
                                              : Colors.white,
                                          side: BorderSide(
                                            color: question.selectedAnswer == 'YES'
                                                ? AppTheme.successGreen
                                                : const Color(0xFFE2E8F0),
                                            width: question.selectedAnswer == 'YES' ? 2 : 1,
                                          ),
                                          padding: const EdgeInsets.symmetric(vertical: 12),
                                          shape: RoundedRectangleBorder(
                                            borderRadius: BorderRadius.circular(10),
                                          ),
                                        ),
                                        onPressed: () {
                                          setState(() {
                                            question.selectedAnswer = 'YES';
                                          });
                                        },
                                        child: Row(
                                          mainAxisAlignment: MainAxisAlignment.center,
                                          children: [
                                            Icon(
                                              Icons.check_circle,
                                              size: 18,
                                              color: question.selectedAnswer == 'YES'
                                                  ? AppTheme.successGreen
                                                  : Colors.grey,
                                            ),
                                            const SizedBox(width: 8),
                                            Text(
                                              'YES',
                                              style: TextStyle(
                                                fontWeight: FontWeight.w700,
                                                color: question.selectedAnswer == 'YES'
                                                    ? AppTheme.successGreen
                                                    : AppTheme.textDark,
                                              ),
                                            ),
                                          ],
                                        ),
                                      ),
                                    ),
                                    const SizedBox(width: 12),
                                    Expanded(
                                      child: OutlinedButton(
                                        style: OutlinedButton.styleFrom(
                                          backgroundColor: question.selectedAnswer == 'NO'
                                              ? const Color(0xFFFEF2F2)
                                              : Colors.white,
                                          side: BorderSide(
                                            color: question.selectedAnswer == 'NO'
                                                ? AppTheme.dangerRed
                                                : const Color(0xFFE2E8F0),
                                            width: question.selectedAnswer == 'NO' ? 2 : 1,
                                          ),
                                          padding: const EdgeInsets.symmetric(vertical: 12),
                                          shape: RoundedRectangleBorder(
                                            borderRadius: BorderRadius.circular(10),
                                          ),
                                        ),
                                        onPressed: () {
                                          setState(() {
                                            question.selectedAnswer = 'NO';
                                          });
                                        },
                                        child: Row(
                                          mainAxisAlignment: MainAxisAlignment.center,
                                          children: [
                                            Icon(
                                              Icons.cancel,
                                              size: 18,
                                              color: question.selectedAnswer == 'NO'
                                                  ? AppTheme.dangerRed
                                                  : Colors.grey,
                                            ),
                                            const SizedBox(width: 8),
                                            Text(
                                              'NO',
                                              style: TextStyle(
                                                fontWeight: FontWeight.w700,
                                                color: question.selectedAnswer == 'NO'
                                                    ? AppTheme.dangerRed
                                                    : AppTheme.textDark,
                                              ),
                                            ),
                                          ],
                                        ),
                                      ),
                                    ),
                                  ],
                                ),

                                // Conditional Issue Description Field (Mandatory if NO is selected)
                                if (isNo) ...[
                                  const SizedBox(height: 12),
                                  TextFormField(
                                    initialValue: question.issueDescription,
                                    decoration: InputDecoration(
                                      labelText: 'Describe the identified issue *',
                                      labelStyle: const TextStyle(color: AppTheme.dangerRed, fontSize: 13),
                                      hintText: 'e.g. Keyboard keys are not responding',
                                      filled: true,
                                      fillColor: const Color(0xFFFFF5F5),
                                      contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
                                      border: OutlineInputBorder(
                                        borderRadius: BorderRadius.circular(8),
                                        borderSide: const BorderSide(color: Color(0xFFFCA5A5)),
                                      ),
                                    ),
                                    onChanged: (val) {
                                      question.issueDescription = val;
                                    },
                                  ),
                                ],
                              ],
                            ),
                          ),
                        );
                      },
                    ),
                    const SizedBox(height: 20),

                    // Optional Notes
                    Card(
                      child: Padding(
                        padding: const EdgeInsets.all(16),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            const Text(
                              'Additional Technician Remarks (Optional)',
                              style: TextStyle(fontSize: 13, fontWeight: FontWeight.w600, color: AppTheme.textDark),
                            ),
                            const SizedBox(height: 8),
                            TextField(
                              controller: _notesController,
                              maxLines: 3,
                              decoration: const InputDecoration(
                                hintText: 'Enter general comments or recommendations...',
                              ),
                            ),
                          ],
                        ),
                      ),
                    ),
                    const SizedBox(height: 28),

                    // Submit Checklist Button
                    ElevatedButton(
                      style: ElevatedButton.styleFrom(
                        backgroundColor: AppTheme.primaryBlue,
                        padding: const EdgeInsets.symmetric(vertical: 16),
                      ),
                      onPressed: _isSubmitting ? null : _handleSubmitReport,
                      child: _isSubmitting
                          ? const SizedBox(
                              height: 22,
                              width: 22,
                              child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white),
                            )
                          : const Text(
                              'Submit Maintenance Report',
                              style: TextStyle(fontSize: 16, fontWeight: FontWeight.w700),
                            ),
                    ),
                    const SizedBox(height: 20),
                  ],
                ),
              ),
            ),
    );
  }
}
