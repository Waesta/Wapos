<?php
require_once 'includes/bootstrap.php';

$auth->requireRole(['admin', 'manager', 'frontdesk']);

$pageTitle = 'Events & Banquet Management';
require_once 'includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="bi bi-calendar-event me-2"></i>Events & Banquet Management</h2>
        <div>
            <button class="btn btn-primary" onclick="showBookingModal()">
                <i class="bi bi-plus-lg me-1"></i>New Booking
            </button>
            <button class="btn btn-outline-secondary" onclick="showVenuesModal()">
                <i class="bi bi-building me-1"></i>Manage Venues
            </button>
        </div>
    </div>

    <!-- Dashboard Stats -->
    <div class="row mb-4" id="dashboardStats">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <h6 class="card-subtitle mb-2">Total Bookings</h6>
                    <h3 class="card-title mb-0" id="statTotalBookings">0</h3>
                    <small>This Month</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h6 class="card-subtitle mb-2">Confirmed Events</h6>
                    <h3 class="card-title mb-0" id="statConfirmedBookings">0</h3>
                    <small>This Month</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <h6 class="card-subtitle mb-2">Total Revenue</h6>
                    <h3 class="card-title mb-0" id="statTotalRevenue">0</h3>
                    <small>This Month</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-white">
                <div class="card-body">
                    <h6 class="card-subtitle mb-2">Upcoming Events</h6>
                    <h3 class="card-title mb-0" id="statUpcomingEvents">0</h3>
                    <small>Next 7 Days</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select class="form-select" id="filterStatus" onchange="loadBookings()">
                        <option value="">All Statuses</option>
                        <option value="inquiry">Inquiry</option>
                        <option value="pending">Pending</option>
                        <option value="confirmed">Confirmed</option>
                        <option value="deposit_paid">Deposit Paid</option>
                        <option value="fully_paid">Fully Paid</option>
                        <option value="completed">Completed</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Date From</label>
                    <input type="date" class="form-control" id="filterDateFrom" onchange="loadBookings()">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Date To</label>
                    <input type="date" class="form-control" id="filterDateTo" onchange="loadBookings()">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Search</label>
                    <input type="text" class="form-control" id="filterSearch" placeholder="Booking #, Customer..." onkeyup="loadBookings()">
                </div>
            </div>
        </div>
    </div>

    <!-- Bookings Table -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Event Bookings</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover" id="bookingsTable">
                    <thead>
                        <tr>
                            <th>Booking #</th>
                            <th>Event Title</th>
                            <th>Customer</th>
                            <th>Venue</th>
                            <th>Event Date</th>
                            <th>Guests</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="bookingsTableBody">
                        <tr>
                            <td colspan="9" class="text-center">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="visually-hidden">Loading...</span>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- New/Edit Booking Modal -->
<div class="modal fade" id="bookingModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="bookingModalTitle">New Event Booking</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="bookingForm">
                    <input type="hidden" id="bookingId" name="id">
                    
                    <ul class="nav nav-tabs mb-3" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active" data-bs-toggle="tab" href="#tabBasicInfo">Basic Info</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-bs-toggle="tab" href="#tabServices">Services</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-bs-toggle="tab" href="#tabPayments">Payments</a>
                        </li>
                    </ul>

                    <div class="tab-content">
                        <!-- Basic Info Tab -->
                        <div class="tab-pane fade show active" id="tabBasicInfo">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Event Type *</label>
                                    <select class="form-select" id="eventTypeId" name="event_type_id" required>
                                        <option value="">Select Event Type</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Venue *</label>
                                    <select class="form-select" id="venueId" name="venue_id" required onchange="checkVenueAvailability()">
                                        <option value="">Select Venue</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Customer Name *</label>
                                    <input type="text" class="form-control" id="customerName" name="customer_name" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Customer Phone *</label>
                                    <input type="tel" class="form-control" id="customerPhone" name="customer_phone" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Customer Email</label>
                                    <input type="email" class="form-control" id="customerEmail" name="customer_email">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Company</label>
                                    <input type="text" class="form-control" id="customerCompany" name="customer_company">
                                </div>
                                <div class="col-md-12">
                                    <label class="form-label">Event Title *</label>
                                    <input type="text" class="form-control" id="eventTitle" name="event_title" required placeholder="e.g., John & Jane Wedding">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Event Date *</label>
                                    <input type="date" class="form-control" id="eventDate" name="event_date" required onchange="checkVenueAvailability()">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Start Time *</label>
                                    <input type="time" class="form-control" id="startTime" name="start_time" required onchange="checkVenueAvailability()">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">End Time *</label>
                                    <input type="time" class="form-control" id="endTime" name="end_time" required onchange="checkVenueAvailability()">
                                </div>
                                <div class="col-md-12">
                                    <div id="availabilityAlert"></div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Expected Guests *</label>
                                    <input type="number" class="form-control" id="expectedGuests" name="expected_guests" required min="1">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Status</label>
                                    <select class="form-select" id="bookingStatus" name="status">
                                        <option value="inquiry">Inquiry</option>
                                        <option value="pending">Pending</option>
                                        <option value="confirmed">Confirmed</option>
                                    </select>
                                </div>
                                <div class="col-md-12">
                                    <label class="form-label">Event Description</label>
                                    <textarea class="form-control" id="eventDescription" name="event_description" rows="3"></textarea>
                                </div>
                                <div class="col-md-12">
                                    <label class="form-label">Special Requests</label>
                                    <textarea class="form-control" id="specialRequests" name="special_requests" rows="2"></textarea>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Venue Rate</label>
                                    <input type="number" class="form-control" id="venueRate" name="venue_rate" step="0.01" onchange="calculateTotal()">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Discount</label>
                                    <input type="number" class="form-control" id="discountAmount" name="discount_amount" step="0.01" onchange="calculateTotal()">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Total Amount *</label>
                                    <input type="number" class="form-control" id="totalAmount" name="total_amount" step="0.01" required readonly>
                                </div>
                            </div>
                        </div>

                        <!-- Services Tab -->
                        <div class="tab-pane fade" id="tabServices">
                            <div class="mb-3">
                                <button type="button" class="btn btn-sm btn-primary" onclick="showAddServiceModal()">
                                    <i class="bi bi-plus-lg"></i> Add Service
                                </button>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Service</th>
                                            <th>Category</th>
                                            <th>Quantity</th>
                                            <th>Unit Price</th>
                                            <th>Subtotal</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody id="servicesTableBody">
                                        <tr>
                                            <td colspan="6" class="text-center text-muted">No services added yet</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Payments Tab -->
                        <div class="tab-pane fade" id="tabPayments">
                            <div class="mb-3">
                                <button type="button" class="btn btn-sm btn-success" onclick="showRecordPaymentModal()">
                                    <i class="bi bi-cash"></i> Record Payment
                                </button>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Payment #</th>
                                            <th>Date</th>
                                            <th>Type</th>
                                            <th>Method</th>
                                            <th>Amount</th>
                                            <th>Reference</th>
                                        </tr>
                                    </thead>
                                    <tbody id="paymentsTableBody">
                                        <tr>
                                            <td colspan="6" class="text-center text-muted">No payments recorded yet</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" onclick="saveBooking()">Save Booking</button>
            </div>
        </div>
    </div>
</div>

<!-- Venues Management Modal -->
<div class="modal fade" id="venuesModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Manage Venues</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Venue Name</th>
                                <th>Type</th>
                                <th>Capacity</th>
                                <th>Rates</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody id="venuesTableBody">
                            <tr>
                                <td colspan="5" class="text-center">Loading...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Service Modal -->
<div class="modal fade" id="addServiceModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Service to Booking</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="addServiceForm">
                    <input type="hidden" id="serviceBookingId" name="booking_id">
                    <div class="mb-3">
                        <label class="form-label">Service *</label>
                        <select class="form-select" id="serviceId" name="service_id" required onchange="updateServicePrice()">
                            <option value="">Select Service</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Quantity *</label>
                        <input type="number" class="form-control" id="serviceQuantity" name="quantity" value="1" min="1" required onchange="calculateServiceTotal()">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Unit Price *</label>
                        <input type="number" class="form-control" id="serviceUnitPrice" name="unit_price" step="0.01" required onchange="calculateServiceTotal()">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Subtotal</label>
                        <input type="number" class="form-control" id="serviceSubtotal" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea class="form-control" id="serviceNotes" name="notes" rows="2"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="saveService()">Add Service</button>
            </div>
        </div>
    </div>
</div>

<!-- Record Payment Modal -->
<div class="modal fade" id="recordPaymentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Record Payment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="recordPaymentForm">
                    <input type="hidden" id="paymentBookingId" name="booking_id">
                    <div class="mb-3">
                        <label class="form-label">Payment Type *</label>
                        <select class="form-select" id="paymentType" name="payment_type" required>
                            <option value="deposit">Deposit</option>
                            <option value="partial">Partial Payment</option>
                            <option value="full">Full Payment</option>
                            <option value="balance">Balance Payment</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Payment Method *</label>
                        <select class="form-select" id="paymentMethod" name="payment_method" required>
                            <option value="cash">Cash</option>
                            <option value="mpesa">M-Pesa</option>
                            <option value="card">Card</option>
                            <option value="bank_transfer">Bank Transfer</option>
                            <option value="cheque">Cheque</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Amount *</label>
                        <input type="number" class="form-control" id="paymentAmount" name="amount" step="0.01" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Payment Date *</label>
                        <input type="date" class="form-control" id="paymentDate" name="payment_date" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Reference Number</label>
                        <input type="text" class="form-control" id="paymentReference" name="reference_number" placeholder="e.g., M-Pesa code, cheque number">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea class="form-control" id="paymentNotes" name="notes" rows="2"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" onclick="savePayment()">Record Payment</button>
            </div>
        </div>
    </div>
</div>

<script>
const csrfToken = '<?php echo generateCSRFToken(); ?>';
let currentBookingId = null;

document.addEventListener('DOMContentLoaded', function() {
    loadDashboardStats();
    loadBookings();
    loadEventTypes();
    loadVenues();
});

function loadDashboardStats() {
    fetch('api/events-api.php?action=get_dashboard_stats')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('statTotalBookings').textContent = data.data.total_bookings || 0;
                document.getElementById('statConfirmedBookings').textContent = data.data.confirmed_bookings || 0;
                document.getElementById('statTotalRevenue').textContent = formatCurrency(data.data.total_revenue || 0);
                document.getElementById('statUpcomingEvents').textContent = data.data.upcoming_events || 0;
            }
        });
}

function loadBookings() {
    const filters = {
        status: document.getElementById('filterStatus').value,
        date_from: document.getElementById('filterDateFrom').value,
        date_to: document.getElementById('filterDateTo').value,
        search: document.getElementById('filterSearch').value
    };

    const params = new URLSearchParams(Object.entries(filters).filter(([k, v]) => v));
    
    fetch(`api/events-api.php?action=get_bookings&${params}`)
        .then(response => response.json())
        .then(data => {
            const tbody = document.getElementById('bookingsTableBody');
            if (data.success && data.data.length > 0) {
                tbody.innerHTML = data.data.map(booking => `
                    <tr>
                        <td><strong>${booking.booking_number}</strong></td>
                        <td>${booking.event_title}</td>
                        <td>${booking.customer_name}</td>
                        <td>${booking.venue_name || 'N/A'}</td>
                        <td>${formatDate(booking.event_date)}<br><small>${booking.start_time} - ${booking.end_time}</small></td>
                        <td>${booking.expected_guests}</td>
                        <td>${formatCurrency(booking.total_amount)}</td>
                        <td><span class="badge bg-${getStatusColor(booking.status)}">${booking.status}</span></td>
                        <td>
                            <button class="btn btn-sm btn-outline-primary" onclick="viewBooking(${booking.id})">
                                <i class="bi bi-eye"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-success" onclick="editBooking(${booking.id})">
                                <i class="bi bi-pencil"></i>
                            </button>
                        </td>
                    </tr>
                `).join('');
            } else {
                tbody.innerHTML = '<tr><td colspan="9" class="text-center text-muted">No bookings found</td></tr>';
            }
        });
}

function loadEventTypes() {
    fetch('api/events-api.php?action=get_event_types')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const select = document.getElementById('eventTypeId');
                select.innerHTML = '<option value="">Select Event Type</option>' +
                    data.data.map(type => `<option value="${type.id}">${type.type_name}</option>`).join('');
            }
        });
}

function loadVenues() {
    fetch('api/events-api.php?action=get_venues')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const select = document.getElementById('venueId');
                select.innerHTML = '<option value="">Select Venue</option>' +
                    data.data.map(venue => `<option value="${venue.id}">${venue.venue_name} (${venue.capacity_seated} seated)</option>`).join('');
                
                const tbody = document.getElementById('venuesTableBody');
                tbody.innerHTML = data.data.map(venue => `
                    <tr>
                        <td>${venue.venue_name}</td>
                        <td>${venue.venue_type}</td>
                        <td>${venue.capacity_seated} seated / ${venue.capacity_standing} standing</td>
                        <td>Full Day: ${formatCurrency(venue.full_day_rate)}</td>
                        <td><span class="badge bg-${venue.is_active ? 'success' : 'secondary'}">${venue.is_active ? 'Active' : 'Inactive'}</span></td>
                    </tr>
                `).join('');
            }
        });
}

function showBookingModal() {
    currentBookingId = null;
    document.getElementById('bookingForm').reset();
    document.getElementById('bookingModalTitle').textContent = 'New Event Booking';
    new bootstrap.Modal(document.getElementById('bookingModal')).show();
}

function showVenuesModal() {
    loadVenues();
    new bootstrap.Modal(document.getElementById('venuesModal')).show();
}

function showAddServiceModal() {
    if (!currentBookingId) {
        showNotification('Error', 'Please save the booking first before adding services', 'warning');
        return;
    }
    document.getElementById('addServiceForm').reset();
    document.getElementById('serviceBookingId').value = currentBookingId;
    loadAvailableServices();
    new bootstrap.Modal(document.getElementById('addServiceModal')).show();
}

function showRecordPaymentModal() {
    if (!currentBookingId) {
        showNotification('Error', 'Please save the booking first before recording payments', 'warning');
        return;
    }
    document.getElementById('recordPaymentForm').reset();
    document.getElementById('paymentBookingId').value = currentBookingId;
    document.getElementById('paymentDate').valueAsDate = new Date();
    new bootstrap.Modal(document.getElementById('recordPaymentModal')).show();
}

function loadAvailableServices() {
    fetch('api/events-api.php?action=get_services')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const select = document.getElementById('serviceId');
                select.innerHTML = '<option value="">Select Service</option>' +
                    data.data.map(service => `<option value="${service.id}" data-price="${service.unit_price}">${service.service_name} - ${service.category}</option>`).join('');
            }
        });
}

function updateServicePrice() {
    const select = document.getElementById('serviceId');
    const selectedOption = select.options[select.selectedIndex];
    if (selectedOption && selectedOption.dataset.price) {
        document.getElementById('serviceUnitPrice').value = selectedOption.dataset.price;
        calculateServiceTotal();
    }
}

function calculateServiceTotal() {
    const quantity = parseFloat(document.getElementById('serviceQuantity').value) || 0;
    const unitPrice = parseFloat(document.getElementById('serviceUnitPrice').value) || 0;
    const subtotal = quantity * unitPrice;
    document.getElementById('serviceSubtotal').value = subtotal.toFixed(2);
}

function saveService() {
    const form = document.getElementById('addServiceForm');
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }

    const formData = new FormData(form);
    const data = Object.fromEntries(formData.entries());

    fetch('api/events-api.php?action=add_booking_service', {
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
            showNotification('Success', 'Service added successfully', 'success');
            bootstrap.Modal.getInstance(document.getElementById('addServiceModal')).hide();
            loadBookingServices(currentBookingId);
        } else {
            showNotification('Error', data.message, 'danger');
        }
    });
}

function savePayment() {
    const form = document.getElementById('recordPaymentForm');
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }

    const formData = new FormData(form);
    const data = Object.fromEntries(formData.entries());

    fetch('api/events-api.php?action=record_payment', {
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
            showNotification('Success', 'Payment recorded successfully', 'success');
            bootstrap.Modal.getInstance(document.getElementById('recordPaymentModal')).hide();
            loadBookingPayments(currentBookingId);
            loadBookings();
            loadDashboardStats();
        } else {
            showNotification('Error', data.message, 'danger');
        }
    });
}

function loadBookingServices(bookingId) {
    fetch(`api/events-api.php?action=get_booking_services&booking_id=${bookingId}`)
        .then(response => response.json())
        .then(data => {
            const tbody = document.getElementById('servicesTableBody');
            if (data.success && data.data.length > 0) {
                tbody.innerHTML = data.data.map(service => `
                    <tr>
                        <td>${service.service_name}</td>
                        <td>${service.category}</td>
                        <td>${service.quantity}</td>
                        <td>${formatCurrency(service.unit_price)}</td>
                        <td>${formatCurrency(service.subtotal)}</td>
                        <td>
                            <button class="btn btn-sm btn-outline-danger" onclick="removeService(${service.id})">
                                <i class="bi bi-trash"></i>
                            </button>
                        </td>
                    </tr>
                `).join('');
            } else {
                tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted">No services added yet</td></tr>';
            }
        });
}

function loadBookingPayments(bookingId) {
    fetch(`api/events-api.php?action=get_booking_payments&booking_id=${bookingId}`)
        .then(response => response.json())
        .then(data => {
            const tbody = document.getElementById('paymentsTableBody');
            if (data.success && data.data.length > 0) {
                tbody.innerHTML = data.data.map(payment => `
                    <tr>
                        <td>${payment.payment_number}</td>
                        <td>${formatDate(payment.payment_date)}</td>
                        <td>${payment.payment_type}</td>
                        <td>${payment.payment_method}</td>
                        <td>${formatCurrency(payment.amount)}</td>
                        <td>${payment.reference_number || 'N/A'}</td>
                    </tr>
                `).join('');
            } else {
                tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted">No payments recorded yet</td></tr>';
            }
        });
}

function removeService(serviceId) {
    if (confirm('Remove this service?')) {
        fetch('api/events-api.php?action=remove_booking_service', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken
            },
            body: JSON.stringify({ id: serviceId })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('Success', 'Service removed', 'success');
                loadBookingServices(currentBookingId);
            } else {
                showNotification('Error', data.message, 'danger');
            }
        });
    }
}

function checkVenueAvailability() {
    const venueId = document.getElementById('venueId').value;
    const date = document.getElementById('eventDate').value;
    const startTime = document.getElementById('startTime').value;
    const endTime = document.getElementById('endTime').value;

    if (venueId && date && startTime && endTime) {
        const params = new URLSearchParams({
            venue_id: venueId,
            date: date,
            start_time: startTime,
            end_time: endTime
        });

        if (currentBookingId) {
            params.append('exclude_booking_id', currentBookingId);
        }

        fetch(`api/events-api.php?action=check_venue_availability&${params}`)
            .then(response => response.json())
            .then(data => {
                const alert = document.getElementById('availabilityAlert');
                if (data.success) {
                    if (data.available) {
                        alert.innerHTML = '<div class="alert alert-success"><i class="bi bi-check-circle"></i> Venue is available</div>';
                    } else {
                        alert.innerHTML = '<div class="alert alert-danger"><i class="bi bi-exclamation-triangle"></i> Venue is not available at this time</div>';
                    }
                }
            });
    }
}

function calculateTotal() {
    const venueRate = parseFloat(document.getElementById('venueRate').value) || 0;
    const discount = parseFloat(document.getElementById('discountAmount').value) || 0;
    const total = venueRate - discount;
    document.getElementById('totalAmount').value = total.toFixed(2);
}

function saveBooking() {
    const form = document.getElementById('bookingForm');
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }

    const formData = new FormData(form);
    const data = Object.fromEntries(formData.entries());

    const action = currentBookingId ? 'update_booking' : 'create_booking';
    
    fetch(`api/events-api.php?action=${action}`, {
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
            showNotification('Success', data.message, 'success');
            bootstrap.Modal.getInstance(document.getElementById('bookingModal')).hide();
            loadBookings();
            loadDashboardStats();
        } else {
            showNotification('Error', data.message, 'danger');
        }
    });
}

function editBooking(id) {
    currentBookingId = id;
    fetch(`api/events-api.php?action=get_booking&id=${id}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const booking = data.data;
                document.getElementById('bookingId').value = booking.id;
                document.getElementById('eventTypeId').value = booking.event_type_id;
                document.getElementById('venueId').value = booking.venue_id;
                document.getElementById('customerName').value = booking.customer_name;
                document.getElementById('customerPhone').value = booking.customer_phone;
                document.getElementById('customerEmail').value = booking.customer_email || '';
                document.getElementById('customerCompany').value = booking.customer_company || '';
                document.getElementById('eventTitle').value = booking.event_title;
                document.getElementById('eventDate').value = booking.event_date;
                document.getElementById('startTime').value = booking.start_time;
                document.getElementById('endTime').value = booking.end_time;
                document.getElementById('expectedGuests').value = booking.expected_guests;
                document.getElementById('bookingStatus').value = booking.status;
                document.getElementById('eventDescription').value = booking.event_description || '';
                document.getElementById('specialRequests').value = booking.special_requests || '';
                document.getElementById('venueRate').value = booking.venue_rate;
                document.getElementById('discountAmount').value = booking.discount_amount;
                document.getElementById('totalAmount').value = booking.total_amount;
                
                loadBookingServices(id);
                loadBookingPayments(id);
                
                document.getElementById('bookingModalTitle').textContent = 'Edit Booking - ' + booking.booking_number;
                new bootstrap.Modal(document.getElementById('bookingModal')).show();
            }
        });
}

function viewBooking(id) {
    editBooking(id);
}

function getStatusColor(status) {
    const colors = {
        'inquiry': 'secondary',
        'pending': 'warning',
        'confirmed': 'primary',
        'deposit_paid': 'info',
        'fully_paid': 'success',
        'completed': 'success',
        'cancelled': 'danger',
        'no_show': 'dark'
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
