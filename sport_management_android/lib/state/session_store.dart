import 'dart:convert';

import 'package:flutter/foundation.dart';

import '../models/app_user.dart';
import '../services/api_client.dart';
import '../services/api_exception.dart';
import '../services/token_storage.dart';

enum SessionStatus { unknown, signedOut, signedIn }

/// Owns authentication state and exposes the API client to the widget tree.
///
/// On launch it restores the token from disk, then validates it against
/// `GET /me` so a revoked token lands the user back on the login screen.
class SessionStore extends ChangeNotifier {
  SessionStore({TokenStorage? storage, ApiClient? client})
      : _storage = storage ?? TokenStorage() {
    _client = client ?? ApiClient(tokenProvider: () => _token);
  }

  final TokenStorage _storage;
  late final ApiClient _client;

  String? _token;
  AppUser? _user;
  SessionStatus _status = SessionStatus.unknown;
  String? _lastError;

  ApiClient get client => _client;
  AppUser? get user => _user;
  SessionStatus get status => _status;
  String? get lastError => _lastError;
  bool get isSignedIn => _status == SessionStatus.signedIn;

  /// Restore a previous session, if the stored token is still valid.
  Future<void> bootstrap() async {
    final token = await _storage.readToken();
    if (token == null) {
      _setSignedOut();
      return;
    }

    _token = token;

    // Show the cached user immediately while /me is in flight.
    final cached = await _storage.readUserJson();
    if (cached != null) {
      try {
        _user = AppUser.fromJson(jsonDecode(cached) as Map<String, dynamic>);
      } catch (_) {
        _user = null;
      }
    }

    try {
      final me = await _client.me();
      _user = AppUser.fromJson(me);
      await _storage.writeUserJson(jsonEncode(_user!.toJson()));
      _status = SessionStatus.signedIn;
      notifyListeners();
    } on ApiException catch (error) {
      if (error.isUnauthorized) {
        await signOut(remote: false);
      } else {
        // Network problem: trust the cached session so the app still opens.
        if (_user != null) {
          _status = SessionStatus.signedIn;
          notifyListeners();
        } else {
          _lastError = error.message;
          _setSignedOut();
        }
      }
    }
  }

  /// Authenticate and persist the returned token.
  ///
  /// Throws [ApiException] on failure so the login screen can react.
  Future<void> signIn({
    required String email,
    required String password,
    String deviceName = 'mobile',
  }) async {
    final payload = await _client.login(
      email: email,
      password: password,
      deviceName: deviceName,
    );

    _token = payload['token'] as String;
    await _storage.writeToken(_token!);

    _user = AppUser.fromJson(
      (payload['user'] as Map).map((k, v) => MapEntry('$k', v)),
    );
    await _storage.writeUserJson(jsonEncode(_user!.toJson()));

    _status = SessionStatus.signedIn;
    _lastError = null;
    notifyListeners();
  }

  /// Clear the session, optionally revoking the token server-side.
  Future<void> signOut({bool remote = true}) async {
    if (remote && _token != null) {
      await _client.logout();
    }
    _token = null;
    _user = null;
    await _storage.clear();
    _setSignedOut();
  }

  /// Update the signed-in user's name (and optional phone number).
  Future<void> updateProfile({required String name, String? phone}) async {
    final data = await _client.post(
      '/profile',
      body: <String, dynamic>{
        'name': name,
        'phone': phone,
      },
    );
    await _applyUser(data);
  }

  /// Upload a new profile photo from a local file path.
  Future<void> uploadAvatar(String filePath) async {
    final data = await _client.postMultipart(
      '/profile/photo',
      filePath: filePath,
    );
    await _applyUser(data);
  }

  /// Change the signed-in user's password after confirming the current one.
  Future<void> changePassword({
    required String currentPassword,
    required String newPassword,
    required String confirmation,
  }) async {
    await _client.post(
      '/profile/password',
      body: {
        'current_password': currentPassword,
        'password': newPassword,
        'password_confirmation': confirmation,
      },
    );
  }

  /// Replace the cached user with a fresh payload from the API.
  Future<void> _applyUser(dynamic payload) async {
    if (payload is! Map) {
      throw ApiException('The server returned an unexpected response.');
    }
    _user = AppUser.fromJson(
      payload.map((key, value) => MapEntry('$key', value)),
    );
    await _storage.writeUserJson(jsonEncode(_user!.toJson()));
    notifyListeners();
  }

  /// Refresh the cached user profile from the server.
  Future<void> refreshUser() async {
    if (!isSignedIn) return;
    try {
      final me = await _client.me();
      _user = AppUser.fromJson(me);
      await _storage.writeUserJson(jsonEncode(_user!.toJson()));
      notifyListeners();
    } on ApiException catch (error) {
      if (error.isUnauthorized) {
        await signOut(remote: false);
      }
    }
  }

  void _setSignedOut() {
    _status = SessionStatus.signedOut;
    notifyListeners();
  }

  @override
  void dispose() {
    _client.dispose();
    super.dispose();
  }
}
