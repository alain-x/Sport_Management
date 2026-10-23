<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/** Calendar entries (training, meetings, travel, deadlines, media). */
class EventModel extends Model
{
    use HasFactory;

    protected $table = 'events';

    public $timestamps = false;

    public const TYPES = ['training', 'meeting', 'travel', 'deadline', 'media', 'other'];

    /** Check-in opens this many minutes before kick-off by default. */
    public const DEFAULT_CHECKIN_OPENS_MINUTES = 20;

    /** Check-ins this many minutes past kick-off are recorded as late. */
    public const DEFAULT_CHECKIN_LATE_MINUTES = 10;

    /** Hard bounds for the configurable check-in minute values. */
    public const MAX_CHECKIN_MINUTES = 180;

    protected $fillable = [
        'title', 'type', 'starts_at', 'checkin_opens_minutes', 'checkin_late_minutes',
        'team_id', 'tournament_id', 'description', 'created_by',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'checkin_opens_minutes' => 'integer',
        'checkin_late_minutes' => 'integer',
        'team_id' => 'integer',
        'tournament_id' => 'integer',
        'created_by' => 'integer',
        'created_at' => 'datetime',
    ];

    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    public function tournament()
    {
        return $this->belongsTo(Tournament::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function attendance()
    {
        return $this->hasMany(Attendance::class, 'event_id');
    }

    private static function joined()
    {
        return static::with(['team:id,name,code', 'tournament:id,name', 'creator:id,name']);
    }

    /** @return array<string, mixed> */
    private static function row(self $e): array
    {
        $row = $e->toArray();
        $row['team_name'] = $e->team?->name;
        $row['team_code'] = $e->team?->code;
        $row['tournament_name'] = $e->tournament?->name;
        $row['creator_name'] = $e->creator?->name;
        // Views format starts_at with strtotime(); keep the datetime string shape.
        $row['starts_at'] = $e->starts_at?->format('Y-m-d H:i:s');
        $row['checkin_opens_minutes'] = $e->checkin_opens_minutes
            ?? self::DEFAULT_CHECKIN_OPENS_MINUTES;
        $row['checkin_late_minutes'] = $e->checkin_late_minutes
            ?? self::DEFAULT_CHECKIN_LATE_MINUTES;
        $row['checkin_opens_at'] = $e->checkinOpensAt()?->format('Y-m-d H:i:s');
        $row['checkin_late_at'] = $e->checkinLateAt()?->format('Y-m-d H:i:s');

        return $row;
    }

    /** When check-in opens (start minus the configured early window). */
    public function checkinOpensAt(): ?Carbon
    {
        if ($this->starts_at === null) {
            return null;
        }

        $minutes = (int) ($this->checkin_opens_minutes
            ?? self::DEFAULT_CHECKIN_OPENS_MINUTES);

        return $this->starts_at->copy()->subMinutes($minutes);
    }

    /** When a check-in stops counting as on time (start plus the grace period). */
    public function checkinLateAt(): ?Carbon
    {
        if ($this->starts_at === null) {
            return null;
        }

        $minutes = (int) ($this->checkin_late_minutes
            ?? self::DEFAULT_CHECKIN_LATE_MINUTES);

        return $this->starts_at->copy()->addMinutes($minutes);
    }

    /**
     * Events between $from and $to visible to the given team scope.
     * $teamId = null means unrestricted (admin); otherwise league-wide
     * events (team_id NULL) plus that team's own events are visible.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function between(string $from, string $to, ?int $teamId): array
    {
        return static::joined()
            ->where('starts_at', '>=', $from)
            ->where('starts_at', '<', $to)
            ->when($teamId !== null, fn ($q) => $q->where(fn ($w) => $w
                ->whereNull('team_id')->orWhere('team_id', $teamId)))
            ->orderBy('starts_at')
            ->get()
            ->map(fn (self $e) => static::row($e))->all();
    }

    /** Next $limit upcoming events visible to the given team scope. */
    public static function upcoming(int $limit, ?int $teamId, ?int $onlyTeamId = null): array
    {
        $from = now();
        $to = now()->addDays(90);

        return static::joined()
            ->where('starts_at', '>=', $from)
            ->where('starts_at', '<', $to)
            ->when($onlyTeamId !== null, fn ($q) => $q->where('team_id', $onlyTeamId))
            ->when($onlyTeamId === null && $teamId !== null, fn ($q) => $q->where(fn ($w) => $w
                ->whereNull('team_id')->orWhere('team_id', $teamId)))
            ->orderBy('starts_at')
            ->limit($limit)
            ->get()
            ->map(fn (self $e) => static::row($e))->all();
    }

    /** Event row with details attached, or null. */
    public static function withDetails(int $id): ?array
    {
        $event = static::joined()->find($id);

        return $event !== null ? static::row($event) : null;
    }

    /** Event row as an array, or null. */
    public static function findArray(int $id): ?array
    {
        $event = static::find($id);

        return $event !== null ? static::row($event) : null;
    }
}
