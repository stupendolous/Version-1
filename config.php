<?php
// Load settings from a .env file next to this one (KEY=VALUE per line), if present.
// Real environment variables win over .env.
$envFile = __DIR__ . '/.env';
if (is_readable($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
            continue;
        }
        [$k, $v] = array_map('trim', explode('=', $line, 2));
        $v = trim($v, "\"'");
        if (getenv($k) === false) {
            putenv("$k=$v");
            $_ENV[$k] = $v;
        }
    }
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$connect = mysqli_connect(
    getenv('DB_HOST') ?: '127.0.0.1',
    getenv('DB_USER') ?: 'root',
    getenv('DB_PASS') !== false ? getenv('DB_PASS') : '123456',
    getenv('DB_NAME') ?: 'barang',
    (int)(getenv('DB_PORT') ?: 3306)
);
mysqli_set_charset($connect, 'utf8mb4');
date_default_timezone_set(getenv('TIMEZONE') ?: 'Asia/Kuala_Lumpur');
mysqli_query($connect, "SET time_zone = '" . date('P') . "'");
