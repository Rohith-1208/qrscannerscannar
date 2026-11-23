// ==============================================================================
// Agent Model
// ==============================================================================

class AgentModel {
  final String agentId;
  final String name;
  final String email;
  final String status;

  AgentModel({
    required this.agentId,
    required this.name,
    required this.email,
    required this.status,
  });

  factory AgentModel.fromJson(Map<String, dynamic> json) {
    return AgentModel(
      agentId: json['agent_id'] ?? '',
      name: json['name'] ?? '',
      email: json['email'] ?? '',
      status: json['status'] ?? 'active',
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'agent_id': agentId,
      'name': name,
      'email': email,
      'status': status,
    };
  }
}
