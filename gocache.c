#include <php.h>
#include "gocache.h"
#include "gocache_arginfo.h"
#include "_cgo_export.h"

PHP_FUNCTION(GoCache_Driver_get)
{
    zend_string *key;
    ZEND_PARSE_PARAMETERS_START(1, 1)
        Z_PARAM_STR(key)
    ZEND_PARSE_PARAMETERS_END();

    zend_string *result = go_get(key);
    if (result) {
        RETURN_STR(result);
    }
    RETURN_NULL();
}

PHP_FUNCTION(GoCache_Driver_set)
{
    zend_string *key, *value;
    zend_long ttl;
    ZEND_PARSE_PARAMETERS_START(3, 3)
        Z_PARAM_STR(key)
        Z_PARAM_STR(value)
        Z_PARAM_LONG(ttl)
    ZEND_PARSE_PARAMETERS_END();

    go_set(key, value, ttl);
    RETURN_TRUE;
}

PHP_FUNCTION(GoCache_Driver_forget)
{
    zend_string *key;
    ZEND_PARSE_PARAMETERS_START(1, 1)
        Z_PARAM_STR(key)
    ZEND_PARSE_PARAMETERS_END();

    go_forget(key);
    RETURN_TRUE;
}

zend_module_entry gocache_module_entry = {
    STANDARD_MODULE_HEADER,
    "gocache",
    ext_functions,
    NULL,
    NULL,
    NULL,
    NULL,
    NULL,
    "0.1",
    STANDARD_MODULE_PROPERTIES
};