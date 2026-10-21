import 'package:flutter/material.dart';

import '../models/json.dart';
import '../services/api_exception.dart';
import '../state/session_store.dart';
import '../widgets/common.dart';

/// Player profile with overview, stats, and (when permitted) private
/// fitness and welfare tabs.
class PlayerDetailScreen extends StatefulWidget {
  const PlayerDetailScreen({
    super.key,
    required this.session,
    required this.playerId,
  });

  final SessionStore session;
  final int playerId;

  @override
  State<PlayerDetailScreen> createState() => _PlayerDetailScreenState();
}

class _PlayerDetailScreenState extends State<PlayerDetailScreen> {
  late Future<Map<String, dynamic>> _future;

  @override
  void initState() {
    super.initState();
    _load();
  }

  void _load() {
    _future = widget.session.client.getMap('/players/${widget.playerId}');
  }

  @override
  Widget build(BuildContext context) {
    return DefaultTabController(
      length: 3,
      child: Scaffold(
        appBar: AppBar(
          title: const Text('Player'),
          bottom: const TabBar(
            tabs: [
              Tab(text: 'Overview'),
              Tab(text: 'Fitness'),
              Tab(text: 'Welfare'),
            ],
          ),
        ),
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
                    : 'Could not load the player.',
                onRetry: () => setState(_load),
              );
            }

            final player = snapshot.data ?? const {};
            final canSeePrivate = player['can_see_private'] == true;

            return TabBarView(
              children: [
                _OverviewTab(player: player),
                _PrivateGate(
                  allowed: canSeePrivate,
                  child: _MetricsTab(metrics: Json.list(player['metrics'])),
                ),
                _PrivateGate(
                  allowed: canSeePrivate,
                  child: _WelfareTab(welfare: Json.list(player['welfare'])),
                ),
              ],
            );
          },
        ),
      ),
    );
  }
}

class _PrivateGate extends StatelessWidget {
  const _PrivateGate({required this.allowed, required this.child});

  final bool allowed;
  final Widget child;

  @override
  Widget build(BuildContext context) {
    if (allowed) return child;
    return const EmptyView(
      message: 'This information is private and only visible to the player '
          'and team staff.',
      icon: Icons.lock_outline,
    );
  }
}

class _OverviewTab extends StatelessWidget {
  const _OverviewTab({required this.player});

  final Map<String, dynamic> player;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    final name =
        '${Json.string(player['first_name'])} ${Json.string(player['last_name'])}'
            .trim();

    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        Card(
          child: Padding(
            padding: const EdgeInsets.all(16),
            child: Column(
              children: [
                CircleAvatar(
                  radius: 40,
                  backgroundColor: scheme.primaryContainer,
                  child: Text(
                    Json.string(player['jersey_number'], '#'),
                    style: TextStyle(
                      color: scheme.onPrimaryContainer,
                      fontSize: 22,
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                ),
                const SizedBox(height: 12),
                Text(
                  name.isEmpty ? 'Unknown player' : name,
                  style: const TextStyle(
                    fontSize: 19,
                    fontWeight: FontWeight.w700,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  [
                    Json.string(player['position']),
                    Json.string(player['team_name']),
                  ].where((s) => s.isNotEmpty).join(' · '),
                  style: TextStyle(color: scheme.outline),
                ),
                if (player['is_self'] == true) ...[
                  const SizedBox(height: 10),
                  Chip(
                    label: const Text('This is you'),
                    avatar: const Icon(Icons.person, size: 16),
                    backgroundColor: scheme.primaryContainer,
                  ),
                ],
              ],
            ),
          ),
        ),
        const SizedBox(height: 12),
        Card(
          child: Padding(
            padding: const EdgeInsets.all(16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text('Season statistics',
                    style: TextStyle(fontWeight: FontWeight.w700)),
                const Divider(),
                DetailRow(
                    label: 'Appearances',
                    value: '${Json.intOr(player['appearances'])}'),
                DetailRow(
                    label: 'Goals', value: '${Json.intOr(player['goals'])}'),
                DetailRow(
                    label: 'Assists', value: '${Json.intOr(player['assists'])}'),
                DetailRow(
                  label: 'Minutes',
                  value: '${Json.intOr(player['minutes_played'])}',
                ),
                DetailRow(
                  label: 'Yellow cards',
                  value: '${Json.intOr(player['yellow_cards'])}',
                ),
                DetailRow(
                  label: 'Red cards',
                  value: '${Json.intOr(player['red_cards'])}',
                ),
                DetailRow(
                  label: 'Rating',
                  value: Json.string(player['rating'], '—'),
                ),
              ],
            ),
          ),
        ),
        const SizedBox(height: 12),
        Card(
          child: Padding(
            padding: const EdgeInsets.all(16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text('Profile',
                    style: TextStyle(fontWeight: FontWeight.w700)),
                const Divider(),
                DetailRow(
                  label: 'Date of birth',
                  value: Json.dateLabel(player['date_of_birth']),
                ),
                DetailRow(
                  label: 'Nationality',
                  value: Json.string(player['nationality'], '—'),
                ),
                DetailRow(
                  label: 'Preferred foot',
                  value: Json.string(player['foot'], '—'),
                ),
              ],
            ),
          ),
        ),
      ],
    );
  }
}

class _MetricsTab extends StatelessWidget {
  const _MetricsTab({required this.metrics});

  final List<Map<String, dynamic>> metrics;

  @override
  Widget build(BuildContext context) {
    if (metrics.isEmpty) {
      return const EmptyView(
        message: 'No fitness tests recorded yet.',
        icon: Icons.fitness_center,
      );
    }

    return ListView.builder(
      padding: const EdgeInsets.all(16),
      itemCount: metrics.length,
      itemBuilder: (context, i) {
        final metric = metrics[i];
        return Card(
          child: Padding(
            padding: const EdgeInsets.all(16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    Text(
                      Json.dateLabel(metric['test_date']),
                      style: const TextStyle(fontWeight: FontWeight.w700),
                    ),
                    StatusChip(
                      label: Json.string(metric['fitness'], '—'),
                      color: Theme.of(context).colorScheme.primary,
                    ),
                  ],
                ),
                const Divider(),
                DetailRow(
                  label: 'Height',
                  value: '${Json.string(metric['height_cm'], '—')} cm',
                ),
                DetailRow(
                  label: 'Weight',
                  value: '${Json.string(metric['weight_kg'], '—')} kg',
                ),
                DetailRow(
                  label: 'Body fat',
                  value: '${Json.string(metric['body_fat'], '—')} %',
                ),
                DetailRow(
                  label: 'Top speed',
                  value: Json.string(metric['top_speed'], '—'),
                ),
                DetailRow(
                  label: 'Stamina',
                  value: Json.string(metric['stamina'], '—'),
                ),
                DetailRow(
                  label: 'Strength',
                  value: Json.string(metric['strength'], '—'),
                ),
                DetailRow(
                  label: 'Agility',
                  value: Json.string(metric['agility'], '—'),
                ),
              ],
            ),
          ),
        );
      },
    );
  }
}

class _WelfareTab extends StatelessWidget {
  const _WelfareTab({required this.welfare});

  final List<Map<String, dynamic>> welfare;

  @override
  Widget build(BuildContext context) {
    if (welfare.isEmpty) {
      return const EmptyView(
        message: 'No wellbeing assessments recorded yet.',
        icon: Icons.favorite_outline,
      );
    }

    return ListView.builder(
      padding: const EdgeInsets.all(16),
      itemCount: welfare.length,
      itemBuilder: (context, i) {
        final entry = welfare[i];
        return Card(
          child: Padding(
            padding: const EdgeInsets.all(16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    Text(
                      Json.dateLabel(entry['assessment_date']),
                      style: const TextStyle(fontWeight: FontWeight.w700),
                    ),
                    StatusChip(
                      label: Json.string(entry['wellbeing'], '—'),
                      color: Theme.of(context).colorScheme.primary,
                    ),
                  ],
                ),
                const Divider(),
                DetailRow(
                  label: 'Confidence',
                  value: '${Json.intOr(entry['confidence'])}/10',
                ),
                DetailRow(
                  label: 'Focus',
                  value: '${Json.intOr(entry['focus'])}/10',
                ),
                DetailRow(
                  label: 'Motivation',
                  value: '${Json.intOr(entry['motivation'])}/10',
                ),
                DetailRow(
                  label: 'Stress',
                  value: '${Json.intOr(entry['stress'])}/10',
                ),
                if (Json.string(entry['notes']).isNotEmpty) ...[
                  const Divider(),
                  Text(Json.string(entry['notes'])),
                ],
              ],
            ),
          ),
        );
      },
    );
  }
}
