<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/** Physical fitness test history (one player, many rows). */
class PlayerMetric extends Model
{
    protected static string $table = 'player_metrics';

    public const FITNESS = ['excellent', 'good', 'fair', 'poor', 'injured'];

    public static function forPlayer(int $playerId): array
    {
        return Database::query(
            'SELECT m.*, u.name AS recorded_by_name
             FROM player_metrics m
             LEFT JOIN users u ON u.id = m.recorded_by
             WHERE m.player_id = :pid
             ORDER BY m.test_date DESC, m.id DESC',
            [':pid' => $playerId]
        )->fetchAll();
    }

    public static function latestFor(int $playerId): ?array
    {
        $row = Database::query(
            'SELECT * FROM player_metrics WHERE player_id = :pid
             ORDER BY test_date DESC, id DESC LIMIT 1',
            [':pid' => $playerId]
        )->fetch();

        return $row !== false ? $row : null;
    }

    /** Metric row with its owning player's team (for authorization). */
    public static function withPlayer(int $id): ?array
    {
        $row = Database::query(
            'SELECT m.*, p.team_id, p.first_name, p.last_name
             FROM player_metrics m
             JOIN players p ON p.id = m.player_id
             WHERE m.id = :id',
            [':id' => $id]
        )->fetch();

        return $row !== false ? $row : null;
    }
}
