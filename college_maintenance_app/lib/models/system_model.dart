// ==============================================================================
// College Equipment (System) Model
// ==============================================================================

class SystemModel {
  final String systemId;
  final String equipmentName;
  final String equipmentType;
  final String department;
  final String building;
  final String roomNumber;
  final double latitude;
  final double longitude;
  final double allowedRadius;
  final String status;

  SystemModel({
    required this.systemId,
    required this.equipmentName,
    required this.equipmentType,
    required this.department,
    required this.building,
    required this.roomNumber,
    required this.latitude,
    required this.longitude,
    required this.allowedRadius,
    required this.status,
  });

  factory SystemModel.fromJson(Map<String, dynamic> json) {
    return SystemModel(
      systemId: json['system_id'] ?? '',
      equipmentName: json['equipment_name'] ?? '',
      equipmentType: json['equipment_type'] ?? 'Desktop Computer',
      department: json['department'] ?? '',
      building: json['building'] ?? '',
      roomNumber: json['room_number'] ?? '',
      latitude: double.tryParse(json['latitude']?.toString() ?? '0') ?? 0.0,
      longitude: double.tryParse(json['longitude']?.toString() ?? '0') ?? 0.0,
      allowedRadius: double.tryParse(json['allowed_radius']?.toString() ?? '10') ?? 10.0,
      status: json['status'] ?? 'working',
    );
  }
}
