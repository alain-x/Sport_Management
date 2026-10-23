import 'package:flutter/material.dart';

import '../models/json.dart';
import '../services/api_exception.dart';
import '../state/session_store.dart';
import '../widgets/common.dart';

/// A single match with its scoreboard and fixture details.
class MatchDetailScreen extends StatefulWidget {
  const MatchDetailScreen({
    super.key,
    required this.session,
    required this.matchId,
  });

  final SessionStore session;
  final int matchId;

  @override
  State<MatchDetailScreen> createState() => _MatchDetailScreenState();
}

class _MatchDetailScreenState extends State<MatchDetailScreen> {
  late Future<Map<String, dynamic>> _future;

  @override
  void initState() {
    super.initState();
    _load();
  }

  void _load() {
    _future = widget.session.client.getMap('/matches/${widget.matchId}');
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Match')),
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
                  : 'Could not load the match.',
              onRetry: () => setState(_load),
            );
          }

          final match = snapshot.data ?? const {};
          final status = Json.string(match['status'], 'scheduled');
          final scheme = Theme.of(context).colorScheme;
          final venue = Json.string(match['venue'], 'Venue to be confirmed');
          final tournament = Json.string(match['tournament_name'], 'Friendly');

          return ListView(
            padding: const EdgeInsets.all(16),
            children: [
              _Scoreboard(match: match, status: status),
              const SizedBox(height: 16),
              Text(
                'FIXTURE DETAILS',
                style: TextStyle(
                  fontSize: 11,
                  letterSpacing: 0.8,
                  fontWeight: FontWeight.w700,
                  color: scheme.outline,
                ),
              ),
              const SizedBox(height: 8),
              Card(
                child: Padding(
                  padding: const EdgeInsets.fromLTRB(16, 6, 16, 6),
                  child: Column(
                    children: [
                      DetailRow(
                        label: 'Competition',
                        value: tournament,
                      ),
                      DetailRow(
                        label: 'Kick-off',
                        value: Json.dateTimeLabel(match['match_date']),
                      ),
                      DetailRow(label: 'Venue', value: venue),
                      DetailRow(
                        label: 'Home team',
                        value: Json.teamName(match, 'home', fallback: '—'),
                      ),
                      DetailRow(
                        label: 'Away team',
                        value: Json.teamName(match, 'away', fallback: '—'),
                      ),
                      DetailRow(label: 'Status', value: _statusLabel(status)),
                    ],
                  ),
                ),
              ),
            ],
          );
        },
      ),
    );
  }

  static String _statusLabel(String status) {
    switch (status) {
      case 'live':
        return 'In progress';
      case 'finished':
        return 'Full time';
      case 'scheduled':
        return 'Scheduled';
      default:
        return status;
    }
  }
}

/// Gradient hero showing both teams and the score.
class _Scoreboard extends StatelessWidget {
  const _Scoreboard({required this.match, required this.status});

  final Map<String, dynamic> match;
  final String status;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    final statusColor = StatusChip.colorFor(status, scheme);
    final homeName = Json.teamName(match, 'home', fallback: 'Home');
    final awayName = Json.teamName(match, 'away', fallback: 'Away');
    final homeScore = Json.intOrNull(match['home_score']);
    final awayScore = Json.intOrNull(match['away_score']);
    final played = status == 'live' || status == 'finished';
    final outcome = _outcome(homeName, awayName, homeScore, awayScore, played);

    return Container(
      padding: const EdgeInsets.fromLTRB(18, 16, 18, 18),
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(22),
        gradient: LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [
            scheme.primaryContainer,
            scheme.surfaceContainerHighest,
          ],
        ),
        border: Border.all(color: scheme.primary.withValues(alpha: 0.18)),
      ),
      child: Column(
        children: [
          Row(
            children: [
              StatusChip(label: status.toUpperCase(), color: statusColor),
              const SizedBox(width: 8),
              Expanded(
                child: Text(
                  Json.string(match['tournament_name'], 'Friendly'),
                  textAlign: TextAlign.end,
                  style: TextStyle(
                    fontSize: 12,
                    fontWeight: FontWeight.w600,
                    color: scheme.onPrimaryContainer.withValues(alpha: 0.8),
                  ),
                  overflow: TextOverflow.ellipsis,
                ),
              ),
            ],
          ),
          const SizedBox(height: 20),
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              _Side(
                name: homeName,
                code: Json.teamCode(match, 'home'),
                sideLabel: 'HOME',
              ),
              Padding(
                padding: const EdgeInsets.symmetric(horizontal: 8),
                child: Column(
                  children: [
                    const SizedBox(height: 6),
                    Text(
                      played ? '${homeScore ?? 0} – ${awayScore ?? 0}' : 'VS',
                      style: TextStyle(
                        fontSize: played ? 30 : 18,
                        fontWeight: FontWeight.w800,
                        letterSpacing: 1,
                        color: scheme.onPrimaryContainer,
                      ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      played ? 'SCORE' : 'KICK-OFF',
                      style: TextStyle(
                        fontSize: 9,
                        letterSpacing: 1,
                        fontWeight: FontWeight.w700,
                        color: scheme.onPrimaryContainer.withValues(alpha: 0.6),
                      ),
                    ),
                  ],
                ),
              ),
              _Side(
                name: awayName,
                code: Json.teamCode(match, 'away'),
                sideLabel: 'AWAY',
              ),
            ],
          ),
          const SizedBox(height: 16),
          Row(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Icon(
                Icons.calendar_today_outlined,
                size: 14,
                color: scheme.onPrimaryContainer.withValues(alpha: 0.7),
              ),
              const SizedBox(width: 6),
              Flexible(
                child: Text(
                  Json.dateTimeLabel(match['match_date']),
                  style: TextStyle(
                    fontSize: 12.5,
                    fontWeight: FontWeight.w600,
                    color: scheme.onPrimaryContainer.withValues(alpha: 0.8),
                  ),
                ),
              ),
            ],
          ),
          if (outcome != null) ...[
            const SizedBox(height: 12),
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
              decoration: BoxDecoration(
                color: scheme.surface.withValues(alpha: 0.7),
                borderRadius: BorderRadius.circular(20),
              ),
              child: Text(
                outcome,
                style: TextStyle(
                  fontSize: 12,
                  fontWeight: FontWeight.w700,
                  color: scheme.onSurface,
                ),
              ),
            ),
          ],
        ],
      ),
    );
  }

  static String? _outcome(
    String home,
    String away,
    int? homeScore,
    int? awayScore,
    bool played,
  ) {
    if (!played || homeScore == null || awayScore == null) return null;
    if (homeScore == awayScore) return 'Match drawn';
    final winner = homeScore > awayScore ? home : away;
    final margin = (homeScore - awayScore).abs();
    return '$winner won by $margin';
  }
}

class _Side extends StatelessWidget {
  const _Side({
    required this.name,
    required this.code,
    required this.sideLabel,
  });

  final String name;
  final String code;
  final String sideLabel;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    final initial = code.isNotEmpty
        ? code
        : (name.isEmpty ? '?' : name.characters.first.toUpperCase());

    return Expanded(
      child: Column(
        children: [
          CircleAvatar(
            radius: 26,
            backgroundColor: scheme.surface.withValues(alpha: 0.85),
            child: Text(
              initial.length > 3 ? initial.substring(0, 3) : initial,
              style: TextStyle(
                color: scheme.onSurface,
                fontWeight: FontWeight.w800,
                fontSize: 13,
              ),
            ),
          ),
          const SizedBox(height: 8),
          Text(
            name,
            textAlign: TextAlign.center,
            maxLines: 2,
            overflow: TextOverflow.ellipsis,
            style: TextStyle(
              fontWeight: FontWeight.w700,
              fontSize: 13,
              color: scheme.onPrimaryContainer,
            ),
          ),
          const SizedBox(height: 3),
          Text(
            sideLabel,
            style: TextStyle(
              fontSize: 9,
              letterSpacing: 0.8,
              fontWeight: FontWeight.w700,
              color: scheme.onPrimaryContainer.withValues(alpha: 0.55),
            ),
          ),
        ],
      ),
    );
  }
}
