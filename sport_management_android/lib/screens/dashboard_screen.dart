import 'package:flutter/material.dart';

import '../config.dart';
import '../models/json.dart';
import '../services/api_exception.dart';
import '../services/attendance_export.dart';
import '../state/session_store.dart';
import '../widgets/common.dart';
import 'event_detail_screen.dart';
import 'match_detail_screen.dart';
import 'player_detail_screen.dart';
import 'team_detail_screen.dart';

/// Role-aware home screen mirroring the web dashboard.
///
/// * Administrators get league-wide totals, attendance and check-ins.
/// * Managers get their own squad's attendance, trend and performance plus an
///   Excel export - the API already limits every query to their team.
/// * Players get their own attendance rate, timeline and upcoming sessions.
class DashboardScreen extends StatefulWidget {
  const DashboardScreen({super.key, required this.session});

  final SessionStore session;

  @override
  State<DashboardScreen> createState() => _DashboardScreenState();
}

class _DashboardScreenState extends State<DashboardScreen> {
  late Future<Map<String, dynamic>> _future;
  Map<String, dynamic>? _data;
  bool _exporting = false;

  @override
  void initState() {
    super.initState();
    _future = _fetch();
  }

  Future<Map<String, dynamic>> _fetch() async {
    final data = await widget.session.client.dashboard();
    _data = data;
    return data;
  }

  Future<void> _refresh() async {
    final future = _fetch();
    setState(() {
      _future = future;
    });
    try {
      await future;
    } catch (_) {
      // FutureBuilder renders the error state below.
    }
  }

  Future<void> _openScreen(Widget screen) async {
    await Navigator.of(context).push(
      MaterialPageRoute(builder: (_) => screen),
    );
    if (mounted) await _refresh();
  }

  Future<void> _exportSquad() async {
    final data = _data;
    if (data == null) return;

    final messenger = ScaffoldMessenger.of(context);
    setState(() => _exporting = true);
    try {
      await AttendanceExport.squadAttendance(
        teamName: Json.string(Json.map(data['team'])['name'], 'Team'),
        summary: Json.map(data['attendance']),
        perPlayer: Json.list(data['attendance_per_player']),
      );
      if (!mounted) return;
      setState(() => _exporting = false);
      messenger.showSnackBar(
        const SnackBar(content: Text('Squad attendance ready to share.')),
      );
    } catch (error) {
      if (!mounted) return;
      setState(() => _exporting = false);
      messenger.showSnackBar(
        SnackBar(content: Text('Could not export attendance: $error')),
      );
    }
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
          return ErrorRetryView(message: message, onRetry: _refresh);
        }

        final data = snapshot.data ?? const <String, dynamic>{};
        final role = Json.string(data['role']);

        return RefreshIndicator(
          onRefresh: _refresh,
          child: ListView(
            physics: const AlwaysScrollableScrollPhysics(),
            padding: const EdgeInsets.fromLTRB(16, 16, 16, 32),
            children: [
              _WelcomeHeader(session: widget.session),
              if (role == 'admin') ..._adminSections(data),
              if (role == 'manager') ..._managerSections(data),
              if (role == 'player') ..._playerSections(data),
            ],
          ),
        );
      },
    );
  }

  // -----------------------------------------------------------------
  // Admin
  // -----------------------------------------------------------------

  List<Widget> _adminSections(Map<String, dynamic> data) {
    final stats = Json.map(data['stats']);

    return [
      const SizedBox(height: 16),
      _KpiGrid(
        children: [
          _KpiCard(
            label: 'Teams',
            value: '${Json.intOr(stats['teams'])}',
            icon: Icons.groups_2_outlined,
            color: const Color(0xFF0F766E),
          ),
          _KpiCard(
            label: 'Players',
            value: '${Json.intOr(stats['players'])}',
            icon: Icons.person_outline,
            color: const Color(0xFF4338CA),
          ),
          _KpiCard(
            label: 'Tournaments',
            value: '${Json.intOr(stats['tournaments'])}',
            icon: Icons.emoji_events_outlined,
            color: const Color(0xFFB45309),
          ),
          _KpiCard(
            label: 'Matches',
            value: '${Json.intOr(stats['matches'])}',
            icon: Icons.sports_soccer,
            color: const Color(0xFFBE185D),
          ),
        ],
      ),
      _Panel(
        title: 'League attendance',
        subtitle: 'Every team, every session',
        child: _AttendanceSummary(
          summary: Json.map(data['attendance']),
          extra: [
            (
              'Players tracked',
              '${Json.intOr(Json.map(data['attendance'])['players'])}'
            ),
            (
              'Sessions',
              '${Json.intOr(Json.map(data['attendance'])['sessions'])}'
            ),
          ],
        ),
      ),
      _Panel(
        title: 'Recent check-ins',
        child: _RecentCheckIns(
          rows: Json.list(data['recent_check_ins']),
          onOpen: _openEvent,
        ),
      ),
      _Panel(
        title: 'Upcoming events',
        child: _EventsList(
          events: Json.list(data['upcoming_events']),
          onOpen: _openEvent,
        ),
      ),
      _Panel(
        title: 'Recent results',
        child: _MatchList(
          matches: Json.list(data['recent_matches']),
          empty: 'No finished matches yet.',
          onOpen: _openMatch,
        ),
      ),
      _Panel(
        title: 'Upcoming fixtures',
        child: _MatchList(
          matches: Json.list(data['upcoming_matches']),
          empty: 'No scheduled matches.',
          onOpen: _openMatch,
        ),
      ),
      _Panel(
        title: 'Top scorers',
        child: _ScorerList(
          players: Json.list(data['top_scorers']),
          onOpen: _openPlayer,
        ),
      ),
    ];
  }

  // -----------------------------------------------------------------
  // Manager
  // -----------------------------------------------------------------

  List<Widget> _managerSections(Map<String, dynamic> data) {
    final team = Json.map(data['team']);
    final attendance = Json.map(data['attendance']);
    final performance = Json.map(data['performance']);
    final perPlayer = Json.list(data['attendance_per_player']);
    final alerts = Json.list(data['alerts']);

    return [
      const SizedBox(height: 16),
      if (team['id'] != null)
        _TeamBanner(
          team: team,
          onTap: () {
            final id = Json.intOrNull(team['id']);
            if (id == null) return;
            _openScreen(TeamDetailScreen(session: widget.session, teamId: id));
          },
        ),
      if (team['id'] != null) const SizedBox(height: 12),
      _KpiGrid(
        children: [
          _KpiCard(
            label: 'Squad',
            value: '${Json.intOr(team['players_count'])}',
            icon: Icons.groups_2_outlined,
            color: const Color(0xFF0F766E),
          ),
          _KpiCard(
            label: 'Staff',
            value: '${Json.intOr(data['staff_count'])}',
            icon: Icons.badge_outlined,
            color: const Color(0xFF4338CA),
          ),
          _KpiCard(
            label: 'Attendance',
            value: '${Json.intOr(attendance['rate'])}%',
            icon: Icons.fact_check_outlined,
            color: _rateColor(Json.intOr(attendance['rate'])),
          ),
          _KpiCard(
            label: 'Sessions marked',
            value: '${Json.intOr(attendance['marked'])}',
            icon: Icons.event_available_outlined,
            color: const Color(0xFFB45309),
          ),
        ],
      ),
      _Panel(
        title: 'Squad attendance',
        subtitle: 'Present + late count as attended',
        action: IconButton(
          tooltip: 'Export squad attendance (Excel)',
          onPressed: _exporting ? null : _exportSquad,
          icon: _exporting
              ? const SizedBox(
                  width: 18,
                  height: 18,
                  child: CircularProgressIndicator(strokeWidth: 2),
                )
              : const Icon(Icons.ios_share),
        ),
        child: _AttendanceSummary(summary: attendance),
      ),
      _Panel(
        title: 'Six-month trend',
        child: _TrendChart(trend: Json.list(data['attendance_trend'])),
      ),
      _Panel(
        title: 'Attendance by player',
        subtitle: 'Tap a player to open their full record',
        action: IconButton(
          tooltip: 'Export squad attendance (Excel)',
          onPressed: _exporting ? null : _exportSquad,
          icon: const Icon(Icons.table_view_outlined),
        ),
        child: _PlayerAttendanceList(
          players: perPlayer,
          onOpen: _openPlayer,
        ),
      ),
      _Panel(
        title: 'Recent check-ins',
        child: _RecentCheckIns(
          rows: Json.list(data['recent_check_ins']),
          onOpen: _openEvent,
        ),
      ),
      _Panel(
        title: 'Performance',
        child: _PerformanceSummary(performance: performance),
      ),
      _Panel(
        title: 'Top scorers',
        child: _ScorerList(
          players: Json.list(performance['top_scorers']),
          onOpen: _openPlayer,
        ),
      ),
      _Panel(
        title: 'Squad fitness',
        child: _FitnessStrip(
          fitness: Json.map(performance['fitness']),
        ),
      ),
      if (alerts.isNotEmpty)
        _Panel(
          title: 'Medical & wellbeing alerts',
          subtitle: 'Latest test or welfare assessment needs attention',
          child: _AlertsList(rows: alerts, onOpen: _openPlayer),
        ),
      _Panel(
        title: 'Upcoming events',
        child: _EventsList(
          events: Json.list(data['upcoming_events']),
          onOpen: _openEvent,
        ),
      ),
      _Panel(
        title: 'Recent results',
        child: _MatchList(
          matches: Json.list(data['recent_matches']),
          empty: 'No finished matches yet.',
          onOpen: _openMatch,
        ),
      ),
      _Panel(
        title: 'Upcoming fixtures',
        child: _MatchList(
          matches: Json.list(data['upcoming_matches']),
          empty: 'No scheduled matches.',
          onOpen: _openMatch,
        ),
      ),
    ];
  }

  // -----------------------------------------------------------------
  // Player
  // -----------------------------------------------------------------

  List<Widget> _playerSections(Map<String, dynamic> data) {
    final player = Json.map(data['player']);
    final team = Json.map(data['team']);
    final summary = Json.map(data['attendance_summary']);
    final performance = Json.map(data['performance']);
    final metric = data['latest_metric'];
    final welfare = data['latest_welfare'];
    final statuses = Json.map(data['attendance_statuses']);
    final rate = Json.intOr(summary['rate']);

    return [
      const SizedBox(height: 16),
      _PlayerBanner(
        player: player,
        team: team,
        rate: rate,
        onTap: () {
          final id = Json.intOrNull(player['id']);
          if (id == null) return;
          _openScreen(PlayerDetailScreen(session: widget.session, playerId: id));
        },
      ),
      const SizedBox(height: 12),
      _KpiGrid(
        children: [
          _KpiCard(
            label: 'Attendance',
            value: '$rate%',
            icon: Icons.fact_check_outlined,
            color: _rateColor(rate),
          ),
          _KpiCard(
            label: 'Goals',
            value: '${Json.intOr(performance['goals'])}',
            icon: Icons.sports_soccer,
            color: const Color(0xFFBE185D),
          ),
          _KpiCard(
            label: 'Assists',
            value: '${Json.intOr(performance['assists'])}',
            icon: Icons.handshake_outlined,
            color: const Color(0xFF4338CA),
          ),
          _KpiCard(
            label: 'Rating',
            value: Json.string(performance['rating'], '—'),
            icon: Icons.star_outline,
            color: const Color(0xFFB45309),
          ),
        ],
      ),
      _Panel(
        title: 'My attendance',
        child: _AttendanceSummary(summary: summary),
      ),
      _Panel(
        title: 'Upcoming sessions',
        subtitle: 'Open a training session to check in with a selfie',
        child: _EventsList(
          events: Json.list(data['upcoming']),
          statuses: statuses,
          onOpen: _openEvent,
        ),
      ),
      _Panel(
        title: 'Attendance history',
        child: _HistoryList(
          rows: Json.list(data['attendance_history']),
          onOpen: _openEvent,
        ),
      ),
      _Panel(
        title: 'My performance',
        child: _PerformanceSummary(performance: performance),
      ),
      if (metric != null)
        _Panel(
          title: 'Latest fitness check',
          child: _LatestMetricCard(metric: Json.map(metric)),
        ),
      if (welfare != null)
        _Panel(
          title: 'Latest wellbeing check',
          child: _LatestWelfareCard(welfare: Json.map(welfare)),
        ),
    ];
  }

  void _openEvent(int eventId) {
    _openScreen(
      EventDetailScreen(session: widget.session, eventId: eventId),
    );
  }

  void _openMatch(int matchId) {
    _openScreen(
      MatchDetailScreen(session: widget.session, matchId: matchId),
    );
  }

  void _openPlayer(int playerId) {
    _openScreen(
      PlayerDetailScreen(session: widget.session, playerId: playerId),
    );
  }

  Color _rateColor(int rate) {
    if (rate >= 75) return const Color(0xFF0F766E);
    if (rate >= 50) return const Color(0xFFB45309);
    return const Color(0xFFB91C1C);
  }
}

// ---------------------------------------------------------------------
// Layout pieces
// ---------------------------------------------------------------------

class _WelcomeHeader extends StatelessWidget {
  const _WelcomeHeader({required this.session});

  final SessionStore session;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    final user = session.user;
    final now = DateTime.now();
    final greeting = now.hour < 12
        ? 'Good morning'
        : now.hour < 17
            ? 'Good afternoon'
            : 'Good evening';
    final subtitle = [
      user?.roleLabel,
      if (user?.teamName != null && user!.teamName!.isNotEmpty) user.teamName,
    ].whereType<String>().join(' · ');

    return Container(
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(20),
        gradient: LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [scheme.primary, Color.lerp(scheme.primary, Colors.black, 0.22)!],
        ),
      ),
      child: Row(
        children: [
          UserAvatar(
            photo: user?.photo,
            initials: user?.initials ?? '?',
            radius: 26,
            backgroundColor: Colors.white.withValues(alpha: 0.18),
            foregroundColor: Colors.white,
          ),
          const SizedBox(width: 14),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  '$greeting, ${_firstName(user?.name)}',
                  style: const TextStyle(
                    color: Colors.white,
                    fontWeight: FontWeight.w700,
                    fontSize: 17,
                  ),
                ),
                const SizedBox(height: 2),
                Text(
                  subtitle,
                  style: TextStyle(
                    color: Colors.white.withValues(alpha: 0.82),
                    fontSize: 13,
                  ),
                ),
                const SizedBox(height: 6),
                Text(
                  _longDate(now),
                  style: TextStyle(
                    color: Colors.white.withValues(alpha: 0.65),
                    fontSize: 12,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _KpiGrid extends StatelessWidget {
  const _KpiGrid({required this.children});

  final List<Widget> children;

  @override
  Widget build(BuildContext context) {
    return GridView.count(
      crossAxisCount: 2,
      mainAxisSpacing: 12,
      crossAxisSpacing: 12,
      childAspectRatio: 2.05,
      shrinkWrap: true,
      physics: const NeverScrollableScrollPhysics(),
      padding: EdgeInsets.zero,
      children: children,
    );
  }
}

class _KpiCard extends StatelessWidget {
  const _KpiCard({
    required this.label,
    required this.value,
    required this.icon,
    required this.color,
  });

  final String label;
  final String value;
  final IconData icon;
  final Color color;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    return Card(
      margin: EdgeInsets.zero,
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
        child: Row(
          children: [
            Container(
              width: 38,
              height: 38,
              decoration: BoxDecoration(
                color: color.withValues(alpha: 0.12),
                borderRadius: BorderRadius.circular(10),
              ),
              child: Icon(icon, color: color, size: 20),
            ),
            const SizedBox(width: 10),
            Expanded(
              child: Column(
                mainAxisAlignment: MainAxisAlignment.center,
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    value,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                      fontSize: 20,
                      height: 1.1,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                  const SizedBox(height: 2),
                  Text(
                    label,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: TextStyle(fontSize: 12, color: scheme.outline),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _Panel extends StatelessWidget {
  const _Panel({
    required this.title,
    required this.child,
    this.subtitle,
    this.action,
  });

  final String title;
  final String? subtitle;
  final Widget? action;
  final Widget child;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    return Padding(
      padding: const EdgeInsets.only(top: 20),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Padding(
            padding: const EdgeInsets.only(left: 4, right: 4, bottom: 8),
            child: Row(
              children: [
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        title,
                        style: const TextStyle(
                          fontWeight: FontWeight.w700,
                          fontSize: 15,
                        ),
                      ),
                      if (subtitle != null && subtitle!.isNotEmpty) ...[
                        const SizedBox(height: 2),
                        Text(
                          subtitle!,
                          style: TextStyle(
                            fontSize: 12,
                            color: scheme.outline,
                          ),
                        ),
                      ],
                    ],
                  ),
                ),
                ?action,
              ],
            ),
          ),
          Card(clipBehavior: Clip.antiAlias, child: child),
        ],
      ),
    );
  }
}

class _TeamBanner extends StatelessWidget {
  const _TeamBanner({required this.team, required this.onTap});

  final Map<String, dynamic> team;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    final code = Json.string(team['code']);
    final detail = [
      Json.string(team['city']),
      Json.string(team['coach']),
    ].where((s) => s.isNotEmpty).join(' · ');

    return Card(
      margin: EdgeInsets.zero,
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
          child: Row(
            children: [
              CircleAvatar(
                radius: 22,
                backgroundColor: scheme.primaryContainer,
                child: Icon(Icons.groups_2, color: scheme.onPrimaryContainer),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Flexible(
                          child: Text(
                            Json.string(team['name'], 'Your team'),
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: const TextStyle(
                              fontWeight: FontWeight.w700,
                              fontSize: 15,
                            ),
                          ),
                        ),
                        if (code.isNotEmpty) ...[
                          const SizedBox(width: 8),
                          Text(
                            code,
                            style: TextStyle(
                              fontSize: 11,
                              fontWeight: FontWeight.w700,
                              color: scheme.outline,
                            ),
                          ),
                        ],
                      ],
                    ),
                    if (detail.isNotEmpty) ...[
                      const SizedBox(height: 2),
                      Text(
                        detail,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: TextStyle(
                          fontSize: 12,
                          color: scheme.outline,
                        ),
                      ),
                    ],
                  ],
                ),
              ),
              const Icon(Icons.chevron_right),
            ],
          ),
        ),
      ),
    );
  }
}

class _PlayerBanner extends StatelessWidget {
  const _PlayerBanner({
    required this.player,
    required this.team,
    required this.rate,
    required this.onTap,
  });

  final Map<String, dynamic> player;
  final Map<String, dynamic> team;
  final int rate;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    final name = _fullName(player);
    final photo = Json.string(player['photo']);
    final subtitle = [
      Json.string(player['position'], 'Player'),
      Json.string(team['name']),
    ].where((s) => s.isNotEmpty).join(' · ');

    return Card(
      margin: EdgeInsets.zero,
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Row(
            children: [
              CircleAvatar(
                radius: 28,
                backgroundColor: scheme.primaryContainer,
                foregroundImage:
                    photo.isEmpty ? null : NetworkImage(AppConfig.uploadUrl(photo)),
                child: photo.isEmpty
                    ? Text(
                        Json.string(player['jersey_number'], '?'),
                        style: TextStyle(
                          color: scheme.onPrimaryContainer,
                          fontWeight: FontWeight.bold,
                        ),
                      )
                    : null,
              ),
              const SizedBox(width: 14),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      name.isEmpty ? 'Your profile' : name,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        fontWeight: FontWeight.w800,
                        fontSize: 16,
                      ),
                    ),
                    const SizedBox(height: 2),
                    Text(
                      subtitle,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: TextStyle(
                        fontSize: 12,
                        color: scheme.outline,
                      ),
                    ),
                    const SizedBox(height: 8),
                    Text(
                      '$rate% attendance',
                      style: TextStyle(
                        fontSize: 12,
                        fontWeight: FontWeight.w700,
                        color: _rateColor(rate),
                      ),
                    ),
                  ],
                ),
              ),
              const Icon(Icons.chevron_right),
            ],
          ),
        ),
      ),
    );
  }
}

// ---------------------------------------------------------------------
// Attendance widgets
// ---------------------------------------------------------------------

class _AttendanceSummary extends StatelessWidget {
  const _AttendanceSummary({required this.summary, this.extra = const []});

  final Map<String, dynamic> summary;
  final List<(String, String)> extra;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    final rate = Json.intOr(summary['rate']);
    final attended = Json.intOr(summary['attended']);
    final marked = Json.intOr(summary['marked']);
    final color = _rateColor(rate);

    return Padding(
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
                        fontSize: 36,
                        height: 1.1,
                        fontWeight: FontWeight.w800,
                        color: color,
                      ),
                    ),
                    Text(
                      '$attended of $marked sessions attended',
                      style: TextStyle(fontSize: 12, color: scheme.outline),
                    ),
                  ],
                ),
              ),
              Container(
                width: 66,
                height: 66,
                decoration: BoxDecoration(
                  color: color.withValues(alpha: 0.10),
                  shape: BoxShape.circle,
                  border: Border.all(color: color.withValues(alpha: 0.25)),
                ),
                child: Column(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    Text(
                      '$marked',
                      style: TextStyle(
                        fontSize: 18,
                        fontWeight: FontWeight.w800,
                        color: color,
                      ),
                    ),
                    Text(
                      'marked',
                      style: TextStyle(fontSize: 10, color: scheme.outline),
                    ),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 14),
          ClipRRect(
            borderRadius: BorderRadius.circular(8),
            child: LinearProgressIndicator(
              value: (rate / 100).clamp(0, 1).toDouble(),
              minHeight: 9,
              backgroundColor: scheme.surfaceContainerHighest,
              color: color,
            ),
          ),
          const SizedBox(height: 16),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              _TallyChip(
                label: 'Present',
                value: Json.intOr(summary['present']),
                color: Colors.green.shade600,
              ),
              _TallyChip(
                label: 'Late',
                value: Json.intOr(summary['late']),
                color: Colors.orange.shade700,
              ),
              _TallyChip(
                label: 'Absent',
                value: Json.intOr(summary['absent']),
                color: Colors.red.shade600,
              ),
              _TallyChip(
                label: 'Excused',
                value: Json.intOr(summary['excused']),
                color: Colors.blueGrey.shade600,
              ),
            ],
          ),
          if (extra.isNotEmpty) ...[
            const Divider(height: 26),
            Row(
              children: [
                for (var i = 0; i < extra.length; i++) ...[
                  if (i > 0) const SizedBox(width: 24),
                  _MiniFact(label: extra[i].$1, value: extra[i].$2),
                ],
              ],
            ),
          ],
        ],
      ),
    );
  }
}

class _TallyChip extends StatelessWidget {
  const _TallyChip({
    required this.label,
    required this.value,
    required this.color,
  });

  final String label;
  final int value;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.10),
        borderRadius: BorderRadius.circular(20),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Container(
            width: 7,
            height: 7,
            decoration: BoxDecoration(color: color, shape: BoxShape.circle),
          ),
          const SizedBox(width: 6),
          Text(
            '$label $value',
            style: TextStyle(
              fontSize: 12,
              fontWeight: FontWeight.w600,
              color: color,
            ),
          ),
        ],
      ),
    );
  }
}

class _MiniFact extends StatelessWidget {
  const _MiniFact({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          value,
          style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w800),
        ),
        Text(label, style: TextStyle(fontSize: 11, color: scheme.outline)),
      ],
    );
  }
}

class _TrendChart extends StatelessWidget {
  const _TrendChart({required this.trend});

  final List<Map<String, dynamic>> trend;

  @override
  Widget build(BuildContext context) {
    final hasData = trend.any((row) => Json.intOr(row['marked']) > 0);

    if (!hasData) {
      return const _EmptyLine('No attendance in the last six months yet.');
    }

    return Padding(
      padding: const EdgeInsets.fromLTRB(14, 18, 14, 14),
      child: SizedBox(
        height: 150,
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            for (final row in trend)
              Expanded(
                child: Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 3),
                  child: _TrendBar(row: row),
                ),
              ),
          ],
        ),
      ),
    );
  }
}

class _TrendBar extends StatelessWidget {
  const _TrendBar({required this.row});

  final Map<String, dynamic> row;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    final rate = Json.intOr(row['rate']);
    final marked = Json.intOr(row['marked']);
    final color = _rateColor(rate);
    final label = Json.string(row['label'], '—');

    return Tooltip(
      message: '$rate% · $marked marked',
      child: Column(
        children: [
          Text(
            marked > 0 ? '$rate%' : '—',
            style: TextStyle(
              fontSize: 10,
              fontWeight: FontWeight.w700,
              color: marked > 0 ? color : scheme.outline,
            ),
          ),
          const SizedBox(height: 4),
          Expanded(
            child: LayoutBuilder(
              builder: (context, constraints) {
                final fraction = marked > 0 ? rate / 100 : 0.02;
                final height =
                    (constraints.maxHeight * fraction).clamp(3.0, constraints.maxHeight);
                return Align(
                  alignment: Alignment.bottomCenter,
                  child: Container(
                    width: 20,
                    height: height.toDouble(),
                    decoration: BoxDecoration(
                      color: marked > 0
                          ? color.withValues(alpha: 0.75)
                          : scheme.surfaceContainerHighest,
                      borderRadius: const BorderRadius.vertical(
                        top: Radius.circular(6),
                      ),
                    ),
                  ),
                );
              },
            ),
          ),
          const SizedBox(height: 6),
          Text(
            label,
            style: TextStyle(fontSize: 11, color: scheme.outline),
          ),
        ],
      ),
    );
  }
}

class _PlayerAttendanceList extends StatelessWidget {
  const _PlayerAttendanceList({
    required this.players,
    required this.onOpen,
  });

  final List<Map<String, dynamic>> players;
  final void Function(int playerId) onOpen;

  @override
  Widget build(BuildContext context) {
    if (players.isEmpty) {
      return const _EmptyLine('No players in this squad yet.');
    }

    return Column(
      children: [
        for (var i = 0; i < players.length; i++) ...[
          if (i > 0) const Divider(height: 1),
          _PlayerAttendanceTile(
            player: players[i],
            onTap: () {
              final id = Json.intOrNull(players[i]['player_id']);
              if (id != null) onOpen(id);
            },
          ),
        ],
      ],
    );
  }
}

class _PlayerAttendanceTile extends StatelessWidget {
  const _PlayerAttendanceTile({required this.player, required this.onTap});

  final Map<String, dynamic> player;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    final rate = Json.intOr(player['rate']);
    final color = _rateColor(rate);
    final name = _fullName(player);

    return ListTile(
      onTap: onTap,
      leading: CircleAvatar(
        radius: 18,
        backgroundColor: scheme.primaryContainer,
        child: Text(
          Json.string(player['jersey_number'], '?'),
          style: TextStyle(
            fontSize: 12,
            fontWeight: FontWeight.w700,
            color: scheme.onPrimaryContainer,
          ),
        ),
      ),
      title: Text(
        name.isEmpty ? 'Unknown player' : name,
        maxLines: 1,
        overflow: TextOverflow.ellipsis,
        style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 14),
      ),
      subtitle: Text(
        '${Json.intOr(player['present'])} present · '
        '${Json.intOr(player['late'])} late · '
        '${Json.intOr(player['absent'])} absent',
        maxLines: 1,
        overflow: TextOverflow.ellipsis,
        style: TextStyle(fontSize: 11, color: scheme.outline),
      ),
      trailing: SizedBox(
        width: 62,
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          crossAxisAlignment: CrossAxisAlignment.end,
          children: [
            Text(
              '$rate%',
              style: TextStyle(
                fontSize: 13,
                fontWeight: FontWeight.w800,
                color: color,
              ),
            ),
            const SizedBox(height: 4),
            ClipRRect(
              borderRadius: BorderRadius.circular(4),
              child: LinearProgressIndicator(
                value: (rate / 100).clamp(0, 1).toDouble(),
                minHeight: 4,
                backgroundColor: scheme.surfaceContainerHighest,
                color: color,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _RecentCheckIns extends StatelessWidget {
  const _RecentCheckIns({required this.rows, required this.onOpen});

  final List<Map<String, dynamic>> rows;
  final void Function(int eventId) onOpen;

  @override
  Widget build(BuildContext context) {
    if (rows.isEmpty) {
      return const _EmptyLine('No check-ins recorded yet.');
    }

    return Column(
      children: [
        for (var i = 0; i < rows.length; i++) ...[
          if (i > 0) const Divider(height: 1),
          _CheckInTile(
            row: rows[i],
            onTap: () => onOpen(Json.intOr(rows[i]['event_id'])),
          ),
        ],
      ],
    );
  }
}

class _CheckInTile extends StatelessWidget {
  const _CheckInTile({required this.row, required this.onTap});

  final Map<String, dynamic> row;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    final status = Json.string(row['status'], 'present');
    final photo = Json.string(row['photo']);
    final name = _fullName(row);
    final checkedInAt = Json.string(row['checked_in_at']);

    return ListTile(
      onTap: onTap,
      leading: CircleAvatar(
        radius: 20,
        backgroundColor: scheme.surfaceContainerHighest,
        foregroundImage:
            photo.isEmpty ? null : NetworkImage(AppConfig.uploadUrl(photo)),
        child: photo.isEmpty
            ? Text(
                _initials(row),
                style: TextStyle(
                  fontSize: 12,
                  fontWeight: FontWeight.w700,
                  color: scheme.onSurfaceVariant,
                ),
              )
            : null,
      ),
      title: Text(
        name.isEmpty ? 'Unknown player' : name,
        maxLines: 1,
        overflow: TextOverflow.ellipsis,
        style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 14),
      ),
      subtitle: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            Json.string(row['event_title'], 'Session'),
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
          ),
          Text(
            checkedInAt.isEmpty
                ? Json.string(row['event_type'], 'session')
                : Json.dateTimeLabel(checkedInAt),
            style: TextStyle(fontSize: 11, color: scheme.outline),
          ),
        ],
      ),
      isThreeLine: true,
      trailing: StatusChip(
        label: _titleCase(status),
        color: StatusChip.colorFor(status, scheme),
      ),
    );
  }
}

class _PerformanceSummary extends StatelessWidget {
  const _PerformanceSummary({required this.performance});

  final Map<String, dynamic> performance;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    final rating = Json.doubleOr(performance['average_rating'] ??
        performance['rating']);

    return Padding(
      padding: const EdgeInsets.all(18),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Wrap(
            spacing: 26,
            runSpacing: 14,
            children: [
              _MiniFact(
                label: 'Goals',
                value: '${Json.intOr(performance['goals'])}',
              ),
              _MiniFact(
                label: 'Assists',
                value: '${Json.intOr(performance['assists'])}',
              ),
              _MiniFact(
                label: 'Appearances',
                value: '${Json.intOr(performance['appearances'])}',
              ),
              if (performance['minutes_played'] != null)
                _MiniFact(
                  label: 'Minutes',
                  value: '${Json.intOr(performance['minutes_played'])}',
                ),
              _MiniFact(
                label: 'Rating',
                value: rating > 0 ? rating.toStringAsFixed(1) : '—',
              ),
            ],
          ),
          if (performance['attendance_rate'] != null) ...[
            const Divider(height: 26),
            Text(
              'Attendance rate: ${Json.intOr(performance['attendance_rate'])}%',
              style: TextStyle(fontSize: 12, color: scheme.outline),
            ),
          ],
        ],
      ),
    );
  }
}

class _FitnessStrip extends StatelessWidget {
  const _FitnessStrip({required this.fitness});

  final Map<String, dynamic> fitness;

  static const _labels = {
    'excellent': 'Excellent',
    'good': 'Good',
    'fair': 'Fair',
    'poor': 'Poor',
    'injured': 'Injured',
    'untested': 'Untested',
  };

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    final entries = _labels.entries
        .where((entry) => Json.intOr(fitness[entry.key]) > 0)
        .toList();

    if (entries.isEmpty) {
      return const _EmptyLine('No fitness tests recorded yet.');
    }

    return Padding(
      padding: const EdgeInsets.all(16),
      child: Wrap(
        spacing: 8,
        runSpacing: 8,
        children: [
          for (final entry in entries)
            _TallyChip(
              label: entry.value,
              value: Json.intOr(fitness[entry.key]),
              color: switch (entry.key) {
                'excellent' => Colors.green.shade700,
                'good' => Colors.teal.shade600,
                'fair' => Colors.orange.shade700,
                'poor' => Colors.deepOrange.shade700,
                'injured' => Colors.red.shade600,
                _ => scheme.outline,
              },
            ),
        ],
      ),
    );
  }
}

class _AlertsList extends StatelessWidget {
  const _AlertsList({required this.rows, required this.onOpen});

  final List<Map<String, dynamic>> rows;
  final void Function(int playerId) onOpen;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;

    return Column(
      children: [
        for (var i = 0; i < rows.length; i++) ...[
          if (i > 0) const Divider(height: 1),
          ListTile(
            onTap: () {
              final id = Json.intOrNull(rows[i]['id']);
              if (id != null) onOpen(id);
            },
            leading: CircleAvatar(
              radius: 18,
              backgroundColor: Colors.red.shade50,
              child: Icon(
                Icons.monitor_heart_outlined,
                size: 19,
                color: Colors.red.shade700,
              ),
            ),
            title: Text(
              _fullName(rows[i]),
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 14),
            ),
            subtitle: Text(
              '${_titleCase(Json.string(rows[i]['flag'], 'attention'))} · '
              '${Json.dateLabel(rows[i]['flag_date'])}',
              style: TextStyle(fontSize: 11, color: scheme.outline),
            ),
          ),
        ],
      ],
    );
  }
}

class _HistoryList extends StatelessWidget {
  const _HistoryList({required this.rows, required this.onOpen});

  final List<Map<String, dynamic>> rows;
  final void Function(int eventId) onOpen;

  @override
  Widget build(BuildContext context) {
    if (rows.isEmpty) {
      return const _EmptyLine('No attendance recorded yet.');
    }

    final scheme = Theme.of(context).colorScheme;

    return Column(
      children: [
        for (var i = 0; i < rows.length; i++) ...[
          if (i > 0) const Divider(height: 1),
          ListTile(
            onTap: () => onOpen(Json.intOr(rows[i]['event_id'])),
            leading: Icon(
              _iconForEvent(Json.string(rows[i]['event_type'], 'event')),
              color: scheme.primary,
            ),
            title: Text(
              Json.string(rows[i]['event_title'], 'Session'),
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 14),
            ),
            subtitle: Text(
              [
                Json.dateTimeLabel(rows[i]['event_starts_at']),
                if (Json.string(rows[i]['checked_in_at']).isNotEmpty)
                  'Checked in ${Json.dateTimeLabel(rows[i]['checked_in_at'])}',
              ].join(' · '),
              style: TextStyle(fontSize: 11, color: scheme.outline),
            ),
            trailing: StatusChip(
              label: _titleCase(Json.string(rows[i]['status'], 'present')),
              color: StatusChip.colorFor(
                Json.string(rows[i]['status'], 'present'),
                scheme,
              ),
            ),
          ),
        ],
      ],
    );
  }
}

class _LatestMetricCard extends StatelessWidget {
  const _LatestMetricCard({required this.metric});

  final Map<String, dynamic> metric;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;

    return Padding(
      padding: const EdgeInsets.all(18),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Icon(Icons.monitor_heart_outlined, color: scheme.primary),
              const SizedBox(width: 8),
              Expanded(
                child: Text(
                  _titleCase(Json.string(metric['fitness'], 'not assessed')),
                  style: const TextStyle(
                    fontWeight: FontWeight.w700,
                    fontSize: 15,
                  ),
                ),
              ),
              Text(
                Json.dateLabel(metric['test_date']),
                style: TextStyle(fontSize: 12, color: scheme.outline),
              ),
            ],
          ),
          const SizedBox(height: 10),
          Wrap(
            spacing: 24,
            runSpacing: 12,
            children: [
              _MiniFact(
                label: 'Weight',
                value: '${Json.string(metric['weight_kg'], '—')} kg',
              ),
              _MiniFact(
                label: 'Body fat',
                value: '${Json.string(metric['body_fat'], '—')}%',
              ),
              _MiniFact(
                label: 'Top speed',
                value: '${Json.string(metric['top_speed'], '—')} km/h',
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _LatestWelfareCard extends StatelessWidget {
  const _LatestWelfareCard({required this.welfare});

  final Map<String, dynamic> welfare;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    final status = Json.string(welfare['wellbeing'], 'good');

    return Padding(
      padding: const EdgeInsets.all(18),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Icon(Icons.favorite_outline, color: scheme.primary),
              const SizedBox(width: 8),
              Expanded(
                child: Text(
                  _titleCase(status),
                  style: const TextStyle(
                    fontWeight: FontWeight.w700,
                    fontSize: 15,
                  ),
                ),
              ),
              Text(
                Json.dateLabel(welfare['assessment_date']),
                style: TextStyle(fontSize: 12, color: scheme.outline),
              ),
            ],
          ),
          if (Json.string(welfare['notes']).isNotEmpty) ...[
            const SizedBox(height: 8),
            Text(
              Json.string(welfare['notes']),
              style: TextStyle(fontSize: 13, color: scheme.onSurfaceVariant),
            ),
          ],
        ],
      ),
    );
  }
}

// ---------------------------------------------------------------------
// Lists: events, matches, scorers
// ---------------------------------------------------------------------

class _EventsList extends StatelessWidget {
  const _EventsList({
    required this.events,
    required this.onOpen,
    this.statuses,
  });

  final List<Map<String, dynamic>> events;
  final void Function(int eventId) onOpen;
  final Map<String, dynamic>? statuses;

  @override
  Widget build(BuildContext context) {
    if (events.isEmpty) {
      return const _EmptyLine('No upcoming events.');
    }

    return Column(
      children: [
        for (var i = 0; i < events.length; i++) ...[
          if (i > 0) const Divider(height: 1),
          _EventTile(
            event: events[i],
            status: statuses == null
                ? null
                : Json.string(statuses!['${Json.intOr(events[i]['id'])}']),
            onTap: () => onOpen(Json.intOr(events[i]['id'])),
          ),
        ],
      ],
    );
  }
}

class _EventTile extends StatelessWidget {
  const _EventTile({
    required this.event,
    required this.onTap,
    this.status,
  });

  final Map<String, dynamic> event;
  final VoidCallback onTap;
  final String? status;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    final starts = Json.dateTime(event['starts_at']);
    final type = Json.string(event['type'], 'event');
    final hasStatus = status != null && status!.isNotEmpty;

    return ListTile(
      onTap: onTap,
      leading: Container(
        width: 44,
        padding: const EdgeInsets.symmetric(vertical: 6),
        decoration: BoxDecoration(
          color: scheme.surfaceContainerHighest,
          borderRadius: BorderRadius.circular(10),
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Text(
              starts == null ? '—' : '${starts.day}',
              style: const TextStyle(
                fontSize: 16,
                height: 1.1,
                fontWeight: FontWeight.w800,
              ),
            ),
            Text(
              starts == null ? '' : _monthShort(starts),
              style: TextStyle(
                fontSize: 10,
                height: 1.2,
                fontWeight: FontWeight.w600,
                color: scheme.outline,
              ),
            ),
          ],
        ),
      ),
      title: Text(
        Json.string(event['title'], 'Event'),
        maxLines: 1,
        overflow: TextOverflow.ellipsis,
        style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 14),
      ),
      subtitle: Text(
        [
          if (starts != null) _timeLabel(starts),
          _titleCase(type),
          if (Json.string(event['team_name']).isNotEmpty)
            Json.string(event['team_name']),
        ].join(' · '),
        maxLines: 1,
        overflow: TextOverflow.ellipsis,
        style: TextStyle(fontSize: 11, color: scheme.outline),
      ),
      trailing: hasStatus
          ? StatusChip(
              label: _titleCase(status!),
              color: StatusChip.colorFor(status!, scheme),
            )
          : const Icon(Icons.chevron_right, size: 20),
    );
  }
}

class _MatchList extends StatelessWidget {
  const _MatchList({
    required this.matches,
    required this.empty,
    required this.onOpen,
  });

  final List<Map<String, dynamic>> matches;
  final String empty;
  final void Function(int matchId) onOpen;

  @override
  Widget build(BuildContext context) {
    if (matches.isEmpty) return _EmptyLine(empty);

    return Column(
      children: [
        for (var i = 0; i < matches.length; i++) ...[
          if (i > 0) const Divider(height: 1),
          _MatchTile(
            match: matches[i],
            onTap: () => onOpen(Json.intOr(matches[i]['id'])),
          ),
        ],
      ],
    );
  }
}

class _MatchTile extends StatelessWidget {
  const _MatchTile({required this.match, required this.onTap});

  final Map<String, dynamic> match;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final status = Json.string(match['status'], 'scheduled');
    final home = Json.teamName(match, 'home', fallback: 'Home');
    final away = Json.teamName(match, 'away', fallback: 'Away');
    final homeCode = Json.teamCode(match, 'home');
    final awayCode = Json.teamCode(match, 'away');
    final scheduled = status == 'scheduled';

    return ListTile(
      onTap: onTap,
      title: Row(
        children: [
          if (homeCode.isNotEmpty) _codeBadge(context, homeCode),
          Expanded(
            child: Text(
              '$home  vs  $away',
              style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 14),
              overflow: TextOverflow.ellipsis,
            ),
          ),
          if (awayCode.isNotEmpty) _codeBadge(context, awayCode),
        ],
      ),
      subtitle: Padding(
        padding: const EdgeInsets.only(top: 4),
        child: Text(
          Json.dateTimeLabel(match['match_date']),
          style: theme.textTheme.bodySmall
              ?.copyWith(color: theme.colorScheme.outline),
        ),
      ),
      trailing: scheduled
          ? StatusChip(
              label: 'Scheduled',
              color: StatusChip.colorFor(status, theme.colorScheme),
            )
          : Text(
              '${Json.intOr(match['home_score'])} - '
              '${Json.intOr(match['away_score'])}',
              style: const TextStyle(
                fontWeight: FontWeight.w700,
                fontSize: 18,
              ),
            ),
    );
  }

  Widget _codeBadge(BuildContext context, String code) {
    final scheme = Theme.of(context).colorScheme;
    return Container(
      margin: const EdgeInsets.only(right: 8),
      padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
      decoration: BoxDecoration(
        color: scheme.surfaceContainerHighest,
        borderRadius: BorderRadius.circular(4),
        border: Border.all(color: scheme.outlineVariant),
      ),
      child: Text(
        code,
        style: TextStyle(
          color: scheme.outline,
          fontSize: 11,
          fontWeight: FontWeight.w700,
        ),
      ),
    );
  }
}

class _ScorerList extends StatelessWidget {
  const _ScorerList({required this.players, required this.onOpen});

  final List<Map<String, dynamic>> players;
  final void Function(int playerId) onOpen;

  @override
  Widget build(BuildContext context) {
    if (players.isEmpty) {
      return const _EmptyLine('No goals recorded yet.');
    }

    return Column(
      children: [
        for (var i = 0; i < players.length; i++) ...[
          if (i > 0) const Divider(height: 1),
          ListTile(
            onTap: () {
              final id = Json.intOrNull(players[i]['id']);
              if (id != null) onOpen(id);
            },
            leading: CircleAvatar(
              radius: 16,
              child: Text(
                '${i + 1}',
                style: const TextStyle(
                  fontSize: 12,
                  fontWeight: FontWeight.w700,
                ),
              ),
            ),
            title: Text(
              _fullName(players[i]),
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 14),
            ),
            subtitle: Text(
              Json.string(players[i]['team_name'], '—'),
              style: TextStyle(
                fontSize: 11,
                color: Theme.of(context).colorScheme.outline,
              ),
            ),
            trailing: Text(
              '${Json.intOr(players[i]['goals'])} goals',
              style: const TextStyle(
                fontWeight: FontWeight.w700,
                fontSize: 13,
              ),
            ),
          ),
        ],
      ],
    );
  }
}

class _EmptyLine extends StatelessWidget {
  const _EmptyLine(this.text);

  final String text;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.all(18),
      child: Text(
        text,
        style: TextStyle(color: Theme.of(context).colorScheme.outline),
      ),
    );
  }
}

// ---------------------------------------------------------------------
// Formatting helpers
// ---------------------------------------------------------------------

const _monthsShort = [
  'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun',
  'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec',
];

const _monthsLong = [
  'January', 'February', 'March', 'April', 'May', 'June',
  'July', 'August', 'September', 'October', 'November', 'December',
];

const _weekdaysLong = [
  'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday',
];

String _fullName(Map<String, dynamic> row) =>
    '${Json.string(row['first_name'])} ${Json.string(row['last_name'])}'.trim();

String _initials(Map<String, dynamic> row) {
  final first = Json.string(row['first_name']);
  final last = Json.string(row['last_name']);
  final a = first.isEmpty ? '' : first[0];
  final b = last.isEmpty ? '' : last[0];
  final value = '$a$b'.toUpperCase();
  return value.isEmpty ? '?' : value;
}

String _firstName(String? name) {
  final trimmed = (name ?? '').trim();
  if (trimmed.isEmpty) return 'there';
  return trimmed.split(RegExp(r'\s+')).first;
}

String _titleCase(String value) {
  if (value.isEmpty) return value;
  return value
      .split(RegExp(r'[\s_]+'))
      .where((part) => part.isNotEmpty)
      .map((part) => '${part[0].toUpperCase()}${part.substring(1)}')
      .join(' ');
}

String _monthShort(DateTime date) => _monthsShort[date.month - 1];

String _longDate(DateTime date) =>
    '${_weekdaysLong[date.weekday - 1]}, ${date.day} '
    '${_monthsLong[date.month - 1]} ${date.year}';

String _timeLabel(DateTime date) {
  final h = date.hour.toString().padLeft(2, '0');
  final m = date.minute.toString().padLeft(2, '0');
  return '$h:$m';
}

IconData _iconForEvent(String type) {
  switch (type) {
    case 'training':
      return Icons.fitness_center;
    case 'meeting':
      return Icons.groups;
    case 'travel':
      return Icons.directions_bus_outlined;
    case 'deadline':
      return Icons.flag_outlined;
    case 'media':
      return Icons.campaign_outlined;
    default:
      return Icons.event_outlined;
  }
}

Color _rateColor(int rate) {
  if (rate >= 75) return const Color(0xFF0F766E);
  if (rate >= 50) return const Color(0xFFB45309);
  return const Color(0xFFB91C1C);
}
