<?php

namespace Celery;

/**
 * Abstraction for AMQP client libraries
 * Abstract base class
 * @package celery-php
 */
abstract class AbstractAMQPConnector
{
    /**
     * Return a concrete AMQP abstraction object. Factory method.
     * @param string $name Name of desired concrete object: 'pecl', 'php-amqplib' or false: autodetect
     * @return AbstractAMQPConnector concrete object implementing AbstractAMQPConnector interface
     */
    public static function GetConcrete($name = false)
    {
        if ($name === false) {
            $name = self::GetBestInstalledExtensionName();
        }

        return self::GetConcreteByName($name);
    }

    /**
     * Return a concrete AMQP abstraction object given by the name
     * @param string $name Name of desired concrete object: 'pecl', 'php-amqplib'
     * @return AbstractAMQPConnector concrete object implementing AbstractAMQPConnector interface
     */
    public static function GetConcreteByName($name)
    {
        if ($name == 'pecl') {
            return new PECLAMQPConnector();
        } elseif ($name == 'php-amqplib') {
            return new AMQPLibConnector();
        } elseif ($name == 'php-amqplib-ssl') {
            return new AMQPLibConnectorSsl();
        } elseif ($name == 'redis') {
            return new RedisConnector();
        } else {
            throw new \Exception('Unknown extension name ' . $name);
        }
    }

    /**
     * Return name of best available AMQP connector library
     * @return string Name of available library or 'unknown'
     */
    public static function GetBestInstalledExtensionName($ssl = false)
    {
        if ($ssl === true) { //pecl doesn't support ssl
            return 'php-amqplib-ssl';
        } elseif (class_exists('\AMQPConnection') && extension_loaded('amqp')) {
            return 'pecl';
        } elseif (class_exists('\PhpAmqpLib\Connection\AMQPConnection')) {
            return 'php-amqplib';
        } else {
            return 'unknown';
        }
    }

    /**
     * Return backend-specific connection object passed to all other calls
     * @param array $details Array of connection details
     * @return object
     */
    abstract public function GetConnectionObject($details); // details = array

    /**
     * Initialize connection on a given connection object
     * @return NULL
     */
    abstract public function Connect($connection);

    /**
     * Post a task to exchange specified in $details
     * @param AMQPConnection $connection Connection object
     * @param array $details Array of connection details
     * @param string $body JSON-encoded task body
     * @param array $properties AMQP message properties
     * @param array $headers Celery task headers
     * @return bool true if posted successfuly
     */
    abstract public function PostToExchange($connection, $details, $body, $properties, $headers);

    /**
     * Return result of task execution for $task_id
     * @param object $connection Backend-specific connection object returned by GetConnectionObject()
     * @param string $task_id Celery task identifier
     * @param boolean $removeMessageFromQueue whether to remove message from queue
     * @return array array('body' => JSON-encoded message body, 'complete_result' => library-specific message object)
     * 			or false if result not ready yet
     */
    abstract public function GetMessageBody($connection, $task_id, $removeMessageFromQueue);
}
