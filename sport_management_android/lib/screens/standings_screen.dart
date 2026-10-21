import 'package:flutter/material.dart';

import '../models/json.dart';
import '../services/api_exception.dart';
import '../state/session_store.dart';
import '../widgets/common.dart';

/// League table for the active tournament.
class StandingsScreen extends StatefulWidget {
  const StandingsScreen({super.key, required this.session});

  final SessionStore session;

  @override
  State<StandingsScreen> createState() => _StandingsScreenState();
}

class _StandingsScreenState extends State<StandingsScreen> {
  late Future<Map<String, dynamic>> _future;

  @override
  void initState() {
    super.initState();
    _load();
  }

  void _load() {
    _future = widget.session.client.getMap('/standings');
  }

  @override
  Widget build(BuildContext context) {
    return FutureBuilder<Map<String, dynamic>>(
      future: _future,
      builder: (context, snapshot) {
        if (snapshot.connectionState == ConnectionState.waiting) {
          return const LoadingView(message: 'Loading standings…');
        }
        if (snapshot.hasError) {
          final error = snapshot.error;
          return ErrorRetryView(
            message: error is ApiException
                ? error.message
                : 'Could not load standings.',
            onRetry: () => setState(_load),
          );
        }

        final data = snapshot.data ?? const {};
        final tournament = data['tournament'] == null
            ? null
            : Json.map(data['tournament']);
        final rows = Json.list(data['standings']);

        if (rows.isEmpty) {
          return const EmptyView(
            message: 'No standings available yet.',
            icon: Icons.leaderboard_outlined,
          );
        }

        return RefreshIndicator(
          onRefresh: () async => setState(_load),
          child: ListView(
            padding: const EdgeInsets.all(12),
            children: [
              if (tournament != null)
                Padding(
                  padding: const EdgeInsets.fromLTRB(4, 4, 4, 12),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        Json.string(tournament['name'], 'Competition'),
                        style: const TextStyle(
                          fontSize: 17,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                      if (Json.string(tournament['status']).isNotEmpty)
                        Text(
                          'Status: ${Json.string(tournament['status'])}',
                          style: TextStyle(
                            color: Theme.of(context).colorScheme.outline,
                          ),
                        ),
                    ],
                  ),
                ),
              Card(
                clipBehavior: Clip.antiAlias,
                child: SingleChildScrollView(
                  scrollDirection: Axis.horizontal,
                  child: DataTable(
                    headingRowHeight: 44,
                    dataRowMinHeight: 44,
                    dataRowMaxHeight: 52,
                    columnSpacing: 18,
                    columns: const [
                      DataColumn(label: Text('#')),
                      DataColumn(label: Text('Team')),
                      DataColumn(label: Text('P'), numeric: true),
                      DataColumn(label: Text('W'), numeric: true),
                      DataColumn(label: Text('D'), numeric: true),
                      DataColumn(label: Text('L'), numeric: true),
                      DataColumn(label: Text('GD'), numeric: true),
                      DataColumn(label: Text('Pts'), numeric: true),
                    ],
                    rows: [
                      for (var i = 0; i < rows.length; i++)
                        _buildRow(context, i + 1, rows[i]),
                    ],
                  ),
                ),
              ),
            ],
          ),
        );
      },
    );
  }

  DataRow _buildRow(BuildContext context, int rank, Map<String, dynamic> row) {
    final scheme = Theme.of(context).colorScheme;
    final isTop = rank <= 3;

    return DataRow(
      cells: [
        DataCell(
          Text(
            Json.ordinal(rank),
            style: TextStyle(
              fontWeight: FontWeight.w700,
              color: isTop ? scheme.primary : null,
            ),
          ),
        ),
        DataCell(
          Text(
            Json.string(row['team_name'], '—'),
            style: const TextStyle(fontWeight: FontWeight.w600),
          ),
        ),
        DataCell(Text('${Json.intOr(row['played'])}')),
        DataCell(Text('${Json.intOr(row['won'])}')),
        DataCell(Text('${Json.intOr(row['drawn'])}')),
        DataCell(Text('${Json.intOr(row['lost'])}')),
        DataCell(Text('${Json.intOr(row['gd'])}')),
        DataCell(
          Text(
            '${Json.intOr(row['points'])}',
            style: const TextStyle(fontWeight: FontWeight.w800),
          ),
        ),
      ],
    );
  }
}
