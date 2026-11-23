// ==============================================================================
// Maintenance Report Model
// ==============================================================================

class MaintenanceReportModel {
  final String reportId;
  final String systemId;
  final String equipmentName;
  final String department;
  final String roomNumber;
  final String maintenanceDate;
  final String maintenanceTime;
  final String overallStatus;
  final String? verificationStatus;
  final double? calculatedDistance;

  MaintenanceReportModel({
    required this.reportId,
    required this.systemId,
    required this.equipmentName,
    required this.department,
    required this.roomNumber,
    required this.maintenanceDate,
    required this.maintenanceTime,
    required this.overallStatus,
    this.verificationStatus,
    this.calculatedDistance,
  });

  factory MaintenanceReportModel.fromJson(Map<String, dynamic> json) {
    return MaintenanceReportModel(
      reportId: json['report_id'] ?? '',
      systemId: json['system_id'] ?? '',
      equipmentName: json['equipment_name'] ?? '',
      department: json['department'] ?? '',
      roomNumber: json['room_number'] ?? '',
      maintenanceDate: json['maintenance_date'] ?? '',
      maintenanceTime: json['maintenance_time'] ?? '',
      overallStatus: json['overall_status'] ?? '',
      verificationStatus: json['verification_status'],
      calculatedDistance: double.tryParse(json['calculated_distance']?.toString() ?? ''),
    );
  }
}
