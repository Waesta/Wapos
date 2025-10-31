<?php

namespace App\Domain\Helpers;

/**
 * Date Helper - Pure functions for date operations
 */
class DateHelper
{
    public static function now(): string
    {
        return date('Y-m-d H:i:s');
    }

    public static function toISO8601(string $datetime): string
    {
        return date('c', strtotime($datetime));
    }

    public static function fromISO8601(string $iso): string
    {
        return date('Y-m-d H:i:s', strtotime($iso));
    }

    public static function isAfter(string $datetime, string $reference): bool
    {
        return strtotime($datetime) > strtotime($reference);
    }

    public static function isBefore(string $datetime, string $reference): bool
    {
        return strtotime($datetime) < strtotime($reference);
    }

    public static function addDays(string $datetime, int $days): string
    {
        return date('Y-m-d H:i:s', strtotime($datetime . " +{$days} days"));
    }

    public static function diffInSeconds(string $start, string $end): int
    {
        return strtotime($end) - strtotime($start);
    }
}
