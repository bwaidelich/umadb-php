dnl config.m4 for extension umadb

PHP_ARG_ENABLE([umadb],
  [whether to enable umadb support],
  [AS_HELP_STRING([--enable-umadb],
    [Enable umadb support])],
  [yes])

if test "$PHP_UMADB" != "no"; then
  dnl Check for cargo
  AC_PATH_PROG(CARGO, cargo, no)

  if test "$CARGO" = "no"; then
    AC_MSG_ERROR([cargo is required to build this extension. Install Rust from https://rustup.rs/])
  fi

  dnl Check for rustc
  AC_PATH_PROG(RUSTC, rustc, no)

  if test "$RUSTC" = "no"; then
    AC_MSG_ERROR([rustc is required to build this extension. Install Rust from https://rustup.rs/])
  fi

  dnl Check Rust version (need 1.70+)
  RUSTC_VERSION=$($RUSTC --version | cut -d' ' -f2)
  AC_MSG_CHECKING([for Rust version >= 1.70])
  AC_MSG_RESULT([$RUSTC_VERSION])

  dnl Define variables
  PHP_SUBST(UMADB_SHARED_LIBADD)

  dnl Build with cargo
  AC_MSG_NOTICE([Building Rust extension with cargo...])

  dnl Override LIBTOOL to use our wrapper script
  dnl This ensures the wrapper is used instead of the auto-generated libtool
  LIBTOOL='$(SHELL) $(top_srcdir)/libtool-wrapper.sh'
  PHP_SUBST(LIBTOOL)

  dnl Add custom build step that overrides the default build
  PHP_ADD_MAKEFILE_FRAGMENT

  dnl After configure finishes, replace the generated libtool with our wrapper
  dnl This ensures PIE and other build systems use our wrapper
  AC_CONFIG_COMMANDS([libtool-setup], [
    if test -f libtool; then
      mv libtool libtool.real
    fi
    cp "$srcdir/libtool-wrapper.sh" libtool
    chmod +x libtool
  ])

  dnl Register the extension with dummy source file
  dnl The actual extension is built by cargo via Makefile.frag
  PHP_NEW_EXTENSION(umadb, [umadb_dummy.c], $ext_shared,, , yes)
fi
