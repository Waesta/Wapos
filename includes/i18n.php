<?php
/**
 * WAPOS Internationalization (i18n) System
 * 
 * Provides multi-language support with:
 * - Language detection (browser, user preference, system default)
 * - Translation loading from JSON files
 * - Pluralization support
 * - Number, currency, and date formatting
 * - RTL language support
 */

class I18n
{
    private static ?I18n $instance = null;
    private string $locale;
    private string $fallbackLocale = 'en';
    private array $translations = [];
    private array $supportedLocales = [
        'en' => ['name' => 'English', 'native' => 'English', 'rtl' => false],
        'fr' => ['name' => 'French', 'native' => 'Français', 'rtl' => false],
        'es' => ['name' => 'Spanish', 'native' => 'Español', 'rtl' => false],
        'sw' => ['name' => 'Swahili', 'native' => 'Kiswahili', 'rtl' => false],
        'ar' => ['name' => 'Arabic', 'native' => 'العربية', 'rtl' => true],
        'pt' => ['name' => 'Portuguese', 'native' => 'Português', 'rtl' => false],
        'de' => ['name' => 'German', 'native' => 'Deutsch', 'rtl' => false],
        'zh' => ['name' => 'Chinese', 'native' => '中文', 'rtl' => false],
    ];

    private function __construct()
    {
        $this->locale = $this->detectLocale();
        $this->loadTranslations();
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Detect the best locale to use
     */
    private function detectLocale(): string
    {
        // 1. Check user preference (stored in session/settings)
        if (isset($_SESSION['locale']) && $this->isSupported($_SESSION['locale'])) {
            return $_SESSION['locale'];
        }

        // 2. Check system setting (with error handling for early initialization)
        try {
            if (function_exists('settings')) {
                $systemLocale = settings('system_locale') ?? null;
                if ($systemLocale && $this->isSupported($systemLocale)) {
                    return $systemLocale;
                }
            }
        } catch (Throwable $e) {
            // Settings not available yet, continue with fallback
        }

        // 3. Check browser Accept-Language header
        if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
            $browserLocales = $this->parseAcceptLanguage($_SERVER['HTTP_ACCEPT_LANGUAGE']);
            foreach ($browserLocales as $locale) {
                if ($this->isSupported($locale)) {
                    return $locale;
                }
                // Try base language (e.g., 'en' from 'en-US')
                $base = substr($locale, 0, 2);
                if ($this->isSupported($base)) {
                    return $base;
                }
            }
        }

        // 4. Fall back to default
        return $this->fallbackLocale;
    }

    /**
     * Parse Accept-Language header
     */
    private function parseAcceptLanguage(string $header): array
    {
        $locales = [];
        $parts = explode(',', $header);
        
        foreach ($parts as $part) {
            $part = trim($part);
            $q = 1.0;
            
            if (strpos($part, ';q=') !== false) {
                [$part, $qPart] = explode(';q=', $part);
                $q = (float)$qPart;
            }
            
            $locales[$part] = $q;
        }
        
        arsort($locales);
        return array_keys($locales);
    }

    /**
     * Check if a locale is supported
     */
    public function isSupported(string $locale): bool
    {
        return isset($this->supportedLocales[$locale]);
    }

    /**
     * Load translations for current locale
     */
    private function loadTranslations(): void
    {
        $path = ROOT_PATH . '/lang/' . $this->locale . '.json';
        
        if (file_exists($path)) {
            $content = file_get_contents($path);
            $this->translations = json_decode($content, true) ?? [];
        }

        // Load fallback if different
        if ($this->locale !== $this->fallbackLocale) {
            $fallbackPath = ROOT_PATH . '/lang/' . $this->fallbackLocale . '.json';
            if (file_exists($fallbackPath)) {
                $fallbackContent = file_get_contents($fallbackPath);
                $fallbackTranslations = json_decode($fallbackContent, true) ?? [];
                // Merge with fallback (current locale takes precedence)
                $this->translations = array_merge($fallbackTranslations, $this->translations);
            }
        }
    }

    /**
     * Get current locale
     */
    public function getLocale(): string
    {
        return $this->locale;
    }

    /**
     * Set locale
     */
    public function setLocale(string $locale): void
    {
        if ($this->isSupported($locale)) {
            $this->locale = $locale;
            $_SESSION['locale'] = $locale;
            $this->loadTranslations();
        }
    }

    /**
     * Get all supported locales
     */
    public function getSupportedLocales(): array
    {
        return $this->supportedLocales;
    }

    /**
     * Check if current locale is RTL
     */
    public function isRtl(): bool
    {
        return $this->supportedLocales[$this->locale]['rtl'] ?? false;
    }

    /**
     * Translate a key
     */
    public function translate(string $key, array $params = []): string
    {
        // Support nested keys with dot notation
        $translation = $this->getNestedValue($this->translations, $key);
        
        if ($translation === null) {
            // Return key if no translation found
            return $key;
        }

        // Replace parameters
        foreach ($params as $param => $value) {
            $translation = str_replace(':' . $param, $value, $translation);
            $translation = str_replace('{' . $param . '}', $value, $translation);
        }

        return $translation;
    }

    /**
     * Get nested array value using dot notation
     */
    private function getNestedValue(array $array, string $key)
    {
        $keys = explode('.', $key);
        $value = $array;

        foreach ($keys as $k) {
            if (!isset($value[$k])) {
                return null;
            }
            $value = $value[$k];
        }

        return is_string($value) ? $value : null;
    }

    /**
     * Pluralize a translation
     */
    public function plural(string $key, int $count, array $params = []): string
    {
        $params['count'] = $count;
        
        // Try specific plural forms
        if ($count === 0) {
            $translation = $this->getNestedValue($this->translations, $key . '.zero');
        } elseif ($count === 1) {
            $translation = $this->getNestedValue($this->translations, $key . '.one');
        } else {
            $translation = $this->getNestedValue($this->translations, $key . '.other');
        }

        // Fall back to base key
        if ($translation === null) {
            $translation = $this->getNestedValue($this->translations, $key);
        }

        if ($translation === null) {
            return $key;
        }

        // Replace parameters
        foreach ($params as $param => $value) {
            $translation = str_replace(':' . $param, $value, $translation);
            $translation = str_replace('{' . $param . '}', $value, $translation);
        }

        return $translation;
    }

    /**
     * Format a number according to locale
     */
    public function formatNumber(float $number, int $decimals = 2): string
    {
        $formatter = new NumberFormatter($this->locale, NumberFormatter::DECIMAL);
        $formatter->setAttribute(NumberFormatter::FRACTION_DIGITS, $decimals);
        return $formatter->format($number);
    }

    /**
     * Format currency according to locale
     */
    public function formatCurrency(float $amount, ?string $currency = null): string
    {
        $currency = $currency ?? (settings('currency_code') ?? 'USD');
        $formatter = new NumberFormatter($this->locale, NumberFormatter::CURRENCY);
        return $formatter->formatCurrency($amount, $currency);
    }

    /**
     * Format date according to locale
     */
    public function formatDate($date, string $format = 'medium'): string
    {
        if (is_string($date)) {
            $date = new DateTime($date);
        }

        $formats = [
            'short' => IntlDateFormatter::SHORT,
            'medium' => IntlDateFormatter::MEDIUM,
            'long' => IntlDateFormatter::LONG,
            'full' => IntlDateFormatter::FULL,
        ];

        $dateFormat = $formats[$format] ?? IntlDateFormatter::MEDIUM;
        
        $formatter = new IntlDateFormatter(
            $this->locale,
            $dateFormat,
            IntlDateFormatter::NONE
        );

        return $formatter->format($date);
    }

    /**
     * Format datetime according to locale
     */
    public function formatDateTime($date, string $dateFormat = 'medium', string $timeFormat = 'short'): string
    {
        if (is_string($date)) {
            $date = new DateTime($date);
        }

        $formats = [
            'short' => IntlDateFormatter::SHORT,
            'medium' => IntlDateFormatter::MEDIUM,
            'long' => IntlDateFormatter::LONG,
            'full' => IntlDateFormatter::FULL,
        ];

        $formatter = new IntlDateFormatter(
            $this->locale,
            $formats[$dateFormat] ?? IntlDateFormatter::MEDIUM,
            $formats[$timeFormat] ?? IntlDateFormatter::SHORT
        );

        return $formatter->format($date);
    }

    /**
     * Format relative time (e.g., "2 hours ago")
     */
    public function formatRelativeTime($date): string
    {
        if (is_string($date)) {
            $date = new DateTime($date);
        }

        $now = new DateTime();
        $diff = $now->getTimestamp() - $date->getTimestamp();

        $units = [
            'year' => 31536000,
            'month' => 2592000,
            'week' => 604800,
            'day' => 86400,
            'hour' => 3600,
            'minute' => 60,
            'second' => 1,
        ];

        foreach ($units as $unit => $seconds) {
            if (abs($diff) >= $seconds) {
                $count = (int)floor(abs($diff) / $seconds);
                $key = $diff < 0 ? 'time.in_' . $unit : 'time.' . $unit . '_ago';
                return $this->plural($key, $count);
            }
        }

        return $this->translate('time.just_now');
    }
}

// ============================================
// HELPER FUNCTIONS
// ============================================

/**
 * Translate a string
 */
function __($key, array $params = []): string
{
    return I18n::getInstance()->translate($key, $params);
}

/**
 * Translate and echo
 */
function _e($key, array $params = []): void
{
    echo __($key, $params);
}

/**
 * Pluralize a translation
 */
function __n($key, int $count, array $params = []): string
{
    return I18n::getInstance()->plural($key, $count, $params);
}

/**
 * Format number
 */
function format_number(float $number, int $decimals = 2): string
{
    return I18n::getInstance()->formatNumber($number, $decimals);
}

/**
 * Format date
 */
function format_date($date, string $format = 'medium'): string
{
    return I18n::getInstance()->formatDate($date, $format);
}

/**
 * Format datetime
 */
function format_datetime($date, string $dateFormat = 'medium', string $timeFormat = 'short'): string
{
    return I18n::getInstance()->formatDateTime($date, $dateFormat, $timeFormat);
}

/**
 * Format relative time
 */
function format_relative($date): string
{
    return I18n::getInstance()->formatRelativeTime($date);
}

/**
 * Get current locale
 */
function current_locale(): string
{
    return I18n::getInstance()->getLocale();
}

/**
 * Check if RTL
 */
function is_rtl(): bool
{
    return I18n::getInstance()->isRtl();
}
