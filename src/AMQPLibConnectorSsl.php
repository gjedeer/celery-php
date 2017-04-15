<?php

namespace Celery;

/**
 * Driver for pure PHP implementation of AMQP protocol
 * @link https://github.com/php-amqplib/php-amqplib
 * @package celery-php
 */
class AMQPLibConnectorSsl extends AMQPLibConnector
{
    public function GetConnectionObject($details)
    {
        return new \PhpAmqpLib\Connection\AMQPSSLConnection(
            $details['host'],
            $details['port'],
            $details['login'],
            $details['password'],
            $details['vhost'],
            $details['ssl_options']
        );
    }
}
