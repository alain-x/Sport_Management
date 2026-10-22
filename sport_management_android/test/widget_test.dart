import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;

import 'package:sport_management_android/config.dart';
import 'package:sport_management_android/models/app_user.dart';
import 'package:sport_management_android/models/json.dart';
import 'package:sport_management_android/services/api_client.dart';
import 'package:sport_management_android/services/api_exception.dart';

void main() {
  group('AppConfig', () {
    test('exposes an /api base URL', () {
      expect(AppConfig.apiBaseUrl.endsWith('/api'), isTrue);
      expect(AppConfig.loginErrorDuration, const Duration(seconds: 3));
    });
  });

  group('AppUser', () {
    test('parses the login/me payload', () {
      final user = AppUser.fromJson({
        'id': 7,
        'name': 'Alain Valentin',
        'email': 'alain@example.com',
        'role': 'player',
        'team_id': 1,
        'player_id': 3,
        'team_name': '1000 Hills Rugby',
      });

      expect(user.id, 7);
      expect(user.isPlayer, isTrue);
      expect(user.initials, 'AV');
      expect(user.roleLabel, 'Player');
      expect(user.teamName, '1000 Hills Rugby');
    });
  });

  group('Json', () {
    // A match row as returned by /api/dashboard: the team name is present
    // both flattened and as a nested object.
    final match = <String, dynamic>{
      'id': 1,
      'status': 'finished',
      'home_score': 2,
      'away_score': 1,
      'match_date': '2026-08-15 18:00:00',
      'home_name': '1000 Hills Rugby',
      'home_code': '1HR',
      'away_name': 'Lions De fer',
      'away_code': 'LDF',
      'home_team': {'id': 1, 'name': '1000 Hills Rugby', 'code': '1HR'},
      'away_team': {'id': 2, 'name': 'Lions De fer', 'code': 'LDF'},
    };

    test('teamName prefers the flattened name', () {
      expect(Json.teamName(match, 'home', fallback: 'Home'), '1000 Hills Rugby');
      expect(Json.teamName(match, 'away', fallback: 'Away'), 'Lions De fer');
    });

    test('teamName falls back to the nested team object', () {
      final nestedOnly = <String, dynamic>{
        'home_team': {'id': 1, 'name': '1000 Hills Rugby', 'code': '1HR'},
      };

      expect(
        Json.teamName(nestedOnly, 'home', fallback: 'Home'),
        '1000 Hills Rugby',
      );
    });

    test('teamName falls back when neither shape is present', () {
      expect(Json.teamName(<String, dynamic>{}, 'home', fallback: 'Home'), 'Home');
    });

    test('teamCode reads the flat or nested code', () {
      expect(Json.teamCode(match, 'home'), '1HR');
      expect(
        Json.teamCode(
          <String, dynamic>{
            'away_team': {'id': 2, 'name': 'Lions De fer', 'code': 'LDF'},
          },
          'away',
        ),
        'LDF',
      );
    });

    test('string never leaks a raw map into the UI', () {
      expect(Json.string(match['home_team'], 'Home'), 'Home');
      expect(Json.string(match['home_name'], 'Home'), '1000 Hills Rugby');
    });
  });

  group('ApiClient', () {
    test('builds an /api URL and attaches no token when none is set', () async {
      late Uri captured;
      final client = ApiClient(
        httpClient: _CapturingClient((request) {
          captured = request.url;
          return _json('{"data":{"id":1}}', 200);
        }),
      );

      await client.getMap('/me');

      expect(captured.path.endsWith('/api/me'), isTrue);
    });

    test('unwraps the data envelope for object payloads', () async {
      final client = ApiClient(
        httpClient: _CapturingClient(
          (_) => _json('{"data":{"items":[{"id":1}],"meta":{"total":1}}}', 200),
        ),
      );

      final payload = await client.getMap('/teams');
      final items = (payload['items'] as List);
      expect(items, hasLength(1));
      expect((items.first as Map)['id'], 1);
    });

    test('throws ApiException with field errors on 422', () async {
      final client = ApiClient(
        httpClient: _CapturingClient(
          (_) => _json(
            '{"message":"Invalid","errors":{"email":["Bad email"]}}',
            422,
          ),
        ),
      );

      expect(
        () => client.post('/login'),
        throwsA(
          isA<ApiException>()
              .having((e) => e.statusCode, 'status', 422)
              .having((e) => e.firstErrorFor('email'), 'email error', 'Bad email'),
        ),
      );
    });

    test('marks 401 as unauthorized', () async {
      final client = ApiClient(
        httpClient: _CapturingClient((_) => _json('{"message":"Unauthenticated."}', 401)),
      );

      expect(
        () => client.get('/me'),
        throwsA(isA<ApiException>().having((e) => e.isUnauthorized, 'isUnauthorized', isTrue)),
      );
    });
  });
}

// ---------------------------------------------------------------------------
// Tiny fake HTTP client so the tests never touch the network.
// ---------------------------------------------------------------------------

class _CapturingClient extends http.BaseClient {
  _CapturingClient(this.handler);

  final http.Response Function(http.BaseRequest request) handler;

  @override
  Future<http.StreamedResponse> send(http.BaseRequest request) async {
    final response = handler(request);
    return http.StreamedResponse(
      Stream.value(response.bodyBytes),
      response.statusCode,
      headers: response.headers,
      request: request,
    );
  }
}

http.Response _json(String body, int status) => http.Response(
      body,
      status,
      headers: {'content-type': 'application/json; charset=utf-8'},
    );
