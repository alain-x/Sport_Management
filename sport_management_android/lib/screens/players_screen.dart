import 'package:flutter/material.dart';

import '../models/json.dart';
import '../services/api_exception.dart';
import '../state/session_store.dart';
import '../widgets/common.dart';
import 'player_detail_screen.dart';

/// Searchable list of players. Managers only ever see their own squad because
/// the API pins non-admin accounts to their team.
class PlayersScreen extends StatefulWidget {
  const PlayersScreen({super.key, required this.session, this.embedded = false});

  final SessionStore session;
  final bool embedded;

  @override
  State<PlayersScreen> createState() => _PlayersScreenState();
}

class _PlayersScreenState extends State<PlayersScreen> {
  final _searchController = TextEditingController();
  late Future<List<Map<String, dynamic>>> _future;

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  void _load() {
    _future = widget.session.client
        .get('/players', query: {
          if (_searchController.text.trim().isNotEmpty)
            'q': _searchController.text.trim(),
          'per_page': 50,
        })
        .then((data) => Json.list(Json.map(data)['items']));
  }

  @override
  Widget build(BuildContext context) {
    final content = Column(
      children: [
        Padding(
          padding: const EdgeInsets.fromLTRB(16, 8, 16, 8),
          child: TextField(
            controller: _searchController,
            textInputAction: TextInputAction.search,
            decoration: InputDecoration(
              hintText: 'Search by name or nationality',
              prefixIcon: const Icon(Icons.search),
              isDense: true,
              suffixIcon: IconButton(
                icon: const Icon(Icons.clear),
                tooltip: 'Clear',
                onPressed: () {
                  _searchController.clear();
                  setState(_load);
                },
              ),
            ),
            onSubmitted: (_) => setState(_load),
          ),
        ),
        Expanded(
          child: FutureBuilder<List<Map<String, dynamic>>>(
            future: _future,
            builder: (context, snapshot) {
              if (snapshot.connectionState == ConnectionState.waiting) {
                return const LoadingView(message: 'Loading the squad…');
              }
              if (snapshot.hasError) {
                final error = snapshot.error;
                return ErrorRetryView(
                  message: error is ApiException
                      ? error.message
                      : 'Could not load players.',
                  onRetry: () => setState(_load),
                );
              }

              final players = snapshot.data ?? const [];
              if (players.isEmpty) {
                return const EmptyView(
                  message: 'No players found.',
                  icon: Icons.person_search_outlined,
                );
              }

              return RefreshIndicator(
                onRefresh: () async => setState(_load),
                child: ListView.builder(
                  padding: const EdgeInsets.fromLTRB(16, 4, 16, 24),
                  itemCount: players.length,
                  itemBuilder: (context, i) => Padding(
                    padding: const EdgeInsets.only(bottom: 8),
                    child: _PlayerCard(
                      session: widget.session,
                      player: players[i],
                    ),
                  ),
                ),
              );
            },
          ),
        ),
      ],
    );

    if (widget.embedded) return content;

    return Scaffold(
      appBar: AppBar(title: const Text('Players')),
      body: content,
    );
  }
}

class _PlayerCard extends StatelessWidget {
  const _PlayerCard({required this.session, required this.player});

  final SessionStore session;
  final Map<String, dynamic> player;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    final name =
        '${Json.string(player['first_name'])} ${Json.string(player['last_name'])}'
            .trim();
    final rate = Json.intOr(player['attendance_rate']);
    final marked = Json.intOr(player['attendance_marked']);
    final jersey = Json.string(player['jersey_number'], '—');

    return Card(
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: () {
          final id = Json.intOrNull(player['id']);
          if (id == null) return;
          Navigator.of(context).push(
            MaterialPageRoute(
              builder: (_) => PlayerDetailScreen(
                session: session,
                playerId: id,
              ),
            ),
          );
        },
        child: Padding(
          padding: const EdgeInsets.all(12),
          child: Row(
            children: [
              CircleAvatar(
                radius: 22,
                backgroundColor: scheme.primaryContainer,
                child: Text(
                  jersey,
                  style: TextStyle(
                    color: scheme.onPrimaryContainer,
                    fontWeight: FontWeight.bold,
                  ),
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      name.isEmpty ? 'Unknown player' : name,
                      style: const TextStyle(fontWeight: FontWeight.w700),
                    ),
                    const SizedBox(height: 2),
                    Text(
                      [
                        Json.string(player['position']),
                        Json.string(player['team_name']),
                      ].where((s) => s.isNotEmpty).join(' · '),
                      style: TextStyle(
                        fontSize: 12,
                        color: scheme.outline,
                      ),
                    ),
                    const SizedBox(height: 8),
                    Row(
                      children: [
                        Expanded(
                          child: ClipRRect(
                            borderRadius: BorderRadius.circular(6),
                            child: LinearProgressIndicator(
                              value: rate / 100,
                              minHeight: 5,
                              backgroundColor: scheme.surfaceContainerHighest,
                              color: rate >= 75
                                  ? scheme.primary
                                  : Colors.orange.shade700,
                            ),
                          ),
                        ),
                        const SizedBox(width: 8),
                        Text(
                          marked == 0 ? 'No sessions' : '$rate% attendance',
                          style: TextStyle(
                            fontSize: 11,
                            fontWeight: FontWeight.w600,
                            color: scheme.outline,
                          ),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
              const SizedBox(width: 4),
              Column(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  Text(
                    '${Json.intOr(player['goals'])}',
                    style: const TextStyle(
                      fontWeight: FontWeight.w800,
                      fontSize: 16,
                    ),
                  ),
                  Text(
                    'goals',
                    style: TextStyle(fontSize: 10, color: scheme.outline),
                  ),
                ],
              ),
              const SizedBox(width: 4),
              const Icon(Icons.chevron_right),
            ],
          ),
        ),
      ),
    );
  }
}
