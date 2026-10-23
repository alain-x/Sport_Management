import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';

/// Picks a profile photo. Unlike attendance selfies (camera only), a profile
/// picture may come from either the camera or the device gallery.
class PhotoPicker {
  PhotoPicker({ImagePicker? picker}) : _picker = picker ?? ImagePicker();

  final ImagePicker _picker;

  /// Shows a source picker, then opens the camera/gallery.
  ///
  /// Returns the local file path on success, or null when the user cancelled
  /// or the platform denied access.
  Future<String?> pick(BuildContext context) async {
    final source = await showModalBottomSheet<ImageSource>(
      context: context,
      showDragHandle: true,
      builder: (ctx) => SafeArea(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            ListTile(
              leading: const Icon(Icons.photo_camera_outlined),
              title: const Text('Take a photo'),
              onTap: () => Navigator.of(ctx).pop(ImageSource.camera),
            ),
            ListTile(
              leading: const Icon(Icons.photo_library_outlined),
              title: const Text('Choose from gallery'),
              onTap: () => Navigator.of(ctx).pop(ImageSource.gallery),
            ),
            const SizedBox(height: 8),
          ],
        ),
      ),
    );
    if (source == null) return null;

    try {
      final photo = await _picker.pickImage(
        source: source,
        imageQuality: 85,
        maxWidth: 1600,
        maxHeight: 1600,
        preferredCameraDevice: CameraDevice.front,
      );
      return photo?.path;
    } catch (error) {
      if (!context.mounted) return null;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            source == ImageSource.camera
                ? 'Could not open the camera: $error'
                : 'Could not open the gallery: $error',
          ),
        ),
      );
      return null;
    }
  }
}
