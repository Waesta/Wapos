<?php
/**
 * Currency Helper Functions
 * Provides currency-neutral formatting functions
 */

if (!function_exists('formatMoney')) {
/**
 * Format money amount with configured currency symbol
 * @param float $amount The amount to format
 * @param bool $showSymbol Whether to show currency symbol (default: true)
 * @return string Formatted money string
 */
function formatMoney($amount, $showSymbol = true) {
    // Format the number with configured separators
    $formatted = number_format(
        (float)$amount,
        2,
        DECIMAL_SEPARATOR,
        THOUSANDS_SEPARATOR
    );
    
    if (!$showSymbol) {
        return $formatted;
    }
    
    // Add currency symbol based on position
    if (CURRENCY_POSITION === 'before') {
        return CURRENCY_SYMBOL . $formatted;
    } else {
        return $formatted . ' ' . CURRENCY_SYMBOL;
    }
}
}

if (!function_exists('formatAmount')) {
/**
 * Format money amount without symbol
 * @param float $amount The amount to format
 * @return string Formatted number string
 */
function formatAmount($amount) {
    return formatMoney($amount, false);
}
}

if (!function_exists('getCurrencySymbol')) {
/**
 * Get currency symbol
 * @return string Currency symbol
 */
function getCurrencySymbol() {
    return CURRENCY_SYMBOL;
}
}

if (!function_exists('getCurrencyCode')) {
/**
 * Get currency code
 * @return string Currency code (USD, EUR, etc.)
 */
function getCurrencyCode() {
    return CURRENCY_CODE;
}
}

if (!function_exists('parseMoney')) {
/**
 * Parse money string to float
 * @param string $moneyString Money string to parse
 * @return float Parsed amount
 */
function parseMoney($moneyString) {
    // Remove currency symbol and spaces
    $cleaned = str_replace([CURRENCY_SYMBOL, ' '], '', $moneyString);
    
    // Replace thousands separator
    $cleaned = str_replace(THOUSANDS_SEPARATOR, '', $cleaned);
    
    // Replace decimal separator with dot
    $cleaned = str_replace(DECIMAL_SEPARATOR, '.', $cleaned);
    
    return (float)$cleaned;
}
}
?>
