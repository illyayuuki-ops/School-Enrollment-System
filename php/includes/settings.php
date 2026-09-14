<?php
declare(strict_types=1);

/**
 * Settings helper functions for reading app_settings from database.
 */

/**
 * Get a setting value from app_settings table.
 * 
 * @param PDO|null $pdo Database connection (nullable for login page resilience)
 * @param string $key Setting key
 * @param string|null $default Default value if not found
 * @return string|null Setting value or default
 */
function get_setting(?PDO $pdo, string $key, ?string $default = null): ?string
{
    if ($pdo === null) {
        return $default;
    }
    try {
        $stmt = $pdo->prepare('SELECT value FROM app_settings WHERE "key" = :key');
        $stmt->execute(['key' => $key]);
        $row = $stmt->fetch();
        return $row ? (string) $row['value'] : $default;
    } catch (PDOException $e) {
        error_log('get_setting error: ' . $e->getMessage());
        return $default;
    }
}

/**
 * Get the academic year label from app_settings.
 * 
 * @param PDO|null $pdo Database connection (nullable for login page resilience)
 * @return string Formatted label (e.g. "A.Y. 2026-2027")
 */
function get_academic_year(?PDO $pdo): string
{
    return academic_year_label($pdo);
}

/**
 * Get formatted academic year label.
 * 
 * @param PDO|null $pdo Database connection (nullable for login page resilience)
 * @return string Formatted label (e.g. "A.Y. 2026-2027")
 */
function academic_year_label(?PDO $pdo): string
{
    $year = get_setting($pdo, 'academic_year', '2026-2027');
    return 'A.Y. ' . $year;
}

function get_school_name(?PDO $pdo): string
{
    $name = get_setting($pdo, 'school_name', 'Steiner Maris Academy');
    return $name;
}

/**
 * Get the default enrollment semester (first by display_order).
 * 
 * @param PDO|null $pdo Database connection (nullable for login page resilience)
 * @return string Default semester name
 */
function get_default_semester(?PDO $pdo): string
{
    if ($pdo === null) {
        return '1st Semester';
    }
    try {
        $stmt = $pdo->prepare("SELECT name FROM enrollment_semesters ORDER BY display_order LIMIT 1");
        $stmt->execute();
        $row = $stmt->fetch();
        return $row ? (string) $row['name'] : '1st Semester';
    } catch (PDOException $e) {
        error_log('get_default_semester error: ' . $e->getMessage());
        return '1st Semester';
    }
}