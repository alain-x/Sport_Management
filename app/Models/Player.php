<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Player extends Model
{
    use HasFactory;

    protected $table = 'players';

    public $timestamps = false;

    protected $fillable = [
        'team_id', 'first_name', 'last_name', 'jersey_number', 'position',
        'date_of_birth', 'nationality', 'foot', 'photo',
        'goals', 'assists', 'appearances', 'minutes_played',
        'yellow_cards', 'red_cards', 'rating',
    ];

    protected $casts = [
        'team_id' => 'integer',
        'jersey_number' => 'integer',
        'date_of_birth' => 'date',
        'goals' => 'integer',
        'assists' => 'integer',
        'appearances' => 'integer',
        'minutes_played' => 'integer',
        'yellow_cards' => 'integer',
        'red_cards' => 'integer',
        'rating' => 'float',
        'created_at' => 'datetime',
    ];

    public const POSITIONS = ['Goalkeeper', 'Defender', 'Midfielder', 'Forward'];

    public const FEET = ['left', 'right', 'both'];

    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    public function metrics()
    {
        return $this->hasMany(PlayerMetric::class)->orderByDesc('test_date')->orderByDesc('id');
    }

    public function welfare()
    {
        return $this->hasMany(PlayerWelfare::class)->orderByDesc('assessment_date')->orderByDesc('id');
    }

    public function attendance()
    {
        return $this->hasMany(Attendance::class);
    }

    public function user()
    {
        return $this->hasOne(User::class);
    }

    public function fullName(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }

    /** Paginated players (with team name/code) for the index screen. */
    public static function paginate(int $limit, int $offset, string $search = '', ?int $teamId = null): array
    {
        return static::filterQuery($search, $teamId)
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->skip($offset)
            ->take($limit)
            ->get()
            ->map(fn (self $p) => static::row($p))
            ->all();
    }

    public static function countFiltered(string $search = '', ?int $teamId = null): int
    {
        return static::filterQuery($search, $teamId)->count();
    }

    private static function filterQuery(string $search, ?int $teamId)
    {
        $query = static::query()->with('team:id,name,code');

        if ($search !== '') {
            $like = '%'.$search.'%';
            $query->where(function ($q) use ($like) {
                $q->where('first_name', 'like', $like)
                    ->orWhere('last_name', 'like', $like)
                    ->orWhere('nationality', 'like', $like);
            });
        }

        return $teamId === null ? $query : $query->where('team_id', $teamId);
    }

    /** @return array<string, mixed> */
    private static function row(self $p): array
    {
        return $p->toArray() + [
            'team_name' => $p->team?->name,
            'team_code' => $p->team?->code,
        ];
    }

    public static function countAll(): int
    {
        return static::count();
    }

    public static function topScorers(int $limit = 5): array
    {
        return static::with('team:id,name,code')
            ->where('goals', '>', 0)
            ->orderByDesc('goals')
            ->orderByDesc('assists')
            ->orderBy('last_name')
            ->limit($limit)
            ->get()
            ->map(fn (self $p) => static::row($p))
            ->all();
    }

    /** Top scorers limited to one team (team dashboard). */
    public static function topScorersForTeam(int $teamId, int $limit = 5): array
    {
        return static::with('team:id,name,code')
            ->where('team_id', $teamId)
            ->where('goals', '>', 0)
            ->orderByDesc('goals')
            ->orderByDesc('assists')
            ->orderBy('last_name')
            ->limit($limit)
            ->get()
            ->map(fn (self $p) => static::row($p))
            ->all();
    }

    /** Squad roster (compact) used by attendance sheets and exports. */
    public static function rosterForTeam(int $teamId): array
    {
        return static::where('team_id', $teamId)
            ->orderByRaw('jersey_number IS NULL, jersey_number ASC')
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name', 'jersey_number', 'position', 'photo'])
            ->map(fn (self $p) => [
                'id' => (int) $p->id,
                'first_name' => $p->first_name,
                'last_name' => $p->last_name,
                'jersey_number' => $p->jersey_number,
                'position' => $p->position,
                'photo' => $p->photo,
            ])
            ->all();
    }

    /**
     * Aggregate performance for a squad (dashboard + exports).
     *
     * @return array<string, mixed>
     */
    public static function performanceSummaryForTeam(int $teamId): array
    {
        $row = static::where('team_id', $teamId)->first([
            DB::raw('COALESCE(SUM(goals), 0) AS goals'),
            DB::raw('COALESCE(SUM(assists), 0) AS assists'),
            DB::raw('COALESCE(SUM(appearances), 0) AS appearances'),
            DB::raw('COALESCE(SUM(minutes_played), 0) AS minutes_played'),
            DB::raw('AVG(rating) AS average_rating'),
        ]);

        return [
            'goals' => (int) ($row->goals ?? 0),
            'assists' => (int) ($row->assists ?? 0),
            'appearances' => (int) ($row->appearances ?? 0),
            'minutes_played' => (int) ($row->minutes_played ?? 0),
            'average_rating' => round((float) ($row->average_rating ?? 0), 2),
            'top_scorers' => static::topScorersForTeam($teamId, 5),
            'fitness' => static::fitnessOverview($teamId),
        ];
    }

    /**
     * Squad fitness overview: latest physical test status counts
     * (excellent/good/fair/poor/injured + "untested" players).
     *
     * @return array<string, int>
     */
    public static function fitnessOverview(int $teamId): array
    {
        $rows = DB::select(
            'SELECT m.fitness AS fitness, COUNT(*) AS total
             FROM players p
             LEFT JOIN player_metrics m ON m.id = (
                 SELECT m2.id FROM player_metrics m2
                 WHERE m2.player_id = p.id
                 ORDER BY m2.test_date DESC, m2.id DESC
                 LIMIT 1
             )
             WHERE p.team_id = ?
             GROUP BY m.fitness',
            [$teamId]
        );

        $overview = array_fill_keys([...PlayerMetric::FITNESS, 'untested'], 0);
        foreach ($rows as $row) {
            $status = $row->fitness ?? 'untested';
            $overview[$status] = (int) $row->total;
        }

        return $overview;
    }

    /**
     * Squad wellbeing/medical alerts: players whose latest test marks them
     * injured, or whose latest welfare assessment is low/struggling.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function alerts(int $teamId): array
    {
        $injured = DB::select(
            "SELECT p.id, p.first_name, p.last_name, p.jersey_number, p.photo,
                    m.fitness AS flag, m.notes AS flag_notes, m.test_date AS flag_date
             FROM players p
             JOIN player_metrics m ON m.id = (
                 SELECT m2.id FROM player_metrics m2
                 WHERE m2.player_id = p.id
                 ORDER BY m2.test_date DESC, m2.id DESC
                 LIMIT 1
             )
             WHERE p.team_id = ? AND m.fitness = 'injured'
             ORDER BY m.test_date DESC",
            [$teamId]
        );

        $welfare = DB::select(
            "SELECT p.id, p.first_name, p.last_name, p.jersey_number, p.photo,
                    w.wellbeing AS flag, w.notes AS flag_notes, w.assessment_date AS flag_date
             FROM players p
             JOIN player_welfare w ON w.id = (
                 SELECT w2.id FROM player_welfare w2
                 WHERE w2.player_id = p.id
                 ORDER BY w2.assessment_date DESC, w2.id DESC
                 LIMIT 1
             )
             WHERE p.team_id = ? AND w.wellbeing IN ('low', 'struggling')
             ORDER BY w.assessment_date DESC",
            [$teamId]
        );

        return array_merge(
            array_map(fn ($r) => (array) $r, $injured),
            array_map(fn ($r) => (array) $r, $welfare)
        );
    }

    /** Player row with its team name/code attached, or null. */
    public static function withTeam(int $id): ?array
    {
        $player = static::with('team:id,name,code')->find($id);

        return $player !== null ? static::row($player) : null;
    }
}
