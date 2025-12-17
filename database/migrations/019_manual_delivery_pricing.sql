-- Migration: Manual Delivery Pricing Mode
-- Purpose: Add setting to enable manual delivery pricing for clients without Google Maps subscription
-- Date: 2025-12-17

-- Add manual pricing mode setting
INSERT INTO settings (setting_key, setting_value, setting_type, category, description, created_at, updated_at)
VALUES (
    'delivery_manual_pricing_mode',
    '0',
    'boolean',
    'delivery_logistics',
    'Enable manual delivery pricing mode (no Google Maps API required)',
    NOW(),
    NOW()
)
ON DUPLICATE KEY UPDATE 
    description = 'Enable manual delivery pricing mode (no Google Maps API required)',
    updated_at = NOW();

-- Add setting for manual pricing instructions
INSERT INTO settings (setting_key, setting_value, setting_type, category, description, created_at, updated_at)
VALUES (
    'delivery_manual_pricing_instructions',
    'Enter delivery fee manually based on distance or flat rate',
    'text',
    'delivery_logistics',
    'Instructions shown when manual pricing mode is enabled',
    NOW(),
    NOW()
)
ON DUPLICATE KEY UPDATE 
    description = 'Instructions shown when manual pricing mode is enabled',
    updated_at = NOW();
