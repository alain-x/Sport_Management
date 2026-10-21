<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/** Training-session attendance, optionally with a check-in photo. */
class Attendance extends Model
{
    use HasFactory;

    protected $table = 'attendance';

    public $timestamps = false;

    public const STATUSES = ['present', 'absent', 'late', 'excused'];

    protected $fillable = ['event_id', 'player_id', 'status', 'photo', 'notes'];

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
                return $a->toArray() + [
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
        return static::where('event_id', $eventId)->where('player_id', $playerId)->first()?->toArray();
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
     * A null $photo keeps any previously uploaded photo.
     */
    public static function record(int $eventId, int $playerId, string $status, ?string $photo, ?string $notes): void
    {
        $existing = static::where('event_id', $eventId)->where('player_id', $playerId)->first();

        if ($existing !== null) {
            $data = ['status' => $status, 'notes' => $notes];
            if ($photo !== null) {
                $data['photo'] = $photo;
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
}
