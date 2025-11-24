/* Dummy C file for PECL build system compatibility
 *
 * The actual UmaDB extension is built with Rust using Cargo.
 * This file provides minimal PHP module scaffolding to satisfy libtool linking.
 */

#ifdef HAVE_CONFIG_H
#include "config.h"
#endif

#include "php.h"
#include "php_ini.h"
#include "ext/standard/info.h"

/* Dummy module entry - will be replaced by Rust extension */
zend_module_entry umadb_module_entry = {
    STANDARD_MODULE_HEADER,
    "umadb",                /* Extension name */
    NULL,                   /* zend_function_entry */
    NULL,                   /* PHP_MINIT */
    NULL,                   /* PHP_MSHUTDOWN */
    NULL,                   /* PHP_RINIT */
    NULL,                   /* PHP_RSHUTDOWN */
    NULL,                   /* PHP_MINFO */
    "0.1.1",                /* Version */
    STANDARD_MODULE_PROPERTIES
};

#ifdef COMPILE_DL_UMADB
ZEND_GET_MODULE(umadb)
#endif
