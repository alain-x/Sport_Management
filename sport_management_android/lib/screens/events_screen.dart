import 'package:flutter/material.dart';

import '../models/json.dart';
import '../services/api_exception.dart';
import '../state/session_store.dart';
import '../widgets/common.dart';
import 'event_detail_screen.dart';
import 'event_form_screen.dart';

/// Role-aware calendar of training sessions and events.
///
/// Players see their own team's calendar and check in from the detail screen.
/// Coaches/administrators can register new categories/sessions and track
/// attendance.
class EventsScreen extends StatefulWidget {
  const EventsScreen({super.key, required this.session, this.embedded = false});

  final SessionStore session;
  final bool embedded;

  @override
  State<EventsScreen> createState() => _EventsScreenState();
}

class _EventsScreenState extends State<EventsScreen> {
  late DateTime _month;
  String _category = 'all';
  late Future<_EventsPayload> _future;

  bool get _canManage =>
      widget.session.user?.isAdmin == true ||
      widget.session.user?.isManager == true;

  @override
  void initState() {
    super.initState();
    final now = DateTime.now();
    _month = DateTime(now.year, now.month);
    _load();
  }

  void _load() {
    final from = DateTime(_month.year, _month.month);
    final to = DateTime(_month.year, _month.month + 1);

    _future = widget.session.client
        .get('/events', query: {'from': _format(from), 'to': _format(to)})
        .then((data) => _EventsPayload.fromJson(Json.map(data)));
  }

  String _format(DateTime dt) =>
      '${dt.year}-${dt.month.toString().padLeft(2, '0')}-'
      '${dt.day.toString().padLeft(2, '0')} 00:00:00';

  void _reload() => setState(_load);

  void _shiftMonth(int months) {
    setState(() {
      _month = DateTime(_month.year, _month.month + months);
      _load();
    });
  }

  Future<void> _openEvent(int eventId) async {
    final changed = await Navigator.of(context).push<bool>(
      MaterialPageRoute(
        builder: (_) => EventDetailScreen(
          session: widget.session,
          eventId: eventId,
        ),
      ),
    );
    if (changed == true && mounted) _reload();
  }

  Future<void> _createEvent(List<String> categories) async {
    final saved = await Navigator.of(context).push<bool>(
      MaterialPageRoute(
        builder: (_) => EventFormScreen(
          session: widget.session,
          categories: categories,
        ),
      ),
    );
    if (saved == true && mounted) _reload();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: widget.embedded
          ? null
          : AppBar(title: const Text('Calendar & training')),
      body: FutureBuilder<_EventsPayload>(
        future: _future,
        builder: (context, snapshot) {
          final payload = snapshot.data;
          final categories = payload?.categories ?? _fallbackCategories;

          return Column(
            children: [
              _MonthBar(
                month: _month,
                onPrevious: () => _shiftMonth(-1),
                onNext: () => _shiftMonth(1),
                onToday: () {
                  final now = DateTime.now();
                  setState(() {
                    _month = DateTime(now.year, now.month);
                    _load();
                  });
                },
              ),
              SizedBox(
                height: 44,
                child: ListView(
                  scrollDirection: Axis.horizontal,
                  padding: const EdgeInsets.symmetric(horizontal: 16),
                  children: [
                    _CategoryChip(
                      label: 'All',
                      selected: _category == 'all',
                      onTap: () => setState(() => _category = 'all'),
                    ),
                    for (final category in categories)
                      _CategoryChip(
                        label: _titleCase(category),
                        icon: _iconFor(category),
                        selected: _category == category,
                        onTap: () => setState(() => _category = category),
                      ),
                  ],
                ),
              ),
              Expanded(child: _buildBody(snapshot, categories)),
            ],
          );
        },
      ),
      floatingActionButton: _canManage
          ? FloatingActionButton.extended(
              onPressed: () => _createEvent(_fallbackCategories),
              icon: const Icon(Icons.add),
              label: const Text('New event'),
            )
          : null,
    );
  }

  Widget _buildBody(
    AsyncSnapshot<_EventsPayload> snapshot,
    List<String> categories,
  ) {
    if (snapshot.connectionState == ConnectionState.waiting) {
      return const LoadingView(message: 'Loading the calendar…');
    }
    if (snapshot.hasError) {
      final error = snapshot.error;
      return ErrorRetryView(
        message:
            error is ApiException ? error.message : 'Could not load events.',
        onRetry: _reload,
      );
    }

    final payload = snapshot.data;
    final all = payload?.items ?? const <Map<String, dynamic>>[];
    final events = _category == 'all'
        ? all
        : all
            .where((event) => Json.string(event['type']) == _category)
            .toList();

    if (events.isEmpty) {
      return RefreshIndicator(
        onRefresh: () async => _reload(),
        child: ListView(
          children: [
            SizedBox(
              height: 320,
              child: EmptyView(
                message: _category == 'all'
                    ? 'No events in ${_monthLabel(_month)}.'
                    : 'No $_category events this month.',
                icon: Icons.event_busy_outlined,
              ),
            ),
          ],
        ),
      );
    }

    final rows = <_CalendarRow>[];
    String? lastDay;
    for (final event in events) {
      final dt = Json.dateTime(event['starts_at']);
      final dayKey = dt == null
          ? 'unknown'
          : '${dt.year}-${dt.month}-${dt.day}';
      if (dayKey != lastDay) {
        rows.add(_CalendarRow.header(dayKey, dt));
        lastDay = dayKey;
      }
      rows.add(_CalendarRow.event(event));
    }

    return RefreshIndicator(
      onRefresh: () async => _reload(),
      child: ListView.builder(
        padding: const EdgeInsets.fromLTRB(16, 8, 16, 96),
        itemCount: rows.length,
        itemBuilder: (context, index) {
          final row = rows[index];
          if (row.header != null) {
            return _DayHeader(day: row.header!, date: row.date);
          }
          final event = row.event!;
          return Padding(
            padding: const EdgeInsets.only(bottom: 10),
            child: _EventCard(
              event: event,
              isPlayer: widget.session.user?.isPlayer ?? false,
              onTap: () {
                final id = Json.intOrNull(event['id']);
                if (id != null) _openEvent(id);
              },
            ),
          );
        },
      ),
    );
  }
}

class _EventsPayload {
  _EventsPayload({
    required this.items,
    required this.categories,
    required this.teamName,
  });

  final List<Map<String, dynamic>> items;
  final List<String> categories;
  final String teamName;

  factory _EventsPayload.fromJson(Map<String, dynamic> data) {
    final rawCategories = data['categories'];
    final categories = rawCategories is List
        ? rawCategories.map((value) => '$value').where((value) => value.isNotEmpty).toList()
        : <String>[];

    return _EventsPayload(
      items: Json.list(data['items']),
      categories: categories,
      teamName: Json.string(Json.map(data['team'])['name']),
    );
  }
}

const _fallbackCategories = [
  'training',
  'meeting',
  'travel',
  'deadline',
  'media',
  'other',
];

class _MonthBar extends StatelessWidget {
  const _MonthBar({
    required this.month,
    required this.onPrevious,
    required this.onNext,
    required this.onToday,
  });

  final DateTime month;
  final VoidCallback onPrevious;
  final VoidCallback onNext;
  final VoidCallback onToday;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    final now = DateTime.now();
    final isCurrent = month.year == now.year && month.month == now.month;

    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 8, 16, 8),
      child: Row(
        children: [
          IconButton(
            tooltip: 'Previous month',
            onPressed: onPrevious,
            icon: const Icon(Icons.chevron_left),
          ),
          Expanded(
            child: Text(
              _monthLabel(month),
              textAlign: TextAlign.center,
              style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 16),
            ),
          ),
          IconButton(
            tooltip: 'Next month',
            onPressed: onNext,
            icon: const Icon(Icons.chevron_right),
          ),
          if (!isCurrent)
            TextButton(
              onPressed: onToday,
              style: TextButton.styleFrom(
                visualDensity: VisualDensity.compact,
                foregroundColor: scheme.primary,
              ),
              child: const Text('Today'),
            ),
        ],
      ),
    );
  }
}

class _CategoryChip extends StatelessWidget {
  const _CategoryChip({
    required this.label,
    required this.selected,
    required this.onTap,
    this.icon,
  });

  final String label;
  final bool selected;
  final VoidCallback onTap;
  final IconData? icon;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(right: 8),
      child: FilterChip(
        selected: selected,
        onSelected: (_) => onTap(),
        showCheckmark: false,
        avatar: icon == null ? null : Icon(icon, size: 16),
        label: Text(label),
      ),
    );
  }
}

class _CalendarRow {
  _CalendarRow.header(this.header, this.date) : event = null;
  _CalendarRow.event(this.event)
      : header = null,
        date = null;

  final String? header;
  final DateTime? date;
  final Map<String, dynamic>? event;
}

class _DayHeader extends StatelessWidget {
  const _DayHeader({required this.day, required this.date});

  final String day;
  final DateTime? date;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    final label = date == null
        ? 'Date to be confirmed'
        : '${_weekday(date!)} · ${Json.dateLabel(day)}';

    return Padding(
      padding: const EdgeInsets.only(top: 10, bottom: 8, left: 4),
      child: Text(
        label.toUpperCase(),
        style: TextStyle(
          fontSize: 11,
          letterSpacing: 0.8,
          fontWeight: FontWeight.w700,
          color: scheme.outline,
        ),
      ),
    );
  }
}

class _EventCard extends StatelessWidget {
  const _EventCard({
    required this.event,
    required this.isPlayer,
    required this.onTap,
  });

  final Map<String, dynamic> event;
  final bool isPlayer;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    final type = Json.string(event['type'], 'other');
    final color = _colorFor(type, scheme);
    final startsAt = Json.dateTime(event['starts_at']);
    final counts = Json.map(event['attendance_counts']);
    final total = Json.intOr(counts['total']);
    final present = Json.intOr(counts['present']) + Json.intOr(counts['late']);
    final mine = event['my_attendance'];

    return Card(
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.all(14),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Container(
                width: 54,
                height: 60,
                decoration: BoxDecoration(
                  color: color.withValues(alpha: 0.12),
                  borderRadius: BorderRadius.circular(14),
                ),
                child: Column(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    Text(
                      startsAt == null ? '—' : '${startsAt.day}',
                      style: TextStyle(
                        color: color,
                        fontSize: 20,
                        fontWeight: FontWeight.w800,
                        height: 1,
                      ),
                    ),
                    const SizedBox(height: 2),
                    Text(
                      startsAt == null ? '' : _shortMonth(startsAt.month),
                      style: TextStyle(
                        color: color,
                        fontSize: 11,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Expanded(
                          child: Text(
                            Json.string(event['title'], 'Event'),
                            maxLines: 2,
                            overflow: TextOverflow.ellipsis,
                            style: const TextStyle(
                              fontWeight: FontWeight.w700,
                              fontSize: 15,
                            ),
                          ),
                        ),
                        const SizedBox(width: 8),
                        StatusChip(
                          label: _titleCase(type),
                          color: color,
                        ),
                      ],
                    ),
                    const SizedBox(height: 6),
                    Row(
                      children: [
                        Icon(
                          Icons.schedule,
                          size: 14,
                          color: scheme.outline,
                        ),
                        const SizedBox(width: 4),
                        Text(
                          startsAt == null ? 'Time TBC' : _timeLabel(startsAt),
                          style: TextStyle(
                            fontSize: 12,
                            color: scheme.outline,
                          ),
                        ),
                        if (Json.string(event['team_name']).isNotEmpty) ...[
                          const SizedBox(width: 12),
                          Icon(
                            Icons.groups_outlined,
                            size: 14,
                            color: scheme.outline,
                          ),
                          const SizedBox(width: 4),
                          Expanded(
                            child: Text(
                              Json.string(event['team_name']),
                              overflow: TextOverflow.ellipsis,
                              style: TextStyle(
                                fontSize: 12,
                                color: scheme.outline,
                              ),
                            ),
                          ),
                        ],
                      ],
                    ),
                    const SizedBox(height: 8),
                    if (isPlayer)
                      _PlayerStatus(
                        type: type,
                        attendance: mine,
                      )
                    else
                      _StaffTally(
                        total: total,
                        present: present,
                        color: color,
                      ),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _StaffTally extends StatelessWidget {
  const _StaffTally({
    required this.total,
    required this.present,
    required this.color,
  });

  final int total;
  final int present;
  final Color color;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    if (total == 0) {
      return Row(
        children: [
          Icon(Icons.hourglass_empty, size: 14, color: scheme.outline),
          const SizedBox(width: 4),
          Text(
            'No check-ins yet',
            style: TextStyle(fontSize: 12, color: scheme.outline),
          ),
        ],
      );
    }

    return Row(
      children: [
        Icon(Icons.check_circle_outline, size: 14, color: color),
        const SizedBox(width: 4),
        Text(
          '$present of $total checked in',
          style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w600),
        ),
      ],
    );
  }
}

class _PlayerStatus extends StatelessWidget {
  const _PlayerStatus({required this.type, required this.attendance});

  final String type;
  final dynamic attendance;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    final record = attendance is Map ? Json.map(attendance) : null;

    if (record == null) {
      if (type != 'training') {
        return Text(
          _titleCase(type),
          style: TextStyle(fontSize: 12, color: scheme.outline),
        );
      }
      return Row(
        children: [
          Icon(Icons.camera_alt_outlined, size: 14, color: scheme.primary),
          const SizedBox(width: 4),
          Text(
            'Tap to check in with a selfie',
            style: TextStyle(
              fontSize: 12,
              color: scheme.primary,
              fontWeight: FontWeight.w600,
            ),
          ),
        ],
      );
    }

    final status = Json.string(record['status'], 'present');
    return Row(
      children: [
        StatusChip(
          label: _titleCase(status),
          color: StatusChip.colorFor(status, scheme),
        ),
        const SizedBox(width: 8),
        Text(
          Json.string(record['checked_in_at'], ''),
          style: TextStyle(fontSize: 12, color: scheme.outline),
        ),
      ],
    );
  }
}

IconData _iconFor(String type) {
  switch (type) {
    case 'training':
      return Icons.fitness_center;
    case 'meeting':
      return Icons.groups_2_outlined;
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

Color _colorFor(String type, ColorScheme scheme) {
  switch (type) {
    case 'training':
      return const Color(0xFF0F766E);
    case 'meeting':
      return const Color(0xFF2563EB);
    case 'travel':
      return const Color(0xFFB45309);
    case 'deadline':
      return const Color(0xFFB91C1C);
    case 'media':
      return const Color(0xFF7C3AED);
    default:
      return scheme.primary;
  }
}

String _titleCase(String value) {
  if (value.isEmpty) return value;
  return '${value[0].toUpperCase()}${value.substring(1)}';
}

String _monthLabel(DateTime date) {
  const months = [
    'January', 'February', 'March', 'April', 'May', 'June',
    'July', 'August', 'September', 'October', 'November', 'December',
  ];
  return '${months[date.month - 1]} ${date.year}';
}

String _shortMonth(int month) {
  const months = [
    'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun',
    'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec',
  ];
  return months[month - 1];
}

String _weekday(DateTime date) {
  const days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
  return days[date.weekday - 1];
}

String _timeLabel(DateTime date) {
  final h = date.hour.toString().padLeft(2, '0');
  final m = date.minute.toString().padLeft(2, '0');
  return '$h:$m';
}
