<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MatchModel extends Model
{
    use HasFactory;

    protected $table = 'matches';

    public $timestamps = false;

    public const STATUSES = ['scheduled', 'live', 'finished'];

    protected $fillable = [
        'tournament_id', 'home_team_id', 'away_team_id',
        'home_score', 'away_score', 'match_date', 'venue', 'status',
    ];

    protected $casts = [
        'tournament_id' => 'integer',
        'home_team_id' => 'integer',
        'away_team_id' => 'integer',
        'home_score' => 'integer',
        'away_score' => 'integer',
        'match_date' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function tournament()
    {
        return $this->belongsTo(Tournament::class);
    }

    public function homeTeam()
    {
        return $this->belongsTo(Team::class, 'home_team_id');
    }

    public function awayTeam()
    {
        return $this->belongsTo(Team::class, 'away_team_id');
    }

    /** Base query with the joined team/tournament columns the views expect. */
    private static function joined()
    {
        return static::query()->with(['homeTeam:id,name,code', 'awayTeam:id,name,code', 'tournament:id,name']);
    }

    /** @return array<string, mixed> */
    private static function row(self $m): array
    {
        $row = $m->toArray();
        $row['home_name'] = $m->homeTeam?->name;
        $row['home_code'] = $m->homeTeam?->code;
        $row['away_name'] = $m->awayTeam?->name;
        $row['away_code'] = $m->awayTeam?->code;
        $row['tournament_name'] = $m->tournament?->name;
        // The original rows carried a datetime string for match_date.
        $row['match_date'] = $m->match_date?->format('Y-m-d H:i:s');

        return $row;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function paginate(int $limit, int $offset, array $filters = []): array
    {
        return static::applyFilters(static::joined(), $filters)
            ->orderByDesc('match_date')
            ->orderByDesc('id')
            ->skip($offset)
            ->take($limit)
            ->get()
            ->map(fn (self $m) => static::row($m))
            ->all();
    }

    public static function countFiltered(array $filters = []): int
    {
        return static::applyFilters(static::query(), $filters)->count();
    }

    public static function countAll(): int
    {
        return static::count();
    }

    private static function applyFilters($query, array $filters)
    {
        return $query
            ->when(! empty($filters['tournament_id']), fn ($q) => $q->where('tournament_id', (int) $filters['tournament_id']))
            ->when(! empty($filters['status']), fn ($q) => $q->where('status', $filters['status']));
    }

    public static function recent(int $limit = 5): array
    {
        return static::joined()->where('status', 'finished')
            ->orderByDesc('match_date')->orderByDesc('id')->limit($limit)->get()
            ->map(fn (self $m) => static::row($m))->all();
    }

    public static function upcoming(int $limit = 5): array
    {
        return static::joined()->where('status', 'scheduled')
            ->orderBy('match_date')->limit($limit)->get()
            ->map(fn (self $m) => static::row($m))->all();
    }

    /** Matches kicking off between two datetimes (calendar overlay). */
    public static function between(string $from, string $to, ?int $teamId): array
    {
        return static::joined()
            ->where('match_date', '>=', $from)
            ->where('match_date', '<', $to)
            ->when($teamId !== null, fn ($q) => $q->where(fn ($w) => $w
                ->where('home_team_id', $teamId)->orWhere('away_team_id', $teamId)))
            ->orderBy('match_date')
            ->get()
            ->map(fn (self $m) => static::row($m))->all();
    }

    /** Latest finished results involving one team. */
    public static function recentForTeam(int $teamId, int $limit = 5): array
    {
        return static::joined()->where('status', 'finished')
            ->where(fn ($q) => $q->where('home_team_id', $teamId)->orWhere('away_team_id', $teamId))
            ->orderByDesc('match_date')->orderByDesc('id')->limit($limit)->get()
            ->map(fn (self $m) => static::row($m))->all();
    }

    /** Next scheduled fixtures involving one team. */
    public static function upcomingForTeam(int $teamId, int $limit = 5): array
    {
        return static::joined()->where('status', 'scheduled')
            ->where(fn ($q) => $q->where('home_team_id', $teamId)->orWhere('away_team_id', $teamId))
            ->orderBy('match_date')->limit($limit)->get()
            ->map(fn (self $m) => static::row($m))->all();
    }

    public static function finishedForTournament(int $tournamentId): array
    {
        return static::joined()->where('tournament_id', $tournamentId)
            ->where('status', 'finished')->orderBy('match_date')->get()
            ->map(fn (self $m) => static::row($m))->all();
    }

    /** Match row with teams/tournament attached, or null. */
    public static function withTeams(int $id): ?array
    {
        $match = static::joined()->find($id);

        return $match !== null ? static::row($match) : null;
    }

    /**
     * Compute a league table (P W D L GF GA GD Pts) from finished matches.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function standings(int $tournamentId): array
    {
        $matches = self::finishedForTournament($tournamentId);
        $stats = [];

        foreach ($matches as $m) {
            foreach (['home', 'away'] as $side) {
                $teamId = (int) $m[$side.'_team_id'];
                if (! isset($stats[$teamId])) {
                    $stats[$teamId] = [
                        'team_id' => $teamId,
                        'team_name' => $m[$side.'_name'],
                        'team_code' => $m[$side.'_code'],
                        'played' => 0, 'won' => 0, 'drawn' => 0, 'lost' => 0,
                        'gf' => 0, 'ga' => 0, 'points' => 0,
                    ];
                }
            }

            $homeId = (int) $m['home_team_id'];
            $awayId = (int) $m['away_team_id'];
            $homeScore = (int) $m['home_score'];
            $awayScore = (int) $m['away_score'];

            $stats[$homeId]['played']++;
            $stats[$awayId]['played']++;
            $stats[$homeId]['gf'] += $homeScore;
            $stats[$homeId]['ga'] += $awayScore;
            $stats[$awayId]['gf'] += $awayScore;
            $stats[$awayId]['ga'] += $homeScore;

            if ($homeScore > $awayScore) {
                $stats[$homeId]['won']++;
                $stats[$homeId]['points'] += 3;
                $stats[$awayId]['lost']++;
            } elseif ($homeScore < $awayScore) {
                $stats[$awayId]['won']++;
                $stats[$awayId]['points'] += 3;
                $stats[$homeId]['lost']++;
            } else {
                $stats[$homeId]['drawn']++;
                $stats[$awayId]['drawn']++;
                $stats[$homeId]['points']++;
                $stats[$awayId]['points']++;
            }
        }

        // No finished matches yet: show every team with zeroed stats.
        if ($stats === []) {
            foreach (Team::options() as $team) {
                $stats[(int) $team['id']] = [
                    'team_id' => (int) $team['id'],
                    'team_name' => $team['name'],
                    'team_code' => $team['code'],
                    'played' => 0, 'won' => 0, 'drawn' => 0, 'lost' => 0,
                    'gf' => 0, 'ga' => 0, 'points' => 0,
                ];
            }
        }

        usort($stats, static fn (array $a, array $b): int => [
            $b['points'], $b['gf'] - $b['ga'], $b['gf'], $b['team_name'],
        ] <=> [
            $a['points'], $a['gf'] - $a['ga'], $a['gf'], $a['team_name'],
        ]);

        foreach ($stats as &$row) {
            $row['gd'] = $row['gf'] - $row['ga'];
        }
        unset($row);

        return $stats;
    }
}
