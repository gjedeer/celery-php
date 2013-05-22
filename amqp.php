<?php

/* TODO documentation is completely missing */

/* Include Composer installed packages if available */
@include_once('vendor/autoload.php');

/* Include namespaced code only if PhpAmqpLib available */
if(class_exists('PhpAmqpLib\Connection\AMQPConnection'))
{
	require_once('amqplibconnector.php');
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
	function GetConcrete($name = false)
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
		else
		{
			throw new Exception('Unknown extension name ' . $name);
		}
	}

	/**
	 * Return name of best available AMQP connector library
	 * @return string Name of available library or 'unknown'
	 */
	static function GetBestInstalledExtensionName()
	{
		if(class_exists('AMQPConnection') && extension_loaded('amqp'))
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
	 */
	abstract function PostToExchange($connection, $details, $task, $params);

	/**
	 * Return result of task execution for $task_id
	 * @param AMQPConnection $connection Connection object
	 * @param string $task_id Celery task identifier
	 * @return array array('body' => JSON-encoded message body, 'complete_result' => library-specific message object)
	 * 			or false if result not ready yet
	 */
	abstract function GetMessageBody($connection, $task_id);
}

class PECLAMQPConnector extends AbstractAMQPConnector
{
	function GetConnectionObject($details)
	{
		$connection = new AMQPConnection();
		$connection->setHost($details['host']);
		$connection->setLogin($details['login']);
		$connection->setPassword($details['password']);
		$connection->setVhost($details['vhost']);
		$connection->setPort($details['port']);

		return $connection;
	}

	function Connect($connection)
	{
		$connection->connect();
	}

	function PostToExchange($connection, $details, $task, $params)
	{
		$ch = new AMQPChannel($connection);
		$xchg = new AMQPExchange($ch);
		$xchg->setName($details['exchange']);

		$success = $xchg->publish($task, $details['exchange'], 0, $params);
		$connection->disconnect();

		return $success;
	}

	function GetMessageBody($connection, $task_id)
	{
		$this->Connect($connection);
		$ch = new AMQPChannel($connection);
		$q = new AMQPQueue($ch);
		$q->setName($task_id);
		$q->setFlags(AMQP_AUTODELETE);
#		$q->setArgument('x-expires', 86400000);
		$q->declare();
		try
		{
			$q->bind('celeryresults', $task_id);
		}
		catch(AMQPQueueException $e)
		{
   			$q->delete();
			$connection->disconnect();
			return false;
		}

		$message = $q->get(AMQP_AUTOACK);

		if(!$message) 
		{
   			$q->delete();
			$connection->disconnect();
			return false;
		}

		if($message->getContentType() != 'application/json')
		{
			$q->delete();
			$connection->disconnect();

			throw new CeleryException('Response was not encoded using JSON - found ' . 
				$message->getContentType(). 
				' - check your CELERY_RESULT_SERIALIZER setting!');
		}

		$q->delete();
		$connection->disconnect();

		return array(
			'complete_result' => $message,
			'body' => $message->getBody(),
		);
	}
}

echo AbstractAMQPConnector::GetBestInstalledExtensionName();

?>
