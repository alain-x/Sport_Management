<?php

namespace App\Http\Controllers;

use App\Models\ChatMessage;
use App\Models\ChatRoom;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Team and group chat.
 *
 * Every team has an automatic room its staff and players can post in.
 * Administrators can additionally create named group rooms with members.
 */
class ChatController extends Controller
{
    /** List the rooms the signed-in user can open. */
    public function index()
    {
        $user = $this->currentUser();
        $rooms = ChatRoom::forUser($user);
        $roomIds = array_map(static fn (array $room): int => (int) $room['id'], $rooms);

        return $this->view('chat.index', [
            'pageTitle' => 'Chat',
            'rooms' => $rooms,
            'previews' => ChatMessage::lastPerRoom($roomIds),
            'canCreateGroup' => is_admin(),
        ]);
    }

    /** Open a room: message history + composer. */
    public function show(string $id)
    {
        $user = $this->currentUser();
        $room = ChatRoom::accessible((int) $id, $user);

        if ($room === null) {
            abort(404);
        }

        $messages = ChatMessage::forRoom((int) $id);
        $lastId = $messages === [] ? 0 : (int) $messages[array_key_last($messages)]['id'];

        return $this->view('chat.room', [
            'pageTitle' => $room['name'],
            'room' => $room,
            'messages' => $messages,
            'lastId' => $lastId,
            'members' => $room['type'] === 'group' ? ChatRoom::roomMembers((int) $id) : [],
            'memberCount' => ChatRoom::memberCount($room),
            'canEditGroup' => is_admin() && $room['type'] === 'group',
        ]);
    }

    /** Post a message to a room. */
    public function post(Request $request, string $id): RedirectResponse
    {
        $user = $this->currentUser();
        $room = ChatRoom::accessible((int) $id, $user);

        if ($room === null) {
            abort(404);
        }

        $body = trim((string) $request->input('body', ''));
        if ($body === '') {
            return redirect('/chat/'.$id)->with('error', 'Message cannot be empty.');
        }
        if (mb_strlen($body) > 4000) {
            return redirect('/chat/'.$id)->with('error', 'Message is too long (4000 characters maximum).');
        }

        ChatMessage::create([
            'room_id' => (int) $id,
            'user_id' => (int) $user['id'],
            'body' => $body,
        ]);

        return redirect('/chat/'.$id);
    }

    /** Lightweight JSON endpoint the room page polls for new messages. */
    public function messages(Request $request, string $id): JsonResponse
    {
        $user = $this->currentUser();
        $room = ChatRoom::accessible((int) $id, $user);

        if ($room === null) {
            return response()->json(['messages' => []], 403);
        }

        $after = (int) $request->query('after', 0);

        return response()->json(['messages' => ChatMessage::after((int) $id, $after)]);
    }

    // ------------------------------------------------------------------
    // Group rooms (administrators only).
    // ------------------------------------------------------------------

    public function create()
    {
        $this->requireAdmin();

        return $this->view('chat.form', [
            'pageTitle' => 'New Group Chat',
            'room' => null,
            'members' => [],
            'users' => User::forChatOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->requireAdmin();

        $name = trim((string) $request->input('name', ''));
        $memberIds = array_map('intval', (array) $request->input('members', []));

        $errors = [];
        if ($name === '' || mb_strlen($name) > 120) {
            $errors['name'][] = 'Group name is required (up to 120 characters).';
        }
        if ($errors !== []) {
            return redirect('/chat/create')->withInput(['name' => $name])->withErrors($errors);
        }

        $adminId = (int) ($this->user()?->id ?? 0);
        $room = ChatRoom::create([
            'name' => $name,
            'type' => 'group',
            'team_id' => null,
            'created_by' => $adminId,
        ]);
        $memberIds[] = $adminId; // The creator is always a member.
        ChatRoom::syncMembers((int) $room->id, $memberIds);

        return redirect('/chat/'.$room->id)->with('success', 'Group chat created.');
    }

    public function edit(string $id)
    {
        $this->requireAdmin();

        $room = ChatRoom::findArray((int) $id);
        if ($room === null || $room['type'] !== 'group') {
            abort(404);
        }

        return $this->view('chat.form', [
            'pageTitle' => 'Edit Group Chat',
            'room' => $room,
            'members' => array_map(
                static fn (array $member): int => (int) $member['id'],
                ChatRoom::roomMembers((int) $id)
            ),
            'users' => User::forChatOptions(),
        ]);
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $this->requireAdmin();

        $room = ChatRoom::findArray((int) $id);
        if ($room === null || $room['type'] !== 'group') {
            abort(404);
        }

        $name = trim((string) $request->input('name', ''));
        $memberIds = array_map('intval', (array) $request->input('members', []));

        $errors = [];
        if ($name === '' || mb_strlen($name) > 120) {
            $errors['name'][] = 'Group name is required (up to 120 characters).';
        }
        if ($errors !== []) {
            return redirect('/chat/'.$id.'/edit')->withInput(['name' => $name])->withErrors($errors);
        }

        ChatRoom::whereKey((int) $id)->update(['name' => $name]);

        $adminId = (int) ($this->user()?->id ?? 0);
        $memberIds[] = $adminId; // Keep the creator in the room.
        ChatRoom::syncMembers((int) $id, $memberIds);

        return redirect('/chat/'.$id)->with('success', 'Group chat updated.');
    }

    /** The signed-in user as a plain array, or a 403 when there is none. */
    private function currentUser(): array
    {
        $user = auth_user();
        if ($user === null) {
            abort(403, 'Please sign in to use chat.');
        }

        return $user;
    }
}
