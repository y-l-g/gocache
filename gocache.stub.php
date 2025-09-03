<?php

/** @generate-class-entries */

namespace GoCache\Driver;

function get(string $key): ?string {}
function set(string $key, string $value, int $ttl): bool {}
function forget(string $key): bool {}