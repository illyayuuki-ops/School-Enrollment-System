<?php
declare(strict_types=1);

/**
 * Settings helper functions for reading app_settings from database.
 */

/**
 * Get a setting value from app_settings table.
 * 
 * @param PDO $pdo Database connection
 * @param string $key Setting key
 * @param string|null $default Default value if not found
 * @return string|null Setting value or default
 */
function get_setting(PDO $pdo, string $key, ?string $default = null): ?string
{
    try {
        $stmt = $pdo->prepare("SELECT value FROM app_settings WHERE `key` = :key");
        $stmt->execute(['key' => $key]);
        $row = $stmt->fetch();
        return $row ? (string) $row['value'] : $default;
    } catch (PDOException $e) {
        error_log('get_setting error: ' . $e->getMessage());
        return $default;
    }
}

/**
 * Get multiple settings at once.
 * 
 * @param PDO $pdo Database connection
 * @param array $keys Array of setting keys
 * @param array $defaults Default values for each key
 * @return array Associative array of key => value
 */
function get_settings(PDO $pdo, array $keys, array $defaults = []): array
{
    $result = $defaults;
    if (empty($keys)) {
        return $result;
    }
    
    try {
        $placeholders = implode(',', array_fill(0, count($keys), '?'));
        $stmt = $pdo->prepare("SELECT `key`, value FROM app_settings WHERE `key` IN ($placeholders)");
        $stmt->execute($keys);
        while ($row = $stmt->fetch()) {
            $result[$row['key']] = (string) $row['value'];
        }
    } catch (PDOException $e) {
        error_log('get_settings error: ' . $e->getMessage());
    }
    
    return $result;
}

/**
 * Get the current academic year from app_settings.
 * 
 * @param PDO $pdo Database connection
 * @return string Academic year (e.g. '2026-2027')
 */
function get_academic_year(PDO $pdo): string
{
    return get_setting($pdo, 'academic_year', '2026-2027');
}

/**
 * Get the current school name from app_settings.
 * 
 * @param PDO $pdo Database connection
 * @return string School name
 */
function get_school_name(PDO $pdo): string
{
    return get_setting($pdo, 'school_name', 'Steiner Maris Academy');
}

/**
 * Check if registration is open.
 * 
 * @param PDO $pdo Database connection
 * @return bool True if registration is open
 */
function is_registration_open(PDO $pdo): bool
{
    $value = get_setting($pdo, 'registration_open', '1');
    return $value === '1' || $value === 'true' || $value === 'on';
}

/**
 * Check if a level type is active for the current academic year.
 * 
 * @param PDO $pdo Database connection
 * @param string $levelType Level type (BED, College, TESDA)
 * @return bool True if active
 */
function is_level_active(PDO $pdo, string $levelType): bool
{
    $key = 'level_active_' . $levelType;
    $value = get_setting($pdo, $key, '1');
    return $value === '1' || $value === 'true' || $value === 'on';
}

/**
 * Get the default enrollment semester (first by display_order).
 * 
 * @param PDO $pdo Database connection
 * @return string Default semester name
 */
function get_default_semester(PDO $pdo): string
{
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

/**
 * Get formatted academic year label.
 * 
 * @param PDO $pdo Database connection
 * @return string Formatted label (e.g. "A.Y. 2026-2027")
 */
function academic_year_label(PDO $pdo): string
{
    $year = get_academic_year($pdo);
    return 'A.Y. ' . $year;
}