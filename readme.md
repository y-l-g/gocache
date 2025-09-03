# GoCache: An In-Memory Cache for FrankenPHP

## Project Objective

GoCache is a PHP extension written in Go that provides a high-performance, in-memory, key-value cache shared across all PHP workers within a single FrankenPHP instance. The project's goal is to offer a zero-dependency caching solution that eliminates network latency and the operational overhead of managing an external service like Redis or Memcached for single-server applications.

## Core Functionality

The system operates on three technical layers:

1.  **Go Engine**: A singleton Go struct (`CacheEngine`) is instantiated at server startup. It manages a concurrent-safe `map` using a `sync.RWMutex` to prevent race conditions. A background goroutine ("janitor") runs periodically to purge expired keys, managing memory usage.
2.  **C Bridge (Manual Implementation)**: A C layer acts as the bridge between PHP and Go. A `gocache.stub.php` file defines the PHP function signatures, which is processed by `gen_stub.php` to generate C headers (`gocache_arginfo.h`). The `gocache.c` file implements the PHP functions by parsing parameters and calling the corresponding Go functions (exported via `//export`) through CGO. The extension is registered with PHP's module API.
3.  **PHP Facade**: A final PHP class, `GoCache\Cache`, provides a simple, static API for developers. It handles the JSON serialization/deserialization of complex data types and implements the high-level `remember` logic by composing the underlying `get` and `set` native calls.

## Project Structure

```
gocache/
├── app/
│   ├── GoCache/
│   │   └── Cache.php      # The user-facing PHP facade class
│   ├── Caddyfile          # Configuration file for frankenphp
│   ├── api.php            # The backend logic for the demo application
│   └── index.php          # The frontend HTML/JS for the demo application
├── gocache.c              # The C bridge implementing the PHP functions
├── gocache.go             # The core Go cache engine and exported functions
├── gocache.h              # The C header file for the module
├── gocache.stub.php       # The PHP stub file for C header generation
└── go.mod                 # The Go module definition
```

## Build and Run Instructions

### Prerequisites

*   Go toolchain (1.25 or later)
*   PHP (8.4 or later, compiled with ZTS support)
*   PHP development tools, specifically `php-config` and the `gen_stub.php` script from the PHP source code.
*   `xcaddy` build tool.

### Steps

1.  **Generate C Headers**: From the project root, run the PHP stub generator. This reads `gocache.stub.php` and creates the `gocache_arginfo.h` file required by `gocache.c`. Adjust the path to `gen_stub.php` as needed.
    ```bash
    php /path/to/php-src/build/gen_stub.php gocache.stub.php
    ```

2.  **Compile the Binary**: From the project root, build the custom FrankenPHP binary. This command compiles the Go code, links it with the C bridge, and packages it into an `app/frankenphp` executable.
    ```bash
    CGO_ENABLED=1 \
    XCADDY_GO_BUILD_FLAGS="-ldflags='-w -s' -tags=nobadger,nomysql,nopgx,nowatcher" \
    CGO_CFLAGS=$(php-config --includes) \
    CGO_LDFLAGS="$(php-config --ldflags) $(php-config --libs)" \
    xcaddy build \
        --output app/frankenphp \
        --with github.com/y-l-g/gocache=. \
        --with github.com/dunglas/frankenphp/caddy \
        --with github.com/dunglas/caddy-cbrotli
    ```

3.  **Run the Server**: Navigate to the `app` directory and start the server.
    ```bash
    cd app
    ./frankenphp run
    ```    
    The demo application will be available at `http://localhost:8080`.

## Current Limitations

*   **Single-Node Architecture**: The cache state is stored in the memory of a single server process. It cannot be shared across multiple servers, making it suitable only for vertical scaling.
*   **No Data Persistence**: The cache is volatile. All cached data is lost if the server process is restarted. It is not suitable for data that must survive restarts.
*   **Simple Data Structures**: The cache only supports a simple key-value model. It does not provide advanced data structures like hashes, sets, or sorted lists found in systems like Redis.

## Potential Improvements

*   **Atomic Operations**: Implement atomic `increment` and `decrement` methods for use cases like counters.
*   **Cache Tagging**: Introduce a tagging mechanism to allow for the invalidation of multiple related cache keys in a single operation.
*   **Monitoring Endpoint**: Expose an optional HTTP endpoint to provide real-time statistics (e.g., memory usage, hit/miss ratio, number of keys).