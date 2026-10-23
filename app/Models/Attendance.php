<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/** Training-session attendance, optionally with a check-in photo. */
class Attendance extends Model
{
    use HasFactory;

    protected $table = 'attendance';

    public $timestamps = false;

    public const STATUSES = ['present', 'absent', 'late', 'excused'];

    protected $fillable = ['event_id', 'player_id', 'status', 'photo', 'notes', 'created_at'];

    protected $casts = [
        'event_id' => 'integer',
        'player_id' => 'integer',
        'created_at' => 'datetime',
    ];

    public function player()
    {
        return $this->belongsTo(Player::class);
    }

    public function event()
    {
        return $this->belongsTo(EventModel::class, 'event_id');
    }

    /** @return array<string, mixed> */
    private static function row(self $a): array
    {
        return $a->toArray() + [
            'checked_in_at' => $a->created_at?->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Attendance records for one event, joined with the player's profile.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function forEvent(int $eventId): array
    {
        return static::with('player:id,first_name,last_name,jersey_number,photo,position')
            ->where('event_id', $eventId)
            ->get()
            ->sortBy(fn (self $a) => ($a->player?->last_name ?? '').' '.($a->player?->first_name ?? ''))
            ->map(function (self $a) {
                return static::row($a) + [
                    'first_name' => $a->player?->first_name,
                    'last_name' => $a->player?->last_name,
                    'jersey_number' => $a->player?->jersey_number,
                    'player_photo' => $a->player?->photo,
                    'position' => $a->player?->position,
                ];
            })
            ->values()
            ->all();
    }

    /** A single player's attendance for one event (or null). */
    public static function forPlayerEvent(int $playerId, int $eventId): ?array
    {
        $a = static::where('event_id', $eventId)->where('player_id', $playerId)->first();

        return $a !== null ? static::row($a) : null;
    }

    /**
     * Full attendance rows for a player across several events, keyed by event id.
     *
     * @param  int[]  $eventIds
     * @return array<int, array<string, mixed>>
     */
    public static function forPlayerEvents(int $playerId, array $eventIds): array
    {
        if ($eventIds === []) {
            return [];
        }

        $map = [];
        foreach (static::where('player_id', $playerId)->whereIn('event_id', $eventIds)->get() as $row) {
            $map[(int) $row->event_id] = static::row($row);
        }

        return $map;
    }

    /**
     * Counts by status for several events at once, keyed by event id.
     *
     * @param  int[]  $eventIds
     * @return array<int, array<string, int>>
     */
    public static function countsForEvents(array $eventIds): array
    {
        if ($eventIds === []) {
            return [];
        }

        $rows = static::whereIn('event_id', $eventIds)
            ->selectRaw('event_id, status, COUNT(*) AS total')
            ->groupBy('event_id', 'status')
            ->get();

        $map = [];
        foreach ($rows as $row) {
            $eventId = (int) $row->event_id;
            if (! isset($map[$eventId])) {
                $map[$eventId] = array_fill_keys(self::STATUSES, 0) + ['total' => 0];
            }
            $map[$eventId][(string) $row->status] = (int) $row->total;
            $map[$eventId]['total'] += (int) $row->total;
        }

        return $map;
    }

    /**
     * A player's attendance history with the event it belongs to.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function historyForPlayer(int $playerId, int $limit = 100): array
    {
        $rows = DB::table('attendance')
            ->join('events', 'events.id', '=', 'attendance.event_id')
            ->where('attendance.player_id', $playerId)
            ->orderByDesc('events.starts_at')
            ->orderByDesc('attendance.id')
            ->limit($limit)
            ->get([
                'attendance.id',
                'attendance.event_id',
                'attendance.status',
                'attendance.photo',
                'attendance.notes',
                'attendance.created_at',
                'events.title AS event_title',
                'events.type AS event_type',
                'events.starts_at AS event_starts_at',
                'events.team_id AS team_id',
            ]);

        return $rows->map(static fn ($row): array => [
            'id' => (int) $row->id,
            'event_id' => (int) $row->event_id,
            'status' => (string) $row->status,
            'photo' => $row->photo,
            'notes' => $row->notes,
            'checked_in_at' => $row->created_at,
            'event_title' => $row->event_title,
            'event_type' => $row->event_type,
            'event_starts_at' => $row->event_starts_at,
            'team_id' => $row->team_id === null ? null : (int) $row->team_id,
        ])->all();
    }

    /**
     * Map of event_id => status for one player across the given events.
     *
     * @param  int[]  $eventIds
     * @return array<int, string>
     */
    public static function statusesForPlayer(int $playerId, array $eventIds): array
    {
        if ($eventIds === []) {
            return [];
        }

        $rows = static::where('player_id', $playerId)
            ->whereIn('event_id', array_map('intval', $eventIds))
            ->get(['event_id', 'status']);

        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row->event_id] = (string) $row->status;
        }

        return $map;
    }

    /**
     * Insert or update the attendance row for (event, player).
     * A null $photo keeps any previously uploaded photo. When $touch is true
     * the check-in timestamp (created_at = time taken) is refreshed.
     */
    public static function record(
        int $eventId,
        int $playerId,
        string $status,
        ?string $photo,
        ?string $notes,
        bool $touch = false
    ): void {
        $existing = static::where('event_id', $eventId)->where('player_id', $playerId)->first();

        if ($existing !== null) {
            $data = ['status' => $status, 'notes' => $notes];
            if ($photo !== null) {
                $data['photo'] = $photo;
            }
            if ($touch) {
                $data['created_at'] = now();
            }
            $existing->update($data);

            return;
        }

        static::create([
            'event_id' => $eventId,
            'player_id' => $playerId,
            'status' => $status,
            'photo' => $photo,
            'notes' => $notes,
            'created_at' => now(),
        ]);
    }

    /** Staff edit: change a status (and optional note) without touching the photo/time. */
    public static function setStatus(int $eventId, int $playerId, string $status, ?string $notes = null): void
    {
        $existing = static::where('event_id', $eventId)->where('player_id', $playerId)->first();

        if ($existing !== null) {
            $existing->update(['status' => $status, 'notes' => $notes]);

            return;
        }

        static::create([
            'event_id' => $eventId,
            'player_id' => $playerId,
            'status' => $status,
            'notes' => $notes,
        ]);
    }

    /** Count of present attendees for an event. */
    public static function countPresent(int $eventId): int
    {
        return static::where('event_id', $eventId)->where('status', 'present')->count();
    }

    /** Attendance tally by status for an event. */
    public static function countByStatus(int $eventId): array
    {
        $rows = static::where('event_id', $eventId)
            ->selectRaw('status, COUNT(*) AS total')
            ->groupBy('status')
            ->get();

        $tally = array_fill_keys(self::STATUSES, 0);
        foreach ($rows as $row) {
            $tally[$row->status] = (int) $row->total;
        }

        return $tally;
    }

    /** A player's attendance tally across all sessions (portal summary). */
    public static function summaryForPlayer(int $playerId): array
    {
        $rows = static::where('player_id', $playerId)
            ->selectRaw('status, COUNT(*) AS total')
            ->groupBy('status')
            ->get();

        $tally = array_fill_keys(self::STATUSES, 0);
        foreach ($rows as $row) {
            $tally[$row->status] = (int) $row->total;
        }

        return $tally;
    }

    /**
     * Attendance rates for several players (list views), keyed by player id.
     *
     * @param  int[]  $playerIds
     * @return array<int, array<string, int>>
     */
    public static function ratesForPlayers(array $playerIds): array
    {
        $ids = array_values(array_filter(array_map('intval', $playerIds)));
        if ($ids === []) {
            return [];
        }

        $rows = static::whereIn('player_id', $ids)
            ->selectRaw('player_id, status, COUNT(*) AS total')
            ->groupBy('player_id', 'status')
            ->get();

        $map = [];
        foreach ($rows as $row) {
            $id = (int) $row->player_id;
            if (! isset($map[$id])) {
                $map[$id] = array_fill_keys(self::STATUSES, 0) + [
                    'marked' => 0,
                    'attended' => 0,
                    'rate' => 0,
                ];
            }
            $map[$id][(string) $row->status] = (int) $row->total;
            $map[$id]['marked'] += (int) $row->total;
        }

        foreach ($map as &$tally) {
            $tally['attended'] = $tally['present'] + $tally['late'];
            $tally['rate'] = $tally['marked'] > 0
                ? (int) round($tally['attended'] / $tally['marked'] * 100)
                : 0;
        }
        unset($tally);

        return $map;
    }

    /** League-wide attendance tally (admin dashboard). */
    public static function overallSummary(): array
    {
        $rows = static::selectRaw('status, COUNT(*) AS total')
            ->groupBy('status')
            ->get();

        $tally = array_fill_keys(self::STATUSES, 0);
        foreach ($rows as $row) {
            $tally[(string) $row->status] = (int) $row->total;
        }

        return self::withRate($tally) + [
            'players' => (int) static::distinct()->count('player_id'),
            'sessions' => (int) static::distinct()->count('event_id'),
        ];
    }

    /** Most recent check-ins (admin + manager dashboards). */
    public static function recentCheckIns(int $limit = 8, ?int $teamId = null): array
    {
        $rows = DB::table('attendance')
            ->join('events', 'events.id', '=', 'attendance.event_id')
            ->join('players', 'players.id', '=', 'attendance.player_id')
            ->leftJoin('teams', 'teams.id', '=', 'events.team_id')
            ->when($teamId !== null, fn ($q) => $q->where('events.team_id', $teamId))
            ->orderByDesc('attendance.created_at')
            ->orderByDesc('attendance.id')
            ->limit($limit)
            ->get([
                'attendance.id',
                'attendance.player_id',
                'attendance.status',
                'attendance.photo',
                'attendance.notes',
                'attendance.created_at',
                'players.first_name',
                'players.last_name',
                'players.jersey_number',
                'events.id AS event_id',
                'events.title AS event_title',
                'events.type AS event_type',
                'events.starts_at AS event_starts_at',
                'teams.name AS team_name',
            ]);

        return $rows->map(static fn ($row): array => [
            'id' => (int) $row->id,
            'player_id' => (int) $row->player_id,
            'first_name' => $row->first_name,
            'last_name' => $row->last_name,
            'jersey_number' => $row->jersey_number === null ? null : (int) $row->jersey_number,
            'status' => (string) $row->status,
            'photo' => $row->photo,
            'notes' => $row->notes,
            'checked_in_at' => $row->created_at,
            'event_id' => (int) $row->event_id,
            'event_title' => $row->event_title,
            'event_type' => $row->event_type,
            'event_starts_at' => $row->event_starts_at,
            'team_name' => $row->team_name,
        ])->all();
    }

    /** Add totals + attendance percentage to a raw status tally. */
    public static function withRate(array $tally): array
    {
        $marked = 0;
        foreach (self::STATUSES as $status) {
            $marked += (int) ($tally[$status] ?? 0);
        }

        $attended = (int) ($tally['present'] ?? 0) + (int) ($tally['late'] ?? 0);

        return $tally + [
            'marked' => $marked,
            'attended' => $attended,
            'rate' => $marked > 0 ? (int) round($attended / $marked * 100) : 0,
        ];
    }

    /** Attendance tally for every session belonging to a team. */
    public static function teamSummary(int $teamId): array
    {
        $rows = DB::table('attendance')
            ->join('events', 'events.id', '=', 'attendance.event_id')
            ->where('events.team_id', $teamId)
            ->selectRaw('attendance.status, COUNT(*) AS total')
            ->groupBy('attendance.status')
            ->get();

        $tally = array_fill_keys(self::STATUSES, 0);
        foreach ($rows as $row) {
            $tally[(string) $row->status] = (int) $row->total;
        }

        return self::withRate($tally);
    }

    /**
     * Per-player attendance for a team, including players with no records.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function teamPerPlayer(int $teamId): array
    {
        $rows = DB::table('players')
            ->leftJoin('attendance', 'attendance.player_id', '=', 'players.id')
            ->where('players.team_id', $teamId)
            ->selectRaw("players.id, players.first_name, players.last_name,
                players.jersey_number, players.position, players.photo,
                SUM(CASE WHEN attendance.status = 'present' THEN 1 ELSE 0 END) AS present,
                SUM(CASE WHEN attendance.status = 'late' THEN 1 ELSE 0 END) AS late,
                SUM(CASE WHEN attendance.status = 'absent' THEN 1 ELSE 0 END) AS absent,
                SUM(CASE WHEN attendance.status = 'excused' THEN 1 ELSE 0 END) AS excused,
                COUNT(attendance.id) AS marked")
            ->groupBy(
                'players.id', 'players.first_name', 'players.last_name',
                'players.jersey_number', 'players.position', 'players.photo'
            )
            ->orderBy('players.last_name')
            ->orderBy('players.first_name')
            ->get();

        return $rows->map(static function ($row): array {
            $present = (int) $row->present;
            $late = (int) $row->late;
            $absent = (int) $row->absent;
            $excused = (int) $row->excused;
            $marked = (int) $row->marked;
            $attended = $present + $late;

            return [
                'player_id' => (int) $row->id,
                'first_name' => $row->first_name,
                'last_name' => $row->last_name,
                'jersey_number' => $row->jersey_number === null ? null : (int) $row->jersey_number,
                'position' => $row->position,
                'photo' => $row->photo,
                'present' => $present,
                'late' => $late,
                'absent' => $absent,
                'excused' => $excused,
                'marked' => $marked,
                'attended' => $attended,
                'rate' => $marked > 0 ? (int) round($attended / $marked * 100) : 0,
            ];
        })->all();
    }

    /**
     * Six-month attendance trend for a team (attended vs marked per month).
     *
     * @return array<int, array<string, mixed>>
     */
    public static function teamMonthlyTrend(int $teamId, int $months = 6): array
    {
        $start = now()->subMonths($months - 1)->startOfMonth();

        $rows = DB::table('attendance')
            ->join('events', 'events.id', '=', 'attendance.event_id')
            ->where('events.team_id', $teamId)
            ->where('events.starts_at', '>=', $start)
            ->selectRaw("DATE_FORMAT(events.starts_at, '%Y-%m') AS month,
                attendance.status, COUNT(*) AS total")
            ->groupBy('month', 'attendance.status')
            ->get();

        $buckets = [];
        for ($i = $months - 1; $i >= 0; $i--) {
            $key = now()->subMonths($i)->format('Y-m');
            $buckets[$key] = [
                'month' => $key,
                'label' => now()->subMonths($i)->format('M'),
                'present' => 0,
                'late' => 0,
                'absent' => 0,
                'excused' => 0,
                'marked' => 0,
                'rate' => 0,
            ];
        }

        foreach ($rows as $row) {
            $key = (string) $row->month;
            if (! isset($buckets[$key])) {
                continue;
            }
            $buckets[$key][(string) $row->status] = (int) $row->total;
            $buckets[$key]['marked'] += (int) $row->total;
        }

        foreach ($buckets as &$bucket) {
            $attended = $bucket['present'] + $bucket['late'];
            $bucket['rate'] = $bucket['marked'] > 0
                ? (int) round($attended / $bucket['marked'] * 100)
                : 0;
        }
        unset($bucket);

        return array_values($buckets);
    }
}
