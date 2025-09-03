/* This is a generated file, edit the .stub.php file instead.
 * Stub hash: 8901f29b7fd757712e47f4dc69abe46208e5f644 */

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_GoCache_Driver_get, 0, 1, IS_STRING, 1)
	ZEND_ARG_TYPE_INFO(0, key, IS_STRING, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_GoCache_Driver_set, 0, 3, _IS_BOOL, 0)
	ZEND_ARG_TYPE_INFO(0, key, IS_STRING, 0)
	ZEND_ARG_TYPE_INFO(0, value, IS_STRING, 0)
	ZEND_ARG_TYPE_INFO(0, ttl, IS_LONG, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_GoCache_Driver_forget, 0, 1, _IS_BOOL, 0)
	ZEND_ARG_TYPE_INFO(0, key, IS_STRING, 0)
ZEND_END_ARG_INFO()

ZEND_FUNCTION(GoCache_Driver_get);
ZEND_FUNCTION(GoCache_Driver_set);
ZEND_FUNCTION(GoCache_Driver_forget);

static const zend_function_entry ext_functions[] = {
	ZEND_RAW_FENTRY(ZEND_NS_NAME("GoCache\\Driver", "get"), zif_GoCache_Driver_get, arginfo_GoCache_Driver_get, 0, NULL, NULL)
	ZEND_RAW_FENTRY(ZEND_NS_NAME("GoCache\\Driver", "set"), zif_GoCache_Driver_set, arginfo_GoCache_Driver_set, 0, NULL, NULL)
	ZEND_RAW_FENTRY(ZEND_NS_NAME("GoCache\\Driver", "forget"), zif_GoCache_Driver_forget, arginfo_GoCache_Driver_forget, 0, NULL, NULL)
	ZEND_FE_END
};
