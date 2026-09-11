<?php
declare(strict_types=1);

/**
 * Database schema bootstrap.
 * Reads database/schema.sql and executes it via PDO in a single exec() call.
 * Safe to call multiple times — uses a static request-scoped guard.
 */
function ensure_schema(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    $schemaPath = __DIR__ . '/../../database/schema.sql';
    if (!is_file($schemaPath)) {
        throw new RuntimeException('Schema file not found: ' . $schemaPath);
    }
    $sql = file_get_contents($schemaPath);
    if ($sql === false) {
        throw new RuntimeException('Failed to read schema file: ' . $schemaPath);
    }

    // Execute the entire schema as a single string to preserve DO $$ ... $$; blocks
    // (which contain internal semicolons that would break a simple splitter).
    try {
        $pdo->exec($sql);
    } catch (PDOException $e) {
        // Only ignore "already exists" style errors; rethrow anything else
        $state = $e->getCode() ?? '';
        $msg = $e->getMessage() ?? '';
        if (stripos($msg, 'already exists') !== false ||
            stripos($msg, 'duplicate') !== false ||
            $state === '42710' || // duplicate_object
            $state === '23505') { // unique_violation
            return;
        }
        throw $e;
    }
}