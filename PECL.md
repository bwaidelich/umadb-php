# PECL Installation Guide

This extension can be installed via PECL, though it has special requirements due to being built with Rust.

## Prerequisites

Before installing via PECL, you **must** have:

1. **PHP 8.4+** with development headers
2. **PECL** (comes with php-pear)
3. **Rust 1.70+** installed from [rustup.rs](https://rustup.rs/)
4. **Cargo** (comes with Rust)

### Installing Prerequisites

#### Ubuntu/Debian

```bash
sudo apt update
sudo apt install php-pear php-dev

# Install Rust
curl --proto '=https' --tlsv1.2 -sSf https://sh.rustup.rs | sh
source $HOME/.cargo/env
```

#### macOS

```bash
brew install php

# PECL comes with PHP on macOS
# Install Rust
curl --proto '=https' --tlsv1.2 -sSf https://sh.rustup.rs | sh
source $HOME/.cargo/env
```

#### Verify Prerequisites

```bash
# Check PECL
pecl version

# Check Rust
rustc --version  # Should be 1.70.0 or higher
cargo --version
```

## Installation via PECL

### From PECL Repository (Once Published)

```bash
pecl install umadb
```

### From Local Package

```bash
# Build the package
pecl package

# Install from local .tgz file
pecl install umadb-0.1.1.tgz
```

### From Git Repository

```bash
# Clone repository
git clone https://github.com/bwaidelich/umadb-php.git
cd umadb-php

# Build and install with PECL
pecl install package.xml
```

## Enable the Extension

PECL usually adds the extension to php.ini automatically, but you can verify:

```bash
# Check if enabled
php -m | grep umadb
```

If not enabled, add to your `php.ini`:

```ini
extension=umadb.so
```

Find your php.ini:

```bash
php --ini
```

## Alternative: Manual Build with PECL Tools

You can also use phpize (from PECL) for a more traditional build:

```bash
# Prepare the extension
phpize

# Configure (checks for cargo/rustc)
./configure --enable-umadb

# Build (calls cargo internally)
make

# Install
sudo make install
```

## How It Works

This extension is **special** because it's built with Rust, not C:

1. **`config.m4`** - Checks for Rust/Cargo (instead of C compiler)
2. **`Makefile.frag`** - Calls `cargo build` (instead of compiling C files)
3. **Build process** - Uses Rust toolchain to create the extension

The PECL infrastructure is used for packaging and distribution, but the actual compilation is done by Cargo.

## Creating a PECL Package

For maintainers wanting to publish to PECL:

### 1. Prepare package.xml

The `package.xml` file is already included and defines:
- Package metadata (name, description, authors)
- Version and stability
- File list and roles
- Dependencies (PHP 8.0+)
- License information

### 2. Build the Package

```bash
pecl package
```

This creates: `umadb-0.1.1.tgz`

### 3. Test Local Installation

```bash
pecl install umadb-0.1.1.tgz
```

### 4. Publish to PECL

Request an account at https://pecl.php.net/account-request.php

Then:

```bash
# Upload package
pecl upload umadb-0.1.1.tgz
```

Or via web interface at https://pecl.php.net/package-new.php

### 5. Update for New Releases

Update `package.xml`:
- Version numbers
- Release date
- Release notes

Then rebuild and upload:

```bash
pecl package
# Upload new version
```

## Differences from Traditional PECL Extensions

| Aspect | Traditional PECL | UmaDB Extension |
|--------|-----------------|-----------------|
| Language | C/C++ | Rust |
| Build Tool | gcc/clang | cargo |
| API | Zend Engine | ext-php-rs |
| Dependencies | System libraries | Rust crates |
| Build Time | Fast (seconds) | Slower (Rust compile) |

## Advantages of Rust-based Extension

✅ **Memory Safety** - No buffer overflows or use-after-free
✅ **Modern Tooling** - Cargo package management
✅ **Type Safety** - Compile-time guarantees
✅ **Performance** - Zero-cost abstractions
✅ **Maintainability** - Safer refactoring

## Limitations

⚠️ **Requires Rust** - Users must have Rust installed
⚠️ **Larger Binary** - Rust binaries are typically larger
⚠️ **Build Time** - Initial compilation is slower
⚠️ **Not Precompiled** - No binary distributions on PECL

## Troubleshooting

### "cargo: command not found"

Install Rust:

```bash
curl --proto '=https' --tlsv1.2 -sSf https://sh.rustup.rs | sh
source $HOME/.cargo/env
```

### "Rust version too old"

Update Rust:

```bash
rustup update stable
```

### Build Fails During PECL Install

Try manual build:

```bash
# Get the source
pecl download umadb

# Extract
tar -xzf umadb-0.1.1.tgz
cd umadb-0.1.1

# Build directly with cargo
cargo build --release

# Copy manually
sudo cp target/release/libumadb_php.so $(php-config --extension-dir)/umadb.so
```

### Extension Loads but Classes Not Found

Check the extension name in php.ini:

```ini
# Should be:
extension=umadb.so

# NOT:
extension=umadb_php.so
```

The PECL build renames the library for consistency.

## See Also

- [INSTALL.md](INSTALL.md) - Manual installation guide
- [README.md](README.md) - API documentation
- [QUICKSTART.md](QUICKSTART.md) - Quick start guide
- [PECL Documentation](https://pecl.php.net/support.php) - General PECL help
