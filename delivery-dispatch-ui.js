/**
 * Delivery Dispatch UI Components
 * Provides auto-assign functionality and rider suggestions modal
 */

// Auto-assign delivery to optimal rider
async function autoAssignDelivery(deliveryId, priority = 'normal') {
    if (!deliveryId) {
        showNotification('error', 'Invalid delivery ID');
        return;
    }

    const button = event?.target?.closest('button');
    if (button) {
        button.disabled = true;
        button.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Assigning...';
    }

    try {
        const response = await fetch('/api/delivery-dispatch.php?action=auto_assign', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                delivery_id: deliveryId,
                priority: priority
            })
        });

        const result = await response.json();

        if (result.success) {
            const rider = result.assigned_rider;
            showNotification('success', 
                `✅ Assigned to ${rider.rider_name}\n` +
                `📍 Distance: ${rider.distance_km} km\n` +
                `⏱️ ETA: ${rider.duration_minutes} min`
            );
            
            // Reload page or refresh delivery list
            setTimeout(() => location.reload(), 2000);
        } else {
            if (result.error === 'no_riders_available') {
                showNotification('warning', '⚠️ No riders available. Please assign manually.');
            } else if (result.error === 'route_calculation_failed') {
                showNotification('error', '❌ Could not calculate routes. Please try again.');
            } else {
                showNotification('error', result.error || 'Assignment failed');
            }
            
            if (button) {
                button.disabled = false;
                button.innerHTML = '<i class="bi bi-lightning-fill me-1"></i>Auto-Assign';
            }
        }
    } catch (error) {
        console.error('Auto-assign error:', error);
        showNotification('error', 'Network error. Please try again.');
        
        if (button) {
            button.disabled = false;
            button.innerHTML = '<i class="bi bi-lightning-fill me-1"></i>Auto-Assign';
        }
    }
}

// Show rider suggestions modal
async function showRiderSuggestions(deliveryLat, deliveryLng, deliveryId = null) {
    if (!deliveryLat || !deliveryLng) {
        showNotification('error', 'Delivery coordinates missing');
        return;
    }

    // Show loading modal
    const modalHtml = `
        <div class="modal fade" id="riderSuggestionsModal" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="bi bi-people-fill me-2"></i>Rider Suggestions</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body text-center py-5">
                        <div class="spinner-border text-primary mb-3" role="status"></div>
                        <p class="text-muted">Calculating optimal riders...</p>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    // Remove existing modal if any
    const existingModal = document.getElementById('riderSuggestionsModal');
    if (existingModal) {
        existingModal.remove();
    }
    
    document.body.insertAdjacentHTML('beforeend', modalHtml);
    const modal = new bootstrap.Modal(document.getElementById('riderSuggestionsModal'));
    modal.show();

    try {
        const response = await fetch('/api/delivery-dispatch.php?action=find_optimal_rider', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                delivery_lat: deliveryLat,
                delivery_lng: deliveryLng
            })
        });

        const result = await response.json();

        if (result.success && result.data) {
            displayRiderSuggestions(result.data, deliveryId);
        } else {
            throw new Error(result.error || 'Failed to get rider suggestions');
        }
    } catch (error) {
        console.error('Rider suggestions error:', error);
        document.querySelector('#riderSuggestionsModal .modal-body').innerHTML = `
            <div class="alert alert-danger">
                <i class="bi bi-exclamation-triangle me-2"></i>
                ${error.message || 'Failed to load rider suggestions'}
            </div>
        `;
    }
}

// Display rider suggestions in modal
function displayRiderSuggestions(data, deliveryId) {
    const optimal = data.optimal_rider;
    const alternatives = data.alternatives || [];
    const manualMode = data.manual_mode || false;

    let html = `
        <div class="alert alert-info mb-3">
            <i class="bi bi-info-circle me-2"></i>
            <strong>Selection Criteria:</strong> ${data.selection_criteria.primary}
            ${manualMode ? '<span class="badge bg-warning ms-2">Manual Mode</span>' : ''}
        </div>
    `;

    // Optimal rider
    html += `
        <div class="card border-success mb-3">
            <div class="card-header bg-success text-white">
                <i class="bi bi-trophy-fill me-2"></i><strong>Recommended Rider</strong>
            </div>
            <div class="card-body">
                ${renderRiderCard(optimal, true, deliveryId)}
            </div>
        </div>
    `;

    // Alternatives
    if (alternatives.length > 0) {
        html += `<h6 class="text-muted mb-3">Alternative Options</h6>`;
        alternatives.forEach((rider, index) => {
            html += `
                <div class="card mb-2">
                    <div class="card-body">
                        ${renderRiderCard(rider, false, deliveryId)}
                    </div>
                </div>
            `;
        });
    }

    // Summary
    html += `
        <div class="mt-3 text-muted small">
            <i class="bi bi-graph-up me-1"></i>
            Evaluated ${data.total_candidates} riders, ${data.successful_calculations} routes calculated
        </div>
    `;

    document.querySelector('#riderSuggestionsModal .modal-body').innerHTML = html;
}

// Render individual rider card
function renderRiderCard(rider, isOptimal, deliveryId) {
    const hasGps = rider.has_gps_location;
    const capacityPercent = (rider.current_deliveries / rider.max_capacity) * 100;
    
    let html = `
        <div class="d-flex justify-content-between align-items-start mb-2">
            <div>
                <h6 class="mb-1">
                    ${escapeHtml(rider.rider_name)}
                    ${isOptimal ? '<span class="badge bg-success ms-2">Best Match</span>' : ''}
                </h6>
                <small class="text-muted">
                    <i class="bi bi-telephone me-1"></i>${escapeHtml(rider.rider_phone)}
                    <br>
                    <i class="bi bi-${rider.vehicle_type === 'bike' ? 'bicycle' : 'car-front'} me-1"></i>
                    ${escapeHtml(rider.vehicle_type)} - ${escapeHtml(rider.vehicle_number)}
                </small>
            </div>
            <div class="text-end">
                <div class="badge bg-primary-subtle text-primary mb-1">
                    Score: ${rider.score}
                </div>
            </div>
        </div>

        <div class="row g-2 mb-2">
            <div class="col-6">
                <div class="d-flex align-items-center">
                    <i class="bi bi-clock text-primary me-2"></i>
                    <div>
                        <small class="text-muted d-block">Duration</small>
                        <strong>${rider.duration_minutes} min</strong>
                    </div>
                </div>
            </div>
            <div class="col-6">
                <div class="d-flex align-items-center">
                    <i class="bi bi-signpost text-info me-2"></i>
                    <div>
                        <small class="text-muted d-block">Distance</small>
                        <strong>${rider.distance_km} km</strong>
                    </div>
                </div>
            </div>
        </div>

        <div class="mb-2">
            <small class="text-muted">Capacity:</small>
            <div class="progress" style="height: 20px;">
                <div class="progress-bar ${capacityPercent >= 80 ? 'bg-warning' : 'bg-success'}" 
                     style="width: ${capacityPercent}%">
                    ${rider.current_deliveries}/${rider.max_capacity}
                </div>
            </div>
        </div>

        <div class="d-flex gap-2 align-items-center">
            ${hasGps ? 
                '<span class="badge bg-success-subtle text-success"><i class="bi bi-geo-alt-fill me-1"></i>GPS Active</span>' : 
                '<span class="badge bg-warning-subtle text-warning"><i class="bi bi-geo-alt me-1"></i>No GPS</span>'
            }
            ${rider.location_updated_at ? 
                `<small class="text-muted">Updated ${formatRelativeTime(rider.location_updated_at)}</small>` : 
                ''
            }
        </div>
    `;

    // Add assign button if deliveryId provided
    if (deliveryId && isOptimal) {
        html += `
            <button class="btn btn-success w-100 mt-3" onclick="assignRiderToDelivery(${deliveryId}, ${rider.rider_id})">
                <i class="bi bi-check-circle me-1"></i>Assign This Rider
            </button>
        `;
    }

    return html;
}

// Assign specific rider to delivery
async function assignRiderToDelivery(deliveryId, riderId) {
    if (!deliveryId || !riderId) {
        showNotification('error', 'Invalid delivery or rider ID');
        return;
    }

    const button = event?.target;
    if (button) {
        button.disabled = true;
        button.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Assigning...';
    }

    try {
        // Use existing delivery assignment endpoint
        const formData = new FormData();
        formData.append('action', 'assign_rider');
        formData.append('delivery_id', deliveryId);
        formData.append('rider_id', riderId);

        const response = await fetch('/api/delivery.php', {
            method: 'POST',
            body: formData
        });

        const result = await response.json();

        if (result.success) {
            showNotification('success', '✅ Rider assigned successfully');
            
            // Close modal and reload
            bootstrap.Modal.getInstance(document.getElementById('riderSuggestionsModal'))?.hide();
            setTimeout(() => location.reload(), 1500);
        } else {
            throw new Error(result.error || 'Assignment failed');
        }
    } catch (error) {
        console.error('Assignment error:', error);
        showNotification('error', error.message || 'Failed to assign rider');
        
        if (button) {
            button.disabled = false;
            button.innerHTML = '<i class="bi bi-check-circle me-1"></i>Assign This Rider';
        }
    }
}

// Show notification helper
function showNotification(type, message) {
    const alertClass = type === 'success' ? 'alert-success' : 
                      type === 'warning' ? 'alert-warning' : 
                      type === 'error' ? 'alert-danger' : 'alert-info';
    
    const icon = type === 'success' ? 'check-circle' : 
                type === 'warning' ? 'exclamation-triangle' : 
                type === 'error' ? 'x-circle' : 'info-circle';

    const alertHtml = `
        <div class="alert ${alertClass} alert-dismissible fade show position-fixed top-0 start-50 translate-middle-x mt-3" 
             style="z-index: 9999; min-width: 300px; max-width: 500px;" role="alert">
            <i class="bi bi-${icon} me-2"></i>${message.replace(/\n/g, '<br>')}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;
    
    document.body.insertAdjacentHTML('beforeend', alertHtml);
    
    // Auto-dismiss after 5 seconds
    setTimeout(() => {
        const alerts = document.querySelectorAll('.alert');
        alerts[alerts.length - 1]?.remove();
    }, 5000);
}

// Format relative time
function formatRelativeTime(dateString) {
    if (!dateString) return '';
    
    const date = new Date(dateString);
    const diff = Date.now() - date.getTime();
    const minutes = Math.round(diff / 60000);
    
    if (minutes < 1) return 'just now';
    if (minutes < 60) return `${minutes} min ago`;
    
    const hours = Math.round(minutes / 60);
    if (hours < 24) return `${hours} hr ago`;
    
    const days = Math.round(hours / 24);
    return `${days} day${days !== 1 ? 's' : ''} ago`;
}

// Escape HTML helper
function escapeHtml(value) {
    if (value === null || value === undefined) return '';
    return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}
