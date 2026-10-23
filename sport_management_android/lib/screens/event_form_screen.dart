import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../models/json.dart';
import '../services/api_exception.dart';
import '../state/session_store.dart';

/// Create or edit a calendar entry (training session, meeting, travel...).
///
/// Team managers can only register events for their own team; the API enforces
/// that as well. Administrators may pick any team or a league-wide event.
class EventFormScreen extends StatefulWidget {
  const EventFormScreen({
    super.key,
    required this.session,
    this.event,
    this.categories = const [],
  });

  final SessionStore session;
  final Map<String, dynamic>? event;
  final List<String> categories;

  @override
  State<EventFormScreen> createState() => _EventFormScreenState();
}

class _EventFormScreenState extends State<EventFormScreen> {
  final _formKey = GlobalKey<FormState>();
  late final TextEditingController _title;
  late final TextEditingController _description;
  late final TextEditingController _opensMinutes;
  late final TextEditingController _lateMinutes;

  static const int _defaultOpensMinutes = 20;
  static const int _defaultLateMinutes = 10;
  static const int _maxMinutes = 180;

  late Future<Map<String, dynamic>> _optionsFuture;
  String _type = 'training';
  DateTime _startsAt = DateTime.now().add(const Duration(days: 1));
  int? _teamId;
  int? _tournamentId;
  bool _saving = false;
  Map<String, List<String>> _errors = const {};

  bool get _isEditing => widget.event != null;
  bool get _isAdmin => widget.session.user?.isAdmin ?? false;

  @override
  void initState() {
    super.initState();
    final event = widget.event;
    _title = TextEditingController(text: Json.string(event?['title']));
    _description =
        TextEditingController(text: Json.string(event?['description']));
    _type = Json.string(event?['type'], widget.categories.isNotEmpty
        ? widget.categories.first
        : 'training');
    _startsAt = Json.dateTime(event?['starts_at']) ??
        DateTime.now().add(const Duration(days: 1));
    _teamId = Json.intOrNull(event?['team_id']);
    _tournamentId = Json.intOrNull(event?['tournament_id']);
    _opensMinutes = TextEditingController(
      text: '${Json.intOr(event?['checkin_opens_minutes'], _defaultOpensMinutes)}',
    );
    _lateMinutes = TextEditingController(
      text: '${Json.intOr(event?['checkin_late_minutes'], _defaultLateMinutes)}',
    );
    _optionsFuture = widget.session.client.getMap('/event-options');
  }

  @override
  void dispose() {
    _title.dispose();
    _description.dispose();
    _opensMinutes.dispose();
    _lateMinutes.dispose();
    super.dispose();
  }

  Future<void> _pickDate() async {
    final picked = await showDatePicker(
      context: context,
      initialDate: _startsAt,
      firstDate: DateTime(2020),
      lastDate: DateTime(2100),
    );
    if (picked == null || !mounted) return;

    final time = await showTimePicker(
      context: context,
      initialTime: TimeOfDay.fromDateTime(_startsAt),
    );
    if (!mounted) return;

    setState(() {
      _startsAt = DateTime(
        picked.year,
        picked.month,
        picked.day,
        time?.hour ?? _startsAt.hour,
        time?.minute ?? _startsAt.minute,
      );
    });
  }

  Future<void> _save() async {
    setState(() => _errors = const {});
    if (!(_formKey.currentState?.validate() ?? false)) return;

    setState(() => _saving = true);
    final payload = <String, dynamic>{
      'title': _title.text.trim(),
      'type': _type,
      'starts_at': _format(_startsAt),
      'checkin_opens_minutes':
          int.tryParse(_opensMinutes.text.trim()) ?? _defaultOpensMinutes,
      'checkin_late_minutes':
          int.tryParse(_lateMinutes.text.trim()) ?? _defaultLateMinutes,
      'team_id': _teamId,
      'tournament_id': _tournamentId,
      'description': _description.text.trim(),
    };

    try {
      if (_isEditing) {
        await widget.session.client
            .post('/events/${widget.event!['id']}', body: payload);
      } else {
        await widget.session.client.post('/events', body: payload);
      }

      if (!mounted) return;
      Navigator.of(context).pop(true);
    } on ApiException catch (error) {
      if (!mounted) return;
      setState(() {
        _errors = error.errors;
        _saving = false;
      });
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(error.flatErrors)),
      );
    } catch (_) {
      if (!mounted) return;
      setState(() => _saving = false);
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Could not save the event.')),
      );
    }
  }

  String? _errorFor(String field) {
    final list = _errors[field];
    if (list == null || list.isEmpty) return null;
    return list.first;
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text(_isEditing ? 'Edit event' : 'New event'),
      ),
      body: FutureBuilder<Map<String, dynamic>>(
        future: _optionsFuture,
        builder: (context, snapshot) {
          if (snapshot.connectionState == ConnectionState.waiting) {
            return const Center(child: CircularProgressIndicator());
          }

          final options = snapshot.data ?? const <String, dynamic>{};
          final categories = _categoryOptions(options);
          if (!categories.contains(_type)) {
            _type = categories.first;
          }
          final teams = Json.list(options['teams']);
          final tournaments = Json.list(options['tournaments']);
          final ownTeam = Json.map(options['team']);
          final ownTeamName = Json.string(
            ownTeam['name'],
            widget.session.user?.teamName ?? 'Your team',
          );

          return Form(
            key: _formKey,
            child: ListView(
              padding: const EdgeInsets.fromLTRB(16, 12, 16, 32),
              children: [
                TextFormField(
                  controller: _title,
                  textCapitalization: TextCapitalization.sentences,
                  decoration: InputDecoration(
                    labelText: 'Title',
                    hintText: 'e.g. Speed & conditioning session',
                    errorText: _errorFor('title'),
                  ),
                  validator: (value) {
                    final text = (value ?? '').trim();
                    if (text.length < 3) {
                      return 'Give the event a title (3+ characters).';
                    }
                    return null;
                  },
                ),
                const SizedBox(height: 14),
                DropdownButtonFormField<String>(
                  initialValue: _type,
                  decoration: InputDecoration(
                    labelText: 'Category',
                    errorText: _errorFor('type'),
                  ),
                  items: [
                    for (final category in categories)
                      DropdownMenuItem(
                        value: category,
                        child: Text(_titleCase(category)),
                      ),
                  ],
                  onChanged: (value) {
                    if (value != null) setState(() => _type = value);
                  },
                ),
                const SizedBox(height: 14),
                InkWell(
                  borderRadius: BorderRadius.circular(14),
                  onTap: _pickDate,
                  child: InputDecorator(
                    decoration: InputDecoration(
                      labelText: 'Date & time',
                      errorText: _errorFor('starts_at'),
                      suffixIcon: const Icon(Icons.calendar_month_outlined),
                    ),
                    child: Text(
                      '${Json.dateLabel(_format(_startsAt))} · '
                      '${_startsAt.hour.toString().padLeft(2, '0')}:'
                      '${_startsAt.minute.toString().padLeft(2, '0')}',
                      style: const TextStyle(fontWeight: FontWeight.w600),
                    ),
                  ),
                ),
                const SizedBox(height: 18),
                Text(
                  'Check-in window',
                  style: TextStyle(
                    fontWeight: FontWeight.w700,
                    fontSize: 14,
                    color: Theme.of(context).colorScheme.primary,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  'Training attendance opens this many minutes before the '
                  'start; checking in later than the grace period is recorded '
                  'as late.',
                  style: TextStyle(
                    fontSize: 12,
                    color: Theme.of(context).colorScheme.outline,
                    height: 1.35,
                  ),
                ),
                const SizedBox(height: 10),
                Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Expanded(
                      child: TextFormField(
                        controller: _opensMinutes,
                        keyboardType: TextInputType.number,
                        inputFormatters: [
                          FilteringTextInputFormatter.digitsOnly,
                        ],
                        decoration: InputDecoration(
                          labelText: 'Opens before',
                          suffixText: 'min',
                          errorText: _errorFor('checkin_opens_minutes'),
                        ),
                        validator: (value) => _validateMinutes(value),
                      ),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: TextFormField(
                        controller: _lateMinutes,
                        keyboardType: TextInputType.number,
                        inputFormatters: [
                          FilteringTextInputFormatter.digitsOnly,
                        ],
                        decoration: InputDecoration(
                          labelText: 'Late after',
                          suffixText: 'min',
                          errorText: _errorFor('checkin_late_minutes'),
                        ),
                        validator: (value) => _validateMinutes(value),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 14),
                if (_isAdmin)
                  DropdownButtonFormField<int?>(
                    initialValue: _teamId,
                    decoration: InputDecoration(
                      labelText: 'Team',
                      errorText: _errorFor('team_id'),
                    ),
                    items: [
                      const DropdownMenuItem<int?>(
                        value: null,
                        child: Text('League-wide (all teams)'),
                      ),
                      for (final team in teams)
                        DropdownMenuItem<int?>(
                          value: Json.intOrNull(team['id']),
                          child: Text(Json.string(team['name'], 'Team')),
                        ),
                    ],
                    onChanged: (value) => setState(() => _teamId = value),
                  )
                else
                  InputDecorator(
                    decoration: const InputDecoration(labelText: 'Team'),
                    child: Row(
                      children: [
                        const Icon(Icons.lock_outline, size: 16),
                        const SizedBox(width: 8),
                        Expanded(
                          child: Text(
                            ownTeamName,
                            style:
                                const TextStyle(fontWeight: FontWeight.w600),
                          ),
                        ),
                      ],
                    ),
                  ),
                const SizedBox(height: 14),
                DropdownButtonFormField<int?>(
                  initialValue: _tournamentId,
                  decoration: const InputDecoration(
                    labelText: 'Tournament (optional)',
                  ),
                  items: [
                    const DropdownMenuItem<int?>(
                      value: null,
                      child: Text('None'),
                    ),
                    for (final tournament in tournaments)
                      DropdownMenuItem<int?>(
                        value: Json.intOrNull(tournament['id']),
                        child: Text(Json.string(tournament['name'], 'Tournament')),
                      ),
                  ],
                  onChanged: (value) => setState(() => _tournamentId = value),
                ),
                const SizedBox(height: 14),
                TextFormField(
                  controller: _description,
                  maxLines: 4,
                  textCapitalization: TextCapitalization.sentences,
                  decoration: InputDecoration(
                    labelText: 'Notes (optional)',
                    alignLabelWithHint: true,
                    errorText: _errorFor('description'),
                  ),
                ),
                const SizedBox(height: 24),
                FilledButton.icon(
                  onPressed: _saving ? null : _save,
                  icon: _saving
                      ? const SizedBox(
                          width: 18,
                          height: 18,
                          child: CircularProgressIndicator(strokeWidth: 2),
                        )
                      : const Icon(Icons.check),
                  label: Text(_isEditing ? 'Save changes' : 'Create event'),
                ),
              ],
            ),
          );
        },
      ),
    );
  }

  String? _validateMinutes(String? value) {
    final text = (value ?? '').trim();
    if (text.isEmpty) return null;
    final minutes = int.tryParse(text);
    if (minutes == null || minutes < 0 || minutes > _maxMinutes) {
      return 'Use 0–$_maxMinutes minutes.';
    }
    return null;
  }

  List<String> _categoryOptions(Map<String, dynamic> options) {
    final raw = options['categories'];
    final fromApi = raw is List
        ? raw.map((value) => '$value').where((value) => value.isNotEmpty).toList()
        : <String>[];
    if (fromApi.isNotEmpty) return fromApi;
    if (widget.categories.isNotEmpty) return widget.categories;
    return const ['training', 'meeting', 'travel', 'deadline', 'media', 'other'];
  }

  String _titleCase(String value) {
    if (value.isEmpty) return value;
    return '${value[0].toUpperCase()}${value.substring(1)}';
  }

  String _format(DateTime dt) {
    final month = dt.month.toString().padLeft(2, '0');
    final day = dt.day.toString().padLeft(2, '0');
    final hour = dt.hour.toString().padLeft(2, '0');
    final minute = dt.minute.toString().padLeft(2, '0');
    return '${dt.year}-$month-$day $hour:$minute:00';
  }
}
