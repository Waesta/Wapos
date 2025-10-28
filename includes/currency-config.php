<?php
// Currency Configuration System
// This file handles currency settings and formatting

class CurrencyManager {
    private static $instance = null;
    private $db;
    private $currency_settings = [];
    
    private function __construct() {
        $this->db = Database::getInstance();
        $this->loadCurrencySettings();
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function loadCurrencySettings() {
        try {
            // Get currency settings from database
            $settings = $this->db->fetchAll("SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE 'currency_%'");
            
            foreach ($settings as $setting) {
                $this->currency_settings[$setting['setting_key']] = $setting['setting_value'];
            }
            
            // Set defaults if not found
            if (empty($this->currency_settings)) {
                $this->setDefaultCurrencySettings();
            }
        } catch (Exception $e) {
            // Fallback to defaults if database error
            $this->setDefaultCurrencySettings();
        }
    }
    
    private function setDefaultCurrencySettings() {
        $this->currency_settings = [
            'currency_code' => 'USD',
            'currency_symbol' => '$',
            'currency_name' => 'US Dollar',
            'currency_position' => 'before', // before or after
            'decimal_places' => 2,
            'decimal_separator' => '.',
            'thousands_separator' => ',',
            'currency_format' => '{symbol}{amount}' // {symbol}{amount} or {amount}{symbol}
        ];
    }
    
    public function getCurrencyCode() {
        return $this->currency_settings['currency_code'] ?? 'USD';
    }
    
    public function getCurrencySymbol() {
        return $this->currency_settings['currency_symbol'] ?? '$';
    }
    
    public function getCurrencyName() {
        return $this->currency_settings['currency_name'] ?? 'US Dollar';
    }
    
    public function formatMoney($amount, $showSymbol = false) {
        $amount = (float)$amount;
        $decimalPlaces = (int)($this->currency_settings['decimal_places'] ?? 2);
        $decimalSeparator = $this->currency_settings['decimal_separator'] ?? '.';
        $thousandsSeparator = $this->currency_settings['thousands_separator'] ?? ',';
        
        $formattedAmount = number_format($amount, $decimalPlaces, $decimalSeparator, $thousandsSeparator);
        
        if (!$showSymbol) {
            return $formattedAmount;
        }
        
        $symbol = $this->getCurrencySymbol();
        $position = $this->currency_settings['currency_position'] ?? 'before';
        
        if ($position === 'after') {
            return $formattedAmount . ' ' . $symbol;
        } else {
            return $symbol . ' ' . $formattedAmount;
        }
    }
    
    public function getJavaScriptConfig() {
        return [
            'code' => $this->getCurrencyCode(),
            'symbol' => $this->getCurrencySymbol(),
            'name' => $this->getCurrencyName(),
            'position' => $this->currency_settings['currency_position'] ?? 'before',
            'decimal_places' => (int)($this->currency_settings['decimal_places'] ?? 2),
            'decimal_separator' => $this->currency_settings['decimal_separator'] ?? '.',
            'thousands_separator' => $this->currency_settings['thousands_separator'] ?? ','
        ];
    }
    
    public function updateCurrencySettings($settings) {
        foreach ($settings as $key => $value) {
            if (strpos($key, 'currency_') === 0) {
                $this->db->query(
                    "INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) 
                     ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)",
                    [$key, $value]
                );
                $this->currency_settings[$key] = $value;
            }
        }
    }
}

// Global currency formatting function
function formatCurrency($amount, $showSymbol = false) {
    return CurrencyManager::getInstance()->formatMoney($amount, $showSymbol);
}

// Initialize default currency settings in database if they don't exist
function initializeCurrencySettings() {
    $db = Database::getInstance();
    
    $defaultSettings = [
        'currency_code' => 'USD',
        'currency_symbol' => '$',
        'currency_name' => 'US Dollar',
        'currency_position' => 'before',
        'decimal_places' => '2',
        'decimal_separator' => '.',
        'thousands_separator' => ',',
        'currency_format' => '{symbol}{amount}'
    ];
    
    foreach ($defaultSettings as $key => $value) {
        $existing = $db->fetchOne("SELECT setting_value FROM settings WHERE setting_key = ?", [$key]);
        if (!$existing) {
            $db->insert('settings', [
                'setting_key' => $key,
                'setting_value' => $value,
                'setting_type' => 'string',
                'description' => ucwords(str_replace('_', ' ', $key))
            ]);
        }
    }
}

// Auto-initialize when included
if (class_exists('Database')) {
    try {
        initializeCurrencySettings();
    } catch (Exception $e) {
        // Silently fail if database not available
        error_log('Currency settings initialization failed: ' . $e->getMessage());
    }
}
?>
