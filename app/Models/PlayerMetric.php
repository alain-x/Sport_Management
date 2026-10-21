<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/** Physical fitness test history (one player, many rows). */
class PlayerMetric extends Model
{
    use HasFactory;

    protected $table = 'player_metrics';

    public $timestamps = false;

    public const FITNESS = ['excellent', 'good', 'fair', 'poor', 'injured'];

    protected $fillable = [
        'player_id', 'test_date', 'height_cm', 'weight_kg', 'top_speed',
        'stamina', 'strength', 'agility', 'body_fat', 'fitness', 'notes', 'recorded_by',
    ];

    protected $casts = [
        'player_id' => 'integer',
        'test_date' => 'date',
        'height_cm' => 'float',
        'weight_kg' => 'float',
        'top_speed' => 'float',
        'stamina' => 'integer',
        'strength' => 'integer',
        'agility' => 'integer',
        'body_fat' => 'integer',
        'recorded_by' => 'integer',
        'created_at' => 'datetime',
    ];

    public function player()
    {
        return $this->belongsTo(Player::class);
    }

    public function recorder()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /** @return array<string, mixed> */
    private static function row(self $m): array
    {
        $row = $m->toArray();
        $row['test_date'] = $m->test_date?->format('Y-m-d');
        $row['recorded_by_name'] = $m->recorder?->name;

        return $row;
    }

    /** @return array<int, array<string, mixed>> */
    public static function forPlayer(int $playerId): array
    {
        return static::with('recorder:id,name')
            ->where('player_id', $playerId)
            ->orderByDesc('test_date')->orderByDesc('id')
            ->get()
            ->map(fn (self $m) => static::row($m))->all();
    }

    public static function latestFor(int $playerId): ?array
    {
        $m = static::with('recorder:id,name')
            ->where('player_id', $playerId)
            ->orderByDesc('test_date')->orderByDesc('id')
            ->first();

        return $m !== null ? static::row($m) : null;
    }

    /** Metric row with its owning player's team (for authorization), or null. */
    public static function withPlayer(int $id): ?array
    {
        $m = static::with('player:id,team_id,first_name,last_name')->find($id);
        if ($m === null) {
            return null;
        }

        return $m->toArray() + [
            'team_id' => $m->player?->team_id,
            'first_name' => $m->player?->first_name,
            'last_name' => $m->player?->last_name,
        ];
    }
}
