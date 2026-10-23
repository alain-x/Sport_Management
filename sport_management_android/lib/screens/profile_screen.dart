import 'package:flutter/material.dart';

import '../config.dart';
import '../services/api_exception.dart';
import '../services/photo_picker.dart';
import '../state/session_store.dart';
import '../widgets/common.dart';

/// The signed-in user's own profile.
///
/// Every role can change their display name, upload a profile photo and set a
/// new password. The API only ever updates the account behind the bearer token,
/// so there is no way to touch somebody else's profile from here.
class ProfileScreen extends StatefulWidget {
  const ProfileScreen({super.key, required this.session});

  final SessionStore session;

  @override
  State<ProfileScreen> createState() => _ProfileScreenState();
}

class _ProfileScreenState extends State<ProfileScreen> {
  bool _busy = false;

  SessionStore get _session => widget.session;

  Future<void> _changePhoto() async {
    final path = await PhotoPicker().pick(context);
    if (path == null || !mounted) return;

    setState(() => _busy = true);
    final messenger = ScaffoldMessenger.of(context);
    try {
      await _session.uploadAvatar(path);
      if (!mounted) return;
      setState(() => _busy = false);
      messenger.showSnackBar(
        const SnackBar(content: Text('Profile photo updated.')),
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

  Future<void> _editName() async {
    final saved = await showDialog<bool>(
      context: context,
      builder: (_) => _NameDialog(session: _session),
    );
    if (saved != true || !mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(
      const SnackBar(content: Text('Profile updated.')),
    );
  }

  Future<void> _changePassword() async {
    final saved = await showDialog<bool>(
      context: context,
      builder: (_) => _PasswordDialog(session: _session),
    );
    if (saved != true || !mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(
      const SnackBar(content: Text('Password updated.')),
    );
  }

  Future<void> _signOut() async {
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
      await _session.signOut();
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('My profile')),
      body: AnimatedBuilder(
        animation: _session,
        builder: (context, _) {
          final user = _session.user;
          final scheme = Theme.of(context).colorScheme;

          return RefreshIndicator(
            onRefresh: _session.refreshUser,
            child: ListView(
              padding: const EdgeInsets.all(16),
              children: [
                if (_busy) ...[
                  const LinearProgressIndicator(minHeight: 2),
                  const SizedBox(height: 10),
                ],
                Card(
                  child: Padding(
                    padding: const EdgeInsets.all(20),
                    child: Column(
                      children: [
                        Stack(
                          alignment: Alignment.bottomRight,
                          children: [
                            UserAvatar(
                              photo: user?.photo,
                              initials: user?.initials ?? '?',
                              radius: 44,
                              backgroundColor: scheme.primary,
                              foregroundColor: scheme.onPrimary,
                            ),
                            Material(
                              color: scheme.primary,
                              shape: const CircleBorder(),
                              child: InkWell(
                                customBorder: const CircleBorder(),
                                onTap: _busy ? null : _changePhoto,
                                child: Padding(
                                  padding: const EdgeInsets.all(7),
                                  child: Icon(
                                    Icons.photo_camera_outlined,
                                    size: 18,
                                    color: scheme.onPrimary,
                                  ),
                                ),
                              ),
                            ),
                          ],
                        ),
                        const SizedBox(height: 8),
                        TextButton.icon(
                          onPressed: _busy ? null : _changePhoto,
                          icon: const Icon(Icons.upload_outlined, size: 18),
                          label: const Text('Change photo'),
                        ),
                        const SizedBox(height: 4),
                        Text(
                          user?.name ?? 'Unknown user',
                          style: const TextStyle(
                            fontSize: 19,
                            fontWeight: FontWeight.w700,
                          ),
                          textAlign: TextAlign.center,
                        ),
                        const SizedBox(height: 4),
                        Text(
                          user?.email ?? '',
                          style: TextStyle(color: scheme.outline),
                          textAlign: TextAlign.center,
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
                  child: Column(
                    children: [
                      ListTile(
                        leading: const Icon(Icons.badge_outlined),
                        title: const Text('Edit name'),
                        subtitle: Text(user?.name ?? '—'),
                        trailing: const Icon(Icons.chevron_right),
                        onTap: _busy ? null : _editName,
                      ),
                      const Divider(height: 1),
                      ListTile(
                        leading: const Icon(Icons.lock_outline),
                        title: const Text('Change password'),
                        subtitle: const Text('At least 8 characters'),
                        trailing: const Icon(Icons.chevron_right),
                        onTap: _busy ? null : _changePassword,
                      ),
                    ],
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
                        DetailRow(
                          label: 'API server',
                          value: AppConfig.apiBaseUrl,
                        ),
                      ],
                    ),
                  ),
                ),
                const SizedBox(height: 24),
                OutlinedButton.icon(
                  onPressed: _signOut,
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
        },
      ),
    );
  }
}

/// Dialog for editing the display name and phone number.
class _NameDialog extends StatefulWidget {
  const _NameDialog({required this.session});

  final SessionStore session;

  @override
  State<_NameDialog> createState() => _NameDialogState();
}

class _NameDialogState extends State<_NameDialog> {
  final _formKey = GlobalKey<FormState>();
  late final TextEditingController _name;
  late final TextEditingController _phone;
  bool _saving = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    final user = widget.session.user;
    _name = TextEditingController(text: user?.name ?? '');
    _phone = TextEditingController(text: user?.phone ?? '');
  }

  @override
  void dispose() {
    _name.dispose();
    _phone.dispose();
    super.dispose();
  }

  Future<void> _save() async {
    if (!(_formKey.currentState?.validate() ?? false)) return;

    setState(() {
      _saving = true;
      _error = null;
    });

    try {
      await widget.session.updateProfile(
        name: _name.text.trim(),
        phone: _phone.text.trim(),
      );
      if (!mounted) return;
      Navigator.of(context).pop(true);
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
        _error = 'Could not update your profile: $error';
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    return AlertDialog(
      scrollable: true,
      title: const Text('Edit name'),
      content: Form(
        key: _formKey,
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            TextFormField(
              controller: _name,
              autofocus: true,
              textCapitalization: TextCapitalization.words,
              decoration: const InputDecoration(labelText: 'Full name'),
              validator: (value) {
                if ((value ?? '').trim().length < 2) {
                  return 'Enter at least 2 characters.';
                }
                return null;
              },
            ),
            const SizedBox(height: 12),
            TextFormField(
              controller: _phone,
              keyboardType: TextInputType.phone,
              decoration: const InputDecoration(
                labelText: 'Phone (optional)',
              ),
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
        FilledButton(
          onPressed: _saving ? null : _save,
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
}

/// Dialog for changing the account password.
class _PasswordDialog extends StatefulWidget {
  const _PasswordDialog({required this.session});

  final SessionStore session;

  @override
  State<_PasswordDialog> createState() => _PasswordDialogState();
}

class _PasswordDialogState extends State<_PasswordDialog> {
  final _formKey = GlobalKey<FormState>();
  final _current = TextEditingController();
  final _next = TextEditingController();
  final _confirmation = TextEditingController();
  bool _saving = false;
  String? _error;

  @override
  void dispose() {
    _current.dispose();
    _next.dispose();
    _confirmation.dispose();
    super.dispose();
  }

  Future<void> _save() async {
    if (!(_formKey.currentState?.validate() ?? false)) return;

    setState(() {
      _saving = true;
      _error = null;
    });

    try {
      await widget.session.changePassword(
        currentPassword: _current.text,
        newPassword: _next.text,
        confirmation: _confirmation.text,
      );
      if (!mounted) return;
      Navigator.of(context).pop(true);
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
        _error = 'Could not change your password: $error';
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    return AlertDialog(
      scrollable: true,
      title: const Text('Change password'),
      content: Form(
        key: _formKey,
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            TextFormField(
              controller: _current,
              obscureText: true,
              autofocus: true,
              decoration: const InputDecoration(
                labelText: 'Current password',
              ),
              validator: (value) => (value ?? '').isEmpty
                  ? 'Enter your current password.'
                  : null,
            ),
            const SizedBox(height: 12),
            TextFormField(
              controller: _next,
              obscureText: true,
              decoration: const InputDecoration(
                labelText: 'New password',
                helperText: 'At least 8 characters',
              ),
              validator: (value) {
                if ((value ?? '').length < 8) {
                  return 'Use at least 8 characters.';
                }
                if (value == _current.text) {
                  return 'Choose a different password.';
                }
                return null;
              },
            ),
            const SizedBox(height: 12),
            TextFormField(
              controller: _confirmation,
              obscureText: true,
              decoration: const InputDecoration(
                labelText: 'Confirm new password',
              ),
              validator: (value) => value != _next.text
                  ? 'The passwords do not match.'
                  : null,
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
        FilledButton(
          onPressed: _saving ? null : _save,
          child: _saving
              ? const SizedBox(
                  width: 18,
                  height: 18,
                  child: CircularProgressIndicator(strokeWidth: 2),
                )
              : const Text('Update'),
        ),
      ],
    );
  }
}
