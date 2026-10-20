<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/** Calendar entries (training, meetings, travel, deadlines, media). */
class EventModel extends Model
{
    protected static string $table = 'events';

    public const TYPES = ['training', 'meeting', 'travel', 'deadline', 'media', 'other'];

    private const SELECT_JOINED = 'SELECT e.*,
                t.name AS team_name, t.code AS team_code,
                tt.name AS tournament_name,
                u.name AS creator_name
             FROM events e
             LEFT JOIN teams t ON t.id = e.team_id
             LEFT JOIN tournaments tt ON tt.id = e.tournament_id
             LEFT JOIN users u ON u.id = e.created_by';

    /**
     * Events between $from and $to visible to the given team scope.
     * $teamId = null means unrestricted (admin); otherwise league-wide
     * events (team_id NULL) plus that team's own events are visible.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function between(string $from, string $to, ?int $teamId): array
    {
        $where = 'e.starts_at >= :from AND e.starts_at < :to';
        $params = [':from' => $from, ':to' => $to];

        if ($teamId !== null) {
            $where .= ' AND (e.team_id IS NULL OR e.team_id = :scope_team)';
            $params[':scope_team'] = $teamId;
        }

        return Database::query(
            self::SELECT_JOINED . ' WHERE ' . $where . ' ORDER BY e.starts_at ASC',
            $params
        )->fetchAll();
    }

    /** Next $limit upcoming events visible to the given team scope. */
    public static function upcoming(int $limit, ?int $teamId, ?int $onlyTeamId = null): array
    {
        $from = date('Y-m-d H:i:s');
        $to = date('Y-m-d H:i:s', strtotime('+90 days'));

        $where = 'e.starts_at >= :from AND e.starts_at < :to';
        $params = [':from' => $from, ':to' => $to];

        if ($onlyTeamId !== null) {
            $where .= ' AND e.team_id = :only_team';
            $params[':only_team'] = $onlyTeamId;
        } elseif ($teamId !== null) {
            $where .= ' AND (e.team_id IS NULL OR e.team_id = :scope_team)';
            $params[':scope_team'] = $teamId;
        }

        return Database::query(
            self::SELECT_JOINED . ' WHERE ' . $where . ' ORDER BY e.starts_at ASC LIMIT ' . $limit,
            $params
        )->fetchAll();
    }

    public static function withDetails(int $id): ?array
    {
        $row = Database::query(self::SELECT_JOINED . ' WHERE e.id = :id', [':id' => $id])->fetch();

        return $row !== false ? $row : null;
    }
}
