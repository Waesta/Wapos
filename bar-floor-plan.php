<?php
/**
 * Bar Floor Plan Editor
 * 
 * Visual drag-drop layout for:
 * - Bar counter positions
 * - Table placements
 * - Station assignments
 * - Capacity management
 */

require_once 'includes/bootstrap.php';
$auth->requireRole(['admin', 'manager']);

$db = Database::getInstance();

// Get existing tables
$tables = $db->fetchAll("
    SELECT t.*, 
           (SELECT COUNT(*) FROM orders WHERE table_id = t.id AND status IN ('pending', 'preparing', 'ready')) as active_orders
    FROM restaurant_tables t 
    WHERE t.is_active = 1 
    ORDER BY t.table_number
");

// Get bar stations
$stations = $db->fetchAll("SELECT * FROM bar_stations WHERE is_active = 1 ORDER BY display_order");

// Get floor plan layout if exists
$floorPlan = $db->fetchOne("SELECT * FROM settings WHERE setting_key = 'bar_floor_plan'");
$layoutData = $floorPlan ? json_decode($floorPlan['setting_value'], true) : [];

$csrfToken = generateCSRFToken();
$pageTitle = 'Bar Floor Plan';
include 'includes/header.php';
?>

<style>
    .floor-plan-container {
        display: grid;
        grid-template-columns: 280px 1fr;
        gap: 1rem;
        height: calc(100vh - 140px);
    }
    
    .sidebar-panel {
        background: var(--bs-body-bg);
        border: 1px solid var(--bs-border-color);
        border-radius: 0.5rem;
        overflow: hidden;
        display: flex;
        flex-direction: column;
    }
    
    .sidebar-header {
        padding: 1rem;
        border-bottom: 1px solid var(--bs-border-color);
        background: var(--bs-tertiary-bg);
    }
    
    .sidebar-content {
        flex: 1;
        overflow-y: auto;
        padding: 1rem;
    }
    
    .element-palette {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 0.5rem;
        margin-bottom: 1rem;
    }
    
    .palette-item {
        padding: 0.75rem;
        border: 2px dashed var(--bs-border-color);
        border-radius: 0.5rem;
        text-align: center;
        cursor: grab;
        transition: all 0.2s;
        font-size: 0.85rem;
    }
    
    .palette-item:hover {
        border-color: var(--bs-primary);
        background: rgba(var(--bs-primary-rgb), 0.1);
    }
    
    .palette-item i {
        font-size: 1.5rem;
        display: block;
        margin-bottom: 0.25rem;
    }
    
    .palette-item.table { color: #0d6efd; }
    .palette-item.bar-counter { color: #6f42c1; }
    .palette-item.station { color: #17a2b8; }
    .palette-item.wall { color: #6c757d; }
    .palette-item.door { color: #198754; }
    .palette-item.plant { color: #20c997; }
    
    .canvas-container {
        background: var(--bs-body-bg);
        border: 1px solid var(--bs-border-color);
        border-radius: 0.5rem;
        overflow: hidden;
        display: flex;
        flex-direction: column;
    }
    
    .canvas-toolbar {
        padding: 0.75rem 1rem;
        border-bottom: 1px solid var(--bs-border-color);
        background: var(--bs-tertiary-bg);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .canvas-area {
        flex: 1;
        position: relative;
        overflow: auto;
        background: 
            linear-gradient(90deg, rgba(0,0,0,0.03) 1px, transparent 1px),
            linear-gradient(rgba(0,0,0,0.03) 1px, transparent 1px);
        background-size: 20px 20px;
    }
    
    .floor-canvas {
        width: 1200px;
        height: 800px;
        position: relative;
        margin: 20px;
    }
    
    .floor-element {
        position: absolute;
        cursor: move;
        user-select: none;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-direction: column;
        border-radius: 0.5rem;
        transition: box-shadow 0.2s;
    }
    
    .floor-element:hover {
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    }
    
    .floor-element.selected {
        outline: 3px solid var(--bs-primary);
        outline-offset: 2px;
    }
    
    .floor-element.table {
        background: linear-gradient(135deg, #e3f2fd, #bbdefb);
        border: 2px solid #1976d2;
        min-width: 80px;
        min-height: 80px;
    }
    
    .floor-element.table.occupied {
        background: linear-gradient(135deg, #ffebee, #ffcdd2);
        border-color: #d32f2f;
    }
    
    .floor-element.table.reserved {
        background: linear-gradient(135deg, #fff3e0, #ffe0b2);
        border-color: #f57c00;
    }
    
    .floor-element.bar-counter {
        background: linear-gradient(135deg, #f3e5f5, #e1bee7);
        border: 2px solid #7b1fa2;
        min-width: 200px;
        min-height: 60px;
        border-radius: 30px;
    }
    
    .floor-element.station {
        background: linear-gradient(135deg, #e0f7fa, #b2ebf2);
        border: 2px solid #00838f;
        min-width: 100px;
        min-height: 100px;
    }
    
    .floor-element.wall {
        background: #455a64;
        border: none;
        min-width: 20px;
        min-height: 100px;
        border-radius: 0;
    }
    
    .floor-element.door {
        background: linear-gradient(135deg, #e8f5e9, #c8e6c9);
        border: 2px solid #388e3c;
        min-width: 60px;
        min-height: 20px;
    }
    
    .floor-element.plant {
        background: linear-gradient(135deg, #e8f5e9, #a5d6a7);
        border: 2px solid #43a047;
        min-width: 50px;
        min-height: 50px;
        border-radius: 50%;
    }
    
    .element-label {
        font-weight: 600;
        font-size: 0.9rem;
        color: #333;
    }
    
    .element-sublabel {
        font-size: 0.7rem;
        color: #666;
    }
    
    .element-icon {
        font-size: 1.5rem;
        margin-bottom: 0.25rem;
    }
    
    .resize-handle {
        position: absolute;
        width: 12px;
        height: 12px;
        background: var(--bs-primary);
        border-radius: 50%;
        cursor: se-resize;
        right: -6px;
        bottom: -6px;
    }
    
    .properties-panel {
        margin-top: 1rem;
        padding-top: 1rem;
        border-top: 1px solid var(--bs-border-color);
    }
    
    .properties-panel h6 {
        margin-bottom: 0.75rem;
    }
    
    .zoom-controls {
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    
    .zoom-controls .btn {
        padding: 0.25rem 0.5rem;
    }
    
    @media (max-width: 992px) {
        .floor-plan-container {
            grid-template-columns: 1fr;
            height: auto;
        }
        
        .sidebar-panel {
            order: 2;
        }
    }
</style>

<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="mb-1"><i class="bi bi-grid-3x3 me-2"></i>Bar Floor Plan</h4>
            <p class="text-muted mb-0">Drag elements to design your bar layout</p>
        </div>
        <div>
            <button class="btn btn-outline-secondary me-2" onclick="clearCanvas()">
                <i class="bi bi-trash me-1"></i>Clear
            </button>
            <button class="btn btn-primary" onclick="saveLayout()">
                <i class="bi bi-save me-1"></i>Save Layout
            </button>
        </div>
    </div>
    
    <div class="floor-plan-container">
        <!-- Sidebar -->
        <div class="sidebar-panel">
            <div class="sidebar-header">
                <h6 class="mb-0"><i class="bi bi-palette me-2"></i>Elements</h6>
            </div>
            <div class="sidebar-content">
                <div class="element-palette">
                    <div class="palette-item table" draggable="true" data-type="table">
                        <i class="bi bi-square"></i>
                        Table
                    </div>
                    <div class="palette-item bar-counter" draggable="true" data-type="bar-counter">
                        <i class="bi bi-cup-straw"></i>
                        Bar Counter
                    </div>
                    <div class="palette-item station" draggable="true" data-type="station">
                        <i class="bi bi-display"></i>
                        Station
                    </div>
                    <div class="palette-item wall" draggable="true" data-type="wall">
                        <i class="bi bi-dash-lg"></i>
                        Wall
                    </div>
                    <div class="palette-item door" draggable="true" data-type="door">
                        <i class="bi bi-door-open"></i>
                        Door
                    </div>
                    <div class="palette-item plant" draggable="true" data-type="plant">
                        <i class="bi bi-flower1"></i>
                        Decor
                    </div>
                </div>
                
                <h6 class="mb-2">Existing Tables</h6>
                <div class="list-group list-group-flush mb-3" style="max-height: 200px; overflow-y: auto;">
                    <?php foreach ($tables as $table): ?>
                        <div class="list-group-item list-group-item-action py-2 px-2" 
                             draggable="true" 
                             data-type="existing-table"
                             data-table-id="<?= $table['id'] ?>"
                             data-table-number="<?= htmlspecialchars($table['table_number']) ?>"
                             data-capacity="<?= $table['capacity'] ?>">
                            <div class="d-flex justify-content-between align-items-center">
                                <span><i class="bi bi-square me-2"></i>Table <?= htmlspecialchars($table['table_number']) ?></span>
                                <span class="badge bg-secondary"><?= $table['capacity'] ?> seats</span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <h6 class="mb-2">Bar Stations</h6>
                <div class="list-group list-group-flush" style="max-height: 150px; overflow-y: auto;">
                    <?php foreach ($stations as $station): ?>
                        <div class="list-group-item list-group-item-action py-2 px-2"
                             draggable="true"
                             data-type="existing-station"
                             data-station-id="<?= $station['id'] ?>"
                             data-station-name="<?= htmlspecialchars($station['name']) ?>"
                             data-station-code="<?= htmlspecialchars($station['code']) ?>">
                            <i class="bi bi-display me-2" style="color: <?= $station['color'] ?>"></i>
                            <?= htmlspecialchars($station['name']) ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- Properties Panel -->
                <div class="properties-panel" id="propertiesPanel" style="display: none;">
                    <h6><i class="bi bi-sliders me-2"></i>Properties</h6>
                    <div id="propertiesContent">
                        <!-- Populated when element selected -->
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Canvas -->
        <div class="canvas-container">
            <div class="canvas-toolbar">
                <div class="zoom-controls">
                    <button class="btn btn-outline-secondary btn-sm" onclick="zoomOut()">
                        <i class="bi bi-zoom-out"></i>
                    </button>
                    <span id="zoomLevel">100%</span>
                    <button class="btn btn-outline-secondary btn-sm" onclick="zoomIn()">
                        <i class="bi bi-zoom-in"></i>
                    </button>
                    <button class="btn btn-outline-secondary btn-sm ms-2" onclick="resetZoom()">
                        <i class="bi bi-arrows-angle-expand"></i> Fit
                    </button>
                </div>
                <div>
                    <span class="text-muted me-3">
                        <i class="bi bi-grid-3x3 me-1"></i>Grid: 20px
                    </span>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="checkbox" id="snapToGrid" checked>
                        <label class="form-check-label" for="snapToGrid">Snap to Grid</label>
                    </div>
                </div>
            </div>
            <div class="canvas-area" id="canvasArea">
                <div class="floor-canvas" id="floorCanvas">
                    <!-- Elements rendered here -->
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const CSRF_TOKEN = '<?= $csrfToken ?>';
const GRID_SIZE = 20;
let elements = <?= json_encode($layoutData['elements'] ?? []) ?>;
let selectedElement = null;
let zoom = 1;
let isDragging = false;
let dragOffset = {x: 0, y: 0};

// Initialize
document.addEventListener('DOMContentLoaded', () => {
    renderElements();
    setupDragAndDrop();
});

function setupDragAndDrop() {
    const canvas = document.getElementById('floorCanvas');
    
    // Palette drag
    document.querySelectorAll('[draggable="true"]').forEach(item => {
        item.addEventListener('dragstart', (e) => {
            e.dataTransfer.setData('text/plain', JSON.stringify({
                type: item.dataset.type,
                tableId: item.dataset.tableId,
                tableNumber: item.dataset.tableNumber,
                capacity: item.dataset.capacity,
                stationId: item.dataset.stationId,
                stationName: item.dataset.stationName,
                stationCode: item.dataset.stationCode
            }));
        });
    });
    
    // Canvas drop
    canvas.addEventListener('dragover', (e) => {
        e.preventDefault();
    });
    
    canvas.addEventListener('drop', (e) => {
        e.preventDefault();
        const data = JSON.parse(e.dataTransfer.getData('text/plain'));
        const rect = canvas.getBoundingClientRect();
        let x = (e.clientX - rect.left) / zoom;
        let y = (e.clientY - rect.top) / zoom;
        
        if (document.getElementById('snapToGrid').checked) {
            x = Math.round(x / GRID_SIZE) * GRID_SIZE;
            y = Math.round(y / GRID_SIZE) * GRID_SIZE;
        }
        
        addElement(data, x, y);
    });
}

function addElement(data, x, y) {
    const id = 'el_' + Date.now();
    let element = {
        id: id,
        type: data.type,
        x: x,
        y: y,
        width: 80,
        height: 80,
        rotation: 0
    };
    
    switch (data.type) {
        case 'table':
        case 'existing-table':
            element.label = data.tableNumber ? `T${data.tableNumber}` : 'New';
            element.sublabel = data.capacity ? `${data.capacity} seats` : '4 seats';
            element.tableId = data.tableId;
            element.type = 'table';
            break;
        case 'bar-counter':
            element.label = 'Bar Counter';
            element.width = 200;
            element.height = 60;
            break;
        case 'station':
        case 'existing-station':
            element.label = data.stationName || 'Station';
            element.stationId = data.stationId;
            element.stationCode = data.stationCode;
            element.width = 100;
            element.height = 100;
            element.type = 'station';
            break;
        case 'wall':
            element.width = 20;
            element.height = 100;
            break;
        case 'door':
            element.width = 60;
            element.height = 20;
            break;
        case 'plant':
            element.width = 50;
            element.height = 50;
            break;
    }
    
    elements.push(element);
    renderElements();
}

function renderElements() {
    const canvas = document.getElementById('floorCanvas');
    canvas.innerHTML = '';
    
    elements.forEach(el => {
        const div = document.createElement('div');
        div.className = `floor-element ${el.type}`;
        div.id = el.id;
        div.style.left = el.x + 'px';
        div.style.top = el.y + 'px';
        div.style.width = el.width + 'px';
        div.style.height = el.height + 'px';
        if (el.rotation) {
            div.style.transform = `rotate(${el.rotation}deg)`;
        }
        
        let content = '';
        if (el.type === 'table') {
            content = `
                <span class="element-icon">🪑</span>
                <span class="element-label">${el.label || 'Table'}</span>
                <span class="element-sublabel">${el.sublabel || ''}</span>
            `;
        } else if (el.type === 'station') {
            content = `
                <span class="element-icon">🖥️</span>
                <span class="element-label">${el.label || 'Station'}</span>
            `;
        } else if (el.type === 'bar-counter') {
            content = `<span class="element-label">🍸 ${el.label || 'Bar'}</span>`;
        } else if (el.type === 'plant') {
            content = `<span class="element-icon">🌿</span>`;
        } else if (el.type === 'door') {
            content = `<span class="element-icon">🚪</span>`;
        }
        
        div.innerHTML = content + '<div class="resize-handle"></div>';
        
        // Make draggable
        div.addEventListener('mousedown', (e) => {
            if (e.target.classList.contains('resize-handle')) {
                startResize(e, el);
            } else {
                startDrag(e, el);
            }
        });
        
        div.addEventListener('click', (e) => {
            e.stopPropagation();
            selectElement(el);
        });
        
        canvas.appendChild(div);
    });
}

function startDrag(e, element) {
    isDragging = true;
    selectedElement = element;
    const div = document.getElementById(element.id);
    dragOffset = {
        x: e.clientX - div.offsetLeft * zoom,
        y: e.clientY - div.offsetTop * zoom
    };
    
    document.addEventListener('mousemove', onDrag);
    document.addEventListener('mouseup', stopDrag);
}

function onDrag(e) {
    if (!isDragging || !selectedElement) return;
    
    let x = (e.clientX - dragOffset.x) / zoom;
    let y = (e.clientY - dragOffset.y) / zoom;
    
    if (document.getElementById('snapToGrid').checked) {
        x = Math.round(x / GRID_SIZE) * GRID_SIZE;
        y = Math.round(y / GRID_SIZE) * GRID_SIZE;
    }
    
    selectedElement.x = Math.max(0, x);
    selectedElement.y = Math.max(0, y);
    
    const div = document.getElementById(selectedElement.id);
    div.style.left = selectedElement.x + 'px';
    div.style.top = selectedElement.y + 'px';
}

function stopDrag() {
    isDragging = false;
    document.removeEventListener('mousemove', onDrag);
    document.removeEventListener('mouseup', stopDrag);
}

function startResize(e, element) {
    e.stopPropagation();
    const startX = e.clientX;
    const startY = e.clientY;
    const startWidth = element.width;
    const startHeight = element.height;
    
    function onResize(e) {
        let newWidth = startWidth + (e.clientX - startX) / zoom;
        let newHeight = startHeight + (e.clientY - startY) / zoom;
        
        if (document.getElementById('snapToGrid').checked) {
            newWidth = Math.round(newWidth / GRID_SIZE) * GRID_SIZE;
            newHeight = Math.round(newHeight / GRID_SIZE) * GRID_SIZE;
        }
        
        element.width = Math.max(40, newWidth);
        element.height = Math.max(40, newHeight);
        
        const div = document.getElementById(element.id);
        div.style.width = element.width + 'px';
        div.style.height = element.height + 'px';
    }
    
    function stopResize() {
        document.removeEventListener('mousemove', onResize);
        document.removeEventListener('mouseup', stopResize);
    }
    
    document.addEventListener('mousemove', onResize);
    document.addEventListener('mouseup', stopResize);
}

function selectElement(element) {
    // Deselect previous
    document.querySelectorAll('.floor-element').forEach(el => el.classList.remove('selected'));
    
    // Select new
    selectedElement = element;
    document.getElementById(element.id).classList.add('selected');
    
    // Show properties
    showProperties(element);
}

function showProperties(element) {
    const panel = document.getElementById('propertiesPanel');
    const content = document.getElementById('propertiesContent');
    
    panel.style.display = 'block';
    
    content.innerHTML = `
        <div class="mb-2">
            <label class="form-label small">Label</label>
            <input type="text" class="form-control form-control-sm" value="${element.label || ''}" 
                   onchange="updateProperty('label', this.value)">
        </div>
        <div class="row mb-2">
            <div class="col-6">
                <label class="form-label small">Width</label>
                <input type="number" class="form-control form-control-sm" value="${element.width}" 
                       onchange="updateProperty('width', parseInt(this.value))">
            </div>
            <div class="col-6">
                <label class="form-label small">Height</label>
                <input type="number" class="form-control form-control-sm" value="${element.height}" 
                       onchange="updateProperty('height', parseInt(this.value))">
            </div>
        </div>
        <div class="mb-2">
            <label class="form-label small">Rotation</label>
            <input type="range" class="form-range" min="0" max="360" value="${element.rotation || 0}" 
                   onchange="updateProperty('rotation', parseInt(this.value))">
        </div>
        <button class="btn btn-outline-danger btn-sm w-100" onclick="deleteElement()">
            <i class="bi bi-trash me-1"></i>Delete
        </button>
    `;
}

function updateProperty(prop, value) {
    if (!selectedElement) return;
    selectedElement[prop] = value;
    renderElements();
    selectElement(selectedElement);
}

function deleteElement() {
    if (!selectedElement) return;
    elements = elements.filter(el => el.id !== selectedElement.id);
    selectedElement = null;
    document.getElementById('propertiesPanel').style.display = 'none';
    renderElements();
}

function zoomIn() {
    zoom = Math.min(2, zoom + 0.1);
    applyZoom();
}

function zoomOut() {
    zoom = Math.max(0.5, zoom - 0.1);
    applyZoom();
}

function resetZoom() {
    zoom = 1;
    applyZoom();
}

function applyZoom() {
    document.getElementById('floorCanvas').style.transform = `scale(${zoom})`;
    document.getElementById('floorCanvas').style.transformOrigin = 'top left';
    document.getElementById('zoomLevel').textContent = Math.round(zoom * 100) + '%';
}

function clearCanvas() {
    if (!confirm('Clear all elements from the floor plan?')) return;
    elements = [];
    renderElements();
}

async function saveLayout() {
    try {
        const response = await fetch('api/settings.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                action: 'save_setting',
                key: 'bar_floor_plan',
                value: JSON.stringify({elements: elements}),
                csrf_token: CSRF_TOKEN
            })
        });
        const result = await response.json();
        
        if (result.success) {
            alert('Floor plan saved successfully!');
        } else {
            alert(result.message || 'Failed to save');
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Failed to save floor plan');
    }
}

// Click outside to deselect
document.getElementById('floorCanvas').addEventListener('click', (e) => {
    if (e.target.id === 'floorCanvas') {
        document.querySelectorAll('.floor-element').forEach(el => el.classList.remove('selected'));
        selectedElement = null;
        document.getElementById('propertiesPanel').style.display = 'none';
    }
});

// Keyboard shortcuts
document.addEventListener('keydown', (e) => {
    if (e.key === 'Delete' && selectedElement) {
        deleteElement();
    }
});
</script>

<?php include 'includes/footer.php'; ?>
