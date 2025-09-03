<?php

namespace GoCache;

final class Cache
{
    public static function remember(string $key, int $ttl, callable $callback): mixed
    {
        $value = self::get($key);

        if ($value !== null) {
            return $value;
        }

        $value = $callback();

        self::set($key, $value, $ttl);

        return $value;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $value = \GoCache\Driver\get($key);

        if ($value === null) {
            return $default;
        }

        return json_decode($value, true);
    }

    public static function set(string $key, mixed $value, int $ttl): bool
    {
        return \GoCache\Driver\set($key, json_encode($value), $ttl);
    }

    public static function forget(string $key): bool
    {
        return \GoCache\Driver\forget($key);
    }
}