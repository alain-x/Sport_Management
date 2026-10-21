import 'package:shared_preferences/shared_preferences.dart';

/// Persists the Sanctum token and a small snapshot of the signed-in user
/// across app launches.
class TokenStorage {
  static const String _tokenKey = 'auth_token';
  static const String _userKey = 'auth_user';

  Future<SharedPreferences> get _prefs => SharedPreferences.getInstance();

  Future<String?> readToken() async {
    final prefs = await _prefs;
    final token = prefs.getString(_tokenKey);
    if (token == null || token.isEmpty) return null;
    return token;
  }

  Future<void> writeToken(String token) async {
    final prefs = await _prefs;
    await prefs.setString(_tokenKey, token);
  }

  /// Raw JSON of the last signed-in user (used for instant startup, then
  /// refreshed from `GET /me`).
  Future<String?> readUserJson() async {
    final prefs = await _prefs;
    return prefs.getString(_userKey);
  }

  Future<void> writeUserJson(String json) async {
    final prefs = await _prefs;
    await prefs.setString(_userKey, json);
  }

  Future<void> clear() async {
    final prefs = await _prefs;
    await prefs.remove(_tokenKey);
    await prefs.remove(_userKey);
  }
}
