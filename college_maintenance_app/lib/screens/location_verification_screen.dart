import 'package:flutter/material.dart';
import 'package:geolocator/geolocator.dart';
import '../models/qr_payload_model.dart';
import '../services/api_service.dart';
import '../services/location_service.dart';
import '../utils/app_theme.dart';
import '../utils/haversine.dart';
import 'checklist_screen.dart';

// ==============================================================================
// Screen 4.4: GPS Location Verification (CRITICAL MODULE)
// ==============================================================================

enum LocationState {
  acquiringGps,
  verifying,
  verifiedSuccess,
  outsideLocation,     // distance > 10m
  accuracyInsufficient, // poor satellite lock
  error,
}

class LocationVerificationScreen extends StatefulWidget {
  final QrPayloadModel qrPayload;
  final Map<String, dynamic> systemData;

  const LocationVerificationScreen({
    super.key,
    required this.qrPayload,
    required this.systemData,
  });

  @override
  State<LocationVerificationScreen> createState() => _LocationVerificationScreenState();
}

class _LocationVerificationScreenState extends State<LocationVerificationScreen> {
  LocationState _state = LocationState.acquiringGps;
  String? _errorMessage;

  Position? _currentPosition;

  @override
  void initState() {
    super.initState();
    _startLocationVerification();
  }

  Future<void> _startLocationVerification() async {
    setState(() {
      _state = LocationState.acquiringGps;
      _errorMessage = null;
    });

    // 1. Obtain GPS Position and check accuracy
    final LocationResult locResult = await LocationService.getCurrentLocation();

    if (!locResult.isSuccess) {
      if (locResult.isAccuracyInsufficient) {
        // Save the position so user can proceed in indoor mode if needed
        _currentPosition = locResult.position;
        setState(() {
          _state = LocationState.accuracyInsufficient;
          _errorMessage = locResult.errorMessage;
        });
      } else {
        setState(() {
          _state = LocationState.error;
          _errorMessage = locResult.errorMessage ?? 'Unable to acquire GPS fix.';
        });
      }
      return;
    }

    final Position position = locResult.position!;
    _currentPosition = position;

    setState(() {
      _state = LocationState.verifying;
    });

    // 2. Calculate Distance using Haversine formula
    final double distance = HaversineCalculator.calculateDistance(
      lat1: position.latitude,
      lon1: position.longitude,
      lat2: widget.qrPayload.latitude,
      lon2: widget.qrPayload.longitude,
    );

    // 3. Compare with permitted radius of 10 metres
    final double allowedRadius = widget.qrPayload.radius;

    if (distance <= allowedRadius) {
      // 4. Double check with Backend API for server-side verification evidence
      final Map<String, dynamic> backendVerify = await ApiService.verifyLocation(
        systemId: widget.qrPayload.systemId,
        agentLatitude: position.latitude,
        agentLongitude: position.longitude,
        gpsAccuracy: position.accuracy,
        qrSignature: widget.qrPayload.signature,
        qrVersion: widget.qrPayload.version,
      );

      if (!mounted) return;

      if (backendVerify['success'] == true && backendVerify['verified'] == true) {
        setState(() {
          _state = LocationState.verifiedSuccess;
        });
      } else if (backendVerify['accuracy_insufficient'] == true) {
        setState(() {
          _state = LocationState.accuracyInsufficient;
          _errorMessage = backendVerify['error'];
        });
      } else {
        // Server rejected location proximity
        setState(() {
          _state = LocationState.outsideLocation;
        });
      }
    } else {
      // Distance > 10 metres
      setState(() {
        _state = LocationState.outsideLocation;
      });
    }
  }

  void _proceedToChecklist() {
    if (_state != LocationState.verifiedSuccess || _currentPosition == null) return;

    Navigator.pushReplacement(
      context,
      MaterialPageRoute(
        builder: (_) => ChecklistScreen(
          systemId: widget.qrPayload.systemId,
          equipmentName: widget.systemData['equipment_name'] ?? 'Equipment',
          equipmentType: widget.systemData['equipment_type'] ?? 'Desktop Computer',
          department: widget.systemData['department'] ?? '',
          roomNumber: widget.systemData['room_number'] ?? '',
          agentPosition: _currentPosition!,
          qrSignature: widget.qrPayload.signature,
          qrVersion: widget.qrPayload.version,
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Location Verification'),
      ),
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(24.0),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              // ================================================================
              // CASE 1: OUTSIDE PERMITTED LOCATION (RULE 6 ENFORCEMENT)
              // IMPORTANT UI REQUIREMENT:
              // Display ONLY: "Not in correct location"
              // Do NOT display coordinates, distance, or technical info.
              // ================================================================
              if (_state == LocationState.outsideLocation) ...[
                const Spacer(),
                Center(
                  child: Container(
                    width: 80,
                    height: 80,
                    decoration: BoxDecoration(
                      color: const Color(0xFFFEE2E2),
                      shape: BoxShape.circle,
                      border: Border.all(color: const Color(0xFFFCA5A5), width: 2),
                    ),
                    child: const Icon(
                      Icons.location_off_rounded,
                      color: AppTheme.dangerRed,
                      size: 44,
                    ),
                  ),
                ),
                const SizedBox(height: 28),
                const Text(
                  'Not in correct location',
                  textAlign: TextAlign.center,
                  style: TextStyle(
                    fontSize: 22,
                    fontWeight: FontWeight.w800,
                    color: AppTheme.dangerRed,
                    letterSpacing: -0.2,
                  ),
                ),
                const Spacer(),
                ElevatedButton(
                  style: ElevatedButton.styleFrom(backgroundColor: AppTheme.primaryBlue),
                  onPressed: _startLocationVerification,
                  child: const Text('Retry Location Check'),
                ),
                const SizedBox(height: 12),
                TextButton(
                  onPressed: () => Navigator.pop(context),
                  child: const Text('Back to Dashboard'),
                ),
              ]

              // ================================================================
              // CASE 2: VERIFIED SUCCESS (RULE 8: ALLOW ACCESS TO CHECKLIST)
              // ================================================================
              else if (_state == LocationState.verifiedSuccess) ...[
                const Spacer(),
                Center(
                  child: Container(
                    width: 88,
                    height: 88,
                    decoration: BoxDecoration(
                      color: const Color(0xFFECFDF5),
                      shape: BoxShape.circle,
                      border: Border.all(color: const Color(0xFFA7F3D0), width: 2),
                    ),
                    child: const Icon(
                      Icons.check_circle_rounded,
                      color: AppTheme.successGreen,
                      size: 52,
                    ),
                  ),
                ),
                const SizedBox(height: 24),
                const Text(
                  'Location Verified',
                  textAlign: TextAlign.center,
                  style: TextStyle(
                    fontSize: 24,
                    fontWeight: FontWeight.w800,
                    color: AppTheme.successGreen,
                  ),
                ),
                const SizedBox(height: 10),
                Text(
                  'Physically present at ${widget.qrPayload.systemId}\n'
                  '${widget.systemData['equipment_name'] ?? ''} (${widget.systemData['room_number'] ?? ''})',
                  textAlign: TextAlign.center,
                  style: const TextStyle(
                    fontSize: 14,
                    color: AppTheme.textMuted,
                    fontWeight: FontWeight.w500,
                  ),
                ),
                const Spacer(),
                ElevatedButton(
                  style: ElevatedButton.styleFrom(
                    backgroundColor: AppTheme.successGreen,
                    padding: const EdgeInsets.symmetric(vertical: 16),
                  ),
                  onPressed: _proceedToChecklist,
                  child: const Text(
                    'Open Maintenance Checklist →',
                    style: TextStyle(fontSize: 16, fontWeight: FontWeight.w700),
                  ),
                ),
              ]

              // ================================================================
              // CASE 3: INSUFFICIENT GPS ACCURACY (RULE 7)
              // ================================================================
              else if (_state == LocationState.accuracyInsufficient) ...[
                const Spacer(),
                Center(
                  child: Container(
                    width: 80,
                    height: 80,
                    decoration: BoxDecoration(
                      color: const Color(0xFFFEF3C7),
                      shape: BoxShape.circle,
                      border: Border.all(color: const Color(0xFFFDE68A), width: 2),
                    ),
                    child: const Icon(
                      Icons.satellite_alt_rounded,
                      color: AppTheme.warningOrange,
                      size: 42,
                    ),
                  ),
                ),
                const SizedBox(height: 24),
                const Text(
                  'Waiting for Reliable GPS Fix',
                  textAlign: TextAlign.center,
                  style: TextStyle(
                    fontSize: 20,
                    fontWeight: FontWeight.w700,
                    color: AppTheme.warningOrange,
                  ),
                ),
                const SizedBox(height: 12),
                Text(
                  _errorMessage ?? 'GPS signal is currently weak. Please move slightly near a window or open space and tap retry.',
                  textAlign: TextAlign.center,
                  style: const TextStyle(fontSize: 14, color: AppTheme.textMuted),
                ),
                const Spacer(),
                ElevatedButton(
                  onPressed: _startLocationVerification,
                  child: const Text('Retry Location Reading'),
                ),
                const SizedBox(height: 10),
                OutlinedButton.icon(
                  icon: const Icon(Icons.home_work_outlined),
                  label: const Text('Proceed Anyway (Indoor Mode)'),
                  style: OutlinedButton.styleFrom(
                    padding: const EdgeInsets.symmetric(vertical: 14),
                  ),
                  onPressed: () {
                    if (_currentPosition != null) {
                      setState(() {
                        _state = LocationState.verifiedSuccess;
                      });
                      _proceedToChecklist();
                    } else {
                      _startLocationVerification();
                    }
                  },
                ),
                const SizedBox(height: 12),
                TextButton(
                  onPressed: () => Navigator.pop(context),
                  child: const Text('Cancel'),
                ),
              ]

              // ================================================================
              // CASE 4: ACQUIRING GPS OR VERIFYING (LOADING)
              // ================================================================
              else if (_state == LocationState.acquiringGps || _state == LocationState.verifying) ...[
                const Spacer(),
                const Center(
                  child: SizedBox(
                    width: 56,
                    height: 56,
                    child: CircularProgressIndicator(
                      strokeWidth: 3.5,
                      color: AppTheme.primaryBlue,
                    ),
                  ),
                ),
                const SizedBox(height: 28),
                Text(
                  _state == LocationState.acquiringGps
                      ? 'Acquiring High-Accuracy GPS...'
                      : 'Verifying Physical Distance (Haversine)...',
                  textAlign: TextAlign.center,
                  style: const TextStyle(
                    fontSize: 18,
                    fontWeight: FontWeight.w700,
                    color: AppTheme.textDark,
                  ),
                ),
                const SizedBox(height: 8),
                const Text(
                  'Confirming physical proximity within 10 metres',
                  textAlign: TextAlign.center,
                  style: TextStyle(fontSize: 13, color: AppTheme.textMuted),
                ),
                const Spacer(),
              ]

              // ================================================================
              // CASE 5: GENERIC ERROR (PERMISSION / SENSOR DISABLED)
              // ================================================================
              else ...[
                const Spacer(),
                const Icon(Icons.error_outline_rounded, color: AppTheme.dangerRed, size: 54),
                const SizedBox(height: 18),
                const Text(
                  'Location Access Error',
                  textAlign: TextAlign.center,
                  style: TextStyle(fontSize: 20, fontWeight: FontWeight.w700, color: AppTheme.dangerRed),
                ),
            