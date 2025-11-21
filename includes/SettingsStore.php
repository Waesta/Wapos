<?php

class SettingsStore
{
    private static ?array $cache = null;
    private static int $lastLoadedAt = 0;
    private static int $ttlSeconds = 60;

    private static function prime(): array
    {
        $db = Database::getInstance();
        $rows = $db->fetchAll('SELECT setting_key, setting_value FROM settings');

        self::$cache = [];
        foreach ($rows as $row) {
            if (!isset($row['setting_key'])) {
                continue;
            }
            self::$cache[$row['setting_key']] = $row['setting_value'] ?? null;
        }
        self::$lastLoadedAt = time();
        return self::$cache;
    }

    private static function isExpired(): bool
    {
        if (self::$cache === null) {
            return true;
        }

        if (self::$ttlSeconds <= 0) {
            return false;
        }

        return (time() - self::$lastLoadedAt) >= self::$ttlSeconds;
    }

    public static function all(bool $forceRefresh = false): array
    {
        if ($forceRefresh || self::isExpired()) {
            return self::prime();
        }

        return self::$cache ?? self::prime();
    }

    public static function refresh(): array
    {
        return self::prime();
    }

    public static function get(string $key, $default = null)
    {
        $data = self::all();
        return $data[$key] ?? $default;
    }

    public static function has(string $key): bool
    {
        $data = self::all();
        return array_key_exists($key, $data);
    }

    public static function getByPrefix(string $prefix): array
    {
        $results = [];
        $data = self::all();
        foreach ($data as $key => $value) {
            if (strpos($key, $prefix) === 0) {
                $results[$key] = $value;
            }
        }
        return $results;
    }

    public static function select(array $keys): array
    {
        $results = [];
        if (empty($keys)) {
            return $results;
        }

        $data = self::all();
        foreach ($keys as $key) {
            if (array_key_exists($key, $data)) {
                $results[$key] = $data[$key];
            }
        }

        return $results;
    }

    public static function persist(string $key, $value): void
    {
        $db = Database::getInstance();
        $db->query(
            "INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) " .
            "ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)",
            [$key, $value]
        );

        if (self::$cache === null) {
            self::$cache = [];
        }
        self::$cache[$key] = $value;
    }

    public static function persistMany(array $settings): void
    {
        foreach ($settings as $key => $value) {
            self::persist($key, $value);
        }
    }
}

function settings(?string $key = null, $default = null)
{
    if ($key === null) {
        return SettingsStore::all();
    }

    return SettingsStore::get($key, $default);
}

function settings_many(array $keys): array
{
    return SettingsStore::select($keys);
}
