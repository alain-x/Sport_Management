import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;

import 'package:sport_management_android/screens/matches_screen.dart';
import 'package:sport_management_android/services/api_client.dart';
import 'package:sport_management_android/state/session_store.dart';

void main() {
  group('MatchesScreen', () {
    testWidgets('groups fixtures into live, upcoming and results', (
      tester,
    ) async {
      final session = _sessionWith((_) => _matchesPayload());

      await tester.pumpWidget(_host(session));
      await tester.pumpAndSettle();

      expect(find.text('LIVE NOW'), findsOneWidget);
      expect(find.text('UPCOMING FIXTURES'), findsOneWidget);
      expect(find.text('RECENT RESULTS'), findsOneWidget);

      // Team names come from the flattened API fields.
      expect(find.text('1000 Hills Rugby'), findsWidgets);
      expect(find.text('Lions De fer'), findsOneWidget);
      expect(find.text('Kigali Sharks'), findsOneWidget);
      expect(find.text('Rubavu Rangers'), findsOneWidget);

      // Played matches show each side's score; scheduled ones the kick-off.
      final liveCard = find.byType(Card).first;
      expect(
        find.descendant(of: liveCard, matching: find.text('2')),
        findsOneWidget,
      );
      expect(
        find.descendant(of: liveCard, matching: find.text('1')),
        findsOneWidget,
      );
      expect(find.text('KICK-OFF 18:30'), findsOneWidget);
    });

    testWidgets('requests only results when the Results filter is selected', (
      tester,
    ) async {
      final requests = <Uri>[];
      final session = _sessionWith((request) {
        requests.add(request.url);
        return _matchesPayload();
      });

      await tester.pumpWidget(_host(session));
      await tester.pumpAndSettle();

      await tester.tap(find.widgetWithText(FilterChip, 'Results'));
      await tester.pumpAndSettle();

      expect(requests.last.queryParameters['status'], 'finished');
      expect(find.text('RECENT RESULTS'), findsOneWidget);
      expect(find.text('LIVE NOW'), findsNothing);
      expect(find.text('UPCOMING FIXTURES'), findsNothing);
    });

    testWidgets('shows an empty state when the filter has no matches', (
      tester,
    ) async {
      final session = _sessionWith((_) => _envelope({'items': <dynamic>[]}));

      await tester.pumpWidget(_host(session));
      await tester.pumpAndSettle();

      expect(
        find.text('No matches to show for this filter.'),
        findsOneWidget,
      );
    });
  });
}

Widget _host(SessionStore session) {
  return MaterialApp(
    theme: ThemeData(useMaterial3: true),
    home: Scaffold(body: MatchesScreen(session: session)),
  );
}

SessionStore _sessionWith(
  http.Response Function(http.BaseRequest request) handler,
) {
  return SessionStore(
    client: ApiClient(httpClient: _FakeClient(handler)),
  );
}

const _live = <String, dynamic>{
  'id': 1,
  'status': 'live',
  'home_team_id': 1,
  'away_team_id': 2,
  'home_name': '1000 Hills Rugby',
  'home_code': '1HR',
  'away_name': 'Lions De fer',
  'away_code': 'LDF',
  'home_score': 2,
  'away_score': 1,
  'match_date': '2026-08-15 18:00:00',
  'tournament_name': 'Rwanda Cup',
  'venue': 'Amahoro Stadium',
};

const _upcoming = <String, dynamic>{
  'id': 2,
  'status': 'scheduled',
  'home_team_id': 1,
  'away_team_id': 3,
  'home_name': '1000 Hills Rugby',
  'home_code': '1HR',
  'away_name': 'Kigali Sharks',
  'away_code': 'KSH',
  'match_date': '2026-09-02 18:30:00',
  'tournament_name': 'Rwanda Cup',
  'venue': 'Amahoro Stadium',
};

const _finished = <String, dynamic>{
  'id': 3,
  'status': 'finished',
  'home_team_id': 1,
  'away_team_id': 4,
  'home_name': '1000 Hills Rugby',
  'home_code': '1HR',
  'away_name': 'Rubavu Rangers',
  'away_code': 'RVR',
  'home_score': 0,
  'away_score': 3,
  'match_date': '2026-07-11 15:00:00',
  'tournament_name': 'Friendly',
  'venue': null,
};

http.Response _matchesPayload() {
  return _envelope({
    'items': [_live, _upcoming, _finished],
    'meta': {'total': 3, 'page': 1, 'per_page': 50},
  });
}

http.Response _envelope(Object data) => http.Response(
      '{"data":${jsonEncode(data)}}',
      200,
      headers: {'content-type': 'application/json; charset=utf-8'},
    );

class _FakeClient extends http.BaseClient {
  _FakeClient(this.handler);

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
