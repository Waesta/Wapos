<?php
/**
 * Schema Audit Utility
 * ---------------------
 * Generates a JSON snapshot of the current MySQL schema so we can
 * compare against the expected application schema and spot drift.
 *
 * CLI usage:
 *   php scripts/schema_audit.php
 *
 * Browser usage (requires admin role):
 *   http://localhost/wapos/scripts/schema_audit.php
 */

require_once __DIR__ . '/../includes/bootstrap.php';

// When running via browser, ensure only admins can access the report
if (php_sapi_name() !== 'cli') {
    if (!isset($auth)) {
        throw new RuntimeException('Auth subsystem not initialized.');
    }
    $auth->requireRole('admin');
}

$db = Database::getInstance()->getConnection();
$dbNameStmt = $db->query('SELECT DATABASE()');
$dbName = $dbNameStmt ? (string) $dbNameStmt->fetchColumn() : DB_NAME;

$tablesStmt = $db->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'");
$tables = [];
while ($row = $tablesStmt->fetch(PDO::FETCH_NUM)) {
    $tables[] = $row[0];
}

sort($tables);

$report = [
    'database' => $dbName,
    'generated_at' => date('c'),
    'table_count' => count($tables),
    'tables' => [],
];

foreach ($tables as $table) {
    $report['tables'][$table] = [
        'columns' => fetchColumns($db, $table),
        'indexes' => fetchIndexes($db, $table),
        'foreign_keys' => fetchForeignKeys($db, $table, $dbName),
        'row_count' => fetchRowCount($db, $table),
    ];
}

$outputDir = ROOT_PATH . '/storage/schema-audits';
if (!is_dir($outputDir) && !mkdir($outputDir, 0775, true) && !is_dir($outputDir)) {
    throw new RuntimeException('Unable to create schema audit directory: ' . $outputDir);
}

$timestamp = date('Ymd_His');
$jsonReportPath = sprintf('%s/schema-report-%s.json', $outputDir, $timestamp);
$prettyJson = json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
if ($prettyJson === false) {
    throw new RuntimeException('Failed to encode schema report as JSON.');
}
file_put_contents($jsonReportPath, $prettyJson);
file_put_contents(sprintf('%s/schema-report-latest.json', $outputDir), $prettyJson);

$summary = sprintf(
    "Schema audit complete. %d tables documented.\nReport saved to: %s\n",
    $report['table_count'],
    str_replace('\\', '/', $jsonReportPath)
);

if (php_sapi_name() === 'cli') {
    fwrite(STDOUT, $summary);
} else {
    header('Content-Type: text/plain');
    echo $summary;
}

// -----------------------------------------------------------------------------

function fetchColumns(PDO $db, string $table): array
{
    $stmt = $db->prepare('SHOW FULL COLUMNS FROM `' . str_replace('`', '``', $table) . '`');
    $stmt->execute();
    $columns = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $columns[] = [
            'field' => $row['Field'],
            'type' => $row['Type'],
            'collation' => $row['Collation'],
            'null' => $row['Null'],
            'key' => $row['Key'],
            'default' => $row['Default'],
            'extra' => $row['Extra'],
            'comment' => $row['Comment'],
        ];
    }
    return $columns;
}

function fetchIndexes(PDO $db, string $table): array
{
    $stmt = $db->prepare('SHOW INDEX FROM `' . str_replace('`', '``', $table) . '`');
    $stmt->execute();
    $indexes = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $keyName = $row['Key_name'];
        if (!isset($indexes[$keyName])) {
            $indexes[$keyName] = [
                'unique' => $row['Non_unique'] == 0,
                'columns' => [],
                'index_type' => $row['Index_type'],
            ];
        }
        $indexes[$keyName]['columns'][(int)$row['Seq_in_index']] = $row['Column_name'];
    }

    foreach ($indexes as &$index) {
        ksort($index['columns']);
        $index['columns'] = array_values($index['columns']);
    }

    return $indexes;
}

function fetchForeignKeys(PDO $db, string $table, string $schema): array
{
    $sql = "
        SELECT
            CONSTRAINT_NAME,
            COLUMN_NAME,
            REFERENCED_TABLE_NAME,
            REFERENCED_COLUMN_NAME
        FROM information_schema.KEY_COLUMN_USAGE
        WHERE TABLE_SCHEMA = :schema
          AND TABLE_NAME = :table
          AND REFERENCED_TABLE_NAME IS NOT NULL
        ORDER BY CONSTRAINT_NAME, ORDINAL_POSITION
    ";
    $stmt = $db->prepare($sql);
    $stmt->execute([
        ':schema' => $schema,
        ':table' => $table,
    ]);

    $fks = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $constraint = $row['CONSTRAINT_NAME'];
        if (!isset($fks[$constraint])) {
            $fks[$constraint] = [
                'columns' => [],
                'referenced_table' => $row['REFERENCED_TABLE_NAME'],
                'referenced_columns' => [],
            ];
        }
        $fks[$constraint]['columns'][] = $row['COLUMN_NAME'];
        $fks[$constraint]['referenced_columns'][] = $row['REFERENCED_COLUMN_NAME'];
    }

    return $fks;
}

function fetchRowCount(PDO $db, string $table): int
{
    try {
        $stmt = $db->query('SELECT COUNT(*) FROM `' . str_replace('`', '``', $table) . '`');
        return (int) $stmt->fetchColumn();
    } catch (Throwable $e) {
        return -1; // indicates failure (e.g. table corruption)
    }
}
