#!/bin/bash
# Libtool wrapper that builds with Cargo instead of linking with libtool
#
# This script intercepts the libtool call and builds the Rust extension with Cargo.
# It's called by the PHP extension build system when it tries to link the extension.

# Find the source directory (where Cargo.toml is)
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

# Check if this is the link step (creating umadb.la)
if [[ "$*" == *"--mode=link"* ]] && [[ "$*" == *"umadb.la"* ]]; then
    echo ""
    echo "========================================"
    echo "Intercepted libtool link step"
    echo "Building Rust extension with Cargo..."
    echo "This may take a few minutes on first build"
    echo "========================================"

    # Determine OS and extension file name
    if [[ "$(uname -s)" == "Linux" ]]; then
        EXT_FILE="libumadb.so"
    else
        EXT_FILE="libumadb.dylib"
    fi

    # Build with Cargo in the source directory
    (cd "$SCRIPT_DIR" && cargo build --release)

    if [ $? -ne 0 ]; then
        echo "✗ Cargo build failed"
        exit 1
    fi

    # Create directories in current (build) directory
    mkdir -p .libs modules

    # Copy the built extension from source directory to build directory
    cp "$SCRIPT_DIR/target/release/$EXT_FILE" .libs/umadb.so
    cp "$SCRIPT_DIR/target/release/$EXT_FILE" modules/umadb.so

    # Create a dummy .la file to satisfy the build system
    echo "# Dummy libtool library file" > umadb.la
    echo "dlname='umadb.so'" >> umadb.la
    echo "library_names='umadb.so'" >> umadb.la
    echo "installed=no" >> umadb.la

    echo "✓ Rust extension built successfully"
    echo "✓ Extension ready at: .libs/umadb.so"
    exit 0
fi

# Check if this is the install step (installing umadb.la)
if [[ "$*" == *"--mode=install"* ]] && [[ "$*" == *"umadb.la"* ]]; then
    # Extract the destination directory from the arguments
    dest=""
    for arg in "$@"; do
        if [[ "$arg" == /* ]]; then
            parent_dir=$(dirname "$arg" 2>/dev/null)
            if [ -d "$parent_dir" ]; then
                dest="$arg"
                break
            fi
        fi
    done

    if [ -n "$dest" ]; then
        # Install means copying the built extension to the destination
        mkdir -p "$(dirname "$dest")"
        if [ -f ".libs/umadb.so" ]; then
            cp .libs/umadb.so "$(dirname "$dest")/umadb.so"
            echo "✓ Extension installed to: $(dirname "$dest")/umadb.so"
            exit 0
        fi
    fi
fi

# For compile steps, extract and run the compiler command directly
if [[ "$*" == *"--mode=compile"* ]]; then
    # Parse out the compiler and its arguments
    # Skip libtool-specific args and pass through compiler args
    args=()
    skip_next=false
    compiler="cc"

    for arg in "$@"; do
        if [ "$skip_next" = true ]; then
            skip_next=false
            continue
        fi

        case "$arg" in
            --mode=compile|--tag=*|-I.|-DHAVE_CONFIG_H)
                # Skip libtool-specific arguments
                continue
                ;;
            -o)
                # Collect output file argument
                skip_next=true
                args+=("$arg")
                ;;
            *.c|*.cc|*.cpp|-c|-I*|-D*|-g|-O*|-fno-common|-DPIC|-MMD|-MF|-MT)
                # Pass through compiler arguments
                args+=("$arg")
                ;;
            cc|gcc|g++|clang|clang++)
                # Detect compiler
                compiler="$arg"
                ;;
        esac
    done

    # Execute the compiler
    exec "$compiler" "${args[@]}"
fi

# For other operations, try to use the real libtool if it exists
if [ -f "$SCRIPT_DIR/libtool.real" ]; then
    exec "$SCRIPT_DIR/libtool.real" "$@"
elif [ -f "./libtool.real" ]; then
    exec ./libtool.real "$@"
else
    # If no real libtool exists and this is an unhandled operation,
    # provide a helpful error message
    echo "Error: Real libtool not found and don't know how to handle: $*"
    echo "This extension requires Cargo to build. The libtool wrapper should intercept the build."
    exit 1
fi
