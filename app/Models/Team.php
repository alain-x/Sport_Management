<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

class Team extends Model
{
    protected static string $table = 'teams';

    public static function paginate(int $limit, int $offset, string $search = ''): array
    {
        $where = '';
        $params = [];
        if ($search !== '') {
            $where = ' WHERE t.name LIKE :q1 OR t.code LIKE :q2 OR t.city LIKE :q3 OR t.coach LIKE :q4';
            $params[':q1'] = '%' . $search . '%';
            $params[':q2'] = '%' . $search . '%';
            $params[':q3'] = '%' . $search . '%';
            $params[':q4'] = '%' . $search . '%';
        }

        $sql = 'SELECT t.*, COUNT(p.id) AS players_count
                FROM teams t
                LEFT JOIN players p ON p.team_id = t.id'
            . $where
            . ' GROUP BY t.id
               ORDER BY t.name ASC
               LIMIT ' . $limit . ' OFFSET ' . $offset;
        return Database::query($sql, $params)->fetchAll();
    }

    public static function countSearch(string $search = ''): int
    {
        if ($search === '') {
            return self::countAll();
        }

        return (int) Database::query(
            'SELECT COUNT(*) FROM teams WHERE name LIKE :q1 OR code LIKE :q2 OR city LIKE :q3 OR coach LIKE :q4',
            [
                ':q1' => '%' . $search . '%',
                ':q2' => '%' . $search . '%',
                ':q3' => '%' . $search . '%',
                ':q4' => '%' . $search . '%',
            ]
        )->fetchColumn();
    }

    /** Compact id => name/code list for dropdowns. */
    public static function options(): array
    {
        return Database::query('SELECT id, name, code FROM teams ORDER BY name ASC')->fetchAll();
    }

    /** Team row with its staff accounts attached. */
    public static function withStaff(int $id): ?array
    {
        $team = self::find($id);
        if ($team === null) {
            return null;
        }

        $team['staff'] = User::forTeam($id);
        $team['staff_count'] = count($team['staff']);

        return $team;
    }

    public static function withPlayers(int $id): ?array
    {
        $team = self::find($id);
        if ($team === null) {
            return null;
        }

        $team['players'] = Database::query(
            'SELECT * FROM players WHERE team_id = :id ORDER BY jersey_number IS NULL, jersey_number ASC, last_name ASC',
            [':id' => $id]
        )->fetchAll();

        $team['players_count'] = count($team['players']);
        $team['finished_matches'] = (int) Database::query(
            'SELECT COUNT(*) FROM matches
             WHERE (home_team_id = :home OR away_team_id = :away) AND status = \'finished\'',
            [':home' => $id, ':away' => $id]
        )->fetchColumn();

        return $team;
    }
}
