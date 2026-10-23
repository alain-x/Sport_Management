import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';

/// Captures a selfie with the device camera only (no gallery picker offered).
/// The API requires a camera photo for training check-in and stamps the time.
class SelfieCapture {
  SelfieCapture({ImagePicker? picker}) : _picker = picker ?? ImagePicker();

  final ImagePicker _picker;

  /// Opens the native camera. Returns the local file path on success, or null
  /// if the user cancelled/denied.
  Future<String?> takeSelfie(BuildContext context) async {
    try {
      final photo = await _picker.pickImage(
        source: ImageSource.camera,
        preferredCameraDevice: CameraDevice.front,
        imageQuality: 85,
        maxWidth: 1600,
      );

      return photo?.path;
    } catch (error) {
      if (!context.mounted) return null;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            'Could not open the camera: $error',
          ),
        ),
      );
      return null;
    }
  }
}
