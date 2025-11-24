<?php
require_once '../includes/bootstrap.php';

header('Content-Type: application/json');

if (!$auth->isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$db = Database::getInstance();

function deliveryColumnExists($db, string $column): bool
{
    static $cache = [];
    if (array_key_exists($column, $cache)) {
        return $cache[$column];
    }

    $result = $db->fetchOne("SHOW COLUMNS FROM deliveries LIKE ?", [$column]);
    $cache[$column] = (bool)$result;
    return $cache[$column];
}

function tableExists($db, string $table): bool
{
    static $cache = [];
    if (array_key_exists($table, $cache)) {
        return $cache[$table];
    }

    $row = $db->fetchOne(
        'SELECT COUNT(*) AS total FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
        [$table]
    );

    $cache[$table] = !empty($row['total']);
    return $cache[$table];
}

function calculateDistanceKm(?float $lat1, ?float $lon1, ?float $lat2, ?float $lon2): ?float
{
    if ($lat1 === null || $lon1 === null || $lat2 === null || $lon2 === null) {
        return null;
    }

    $earthRadiusKm = 6371.0;

    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    $lat1Rad = deg2rad($lat1);
    $lat2Rad = deg2rad($lat2);

    $a = sin($dLat / 2) * sin($dLat / 2)
        + sin($dLon / 2) * sin($dLon / 2) * cos($lat1Rad) * cos($lat2Rad);
    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

    return round($earthRadiusKm * $c, 2);
}

function formatIdleDuration(?string $timestamp): ?string
{
    if (!$timestamp) {
        return null;
    }

    $ts = strtotime($timestamp);
    if ($ts === false) {
        return null;
    }

    $diffMinutes = (int)floor((time() - $ts) / 60);
    if ($diffMinutes <= 0) {
        return 'idle now';
    }

    if ($diffMinutes < 60) {
        return $diffMinutes . ' min idle';
    }

    $hours = intdiv($diffMinutes, 60);
    $minutes = $diffMinutes % 60;

    if ($hours >= 24) {
        $days = intdiv($hours, 24);
        $remainingHours = $hours % 24;
        if ($remainingHours > 0) {
            return sprintf('idle %dd %dh', $days, $remainingHours);
        }
        return sprintf('idle %dd', $days);
    }

    if ($minutes > 0) {
        return sprintf('idle %dh %dm', $hours, $minutes);
    }

    return sprintf('idle %dh', $hours);
}

try {
    $stats = [
        'active_deliveries' => $db->fetchOne("SELECT COUNT(*) as count FROM deliveries WHERE status IN ('assigned', 'picked-up', 'in-transit')")['count'] ?? 0,
        'pending_deliveries' => $db->fetchOne("SELECT COUNT(*) as count FROM deliveries WHERE status = 'pending'")['count'] ?? 0,
        'completed_today' => $db->fetchOne("SELECT COUNT(*) as count FROM deliveries WHERE status = 'delivered' AND DATE(actual_delivery_time) = CURDATE()")['count'] ?? 0,
        'average_delivery_time' => round($db->fetchOne("SELECT AVG(TIMESTAMPDIFF(MINUTE, created_at, actual_delivery_time)) as avg_time FROM deliveries WHERE status = 'delivered' AND DATE(actual_delivery_time) = CURDATE()")['avg_time'] ?? 0)
    ];

    $supportsDeliveryMetrics = deliveryColumnExists($db, 'estimated_distance_km');
    $supportsPricingAudit = tableExists($db, 'delivery_pricing_audit');

    $riders = $db->fetchAll("
        SELECT 
            r.id,
            r.name,
            r.phone,
            r.vehicle_type,
            r.vehicle_number,
            r.vehicle_make,
            r.vehicle_color,
            r.license_number,
            r.vehicle_plate_photo_url,
            r.status,
            COALESCE(r.total_deliveries, 0) AS total_deliveries,
            COALESCE(r.rating, 0) AS rating,
            r.current_latitude,
            r.current_longitude,
            r.last_location_update,
            r.last_delivery_at,
            (
                SELECT COUNT(*)
                FROM deliveries d
                WHERE d.rider_id = r.id
                  AND d.status IN ('assigned', 'picked-up', 'in-transit')
            ) AS active_jobs,
            (
                SELECT MAX(COALESCE(d2.assigned_at, d2.updated_at))
                FROM deliveries d2
                WHERE d2.rider_id = r.id
            ) AS last_assigned_at
        FROM riders r
        WHERE r.is_active = 1
        ORDER BY r.name
    ");

    $deliverySettingKeys = [
        'delivery_max_active_jobs',
        'delivery_sla_pending_limit',
        'delivery_sla_assigned_limit',
        'delivery_sla_delivery_limit',
        'delivery_sla_slack_minutes',
    ];
    $placeholders = implode(',', array_fill(0, count($deliverySettingKeys), '?'));
    $settingsRows = $db->fetchAll(
        "SELECT setting_key, setting_value FROM settings WHERE setting_key IN ($placeholders)",
        $deliverySettingKeys
    );
    $deliverySettings = [];
    foreach ($settingsRows as $row) {
        $deliverySettings[$row['setting_key']] = $row['setting_value'];
    }

    $maxActiveJobsSetting = isset($deliverySettings['delivery_max_active_jobs'])
        ? (int)$deliverySettings['delivery_max_active_jobs']
        : 3;
    $maxActiveJobs = max(1, $maxActiveJobsSetting);

    $slaConfig = [
        'pending_minutes_limit' => max(1, (int)($deliverySettings['delivery_sla_pending_limit'] ?? 15)),
        'assigned_minutes_limit' => max(1, (int)($deliverySettings['delivery_sla_assigned_limit'] ?? 10)),
        'delivery_minutes_limit' => max(1, (int)($deliverySettings['delivery_sla_delivery_limit'] ?? 45)),
        'slack_minutes' => max(0, (int)($deliverySettings['delivery_sla_slack_minutes'] ?? 5)),
    ];

    $riderProfiles = [];
    $availableRiders = 0;
    $busyRiders = 0;
    $offlineRiders = 0;
    $totalActiveJobs = 0;
    $idleMinutesSum = 0;
    $idleCount = 0;
    foreach ($riders as &$rider) {
        $rider['active_jobs'] = (int)($rider['active_jobs'] ?? 0);

        $lastActivity = $rider['last_delivery_at']
            ?? $rider['last_assigned_at']
            ?? $rider['last_location_update']
            ?? null;

        $idleMinutes = null;
        if ($lastActivity) {
            $timestamp = strtotime($lastActivity);
            if ($timestamp !== false) {
                $idleMinutes = max(0, (int)floor((time() - $timestamp) / 60));
            }
        }

        $rider['idle_minutes'] = $idleMinutes;
        $rider['idle_display'] = formatIdleDuration($lastActivity);
        $rider['location_available'] = $rider['current_latitude'] !== null && $rider['current_longitude'] !== null;

        $riderProfiles[(int)$rider['id']] = $rider;

        $statusLower = strtolower((string)($rider['status'] ?? 'offline'));
        if ($statusLower === 'available') {
            $availableRiders++;
        } elseif ($statusLower === 'busy') {
            $busyRiders++;
        } else {
            $offlineRiders++;
        }

        $totalActiveJobs += $rider['active_jobs'];
        if ($idleMinutes !== null) {
            $idleMinutesSum += $idleMinutes;
            $idleCount++;
        }
    }
    unset($rider);

    $stats['riders_available'] = $availableRiders;
    $stats['riders_busy'] = $busyRiders;
    $stats['riders_offline'] = $offlineRiders;
    $stats['rider_active_jobs'] = $totalActiveJobs;
    if ($idleCount > 0) {
        $stats['avg_idle_minutes'] = round($idleMinutesSum / $idleCount, 1);
    }

    // Get active deliveries
    $deliveriesQuery = "
        SELECT 
            d.id,
            d.order_id,
            d.status,
            d.rider_id,
            d.delivery_address,
            d.created_at,
            d.updated_at,
            o.order_number,
            o.customer_name,
            o.customer_phone,
            o.total_amount,
            r.name as rider_name,
            r.phone as rider_phone,
            r.vehicle_type,
            r.vehicle_number,
            r.vehicle_make,
            r.vehicle_color,
            r.license_number,
            r.vehicle_plate_photo_url,
            r.current_latitude,
            r.current_longitude,
            TIMESTAMPDIFF(MINUTE, d.created_at, NOW()) as elapsed_minutes";

    if ($supportsDeliveryMetrics) {
        $deliveriesQuery .= ",
            d.estimated_distance_km,
            d.delivery_latitude,
            d.delivery_longitude,
            d.delivery_zone_id,
            dz.zone_name as delivery_zone_name,
            dz.zone_code as delivery_zone_code";
    }

    if ($supportsPricingAudit) {
        $deliveriesQuery .= ",
            audit.distance_m as pricing_distance_m,
            audit.fee_applied as pricing_fee_applied,
            audit.provider as pricing_provider,
            audit.cache_hit as pricing_cache_hit,
            audit.fallback_used as pricing_fallback_used,
            audit.request_id as pricing_request_id,
            audit.created_at as pricing_calculated_at,
            rules.rule_name as pricing_rule_name";
    }

    $deliveriesQuery .= "
        FROM deliveries d
        JOIN orders o ON d.order_id = o.id
        LEFT JOIN riders r ON d.rider_id = r.id";

    if ($supportsDeliveryMetrics) {
        $deliveriesQuery .= "
        LEFT JOIN delivery_zones dz ON dz.id = d.delivery_zone_id";
    }

    if ($supportsPricingAudit) {
        $deliveriesQuery .= "
        LEFT JOIN (
            SELECT a.order_id, a.distance_m, a.fee_applied, a.provider, a.cache_hit, a.fallback_used, a.request_id, a.created_at, a.rule_id
            FROM delivery_pricing_audit a
            INNER JOIN (
                SELECT order_id, MAX(created_at) AS latest_created
                FROM delivery_pricing_audit
                GROUP BY order_id
            ) latest ON latest.order_id = a.order_id AND latest.latest_created = a.created_at
        ) audit ON audit.order_id = d.order_id
        LEFT JOIN delivery_pricing_rules rules ON rules.id = audit.rule_id";
    }

    $deliveriesQuery .= "
        WHERE d.status IN ('pending', 'assigned', 'picked-up', 'in-transit')
        ORDER BY d.created_at ASC
    ";

    $deliveries = $db->fetchAll($deliveriesQuery);

    $activeRiders = count(array_filter($riders, fn($r) => ($r['status'] ?? '') === 'available'));
    $stats['active_riders'] = $activeRiders;
    $stats['active_deliveries_current'] = count($deliveries);

    $pendingActive = 0;
    $inTransitActive = 0;
    $distanceSum = 0.0;
    $distanceCount = 0;

    $pricingSummary = [
        'tracked_orders' => 0,
        'avg_distance_km' => null,
        'avg_fee' => null,
        'cache_hits' => 0,
        'fallback_calls' => 0,
    ];

    $pricingDistanceSum = 0.0;
    $pricingFeeSum = 0.0;

    $ordersAtRisk = [];
    $ordersLate = [];
    $deliveredWithinSla = 0;
    $deliveredTotal = 0;
    $now = new DateTimeImmutable('now');

    foreach ($deliveries as &$delivery) {
        $status = $delivery['status'] ?? 'pending';
        if ($status === 'pending' || $status === 'assigned') {
            $pendingActive++;
        }
        if ($status === 'picked-up' || $status === 'in-transit') {
            $inTransitActive++;
        }

        if ($supportsDeliveryMetrics && isset($delivery['estimated_distance_km']) && $delivery['estimated_distance_km'] !== null) {
            $distanceSum += (float)$delivery['estimated_distance_km'];
            $distanceCount++;
        }

        if ($supportsPricingAudit && isset($delivery['pricing_fee_applied'])) {
            $pricingSummary['tracked_orders']++;
            $pricingFeeSum += (float)$delivery['pricing_fee_applied'];
            if (isset($delivery['pricing_distance_m']) && $delivery['pricing_distance_m'] !== null) {
                $pricingDistanceSum += ((float)$delivery['pricing_distance_m']) / 1000;
            }
            if (!empty($delivery['pricing_cache_hit'])) {
                $pricingSummary['cache_hits']++;
            }
            if (!empty($delivery['pricing_fallback_used'])) {
                $pricingSummary['fallback_calls']++;
            }
        }

        // SLA metrics
        $createdAt = isset($delivery['created_at']) ? new DateTimeImmutable($delivery['created_at']) : null;
        $assignedAt = isset($delivery['assigned_at']) ? new DateTimeImmutable($delivery['assigned_at']) : null;
        $pickedAt = isset($delivery['picked_up_at']) ? new DateTimeImmutable($delivery['picked_up_at']) : null;
        $deliveredAt = isset($delivery['actual_delivery_time']) ? new DateTimeImmutable($delivery['actual_delivery_time']) : null;

        $delivery['sla'] = [
            'phase' => $status,
            'wait_minutes' => null,
            'elapsed_minutes' => $delivery['elapsed_minutes'] ?? null,
            'promised_time' => null,
            'delay_minutes' => null,
            'is_at_risk' => false,
            'is_late' => false,
        ];

        if ($createdAt) {
            $elapsedSinceOrder = (int)floor(($now->getTimestamp() - $createdAt->getTimestamp()) / 60);
            $delivery['sla']['wait_minutes'] = $elapsedSinceOrder;

            $promised = clone $createdAt;
            $promised->modify('+' . $slaConfig['delivery_minutes_limit'] . ' minutes');
            $delivery['sla']['promised_time'] = $promised->format('Y-m-d H:i:s');

            if ($deliveredAt) {
                $deliveredTotal++;
                $actualDuration = (int)floor(($deliveredAt->getTimestamp() - $createdAt->getTimestamp()) / 60);
                if ($actualDuration <= $slaConfig['delivery_minutes_limit'] + $slaConfig['slack_minutes']) {
                    $deliveredWithinSla++;
                } else {
                    $delivery['sla']['is_late'] = true;
                    $delivery['sla']['delay_minutes'] = $actualDuration - $slaConfig['delivery_minutes_limit'];
                }
            } else {
                $delivery['sla']['delay_minutes'] = max(0, $elapsedSinceOrder - $slaConfig['delivery_minutes_limit']);

                $phaseLimit = match ($status) {
                    'pending' => $slaConfig['pending_minutes_limit'],
                    'assigned' => $slaConfig['assigned_minutes_limit'],
                    'picked-up', 'in-transit' => $slaConfig['delivery_minutes_limit'],
                    default => $slaConfig['delivery_minutes_limit'],
                };

                $phaseStart = match ($status) {
                    'pending' => $createdAt,
                    'assigned' => $assignedAt ?? $createdAt,
                    'picked-up', 'in-transit' => $pickedAt ?? $assignedAt ?? $createdAt,
                    default => $createdAt,
                };

                if ($phaseStart) {
                    $phaseElapsed = (int)floor(($now->getTimestamp() - $phaseStart->getTimestamp()) / 60);
                    if ($phaseElapsed > ($phaseLimit + $slaConfig['slack_minutes'])) {
                        $delivery['sla']['is_at_risk'] = true;
                        $ordersAtRisk[] = [
                            'order_id' => $delivery['order_id'],
                            'order_number' => $delivery['order_number'] ?? null,
                            'customer_name' => $delivery['customer_name'] ?? null,
                            'delivery_address' => $delivery['delivery_address'] ?? null,
                            'estimated_delivery_time' => $delivery['estimated_delivery_time'] ?? null,
                            'promised_time' => $delivery['sla']['promised_time'],
                            'delay_minutes' => $delivery['sla']['delay_minutes'],
                            'status' => $status,
                        ];
                    }
                }

                if ($delivery['sla']['delay_minutes'] > $slaConfig['slack_minutes']) {
                    $ordersLate[] = $delivery['order_id'];
                }
            }
        }

        // Compute rider recommendations for this delivery
        $deliveryLat = ($supportsDeliveryMetrics && isset($delivery['delivery_latitude']) && $delivery['delivery_latitude'] !== null)
            ? (float)$delivery['delivery_latitude']
            : null;
        $deliveryLon = ($supportsDeliveryMetrics && isset($delivery['delivery_longitude']) && $delivery['delivery_longitude'] !== null)
            ? (float)$delivery['delivery_longitude']
            : null;

        $candidateRecommendations = [];
        foreach ($riderProfiles as $riderId => $profile) {
            $statusNormalized = strtolower((string)($profile['status'] ?? ''));
            $activeJobs = (int)($profile['active_jobs'] ?? 0);

            if ($statusNormalized !== 'available' && $activeJobs >= $maxActiveJobs) {
                continue;
            }

            $distanceKm = null;
            if ($supportsDeliveryMetrics && $deliveryLat !== null && $deliveryLon !== null && !empty($profile['location_available'])) {
                $distanceKm = calculateDistanceKm(
                    isset($profile['current_latitude']) ? (float)$profile['current_latitude'] : null,
                    isset($profile['current_longitude']) ? (float)$profile['current_longitude'] : null,
                    $deliveryLat,
                    $deliveryLon
                );
            }

            $score = 0.0;
            $score += $statusNormalized === 'available' ? 40 : 20;
            $score += max(0, $maxActiveJobs - $activeJobs) * 10;

            $rating = (float)($profile['rating'] ?? 0);
            $score += $rating * 4;

            if ($distanceKm !== null) {
                $score += max(0, 15 - min($distanceKm, 15)) * 3;
            } else {
                // Modest boost when distance is unknown to avoid penalizing riders without GPS
                $score += 6;
            }

            if ($profile['idle_minutes'] !== null) {
                $score += min((int)$profile['idle_minutes'], 240) / 4; // up to +60 for long idle riders
            }

            $candidateRecommendations[] = [
                'rider_id' => (int)$riderId,
                'score' => round($score, 2),
                'status' => $statusNormalized ?: null,
                'active_jobs' => $activeJobs,
                'idle_minutes' => $profile['idle_minutes'],
                'idle_display' => $profile['idle_display'],
                'rating' => round($rating, 2),
                'distance_km' => $distanceKm,
                'distance_display' => $distanceKm !== null ? number_format($distanceKm, 2) . ' km' : null,
            ];
        }

        usort($candidateRecommendations, function (array $a, array $b) {
            if ($b['score'] === $a['score']) {
                return $a['active_jobs'] <=> $b['active_jobs'];
            }
            return $b['score'] <=> $a['score'];
        });

        $topRecommendations = array_slice($candidateRecommendations, 0, 3);
        $delivery['recommended_riders'] = $topRecommendations;
        $delivery['recommended_rider_id'] = $topRecommendations[0]['rider_id'] ?? null;
    }
    unset($delivery);

    $stats['pending_active'] = $pendingActive;
    $stats['in_transit_active'] = $inTransitActive;

    if ($supportsDeliveryMetrics && $distanceCount > 0) {
        $stats['average_distance_km'] = round($distanceSum / $distanceCount, 2);
    }

    if ($supportsPricingAudit && $pricingSummary['tracked_orders'] > 0) {
        if ($pricingDistanceSum > 0) {
            $pricingSummary['avg_distance_km'] = round($pricingDistanceSum / $pricingSummary['tracked_orders'], 2);
        }
        $pricingSummary['avg_fee'] = round($pricingFeeSum / $pricingSummary['tracked_orders'], 2);
    }

    $stats['orders_at_risk'] = count($ordersAtRisk);
    $stats['orders_overdue'] = count(array_unique($ordersLate));
    if ($deliveredTotal > 0) {
        $stats['on_time_rate'] = round(($deliveredWithinSla / $deliveredTotal) * 100, 1);
    }

    // Get chart data for the last 7 days
    $chartData = $db->fetchAll("
        SELECT 
            DATE(actual_delivery_time) as delivery_date,
            COUNT(*) as deliveries_count,
            AVG(TIMESTAMPDIFF(MINUTE, created_at, actual_delivery_time)) as avg_time
        FROM deliveries 
        WHERE status = 'delivered' 
        AND actual_delivery_time >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        GROUP BY DATE(actual_delivery_time)
        ORDER BY delivery_date ASC
    ");

    // Get rider performance data
    $riderPerformance = $db->fetchAll("
        SELECT 
            r.name,
            r.id,
            COUNT(d.id) as total_deliveries,
            AVG(d.customer_rating) as avg_customer_rating,
            AVG(TIMESTAMPDIFF(MINUTE, d.created_at, d.actual_delivery_time)) as avg_delivery_time,
            SUM(CASE WHEN d.status = 'delivered' THEN 1 ELSE 0 END) as successful_deliveries
        FROM riders r
        LEFT JOIN deliveries d ON r.id = d.rider_id AND DATE(d.created_at) = CURDATE()
        WHERE r.is_active = 1
        GROUP BY r.id, r.name
        ORDER BY successful_deliveries DESC, avg_customer_rating DESC
    ");

    $pricingMetrics = null;
    if ($supportsPricingAudit) {
        $pricingMetrics = [
            'total_requests' => (int)($db->fetchOne('SELECT COUNT(*) AS total FROM delivery_pricing_audit')['total'] ?? 0),
            'cache_hits' => (int)($db->fetchOne('SELECT COUNT(*) AS total FROM delivery_pricing_audit WHERE cache_hit = 1')['total'] ?? 0),
            'fallback_calls' => (int)($db->fetchOne('SELECT COUNT(*) AS total FROM delivery_pricing_audit WHERE fallback_used = 1')['total'] ?? 0),
            'avg_distance_km' => null,
            'avg_fee' => null,
            'last_request_at' => null,
            'summary' => $pricingSummary,
        ];

        if ($pricingMetrics['total_requests'] > 0) {
            $avgDistanceRow = $db->fetchOne('SELECT AVG(distance_m) AS avg_distance FROM delivery_pricing_audit WHERE distance_m IS NOT NULL');
            $avgFeeRow = $db->fetchOne('SELECT AVG(fee_applied) AS avg_fee FROM delivery_pricing_audit WHERE fee_applied IS NOT NULL');
            $lastRow = $db->fetchOne('SELECT created_at FROM delivery_pricing_audit ORDER BY created_at DESC LIMIT 1');

            if (!empty($avgDistanceRow['avg_distance'])) {
                $pricingMetrics['avg_distance_km'] = round(((float)$avgDistanceRow['avg_distance']) / 1000, 2);
            }
            if (!empty($avgFeeRow['avg_fee'])) {
                $pricingMetrics['avg_fee'] = round((float)$avgFeeRow['avg_fee'], 2);
            }
            if (!empty($lastRow['created_at'])) {
                $pricingMetrics['last_request_at'] = $lastRow['created_at'];
            }
        }
    }

    echo json_encode([
        'success' => true,
        'stats' => $stats,
        'deliveries' => $deliveries,
        'chartData' => $chartData,
        'riderPerformance' => $riderPerformance,
        'supportsDeliveryMetrics' => $supportsDeliveryMetrics,
        'supportsPricingAudit' => $supportsPricingAudit,
        'pricingMetrics' => $pricingMetrics,
        'riders' => $riders,
        'config' => [
            'max_active_jobs' => $maxActiveJobs,
            'sla' => $slaConfig,
        ],
        'sla_risk' => $ordersAtRisk
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
