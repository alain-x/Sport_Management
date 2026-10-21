<?php

namespace App\Http\Controllers\Api;

use App\Models\ChatMessage;
use App\Models\ChatRoom;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Team and group chat over the API.
 *
 * Rooms the caller may open come from ChatRoom::forUser(); access to any
 * individual room is re-checked with ChatRoom::accessible().
 */
class ChatApiController extends ApiController
{
    /** GET /api/chat - rooms the caller can open, with a last-message preview. */
    public function index(): JsonResponse
    {
        $user = $this->userArray();
        $rooms = ChatRoom::forUser($user);
        $roomIds = array_map(static fn (array $r): int => (int) $r['id'], $rooms);

        return $this->ok([
            'items' => $rooms,
            'previews' => ChatMessage::lastPerRoom($roomIds),
        ]);
    }

    /** GET /api/chat/{id}/messages?after=0 - history or new messages since an id. */
    public function messages(Request $request, string $id): JsonResponse
    {
        $room = ChatRoom::accessible((int) $id, $this->userArray());
        if ($room === null) {
            abort(404, 'Chat room not found.');
        }

        $after = (int) $request->query('after', 0);
        $messages = $after > 0
            ? ChatMessage::after((int) $id, $after)
            : ChatMessage::forRoom((int) $id);

        return $this->ok([
            'room' => $room,
            'messages' => $messages,
        ]);
    }

    /** POST /api/chat/{id} - post a message. */
    public function post(Request $request, string $id): JsonResponse
    {
        $user = $this->userArray();
        $room = ChatRoom::accessible((int) $id, $user);
        if ($room === null) {
            abort(404, 'Chat room not found.');
        }

        $body = trim((string) $request->input('body', ''));
        if ($body === '') {
            return $this->validationError(['body' => ['Message cannot be empty.']]);
        }
        if (mb_strlen($body) > 4000) {
            return $this->validationError(['body' => ['Message is too long (4000 characters maximum).']]);
        }

        $message = ChatMessage::create([
            'room_id' => (int) $id,
            'user_id' => (int) $user['id'],
            'body' => $body,
        ]);

        return $this->ok($message, 201);
    }

    /** The signed-in user as the plain array the chat models expect. */
    private function userArray(): array
    {
        $user = $this->user()?->toArray();
        if ($user === null) {
            abort(403, 'Please sign in to use chat.');
        }

        return $user;
    }
}
