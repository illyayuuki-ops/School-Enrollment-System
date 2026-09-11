<?php
declare(strict_types=1);

/**
 * Native PHP connection helper for deployments that run the PHP application
 * directly. The connection string is always read from DATABASE_URL; no
 * credentials are stored in source.
 */
function db_connect(): PDO
{
    $databaseUrl = getenv('DATABASE_URL');
    if (!$databaseUrl) {
        throw new RuntimeException('DATABASE_URL is not configured.');
    }

    $parts = parse_url($databaseUrl);
    if ($parts === false || empty($parts['host'])) {
        throw new RuntimeException('DATABASE_URL is invalid.');
    }

    $rawScheme = $parts['scheme'] ?? 'postgres';
    $scheme = in_array($rawScheme, ['postgres', 'postgresql'], true) ? 'pgsql' : $rawScheme;
    $dsn = sprintf(
        '%s:host=%s;port=%d;dbname=%s',
        $scheme,
        $parts['host'],
        $parts['port'] ?? 5432,
        ltrim($parts['path'] ?? '', '/')
    );

    return new PDO($dsn, $parts['user'] ?? null, $parts['pass'] ?? null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
}