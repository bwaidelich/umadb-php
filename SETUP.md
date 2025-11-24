# UmaDB PHP Extension - Setup Guide

This directory contains a complete template for building a PHP extension with Rust bindings for UmaDB.

## What's Included

This template provides everything needed to create a fully functional PHP extension:

### Core Files

- **`src/lib.rs`** - Main Rust implementation with ext-php-rs bindings
  - `Client` class - Connect to UmaDB and perform operations
  - `Event` class - Represent events with type, data, tags, and UUID
  - `SequencedEvent` class - Events with position in stream
  - `Query` and `QueryItem` classes - Filter events
  - `AppendCondition` class - Enforce consistency boundaries
  - Custom exception classes for error handling

- **`Cargo.toml`** - Rust dependencies and build configuration
  - ext-php-rs for PHP bindings
  - umadb-client and umadb-dcb dependencies
  - Release profile with LTO optimization

- **`composer.json`** - PHP package metadata and dependencies
  - PHPUnit for testing
  - PHPStan for static analysis

### Build System

- **`build.sh`** - Automated build script with prerequisite checking
- **`Makefile`** - Make targets for building, testing, and installation
- **`.github/workflows/ci.yml`** - GitHub Actions CI pipeline

### Documentation

- **`README.md`** - Complete API reference and usage guide
- **`INSTALL.md`** - Detailed installation instructions
- **`LICENSE-MIT`** and **`LICENSE-APACHE`** - Dual licensing

### Tests and Examples

- **`tests/ClientTest.php`** - Comprehensive PHPUnit test suite
  - Connection tests
  - Append operations (single and multiple)
  - Read operations (all, filtered, limited, backwards)
  - Query filtering
  - Append conditions
  - Idempotent appends
  - Conflict detection

- **`examples/basic.php`** - Basic operations demo
  - Connecting to server
  - Appending events
  - Reading events
  - Getting head position

- **`examples/query.php`** - Query filtering demo
  - Filtering by event type
  - Filtering by tags
  - Complex queries with multiple items
  - OR logic between query items

- **`examples/consistency.php`** - Dynamic consistency boundaries demo
  - Preventing duplicate registrations
  - Idempotent appends with UUIDs
  - Multi-step workflows
  - Optimistic concurrency control

### Configuration

- **`phpunit.xml`** - PHPUnit configuration
- **`.gitignore`** - Ignore build artifacts and dependencies

## Directory Structure

```
php-extension-template/
├── .github/
│   └── workflows/
│       └── ci.yml              # GitHub Actions CI
├── examples/
│   ├── basic.php               # Basic usage example
│   ├── query.php               # Query filtering example
│   └── consistency.php         # Consistency boundaries example
├── src/
│   └── lib.rs                  # Main Rust implementation
├── tests/
│   └── ClientTest.php          # PHPUnit tests
├── .gitignore                  # Git ignore rules
├── build.sh                    # Build script
├── Cargo.toml                  # Rust dependencies
├── composer.json               # PHP package metadata
├── INSTALL.md                  # Installation guide
├── LICENSE-APACHE              # Apache 2.0 license
├── LICENSE-MIT                 # MIT license
├── Makefile                    # Make targets
├── phpunit.xml                 # PHPUnit configuration
├── README.md                   # API documentation
└── SETUP.md                    # This file
```

## Next Steps

### 1. Copy to New Repository

Copy this entire directory to create your new repository:

```bash
# Create new repository directory
mkdir ~/umadb-php
cp -r php-extension-template/* ~/umadb-php/
cd ~/umadb-php

# Initialize git repository
git init
git add .
git commit -m "Initial commit: UmaDB PHP extension"

# Add remote and push
git remote add origin https://github.com/yourusername/umadb-php.git
git push -u origin main
```

### 2. Update Dependencies

The `Cargo.toml` currently references UmaDB via git. You may want to:

**Option A: Keep git dependencies** (no changes needed)

```toml
umadb-client = { git = "https://github.com/bwaidelich/umadb.git", package = "umadb-client" }
```

**Option B: Use published crates** (once available)

```toml
umadb-client = "0.1.5"
umadb-dcb = "0.1.5"
```

**Option C: Use local path** (for development)

```toml
umadb-client = { path = "../umadb/crates/client" }
umadb-dcb = { path = "../umadb/crates/dcb" }
```

### 3. Build and Test

```bash
# Install prerequisites
# See INSTALL.md for platform-specific instructions

# Build the extension
./build.sh

# Install composer dependencies
composer install

# Run tests (requires UmaDB server running)
make test
```

### 4. Customize

Update these files with your information:

- **`Cargo.toml`** - Update repository URL, authors
- **`composer.json`** - Update package name, authors
- **`README.md`** - Update repository links
- **`.github/workflows/ci.yml`** - Adjust CI configuration

### 5. Development Workflow

```bash
# Make changes to src/lib.rs

# Format code
make fmt

# Check with clippy
make clippy

# Rebuild
make build

# Run tests
make test

# Run examples
php -d extension=target/release/libumadb_php.so examples/basic.php
```

## Architecture Notes

### Rust Side

The extension uses **ext-php-rs** which provides:

- Type-safe PHP/Rust FFI
- Automatic memory management
- Native PHP class/object support
- Exception handling

Key patterns:

1. **Wrapper Classes** - Each PHP class wraps a Rust struct
2. **Error Conversion** - DCBError → PHP exceptions
3. **Builder Pattern** - Client uses builder for configuration
4. **Sync Only** - Blocks on async operations internally

### PHP Side

The extension provides:

- **Namespaced Classes** - All in `UmaDB\` namespace
- **Type Hints** - Full PHP 8.0+ type declarations
- **Exceptions** - Custom exception hierarchy
- **Read-only Properties** - Event and SequencedEvent have read-only fields

## Implementation Details

### Class Mapping

| Rust Type | PHP Class | Description |
|-----------|-----------|-------------|
| `SyncUmaDbClient` | `UmaDB\Client` | Main client class |
| `DCBEvent` | `UmaDB\Event` | Event representation |
| `DCBSequencedEvent` | `UmaDB\SequencedEvent` | Event with position |
| `DCBQuery` | `UmaDB\Query` | Event filter query |
| `DCBQueryItem` | `UmaDB\QueryItem` | Query clause |
| `DCBAppendCondition` | `UmaDB\AppendCondition` | Append constraint |

### Error Mapping

| Rust Error | PHP Exception |
|------------|---------------|
| `DCBError::IntegrityError` | `UmaDB\Exception\IntegrityException` |
| `DCBError::TransportError` | `UmaDB\Exception\TransportException` |
| `DCBError::Corruption` | `UmaDB\Exception\CorruptionException` |
| `DCBError::Io` | `UmaDB\Exception\IoException` |
| Other | `UmaDB\Exception\UmaDBException` |

### Data Flow

1. **PHP calls method** on `UmaDB\Client`
2. **ext-php-rs converts** PHP types to Rust types
3. **Rust client** performs gRPC operation
4. **Results converted** back to PHP types
5. **PHP receives** native PHP objects

### Memory Management

- **Automatic** - ext-php-rs handles reference counting
- **No manual cleanup** - Objects freed when no longer referenced
- **Safe** - No memory leaks or use-after-free

## Testing Strategy

### Unit Tests (Rust)

Not currently included but could be added:

```bash
cargo test
```

### Integration Tests (PHP)

The `tests/ClientTest.php` file includes:

- Connection tests
- CRUD operations
- Query filtering
- Consistency boundary enforcement
- Error handling

Run with:

```bash
vendor/bin/phpunit
```

### Example Scripts

Serve as both examples and manual tests:

```bash
php examples/basic.php
php examples/query.php
php examples/consistency.php
```

## Publishing

### GitHub Releases

Use GitHub Actions to build binaries for multiple platforms:

1. Create release tag: `git tag v0.1.1 && git push --tags`
2. CI builds extensions for Linux and macOS
3. Attach binaries to release

### Packagist

To publish on Packagist:

1. Ensure composer.json is complete
2. Push to GitHub
3. Register on https://packagist.org/

Users can then:

```bash
composer require wwwision/umadb-php
```

Note: They still need to build the extension locally or download pre-built binaries.

## Contributing

When contributing:

1. **Format code**: `make fmt`
2. **Run clippy**: `make clippy`
3. **Run tests**: `make test`
4. **Test examples**: Run all example scripts
5. **Update docs**: Keep README.md in sync with API changes

## Support

For help:

- Read [README.md](README.md) for API documentation
- Read [INSTALL.md](INSTALL.md) for installation help
- Check [examples/](examples/) for usage patterns
- File issues on GitHub

## License

This template is dual-licensed under MIT or Apache 2.0, matching UmaDB's licensing.
