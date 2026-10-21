import 'dart:io' show Platform;

import 'package:flutter/foundation.dart';

/// Where the mobile app finds the Laravel JSON API.
///
/// The correct base URL depends on how you run the app:
///
///  * Android emulator  -> `http://10.0.2.2:8000` (the host machine's loopback)
///  * Physical device   -> `http://<your-computer-LAN-IP>:8000` (same Wi-Fi)
///  * Desktop / web     -> `http://127.0.0.1:8000`
///  * Apache (XAMPP)    -> `http://<host>/sport_management/public`
///
/// Override at build time without editing code:
///
///   flutter run --dart-define=API_BASE_URL=http://192.168.1.20:8000
class AppConfig {
  AppConfig._();

  /// Set with `--dart-define=API_BASE_URL=...`.
  static const String _override = String.fromEnvironment('API_BASE_URL');

  /// Host root (no `/api` suffix). The [apiBaseUrl] getter appends it.
  static String get hostBaseUrl {
    if (_override.isNotEmpty) return _stripTrailingSlash(_override);

    // Web builds always talk to the machine serving the app.
    if (kIsWeb) return 'http://127.0.0.1:8000';

    if (Platform.isAndroid) {
      // 10.0.2.2 is the Android emulator's alias for the host machine.
      return 'http://10.0.2.2:8000';
    }

    return 'http://127.0.0.1:8000';
  }

  /// Full API root, e.g. `http://10.0.2.2:8000/api`.
  static String get apiBaseUrl {
    final base = hostBaseUrl;
    // An explicit Apache URL may already point at /public.
    if (base.endsWith('/api')) return base;
    return '$base/api';
  }

  /// How long a single request may take before it is treated as a timeout.
  static const Duration requestTimeout = Duration(seconds: 20);

  /// How long the login error popup stays on screen.
  static const Duration loginErrorDuration = Duration(seconds: 3);

  static String _stripTrailingSlash(String value) {
    var v = value.trim();
    while (v.endsWith('/')) {
      v = v.substring(0, v.length - 1);
    }
    return v;
  }
}
