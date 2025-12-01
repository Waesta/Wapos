<?php
// Currency Configuration System
// This file handles currency settings and formatting

class CurrencyManager {
    private static $instance = null;
    private $currency_settings = [];
    
    private function __construct() {
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
            $settings = SettingsStore::getByPrefix('currency_');
            if (empty($settings)) {
                $this->setDefaultCurrencySettings();
                SettingsStore::persistMany($this->currency_settings);
                return;
            }

            if ($this->shouldNeutralizeLegacyDefaults($settings)) {
                $this->setDefaultCurrencySettings();
                SettingsStore::persistMany(array_merge($this->currency_settings, [
                    'currency_neutralized_at' => date('c'),
                ]));
                return;
            }

            $this->currency_settings = array_merge($this->defaultCurrencySettings(), $settings);
        } catch (Exception $e) {
            $this->setDefaultCurrencySettings();
        }
    }

    private function defaultCurrencySettings(): array {
        return [
            'currency_code' => '',
            'currency_symbol' => '',
            'currency_name' => 'Default Currency',
            'currency_position' => 'before', // before or after
            'decimal_places' => 2,
            'decimal_separator' => '.',
            'thousands_separator' => ',',
            'currency_format' => '{symbol}{amount}' // {symbol}{amount} or {amount}{symbol}
        ];
    }
    
    private function setDefaultCurrencySettings() {
        $this->currency_settings = $this->defaultCurrencySettings();
    }

    private function shouldNeutralizeLegacyDefaults(array $settings): bool {
        if (!empty($settings['currency_neutralized_at'])) {
            return false;
        }

        $code = strtoupper(trim($settings['currency_code'] ?? ''));
        $symbol = trim($settings['currency_symbol'] ?? '');
        $name = trim($settings['currency_name'] ?? '');

        $legacyCombos = [
            ['code' => 'KES', 'symbol' => 'KSh', 'name' => 'Kenyan Shilling'],
            ['code' => 'USD', 'symbol' => '$', 'name' => 'US Dollar'],
        ];

        foreach ($legacyCombos as $legacy) {
            if ($code === $legacy['code'] && $symbol === $legacy['symbol'] && $name === $legacy['name']) {
                return true;
            }
        }

        return false;
    }
    
    public function getCurrencyCode() {
        return trim($this->currency_settings['currency_code'] ?? '');
    }
    
    public function getCurrencySymbol() {
        return trim($this->currency_settings['currency_symbol'] ?? '');
    }
    
    public function getCurrencyName() {
        return $this->currency_settings['currency_name'] ?? 'Default Currency';
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
        if ($symbol === '') {
            return $formattedAmount;
        }
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
                SettingsStore::persist($key, $value);
                $this->currency_settings[$key] = $value;
            }
        }
        SettingsStore::persist('currency_neutralized_at', date('c'));
    }
}

// Global currency formatting function
function formatCurrency($amount, $showSymbol = false) {
    return CurrencyManager::getInstance()->formatMoney($amount, $showSymbol);
}

// Initialize default currency settings in database if they don't exist
function initializeCurrencySettings() {
    $defaultSettings = [
        'currency_code' => '',
        'currency_symbol' => '',
        'currency_name' => 'Default Currency',
        'currency_position' => 'before',
        'decimal_places' => '2',
        'decimal_separator' => '.',
        'thousands_separator' => ',',
        'currency_format' => '{symbol}{amount}'
    ];
    
    foreach ($defaultSettings as $key => $value) {
        if (!SettingsStore::has($key)) {
            SettingsStore::persist($key, $value);
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
