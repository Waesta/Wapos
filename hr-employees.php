<?php
require_once 'includes/bootstrap.php';

$auth->requireRole(['admin', 'manager', 'hr_manager', 'hr_staff']);

$pageTitle = 'HR & Employee Management';
require_once 'includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="bi bi-person-badge me-2"></i>HR & Employee Management</h2>
        <div>
            <?php if ($auth->hasRole(['admin', 'manager', 'hr_manager'])): ?>
            <button class="btn btn-primary" onclick="showEmployeeModal()">
                <i class="bi bi-person-plus me-1"></i>Add Employee
            </button>
            <button class="btn btn-success" onclick="showCreatePayrollRunModal()">
                <i class="bi bi-cash-stack me-1"></i>Process Payroll
            </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Dashboard Stats -->
    <div class="row mb-4" id="dashboardStats">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <h6 class="card-subtitle mb-2">Total Employees</h6>
                    <h3 class="card-title mb-0" id="statTotalEmployees">0</h3>
                    <small>Active Staff</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-white">
                <div class="card-body">
                    <h6 class="card-subtitle mb-2">On Probation</h6>
                    <h3 class="card-title mb-0" id="statOnProbation">0</h3>
                    <small>New Hires</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <h6 class="card-subtitle mb-2">Leave Requests</h6>
                    <h3 class="card-title mb-0" id="statPendingLeave">0</h3>
                    <small>Pending Approval</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h6 class="card-subtitle mb-2">On Leave</h6>
                    <h3 class="card-title mb-0" id="statOnLeaveToday">0</h3>
                    <small>Total Approved</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabs -->
    <ul class="nav nav-tabs mb-3" role="tablist">
        <li class="nav-item">
            <a class="nav-link active" data-bs-toggle="tab" href="#tabEmployees">Employees</a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-bs-toggle="tab" href="#tabLeave">Leave Management</a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-bs-toggle="tab" href="#tabPayroll">Payroll</a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-bs-toggle="tab" href="#tabDepartments">Departments</a>
        </li>
    </ul>

    <div class="tab-content">
        <!-- Employees Tab -->
        <div class="tab-pane fade show active" id="tabEmployees">
            <div class="card">
                <div class="card-header">
                    <div class="row align-items-center">
                        <div class="col-md-6">
                            <h5 class="mb-0">Employee Directory</h5>
                        </div>
                        <div class="col-md-6">
                            <div class="row g-2">
                                <div class="col-md-6">
                                    <select class="form-select form-select-sm" id="filterDepartment" onchange="loadEmployees()">
                                        <option value="">All Departments</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <input type="text" class="form-control form-control-sm" id="filterSearch" placeholder="Search..." onkeyup="loadEmployees()">
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
                                    <th>Employee #</th>
                                    <th>Name</th>
                                    <th>Department</th>
                                    <th>Position</th>
                                    <th>Email</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="employeesTableBody">
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

        <!-- Leave Management Tab -->
        <div class="tab-pane fade" id="tabLeave">
            <div class="card">
                <div class="card-header">
                    <div class="row align-items-center">
                        <div class="col-md-6">
                            <h5 class="mb-0">Leave Applications</h5>
                        </div>
                        <div class="col-md-6 text-end">
                            <button class="btn btn-sm btn-primary" onclick="showLeaveApplicationModal()">
                                <i class="bi bi-calendar-plus"></i> Apply for Leave
                            </button>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <select class="form-select form-select-sm d-inline-block w-auto" id="filterLeaveStatus" onchange="loadLeaveApplications()">
                            <option value="">All Statuses</option>
                            <option value="pending">Pending</option>
                            <option value="approved">Approved</option>
                            <option value="rejected">Rejected</option>
                        </select>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Application #</th>
                                    <th>Employee</th>
                                    <th>Leave Type</th>
                                    <th>Period</th>
                                    <th>Days</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="leaveApplicationsTableBody">
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

        <!-- Payroll Tab -->
        <div class="tab-pane fade" id="tabPayroll">
            <div class="card">
                <div class="card-header">
                    <div class="row align-items-center">
                        <div class="col-md-6">
                            <h5 class="mb-0">Payroll Runs</h5>
                        </div>
                        <div class="col-md-6 text-end">
                            <?php if ($auth->hasRole(['admin', 'manager', 'hr_manager'])): ?>
                            <button class="btn btn-sm btn-success" onclick="showCreatePayrollRunModal()">
                                <i class="bi bi-plus-lg"></i> New Payroll Run
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
                                    <th>Payroll #</th>
                                    <th>Period</th>
                                    <th>Payment Date</th>
                                    <th>Employees</th>
                                    <th>Total Net</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="payrollRunsTableBody">
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

        <!-- Departments Tab -->
        <div class="tab-pane fade" id="tabDepartments">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Departments & Positions</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6>Departments</h6>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Department</th>
                                            <th>Manager</th>
                                            <th>Employees</th>
                                        </tr>
                                    </thead>
                                    <tbody id="departmentsTableBody">
                                        <tr>
                                            <td colspan="3" class="text-center">Loading...</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <h6>Employee Distribution</h6>
                            <canvas id="departmentChart" height="200"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Employee Modal -->
<div class="modal fade" id="employeeModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="employeeModalTitle">Add Employee</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="employeeForm">
                    <input type="hidden" id="employeeId" name="id">
                    <input type="hidden" name="employment_status" value="probation">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Full Name *</label>
                            <input type="text" class="form-control" name="full_name" required placeholder="e.g., John Doe">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Employee Number *</label>
                            <input type="text" class="form-control" name="employee_number" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">User Account *</label>
                            <select class="form-select" name="user_id" required>
                                <option value="">Select User</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Department</label>
                            <select class="form-select" name="department_id" id="employeeDepartmentId">
                                <option value="">Select Department</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Position</label>
                            <select class="form-select" name="position_id">
                                <option value="">Select Position</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Hire Date *</label>
                            <input type="date" class="form-control" name="hire_date" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Employment Type *</label>
                            <select class="form-select" name="employment_type" required>
                                <option value="full_time">Full Time</option>
                                <option value="part_time">Part Time</option>
                                <option value="contract">Contract</option>
                                <option value="intern">Intern</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Phone</label>
                            <input type="tel" class="form-control" name="personal_phone">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Personal Email</label>
                            <input type="email" class="form-control" name="personal_email">
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">Address</label>
                            <textarea class="form-control" name="address" rows="2"></textarea>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" onclick="saveEmployee()">Save Employee</button>
            </div>
        </div>
    </div>
</div>

<!-- Leave Application Modal -->
<div class="modal fade" id="leaveApplicationModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Apply for Leave</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="leaveApplicationForm">
                    <div class="mb-3 d-none" id="leaveEmployeeSelectGroup">
                        <label class="form-label">Employee *</label>
                        <select class="form-select" id="leaveEmployeeSelect">
                            <option value="">Select Employee</option>
                        </select>
                        <small class="text-muted">Required because your account is not linked to an employee profile.</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Leave Type *</label>
                        <select class="form-select" name="leave_type_id" id="leaveTypeId" required onchange="checkLeaveBalance()">
                            <option value="">Select Leave Type</option>
                        </select>
                        <small class="text-muted" id="leaveBalanceInfo"></small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Start Date *</label>
                        <input type="date" class="form-control" name="start_date" id="leaveStartDate" required onchange="calculateLeaveDays()">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">End Date *</label>
                        <input type="date" class="form-control" name="end_date" id="leaveEndDate" required onchange="calculateLeaveDays()">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Total Days</label>
                        <input type="number" class="form-control" name="total_days" id="leaveTotalDays" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Reason *</label>
                        <textarea class="form-control" name="reason" rows="3" required></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Contact During Leave</label>
                        <input type="text" class="form-control" name="contact_during_leave" placeholder="Phone number or email">
                    </div>
                    <input type="hidden" name="employee_id" id="leaveEmployeeId">
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="submitLeaveApplication()">Submit Application</button>
            </div>
        </div>
    </div>
</div>

<!-- Create Payroll Run Modal -->
<div class="modal fade" id="createPayrollRunModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Create Payroll Run</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="createPayrollRunForm">
                    <div class="mb-3">
                        <label class="form-label">Period Start Date *</label>
                        <input type="date" class="form-control" name="period_start_date" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Period End Date *</label>
                        <input type="date" class="form-control" name="period_end_date" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Payment Date *</label>
                        <input type="date" class="form-control" name="payment_date" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea class="form-control" name="notes" rows="2"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" onclick="createPayrollRun()">Create & Generate</button>
            </div>
        </div>
    </div>
</div>

<!-- Leave Review Modal -->
<div class="modal fade" id="leaveReviewModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Review Leave Application</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="leaveReviewDetails"></div>
                <form id="leaveReviewForm">
                    <input type="hidden" id="reviewApplicationId" name="application_id">
                    <div class="mb-3">
                        <label class="form-label">Comments</label>
                        <textarea class="form-control" name="comments" rows="3"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" onclick="reviewLeave('rejected')">Reject</button>
                <button type="button" class="btn btn-success" onclick="reviewLeave('approved')">Approve</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
const csrfToken = '<?php echo generateCSRFToken(); ?>';
let currentEmployeeId = null;
let departmentChart = null;

document.addEventListener('DOMContentLoaded', function() {
    initializeEmployeeContext();
    loadDashboardStats();
    loadEmployees();
    loadLeaveApplications();
    loadPayrollRuns();
    loadDepartments();
    loadDropdowns();
});

function loadDashboardStats() {
    fetch('api/hr-api.php?action=get_dashboard_stats')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('statTotalEmployees').textContent = data.data.total_employees || 0;
                document.getElementById('statOnProbation').textContent = data.data.on_probation || 0;
                document.getElementById('statPendingLeave').textContent = data.data.pending_leave_requests || 0;
                document.getElementById('statOnLeaveToday').textContent = data.data.employees_on_leave_today || 0;
            }
        })
        .catch(error => console.error('Error loading dashboard stats:', error));
}

function fetchEmployeeContext() {
    return fetch('api/hr-api.php?action=get_employee_by_user')
        .then(response => response.json())
        .then(data => {
            if (data.success && data.data) {
                currentEmployeeId = data.data.id;
                const employeeNameDisplay = document.querySelector('.employee-context-name');
                if (employeeNameDisplay) {
                    employeeNameDisplay.textContent = data.data.full_name || data.data.employee_number;
                }
                return currentEmployeeId;
            }
            throw new Error('No employee profile attached to this account.');
        });
}

function initializeEmployeeContext() {
    fetchEmployeeContext().catch(err => {
        console.warn('Unable to load employee context', err.message || err);
    });

    const userSelect = document.querySelector('[name="user_id"]');
    if (userSelect) {
        userSelect.addEventListener('change', handleUserSelection);
    }
}

function handleUserSelection(event) {
    const option = event.target.selectedOptions[0];
    if (!option) return;

    const fullName = option.getAttribute('data-full-name');
    const email = option.getAttribute('data-email');
    const fullNameInput = document.querySelector('[name="full_name"]');
    const emailInput = document.querySelector('[name="personal_email"]');

    if (fullName && fullNameInput && !fullNameInput.hasAttribute('readonly')) {
        fullNameInput.value = fullName;
    }
    if (email && emailInput && !emailInput.value) {
        emailInput.value = email;
    }
}

function checkLeaveBalance() {
    const leaveTypeId = document.getElementById('leaveTypeId').value;
    const info = document.getElementById('leaveBalanceInfo');

    if (!currentEmployeeId) {
        info.textContent = 'No employee profile linked to your account.';
        return;
    }

    if (!leaveTypeId) {
        info.textContent = '';
        return;
    }

    fetch(`api/hr-api.php?action=get_leave_balance&employee_id=${currentEmployeeId}&leave_type_id=${leaveTypeId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.data) {
                info.textContent = `Balance: ${data.data.balance_days} days remaining in ${data.data.year}.`;
            } else {
                info.textContent = data.message || 'Unable to fetch leave balance.';
            }
        })
        .catch(() => {
            info.textContent = 'Error checking leave balance.';
        });
}

function loadEmployees() {
    const department = document.getElementById('filterDepartment').value;
    const search = document.getElementById('filterSearch').value;
    
    const params = new URLSearchParams();
    if (department) params.append('department_id', department);
    if (search) params.append('search', search);
    
    fetch(`api/hr-api.php?action=get_employees&${params}`)
        .then(response => response.json())
        .then(data => {
            const tbody = document.getElementById('employeesTableBody');
            if (data.success && data.data.length > 0) {
                tbody.innerHTML = data.data.map(emp => `
                    <tr>
                        <td><strong>${emp.employee_number}</strong></td>
                        <td>${emp.full_name}</td>
                        <td>${emp.department_name || 'N/A'}</td>
                        <td>${emp.position_title || 'N/A'}</td>
                        <td>${emp.email}</td>
                        <td><span class="badge bg-${getEmploymentStatusColor(emp.employment_status)}">${emp.employment_status}</span></td>
                        <td>
                            <button class="btn btn-sm btn-outline-primary" onclick="viewEmployee(${emp.id})">
                                <i class="bi bi-eye"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-success" onclick="editEmployee(${emp.id})">
                                <i class="bi bi-pencil"></i>
                            </button>
                        </td>
                    </tr>
                `).join('');
            } else {
                tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted">No employees found</td></tr>';
            }
        });
}

function loadLeaveApplications() {
    const status = document.getElementById('filterLeaveStatus').value;
    const params = new URLSearchParams();
    if (status) params.append('status', status);
    
    fetch(`api/hr-api.php?action=get_leave_applications&${params}`)
        .then(response => response.json())
        .then(data => {
            const tbody = document.getElementById('leaveApplicationsTableBody');
            if (data.success && data.data.length > 0) {
                tbody.innerHTML = data.data.map(app => `
                    <tr>
                        <td><strong>${app.application_number}</strong></td>
                        <td>${app.employee_name}</td>
                        <td>${app.leave_name}</td>
                        <td>${formatDate(app.start_date)} - ${formatDate(app.end_date)}</td>
                        <td>${app.total_days}</td>
                        <td><span class="badge bg-${getLeaveStatusColor(app.status)}">${app.status}</span></td>
                        <td>
                            <?php if ($auth->hasRole(['admin', 'manager', 'hr_manager'])): ?>
                            ${app.status === 'pending' ? `<button class="btn btn-sm btn-outline-primary" onclick="showLeaveReviewModal(${app.id})">Review</button>` : ''}
                            <?php endif; ?>
                        </td>
                    </tr>
                `).join('');
            } else {
                tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted">No leave applications found</td></tr>';
            }
        });
}

function loadPayrollRuns() {
    fetch('api/hr-api.php?action=get_payroll_runs')
        .then(response => response.json())
        .then(data => {
            const tbody = document.getElementById('payrollRunsTableBody');
            if (data.success && data.data.length > 0) {
                tbody.innerHTML = data.data.map(run => `
                    <tr>
                        <td><strong>${run.payroll_number}</strong></td>
                        <td>${formatDate(run.period_start_date)} - ${formatDate(run.period_end_date)}</td>
                        <td>${formatDate(run.payment_date)}</td>
                        <td>${run.employee_count || 0}</td>
                        <td>${formatCurrency(run.total_net || 0)}</td>
                        <td><span class="badge bg-${getPayrollStatusColor(run.status)}">${run.status}</span></td>
                        <td>
                            <?php if ($auth->hasRole(['admin', 'manager'])): ?>
                            ${run.status === 'draft' ? `<button class="btn btn-sm btn-success" onclick="approvePayrollRun(${run.id})">Approve</button>` : ''}
                            <?php endif; ?>
                        </td>
                    </tr>
                `).join('');
            } else {
                tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted">No payroll runs found</td></tr>';
            }
        });
}

function loadDepartments() {
    fetch('api/hr-api.php?action=get_departments')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const tbody = document.getElementById('departmentsTableBody');
                tbody.innerHTML = data.data.map(dept => `
                    <tr>
                        <td>${dept.department_name}</td>
                        <td>${dept.manager_name || 'N/A'}</td>
                        <td>-</td>
                    </tr>
                `).join('');
            }
        });
    
    fetch('api/hr-api.php?action=get_dashboard_stats')
        .then(response => response.json())
        .then(data => {
            if (data.success && data.data.employees_by_department) {
                renderDepartmentChart(data.data.employees_by_department);
            }
        });
}

function loadDropdowns() {
    fetch('api/hr-api.php?action=get_departments')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const selects = [
                    document.getElementById('filterDepartment'),
                    document.getElementById('employeeDepartmentId')
                ];
                selects.forEach(select => {
                    if (select) {
                        select.innerHTML = '<option value="">Select Department</option>' +
                            data.data.map(d => `<option value="${d.id}">${d.department_name}</option>`).join('');
                    }
                });
            }
        });
    
    fetch('api/hr-api.php?action=get_leave_types')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const select = document.getElementById('leaveTypeId');
                if (select) {
                    select.innerHTML = '<option value="">Select Leave Type</option>' +
                        data.data.map(t => `<option value="${t.id}">${t.leave_name} (${t.annual_entitlement_days} days/year)</option>`).join('');
                }
            }
        });
}

function loadEmployeeFormOptions(selected = {}) {
    const userSelect = document.querySelector('[name="user_id"]');
    const deptSelect = document.querySelector('[name="department_id"]');
    const posSelect = document.querySelector('[name="position_id"]');

    const userPromise = fetch('api/hr-api.php?action=get_users')
        .then(response => response.json())
        .then(data => {
            if (data.success && userSelect) {
                userSelect.innerHTML = '<option value="">Select User</option>';
                data.data.forEach(user => {
                    userSelect.innerHTML += `<option value="${user.id}" data-full-name="${user.full_name}" data-email="${user.email}">${user.full_name} (${user.username})</option>`;
                });
                if (selected.user_id) {
                    userSelect.value = selected.user_id;
                }
            }
        });

    const deptPromise = fetch('api/hr-api.php?action=get_departments')
        .then(response => response.json())
        .then(data => {
            if (data.success && deptSelect) {
                deptSelect.innerHTML = '<option value="">Select Department</option>';
                data.data.forEach(dept => {
                    deptSelect.innerHTML += `<option value="${dept.id}">${dept.department_name}</option>`;
                });
                if (selected.department_id) {
                    deptSelect.value = selected.department_id;
                }
            }
        });

    const posPromise = fetch('api/hr-api.php?action=get_positions')
        .then(response => response.json())
        .then(data => {
            if (data.success && posSelect) {
                posSelect.innerHTML = '<option value="">Select Position</option>';
                data.data.forEach(pos => {
                    posSelect.innerHTML += `<option value="${pos.id}">${pos.position_title}</option>`;
                });
                if (selected.position_id) {
                    posSelect.value = selected.position_id;
                }
            }
        });

    return Promise.all([userPromise, deptPromise, posPromise]);
}

function populateEmployeeForm(employee) {
    if (!employee) return;

    document.querySelector('[name="full_name"]').value = employee.full_name || '';
    document.querySelector('[name="employee_number"]').value = employee.employee_number || '';
    document.querySelector('[name="user_id"]').value = employee.user_id || '';
    document.querySelector('[name="department_id"]').value = employee.department_id || '';
    document.querySelector('[name="position_id"]').value = employee.position_id || '';
    document.querySelector('[name="hire_date"]').value = employee.hire_date ? employee.hire_date.substring(0, 10) : '';
    document.querySelector('[name="employment_type"]').value = employee.employment_type || 'full_time';
    document.querySelector('[name="employment_status"]').value = employee.employment_status || 'probation';
    document.querySelector('[name="personal_phone"]').value = employee.personal_phone || '';
    document.querySelector('[name="personal_email"]').value = employee.personal_email || '';
    document.querySelector('[name="address"]').value = employee.address || '';
}

function showEmployeeModal(employee = null) {
    const form = document.getElementById('employeeForm');
    form.reset();
    document.getElementById('employeeModalTitle').textContent = employee ? 'Edit Employee' : 'Add Employee';
    document.getElementById('employeeId').value = employee ? employee.id : '';
    document.querySelector('[name="employment_status"]').value = employee?.employment_status || 'probation';

    const modalInstance = new bootstrap.Modal(document.getElementById('employeeModal'));
    modalInstance.show();

    loadEmployeeFormOptions({
        user_id: employee?.user_id,
        department_id: employee?.department_id,
        position_id: employee?.position_id
    }).then(() => {
        if (employee) {
            populateEmployeeForm(employee);
        } else {
            const fullNameInput = document.querySelector('[name="full_name"]');
            if (fullNameInput) fullNameInput.removeAttribute('readonly');
        }
    }).catch(error => console.error('Error loading employee options:', error));
}

function editEmployee(employeeId) {
    fetch(`api/hr-api.php?action=get_employee&id=${employeeId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.data) {
                showEmployeeModal(data.data);
            } else {
                showNotification('Error', 'Unable to load employee details', 'danger');
            }
        })
        .catch(error => {
            console.error('Load employee error:', error);
            showNotification('Error', 'Failed to load employee details', 'danger');
        });
}

function saveEmployee() {
    const form = document.getElementById('employeeForm');
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }

    const formData = new FormData(form);
    const data = Object.fromEntries(formData.entries());
    const employeeId = document.getElementById('employeeId').value;
    const action = employeeId ? 'update_employee' : 'create_employee';

    const payload = {
        id: employeeId || undefined,
        user_id: data.user_id,
        employee_number: data.employee_number,
        department_id: data.department_id || null,
        position_id: data.position_id || null,
        reports_to_user_id: data.reports_to_user_id || null,
        hire_date: data.hire_date,
        probation_end_date: data.probation_end_date || null,
        employment_status: data.employment_status || 'probation',
        employment_type: data.employment_type,
        work_location: data.work_location || null,
        work_schedule: data.work_schedule || null,
        id_number: data.id_number || null,
        passport_number: data.passport_number || null,
        tax_pin: data.tax_pin || null,
        social_security_number: data.social_security_number || null,
        bank_name: data.bank_name || null,
        bank_account_number: data.bank_account_number || null,
        bank_branch: data.bank_branch || null,
        emergency_contact_name: data.emergency_contact_name || null,
        emergency_contact_relationship: data.emergency_contact_relationship || null,
        emergency_contact_phone: data.emergency_contact_phone || null,
        date_of_birth: data.date_of_birth || null,
        gender: data.gender || null,
        marital_status: data.marital_status || null,
        nationality: data.nationality || null,
        address: data.address || null,
        city: data.city || null,
        postal_code: data.postal_code || null,
        country: data.country || null,
        personal_email: data.personal_email || null,
        personal_phone: data.personal_phone || null,
        photo_path: data.photo_path || null,
        notes: data.notes || null
    };

    fetch(`api/hr-api.php?action=${action}`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': csrfToken
        },
        body: JSON.stringify(payload)
    })
    .then(response => response.text())
    .then(text => {
        console.log('API Response:', text);
        try {
            const data = JSON.parse(text);
            if (data.success) {
                showNotification('Success', data.message || 'Employee saved successfully', 'success');
                bootstrap.Modal.getInstance(document.getElementById('employeeModal')).hide();
                loadEmployees();
            } else {
                showNotification('Error', data.message || 'Failed to save employee', 'danger');
            }
        } catch (e) {
            console.error('JSON Parse Error:', e);
            console.error('Response text:', text);
            showNotification('Error', 'Server error: ' + text.substring(0, 200), 'danger');
        }
    })
    .catch(error => {
        console.error('Save employee error:', error);
        showNotification('Error', error.message || 'An error occurred while saving employee', 'danger');
    });
}

function showLeaveApplicationModal() {
    const openModal = () => {
        const form = document.getElementById('leaveApplicationForm');
        form.reset();
        document.getElementById('leaveEmployeeId').value = currentEmployeeId;
        document.getElementById('leaveBalanceInfo').textContent = '';
        toggleLeaveEmployeeSelector(false);
        new bootstrap.Modal(document.getElementById('leaveApplicationModal')).show();
    };

    if (currentEmployeeId) {
        openModal();
        return;
    }

    fetchEmployeeContext()
        .then(openModal)
        .catch(err => {
            console.warn('Leave application opened without linked employee:', err.message || err);
            toggleLeaveEmployeeSelector(true);
            loadEmployeesForLeave();
            new bootstrap.Modal(document.getElementById('leaveApplicationModal')).show();
        });
}

function toggleLeaveEmployeeSelector(show) {
    const group = document.getElementById('leaveEmployeeSelectGroup');
    if (!group) return;
    if (show) {
        group.classList.remove('d-none');
    } else {
        group.classList.add('d-none');
        document.getElementById('leaveEmployeeSelect').value = '';
    }
}

function loadEmployeesForLeave() {
    const select = document.getElementById('leaveEmployeeSelect');
    if (!select) return;

    select.innerHTML = '<option value="">Select Employee</option>';
    fetch('api/hr-api.php?action=get_employees')
        .then(response => response.json())
        .then(data => {
            if (data.success && data.data.length) {
                data.data.forEach(emp => {
                    select.innerHTML += `<option value="${emp.id}">${emp.full_name} (${emp.employee_number})</option>`;
                });
            } else {
                select.innerHTML = '<option value="">No employees available</option>';
            }
        })
        .catch(err => {
            console.error('Failed to load employees for leave:', err);
            select.innerHTML = '<option value="">Unable to load employees</option>';
        });

    select.addEventListener('change', () => {
        document.getElementById('leaveEmployeeId').value = select.value;
    }, { once: true });
}

function showCreatePayrollRunModal() {
    document.getElementById('createPayrollRunForm').reset();
    const today = new Date();
    const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
    const lastDay = new Date(today.getFullYear(), today.getMonth() + 1, 0);
    
    document.querySelector('[name="period_start_date"]').valueAsDate = firstDay;
    document.querySelector('[name="period_end_date"]').valueAsDate = lastDay;
    document.querySelector('[name="payment_date"]').valueAsDate = new Date(today.getFullYear(), today.getMonth() + 1, 5);
    
    new bootstrap.Modal(document.getElementById('createPayrollRunModal')).show();
}

function showLeaveReviewModal(applicationId) {
    fetch(`api/hr-api.php?action=get_leave_applications&application_id=${applicationId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.data.length > 0) {
                const app = data.data[0];
                document.getElementById('reviewApplicationId').value = app.id;
                document.getElementById('leaveReviewDetails').innerHTML = `
                    <div class="mb-3">
                        <strong>Employee:</strong> ${app.employee_name}<br>
                        <strong>Leave Type:</strong> ${app.leave_name}<br>
                        <strong>Period:</strong> ${formatDate(app.start_date)} - ${formatDate(app.end_date)}<br>
                        <strong>Days:</strong> ${app.total_days}<br>
                        <strong>Reason:</strong> ${app.reason}
                    </div>
                `;
                new bootstrap.Modal(document.getElementById('leaveReviewModal')).show();
            }
        });
}

function calculateLeaveDays() {
    const startDate = new Date(document.getElementById('leaveStartDate').value);
    const endDate = new Date(document.getElementById('leaveEndDate').value);
    
    if (startDate && endDate && endDate >= startDate) {
        const days = Math.ceil((endDate - startDate) / (1000 * 60 * 60 * 24)) + 1;
        document.getElementById('leaveTotalDays').value = days;
    }
}

function submitLeaveApplication() {
    const form = document.getElementById('leaveApplicationForm');
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }

    const formData = new FormData(form);
    const data = Object.fromEntries(formData.entries());
    data.employee_id = 1; // Should be current user's employee ID

    fetch('api/hr-api.php?action=apply_for_leave', {
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
            showNotification('Success', 'Leave application submitted successfully', 'success');
            bootstrap.Modal.getInstance(document.getElementById('leaveApplicationModal')).hide();
            loadLeaveApplications();
            loadDashboardStats();
        } else {
            showNotification('Error', data.message, 'danger');
        }
    });
}

function reviewLeave(status) {
    const applicationId = document.getElementById('reviewApplicationId').value;
    const comments = document.querySelector('[name="comments"]').value;

    fetch('api/hr-api.php?action=review_leave_application', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': csrfToken
        },
        body: JSON.stringify({
            application_id: applicationId,
            status: status,
            comments: comments
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('Success', `Leave application ${status}`, 'success');
            bootstrap.Modal.getInstance(document.getElementById('leaveReviewModal')).hide();
            loadLeaveApplications();
            loadDashboardStats();
        } else {
            showNotification('Error', data.message, 'danger');
        }
    });
}

function createPayrollRun() {
    const form = document.getElementById('createPayrollRunForm');
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }

    const formData = new FormData(form);
    const data = Object.fromEntries(formData.entries());

    fetch('api/hr-api.php?action=create_payroll_run', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': csrfToken
        },
        body: JSON.stringify(data)
    })
    .then(response => {
        if (!response.ok) {
            return response.text().then(text => {
                throw new Error(text || 'Failed to create payroll run');
            });
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            const payrollRunId = data.data.id;
            
            return fetch('api/hr-api.php?action=generate_payroll_details', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': csrfToken
                },
                body: JSON.stringify({ payroll_run_id: payrollRunId })
            });
        }
        throw new Error(data.message || 'Failed to create payroll run');
    })
    .then(response => {
        if (!response.ok) {
            return response.text().then(text => {
                throw new Error(text || 'Failed to generate payroll details');
            });
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            showNotification('Success', 'Payroll run created and generated successfully', 'success');
            bootstrap.Modal.getInstance(document.getElementById('createPayrollRunModal')).hide();
            loadPayrollRuns();
        } else {
            showNotification('Error', data.message || 'Failed to process payroll', 'danger');
        }
    })
    .catch(error => {
        console.error('Payroll creation error:', error);
        showNotification('Error', error.message || 'An error occurred while processing payroll', 'danger');
    });
}

function approvePayrollRun(payrollRunId) {
    if (confirm('Approve this payroll run? This action cannot be undone.')) {
        fetch('api/hr-api.php?action=approve_payroll_run', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken
            },
            body: JSON.stringify({ payroll_run_id: payrollRunId })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('Success', 'Payroll run approved successfully', 'success');
                loadPayrollRuns();
            } else {
                showNotification('Error', data.message, 'danger');
            }
        });
    }
}

function renderDepartmentChart(data) {
    const ctx = document.getElementById('departmentChart');
    if (departmentChart) {
        departmentChart.destroy();
    }
    
    departmentChart = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: data.map(d => d.department_name),
            datasets: [{
                data: data.map(d => d.count),
                backgroundColor: [
                    '#0d6efd', '#6610f2', '#6f42c1', '#d63384', '#dc3545',
                    '#fd7e14', '#ffc107', '#198754', '#20c997', '#0dcaf0'
                ]
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'right'
                }
            }
        }
    });
}

function getEmploymentStatusColor(status) {
    const colors = {
        'probation': 'warning',
        'confirmed': 'success',
        'contract': 'info',
        'resigned': 'secondary',
        'terminated': 'danger'
    };
    return colors[status] || 'secondary';
}

function getLeaveStatusColor(status) {
    const colors = {
        'pending': 'warning',
        'approved': 'success',
        'rejected': 'danger'
    };
    return colors[status] || 'secondary';
}

function getPayrollStatusColor(status) {
    const colors = {
        'draft': 'secondary',
        'approved': 'success',
        'processing': 'info',
        'completed': 'primary'
    };
    return colors[status] || 'secondary';
}

function formatCurrency(amount) {
    return new Intl.NumberFormat('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    }).format(amount);
}

function formatDate(dateString) {
    return new Date(dateString).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
    });
}

function showNotification(title, message, type = 'info') {
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
