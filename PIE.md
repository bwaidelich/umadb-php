# PIE Installation Guide

UmaDB PHP extension supports installation via [PIE](https://github.com/php/pie) (PHP Installer for Extensions), the modern successor to PECL.

## What is PIE?

PIE is a new installer for PHP extensions that works like Composer but for extensions. It simplifies the installation and management of PHP extensions with better dependency resolution and a more user-friendly experience.

## Requirements

Before installing via PIE, ensure you have:

- **PHP 8.4 or higher**
- **Rust 1.70+** installed from [rustup.rs](https://rustup.rs/)
- **PIE** installed globally

### Install Rust

```bash
curl --proto '=https' --tlsv1.2 -sSf https://sh.rustup.rs | sh
source $HOME/.cargo/env
```

### Install PIE

Download the latest PIE PHAR from the [releases page](https://github.com/php/pie/releases):

```bash
# Download PIE
wget https://github.com/php/pie/releases/latest/download/pie.phar
chmod +x pie.phar
sudo mv pie.phar /usr/local/bin/pie

# Verify installation
pie --version
```

## Installation

### Option 1: Install from Packagist

Once the package is published to Packagist:

```bash
pie install wwwision/umadb-php
```

### Option 2: Install from GitHub

```bash
pie install wwwision/umadb-php --repository=https://github.com/bwaidelich/umadb-php.git
```

### Option 3: Install Locally

```bash
# Clone the repository
git clone https://github.com/bwaidelich/umadb-php.git
cd umadb-php

# Install with PIE
pie install .
```

## Configuration

PIE will automatically:

1. Download the extension source
2. Build the extension using Cargo
3. Install the compiled extension to your PHP extension directory
4. Enable the extension in your php.ini

You can verify the installation:

```bash
php -m | grep umadb
```

## Project Integration

To require the UmaDB extension in your PHP project, add it to your `composer.json`:

```json
{
    "require": {
        "php": "^8.4",
        "ext-umadb": "*"
    }
}
```

Then run PIE in your project directory:

```bash
composer install
pie install
```

PIE will detect the `ext-umadb` requirement and automatically install the extension.

## Building from Source

PIE handles the build process automatically, but you can customize the build:

```bash
# Install with specific configure options
pie install wwwision/umadb-php --with-enable-umadb
```

## Advanced Usage

### Specifying PHP Version

```bash
# Install for a specific PHP version
pie install wwwision/umadb-php --php=/usr/bin/php8.4
```

### Development Builds

```bash
# Install in development mode (with debug symbols)
pie install . --dev
```

## Troubleshooting

### Rust Not Found

If PIE reports that Rust is not found:

```bash
curl --proto '=https' --tlsv1.2 -sSf https://sh.rustup.rs | sh
source $HOME/.cargo/env
rustc --version  # Verify installation
```

### Build Fails

If the build fails, ensure you have:

1. Rust 1.70+ installed: `rustc --version`
2. Cargo available: `cargo --version`
3. PHP development headers: `php-config --version`

### Extension Not Loading

If the extension builds but doesn't load:

```bash
# Check PHP extension directory
php -i | grep extension_dir

# Verify the .so file exists
ls $(php-config --extension-dir)/umadb.so

# Check php.ini
php --ini
```

## Comparison with PECL

| Feature | PECL | PIE |
|---------|------|-----|
| Dependency Resolution | Limited | Full (via Composer) |
| Build System | phpize/configure | Supports custom (Cargo) |
| Version Constraints | Basic | Composer-style |
| Per-Project Extensions | No | Yes |
| Modern CLI | No | Yes |
| Active Development | Limited | Active |

## Special Notes for UmaDB

**Important:** UmaDB PHP extension is built with Rust, not C. This means:

- ✅ You **must** have Rust installed
- ✅ Build time is longer than typical C extensions (first build ~1-2 minutes)
- ✅ The extension is more memory-safe and performant
- ✅ PIE handles the Cargo build automatically

## See Also

- [PIE Documentation](https://github.com/php/pie)
- [INSTALL.md](INSTALL.md) - Manual installation guide
- [PECL.md](PECL.md) - PECL installation guide
- [README.md](README.md) - API documentation
