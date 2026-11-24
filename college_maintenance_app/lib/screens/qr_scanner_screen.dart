import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:mobile_scanner/mobile_scanner.dart';
import '../models/qr_payload_model.dart';
import '../services/api_service.dart';
import '../utils/app_theme.dart';
import 'location_verification_screen.dart';

// ==============================================================================
// Screen 4.3: Equipment QR Code Scanner
// ==============================================================================

class QrScannerScreen extends StatefulWidget {
  const QrScannerScreen({super.key});

  @override
  State<QrScannerScreen> createState() => _QrScannerScreenState();
}

class _QrScannerScreenState extends State<QrScannerScreen> {
  final MobileScannerController _scannerController = MobileScannerController(
    detectionSpeed: DetectionSpeed.noDuplicates,
    facing: CameraFacing.back,
    torchEnabled: false,
  );

  bool _isProcessing = false;
  String? _lastScannedPayload;
  String? _errorMessage;

  @override
  void dispose() {
    _scannerController.dispose();
    super.dispose();
  }

  Future<void> _handleBarcodeCapture(BarcodeCapture capture) async {
    if (_isProcessing) return;

    final List<Barcode> barcodes = capture.barcodes;
    if (barcodes.isEmpty) return;

    final String? rawValue = barcodes.first.rawValue;
    if (rawValue == null || rawValue.isEmpty) return;

    // Prevent repeated processing of the exact same QR code in this session
    if (rawValue == _lastScannedPayload) return;

    _lastScannedPayload = rawValue;
    await _processQrString(rawValue);
  }

  Future<void> _processQrString(String rawString) async {
    setState(() {
      _isProcessing = true;
      _errorMessage = null;
    });

    // 1. Validate QR Data Structure
    final QrPayloadModel? qrPayload = QrPayloadModel.fromRawString(rawString);

    if (qrPayload == null) {
      setState(() {
        _isProcessing = false;
        _errorMessage = 'Invalid QR format. This is not a college equipment QR code.';
      });
      return;
    }

    // 2. Validate QR signature & active equipment status with Backend API
    final Map<String, dynamic> verifyResult = await ApiService.verifyQr(qrPayload);

    if (!mounted) return;

    if (verifyResult['success'] == true && verifyResult['valid'] == true) {
      final Map<String, dynamic> systemData = verifyResult['system'] ?? {};

      // Pause scanner while transitioning
      await _scannerController.stop();

      if (!mounted) return;

      // Navigate to Screen 4.4: GPS Location Verification
      Navigator.pushReplacement(
        context,
        MaterialPageRoute(
          builder: (_) => LocationVerificationScreen(
            qrPayload: qrPayload,
            systemData: systemData,
          ),
        ),
      );
    } else {
      setState(() {
        _isProcessing = false;
        _errorMessage = verifyResult['error'] ?? 'QR signature verification rejected by server.';
      });
    }
  }

  // Developer / Emulator test helper: input JSON payload directly
  void _showManualInputModal() {
    final TextEditingController jsonCtrl = TextEditingController(
      text: jsonEncode({
        "system_id": "SYS-001",
        "latitude": 13.075100,
        "longitude": 80.117000,
        "radius": 10,
        "version": 1,
        "signature": "deb429cbe506a4070c14d34ee5f1cd52af1edecf673c51872f7c2714269a4145"
      }),
    );

    showDialog(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Test QR Code Payload'),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Text(
              'Paste or edit a QR payload JSON for testing on emulator:',
              style: TextStyle(fontSize: 12, color: AppTheme.textMuted),
            ),
            const SizedBox(height: 10),
            TextField(
              controller: jsonCtrl,
              maxLines: 5,
              style: const TextStyle(fontFamily: 'monospace', fontSize: 12),
              decoration: const InputDecoration(border: OutlineInputBorder()),
            ),
          ],
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx), child: const Text('Cancel')),
          ElevatedButton(
            onPressed: () {
              Navigator.pop(ctx);
              _processQrString(jsonCtrl.text);
            },
            child: const Text('Process'),
          ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.black,
      appBar: AppBar(
        title: const Text('Scan Equipment QR'),
        backgroundColor: Colors.black,
        actions: [
          IconButton(
            icon: const Icon(Icons.flash_on_rounded),
            tooltip: 'Toggle Flash',
            onPressed: () => _scannerController.toggleTorch(),
          ),
          IconButton(
            icon: const Icon(Icons.code_rounded),
            tooltip: 'Test Payload',
            onPressed: _showManualInputModal,
          ),
        ],
      ),
      body: Stack(
        children: [
          // Camera Scanner View
          MobileScanner(
            controller: _scannerController,
            onDetect: _handleBarcodeCapture,
            errorBuilder: (context, error, child) {
              return Center(
                child: Padding(
                  padding: const EdgeInsets.all(24.0),
                  child: Column(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      const Icon(Icons.videocam_off_rounded, color: Colors.white70, size: 48),
                      const SizedBox(height: 16),
                      Text(
                        'Camera Error: ${error.errorDetails?.message ?? error.errorCode.name}',
                        textAlign: TextAlign.center,
                        style: const TextStyle(color: Colors.white70),
                      ),
                      const SizedBox(height: 16),
                      ElevatedButton(
                        onPressed: _showManualInputModal,
                        child: const Text('Use Test Payload Input'),
                      ),
                    ],
                  ),
                ),
              );
            },
          ),

          // Reticle Scanner Overlay
          Center(
            child: Container(
              width: 260,
              height: 260,
              decoration: BoxDecoration(
                border: Border.all(color: AppTheme.accentBlue, width: 3),
                borderRadius: BorderRadius.circular(20),
                boxShadow: [
                  BoxShadow(
                    color: AppTheme.accentBlue.withValues(alpha: 0.2),
                    blurRadius: 20,
                    spreadRadius: 2,
                  ),
                ],
              ),
              child: Stack(
                children: [
                  Align(
                    alignment: Alignment.center,
                    child: Container(
                      width: 240,
                      height: 2,
                      color: AppTheme.accentBlue.withValues(alpha: 0.7),
                    ),
                  ),
                ],
              ),
            ),
          ),

          // Instructions & Status Banner
          Positioned(
            bottom: 40,
            left: 24,
            right: 24,
            child: Column(
              children: [
                if (_errorMessage != null)
                  Container(
                    margin: const EdgeInsets.only(bottom: 16),
                    padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
                    decoration: BoxDecoration(
                      color: const Color(0xFFFEF2F2),
                      borderRadius: BorderRadius.circular(12),
                      border: Border.all(color: const Color(0xFFFECACA)),
                    ),
                    child: Row(
                      children: [
                        const Icon(Icons.warning_amber_rounded, color: AppTheme.dangerRed, size: 22),
                        const SizedBox(width: 10),
                        Expanded(
                          child: Text(
                            _errorMessage!,
                            style: const TextStyle(color: AppTheme.dangerRed, fontSize: 13, fontWeight: FontWeight.w600),
                          ),
                        ),
                      ],
                    ),
                  ),

                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 14),
                  decoration: BoxDecoration(
                    color: Colors.black.withValues(alpha: 0.75),
                    borderRadius: BorderRadius.circular(30),
                  ),
                  child: Row(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      if (_isProcessing)
                        const SizedBox(
                          width: 16,
                          height: 16,
                          child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white),
                        )
                      else
                        const Icon(Icons.qr_code_scanner, color: Colors.white70, size: 18),
                      const SizedBox(width: 10),
                      Text(
                        _isProcessing ? 'Authenticating QR code...' : 'Point camera at equipment QR code',
                        style: const TextStyle(color: Colors.white, fontSize: 13, fontWeight: FontWeight.w500),
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
