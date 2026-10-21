import 'package:flutter/material.dart';

import '../config.dart';
import '../state/session_store.dart';
import '../widgets/common.dart';

/// Account details and sign-out.
class ProfileScreen extends StatelessWidget {
  const ProfileScreen({super.key, required this.session});

  final SessionStore session;

  @override
  Widget build(BuildContext context) {
    final user = session.user;
    final scheme = Theme.of(context).colorScheme;

    return Scaffold(
      appBar: AppBar(title: const Text('My profile')),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Card(
            child: Padding(
              padding: const EdgeInsets.all(20),
              child: Column(
                children: [
                  CircleAvatar(
                    radius: 40,
                    backgroundColor: scheme.primary,
                    child: Text(
                      user?.initials ?? '?',
                      style: const TextStyle(
                        color: Colors.white,
                        fontSize: 26,
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                  ),
                  const SizedBox(height: 14),
                  Text(
                    user?.name ?? 'Unknown user',
                    style: const TextStyle(
                      fontSize: 19,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    user?.email ?? '',
                    style: TextStyle(color: scheme.outline),
                  ),
                  const SizedBox(height: 10),
                  Chip(
                    label: Text(user?.roleLabel ?? 'User'),
                    backgroundColor: scheme.primaryContainer,
                  ),
                ],
              ),
            ),
          ),
          const SizedBox(height: 12),
          Card(
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Text('Account',
                      style: TextStyle(fontWeight: FontWeight.w700)),
                  const Divider(),
                  DetailRow(
                    label: 'Team',
                    value: user?.teamName ?? '—',
                  ),
                  DetailRow(
                    label: 'Team code',
                    value: user?.teamCode ?? '—',
                  ),
                  DetailRow(
                    label: 'Player ID',
                    value: user?.playerId?.toString() ?? '—',
                  ),
                  DetailRow(
                    label: 'Phone',
                    value: user?.phone ?? '—',
                  ),
                ],
              ),
            ),
          ),
          const SizedBox(height: 12),
          Card(
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Text('Connection',
                      style: TextStyle(fontWeight: FontWeight.w700)),
                  const Divider(),
                  DetailRow(label: 'API server', value: AppConfig.apiBaseUrl),
                ],
              ),
            ),
          ),
          const SizedBox(height: 24),
          OutlinedButton.icon(
            onPressed: () async {
              final confirmed = await showDialog<bool>(
                context: context,
                builder: (ctx) => AlertDialog(
                  title: const Text('Sign out?'),
                  content: const Text(
                    'You will need to sign in again to use the app.',
                  ),
                  actions: [
                    TextButton(
                      onPressed: () => Navigator.of(ctx).pop(false),
                      child: const Text('Cancel'),
                    ),
                    FilledButton(
                      onPressed: () => Navigator.of(ctx).pop(true),
                      child: const Text('Sign out'),
                    ),
                  ],
                ),
              );

              if (confirmed == true) {
                await session.signOut();
              }
            },
            icon: const Icon(Icons.logout),
            style: OutlinedButton.styleFrom(
              foregroundColor: scheme.error,
              minimumSize: const Size.fromHeight(48),
            ),
            label: const Text('Sign out'),
          ),
        ],
      ),
    );
  }
}
