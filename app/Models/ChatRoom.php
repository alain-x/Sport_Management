<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Chat rooms: one automatic room per team (everyone in that team can post)
 * plus admin-created group rooms with explicit membership.
 */
class ChatRoom extends Model
{
    use HasFactory;

    protected $table = 'chat_rooms';

    public $timestamps = false;

    public const TYPE_TEAM = 'team';

    public const TYPE_GROUP = 'group';

    protected $fillable = ['name', 'type', 'team_id', 'created_by'];

    protected $casts = [
        'team_id' => 'integer',
        'created_by' => 'integer',
        'created_at' => 'datetime',
    ];

    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function messages()
    {
        return $this->hasMany(ChatMessage::class, 'room_id');
    }

    public function members()
    {
        return $this->belongsToMany(User::class, 'chat_room_members', 'room_id', 'user_id');
    }

    /**
     * Rooms the given user may open: their team room + group rooms they belong to.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function forUser(array $user): array
    {
        $userId = (int) $user['id'];
        $role = (string) ($user['role'] ?? '');
        $teamId = ($user['team_id'] ?? null) !== null ? (int) $user['team_id'] : null;
        $rooms = [];

        // Automatic team room (staff + players of that team).
        if ($teamId !== null && in_array($role, [User::ROLE_MANAGER, User::ROLE_PLAYER], true)) {
            $room = static::find(static::ensureTeamRoom($teamId));
            if ($room !== null) {
                $rooms[] = $room->toArray();
            }
        }

        // Group rooms (admins see all; everyone else only where they are a member).
        $groups = $role === User::ROLE_ADMIN
            ? static::where('type', self::TYPE_GROUP)->orderBy('name')->get()
            : static::where('type', self::TYPE_GROUP)
                ->whereHas('members', fn ($q) => $q->where('users.id', $userId))
                ->orderBy('name')
                ->get();

        foreach ($groups as $group) {
            $rooms[] = $group->toArray();
        }

        return $rooms;
    }

    /** Load a room only if the user may access it (else null). */
    public static function accessible(int $roomId, array $user): ?array
    {
        $room = static::find($roomId);
        if ($room === null) {
            return null;
        }

        $role = (string) ($user['role'] ?? '');
        if ($role === User::ROLE_ADMIN) {
            return $room->toArray();
        }

        if ($room->type === self::TYPE_TEAM) {
            $teamId = ($user['team_id'] ?? null) !== null ? (int) $user['team_id'] : null;
            $sameTeam = $teamId !== null && (int) $room->team_id === $teamId;

            return $sameTeam && in_array($role, [User::ROLE_MANAGER, User::ROLE_PLAYER], true)
                ? $room->toArray()
                : null;
        }

        $isMember = $room->members()->where('users.id', (int) $user['id'])->exists();

        return $isMember ? $room->toArray() : null;
    }

    /** Get (or lazily create) the team chat room for a club; returns its id. */
    public static function ensureTeamRoom(int $teamId): int
    {
        $room = static::where('type', self::TYPE_TEAM)->where('team_id', $teamId)->first();
        if ($room !== null) {
            return (int) $room->id;
        }

        $name = (Team::find($teamId)?->name ?? 'Team').' Chat';

        return (int) static::create(['name' => $name, 'type' => self::TYPE_TEAM, 'team_id' => $teamId])->id;
    }

    /**
     * Explicit members of a room (group rooms), as plain arrays.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function roomMembers(int $roomId): array
    {
        $room = static::find($roomId);
        if ($room === null) {
            return [];
        }

        return $room->members()->orderBy('name')->get()->map(fn (User $u) => $u->toArray())->all();
    }

    /** Replace the explicit member list of a group room. */
    public static function syncMembers(int $roomId, array $userIds): void
    {
        static::find($roomId)?->members()->sync(array_unique(array_map('intval', $userIds)));
    }

    /** Number of people who can post in a room (team = squad size, group = members). */
    public static function memberCount(array $room): int
    {
        if ($room['type'] === self::TYPE_TEAM) {
            return (int) User::where('team_id', (int) $room['team_id'])
                ->whereIn('role', [User::ROLE_MANAGER, User::ROLE_PLAYER])
                ->count();
        }

        return (int) static::find((int) $room['id'])?->members()->count();
    }

    /** Room row as an array, or null. */
    public static function findArray(int $roomId): ?array
    {
        return static::find($roomId)?->toArray();
    }
}
