<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/** Mental / wellbeing assessments (one player, many rows). */
class PlayerWelfare extends Model
{
    protected static string $table = 'player_welfare';

    public const WELLBEING = ['great', 'good', 'okay', 'low', 'struggling'];

    public static function forPlayer(int $playerId): array
    {
        return Database::query(
            'SELECT w.*, u.name AS assessed_by_name
             FROM player_welfare w
             LEFT JOIN users u ON u.id = w.assessed_by
             WHERE w.player_id = :pid
             ORDER BY w.assessment_date DESC, w.id DESC',
            [':pid' => $playerId]
        )->fetchAll();
    }

    public static function latestFor(int $playerId): ?array
    {
        $row = Database::query(
            'SELECT * FROM player_welfare WHERE player_id = :pid
             ORDER BY assessment_date DESC, id DESC LIMIT 1',
            [':pid' => $playerId]
        )->fetch();

        return $row !== false ? $row : null;
    }

    /** Welfare row with its owning player's team (for authorization). */
    public static function withPlayer(int $id): ?array
    {
        $row = Database::query(
            'SELECT w.*, p.team_id, p.first_name, p.last_name
             FROM player_welfare w
             JOIN players p ON p.id = w.player_id
             WHERE w.id = :id',
            [':id' => $id]
        )->fetch();

        return $row !== false ? $row : null;
    }
}
