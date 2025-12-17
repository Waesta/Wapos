<?php
require_once 'includes/bootstrap.php';

$auth->requireRole(['admin', 'manager', 'security_manager', 'security_staff']);

$pageTitle = 'Security Management';
require_once 'includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="bi bi-shield-check me-2"></i>Security Management</h2>
        <div>
            <button class="btn btn-danger" onclick="showIncidentModal()">
                <i class="bi bi-exclamation-triangle me-1"></i>Report Incident
            </button>
            <button class="btn btn-primary" onclick="showScheduleModal()">
                <i class="bi bi-calendar-plus me-1"></i>Schedule Shift
            </button>
        </div>
    </div>

    <!-- Dashboard Stats -->
    <div class="row mb-4" id="dashboardStats">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <h6 class="card-subtitle mb-2">Scheduled Today</h6>
                    <h3 class="card-title mb-0" id="statScheduledToday">0</h3>
                    <small>Guards on Duty</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h6 class="card-subtitle mb-2">On Duty Now</h6>
                    <h3 class="card-title mb-0" id="statOnDuty">0</h3>
                    <small>Checked In</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-white">
                <div class="card-body">
                    <h6 class="card-subtitle mb-2">Open Incidents</h6>
                    <h3 class="card-title mb-0" id="statOpenIncidents">0</h3>
                    <small>Requires Action</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <h6 class="card-subtitle mb-2">Visitors Inside</h6>
                    <h3 class="card-title mb-0" id="statVisitorsInside">0</h3>
                    <small>Not Exited</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabs -->
    <ul class="nav nav-tabs mb-3" role="tablist">
        <li class="nav-item">
            <a class="nav-link active" data-bs-toggle="tab" href="#tabSchedule">Schedule</a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-bs-toggle="tab" href="#tabIncidents">Incidents</a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-bs-toggle="tab" href="#tabPatrols">Patrols</a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-bs-toggle="tab" href="#tabVisitors">Visitor Log</a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-bs-toggle="tab" href="#tabPersonnel">Personnel</a>
        </li>
    </ul>

    <div class="tab-content">
        <!-- Schedule Tab -->
        <div class="tab-pane fade show active" id="tabSchedule">
            <div class="card">
                <div class="card-header">
                    <div class="row align-items-center">
                        <div class="col-md-6">
                            <h5 class="mb-0">Today's Schedule</h5>
                        </div>
                        <div class="col-md-6 text-end">
                            <input type="date" class="form-control form-control-sm d-inline-block w-auto" id="scheduleDate" onchange="loadSchedule()">
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Personnel</th>
                                    <th>Post</th>
                                    <th>Shift</th>
                                    <th>Time</th>
                                    <th>Status</th>
                                    <th>Check In/Out</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="scheduleTableBody">
                                <tr>
                                    <td colspan="7" class="text-center">
                                        <div class="spinner-border text-primary" role="status"></div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Incidents Tab -->
        <div class="tab-pane fade" id="tabIncidents">
            <div class="card">
                <div class="card-header">
                    <div class="row align-items-center">
                        <div class="col-md-6">
                            <h5 class="mb-0">Security Incidents</h5>
                        </div>
                        <div class="col-md-6">
                            <div class="row g-2">
                                <div class="col-md-6">
                                    <select class="form-select form-select-sm" id="filterIncidentStatus" onchange="loadIncidents()">
                                        <option value="">All Statuses</option>
                                        <option value="open">Open</option>
                                        <option value="under_investigation">Under Investigation</option>
                                        <option value="resolved">Resolved</option>
                                        <option value="closed">Closed</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <select class="form-select form-select-sm" id="filterIncidentSeverity" onchange="loadIncidents()">
                                        <option value="">All Severities</option>
                                        <option value="low">Low</option>
                                        <option value="medium">Medium</option>
                                        <option value="high">High</option>
                                        <option value="critical">Critical</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Incident #</th>
                                    <th>Type</th>
                                    <th>Severity</th>
                                    <th>Date/Time</th>
                                    <th>Location</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="incidentsTableBody">
                                <tr>
                                    <td colspan="7" class="text-center">
                                        <div class="spinner-border text-primary" role="status"></div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Patrols Tab -->
        <div class="tab-pane fade" id="tabPatrols">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Patrol Logs</h5>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <button class="btn btn-primary" onclick="showStartPatrolModal()">
                                <i class="bi bi-play-circle"></i> Start Patrol
                            </button>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Route</th>
                                    <th>Personnel</th>
                                    <th>Start Time</th>
                                    <th>End Time</th>
                                    <th>Checkpoints</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="patrolsTableBody">
                                <tr>
                                    <td colspan="7" class="text-center text-muted">No patrol logs</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Visitor Log Tab -->
        <div class="tab-pane fade" id="tabVisitors">
            <div class="card">
                <div class="card-header">
                    <div class="row align-items-center">
                        <div class="col-md-6">
                            <h5 class="mb-0">Visitor Log</h5>
                        </div>
                        <div class="col-md-6 text-end">
                            <button class="btn btn-sm btn-primary" onclick="showVisitorEntryModal()">
                                <i class="bi bi-person-plus"></i> Log Entry
                            </button>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Visitor Name</th>
                                    <th>ID Type/Number</th>
                                    <th>Host</th>
                                    <th>Purpose</th>
                                    <th>Entry Time</th>
                                    <th>Exit Time</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="visitorsTableBody">
                                <tr>
                                    <td colspan="7" class="text-center">
                                        <div class="spinner-border text-primary" role="status"></div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Personnel Tab -->
        <div class="tab-pane fade" id="tabPersonnel">
            <div class="card">
                <div class="card-header">
                    <div class="row align-items-center">
                        <div class="col-md-6">
                            <h5 class="mb-0">Security Personnel</h5>
                        </div>
                        <div class="col-md-6 text-end">
                            <?php if ($auth->hasRole(['admin', 'manager', 'security_manager'])): ?>
                            <button class="btn btn-sm btn-primary" onclick="showPersonnelModal()">
                                <i class="bi bi-person-plus"></i> Add Personnel
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Employee #</th>
                                    <th>Full Name</th>
                                    <th>Phone</th>
                                    <th>Clearance Level</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="personnelTableBody">
                                <tr>
                                    <td colspan="6" class="text-center">
                                        <div class="spinner-border text-primary" role="status"></div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Incident Report Modal -->
<div class="modal fade" id="incidentModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">Report Security Incident</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="incidentForm">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Incident Type *</label>
                            <select class="form-select" name="incident_type" required>
                                <option value="">Select Type</option>
                                <option value="theft">Theft</option>
                                <option value="vandalism">Vandalism</option>
                                <option value="trespassing">Trespassing</option>
                                <option value="assault">Assault</option>
                                <option value="fire">Fire</option>
                                <option value="medical_emergency">Medical Emergency</option>
                                <option value="suspicious_activity">Suspicious Activity</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Severity *</label>
                            <select class="form-select" name="severity" required>
                                <option value="low">Low</option>
                                <option value="medium">Medium</option>
                                <option value="high">High</option>
                                <option value="critical">Critical</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Incident Date *</label>
                            <input type="date" class="form-control" name="incident_date" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Incident Time *</label>
                            <input type="time" class="form-control" name="incident_time" required>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">Location *</label>
                            <input type="text" class="form-control" name="location" required placeholder="e.g., Main Gate, Parking Lot B">
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">Description *</label>
                            <textarea class="form-control" name="description" rows="4" required placeholder="Detailed description of the incident"></textarea>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">Action Taken</label>
                            <textarea class="form-control" name="action_taken" rows="2" placeholder="Immediate actions taken"></textarea>
                        </div>
                        <div class="col-md-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="police_notified" id="policeNotified">
                                <label class="form-check-label" for="policeNotified">Police Notified</label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="ambulance_called" id="ambulanceCalled">
                                <label class="form-check-label" for="ambulanceCalled">Ambulance Called</label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="property_damage" id="propertyDamage">
                                <label class="form-check-label" for="propertyDamage">Property Damage</label>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" onclick="submitIncident()">Submit Report</button>
            </div>
        </div>
    </div>
</div>

<!-- Schedule Modal -->
<div class="modal fade" id="scheduleModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Schedule Shift</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="scheduleForm">
                    <div class="mb-3">
                        <label class="form-label">Personnel *</label>
                        <select class="form-select" name="personnel_id" id="schedulePersonnelId" required>
                            <option value="">Select Personnel</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Post *</label>
                        <select class="form-select" name="post_id" id="schedulePostId" required>
                            <option value="">Select Post</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Shift *</label>
                        <select class="form-select" name="shift_id" id="scheduleShiftId" required>
                            <option value="">Select Shift</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Date *</label>
                        <input type="date" class="form-control" name="schedule_date" required>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <label class="form-label">Start Time *</label>
                            <input type="time" class="form-control" name="start_time" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">End Time *</label>
                            <input type="time" class="form-control" name="end_time" required>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="submitSchedule()">Create Schedule</button>
            </div>
        </div>
    </div>
</div>

<!-- Start Patrol Modal -->
<div class="modal fade" id="startPatrolModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Start Patrol</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="startPatrolForm">
                    <div class="mb-3">
                        <label class="form-label">Patrol Route *</label>
                        <select class="form-select" name="route_id" id="patrolRouteId" required>
                            <option value="">Select Route</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Personnel *</label>
                        <select class="form-select" name="personnel_id" id="patrolPersonnelId" required>
                            <option value="">Select Personnel</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Schedule (Optional)</label>
                        <select class="form-select" name="schedule_id" id="patrolScheduleId">
                            <option value="">No linked schedule</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea class="form-control" name="notes" rows="2"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" onclick="submitStartPatrol()">Start Patrol</button>
            </div>
        </div>
    </div>
</div>

<!-- Visitor Entry Modal -->
<div class="modal fade" id="visitorEntryModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Log Visitor Entry</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="visitorEntryForm">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Visitor Name *</label>
                            <input type="text" class="form-control" name="visitor_name" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">ID Type *</label>
                            <select class="form-select" name="visitor_id_type" required>
                                <option value="national_id">National ID</option>
                                <option value="passport">Passport</option>
                                <option value="driving_license">Driving License</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">ID Number *</label>
                            <input type="text" class="form-control" name="visitor_id_number" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Phone</label>
                            <input type="tel" class="form-control" name="visitor_phone">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Company</label>
                            <input type="text" class="form-control" name="visitor_company">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Host Name *</label>
                            <input type="text" class="form-control" name="host_name" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Host Phone</label>
                            <input type="tel" class="form-control" name="host_phone">
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">Visit Purpose *</label>
                            <input type="text" class="form-control" name="visit_purpose" required placeholder="e.g., Business meeting, Delivery">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Vehicle Registration</label>
                            <input type="text" class="form-control" name="vehicle_registration">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Badge Number</label>
                            <input type="text" class="form-control" name="badge_number">
                        </div>
                    </div>
                    <input type="hidden" name="personnel_id" id="visitorPersonnelId">
                    <input type="hidden" name="post_id" id="visitorPostId" value="1">
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="submitVisitorEntry()">Log Entry</button>
            </div>
        </div>
    </div>
</div>

<script>
const csrfToken = '<?php echo generateCSRFToken(); ?>';

document.addEventListener('DOMContentLoaded', function() {
    loadDashboardStats();
    loadSchedule();
    loadIncidents();
    loadVisitors();
    loadPersonnel();
    loadDropdowns();
    
    document.getElementById('scheduleDate').valueAsDate = new Date();
});

function loadDashboardStats() {
    fetch('api/security-api.php?action=get_dashboard_stats')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('statScheduledToday').textContent = data.data.scheduled_today || 0;
                document.getElementById('statOnDuty').textContent = data.data.on_duty || 0;
                document.getElementById('statOpenIncidents').textContent = data.data.open_incidents || 0;
                document.getElementById('statVisitorsInside').textContent = data.data.visitors_inside || 0;
            }
        });
}

function loadSchedule() {
    const date = document.getElementById('scheduleDate').value || new Date().toISOString().split('T')[0];
    
    fetch(`api/security-api.php?action=get_schedule&date=${date}`)
        .then(response => response.json())
        .then(data => {
            const tbody = document.getElementById('scheduleTableBody');
            if (data.success && data.data.length > 0) {
                tbody.innerHTML = data.data.map(schedule => `
                    <tr>
                        <td>${schedule.personnel_name}</td>
                        <td>${schedule.post_name}</td>
                        <td>${schedule.shift_name}</td>
                        <td>${schedule.start_time} - ${schedule.end_time}</td>
                        <td><span class="badge bg-${getScheduleStatusColor(schedule.status)}">${schedule.status}</span></td>
                        <td>
                            ${schedule.check_in_time || 'Not checked in'}<br>
                            ${schedule.check_out_time ? '<small>' + schedule.check_out_time + '</small>' : ''}
                        </td>
                        <td>
                            ${schedule.status === 'scheduled' ? `<button class="btn btn-sm btn-success" onclick="checkIn(${schedule.id})">Check In</button>` : ''}
                            ${schedule.status === 'in_progress' ? `<button class="btn btn-sm btn-warning" onclick="checkOut(${schedule.id})">Check Out</button>` : ''}
                        </td>
                    </tr>
                `).join('');
            } else {
                tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted">No schedules for this date</td></tr>';
            }
        });
}

function loadIncidents() {
    const status = document.getElementById('filterIncidentStatus').value;
    const severity = document.getElementById('filterIncidentSeverity').value;
    
    const params = new URLSearchParams();
    if (status) params.append('status', status);
    if (severity) params.append('severity', severity);
    
    fetch(`api/security-api.php?action=get_incidents&${params}`)
        .then(response => response.json())
        .then(data => {
            const tbody = document.getElementById('incidentsTableBody');
            if (data.success && data.data.length > 0) {
                tbody.innerHTML = data.data.map(incident => `
                    <tr>
                        <td><strong>${incident.incident_number}</strong></td>
                        <td>${incident.incident_type.replace('_', ' ')}</td>
                        <td><span class="badge bg-${getSeverityColor(incident.severity)}">${incident.severity}</span></td>
                        <td>${incident.incident_date}<br><small>${incident.incident_time}</small></td>
                        <td>${incident.location}</td>
                        <td><span class="badge bg-${getIncidentStatusColor(incident.status)}">${incident.status}</span></td>
                        <td>
                            <button class="btn btn-sm btn-outline-primary" onclick="viewIncident(${incident.id})">
                                <i class="bi bi-eye"></i>
                            </button>
                        </td>
                    </tr>
                `).join('');
            } else {
                tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted">No incidents found</td></tr>';
            }
        });
}

function loadVisitors() {
    const date = new Date().toISOString().split('T')[0];
    
    fetch(`api/security-api.php?action=get_visitor_log&date=${date}`)
        .then(response => response.json())
        .then(data => {
            const tbody = document.getElementById('visitorsTableBody');
            if (data.success && data.data.length > 0) {
                tbody.innerHTML = data.data.map(visitor => `
                    <tr class="${!visitor.exit_date ? 'table-warning' : ''}">
                        <td>${visitor.visitor_name}</td>
                        <td>${visitor.visitor_id_type}<br><small>${visitor.visitor_id_number}</small></td>
                        <td>${visitor.host_name}</td>
                        <td>${visitor.visit_purpose}</td>
                        <td>${visitor.entry_time}</td>
                        <td>${visitor.exit_time || '<span class="badge bg-warning">Inside</span>'}</td>
                        <td>
                            ${!visitor.exit_date ? `<button class="btn btn-sm btn-danger" onclick="logVisitorExit(${visitor.id})">Log Exit</button>` : ''}
                        </td>
                    </tr>
                `).join('');
            } else {
                tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted">No visitors today</td></tr>';
            }
        });
}

function loadPersonnel() {
    fetch('api/security-api.php?action=get_personnel')
        .then(response => response.json())
        .then(data => {
            const tbody = document.getElementById('personnelTableBody');
            if (data.success && data.data.length > 0) {
                tbody.innerHTML = data.data.map(person => `
                    <tr>
                        <td>${person.employee_number}</td>
                        <td>${person.full_name}</td>
                        <td>${person.phone}</td>
                        <td><span class="badge bg-info">${person.security_clearance_level}</span></td>
                        <td><span class="badge bg-${person.employment_status === 'active' ? 'success' : 'secondary'}">${person.employment_status}</span></td>
                        <td>
                            <button class="btn btn-sm btn-outline-primary" onclick="viewPersonnel(${person.id})">
                                <i class="bi bi-eye"></i>
                            </button>
                        </td>
                    </tr>
                `).join('');
            } else {
                tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted">No personnel found</td></tr>';
            }
        });
}

function loadDropdowns() {
    fetch('api/security-api.php?action=get_personnel')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const select = document.getElementById('schedulePersonnelId');
                select.innerHTML = '<option value="">Select Personnel</option>' +
                    data.data.filter(p => p.employment_status === 'active')
                        .map(p => `<option value="${p.id}">${p.full_name}</option>`).join('');
            }
        });
    
    fetch('api/security-api.php?action=get_posts')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const select = document.getElementById('schedulePostId');
                select.innerHTML = '<option value="">Select Post</option>' +
                    data.data.map(p => `<option value="${p.id}">${p.post_name}</option>`).join('');
            }
        });
    
    fetch('api/security-api.php?action=get_shifts')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const select = document.getElementById('scheduleShiftId');
                select.innerHTML = '<option value="">Select Shift</option>' +
                    data.data.map(s => `<option value="${s.id}">${s.shift_name} (${s.start_time} - ${s.end_time})</option>`).join('');
            }
        });
}

function showIncidentModal() {
    document.getElementById('incidentForm').reset();
    document.querySelector('[name="incident_date"]').valueAsDate = new Date();
    document.querySelector('[name="incident_time"]').value = new Date().toTimeString().slice(0, 5);
    new bootstrap.Modal(document.getElementById('incidentModal')).show();
}

function showScheduleModal() {
    document.getElementById('scheduleForm').reset();
    new bootstrap.Modal(document.getElementById('scheduleModal')).show();
}

function showVisitorEntryModal() {
    document.getElementById('visitorEntryForm').reset();
    new bootstrap.Modal(document.getElementById('visitorEntryModal')).show();
}

function showStartPatrolModal() {
    document.getElementById('startPatrolForm').reset();
    loadPatrolDropdowns();
    new bootstrap.Modal(document.getElementById('startPatrolModal')).show();
}

function loadPatrolDropdowns() {
    // Load patrol routes
    fetch('api/security-api.php?action=get_patrol_routes')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const select = document.getElementById('patrolRouteId');
                select.innerHTML = '<option value="">Select Route</option>' +
                    data.data.map(r => `<option value="${r.id}">${r.route_name} (${r.estimated_duration_minutes} min)</option>`).join('');
            }
        });
    
    // Load personnel
    fetch('api/security-api.php?action=get_personnel')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const select = document.getElementById('patrolPersonnelId');
                select.innerHTML = '<option value="">Select Personnel</option>' +
                    data.data.filter(p => p.employment_status === 'active')
                        .map(p => `<option value="${p.id}">${p.full_name}</option>`).join('');
            }
        });
}

function submitStartPatrol() {
    const form = document.getElementById('startPatrolForm');
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }

    const formData = new FormData(form);
    const data = Object.fromEntries(formData.entries());

    fetch('api/security-api.php?action=start_patrol', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': csrfToken
        },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('Success', 'Patrol started successfully', 'success');
            bootstrap.Modal.getInstance(document.getElementById('startPatrolModal')).hide();
            loadPatrolLogs();
        } else {
            showNotification('Error', data.message, 'danger');
        }
    });
}

function loadPatrolLogs() {
    const date = new Date().toISOString().split('T')[0];
    
    fetch(`api/security-api.php?action=get_patrol_logs&date=${date}`)
        .then(response => response.json())
        .then(data => {
            const tbody = document.getElementById('patrolsTableBody');
            if (data.success && data.data.length > 0) {
                tbody.innerHTML = data.data.map(patrol => `
                    <tr>
                        <td>${patrol.route_name}</td>
                        <td>${patrol.personnel_name}</td>
                        <td>${formatDateTime(patrol.patrol_start_time)}</td>
                        <td>${patrol.patrol_end_time ? formatDateTime(patrol.patrol_end_time) : '<span class="badge bg-warning">In Progress</span>'}</td>
                        <td>${patrol.completed_checkpoints}/${patrol.total_checkpoints}</td>
                        <td><span class="badge bg-${getPatrolStatusColor(patrol.status)}">${patrol.status}</span></td>
                        <td>
                            ${patrol.status === 'in_progress' ? `<button class="btn btn-sm btn-success" onclick="completePatrol(${patrol.id})">Complete</button>` : ''}
                        </td>
                    </tr>
                `).join('');
            } else {
                tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted">No patrol logs today</td></tr>';
            }
        });
}

function getPatrolStatusColor(status) {
    const colors = {
        'in_progress': 'primary',
        'completed': 'success',
        'incomplete': 'warning',
        'abandoned': 'danger'
    };
    return colors[status] || 'secondary';
}

function submitIncident() {
    const form = document.getElementById('incidentForm');
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }

    const formData = new FormData(form);
    const data = Object.fromEntries(formData.entries());
    
    data.police_notified = form.querySelector('[name="police_notified"]').checked;
    data.ambulance_called = form.querySelector('[name="ambulance_called"]').checked;
    data.property_damage = form.querySelector('[name="property_damage"]').checked;

    fetch('api/security-api.php?action=create_incident', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': csrfToken
        },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('Success', 'Incident reported successfully', 'success');
            bootstrap.Modal.getInstance(document.getElementById('incidentModal')).hide();
            loadIncidents();
            loadDashboardStats();
        } else {
            showNotification('Error', data.message, 'danger');
        }
    });
}

function submitSchedule() {
    const form = document.getElementById('scheduleForm');
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }

    const formData = new FormData(form);
    const data = Object.fromEntries(formData.entries());

    fetch('api/security-api.php?action=create_schedule', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': csrfToken
        },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('Success', 'Schedule created successfully', 'success');
            bootstrap.Modal.getInstance(document.getElementById('scheduleModal')).hide();
            loadSchedule();
            loadDashboardStats();
        } else {
            showNotification('Error', data.message, 'danger');
        }
    });
}

function submitVisitorEntry() {
    const form = document.getElementById('visitorEntryForm');
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }

    const formData = new FormData(form);
    const data = Object.fromEntries(formData.entries());
    data.personnel_id = 1;
    data.post_id = 1;

    fetch('api/security-api.php?action=log_visitor_entry', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': csrfToken
        },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('Success', 'Visitor entry logged successfully', 'success');
            bootstrap.Modal.getInstance(document.getElementById('visitorEntryModal')).hide();
            loadVisitors();
            loadDashboardStats();
        } else {
            showNotification('Error', data.message, 'danger');
        }
    });
}

function checkIn(scheduleId) {
    if (confirm('Confirm check-in?')) {
        fetch('api/security-api.php?action=check_in', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken
            },
            body: JSON.stringify({ schedule_id: scheduleId })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('Success', 'Checked in successfully', 'success');
                loadSchedule();
                loadDashboardStats();
            } else {
                showNotification('Error', data.message, 'danger');
            }
        });
    }
}

function checkOut(scheduleId) {
    if (confirm('Confirm check-out?')) {
        fetch('api/security-api.php?action=check_out', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken
            },
            body: JSON.stringify({ schedule_id: scheduleId })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('Success', 'Checked out successfully', 'success');
                loadSchedule();
                loadDashboardStats();
            } else {
                showNotification('Error', data.message, 'danger');
            }
        });
    }
}

function logVisitorExit(visitorLogId) {
    if (confirm('Log visitor exit?')) {
        fetch('api/security-api.php?action=log_visitor_exit', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken
            },
            body: JSON.stringify({ 
                visitor_log_id: visitorLogId,
                personnel_id: 1,
                post_id: 1
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('Success', 'Visitor exit logged', 'success');
                loadVisitors();
                loadDashboardStats();
            } else {
                showNotification('Error', data.message, 'danger');
            }
        });
    }
}

function getScheduleStatusColor(status) {
    const colors = {
        'scheduled': 'secondary',
        'confirmed': 'primary',
        'in_progress': 'success',
        'completed': 'info',
        'cancelled': 'danger',
        'no_show': 'warning'
    };
    return colors[status] || 'secondary';
}

function getSeverityColor(severity) {
    const colors = {
        'low': 'info',
        'medium': 'warning',
        'high': 'danger',
        'critical': 'dark'
    };
    return colors[severity] || 'secondary';
}

function getIncidentStatusColor(status) {
    const colors = {
        'open': 'danger',
        'under_investigation': 'warning',
        'resolved': 'success',
        'closed': 'secondary'
    };
    return colors[status] || 'secondary';
}

function showNotification(title, message, type) {
    const toast = document.createElement('div');
    toast.className = `toast align-items-center text-white bg-${type} border-0`;
    toast.setAttribute('role', 'alert');
    toast.innerHTML = `
        <div class="d-flex">
            <div class="toast-body">
                <strong>${title}</strong><br>${message}
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    `;
    
    let container = document.querySelector('.toast-container');
    if (!container) {
        container = document.createElement('div');
        container.className = 'toast-container position-fixed top-0 end-0 p-3';
        document.body.appendChild(container);
    }
    
    container.appendChild(toast);
    const bsToast = new bootstrap.Toast(toast);
    bsToast.show();
}
</script>

<?php require_once 'includes/footer.php'; ?>
