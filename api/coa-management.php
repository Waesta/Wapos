<?php

require_once '../includes/bootstrap.php';

use App\Services\AuditLogService;

header('Content-Type: application/json');

const COA_ACCOUNT_TYPES = [
    'ASSET',
    'LIABILITY',
    'EQUITY',
    'REVENUE',
    'EXPENSE',
    'CONTRA_REVENUE',
    'COST_OF_SALES',
    'OTHER_INCOME',
    'OTHER_EXPENSE',
];

const COA_CLASSIFICATIONS = [
    'CURRENT_ASSET',
    'NON_CURRENT_ASSET',
    'CONTRA_ASSET',
    'CURRENT_LIABILITY',
    'NON_CURRENT_LIABILITY',
    'CONTRA_LIABILITY',
    'EQUITY',
    'CONTRA_EQUITY',
    'REVENUE',
    'CONTRA_REVENUE',
    'OTHER_INCOME',
    'COST_OF_SALES',
    'OPERATING_EXPENSE',
    'NON_OPERATING_EXPENSE',
    'OTHER_EXPENSE',
];

const COA_STATEMENT_SECTIONS = [
    'BALANCE_SHEET',
    'PROFIT_AND_LOSS',
    'CASH_FLOW',
    'EQUITY',
];

try {
    $auth->requireLogin();
    if (!$auth->hasRole(['admin', 'accountant', 'developer'])) {
        throw new Exception('You do not have permission to manage the chart of accounts.');
    }

    $db = Database::getInstance();
    $pdo = $db->getConnection();
    $auditService = new AuditLogService($pdo);
    $userId = (int)($auth->getUserId() ?? 0);

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $accounts = listAccounts($db);
        $metadata = buildMetadata($db);
        echo json_encode([
            'success' => true,
            'accounts' => $accounts,
            'metadata' => $metadata,
        ]);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
        exit;
    }

    $payload = json_decode(file_get_contents('php://input'), true);
    if (!is_array($payload)) {
        throw new Exception('Invalid JSON payload supplied.');
    }

    enforceCsrfToken($payload['csrf_token'] ?? '');

    $action = trim((string)($payload['action'] ?? ''));
    if ($action === '') {
        throw new Exception('Missing action.');
    }

    switch ($action) {
        case 'save_account':
            $account = saveAccount($db, $auditService, $userId, $payload);
            $response = ['success' => true, 'account' => $account];
            break;

        case 'guided_revenue_stream':
            $guidedResult = createGuidedRevenueStream($db, $auditService, $userId, $payload);
            $response = [
                'success' => true,
                'account' => $guidedResult['account'],
                'tax_preference' => $guidedResult['tax_preference'],
            ];
            break;

        case 'toggle_active':
            $account = setAccountActiveState($db, $auditService, $userId, $payload);
            $response = ['success' => true, 'account' => $account];
            break;

        default:
            throw new Exception('Unsupported action: ' . $action);
    }

    echo json_encode($response);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
    ]);
}

function enforceCsrfToken(string $token): void
{
    if (!validateCSRFToken($token)) {
        throw new Exception('Invalid CSRF token. Please refresh and try again.');
    }
}

function listAccounts(Database $db): array
{
    $rows = $db->fetchAll(
        'SELECT a.id,
                a.code,
                a.name,
                a.type,
                a.classification,
                a.statement_section,
                a.reporting_order,
                a.parent_code,
                parent.name AS parent_name,
                a.ifrs_reference,
                a.is_active,
                a.created_at,
                a.updated_at
         FROM accounts a
         LEFT JOIN accounts parent ON parent.code = a.parent_code
         ORDER BY a.reporting_order ASC, a.code ASC'
    );

    return array_map(static function (array $row): array {
        $row['reporting_order'] = isset($row['reporting_order']) ? (int)$row['reporting_order'] : 0;
        $row['is_active'] = (int)($row['is_active'] ?? 0) === 1;
        return $row;
    }, $rows ?? []);
}

function buildMetadata(Database $db): array
{
    $templates = function_exists('getChartOfAccountsTemplates') ? getChartOfAccountsTemplates() : [];

    return [
        'types' => formatOptions(COA_ACCOUNT_TYPES),
        'classifications' => formatOptions(COA_CLASSIFICATIONS),
        'statement_sections' => formatOptions(COA_STATEMENT_SECTIONS),
        'templates' => $templates,
        'parents' => $db->fetchAll('SELECT code, name FROM accounts WHERE is_active = 1 ORDER BY code ASC'),
    ];
}

function formatOptions(array $values): array
{
    return array_map(static function (string $value): array {
        return [
            'value' => $value,
            'label' => ucwords(strtolower(str_replace(['_', '-'], ' ', $value))),
        ];
    }, $values);
}

function saveAccount(Database $db, AuditLogService $auditService, int $userId, array $payload): array
{
    $id = isset($payload['id']) ? (int)$payload['id'] : null;
    $code = strtoupper(trim((string)($payload['code'] ?? '')));
    $name = trim((string)($payload['name'] ?? ''));
    $type = strtoupper(trim((string)($payload['type'] ?? '')));
    $classification = strtoupper(trim((string)($payload['classification'] ?? '')));
    $statementSection = strtoupper(trim((string)($payload['statement_section'] ?? '')));
    $reportingOrder = isset($payload['reporting_order']) && $payload['reporting_order'] !== ''
        ? max(0, (int)$payload['reporting_order'])
        : 0;
    $parentCodeRaw = strtoupper(trim((string)($payload['parent_code'] ?? '')));
    $parentCode = $parentCodeRaw !== '' ? $parentCodeRaw : null;
    $ifrsReference = trim((string)($payload['ifrs_reference'] ?? ''));
    $isActive = !empty($payload['is_active']) ? 1 : 0;

    if ($code === '') {
        throw new Exception('Account code is required.');
    }

    if (!preg_match('/^[A-Z0-9\-]{3,20}$/', $code)) {
        throw new Exception('Account code must be 3-20 characters (letters, numbers, dash).');
    }

    if ($name === '') {
        throw new Exception('Account name is required.');
    }

    if (!in_array($type, COA_ACCOUNT_TYPES, true)) {
        throw new Exception('Invalid account type supplied.');
    }

    if (!in_array($classification, COA_CLASSIFICATIONS, true)) {
        throw new Exception('Invalid classification supplied.');
    }

    if (!in_array($statementSection, COA_STATEMENT_SECTIONS, true)) {
        throw new Exception('Invalid statement section supplied.');
    }

    if ($parentCode !== null) {
        if ($parentCode === $code) {
            throw new Exception('An account cannot be its own parent.');
        }

        $parentExists = $db->fetchOne('SELECT id FROM accounts WHERE code = ?', [$parentCode]);
        if (!$parentExists) {
            throw new Exception('Parent account code not found.');
        }
    }

    $existingAccount = null;
    if ($id) {
        $existingAccount = fetchAccountById($db, $id);
        if (!$existingAccount) {
            throw new Exception('Account not found.');
        }
    }

    $duplicateCodeAccount = $db->fetchOne('SELECT id FROM accounts WHERE code = ?', [$code]);
    if ($duplicateCodeAccount && (!$id || (int)$duplicateCodeAccount['id'] !== $id)) {
        throw new Exception('An account with that code already exists.');
    }

    $now = date('Y-m-d H:i:s');
    $data = [
        'code' => $code,
        'name' => $name,
        'type' => $type,
        'classification' => $classification,
        'statement_section' => $statementSection,
        'reporting_order' => $reportingOrder,
        'parent_code' => $parentCode,
        'ifrs_reference' => $ifrsReference !== '' ? $ifrsReference : null,
        'is_active' => $isActive,
        'updated_at' => $now,
    ];

    if ($existingAccount === null) {
        $data['created_at'] = $now;
        $newId = (int)$db->insert('accounts', $data);
        $account = fetchAccountById($db, $newId);
        $auditService->log('coa_create', $userId, 'accounts', $newId, null, $account);
        return $account;
    }

    $db->update('accounts', $data, 'id = :id', ['id' => $id]);
    $account = fetchAccountById($db, $id);
    $auditService->log('coa_update', $userId, 'accounts', $id, $existingAccount, $account);

    return $account;
}

function setAccountActiveState(Database $db, AuditLogService $auditService, int $userId, array $payload): array
{
    $id = isset($payload['id']) ? (int)$payload['id'] : 0;
    if ($id <= 0) {
        throw new Exception('Invalid account identifier.');
    }

    $account = fetchAccountById($db, $id);
    if (!$account) {
        throw new Exception('Account not found.');
    }

    $active = !empty($payload['is_active']) ? 1 : 0;
    if ((int)$account['is_active'] === $active) {
        return $account;
    }

    $db->update('accounts', [
        'is_active' => $active,
        'updated_at' => date('Y-m-d H:i:s'),
    ], 'id = :id', ['id' => $id]);

    $updated = fetchAccountById($db, $id);
    $auditService->log(
        $active ? 'coa_activate' : 'coa_deactivate',
        $userId,
        'accounts',
        $id,
        $account,
        $updated
    );

    return $updated;
}

function fetchAccountById(Database $db, int $id): ?array
{
    $row = $db->fetchOne(
        'SELECT id, code, name, type, classification, statement_section, reporting_order, parent_code, ifrs_reference, is_active, created_at, updated_at
         FROM accounts
         WHERE id = ?
         LIMIT 1',
        [$id]
    );

    if (!$row) {
        return null;
    }

    $row['reporting_order'] = isset($row['reporting_order']) ? (int)$row['reporting_order'] : 0;
    $row['is_active'] = (int)($row['is_active'] ?? 0) === 1;

    return $row;
}

function createGuidedRevenueStream(Database $db, AuditLogService $auditService, int $userId, array $payload): array
{
    $templates = function_exists('getChartOfAccountsTemplates') ? getChartOfAccountsTemplates() : [];
    $templateCodeRaw = strtoupper(trim((string)($payload['template_code'] ?? '')));
    $template = $templateCodeRaw !== '' && isset($templates[$templateCodeRaw]) ? $templates[$templateCodeRaw] : null;

    $name = trim((string)($payload['name'] ?? ''));
    if ($name === '') {
        throw new Exception('Revenue stream name is required.');
    }

    $code = strtoupper(trim((string)($payload['code'] ?? '')));
    if ($code === '') {
        throw new Exception('Account code is required.');
    }

    if (!preg_match('/^[A-Z0-9\-]{3,20}$/', $code)) {
        throw new Exception('Account code must be 3-20 characters (letters, numbers, dash).');
    }

    $allowedClassifications = ['REVENUE', 'OTHER_INCOME', 'CONTRA_REVENUE'];
    $classification = strtoupper(trim((string)($payload['classification'] ?? ($template['classification'] ?? 'REVENUE'))));
    if (!in_array($classification, $allowedClassifications, true)) {
        throw new Exception('Unsupported classification for guided revenue stream.');
    }

    $type = strtoupper(trim((string)($payload['type'] ?? ($template['type'] ?? 'REVENUE'))));
    if (!in_array($type, COA_ACCOUNT_TYPES, true)) {
        $type = match ($classification) {
            'OTHER_INCOME' => 'OTHER_INCOME',
            'CONTRA_REVENUE' => 'CONTRA_REVENUE',
            default => 'REVENUE',
        };
    }

    $statementSection = strtoupper(trim((string)($payload['statement_section'] ?? ($template['statement_section'] ?? 'PROFIT_AND_LOSS'))));
    if (!in_array($statementSection, COA_STATEMENT_SECTIONS, true)) {
        $statementSection = 'PROFIT_AND_LOSS';
    }

    $parentCodeRaw = strtoupper(trim((string)($payload['parent_code'] ?? ($template['parent_code'] ?? ''))));
    $parentCode = $parentCodeRaw !== '' ? $parentCodeRaw : null;

    $reportingOrder = isset($payload['reporting_order']) && $payload['reporting_order'] !== ''
        ? max(0, (int)$payload['reporting_order'])
        : (isset($template['reporting_order']) ? (int)$template['reporting_order'] : 0);

    $ifrsReference = trim((string)($payload['ifrs_reference'] ?? ($template['ifrs_reference'] ?? '')));

    $taxBehaviour = strtolower(trim((string)($payload['tax_behaviour'] ?? 'standard')));
    $allowedBehaviours = ['standard', 'zero_rated', 'exempt', 'custom'];
    if (!in_array($taxBehaviour, $allowedBehaviours, true)) {
        throw new Exception('Invalid tax behaviour selection.');
    }

    $customTaxRate = null;
    if ($taxBehaviour === 'custom') {
        if (!isset($payload['custom_tax_rate']) || $payload['custom_tax_rate'] === '') {
            throw new Exception('Please provide a custom tax percentage.');
        }

        $normalized = normalizeDecimalValue($payload['custom_tax_rate']);
        if (!is_numeric($normalized)) {
            throw new Exception('Custom tax rate must be numeric.');
        }

        $customTaxRate = round((float)$normalized, 4);
        if ($customTaxRate < 0) {
            throw new Exception('Custom tax rate cannot be negative.');
        }
    }

    $description = trim((string)($payload['description'] ?? ''));

    $pdo = $db->getConnection();
    $shouldRollback = false;
    try {
        if (!$pdo->inTransaction()) {
            $pdo->beginTransaction();
            $shouldRollback = true;
        }

        $accountPayload = [
            'code' => $code,
            'name' => $name,
            'type' => $type,
            'classification' => $classification,
            'statement_section' => $statementSection,
            'reporting_order' => $reportingOrder,
            'parent_code' => $parentCode,
            'ifrs_reference' => $ifrsReference !== '' ? $ifrsReference : null,
            'is_active' => 1,
        ];

        $account = saveAccount($db, $auditService, $userId, $accountPayload);

        $preference = [
            'behaviour' => $taxBehaviour,
            'custom_rate' => $customTaxRate,
            'description' => $description !== '' ? $description : null,
            'template_code' => $templateCodeRaw !== '' ? $templateCodeRaw : null,
            'account_name' => $name,
            'updated_at' => date('c'),
        ];

        persistRevenueTaxPreference($db, $account['code'], $preference);

        if ($shouldRollback) {
            $pdo->commit();
        }

        return [
            'account' => $account,
            'tax_preference' => $preference,
        ];
    } catch (Exception $e) {
        if ($shouldRollback && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function persistRevenueTaxPreference(Database $db, string $accountCode, array $preference): void
{
    $existing = $db->fetchOne('SELECT setting_value FROM settings WHERE setting_key = ? LIMIT 1', ['revenue_tax_preferences']);
    $map = [];
    if ($existing && isset($existing['setting_value']) && $existing['setting_value'] !== '') {
        $decoded = json_decode((string)$existing['setting_value'], true);
        if (is_array($decoded)) {
            $map = $decoded;
        }
    }

    $map[$accountCode] = $preference;

    static $settingsColumns = null;
    if ($settingsColumns === null) {
        try {
            $columns = $db->fetchAll('SHOW COLUMNS FROM settings');
            $settingsColumns = array_column($columns ?? [], 'Field');
        } catch (Exception $e) {
            $settingsColumns = [];
        }
    }

    $hasSettingType = in_array('setting_type', $settingsColumns ?? [], true);
    $hasDescription = in_array('description', $settingsColumns ?? [], true);

    if ($hasSettingType && $hasDescription) {
        $sql = 'INSERT INTO settings (setting_key, setting_value, setting_type, description) '
            . 'VALUES (?, ?, ?, ?) '
            . 'ON DUPLICATE KEY UPDATE '
            . 'setting_value = VALUES(setting_value), '
            . 'setting_type = VALUES(setting_type), '
            . 'description = VALUES(description)';

        $db->query(
            $sql,
            [
                'revenue_tax_preferences',
                json_encode($map, JSON_UNESCAPED_UNICODE),
                'json',
                'Tax treatment per revenue account',
            ]
        );
        return;
    }

    $db->query(
        'INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) '
        . 'ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)',
        [
            'revenue_tax_preferences',
            json_encode($map, JSON_UNESCAPED_UNICODE),
        ]
    );
}

function normalizeDecimalValue($value): string
{
    if (is_numeric($value)) {
        return (string)$value;
    }

    if (is_string($value)) {
        $normalized = str_replace(',', '', $value);
        if ($normalized === '') {
            return '';
        }

        if (is_numeric($normalized)) {
            return $normalized;
        }
    }

    return '';
}
