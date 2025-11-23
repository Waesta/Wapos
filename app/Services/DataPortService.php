<?php

namespace App\Services;

use Exception;
use PDO;
use RuntimeException;

class DataPortService
{
    private PDO $db;
    /** @var array<string, array> */
    private array $entities;

    public function __construct(PDO $db)
    {
        $this->db = $db;
        $this->entities = $this->defineEntities();
    }

    /**
     * Get all supported entities.
     */
    public function getEntities(): array
    {
        return $this->entities;
    }

    /**
     * Get metadata for a single entity.
     */
    public function getEntity(string $key): array
    {
        $key = strtolower($key);
        if (!isset($this->entities[$key])) {
            throw new RuntimeException('Unsupported entity.');
        }
        return $this->entities[$key];
    }

    /**
     * Stream template headers for the given entity to an open handle.
     */
    public function streamTemplate(string $key, $handle): void
    {
        $entity = $this->getEntity($key);
        $this->writeCsvRow($handle, array_keys($entity['columns']));
    }

    /**
     * Stream export for the given entity to an open handle.
     */
    public function streamExport(string $key, $handle): void
    {
        $entity = $this->getEntity($key);
        $columns = array_keys($entity['columns']);
        $select = $entity['select'] ?? $columns;

        $sql = 'SELECT ' . implode(', ', $select) . ' FROM ' . $entity['table'];
        if (!empty($entity['export_order'])) {
            $sql .= ' ORDER BY ' . $entity['export_order'];
        }

        $stmt = $this->db->query($sql);
        $this->writeCsvRow($handle, $columns);

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $line = [];
            foreach ($columns as $column) {
                $line[] = $row[$column] ?? '';
            }
            $this->writeCsvRow($handle, $line);
        }
    }

    /**
     * Import CSV data for an entity.
     */
    public function importFromCsv(string $key, string $filePath, bool $validateOnly = false): array
    {
        $entity = $this->getEntity($key);
        $handle = fopen($filePath, 'rb');
        if (!$handle) {
            throw new RuntimeException('Unable to open uploaded file.');
        }

        $headerRow = $this->getNextCsvRow($handle);
        if (empty($headerRow)) {
            fclose($handle);
            throw new RuntimeException('The uploaded file is empty.');
        }

        $columnMap = $this->buildColumnMap($entity, $headerRow);
        $requiredColumns = array_filter(array_keys($entity['columns']), static fn ($key) => !empty($entity['columns'][$key]['required']));
        $missing = array_diff($requiredColumns, array_keys($columnMap));
        if (!empty($missing)) {
            fclose($handle);
            throw new RuntimeException('Missing required columns: ' . implode(', ', $missing));
        }

        $rows = [];
        $errors = [];
        $rowNumber = 1; // header row
        while (($row = $this->getNextCsvRow($handle)) !== null) {
            $rowNumber++;
            if ($this->isRowEmpty($row)) {
                continue;
            }

            $parsed = $this->parseRow($entity, $row, $columnMap, $rowNumber);
            if (!empty($parsed['errors'])) {
                $errors = array_merge($errors, $parsed['errors']);
                continue;
            }

            $rows[] = $parsed;
        }
        fclose($handle);

        if (!empty($errors)) {
            return [
                'success' => false,
                'errors' => $errors,
                'inserted' => 0,
                'updated' => 0,
                'total_rows' => count($rows),
            ];
        }

        if ($validateOnly) {
            return [
                'success' => true,
                'message' => 'Validation successful. No data was imported.',
                'inserted' => 0,
                'updated' => 0,
                'total_rows' => count($rows),
            ];
        }

        $inserted = 0;
        $updated = 0;

        try {
            $this->db->beginTransaction();
            foreach ($rows as $parsedRow) {
                $existingId = $parsedRow['existing_id'];
                $data = $parsedRow['data'];

                if ($existingId) {
                    $this->updateRow($entity['table'], $data, $existingId);
                    $updated++;
                } else {
                    $this->insertRow($entity['table'], $data);
                    $inserted++;
                }
            }
            $this->db->commit();
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }

        return [
            'success' => true,
            'message' => sprintf('Import completed. %d inserted, %d updated.', $inserted, $updated),
            'inserted' => $inserted,
            'updated' => $updated,
            'total_rows' => count($rows),
        ];
    }

    private function defineEntities(): array
    {
        return [
            'products' => [
                'label' => 'Products',
                'table' => 'products',
                'match_on' => ['sku'],
                'export_order' => 'name ASC',
                'select' => ['sku', 'name', 'description', 'category_id', 'cost_price', 'selling_price', 'stock_quantity', 'min_stock_level', 'tax_rate', 'is_active'],
                'columns' => [
                    'sku' => ['label' => 'SKU', 'type' => 'string', 'required' => true, 'max' => 100],
                    'name' => ['label' => 'Name', 'type' => 'string', 'required' => true, 'max' => 255],
                    'description' => ['label' => 'Description', 'type' => 'string', 'required' => false],
                    'category_id' => ['label' => 'Category ID', 'type' => 'int', 'required' => false],
                    'cost_price' => ['label' => 'Cost Price', 'type' => 'float', 'required' => false, 'default' => 0],
                    'selling_price' => ['label' => 'Selling Price', 'type' => 'float', 'required' => true],
                    'stock_quantity' => ['label' => 'Stock Quantity', 'type' => 'int', 'required' => false, 'default' => 0],
                    'min_stock_level' => ['label' => 'Min Stock Level', 'type' => 'int', 'required' => false, 'default' => 0],
                    'tax_rate' => ['label' => 'Tax Rate', 'type' => 'float', 'required' => false, 'default' => 0],
                    'is_active' => ['label' => 'Is Active (1/0)', 'type' => 'bool', 'required' => false, 'default' => 1],
                ],
            ],
            'customers' => [
                'label' => 'Customers',
                'table' => 'customers',
                'match_on' => ['email', 'phone'],
                'export_order' => 'full_name ASC',
                'select' => ['full_name', 'email', 'phone', 'address', 'city', 'country'],
                'columns' => [
                    'full_name' => ['label' => 'Full Name', 'type' => 'string', 'required' => true, 'max' => 255],
                    'email' => ['label' => 'Email', 'type' => 'string', 'required' => false, 'max' => 255],
                    'phone' => ['label' => 'Phone', 'type' => 'string', 'required' => false, 'max' => 50],
                    'address' => ['label' => 'Address', 'type' => 'string', 'required' => false],
                    'city' => ['label' => 'City', 'type' => 'string', 'required' => false, 'max' => 150],
                    'country' => ['label' => 'Country', 'type' => 'string', 'required' => false, 'max' => 150],
                ],
            ],
            'suppliers' => [
                'label' => 'Suppliers',
                'table' => 'suppliers',
                'match_on' => ['name'],
                'export_order' => 'name ASC',
                'select' => ['name', 'contact_person', 'email', 'phone', 'address', 'tax_id', 'payment_terms', 'is_active'],
                'columns' => [
                    'name' => ['label' => 'Name', 'type' => 'string', 'required' => true, 'max' => 255],
                    'contact_person' => ['label' => 'Contact Person', 'type' => 'string', 'required' => false, 'max' => 255],
                    'email' => ['label' => 'Email', 'type' => 'string', 'required' => false, 'max' => 255],
                    'phone' => ['label' => 'Phone', 'type' => 'string', 'required' => false, 'max' => 50],
                    'address' => ['label' => 'Address', 'type' => 'string', 'required' => false],
                    'tax_id' => ['label' => 'Tax ID', 'type' => 'string', 'required' => false, 'max' => 100],
                    'payment_terms' => ['label' => 'Payment Terms', 'type' => 'string', 'required' => false, 'max' => 100],
                    'is_active' => ['label' => 'Is Active (1/0)', 'type' => 'bool', 'required' => false, 'default' => 1],
                ],
            ],
            'users' => [
                'label' => 'Users',
                'table' => 'users',
                'match_on' => ['username'],
                'export_order' => 'username ASC',
                'select' => ['username', 'full_name', 'email', 'phone', 'role', 'is_active'],
                'columns' => [
                    'username' => ['label' => 'Username', 'type' => 'string', 'required' => true, 'max' => 100],
                    'full_name' => ['label' => 'Full Name', 'type' => 'string', 'required' => true, 'max' => 255],
                    'email' => ['label' => 'Email', 'type' => 'string', 'required' => false, 'max' => 255],
                    'phone' => ['label' => 'Phone', 'type' => 'string', 'required' => false, 'max' => 50],
                    'role' => ['label' => 'Role', 'type' => 'enum', 'required' => true, 'allowed' => ['super_admin','admin','developer','manager','cashier','waiter','rider','inventory_manager','accountant','staff']],
                    'password' => ['label' => 'Password', 'type' => 'string', 'required_on_create' => true],
                    'is_active' => ['label' => 'Is Active (1/0)', 'type' => 'bool', 'required' => false, 'default' => 1],
                ],
            ],
        ];
    }

    private function buildColumnMap(array $entity, array $headerRow): array
    {
        $map = [];
        foreach ($headerRow as $index => $header) {
            $normalized = strtolower(trim($header));
            foreach ($entity['columns'] as $key => $column) {
                $candidates = array_filter([
                    strtolower($key),
                    strtolower($column['label'] ?? ''),
                ]);
                if (in_array($normalized, $candidates, true)) {
                    $map[$key] = $index;
                    break;
                }
            }
        }
        return $map;
    }

    private function parseRow(array $entity, array $row, array $columnMap, int $rowNumber): array
    {
        $data = [];
        $errors = [];
        $columns = $entity['columns'];

        $matchContext = $this->resolveMatchContext($entity, $row, $columnMap);
        if ($matchContext === null) {
            $errors[] = "Row {$rowNumber}: Missing match key (" . implode(' or ', (array)$entity['match_on']) . ').';
        }

        $existingId = null;
        if ($matchContext !== null) {
            $existingId = $this->findExistingId($entity['table'], $matchContext['column'], $matchContext['value']);
        }

        foreach ($columns as $key => $definition) {
            $value = '';
            if (isset($columnMap[$key])) {
                $value = $row[$columnMap[$key]] ?? '';
            }
            $value = is_string($value) ? trim($value) : $value;

            $isRequired = !empty($definition['required']);
            $requiredOnCreate = !empty($definition['required_on_create']);

            if ($value === '') {
                if ($isRequired || ($requiredOnCreate && !$existingId)) {
                    $errors[] = "Row {$rowNumber}: {$definition['label']} is required.";
                    continue;
                }
                if (array_key_exists('default', $definition)) {
                    $value = $definition['default'];
                } else {
                    continue;
                }
            }

            try {
                $data[$key] = $this->normalizeValue($definition, $value, $rowNumber);
            } catch (Exception $e) {
                $errors[] = $e->getMessage();
            }
        }

        if (!empty($data['password'])) {
            $data['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
        } elseif (array_key_exists('password', $data) && $existingId) {
            unset($data['password']);
        }

        return [
            'row' => $rowNumber,
            'data' => $data,
            'errors' => $errors,
            'existing_id' => $existingId,
        ];
    }

    private function normalizeValue(array $definition, $value, int $rowNumber)
    {
        $type = $definition['type'] ?? 'string';
        switch ($type) {
            case 'int':
                return (int)$value;
            case 'float':
                return (float)$value;
            case 'bool':
                return $this->toBool($value) ? 1 : 0;
            case 'enum':
                $allowed = $definition['allowed'] ?? [];
                if (!in_array(strtolower((string)$value), array_map('strtolower', $allowed), true)) {
                    throw new RuntimeException("Row {$rowNumber}: Invalid value for {$definition['label']}.");
                }
                return strtolower((string)$value);
            case 'string':
            default:
                $stringValue = (string)$value;
                $max = $definition['max'] ?? null;
                if ($max && mb_strlen($stringValue) > $max) {
                    $stringValue = mb_substr($stringValue, 0, $max);
                }
                return $stringValue;
        }
    }

    private function toBool($value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        $value = strtolower((string)$value);
        return in_array($value, ['1', 'true', 'yes', 'y'], true);
    }

    private function resolveMatchContext(array $entity, array $row, array $columnMap): ?array
    {
        $matchColumns = (array)($entity['match_on'] ?? []);
        foreach ($matchColumns as $column) {
            if (!isset($columnMap[$column])) {
                continue;
            }
            $value = trim((string)($row[$columnMap[$column]] ?? ''));
            if ($value !== '') {
                return ['column' => $column, 'value' => $value];
            }
        }
        return null;
    }

    private function findExistingId(string $table, string $column, string $value): ?int
    {
        $sql = sprintf('SELECT id FROM %s WHERE %s = :value LIMIT 1', $table, $column);
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':value' => $value]);
        $id = $stmt->fetchColumn();
        return $id ? (int)$id : null;
    }

    private function insertRow(string $table, array $data): void
    {
        if (empty($data)) {
            return;
        }
        $columns = array_keys($data);
        $placeholders = array_map(static fn ($column) => ':' . $column, $columns);
        $sql = sprintf('INSERT INTO %s (%s) VALUES (%s)', $table, implode(', ', $columns), implode(', ', $placeholders));
        $stmt = $this->db->prepare($sql);
        $stmt->execute($data);
    }

    private function updateRow(string $table, array $data, int $id): void
    {
        if (empty($data)) {
            return;
        }
        $columns = [];
        foreach ($data as $column => $value) {
            $columns[] = $column . ' = :' . $column;
        }
        $sql = sprintf('UPDATE %s SET %s WHERE id = :id', $table, implode(', ', $columns));
        $stmt = $this->db->prepare($sql);
        $data['id'] = $id;
        $stmt->execute($data);
    }

    private function writeCsvRow($handle, array $row): void
    {
        fputcsv($handle, $row);
    }

    private function getNextCsvRow($handle): ?array
    {
        $row = fgetcsv($handle);
        if ($row === false) {
            return null;
        }
        return $row;
    }

    private function isRowEmpty(array $row): bool
    {
        foreach ($row as $value) {
            if (trim((string)$value) !== '') {
                return false;
            }
        }
        return true;
    }
}
