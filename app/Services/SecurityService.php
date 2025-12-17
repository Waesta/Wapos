<?php
namespace App\Services;

use Database;
use Exception;

class SecurityService {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    // ==================== PERSONNEL ====================
    
    public function getPersonnel($filters = []) {
        $sql = "SELECT sp.*, u.username, u.email as user_email
                FROM security_personnel sp
                LEFT JOIN users u ON sp.user_id = u.id
                WHERE 1=1";
        $params = [];
        
        if (!empty($filters['employment_status'])) {
            $sql .= " AND sp.employment_status = ?";
            $params[] = $filters['employment_status'];
        }
        
        if (!empty($filters['search'])) {
            $sql .= " AND (sp.full_name LIKE ? OR sp.employee_number LIKE ?)";
            $search = '%' . $filters['search'] . '%';
            $params[] = $search;
            $params[] = $search;
        }
        
        $sql .= " ORDER BY sp.full_name ASC";
        
        return $this->db->fetchAll($sql, $params);
    }
    
    public function getPersonnelById($id) {
        return $this->db->fetchOne("
            SELECT sp.*, u.username, u.email as user_email, u.role
            FROM security_personnel sp
            LEFT JOIN users u ON sp.user_id = u.id
            WHERE sp.id = ?
        ", [$id]);
    }
    
    public function createPersonnel($data) {
        $sql = "INSERT INTO security_personnel (
            user_id, employee_number, full_name, id_number, phone, email,
            date_of_birth, gender, address, emergency_contact_name, emergency_contact_phone,
            hire_date, employment_status, security_clearance_level, license_number,
            license_expiry_date, uniform_size, photo_path, notes
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $params = [
            $data['user_id'] ?? null,
            $data['employee_number'],
            $data['full_name'],
            $data['id_number'],
            $data['phone'],
            $data['email'] ?? null,
            $data['date_of_birth'] ?? null,
            $data['gender'] ?? null,
            $data['address'] ?? null,
            $data['emergency_contact_name'] ?? null,
            $data['emergency_contact_phone'] ?? null,
            $data['hire_date'],
            $data['employment_status'] ?? 'active',
            $data['security_clearance_level'] ?? 'basic',
            $data['license_number'] ?? null,
            $data['license_expiry_date'] ?? null,
            $data['uniform_size'] ?? null,
            $data['photo_path'] ?? null,
            $data['notes'] ?? null
        ];
        
        return $this->db->execute($sql, $params);
    }
    
    public function updatePersonnel($id, $data) {
        $sql = "UPDATE security_personnel SET
            full_name = ?, id_number = ?, phone = ?, email = ?,
            date_of_birth = ?, gender = ?, address = ?,
            emergency_contact_name = ?, emergency_contact_phone = ?,
            employment_status = ?, security_clearance_level = ?,
            license_number = ?, license_expiry_date = ?, uniform_size = ?,
            photo_path = ?, notes = ?
        WHERE id = ?";
        
        $params = [
            $data['full_name'],
            $data['id_number'],
            $data['phone'],
            $data['email'] ?? null,
            $data['date_of_birth'] ?? null,
            $data['gender'] ?? null,
            $data['address'] ?? null,
            $data['emergency_contact_name'] ?? null,
            $data['emergency_contact_phone'] ?? null,
            $data['employment_status'],
            $data['security_clearance_level'] ?? 'basic',
            $data['license_number'] ?? null,
            $data['license_expiry_date'] ?? null,
            $data['uniform_size'] ?? null,
            $data['photo_path'] ?? null,
            $data['notes'] ?? null,
            $id
        ];
        
        return $this->db->execute($sql, $params);
    }
    
    // ==================== SHIFTS ====================
    
    public function getShifts($activeOnly = true) {
        $sql = "SELECT * FROM security_shifts WHERE 1=1";
        $params = [];
        
        if ($activeOnly) {
            $sql .= " AND is_active = 1";
        }
        
        $sql .= " ORDER BY start_time ASC";
        
        return $this->db->fetchAll($sql, $params);
    }
    
    public function getShiftById($id) {
        return $this->db->fetchOne("SELECT * FROM security_shifts WHERE id = ?", [$id]);
    }
    
    // ==================== POSTS ====================
    
    public function getPosts($activeOnly = true) {
        $sql = "SELECT * FROM security_posts WHERE 1=1";
        $params = [];
        
        if ($activeOnly) {
            $sql .= " AND is_active = 1";
        }
        
        $sql .= " ORDER BY priority DESC, post_name ASC";
        
        return $this->db->fetchAll($sql, $params);
    }
    
    public function getPostById($id) {
        return $this->db->fetchOne("SELECT * FROM security_posts WHERE id = ?", [$id]);
    }
    
    // ==================== SCHEDULE ====================
    
    public function getSchedule($filters = []) {
        $sql = "SELECT ss.*, sp.full_name as personnel_name, sp.phone as personnel_phone,
                spo.post_name, ssh.shift_name, u.full_name as assigned_by_name
                FROM security_schedule ss
                JOIN security_personnel sp ON ss.personnel_id = sp.id
                JOIN security_posts spo ON ss.post_id = spo.id
                JOIN security_shifts ssh ON ss.shift_id = ssh.id
                LEFT JOIN users u ON ss.assigned_by = u.id
                WHERE 1=1";
        $params = [];
        
        if (!empty($filters['date'])) {
            $sql .= " AND ss.schedule_date = ?";
            $params[] = $filters['date'];
        }
        
        if (!empty($filters['date_from'])) {
            $sql .= " AND ss.schedule_date >= ?";
            $params[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $sql .= " AND ss.schedule_date <= ?";
            $params[] = $filters['date_to'];
        }
        
        if (!empty($filters['personnel_id'])) {
            $sql .= " AND ss.personnel_id = ?";
            $params[] = $filters['personnel_id'];
        }
        
        if (!empty($filters['post_id'])) {
            $sql .= " AND ss.post_id = ?";
            $params[] = $filters['post_id'];
        }
        
        if (!empty($filters['status'])) {
            $sql .= " AND ss.status = ?";
            $params[] = $filters['status'];
        }
        
        $sql .= " ORDER BY ss.schedule_date DESC, ss.start_time ASC";
        
        return $this->db->fetchAll($sql, $params);
    }
    
    public function createSchedule($data, $userId) {
        $sql = "INSERT INTO security_schedule (
            personnel_id, post_id, shift_id, schedule_date, start_time, end_time,
            status, notes, assigned_by
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $params = [
            $data['personnel_id'],
            $data['post_id'],
            $data['shift_id'],
            $data['schedule_date'],
            $data['start_time'],
            $data['end_time'],
            $data['status'] ?? 'scheduled',
            $data['notes'] ?? null,
            $userId
        ];
        
        return $this->db->execute($sql, $params);
    }
    
    public function checkIn($scheduleId) {
        $sql = "UPDATE security_schedule SET 
                status = 'in_progress', 
                check_in_time = NOW() 
                WHERE id = ? AND status = 'scheduled'";
        return $this->db->execute($sql, [$scheduleId]);
    }
    
    public function checkOut($scheduleId) {
        $schedule = $this->db->fetchOne("SELECT * FROM security_schedule WHERE id = ?", [$scheduleId]);
        
        if ($schedule && $schedule['check_in_time']) {
            $checkIn = new \DateTime($schedule['check_in_time']);
            $checkOut = new \DateTime();
            $interval = $checkIn->diff($checkOut);
            $actualHours = $interval->h + ($interval->i / 60);
            
            $sql = "UPDATE security_schedule SET 
                    status = 'completed', 
                    check_out_time = NOW(),
                    actual_hours = ?
                    WHERE id = ?";
            return $this->db->execute($sql, [$actualHours, $scheduleId]);
        }
        
        return false;
    }
    
    // ==================== PATROL ROUTES ====================
    
    public function getPatrolRoutes($activeOnly = true) {
        $sql = "SELECT * FROM security_patrol_routes WHERE 1=1";
        $params = [];
        
        if ($activeOnly) {
            $sql .= " AND is_active = 1";
        }
        
        $sql .= " ORDER BY route_name ASC";
        
        return $this->db->fetchAll($sql, $params);
    }
    
    public function getPatrolLogs($filters = []) {
        $sql = "SELECT spl.*, sp.full_name as personnel_name, spr.route_name
                FROM security_patrol_logs spl
                JOIN security_personnel sp ON spl.personnel_id = sp.id
                JOIN security_patrol_routes spr ON spl.route_id = spr.id
                WHERE 1=1";
        $params = [];
        
        if (!empty($filters['personnel_id'])) {
            $sql .= " AND spl.personnel_id = ?";
            $params[] = $filters['personnel_id'];
        }
        
        if (!empty($filters['date'])) {
            $sql .= " AND DATE(spl.patrol_start_time) = ?";
            $params[] = $filters['date'];
        }
        
        $sql .= " ORDER BY spl.patrol_start_time DESC";
        
        return $this->db->fetchAll($sql, $params);
    }
    
    public function startPatrol($scheduleId, $routeId, $personnelId) {
        $route = $this->db->fetchOne("SELECT * FROM security_patrol_routes WHERE id = ?", [$routeId]);
        
        $sql = "INSERT INTO security_patrol_logs (
            schedule_id, route_id, personnel_id, patrol_start_time,
            total_checkpoints, status
        ) VALUES (?, ?, ?, NOW(), ?, 'in_progress')";
        
        $checkpoints = json_decode($route['checkpoints'], true);
        $totalCheckpoints = count($checkpoints);
        
        return $this->db->execute($sql, [$scheduleId, $routeId, $personnelId, $totalCheckpoints]);
    }
    
    public function completePatrol($patrolLogId, $checkpointsCompleted, $observations = null) {
        $sql = "UPDATE security_patrol_logs SET
                patrol_end_time = NOW(),
                checkpoints_completed = ?,
                completed_checkpoints = ?,
                status = 'completed',
                observations = ?
                WHERE id = ?";
        
        return $this->db->execute($sql, [$checkpointsCompleted, count($checkpointsCompleted), $observations, $patrolLogId]);
    }
    
    // ==================== INCIDENTS ====================
    
    public function getIncidents($filters = []) {
        $sql = "SELECT si.*, sp.full_name as reported_by_name,
                u.full_name as resolved_by_name
                FROM security_incidents si
                LEFT JOIN security_personnel sp ON si.reported_by_personnel_id = sp.id
                LEFT JOIN users u ON si.resolved_by = u.id
                WHERE 1=1";
        $params = [];
        
        if (!empty($filters['status'])) {
            $sql .= " AND si.status = ?";
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['severity'])) {
            $sql .= " AND si.severity = ?";
            $params[] = $filters['severity'];
        }
        
        if (!empty($filters['incident_type'])) {
            $sql .= " AND si.incident_type = ?";
            $params[] = $filters['incident_type'];
        }
        
        if (!empty($filters['date_from'])) {
            $sql .= " AND si.incident_date >= ?";
            $params[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $sql .= " AND si.incident_date <= ?";
            $params[] = $filters['date_to'];
        }
        
        if (!empty($filters['search'])) {
            $sql .= " AND (si.incident_number LIKE ? OR si.description LIKE ?)";
            $search = '%' . $filters['search'] . '%';
            $params[] = $search;
            $params[] = $search;
        }
        
        $sql .= " ORDER BY si.incident_date DESC, si.incident_time DESC";
        
        return $this->db->fetchAll($sql, $params);
    }
    
    public function getIncidentById($id) {
        return $this->db->fetchOne("
            SELECT si.*, sp.full_name as reported_by_name,
            u.full_name as resolved_by_name
            FROM security_incidents si
            LEFT JOIN security_personnel sp ON si.reported_by_personnel_id = sp.id
            LEFT JOIN users u ON si.resolved_by = u.id
            WHERE si.id = ?
        ", [$id]);
    }
    
    public function createIncident($data, $userId) {
        $incidentNumber = $this->generateIncidentNumber();
        
        $sql = "INSERT INTO security_incidents (
            incident_number, incident_type, severity, incident_date, incident_time,
            location, description, reported_by_personnel_id, reported_by_name, reported_by_phone,
            witnesses, involved_parties, action_taken, police_notified, police_report_number,
            police_officer_name, ambulance_called, fire_department_called, property_damage,
            estimated_damage_value, injuries_reported, injury_details, evidence_collected,
            status, created_by
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $params = [
            $incidentNumber,
            $data['incident_type'],
            $data['severity'],
            $data['incident_date'],
            $data['incident_time'],
            $data['location'],
            $data['description'],
            $data['reported_by_personnel_id'] ?? null,
            $data['reported_by_name'] ?? null,
            $data['reported_by_phone'] ?? null,
            !empty($data['witnesses']) ? json_encode($data['witnesses']) : null,
            !empty($data['involved_parties']) ? json_encode($data['involved_parties']) : null,
            $data['action_taken'] ?? null,
            $data['police_notified'] ?? false,
            $data['police_report_number'] ?? null,
            $data['police_officer_name'] ?? null,
            $data['ambulance_called'] ?? false,
            $data['fire_department_called'] ?? false,
            $data['property_damage'] ?? false,
            $data['estimated_damage_value'] ?? null,
            $data['injuries_reported'] ?? false,
            $data['injury_details'] ?? null,
            !empty($data['evidence_collected']) ? json_encode($data['evidence_collected']) : null,
            $data['status'] ?? 'open',
            $userId
        ];
        
        $incidentId = $this->db->execute($sql, $params);
        
        return ['id' => $incidentId, 'incident_number' => $incidentNumber];
    }
    
    public function updateIncident($id, $data) {
        $sql = "UPDATE security_incidents SET
            incident_type = ?, severity = ?, incident_date = ?, incident_time = ?,
            location = ?, description = ?, action_taken = ?, police_notified = ?,
            police_report_number = ?, police_officer_name = ?, ambulance_called = ?,
            fire_department_called = ?, property_damage = ?, estimated_damage_value = ?,
            injuries_reported = ?, injury_details = ?, status = ?
        WHERE id = ?";
        
        $params = [
            $data['incident_type'],
            $data['severity'],
            $data['incident_date'],
            $data['incident_time'],
            $data['location'],
            $data['description'],
            $data['action_taken'] ?? null,
            $data['police_notified'] ?? false,
            $data['police_report_number'] ?? null,
            $data['police_officer_name'] ?? null,
            $data['ambulance_called'] ?? false,
            $data['fire_department_called'] ?? false,
            $data['property_damage'] ?? false,
            $data['estimated_damage_value'] ?? null,
            $data['injuries_reported'] ?? false,
            $data['injury_details'] ?? null,
            $data['status'],
            $id
        ];
        
        return $this->db->execute($sql, $params);
    }
    
    public function resolveIncident($id, $resolution, $userId) {
        $sql = "UPDATE security_incidents SET
                status = 'resolved',
                resolution = ?,
                resolved_by = ?,
                resolved_at = NOW()
                WHERE id = ?";
        
        return $this->db->execute($sql, [$resolution, $userId, $id]);
    }
    
    private function generateIncidentNumber() {
        $prefix = 'INC';
        $date = date('Ymd');
        
        $sql = "SELECT incident_number FROM security_incidents 
                WHERE incident_number LIKE ? 
                ORDER BY incident_number DESC LIMIT 1";
        $result = $this->db->fetchOne($sql, [$prefix . $date . '%']);
        
        if ($result) {
            $lastNumber = intval(substr($result['incident_number'], -4));
            $newNumber = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
        } else {
            $newNumber = '0001';
        }
        
        return $prefix . $date . $newNumber;
    }
    
    // ==================== VISITOR LOG ====================
    
    public function getVisitorLog($filters = []) {
        $sql = "SELECT svl.*, sp1.full_name as entry_personnel_name,
                sp2.full_name as exit_personnel_name,
                spo1.post_name as entry_post_name,
                spo2.post_name as exit_post_name
                FROM security_visitor_log svl
                LEFT JOIN security_personnel sp1 ON svl.entry_personnel_id = sp1.id
                LEFT JOIN security_personnel sp2 ON svl.exit_personnel_id = sp2.id
                LEFT JOIN security_posts spo1 ON svl.entry_post_id = spo1.id
                LEFT JOIN security_posts spo2 ON svl.exit_post_id = spo2.id
                WHERE 1=1";
        $params = [];
        
        if (!empty($filters['date'])) {
            $sql .= " AND svl.entry_date = ?";
            $params[] = $filters['date'];
        }
        
        if (!empty($filters['still_inside'])) {
            $sql .= " AND svl.exit_date IS NULL";
        }
        
        if (!empty($filters['search'])) {
            $sql .= " AND (svl.visitor_name LIKE ? OR svl.host_name LIKE ?)";
            $search = '%' . $filters['search'] . '%';
            $params[] = $search;
            $params[] = $search;
        }
        
        $sql .= " ORDER BY svl.entry_date DESC, svl.entry_time DESC";
        
        return $this->db->fetchAll($sql, $params);
    }
    
    public function logVisitorEntry($data, $personnelId, $postId) {
        $sql = "INSERT INTO security_visitor_log (
            visitor_name, visitor_id_type, visitor_id_number, visitor_phone,
            visitor_company, visit_purpose, host_name, host_department, host_phone,
            entry_date, entry_time, vehicle_registration, items_brought_in,
            badge_number, entry_post_id, entry_personnel_id, visitor_photo_path, notes
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $params = [
            $data['visitor_name'],
            $data['visitor_id_type'],
            $data['visitor_id_number'],
            $data['visitor_phone'] ?? null,
            $data['visitor_company'] ?? null,
            $data['visit_purpose'],
            $data['host_name'],
            $data['host_department'] ?? null,
            $data['host_phone'] ?? null,
            date('Y-m-d'),
            date('H:i:s'),
            $data['vehicle_registration'] ?? null,
            $data['items_brought_in'] ?? null,
            $data['badge_number'] ?? null,
            $postId,
            $personnelId,
            $data['visitor_photo_path'] ?? null,
            $data['notes'] ?? null
        ];
        
        return $this->db->execute($sql, $params);
    }
    
    public function logVisitorExit($visitorLogId, $personnelId, $postId, $itemsTakenOut = null) {
        $sql = "UPDATE security_visitor_log SET
                exit_date = ?,
                exit_time = ?,
                items_taken_out = ?,
                exit_post_id = ?,
                exit_personnel_id = ?
                WHERE id = ?";
        
        return $this->db->execute($sql, [
            date('Y-m-d'),
            date('H:i:s'),
            $itemsTakenOut,
            $postId,
            $personnelId,
            $visitorLogId
        ]);
    }
    
    // ==================== DASHBOARD & ANALYTICS ====================
    
    public function getDashboardStats($date = null) {
        $date = $date ?? date('Y-m-d');
        
        $stats = [];
        
        $stats['scheduled_today'] = $this->db->fetchOne("
            SELECT COUNT(*) as count FROM security_schedule 
            WHERE schedule_date = ? AND status IN ('scheduled', 'confirmed')
        ", [$date])['count'];
        
        $stats['on_duty'] = $this->db->fetchOne("
            SELECT COUNT(*) as count FROM security_schedule 
            WHERE schedule_date = ? AND status = 'in_progress'
        ", [$date])['count'];
        
        $stats['active_personnel'] = $this->db->fetchOne("
            SELECT COUNT(*) as count FROM security_personnel 
            WHERE employment_status = 'active'
        ")['count'];
        
        $stats['open_incidents'] = $this->db->fetchOne("
            SELECT COUNT(*) as count FROM security_incidents 
            WHERE status IN ('open', 'under_investigation')
        ")['count'];
        
        $stats['visitors_inside'] = $this->db->fetchOne("
            SELECT COUNT(*) as count FROM security_visitor_log 
            WHERE entry_date = ? AND exit_date IS NULL
        ", [$date])['count'];
        
        $stats['recent_incidents'] = $this->db->fetchAll("
            SELECT * FROM security_incidents 
            WHERE incident_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
            ORDER BY incident_date DESC, incident_time DESC
            LIMIT 5
        ");
        
        $stats['incidents_by_type'] = $this->db->fetchAll("
            SELECT incident_type, COUNT(*) as count
            FROM security_incidents
            WHERE incident_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
            GROUP BY incident_type
            ORDER BY count DESC
        ");
        
        return $stats;
    }
}
