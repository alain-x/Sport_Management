import 'package:flutter/material.dart';

import '../models/json.dart';
import '../services/api_exception.dart';
import '../state/session_store.dart';
import '../widgets/common.dart';
import 'match_detail_screen.dart';

/// Fixtures and results, grouped into live / upcoming / recent scoreboards.
class MatchesScreen extends StatefulWidget {
  const MatchesScreen({super.key, required this.session});

  final SessionStore session;

  @override
  State<MatchesScreen> createState() => _MatchesScreenState();
}

class _MatchesScreenState extends State<MatchesScreen> {
  static const _filters = <String, String?>{
    'All': null,
    'Upcoming': 'scheduled',
    'Live': 'live',
    'Results': 'finished',
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
    final ownTeamId = widget.session.user?.teamId;

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
                  child: FilterChip(
                    label: Text(key),
                    selected: _active == key,
                    showCheckmark: false,
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
                return RefreshIndicator(
                  onRefresh: () async => setState(_load),
                  child: ListView(
                    children: const [
                      SizedBox(height: 80),
                      EmptyView(
                        message: 'No matches to show for this filter.',
                        icon: Icons.sports_soccer_outlined,
                      ),
                    ],
                  ),
                );
              }

              final sections = _sections(matches);
              final rows = <_MatchRow>[];
              for (final section in sections) {
                rows.add(_MatchRow.header(section));
                for (final match in section.matches) {
                  rows.add(_MatchRow.match(match));
                }
              }

              return RefreshIndicator(
                onRefresh: () async => setState(_load),
                child: ListView.builder(
                  padding: const EdgeInsets.fromLTRB(12, 4, 12, 24),
                  itemCount: rows.length,
                  itemBuilder: (context, i) {
                    final row = rows[i];
                    if (row.section != null) {
                      return _SectionHeader(section: row.section!);
                    }
                    final match = row.match!;
                    return Padding(
                      padding: const EdgeInsets.only(bottom: 10),
                      child: _MatchCard(
                        session: widget.session,
                        match: match,
                        ownTeamId: ownTeamId,
                      ),
                    );
                  },
                ),
              );
            },
          ),
        ),
      ],
    );
  }

  /// Splits the payload into the sections that suit the selected filter.
  List<_Section> _sections(List<Map<String, dynamic>> matches) {
    List<Map<String, dynamic>> withStatus(String status) => matches
        .where((m) => Json.string(m['status'], 'scheduled') == status)
        .toList();

    List<Map<String, dynamic>> byDate(
      List<Map<String, dynamic>> items, {
      required bool ascending,
    }) {
      final list = [...items];
      list.sort((a, b) {
        final ad = Json.dateTime(a['match_date']);
        final bd = Json.dateTime(b['match_date']);
        if (ad == null && bd == null) return 0;
        if (ad == null) return 1;
        if (bd == null) return -1;
        return ascending ? ad.compareTo(bd) : bd.compareTo(ad);
      });
      return list;
    }

    final live = withStatus('live');
    final upcoming = byDate(withStatus('scheduled'), ascending: true);
    final results = byDate(withStatus('finished'), ascending: false);

    if (_active == 'Live') {
      return [_Section('Live now', Icons.podcasts, live)];
    }
    if (_active == 'Upcoming') {
      return [_Section('Upcoming fixtures', Icons.event_available_outlined, upcoming)];
    }
    if (_active == 'Results') {
      return [_Section('Recent results', Icons.emoji_events_outlined, results)];
    }

    return [
      if (live.isNotEmpty) _Section('Live now', Icons.podcasts, live),
      if (upcoming.isNotEmpty)
        _Section('Upcoming fixtures', Icons.event_available_outlined, upcoming),
      if (results.isNotEmpty)
        _Section('Recent results', Icons.emoji_events_outlined, results),
    ];
  }
}

class _Section {
  const _Section(this.title, this.icon, this.matches);

  final String title;
  final IconData icon;
  final List<Map<String, dynamic>> matches;
}

class _MatchRow {
  _MatchRow.header(this.section) : match = null;
  _MatchRow.match(this.match) : section = null;

  final _Section? section;
  final Map<String, dynamic>? match;
}

class _SectionHeader extends StatelessWidget {
  const _SectionHeader({required this.section});

  final _Section section;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    final isLive = section.title == 'Live now';
    final color = isLive ? Colors.red.shade600 : scheme.outline;

    return Padding(
      padding: const EdgeInsets.fromLTRB(4, 12, 4, 10),
      child: Row(
        children: [
          Icon(section.icon, size: 15, color: color),
          const SizedBox(width: 6),
          Text(
            section.title.toUpperCase(),
            style: TextStyle(
              fontSize: 11,
              letterSpacing: 0.8,
              fontWeight: FontWeight.w700,
              color: color,
            ),
          ),
          const SizedBox(width: 8),
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 1),
            decoration: BoxDecoration(
              color: color.withValues(alpha: 0.12),
              borderRadius: BorderRadius.circular(10),
            ),
            child: Text(
              '${section.matches.length}',
              style: TextStyle(
                fontSize: 10,
                fontWeight: FontWeight.w700,
                color: color,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _MatchCard extends StatelessWidget {
  const _MatchCard({
    required this.session,
    required this.match,
    required this.ownTeamId,
  });

  final SessionStore session;
  final Map<String, dynamic> match;
  final int? ownTeamId;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    final status = Json.string(match['status'], 'scheduled');
    final statusColor = StatusChip.colorFor(status, scheme);
    final homeId = Json.intOrNull(match['home_team_id']);
    final awayId = Json.intOrNull(match['away_team_id']);
    final involvesOwn =
        ownTeamId != null && (homeId == ownTeamId || awayId == ownTeamId);

    final date = Json.dateLabel(match['match_date']);
    final weekday = _weekday(Json.dateTime(match['match_date']));
    final venue = Json.string(match['venue']);

    return Card(
      clipBehavior: Clip.antiAlias,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(16),
        side: BorderSide(
          color: involvesOwn
              ? scheme.primary.withValues(alpha: 0.45)
              : scheme.outlineVariant.withValues(alpha: 0.6),
        ),
      ),
      child: InkWell(
        onTap: () {
          final id = Json.intOrNull(match['id']);
          if (id == null) return;
          Navigator.of(context).push(
            MaterialPageRoute(
              builder: (_) => MatchDetailScreen(session: session, matchId: id),
            ),
          );
        },
        child: Padding(
          padding: const EdgeInsets.fromLTRB(14, 12, 14, 13),
          child: Column(
            children: [
              Row(
                children: [
                  StatusChip(label: status.toUpperCase(), color: statusColor),
                  const SizedBox(width: 8),
                  Expanded(
                    child: Text(
                      Json.string(match['tournament_name'], 'Friendly'),
                      style: TextStyle(
                        fontSize: 12,
                        fontWeight: FontWeight.w600,
                        color: scheme.onSurfaceVariant,
                      ),
                      overflow: TextOverflow.ellipsis,
                    ),
                  ),
                  if (involvesOwn)
                    Container(
                      padding: const EdgeInsets.symmetric(
                        horizontal: 8,
                        vertical: 3,
                      ),
                      decoration: BoxDecoration(
                        color: scheme.primary.withValues(alpha: 0.1),
                        borderRadius: BorderRadius.circular(20),
                      ),
                      child: Text(
                        'YOUR TEAM',
                        style: TextStyle(
                          fontSize: 9,
                          letterSpacing: 0.6,
                          fontWeight: FontWeight.w800,
                          color: scheme.primary,
                        ),
                      ),
                    ),
                ],
              ),
              const SizedBox(height: 12),
              _TeamLine(
                name: Json.teamName(match, 'home', fallback: 'Home'),
                code: Json.teamCode(match, 'home'),
                score: status == 'scheduled'
                    ? null
                    : Json.intOrNull(match['home_score']),
                winner: _winner(match, home: true),
                isMine: ownTeamId != null && homeId == ownTeamId,
              ),
              Padding(
                padding: const EdgeInsets.symmetric(vertical: 6),
                child: Row(
                  children: [
                    Expanded(
                      child: Divider(
                        height: 1,
                        color: scheme.outlineVariant.withValues(alpha: 0.6),
                      ),
                    ),
                    Padding(
                      padding: const EdgeInsets.symmetric(horizontal: 8),
                      child: Text(
                        _centreLabel(status, Json.dateTime(match['match_date'])),
                        style: TextStyle(
                          fontSize: 9,
                          letterSpacing: 0.8,
                          fontWeight: FontWeight.w800,
                          color: status == 'live'
                              ? statusColor
                              : scheme.outline,
                        ),
                      ),
                    ),
                    Expanded(
                      child: Divider(
                        height: 1,
                        color: scheme.outlineVariant.withValues(alpha: 0.6),
                      ),
                    ),
                  ],
                ),
              ),
              _TeamLine(
                name: Json.teamName(match, 'away', fallback: 'Away'),
                code: Json.teamCode(match, 'away'),
                score: status == 'scheduled'
                    ? null
                    : Json.intOrNull(match['away_score']),
                winner: _winner(match, home: false),
                isMine: ownTeamId != null && awayId == ownTeamId,
              ),
              const SizedBox(height: 12),
              Row(
                children: [
                  Icon(
                    Icons.calendar_today_outlined,
                    size: 13,
                    color: scheme.outline,
                  ),
                  const SizedBox(width: 4),
                  Text(
                    weekday == null ? date : '$weekday, $date',
                    style: TextStyle(fontSize: 11.5, color: scheme.outline),
                  ),
                  if (venue.isNotEmpty) ...[
                    const SizedBox(width: 10),
                    Icon(
                      Icons.place_outlined,
                      size: 13,
                      color: scheme.outline,
                    ),
                    const SizedBox(width: 4),
                    Expanded(
                      child: Text(
                        venue,
                        style: TextStyle(fontSize: 11.5, color: scheme.outline),
                        overflow: TextOverflow.ellipsis,
                      ),
                    ),
                  ],
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }

  /// True when this side won a finished match; null otherwise.
  bool? _winner(Map<String, dynamic> match, {required bool home}) {
    if (Json.string(match['status'], 'scheduled') != 'finished') return null;
    final hs = Json.intOrNull(match['home_score']);
    final as = Json.intOrNull(match['away_score']);
    if (hs == null || as == null || hs == as) return null;
    return home ? hs > as : as > hs;
  }

  /// Label between the two team rows: kick-off, live marker or full time.
  static String _centreLabel(String status, DateTime? date) {
    switch (status) {
      case 'scheduled':
        if (date == null) return 'KICK-OFF TBC';
        final h = date.hour.toString().padLeft(2, '0');
        final m = date.minute.toString().padLeft(2, '0');
        return 'KICK-OFF $h:$m';
      case 'live':
        return 'LIVE';
      case 'finished':
        return 'FULL TIME';
      default:
        return status.toUpperCase();
    }
  }

  static String? _weekday(DateTime? date) {
    if (date == null) return null;
    const days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
    return days[date.weekday - 1];
  }
}

class _TeamLine extends StatelessWidget {
  const _TeamLine({
    required this.name,
    required this.code,
    required this.score,
    required this.winner,
    required this.isMine,
  });

  final String name;
  final String code;
  final int? score;
  final bool? winner;
  final bool isMine;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    final initial = code.isNotEmpty
        ? code
        : (name.isEmpty ? '?' : name.characters.first.toUpperCase());
    final muted = winner == false;

    return Row(
      children: [
        CircleAvatar(
          radius: 15,
          backgroundColor: isMine
              ? scheme.primary
              : scheme.surfaceContainerHighest,
          child: Text(
            initial.length > 3 ? initial.substring(0, 3) : initial,
            style: TextStyle(
              fontSize: 10,
              fontWeight: FontWeight.w800,
              color: isMine ? scheme.onPrimary : scheme.onSurfaceVariant,
            ),
          ),
        ),
        const SizedBox(width: 10),
        Expanded(
          child: Text(
            name,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: TextStyle(
              fontSize: 14,
              fontWeight: winner == true ? FontWeight.w800 : FontWeight.w600,
              color: muted ? scheme.onSurfaceVariant : scheme.onSurface,
            ),
          ),
        ),
        const SizedBox(width: 8),
        if (score != null)
          Text(
            '$score',
            style: TextStyle(
              fontSize: 20,
              height: 1,
              fontWeight: winner == true ? FontWeight.w800 : FontWeight.w600,
              color: muted ? scheme.onSurfaceVariant : scheme.onSurface,
            ),
          ),
      ],
    );
  }
}
