<?php

require_once '../includes/bootstrap.php';

header('Content-Type: application/json');

try {
    $auth->requireLogin();
    $user = $auth->getUser();
    if (!$user || !in_array(strtolower($user['role'] ?? ''), ['admin', 'developer'], true)) {
        throw new Exception('You do not have permission to manage delivery pricing.');
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
        exit;
    }

    $payload = json_decode(file_get_contents('php://input'), true);
    if (!is_array($payload)) {
        throw new Exception('Invalid JSON payload');
    }

    enforceCsrf($payload['csrf_token'] ?? '');

    $action = $payload['action'] ?? '';
    if (!$action) {
        throw new Exception('Missing action');
    }

    $db = Database::getInstance();

    switch ($action) {
        case 'list_rules':
            $response = [
                'success' => true,
                'rules' => listRules($db),
            ];
            break;

        case 'save_rule':
            $response = saveRule($db, $payload);
            break;

        case 'delete_rule':
            deleteRule($db, $payload);
            $response = ['success' => true];
            break;

        case 'get_metrics':
            $response = [
                'success' => true,
                'metrics' => getMetrics($db),
            ];
            break;

        case 'purge_cache':
            purgeCache($db);
            $response = ['success' => true];
            break;

        default:
            throw new Exception('Unsupported action: ' . $action);
    }

    echo json_encode($response);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

function enforceCsrf(string $token): void
{
    if (!validateCSRFToken($token)) {
        throw new Exception('Invalid CSRF token');
    }
}

function listRules(Database $db): array
{
    return $db->fetchAll(
        'SELECT id, rule_name, priority, distance_min_km, distance_max_km, base_fee, per_km_fee, surcharge_percent, notes, is_active
         FROM delivery_pricing_rules
         ORDER BY priority ASC, distance_min_km ASC'
    );
}

function saveRule(Database $db, array $payload): array
{
    $id = isset($payload['id']) ? (int)$payload['id'] : null;
    $ruleName = trim((string)($payload['rule_name'] ?? ''));
    $priority = isset($payload['priority']) ? max(1, (int)$payload['priority']) : 1;
    $distanceMin = isset($payload['distance_min_km']) ? max(0, (float)$payload['distance_min_km']) : 0.0;
    $distanceMaxRaw = $payload['distance_max_km'] ?? null;
    $distanceMax = $distanceMaxRaw === '' || $distanceMaxRaw === null ? null : (float)$distanceMaxRaw;
    $baseFee = isset($payload['base_fee']) ? max(0, (float)$payload['base_fee']) : 0.0;
    $perKmFee = isset($payload['per_km_fee']) ? max(0, (float)$payload['per_km_fee']) : 0.0;
    $surchargePercent = isset($payload['surcharge_percent']) ? max(0, (float)$payload['surcharge_percent']) : 0.0;
    $notes = trim((string)($payload['notes'] ?? ''));
    $isActive = !empty($payload['is_active']) ? 1 : 0;

    if ($ruleName === '') {
        throw new Exception('Rule name is required');
    }

    if ($distanceMax !== null && $distanceMax <= $distanceMin) {
        throw new Exception('Max distance must be greater than min distance');
    }

    // Prevent overlapping ranges with other rules
    $existingRules = $db->fetchAll(
        'SELECT id, rule_name, distance_min_km, distance_max_km FROM delivery_pricing_rules WHERE id <> ? ORDER BY priority ASC',
        [$id ?? 0]
    );

    foreach ($existingRules as $existing) {
        $otherMin = (float)$existing['distance_min_km'];
        $otherMax = $existing['distance_max_km'] !== null ? (float)$existing['distance_max_km'] : null;

        if (rangesOverlap($distanceMin, $distanceMax, $otherMin, $otherMax)) {
            $label = $existing['rule_name'] ?: ('Rule #' . $existing['id']);
            throw new Exception('Distance range overlaps with existing rule: ' . $label);
        }
    }

    $data = [
        'rule_name' => $ruleName,
        'priority' => $priority,
        'distance_min_km' => $distanceMin,
        'distance_max_km' => $distanceMax,
        'base_fee' => $baseFee,
        'per_km_fee' => $perKmFee,
        'surcharge_percent' => $surchargePercent,
        'notes' => $notes,
        'is_active' => $isActive,
    ];

    if ($id) {
        $existing = $db->fetchOne('SELECT id FROM delivery_pricing_rules WHERE id = ?', [$id]);
        if (!$existing) {
            throw new Exception('Rule not found');
        }

        $db->update('delivery_pricing_rules', $data, 'id = :id', ['id' => $id]);
    } else {
        $id = (int)$db->insert('delivery_pricing_rules', $data);
    }

    $rule = $db->fetchOne('SELECT * FROM delivery_pricing_rules WHERE id = ?', [$id]);

    return [
        'success' => true,
        'rule' => $rule,
    ];
}

function deleteRule(Database $db, array $payload): void
{
    $id = isset($payload['id']) ? (int)$payload['id'] : 0;
    if ($id <= 0) {
        throw new Exception('Invalid rule identifier');
    }

    $existing = $db->fetchOne('SELECT id FROM delivery_pricing_rules WHERE id = ?', [$id]);
    if (!$existing) {
        throw new Exception('Rule not found');
    }

    $db->query('DELETE FROM delivery_pricing_rules WHERE id = ?', [$id]);
}

function getMetrics(Database $db): array
{
    $metrics = [
        'total_requests' => 0,
        'cache_hits' => 0,
        'fallback_calls' => 0,
        'avg_distance_km' => 0.0,
        'avg_fee' => 0.0,
        'api_calls' => 0,
        'last_request_at' => null,
        'cache_entries' => 0,
    ];

    $row = $db->fetchOne('SELECT COUNT(*) AS total FROM delivery_pricing_audit');
    $metrics['total_requests'] = (int)($row['total'] ?? 0);

    if ($metrics['total_requests'] > 0) {
        $metrics['cache_hits'] = (int)($db->fetchOne('SELECT COUNT(*) AS total FROM delivery_pricing_audit WHERE cache_hit = 1')['total'] ?? 0);
        $metrics['fallback_calls'] = (int)($db->fetchOne("SELECT COUNT(*) AS total FROM delivery_pricing_audit WHERE provider LIKE '%%fallback%%'")['total'] ?? 0);
        $metrics['api_calls'] = (int)($db->fetchOne('SELECT SUM(api_calls) AS total FROM delivery_pricing_audit')['total'] ?? 0);

        $avgDistance = $db->fetchOne('SELECT AVG(distance_m) AS avg_distance FROM delivery_pricing_audit WHERE distance_m IS NOT NULL');
        $avgFee = $db->fetchOne('SELECT AVG(fee_applied) AS avg_fee FROM delivery_pricing_audit WHERE fee_applied IS NOT NULL');
        $metrics['avg_distance_km'] = $avgDistance && $avgDistance['avg_distance'] ? round($avgDistance['avg_distance'] / 1000, 2) : 0.0;
        $metrics['avg_fee'] = $avgFee && $avgFee['avg_fee'] ? round($avgFee['avg_fee'], 2) : 0.0;

        $last = $db->fetchOne('SELECT created_at FROM delivery_pricing_audit ORDER BY created_at DESC LIMIT 1');
        $metrics['last_request_at'] = $last['created_at'] ?? null;
    }

    $metrics['cache_entries'] = (int)($db->fetchOne('SELECT COUNT(*) AS total FROM delivery_distance_cache')['total'] ?? 0);

    // Recent rule usage
    $ruleUsage = $db->fetchAll(
        "SELECT r.rule_name, COUNT(a.id) AS usage_count
         FROM delivery_pricing_audit a
         LEFT JOIN delivery_pricing_rules r ON r.id = a.rule_id
         GROUP BY r.rule_name
         ORDER BY usage_count DESC
         LIMIT 5"
    );

    // Recent requests sample
    $recentRequests = $db->fetchAll(
        "SELECT provider, cache_hit, fallback_used, fee_applied, distance_m, created_at
         FROM delivery_pricing_audit
         ORDER BY created_at DESC
         LIMIT 10"
    );

    $metrics['rule_usage'] = $ruleUsage;
    $metrics['recent_requests'] = array_map(function ($row) {
        $row['distance_km'] = $row['distance_m'] !== null ? round($row['distance_m'] / 1000, 2) : null;
        unset($row['distance_m']);
        return $row;
    }, $recentRequests);

    return $metrics;
}

function purgeCache(Database $db): void
{
    $db->query('DELETE FROM delivery_distance_cache');
}

function rangesOverlap(float $minA, ?float $maxA, float $minB, ?float $maxB): bool
{
    $maxA = $maxA ?? INF;
    $maxB = $maxB ?? INF;

    return $minA <= $maxB && $minB <= $maxA;
}
