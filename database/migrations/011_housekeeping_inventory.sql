-- Housekeeping Inventory Management Schema
-- Adds tables for laundry, linen, supplies, and minibar tracking

-- Housekeeping inventory items table
CREATE TABLE IF NOT EXISTS housekeeping_inventory (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    section VARCHAR(50) NOT NULL COMMENT 'laundry, linen, public_area, room_amenities, minibar, cleaning',
    item_name VARCHAR(200) NOT NULL,
    item_code VARCHAR(50),
    description TEXT,
    unit VARCHAR(50) DEFAULT 'pcs',
    quantity_on_hand DECIMAL(15,4) DEFAULT 0,
    reorder_level DECIMAL(15,4) DEFAULT 0,
    cost_price DECIMAL(15,2) DEFAULT 0,
    supplier VARCHAR(200),
    location VARCHAR(100),
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_section (section),
    INDEX idx_item_code (item_code),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Linen tracking table (for individual linen items)
CREATE TABLE IF NOT EXISTS housekeeping_linen (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    inventory_id INT UNSIGNED NOT NULL,
    linen_code VARCHAR(50) UNIQUE,
    status ENUM('clean', 'in_use', 'dirty', 'washing', 'damaged', 'discarded') DEFAULT 'clean',
    room_id INT UNSIGNED NULL,
    last_washed_at TIMESTAMP NULL,
    wash_count INT DEFAULT 0,
    condition_notes TEXT,
    acquired_date DATE,
    discarded_date DATE NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_inventory (inventory_id),
    INDEX idx_status (status),
    INDEX idx_room (room_id),
    INDEX idx_code (linen_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Laundry batches table
CREATE TABLE IF NOT EXISTS housekeeping_laundry_batches (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    batch_number VARCHAR(50) NOT NULL,
    status ENUM('pending', 'washing', 'drying', 'folding', 'completed', 'cancelled') DEFAULT 'pending',
    item_count INT DEFAULT 0,
    started_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    notes TEXT,
    created_by INT UNSIGNED,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_batch (batch_number),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Laundry batch items
CREATE TABLE IF NOT EXISTS housekeeping_laundry_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    batch_id INT UNSIGNED NOT NULL,
    linen_id INT UNSIGNED NULL,
    inventory_id INT UNSIGNED NULL,
    quantity INT DEFAULT 1,
    notes VARCHAR(255),
    FOREIGN KEY (batch_id) REFERENCES housekeeping_laundry_batches(id) ON DELETE CASCADE,
    INDEX idx_batch (batch_id),
    INDEX idx_linen (linen_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Minibar par levels per room type
CREATE TABLE IF NOT EXISTS housekeeping_minibar_par (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    room_type_id INT UNSIGNED NULL,
    inventory_id INT UNSIGNED NOT NULL,
    par_level INT DEFAULT 1,
    selling_price DECIMAL(15,2) DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    UNIQUE KEY uk_room_item (room_type_id, inventory_id),
    INDEX idx_room_type (room_type_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Minibar consumption log
CREATE TABLE IF NOT EXISTS housekeeping_minibar_log (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    room_id INT UNSIGNED NOT NULL,
    booking_id INT UNSIGNED NULL,
    inventory_id INT UNSIGNED NOT NULL,
    quantity INT NOT NULL,
    unit_price DECIMAL(15,2) NOT NULL,
    total_amount DECIMAL(15,2) NOT NULL,
    charged_to_folio TINYINT(1) DEFAULT 0,
    folio_charge_id INT UNSIGNED NULL,
    recorded_by INT UNSIGNED,
    recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    notes VARCHAR(255),
    INDEX idx_room (room_id),
    INDEX idx_booking (booking_id),
    INDEX idx_date (recorded_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Inventory transactions log
CREATE TABLE IF NOT EXISTS housekeeping_inventory_log (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    inventory_id INT UNSIGNED NOT NULL,
    transaction_type ENUM('receipt', 'issue', 'adjustment', 'transfer', 'damage', 'return') NOT NULL,
    quantity DECIMAL(15,4) NOT NULL,
    reference_type VARCHAR(50),
    reference_id INT UNSIGNED,
    from_location VARCHAR(100),
    to_location VARCHAR(100),
    notes TEXT,
    user_id INT UNSIGNED,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_inventory (inventory_id),
    INDEX idx_type (transaction_type),
    INDEX idx_date (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add housekeeping_manager and housekeeping_staff roles if not exist
-- Note: This requires ALTER TABLE which may fail if roles already exist
-- The application handles this gracefully

-- Insert sample inventory items for each section
INSERT IGNORE INTO housekeeping_inventory (section, item_name, item_code, unit, reorder_level, location) VALUES
-- Linen
('linen', 'King Bed Sheet', 'LN-KBS-001', 'pcs', 20, 'Linen Store'),
('linen', 'Queen Bed Sheet', 'LN-QBS-001', 'pcs', 30, 'Linen Store'),
('linen', 'Single Bed Sheet', 'LN-SBS-001', 'pcs', 20, 'Linen Store'),
('linen', 'Pillowcase Standard', 'LN-PC-001', 'pcs', 50, 'Linen Store'),
('linen', 'Duvet Cover King', 'LN-DCK-001', 'pcs', 15, 'Linen Store'),
('linen', 'Blanket', 'LN-BLK-001', 'pcs', 20, 'Linen Store'),

-- Laundry (towels)
('laundry', 'Bath Towel', 'LY-BT-001', 'pcs', 50, 'Laundry Room'),
('laundry', 'Hand Towel', 'LY-HT-001', 'pcs', 100, 'Laundry Room'),
('laundry', 'Face Towel', 'LY-FT-001', 'pcs', 100, 'Laundry Room'),
('laundry', 'Bath Mat', 'LY-BM-001', 'pcs', 30, 'Laundry Room'),
('laundry', 'Bathrobe', 'LY-BR-001', 'pcs', 20, 'Laundry Room'),

-- Room Amenities
('room_amenities', 'Shampoo 30ml', 'RA-SH-001', 'bottles', 100, 'Amenities Store'),
('room_amenities', 'Conditioner 30ml', 'RA-CD-001', 'bottles', 100, 'Amenities Store'),
('room_amenities', 'Body Lotion 30ml', 'RA-BL-001', 'bottles', 100, 'Amenities Store'),
('room_amenities', 'Soap Bar 40g', 'RA-SB-001', 'pcs', 200, 'Amenities Store'),
('room_amenities', 'Shower Cap', 'RA-SC-001', 'pcs', 100, 'Amenities Store'),
('room_amenities', 'Dental Kit', 'RA-DK-001', 'pcs', 100, 'Amenities Store'),
('room_amenities', 'Sewing Kit', 'RA-SK-001', 'pcs', 50, 'Amenities Store'),
('room_amenities', 'Shoe Shine Kit', 'RA-SS-001', 'pcs', 50, 'Amenities Store'),

-- Minibar
('minibar', 'Mineral Water 500ml', 'MB-MW-001', 'bottles', 100, 'Minibar Store'),
('minibar', 'Coca Cola 330ml', 'MB-CC-001', 'cans', 50, 'Minibar Store'),
('minibar', 'Orange Juice 250ml', 'MB-OJ-001', 'bottles', 50, 'Minibar Store'),
('minibar', 'Pringles Original', 'MB-PR-001', 'pcs', 30, 'Minibar Store'),
('minibar', 'Chocolate Bar', 'MB-CB-001', 'pcs', 50, 'Minibar Store'),
('minibar', 'Mixed Nuts', 'MB-MN-001', 'pcs', 30, 'Minibar Store'),

-- Public Area Supplies
('public_area', 'Air Freshener Spray', 'PA-AF-001', 'bottles', 20, 'Supplies Store'),
('public_area', 'Hand Sanitizer 500ml', 'PA-HS-001', 'bottles', 30, 'Supplies Store'),
('public_area', 'Tissue Box', 'PA-TB-001', 'boxes', 50, 'Supplies Store'),

-- Cleaning Supplies
('cleaning', 'All-Purpose Cleaner 5L', 'CL-APC-001', 'bottles', 10, 'Cleaning Store'),
('cleaning', 'Glass Cleaner 1L', 'CL-GC-001', 'bottles', 15, 'Cleaning Store'),
('cleaning', 'Toilet Cleaner 1L', 'CL-TC-001', 'bottles', 20, 'Cleaning Store'),
('cleaning', 'Floor Polish 5L', 'CL-FP-001', 'bottles', 5, 'Cleaning Store'),
('cleaning', 'Disinfectant 5L', 'CL-DF-001', 'bottles', 10, 'Cleaning Store'),
('cleaning', 'Garbage Bags Large', 'CL-GB-001', 'pcs', 200, 'Cleaning Store'),
('cleaning', 'Microfiber Cloth', 'CL-MC-001', 'pcs', 50, 'Cleaning Store'),
('cleaning', 'Mop Head', 'CL-MH-001', 'pcs', 20, 'Cleaning Store');
