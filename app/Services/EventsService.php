<?php
namespace App\Services;

use Database;
use Exception;

class EventsService {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    // ==================== VENUES ====================
    
    public function getVenues($filters = []) {
        $sql = "SELECT * FROM event_venues WHERE 1=1";
        $params = [];
        
        if (!empty($filters['is_active'])) {
            $sql .= " AND is_active = ?";
            $params[] = $filters['is_active'];
        }
        
        if (!empty($filters['venue_type'])) {
            $sql .= " AND venue_type = ?";
            $params[] = $filters['venue_type'];
        }
        
        if (!empty($filters['min_capacity'])) {
            $sql .= " AND capacity_seated >= ?";
            $params[] = $filters['min_capacity'];
        }
        
        $sql .= " ORDER BY venue_name ASC";
        
        return $this->db->fetchAll($sql, $params);
    }
    
    public function getVenueById($id) {
        return $this->db->fetchOne("SELECT * FROM event_venues WHERE id = ?", [$id]);
    }
    
    public function createVenue($data) {
        $sql = "INSERT INTO event_venues (
            venue_name, venue_code, venue_type, capacity_seated, capacity_standing,
            area_sqm, location, floor_level, description, amenities,
            hourly_rate, half_day_rate, full_day_rate, setup_time_minutes, 
            teardown_time_minutes, images, is_active, requires_approval
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $params = [
            $data['venue_name'],
            $data['venue_code'],
            $data['venue_type'],
            $data['capacity_seated'] ?? 0,
            $data['capacity_standing'] ?? 0,
            $data['area_sqm'] ?? null,
            $data['location'] ?? null,
            $data['floor_level'] ?? null,
            $data['description'] ?? null,
            !empty($data['amenities']) ? json_encode($data['amenities']) : null,
            $data['hourly_rate'] ?? 0,
            $data['half_day_rate'] ?? 0,
            $data['full_day_rate'] ?? 0,
            $data['setup_time_minutes'] ?? 60,
            $data['teardown_time_minutes'] ?? 60,
            !empty($data['images']) ? json_encode($data['images']) : null,
            $data['is_active'] ?? true,
            $data['requires_approval'] ?? false
        ];
        
        return $this->db->execute($sql, $params);
    }
    
    public function updateVenue($id, $data) {
        $sql = "UPDATE event_venues SET
            venue_name = ?, venue_type = ?, capacity_seated = ?, capacity_standing = ?,
            area_sqm = ?, location = ?, floor_level = ?, description = ?, amenities = ?,
            hourly_rate = ?, half_day_rate = ?, full_day_rate = ?, setup_time_minutes = ?,
            teardown_time_minutes = ?, images = ?, is_active = ?, requires_approval = ?
        WHERE id = ?";
        
        $params = [
            $data['venue_name'],
            $data['venue_type'],
            $data['capacity_seated'] ?? 0,
            $data['capacity_standing'] ?? 0,
            $data['area_sqm'] ?? null,
            $data['location'] ?? null,
            $data['floor_level'] ?? null,
            $data['description'] ?? null,
            !empty($data['amenities']) ? json_encode($data['amenities']) : null,
            $data['hourly_rate'] ?? 0,
            $data['half_day_rate'] ?? 0,
            $data['full_day_rate'] ?? 0,
            $data['setup_time_minutes'] ?? 60,
            $data['teardown_time_minutes'] ?? 60,
            !empty($data['images']) ? json_encode($data['images']) : null,
            $data['is_active'] ?? true,
            $data['requires_approval'] ?? false,
            $id
        ];
        
        return $this->db->execute($sql, $params);
    }
    
    public function checkVenueAvailability($venueId, $date, $startTime, $endTime, $excludeBookingId = null) {
        $sql = "SELECT COUNT(*) as count FROM event_bookings 
                WHERE venue_id = ? 
                AND event_date = ? 
                AND status NOT IN ('cancelled', 'no_show')
                AND (
                    (start_time < ? AND end_time > ?) OR
                    (start_time < ? AND end_time > ?) OR
                    (start_time >= ? AND end_time <= ?)
                )";
        
        $params = [$venueId, $date, $endTime, $startTime, $endTime, $startTime, $startTime, $endTime];
        
        if ($excludeBookingId) {
            $sql .= " AND id != ?";
            $params[] = $excludeBookingId;
        }
        
        $result = $this->db->fetchOne($sql, $params);
        return $result['count'] == 0;
    }
    
    // ==================== EVENT TYPES ====================
    
    public function getEventTypes($activeOnly = true) {
        $sql = "SELECT * FROM event_types WHERE 1=1";
        $params = [];
        
        if ($activeOnly) {
            $sql .= " AND is_active = 1";
        }
        
        $sql .= " ORDER BY type_name ASC";
        
        return $this->db->fetchAll($sql, $params);
    }
    
    public function getEventTypeById($id) {
        return $this->db->fetchOne("SELECT * FROM event_types WHERE id = ?", [$id]);
    }
    
    // ==================== BOOKINGS ====================
    
    public function getBookings($filters = []) {
        $sql = "SELECT eb.*, ev.venue_name, et.type_name, 
                u.full_name as coordinator_name,
                c.name as customer_full_name
                FROM event_bookings eb
                LEFT JOIN event_venues ev ON eb.venue_id = ev.id
                LEFT JOIN event_types et ON eb.event_type_id = et.id
                LEFT JOIN users u ON eb.assigned_coordinator_id = u.id
                LEFT JOIN customers c ON eb.customer_id = c.id
                WHERE 1=1";
        $params = [];
        
        if (!empty($filters['status'])) {
            $sql .= " AND eb.status = ?";
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['venue_id'])) {
            $sql .= " AND eb.venue_id = ?";
            $params[] = $filters['venue_id'];
        }
        
        if (!empty($filters['date_from'])) {
            $sql .= " AND eb.event_date >= ?";
            $params[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $sql .= " AND eb.event_date <= ?";
            $params[] = $filters['date_to'];
        }
        
        if (!empty($filters['search'])) {
            $sql .= " AND (eb.booking_number LIKE ? OR eb.customer_name LIKE ? OR eb.event_title LIKE ?)";
            $search = '%' . $filters['search'] . '%';
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
        }
        
        $sql .= " ORDER BY eb.event_date DESC, eb.start_time DESC";
        
        if (!empty($filters['limit'])) {
            $sql .= " LIMIT ?";
            $params[] = (int)$filters['limit'];
        }
        
        return $this->db->fetchAll($sql, $params);
    }
    
    public function getBookingById($id) {
        $booking = $this->db->fetchOne("
            SELECT eb.*, ev.venue_name, et.type_name,
            u.full_name as coordinator_name,
            c.name as customer_full_name, c.email as customer_email_db, c.phone as customer_phone_db
            FROM event_bookings eb
            LEFT JOIN event_venues ev ON eb.venue_id = ev.id
            LEFT JOIN event_types et ON eb.event_type_id = et.id
            LEFT JOIN users u ON eb.assigned_coordinator_id = u.id
            LEFT JOIN customers c ON eb.customer_id = c.id
            WHERE eb.id = ?
        ", [$id]);
        
        if ($booking) {
            $booking['services'] = $this->getBookingServices($id);
            $booking['payments'] = $this->getBookingPayments($id);
            $booking['setup_requirements'] = $this->getBookingSetupRequirements($id);
        }
        
        return $booking;
    }
    
    public function createBooking($data, $userId) {
        $bookingNumber = $this->generateBookingNumber();
        
        $sql = "INSERT INTO event_bookings (
            booking_number, event_type_id, venue_id, customer_id, customer_name,
            customer_email, customer_phone, customer_company, event_title, event_description,
            event_date, start_time, end_time, setup_start_time, teardown_end_time,
            expected_guests, status, booking_source, special_requests, internal_notes,
            venue_rate, catering_cost, decoration_cost, equipment_cost, other_charges,
            subtotal, discount_amount, discount_reason, tax_amount, total_amount,
            deposit_amount, balance_amount, payment_status, payment_terms,
            cancellation_policy, assigned_coordinator_id, created_by
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $params = [
            $bookingNumber,
            $data['event_type_id'] ?? null,
            $data['venue_id'],
            $data['customer_id'] ?? null,
            $data['customer_name'],
            $data['customer_email'] ?? null,
            $data['customer_phone'],
            $data['customer_company'] ?? null,
            $data['event_title'],
            $data['event_description'] ?? null,
            $data['event_date'],
            $data['start_time'],
            $data['end_time'],
            $data['setup_start_time'] ?? null,
            $data['teardown_end_time'] ?? null,
            $data['expected_guests'],
            $data['status'] ?? 'inquiry',
            $data['booking_source'] ?? 'walk_in',
            $data['special_requests'] ?? null,
            $data['internal_notes'] ?? null,
            $data['venue_rate'] ?? 0,
            $data['catering_cost'] ?? 0,
            $data['decoration_cost'] ?? 0,
            $data['equipment_cost'] ?? 0,
            $data['other_charges'] ?? 0,
            $data['subtotal'] ?? 0,
            $data['discount_amount'] ?? 0,
            $data['discount_reason'] ?? null,
            $data['tax_amount'] ?? 0,
            $data['total_amount'],
            $data['deposit_amount'] ?? 0,
            $data['balance_amount'] ?? $data['total_amount'],
            $data['payment_status'] ?? 'unpaid',
            $data['payment_terms'] ?? null,
            $data['cancellation_policy'] ?? null,
            $data['assigned_coordinator_id'] ?? null,
            $userId
        ];
        
        $bookingId = $this->db->execute($sql, $params);
        
        $this->logActivity($bookingId, 'booking_created', 'Booking created', null, null, $userId);
        
        return ['id' => $bookingId, 'booking_number' => $bookingNumber];
    }
    
    public function updateBooking($id, $data, $userId) {
        $sql = "UPDATE event_bookings SET
            event_type_id = ?, venue_id = ?, customer_name = ?, customer_email = ?,
            customer_phone = ?, customer_company = ?, event_title = ?, event_description = ?,
            event_date = ?, start_time = ?, end_time = ?, setup_start_time = ?,
            teardown_end_time = ?, expected_guests = ?, status = ?, special_requests = ?,
            internal_notes = ?, venue_rate = ?, catering_cost = ?, decoration_cost = ?,
            equipment_cost = ?, other_charges = ?, subtotal = ?, discount_amount = ?,
            discount_reason = ?, tax_amount = ?, total_amount = ?, deposit_amount = ?,
            balance_amount = ?, payment_status = ?, assigned_coordinator_id = ?
        WHERE id = ?";
        
        $params = [
            $data['event_type_id'] ?? null,
            $data['venue_id'],
            $data['customer_name'],
            $data['customer_email'] ?? null,
            $data['customer_phone'],
            $data['customer_company'] ?? null,
            $data['event_title'],
            $data['event_description'] ?? null,
            $data['event_date'],
            $data['start_time'],
            $data['end_time'],
            $data['setup_start_time'] ?? null,
            $data['teardown_end_time'] ?? null,
            $data['expected_guests'],
            $data['status'],
            $data['special_requests'] ?? null,
            $data['internal_notes'] ?? null,
            $data['venue_rate'] ?? 0,
            $data['catering_cost'] ?? 0,
            $data['decoration_cost'] ?? 0,
            $data['equipment_cost'] ?? 0,
            $data['other_charges'] ?? 0,
            $data['subtotal'] ?? 0,
            $data['discount_amount'] ?? 0,
            $data['discount_reason'] ?? null,
            $data['tax_amount'] ?? 0,
            $data['total_amount'],
            $data['deposit_amount'] ?? 0,
            $data['balance_amount'],
            $data['payment_status'],
            $data['assigned_coordinator_id'] ?? null,
            $id
        ];
        
        $result = $this->db->execute($sql, $params);
        $this->logActivity($id, 'booking_updated', 'Booking updated', null, null, $userId);
        
        return $result;
    }
    
    public function confirmBooking($id, $userId) {
        $sql = "UPDATE event_bookings SET status = 'confirmed', confirmed_by = ?, confirmed_at = NOW() WHERE id = ?";
        $result = $this->db->execute($sql, [$userId, $id]);
        $this->logActivity($id, 'booking_confirmed', 'Booking confirmed', 'pending', 'confirmed', $userId);
        return $result;
    }
    
    public function cancelBooking($id, $reason, $userId) {
        $sql = "UPDATE event_bookings SET status = 'cancelled', cancelled_by = ?, cancelled_at = NOW(), cancellation_reason = ? WHERE id = ?";
        $result = $this->db->execute($sql, [$userId, $reason, $id]);
        $this->logActivity($id, 'booking_cancelled', 'Booking cancelled: ' . $reason, null, null, $userId);
        return $result;
    }
    
    private function generateBookingNumber() {
        $prefix = 'EVT';
        $date = date('Ymd');
        
        $sql = "SELECT booking_number FROM event_bookings 
                WHERE booking_number LIKE ? 
                ORDER BY booking_number DESC LIMIT 1";
        $result = $this->db->fetchOne($sql, [$prefix . $date . '%']);
        
        if ($result) {
            $lastNumber = intval(substr($result['booking_number'], -4));
            $newNumber = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
        } else {
            $newNumber = '0001';
        }
        
        return $prefix . $date . $newNumber;
    }
    
    // ==================== SERVICES ====================
    
    public function getServices($category = null) {
        $sql = "SELECT * FROM event_services WHERE is_active = 1";
        $params = [];
        
        if ($category) {
            $sql .= " AND category = ?";
            $params[] = $category;
        }
        
        $sql .= " ORDER BY category, service_name";
        
        return $this->db->fetchAll($sql, $params);
    }
    
    public function getBookingServices($bookingId) {
        return $this->db->fetchAll("
            SELECT ebs.*, es.service_code, es.category
            FROM event_booking_services ebs
            LEFT JOIN event_services es ON ebs.service_id = es.id
            WHERE ebs.booking_id = ?
            ORDER BY ebs.service_category, ebs.service_name
        ", [$bookingId]);
    }
    
    public function addBookingService($bookingId, $data) {
        $sql = "INSERT INTO event_booking_services (
            booking_id, service_id, service_name, service_category,
            quantity, unit_price, subtotal, notes
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        
        $params = [
            $bookingId,
            $data['service_id'] ?? null,
            $data['service_name'],
            $data['service_category'] ?? null,
            $data['quantity'],
            $data['unit_price'],
            $data['subtotal'],
            $data['notes'] ?? null
        ];
        
        return $this->db->execute($sql, $params);
    }
    
    public function removeBookingService($id) {
        return $this->db->execute("DELETE FROM event_booking_services WHERE id = ?", [$id]);
    }
    
    // ==================== PAYMENTS ====================
    
    public function getBookingPayments($bookingId) {
        return $this->db->fetchAll("
            SELECT ep.*, u.full_name as received_by_name
            FROM event_payments ep
            LEFT JOIN users u ON ep.received_by = u.id
            WHERE ep.booking_id = ?
            ORDER BY ep.payment_date DESC
        ", [$bookingId]);
    }
    
    public function recordPayment($bookingId, $data, $userId) {
        $paymentNumber = $this->generatePaymentNumber();
        $paymentDate = $data['payment_date'] ?? date('Y-m-d');
        
        // Create accounting transaction
        $transactionId = null;
        try {
            $booking = $this->getBookingById($bookingId);
            
            $transactionSql = "INSERT INTO transactions (
                transaction_date, transaction_type, category, amount, 
                payment_method, reference_number, description, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            
            $description = "Event Payment - " . $booking['booking_number'] . " - " . $booking['event_title'];
            
            $transactionParams = [
                $paymentDate,
                'income',
                'events_revenue',
                $data['amount'],
                $data['payment_method'],
                $data['reference_number'] ?? $paymentNumber,
                $description,
                $userId
            ];
            
            $transactionId = $this->db->execute($transactionSql, $transactionParams);
        } catch (Exception $e) {
            // Continue even if accounting integration fails
            error_log("Failed to create accounting transaction: " . $e->getMessage());
        }
        
        $sql = "INSERT INTO event_payments (
            booking_id, payment_number, payment_date, payment_type, amount,
            payment_method, reference_number, notes, received_by, transaction_id
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $params = [
            $bookingId,
            $paymentNumber,
            $paymentDate,
            $data['payment_type'],
            $data['amount'],
            $data['payment_method'],
            $data['reference_number'] ?? null,
            $data['notes'] ?? null,
            $userId,
            $transactionId
        ];
        
        $paymentId = $this->db->execute($sql, $params);
        
        $this->updateBookingPaymentStatus($bookingId);
        $this->logActivity($bookingId, 'payment_recorded', 'Payment recorded: ' . $data['amount'], null, null, $userId);
        
        return ['id' => $paymentId, 'payment_number' => $paymentNumber];
    }
    
    private function updateBookingPaymentStatus($bookingId) {
        $booking = $this->getBookingById($bookingId);
        $totalPaid = array_sum(array_column($booking['payments'], 'amount'));
        
        $status = 'unpaid';
        if ($totalPaid >= $booking['total_amount']) {
            $status = 'fully_paid';
        } elseif ($totalPaid >= $booking['deposit_amount'] && $booking['deposit_amount'] > 0) {
            $status = 'deposit_paid';
        } elseif ($totalPaid > 0) {
            $status = 'partially_paid';
        }
        
        $this->db->execute("UPDATE event_bookings SET payment_status = ? WHERE id = ?", [$status, $bookingId]);
    }
    
    private function generatePaymentNumber() {
        $prefix = 'PAY';
        $date = date('Ymd');
        
        $sql = "SELECT payment_number FROM event_payments 
                WHERE payment_number LIKE ? 
                ORDER BY payment_number DESC LIMIT 1";
        $result = $this->db->fetchOne($sql, [$prefix . $date . '%']);
        
        if ($result) {
            $lastNumber = intval(substr($result['payment_number'], -4));
            $newNumber = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
        } else {
            $newNumber = '0001';
        }
        
        return $prefix . $date . $newNumber;
    }
    
    // ==================== SETUP REQUIREMENTS ====================
    
    public function getBookingSetupRequirements($bookingId) {
        return $this->db->fetchAll("
            SELECT esr.*, u.full_name as assigned_to_name
            FROM event_setup_requirements esr
            LEFT JOIN users u ON esr.assigned_to = u.id
            WHERE esr.booking_id = ?
            ORDER BY esr.priority DESC, esr.setup_type
        ", [$bookingId]);
    }
    
    public function addSetupRequirement($bookingId, $data, $userId) {
        $sql = "INSERT INTO event_setup_requirements (
            booking_id, setup_type, description, quantity,
            assigned_to, status, priority, notes
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        
        $params = [
            $bookingId,
            $data['setup_type'],
            $data['description'],
            $data['quantity'] ?? 1,
            $data['assigned_to'] ?? null,
            $data['status'] ?? 'pending',
            $data['priority'] ?? 'medium',
            $data['notes'] ?? null
        ];
        
        return $this->db->execute($sql, $params);
    }
    
    public function updateSetupRequirementStatus($id, $status, $userId) {
        $sql = "UPDATE event_setup_requirements SET status = ?";
        $params = [$status];
        
        if ($status === 'completed') {
            $sql .= ", completed_at = NOW()";
        }
        
        $sql .= " WHERE id = ?";
        $params[] = $id;
        
        return $this->db->execute($sql, $params);
    }
    
    // ==================== ACTIVITY LOG ====================
    
    private function logActivity($bookingId, $action, $description, $oldValue = null, $newValue = null, $userId) {
        $sql = "INSERT INTO event_activity_log (
            booking_id, action, description, old_value, new_value, performed_by, ip_address
        ) VALUES (?, ?, ?, ?, ?, ?, ?)";
        
        $params = [
            $bookingId,
            $action,
            $description,
            $oldValue,
            $newValue,
            $userId,
            $_SERVER['REMOTE_ADDR'] ?? null
        ];
        
        return $this->db->execute($sql, $params);
    }
    
    public function getBookingActivityLog($bookingId) {
        return $this->db->fetchAll("
            SELECT eal.*, u.full_name as performed_by_name
            FROM event_activity_log eal
            LEFT JOIN users u ON eal.performed_by = u.id
            WHERE eal.booking_id = ?
            ORDER BY eal.created_at DESC
        ", [$bookingId]);
    }
    
    // ==================== DASHBOARD & ANALYTICS ====================
    
    public function getDashboardStats($dateFrom = null, $dateTo = null) {
        $dateFrom = $dateFrom ?? date('Y-m-01');
        $dateTo = $dateTo ?? date('Y-m-t');
        
        $stats = [];
        
        $stats['total_bookings'] = $this->db->fetchOne("
            SELECT COUNT(*) as count FROM event_bookings 
            WHERE event_date BETWEEN ? AND ?
        ", [$dateFrom, $dateTo])['count'];
        
        $stats['confirmed_bookings'] = $this->db->fetchOne("
            SELECT COUNT(*) as count FROM event_bookings 
            WHERE event_date BETWEEN ? AND ? AND status = 'confirmed'
        ", [$dateFrom, $dateTo])['count'];
        
        $stats['total_revenue'] = $this->db->fetchOne("
            SELECT COALESCE(SUM(total_amount), 0) as total FROM event_bookings 
            WHERE event_date BETWEEN ? AND ? AND status NOT IN ('cancelled', 'no_show')
        ", [$dateFrom, $dateTo])['total'];
        
        $stats['total_paid'] = $this->db->fetchOne("
            SELECT COALESCE(SUM(ep.amount), 0) as total 
            FROM event_payments ep
            JOIN event_bookings eb ON ep.booking_id = eb.id
            WHERE eb.event_date BETWEEN ? AND ?
        ", [$dateFrom, $dateTo])['total'];
        
        $stats['upcoming_events'] = $this->db->fetchOne("
            SELECT COUNT(*) as count FROM event_bookings 
            WHERE event_date >= CURDATE() AND status IN ('confirmed', 'deposit_paid', 'fully_paid')
        ")['count'];
        
        $stats['bookings_by_type'] = $this->db->fetchAll("
            SELECT et.type_name, COUNT(*) as count, SUM(eb.total_amount) as revenue
            FROM event_bookings eb
            LEFT JOIN event_types et ON eb.event_type_id = et.id
            WHERE eb.event_date BETWEEN ? AND ?
            GROUP BY et.type_name
            ORDER BY count DESC
        ", [$dateFrom, $dateTo]);
        
        $stats['venue_utilization'] = $this->db->fetchAll("
            SELECT ev.venue_name, COUNT(*) as bookings, SUM(eb.total_amount) as revenue
            FROM event_bookings eb
            JOIN event_venues ev ON eb.venue_id = ev.id
            WHERE eb.event_date BETWEEN ? AND ?
            GROUP BY ev.venue_name
            ORDER BY bookings DESC
        ", [$dateFrom, $dateTo]);
        
        return $stats;
    }
    
    public function getUpcomingEvents($limit = 10) {
        return $this->db->fetchAll("
            SELECT eb.*, ev.venue_name, et.type_name
            FROM event_bookings eb
            LEFT JOIN event_venues ev ON eb.venue_id = ev.id
            LEFT JOIN event_types et ON eb.event_type_id = et.id
            WHERE eb.event_date >= CURDATE() 
            AND eb.status IN ('confirmed', 'deposit_paid', 'fully_paid')
            ORDER BY eb.event_date ASC, eb.start_time ASC
            LIMIT ?
        ", [$limit]);
    }
}
