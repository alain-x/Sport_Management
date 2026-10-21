import 'dart:async';
import 'dart:convert';

import 'package:http/http.dart' as http;

import '../config.dart';
import 'api_exception.dart';

/// Thin, typed wrapper around the Laravel JSON API.
///
/// Responsibilities:
///  * build URLs against [AppConfig.apiBaseUrl];
///  * attach the Sanctum bearer token when one is set;
///  * unwrap the `{ "data": ... }` success envelope;
///  * translate failures into [ApiException].
///
/// The token is supplied by the session store, so the client itself stays
/// stateless and easy to test.
class ApiClient {
  ApiClient({http.Client? httpClient, String? Function()? tokenProvider})
      : _http = httpClient ?? http.Client(),
        _tokenProvider = tokenProvider ?? (() => null);

  final http.Client _http;
  final String? Function() _tokenProvider;

  /// GET a resource and return its unwrapped `data` payload.
  Future<dynamic> get(String path, {Map<String, dynamic>? query}) {
    return _send('GET', path, query: query);
  }

  /// GET a resource expected to be a JSON object.
  Future<Map<String, dynamic>> getMap(
    String path, {
    Map<String, dynamic>? query,
  }) async {
    return _asMap(await _send('GET', path, query: query));
  }

  /// GET a resource expected to be a JSON array.
  Future<List<Map<String, dynamic>>> getList(
    String path, {
    Map<String, dynamic>? query,
  }) async {
    final data = await _send('GET', path, query: query);
    if (data is List) {
      return data.map(_asMap).toList();
    }
    return const [];
  }

  /// POST JSON to a resource and return its unwrapped payload.
  Future<dynamic> post(String path, {Object? body}) {
    return _send('POST', path, body: body);
  }

  /// PUT JSON to a resource and return its unwrapped payload.
  Future<dynamic> put(String path, {Object? body}) {
    return _send('PUT', path, body: body);
  }

  /// DELETE a resource.
  Future<dynamic> delete(String path) {
    return _send('DELETE', path);
  }

  /// Log in and return the raw payload (`{ token, token_type, user }`).
  Future<Map<String, dynamic>> login({
    required String email,
    required String password,
    String deviceName = 'mobile',
  }) async {
    final data = await post('/login', body: {
      'email': email,
      'password': password,
      'device_name': deviceName,
    });

    // The login endpoint is the one place that is not wrapped in `data`.
    if (data is Map<String, dynamic> && data['token'] is String) {
      return data;
    }
    throw ApiException('Unexpected response from the server.');
  }

  /// Revoke the current token server-side (best effort).
  Future<void> logout() async {
    try {
      await post('/logout');
    } on ApiException {
      // A revoked/expired token already means we are signed out.
    }
  }

  /// The signed-in user's profile (`GET /me`).
  Future<Map<String, dynamic>> me() async {
    return _asMap(await get('/me'));
  }

  /// Role-aware dashboard summary (`GET /dashboard`).
  Future<Map<String, dynamic>> dashboard() async {
    return _asMap(await get('/dashboard'));
  }

  // -----------------------------------------------------------------
  // Internals
  // -----------------------------------------------------------------

  Future<dynamic> _send(
    String method,
    String path, {
    Map<String, dynamic>? query,
    Object? body,
  }) async {
    final uri = _buildUri(path, query);

    final headers = <String, String>{
      'Accept': 'application/json',
    };
    if (body != null) {
      headers['Content-Type'] = 'application/json';
    }
    final token = _tokenProvider();
    if (token != null && token.isNotEmpty) {
      headers['Authorization'] = 'Bearer $token';
    }

    http.Response response;
    try {
      final request = http.Request(method, uri)..headers.addAll(headers);
      if (body != null) {
        request.body = jsonEncode(body);
      }
      final streamed =
          await _http.send(request).timeout(AppConfig.requestTimeout);
      response = await http.Response.fromStream(streamed);
    } on TimeoutException {
      throw ApiException(
        'The server took too long to respond. Please try again.',
      );
    } catch (error) {
      throw ApiException(
        'Cannot reach the server at ${AppConfig.apiBaseUrl}. '
        'Check that the backend is running and the address is correct.',
      );
    }

    return _decode(response);
  }

  Uri _buildUri(String path, Map<String, dynamic>? query) {
    final normalized = path.startsWith('/') ? path : '/$path';
    final base = Uri.parse('${AppConfig.apiBaseUrl}$normalized');
    if (query == null || query.isEmpty) return base;

    final params = <String, String>{};
    query.forEach((key, value) {
      if (value == null) return;
      params[key] = '$value';
    });
    return base.replace(queryParameters: {...base.queryParameters, ...params});
  }

  dynamic _decode(http.Response response) {
    final status = response.statusCode;
    dynamic decoded;

    if (response.body.isNotEmpty) {
      try {
        decoded = jsonDecode(utf8.decode(response.bodyBytes));
      } catch (_) {
        decoded = null;
      }
    }

    if (status >= 200 && status < 300) {
      if (decoded is Map<String, dynamic> && decoded.containsKey('data')) {
        return decoded['data'];
      }
      return decoded;
    }

    throw _errorFrom(status, decoded);
  }

  ApiException _errorFrom(int status, dynamic decoded) {
    String message;
    Map<String, List<String>> errors = const {};

    if (decoded is Map<String, dynamic>) {
      final rawMessage = decoded['message'];
      final text = rawMessage == null ? '' : '$rawMessage'.trim();
      message = text.isNotEmpty ? text : _defaultMessage(status);

      final rawErrors = decoded['errors'];
      if (rawErrors is Map) {
        errors = rawErrors.map((key, value) {
          final list = value is List ? value.map((e) => '$e').toList() : ['$value'];
          return MapEntry('$key', list);
        });
      }
    } else {
      message = _defaultMessage(status);
    }

    return ApiException(message, statusCode: status, errors: errors);
  }

  String _defaultMessage(int status) {
    switch (status) {
      case 401:
        return 'Your session has expired. Please sign in again.';
      case 403:
        return 'You do not have permission to do that.';
      case 404:
        return 'The requested item could not be found.';
      case 422:
        return 'Some of the details you entered are not valid.';
      case 500:
        return 'The server encountered an error. Please try again later.';
      default:
        return 'Something went wrong (HTTP $status).';
    }
  }

  Map<String, dynamic> _asMap(dynamic value) {
    if (value is Map<String, dynamic>) return value;
    if (value is Map) return value.map((k, v) => MapEntry('$k', v));
    throw ApiException('Unexpected response shape from the server.');
  }

  void dispose() => _http.close();
}
