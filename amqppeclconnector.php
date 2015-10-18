<?php

require_once('amqp.php');

/**
 * Driver for a fast C/librabbitmq implementation of AMQP from PECL
 * @link http://pecl.php.net/package/amqp
 * @package celery-php
 */
class PECLAMQPConnector extends AbstractAMQPConnector
{
	/**
	 * Return AMQPConnection object passed to all other calls
	 * @param array $details Array of connection details
	 * @return AMQPConnection
	 */
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

	/**
	 * Initialize connection on a given connection object
	 * @return NULL
	 */
	function Connect($connection)
	{
		$connection->connect();
		$connection->channel = new AMQPChannel($connection);
	}

	/**
	 * Post a task to exchange specified in $details
	 * @param AMQPConnection $connection Connection object
	 * @param array $details Array of connection details
	 * @param string $task JSON-encoded task
	 * @param array $params AMQP message parameters
	 */
	function PostToExchange($connection, $details, $task, $params)
	{
		$ch = $connection->channel;
		$xchg = new AMQPExchange($ch);
		$xchg->setName($details['exchange']);

		$success = $xchg->publish($task, $details['binding'], 0, $params);

		return $success;
	}

	/**
	 * Return result of task execution for $task_id
	 * @param AMQPConnection $connection Connection object
	 * @param string $task_id Celery task identifier
	 * @param boolean $removeMessageFromQueue whether to remove message from queue
	 * @return array array('body' => JSON-encoded message body, 'complete_result' => AMQPEnvelope object)
	 * 			or false if result not ready yet
	 */
	function GetMessageBody($connection, $task_id, $removeMessageFromQueue = true)
	{
		$this->Connect($connection);
		$ch = $connection->channel;
		$q = new AMQPQueue($ch);
		$q->setName($task_id);
		$q->setFlags(AMQP_AUTODELETE | AMQP_DURABLE);
		$q->declareQueue();
		try
		{
			$q->bind('celeryresults', $task_id);
		}
		catch(AMQPQueueException $e)
		{
			if ($removeMessageFromQueue) {
				$q->delete();
			}
			$connection->disconnect();
			return false;
		}

		$message = $q->get(AMQP_AUTOACK);

		if(!$message) 
		{
			if ($removeMessageFromQueue) {
				$q->delete();
			}
			$connection->disconnect();
			return false;
		}

		if($message->getContentType() != 'application/json')
		{
			if ($removeMessageFromQueue) {
				$q->delete();
			}
			$connection->disconnect();

			throw new CeleryException('Response was not encoded using JSON - found ' . 
				$message->getContentType(). 
				' - check your CELERY_RESULT_SERIALIZER setting!');
		}

		if ($removeMessageFromQueue) {
			$q->delete();
		}
		$connection->disconnect();

		return array(
			'complete_result' => $message,
			'body' => $message->getBody(),
		);
	}
}
