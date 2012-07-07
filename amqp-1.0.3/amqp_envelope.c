/*
  +----------------------------------------------------------------------+
  | PHP Version 5                                                        |
  +----------------------------------------------------------------------+
  | Copyright (c) 1997-2007 The PHP Group                                |
  +----------------------------------------------------------------------+
  | This source file is subject to version 3.01 of the PHP license,      |
  | that is bundled with this package in the file LICENSE, and is        |
  | available through the world-wide-web at the following url:           |
  | http://www.php.net/license/3_01.txt                                  |
  | If you did not receive a copy of the PHP license and are unable to   |
  | obtain it through the world-wide-web, please send a note to          |
  | license@php.net so we can mail you a copy immediately.               |
  +----------------------------------------------------------------------+
  | Author: Alexandre Kalendarev akalend@mail.ru Copyright (c) 2009-2010 |
  | Lead:                                                                |
  | - Pieter de Zwart                                                    |
  | Maintainers:                                                         |
  | - Brad Rodriguez                                                     |
  | - Jonathan Tansavatdi                                                |
  +----------------------------------------------------------------------+
*/

/* $Id: amqp_envelope.c 322514 2012-01-20 20:10:15Z pdezwart $ */

#ifdef HAVE_CONFIG_H
#include "config.h"
#endif

#include "php.h"
#include "php_ini.h"
#include "ext/standard/info.h"
#include "zend_exceptions.h"

#include <stdint.h>
#include <signal.h>
#include <amqp.h>
#include <amqp_framing.h>

#include <unistd.h>

#include "php_amqp.h"

#if PHP_MAJOR_VERSION == 5 && PHP_MINOR_VERSION >= 3
HashTable *amqp_envelope_object_get_debug_info(zval *object, int *is_temp TSRMLS_DC) {
	zval *value;
	
	/* Get the envelope object from which to read */
	amqp_envelope_object *envelope = (amqp_envelope_object *)zend_object_store_get_object(object TSRMLS_CC);
	
	/* Super magic make shit work variable. Seriously though, without this using print_r and/or var_dump will either cause memory leak or crash. */
	*is_temp = 1;
	
	/* Keep the # 18 matching the number of entries in this table*/
	ALLOC_HASHTABLE(envelope->debug_info);
	ZEND_INIT_SYMTABLE_EX(envelope->debug_info, 18 + 1, 0);
	
	/* Start adding values */
	MAKE_STD_ZVAL(value);
	ZVAL_STRINGL(value, envelope->body, strlen(envelope->body), 1);
	zend_hash_add(envelope->debug_info, "body", strlen("body") + 1, &value, sizeof(zval *), NULL);
	
	MAKE_STD_ZVAL(value);
	ZVAL_STRINGL(value, envelope->content_type, strlen(envelope->content_type), 1);
	zend_hash_add(envelope->debug_info, "content_type", strlen("content_type") + 1, &value, sizeof(zval *), NULL);
	
	MAKE_STD_ZVAL(value);
	ZVAL_STRINGL(value, envelope->routing_key, strlen(envelope->routing_key), 1);
	zend_hash_add(envelope->debug_info, "routing_key", strlen("routing_key") + 1, &value, sizeof(zval *), NULL);
	
	MAKE_STD_ZVAL(value);
	ZVAL_LONG(value, envelope->delivery_tag);
	zend_hash_add(envelope->debug_info, "delivery_tag", strlen("delivery_tag") + 1, &value, sizeof(zval *), NULL);
	
	MAKE_STD_ZVAL(value);
	ZVAL_LONG(value, envelope->delivery_mode);
	zend_hash_add(envelope->debug_info, "delivery_mode", strlen("delivery_mode") + 1, &value, sizeof(zval *), NULL);
	
	MAKE_STD_ZVAL(value);
	ZVAL_STRINGL(value, envelope->exchange_name, strlen(envelope->exchange_name), 1);
	zend_hash_add(envelope->debug_info, "exchange_name", strlen("exchange_name") + 1, &value, sizeof(zval *), NULL);
	
	MAKE_STD_ZVAL(value);
	ZVAL_LONG(value, envelope->is_redelivery);
	zend_hash_add(envelope->debug_info, "is_redelivery", strlen("is_redelivery") + 1, &value, sizeof(zval *), NULL);
		
	MAKE_STD_ZVAL(value);
	ZVAL_STRINGL(value, envelope->content_encoding, strlen(envelope->content_encoding), 1);
	zend_hash_add(envelope->debug_info, "content_encoding", strlen("content_encoding") + 1, &value, sizeof(zval *), NULL);
	
	MAKE_STD_ZVAL(value);
	ZVAL_STRINGL(value, envelope->type, strlen(envelope->type), 1);
	zend_hash_add(envelope->debug_info, "type", strlen("type") + 1, &value, sizeof(zval *), NULL);
	
	MAKE_STD_ZVAL(value);
	ZVAL_LONG(value, envelope->timestamp);
	zend_hash_add(envelope->debug_info, "timestamp", strlen("timestamp") + 1, &value, sizeof(zval *), NULL);
	
	MAKE_STD_ZVAL(value);
	ZVAL_LONG(value, envelope->priority);
	zend_hash_add(envelope->debug_info, "priority", strlen("priority") + 1, &value, sizeof(zval *), NULL);
	
	MAKE_STD_ZVAL(value);
	ZVAL_STRINGL(value, envelope->expiration, strlen(envelope->expiration), 1);
	zend_hash_add(envelope->debug_info, "expiration", strlen("expiration") + 1, &value, sizeof(zval *), NULL);
	
	MAKE_STD_ZVAL(value);
	ZVAL_STRINGL(value, envelope->user_id, strlen(envelope->user_id), 1);
	zend_hash_add(envelope->debug_info, "user_id", strlen("user_id") + 1, &value, sizeof(zval *), NULL);
	
	MAKE_STD_ZVAL(value);
	ZVAL_STRINGL(value, envelope->app_id, strlen(envelope->app_id), 1);
	zend_hash_add(envelope->debug_info, "app_id", strlen("app_id") + 1, &value, sizeof(zval *), NULL);
	
	MAKE_STD_ZVAL(value);
	ZVAL_STRINGL(value, envelope->message_id, strlen(envelope->message_id), 1);
	zend_hash_add(envelope->debug_info, "message_id", strlen("message_id") + 1, &value, sizeof(zval *), NULL);
	
	MAKE_STD_ZVAL(value);
	ZVAL_STRINGL(value, envelope->reply_to, strlen(envelope->reply_to), 1);
	zend_hash_add(envelope->debug_info, "reply_to", strlen("reply_to") + 1, &value, sizeof(zval *), NULL);
	
	MAKE_STD_ZVAL(value);
	ZVAL_STRINGL(value, envelope->correlation_id, strlen(envelope->correlation_id), 1);
	zend_hash_add(envelope->debug_info, "correlation_id", strlen("correlation_id") + 1, &value, sizeof(zval *), NULL);
	
	Z_ADDREF_P(envelope->headers);
	zend_hash_add(envelope->debug_info, "headers", strlen("headers") + 1, &envelope->headers, sizeof(envelope->headers), NULL);
	
	return envelope->debug_info;
}
#endif

void amqp_envelope_dtor(void *object TSRMLS_DC)
{
	amqp_envelope_object *envelope = (amqp_envelope_object*)object;
	
	if (envelope->headers) {
		zval_dtor(envelope->headers);
		efree(envelope->headers);
	}
	
	if (envelope->body) {
		efree(envelope->body);
	}
	
	zend_object_std_dtor(&envelope->zo TSRMLS_CC);
	
	efree(object);
}

zend_object_value amqp_envelope_ctor(zend_class_entry *ce TSRMLS_DC)
{
	zend_object_value new_value;
	amqp_envelope_object *envelope = (amqp_envelope_object*)emalloc(sizeof(amqp_envelope_object));

	memset(envelope, 0, sizeof(amqp_envelope_object));

	MAKE_STD_ZVAL(envelope->headers);
	array_init(envelope->headers);

	zend_object_std_init(&envelope->zo, ce TSRMLS_CC);

	new_value.handle = zend_objects_store_put(envelope, (zend_objects_store_dtor_t)zend_objects_destroy_object, (zend_objects_free_object_storage_t)amqp_envelope_dtor, NULL TSRMLS_CC);

#if 0 && PHP_MAJOR_VERSION == 5 && PHP_MINOR_VERSION >= 3
	zend_object_handlers *handlers;
	handlers = zend_get_std_object_handlers();
	handlers->get_debug_info = amqp_envelope_object_get_debug_info;
	new_value.handlers = handlers;
#else
	new_value.handlers = zend_get_std_object_handlers();
#endif

	return new_value;
}

/* {{{ proto AMQPEnvelope::__construct(AMQPConnection obj)
 */
PHP_METHOD(amqp_envelope_class, __construct)
{
	zval *id;
	
	amqp_envelope_object *envelope;

	/* Parse out the method parameters */
	if (zend_parse_method_parameters(ZEND_NUM_ARGS() TSRMLS_CC, getThis(), "O", &id, amqp_envelope_class_entry) == FAILURE) {
		return;
	}

	envelope = (amqp_envelope_object *)zend_object_store_get_object(id TSRMLS_CC);

}
/* }}} */


/* {{{ proto AMQPEnvelope::getBody()
check amqp envelope */
PHP_METHOD(amqp_envelope_class, getBody)
{
	zval *id;
	amqp_envelope_object *envelope;

	/* Try to pull amqp object out of method params */
	if (zend_parse_method_parameters(ZEND_NUM_ARGS() TSRMLS_CC, getThis(), "O", &id, amqp_envelope_class_entry) == FAILURE) {
		return;
	}

	/* Get the envelope object out of the store */
	envelope = (amqp_envelope_object *)zend_object_store_get_object(id TSRMLS_CC);

	if (envelope->body == 0) {
		RETURN_FALSE;
	}

	RETURN_STRING(envelope->body, 1);
}
/* }}} */

/* {{{ proto AMQPEnvelope::getRoutingKey()
check amqp envelope */
PHP_METHOD(amqp_envelope_class, getRoutingKey)
{
	zval *id;
	amqp_envelope_object *envelope;

	/* Try to pull amqp object out of method params */
	if (zend_parse_method_parameters(ZEND_NUM_ARGS() TSRMLS_CC, getThis(), "O", &id, amqp_envelope_class_entry) == FAILURE) {
		return;
	}

	/* Get the envelope object out of the store */
	envelope = (amqp_envelope_object *)zend_object_store_get_object(id TSRMLS_CC);

	RETURN_STRING(envelope->routing_key, 1);
}
/* }}} */

/* {{{ proto AMQPEnvelope::getDeliveryTag()
check amqp envelope */
PHP_METHOD(amqp_envelope_class, getDeliveryMode)
{
	zval *id;
	amqp_envelope_object *envelope;

	/* Try to pull amqp object out of method params */
	if (zend_parse_method_parameters(ZEND_NUM_ARGS() TSRMLS_CC, getThis(), "O", &id, amqp_envelope_class_entry) == FAILURE) {
		return;
	}

	/* Get the envelope object out of the store */
	envelope = (amqp_envelope_object *)zend_object_store_get_object(id TSRMLS_CC);

	RETURN_LONG(envelope->delivery_mode);
}
/* }}} */


/* {{{ proto AMQPEnvelope::getDeliveryTag()
check amqp envelope */
PHP_METHOD(amqp_envelope_class, getDeliveryTag)
{
	zval *id;
	amqp_envelope_object *envelope;

	/* Try to pull amqp object out of method params */
	if (zend_parse_method_parameters(ZEND_NUM_ARGS() TSRMLS_CC, getThis(), "O", &id, amqp_envelope_class_entry) == FAILURE) {
		return;
	}

	/* Get the envelope object out of the store */
	envelope = (amqp_envelope_object *)zend_object_store_get_object(id TSRMLS_CC);

	RETURN_LONG(envelope->delivery_tag);
}
/* }}} */

/* {{{ proto AMQPEnvelope::getExchangeName()
check amqp envelope */
PHP_METHOD(amqp_envelope_class, getExchangeName)
{
	zval *id;
	amqp_envelope_object *envelope;

	/* Try to pull amqp object out of method params */
	if (zend_parse_method_parameters(ZEND_NUM_ARGS() TSRMLS_CC, getThis(), "O", &id, amqp_envelope_class_entry) == FAILURE) {
		return;
	}

	/* Get the envelope object out of the store */
	envelope = (amqp_envelope_object *)zend_object_store_get_object(id TSRMLS_CC);

	RETURN_STRING(envelope->exchange_name, 1);
}
/* }}} */

/* {{{ proto AMQPEnvelope::isRedelivery()
check amqp envelope */
PHP_METHOD(amqp_envelope_class, isRedelivery)
{
	zval *id;
	amqp_envelope_object *envelope;

	/* Try to pull amqp object out of method params */
	if (zend_parse_method_parameters(ZEND_NUM_ARGS() TSRMLS_CC, getThis(), "O", &id, amqp_envelope_class_entry) == FAILURE) {
		return;
	}

	/* Get the envelope object out of the store */
	envelope = (amqp_envelope_object *)zend_object_store_get_object(id TSRMLS_CC);

	/* We have no envelope */
	RETURN_BOOL(envelope->is_redelivery);
}
/* }}} */

/* {{{ proto AMQPEnvelope::getContentType()
check amqp envelope */
PHP_METHOD(amqp_envelope_class, getContentType)
{
	zval *id;
	amqp_envelope_object *envelope;

	/* Try to pull amqp object out of method params */
	if (zend_parse_method_parameters(ZEND_NUM_ARGS() TSRMLS_CC, getThis(), "O", &id, amqp_envelope_class_entry) == FAILURE) {
		return;
	}

	/* Get the envelope object out of the store */
	envelope = (amqp_envelope_object *)zend_object_store_get_object(id TSRMLS_CC);

	RETURN_STRING(envelope->content_type, 1);
}
/* }}} */

/* {{{ proto AMQPEnvelope::getContentEncoding()
check amqp envelope */
PHP_METHOD(amqp_envelope_class, getContentEncoding)
{
	zval *id;
	amqp_envelope_object *envelope;

	/* Try to pull amqp object out of method params */
	if (zend_parse_method_parameters(ZEND_NUM_ARGS() TSRMLS_CC, getThis(), "O", &id, amqp_envelope_class_entry) == FAILURE) {
		return;
	}

	/* Get the envelope object out of the store */
	envelope = (amqp_envelope_object *)zend_object_store_get_object(id TSRMLS_CC);

	RETURN_STRING(envelope->content_encoding, 1);
}
/* }}} */

/* {{{ proto AMQPEnvelope::getType()
check amqp envelope */
PHP_METHOD(amqp_envelope_class, getType)
{
	zval *id;
	amqp_envelope_object *envelope;

	/* Try to pull amqp object out of method params */
	if (zend_parse_method_parameters(ZEND_NUM_ARGS() TSRMLS_CC, getThis(), "O", &id, amqp_envelope_class_entry) == FAILURE) {
		return;
	}

	/* Get the envelope object out of the store */
	envelope = (amqp_envelope_object *)zend_object_store_get_object(id TSRMLS_CC);

	RETURN_STRING(envelope->type, 1);
}
/* }}} */

/* {{{ proto AMQPEnvelope::getTimestamp()
check amqp envelope */
PHP_METHOD(amqp_envelope_class, getTimestamp)
{
	zval *id;
	amqp_envelope_object *envelope;

	/* Try to pull amqp object out of method params */
	if (zend_parse_method_parameters(ZEND_NUM_ARGS() TSRMLS_CC, getThis(), "O", &id, amqp_envelope_class_entry) == FAILURE) {
		return;
	}

	/* Get the envelope object out of the store */
	envelope = (amqp_envelope_object *)zend_object_store_get_object(id TSRMLS_CC);

	RETURN_LONG(envelope->timestamp);
}
/* }}} */

/* {{{ proto AMQPEnvelope::getPriority()
check amqp envelope */
PHP_METHOD(amqp_envelope_class, getPriority)
{
	zval *id;
	amqp_envelope_object *envelope;

	/* Try to pull amqp object out of method params */
	if (zend_parse_method_parameters(ZEND_NUM_ARGS() TSRMLS_CC, getThis(), "O", &id, amqp_envelope_class_entry) == FAILURE) {
		return;
	}

	/* Get the envelope object out of the store */
	envelope = (amqp_envelope_object *)zend_object_store_get_object(id TSRMLS_CC);

	RETURN_LONG(envelope->priority);
}
/* }}} */

/* {{{ proto AMQPEnvelope::getExpiration()
check amqp envelope */
PHP_METHOD(amqp_envelope_class, getExpiration)
{
	zval *id;
	amqp_envelope_object *envelope;

	/* Try to pull amqp object out of method params */
	if (zend_parse_method_parameters(ZEND_NUM_ARGS() TSRMLS_CC, getThis(), "O", &id, amqp_envelope_class_entry) == FAILURE) {
		return;
	}

	/* Get the envelope object out of the store */
	envelope = (amqp_envelope_object *)zend_object_store_get_object(id TSRMLS_CC);

	if (envelope->expiration == 0) {
		RETURN_FALSE;
	}

	RETURN_STRING(envelope->expiration, 1);
}
/* }}} */

/* {{{ proto AMQPEnvelope::getUserId()
check amqp envelope */
PHP_METHOD(amqp_envelope_class, getUserId)
{
	zval *id;
	amqp_envelope_object *envelope;

	/* Try to pull amqp object out of method params */
	if (zend_parse_method_parameters(ZEND_NUM_ARGS() TSRMLS_CC, getThis(), "O", &id, amqp_envelope_class_entry) == FAILURE) {
		return;
	}

	/* Get the envelope object out of the store */
	envelope = (amqp_envelope_object *)zend_object_store_get_object(id TSRMLS_CC);

	RETURN_STRING(envelope->user_id, 1);
}
/* }}} */

/* {{{ proto AMQPEnvelope::getAppId()
check amqp envelope */
PHP_METHOD(amqp_envelope_class, getAppId)
{
	zval *id;
	amqp_envelope_object *envelope;

	/* Try to pull amqp object out of method params */
	if (zend_parse_method_parameters(ZEND_NUM_ARGS() TSRMLS_CC, getThis(), "O", &id, amqp_envelope_class_entry) == FAILURE) {
		return;
	}

	/* Get the envelope object out of the store */
	envelope = (amqp_envelope_object *)zend_object_store_get_object(id TSRMLS_CC);

	RETURN_STRING(envelope->app_id, 1);
}
/* }}} */

/* {{{ proto AMQPEnvelope::getMessageId()
check amqp envelope */
PHP_METHOD(amqp_envelope_class, getMessageId)
{
	zval *id;
	amqp_envelope_object *envelope;

	/* Try to pull amqp object out of method params */
	if (zend_parse_method_parameters(ZEND_NUM_ARGS() TSRMLS_CC, getThis(), "O", &id, amqp_envelope_class_entry) == FAILURE) {
		return;
	}

	/* Get the envelope object out of the store */
	envelope = (amqp_envelope_object *)zend_object_store_get_object(id TSRMLS_CC);

	RETURN_STRING(envelope->message_id, 1);
}
/* }}} */

/* {{{ proto AMQPEnvelope::getReplyTo()
check amqp envelope */
PHP_METHOD(amqp_envelope_class, getReplyTo)
{
	zval *id;
	amqp_envelope_object *envelope;

	/* Try to pull amqp object out of method params */
	if (zend_parse_method_parameters(ZEND_NUM_ARGS() TSRMLS_CC, getThis(), "O", &id, amqp_envelope_class_entry) == FAILURE) {
		return;
	}

	/* Get the envelope object out of the store */
	envelope = (amqp_envelope_object *)zend_object_store_get_object(id TSRMLS_CC);
	
	RETURN_STRING(envelope->reply_to, 1);
}
/* }}} */

/* {{{ proto AMQPEnvelope::getCorrelationId()
check amqp envelope */
PHP_METHOD(amqp_envelope_class, getCorrelationId)
{
	zval *id;
	amqp_envelope_object *envelope;

	/* Try to pull amqp object out of method params */
	if (zend_parse_method_parameters(ZEND_NUM_ARGS() TSRMLS_CC, getThis(), "O", &id, amqp_envelope_class_entry) == FAILURE) {
		return;
	}

	/* Get the envelope object out of the store */
	envelope = (amqp_envelope_object *)zend_object_store_get_object(id TSRMLS_CC);

	RETURN_STRING(envelope->correlation_id, 1);
}
/* }}} */

/* {{{ proto AMQPEnvelope::getHeader(string headerName)
check amqp envelope */
PHP_METHOD(amqp_envelope_class, getHeader)
{
	zval *id;
	zval **tmp;
	amqp_envelope_object *envelope;
	char *key;
	int key_len;

	/* Try to pull amqp object out of method params */
	if (zend_parse_method_parameters(ZEND_NUM_ARGS() TSRMLS_CC, getThis(), "Os", &id, amqp_envelope_class_entry, &key, &key_len) == FAILURE) {
		return;
	}

	/* Get the envelope object out of the store */
	envelope = (amqp_envelope_object *)zend_object_store_get_object(id TSRMLS_CC);

	/* Look for the hash key */
	if (zend_hash_find(HASH_OF(envelope->headers), key, key_len + 1, (void **)&tmp) == FAILURE) {
		RETURN_FALSE;
	}
	
	*return_value = **tmp;
	zval_copy_ctor(return_value);
	INIT_PZVAL(return_value);
	
}
/* }}} */


/* {{{ proto AMQPEnvelope::getHeaders()
check amqp envelope */
PHP_METHOD(amqp_envelope_class, getHeaders)
{
	zval *id;
	amqp_envelope_object *envelope;

	/* Try to pull amqp object out of method params */
	if (zend_parse_method_parameters(ZEND_NUM_ARGS() TSRMLS_CC, getThis(), "O", &id, amqp_envelope_class_entry) == FAILURE) {
		return;
	}

	/* Get the envelope object out of the store */
	envelope = (amqp_envelope_object *)zend_object_store_get_object(id TSRMLS_CC);

	*return_value = *envelope->headers;
	zval_copy_ctor(return_value);

	/* Increment the ref count */
	Z_ADDREF_P(envelope->headers);
}
/* }}} */


/*
*Local variables:
*tab-width: 4
*c-basic-offset: 4
*End:
*vim600: noet sw=4 ts=4 fdm=marker
*vim<600: noet sw=4 ts=4
*/
