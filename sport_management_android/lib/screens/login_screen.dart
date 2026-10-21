import 'package:flutter/material.dart';

import '../config.dart';
import '../services/api_exception.dart';
import '../state/session_store.dart';
import '../widgets/error_popup.dart';

/// Sign-in screen.
///
/// On a password mismatch the server answers 422; we pop a prominent error
/// banner for 3 seconds (auto-dismissing) while leaving an inline message
/// under the password field so the reason is still visible afterwards.
class LoginScreen extends StatefulWidget {
  const LoginScreen({super.key, required this.session});

  final SessionStore session;

  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> {
  final _formKey = GlobalKey<FormState>();
  final _emailController = TextEditingController();
  final _passwordController = TextEditingController();
  final _passwordFocus = FocusNode();

  bool _obscure = true;
  bool _submitting = false;
  String? _inlineError;

  @override
  void dispose() {
    _emailController.dispose();
    _passwordController.dispose();
    _passwordFocus.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    FocusScope.of(context).unfocus();
    setState(() => _inlineError = null);

    if (!(_formKey.currentState?.validate() ?? false)) return;

    setState(() => _submitting = true);

    try {
      await widget.session.signIn(
        email: _emailController.text.trim(),
        password: _passwordController.text,
      );
      // SessionStore notifies its listeners; the root widget swaps screens.
    } on ApiException catch (error) {
      if (!mounted) return;

      final message = error.firstErrorFor('password') ??
          error.firstErrorFor('email') ??
          (error.isValidationError
              ? 'These credentials do not match our records.'
              : error.message);

      setState(() => _inlineError = message);

      // Prominent popup that disappears by itself after 3 seconds.
      await ErrorPopup.show(
        context,
        title: 'Sign-in failed',
        message: message,
        duration: AppConfig.loginErrorDuration,
      );
    } catch (_) {
      if (!mounted) return;
      const message = 'Something went wrong. Please try again.';
      setState(() => _inlineError = message);
      await ErrorPopup.show(context, message: message);
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;

    return Scaffold(
      body: Container(
        decoration: BoxDecoration(
          gradient: LinearGradient(
            begin: Alignment.topLeft,
            end: Alignment.bottomRight,
            colors: [
              scheme.primary,
              Color.lerp(scheme.primary, Colors.black, 0.35)!,
            ],
          ),
        ),
        child: SafeArea(
          child: Center(
            child: SingleChildScrollView(
              padding: const EdgeInsets.all(24),
              child: ConstrainedBox(
                constraints: const BoxConstraints(maxWidth: 420),
                child: Card(
                  elevation: 10,
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(20),
                  ),
                  child: Padding(
                    padding: const EdgeInsets.fromLTRB(24, 28, 24, 24),
                    child: Form(
                      key: _formKey,
                      child: Column(
                        mainAxisSize: MainAxisSize.min,
                        crossAxisAlignment: CrossAxisAlignment.stretch,
                        children: [
                          _buildHeader(context, scheme),
                          const SizedBox(height: 28),
                          TextFormField(
                            controller: _emailController,
                            keyboardType: TextInputType.emailAddress,
                            textInputAction: TextInputAction.next,
                            autofillHints: const [AutofillHints.username],
                            enabled: !_submitting,
                            decoration: InputDecoration(
                              labelText: 'Email address',
                              hintText: 'you@example.com',
                              prefixIcon: const Icon(Icons.mail_outline),
                              border: const OutlineInputBorder(),
                              errorText: _inlineError != null &&
                                      _passwordController.text.isEmpty
                                  ? _inlineError
                                  : null,
                            ),
                            validator: (value) {
                              final text = (value ?? '').trim();
                              if (text.isEmpty) {
                                return 'Please enter your email address.';
                              }
                              if (!text.contains('@') || !text.contains('.')) {
                                return 'Please enter a valid email address.';
                              }
                              return null;
                            },
                            onFieldSubmitted: (_) => _passwordFocus.requestFocus(),
                          ),
                          const SizedBox(height: 18),
                          TextFormField(
                            focusNode: _passwordFocus,
                            controller: _passwordController,
                            obscureText: _obscure,
                            enabled: !_submitting,
                            autofillHints: const [AutofillHints.password],
                            decoration: InputDecoration(
                              labelText: 'Password',
                              prefixIcon: const Icon(Icons.lock_outline),
                              border: const OutlineInputBorder(),
                              errorText: _inlineError,
                              suffixIcon: IconButton(
                                tooltip:
                                    _obscure ? 'Show password' : 'Hide password',
                                icon: Icon(_obscure
                                    ? Icons.visibility_outlined
                                    : Icons.visibility_off_outlined),
                                onPressed: () =>
                                    setState(() => _obscure = !_obscure),
                              ),
                            ),
                            validator: (value) {
                              if ((value ?? '').isEmpty) {
                                return 'Please enter your password.';
                              }
                              return null;
                            },
                            onFieldSubmitted: (_) {
                              if (!_submitting) _submit();
                            },
                          ),
                          const SizedBox(height: 24),
                          SizedBox(
                            height: 50,
                            child: FilledButton(
                              onPressed: _submitting ? null : _submit,
                              style: FilledButton.styleFrom(
                                shape: RoundedRectangleBorder(
                                  borderRadius: BorderRadius.circular(12),
                                ),
                              ),
                              child: _submitting
                                  ? const SizedBox(
                                      width: 22,
                                      height: 22,
                                      child: CircularProgressIndicator(
                                        strokeWidth: 2.4,
                                        color: Colors.white,
                                      ),
                                    )
                                  : const Text(
                                      'Sign in',
                                      style: TextStyle(
                                        fontSize: 16,
                                        fontWeight: FontWeight.w600,
                                      ),
                                    ),
                            ),
                          ),
                          const SizedBox(height: 16),
                          _buildServerHint(context, theme),
                        ],
                      ),
                    ),
                  ),
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }

  Widget _buildHeader(BuildContext context, ColorScheme scheme) {
    return Column(
      children: [
        Container(
          width: 72,
          height: 72,
          decoration: BoxDecoration(
            color: scheme.primaryContainer,
            shape: BoxShape.circle,
          ),
          child: Icon(Icons.sports_rugby, size: 40, color: scheme.primary),
        ),
        const SizedBox(height: 16),
        Text(
          'Sport Manager',
          style: Theme.of(context)
              .textTheme
              .headlineSmall
              ?.copyWith(fontWeight: FontWeight.w700),
        ),
        const SizedBox(height: 6),
        Text(
          'Sign in to manage your teams, players and fixtures.',
          textAlign: TextAlign.center,
          style: Theme.of(context)
              .textTheme
              .bodyMedium
              ?.copyWith(color: scheme.outline),
        ),
      ],
    );
  }

  Widget _buildServerHint(BuildContext context, ThemeData theme) {
    return Row(
      mainAxisAlignment: MainAxisAlignment.center,
      children: [
        Icon(Icons.dns_outlined, size: 14, color: theme.colorScheme.outline),
        const SizedBox(width: 6),
        Flexible(
          child: Text(
            AppConfig.apiBaseUrl,
            style: theme.textTheme.labelSmall
                ?.copyWith(color: theme.colorScheme.outline),
            overflow: TextOverflow.ellipsis,
          ),
        ),
      ],
    );
  }
}
