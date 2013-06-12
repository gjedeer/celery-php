<?php

require_once('amqp.php');

/**
 * Driver for a fast C/librabbitmq implementation of AMQP from PECL
 * @link http://pecl.php.net/package/amqp
 * @package celery-php
 */
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
