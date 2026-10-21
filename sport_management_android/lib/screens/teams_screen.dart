import 'package:flutter/material.dart';

import '../models/json.dart';
import '../services/api_exception.dart';
import '../state/session_store.dart';
import '../widgets/common.dart';
import 'team_detail_screen.dart';

/// Searchable list of teams.
class TeamsScreen extends StatefulWidget {
  const TeamsScreen({super.key, required this.session});

  final SessionStore session;

  @override
  State<TeamsScreen> createState() => _TeamsScreenState();
}

class _TeamsScreenState extends State<TeamsScreen> {
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
        .get('/teams', query: {
          if (_searchController.text.trim().isNotEmpty)
            'q': _searchController.text.trim(),
          'per_page': 50,
        })
        .then((data) => Json.list(Json.map(data)['items']));
  }

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        Padding(
          padding: const EdgeInsets.fromLTRB(12, 12, 12, 4),
          child: TextField(
            controller: _searchController,
            textInputAction: TextInputAction.search,
            decoration: InputDecoration(
              hintText: 'Search teams by name, city or coach',
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
                return const LoadingView(message: 'Loading teams…');
              }
              if (snapshot.hasError) {
                final error = snapshot.error;
                return ErrorRetryView(
                  message: error is ApiException
                      ? error.message
                      : 'Could not load teams.',
                  onRetry: () => setState(_load),
                );
              }

              final teams = snapshot.data ?? const [];
              if (teams.isEmpty) {
                return const EmptyView(
                  message: 'No teams found.',
                  icon: Icons.groups_outlined,
                );
              }

              return RefreshIndicator(
                onRefresh: () async => setState(_load),
                child: ListView.separated(
                  itemCount: teams.length,
                  separatorBuilder: (_, _) => const Divider(height: 1),
                  itemBuilder: (context, i) =>
                      _TeamTile(session: widget.session, team: teams[i]),
                ),
              );
            },
          ),
        ),
      ],
    );
  }
}

class _TeamTile extends StatelessWidget {
  const _TeamTile({required this.session, required this.team});

  final SessionStore session;
  final Map<String, dynamic> team;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    final code = Json.string(team['code']);

    return ListTile(
      leading: CircleAvatar(
        backgroundColor: scheme.primaryContainer,
        child: Text(
          code.isNotEmpty
              ? code
              : Json.string(team['name'], '?').characters.first,
          style: TextStyle(
            color: scheme.onPrimaryContainer,
            fontWeight: FontWeight.bold,
            fontSize: 12,
          ),
        ),
      ),
      title: Text(
        Json.string(team['name'], 'Team'),
        style: const TextStyle(fontWeight: FontWeight.w600),
      ),
      subtitle: Text(
        [
          Json.string(team['city']),
          if (Json.string(team['coach']).isNotEmpty) 'Coach: ${team['coach']}',
        ].where((s) => s.isNotEmpty).join(' · '),
      ),
      trailing: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          Text(
            '${Json.intOr(team['players_count'])}',
            style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 16),
          ),
          Text('players', style: TextStyle(fontSize: 11, color: scheme.outline)),
        ],
      ),
      onTap: () {
        final id = Json.intOrNull(team['id']);
        if (id == null) return;
        Navigator.of(context).push(
          MaterialPageRoute(
            builder: (_) => TeamDetailScreen(session: session, teamId: id),
          ),
        );
      },
    );
  }
}
