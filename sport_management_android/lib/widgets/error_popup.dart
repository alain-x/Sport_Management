import 'dart:async';

import 'package:flutter/material.dart';

/// A prominent, auto-dismissing error popup used by the login screen.
///
/// It slides in at the top of the screen, shows a short message, counts down
/// with a shrinking progress bar and closes itself after [duration]
/// (3 seconds by default). The user can dismiss it early with the close
/// button, and tapping/hovering pauses the countdown so long messages can
/// still be read.
class ErrorPopup extends StatefulWidget {
  const ErrorPopup({
    super.key,
    required this.message,
    this.title = 'Sign-in failed',
    this.duration = const Duration(seconds: 3),
    this.onClosed,
  });

  final String message;
  final String title;
  final Duration duration;
  final VoidCallback? onClosed;

  /// Show the popup as an overlay in the given [context].
  ///
  /// Returns a [Future] that completes once the popup is gone, so callers can
  /// chain follow-up work (e.g. re-enabling a button).
  static Future<void> show(
    BuildContext context, {
    required String message,
    String title = 'Sign-in failed',
    Duration duration = const Duration(seconds: 3),
  }) {
    final overlay = Overlay.maybeOf(context);
    if (overlay == null) return Future<void>.value();

    late OverlayEntry entry;
    final completer = Completer<void>();

    entry = OverlayEntry(
      builder: (ctx) => _ErrorPopupOverlay(
        message: message,
        title: title,
        duration: duration,
        onDismiss: () {
          if (entry.mounted) entry.remove();
          if (!completer.isCompleted) completer.complete();
        },
      ),
    );

    overlay.insert(entry);
    return completer.future;
  }

  @override
  State<ErrorPopup> createState() => _ErrorPopupState();
}

class _ErrorPopupState extends State<ErrorPopup> {
  @override
  Widget build(BuildContext context) {
    return Material(
      color: Colors.transparent,
      child: _ErrorPopupBody(
        message: widget.message,
        title: widget.title,
        duration: widget.duration,
        onDismiss: () {
          widget.onClosed?.call();
        },
      ),
    );
  }
}

class _ErrorPopupOverlay extends StatelessWidget {
  const _ErrorPopupOverlay({
    required this.message,
    required this.title,
    required this.duration,
    required this.onDismiss,
  });

  final String message;
  final String title;
  final Duration duration;
  final VoidCallback onDismiss;

  @override
  Widget build(BuildContext context) {
    final topInset = MediaQuery.of(context).padding.top;
    return Positioned(
      top: topInset + 12,
      left: 16,
      right: 16,
      child: _ErrorPopupBody(
        message: message,
        title: title,
        duration: duration,
        onDismiss: onDismiss,
      ),
    );
  }
}

class _ErrorPopupBody extends StatefulWidget {
  const _ErrorPopupBody({
    required this.message,
    required this.title,
    required this.duration,
    required this.onDismiss,
  });

  final String message;
  final String title;
  final Duration duration;
  final VoidCallback onDismiss;

  @override
  State<_ErrorPopupBody> createState() => _ErrorPopupBodyState();
}

class _ErrorPopupBodyState extends State<_ErrorPopupBody>
    with SingleTickerProviderStateMixin {
  late final AnimationController _controller;
  Timer? _timer;
  bool _paused = false;

  @override
  void initState() {
    super.initState();
    _controller = AnimationController(vsync: this, duration: widget.duration)
      ..addStatusListener((status) {
        if (status == AnimationStatus.completed) {
          widget.onDismiss();
        }
      })
      ..forward();
    _scheduleAutoClose();
  }

  void _scheduleAutoClose() {
    _timer?.cancel();
    final remaining = widget.duration * (1 - _controller.value);
    _timer = Timer(remaining, () {
      if (mounted) _controller.forward();
    });
  }

  void _pause() {
    if (_paused) return;
    _paused = true;
    _timer?.cancel();
    _controller.stop();
  }

  void _resume() {
    if (!_paused) return;
    _paused = false;
    _scheduleAutoClose();
  }

  @override
  void dispose() {
    _timer?.cancel();
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;

    return MouseRegion(
      onEnter: (_) => _pause(),
      onExit: (_) => _resume(),
      child: TweenAnimationBuilder<double>(
        tween: Tween(begin: 0, end: 1),
        duration: const Duration(milliseconds: 220),
        curve: Curves.easeOutCubic,
        builder: (context, value, child) => Opacity(
          opacity: value,
          child: Transform.translate(
            offset: Offset(0, (1 - value) * -16),
            child: child,
          ),
        ),
        child: Material(
          elevation: 8,
          borderRadius: BorderRadius.circular(14),
          color: const Color(0xFFB3261E),
          child: Padding(
            padding: const EdgeInsets.fromLTRB(16, 14, 8, 10),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Icon(Icons.error_outline,
                        color: Colors.white, size: 24),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            widget.title,
                            style: const TextStyle(
                              color: Colors.white,
                              fontWeight: FontWeight.w700,
                              fontSize: 15,
                            ),
                          ),
                          const SizedBox(height: 4),
                          Text(
                            widget.message,
                            style: const TextStyle(
                              color: Colors.white,
                              fontSize: 14,
                              height: 1.3,
                            ),
                          ),
                        ],
                      ),
                    ),
                    IconButton(
                      tooltip: 'Dismiss',
                      onPressed: widget.onDismiss,
                      icon: const Icon(Icons.close, color: Colors.white, size: 20),
                      splashRadius: 20,
                      visualDensity: VisualDensity.compact,
                    ),
                  ],
                ),
                const SizedBox(height: 6),
                ClipRRect(
                  borderRadius: BorderRadius.circular(4),
                  child: AnimatedBuilder(
                    animation: _controller,
                    builder: (context, _) => LinearProgressIndicator(
                      value: 1 - _controller.value,
                      minHeight: 3,
                      backgroundColor: Colors.white24,
                      valueColor: AlwaysStoppedAnimation<Color>(
                        scheme.surface.withValues(alpha: 0.9),
                      ),
                    ),
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
