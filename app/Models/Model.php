<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

abstract class Model
{
    protected static string $table = '';

    public static function find(int $id): ?array
    {
        $stmt = Database::query('SELECT * FROM ' . static::$table . ' WHERE id = :id', [':id' => $id]);
        $row = $stmt->fetch();

        return $row !== false ? $row : null;
    }

    public static function all(string $orderBy = 'id ASC'): array
    {
        return Database::query('SELECT * FROM ' . static::$table . ' ORDER BY ' . $orderBy)->fetchAll();
    }

    public static function countAll(): int
    {
        return (int) Database::query('SELECT COUNT(*) FROM ' . static::$table)->fetchColumn();
    }

    public static function create(array $data): int
    {
        $columns = array_keys($data);
        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            static::$table,
            implode(', ', $columns),
            implode(', ', array_map(static fn (string $c): string => ':' . $c, $columns))
        );

        $params = [];
        foreach ($data as $key => $value) {
            $params[':' . $key] = $value;
        }

        Database::query($sql, $params);

        return (int) Database::pdo()->lastInsertId();
    }

    public static function update(int $id, array $data): bool
    {
        if ($data === []) {
            return false;
        }

        $sets = implode(', ', array_map(static fn (string $c): string => $c . ' = :' . $c, array_keys($data)));
        $params = [':id' => $id];
        foreach ($data as $key => $value) {
            $params[':' . $key] = $value;
        }

        return Database::query(
            'UPDATE ' . static::$table . ' SET ' . $sets . ' WHERE id = :id',
            $params
        )->rowCount() >= 0;
    }

    public static function delete(int $id): bool
    {
        Database::query('DELETE FROM ' . static::$table . ' WHERE id = :id', [':id' => $id]);

        return true;
    }

    /** Fetch rows into a [id => row] map (useful for joins). */
    protected static function fetchMap(\PDOStatement $stmt): array
    {
        $map = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $map[(int) $row['id']] = $row;
        }

        return $map;
    }
}
