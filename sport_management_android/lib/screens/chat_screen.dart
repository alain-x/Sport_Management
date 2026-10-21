import 'dart:async';

import 'package:flutter/material.dart';

import '../models/json.dart';
import '../services/api_exception.dart';
import '../state/session_store.dart';
import '../widgets/common.dart';

/// Chat: pick a room, then read and post messages.
///
/// The screen polls for new messages every few seconds so it feels live
/// without needing a websocket backend.
class ChatScreen extends StatefulWidget {
  const ChatScreen({super.key, required this.session});

  final SessionStore session;

  @override
  State<ChatScreen> createState() => _ChatScreenState();
}

class _ChatScreenState extends State<ChatScreen> {
  late Future<Map<String, dynamic>> _future;

  @override
  void initState() {
    super.initState();
    _load();
  }

  void _load() {
    _future = widget.session.client.getMap('/chat');
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Chat')),
      body: FutureBuilder<Map<String, dynamic>>(
        future: _future,
        builder: (context, snapshot) {
          if (snapshot.connectionState == ConnectionState.waiting) {
            return const LoadingView(message: 'Loading rooms…');
          }
          if (snapshot.hasError) {
            final error = snapshot.error;
            return ErrorRetryView(
              message: error is ApiException
                  ? error.message
                  : 'Could not load chat rooms.',
              onRetry: () => setState(_load),
            );
          }

          final data = snapshot.data ?? const {};
          final rooms = Json.list(data['items']);
          final previews = Json.map(data['previews']);

          if (rooms.isEmpty) {
            return const EmptyView(
              message: 'You are not a member of any chat room yet.',
              icon: Icons.forum_outlined,
            );
          }

          return RefreshIndicator(
            onRefresh: () async => setState(_load),
            child: ListView.separated(
              itemCount: rooms.length,
              separatorBuilder: (_, _) => const Divider(height: 1),
              itemBuilder: (context, i) =>
                  _roomTile(context, rooms[i], previews),
            ),
          );
        },
      ),
    );
  }

  Widget _roomTile(
    BuildContext context,
    Map<String, dynamic> room,
    Map<String, dynamic> previews,
  ) {
    final id = Json.intOrNull(room['id']);
    final preview = Json.map(id == null ? null : previews['$id']);
    final type = Json.string(room['type'], 'group');

    return ListTile(
      leading: CircleAvatar(
        child: Icon(
          type == 'team' ? Icons.groups : Icons.forum,
          size: 20,
        ),
      ),
      title: Text(
        Json.string(room['name'], 'Room'),
        style: const TextStyle(fontWeight: FontWeight.w600),
      ),
      subtitle: Text(
        preview.isEmpty
            ? 'No messages yet'
            : '${Json.string(preview['sender_name'], 'Someone')}: '
                '${Json.string(preview['body'])}',
        maxLines: 1,
        overflow: TextOverflow.ellipsis,
      ),
      trailing: const Icon(Icons.chevron_right),
      onTap: id == null
          ? null
          : () => Navigator.of(context).push(
                MaterialPageRoute(
                  builder: (_) => ChatRoomScreen(
                    session: widget.session,
                    roomId: id,
                    roomName: Json.string(room['name'], 'Room'),
                  ),
                ),
              ),
    );
  }
}

/// A single chat room with its message history and composer.
class ChatRoomScreen extends StatefulWidget {
  const ChatRoomScreen({
    super.key,
    required this.session,
    required this.roomId,
    required this.roomName,
  });

  final SessionStore session;
  final int roomId;
  final String roomName;

  @override
  State<ChatRoomScreen> createState() => _ChatRoomScreenState();
}

class _ChatRoomScreenState extends State<ChatRoomScreen> {
  final _composer = TextEditingController();
  final _scrollController = ScrollController();
  final _messages = <Map<String, dynamic>>[];

  Timer? _poll;
  bool _loading = true;
  bool _sending = false;
  String? _error;
  int _lastId = 0;

  @override
  void initState() {
    super.initState();
    _initialLoad();
    _poll = Timer.periodic(const Duration(seconds: 5), (_) => _pollNew());
  }

  @override
  void dispose() {
    _poll?.cancel();
    _composer.dispose();
    _scrollController.dispose();
    super.dispose();
  }

  Future<void> _initialLoad() async {
    try {
      final data = await widget.session.client
          .get('/chat/${widget.roomId}/messages');
      final items = Json.list(Json.map(data)['messages']);
      if (!mounted) return;
      setState(() {
        _messages
          ..clear()
          ..addAll(items);
        _lastId = items.isEmpty ? 0 : Json.intOr(items.last['id']);
        _loading = false;
        _error = null;
      });
      _scrollToBottom();
    } on ApiException catch (error) {
      if (!mounted) return;
      setState(() {
        _loading = false;
        _error = error.message;
      });
    }
  }

  Future<void> _pollNew() async {
    if (_lastId == 0 || _sending) return;
    try {
      final data = await widget.session.client.get(
        '/chat/${widget.roomId}/messages',
        query: {'after': _lastId},
      );
      final items = Json.list(Json.map(data)['messages']);
      if (!mounted || items.isEmpty) return;
      setState(() {
        _messages.addAll(items);
        _lastId = Json.intOr(items.last['id']);
      });
      _scrollToBottom();
    } on ApiException {
      // Silent: the next poll will retry.
    }
  }

  Future<void> _send() async {
    final body = _composer.text.trim();
    if (body.isEmpty || _sending) return;

    setState(() => _sending = true);
    try {
      final message = await widget.session.client.post(
        '/chat/${widget.roomId}',
        body: {'body': body},
      );
      if (!mounted) return;
      _composer.clear();
      setState(() {
        if (message is Map) {
          _messages.add(Json.map(message));
          _lastId = Json.intOr(Json.map(message)['id'], _lastId);
        }
        _sending = false;
      });
      _scrollToBottom();
    } on ApiException catch (error) {
      if (!mounted) return;
      setState(() => _sending = false);
      ScaffoldMessenger.of(context)
          .showSnackBar(SnackBar(content: Text(error.flatErrors)));
    }
  }

  void _scrollToBottom() {
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (_scrollController.hasClients) {
        _scrollController.animateTo(
          _scrollController.position.maxScrollExtent,
          duration: const Duration(milliseconds: 250),
          curve: Curves.easeOut,
        );
      }
    });
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text(widget.roomName)),
      body: Column(
        children: [
          Expanded(child: _buildBody()),
          const Divider(height: 1),
          _buildComposer(),
        ],
      ),
    );
  }

  Widget _buildBody() {
    if (_loading) return const LoadingView();
    if (_error != null) {
      return ErrorRetryView(message: _error!, onRetry: _initialLoad);
    }
    if (_messages.isEmpty) {
      return const EmptyView(
        message: 'No messages yet. Say hello!',
        icon: Icons.chat_bubble_outline,
      );
    }

    final myId = widget.session.user?.id;
    return ListView.builder(
      controller: _scrollController,
      padding: const EdgeInsets.all(12),
      itemCount: _messages.length,
      itemBuilder: (context, i) =>
          _MessageBubble(message: _messages[i], isMine: Json.intOr(_messages[i]['user_id']) == myId),
    );
  }

  Widget _buildComposer() {
    return SafeArea(
      top: false,
      child: Padding(
        padding: const EdgeInsets.fromLTRB(12, 8, 8, 8),
        child: Row(
          children: [
            Expanded(
              child: TextField(
                controller: _composer,
                minLines: 1,
                maxLines: 4,
                textInputAction: TextInputAction.send,
                decoration: const InputDecoration(
                  hintText: 'Write a message…',
                  border: OutlineInputBorder(),
                  isDense: true,
                ),
                onSubmitted: (_) => _send(),
              ),
            ),
            const SizedBox(width: 8),
            IconButton.filled(
              onPressed: _sending ? null : _send,
              icon: _sending
                  ? const SizedBox(
                      width: 18,
                      height: 18,
                      child: CircularProgressIndicator(
                        strokeWidth: 2,
                        color: Colors.white,
                      ),
                    )
                  : const Icon(Icons.send),
              tooltip: 'Send',
            ),
          ],
        ),
      ),
    );
  }
}

class _MessageBubble extends StatelessWidget {
  const _MessageBubble({required this.message, required this.isMine});

  final Map<String, dynamic> message;
  final bool isMine;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    final name = Json.string(message['sender_name'], 'User');

    return Align(
      alignment: isMine ? Alignment.centerRight : Alignment.centerLeft,
      child: ConstrainedBox(
        constraints: BoxConstraints(
          maxWidth: MediaQuery.of(context).size.width * 0.75,
        ),
        child: Card(
          color: isMine ? scheme.primaryContainer : null,
          child: Padding(
            padding: const EdgeInsets.all(12),
            child: Column(
              crossAxisAlignment:
                  isMine ? CrossAxisAlignment.end : CrossAxisAlignment.start,
              children: [
                if (!isMine)
                  Text(
                    name,
                    style: TextStyle(
                      fontWeight: FontWeight.w700,
                      fontSize: 12,
                      color: scheme.primary,
                    ),
                  ),
                const SizedBox(height: 4),
                Text(Json.string(message['body'])),
                const SizedBox(height: 4),
                Text(
                  Json.dateTimeLabel(
                    message['created_at'],
                    fallback: '',
                  ),
                  style: TextStyle(fontSize: 10, color: scheme.outline),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
