import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../config.dart';
import '../models/json.dart';
import '../services/api_exception.dart';
import '../services/attendance_export.dart';
import '../services/selfie_capture.dart';
import '../state/session_store.dart';
import '../widgets/common.dart';
import 'event_form_screen.dart';

/// A single event with its attendance roster.
///
/// Staff can correct statuses and export the session sheet; player accounts
/// check in by taking a selfie with the camera.
class EventDetailScreen extends StatefulWidget {
  const EventDetailScreen({
    super.key,
    required this.session,
    required this.eventId,
  });

  final SessionStore session;
  final int eventId;

  @override
  State<EventDetailScreen> createState() => _EventDetailScreenState();
}

class _EventDetailScreenState extends State<EventDetailScreen> {
  late Future<_EventDetail> _future;
  bool _busy = false;
  bool _changed = false;

  bool get _isStaff =>
      widget.session.user?.isAdmin == true ||
      widget.session.user?.isManager == true;

  @override
  void initState() {
    super.initState();
    _load();
  }

  void _load() {
    _future = _fetch();
  }

  Future<_EventDetail> _fetch() async {
    if (_isStaff) {
      final data = await widget.session.client
          .getMap('/events/${widget.eventId}/attendance');
      return _EventDetail.fromRoster(data);
    }

    final data =
        await widget.session.client.getMap('/events/${widget.eventId}');
    return _EventDetail.fromEvent(data);
  }

  void _reload() => setState(_load);

  Future<void> _edit(Map<String, dynamic> event) async {
    final saved = await Navigator.of(context).push<bool>(
      MaterialPageRoute(
        builder: (_) => EventFormScreen(
          session: widget.session,
          event: event,
        ),
      ),
    );
    if (saved == true && mounted) {
      _changed = true;
      _reload();
    }
  }

  Future<void> _delete() async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Delete this event?'),
        content: const Text(
          'The event and its attendance records will be removed.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(ctx).pop(false),
            child: const Text('Cancel'),
          ),
          FilledButton(
            style: FilledButton.styleFrom(
              backgroundColor: Theme.of(context).colorScheme.error,
            ),
            onPressed: () => Navigator.of(ctx).pop(true),
            child: const Text('Delete'),
          ),
        ],
      ),
    );
    if (confirmed != true || !mounted) return;

    setState(() => _busy = true);
    final messenger = ScaffoldMessenger.of(context);
    try {
      await widget.session.client
          .post('/events/${widget.eventId}/delete');
      if (!mounted) return;
      Navigator.of(context).pop(true);
    } on ApiException catch (error) {
      if (!mounted) return;
      setState(() => _busy = false);
      messenger.showSnackBar(SnackBar(content: Text(error.message)));
    }
  }

  Future<void> _markStatus(
    Map<String, dynamic> player,
    String status,
  ) async {
    final playerId = Json.intOrNull(player['id']);
    if (playerId == null) return;

    setState(() => _busy = true);
    final messenger = ScaffoldMessenger.of(context);
    try {
      await widget.session.client.post(
        '/events/${widget.eventId}/attendance/$playerId',
        body: {'status': status},
      );
      _changed = true;
      if (!mounted) return;
      setState(() {
        _busy = false;
        _load();
      });
    } on ApiException catch (error) {
      if (!mounted) return;
      setState(() => _busy = false);
      messenger.showSnackBar(SnackBar(content: Text(error.flatErrors)));
    }
  }

  Future<void> _export(_EventDetail detail) async {
    setState(() => _busy = true);
    final messenger = ScaffoldMessenger.of(context);
    try {
      await AttendanceExport.eventAttendance(
        event: detail.event,
        teamName: detail.teamName,
        squad: detail.squad,
        tally: detail.tally,
      );
      if (!mounted) return;
      setState(() => _busy = false);
      messenger.showSnackBar(
        const SnackBar(content: Text('Attendance sheet ready to share.')),
      );
    } catch (error) {
      if (!mounted) return;
      setState(() => _busy = false);
      messenger.showSnackBar(
        SnackBar(content: Text('Could not export the sheet: $error')),
      );
    }
  }

  Future<void> _checkIn(_EventDetail detail) async {
    final window = _CheckinWindow.fromEvent(detail.event);
    final lateNote = window.lateAt == null
        ? ''
        : ' Check-ins after ${_clock(window.lateAt!)} '
            'are recorded as late.';
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Check in with a selfie'),
        content: Text(
          'Take a photo with your camera now. The app records the time the '
          'photo is received and marks you present.$lateNote',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(ctx).pop(false),
            child: const Text('Cancel'),
          ),
          FilledButton.icon(
            onPressed: () => Navigator.of(ctx).pop(true),
            icon: const Icon(Icons.camera_alt),
            label: const Text('Open camera'),
          ),
        ],
      ),
    );
    if (confirmed != true || !mounted) return;

    final path = await SelfieCapture().takeSelfie(context);
    if (path == null || !mounted) return;

    setState(() => _busy = true);
    final messenger = ScaffoldMessenger.of(context);
    try {
      final data = await widget.session.client.postMultipart(
        '/events/${widget.eventId}/attendance',
        filePath: path,
      );
      final payload = Json.map(data);
      final record = Json.map(payload['attendance']);
      final status = Json.string(payload['status'], Json.string(record['status']));
      final time = Json.string(payload['checked_in_at']);

      if (!mounted) return;
      setState(() {
        _busy = false;
        _changed = true;
        _load();
      });
      messenger.showSnackBar(
        SnackBar(
          content: Text(
            'Checked in at ${_timeOf(time)} · ${_titleCase(status)}',
          ),
        ),
      );
    } on ApiException catch (error) {
      if (!mounted) return;
      setState(() => _busy = false);
      messenger.showSnackBar(SnackBar(content: Text(error.flatErrors)));
    } catch (error) {
      if (!mounted) return;
      setState(() => _busy = false);
      messenger.showSnackBar(
        SnackBar(content: Text('Could not upload the photo: $error')),
      );
    }
  }

  Future<void> _editCheckinRules(Map<String, dynamic> event) async {
    final saved = await showDialog<bool>(
      context: context,
      builder: (_) => _CheckinRulesDialog(
        session: widget.session,
        eventId: widget.eventId,
        event: event,
      ),
    );
    if (saved == true && mounted) {
      _changed = true;
      _reload();
    }
  }

  @override
  Widget build(BuildContext context) {
    return PopScope(
      canPop: false,
      onPopInvokedWithResult: (didPop, _) {
        if (!didPop) Navigator.of(context).pop(_changed);
      },
      child: FutureBuilder<_EventDetail>(
        future: _future,
        builder: (context, snapshot) {
          final detail = snapshot.data;
          return Scaffold(
            appBar: AppBar(
              title: Text(
                detail == null
                    ? 'Event'
                    : _titleCase(Json.string(detail.event['type'], 'event')),
              ),
              actions: [
                if (_isStaff && detail != null) ...[
                  IconButton(
                    tooltip: 'Export attendance',
                    onPressed: detail.squad.isEmpty || _busy
                        ? null
                        : () => _export(detail),
                    icon: const Icon(Icons.ios_share),
                  ),
                  IconButton(
                    tooltip: 'Edit event',
                    onPressed:
                        _busy ? null : () => _edit(detail.event),
                    icon: const Icon(Icons.edit_outlined),
                  ),
                  IconButton(
                    tooltip: 'Delete event',
                    onPressed: _busy ? null : _delete,
                    icon: const Icon(Icons.delete_outline),
                  ),
                ],
              ],
            ),
            body: _buildBody(snapshot),
          );
        },
      ),
    );
  }

  Widget _buildBody(AsyncSnapshot<_EventDetail> snapshot) {
    if (snapshot.connectionState == ConnectionState.waiting) {
      return const LoadingView(message: 'Loading the session…');
    }
    if (snapshot.hasError) {
      final error = snapshot.error;
      return ErrorRetryView(
        message:
            error is ApiException ? error.message : 'Could not load the event.',
        onRetry: _reload,
      );
    }

    final detail = snapshot.data;
    if (detail == null) {
      return const EmptyView(message: 'Event not found.');
    }

    return RefreshIndicator(
      onRefresh: () async => _reload(),
      child: ListView(
        padding: const EdgeInsets.fromLTRB(16, 8, 16, 32),
        children: [
          _HeaderCard(detail: detail),
          if (_busy) ...[
            const SizedBox(height: 12),
            const LinearProgressIndicator(minHeight: 2),
          ],
          if (!_isStaff) ...[
            const SizedBox(height: 16),
            _CheckInCard(
              detail: detail,
              busy: _busy,
              onCheckIn: () => _checkIn(detail),
            ),
          ],
          if (_isStaff) ...[
            const SizedBox(height: 16),
            _SummaryCard(
              detail: detail,
              onExport:
                  detail.squad.isEmpty ? null : () => _export(detail),
            ),
            if (Json.string(detail.event['type']) == 'training') ...[
              const SizedBox(height: 16),
              _CheckinRulesCard(
                detail: detail,
                onEdit: _busy
                    ? null
                    : () => _editCheckinRules(detail.event),
              ),
            ],
            const SizedBox(height: 16),
            if (detail.squad.isEmpty)
              const Card(
                child: Padding(
                  padding: EdgeInsets.all(18),
                  child: Text(
                    'This is a league-wide event, so there is no team squad '
                    'to mark attendance for.',
                  ),
                ),
              )
            else ...[
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  const Text(
                    'Squad attendance',
                    style: TextStyle(fontWeight: FontWeight.w700, fontSize: 15),
                  ),
                  Text(
                    '${detail.squad.length} players',
                    style: TextStyle(
                      fontSize: 12,
                      color: Theme.of(context).colorScheme.outline,
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 8),
              Card(
                clipBehavior: Clip.antiAlias,
                child: Column(
                  children: [
                    for (var i = 0; i < detail.squad.length; i++) ...[
                      if (i > 0) const Divider(height: 1),
                      _SquadTile(
                        player: detail.squad[i],
                        busy: _busy,
                        onStatusSelected: (status) =>
                            _markStatus(detail.squad[i], status),
                      ),
                    ],
                  ],
                ),
              ),
            ],
          ],
        ],
      ),
    );
  }
}

class _EventDetail {
  _EventDetail({
    required this.event,
    required this.teamName,
    required this.squad,
    required this.tally,
    required this.myAttendance,
  });

  final Map<String, dynamic> event;
  final String teamName;
  final List<Map<String, dynamic>> squad;
  final Map<String, dynamic> tally;
  final Map<String, dynamic>? myAttendance;

  factory _EventDetail.fromRoster(Map<String, dynamic> data) {
    final team = Json.map(data['team']);
    final event = Json.map(data['event']);

    return _EventDetail(
      event: event,
      teamName: Json.string(
        team['name'],
        Json.string(event['team_name'], 'Team'),
      ),
      squad: Json.list(data['squad']),
      tally: Json.map(data['tally']),
      myAttendance: event['my_attendance'] == null
          ? null
          : Json.map(event['my_attendance']),
    );
  }

  factory _EventDetail.fromEvent(Map<String, dynamic> data) {
    final team = Json.map(data['team']);
    final mine = data['my_attendance'];

    return _EventDetail(
      event: data,
      teamName: Json.string(
        team['name'],
        Json.string(data['team_name'], 'Your team'),
      ),
      squad: const [],
      tally: Json.map(data['tally']),
      myAttendance: mine is Map ? Json.map(mine) : null,
    );
  }
}

class _HeaderCard extends StatelessWidget {
  const _HeaderCard({required this.detail});

  final _EventDetail detail;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    final event = detail.event;
    final type = Json.string(event['type'], 'other');
    final description = Json.string(event['description']);

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(18),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Expanded(
                  child: Text(
                    Json.string(event['title'], 'Event'),
                    style: const TextStyle(
                      fontSize: 19,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ),
                const SizedBox(width: 8),
                StatusChip(label: _titleCase(type), color: scheme.primary),
              ],
            ),
            const SizedBox(height: 12),
            _IconLine(
              icon: Icons.calendar_month_outlined,
              text: Json.dateTimeLabel(event['starts_at']),
            ),
            const SizedBox(height: 8),
            _IconLine(
              icon: Icons.groups_outlined,
              text: detail.teamName.isEmpty
                  ? 'League-wide event'
                  : detail.teamName,
            ),
            if (Json.string(event['tournament_name']).isNotEmpty) ...[
              const SizedBox(height: 8),
              _IconLine(
                icon: Icons.emoji_events_outlined,
                text: Json.string(event['tournament_name']),
              ),
            ],
            if (Json.string(event['creator_name']).isNotEmpty) ...[
              const SizedBox(height: 8),
              _IconLine(
                icon: Icons.person_outline,
                text: 'Registered by ${Json.string(event['creator_name'])}',
              ),
            ],
            if (description.isNotEmpty) ...[
              const SizedBox(height: 14),
              Text(
                description,
                style: TextStyle(color: scheme.onSurfaceVariant, height: 1.4),
              ),
            ],
          ],
        ),
      ),
    );
  }
}

class _IconLine extends StatelessWidget {
  const _IconLine({required this.icon, required this.text});

  final IconData icon;
  final String text;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Icon(icon, size: 17, color: scheme.outline),
        const SizedBox(width: 8),
        Expanded(
          child: Text(text, style: const TextStyle(fontSize: 13)),
        ),
      ],
    );
  }
}

class _SummaryCard extends StatelessWidget {
  const _SummaryCard({required this.detail, this.onExport});

  final _EventDetail detail;
  final VoidCallback? onExport;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    final tally = detail.tally;
    final rate = Json.intOr(tally['rate']);
    final marked = Json.intOr(tally['marked']);
    final attended = Json.intOr(tally['attended']);

    return Card(
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
                        'Attendance',
                        style: TextStyle(
                          fontSize: 12,
                          letterSpacing: 0.6,
                          fontWeight: FontWeight.w700,
                          color: scheme.outline,
                        ),
                      ),
                      const SizedBox(height: 2),
                      Text(
                        '$rate%',
                        style: TextStyle(
                          fontSize: 30,
                          height: 1.1,
                          fontWeight: FontWeight.w800,
                          color: rate >= 75 ? scheme.primary : Colors.orange.shade700,
                        ),
                      ),
                      Text(
                        marked == 0
                            ? 'No attendance recorded'
                            : '$attended of $marked attended',
                        style: TextStyle(fontSize: 12, color: scheme.outline),
                      ),
                    ],
                  ),
                ),
                if (onExport != null)
                  OutlinedButton.icon(
                    onPressed: onExport,
                    icon: const Icon(Icons.download_outlined, size: 18),
                    label: const Text('Excel'),
                  ),
              ],
            ),
            const SizedBox(height: 14),
            ClipRRect(
              borderRadius: BorderRadius.circular(8),
              child: LinearProgressIndicator(
                value: marked == 0 ? 0 : rate / 100,
                minHeight: 8,
                backgroundColor: scheme.surfaceContainerHighest,
              ),
            ),
            const SizedBox(height: 14),
            Wrap(
              spacing: 8,
              runSpacing: 8,
              children: [
                _TallyChip(
                  label: 'Present',
                  count: Json.intOr(tally['present']),
                  color: Colors.green.shade600,
                ),
                _TallyChip(
                  label: 'Late',
                  count: Json.intOr(tally['late']),
                  color: Colors.orange.shade700,
                ),
                _TallyChip(
                  label: 'Absent',
                  count: Json.intOr(tally['absent']),
                  color: Colors.red.shade600,
                ),
                _TallyChip(
                  label: 'Excused',
                  count: Json.intOr(tally['excused']),
                  color: Colors.blueGrey.shade600,
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

class _TallyChip extends StatelessWidget {
  const _TallyChip({
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

/// The effective check-in window: when check-in opens and when it turns late.
class _CheckinWindow {
  const _CheckinWindow({
    required this.opensMinutes,
    required this.lateMinutes,
    this.opensAt,
    this.lateAt,
  });

  factory _CheckinWindow.fromEvent(Map<String, dynamic> event) {
    final startsAt = Json.dateTime(event['starts_at']);
    final opensMinutes = Json.intOr(event['checkin_opens_minutes'], 20);
    final lateMinutes = Json.intOr(event['checkin_late_minutes'], 10);

    return _CheckinWindow(
      opensMinutes: opensMinutes,
      lateMinutes: lateMinutes,
      opensAt: Json.dateTime(event['checkin_opens_at']) ??
          startsAt?.subtract(Duration(minutes: opensMinutes)),
      lateAt: Json.dateTime(event['checkin_late_at']) ??
          startsAt?.add(Duration(minutes: lateMinutes)),
    );
  }

  final int opensMinutes;
  final int lateMinutes;
  final DateTime? opensAt;
  final DateTime? lateAt;

  bool get isOpen => opensAt == null || !DateTime.now().isBefore(opensAt!);
  bool get isLateNow => lateAt != null && DateTime.now().isAfter(lateAt!);
}

/// Staff card that explains and edits the per-event check-in window.
class _CheckinRulesCard extends StatelessWidget {
  const _CheckinRulesCard({required this.detail, this.onEdit});

  final _EventDetail detail;
  final VoidCallback? onEdit;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    final window = _CheckinWindow.fromEvent(detail.event);
    final opensAt = window.opensAt == null
        ? '${window.opensMinutes} min before start'
        : '${_clock(window.opensAt!)} '
            '(${window.opensMinutes} min before start)';
    final lateAt = window.lateAt == null
        ? '${window.lateMinutes} min after start'
        : '${_clock(window.lateAt!)} '
            '(${window.lateMinutes} min after start)';

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(18),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Icon(Icons.timer_outlined, size: 20, color: scheme.primary),
                const SizedBox(width: 8),
                const Expanded(
                  child: Text(
                    'Check-in window',
                    style: TextStyle(
                      fontWeight: FontWeight.w700,
                      fontSize: 15,
                    ),
                  ),
                ),
                TextButton.icon(
                  onPressed: onEdit,
                  icon: const Icon(Icons.edit_outlined, size: 16),
                  label: const Text('Edit'),
                ),
              ],
            ),
            const SizedBox(height: 4),
            _IconLine(
              icon: Icons.lock_open_outlined,
              text: 'Opens $opensAt',
            ),
            const SizedBox(height: 6),
            _IconLine(
              icon: Icons.watch_later_outlined,
              text: 'Late after $lateAt',
            ),
            const SizedBox(height: 8),
            Text(
              'Only the manager or an administrator can change these minutes, '
              'and only for their own team.',
              style: TextStyle(fontSize: 11.5, color: scheme.outline),
            ),
          ],
        ),
      ),
    );
  }
}

/// Dialog that updates or resets the per-event check-in minutes.
class _CheckinRulesDialog extends StatefulWidget {
  const _CheckinRulesDialog({
    required this.session,
    required this.eventId,
    required this.event,
  });

  final SessionStore session;
  final int eventId;
  final Map<String, dynamic> event;

  @override
  State<_CheckinRulesDialog> createState() => _CheckinRulesDialogState();
}

class _CheckinRulesDialogState extends State<_CheckinRulesDialog> {
  final _formKey = GlobalKey<FormState>();
  late final TextEditingController _opens;
  late final TextEditingController _late;
  bool _saving = false;
  String? _error;

  static const int _defaultOpens = 20;
  static const int _defaultLate = 10;
  static const int _maxMinutes = 180;

  @override
  void initState() {
    super.initState();
    _opens = TextEditingController(
      text: '${Json.intOr(widget.event['checkin_opens_minutes'], _defaultOpens)}',
    );
    _late = TextEditingController(
      text: '${Json.intOr(widget.event['checkin_late_minutes'], _defaultLate)}',
    );
  }

  @override
  void dispose() {
    _opens.dispose();
    _late.dispose();
    super.dispose();
  }

  Future<void> _save({required bool reset}) async {
    if (!reset && !(_formKey.currentState?.validate() ?? false)) return;

    setState(() {
      _saving = true;
      _error = null;
    });

    final messenger = ScaffoldMessenger.of(context);
    try {
      await widget.session.client.post(
        '/events/${widget.eventId}/checkin-rules',
        body: reset
            ? {
                'checkin_opens_minutes': null,
                'checkin_late_minutes': null,
              }
            : {
                'checkin_opens_minutes': int.parse(_opens.text.trim()),
                'checkin_late_minutes': int.parse(_late.text.trim()),
              },
      );
      if (!mounted) return;
      Navigator.of(context).pop(true);
      messenger.showSnackBar(
        SnackBar(
          content: Text(
            reset
                ? 'Check-in window reset to the league default.'
                : 'Check-in window updated.',
          ),
        ),
      );
    } on ApiException catch (error) {
      if (!mounted) return;
      setState(() {
        _saving = false;
        _error = error.flatErrors;
      });
    } catch (error) {
      if (!mounted) return;
      setState(() {
        _saving = false;
        _error = 'Could not update the check-in window: $error';
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    return AlertDialog(
      scrollable: true,
      title: const Text('Check-in rules'),
      content: Form(
        key: _formKey,
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            TextFormField(
              controller: _opens,
              keyboardType: TextInputType.number,
              inputFormatters: [FilteringTextInputFormatter.digitsOnly],
              decoration: const InputDecoration(
                labelText: 'Opens before start',
                helperText: 'Players cannot check in earlier than this.',
                suffixText: 'min',
              ),
              validator: _validateMinutes,
            ),
            const SizedBox(height: 12),
            TextFormField(
              controller: _late,
              keyboardType: TextInputType.number,
              inputFormatters: [FilteringTextInputFormatter.digitsOnly],
              decoration: const InputDecoration(
                labelText: 'Late after start',
                helperText: 'Check-ins after this are marked late.',
                suffixText: 'min',
              ),
              validator: _validateMinutes,
            ),
            if (_error != null) ...[
              const SizedBox(height: 12),
              Text(
                _error!,
                style: TextStyle(
                  color: Theme.of(context).colorScheme.error,
                  fontSize: 12,
                ),
              ),
            ],
          ],
        ),
      ),
      actions: [
        TextButton(
          onPressed: _saving ? null : () => Navigator.of(context).pop(false),
          child: const Text('Cancel'),
        ),
        TextButton(
          onPressed: _saving ? null : () => _save(reset: true),
          child: const Text('Reset to default'),
        ),
        FilledButton(
          onPressed: _saving ? null : () => _save(reset: false),
          child: _saving
              ? const SizedBox(
                  width: 18,
                  height: 18,
                  child: CircularProgressIndicator(strokeWidth: 2),
                )
              : const Text('Save'),
        ),
      ],
    );
  }

  String? _validateMinutes(String? value) {
    final text = (value ?? '').trim();
    if (text.isEmpty) return 'Enter a number of minutes.';
    final minutes = int.tryParse(text);
    if (minutes == null || minutes < 0 || minutes > _maxMinutes) {
      return 'Use 0–$_maxMinutes minutes.';
    }
    return null;
  }
}

class _SquadTile extends StatelessWidget {
  const _SquadTile({
    required this.player,
    required this.busy,
    required this.onStatusSelected,
  });

  final Map<String, dynamic> player;
  final bool busy;
  final ValueChanged<String> onStatusSelected;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    final name = '${Json.string(player['first_name'])} '
            '${Json.string(player['last_name'])}'
        .trim();
    final status = Json.string(player['status']);
    final checkedInAt = Json.string(player['checked_in_at']);

    return ListTile(
      leading: _PlayerAvatar(player: player),
      title: Text(
        name.isEmpty ? 'Unknown player' : name,
        style: const TextStyle(fontWeight: FontWeight.w600),
      ),
      subtitle: Text(
        [
          if (Json.string(player['position']).isNotEmpty)
            Json.string(player['position']),
          if (checkedInAt.isNotEmpty) 'Recorded at ${_timeOf(checkedInAt)}',
        ].join(' · '),
        style: TextStyle(fontSize: 12, color: scheme.outline),
      ),
      trailing: PopupMenuButton<String>(
        enabled: !busy,
        tooltip: 'Set attendance',
        onSelected: onStatusSelected,
        itemBuilder: (context) => [
          for (final option in const ['present', 'late', 'absent', 'excused'])
            PopupMenuItem(
              value: option,
              child: Row(
                children: [
                  Icon(
                    Icons.circle,
                    size: 10,
                    color: StatusChip.colorFor(option, scheme),
                  ),
                  const SizedBox(width: 10),
                  Text(_titleCase(option)),
                ],
              ),
            ),
        ],
        child: status.isEmpty
            ? Container(
                padding:
                    const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
                decoration: BoxDecoration(
                  borderRadius: BorderRadius.circular(20),
                  border: Border.all(color: scheme.outlineVariant),
                ),
                child: Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Icon(Icons.add, size: 14, color: scheme.outline),
                    const SizedBox(width: 4),
                    Text(
                      'Mark',
                      style: TextStyle(fontSize: 12, color: scheme.outline),
                    ),
                  ],
                ),
              )
            : StatusChip(
                label: _titleCase(status),
                color: StatusChip.colorFor(status, scheme),
              ),
      ),
      onTap: Json.string(player['photo']).isEmpty
          ? null
          : () => _showPhoto(
                context,
                AppConfig.uploadUrl(Json.string(player['photo'])),
                name,
              ),
    );
  }
}

class _PlayerAvatar extends StatelessWidget {
  const _PlayerAvatar({required this.player});

  final Map<String, dynamic> player;

  @override
  Widget build(BuildContext context) {
    final photo = Json.string(player['photo']);
    final jersey = Json.string(player['jersey_number'], '—');

    return CircleAvatar(
      backgroundColor: Theme.of(context).colorScheme.primaryContainer,
      backgroundImage:
          photo.isEmpty ? null : NetworkImage(AppConfig.uploadUrl(photo)),
      child: photo.isEmpty
          ? Text(
              jersey,
              style: TextStyle(
                color: Theme.of(context).colorScheme.onPrimaryContainer,
                fontWeight: FontWeight.bold,
                fontSize: 13,
              ),
            )
          : null,
    );
  }
}

class _CheckInCard extends StatelessWidget {
  const _CheckInCard({
    required this.detail,
    required this.busy,
    required this.onCheckIn,
  });

  final _EventDetail detail;
  final bool busy;
  final VoidCallback onCheckIn;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    final event = detail.event;
    final attendance = detail.myAttendance;
    final isTraining = Json.string(event['type']) == 'training';
    final window = _CheckinWindow.fromEvent(event);
    final canCheckIn = window.isOpen;
    final status = attendance == null
        ? ''
        : Json.string(attendance['status']);
    final photo = attendance == null
        ? ''
        : Json.string(attendance['photo']);
    final checkedInAt = attendance == null
        ? ''
        : Json.string(attendance['checked_in_at']);

    if (!isTraining) {
      return Card(
        child: Padding(
          padding: const EdgeInsets.all(18),
          child: Row(
            children: [
              Icon(Icons.info_outline, color: scheme.outline),
              const SizedBox(width: 12),
              const Expanded(
                child: Text(
                  'Attendance check-in is only available for training '
                  'sessions.',
                ),
              ),
            ],
          ),
        ),
      );
    }

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(18),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Icon(
                  Icons.camera_front_outlined,
                  color: scheme.primary,
                ),
                const SizedBox(width: 8),
                const Expanded(
                  child: Text(
                    'Your attendance',
                    style: TextStyle(
                      fontWeight: FontWeight.w700,
                      fontSize: 15,
                    ),
                  ),
                ),
                if (status.isNotEmpty)
                  StatusChip(
                    label: _titleCase(status),
                    color: StatusChip.colorFor(status, scheme),
                  ),
              ],
            ),
            if (attendance != null) ...[
              const SizedBox(height: 12),
              Row(
                children: [
                  ClipRRect(
                    borderRadius: BorderRadius.circular(12),
                    child: photo.isEmpty
                        ? Container(
                            width: 72,
                            height: 72,
                            color: scheme.surfaceContainerHighest,
                            child: Icon(
                              Icons.person,
                              color: scheme.outline,
                            ),
                          )
                        : Image.network(
                            AppConfig.uploadUrl(photo),
                            width: 72,
                            height: 72,
                            fit: BoxFit.cover,
                            errorBuilder: (_, _, _) => Container(
                              width: 72,
                              height: 72,
                              color: scheme.surfaceContainerHighest,
                              child: Icon(
                                Icons.broken_image_outlined,
                                color: scheme.outline,
                              ),
                            ),
                          ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        const Text(
                          'Selfie received',
                          style: TextStyle(fontWeight: FontWeight.w600),
                        ),
                        const SizedBox(height: 2),
                        Text(
                          checkedInAt.isEmpty
                              ? '—'
                              : 'Recorded at ${_timeOf(checkedInAt)} on '
                                  '${Json.dateLabel(checkedInAt)}',
                          style: TextStyle(
                            fontSize: 12,
                            color: scheme.outline,
                          ),
                        ),
                        const SizedBox(height: 4),
                        Text(
                          'You can retake the selfie to refresh the recorded '
                          'time.',
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
            ] else ...[
              const SizedBox(height: 10),
              Text(
                'Take a selfie now to record your attendance and the exact '
                'time you checked in.',
                style: TextStyle(
                  fontSize: 13,
                  color: scheme.onSurfaceVariant,
                  height: 1.4,
                ),
              ),
            ],
            const SizedBox(height: 14),
            _CheckinWindowNotice(window: window),
            const SizedBox(height: 12),
            FilledButton.icon(
              onPressed: busy || !canCheckIn ? null : onCheckIn,
              icon: Icon(
                canCheckIn
                    ? Icons.camera_alt_outlined
                    : Icons.lock_clock_outlined,
              ),
              label: Text(
                !canCheckIn && window.opensAt != null
                    ? 'Check-in opens at ${_clock(window.opensAt!)}'
                    : window.isLateNow
                        ? 'Take selfie (late)'
                        : attendance == null
                            ? 'Take selfie & check in'
                            : 'Retake selfie',
              ),
            ),
          ],
        ),
      ),
    );
  }
}

/// Explains whether check-in is open, and whether it will be marked late.
class _CheckinWindowNotice extends StatelessWidget {
  const _CheckinWindowNotice({required this.window});

  final _CheckinWindow window;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    final Color color;
    final IconData icon;
    final String text;

    if (!window.isOpen && window.opensAt != null) {
      color = scheme.outline;
      icon = Icons.lock_clock_outlined;
      text = 'Check-in is not open yet. It opens at '
          '${_clock(window.opensAt!)} '
          '(${window.opensMinutes} min before the start).';
    } else if (window.isLateNow && window.lateAt != null) {
      color = Colors.orange.shade800;
      icon = Icons.warning_amber_outlined;
      text = 'Check-in is open, but your selfie will be recorded as late '
          '(after ${_clock(window.lateAt!)}).';
    } else {
      color = Colors.green.shade700;
      icon = Icons.check_circle_outline;
      text = window.lateAt == null
          ? 'Check-in is open.'
          : 'Check-in is open. Late after ${_clock(window.lateAt!)}.';
    }

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.09),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: color.withValues(alpha: 0.25)),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(icon, size: 17, color: color),
          const SizedBox(width: 8),
          Expanded(
            child: Text(
              text,
              style: TextStyle(
                fontSize: 12,
                color: color,
                height: 1.35,
                fontWeight: FontWeight.w600,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

Future<void> _showPhoto(
  BuildContext context,
  String url,
  String name,
) async {
  if (url.isEmpty) return;
  await showDialog<void>(
    context: context,
    builder: (ctx) => Dialog(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 14, 8, 6),
            child: Row(
              children: [
                Expanded(
                  child: Text(
                    name,
                    style: const TextStyle(fontWeight: FontWeight.w700),
                  ),
                ),
                IconButton(
                  onPressed: () => Navigator.of(ctx).pop(),
                  icon: const Icon(Icons.close),
                ),
              ],
            ),
          ),
          AspectRatio(
            aspectRatio: 3 / 4,
            child: Image.network(
              url,
              fit: BoxFit.cover,
              errorBuilder: (_, _, _) => const Center(
                child: Text('This photo could not be loaded.'),
              ),
            ),
          ),
        ],
      ),
    ),
  );
}

String _titleCase(String value) {
  if (value.isEmpty) return value;
  return '${value[0].toUpperCase()}${value.substring(1)}';
}

String _timeOf(String value) {
  final dt = Json.dateTime(value);
  if (dt == null) return value;
  return _clock(dt);
}

String _clock(DateTime dt) {
  final h = dt.hour.toString().padLeft(2, '0');
  final m = dt.minute.toString().padLeft(2, '0');
  return '$h:$m';
}
