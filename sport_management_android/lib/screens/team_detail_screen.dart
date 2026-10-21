import 'package:flutter/material.dart';

import '../models/json.dart';
import '../services/api_exception.dart';
import '../state/session_store.dart';
import '../widgets/common.dart';
import 'player_detail_screen.dart';

/// Team detail: profile, squad and staff.
class TeamDetailScreen extends StatefulWidget {
  const TeamDetailScreen({
    super.key,
    required this.session,
    required this.teamId,
  });

  final SessionStore session;
  final int teamId;

  @override
  State<TeamDetailScreen> createState() => _TeamDetailScreenState();
}

class _TeamDetailScreenState extends State<TeamDetailScreen> {
  late Future<Map<String, dynamic>> _future;

  @override
  void initState() {
    super.initState();
    _load();
  }

  void _load() {
    _future = widget.session.client.getMap('/teams/${widget.teamId}');
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Team')),
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
                  : 'Could not load the team.',
              onRetry: () => setState(_load),
            );
          }

          final team = snapshot.data ?? const {};
          final players = Json.list(team['players']);
          final staff = Json.list(team['staff']);
          final scheme = Theme.of(context).colorScheme;

          return RefreshIndicator(
            onRefresh: () async => setState(_load),
            child: ListView(
              padding: const EdgeInsets.all(16),
              children: [
                Card(
                  child: Padding(
                    padding: const EdgeInsets.all(16),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          children: [
                            CircleAvatar(
                              radius: 28,
                              backgroundColor: scheme.primaryContainer,
                              child: Text(
                                Json.string(team['code'],
                                    Json.string(team['name'], '?')
                                        .characters
                                        .first),
                                style: TextStyle(
                                  color: scheme.onPrimaryContainer,
                                  fontWeight: FontWeight.bold,
                                ),
                              ),
                            ),
                            const SizedBox(width: 14),
                            Expanded(
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Text(
                                    Json.string(team['name'], 'Team'),
                                    style: const TextStyle(
                                      fontSize: 18,
                                      fontWeight: FontWeight.w700,
                                    ),
                                  ),
                                  if (Json.string(team['city']).isNotEmpty)
                                    Text(
                                      Json.string(team['city']),
                                      style: TextStyle(color: scheme.outline),
                                    ),
                                ],
                              ),
                            ),
                          ],
                        ),
                        const SizedBox(height: 12),
                        DetailRow(
                          label: 'Coach',
                          value: Json.string(team['coach'], '—'),
                        ),
                        DetailRow(
                          label: 'Founded',
                          value: Json.string(team['founded_year'], '—'),
                        ),
                        DetailRow(
                          label: 'Squad size',
                          value: '${Json.intOr(team['players_count'])}',
                        ),
                        DetailRow(
                          label: 'Matches played',
                          value: '${Json.intOr(team['finished_matches'])}',
                        ),
                      ],
                    ),
                  ),
                ),
                if (Json.string(team['description']).isNotEmpty) ...[
                  const SizedBox(height: 12),
                  Card(
                    child: Padding(
                      padding: const EdgeInsets.all(16),
                      child: Text(Json.string(team['description'])),
                    ),
                  ),
                ],
                _sectionTitle(context, 'Squad (${players.length})'),
                Card(
                  child: players.isEmpty
                      ? const Padding(
                          padding: EdgeInsets.all(16),
                          child: Text('No players registered.'),
                        )
                      : Column(
                          children: [
                            for (final player in players)
                              ListTile(
                                leading: CircleAvatar(
                                  child: Text(
                                    Json.string(player['jersey_number'], '—'),
                                    style: const TextStyle(fontSize: 13),
                                  ),
                                ),
                                title: Text(
                                  '${Json.string(player['first_name'])} ${Json.string(player['last_name'])}'
                                      .trim(),
                                ),
                                subtitle:
                                    Text(Json.string(player['position'], '—')),
                                trailing: const Icon(Icons.chevron_right),
                                onTap: () {
                                  final id = Json.intOrNull(player['id']);
                                  if (id == null) return;
                                  Navigator.of(context).push(
                                    MaterialPageRoute(
                                      builder: (_) => PlayerDetailScreen(
                                        session: widget.session,
                                        playerId: id,
                                      ),
                                    ),
                                  );
                                },
                              ),
                          ],
                        ),
                ),
                if (staff.isNotEmpty) ...[
                  _sectionTitle(context, 'Staff (${staff.length})'),
                  Card(
                    child: Column(
                      children: [
                        for (final member in staff)
                          ListTile(
                            leading: const Icon(Icons.badge_outlined),
                            title: Text(Json.string(member['name'], '—')),
                            subtitle: Text(Json.string(member['email'], '')),
                          ),
                      ],
                    ),
                  ),
                ],
              ],
            ),
          );
        },
      ),
    );
  }

  Widget _sectionTitle(BuildContext context, String text) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(4, 20, 4, 8),
      child: Text(
        text,
        style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 15),
      ),
    );
  }
}
