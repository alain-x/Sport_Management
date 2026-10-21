import 'package:flutter/material.dart';

import '../state/session_store.dart';
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
class HomeShell extends StatefulWidget {
  const HomeShell({super.key, required this.session});

  final SessionStore session;

  @override
  State<HomeShell> createState() => _HomeShellState();
}

class _HomeShellState extends State<HomeShell> {
  int _index = 0;

  List<_Destination> get _destinations => [
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
          label: 'Standings',
          icon: Icons.leaderboard_outlined,
          selectedIcon: Icons.leaderboard,
          builder: (_) => StandingsScreen(session: widget.session),
        ),
        _Destination(
          label: 'Teams',
          icon: Icons.groups_outlined,
          selectedIcon: Icons.groups,
          builder: (_) => TeamsScreen(session: widget.session),
        ),
        _Destination(
          label: 'More',
          icon: Icons.more_horiz,
          selectedIcon: Icons.more_horiz,
          builder: (_) => _MoreScreen(session: widget.session),
        ),
      ];

  @override
  Widget build(BuildContext context) {
    final destinations = _destinations;

    return Scaffold(
      appBar: AppBar(
        title: Text(destinations[_index].label),
        actions: [
          IconButton(
            tooltip: 'Chat',
            icon: const Icon(Icons.forum_outlined),
            onPressed: () => Navigator.of(context).push(
              MaterialPageRoute(
                builder: (_) => ChatScreen(session: widget.session),
              ),
            ),
          ),
          IconButton(
            tooltip: 'Profile',
            icon: const Icon(Icons.person_outline),
            onPressed: () => Navigator.of(context).push(
              MaterialPageRoute(
                builder: (_) => ProfileScreen(session: widget.session),
              ),
            ),
          ),
        ],
      ),
      body: IndexedStack(
        index: _index,
        children: [
          for (final d in destinations) d.builder(context),
        ],
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

/// Groups the remaining modules (players, tournaments, events, chat, profile)
/// behind the "More" tab.
class _MoreScreen extends StatelessWidget {
  const _MoreScreen({required this.session});

  final SessionStore session;

  @override
  Widget build(BuildContext context) {
    final entries = <_MoreEntry>[
      _MoreEntry(
        title: 'Players',
        subtitle: 'Squads and player profiles',
        icon: Icons.person_search_outlined,
        builder: (_) => PlayersScreen(session: session),
      ),
      _MoreEntry(
        title: 'Tournaments',
        subtitle: 'Competitions and their fixtures',
        icon: Icons.emoji_events_outlined,
        builder: (_) => TournamentsScreen(session: session),
      ),
      _MoreEntry(
        title: 'Calendar & training',
        subtitle: 'Events and attendance check-in',
        icon: Icons.event_available_outlined,
        builder: (_) => EventsScreen(session: session),
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

    return ListView.separated(
      padding: const EdgeInsets.symmetric(vertical: 8),
      itemCount: entries.length,
      separatorBuilder: (_, _) => const Divider(height: 1),
      itemBuilder: (context, i) {
        final entry = entries[i];
        return ListTile(
          leading: Icon(entry.icon),
          title: Text(entry.title),
          subtitle: Text(entry.subtitle),
          trailing: const Icon(Icons.chevron_right),
          onTap: () => Navigator.of(context).push(
            MaterialPageRoute(builder: entry.builder),
          ),
        );
      },
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
