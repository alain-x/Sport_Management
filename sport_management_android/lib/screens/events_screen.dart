import 'package:flutter/material.dart';

import '../models/json.dart';
import '../services/api_exception.dart';
import '../state/session_store.dart';
import '../widgets/common.dart';

/// Calendar events (training sessions, matches, meetings) with attendance
/// check-in for player accounts.
class EventsScreen extends StatefulWidget {
  const EventsScreen({super.key, required this.session});

  final SessionStore session;

  @override
  State<EventsScreen> createState() => _EventsScreenState();
}

class _EventsScreenState extends State<EventsScreen> {
  late Future<List<Map<String, dynamic>>> _future;

  @override
  void initState() {
    super.initState();
    _load();
  }

  void _load() {
    final now = DateTime.now();
    final from = DateTime(now.year, now.month, 1)
        .subtract(const Duration(days: 31));
    final to = DateTime(now.year, now.month + 2, 1);

    _future = widget.session.client
        .get('/events', query: {
          'from': _format(from),
          'to': _format(to),
        })
        .then((data) => Json.list(Json.map(data)['items']));
  }

  String _format(DateTime dt) =>
      '${dt.year}-${dt.month.toString().padLeft(2, '0')}-'
      '${dt.day.toString().padLeft(2, '0')} 00:00:00';

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Calendar & training')),
      body: FutureBuilder<List<Map<String, dynamic>>>(
        future: _future,
        builder: (context, snapshot) {
          if (snapshot.connectionState == ConnectionState.waiting) {
            return const LoadingView(message: 'Loading events…');
          }
          if (snapshot.hasError) {
            final error = snapshot.error;
            return ErrorRetryView(
              message: error is ApiException
                  ? error.message
                  : 'Could not load events.',
              onRetry: () => setState(_load),
            );
          }

          final events = snapshot.data ?? const [];
          if (events.isEmpty) {
            return const EmptyView(
              message: 'No events in this period.',
              icon: Icons.event_busy_outlined,
            );
          }

          return RefreshIndicator(
            onRefresh: () async => setState(_load),
            child: ListView.separated(
              itemCount: events.length,
              separatorBuilder: (_, _) => const Divider(height: 1),
              itemBuilder: (context, i) => _EventTile(
                session: widget.session,
                event: events[i],
                onChanged: () => setState(_load),
              ),
            ),
          );
        },
      ),
    );
  }
}

class _EventTile extends StatelessWidget {
  const _EventTile({
    required this.session,
    required this.event,
    required this.onChanged,
  });

  final SessionStore session;
  final Map<String, dynamic> event;
  final VoidCallback onChanged;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    final type = Json.string(event['type'], 'event');
    final isTraining = type == 'training';
    final isPlayer = session.user?.isPlayer ?? false;

    return ListTile(
      leading: CircleAvatar(
        backgroundColor: scheme.primaryContainer,
        child: Icon(
          isTraining ? Icons.fitness_center : Icons.event,
          color: scheme.onPrimaryContainer,
          size: 20,
        ),
      ),
      title: Text(
        Json.string(event['title'], 'Event'),
        style: const TextStyle(fontWeight: FontWeight.w600),
      ),
      subtitle: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const SizedBox(height: 2),
          Text(Json.dateTimeLabel(event['starts_at'])),
          Text(
            [
              Json.string(event['team_name']),
              Json.string(event['location']),
            ].where((s) => s.isNotEmpty).join(' · '),
            style: TextStyle(color: scheme.outline, fontSize: 12),
          ),
        ],
      ),
      trailing: isPlayer && isTraining
          ? FilledButton.tonal(
              onPressed: () => _checkIn(context),
              child: const Text('Check in'),
            )
          : StatusChip(label: type, color: scheme.primary),
    );
  }

  Future<void> _checkIn(BuildContext context) async {
    final id = Json.intOrNull(event['id']);
    if (id == null) return;

    final messenger = ScaffoldMessenger.of(context);
    try {
      await session.client.post('/events/$id/attendance', body: {
        'status': 'present',
      });
      messenger.showSnackBar(
        const SnackBar(content: Text('Attendance recorded.')),
      );
      onChanged();
    } on ApiException catch (error) {
      messenger.showSnackBar(SnackBar(content: Text(error.flatErrors)));
    }
  }
}
