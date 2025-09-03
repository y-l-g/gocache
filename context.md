# Project Code Context

This document contains the full source code for the `GoCache` project.

## Server Configuration

### `app/Caddyfile`
```caddy
{
    frankenphp
}

:8080

php_server
```

## Go Module File (`go.mod`)
```go
module gocache

go 1.21
```

## Go Source File (`gocache.go`)
```go
package gocache

//#include "gocache.h"
import "C"
import (
	"sync"
	"time"
	"unsafe"

	"github.com/dunglas/frankenphp"
)

type cacheItem struct {
	value     []byte
	expiresAt int64
}

type CacheEngine struct {
	mu      sync.RWMutex
	items   map[string]cacheItem
	janitor *janitor
}

func NewCacheEngine(janitorInterval time.Duration) *CacheEngine {
	e := &CacheEngine{
		items: make(map[string]cacheItem),
	}
	if janitorInterval > 0 {
		e.janitor = newJanitor(e, janitorInterval)
		e.janitor.Run()
	}
	return e
}

func (e *CacheEngine) Set(key string, value []byte, ttl time.Duration) {
	var expires int64
	if ttl > 0 {
		expires = time.Now().Add(ttl).UnixNano()
	}
	e.mu.Lock()
	e.items[key] = cacheItem{
		value:     value,
		expiresAt: expires,
	}
	e.mu.Unlock()
}

func (e *CacheEngine) Get(key string) ([]byte, bool) {
	e.mu.RLock()
	defer e.mu.RUnlock()

	item, found := e.items[key]
	if !found {
		return nil, false
	}

	if item.expiresAt > 0 && time.Now().UnixNano() > item.expiresAt {
		return nil, false
	}

	return item.value, true
}

func (e *CacheEngine) Delete(key string) {
	e.mu.Lock()
	delete(e.items, key)
	e.mu.Unlock()
}

func (e *CacheEngine) deleteExpired() {
	now := time.Now().UnixNano()
	e.mu.Lock()
	var keysToDelete []string
	for k, v := range e.items {
		if v.expiresAt > 0 && now > v.expiresAt {
			keysToDelete = append(keysToDelete, k)
		}
	}
	for _, k := range keysToDelete {
		delete(e.items, k)
	}
	e.mu.Unlock()
}

type janitor struct {
	engine   *CacheEngine
	interval time.Duration
	stop     chan bool
}

func newJanitor(e *CacheEngine, ci time.Duration) *janitor {
	return &janitor{
		engine:   e,
		interval: ci,
		stop:     make(chan bool),
	}
}

func (j *janitor) Run() {
	ticker := time.NewTicker(j.interval)
	go func() {
		for {
			select {
			case <-ticker.C:
				j.engine.deleteExpired()
			case <-j.stop:
				ticker.Stop()
				return
			}
		}
	}()
}

var engine *CacheEngine

func init() {
	engine = NewCacheEngine(1 * time.Minute)
	frankenphp.RegisterExtension(unsafe.Pointer(&C.gocache_module_entry))
}

//export go_get
func go_get(key *C.zend_string) *C.zend_string {
	goKey := frankenphp.GoString(unsafe.Pointer(key))
	value, found := engine.Get(goKey)

	if !found {
		return nil
	}
	return (*C.zend_string)(frankenphp.PHPString(string(value), false))
}

//export go_set
func go_set(key *C.zend_string, value *C.zend_string, ttl int64) bool {
	goKey := frankenphp.GoString(unsafe.Pointer(key))
	goValue := []byte(frankenphp.GoString(unsafe.Pointer(value)))

	engine.Set(goKey, goValue, time.Duration(ttl)*time.Second)
	return true
}

//export go_forget
func go_forget(key *C.zend_string) bool {
	goKey := frankenphp.GoString(unsafe.Pointer(key))
	engine.Delete(goKey)
	return true
}
```

## C Bridge Files

### C Header (`gocache.h`)
```c
#ifndef _GOCACHE_H
#define _GOCACHE_H

#include <php.h>

extern zend_module_entry gocache_module_entry;

#endif
```

### C Source (`gocache.c`)
```c
#include <php.h>
#include "gocache.h"
#include "gocache_arginfo.h"
#include "_cgo_export.h"

PHP_FUNCTION(GoCache_Driver_get)
{
    zend_string *key;
    ZEND_PARSE_PARAMETERS_START(1, 1)
        Z_PARAM_STR(key)
    ZEND_PARSE_PARAMETERS_END();

    zend_string *result = go_get(key);
    if (result) {
        RETURN_STR(result);
    }
    RETURN_NULL();
}

PHP_FUNCTION(GoCache_Driver_set)
{
    zend_string *key, *value;
    zend_long ttl;
    ZEND_PARSE_PARAMETERS_START(3, 3)
        Z_PARAM_STR(key)
        Z_PARAM_STR(value)
        Z_PARAM_LONG(ttl)
    ZEND_PARSE_PARAMETERS_END();

    go_set(key, value, ttl);
    RETURN_TRUE;
}

PHP_FUNCTION(GoCache_Driver_forget)
{
    zend_string *key;
    ZEND_PARSE_PARAMETERS_START(1, 1)
        Z_PARAM_STR(key)
    ZEND_PARSE_PARAMETERS_END();

    go_forget(key);
    RETURN_TRUE;
}

zend_module_entry gocache_module_entry = {
    STANDARD_MODULE_HEADER,
    "gocache",
    ext_functions,
    NULL,
    NULL,
    NULL,
    NULL,
    NULL,
    "0.1",
    STANDARD_MODULE_PROPERTIES
};
```

## PHP Application Files

### PHP Stub (`gocache.stub.php`)
```php
<?php

/** @generate-class-entries */

namespace GoCache\Driver;

function get(string $key): ?string {}
function set(string $key, string $value, int $ttl): bool {}
function forget(string $key): bool {}
```

### PHP Facade (`app/GoCache/Cache.php`)
```php
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
```

### Demo Backend (`app/api.php`)
```php
<?php

require __DIR__ . '/GoCache/Cache.php';
use GoCache\Cache;

define('DB_MESSAGES', __DIR__ . '/messages.json');
define('DB_USERS', [
    1 => ['id' => 1, 'name' => 'Alice'],
    2 => ['id' => 2, 'name' => 'Bob'],
]);

function get_user_profile(int $userId): array
{
    $key = 'user_profile:' . $userId;
    return Cache::remember($key, 3600, function () use ($userId) {
        sleep(1);
        error_log("DATABASE HIT for user ID: $userId");
        return DB_USERS[$userId] ?? ['id' => $userId, 'name' => 'Unknown'];
    });
}

header('Content-Type: application/json');
$action = $_GET['action'] ?? '';

switch ($action) {
    case 'get_messages':
        $messages = file_exists(DB_MESSAGES) ? json_decode(file_get_contents(DB_MESSAGES), true) : [];
        $enrichedMessages = [];
        foreach ($messages as $message) {
            $message['user'] = get_user_profile($message['userId']);
            $enrichedMessages[] = $message;
        }
        echo json_encode(array_reverse($enrichedMessages));
        break;

    case 'post_message':
        $input = json_decode(file_get_contents('php://input'), true);
        $messages = file_exists(DB_MESSAGES) ? json_decode(file_get_contents(DB_MESSAGES), true) : [];
        
        $messages[] = [
            'userId' => (int)($input['userId'] ?? 1),
            'text' => htmlspecialchars($input['text'] ?? ''),
            'time' => time(),
        ];
        
        file_put_contents(DB_MESSAGES, json_encode($messages));
        echo json_encode(['status' => 'ok']);
        break;
}
```

### Demo Frontend (`app/index.php`)
```php
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>GoCache Demo</title>
    <style>
        body { font-family: sans-serif; max-width: 600px; margin: auto; padding: 20px; background: #f4f4f4; }
        #chatbox { list-style: none; padding: 0; margin: 0 0 20px 0; height: 300px; overflow-y: scroll; border: 1px solid #ccc; background: #fff; padding: 10px; }
        #chatbox li { margin-bottom: 10px; padding-bottom: 10px; border-bottom: 1px solid #eee; }
        #chatbox li strong { color: #007bff; }
        form { display: flex; }
        input { flex-grow: 1; border: 1px solid #ccc; padding: 10px; }
        button { border: none; background: #007bff; color: white; padding: 10px 15px; cursor: pointer; }
        #status { text-align: center; padding: 10px; color: #888; height: 20px; }
    </style>
</head>
<body>
    <h1>GoCache Shoutbox</h1>
    <div id="status">Loading messages...</div>
    <ul id="chatbox"></ul>
    <form id="messageForm">
        <input type="text" id="messageText" placeholder="Type a message..." required>
        <button type="submit">Send</button>
    </form>

    <script>
        const chatbox = document.getElementById('chatbox');
        const messageForm = document.getElementById('messageForm');
        const messageText = document.getElementById('messageText');
        const status = document.getElementById('status');

        async function fetchMessages() {
            status.textContent = 'Fetching messages... (might be slow)';
            const startTime = Date.now();
            const response = await fetch('api.php?action=get_messages');
            const messages = await response.json();
            chatbox.innerHTML = '';
            for (const msg of messages) {
                const li = document.createElement('li');
                const date = new Date(msg.time * 1000).toLocaleTimeString();
                li.innerHTML = `<strong>${msg.user.name}</strong> (${date}):<br>${msg.text}`;
                chatbox.appendChild(li);
            }
            const duration = (Date.now() - startTime) / 1000;
            status.textContent = `Feed updated in ${duration.toFixed(2)} seconds.`;
        }

        messageForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const text = messageText.value;
            if (!text) return;
            status.textContent = 'Sending...';
            const userId = (chatbox.children.length % 2 === 0) ? 1 : 2;
            await fetch('api.php?action=post_message', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ text: text, userId: userId })
            });
            messageText.value = '';
            await fetchMessages();
        });
        
        fetchMessages();
    </script>
</body>
</html>