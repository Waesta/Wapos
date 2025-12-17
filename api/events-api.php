<?php
/**
 * Events & Banquet Management API
 * Handles all event booking, venue, and service operations
 */

require_once __DIR__ . '/../includes/bootstrap.php';

use App\Services\EventsService;

header('Content-Type: application/json');

$auth->requireRole(['admin', 'manager', 'frontdesk']);

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$eventsService = new EventsService();
$userId = $_SESSION['user_id'];

try {
    switch ($action) {
        
        // ==================== VENUES ====================
        
        case 'get_venues':
            $filters = [
                'is_active' => $_GET['is_active'] ?? null,
                'venue_type' => $_GET['venue_type'] ?? null,
                'min_capacity' => $_GET['min_capacity'] ?? null
            ];
            $venues = $eventsService->getVenues($filters);
            echo json_encode(['success' => true, 'data' => $venues]);
            break;
            
        case 'get_venue':
            $id = $_GET['id'] ?? null;
            if (!$id) throw new Exception('Venue ID required');
            
            $venue = $eventsService->getVenueById($id);
            echo json_encode(['success' => true, 'data' => $venue]);
            break;
            
        case 'create_venue':
            validateCSRFToken();
            $data = json_decode(file_get_contents('php://input'), true);
            
            $result = $eventsService->createVenue($data);
            echo json_encode(['success' => true, 'message' => 'Venue created successfully', 'id' => $result]);
            break;
            
        case 'update_venue':
            validateCSRFToken();
            $data = json_decode(file_get_contents('php://input'), true);
            $id = $data['id'] ?? null;
            if (!$id) throw new Exception('Venue ID required');
            
            $eventsService->updateVenue($id, $data);
            echo json_encode(['success' => true, 'message' => 'Venue updated successfully']);
            break;
            
        case 'check_venue_availability':
            $venueId = $_GET['venue_id'] ?? null;
            $date = $_GET['date'] ?? null;
            $startTime = $_GET['start_time'] ?? null;
            $endTime = $_GET['end_time'] ?? null;
            $excludeBookingId = $_GET['exclude_booking_id'] ?? null;
            
            if (!$venueId || !$date || !$startTime || !$endTime) {
                throw new Exception('Venue ID, date, start time, and end time required');
            }
            
            $available = $eventsService->checkVenueAvailability($venueId, $date, $startTime, $endTime, $excludeBookingId);
            echo json_encode(['success' => true, 'available' => $available]);
            break;
            
        // ==================== EVENT TYPES ====================
        
        case 'get_event_types':
            $eventTypes = $eventsService->getEventTypes();
            echo json_encode(['success' => true, 'data' => $eventTypes]);
            break;
            
        case 'get_event_type':
            $id = $_GET['id'] ?? null;
            if (!$id) throw new Exception('Event type ID required');
            
            $eventType = $eventsService->getEventTypeById($id);
            echo json_encode(['success' => true, 'data' => $eventType]);
            break;
            
        // ==================== BOOKINGS ====================
        
        case 'get_bookings':
            $filters = [
                'status' => $_GET['status'] ?? null,
                'venue_id' => $_GET['venue_id'] ?? null,
                'date_from' => $_GET['date_from'] ?? null,
                'date_to' => $_GET['date_to'] ?? null,
                'search' => $_GET['search'] ?? null,
                'limit' => $_GET['limit'] ?? null
            ];
            $bookings = $eventsService->getBookings($filters);
            echo json_encode(['success' => true, 'data' => $bookings]);
            break;
            
        case 'get_booking':
            $id = $_GET['id'] ?? null;
            if (!$id) throw new Exception('Booking ID required');
            
            $booking = $eventsService->getBookingById($id);
            echo json_encode(['success' => true, 'data' => $booking]);
            break;
            
        case 'create_booking':
            validateCSRFToken();
            $data = json_decode(file_get_contents('php://input'), true);
            
            $result = $eventsService->createBooking($data, $userId);
            echo json_encode([
                'success' => true, 
                'message' => 'Booking created successfully',
                'data' => $result
            ]);
            break;
            
        case 'update_booking':
            validateCSRFToken();
            $data = json_decode(file_get_contents('php://input'), true);
            $id = $data['id'] ?? null;
            if (!$id) throw new Exception('Booking ID required');
            
            $eventsService->updateBooking($id, $data, $userId);
            echo json_encode(['success' => true, 'message' => 'Booking updated successfully']);
            break;
            
        case 'confirm_booking':
            validateCSRFToken();
            $data = json_decode(file_get_contents('php://input'), true);
            $id = $data['id'] ?? null;
            if (!$id) throw new Exception('Booking ID required');
            
            $eventsService->confirmBooking($id, $userId);
            echo json_encode(['success' => true, 'message' => 'Booking confirmed successfully']);
            break;
            
        case 'cancel_booking':
            validateCSRFToken();
            $data = json_decode(file_get_contents('php://input'), true);
            $id = $data['id'] ?? null;
            $reason = $data['reason'] ?? '';
            if (!$id) throw new Exception('Booking ID required');
            
            $eventsService->cancelBooking($id, $reason, $userId);
            echo json_encode(['success' => true, 'message' => 'Booking cancelled successfully']);
            break;
            
        // ==================== SERVICES ====================
        
        case 'get_services':
            $category = $_GET['category'] ?? null;
            $services = $eventsService->getServices($category);
            echo json_encode(['success' => true, 'data' => $services]);
            break;
            
        case 'get_booking_services':
            $bookingId = $_GET['booking_id'] ?? null;
            if (!$bookingId) throw new Exception('Booking ID required');
            
            $services = $eventsService->getBookingServices($bookingId);
            echo json_encode(['success' => true, 'data' => $services]);
            break;
            
        case 'add_booking_service':
            validateCSRFToken();
            $data = json_decode(file_get_contents('php://input'), true);
            $bookingId = $data['booking_id'] ?? null;
            if (!$bookingId) throw new Exception('Booking ID required');
            
            // Get service details if service_id is provided
            if (!empty($data['service_id'])) {
                $service = $eventsService->getServices()[0] ?? null;
                $serviceDetails = null;
                foreach ($eventsService->getServices() as $s) {
                    if ($s['id'] == $data['service_id']) {
                        $serviceDetails = $s;
                        break;
                    }
                }
                if ($serviceDetails) {
                    $data['service_name'] = $serviceDetails['service_name'];
                    $data['service_category'] = $serviceDetails['category'];
                }
            }
            
            // Calculate subtotal
            $data['subtotal'] = ($data['quantity'] ?? 1) * ($data['unit_price'] ?? 0);
            
            $result = $eventsService->addBookingService($bookingId, $data);
            echo json_encode(['success' => true, 'message' => 'Service added successfully', 'id' => $result]);
            break;
            
        case 'remove_booking_service':
            validateCSRFToken();
            $data = json_decode(file_get_contents('php://input'), true);
            $id = $data['id'] ?? null;
            if (!$id) throw new Exception('Service ID required');
            
            $eventsService->removeBookingService($id);
            echo json_encode(['success' => true, 'message' => 'Service removed successfully']);
            break;
            
        // ==================== PAYMENTS ====================
        
        case 'get_booking_payments':
            $bookingId = $_GET['booking_id'] ?? null;
            if (!$bookingId) throw new Exception('Booking ID required');
            
            $payments = $eventsService->getBookingPayments($bookingId);
            echo json_encode(['success' => true, 'data' => $payments]);
            break;
            
        case 'record_payment':
            validateCSRFToken();
            $data = json_decode(file_get_contents('php://input'), true);
            $bookingId = $data['booking_id'] ?? null;
            if (!$bookingId) throw new Exception('Booking ID required');
            
            $result = $eventsService->recordPayment($bookingId, $data, $userId);
            echo json_encode([
                'success' => true, 
                'message' => 'Payment recorded successfully',
                'data' => $result
            ]);
            break;
            
        // ==================== SETUP REQUIREMENTS ====================
        
        case 'get_setup_requirements':
            $bookingId = $_GET['booking_id'] ?? null;
            if (!$bookingId) throw new Exception('Booking ID required');
            
            $requirements = $eventsService->getBookingSetupRequirements($bookingId);
            echo json_encode(['success' => true, 'data' => $requirements]);
            break;
            
        case 'add_setup_requirement':
            validateCSRFToken();
            $data = json_decode(file_get_contents('php://input'), true);
            $bookingId = $data['booking_id'] ?? null;
            if (!$bookingId) throw new Exception('Booking ID required');
            
            $result = $eventsService->addSetupRequirement($bookingId, $data, $userId);
            echo json_encode(['success' => true, 'message' => 'Setup requirement added successfully', 'id' => $result]);
            break;
            
        case 'update_setup_status':
            validateCSRFToken();
            $data = json_decode(file_get_contents('php://input'), true);
            $id = $data['id'] ?? null;
            $status = $data['status'] ?? null;
            if (!$id || !$status) throw new Exception('Setup requirement ID and status required');
            
            $eventsService->updateSetupRequirementStatus($id, $status, $userId);
            echo json_encode(['success' => true, 'message' => 'Setup status updated successfully']);
            break;
            
        // ==================== ACTIVITY LOG ====================
        
        case 'get_activity_log':
            $bookingId = $_GET['booking_id'] ?? null;
            if (!$bookingId) throw new Exception('Booking ID required');
            
            $log = $eventsService->getBookingActivityLog($bookingId);
            echo json_encode(['success' => true, 'data' => $log]);
            break;
            
        // ==================== DASHBOARD & ANALYTICS ====================
        
        case 'get_dashboard_stats':
            $dateFrom = $_GET['date_from'] ?? null;
            $dateTo = $_GET['date_to'] ?? null;
            
            $stats = $eventsService->getDashboardStats($dateFrom, $dateTo);
            echo json_encode(['success' => true, 'data' => $stats]);
            break;
            
        case 'get_upcoming_events':
            $limit = $_GET['limit'] ?? 10;
            
            $events = $eventsService->getUpcomingEvents($limit);
            echo json_encode(['success' => true, 'data' => $events]);
            break;
            
        default:
            throw new Exception('Invalid action');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
