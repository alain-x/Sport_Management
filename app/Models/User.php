<?php

declare(strict_types=1);

namespace App\Models;

class User extends Model
{
    protected static string $table = 'users';

    public static function findByEmail(string $email): ?array
    {
        $stmt = \App\Core\Database::query('SELECT * FROM users WHERE email = :email LIMIT 1', [':email' => $email]);
        $row = $stmt->fetch();

        return $row !== false ? $row : null;
    }

    /** Staff accounts belonging to one team (role = manager), newest first. */
    public static function forTeam(int $teamId): array
    {
        return \App\Core\Database::query(
            'SELECT * FROM users WHERE team_id = :tid AND role = \'manager\'
             ORDER BY created_at DESC, id DESC',
            [':tid' => $teamId]
        )->fetchAll();
    }

    public static function countForTeam(int $teamId): int
    {
        return (int) \App\Core\Database::query(
            'SELECT COUNT(*) FROM users WHERE team_id = :tid AND role = \'manager\'',
            [':tid' => $teamId]
        )->fetchColumn();
    }

    /** Admin user list with team names, search + role filter, paginated. */
    public static function paginateAll(int $limit, int $offset, string $search = '', string $role = ''): array
    {
        [$where, $params] = self::filters($search, $role);

        return \App\Core\Database::query(
            'SELECT u.*, t.name AS team_name, t.code AS team_code
             FROM users u
             LEFT JOIN teams t ON t.id = u.team_id'
            . $where
            . ' ORDER BY u.role ASC, u.name ASC
               LIMIT ' . $limit . ' OFFSET ' . $offset,
            $params
        )->fetchAll();
    }

    public static function countFiltered(string $search = '', string $role = ''): int
    {
        [$where, $params] = self::filters($search, $role);

        return (int) \App\Core\Database::query(
            'SELECT COUNT(*) FROM users u' . $where,
            $params
        )->fetchColumn();
    }

    /** @return array{0:string,1:array} */
    private static function filters(string $search, string $role): array
    {
        $parts = [];
        $params = [];

        if ($search !== '') {
            $parts[] = '(u.name LIKE :q1 OR u.email LIKE :q2 OR t.name LIKE :q3)';
            $params[':q1'] = '%' . $search . '%';
            $params[':q2'] = '%' . $search . '%';
            $params[':q3'] = '%' . $search . '%';
        }
        if ($role !== '' && in_array($role, ['admin', 'manager'], true)) {
            $parts[] = 'u.role = :role';
            $params[':role'] = $role;
        }

        return [$parts === [] ? '' : ' WHERE ' . implode(' AND ', $parts), $params];
    }
}
