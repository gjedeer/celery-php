<?php

/* TODO documentation is completely missing */

/* Include Composer installed packages if available */
@include_once('vendor/autoload.php');

/* Include namespaced code only if PhpAmqpLib available */
if(class_exists('PhpAmqpLib\Connection\AMQPConnection'))
{
	require_once('amqplibconnector.php');
	require_once('amqplibconnectorssl.php');

}

require_once('amqppeclconnector.php');

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
	static function GetConcrete($name = false)
	{
		if($name === false)
		{
			$name = self::GetBestInstalledExtensionName();
		}

		return self::GetConcreteByName($name);
	}

	/**
	 * Return a concrete AMQP abstraction object given by the name
	 * @param string $name Name of desired concrete object: 'pecl', 'php-amqplib'
	 * @return AbstractAMQPConnector concrete object implementing AbstractAMQPConnector interface
	 */
	static function GetConcreteByName($name)
	{
		if($name == 'pecl')
		{
			return new PECLAMQPConnector();
		}
		elseif($name == 'php-amqplib')
		{
			return new AMQPLibConnector();
		}
		elseif($name == 'php-amqplib-ssl')
		{
			return new AMQPLibConnectorSsl();
		}
		else
		{
			throw new Exception('Unknown extension name ' . $name);
		}
	}

	/**
	 * Return name of best available AMQP connector library
	 * @return string Name of available library or 'unknown'
	 */
	static function GetBestInstalledExtensionName($ssl = false)
	{
		if($ssl === true) //pecl doesn't support ssl
		{
			return 'php-amqplib-ssl';
		}
		elseif(class_exists('AMQPConnection') && extension_loaded('amqp'))
		{
			return 'pecl';
		}
		elseif(class_exists('PhpAmqpLib\Connection\AMQPConnection'))
		{
			return 'php-amqplib';
		}
		else
		{
			return 'unknown';
		}
	}

	/**
	 * Return backend-specific connection object passed to all other calls
	 * @param array $details Array of connection details
	 * @return object
	 */
	abstract function GetConnectionObject($details); // details = array
	
	/**
	 * Initialize connection on a given connection object
	 * @return NULL
	 */
	abstract function Connect($connection);

	/**
	 * Post a task to exchange specified in $details
	 * @param AMQPConnection $connection Connection object
	 * @param array $details Array of connection details
	 * @param string $task JSON-encoded task
	 * @param array $params AMQP message parameters
	 * @return bool true if posted successfuly
	 */
	abstract function PostToExchange($connection, $details, $task, $params);

	/**
	 * Return result of task execution for $task_id
	 * @param object $connection Backend-specific connection object returned by GetConnectionObject()
	 * @param string $task_id Celery task identifier
	 * @return array array('body' => JSON-encoded message body, 'complete_result' => library-specific message object)
	 * 			or false if result not ready yet
	 */
	abstract function GetMessageBody($connection, $task_id);
}


?>
