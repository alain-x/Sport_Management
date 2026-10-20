<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

class Player extends Model
{
    protected static string $table = 'players';

    public const POSITIONS = ['Goalkeeper', 'Defender', 'Midfielder', 'Forward'];

    public static function paginate(int $limit, int $offset, string $search = '', ?int $teamId = null): array
    {
        $where = [];
        $params = [];
        if ($search !== '') {
            $where[] = '(p.first_name LIKE :q1 OR p.last_name LIKE :q2 OR p.nationality LIKE :q3)';
            $params[':q1'] = '%' . $search . '%';
            $params[':q2'] = '%' . $search . '%';
            $params[':q3'] = '%' . $search . '%';
        }
        if ($teamId !== null) {
            $where[] = 'p.team_id = :team_id';
            $params[':team_id'] = $teamId;
        }
        $whereSql = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);

        $sql = 'SELECT p.*, t.name AS team_name, t.code AS team_code
                FROM players p
                JOIN teams t ON t.id = p.team_id'
            . $whereSql
            . ' ORDER BY p.last_name ASC, p.first_name ASC
               LIMIT ' . $limit . ' OFFSET ' . $offset;

        return Database::query($sql, $params)->fetchAll();
    }

    public static function countFiltered(string $search = '', ?int $teamId = null): int
    {
        $where = [];
        $params = [];
        if ($search !== '') {
            $where[] = '(p.first_name LIKE :q1 OR p.last_name LIKE :q2 OR p.nationality LIKE :q3)';
            $params[':q1'] = '%' . $search . '%';
            $params[':q2'] = '%' . $search . '%';
            $params[':q3'] = '%' . $search . '%';
        }
        if ($teamId !== null) {
            $where[] = 'p.team_id = :team_id';
            $params[':team_id'] = $teamId;
        }
        $whereSql = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);

        return (int) Database::query('SELECT COUNT(*) FROM players p' . $whereSql, $params)->fetchColumn();
    }

    public static function topScorers(int $limit = 5): array
    {
        return Database::query(
            'SELECT p.*, t.name AS team_name, t.code AS team_code
             FROM players p
             JOIN teams t ON t.id = p.team_id
             WHERE p.goals > 0
             ORDER BY p.goals DESC, p.assists DESC, p.last_name ASC
             LIMIT ' . $limit
        )->fetchAll();
    }

    /** Top scorers limited to one team (team dashboard). */
    public static function topScorersForTeam(int $teamId, int $limit = 5): array
    {
        return Database::query(
            'SELECT p.*, t.name AS team_name, t.code AS team_code
             FROM players p
             JOIN teams t ON t.id = p.team_id
             WHERE p.team_id = :tid AND p.goals > 0
             ORDER BY p.goals DESC, p.assists DESC, p.last_name ASC
             LIMIT ' . $limit,
            [':tid' => $teamId]
        )->fetchAll();
    }

    /**
     * Squad fitness overview: latest physical test status counts
     * (excellent/good/fair/poor/injured + "untested" players).
     *
     * @return array<string, int>
     */
    public static function fitnessOverview(int $teamId): array
    {
        $rows = Database::query(
            'SELECT m.fitness AS fitness, COUNT(*) AS total
             FROM players p
             LEFT JOIN player_metrics m ON m.id = (
                 SELECT m2.id FROM player_metrics m2
                 WHERE m2.player_id = p.id
                 ORDER BY m2.test_date DESC, m2.id DESC
                 LIMIT 1
             )
             WHERE p.team_id = :tid
             GROUP BY m.fitness',
            [':tid' => $teamId]
        )->fetchAll();

        $overview = array_fill_keys([...PlayerMetric::FITNESS, 'untested'], 0);
        foreach ($rows as $row) {
            $status = $row['fitness'] ?? 'untested';
            $overview[$status] = (int) $row['total'];
        }

        return $overview;
    }

    /**
     * Squad wellbeing/medical alerts: players whose latest test marks them
     * injured, or whose latest welfare assessment is low/struggling.
     */
    public static function alerts(int $teamId): array
    {
        $injured = Database::query(
            'SELECT p.id, p.first_name, p.last_name, p.jersey_number, p.photo,
                    m.fitness AS flag, m.notes AS flag_notes, m.test_date AS flag_date
             FROM players p
             JOIN player_metrics m ON m.id = (
                 SELECT m2.id FROM player_metrics m2
                 WHERE m2.player_id = p.id
                 ORDER BY m2.test_date DESC, m2.id DESC
                 LIMIT 1
             )
             WHERE p.team_id = :tid AND m.fitness = \'injured\'
             ORDER BY m.test_date DESC',
            [':tid' => $teamId]
        )->fetchAll();

        $welfare = Database::query(
            'SELECT p.id, p.first_name, p.last_name, p.jersey_number, p.photo,
                    w.wellbeing AS flag, w.notes AS flag_notes, w.assessment_date AS flag_date
             FROM players p
             JOIN player_welfare w ON w.id = (
                 SELECT w2.id FROM player_welfare w2
                 WHERE w2.player_id = p.id
                 ORDER BY w2.assessment_date DESC, w2.id DESC
                 LIMIT 1
             )
             WHERE p.team_id = :tid AND w.wellbeing IN (\'low\', \'struggling\')
             ORDER BY w.assessment_date DESC',
            [':tid' => $teamId]
        )->fetchAll();

        return array_merge($injured, $welfare);
    }

    public static function withTeam(int $id): ?array
    {
        $player = Database::query(
            'SELECT p.*, t.name AS team_name, t.code AS team_code
             FROM players p JOIN teams t ON t.id = p.team_id
             WHERE p.id = :id',
            [':id' => $id]
        )->fetch();

        return $player !== false ? $player : null;
    }
}
