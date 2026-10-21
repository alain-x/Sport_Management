import 'package:flutter/material.dart';

import '../models/json.dart';
import '../services/api_exception.dart';
import '../state/session_store.dart';
import '../widgets/common.dart';
import 'match_detail_screen.dart';
import 'player_detail_screen.dart';
import 'team_detail_screen.dart';

/// Role-aware home screen mirroring the web dashboard.
class DashboardScreen extends StatefulWidget {
  const DashboardScreen({super.key, required this.session});

  final SessionStore session;

  @override
  State<DashboardScreen> createState() => _DashboardScreenState();
}

class _DashboardScreenState extends State<DashboardScreen> {
  Future<Map<String, dynamic>>? _future;

  @override
  void initState() {
    super.initState();
    _load();
  }

  void _load() {
    setState(() => _future = widget.session.client.dashboard());
  }

  @override
  Widget build(BuildContext context) {
    return FutureBuilder<Map<String, dynamic>>(
      future: _future,
      builder: (context, snapshot) {
        if (snapshot.connectionState == ConnectionState.waiting) {
          return const LoadingView(message: 'Loading your dashboard…');
        }
        if (snapshot.hasError) {
          final error = snapshot.error;
          final message =
              error is ApiException ? error.message : 'Could not load data.';
          return ErrorRetryView(message: message, onRetry: _load);
        }

        final data = snapshot.data ?? const {};
        final role = Json.string(data['role']);

        return RefreshIndicator(
          onRefresh: () async => _load(),
          child: ListView(
            padding: const EdgeInsets.all(16),
            children: [
              _WelcomeCard(session: widget.session),
              const SizedBox(height: 16),
              if (role == 'admin') _buildAdmin(data),
              if (role == 'manager') _buildManager(data),
              if (role == 'player') _buildPlayer(data),
            ],
          ),
        );
      },
    );
  }

  // ----- Admin -----

  Widget _buildAdmin(Map<String, dynamic> data) {
    final stats = Json.map(data['stats']);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Row(
          children: [
            _StatCard(
              label: 'Teams',
              value: Json.intOr(stats['teams']),
              icon: Icons.groups,
            ),
            const SizedBox(width: 12),
            _StatCard(
              label: 'Players',
              value: Json.intOr(stats['players']),
              icon: Icons.person,
            ),
          ],
        ),
        const SizedBox(height: 12),
        Row(
          children: [
            _StatCard(
              label: 'Tournaments',
              value: Json.intOr(stats['tournaments']),
              icon: Icons.emoji_events,
            ),
            const SizedBox(width: 12),
            _StatCard(
              label: 'Matches',
              value: Json.intOr(stats['matches']),
              icon: Icons.sports_soccer,
            ),
          ],
        ),
        _matchSection('Recent results', data['recent_matches'], empty: true),
        _matchSection('Upcoming fixtures', data['upcoming_matches']),
        _scorerSection('Top scorers', data['top_scorers']),
        _eventSection('Upcoming events', data['upcoming_events']),
      ],
    );
  }

  // ----- Manager -----

  Widget _buildManager(Map<String, dynamic> data) {
    final team = Json.map(data['team']);
    final teamId = Json.intOrNull(team['id']);

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        if (teamId != null)
          _TeamHeaderCard(session: widget.session, team: team),
        const SizedBox(height: 12),
        Row(
          children: [
            _StatCard(
              label: 'Squad',
              value: Json.intOr(team['players_count']),
              icon: Icons.person,
            ),
            const SizedBox(width: 12),
            _StatCard(
              label: 'Staff',
              value: Json.intOr(data['staff_count']),
              icon: Icons.badge,
            ),
          ],
        ),
        _matchSection('Recent results', data['recent_matches']),
        _matchSection('Upcoming fixtures', data['upcoming_matches']),
        _scorerSection('Top scorers', data['top_scorers']),
        _eventSection('Upcoming events', data['upcoming_events']),
      ],
    );
  }

  // ----- Player -----

  Widget _buildPlayer(Map<String, dynamic> data) {
    final player = Json.map(data['player']);
    final summary = Json.map(data['attendance_summary']);
    final metric = data['latest_metric'];

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Card(
          child: ListTile(
            leading: CircleAvatar(
              child: Text(Json.string(player['jersey_number'], '—')),
            ),
            title: Text(
              '${Json.string(player['first_name'])} ${Json.string(player['last_name'])}'
                  .trim(),
              style: const TextStyle(fontWeight: FontWeight.w600),
            ),
            subtitle: Text(Json.string(player['position'], 'Player')),
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
        ),
        const SizedBox(height: 12),
        Row(
          children: [
            _StatCard(
              label: 'Present',
              value: Json.intOr(summary['present']),
              icon: Icons.check_circle_outline,
            ),
            const SizedBox(width: 12),
            _StatCard(
              label: 'Absent',
              value: Json.intOr(summary['absent']),
              icon: Icons.cancel_outlined,
            ),
          ],
        ),
        if (metric != null) ...[
          const SizedBox(height: 16),
          _LatestMetricCard(metric: Json.map(metric)),
        ],
        _eventSection('Your upcoming sessions', data['upcoming']),
      ],
    );
  }

  // ----- Shared sections -----

  Widget _matchSection(String title, dynamic raw, {bool empty = false}) {
    final matches = Json.list(raw);
    return _Section(
      title: title,
      child: matches.isEmpty
          ? _EmptyLine(empty ? 'Nothing here yet.' : 'No matches to show.')
          : Column(
              children: [
                for (final match in matches)
                  _MatchTile(session: widget.session, match: match),
              ],
            ),
    );
  }

  Widget _scorerSection(String title, dynamic raw) {
    final scorers = Json.list(raw);
    return _Section(
      title: title,
      child: scorers.isEmpty
          ? const _EmptyLine('No goals recorded yet.')
          : Column(
              children: [
                for (var i = 0; i < scorers.length; i++)
                  _ScorerTile(rank: i + 1, player: scorers[i]),
              ],
            ),
    );
  }

  Widget _eventSection(String title, dynamic raw) {
    final events = Json.list(raw);
    return _Section(
      title: title,
      child: events.isEmpty
          ? const _EmptyLine('No upcoming events.')
          : Column(
              children: [
                for (final event in events) _EventTile(event: event),
              ],
            ),
    );
  }
}

class _WelcomeCard extends StatelessWidget {
  const _WelcomeCard({required this.session});

  final SessionStore session;

  @override
  Widget build(BuildContext context) {
    final user = session.user;
    final scheme = Theme.of(context).colorScheme;

    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: scheme.primaryContainer.withValues(alpha: 0.5),
        borderRadius: BorderRadius.circular(16),
      ),
      child: Row(
        children: [
          CircleAvatar(
            radius: 26,
            backgroundColor: scheme.primary,
            child: Text(
              user?.initials ?? '?',
              style: const TextStyle(
                color: Colors.white,
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
                  'Welcome back, ${user?.name ?? 'there'}',
                  style: const TextStyle(
                    fontWeight: FontWeight.w700,
                    fontSize: 16,
                  ),
                ),
                const SizedBox(height: 2),
                Text(
                  [
                    user?.roleLabel,
                    if (user?.teamName != null) user!.teamName,
                  ].whereType<String>().join(' · '),
                  style: TextStyle(color: scheme.outline),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _TeamHeaderCard extends StatelessWidget {
  const _TeamHeaderCard({required this.session, required this.team});

  final SessionStore session;
  final Map<String, dynamic> team;

  @override
  Widget build(BuildContext context) {
    return Card(
      child: ListTile(
        leading: const CircleAvatar(child: Icon(Icons.groups)),
        title: Text(
          Json.string(team['name'], 'Your team'),
          style: const TextStyle(fontWeight: FontWeight.w600),
        ),
        subtitle: Text(
          [Json.string(team['city']), Json.string(team['coach'])]
              .where((s) => s.isNotEmpty)
              .join(' · '),
        ),
        trailing: const Icon(Icons.chevron_right),
        onTap: () {
          final id = Json.intOrNull(team['id']);
          if (id == null) return;
          Navigator.of(context).push(
            MaterialPageRoute(
              builder: (_) => TeamDetailScreen(session: session, teamId: id),
            ),
          );
        },
      ),
    );
  }
}

class _StatCard extends StatelessWidget {
  const _StatCard({
    required this.label,
    required this.value,
    required this.icon,
  });

  final String label;
  final int value;
  final IconData icon;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    return Expanded(
      child: Card(
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Row(
            children: [
              Icon(icon, color: scheme.primary),
              const SizedBox(width: 12),
              Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    '$value',
                    style: const TextStyle(
                      fontSize: 22,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                  Text(label, style: TextStyle(color: scheme.outline)),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _LatestMetricCard extends StatelessWidget {
  const _LatestMetricCard({required this.metric});

  final Map<String, dynamic> metric;

  @override
  Widget build(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text(
              'Latest fitness check',
              style: TextStyle(fontWeight: FontWeight.w700),
            ),
            const SizedBox(height: 8),
            DetailRow(
              label: 'Weight',
              value: '${Json.string(metric['weight_kg'], '—')} kg',
            ),
            DetailRow(
              label: 'Body fat',
              value: '${Json.string(metric['body_fat'], '—')} %',
            ),
            DetailRow(
              label: 'Recorded',
              value: Json.dateLabel(metric['recorded_at']),
            ),
          ],
        ),
      ),
    );
  }
}

class _Section extends StatelessWidget {
  const _Section({required this.title, required this.child});

  final String title;
  final Widget child;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(top: 20),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Padding(
            padding: const EdgeInsets.only(bottom: 8, left: 4),
            child: Text(
              title,
              style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 15),
            ),
          ),
          Card(child: child),
        ],
      ),
    );
  }
}

class _EmptyLine extends StatelessWidget {
  const _EmptyLine(this.text);

  final String text;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.all(16),
      child: Text(
        text,
        style: TextStyle(color: Theme.of(context).colorScheme.outline),
      ),
    );
  }
}

class _MatchTile extends StatelessWidget {
  const _MatchTile({required this.session, required this.match});

  final SessionStore session;
  final Map<String, dynamic> match;

  @override
  Widget build(BuildContext context) {
    final status = Json.string(match['status'], 'scheduled');
    final home = Json.string(match['home_team_name'],
        Json.string(match['home_team'], 'Home'));
    final away = Json.string(match['away_team_name'],
        Json.string(match['away_team'], 'Away'));

    return ListTile(
      title: Text('$home  vs  $away'),
      subtitle: Text(Json.dateTimeLabel(match['match_date'])),
      trailing: status == 'scheduled'
          ? StatusChip(
              label: 'Scheduled',
              color: StatusChip.colorFor(status, Theme.of(context).colorScheme),
            )
          : Text(
              '${Json.intOr(match['home_score'])} - ${Json.intOr(match['away_score'])}',
              style: const TextStyle(
                fontWeight: FontWeight.w700,
                fontSize: 18,
              ),
            ),
      onTap: () {
        final id = Json.intOrNull(match['id']);
        if (id == null) return;
        Navigator.of(context).push(
          MaterialPageRoute(
            builder: (_) => MatchDetailScreen(session: session, matchId: id),
          ),
        );
      },
    );
  }
}

class _ScorerTile extends StatelessWidget {
  const _ScorerTile({required this.rank, required this.player});

  final int rank;
  final Map<String, dynamic> player;

  @override
  Widget build(BuildContext context) {
    final name = '${Json.string(player['first_name'])} ${Json.string(player['last_name'])}'
        .trim();
    return ListTile(
      leading: CircleAvatar(
        radius: 16,
        child: Text('$rank', style: const TextStyle(fontSize: 12)),
      ),
      title: Text(name.isEmpty ? 'Unknown player' : name),
      subtitle: Text(Json.string(player['team_name'], '—')),
      trailing: Text(
        '${Json.intOr(player['goals'])} goals',
        style: const TextStyle(fontWeight: FontWeight.w600),
      ),
    );
  }
}

class _EventTile extends StatelessWidget {
  const _EventTile({required this.event});

  final Map<String, dynamic> event;

  @override
  Widget build(BuildContext context) {
    final type = Json.string(event['type'], 'event');
    return ListTile(
      leading: Icon(
        type == 'training' ? Icons.fitness_center : Icons.event,
      ),
      title: Text(Json.string(event['title'], 'Event')),
      subtitle: Text(Json.dateTimeLabel(event['starts_at'])),
      trailing: StatusChip(
        label: type,
        color: Theme.of(context).colorScheme.primary,
      ),
    );
  }
}
