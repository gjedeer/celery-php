<?php

class AMQPCapabilities
{
	protected $loader;

	public function __construct() 
	{
		// Try to load Composer's loader

		// Load as module within external application
		$this->loader = @include(dirname(__FILE__) . "/../../autoload.php");
		if($this->loader == null) 
		{
			// Load with local composer for testing
			$this->loader = @include('vendor/autoload.php');
		}

		// If there is now loader, fail.
		if($this->loader == null) 
		{
			throw new CeleryException("Composer not installed");
		}
	}

	public function TestLib($library) 
	{
		$hasLib = $this->loader->findFile($library);
		return ($hasLib !== false);
	}

	public function TestAndLoadPhpAmqpLib() 
	{
		$hasLib = $this->TestLib('PhpAmqpLib\Connection\AMQPStreamConnection');
		if($hasLib) 
		{
			require_once('amqplibconnector.php');
			require_once('amqplibconnectorssl.php');
		}
		return $hasLib;
	}

	public function TestAndLoadPredis() 
	{
		$hasLib = $this->TestLib('Predis\Autoloader');
		if($hasLib) 
		{
			/* Include only if predis available */
			require_once('redisconnector.php');
		}
		return $hasLib;
	}

	public function TestAndLoadPECL() 
	{
		$hasLib = class_exists('AMQPConnection') && extension_loaded('amqp');
		if($hasLib) 
		{
			require_once('amqppeclconnector.php');
		}
		return $hasLib;
	}
}

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
		$caps = new AMQPCapabilities();

		switch($name) {
			case 'pecl':
				if($caps->TestAndLoadPECL() === true) 
				{
					return new PECLAMQPConnector();
				}
				break;

			case 'php-amqplib':
			case 'php-amqplib-ssl':
				if($caps->TestAndLoadPhpAmqpLib() === true) 
				{
					if($name === 'php-amqplib-ssl') 
					{
						return new AMQPLibConnectorSsl();
					} 
					else 
					{
						return new AMQPLibConnector();
					}
				}
			break;

			case 'redis':
				if($caps->TestAndLoadPredis() === true) 
				{
					return new RedisConnector();
				}
			break;

			default:
			throw new CeleryException('Unknown extension name ' . $name);
		}
		throw new CeleryException('AMQP extension ' . $name . ' is not installed properly using Composer');
	}

	/**
	 * Return name of best available AMQP connector library
	 * @return string Name of available library or 'unknown'
	 */
	static function GetBestInstalledExtensionName($ssl = false)
	{
		$caps = new AMQPCapabilities();
		$hasPhpAmqpLib = $caps->TestAndLoadPhpAmqpLib();
		$hasPECL = $caps->TestAndLoadPECL();

		if($ssl === true && $hasPhpAmqpLib === true) //pecl doesn't support ssl
		{
			return 'php-amqplib-ssl';
		}
		elseif($hasPECL === true)
		{
			return 'pecl';
		}
		elseif($hasPhpAmqpLib === true)
		{
			return 'php-amqplib';
		}

		throw new CeleryException('You must install at least one AMQP extension using Composer');
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
	 * @param boolean $removeMessageFromQueue whether to remove message from queue
	 * @return array array('body' => JSON-encoded message body, 'complete_result' => library-specific message object)
	 * 			or false if result not ready yet
	 */
	abstract function GetMessageBody($connection, $task_id, $removeMessageFromQueue);
}


?>
