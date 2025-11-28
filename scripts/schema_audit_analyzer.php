<?php

declare(strict_types=1);

/**
 * Summarize the latest schema audit JSON report produced by schema_audit.php.
 *
 * Usage: php scripts/schema_audit_analyzer.php [path/to/report.json]
 */

$reportPath = $argv[1] ?? findLatestReport(__DIR__ . '/../storage/schema-audits');

if ($reportPath === null || !is_file($reportPath)) {
    fwrite(STDERR, "Schema audit report not found. Provide a path or run scripts/schema_audit.php first." . PHP_EOL);
    exit(1);
}

$json = file_get_contents($reportPath);
$data = json_decode($json, true);

if (!is_array($data) || empty($data['tables']) || !is_array($data['tables'])) {
    fwrite(STDERR, "Invalid schema audit report: {$reportPath}" . PHP_EOL);
    exit(1);
}

$tables = $data['tables'];

$zeroRows = [];
$missingPrimaryKey = [];
$noForeignKeys = [];
$noSecondaryIndexes = [];
$tablesByModule = [];

foreach ($tables as $name => $details) {
    $rowCount = $details['row_count'] ?? null;
    if ($rowCount === 0) {
        $zeroRows[] = $name;
    }

    $indexes = $details['indexes'] ?? [];
    $hasPrimary = isset($indexes['PRIMARY']);
    if (!$hasPrimary) {
        $missingPrimaryKey[] = $name;
    }

    $foreignKeys = $details['foreign_keys'] ?? [];
    if (empty($foreignKeys)) {
        $noForeignKeys[] = $name;
    }

    $secondaryIndexes = array_filter(array_keys($indexes), static fn(string $index) => $index !== 'PRIMARY');
    if (empty($secondaryIndexes)) {
        $noSecondaryIndexes[] = $name;
    }

    $module = strtok($name, '_') ?: $name;
    $tablesByModule[$module][] = $name;
}

$summary = [
    'report_path' => realpath($reportPath),
    'generated_at' => $data['generated_at'] ?? null,
    'table_count' => $data['table_count'] ?? count($tables),
    'tables_with_zero_rows' => $zeroRows,
    'tables_missing_primary_key' => $missingPrimaryKey,
    'tables_without_foreign_keys' => $noForeignKeys,
    'tables_without_secondary_indexes' => $noSecondaryIndexes,
    'tables_grouped_by_module_prefix' => $tablesByModule,
];

$outputPath = __DIR__ . '/../storage/schema-audits/schema-analysis-' . date('Ymd_His') . '.json';
file_put_contents($outputPath, json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

echo 'Analysis saved to: ' . realpath($outputPath) . PHP_EOL;

/**
 * Locate the latest schema-report JSON file.
 */
function findLatestReport(string $directory): ?string
{
    if (!is_dir($directory)) {
        return null;
    }

    $files = glob(rtrim($directory, '/\\') . '/schema-report-*.json');
    if (!$files) {
        return null;
    }

    rsort($files, SORT_STRING);
    return $files[0] ?? null;
}
