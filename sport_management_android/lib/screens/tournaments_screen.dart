import 'package:flutter/material.dart';

import '../models/json.dart';
import '../services/api_exception.dart';
import '../state/session_store.dart';
import '../widgets/common.dart';
import 'standings_screen.dart';

/// Tournaments list; tapping one opens its fixtures and table.
class TournamentsScreen extends StatefulWidget {
  const TournamentsScreen({super.key, required this.session});

  final SessionStore session;

  @override
  State<TournamentsScreen> createState() => _TournamentsScreenState();
}

class _TournamentsScreenState extends State<TournamentsScreen> {
  late Future<List<Map<String, dynamic>>> _future;

  @override
  void initState() {
    super.initState();
    _load();
  }

  void _load() {
    _future = widget.session.client
        .get('/tournaments')
        .then((data) => Json.list(Json.map(data)['items']));
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Tournaments')),
      body: FutureBuilder<List<Map<String, dynamic>>>(
        future: _future,
        builder: (context, snapshot) {
          if (snapshot.connectionState == ConnectionState.waiting) {
            return const LoadingView(message: 'Loading tournaments…');
          }
          if (snapshot.hasError) {
            final error = snapshot.error;
            return ErrorRetryView(
              message: error is ApiException
                  ? error.message
                  : 'Could not load tournaments.',
              onRetry: () => setState(_load),
            );
          }

          final tournaments = snapshot.data ?? const [];
          if (tournaments.isEmpty) {
            return const EmptyView(
              message: 'No tournaments yet.',
              icon: Icons.emoji_events_outlined,
            );
          }

          return RefreshIndicator(
            onRefresh: () async => setState(_load),
            child: ListView.separated(
              itemCount: tournaments.length,
              separatorBuilder: (_, _) => const Divider(height: 1),
              itemBuilder: (context, i) => _TournamentTile(
                session: widget.session,
                tournament: tournaments[i],
              ),
            ),
          );
        },
      ),
    );
  }
}

class _TournamentTile extends StatelessWidget {
  const _TournamentTile({required this.session, required this.tournament});

  final SessionStore session;
  final Map<String, dynamic> tournament;

  @override
  Widget build(BuildContext context) {
    final status = Json.string(tournament['status'], '');
    final statusColor = switch (status) {
      'ongoing' => Colors.green.shade600,
      'completed' => Colors.blueGrey.shade600,
      'upcoming' => Colors.orange.shade700,
      _ => Theme.of(context).colorScheme.primary,
    };

    return ListTile(
      leading: const Icon(Icons.emoji_events_outlined),
      title: Text(
        Json.string(tournament['name'], 'Tournament'),
        style: const TextStyle(fontWeight: FontWeight.w600),
      ),
      subtitle: Text(
        '${Json.dateLabel(tournament['start_date'])} – '
        '${Json.dateLabel(tournament['end_date'])}',
      ),
      trailing: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        crossAxisAlignment: CrossAxisAlignment.end,
        children: [
          if (status.isNotEmpty) StatusChip(label: status, color: statusColor),
          const SizedBox(height: 4),
          Text(
            '${Json.intOr(tournament['matches_count'])} matches',
            style: TextStyle(
              fontSize: 11,
              color: Theme.of(context).colorScheme.outline,
            ),
          ),
        ],
      ),
      onTap: () => Navigator.of(context).push(
        MaterialPageRoute(
          builder: (_) => TournamentDetailScreen(
            session: session,
            tournamentId: Json.intOr(tournament['id']),
          ),
        ),
      ),
    );
  }
}

/// One tournament: its league table plus its matches.
class TournamentDetailScreen extends StatefulWidget {
  const TournamentDetailScreen({
    super.key,
    required this.session,
    required this.tournamentId,
  });

  final SessionStore session;
  final int tournamentId;

  @override
  State<TournamentDetailScreen> createState() => _TournamentDetailScreenState();
}

class _TournamentDetailScreenState extends State<TournamentDetailScreen> {
  late Future<Map<String, dynamic>> _future;

  @override
  void initState() {
    super.initState();
    _load();
  }

  void _load() {
    _future =
        widget.session.client.getMap('/tournaments/${widget.tournamentId}');
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Tournament')),
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
                  : 'Could not load the tournament.',
              onRetry: () => setState(_load),
            );
          }

          final tournament = snapshot.data ?? const {};
          final matches = Json.list(tournament['matches']);

          return ListView(
            padding: const EdgeInsets.all(16),
            children: [
              Card(
                child: Padding(
                  padding: const EdgeInsets.all(16),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        Json.string(tournament['name'], 'Tournament'),
                        style: const TextStyle(
                          fontSize: 18,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                      const SizedBox(height: 6),
                      DetailRow(
                        label: 'Starts',
                        value: Json.dateLabel(tournament['start_date']),
                      ),
                      DetailRow(
                        label: 'Ends',
                        value: Json.dateLabel(tournament['end_date']),
                      ),
                      DetailRow(
                        label: 'Status',
                        value: Json.string(tournament['status'], '—'),
                      ),
                    ],
                  ),
                ),
              ),
              Padding(
                padding: const EdgeInsets.fromLTRB(4, 20, 4, 8),
                child: Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    const Text(
                      'Matches',
                      style: TextStyle(
                        fontWeight: FontWeight.w700,
                        fontSize: 15,
                      ),
                    ),
                    TextButton.icon(
                      icon: const Icon(Icons.leaderboard_outlined, size: 18),
                      label: const Text('View table'),
                      onPressed: () => Navigator.of(context).push(
                        MaterialPageRoute(
                          builder: (_) => StandingsScreen(session: widget.session),
                        ),
                      ),
                    ),
                  ],
                ),
              ),
              Card(
                child: matches.isEmpty
                    ? const Padding(
                        padding: EdgeInsets.all(16),
                        child: Text('No matches scheduled.'),
                      )
                    : Column(
                        children: [
                          for (final match in matches)
                            ListTile(
                              title: Text(
                                '${Json.string(match['home_name'], 'Home')} vs '
                                '${Json.string(match['away_name'], 'Away')}',
                              ),
                              subtitle: Text(
                                Json.dateTimeLabel(match['match_date']),
                              ),
                              trailing: Json.string(match['status']) ==
                                      'scheduled'
                                  ? const Text('—')
                                  : Text(
                                      '${Json.intOr(match['home_score'])} - '
                                      '${Json.intOr(match['away_score'])}',
                                      style: const TextStyle(
                                        fontWeight: FontWeight.w700,
                                      ),
                                    ),
                            ),
                        ],
                      ),
              ),
            ],
          );
        },
      ),
    );
  }
}
