import 'package:flutter/material.dart';

import '../models/app_user.dart';
import '../state/session_store.dart';
import '../widgets/common.dart';
import 'chat_screen.dart';
import 'dashboard_screen.dart';
import 'events_screen.dart';
import 'matches_screen.dart';
import 'players_screen.dart';
import 'profile_screen.dart';
import 'standings_screen.dart';
import 'teams_screen.dart';
import 'tournaments_screen.dart';

/// The signed-in shell: a title bar, bottom navigation and the module screens.
///
/// Navigation is role-aware. Everyone gets Home / Matches / Events / Squad,
/// while the "More" tab only exposes modules the signed-in role can use.
class HomeShell extends StatefulWidget {
  const HomeShell({super.key, required this.session});

  final SessionStore session;

  @override
  State<HomeShell> createState() => _HomeShellState();
}

class _HomeShellState extends State<HomeShell> {
  int _index = 0;

  void _open(WidgetBuilder builder) {
    Navigator.of(context).push(MaterialPageRoute(builder: builder));
  }

  @override
  Widget build(BuildContext context) {
    final user = widget.session.user;
    final isPlayer = user?.isPlayer ?? false;

    final destinations = <_Destination>[
      _Destination(
        label: 'Home',
        icon: Icons.dashboard_outlined,
        selectedIcon: Icons.dashboard,
        builder: (_) => DashboardScreen(session: widget.session),
      ),
      _Destination(
        label: 'Matches',
        icon: Icons.sports_soccer_outlined,
        selectedIcon: Icons.sports_soccer,
        builder: (_) => MatchesScreen(session: widget.session),
      ),
      _Destination(
        label: 'Events',
        icon: Icons.event_available_outlined,
        selectedIcon: Icons.event_available,
        builder: (_) => EventsScreen(session: widget.session, embedded: true),
      ),
      _Destination(
        label: isPlayer ? 'Squad' : 'Players',
        icon: Icons.groups_outlined,
        selectedIcon: Icons.groups,
        builder: (_) => PlayersScreen(session: widget.session, embedded: true),
      ),
      _Destination(
        label: 'More',
        icon: Icons.grid_view_outlined,
        selectedIcon: Icons.grid_view,
        builder: (_) => _MoreScreen(session: widget.session),
      ),
    ];

    final current = destinations[_index];

    return Scaffold(
      appBar: AppBar(
        title: Row(
          children: [
            Container(
              width: 34,
              height: 34,
              decoration: BoxDecoration(
                color: Theme.of(context).colorScheme.primary,
                borderRadius: BorderRadius.circular(10),
              ),
              child: const Icon(
                Icons.sports_rugby,
                color: Colors.white,
                size: 20,
              ),
            ),
            const SizedBox(width: 10),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                mainAxisSize: MainAxisSize.min,
                children: [
                  Text(
                    _index == 0 ? 'Sport Manager' : current.label,
                    style: const TextStyle(
                      fontWeight: FontWeight.w700,
                      fontSize: 18,
                    ),
                  ),
                  if (_index == 0)
                    Text(
                      _subtitle(user),
                      style: TextStyle(
                        fontSize: 12,
                        color: Theme.of(context).colorScheme.outline,
                        fontWeight: FontWeight.w400,
                      ),
                    ),
                ],
              ),
            ),
          ],
        ),
        actions: [
          IconButton(
            tooltip: 'Chat',
            icon: const Icon(Icons.forum_outlined),
            onPressed: () => _open((_) => ChatScreen(session: widget.session)),
          ),
          Padding(
            padding: const EdgeInsets.only(right: 8),
            child: InkWell(
              borderRadius: BorderRadius.circular(24),
              onTap: () =>
                  _open((_) => ProfileScreen(session: widget.session)),
              child: UserAvatar(
                photo: user?.photo,
                initials: user?.initials ?? '?',
                radius: 17,
                backgroundColor:
                    Theme.of(context).colorScheme.primaryContainer,
                foregroundColor:
                    Theme.of(context).colorScheme.onPrimaryContainer,
              ),
            ),
          ),
        ],
      ),
      body: IndexedStack(
        index: _index,
        children: [for (final d in destinations) d.builder(context)],
      ),
      bottomNavigationBar: NavigationBar(
        selectedIndex: _index,
        onDestinationSelected: (i) => setState(() => _index = i),
        destinations: [
          for (final d in destinations)
            NavigationDestination(
              icon: Icon(d.icon),
              selectedIcon: Icon(d.selectedIcon),
              label: d.label,
            ),
        ],
      ),
    );
  }

  String _subtitle(AppUser? user) {
    if (user == null) return '';
    return [
      user.roleLabel,
      if (user.teamName != null && user.teamName!.isNotEmpty) user.teamName!,
    ].join(' · ');
  }
}

class _Destination {
  _Destination({
    required this.label,
    required this.icon,
    required this.selectedIcon,
    required this.builder,
  });

  final String label;
  final IconData icon;
  final IconData selectedIcon;
  final WidgetBuilder builder;
}

/// Groups the remaining modules behind the "More" tab, filtered by role.
class _MoreScreen extends StatelessWidget {
  const _MoreScreen({required this.session});

  final SessionStore session;

  @override
  Widget build(BuildContext context) {
    final user = session.user;
    final entries = <_MoreEntry>[
      if (user?.isAdmin ?? false)
        _MoreEntry(
          title: 'Teams',
          subtitle: 'All registered clubs and their squads',
          icon: Icons.groups_2_outlined,
          builder: (_) => TeamsScreen(session: session),
        ),
      if (user?.isManager ?? false)
        _MoreEntry(
          title: 'My team',
          subtitle: '${user?.teamName ?? 'Your club'} squad and staff',
          icon: Icons.shield_outlined,
          builder: (_) => TeamsScreen(session: session),
        ),
      _MoreEntry(
        title: 'Tournaments',
        subtitle: 'Competitions and their fixtures',
        icon: Icons.emoji_events_outlined,
        builder: (_) => TournamentsScreen(session: session),
      ),
      _MoreEntry(
        title: 'Standings',
        subtitle: 'League tables for active competitions',
        icon: Icons.leaderboard_outlined,
        builder: (_) => StandingsScreen(session: session),
      ),
      _MoreEntry(
        title: 'Chat',
        subtitle: 'Team and group conversations',
        icon: Icons.forum_outlined,
        builder: (_) => ChatScreen(session: session),
      ),
      _MoreEntry(
        title: 'My profile',
        subtitle: 'Account details and sign out',
        icon: Icons.account_circle_outlined,
        builder: (_) => ProfileScreen(session: session),
      ),
    ];

    return ListView(
      padding: const EdgeInsets.fromLTRB(16, 8, 16, 24),
      children: [
        Padding(
          padding: const EdgeInsets.only(left: 4, bottom: 12),
          child: Text(
            'Manage',
            style: TextStyle(
              fontWeight: FontWeight.w700,
              fontSize: 13,
              letterSpacing: 0.6,
              color: Theme.of(context).colorScheme.outline,
            ),
          ),
        ),
        for (final entry in entries)
          Padding(
            padding: const EdgeInsets.only(bottom: 10),
            child: Card(
              child: ListTile(
                leading: CircleAvatar(
                  backgroundColor:
                      Theme.of(context).colorScheme.primaryContainer,
                  child: Icon(
                    entry.icon,
                    color: Theme.of(context).colorScheme.onPrimaryContainer,
                    size: 20,
                  ),
                ),
                title: Text(
                  entry.title,
                  style: const TextStyle(fontWeight: FontWeight.w600),
                ),
                subtitle: Text(entry.subtitle),
                trailing: const Icon(Icons.chevron_right),
                onTap: () => Navigator.of(context).push(
                  MaterialPageRoute(builder: entry.builder),
                ),
              ),
            ),
          ),
      ],
    );
  }
}

class _MoreEntry {
  _MoreEntry({
    required this.title,
    required this.subtitle,
    required this.icon,
    required this.builder,
  });

  final String title;
  final String subtitle;
  final IconData icon;
  final WidgetBuilder builder;
}
