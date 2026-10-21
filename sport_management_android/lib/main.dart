import 'package:flutter/material.dart';

import 'screens/home_shell.dart';
import 'screens/login_screen.dart';
import 'state/session_store.dart';

void main() {
  runApp(const SportManagementApp());
}

/// Root of the Sport Management mobile client.
///
/// Authentication state lives in a single [SessionStore]; this widget swaps
/// between a splash, the login screen and the signed-in shell as it changes.
class SportManagementApp extends StatefulWidget {
  const SportManagementApp({super.key});

  @override
  State<SportManagementApp> createState() => _SportManagementAppState();
}

class _SportManagementAppState extends State<SportManagementApp> {
  final SessionStore _session = SessionStore();

  @override
  void initState() {
    super.initState();
    _session.bootstrap();
  }

  @override
  void dispose() {
    _session.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'Sport Manager',
      debugShowCheckedModeBanner: false,
      theme: _buildTheme(Brightness.light),
      darkTheme: _buildTheme(Brightness.dark),
      home: AnimatedBuilder(
        animation: _session,
        builder: (context, _) {
          switch (_session.status) {
            case SessionStatus.unknown:
              return const _SplashScreen();
            case SessionStatus.signedOut:
              return LoginScreen(session: _session);
            case SessionStatus.signedIn:
              return HomeShell(session: _session);
          }
        },
      ),
    );
  }

  ThemeData _buildTheme(Brightness brightness) {
    final scheme = ColorScheme.fromSeed(
      seedColor: const Color(0xFF1565C0),
      brightness: brightness,
    );

    return ThemeData(
      useMaterial3: true,
      colorScheme: scheme,
      scaffoldBackgroundColor: scheme.surface,
      appBarTheme: AppBarTheme(
        backgroundColor: scheme.surface,
        foregroundColor: scheme.onSurface,
        elevation: 0,
        scrolledUnderElevation: 2,
        centerTitle: false,
      ),
      cardTheme: CardThemeData(
        elevation: 0,
        margin: EdgeInsets.zero,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(14),
          side: BorderSide(color: scheme.outlineVariant),
        ),
      ),
      inputDecorationTheme: const InputDecorationTheme(
        border: OutlineInputBorder(),
      ),
    );
  }
}

/// Shown for the brief moment while the stored token is validated.
class _SplashScreen extends StatelessWidget {
  const _SplashScreen();

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    return Scaffold(
      body: Center(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(Icons.sports_rugby, size: 64, color: scheme.primary),
            const SizedBox(height: 20),
            const Text(
              'Sport Manager',
              style: TextStyle(fontSize: 22, fontWeight: FontWeight.w700),
            ),
            const SizedBox(height: 24),
            const SizedBox(
              width: 28,
              height: 28,
              child: CircularProgressIndicator(strokeWidth: 3),
            ),
          ],
        ),
      ),
    );
  }
}
