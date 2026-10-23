import 'package:flutter/material.dart';

import '../models/json.dart';
import '../services/api_exception.dart';
import '../services/attendance_export.dart';
import '../state/session_store.dart';
import '../widgets/common.dart';

/// Player profile with overview, attendance, performance and (when permitted)
/// private fitness/welfare tabs. Staff can export the full record to Excel.
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
  Map<String, dynamic>? _data;
  bool _exporting = false;

  bool get _canExport =>
      widget.session.user?.isAdmin == true ||
      widget.session.user?.isManager == true ||
      _data?['is_self'] == true;

  @override
  void initState() {
    super.initState();
    _load();
  }

  void _load() {
    _future = widget.session.client
        .getMap('/players/${widget.playerId}')
        .then((data) {
      _data = data;
      return data;
    });
  }

  Future<void> _export() async {
    final data = _data;
    if (data == null) return;

    setState(() => _exporting = true);
    final messenger = ScaffoldMessenger.of(context);
    try {
      await AttendanceExport.playerRecord(
        player: data,
        teamName: Json.string(data['team_name'], 'Team'),
        attendance: Json.list(data['attendance']),
        summary: Json.map(data['attendance_summary']),
      );
      if (!mounted) return;
      setState(() => _exporting = false);
      messenger.showSnackBar(
        const SnackBar(content: Text('Player record ready to share.')),
      );
    } catch (error) {
      if (!mounted) return;
      setState(() => _exporting = false);
      messenger.showSnackBar(
        SnackBar(content: Text('Could not export the record: $error')),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    return DefaultTabController(
      length: 4,
      child: Scaffold(
        appBar: AppBar(
          title: Text(
            _data == null
                ? 'Player'
                : '${Json.string(_data!['first_name'])} '
                        '${Json.string(_data!['last_name'])}'
                    .trim(),
          ),
          actions: [
            if (_canExport)
              IconButton(
                tooltip: 'Export player record (Excel)',
                onPressed: _exporting ? null : _export,
                icon: _exporting
                    ? const SizedBox(
                        width: 18,
                        height: 18,
                        child: CircularProgressIndicator(strokeWidth: 2),
                      )
                    : const Icon(Icons.ios_share),
              ),
          ],
          bottom: const TabBar(
            isScrollable: true,
            tabAlignment: TabAlignment.start,
            tabs: [
              Tab(text: 'Overview'),
              Tab(text: 'Attendance'),
              Tab(text: 'Performance'),
              Tab(text: 'Health'),
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

            final player = snapshot.data ?? const <String, dynamic>{};
            final canSeePrivate = player['can_see_private'] == true;

            return TabBarView(
              children: [
                _OverviewTab(player: player),
                _AttendanceTab(
                  history: Json.list(player['attendance']),
                  summary: Json.map(player['attendance_summary']),
                ),
                _PerformanceTab(
                  player: player,
                  performance: Json.map(player['performance']),
                  summary: Json.map(player['attendance_summary']),
                ),
                _HealthTab(
                  allowed: canSeePrivate,
                  metrics: Json.list(player['metrics']),
                  welfare: Json.list(player['welfare']),
                ),
              ],
            );
          },
        ),
      ),
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
    final summary = Json.map(player['attendance_summary']);
    final rate = Json.intOr(summary['rate']);

    return ListView(
      padding: const EdgeInsets.fromLTRB(16, 12, 16, 24),
      children: [
        Card(
          child: Padding(
            padding: const EdgeInsets.all(18),
            child: Column(
              children: [
                CircleAvatar(
                  radius: 40,
                  backgroundColor: scheme.primaryContainer,
                  child: Text(
                    Json.string(player['jersey_number'], '#'),
                    style: TextStyle(
                      color: scheme.onPrimaryContainer,
                      fontSize: 24,
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                ),
                const SizedBox(height: 12),
                Text(
                  name.isEmpty ? 'Unknown player' : name,
                  textAlign: TextAlign.center,
                  style: const TextStyle(
                    fontSize: 20,
                    fontWeight: FontWeight.w800,
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
                const SizedBox(height: 16),
                Row(
                  children: [
                    _MiniStat(
                      label: 'Attendance',
                      value: '$rate%',
                      icon: Icons.fact_check_outlined,
                    ),
                    const SizedBox(width: 10),
                    _MiniStat(
                      label: 'Goals',
                      value: '${Json.intOr(player['goals'])}',
                      icon: Icons.sports_soccer,
                    ),
                    const SizedBox(width: 10),
                    _MiniStat(
                      label: 'Rating',
                      value: Json.string(player['rating'], '—'),
                      icon: Icons.star_outline,
                    ),
                  ],
                ),
              ],
            ),
          ),
        ),
        const SizedBox(height: 12),
        _InfoCard(
          title: 'Season statistics',
          rows: [
            ('Appearances', '${Json.intOr(player['appearances'])}'),
            ('Goals', '${Json.intOr(player['goals'])}'),
            ('Assists', '${Json.intOr(player['assists'])}'),
            ('Minutes', '${Json.intOr(player['minutes_played'])}'),
            ('Yellow cards', '${Json.intOr(player['yellow_cards'])}'),
            ('Red cards', '${Json.intOr(player['red_cards'])}'),
            ('Rating', Json.string(player['rating'], '—')),
          ],
        ),
        const SizedBox(height: 12),
        _InfoCard(
          title: 'Profile',
          rows: [
            ('Date of birth', Json.dateLabel(player['date_of_birth'])),
            ('Nationality', Json.string(player['nationality'], '—')),
            ('Preferred foot', Json.string(player['foot'], '—')),
            ('Team', Json.string(player['team_name'], '—')),
          ],
        ),
      ],
    );
  }
}

class _AttendanceTab extends StatelessWidget {
  const _AttendanceTab({required this.history, required this.summary});

  final List<Map<String, dynamic>> history;
  final Map<String, dynamic> summary;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    final rate = Json.intOr(summary['rate']);

    return ListView(
      padding: const EdgeInsets.fromLTRB(16, 12, 16, 24),
      children: [
        Card(
          child: Padding(
            padding: const EdgeInsets.all(18),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            'ATTENDANCE RATE',
                            style: TextStyle(
                              fontSize: 11,
                              letterSpacing: 0.8,
                              fontWeight: FontWeight.w700,
                              color: scheme.outline,
                            ),
                          ),
                          const SizedBox(height: 2),
                          Text(
                            '$rate%',
                            style: TextStyle(
                              fontSize: 34,
                              fontWeight: FontWeight.w800,
                              height: 1.1,
                              color: rate >= 75
                                  ? scheme.primary
                                  : Colors.orange.shade700,
                            ),
                          ),
                          Text(
                            '${Json.intOr(summary['attended'])} of '
                            '${Json.intOr(summary['marked'])} sessions attended',
                            style: TextStyle(
                              fontSize: 12,
                              color: scheme.outline,
                            ),
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 12),
                ClipRRect(
                  borderRadius: BorderRadius.circular(8),
                  child: LinearProgressIndicator(
                    value: rate / 100,
                    minHeight: 9,
                    backgroundColor: scheme.surfaceContainerHighest,
                  ),
                ),
                const SizedBox(height: 16),
                Wrap(
                  spacing: 8,
                  runSpacing: 8,
                  children: [
                    _CountBadge(
                      label: 'Present',
                      count: Json.intOr(summary['present']),
                      color: Colors.green.shade600,
                    ),
                    _CountBadge(
                      label: 'Late',
                      count: Json.intOr(summary['late']),
                      color: Colors.orange.shade700,
                    ),
                    _CountBadge(
                      label: 'Absent',
                      count: Json.intOr(summary['absent']),
                      color: Colors.red.shade600,
                    ),
                    _CountBadge(
                      label: 'Excused',
                      count: Json.intOr(summary['excused']),
                      color: Colors.blueGrey.shade600,
                    ),
                  ],
                ),
              ],
            ),
          ),
        ),
        const SizedBox(height: 18),
        Padding(
          padding: const EdgeInsets.only(left: 4, bottom: 8),
          child: Text(
            'Session history',
            style: TextStyle(
              fontWeight: FontWeight.w700,
              fontSize: 15,
              color: scheme.onSurface,
            ),
          ),
        ),
        if (history.isEmpty)
          const Card(
            child: Padding(
              padding: EdgeInsets.all(18),
              child: Text('No attendance recorded yet.'),
            ),
          )
        else
          Card(
            clipBehavior: Clip.antiAlias,
            child: Column(
              children: [
                for (var i = 0; i < history.length; i++) ...[
                  if (i > 0) const Divider(height: 1),
                  _HistoryTile(row: history[i]),
                ],
              ],
            ),
          ),
      ],
    );
  }
}

class _HistoryTile extends StatelessWidget {
  const _HistoryTile({required this.row});

  final Map<String, dynamic> row;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    final status = Json.string(row['status'], 'present');
    final checkedIn = Json.string(row['checked_in_at']);

    return ListTile(
      leading: CircleAvatar(
        radius: 18,
        backgroundColor:
            StatusChip.colorFor(status, scheme).withValues(alpha: 0.12),
        child: Icon(
          _iconFor(status),
          size: 18,
          color: StatusChip.colorFor(status, scheme),
        ),
      ),
      title: Text(
        Json.string(row['event_title'], 'Session'),
        maxLines: 1,
        overflow: TextOverflow.ellipsis,
        style: const TextStyle(fontWeight: FontWeight.w600),
      ),
      subtitle: Text(
        [
          Json.dateTimeLabel(row['event_starts_at']),
          if (checkedIn.isNotEmpty) 'Recorded ${Json.dateTimeLabel(checkedIn)}',
        ].join(' · '),
        style: TextStyle(fontSize: 11, color: scheme.outline),
      ),
      trailing: StatusChip(
        label: status[0].toUpperCase() + status.substring(1),
        color: StatusChip.colorFor(status, scheme),
      ),
    );
  }

  IconData _iconFor(String status) {
    switch (status) {
      case 'late':
        return Icons.schedule;
      case 'absent':
        return Icons.close;
      case 'excused':
        return Icons.info_outline;
      default:
        return Icons.check;
    }
  }
}

class _PerformanceTab extends StatelessWidget {
  const _PerformanceTab({
    required this.player,
    required this.performance,
    required this.summary,
  });

  final Map<String, dynamic> player;
  final Map<String, dynamic> performance;
  final Map<String, dynamic> summary;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;

    return ListView(
      padding: const EdgeInsets.fromLTRB(16, 12, 16, 24),
      children: [
        Card(
          child: Padding(
            padding: const EdgeInsets.all(18),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'SEASON OUTPUT',
                  style: TextStyle(
                    fontSize: 11,
                    letterSpacing: 0.8,
                    fontWeight: FontWeight.w700,
                    color: scheme.outline,
                  ),
                ),
                const SizedBox(height: 12),
                Row(
                  children: [
                    _BigStat(
                      label: 'Goals',
                      value: Json.intOr(performance['goals']),
                    ),
                    _BigStat(
                      label: 'Assists',
                      value: Json.intOr(performance['assists']),
                    ),
                    _BigStat(
                      label: 'Apps',
                      value: Json.intOr(performance['appearances']),
                    ),
                  ],
                ),
                const SizedBox(height: 18),
                Row(
                  children: [
                    _BigStat(
                      label: 'Minutes',
                      value: Json.intOr(performance['minutes_played']),
                    ),
                    _BigStat(
                      label: 'Attendance',
                      value: Json.intOr(performance['attendance_rate']),
                      suffix: '%',
                    ),
                    _BigStat(
                      label: 'Rating',
                      value: Json.doubleOr(performance['rating']),
                      decimal: true,
                    ),
                  ],
                ),
              ],
            ),
          ),
        ),
        const SizedBox(height: 12),
        _InfoCard(
          title: 'Discipline & availability',
          rows: [
            ('Appearances', '${Json.intOr(player['appearances'])}'),
            ('Minutes played', '${Json.intOr(player['minutes_played'])}'),
            ('Yellow cards', '${Json.intOr(player['yellow_cards'])}'),
            ('Red cards', '${Json.intOr(player['red_cards'])}'),
            ('Sessions attended', '${Json.intOr(summary['attended'])}'),
            ('Sessions marked', '${Json.intOr(summary['marked'])}'),
          ],
        ),
      ],
    );
  }
}

class _HealthTab extends StatelessWidget {
  const _HealthTab({
    required this.allowed,
    required this.metrics,
    required this.welfare,
  });

  final bool allowed;
  final List<Map<String, dynamic>> metrics;
  final List<Map<String, dynamic>> welfare;

  @override
  Widget build(BuildContext context) {
    if (!allowed) {
      return const EmptyView(
        message:
            'This information is private and only visible to the player and '
            'team staff.',
        icon: Icons.lock_outline,
      );
    }

    return ListView(
      padding: const EdgeInsets.fromLTRB(16, 12, 16, 24),
      children: [
        if (metrics.isEmpty)
          const Card(
            child: Padding(
              padding: EdgeInsets.all(18),
              child: Text('No fitness tests recorded yet.'),
            ),
          )
        else
          for (final metric in metrics)
            Padding(
              padding: const EdgeInsets.only(bottom: 10),
              child: _FitnessCard(metric: metric),
            ),
        const SizedBox(height: 6),
        Padding(
          padding: const EdgeInsets.only(left: 4, bottom: 8),
          child: const Text(
            'Wellbeing assessments',
            style: TextStyle(fontWeight: FontWeight.w700, fontSize: 15),
          ),
        ),
        if (welfare.isEmpty)
          const Card(
            child: Padding(
              padding: EdgeInsets.all(18),
              child: Text('No wellbeing assessments recorded yet.'),
            ),
          )
        else
          for (final entry in welfare)
            Padding(
              padding: const EdgeInsets.only(bottom: 10),
              child: _WelfareCard(entry: entry),
            ),
      ],
    );
  }
}

class _FitnessCard extends StatelessWidget {
  const _FitnessCard({required this.metric});

  final Map<String, dynamic> metric;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
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
                  color: scheme.primary,
                ),
              ],
            ),
            const Divider(height: 20),
            Wrap(
              spacing: 18,
              runSpacing: 8,
              children: [
                _SmallValue(
                  label: 'Height',
                  value: '${Json.string(metric['height_cm'], '—')} cm',
                ),
                _SmallValue(
                  label: 'Weight',
                  value: '${Json.string(metric['weight_kg'], '—')} kg',
                ),
                _SmallValue(
                  label: 'Body fat',
                  value: '${Json.string(metric['body_fat'], '—')} %',
                ),
                _SmallValue(
                  label: 'Top speed',
                  value: Json.string(metric['top_speed'], '—'),
                ),
                _SmallValue(
                  label: 'Stamina',
                  value: Json.string(metric['stamina'], '—'),
                ),
                _SmallValue(
                  label: 'Strength',
                  value: Json.string(metric['strength'], '—'),
                ),
                _SmallValue(
                  label: 'Agility',
                  value: Json.string(metric['agility'], '—'),
                ),
              ],
            ),
            if (Json.string(metric['notes']).isNotEmpty) ...[
              const SizedBox(height: 10),
              Text(
                Json.string(metric['notes']),
                style: TextStyle(fontSize: 12, color: scheme.outline),
              ),
            ],
          ],
        ),
      ),
    );
  }
}

class _WelfareCard extends StatelessWidget {
  const _WelfareCard({required this.entry});

  final Map<String, dynamic> entry;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
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
                  color: scheme.primary,
                ),
              ],
            ),
            const Divider(height: 20),
            Row(
              children: [
                _SmallValue(
                  label: 'Confidence',
                  value: '${Json.intOr(entry['confidence'])}/10',
                ),
                _SmallValue(
                  label: 'Focus',
                  value: '${Json.intOr(entry['focus'])}/10',
                ),
                _SmallValue(
                  label: 'Motivation',
                  value: '${Json.intOr(entry['motivation'])}/10',
                ),
                _SmallValue(
                  label: 'Stress',
                  value: '${Json.intOr(entry['stress'])}/10',
                ),
              ],
            ),
            if (Json.string(entry['notes']).isNotEmpty) ...[
              const SizedBox(height: 10),
              Text(
                Json.string(entry['notes']),
                style: TextStyle(fontSize: 12, color: scheme.outline),
              ),
            ],
          ],
        ),
      ),
    );
  }
}

class _InfoCard extends StatelessWidget {
  const _InfoCard({required this.title, required this.rows});

  final String title;
  final List<(String, String)> rows;

  @override
  Widget build(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              title,
              style: const TextStyle(fontWeight: FontWeight.w700),
            ),
            const Divider(height: 20),
            for (final row in rows) DetailRow(label: row.$1, value: row.$2),
          ],
        ),
      ),
    );
  }
}

class _MiniStat extends StatelessWidget {
  const _MiniStat({
    required this.label,
    required this.value,
    required this.icon,
  });

  final String label;
  final String value;
  final IconData icon;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    return Expanded(
      child: Container(
        padding: const EdgeInsets.symmetric(vertical: 12, horizontal: 8),
        decoration: BoxDecoration(
          color: scheme.surfaceContainerHighest.withValues(alpha: 0.35),
          borderRadius: BorderRadius.circular(12),
        ),
        child: Column(
          children: [
            Icon(icon, size: 18, color: scheme.primary),
            const SizedBox(height: 6),
            Text(
              value,
              style: const TextStyle(
                fontWeight: FontWeight.w800,
                fontSize: 16,
              ),
            ),
            Text(
              label,
              style: TextStyle(fontSize: 10, color: scheme.outline),
            ),
          ],
        ),
      ),
    );
  }
}

class _BigStat extends StatelessWidget {
  const _BigStat({
    required this.label,
    required this.value,
    this.suffix = '',
    this.decimal = false,
  });

  final String label;
  final num value;
  final String suffix;
  final bool decimal;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    final text = decimal
        ? (value == 0 ? '—' : value.toStringAsFixed(2))
        : '$value';

    return Expanded(
      child: Column(
        children: [
          Text(
            '$text$suffix',
            style: const TextStyle(
              fontSize: 26,
              fontWeight: FontWeight.w800,
              height: 1.1,
            ),
          ),
          const SizedBox(height: 2),
          Text(
            label,
            style: TextStyle(fontSize: 11, color: scheme.outline),
          ),
        ],
      ),
    );
  }
}

class _SmallValue extends StatelessWidget {
  const _SmallValue({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          label,
          style: TextStyle(fontSize: 10, color: scheme.outline),
        ),
        Text(
          value,
          style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 13),
        ),
      ],
    );
  }
}

class _CountBadge extends StatelessWidget {
  const _CountBadge({
    required this.label,
    required this.count,
    required this.color,
  });

  final String label;
  final int count;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.10),
        borderRadius: BorderRadius.circular(10),
        border: Border.all(color: color.withValues(alpha: 0.25)),
      ),
      child: Text(
        '$label $count',
        style: TextStyle(
          color: color,
          fontSize: 12,
          fontWeight: FontWeight.w700,
        ),
      ),
    );
  }
}
