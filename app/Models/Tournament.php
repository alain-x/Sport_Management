<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Tournament extends Model
{
    use HasFactory;

    protected $table = 'tournaments';

    public $timestamps = false;

    public const STATUSES = ['upcoming', 'ongoing', 'completed'];

    protected $fillable = [
        'name', 'sport', 'start_date', 'end_date', 'status', 'description',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'created_at' => 'datetime',
    ];

    public function matches()
    {
        return $this->hasMany(MatchModel::class);
    }

    /** @return array<int, array<string, mixed>> */
    public static function allWithCounts(string $orderBy = 'start_date DESC'): array
    {
        [$column, $direction] = array_pad(preg_split('/\s+/', trim($orderBy)) ?: [], 2, 'asc');

        return static::withCount('matches')
            ->orderBy($column, strtolower($direction) === 'desc' ? 'desc' : 'asc')
            ->get()
            ->map(fn (self $t) => static::row($t) + ['matches_count' => (int) $t->matches_count])
            ->all();
    }

    public static function countAll(): int
    {
        return static::count();
    }

    /** @return array<int, array<string, mixed>> */
    public static function byStatus(string $status, int $limit = 10): array
    {
        return static::where('status', $status)
            ->orderByDesc('start_date')
            ->limit($limit)
            ->get()
            ->map(fn (self $t) => static::row($t))
            ->all();
    }

    /** Tournament row with its fixtures attached, or null. */
    public static function withMatches(int $id): ?array
    {
        $tournament = static::with(['matches' => function ($q) {
            $q->with(['homeTeam:id,name,code', 'awayTeam:id,name,code'])->orderBy('match_date');
        }])->find($id);

        if ($tournament === null) {
            return null;
        }

        $matches = $tournament->matches->map(function (MatchModel $m) {
            $row = $m->toArray();
            $row['home_name'] = $m->homeTeam?->name;
            $row['home_code'] = $m->homeTeam?->code;
            $row['away_name'] = $m->awayTeam?->name;
            $row['away_code'] = $m->awayTeam?->code;
            $row['match_date'] = $m->match_date?->format('Y-m-d H:i:s');

            return $row;
        })->all();

        $row = static::row($tournament);
        $row['matches'] = $matches;

        return $row;
    }

    /** Tournament row as an array, or null. */
    public static function findArray(int $id): ?array
    {
        $t = static::find($id);

        return $t !== null ? static::row($t) : null;
    }

    /** @return array<string, mixed> */
    private static function row(self $t): array
    {
        $row = $t->toArray();
        $row['start_date'] = $t->start_date?->format('Y-m-d');
        $row['end_date'] = $t->end_date?->format('Y-m-d');

        return $row;
    }
}
