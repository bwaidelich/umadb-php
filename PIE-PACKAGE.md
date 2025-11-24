# PIE Package Configuration

This document describes the PIE (PHP Installer for Extensions) package configuration for the UmaDB PHP extension.

## Overview

PIE is the modern successor to PECL, providing a Composer-like experience for installing PHP extensions. The UmaDB extension now supports installation via PIE while maintaining backward compatibility with PECL.

## Package Configuration

### composer.json

The main PIE configuration is in `composer.json`:

```json
{
    "name": "wwwision/umadb-php",
    "type": "php-ext",
    "require": {
        "php": "^8.4"
    },
    "replace": {
        "ext-umadb": "*"
    },
    "php-ext": {
        "extension-name": "umadb",
        "priority": 20,
        "support-zts": false,
        "configure-options": [
            {
                "name": "enable-umadb",
                "description": "Enable UmaDB support",
                "needs-value": false
            }
        ]
    }
}
```

### Key Fields

- **type**: `php-ext` identifies this as a PHP extension package
- **replace**: Declares that this package provides `ext-umadb` virtual package
- **php-ext**: Extension-specific metadata for PIE

### Extension Metadata

- **extension-name**: The actual PHP extension name (`umadb`)
- **priority**: Load priority (20 = default, loaded early)
- **support-zts**: `false` - not ZTS-compatible (uses Tokio runtime)
- **configure-options**: Build configuration flags

## Installation Methods

### 1. Via PIE (Recommended)

```bash
pie install wwwision/umadb-php
```

### 2. Project Dependency

In your project's `composer.json`:

```json
{
    "require": {
        "ext-umadb": "*"
    }
}
```

Then:

```bash
composer install
pie install
```

## Build Process

PIE will:

1. Clone/download the extension source
2. Detect `config.m4` (PECL compatibility)
3. Run `phpize` and `./configure`
4. Execute `make` which triggers Cargo via `Makefile.frag`
5. Install the compiled `.so`/`.dylib` to PHP's extension directory
6. Enable the extension in `php.ini`

## Special Considerations for Rust Extensions

### Build Requirements

Unlike typical PHP extensions built with C, UmaDB requires:

- **Rust 1.70+** and Cargo
- The `build.rs` script for platform-specific linking
- Cargo workspace with dependencies from git

### Build Time

First build: ~1-2 minutes (Rust compilation + dependencies)
Subsequent builds: ~20-30 seconds (incremental compilation)

### Platform Support

- **Linux**: Full support with dynamic linking
- **macOS**: Full support with dynamic lookup (`-undefined dynamic_lookup`)
- **Windows**: Not yet tested (may require adjustments)

## Compatibility Matrix

| Tool | Support | Notes |
|------|---------|-------|
| PIE | ✅ Full | Modern, recommended |
| PECL | ✅ Full | Traditional, still works |
| Manual Build | ✅ Full | Via `cargo build` or `make` |
| Composer | ⚠️ Partial | Can declare dependency, can't install |

## Publishing

### To Packagist (for PIE)

1. Ensure the GitHub repository is public at https://github.com/bwaidelich/umadb-php
2. Submit to [Packagist](https://packagist.org/packages/submit)
3. Add the GitHub webhook for auto-updates

### To PECL

1. Create PECL account at https://pecl.php.net/account-request.php
2. Upload `umadb-0.1.1.tgz` via web interface
3. Or use: `pecl upload umadb-0.1.1.tgz`

## Testing the PIE Package

### Validate Configuration

```bash
composer validate
```

### Test Local Installation

```bash
pie install .
php -m | grep umadb
```

### Test from Packagist (after publishing)

```bash
pie install wwwision/umadb-php
```

## Differences from Pure C Extensions

Traditional C extensions:
- Use `./configure` with autotools
- Compile with `gcc`/`clang`
- Link against PHP libraries

UmaDB extension:
- Uses `config.m4` for PECL compatibility
- Actually builds with Cargo (Rust)
- Uses `ext-php-rs` bindings
- Dynamic linking via `build.rs`

PIE handles this gracefully because it:
- Doesn't assume the build tool
- Just runs `make` after `./configure`
- Our `Makefile.frag` delegates to Cargo

## Documentation

- [PIE.md](PIE.md) - User installation guide
- [PECL.md](PECL.md) - PECL installation guide
- [INSTALL.md](INSTALL.md) - Manual installation guide

## References

- [PIE GitHub](https://github.com/php/pie)
- [PIE Design Docs](https://github.com/ThePHPF/pie-design)
- [ext-php-rs Documentation](https://docs.rs/ext-php-rs/)
