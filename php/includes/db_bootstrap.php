<?php
declare(strict_types=1);

/**
 * Database schema bootstrap.
 * Reads database/schema.sql, splits into individual statements (respecting dollar-quoted blocks),
 * and executes each statement individually with idempotent error handling.
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

    // Split SQL into individual statements, respecting dollar-quoted blocks
    $statements = split_sql_statements($sql);

    foreach ($statements as $stmt) {
        $stmt = trim($stmt);
        if ($stmt === '' || str_starts_with($stmt, '--')) {
            continue;
        }
        try {
            $pdo->exec($stmt);
        } catch (PDOException $e) {
            // Only ignore genuinely idempotent errors; continue to next statement
            $state = $e->getCode() ?? '';
            $msg = $e->getMessage() ?? '';
            $isIdempotentError = stripos($msg, 'already exists') !== false
                || stripos($msg, 'duplicate') !== false
                || $state === '42710'  // duplicate_object
                || $state === '23505'  // unique_violation
                || $state === '42P10'  // invalid ON CONFLICT specification (no unique constraint)
                || $state === '42P07'; // duplicate_table
            
            if ($isIdempotentError) {
                continue;
            }
            throw $e;
        }
    }
}

/**
 * Split SQL into individual statements, respecting dollar-quoted blocks ($$...$$ or $tag$...$tag$).
 * 
 * @param string $sql The full SQL content
 * @return array Individual SQL statements
 */
function split_sql_statements(string $sql): array
{
    $statements = [];
    $current = '';
    $inDollarQuote = false;
    $dollarTag = '';
    $len = strlen($sql);
    $i = 0;

    while ($i < $len) {
        $char = $sql[$i];
        $nextChar = ($i + 1 < $len) ? $sql[$i + 1] : '';
        
        // Check for dollar quote start/end ($$ or $tag$)
        if (!$inDollarQuote && $char === '$' && $nextChar === '$') {
            $inDollarQuote = true;
            $dollarTag = '$$';
            $current .= '$$';
            $i += 2;
            continue;
        }
        
        if (!$inDollarQuote && $char === '$' && $nextChar !== '$' && $nextChar !== '') {
            // Potential $tag$ start - look ahead for closing $
            $tagEnd = strpos($sql, '$', $i + 1);
            if ($tagEnd !== false) {
                $tag = substr($sql, $i, $tagEnd - $i + 1);
                // Verify it's a valid tag start (letters, digits, underscore only)
                if (preg_match('/^\$[a-zA-Z_][a-zA-Z0-9_]*\$$/', $tag)) {
                    $inDollarQuote = true;
                    $dollarTag = $tag;
                    $current .= $tag;
                    $i += strlen($tag);
                    continue;
                }
            }
        }
        
        if ($inDollarQuote) {
            $current .= $char;
            // Check for dollar quote end
            if ($char === '$' && $nextChar === '$' && $dollarTag === '$$') {
                $inDollarQuote = false;
                $dollarTag = '';
            } elseif ($dollarTag !== '$$' && $char === '$') {
                // Check if we have the closing tag
                $remaining = substr($sql, $i);
                if (str_starts_with($remaining, $dollarTag)) {
                    $current .= substr($dollarTag, 1); // Add the rest of the tag (first $ already added)
                    $i += strlen($dollarTag) - 1;
                    $inDollarQuote = false;
                    $dollarTag = '';
                }
            }
        } else {
            // Not in dollar quote - check for statement terminator
            if ($char === ';') {
                // Check if it's not part of a comment
                $statements[] = $current . ';';
                $current = '';
            } else {
                $current .= $char;
            }
        }
        $i++;
    }

    // Add any remaining content
    $current = trim($current);
    if ($current !== '' && !str_starts_with($current, '--')) {
        $statements[] = $current;
    }

    return $statements;
}