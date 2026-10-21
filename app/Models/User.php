<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    public const ROLE_ADMIN = 'admin';

    public const ROLE_MANAGER = 'manager';

    public const ROLE_PLAYER = 'player';

    protected $table = 'users';

    public $timestamps = false;

    protected $fillable = [
        'name', 'email', 'password', 'role', 'team_id', 'player_id', 'phone', 'photo',
    ];

    protected $hidden = [
        'password', 'remember_token',
    ];

    protected $casts = [
        'team_id' => 'integer',
        'player_id' => 'integer',
        'created_at' => 'datetime',
        'password' => 'hashed',
    ];

    // ------------------------------------------------------------------
    // Relationships
    // ------------------------------------------------------------------

    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    /** Linked player record (player logins only). */
    public function player()
    {
        return $this->belongsTo(Player::class);
    }

    public function chatRooms()
    {
        return $this->belongsToMany(ChatRoom::class, 'chat_room_members', 'user_id', 'room_id');
    }

    public function chatMessages()
    {
        return $this->hasMany(ChatMessage::class);
    }

    // ------------------------------------------------------------------
    // Role helpers
    // ------------------------------------------------------------------

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isManager(): bool
    {
        return $this->role === self::ROLE_MANAGER;
    }

    public function isPlayer(): bool
    {
        return $this->role === self::ROLE_PLAYER;
    }

    /** Admin or team staff — anyone who may manage records. */
    public function isStaff(): bool
    {
        return $this->isAdmin() || $this->isManager();
    }

    public function hasRole(string ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    /** True when this account belongs to (or manages) the given team. */
    public function belongsToTeam(?int $teamId): bool
    {
        return $teamId !== null && $this->team_id === $teamId;
    }

    // ------------------------------------------------------------------
    // Query helpers (array shapes matching the original app)
    // ------------------------------------------------------------------

    public static function findByEmail(string $email): ?array
    {
        return static::where('email', $email)->first()?->toArray();
    }

    /** User row as a plain array (legacy shape), or null. */
    public static function findArray(int $id): ?array
    {
        return static::find($id)?->toArray();
    }

    /** Staff accounts belonging to one team (role = manager), newest first. */
    public static function forTeam(int $teamId): array
    {
        return static::where('team_id', $teamId)
            ->where('role', self::ROLE_MANAGER)
            ->orderByDesc('created_at')->orderByDesc('id')
            ->get()->map(fn (self $u) => $u->toArray())->all();
    }

    public static function countForTeam(int $teamId): int
    {
        return static::where('team_id', $teamId)->where('role', self::ROLE_MANAGER)->count();
    }

    /** The login account linked to a player (1:1), if one exists. */
    public static function findByPlayerId(int $playerId): ?array
    {
        return static::where('player_id', $playerId)->first()?->toArray();
    }

    /** Compact list for group-chat member pickers. */
    public static function forChatOptions(): array
    {
        return static::with('team:id,name')
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'role', 'team_id'])
            ->map(fn (self $u) => [
                'id' => (int) $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'role' => $u->role,
                'team_name' => $u->team?->name,
            ])
            ->all();
    }

    /** Admin user list with team names, search + role filter, paginated. */
    public static function paginateAll(int $limit, int $offset, string $search = '', string $role = ''): array
    {
        return static::searchQuery($search, $role)
            ->with('team:id,name,code')
            // admin -> manager -> player, then by name
            ->orderByRaw("FIELD(role, 'admin', 'manager', 'player')")
            ->orderBy('name')
            ->skip($offset)
            ->take($limit)
            ->get()
            ->map(fn (self $u) => $u->toArray() + [
                'team_name' => $u->team?->name,
                'team_code' => $u->team?->code,
            ])
            ->all();
    }

    public static function countFiltered(string $search = '', string $role = ''): int
    {
        return static::searchQuery($search, $role)->count();
    }

    private static function searchQuery(string $search, string $role): Builder
    {
        return static::query()
            ->when($search !== '', function (Builder $q) use ($search) {
                $like = '%'.$search.'%';
                $q->where(function (Builder $inner) use ($like) {
                    $inner->where('name', 'like', $like)
                        ->orWhere('email', 'like', $like)
                        ->orWhereHas('team', fn (Builder $t) => $t->where('name', 'like', $like));
                });
            })
            ->when(in_array($role, [self::ROLE_ADMIN, self::ROLE_MANAGER, self::ROLE_PLAYER], true),
                fn (Builder $q) => $q->where('role', $role));
    }

    /** Distinct teams for dropdowns (delegates to Team). */
    public static function teamOptions(): array
    {
        return Team::options();
    }
}
