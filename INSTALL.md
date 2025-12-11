# Installation Guide

## Prerequisites

Before installing the UmaDB PHP extension, ensure you have:

- **PHP 8.4 or higher** installed with development headers
- **Rust** 1.70 or higher (install from [rustup.rs](https://rustup.rs/))
- **Cargo** (comes with Rust)
- **php-config** (usually part of PHP development packages)

### Installing Prerequisites

#### Ubuntu/Debian

```bash
# Install PHP and development headers
sudo apt update
sudo apt install php8.4 php8.4-dev

# Install Rust
curl --proto '=https' --tlsv1.2 -sSf https://sh.rustup.rs | sh
source $HOME/.cargo/env
```

#### macOS

```bash
# Install PHP
brew install php

# Install Rust
curl --proto '=https' --tlsv1.2 -sSf https://sh.rustup.rs | sh
source $HOME/.cargo/env
```

#### Fedora/RHEL/CentOS

```bash
# Install PHP and development headers
sudo dnf install php php-devel

# Install Rust
curl --proto '=https' --tlsv1.2 -sSf https://sh.rustup.rs | sh
source $HOME/.cargo/env
```

## Installation Methods

### Method 1: PIE (Recommended)

PIE (PHP Installer for Extensions) is the modern way to install PHP extensions.

**Note:** Requires Rust 1.70+ to be installed since the extension is built from source.

```bash
# Install Rust (if not already installed)
curl --proto '=https' --tlsv1.2 -sSf https://sh.rustup.rs | sh
source $HOME/.cargo/env

# Install PIE
wget https://github.com/php/pie/releases/latest/download/pie.phar
chmod +x pie.phar && sudo mv pie.phar /usr/local/bin/pie

# Install extension
pie install wwwision/umadb-php
```

See [PIE.md](PIE.md) for detailed PIE installation guide.

### Method 2: Using the Build Script

```bash
# Clone the repository
git clone https://github.com/bwaidelich/umadb-php.git
cd umadb-php

# Run the build script
./build.sh
```

The script will:
1. Check prerequisites
2. Build the extension
3. Show installation instructions

### Method 3: Using Make

```bash
# Build the extension
make build

# Install to PHP extension directory (requires sudo)
sudo make install
```

### Method 4: Manual Build

```bash
# Build the Rust extension
cargo build --release

# Find your PHP extension directory
PHP_EXT_DIR=$(php-config --extension-dir)

# Copy the extension (adjust .so/.dylib based on your OS)
sudo cp target/release/libumadb_php.so $PHP_EXT_DIR/umadb_php.so
```

## Enabling the Extension

### Method 1: php.ini Configuration (System-wide)

Find your php.ini file:

```bash
php --ini
```

Add the extension line:

```ini
extension=umadb_php.so
```

Restart your web server (if using PHP-FPM):

```bash
# On Ubuntu/Debian
sudo systemctl restart php8.2-fpm

# On macOS
brew services restart php
```

### Method 2: CLI Only (Per-script)

Run PHP with the extension loaded:

```bash
php -d extension=umadb_php.so your-script.php
```

### Method 3: Development php.ini (PHP CLI only)

Find PHP CLI ini directory:

```bash
php --ini | grep "Scan for additional"
```

Create a file in that directory:

```bash
echo "extension=umadb_php.so" | sudo tee /etc/php/8.2/cli/conf.d/20-umadb.ini
```

## Verifying Installation

Check if the extension is loaded:

```bash
php -m | grep umadb
```

You should see:

```
umadb_php
```

Or check with PHP code:

```php
<?php
if (extension_loaded('umadb_php')) {
    echo "UmaDB extension is loaded!\n";
} else {
    echo "UmaDB extension is NOT loaded.\n";
}
```

## Testing the Installation

### Start UmaDB Server

```bash
# Using Docker
docker run -d -p 50051:50051 ghcr.io/pyeventsourcing/umadb:latest

# Or build from source
cd /path/to/umadb
cargo run --bin umadb -- --listen 127.0.0.1:50051 --db-path /tmp/test.db
```

### Run Basic Test

Create a file `test.php`:

```php
<?php

use UmaDB\Client;
use UmaDB\Event;

try {
    $client = new Client('http://localhost:50051');
    echo "✓ Connected to UmaDB\n";

    $event = new Event('TestEvent', 'test data', ['test']);
    $position = $client->append([$event]);
    echo "✓ Event appended at position: {$position}\n";

    $head = $client->head();
    echo "✓ Current head: {$head}\n";

    echo "\n✓ Installation successful!\n";
} catch (Exception $e) {
    echo "✗ Error: {$e->getMessage()}\n";
}
```

Run it:

```bash
php test.php
```

## Troubleshooting

### Extension Not Found

**Error:** `PHP Warning: PHP Startup: Unable to load dynamic library 'umadb_php.so'`

**Solutions:**
1. Verify the extension was copied to the correct directory:
   ```bash
   ls $(php-config --extension-dir)/umadb_php.so
   ```

2. Check file permissions:
   ```bash
   sudo chmod 644 $(php-config --extension-dir)/umadb_php.so
   ```

3. On Linux, check SELinux context:
   ```bash
   sudo chcon -t textrel_shlib_t $(php-config --extension-dir)/umadb_php.so
   ```

### Symbol Not Found (macOS)

**Error:** `Symbol not found: _php_module_startup`

**Solution:** Rebuild with the correct PHP version:

```bash
# Ensure correct PHP is in PATH
which php
php --version

# Clean and rebuild
cargo clean
cargo build --release
```

### Undefined Class

**Error:** `Error: Class "UmaDB\Client" not found`

**Solutions:**
1. Verify extension is loaded:
   ```bash
   php -m | grep umadb
   ```

2. Check you're loading the extension before using it:
   ```bash
   php -d extension=umadb_php.so your-script.php
   ```

### Connection Refused

**Error:** `TransportException: Connection refused`

**Solutions:**
1. Ensure UmaDB server is running:
   ```bash
   docker ps | grep umadb
   # or
   ps aux | grep umadb
   ```

2. Check the server URL in your code matches where the server is running

3. Test connectivity:
   ```bash
   curl http://localhost:50051
   ```

### Build Fails

**Error:** Various compilation errors

**Solutions:**
1. Update Rust:
   ```bash
   rustup update stable
   ```

2. Clean and rebuild:
   ```bash
   cargo clean
   cargo build --release
   ```

3. Check Rust version:
   ```bash
   rustc --version  # Should be 1.70 or higher
   ```

## Uninstalling

To remove the extension:

```bash
# Remove the extension file
sudo rm $(php-config --extension-dir)/umadb_php.so

# Remove configuration (if you created it)
sudo rm /etc/php/*/*/conf.d/*umadb*.ini

# Restart PHP-FPM (if applicable)
sudo systemctl restart php*-fpm
```

## Next Steps

- Read the [README.md](README.md) for API documentation
- Check out [examples/](examples/) for usage examples
- Run the test suite: `composer install && vendor/bin/phpunit`
