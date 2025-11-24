# Quick Start Guide

Get up and running with UmaDB PHP extension in 5 minutes.

## Prerequisites

```bash
# Ubuntu/Debian
sudo apt install php8.2 php8.2-dev

# macOS
brew install php

# Install Rust
curl --proto '=https' --tlsv1.2 -sSf https://sh.rustup.rs | sh
```

## Build

```bash
./build.sh
```

## Install

```bash
sudo cp target/release/libumadb_php.so $(php-config --extension-dir)/
echo "extension=umadb_php.so" | sudo tee -a $(php --ini | grep 'Loaded Configuration' | cut -d: -f2 | xargs)
```

Or add manually to `php.ini`:

```ini
extension=umadb_php.so
```

## Verify

```bash
php -m | grep umadb
```

Should output: `umadb_php`

## Start UmaDB Server

```bash
docker run -d -p 50051:50051 ghcr.io/pyeventsourcing/umadb:latest
```

## Test

Create `test.php`:

```php
<?php

use UmaDB\Client;
use UmaDB\Event;

$client = new Client('http://localhost:50051');

$event = new Event('TestEvent', 'Hello UmaDB!', ['test']);
$position = $client->append([$event]);

echo "Event appended at position: {$position}\n";
echo "Current head: " . $client->head() . "\n";

foreach ($client->read() as $seqEvent) {
    echo "[{$seqEvent->position}] {$seqEvent->event->event_type}\n";
}
```

Run:

```bash
php test.php
```

## Next Steps

- Read [README.md](README.md) for full API documentation
- Explore [examples/](examples/) for more usage patterns
- Check [INSTALL.md](INSTALL.md) for troubleshooting

## Common Commands

```bash
# Build
make build

# Install
sudo make install

# Run tests
composer install
make test

# Run examples
php examples/basic.php
php examples/query.php
php examples/consistency.php
```

## Troubleshooting

**Extension not loading?**

```bash
# Check PHP extension directory
php-config --extension-dir

# Verify file exists
ls $(php-config --extension-dir)/umadb_php.so

# Check PHP configuration
php --ini
```

**Can't connect to server?**

```bash
# Check server is running
docker ps | grep umadb

# Test connectivity
curl http://localhost:50051
```

**Build fails?**

```bash
# Update Rust
rustup update stable

# Clean and rebuild
cargo clean
cargo build --release
```

## Need Help?

- See [INSTALL.md](INSTALL.md) for detailed installation guide
- See [SETUP.md](SETUP.md) for development setup
- Check UmaDB documentation at https://github.com/bwaidelich/umadb
