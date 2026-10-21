<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Team extends Model
{
    use HasFactory;

    protected $table = 'teams';

    public $timestamps = false;

    protected $fillable = [
        'name', 'code', 'coach', 'city', 'founded_year', 'logo', 'description',
    ];

    protected $casts = [
        'founded_year' => 'integer',
        'created_at' => 'datetime',
    ];

    public function players()
    {
        return $this->hasMany(Player::class);
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function homeMatches()
    {
        return $this->hasMany(MatchModel::class, 'home_team_id');
    }

    public function awayMatches()
    {
        return $this->hasMany(MatchModel::class, 'away_team_id');
    }

    /** Compact id => name/code list for dropdowns (array shape). */
    public static function options(): array
    {
        return static::query()
            ->orderBy('name')
            ->get(['id', 'name', 'code'])
            ->map(fn (self $t) => [
                'id' => (int) $t->id,
                'name' => $t->name,
                'code' => $t->code,
            ])
            ->all();
    }

    /** Search across name/code/city/coach (matches the original OR query). */
    public static function searchQuery(string $search)
    {
        $query = static::query();

        if ($search === '') {
            return $query;
        }

        $like = '%'.$search.'%';

        return $query->where(function ($q) use ($like) {
            $q->where('name', 'like', $like)
                ->orWhere('code', 'like', $like)
                ->orWhere('city', 'like', $like)
                ->orWhere('coach', 'like', $like);
        });
    }

    /**
     * Paginated teams with a players_count, ordered by name (index screen).
     *
     * @return array<int, array<string, mixed>>
     */
    public static function paginate(int $limit, int $offset, string $search = ''): array
    {
        return static::searchQuery($search)
            ->withCount('players')
            ->orderBy('name')
            ->skip($offset)
            ->take($limit)
            ->get()
            ->map(fn (self $t) => $t->toArray() + ['players_count' => (int) $t->players_count])
            ->all();
    }

    public static function countSearch(string $search = ''): int
    {
        return static::searchQuery($search)->count();
    }

    public static function countAll(): int
    {
        return static::count();
    }

    /** Team row (with a staff list) as an array, or null. */
    public static function withStaff(int $id): ?array
    {
        $team = static::find($id);
        if ($team === null) {
            return null;
        }

        $staff = static::query()
            ->whereKey($id)
            ->first()
            ->users()
            ->where('role', User::ROLE_MANAGER)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get()
            ->toArray();

        return $team->toArray() + [
            'staff' => $staff,
            'staff_count' => count($staff),
        ];
    }

    /** Team row (with its squad) as an array, or null. */
    public static function withPlayers(int $id): ?array
    {
        $team = static::find($id);
        if ($team === null) {
            return null;
        }

        $players = $team->players()
            ->orderByRaw('jersey_number IS NULL, jersey_number ASC')
            ->orderBy('last_name')
            ->get()
            ->toArray();

        $finished = $team->homeMatches()->where('status', 'finished')->count()
            + $team->awayMatches()->where('status', 'finished')->count();

        return $team->toArray() + [
            'players' => $players,
            'players_count' => count($players),
            'finished_matches' => $finished,
        ];
    }

    /** Team row as an array, or null. */
    public static function findArray(int $id): ?array
    {
        return static::find($id)?->toArray();
    }
}
