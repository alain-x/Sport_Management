<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

class Tournament extends Model
{
    protected static string $table = 'tournaments';

    public const STATUSES = ['upcoming', 'ongoing', 'completed'];

    public static function allWithCounts(string $orderBy = 'start_date DESC'): array
    {
        return Database::query(
            'SELECT tt.*, COUNT(m.id) AS matches_count
             FROM tournaments tt
             LEFT JOIN matches m ON m.tournament_id = tt.id
             GROUP BY tt.id
             ORDER BY ' . $orderBy
        )->fetchAll();
    }

    public static function byStatus(string $status, int $limit = 10): array
    {
        return Database::query(
            'SELECT * FROM tournaments WHERE status = :status ORDER BY start_date DESC LIMIT ' . $limit,
            [':status' => $status]
        )->fetchAll();
    }

    public static function withMatches(int $id): ?array
    {
        $tournament = self::find($id);
        if ($tournament === null) {
            return null;
        }

        $tournament['matches'] = Database::query(
            'SELECT m.*, ht.name AS home_name, ht.code AS home_code,
                    at.name AS away_name, at.code AS away_code
             FROM matches m
             JOIN teams ht ON ht.id = m.home_team_id
             JOIN teams at ON at.id = m.away_team_id
             WHERE m.tournament_id = :id
             ORDER BY m.match_date ASC',
            [':id' => $id]
        )->fetchAll();

        return $tournament;
    }
}
