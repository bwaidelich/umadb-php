# Makefile fragment for PHP extension build
# This intercepts the build process by using our wrapper script as libtool

# Force override LIBTOOL to use our wrapper script (override is needed because LIBTOOL is set earlier)
override LIBTOOL = $(SHELL) $(top_srcdir)/libtool-wrapper.sh

# Detect OS
UNAME_S := $(shell uname -s)
ifeq ($(UNAME_S),Linux)
    EXT_FILE = libumadb_php.so
endif
ifeq ($(UNAME_S),Darwin)
    EXT_FILE = libumadb_php.dylib
endif
