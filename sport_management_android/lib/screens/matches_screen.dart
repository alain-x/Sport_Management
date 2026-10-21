import 'package:flutter/material.dart';

import '../models/json.dart';
import '../services/api_exception.dart';
import '../state/session_store.dart';
import '../widgets/common.dart';
import 'match_detail_screen.dart';

/// Fixtures and results, with status filters.
class MatchesScreen extends StatefulWidget {
  const MatchesScreen({super.key, required this.session});

  final SessionStore session;

  @override
  State<MatchesScreen> createState() => _MatchesScreenState();
}

class _MatchesScreenState extends State<MatchesScreen> {
  static const _filters = <String, String?>{
    'All': null,
    'Scheduled': 'scheduled',
    'Live': 'live',
    'Finished': 'finished',
  };

  String _active = 'All';
  late Future<List<Map<String, dynamic>>> _future;

  @override
  void initState() {
    super.initState();
    _load();
  }

  void _load() {
    final status = _filters[_active];
    _future = widget.session.client
        .get('/matches', query: {
          'per_page': 50,
          'status': ?status,
        })
        .then((data) => Json.list(Json.map(data)['items']));
  }

  void _select(String key) {
    setState(() {
      _active = key;
      _load();
    });
  }

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        SingleChildScrollView(
          scrollDirection: Axis.horizontal,
          padding: const EdgeInsets.fromLTRB(12, 12, 12, 4),
          child: Row(
            children: [
              for (final key in _filters.keys)
                Padding(
                  padding: const EdgeInsets.only(right: 8),
                  child: ChoiceChip(
                    label: Text(key),
                    selected: _active == key,
                    onSelected: (_) => _select(key),
                  ),
                ),
            ],
          ),
        ),
        Expanded(
          child: FutureBuilder<List<Map<String, dynamic>>>(
            future: _future,
            builder: (context, snapshot) {
              if (snapshot.connectionState == ConnectionState.waiting) {
                return const LoadingView(message: 'Loading matches…');
              }
              if (snapshot.hasError) {
                final error = snapshot.error;
                return ErrorRetryView(
                  message: error is ApiException
                      ? error.message
                      : 'Could not load matches.',
                  onRetry: () => setState(_load),
                );
              }

              final matches = snapshot.data ?? const [];
              if (matches.isEmpty) {
                return const EmptyView(
                  message: 'No matches to show for this filter.',
                  icon: Icons.sports_soccer_outlined,
                );
              }

              return RefreshIndicator(
                onRefresh: () async => setState(_load),
                child: ListView.separated(
                  itemCount: matches.length,
                  separatorBuilder: (_, _) => const Divider(height: 1),
                  itemBuilder: (context, i) => _MatchRow(
                    session: widget.session,
                    match: matches[i],
                  ),
                ),
              );
            },
          ),
        ),
      ],
    );
  }
}

class _MatchRow extends StatelessWidget {
  const _MatchRow({required this.session, required this.match});

  final SessionStore session;
  final Map<String, dynamic> match;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final status = Json.string(match['status'], 'scheduled');
    final home = Json.string(match['home_name'], 'Home');
    final away = Json.string(match['away_name'], 'Away');

    return ListTile(
      onTap: () {
        final id = Json.intOrNull(match['id']);
        if (id == null) return;
        Navigator.of(context).push(
          MaterialPageRoute(
            builder: (_) => MatchDetailScreen(session: session, matchId: id),
          ),
        );
      },
      title: Padding(
        padding: const EdgeInsets.only(bottom: 4),
        child: Row(
          children: [
            StatusChip(
              label: status,
              color: StatusChip.colorFor(status, theme.colorScheme),
            ),
            const SizedBox(width: 8),
            Expanded(
              child: Text(
                Json.string(match['tournament_name'], 'Friendly'),
                style: theme.textTheme.labelSmall,
                overflow: TextOverflow.ellipsis,
              ),
            ),
          ],
        ),
      ),
      subtitle: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const SizedBox(height: 4),
          _teamLine(context, home, Json.intOrNull(match['home_score']), true),
          const SizedBox(height: 2),
          _teamLine(context, away, Json.intOrNull(match['away_score']), false),
          const SizedBox(height: 6),
          Text(
            Json.dateTimeLabel(match['match_date']),
            style: theme.textTheme.bodySmall
                ?.copyWith(color: theme.colorScheme.outline),
          ),
        ],
      ),
    );
  }

  Widget _teamLine(BuildContext context, String name, int? score, bool home) {
    return Row(
      children: [
        Expanded(
          child: Text(
            name,
            style: const TextStyle(fontWeight: FontWeight.w600),
          ),
        ),
        if (score != null)
          Text(
            '$score',
            style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 16),
          ),
      ],
    );
  }
}
