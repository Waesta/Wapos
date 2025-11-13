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

try {
    $stats = [
        'active_deliveries' => $db->fetchOne("SELECT COUNT(*) as count FROM deliveries WHERE status IN ('assigned', 'picked-up', 'in-transit')")['count'] ?? 0,
        'pending_deliveries' => $db->fetchOne("SELECT COUNT(*) as count FROM deliveries WHERE status = 'pending'")['count'] ?? 0,
        'completed_today' => $db->fetchOne("SELECT COUNT(*) as count FROM deliveries WHERE status = 'delivered' AND DATE(actual_delivery_time) = CURDATE()")['count'] ?? 0,
        'average_delivery_time' => round($db->fetchOne("SELECT AVG(TIMESTAMPDIFF(MINUTE, created_at, actual_delivery_time)) as avg_time FROM deliveries WHERE status = 'delivered' AND DATE(actual_delivery_time) = CURDATE()")['avg_time'] ?? 0)
    ];

    $supportsDeliveryMetrics = deliveryColumnExists($db, 'estimated_distance_km');

    $riders = $db->fetchAll("
        SELECT 
            id,
            name,
            phone,
            vehicle_type,
            vehicle_number,
            status,
            COALESCE(total_deliveries, 0) AS total_deliveries,
            COALESCE(rating, 0) AS rating
        FROM riders
        WHERE is_active = 1
        ORDER BY name
    ");

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

    $deliveriesQuery .= "
        FROM deliveries d
        JOIN orders o ON d.order_id = o.id
        LEFT JOIN riders r ON d.rider_id = r.id";

    if ($supportsDeliveryMetrics) {
        $deliveriesQuery .= "
        LEFT JOIN delivery_zones dz ON dz.id = d.delivery_zone_id";
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

    foreach ($deliveries as $delivery) {
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
    }

    $stats['pending_active'] = $pendingActive;
    $stats['in_transit_active'] = $inTransitActive;

    if ($supportsDeliveryMetrics && $distanceCount > 0) {
        $stats['average_distance_km'] = round($distanceSum / $distanceCount, 2);
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

    echo json_encode([
        'success' => true,
        'stats' => $stats,
        'deliveries' => $deliveries,
        'chartData' => $chartData,
        'riderPerformance' => $riderPerformance,
        'supportsDeliveryMetrics' => $supportsDeliveryMetrics,
        'riders' => $riders
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
