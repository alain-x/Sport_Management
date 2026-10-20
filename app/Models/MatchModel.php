<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

class MatchModel extends Model
{
    protected static string $table = 'matches';

    public const STATUSES = ['scheduled', 'live', 'finished'];

    private const SELECT_JOINED = 'SELECT m.*,
                ht.name AS home_name, ht.code AS home_code,
                at.name AS away_name, at.code AS away_code,
                tt.name AS tournament_name
             FROM matches m
             JOIN teams ht ON ht.id = m.home_team_id
             JOIN teams at ON at.id = m.away_team_id
             LEFT JOIN tournaments tt ON tt.id = m.tournament_id';

    public static function paginate(int $limit, int $offset, array $filters = []): array
    {
        [$where, $params] = self::filters($filters);

        return Database::query(
            self::SELECT_JOINED . $where . ' ORDER BY m.match_date DESC, m.id DESC
            LIMIT ' . $limit . ' OFFSET ' . $offset,
            $params
        )->fetchAll();
    }

    public static function countFiltered(array $filters = []): int
    {
        [$where, $params] = self::filters($filters);

        return (int) Database::query('SELECT COUNT(*) FROM matches m' . $where, $params)->fetchColumn();
    }

    /** @return array{0:string,1:array} */
    private static function filters(array $filters): array
    {
        $parts = [];
        $params = [];

        if (!empty($filters['tournament_id'])) {
            $parts[] = 'm.tournament_id = :tournament_id';
            $params[':tournament_id'] = (int) $filters['tournament_id'];
        }
        if (!empty($filters['status'])) {
            $parts[] = 'm.status = :status';
            $params[':status'] = $filters['status'];
        }

        return [$parts === [] ? '' : ' WHERE ' . implode(' AND ', $parts), $params];
    }

    public static function recent(int $limit = 5): array
    {
        return Database::query(
            self::SELECT_JOINED . " WHERE m.status = 'finished'
             ORDER BY m.match_date DESC, m.id DESC LIMIT " . $limit
        )->fetchAll();
    }

    public static function upcoming(int $limit = 5): array
    {
        return Database::query(
            self::SELECT_JOINED . " WHERE m.status = 'scheduled'
             ORDER BY m.match_date ASC LIMIT " . $limit
        )->fetchAll();
    }

    /**
     * Matches kicking off between two datetimes (calendar overlay).
     * $teamId = null shows every match; otherwise only that team's fixtures.
     */
    public static function between(string $from, string $to, ?int $teamId): array
    {
        $where = 'm.match_date >= :from AND m.match_date < :to';
        $params = [':from' => $from, ':to' => $to];

        if ($teamId !== null) {
            $where .= ' AND (m.home_team_id = :scope_home OR m.away_team_id = :scope_away)';
            $params[':scope_home'] = $teamId;
            $params[':scope_away'] = $teamId;
        }

        return Database::query(
            self::SELECT_JOINED . ' WHERE ' . $where . ' ORDER BY m.match_date ASC',
            $params
        )->fetchAll();
    }

    /** Latest finished results involving one team. */
    public static function recentForTeam(int $teamId, int $limit = 5): array
    {
        return Database::query(
            self::SELECT_JOINED . " WHERE m.status = 'finished'
             AND (m.home_team_id = :team_a OR m.away_team_id = :team_b)
             ORDER BY m.match_date DESC, m.id DESC LIMIT " . $limit,
            [':team_a' => $teamId, ':team_b' => $teamId]
        )->fetchAll();
    }

    /** Next scheduled fixtures involving one team. */
    public static function upcomingForTeam(int $teamId, int $limit = 5): array
    {
        return Database::query(
            self::SELECT_JOINED . " WHERE m.status = 'scheduled'
             AND (m.home_team_id = :team_a OR m.away_team_id = :team_b)
             ORDER BY m.match_date ASC LIMIT " . $limit,
            [':team_a' => $teamId, ':team_b' => $teamId]
        )->fetchAll();
    }

    public static function finishedForTournament(int $tournamentId): array
    {
        return Database::query(
            self::SELECT_JOINED . " WHERE m.tournament_id = :tid AND m.status = 'finished'
             ORDER BY m.match_date ASC",
            [':tid' => $tournamentId]
        )->fetchAll();
    }

    public static function withTeams(int $id): ?array
    {
        $match = Database::query(
            self::SELECT_JOINED . ' WHERE m.id = :id',
            [':id' => $id]
        )->fetch();

        return $match !== false ? $match : null;
    }

    /**
     * Compute a league table (P W D L GF GA GD Pts) from finished matches.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function standings(int $tournamentId): array
    {
        $matches = self::finishedForTournament($tournamentId);
        $stats = [];

        foreach ($matches as $m) {
            foreach (['home', 'away'] as $side) {
                $teamId = (int) $m[$side . '_team_id'];
                if (!isset($stats[$teamId])) {
                    $stats[$teamId] = [
                        'team_id' => $teamId,
                        'team_name' => $m[$side . '_name'],
                        'team_code' => $m[$side . '_code'],
                        'played' => 0,
                        'won' => 0,
                        'drawn' => 0,
                        'lost' => 0,
                        'gf' => 0,
                        'ga' => 0,
                        'points' => 0,
                    ];
                }
            }

            $homeId = (int) $m['home_team_id'];
            $awayId = (int) $m['away_team_id'];
            $homeScore = (int) $m['home_score'];
            $awayScore = (int) $m['away_score'];

            $stats[$homeId]['played']++;
            $stats[$awayId]['played']++;
            $stats[$homeId]['gf'] += $homeScore;
            $stats[$homeId]['ga'] += $awayScore;
            $stats[$awayId]['gf'] += $awayScore;
            $stats[$awayId]['ga'] += $homeScore;

            if ($homeScore > $awayScore) {
                $stats[$homeId]['won']++;
                $stats[$homeId]['points'] += 3;
                $stats[$awayId]['lost']++;
            } elseif ($homeScore < $awayScore) {
                $stats[$awayId]['won']++;
                $stats[$awayId]['points'] += 3;
                $stats[$homeId]['lost']++;
            } else {
                $stats[$homeId]['drawn']++;
                $stats[$awayId]['drawn']++;
                $stats[$homeId]['points']++;
                $stats[$awayId]['points']++;
            }
        }

        // No finished matches yet: show every team with zeroed stats.
        if ($stats === []) {
            foreach (Team::options() as $team) {
                $stats[(int) $team['id']] = [
                    'team_id' => (int) $team['id'],
                    'team_name' => $team['name'],
                    'team_code' => $team['code'],
                    'played' => 0,
                    'won' => 0,
                    'drawn' => 0,
                    'lost' => 0,
                    'gf' => 0,
                    'ga' => 0,
                    'points' => 0,
                ];
            }
        }

        usort($stats, static fn (array $a, array $b): int => [
            $b['points'],
            $b['gf'] - $b['ga'],
            $b['gf'],
            $b['team_name'],
        ] <=> [
            $a['points'],
            $a['gf'] - $a['ga'],
            $a['gf'],
            $a['team_name'],
        ]);

        foreach ($stats as &$row) {
            $row['gd'] = $row['gf'] - $row['ga'];
        }
        unset($row);

        return $stats;
    }
}
