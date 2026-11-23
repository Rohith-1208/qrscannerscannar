// ==============================================================================
// Checklist Question & Answer Model
// ==============================================================================

class ChecklistQuestionModel {
  final int questionId;
  final String questionText;
  final int sortOrder;
  final bool isRequired;

  // Selected state
  String? selectedAnswer; // 'YES' or 'NO'
  String issueDescription;

  ChecklistQuestionModel({
    required this.questionId,
    required this.questionText,
    required this.sortOrder,
    required this.isRequired,
    this.selectedAnswer,
    this.issueDescription = '',
  });

  factory ChecklistQuestionModel.fromJson(Map<String, dynamic> json) {
    return ChecklistQuestionModel(
      questionId: int.tryParse(json['question_id']?.toString() ?? '0') ?? 0,
      questionText: json['question_text'] ?? '',
      sortOrder: int.tryParse(json['sort_order']?.toString() ?? '0') ?? 0,
      isRequired: (json['is_required']?.toString() ?? '1') == '1',
      selectedAnswer: null,
      issueDescription: '',
    );
  }

  Map<String, dynamic> toSubmissionJson() {
    return {
      'question_id': questionId,
      'answer': selectedAnswer ?? 'YES',
      'issue_description': selectedAnswer == 'NO' ? issueDescription.trim() : null,
    };
  }
}
