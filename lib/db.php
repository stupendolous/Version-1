<?php
require_once __DIR__ . '/../config.php';

function db(): mysqli
{
    global $connect;
    return $connect;
}

/** Run a prepared statement. Types are inferred: int -> i, float -> d, everything else -> s. */
function q(string $sql, array $params = []): mysqli_stmt
{
    $stmt = db()->prepare($sql);
    if ($params) {
        $types = '';
        foreach ($params as $p) {
            $types .= is_int($p) ? 'i' : (is_float($p) ? 'd' : 's');
        }
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    return $stmt;
}

function rows(string $sql, array $params = []): array
{
    $res = q($sql, $params)->get_result();
    return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
}

function row(string $sql, array $params = []): ?array
{
    return rows($sql, $params)[0] ?? null;
}

function val(string $sql, array $params = []): mixed
{
    $r = row($sql, $params);
    return $r ? reset($r) : null;
}

function insert_id(): int
{
    return (int)db()->insert_id;
}

function setting(string $key, ?string $default = null): ?string
{
    $v = val('SELECT v FROM settings WHERE k = ?', [$key]);
    return $v ?? $default;
}

function set_setting(string $key, string $value): void
{
    q('INSERT INTO settings (k, v) VALUES (?, ?) ON DUPLICATE KEY UPDATE v = VALUES(v)', [$key, $value]);
}

function rm(float|string|null $n): string
{
    return 'RM ' . number_format((float)$n, 2);
}
