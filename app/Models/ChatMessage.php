<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/** A chat message inside a room, joined with its sender. */
class ChatMessage extends Model
{
    use HasFactory;

    protected $table = 'chat_messages';

    public $timestamps = false;

    protected $fillable = ['room_id', 'user_id', 'body'];

    protected $casts = [
        'room_id' => 'integer',
        'user_id' => 'integer',
        'created_at' => 'datetime',
    ];

    public function room()
    {
        return $this->belongsTo(ChatRoom::class, 'room_id');
    }

    public function sender()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** @return array<string, mixed> */
    private static function row(self $m): array
    {
        return $m->toArray() + [
            'sender_name' => $m->sender?->name,
            'sender_photo' => $m->sender?->photo,
            'sender_role' => $m->sender?->role,
        ];
    }

    /**
     * Messages for a room (oldest first) joined with sender name/photo/role.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function forRoom(int $roomId, int $limit = 300): array
    {
        return static::with('sender:id,name,photo,role')
            ->where('room_id', $roomId)
            ->orderBy('id')
            ->limit($limit)
            ->get()
            ->map(fn (self $m) => static::row($m))->all();
    }

    /**
     * Messages newer than $afterId (used by the live-refresh poll).
     *
     * @return array<int, array<string, mixed>>
     */
    public static function after(int $roomId, int $afterId, int $limit = 100): array
    {
        return static::with('sender:id,name,photo,role')
            ->where('room_id', $roomId)
            ->where('id', '>', $afterId)
            ->orderBy('id')
            ->limit($limit)
            ->get()
            ->map(fn (self $m) => static::row($m))->all();
    }

    /**
     * Latest message per room (for the room-list preview), keyed by room id.
     *
     * @param  int[]  $roomIds
     * @return array<int, array<string, mixed>>
     */
    public static function lastPerRoom(array $roomIds): array
    {
        if ($roomIds === []) {
            return [];
        }

        $rows = static::with('sender:id,name')
            ->whereIn('room_id', array_map('intval', $roomIds))
            ->whereIn('id', function ($sub) use ($roomIds) {
                $sub->selectRaw('MAX(id)')->from('chat_messages')
                    ->whereIn('room_id', array_map('intval', $roomIds))
                    ->groupBy('room_id');
            })
            ->get();

        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row->room_id] = static::row($row);
        }

        return $map;
    }
}
