-- Create Rider Account Script
-- Run this SQL to create a rider account

-- First, create the user account
INSERT INTO users (username, password, full_name, email, role, is_active)
VALUES (
    'rider1',                                    -- Change username
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',  -- Password: 'password' - CHANGE THIS!
    'John Rider',                                -- Change full name
    'rider1@example.com',                        -- Change email
    'rider',
    1
);

-- Get the user ID that was just created
SET @user_id = LAST_INSERT_ID();

-- Create the rider profile
INSERT INTO riders (user_id, name, phone, vehicle_type, vehicle_number, status)
VALUES (
    @user_id,
    'John Rider',                                -- Same as full name
    '+254712345678',                             -- Change phone number
    'motorcycle',                                -- Options: motorcycle, car, bicycle, van
    'KAA 123A',                                  -- Change vehicle number
    'available'                                  -- Status: available, busy, offline
);

-- Verify the accounts were created
SELECT 
    u.id,
    u.username,
    u.full_name,
    u.role,
    u.is_active,
    r.id as rider_id,
    r.phone,
    r.vehicle_type,
    r.vehicle_number,
    r.status
FROM users u
LEFT JOIN riders r ON u.id = r.user_id
WHERE u.username = 'rider1';
