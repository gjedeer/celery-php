<?php

require_once('amqp.php');

use PhpAmqpLib\Connection\AMQPSSLConnection;
use PhpAmqpLib\Message\AMQPMessage;

/**
 * Driver for pure PHP implementation of AMQP protocol
 * @link https://github.com/videlalvaro/php-amqplib
 * @package celery-php
 */
class AMQPLibConnectorSsl extends AMQPLibConnector
{
	function GetConnectionObject($details)
	{
		return new AMQPSSLConnection(
			$details['host'],
			$details['port'],
			$details['login'],
			$details['password'],
			$details['vhost'],
			$details['ssl_options']
		);
	}
}
