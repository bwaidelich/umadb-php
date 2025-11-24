# UmaDB PHP Extension

PHP bindings for [UmaDB](https://github.com/umadb-io/umadb) event store, built with Rust using [ext-php-rs](https://github.com/davidcole1340/ext-php-rs).

UmaDB is a specialist event store for **Dynamic Consistency Boundaries (DCB)**, enabling flexible, query-driven append conditions for implementing business rules without hardcoded aggregate boundaries.

## Features

- ✨ **Full DCB API** - Complete implementation of the Dynamic Consistency Boundaries specification
- 🚀 **High Performance** - Rust-powered with zero-copy data handling
- 🔒 **Type Safe** - Leverages PHP 8.0+ type system
- 💪 **Sync Client** - Blocking operations suitable for traditional PHP applications
- 🎯 **Simple API** - Read, append, head operations with intuitive builder patterns
- 🔄 **Idempotent Appends** - UUID-based event deduplication
- 🏷️ **Tag-based Filtering** - Efficient event queries
- ⚡ **Optimistic Concurrency** - Position-based conflict detection

## Requirements

- **PHP** 8.4 or higher
- **Rust** 1.70 or higher (for building)
- **UmaDB Server** running and accessible

## Installation

### Option 1: PIE (Recommended)

**Note:** Requires Rust 1.70+ and PIE installed. See [PIE.md](PIE.md) for details.

```bash
# Install Rust (if not already installed)
curl --proto '=https' --tlsv1.2 -sSf https://sh.rustup.rs | sh

# Install PIE
wget https://github.com/php/pie/releases/latest/download/pie.phar
chmod +x pie.phar && sudo mv pie.phar /usr/local/bin/pie

# Install extension
pie install wwwision/umadb-php
```

### Option 2: Building from Source

```bash
# Clone the repository
git clone https://github.com/bwaidelich/umadb-php.git
cd umadb-php

# Build the extension
./build.sh

# Or use Make
make build

# Install to PHP extension directory
sudo make install
```

See [INSTALL.md](INSTALL.md) for detailed instructions.

### Enable the Extension

Add to your `php.ini`:

```ini
extension=umadb_php.so
```

Or for CLI only, use:

```bash
php -d extension=umadb_php.so your-script.php
```

Verify installation:

```bash
php -m | grep umadb
```

## Quick Start

```php
<?php

use UmaDB\Client;
use UmaDB\Event;

// Connect to UmaDB server
$client = new Client('http://localhost:50051');

// Create an event
$event = new Event(
    event_type: 'UserCreated',
    data: json_encode(['userId' => '12345', 'name' => 'Alice']),
    tags: ['user:12345']
);

// Append event
$position = $client->append([$event]);
echo "Event appended at position: {$position}\n";

// Read all events
$events = $client->read();
foreach ($events as $seqEvent) {
    echo "[{$seqEvent->position}] {$seqEvent->event->event_type}\n";
}

// Get current head
$head = $client->head();
echo "Current head: {$head}\n";
```

## API Reference

### Client

#### Constructor

```php
new Client(
    string $url,
    ?string $ca_path = null,
    ?int $batch_size = null
)
```

**Parameters:**
- `$url` - Server URL (e.g., `http://localhost:50051` or `https://server:50051`)
- `$ca_path` - Optional path to CA certificate for TLS (self-signed certs)
- `$batch_size` - Optional batch size hint for reading events (default: server decides)

**Note on Named Arguments:** Parameter names use snake_case (not camelCase). When using named arguments, all preceding optional parameters must be provided explicitly (even as `null`) due to ext-php-rs limitations.

**Examples:**

```php
// Basic connection
$client = new Client('http://localhost:50051');

// TLS with self-signed certificate
$client = new Client('https://localhost:50051', ca_path: '/path/to/ca.pem');

// Custom batch size (must provide all parameters explicitly)
$client = new Client('http://localhost:50051', ca_path: null, batch_size: 100);
```

#### read()

```php
public function read(
    ?Query $query = null,
    ?int $start = null,
    ?bool $backwards = false,
    ?int $limit = null,
    ?bool $subscribe = false
): array
```

Reads events from the event store.

**Parameters:**
- `$query` - Optional query to filter events by type and tags
- `$start` - Starting position (inclusive if forward, exclusive if backward)
- `$backwards` - Read backwards from start position
- `$limit` - Maximum number of events to return
- `$subscribe` - Subscribe to new events (streaming mode)

**Returns:** Array of `SequencedEvent` objects

**Examples:**

```php
// Read all events
$events = $client->read();

// Read with query
$query = new Query([
    new QueryItem(types: ['OrderCreated'], tags: ['order'])
]);
$events = $client->read(query: $query);

// Read backwards with limit
$events = $client->read(start: 1000, backwards: true, limit: 10);

// Subscribe to new events
$events = $client->read(subscribe: true);
```

#### append()

```php
public function append(
    array $events,
    ?AppendCondition $condition = null
): int
```

Appends events to the event store.

**Parameters:**
- `$events` - Array of `Event` objects to append
- `$condition` - Optional append condition for consistency enforcement

**Returns:** Position of the last appended event

**Throws:** `IntegrityException` if append condition fails

**Examples:**

```php
// Simple append
$event = new Event('UserCreated', $data, ['user']);
$position = $client->append([$event]);

// Append with condition
$condition = new AppendCondition($query, after: $head);
$position = $client->append([$event], $condition);

// Append multiple events
$position = $client->append([
    new Event('OrderCreated', $data1, ['order']),
    new Event('OrderPaid', $data2, ['order', 'payment']),
]);
```

#### head()

```php
public function head(): ?int
```

Returns the current head position of the event store, or `null` if empty.

**Example:**

```php
$head = $client->head();
if ($head === null) {
    echo "Store is empty\n";
} else {
    echo "Last event at position: {$head}\n";
}
```

### Event

```php
new Event(
    string $event_type,
    string $data,
    ?array $tags = null,
    ?string $uuid = null
)
```

Represents an event in the event store.

**Properties:**
- `string $event_type` - Event type identifier (read-only)
- `string $data` - Binary event data (read-only)
- `array $tags` - Tags for filtering (read-only)
- `?string $uuid` - Optional UUID for idempotency (read-only)

**Example:**

```php
$event = new Event(
    event_type: 'UserRegistered',
    data: json_encode(['userId' => '123', 'email' => 'user@example.com']),
    tags: ['user:123', 'email:' . sha1('user@example.com')],
    uuid: '550e8400-e29b-41d4-a716-446655440000'
);
```

### SequencedEvent

Represents an event with its position in the stream.

**Properties:**
- `Event $event` - The event object (read-only)
- `int $position` - Position in the stream (read-only)

**Note:** This class is returned by `read()` and cannot be instantiated directly.

### Query

```php
new Query(?array $items = null)
```

A query for filtering events. An event matches if it matches **any** query item (OR logic).

**Parameters:**
- `$items` - Array of `QueryItem` objects

**Example:**

```php
$query = new Query([
    new QueryItem(types: ['UserCreated'], tags: ['user']),
    new QueryItem(types: ['UserUpdated'], tags: ['user']),
]);
```

### QueryItem

```php
new QueryItem(
    ?array $types = null,
    ?array $tags = null
)
```

A query item specifying event types and tags to match.

**Matching Rules:**
- Event type must be in `$types` (or `$types` is empty/null = match all types)
- **All** tags in `$tags` must be present in the event tags (AND logic)

**Parameters:**
- `$types` - Array of event type strings to match
- `$tags` - Array of tag strings that must all be present

**Examples:**

```php
// Match all "OrderCreated" events
$item = new QueryItem(types: ['OrderCreated']);

// Match events with specific tags
$item = new QueryItem(tags: ['order', 'order:12345']);

// Match specific types with specific tags
$item = new QueryItem(
    types: ['OrderPaid', 'OrderShipped'],
    tags: ['order:12345']
);

// Match all events (no filter)
$item = new QueryItem();
```

### AppendCondition

```php
new AppendCondition(
    Query $fail_if_events_match,
    ?int $after = null
)
```

Condition for conditional appends, enabling optimistic concurrency control and business rule enforcement.

**Parameters:**
- `$fail_if_events_match` - Query that must **not** match any existing events
- `$after` - Optional position constraint (fail if events exist after this position)

**Examples:**

```php
// Prevent duplicate events
$condition = new AppendCondition(
    fail_if_events_match: new Query([
        new QueryItem(types: ['UserRegistered'], tags: ['email:alice@example.com'])
    ])
);

// Optimistic concurrency (position-based)
$head = $client->head();
$condition = new AppendCondition(
    fail_if_events_match: new Query([]),
    after: $head
);

// Combined: business rule + position check
$condition = new AppendCondition(
    fail_if_events_match: $boundaryQuery,
    after: $lastKnownPosition
);
```

## Exception Classes

All exceptions extend PHP's base `Exception` class and are in the `UmaDB\Exception` namespace:

- `UmaDB\Exception\IntegrityException` - Append condition failed
- `UmaDB\Exception\TransportException` - gRPC/network errors
- `UmaDB\Exception\CorruptionException` - Data corruption detected
- `UmaDB\Exception\IoException` - I/O errors
- `UmaDB\Exception\UmaDBException` - Generic UmaDB error

**Example:**

```php
use UmaDB\Exception\IntegrityException;

try {
    $client->append([$event], $condition);
} catch (IntegrityException $e) {
    echo "Append condition failed: {$e->getMessage()}\n";
}
```

## Usage Examples

### Idempotent Appends

Use UUIDs to make appends idempotent:

```php
$uuid = '550e8400-e29b-41d4-a716-446655440000';
$event = new Event('OrderCreated', $data, ['order'], $uuid);

// First append
$position1 = $client->append([$event]);  // Returns position 100

// Retry (e.g., after network failure) - same UUID
$position2 = $client->append([$event]);  // Returns position 100 (same!)

assert($position1 === $position2);  // true
```

### Prevent Duplicate Email Registration

```php
$email = 'alice@example.com';
$emailHash = sha1($email);

// Define consistency boundary
$boundaryQuery = new Query([
    new QueryItem(types: ['UserRegistered'], tags: ["email:{$email}"])
]);

// Read current state
$head = $client->head();

// Create append condition
$condition = new AppendCondition($boundaryQuery, $head);

// Try to register
$event = new Event(
    'UserRegistered',
    json_encode(['email' => $email, 'name' => 'Alice'}),
    ["email:$emailHash"]
);

try {
    $position = $client->append([$event], $condition);
    echo "User registered successfully\n";
} catch (IntegrityException $e) {
    echo "Email already registered\n";
}
```

### Multi-step Workflow Coordination

```php
$workflowId = 'workflow-123';

// Step 1: Start workflow
$event1 = new Event(
    'WorkflowStarted',
    json_encode(['workflowId' => $workflowId]),
    ["workflow:{$workflowId}", 'step:1']
);
$client->append([$event1]);

// Step 2: Prevent duplicate execution
$step2Boundary = new Query([
    new QueryItem(types: ['WorkflowStep2Completed'], tags: ["workflow:{$workflowId}"])
]);

$head = $client->head();
$condition = new AppendCondition($step2Boundary, $head);

$event2 = new Event(
    'WorkflowStep2Completed',
    json_encode(['workflowId' => $workflowId, 'result' => 'success']),
    ["workflow:{$workflowId}", 'step:2']
);

$client->append([$event2], $condition);  // Only succeeds once
```

### Query Filtering

```php
// Query by event type
$query = new Query([
    new QueryItem(types: ['OrderCreated', 'OrderUpdated'])
]);
$orderEvents = $client->read(query: $query);

// Query by tags
$query = new Query([
    new QueryItem(tags: ['order:12345'])
]);
$specificOrderEvents = $client->read(query: $query);

// Complex query (OR logic between items)
$query = new Query([
    // Match user events
    new QueryItem(types: ['UserCreated'], tags: ['user']),
    // OR payment events
    new QueryItem(types: ['PaymentProcessed'], tags: ['payment']),
]);
$events = $client->read(query: $query);
```

## Development

### Building

```bash
# Build release version
make build

# Build debug version
make build-dev

# Run Rust tests
make test-rust

# Run clippy
make clippy

# Format code
make fmt
```

### Testing

```bash
# Install PHP dependencies
composer install

# Run PHP tests (requires running UmaDB server)
make test

# Or directly with PHPUnit
vendor/bin/phpunit
```

### Running Examples

Start UmaDB server:

```bash
# In UmaDB repository
cargo run --bin umadb -- --listen 127.0.0.1:50051 --db-path /tmp/umadb-test.db
```

Run examples:

```bash
php examples/basic.php
php examples/query.php
php examples/consistency.php
```

## Architecture

This extension uses **ext-php-rs** to create Rust-powered PHP extensions with:

- **Zero-copy data transfer** where possible
- **Type-safe FFI** between PHP and Rust
- **Automatic memory management** via reference counting
- **Native PHP exception** handling

The extension wraps the `umadb-client` Rust crate, providing a synchronous client that internally manages a Tokio runtime for async gRPC operations.

## Comparison with Python Bindings

Similar to the Python bindings (`umadb` package), this PHP extension:

- ✅ Exposes the same DCB API
- ✅ Uses synchronous client only (blocking operations)
- ✅ Pre-collects read results into arrays/lists
- ✅ Provides idiomatic language bindings

Differences:

- PHP extension is compiled and loaded as a native extension
- Python uses PyO3 and Maturin for packaging
- PHP has no async/await (yet), so sync-only is natural

## License

Licensed under the MIT License. See [LICENSE-MIT](LICENSE-MIT) for details.

## Contributing

Contributions are welcome! Please ensure:

1. Code is formatted: `make fmt`
2. Clippy passes: `make clippy`
3. Tests pass: `make test` and `make test-rust`
4. Examples run successfully

## Links

- [UmaDB Main Repository](https://github.com/umadb-io/umadb)
- [DCB Specification](https://dcb.events/specification/)
- [ext-php-rs Documentation](https://docs.rs/ext-php-rs/)
