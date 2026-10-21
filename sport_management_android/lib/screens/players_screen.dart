import 'package:flutter/material.dart';

import '../models/json.dart';
import '../services/api_exception.dart';
import '../state/session_store.dart';
import '../widgets/common.dart';
import 'player_detail_screen.dart';

/// Searchable list of players.
class PlayersScreen extends StatefulWidget {
  const PlayersScreen({super.key, required this.session});

  final SessionStore session;

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
    return Scaffold(
      appBar: AppBar(title: const Text('Players')),
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(12, 12, 12, 4),
            child: TextField(
              controller: _searchController,
              textInputAction: TextInputAction.search,
              decoration: InputDecoration(
                hintText: 'Search by name or nationality',
                prefixIcon: const Icon(Icons.search),
                border: const OutlineInputBorder(),
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
                  return const LoadingView(message: 'Loading players…');
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
                  child: ListView.separated(
                    itemCount: players.length,
                    separatorBuilder: (_, _) => const Divider(height: 1),
                    itemBuilder: (context, i) => _PlayerTile(
                      session: widget.session,
                      player: players[i],
                    ),
                  ),
                );
              },
            ),
          ),
        ],
      ),
    );
  }
}

class _PlayerTile extends StatelessWidget {
  const _PlayerTile({required this.session, required this.player});

  final SessionStore session;
  final Map<String, dynamic> player;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    final name = '${Json.string(player['first_name'])} ${Json.string(player['last_name'])}'
        .trim();

    return ListTile(
      leading: CircleAvatar(
        backgroundColor: scheme.primaryContainer,
        child: Text(
          Json.string(player['jersey_number'], '—'),
          style: TextStyle(
            color: scheme.onPrimaryContainer,
            fontWeight: FontWeight.bold,
          ),
        ),
      ),
      title: Text(name.isEmpty ? 'Unknown player' : name),
      subtitle: Text(
        [
          Json.string(player['position']),
          Json.string(player['team_name']),
        ].where((s) => s.isNotEmpty).join(' · '),
      ),
      trailing: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          Text(
            '${Json.intOr(player['goals'])}',
            style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 16),
          ),
          Text('goals', style: TextStyle(fontSize: 11, color: scheme.outline)),
        ],
      ),
      onTap: () {
        final id = Json.intOrNull(player['id']);
        if (id == null) return;
        Navigator.of(context).push(
          MaterialPageRoute(
            builder: (_) => PlayerDetailScreen(session: session, playerId: id),
          ),
        );
      },
    );
  }
}
