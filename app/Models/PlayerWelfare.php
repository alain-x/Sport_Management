<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/** Mental / wellbeing assessments (one player, many rows). */
class PlayerWelfare extends Model
{
    use HasFactory;

    protected $table = 'player_welfare';

    public $timestamps = false;

    public const WELLBEING = ['great', 'good', 'okay', 'low', 'struggling'];

    protected $fillable = [
        'player_id', 'assessment_date', 'confidence', 'focus', 'motivation',
        'stress', 'wellbeing', 'notes', 'assessed_by',
    ];

    protected $casts = [
        'player_id' => 'integer',
        'assessment_date' => 'date',
        'confidence' => 'integer',
        'focus' => 'integer',
        'motivation' => 'integer',
        'stress' => 'integer',
        'assessed_by' => 'integer',
        'created_at' => 'datetime',
    ];

    public function player()
    {
        return $this->belongsTo(Player::class);
    }

    public function assessor()
    {
        return $this->belongsTo(User::class, 'assessed_by');
    }

    /** @return array<string, mixed> */
    private static function row(self $w): array
    {
        $row = $w->toArray();
        $row['assessment_date'] = $w->assessment_date?->format('Y-m-d');
        $row['assessed_by_name'] = $w->assessor?->name;

        return $row;
    }

    /** @return array<int, array<string, mixed>> */
    public static function forPlayer(int $playerId): array
    {
        return static::with('assessor:id,name')
            ->where('player_id', $playerId)
            ->orderByDesc('assessment_date')->orderByDesc('id')
            ->get()
            ->map(fn (self $w) => static::row($w))->all();
    }

    public static function latestFor(int $playerId): ?array
    {
        $w = static::with('assessor:id,name')
            ->where('player_id', $playerId)
            ->orderByDesc('assessment_date')->orderByDesc('id')
            ->first();

        return $w !== null ? static::row($w) : null;
    }

    /** Welfare row with its owning player's team (for authorization), or null. */
    public static function withPlayer(int $id): ?array
    {
        $w = static::with('player:id,team_id,first_name,last_name')->find($id);
        if ($w === null) {
            return null;
        }

        return $w->toArray() + [
            'team_id' => $w->player?->team_id,
            'first_name' => $w->player?->first_name,
            'last_name' => $w->player?->last_name,
        ];
    }
}
