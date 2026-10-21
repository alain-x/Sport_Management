import 'package:flutter/material.dart';

import '../models/json.dart';
import '../services/api_exception.dart';
import '../state/session_store.dart';
import '../widgets/common.dart';

/// A single match with its details.
class MatchDetailScreen extends StatefulWidget {
  const MatchDetailScreen({
    super.key,
    required this.session,
    required this.matchId,
  });

  final SessionStore session;
  final int matchId;

  @override
  State<MatchDetailScreen> createState() => _MatchDetailScreenState();
}

class _MatchDetailScreenState extends State<MatchDetailScreen> {
  late Future<Map<String, dynamic>> _future;

  @override
  void initState() {
    super.initState();
    _load();
  }

  void _load() {
    _future = widget.session.client.getMap('/matches/${widget.matchId}');
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Match')),
      body: FutureBuilder<Map<String, dynamic>>(
        future: _future,
        builder: (context, snapshot) {
          if (snapshot.connectionState == ConnectionState.waiting) {
            return const LoadingView();
          }
          if (snapshot.hasError) {
            final error = snapshot.error;
            return ErrorRetryView(
              message: error is ApiException
                  ? error.message
                  : 'Could not load the match.',
              onRetry: () => setState(_load),
            );
          }

          final match = snapshot.data ?? const {};
          final status = Json.string(match['status'], 'scheduled');
          final scheme = Theme.of(context).colorScheme;

          return ListView(
            padding: const EdgeInsets.all(16),
            children: [
              Card(
                child: Padding(
                  padding: const EdgeInsets.symmetric(
                    horizontal: 16,
                    vertical: 24,
                  ),
                  child: Column(
                    children: [
                      StatusChip(
                        label: status,
                        color: StatusChip.colorFor(status, scheme),
                      ),
                      const SizedBox(height: 20),
                      Row(
                        children: [
                          _Side(
                            name: Json.string(match['home_name'], 'Home'),
                            code: Json.string(match['home_code']),
                            score: Json.intOrNull(match['home_score']),
                          ),
                          Text(
                            'vs',
                            style: TextStyle(
                              color: scheme.outline,
                              fontWeight: FontWeight.w600,
                            ),
                          ),
                          _Side(
                            name: Json.string(match['away_name'], 'Away'),
                            code: Json.string(match['away_code']),
                            score: Json.intOrNull(match['away_score']),
                          ),
                        ],
                      ),
                    ],
                  ),
                ),
              ),
              const SizedBox(height: 16),
              Card(
                child: Padding(
                  padding: const EdgeInsets.all(16),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      DetailRow(
                        label: 'Kick-off',
                        value: Json.dateTimeLabel(match['match_date']),
                      ),
                      DetailRow(
                        label: 'Tournament',
                        value: Json.string(match['tournament_name'], '—'),
                      ),
                      DetailRow(
                        label: 'Venue',
                        value: Json.string(match['venue'], '—'),
                      ),
                      DetailRow(label: 'Status', value: status),
                    ],
                  ),
                ),
              ),
            ],
          );
        },
      ),
    );
  }
}

class _Side extends StatelessWidget {
  const _Side({required this.name, required this.code, this.score});

  final String name;
  final String code;
  final int? score;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    return Expanded(
      child: Column(
        children: [
          CircleAvatar(
            radius: 26,
            backgroundColor: scheme.primaryContainer,
            child: Text(
              code.isNotEmpty ? code : name.characters.first,
              style: TextStyle(
                color: scheme.onPrimaryContainer,
                fontWeight: FontWeight.bold,
              ),
            ),
          ),
          const SizedBox(height: 8),
          Text(
            name,
            textAlign: TextAlign.center,
            style: const TextStyle(fontWeight: FontWeight.w600),
          ),
          if (score != null) ...[
            const SizedBox(height: 8),
            Text(
              '$score',
              style: const TextStyle(
                fontSize: 28,
                fontWeight: FontWeight.w800,
              ),
            ),
          ],
        ],
      ),
    );
  }
}
