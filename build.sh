#!/usr/bin/env bash
set -e

echo "Building UmaDB PHP Extension..."

# Check for Rust/Cargo
if ! command -v cargo &> /dev/null; then
    echo "Error: cargo not found. Please install Rust from https://rustup.rs/"
    exit 1
fi

# Check for PHP
if ! command -v php &> /dev/null; then
    echo "Error: php not found. Please install PHP 8.0 or higher."
    exit 1
fi

# Check PHP version
PHP_VERSION=$(php -r 'echo PHP_MAJOR_VERSION . "." . PHP_MINOR_VERSION;')
echo "Detected PHP version: $PHP_VERSION"

if [ $(php -r 'echo (PHP_VERSION_ID >= 80000) ? 1 : 0;') -eq 0 ]; then
    echo "Error: PHP 8.0 or higher is required. Current version: $(php -r 'echo PHP_VERSION;')"
    exit 1
fi

# Build the extension
echo "Building release binary..."
cargo build --release

# Determine extension file
UNAME_S=$(uname -s)
if [ "$UNAME_S" = "Linux" ]; then
    EXT_FILE="target/release/libumadb.so"
elif [ "$UNAME_S" = "Darwin" ]; then
    EXT_FILE="target/release/libumadb.dylib"
else
    echo "Error: Unsupported platform: $UNAME_S"
    exit 1
fi

if [ ! -f "$EXT_FILE" ]; then
    echo "Error: Extension file not found: $EXT_FILE"
    exit 1
fi

echo ""
echo "✓ Build successful!"
echo "Extension location: $EXT_FILE"
echo ""
echo "To install the extension:"
echo "  1. Copy the extension to your PHP extension directory:"
echo "     sudo cp $EXT_FILE \$(php-config --extension-dir)/umadb.so"
echo ""
echo "  2. Add to your php.ini:"
echo "     extension=umadb.so"
echo ""
echo "  3. Verify installation:"
echo "     php -m | grep umadb"
echo ""
